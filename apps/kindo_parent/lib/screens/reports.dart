import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets.dart';

/// How practice is going: numbers, letter by letter, calm time, milestones to share, and the report to print or send to a therapist.
class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key});

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  int _days = 14;

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final child = s.currentChild;
    if (child == null) return EmptyState(title: context.tr('No child yet', 'ابھی کوئی بچہ نہیں'), icon: Icons.child_care_rounded);

    return ListView(padding: pagePadding, children: [
      const ChildHeader(),
      const SizedBox(height: 12),
      SegmentedButton<int>(
        segments: [for (final d in const [7, 14, 30]) ButtonSegment(value: d, label: Text(context.tr('$d days', '$d دن')))],
        selected: {_days},
        onSelectionChanged: (v) => setState(() => _days = v.first),
      ),
      Load<Json>(
        keyValue: '${child.id}-$_days',
        future: () => s.api.insightsSummary(child.id, days: _days),
        builder: (context, d, _) {
          final p = asJson(d['practice']);
          final calm = asJson(d['calm']);
          final needs = asJson(d['needs']);
          return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            SectionTitle(context.tr('Practice', 'مشق')),
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 1.7,
              children: [
                Stat('${p['minutes'] ?? 0}', context.tr('minutes', 'منٹ')),
                Stat(p['accuracy_pct'] == null ? '-' : '${p['accuracy_pct']}%', context.tr('right answers', 'درست جوابات'), color: KindoColors.lavenderTint),
                Stat('${p['sessions'] ?? 0}', context.tr('sessions', 'سیشن')),
                Stat('${calm['minutes'] ?? 0}', context.tr('calm minutes', 'پرسکون منٹ'), color: KindoColors.cream),
              ],
            ),
            SectionTitle(context.tr('Asking for things', 'مانگنا')),
            KindoCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(context.tr('${needs['total'] ?? 0} times, ${needs['answered'] ?? 0} answered', '${needs['total'] ?? 0} بار، ${needs['answered'] ?? 0} کا جواب دیا')),
                if (needs['avg_reply_seconds'] != null) Text(context.tr('You usually answer in ${needs['avg_reply_seconds']} seconds', 'آپ عموماً ${needs['avg_reply_seconds']} سیکنڈ میں جواب دیتے ہیں'), style: Theme.of(context).textTheme.bodySmall),
                const SizedBox(height: 8),
                for (final e in asJson(needs['by_type']).entries) Padding(padding: const EdgeInsets.only(top: 2), child: Text('${e.key}: ${e.value}')),
              ]),
            ),
          ]);
        },
      ),
      SectionTitle(context.tr('Letter by letter', 'حرف بہ حرف')),
      Load<List<LetterStat>>(
        keyValue: '${child.id}-$_days',
        future: () => s.api.letters(child.id, days: _days),
        builder: (context, letters, _) {
          if (letters.isEmpty) return KindoCard(child: Text(context.tr('Letters appear here after your child traces some.', 'آپ کا بچہ جب حروف لکھے گا تو یہاں نظر آئیں گے۔')));
          return KindoCard(
            child: Wrap(spacing: 8, runSpacing: 8, children: [
              for (final l in letters)
                Container(
                  width: 62,
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  decoration: BoxDecoration(color: switch (l.status) { 'strong' => KindoColors.sage.withValues(alpha: .35), 'needs_practice' => KindoColors.warnTint, _ => KindoColors.butter }, borderRadius: BorderRadius.circular(16)),
                  child: Column(children: [Text(l.letter, style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w800)), Text(l.accuracyPct == null ? '-' : '${l.accuracyPct}%', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700))]),
                ),
            ]),
          );
        },
      ),
      SectionTitle(context.tr('Celebrate', 'جشن منائیں')),
      Load<List<Milestone>>(
        keyValue: child.id,
        future: () => s.api.milestones(child.id),
        builder: (context, ms, _) {
          if (ms.isEmpty) return KindoCard(child: Text(context.tr('Milestones appear as your child practises.', 'جیسے جیسے بچہ مشق کرے گا، سنگ میل یہاں آئیں گے۔')));
          return KindoCard(
            padding: EdgeInsets.zero,
            child: Column(children: [
              for (final m in ms)
                NavRow(icon: Icons.emoji_events_rounded, title: s.lang == 'ur' ? m.title.ur : m.title.en, subtitle: m.earnedOn, trailing: const Icon(Icons.ios_share_rounded), onTap: () => _shareCard(context, child, m)),
            ]),
          );
        },
      ),
      SectionTitle(context.tr('Share', 'شیئر کریں')),
      KindoCard(
        padding: EdgeInsets.zero,
        child: Column(children: [
          NavRow(
            icon: Icons.picture_as_pdf_outlined,
            title: context.tr('Progress report (PDF)', 'پیش رفت رپورٹ (پی ڈی ایف)'),
            subtitle: context.tr('For a therapist, a teacher or your records', 'تھراپسٹ، استاد یا ریکارڈ کے لیے'),
            onTap: () async {
              final b = await guarded(context, () => s.api.reportPdf(child.id, days: _days));
              if (b != null) await shareBytes(b, 'kindo-${child.name}-report.pdf', 'application/pdf');
            },
          ),
          NavRow(
            icon: Icons.table_chart_outlined,
            title: context.tr('Spreadsheet (CSV)', 'اسپریڈ شیٹ (سی ایس وی)'),
            onTap: () async {
              final b = await guarded(context, () => s.api.reportCsv(child.id, days: _days));
              if (b != null) await shareBytes(b, 'kindo-${child.name}.csv', 'text/csv');
            },
          ),
          NavRow(icon: Icons.auto_awesome_outlined, title: context.tr('Written summary (Pro)', 'تحریری خلاصہ (پرو)'), onTap: () => _ai(context, child)),
        ]),
      ),
    ]);
  }

  Future<void> _ai(BuildContext context, Child child) async {
    final s = context.read<KindoSession>();
    final nav = Navigator.of(context);
    final ok = await guarded(context, () async {
      await s.api.requestAiReport(child.id);
      return true;
    }, onUpgrade: () {});
    if (ok != true) return;
    await nav.push(MaterialPageRoute<void>(builder: (_) => Provider<KindoSession>.value(value: s, child: LangDirection(child: _AiReport(childId: child.id)))));
  }

  Future<void> _shareCard(BuildContext context, Child child, Milestone m) async {
    final s = context.read<KindoSession>();
    var lang = s.lang;
    var showName = true;
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text(s.lang == 'ur' ? m.title.ur : m.title.en, style: Theme.of(c).textTheme.titleLarge),
            const SizedBox(height: 12),
            SegmentedButton<String>(segments: const [ButtonSegment(value: 'en', label: Text('English')), ButtonSegment(value: 'ur', label: Text('اردو'))], selected: {lang}, onSelectionChanged: (v) => set(() => lang = v.first)),
            SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(context.tr('Show my child\'s name', 'بچے کا نام دکھائیں')), value: showName, onChanged: (v) => set(() => showName = v)),
            FilledButton.icon(
              icon: const Icon(Icons.ios_share_rounded),
              label: Text(context.tr('Share card', 'کارڈ شیئر کریں')),
              onPressed: () async {
                final nav = Navigator.of(c);
                final b = await guarded(context, () => s.api.milestoneCard(child.id, m.key, lang: lang, showName: showName));
                if (b != null) await shareBytes(b, 'kindo-milestone.png', 'image/png');
                nav.pop();
              },
            ),
          ]),
        ),
      ),
    );
  }
}

