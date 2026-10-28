import 'dart:async';
import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';

import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

/// A refused or failed request, with what the server said in words a parent can read.
class ApiException implements Exception {
  ApiException(this.status, this.message, {this.errors = const {}, this.body = const {}});

  /// HTTP status, or 0 when the server could not be reached at all.
  final int status;
  final String message;

  /// Field name to messages, from a 422.
  final Map<String, List<String>> errors;
  final Map<String, dynamic> body;

  bool get isNetwork => status == 0;
  bool get isUnauthorized => status == 401;

  /// 402: a plan limit. Show [message] and the plan screen.
  bool get needsUpgrade => status == 402 || body['upgrade'] == true;

  /// 423: a game is locked. One of `no_minutes`, `switched_off`, `no_content`.
  String? get lockedReason => status == 423 ? body['reason'] as String? : null;

  /// 422 on sign-up: the parent must read and accept the terms, then send the same code again.
  bool get termsRequired => body['terms_required'] == true;

  /// A failure worth retrying later (no connection, server trouble, too many requests).
  bool get isRetryable => status == 0 || status == 408 || status == 429 || status >= 500;

  @override
  String toString() => 'ApiException($status): $message';
}

/// Talks to `/api/v1`. Sends JSON, always asks for JSON, adds the bearer token, and turns every failure into an [ApiException].
class ApiClient {
  ApiClient({
    required String baseUrl,
    http.Client? client,
    this.token,
    this.onUnauthorized,
    this.timeout = const Duration(seconds: 30),
  })  : baseUrl = baseUrl.endsWith('/') ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl,
        _client = client ?? http.Client();

  /// Everything up to and including `/api/v1`, for example `https://kindo.example/api/v1`.
  final String baseUrl;
  final http.Client _client;
  final Duration timeout;

  /// The current bearer token, or null when signed out.
  String? Function()? token;

