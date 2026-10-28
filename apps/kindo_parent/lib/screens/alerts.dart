import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets.dart';
import 'home.dart';

/// What the child has asked for, newest first. Opening one marks it seen; "I'm coming" tells the child someone is on the way.
class AlertsScreen extends StatefulWidget {
  const AlertsScreen({super.key});

  @override
  State<AlertsScreen> createState() => _AlertsScreenState();
}

class _AlertsScreenState extends State<AlertsScreen> {
  final Set<int> _marked = {};

  String _ago(BuildContext context, DateTime? t) {
    if (t == null) return '';
    final d = DateTime.now().difference(t);
    if (d.inMinutes < 1) return context.tr('just now', 'ابھی');
    if (d.inMinutes < 60) return context.tr('${d.inMinutes} min ago', '${d.inMinutes} منٹ پہلے');
    if (d.inHours < 24) return context.tr('${d.inHours} h ago', '${d.inHours} گھنٹے پہلے');
    return context.tr('${d.inDays} d ago', '${d.inDays} دن پہلے');
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final child = s.currentChild;
    if (child == null) return EmptyState(title: context.tr('No child yet', 'ابھی کوئی بچہ نہیں'), icon: Icons.child_care_rounded);
    final needs = s.sync.needsFor(child.id);

    return RefreshIndicator(
      onRefresh: s.syncNow,
      child: ListView(padding: pagePadding, children: [
        const ChildHeader(),
        SectionTitle(context.tr('Alerts', 'الرٹس')),
        if (needs.isEmpty)
          SizedBox(height: 320, child: EmptyState(title: context.tr('Nothing yet', 'ابھی کچھ نہیں'), message: context.tr('When your child taps a picture card, it shows up here at once.', 'جب آپ کا بچہ تصویری کارڈ دباتا ہے تو یہاں فوراً نظر آتا ہے۔'), icon: Icons.notifications_none_rounded)),
        for (final n in needs.take(60))
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Builder(builder: (context) {
              if (n.status == 'sent' && _marked.add(n.id)) {
                // opening the screen counts as seeing it
                s.api.markSeen(n.id).then((_) => s.syncNow()).catchError((_) => null);
              }
              final answered = n.status == 'on_the_way';
              return KindoCard(
                color: answered ? Colors.white : KindoColors.butter,
                child: Row(children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(needLabel(context, n.needType), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 2),
                      Text(
                        answered
                            ? context.tr('${n.repliedBy ?? 'Someone'} replied${n.replySeconds == null ? '' : ' in ${n.replySeconds}s'}', '${n.repliedBy ?? 'کسی نے'} جواب دیا')
                            : '${_ago(context, n.createdAt)}${n.seenAt != null ? ' - ${context.tr('seen', 'دیکھ لیا')}' : ''}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ]),
                  ),
                  if (!answered)
                    FilledButton(
                      onPressed: () async {
                        await guarded(context, () => s.api.acknowledge(n.id));
                        await s.syncNow();
                      },
                      child: Text(context.tr("I'm coming", 'آ رہا/رہی ہوں')),
                    )
                  else
                    const Icon(Icons.check_circle_rounded, color: KindoColors.sageDeep),
                ]),
              );
            }),
          ),
      ]),
    );
  }
}
