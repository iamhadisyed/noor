import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets.dart';
import 'cards.dart';
import 'children.dart';
import 'lessons.dart';
import 'rewards.dart';

/// Words for a need, for the parent.
String needLabel(BuildContext context, String type) {
  const ur = {'water': 'پانی', 'food': 'کھانا', 'potty': 'واش روم', 'pain': 'درد', 'help': 'مدد', 'break': 'وقفہ', 'sleep': 'نیند', 'hug': 'گلے لگنا', 'play': 'کھیل', 'tv': 'ٹی وی'};
  final en = type.isEmpty ? type : '${type[0].toUpperCase()}${type.substring(1).replaceAll('_', ' ')}';
  return context.tr(en, ur[type.toLowerCase()] ?? en);
}

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final child = s.currentChild;
    return RefreshIndicator(
      onRefresh: s.refresh,
      child: ListView(padding: pagePadding, children: [
        if (child == null) ...[
          const SizedBox(height: 40),
          EmptyState(
            title: context.tr('Welcome to Kindo', 'کنڈو میں خوش آمدید'),
            message: context.tr('Add your child to start making lessons and picture cards.', 'اپنے بچے کو شامل کریں تاکہ اسباق اور تصویری کارڈ بنا سکیں۔'),
            icon: Icons.child_care_rounded,
            action: FilledButton(onPressed: () => push(context, const ChildFormScreen()), child: Text(context.tr('Add a child', 'بچہ شامل کریں'))),
          ),
        ] else ...[
          const ChildHeader(),
          const SizedBox(height: 16),
          _Pending(childId: child.id),
          _Summary(childId: child.id),
          SectionTitle(context.tr('Do something', 'کچھ کریں')),
          KindoCard(
            padding: EdgeInsets.zero,
            child: Column(children: [
              NavRow(icon: Icons.add_circle_outline_rounded, title: context.tr('Make a lesson', 'سبق بنائیں'), subtitle: context.tr('Words, pictures, tracing', 'الفاظ، تصویریں، لکھائی'), onTap: () => push(context, NewLessonScreen(child: child))),
              NavRow(icon: Icons.photo_library_outlined, title: context.tr('Picture cards', 'تصویری کارڈ'), subtitle: context.tr('What your child can ask for', 'آپ کا بچہ کیا مانگ سکتا ہے'), onTap: () => push(context, const CardsScreen())),
              NavRow(icon: Icons.emoji_events_outlined, title: context.tr('Games and play time', 'کھیل اور وقت'), subtitle: context.tr('${child.balanceMinutes} minutes saved', '${child.balanceMinutes} منٹ جمع ہیں'), onTap: () => push(context, RewardsScreen(child: child))),
              NavRow(icon: Icons.tune_rounded, title: context.tr('How ${child.name} learns', 'سیکھنے کا انداز'), subtitle: context.tr('Pace, help, praise', 'رفتار، مدد، تعریف'), onTap: () => push(context, ChildFormScreen(child: child))),
            ]),
          ),
          const _Announcements(),
        ],
      ]),
    );
  }
}

class _Pending extends StatelessWidget {
  const _Pending({required this.childId});
  final int childId;

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final open = s.sync.needsFor(childId).where((n) => n.status != 'on_the_way').take(3).toList();
    if (open.isEmpty) return const SizedBox.shrink();

    return KindoCard(
      color: KindoColors.butter,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(context.tr('Your child is asking', 'آپ کا بچہ کہہ رہا ہے'), style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        for (final n in open)
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Row(children: [
              Expanded(child: Text(needLabel(context, n.needType), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800))),
              FilledButton(
                onPressed: () async {
                  await guarded(context, () => s.api.acknowledge(n.id));
                  await s.syncNow();
                },
                child: Text(context.tr("I'm coming", 'آ رہا/رہی ہوں')),
              ),
            ]),
          ),
      ]),
    );
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.childId});
  final int childId;

  @override
  Widget build(BuildContext context) {
    final s = context.read<KindoSession>();
    return Load<Json>(
      keyValue: childId,
      future: () => s.api.insightsSummary(childId, days: 7),
      builder: (context, d, _) {
        final p = asJson(d['practice']);
        final n = asJson(d['needs']);
        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SectionTitle(context.tr('Last 7 days', 'پچھلے 7 دن')),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 1.7,
            children: [
              Stat('${p['minutes'] ?? 0}', context.tr('minutes practised', 'منٹ مشق')),
              Stat(p['accuracy_pct'] == null ? '-' : '${p['accuracy_pct']}%', context.tr('right answers', 'درست جوابات'), color: KindoColors.lavenderTint),
              Stat('${p['sessions'] ?? 0}', context.tr('practice sessions', 'مشق کے سیشن')),
              Stat('${n['total'] ?? 0}', context.tr('times they asked', 'بار مانگا'), color: KindoColors.cream),
            ],
          ),
        ]);
      },
    );
  }
}

class _Announcements extends StatelessWidget {
  const _Announcements();

  @override
  Widget build(BuildContext context) {
    final s = context.read<KindoSession>();
    return Load<List<Json>>(
      future: s.api.announcements,
      builder: (context, list, reload) => Column(children: [
        for (final a in list)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: KindoCard(
              color: KindoColors.lavenderTint,
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('${a['title'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w800)), const SizedBox(height: 4), Text('${a['body'] ?? ''}')])),
                IconButton(
                  tooltip: context.tr('Dismiss', 'بند کریں'),
                  icon: const Icon(Icons.close_rounded),
                  onPressed: () async {
                    await guarded(context, () => s.api.dismissAnnouncement((a['id'] as num).toInt()));
                    await reload();
                  },
                ),
              ]),
            ),
          ),
      ]),
    );
  }
}
