import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets.dart';
import 'cards.dart';
import 'children.dart';
import 'guidance.dart';
import 'rewards.dart';

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final u = s.user;
    final child = s.currentChild;

    return ListView(padding: pagePadding, children: [
      KindoCard(
        child: Row(children: [
          CircleAvatar(radius: 26, backgroundColor: KindoColors.lilac, child: Text((u?.name.isNotEmpty ?? false) ? u!.name[0].toUpperCase() : '?', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 22))),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(u?.name.isEmpty ?? true ? (u?.email ?? '') : u!.name, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17)), if (u != null) Text(u.email, style: Theme.of(context).textTheme.bodySmall)])),
          Pill(u?.isPro ?? false ? 'Pro' : context.tr('Free', 'مفت'), color: u?.isPro ?? false ? KindoColors.butter : KindoColors.lavenderTint),
        ]),
      ),
      SectionTitle(context.tr('Your child', 'آپ کا بچہ')),
      KindoCard(
        padding: EdgeInsets.zero,
        child: Column(children: [
          for (final c in s.children) NavRow(icon: c.isSpecial ? Icons.favorite_border_rounded : Icons.child_care_rounded, title: c.name, subtitle: context.tr('How ${c.name} learns', 'سیکھنے کا انداز'), onTap: () => push(context, ChildFormScreen(child: c))),
          NavRow(icon: Icons.add_rounded, title: context.tr('Add a child', 'بچہ شامل کریں'), onTap: () => push(context, const ChildFormScreen())),
          NavRow(icon: Icons.photo_library_outlined, title: context.tr('Picture and family cards', 'تصویری اور خاندانی کارڈ'), onTap: child == null ? null : () => push(context, const CardsScreen())),
          NavRow(icon: Icons.emoji_events_outlined, title: context.tr('Games and play time', 'کھیل اور وقت'), onTap: child == null ? null : () => push(context, RewardsScreen(child: child))),
          NavRow(icon: Icons.menu_book_outlined, title: context.tr('Autism guide for parents', 'آٹزم: والدین کے لیے رہنما'), subtitle: context.tr('Health checks, food, exercise. Pro', 'صحت کے معائنے، خوراک، ورزش۔ پرو'), onTap: () => push(context, const GuidanceScreen())),
          NavRow(icon: Icons.support_agent_rounded, title: context.tr('Therapists and teachers', 'تھراپسٹ اور اساتذہ'), onTap: child == null ? null : () => push(context, SpecialistsScreen(child: child))),
        ]),
      ),
      SectionTitle(context.tr('Family and plan', 'خاندان اور پلان')),
      KindoCard(
        padding: EdgeInsets.zero,
        child: Column(children: [
          NavRow(icon: Icons.group_outlined, title: context.tr('Share with family', 'خاندان کے ساتھ شیئر کریں'), onTap: () => push(context, const HouseholdScreen())),
          NavRow(icon: Icons.workspace_premium_outlined, title: context.tr('Plan', 'پلان'), subtitle: u?.isPro ?? false ? 'Pro' : context.tr('Free', 'مفت'), onTap: () => push(context, const PlanScreen())),
          NavRow(icon: Icons.assignment_ind_outlined, title: context.tr('Children shared with me', 'میرے ساتھ شیئر کیے گئے بچے'), onTap: () => push(context, const SharedWithMeScreen())),
        ]),
      ),
      SectionTitle(context.tr('Settings', 'ترتیبات')),
      KindoCard(
        padding: EdgeInsets.zero,
        child: Column(children: [
          NavRow(icon: Icons.language_rounded, title: context.tr('Language', 'زبان'), subtitle: s.lang == 'ur' ? 'اردو' : 'English', onTap: () => s.setLang(s.lang == 'ur' ? 'en' : 'ur')),
          NavRow(icon: Icons.notifications_outlined, title: context.tr('Notifications', 'نوٹیفکیشن'), onTap: () => push(context, const NotificationsScreen())),
          NavRow(icon: Icons.manage_accounts_outlined, title: context.tr('Account and privacy', 'اکاؤنٹ اور رازداری'), onTap: () => push(context, const AccountScreen())),
          NavRow(icon: Icons.dns_outlined, title: context.tr('Server', 'سرور'), subtitle: s.baseUrl, onTap: () => _server(context)),
        ]),
      ),
      const SizedBox(height: 16),
      OutlinedButton(
        onPressed: () async {
          if (await confirm(context, title: context.tr('Sign out?', 'سائن آؤٹ کریں؟'), yes: context.tr('Sign out', 'سائن آؤٹ'), no: context.tr('Cancel', 'منسوخ')) && context.mounted) await s.signOut();
        },
        child: Text(context.tr('Sign out', 'سائن آؤٹ')),
      ),
    ]);
  }

  Future<void> _server(BuildContext context) async {
    final s = context.read<KindoSession>();
    final c = TextEditingController(text: s.baseUrl);
    final url = await showDialog<String>(
      context: context,
      builder: (d) => AlertDialog(
        title: Text(context.tr('Server address', 'سرور کا پتہ')),
        content: TextField(controller: c, keyboardType: TextInputType.url, decoration: const InputDecoration(hintText: 'https://kindo.example/api/v1')),
        actions: [TextButton(onPressed: () => Navigator.pop(d), child: Text(context.tr('Cancel', 'منسوخ'))), FilledButton(onPressed: () => Navigator.pop(d, c.text), child: Text(context.tr('Save', 'محفوظ کریں')))],
      ),
    );
    if (url != null) await s.setBaseUrl(url);
  }
}

