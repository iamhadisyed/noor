import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets/gate.dart';
import 'calm.dart';
import 'discover.dart';
import 'family_game.dart';
import 'games.dart';
import 'lessons.dart';
import 'needs.dart';

void goTo(BuildContext context, Widget page) => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => LangDirection(child: page)));

class JuniorShell extends StatefulWidget {
  const JuniorShell({super.key});

  @override
  State<JuniorShell> createState() => _JuniorShellState();
}

class _JuniorShellState extends State<JuniorShell> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    final s = context.read<KindoSession>();
    s.startAutoSync(every: const Duration(seconds: 30));
    s.startPush();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final s = context.read<KindoSession>();
    if (state == AppLifecycleState.resumed) {
      s.refresh();
      s.startAutoSync(every: const Duration(seconds: 30));
    } else if (state == AppLifecycleState.paused) {
      s.stopAutoSync();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> _grownUps() async {
    if (!await parentGate(context) || !mounted) return;
    final s = context.read<KindoSession>();
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (c) => LangDirection(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text(c.tr('Grown-ups', 'بڑوں کے لیے'), style: Theme.of(c).textTheme.titleLarge),
            const SizedBox(height: 12),
            if (s.children.length > 1)
              OutlinedButton(
                onPressed: () async {
                  Navigator.pop(c);
                  await s.signOutChild();
                },
                child: Text(c.tr('Change child', 'بچہ بدلیں')),
              ),
            const SizedBox(height: 8),
            OutlinedButton(onPressed: () => s.setLang(s.lang == 'ur' ? 'en' : 'ur'), child: Text(s.lang == 'ur' ? 'English' : 'اردو')),
            const SizedBox(height: 8),
            OutlinedButton(onPressed: () { s.refresh(); Navigator.pop(c); }, child: Text(c.tr('Update now', 'ابھی اپ ڈیٹ کریں'))),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: () async {
                Navigator.pop(c);
                await s.signOut();
              },
              child: Text(c.tr('Sign out', 'سائن آؤٹ')),
            ),
          ]),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final child = s.currentChild!;
    final lessons = s.sync.lessonsFor(child.id, publishedOnly: true);
    final family = s.sync.familyFor(child.id);

    final tiles = <_Tile>[
      _Tile(context.tr('I need...', 'مجھے چاہیے...'), Icons.record_voice_over_rounded, KindoColors.butter, () => goTo(context, const NeedsBoard())),
      _Tile(context.tr('Learn', 'سیکھیں'), Icons.menu_book_rounded, KindoColors.lilac, () => goTo(context, const LessonsList()), badge: lessons.isEmpty ? null : '${lessons.length}'),
      _Tile(context.tr('Play', 'کھیلیں'), Icons.sports_esports_rounded, KindoColors.lavender, () => goTo(context, const GamesHub()), badge: child.balanceMinutes > 0 ? '${child.balanceMinutes}m' : null),
      _Tile(context.tr('Discover', 'دریافت'), Icons.rocket_launch_rounded, const Color(0xFFB7D8FF), () => goTo(context, const DiscoverHub())),
      _Tile(context.tr('Calm', 'سکون'), Icons.spa_rounded, const Color(0xFFCFE8DB), () => goTo(context, const CalmHub())),
      if (family.length >= 2) _Tile(context.tr('My family', 'میرا خاندان'), Icons.family_restroom_rounded, KindoColors.cream, () => goTo(context, const FamilyGame())),
    ];

    return LangDirection(
      child: Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(children: [
              Row(children: [
                Expanded(child: Text(context.tr('Hello, ${child.name}!', 'السلام علیکم، ${child.name}!'), style: Theme.of(context).textTheme.headlineMedium)),
                if (!s.online || s.outbox.pending.isNotEmpty) Padding(padding: const EdgeInsets.only(right: 4), child: Icon(s.online ? Icons.sync_rounded : Icons.cloud_off_rounded, color: KindoColors.gray)),
                IconButton(tooltip: context.tr('Grown-ups', 'بڑوں کے لیے'), icon: const Icon(Icons.settings_outlined), onPressed: _grownUps),
              ]),
              const SizedBox(height: 16),
              Expanded(
                child: LayoutBuilder(builder: (context, c) {
                  final cols = c.maxWidth > 700 ? 3 : 2;
                  return GridView.count(
                    crossAxisCount: cols,
                    mainAxisSpacing: 16,
                    crossAxisSpacing: 16,
                    childAspectRatio: c.maxWidth > 700 ? 1.3 : 0.95,
                    children: [for (final t in tiles) _TileView(t)],
                  );
                }),
              ),
            ]),
          ),
        ),
      ),
    );
  }
}

class _Tile {
  const _Tile(this.label, this.icon, this.color, this.onTap, {this.badge});
  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;
  final String? badge;
}

class _TileView extends StatelessWidget {
  const _TileView(this.t);
  final _Tile t;

  @override
  Widget build(BuildContext context) => Semantics(
        button: true,
        label: t.label,
        child: Material(
          color: t.color,
          borderRadius: BorderRadius.circular(36),
          child: InkWell(
            borderRadius: BorderRadius.circular(36),
            onTap: t.onTap,
            child: Stack(children: [
              Center(child: Column(mainAxisSize: MainAxisSize.min, children: [Icon(t.icon, size: 72, color: KindoColors.ink), const SizedBox(height: 10), Text(t.label, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontSize: 22), textAlign: TextAlign.center)])),
              if (t.badge != null) Positioned(top: 14, right: 14, child: Pill(t.badge!, color: Colors.white)),
            ]),
          ),
        ),
      );
}
