import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import 'screens/cool_time.dart';
import 'screens/home.dart';
import 'screens/picker.dart';

class JuniorApp extends StatefulWidget {
  const JuniorApp({super.key, required this.session});
  final KindoSession session;

  @override
  State<JuniorApp> createState() => _JuniorAppState();
}

class _JuniorAppState extends State<JuniorApp> {
  final _nav = GlobalKey<NavigatorState>();
  late final ScreenTimeGuard _guard = ScreenTimeGuard(navigator: _nav);
  KindoSession get session => widget.session;

  @override
  void initState() {
    super.initState();
    session.addListener(_changed);
    _changed();
  }

  void _changed() => _guard.attach(session);

  @override
  void dispose() {
    session.removeListener(_changed);
    _guard.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider<KindoSession>.value(
      value: session,
      child: Consumer<KindoSession>(
        builder: (context, s, _) {
          final child = s.currentChild;
          return MaterialApp(
            navigatorKey: _nav,
            title: 'Kindo Junior',
            debugShowCheckedModeBanner: false,
            // children who find bright colours hard get the softer theme
            theme: KindoTheme.build(sensory: child?.sensoryMode ?? false),
            locale: Locale(s.lang),
            supportedLocales: const [Locale('en'), Locale('ur')],
            localizationsDelegates: const [GlobalMaterialLocalizations.delegate, GlobalWidgetsLocalizations.delegate, GlobalCupertinoLocalizations.delegate],
            home: !s.ready
                ? const Scaffold(body: Loading())
                : !s.signedIn
                    ? SignInScreen(appName: 'Kindo Junior', tagline: s.lang == 'ur' ? 'پہلے والدین سائن ان کریں' : 'A parent signs in first')
                    : child == null || s.currentChildId == null && s.children.length > 1
                        ? const ChildPicker()
                        : const JuniorShell(),
          );
        },
      ),
    );
  }
}