// ---------- therapists and teachers ----------

class SpecialistsScreen extends StatelessWidget {
  const SpecialistsScreen({super.key, required this.child});
  final Child child;

  @override
  Widget build(BuildContext context) {
    final s = context.read<KindoSession>();
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Therapists and teachers', 'تھراپسٹ اور اساتذہ'))),
      body: Load<List<Json>>(
        future: () => s.api.specialists(child.id),
        builder: (context, list, reload) => ListView(padding: pagePadding, children: [
          Text(context.tr('They see ${child.name}\'s progress and can set exercises. They never see alerts, photos or family cards. You can stop sharing at any time.', 'وہ پیش رفت دیکھتے اور مشقیں دیتے ہیں۔ وہ الرٹس، تصویریں یا خاندانی کارڈ نہیں دیکھتے۔ آپ کسی بھی وقت شیئرنگ بند کر سکتے ہیں۔'), style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 12),
          for (final a in list)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: KindoCard(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${a['label']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
                  Text('${a['role']} - ${a['status']}', style: Theme.of(context).textTheme.bodySmall),
                  const SizedBox(height: 8),
                  Row(children: [
                    if (a['status'] == 'pending') OutlinedButton(onPressed: () async { final r = await guarded(context, () => s.api.newSpecialistCode((a['id'] as num).toInt())); if (r != null && context.mounted) _showCode(context, r); }, child: Text(context.tr('New code', 'نیا کوڈ'))),
                    const Spacer(),
                    TextButton(onPressed: () async { await guarded(context, () => s.api.revokeSpecialist((a['id'] as num).toInt())); await reload(); }, child: Text(context.tr('Stop sharing', 'شیئرنگ بند کریں'), style: const TextStyle(color: KindoColors.warn))),
                  ]),
                ]),
              ),
            ),
          FilledButton.icon(
            icon: const Icon(Icons.person_add_alt_1_rounded),
            label: Text(context.tr('Invite someone', 'کسی کو مدعو کریں')),
            onPressed: () async {
              final label = TextEditingController();
              var role = 'therapist';
              final ok = await showDialog<bool>(
                context: context,
                builder: (d) => StatefulBuilder(
                  builder: (d, set) => AlertDialog(
                    title: Text(context.tr('Invite', 'مدعو کریں')),
                    content: Column(mainAxisSize: MainAxisSize.min, children: [
                      TextField(controller: label, decoration: InputDecoration(labelText: context.tr('Name or note (Dr Sana, Class teacher)', 'نام یا نوٹ'))),
                      const SizedBox(height: 12),
                      SegmentedButton<String>(segments: [ButtonSegment(value: 'therapist', label: Text(context.tr('Therapist', 'تھراپسٹ'))), ButtonSegment(value: 'teacher', label: Text(context.tr('Teacher', 'استاد')))], selected: {role}, onSelectionChanged: (v) => set(() => role = v.first)),
                    ]),
                    actions: [TextButton(onPressed: () => Navigator.pop(d, false), child: Text(context.tr('Cancel', 'منسوخ'))), FilledButton(onPressed: () => Navigator.pop(d, true), child: Text(context.tr('Make code', 'کوڈ بنائیں')))],
                  ),
                ),
              );
              if (ok != true || label.text.trim().isEmpty || !context.mounted) return;
              final r = await guarded(context, () => s.api.inviteSpecialist(child.id, role: role, label: label.text.trim()));
              if (r != null && context.mounted) _showCode(context, r);
              await reload();
            },
          ),
        ]),
      ),
    );
  }

  void _showCode(BuildContext context, Json r) => showDialog<void>(
        context: context,
        builder: (d) => AlertDialog(
          title: Text(context.tr('Give them this code', 'انہیں یہ کوڈ دیں')),
          content: Text('${r['code'] ?? ''}', style: Theme.of(context).textTheme.displaySmall?.copyWith(letterSpacing: 6, fontWeight: FontWeight.w800), textAlign: TextAlign.center),
          actions: [TextButton(onPressed: () => Navigator.pop(d), child: Text(context.tr('Done', 'ہو گیا')))],
        ),
      );
}

