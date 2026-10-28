import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets/celebrate.dart';
import 'home.dart';

/// Calm activities: breathing with a bubble, looking and touching, squeezing and letting go. No score, no minutes spent, never locked.
class CalmHub extends StatefulWidget {
  const CalmHub({super.key});

  @override
  State<CalmHub> createState() => _CalmHubState();
}

class _CalmHubState extends State<CalmHub> {
  static const _key = 'kindo.activities';
  List<CalmActivity>? _items;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final s = context.read<KindoSession>();
    // use the saved copy first so this opens at once, even with no connection
    final saved = await s.store.read(_key);
    if (saved != null && mounted) setState(() => _items = _parse(jsonDecode(saved)));
    try {
      final fresh = await s.client.get('/activities');
      await s.store.write(_key, jsonEncode(fresh));
      if (mounted) setState(() => _items = _parse(fresh));
    } on ApiException {
      // keep the saved copy
    }
  }

  List<CalmActivity> _parse(Object? j) => asJsonList(asJson(j)['activities']).map(CalmActivity.fromJson).toList();

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final ur = s.lang == 'ur';
    final items = _items;
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Calm', 'سکون'))),
      body: items == null
          ? const Loading()
          : ListView(padding: const EdgeInsets.all(16), children: [
              for (final a in items)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: KindoCard(
                    color: const Color(0xFFCFE8DB),
                    onTap: () => goTo(context, CalmPlayer(activity: a)),
                    child: Row(children: [
                      Icon(a.kind == 'breathing' ? Icons.air_rounded : Icons.spa_rounded, size: 44),
                      const SizedBox(width: 16),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(ur ? a.title.ur : a.title.en, style: Theme.of(context).textTheme.titleLarge), Text(ur ? a.about.ur : a.about.en, style: Theme.of(context).textTheme.bodySmall)])),
                    ]),
                  ),
                ),
            ]),
    );
  }
}

class CalmPlayer extends StatefulWidget {
  const CalmPlayer({super.key, required this.activity});
  final CalmActivity activity;

  @override
  State<CalmPlayer> createState() => _CalmPlayerState();
}

class _CalmPlayerState extends State<CalmPlayer> {
  int _step = 0;
  bool _done = false;
  Timer? _auto;
  final _started = DateTime.now();

  CalmActivity get _a => widget.activity;

  @override
  void initState() {
    super.initState();
    if (_a.kind != 'breathing') _arm();
  }

  void _arm() {
    _auto?.cancel();
    final support = context.read<KindoSession>().currentChild?.support ?? const Support();
    // a child on a "no timers" profile moves on by tapping only
    if (support.noTimers) return;
    final secs = (_a.steps[_step].seconds * (support.slow ? 1.5 : 1)).round();
    _auto = Timer(Duration(seconds: secs + 2), _next);
  }

  void _next() {
    if (!mounted) return;
    if (_step + 1 >= _a.steps.length) return _finish(DateTime.now().difference(_started).inSeconds);
    setState(() => _step++);
    _arm();
  }

  void _finish(int seconds) {
    _auto?.cancel();
    final s = context.read<KindoSession>();
    final c = s.currentChild;
    if (c != null) s.queue('POST', '/children/${c.id}/activities/${_a.activity}/done', body: {'seconds': seconds.clamp(0, 3600)}, keyPrefix: 'calm');
    setState(() => _done = true);
  }

  @override
  void dispose() {
    _auto?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final support = s.currentChild?.support ?? const Support();
    final ur = s.lang == 'ur';

    Widget body;
    if (_done) {
      body = Column(mainAxisSize: MainAxisSize.min, children: [
        Celebrate(support: const Support(praise: 'quiet'), message: context.tr('Nice and calm', 'بہت پرسکون')),
        const SizedBox(height: 24),
        FilledButton(onPressed: () => Navigator.pop(context), child: Text(context.tr('Done', 'ہو گیا'))),
      ]);
    } else if (_a.kind == 'breathing') {
      body = BreathingBubble(activity: _a, lang: s.lang, slow: support.slow, onDone: _finish);
    } else {
      final st = _a.steps[_step];
      body = Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 220,
          height: 220,
          decoration: const BoxDecoration(color: Color(0xFFCFE8DB), shape: BoxShape.circle),
          alignment: Alignment.center,
          child: Icon(st.cue == 'rest' ? Icons.self_improvement_rounded : Icons.accessibility_new_rounded, size: 110),
        ),
        const SizedBox(height: 24),
        Text(ur ? st.text.ur : st.text.en, style: Theme.of(context).textTheme.headlineMedium, textAlign: TextAlign.center),
        const SizedBox(height: 8),
        Text('${_step + 1} / ${_a.steps.length}', style: Theme.of(context).textTheme.bodySmall),
        const SizedBox(height: 20),
        FilledButton.icon(onPressed: _next, icon: const Icon(Icons.arrow_forward_rounded), label: Text(context.tr('Next', 'آگے'))),
      ]);
    }

    return Scaffold(
      appBar: AppBar(title: Text(ur ? _a.title.ur : _a.title.en)),
      body: SafeArea(child: Center(child: SingleChildScrollView(padding: const EdgeInsets.all(24), child: body))),
    );
  }
}