class _AiReport extends StatelessWidget {
  const _AiReport({required this.childId});
  final int childId;

  static String _lines(Object? v) {
    if (v == null) return '';
    if (v is List) return v.map((e) => '- ${e is Map ? e.values.join(': ') : e}').join('\n');
    if (v is Map) return v.entries.map((e) => '${e.key}: ${e.value}').join('\n');
    return '$v';
  }

  @override
  Widget build(BuildContext context) {
    final s = context.read<KindoSession>();
    final parts = <(String, String, String)>[
      ('language_dominance', 'Language', 'زبان'),
      ('phonetic_blindspots', 'Sounds to practise', 'مشق کی آوازیں'),
      ('sensory_behavior_summary', 'Comfort and sensory notes', 'آرام اور حسی نوٹس'),
      ('potty_timing_analysis', 'Toilet timing', 'واش روم کا وقت'),
      ('actionable_parent_exercises', 'Things to try', 'آزمانے کی چیزیں'),
      ('dietary_chaining_tips', 'Food tips', 'کھانے کے مشورے'),
    ];

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Written summary', 'تحریری خلاصہ'))),
      body: Load<Json?>(
        future: () => s.api.latestAiReport(childId),
        builder: (context, r, reload) => ListView(padding: pagePadding, children: [
          if (r == null)
            KindoCard(child: Column(children: [Text(context.tr('Your summary is being written. This takes a minute.', 'آپ کا خلاصہ لکھا جا رہا ہے۔ ایک منٹ لگے گا۔')), const SizedBox(height: 12), OutlinedButton(onPressed: reload, child: Text(context.tr('Check again', 'دوبارہ دیکھیں')))]))
          else
            for (final p in parts)
              if (_lines(r[p.$1]).isNotEmpty)
                Padding(padding: const EdgeInsets.only(bottom: 10), child: KindoCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(context.tr(p.$2, p.$3), style: const TextStyle(fontWeight: FontWeight.w800)), const SizedBox(height: 6), Text(_lines(r[p.$1]))]))),
        ]),
      ),
    );
  }
}