/// A therapist or teacher: enter a code, then see the children shared with you and set exercises.
class SharedWithMeScreen extends StatelessWidget {
  const SharedWithMeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.read<KindoSession>();
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Shared with me', 'میرے ساتھ شیئر'))),
      body: Load<List<Json>>(
        future: s.api.sharedWithMe,
        builder: (context, list, reload) => ListView(padding: pagePadding, children: [
          OutlinedButton.icon(
            icon: const Icon(Icons.pin_outlined),
            label: Text(context.tr('Enter a code', 'کوڈ درج کریں')),
            onPressed: () async {
              final c = TextEditingController();
              final code = await showDialog<String>(
                context: context,
                builder: (d) => AlertDialog(title: Text(context.tr('6-digit code', '6 ہندسوں کا کوڈ')), content: TextField(controller: c, keyboardType: TextInputType.number, maxLength: 6, autofocus: true), actions: [TextButton(onPressed: () => Navigator.pop(d), child: Text(context.tr('Cancel', 'منسوخ'))), FilledButton(onPressed: () => Navigator.pop(d, c.text.trim()), child: Text(context.tr('Join', 'شامل ہوں')))]),
              );
              if (code == null || code.isEmpty || !context.mounted) return;
              await guarded(context, () => s.api.redeemSpecialistCode(code));
              await reload();
            },
          ),
          const SizedBox(height: 12),
          if (list.isEmpty) KindoCard(child: Text(context.tr('No one has shared a child with you yet.', 'ابھی کسی نے کوئی بچہ آپ کے ساتھ شیئر نہیں کیا۔'))),
          for (final c in list)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: KindoCard(
                onTap: () => push(context, _SharedChild(info: c)),
                child: Row(children: [Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('${c['name']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17)), Text('${c['role']}', style: Theme.of(context).textTheme.bodySmall)])), const Icon(Icons.chevron_right_rounded)]),
              ),
            ),
        ]),
      ),
    );
  }
}

class _SharedChild extends StatelessWidget {
  const _SharedChild({required this.info});
  final Json info;

