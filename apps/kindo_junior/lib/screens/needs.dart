import 'dart:async';

import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import 'calm.dart';
import 'home.dart';

const _groups = ['needs', 'food', 'emotions', 'activities'];

/// The picture board. A tap says the word out loud and tells the parent's phone. It works with no connection: the alert is saved
/// and sent as soon as there is one.
class NeedsBoard extends StatefulWidget {
  const NeedsBoard({super.key});

  @override
  State<NeedsBoard> createState() => _NeedsBoardState();
}

class _NeedsBoardState extends State<NeedsBoard> {
  String _group = 'needs';
  AacCard? _sent;
  Timer? _clear;
  DateTime _last = DateTime.fromMillisecondsSinceEpoch(0);

  @override
  void dispose() {
    _clear?.cancel();
    super.dispose();
  }

  Future<void> _tap(AacCard c) async {
    final s = context.read<KindoSession>();
    final child = s.currentChild!;
    // say it first, so the child hears something at once
    if (c.audioUrl != null) {
      AudioHub.instance.play(s, c.audioUrl, version: c.audioV);
    } else {
      Speaker.instance.speak(s.lang == 'ur' && c.urduLabel.isNotEmpty ? c.urduLabel : c.englishLabel, lang: s.lang, slow: child.support.slow);
    }
    // the same card tapped again within two seconds is the same request
    if (DateTime.now().difference(_last) < const Duration(seconds: 2)) return;
    _last = DateTime.now();
    await s.queue('POST', '/child/alert', body: {'child_id': child.id, 'need_type': c.needType, if (c.custom) 'card_id': c.id}, keyPrefix: 'alert');
    if (!mounted) return;
    setState(() => _sent = c);
    _clear?.cancel();
    _clear = Timer(const Duration(seconds: 4), () => mounted ? setState(() => _sent = null) : null);
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final child = s.currentChild!;
    final mine = s.sync.aacCardsFor(child.id);
    final all = [...s.stockCards, ...mine];
    final urgent = all.where((c) => c.isEmergency).toList();
    final shown = all.where((c) => c.category == _group && !c.isEmergency).toList()..sort((a, b) => a.custom == b.custom ? 0 : (a.custom ? -1 : 1));
    final latest = s.sync.needsFor(child.id).firstOrNull;
    final coming = latest != null && latest.status == 'on_the_way' && latest.createdAt != null && DateTime.now().difference(latest.createdAt!).inMinutes < 10;

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('I need...', 'مجھے چاہیے...'))),
      body: SafeArea(
        child: Column(children: [
          if (coming)
            Container(
              width: double.infinity,
              margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: KindoColors.sage.withValues(alpha: .35), borderRadius: BorderRadius.circular(22)),
              child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [const Icon(Icons.check_circle_rounded, color: KindoColors.sageDeep), const SizedBox(width: 10), Text(context.tr('Someone is coming!', 'کوئی آ رہا ہے!'), style: Theme.of(context).textTheme.titleLarge)]),
            ),
          if (_sent != null)
            Container(
              width: double.infinity,
              margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: KindoColors.butter, borderRadius: BorderRadius.circular(22)),
              child: Text(context.tr('I told them: ${_sent!.englishLabel}', 'میں نے بتا دیا: ${_sent!.urduLabel.isEmpty ? _sent!.englishLabel : _sent!.urduLabel}'), textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleLarge),
            ),
          if (urgent.isNotEmpty)
            SizedBox(
              height: 120,
              child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 16), children: [for (final c in urgent) Padding(padding: const EdgeInsets.only(right: 12), child: SizedBox(width: 120, child: _CardTile(card: c, urgent: true, onTap: () => _tap(c))))]),
            ),
          SizedBox(
            height: 56,
            child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 16), children: [
              for (final g in _groups)
                Padding(
                  padding: const EdgeInsets.only(right: 8, top: 6, bottom: 6),
                  child: ChoiceChip(
                    label: Text(_groupLabel(context, g), style: const TextStyle(fontSize: 16)),
                    selected: _group == g,
                    onSelected: (_) => setState(() => _group = g),
                    selectedColor: KindoColors.lilac,
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                  ),
                ),
            ]),
          ),
          Expanded(
            child: LayoutBuilder(builder: (context, c) {
              final cols = (c.maxWidth / 150).floor().clamp(2, 6);
              return GridView.count(
                padding: const EdgeInsets.all(16),
                crossAxisCount: cols,
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: .9,
                children: [for (final card in shown) _CardTile(card: card, onTap: () => _tap(card))],
              );
            }),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: OutlinedButton.icon(onPressed: () => goTo(context, const CalmHub()), icon: const Icon(Icons.spa_rounded), label: Text(context.tr('I feel upset', 'میں پریشان ہوں'))),
          ),
        ]),
      ),
    );
  }

  String _groupLabel(BuildContext context, String g) => switch (g) {
        'needs' => context.tr('Needs', 'ضروریات'),
        'food' => context.tr('Food', 'کھانا'),
        'emotions' => context.tr('Feelings', 'احساسات'),
        _ => context.tr('Play', 'کھیل'),
      };
}

class _CardTile extends StatelessWidget {
  const _CardTile({required this.card, required this.onTap, this.urgent = false});
  final AacCard card;
  final VoidCallback onTap;
  final bool urgent;

  @override
  Widget build(BuildContext context) {
    final ur = context.watch<KindoSession>().lang == 'ur';
    return Semantics(
      button: true,
      label: card.englishLabel,
      child: Material(
        color: urgent ? KindoColors.warnTint : Colors.white,
        borderRadius: BorderRadius.circular(26),
        child: InkWell(
          borderRadius: BorderRadius.circular(26),
          onTap: onTap,
          child: Container(
            padding: const EdgeInsets.all(10),
            decoration: urgent ? BoxDecoration(borderRadius: BorderRadius.circular(26), border: Border.all(color: KindoColors.warn, width: 3)) : null,
            child: Column(children: [
              Expanded(child: CardPicture(card)),
              const SizedBox(height: 6),
              Text(ur && card.urduLabel.isNotEmpty ? card.urduLabel : card.englishLabel, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            ]),
          ),
        ),
      ),
    );
  }
}
