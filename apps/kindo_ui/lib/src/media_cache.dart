import 'dart:async';
import 'dart:io' show Directory, File;
import 'dart:typed_data';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:kindo_core/kindo_core.dart';
import 'package:path_provider/path_provider.dart';

/// Pictures and sounds the child needs without a connection. The server's picture and sound links are signed and expire after 15 minutes,
/// so the saved copy is keyed by the link's path plus the server's version code (`*_v`), never by the signature: a new link for the same
/// file finds the saved copy, and a replaced file (new version) is fetched again.
class MediaCache {
  MediaCache(this.client);
  final ApiClient client;

  final Map<String, Uint8List> _memory = {};
  final Map<String, Future<Uint8List?>> _inflight = {};
  Directory? _dir;
  bool _dirTried = false;

  static String keyFor(String url, String? version) {
    final u = Uri.parse(url);
    final raw = '${u.path}|${version ?? ''}';
    // a file name that is safe on every platform
    return raw.replaceAll(RegExp(r'[^A-Za-z0-9]+'), '_');
  }

  Future<Directory?> _folder() async {
    if (kIsWeb) return null;
    if (_dirTried) return _dir;
    _dirTried = true;
    try {
      final base = await getApplicationSupportDirectory();
      _dir = await Directory('${base.path}/kindo_media').create(recursive: true);
    } catch (_) {
      _dir = null;   // no disk (a test, an odd platform): the memory copy still works
    }
    return _dir;
  }

  /// Is this file already saved?
  bool has(String url, String? version) => _memory.containsKey(keyFor(url, version));

  /// The bytes of a picture or sound, from the saved copy if there is one, else downloaded (and saved). Null if it cannot be had.
  Future<Uint8List?> bytes(String url, {String? version}) {
    final key = keyFor(url, version);
    final hit = _memory[key];
    if (hit != null) return Future.value(hit);

    return _inflight[key] ??= _load(url, key).whenComplete(() => _inflight.remove(key));
  }

  Future<Uint8List?> _load(String url, String key) async {
    final dir = await _folder();
    if (dir != null) {
      final f = File('${dir.path}/$key');
      if (await f.exists()) {
        final b = await f.readAsBytes();
        _memory[key] = b;
        return b;
      }
    }
    try {
      final b = await client.download(url);
      _memory[key] = b;
      if (dir != null) await File('${dir.path}/$key').writeAsBytes(b, flush: true);
      return b;
    } on ApiException {
      return null;
    }
  }

  /// Download everything a child will need (after a sync), quietly, one after the other.
  Future<void> prefetch(Iterable<({String url, String? version})> items) async {
    for (final i in items) {
      await bytes(i.url, version: i.version);
    }
  }

  /// Forget everything saved (sign-out).
  Future<void> clear() async {
    _memory.clear();
    final dir = await _folder();
    if (dir != null && await dir.exists()) {
      await for (final e in dir.list()) {
        try {
          await e.delete();
        } catch (_) {}
      }
    }
  }
}