  @override
  Widget build(BuildContext context) {
    final s = context.read<KindoSession>();
    final id = (info['child_id'] as num).toInt();
    return Scaffold(
      appBar: AppBar(title: Text('${info['name']}')),
      body: Load<Json>(
        future: () => s.api.sharedSummary(id),
        builder: (context, d, reload) {
          final p = asJson(d['practice']);
          return ListView(padding: pagePadding, children: [
            GridView.count(crossAxisCount: 2, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(), mainAxisSpacing: 12, crossAxisSpacing: 12, childAspectRatio: 1.7, children: [
              Stat('${p['minutes'] ?? 0}', context.tr('minutes', 'منٹ')),
              Stat(p['accuracy_pct'] == null ? '-' : '${p['accuracy_pct']}%', context.tr('right answers', 'درست جوابات'), color: KindoColors.lavenderTint),
            ]),
            SectionTitle(context.tr('Exercises', 'مشقیں')),
            Load<List<Json>>(
              future: () => s.api.sharedAssignments(id),
              builder: (context, list, reloadList) => Column(children: [
                for (final a in list) KindoCard(child: Row(children: [Expanded(child: Text('${a['title']}')), if (a['done_at'] != null || a['status'] == 'done') const Icon(Icons.check_circle_rounded, color: KindoColors.sageDeep)])),
                const SizedBox(height: 10),
                FilledButton.icon(
                  icon: const Icon(Icons.add_rounded),
                  label: Text(context.tr('Set an exercise', 'مشق دیں')),
                  onPressed: () async {
                    final t = TextEditingController();
                    final title = await showDialog<String>(context: context, builder: (d) => AlertDialog(title: Text(context.tr('Exercise', 'مشق')), content: TextField(controller: t, autofocus: true), actions: [TextButton(onPressed: () => Navigator.pop(d), child: Text(context.tr('Cancel', 'منسوخ'))), FilledButton(onPressed: () => Navigator.pop(d, t.text.trim()), child: Text(context.tr('Set', 'دیں')))]));
                    if (title == null || title.isEmpty || !context.mounted) return;
                    await guarded(context, () => s.api.assignExercise(id, title: title));
                    await reloadList();
                  },
                ),
              ]),
            ),
            const SizedBox(height: 16),
            TextButton(
              onPressed: () async {
                final nav = Navigator.of(context);
                if (await confirm(context, title: context.tr('Stop seeing ${info['name']}?', 'دیکھنا بند کریں؟'), yes: context.tr('Stop', 'بند کریں'), no: context.tr('Cancel', 'منسوخ'), danger: true)) {
                  await s.api.stopSharing(id);
                  nav.pop();
                }
              },
              child: Text(context.tr('Stop sharing with me', 'میرے ساتھ شیئرنگ بند کریں'), style: const TextStyle(color: KindoColors.warn)),
            ),
          ]);
        },
      ),
    );
  }
}

// ---------- household ----------

