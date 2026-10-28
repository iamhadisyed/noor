import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import 'screens/alerts.dart';
import 'screens/home.dart';
import 'screens/lessons.dart';
import 'screens/more.dart';
import 'screens/reports.dart';

class ParentApp extends StatelessWidget {
  const ParentApp({super.key, required this.session});
  final KindoSession session;

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider<KindoSession>.value(
      value: session,
      child: Consumer<KindoSession>(
        builder: (context, s, _) => MaterialApp(
          title: 'Kindo Parent',
          debugShowCheckedModeBanner: false,
          theme: KindoTheme.build(),
          locale: Locale(s.lang),
          supportedLocales: const [Locale('en'), Locale('ur')],
          localizationsDelegates: const [GlobalMaterialLocalizations.delegate, GlobalWidgetsLocalizations.delegate, GlobalCupertinoLocalizations.delegate],
          home: !s.ready
              ? const Scaffold(body: Loading())
              : s.signedIn
                  ? const Shell()
                  : SignInScreen(appName: 'Kindo', tagline: s.lang == 'ur' ? 'والدین کی ایپ' : 'For parents and carers'),
        ),
      ),
    );
  }
}

/// The signed-in app: five tabs.
class Shell extends StatefulWidget {
  const Shell({super.key});

  @override
  State<Shell> createState() => _ShellState();
}

class _ShellState extends State<Shell> with WidgetsBindingObserver {
  int _tab = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    final s = context.read<KindoSession>();
    s.startAutoSync();
    s.startPush();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final s = context.read<KindoSession>();
    if (state == AppLifecycleState.resumed) {
      s.refresh();
      s.startAutoSync();
    } else if (state == AppLifecycleState.paused) {
      s.stopAutoSync();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final unanswered = s.currentChild == null ? 0 : s.sync.needsFor(s.currentChild!.id).where((n) => n.status != 'on_the_way').length;
    final pages = <Widget>[const HomeScreen(), const LessonsScreen(), const AlertsScreen(), const ReportsScreen(), const MoreScreen()];

    return LangDirection(
      child: Scaffold(
        body: SafeArea(
          child: Column(children: [
            if (!s.online || s.outbox.pending.isNotEmpty) _OfflineBar(pending: s.outbox.pending.length, online: s.online),
            Expanded(child: IndexedStack(index: _tab, children: pages)),
          ]),
        ),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _tab,
          onDestinationSelected: (i) => setState(() => _tab = i),
          destinations: [
            NavigationDestination(icon: const Icon(Icons.home_outlined, color: Colors.white), selectedIcon: const Icon(Icons.home_rounded, color: KindoColors.ink), label: context.tr('Home', 'ہوم')),
            NavigationDestination(icon: const Icon(Icons.menu_book_outlined, color: Colors.white), selectedIcon: const Icon(Icons.menu_book_rounded, color: KindoColors.ink), label: context.tr('Lessons', 'اسباق')),
            NavigationDestination(
              icon: Badge(isLabelVisible: unanswered > 0, label: Text('$unanswered'), child: const Icon(Icons.notifications_none_rounded, color: Colors.white)),
              selectedIcon: const Icon(Icons.notifications_rounded, color: KindoColors.ink),
              label: context.tr('Alerts', 'الرٹس'),
            ),
            NavigationDestination(icon: const Icon(Icons.insights_outlined, color: Colors.white), selectedIcon: const Icon(Icons.insights_rounded, color: KindoColors.ink), label: context.tr('Reports', 'رپورٹس')),
            NavigationDestination(icon: const Icon(Icons.menu_rounded, color: Colors.white), selectedIcon: const Icon(Icons.menu_rounded, color: KindoColors.ink), label: context.tr('More', 'مزید')),
          ],
        ),
      ),
    );
  }
}

class _OfflineBar extends StatelessWidget {
  const _OfflineBar({required this.pending, required this.online});
  final int pending;
  final bool online;

  @override
  Widget build(BuildContext context) => Material(
        color: KindoColors.butter,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Row(children: [
            Icon(online ? Icons.sync_rounded : Icons.cloud_off_rounded, size: 18),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                online ? context.tr('Sending $pending change(s)...', 'تبدیلیاں بھیجی جا رہی ہیں...') : context.tr('Offline. Your changes are saved and will be sent.', 'آف لائن۔ آپ کی تبدیلیاں محفوظ ہیں، بعد میں بھیجی جائیں گی۔'),
                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
              ),
            ),
          ]),
        ),
      );
}
