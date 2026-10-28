import 'dart:convert';

import '../api/api_client.dart';
import '../store/local_store.dart';

/// One write waiting to be sent: a child's alert, a practice session, a finished game.
class OutboxItem {
  OutboxItem({required this.id, required this.method, required this.path, required this.key, this.body, DateTime? createdAt, this.attempts = 0, this.error}) : createdAt = createdAt ?? DateTime.now();
  factory OutboxItem.fromJson(Map<String, dynamic> j) => OutboxItem(
        id: j['id'] as String,
        method: j['method'] as String,
        path: j['path'] as String,
        key: j['key'] as String,
        body: j['body'],
        createdAt: DateTime.tryParse('${j['created_at']}'),
        attempts: (j['attempts'] as num?)?.toInt() ?? 0,
        error: j['error'] as String?,
      );

  final String id;
  final String method;
  final String path;

  /// The `Idempotency-Key`: sending the same item twice never does the thing twice.
  final String key;
  final Object? body;
  final DateTime createdAt;
  int attempts;

  /// Set when the server refused it for good.
  String? error;

  Map<String, dynamic> toJson() => {'id': id, 'method': method, 'path': path, 'key': key, 'body': body, 'created_at': createdAt.toIso8601String(), 'attempts': attempts, 'error': error};
}

class FlushResult {
  FlushResult({required this.sent, required this.pending, required this.rejected, this.stoppedBy});
  final int sent;
  final int pending;

  /// Refused for good; the app should tell the parent.
  final List<OutboxItem> rejected;

  /// Why sending stopped early (usually no connection), if it did.
  final ApiException? stoppedBy;
}

/// Writes made while offline wait here and go out in the order they were made, each with its own `Idempotency-Key`.
/// A write the server refuses (a 4xx other than "try again later") is set aside in [rejected] and the rest carry on, so one bad
/// item never blocks the others and is never dropped silently.
class Outbox {
  Outbox(this.client, this.store, {this.storeKey = 'kindo.outbox'});

  final ApiClient client;
  final LocalStore store;
  final String storeKey;
  final List<OutboxItem> _pending = [];
  final List<OutboxItem> _rejected = [];
  bool _flushing = false;

  List<OutboxItem> get pending => List.unmodifiable(_pending);
  List<OutboxItem> get rejected => List.unmodifiable(_rejected);

  Future<void> load() async {
    final raw = await store.read(storeKey);
    if (raw == null) return;
    try {
      final j = jsonDecode(raw) as Map<String, dynamic>;
      _pending
        ..clear()
        ..addAll((j['pending'] as List? ?? []).map((e) => OutboxItem.fromJson(Map<String, dynamic>.from(e as Map))));
      _rejected
        ..clear()
        ..addAll((j['rejected'] as List? ?? []).map((e) => OutboxItem.fromJson(Map<String, dynamic>.from(e as Map))));
    } on FormatException {
      await store.remove(storeKey);
    }
  }

  Future<void> _save() => store.write(storeKey, jsonEncode({'pending': _pending.map((e) => e.toJson()).toList(), 'rejected': _rejected.map((e) => e.toJson()).toList()}));

  /// Add a write. It is saved at once, so closing the app loses nothing.
  Future<OutboxItem> add(String method, String path, {Object? body, String keyPrefix = 'app'}) async {
    final key = newIdempotencyKey(keyPrefix);
    final item = OutboxItem(id: key, method: method, path: path, key: key, body: body);
    _pending.add(item);
    await _save();

    return item;
  }

  Future<void> discardRejected(String id) async {
    _rejected.removeWhere((e) => e.id == id);
    await _save();
  }

  /// Send everything waiting, in order. Stops at the first item that fails for a reason worth retrying (no connection, server trouble).
  Future<FlushResult> flush() async {
    if (_flushing) return FlushResult(sent: 0, pending: _pending.length, rejected: rejected);
    _flushing = true;
    var sent = 0;
    ApiException? stopped;
    try {
      while (_pending.isNotEmpty) {
        final item = _pending.first;
        item.attempts++;
        try {
          switch (item.method) {
            case 'POST':
              await client.post(item.path, body: item.body, idempotencyKey: item.key);
            case 'PATCH':
              await client.patch(item.path, body: item.body, idempotencyKey: item.key);
            case 'DELETE':
              await client.delete(item.path, body: item.body);
            default:
              throw ArgumentError('Unsupported method ${item.method}');
          }
          _pending.removeAt(0);
          sent++;
          await _save();
        } on ApiException catch (e) {
          if (e.isRetryable || e.isUnauthorized) {
            stopped = e;
            await _save();
            break;
          }
          item.error = e.message;
          _pending.removeAt(0);
          _rejected.add(item);
          await _save();
        }
      }
    } finally {
      _flushing = false;
    }

    return FlushResult(sent: sent, pending: _pending.length, rejected: rejected, stoppedBy: stopped);
  }
}