class HouseholdScreen extends StatelessWidget {
  const HouseholdScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.read<KindoSession>();
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Share with family', 'خاندان کے ساتھ شیئر کریں'))),
      body: Load<Json>(
        future: s.api.household,
        builder: (context, d, reload) {
          final members = asJsonList(d['members']);
          final invites = asJsonList(d['invitations']);
          final joined = asJsonList(d['joined']);
          return ListView(padding: pagePadding, children: [
            Text(context.tr('A partner or grandparent you invite sees and edits the same children, lessons and alerts.', 'آپ کا مدعو کیا ہوا ساتھی یا دادا/نانا انہی بچوں، اسباق اور الرٹس کو دیکھتا اور بدلتا ہے۔'), style: Theme.of(context).textTheme.bodySmall),
            for (final i in invites)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: KindoCard(
                  color: KindoColors.butter,
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(context.tr('${asJson(i['from'])['name']} invited you', '${asJson(i['from'])['name']} نے آپ کو مدعو کیا')),
                    Row(children: [
                      FilledButton(onPressed: () async { await guarded(context, () => s.api.acceptInvitation((i['id'] as num).toInt())); await s.refresh(); await reload(); }, child: Text(context.tr('Accept', 'قبول کریں'))),
                      const SizedBox(width: 8),
                      TextButton(onPressed: () async { await guarded(context, () => s.api.declineInvitation((i['id'] as num).toInt())); await reload(); }, child: Text(context.tr('Decline', 'مسترد کریں'))),
                    ]),
                  ]),
                ),
              ),
            SectionTitle(context.tr('People you share with', 'جن کے ساتھ شیئر کیا')),
            if (members.isEmpty) KindoCard(child: Text(context.tr('No one yet.', 'ابھی کوئی نہیں۔'))),
            for (final m in members)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: KindoCard(child: Row(children: [Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('${m['name'] ?? m['email']}', style: const TextStyle(fontWeight: FontWeight.w700)), Text('${m['email']} - ${m['status']}', style: Theme.of(context).textTheme.bodySmall)])), IconButton(icon: const Icon(Icons.close_rounded), onPressed: () async { await guarded(context, () => s.api.removeHouseholdMember((m['id'] as num).toInt())); await reload(); })])),
              ),
            for (final j in joined) ...[
              SectionTitle(context.tr('You are part of', 'آپ شامل ہیں')),
              KindoCard(child: Row(children: [Expanded(child: Text('${asJson(j['owner'])['name']}')), TextButton(onPressed: () async { await guarded(context, () => s.api.leaveHousehold((j['id'] as num).toInt())); await s.refresh(); await reload(); }, child: Text(context.tr('Leave', 'چھوڑیں')))])),
            ],
            const SizedBox(height: 12),
            FilledButton.icon(
              icon: const Icon(Icons.person_add_alt_1_rounded),
              label: Text(context.tr('Invite by email', 'ای میل سے مدعو کریں')),
              onPressed: () async {
                final c = TextEditingController();
                final email = await showDialog<String>(context: context, builder: (dd) => AlertDialog(title: Text(context.tr('Their email', 'ان کی ای میل')), content: TextField(controller: c, keyboardType: TextInputType.emailAddress, autofocus: true), actions: [TextButton(onPressed: () => Navigator.pop(dd), child: Text(context.tr('Cancel', 'منسوخ'))), FilledButton(onPressed: () => Navigator.pop(dd, c.text.trim()), child: Text(context.tr('Invite', 'مدعو کریں')))]));
                if (email == null || email.isEmpty || !context.mounted) return;
                await guarded(context, () => s.api.inviteToHousehold(email));
                await reload();
              },
            ),
          ]);
        },
      ),
    );
  }
}

// ---------- plan ----------

class PlanScreen extends StatelessWidget {
  const PlanScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.read<KindoSession>();
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Plan', 'پلان'))),
      body: Load<Json>(
        future: s.api.billing,
        builder: (context, d, reload) {
          final pro = d['plan'] == 'pro';
          final free = asJson(asJson(d['limits'])['free']);
          return ListView(padding: pagePadding, children: [
            KindoCard(
              color: pro ? KindoColors.butter : Colors.white,
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(pro ? 'Kindo Pro' : context.tr('Free plan', 'مفت پلان'), style: Theme.of(context).textTheme.headlineSmall),
                if (pro && d['days_left'] != null) Text(context.tr('${d['days_left']} days left', '${d['days_left']} دن باقی'))
                else if (!pro) Text(context.tr('${free['children']} child, ${free['lessons_per_child']} lessons, ${free['family_cards']} family cards and ${free['custom_cards_per_child']} own picture cards for each child.', '${free['children']} بچہ، ${free['lessons_per_child']} اسباق، ${free['family_cards']} خاندانی کارڈ اور ہر بچے کے ${free['custom_cards_per_child']} اپنے تصویری کارڈ۔')),
              ]),
            ),
            SectionTitle(context.tr('Have a voucher code?', 'کیا آپ کے پاس واؤچر کوڈ ہے؟')),
            FilledButton(
              onPressed: () async {
                final c = TextEditingController();
                final code = await showDialog<String>(context: context, builder: (dd) => AlertDialog(title: Text(context.tr('Voucher code', 'واؤچر کوڈ')), content: TextField(controller: c, textCapitalization: TextCapitalization.characters, autofocus: true), actions: [TextButton(onPressed: () => Navigator.pop(dd), child: Text(context.tr('Cancel', 'منسوخ'))), FilledButton(onPressed: () => Navigator.pop(dd, c.text.trim()), child: Text(context.tr('Redeem', 'استعمال کریں')))]));
                if (code == null || code.isEmpty || !context.mounted) return;
                final r = await guarded(context, () => s.api.redeemVoucher(code));
                if (r != null) {
                  await s.refresh();
                  await reload();
                }
              },
              child: Text(context.tr('Enter a code', 'کوڈ درج کریں')),
            ),
          ]);
        },
      ),
    );
  }
}

