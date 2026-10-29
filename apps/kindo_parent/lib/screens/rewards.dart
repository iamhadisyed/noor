import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets.dart';

/// Play time the child has earned by practising, which games are on, and the rules for earning more.
class RewardsScreen extends StatelessWidget {
  const RewardsScreen({super.key, required this.child});
  final Child child;

  @override
  Widget build(BuildContext context) {
    final s = context.read<KindoSession>();
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Games and play time', 'کھیل اور وقت'))),
      body: Load<Json>(
        future: () => s.api.rewards(child.id),
        builder: (context, d, reload) => _Body(child: child, data: d, reload: reload),
      ),
    );
  }
}

class _Body extends StatefulWidget {
  const _Body({required this.child, required this.data, required this.reload});
  final Child child;
  final Json data;
  final Future<void> Function() reload;

  @override
  State<_Body> createState() => _BodyState();
}

class _BodyState extends State<_Body> {
  late Json _data = widget.data;

  KindoSession get _s => context.read<KindoSession>();

  Future<void> _save(Json changes) async {
    final d = await guarded(context, () => _s.api.saveRewardSettings(widget.child.id, changes));
    if (d != null && mounted) setState(() => _data = d);
  }

  Future<void> _bonus() async {
    var minutes = 5.0;
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => AlertDialog(
          title: Text(context.tr('Give extra minutes', 'اضافی منٹ دیں')),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            Text('${minutes.round()} ${context.tr('minutes', 'منٹ')}', style: Theme.of(context).textTheme.headlineSmall),
            Slider(value: minutes, min: 1, max: 30, divisions: 29, onChanged: (v) => set(() => minutes = v)),
          ]),
          actions: [TextButton(onPressed: () => Navigator.pop(c, false), child: Text(context.tr('Cancel', 'منسوخ'))), FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(context.tr('Give', 'دیں')))],
        ),
      ),
    );
    if (ok != true || !mounted) return;
    final d = await guarded(context, () => _s.api.addBonusMinutes(widget.child.id, minutes.round()));
    if (d != null && mounted) setState(() => _data = d);
  }

  @override
  Widget build(BuildContext context) {
    final settings = asJson(_data['settings']);
    final games = asJsonList(_data['games']).map(GameInfo.fromJson).toList();
    final ur = _s.lang == 'ur';
    final enabledList = settings['enabled_games'] as List?;

    Future<void> toggle(GameInfo g, bool on) async {
      final all = games.map((x) => x.game).toList();
      final current = enabledList == null ? all : enabledList.map((e) => '$e').toList();
      final next = on ? {...current, g.game}.toList() : current.where((x) => x != g.game).toList();
      await _save({'enabled_games': next.length == all.length ? null : next});
    }

    return RefreshIndicator(
      onRefresh: widget.reload,
      child: ListView(padding: pagePadding, children: [
        KindoCard(
          color: KindoColors.butter,
          child: Row(children: [
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${_data['balance_minutes'] ?? 0}', style: Theme.of(context).textTheme.displaySmall?.copyWith(fontWeight: FontWeight.w800)),
              Text(context.tr('minutes saved up', 'منٹ جمع ہیں')),
              Text(context.tr('${_data['earned_today'] ?? 0} earned today - ${_data['streak_days'] ?? 0} day streak', 'آج ${_data['earned_today'] ?? 0} کمائے - ${_data['streak_days'] ?? 0} دن مسلسل'), style: Theme.of(context).textTheme.bodySmall),
            ])),
            FilledButton(onPressed: _bonus, child: Text(context.tr('Give minutes', 'منٹ دیں'))),
          ]),
        ),
        SwitchListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: 4),
          title: Text(context.tr('Games are on', 'کھیل چالو ہیں')),
          subtitle: Text(context.tr('Games are a reward for practice. Switch off to hide them.', 'کھیل مشق کا انعام ہیں۔ چھپانے کے لیے بند کریں۔')),
          value: settings['enabled'] == true,
          onChanged: (v) => _save({'enabled': v}),
        ),
        SectionTitle(context.tr('How minutes are earned', 'منٹ کیسے کمائے جاتے ہیں')),
        KindoCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            for (final e in asJsonList(_data['how_to_earn'])) Padding(padding: const EdgeInsets.symmetric(vertical: 3), child: Text('+${e['minutes']}  ${e['text']}')),
            const SizedBox(height: 8),
            _Slide(label: context.tr('Good score at', 'اچھا اسکور'), suffix: '%', value: (settings['threshold_pct'] as num?)?.toInt() ?? 70, min: 30, max: 100, onSave: (v) => _save({'threshold_pct': v})),
            _Slide(label: context.tr('Minutes for a good session', 'اچھے سیشن کے منٹ'), value: (settings['minutes_per_good_session'] as num?)?.toInt() ?? 3, min: 1, max: 30, onSave: (v) => _save({'minutes_per_good_session': v})),
            SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(context.tr('Reward effort too', 'کوشش پر بھی انعام')), subtitle: Text(context.tr('For finishing a session, even if some answers were wrong.', 'سیشن مکمل کرنے پر، چاہے کچھ جواب غلط ہوں۔')), value: settings['reward_for_effort'] == true, onChanged: (v) => _save({'reward_for_effort': v})),
            _Slide(label: context.tr('Most per day', 'روزانہ زیادہ سے زیادہ'), value: (settings['daily_cap_minutes'] as num?)?.toInt() ?? 30, min: 5, max: 120, onSave: (v) => _save({'daily_cap_minutes': v})),
            _Slide(label: context.tr('Longest game', 'سب سے لمبا کھیل'), value: (settings['max_session_minutes'] as num?)?.toInt() ?? 10, min: 1, max: 60, onSave: (v) => _save({'max_session_minutes': v})),
          ]),
        ),
        SectionTitle(context.tr('Games', 'کھیل')),
        KindoCard(
          padding: EdgeInsets.zero,
          child: Column(children: [
            for (final g in games)
              SwitchListTile(
                title: Text(ur ? g.title.ur : g.title.en),
                subtitle: Text('${context.tr('Ages ${g.ageLabel}', 'عمر ${g.ageLabel}')}  -  ${!g.available ? context.tr('Needs content from the Kindo team', 'کنڈو ٹیم کے مواد کی ضرورت ہے') : g.plays == 0 ? context.tr('Not played yet', 'ابھی نہیں کھیلا') : context.tr('Level ${g.level}, played ${g.plays} times', 'لیول ${g.level}، ${g.plays} بار کھیلا')}'),
                secondary: g.forTracks.length == 1 && g.forTracks.first == 'special' ? const Icon(Icons.favorite_border_rounded) : null,
                value: g.enabled,
                onChanged: g.available ? (v) => toggle(g, v) : null,
              ),
          ]),
        ),
        SectionTitle(context.tr('Recent', 'حالیہ')),
        for (final r in asJsonList(_data['ledger']).take(8)) ListTile(dense: true, title: Text('${(r['minutes'] as num) > 0 ? '+' : ''}${r['minutes']}  ${r['reason'] ?? ''}'), subtitle: r['note'] == null ? null : Text('${r['note']}')),
      ]),
    );
  }
}

class _Slide extends StatefulWidget {
  const _Slide({required this.label, required this.value, required this.min, required this.max, required this.onSave, this.suffix = ''});
  final String label;
  final int value, min, max;
  final String suffix;
  final void Function(int) onSave;

  @override
  State<_Slide> createState() => _SlideState();
}

class _SlideState extends State<_Slide> {
  late double _v = widget.value.toDouble();

  @override
  Widget build(BuildContext context) => Row(children: [
        Expanded(flex: 3, child: Text(widget.label)),
        Expanded(flex: 4, child: Slider(value: _v.clamp(widget.min.toDouble(), widget.max.toDouble()), min: widget.min.toDouble(), max: widget.max.toDouble(), divisions: widget.max - widget.min, label: '${_v.round()}${widget.suffix}', onChanged: (v) => setState(() => _v = v), onChangeEnd: (v) => widget.onSave(v.round()))),
        SizedBox(width: 44, child: Text('${_v.round()}${widget.suffix}', textAlign: TextAlign.end, style: const TextStyle(fontWeight: FontWeight.w700))),
      ]);
}
