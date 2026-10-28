import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';

import 'app.dart';

/// The server address. Build with `--dart-define=KINDO_API=https://your-site/api/v1`.
const _api = String.fromEnvironment('KINDO_API', defaultValue: 'https://kindo.example/api/v1');
const _push = bool.fromEnvironment('KINDO_PUSH', defaultValue: false);

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final store = await PrefsStore.open();
  final session = KindoSession(store: store, defaultBaseUrl: _api, push: _push ? FcmPushService() : const NoPush());
  await session.init();
  runApp(JuniorApp(session: session));
}