// ---------- notifications and account ----------

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  Json? _p;
  KindoSession get _s => context.read<KindoSession>();

  @override
  void initState() {
    super.initState();
    _s.api.notificationPrefs().then((p) => mounted ? setState(() => _p = p) : null).catchError((Object e) {
      if (mounted) showError(context, e);
    });
  }

  Future<void> _save(Json changes) async {
    final p = await guarded(context, () => _s.api.saveNotificationPrefs(changes));
    if (p != null && mounted) setState(() => _p = p);
  }

  @override
  Widget build(BuildContext context) {
    final p = _p;
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Notifications', 'نوٹیفکیشن'))),
      body: p == null
          ? const Loading()
          : ListView(padding: pagePadding, children: [
              Text(context.tr('Urgent requests (${(p['urgent'] as List?)?.join(', ') ?? ''}) always reach you, even in quiet hours.', 'ضروری درخواستیں (${(p['urgent'] as List?)?.join(', ') ?? ''}) ہمیشہ آپ تک پہنچتی ہیں، خاموش اوقات میں بھی۔'), style: Theme.of(context).textTheme.bodySmall),
              SwitchListTile(
                title: Text(context.tr('Quiet hours', 'خاموش اوقات')),
                subtitle: Text('${asJson(p['quiet_hours'])['start'] ?? '22:00'} - ${asJson(p['quiet_hours'])['end'] ?? '07:00'}'),
                value: asJson(p['quiet_hours'])['enabled'] == true,
                onChanged: (v) => _save({'quiet_hours': {'enabled': v}}),
              ),
              SectionTitle(context.tr('Mute these', 'انہیں خاموش کریں')),
              for (final m in (p['mutable'] as List? ?? const []))
                SwitchListTile(title: Text('$m'), value: (p['muted_needs'] as List? ?? const []).contains(m), onChanged: (v) {
                  final cur = {...(p['muted_needs'] as List? ?? const []).map((e) => '$e')};
                  v ? cur.add('$m') : cur.remove('$m');
                  _save({'muted_needs': cur.toList()});
                }),
            ]),
    );
  }
}