  /// Called once when the server says the token is no longer valid, so the app can sign the parent out.
  void Function()? onUnauthorized;

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final q = <String, String>{};
    query?.forEach((k, v) {
      if (v != null) q[k] = v.toString();
    });
    final base = Uri.parse('$baseUrl${path.startsWith('/') ? path : '/$path'}');
    return q.isEmpty ? base : base.replace(queryParameters: {...base.queryParameters, ...q});
  }

  Map<String, String> _headers({bool json = true, String? idempotencyKey}) {
    final t = token?.call();
    return {
      'Accept': 'application/json',
      if (json) 'Content-Type': 'application/json',
      if (t != null && t.isNotEmpty) 'Authorization': 'Bearer $t',
      if (idempotencyKey != null) 'Idempotency-Key': idempotencyKey,
    };
  }

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) => _send('GET', path, query: query);

  Future<dynamic> post(String path, {Object? body, String? idempotencyKey}) => _send('POST', path, body: body, idempotencyKey: idempotencyKey);

  Future<dynamic> patch(String path, {Object? body, String? idempotencyKey}) => _send('PATCH', path, body: body, idempotencyKey: idempotencyKey);

  Future<dynamic> delete(String path, {Object? body}) => _send('DELETE', path, body: body);

  /// A file download (a PDF, a picture, a zip). The server answers JSON errors, which become [ApiException]s.
  Future<Uint8List> getBytes(String path, {Map<String, dynamic>? query}) async {
    final res = await _run(() => _client.get(_uri(path, query), headers: _headers(json: false)));
    if (res.statusCode >= 200 && res.statusCode < 300) return res.bodyBytes;
    throw _failure(res);
  }

  /// Fetch a signed picture or sound link (these links carry their own signature and need no token).
  Future<Uint8List> download(String absoluteUrl) async {
    final res = await _run(() => _client.get(Uri.parse(absoluteUrl)));
    if (res.statusCode >= 200 && res.statusCode < 300) return res.bodyBytes;
    throw _failure(res);
  }

  /// Send a form with files (picture, voice). [files] maps the field name to the file.
  Future<dynamic> postMultipart(String path, {Map<String, String> fields = const {}, Map<String, UploadFile> files = const {}, String? idempotencyKey}) async {
    final req = http.MultipartRequest('POST', _uri(path));
    req.headers.addAll(_headers(json: false, idempotencyKey: idempotencyKey));
    req.fields.addAll(fields);
    files.forEach((name, f) => req.files.add(http.MultipartFile.fromBytes(name, f.bytes, filename: f.filename, contentType: f.contentType == null ? null : MediaType.parse(f.contentType!))));
    final res = await _run(() async => http.Response.fromStream(await _client.send(req)));
    return _decode(res);
  }

  Future<dynamic> _send(String method, String path, {Object? body, Map<String, dynamic>? query, String? idempotencyKey}) async {
    final req = http.Request(method, _uri(path, query));
    req.headers.addAll(_headers(idempotencyKey: idempotencyKey));
    if (body != null) req.body = jsonEncode(body);
    final res = await _run(() async => http.Response.fromStream(await _client.send(req)));
    return _decode(res);
  }

  Future<http.Response> _run(Future<http.Response> Function() call) async {
    try {
      return await call().timeout(timeout);
    } on TimeoutException {
      throw ApiException(0, 'The server took too long to answer. Check your connection and try again.');
    } on http.ClientException {
      throw ApiException(0, 'Cannot reach Kindo. Check your connection and try again.');
    } on Exception catch (e) {
      // sockets and TLS errors differ by platform; all of them mean "could not connect"
      if (e is ApiException) rethrow;
      throw ApiException(0, 'Cannot reach Kindo. Check your connection and try again.');
    }
  }

  dynamic _decode(http.Response res) {
    if (res.statusCode >= 200 && res.statusCode < 300) {
      if (res.statusCode == 204 || res.body.isEmpty) return null;
      try {
        return jsonDecode(utf8.decode(res.bodyBytes));
      } on FormatException {
        throw ApiException(res.statusCode, 'The server sent something we could not read.');
      }
    }
    throw _failure(res);
  }

  ApiException _failure(http.Response res) {
    Map<String, dynamic> body = {};
    try {
      final d = jsonDecode(utf8.decode(res.bodyBytes));
      if (d is Map<String, dynamic>) body = d;
    } on FormatException {
      // not JSON (a proxy error page, for example): keep the generic message
    }
    final errors = <String, List<String>>{};
    final e = body['errors'];
    if (e is Map) {
      e.forEach((k, v) => errors['$k'] = v is List ? v.map((x) => '$x').toList() : ['$v']);
    }
    if (res.statusCode == 401) onUnauthorized?.call();
    final message = (body['message'] as String?) ?? _fallback(res.statusCode);

    return ApiException(res.statusCode, message, errors: errors, body: body);
  }

  static String _fallback(int status) {
    if (status == 401) return 'Please sign in again.';
    if (status == 403) return 'This is not available for your account.';
    if (status == 404) return 'We could not find that.';
    if (status == 429) return 'Too many tries. Please wait a little and try again.';
    if (status >= 500) return 'Something went wrong on our side. Please try again.';
    return 'Something went wrong. Please try again.';
  }

  void close() => _client.close();
}

/// A file to upload.
class UploadFile {
  UploadFile(this.bytes, this.filename, {this.contentType});
  final List<int> bytes;
  final String filename;
  final String? contentType;
}

final _rand = Random.secure();

/// A new unique key for the `Idempotency-Key` header: `<prefix>-<time>-<random>`, 8 to 80 safe characters.
String newIdempotencyKey([String prefix = 'app']) {
  final t = DateTime.now().microsecondsSinceEpoch.toRadixString(36);
  final r = List.generate(8, (_) => _rand.nextInt(36).toRadixString(36)).join();
  final p = prefix.replaceAll(RegExp(r'[^A-Za-z0-9._:-]'), '').padRight(1, 'x');

  return '${p.length > 20 ? p.substring(0, 20) : p}-$t-$r';
}
