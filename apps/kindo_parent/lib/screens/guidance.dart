import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets.dart';
import 'more.dart';

/// "A guide for parents of a child with autism" (Pro): which health checks doctors often ask about, food, exercise, sleep and routines,
/// and when to see a doctor soon. General information only; the disclaimer comes first and last, and the server sends it with the guide.
class GuidanceScreen extends StatelessWidget {
  const GuidanceScreen({super.key, this.guide = 'autism'});
  final String guide;

  @override
  Widget build(BuildContext context) {
    final s = context.read<KindoSession>();
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Parent guide', 'والدین کا رہنما'))),
      body: FutureBuilder<Json>(
        future: s.api.guidance(guide),
        builder: (context, snap) {
          if (snap.hasError) {
            final e = snap.error;
            if (e is ApiException && e.needsUpgrade) return _Locked(body: e.body);
            return ErrorBox(snap.error!, onRetry: () => (context as Element).markNeedsBuild());
          }
          if (!snap.hasData) return const Loading();
          return _Guide(data: snap.data!);
        },
      ),
    );
  }
}

String _t(Object? v, String lang) {
  final m = asJson(v);
  final s = '${m[lang] ?? ''}';
  return s.isEmpty ? '${m['en'] ?? ''}' : s;
}

class _Guide extends StatelessWidget {
  const _Guide({required this.data});
  final Json data;

  @override
  Widget build(BuildContext context) {
    final lang = context.read<KindoSession>().lang;
    final sections = asJsonList(data['sections']);
    return ListView(padding: pagePadding, children: [
      Text(_t(data['title'], lang), style: Theme.of(context).textTheme.headlineSmall),
      const SizedBox(height: 14),
      _Notice(text: _t(data['disclaimer'], lang), title: context.tr('Please read first', 'پہلے یہ پڑھیں')),
      const SizedBox(height: 14),
      for (final sec in sections)
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: KindoCard(
            padding: EdgeInsets.zero,
            child: Theme(
              data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
              child: ExpansionTile(
                key: PageStorageKey('guide-${sec['key']}'),
                backgroundColor: Colors.transparent,
                collapsedBackgroundColor: Colors.transparent,
                shape: const Border(),
                collapsedShape: const Border(),
                leading: Text(_emoji('${sec['key']}'), style: const TextStyle(fontSize: 28)),
                title: Text(_t(sec['title'], lang), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
                childrenPadding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
                expandedCrossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(_t(sec['intro'], lang)),
                  const SizedBox(height: 12),
                  for (final item in asJsonList(sec['items']))
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(_t(item['title'], lang), style: const TextStyle(fontWeight: FontWeight.w800)),
                        const SizedBox(height: 2),
                        Text(_t(item['text'], lang)),
                      ]),
                    ),
                ],
              ),
            ),
          ),
        ),
      _Notice(text: _t(data['closing'], lang), title: context.tr('Remember', 'یاد رکھیں')),
      const SizedBox(height: 8),
    ]);
  }

  static String _emoji(String key) => switch (key) { 'tests' => '🩺', 'food' => '🥗', 'exercise' => '🏃', 'daily' => '🌙', 'doctor' => '🚑', _ => '📘' };
}

/// The warning that this is general information. Always visible, never collapsed.
class _Notice extends StatelessWidget {
  const _Notice({required this.text, required this.title});
  final String text;
  final String title;

  @override
  Widget build(BuildContext context) => KindoCard(
        color: KindoColors.butter,
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('⚠️', style: TextStyle(fontSize: 24)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)), const SizedBox(height: 4), Text(text)])),
        ]),
      );
}

/// Free plan: say what Pro adds and how to get it.
class _Locked extends StatelessWidget {
  const _Locked({required this.body});
  final Json body;

  @override
  Widget build(BuildContext context) {
    final lang = context.read<KindoSession>().lang;
    return ListView(padding: pagePadding, children: [
      Text(_t(body['title'], lang), style: Theme.of(context).textTheme.headlineSmall),
      const SizedBox(height: 14),
      KindoCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [const Icon(Icons.lock_outline_rounded), const SizedBox(width: 10), Expanded(child: Text(context.tr('This guide is part of Kindo Pro.', 'یہ رہنما کنڈو پرو کا حصہ ہے۔'), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)))]),
          const SizedBox(height: 10),
          Text(context.tr('Inside:', 'اندر کیا ہے:')),
          for (final p in asJsonList(body['preview'])) Padding(padding: const EdgeInsets.only(top: 6), child: Text('- ${_t(p['title'], lang)}')),
          const SizedBox(height: 14),
          Text(context.tr('It is general information only, written to take to your own doctor. It never replaces your child\'s doctor.', 'یہ صرف عمومی معلومات ہیں جو اپنے ڈاکٹر کے پاس لے جانے کے لیے لکھی گئی ہیں۔ یہ آپ کے بچے کے ڈاکٹر کی جگہ کبھی نہیں لیتیں۔'), style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 14),
          FilledButton(onPressed: () => push(context, const PlanScreen()), child: Text(context.tr('See plans', 'پلان دیکھیں'))),
        ]),
      ),
    ]);
  }
}