class AccountScreen extends StatelessWidget {
  const AccountScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.read<KindoSession>();
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Account and privacy', 'اکاؤنٹ اور رازداری'))),
      body: ListView(padding: pagePadding, children: [
        KindoCard(
          padding: EdgeInsets.zero,
          child: Column(children: [
            NavRow(
              icon: Icons.edit_outlined,
              title: context.tr('Your name', 'آپ کا نام'),
              subtitle: s.user?.name,
              onTap: () async {
                final c = TextEditingController(text: s.user?.name);
                final name = await showDialog<String>(context: context, builder: (d) => AlertDialog(title: Text(context.tr('Your name', 'آپ کا نام')), content: TextField(controller: c, autofocus: true), actions: [TextButton(onPressed: () => Navigator.pop(d), child: Text(context.tr('Cancel', 'منسوخ'))), FilledButton(onPressed: () => Navigator.pop(d, c.text.trim()), child: Text(context.tr('Save', 'محفوظ کریں')))]));
                if (name == null || name.isEmpty || !context.mounted) return;
                await guarded(context, () => s.api.updateMe(name: name));
                await s.refresh();
              },
            ),
            NavRow(
              icon: Icons.download_outlined,
              title: context.tr('Download my data', 'میرا ڈیٹا ڈاؤن لوڈ کریں'),
              subtitle: context.tr('Everything we hold about you and your children', 'آپ اور آپ کے بچوں کے بارے میں سب کچھ'),
              onTap: () async {
                final b = await guarded(context, s.api.exportMyData);
                if (b != null) await shareBytes(b, 'kindo-my-data.zip', 'application/zip');
              },
            ),
            NavRow(icon: Icons.devices_outlined, title: context.tr('Signed-in devices', 'سائن ان ڈیوائسز'), onTap: () => push(context, const _SessionsScreen())),
            NavRow(
              icon: Icons.delete_forever_outlined,
              title: context.tr('Delete my account', 'میرا اکاؤنٹ حذف کریں'),
              onTap: () async {
                if (!await confirm(context, title: context.tr('Delete your account?', 'اکاؤنٹ حذف کریں؟'), body: context.tr('We will email you a code. Everything is deleted for good.', 'ہم آپ کو کوڈ ای میل کریں گے۔ سب کچھ ہمیشہ کے لیے حذف ہو جائے گا۔'), yes: context.tr('Send code', 'کوڈ بھیجیں'), no: context.tr('Cancel', 'منسوخ'), danger: true) || !context.mounted) return;
                final sent = await guarded(context, () async {
                  await s.api.requestDeleteCode();
                  return true;
                });
                if (sent != true || !context.mounted) return;
                final c = TextEditingController();
                final code = await showDialog<String>(context: context, builder: (d) => AlertDialog(title: Text(context.tr('Code from your email', 'ای میل کا کوڈ')), content: TextField(controller: c, keyboardType: TextInputType.number, autofocus: true), actions: [TextButton(onPressed: () => Navigator.pop(d), child: Text(context.tr('Cancel', 'منسوخ'))), FilledButton(style: FilledButton.styleFrom(backgroundColor: KindoColors.warn), onPressed: () => Navigator.pop(d, c.text.trim()), child: Text(context.tr('Delete everything', 'سب حذف کریں')))]));
                if (code == null || code.isEmpty || !context.mounted) return;
                final done = await guarded(context, () async {
                  await s.api.deleteAccount(code);
                  return true;
                });
                if (done == true) await s.signOut(callServer: false);
              },
            ),
          ]),
        ),
      ]),
    );
  }
}

class _SessionsScreen extends StatelessWidget {
  const _SessionsScreen();

  @override
  Widget build(BuildContext context) {
    final s = context.read<KindoSession>();
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Signed-in devices', 'سائن ان ڈیوائسز'))),
      body: Load<List<Json>>(
        future: s.api.mySessions,
        builder: (context, list, reload) => ListView(padding: pagePadding, children: [
          for (final d in list)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: KindoCard(
                child: Row(children: [
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('${d['name'] ?? d['device'] ?? 'Device'}', style: const TextStyle(fontWeight: FontWeight.w700)), Text('${d['last_used_at'] ?? ''}', style: Theme.of(context).textTheme.bodySmall)])),
                  if (d['current'] != true) TextButton(onPressed: () async { await guarded(context, () => s.api.revokeSession((d['id'] as num).toInt())); await reload(); }, child: Text(context.tr('Sign out', 'سائن آؤٹ'))) else Pill(context.tr('This phone', 'یہ فون')),
                ]),
              ),
            ),
        ]),
      ),
    );
  }
}
