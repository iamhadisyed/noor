import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

/// What a push from the server can ask the app to do.
class PushMessage {
  const PushMessage(this.type, this.data);

  /// `need_alert` (a child needs something: parent app) or `sync` (something changed: both apps).
  final String type;
  final Map<String, String> data;
}

/// Receives pushes and tells the app. The server sends two kinds (see docs/SYNC.md and APP_HANDOFF.md section 5).
abstract class PushService {
  Stream<PushMessage> get messages;

  /// Ask permission, get the device token and give it to [onToken] (to register with the server). Safe to call when Firebase is not set up.
  Future<void> start(Future<void> Function(String token, String deviceType) onToken);
  Future<void> stop();
}

/// Used when Firebase is not set up (development, tests, web without a Firebase project): nothing arrives, nothing breaks.
class NoPush implements PushService {
  const NoPush();

  @override
  Stream<PushMessage> get messages => const Stream.empty();

  @override
  Future<void> start(Future<void> Function(String token, String deviceType) onToken) async {}

  @override
  Future<void> stop() async {}
}

/// Firebase Cloud Messaging. Needs the Firebase config files from the owner's Firebase project
/// (`google-services.json` for Android, `GoogleService-Info.plist` for iOS); without them it quietly does nothing.
class FcmPushService implements PushService {
  final _out = StreamController<PushMessage>.broadcast();
  final List<StreamSubscription<dynamic>> _subs = [];

  @override
  Stream<PushMessage> get messages => _out.stream;

  @override
  Future<void> start(Future<void> Function(String token, String deviceType) onToken) async {
    try {
      if (Firebase.apps.isEmpty) await Firebase.initializeApp();
      final fm = FirebaseMessaging.instance;
      await fm.requestPermission(alert: true, badge: true, sound: true, criticalAlert: true);
      // getToken still returns the FCM registration token, which is what the server sends to (register() gives an installation id instead)
      // ignore: deprecated_member_use
      final token = await fm.getToken();
      if (token != null) await onToken(token, defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android');
      _subs
        // ignore: deprecated_member_use
        ..add(fm.onTokenRefresh.listen((t) => onToken(t, defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android')))
        ..add(FirebaseMessaging.onMessage.listen(_forward))
        ..add(FirebaseMessaging.onMessageOpenedApp.listen(_forward));
    } catch (e) {
      debugPrint('Push is not set up: $e');   // no Firebase project configured: carry on without push
    }
  }

  void _forward(RemoteMessage m) {
    final data = m.data.map((k, v) => MapEntry(k, '$v'));
    _out.add(PushMessage(data['type'] ?? 'sync', data));
  }

  @override
  Future<void> stop() async {
    for (final s in _subs) {
      await s.cancel();
    }
    _subs.clear();
  }
}
