import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets.dart';

/// "What did my child do?" (everyone sees today; Pro shows the last two weeks and screen time) and screen time rules (Pro).

class ActivityScreen extends StatefulWidget {
  const ActivityScreen({super.key, required this.child});
  final Child child;

  @override
  State<ActivityScreen> createState() => _ActivityScreenState();
}

class _ActivityScreenState extends State<ActivityScreen> {
  int _days = 7;
  KindoSession get _s => context.read<KindoSession>();

  static String _icon(String kind) => switch (kind) { 'practice' => '✏️', 'game' => '🎮', 'calm' => '🌿', 'need' => '🗣️', 'reward' => '⭐', _ => '•' };

  @override
  Widget build(BuildContext context) {
    final pro = context.watch<KindoSession>().user?.isPro ?? false;
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('What ${widget.child.name} did', '${widget.child.name} نے کیا کیا'))),
      body: Load<Json>(
        keyValue: _days,
        future: () => _s.api.activity(widget.child.id, days: pro ? _days : 1),
        builder: (context, d, reload) {
          final items = asJsonList(d['items']);
          final sum = asJson(d['summary']);
          final week = asJsonList(d['screen_time']);
          return ListView(padding: pagePadding, children: [
            if (pro)
              SegmentedButton<int>(
                segments: [
                  ButtonSegment(value: 1, label: Text(context.tr('Today', 'آج'))),
                  ButtonSegment(value: 7, label: Text(context.tr('7 days', '7 دن'))),
                  ButtonSegment(value: 14, label: Text(context.tr('14 days', '14 دن'))),
                ],
                selected: {_days},
                onSelectionChanged: (v) => setState(() => _days = v.first),
              ),
            const SizedBox(height: 12),
            KindoCard(
              child: Wrap(spacing: 18, runSpacing: 10, children: [
                _Stat('✏️', '${sum['practice_minutes'] ?? 0}', context.tr('min practising', 'منٹ مشق')),
                _Stat('🎮', '${sum['game_minutes'] ?? 0}', context.tr('min playing', 'منٹ کھیل')),
                _Stat('🌿', '${sum['calm_sessions'] ?? 0}', context.tr('calm times', 'سکون')),
                _Stat('🗣️', '${sum['asked_for'] ?? 0}', context.tr('times asked for something', 'بار کچھ مانگا')),
              ]),
            ),
            if (week.isNotEmpty) ...[
              SectionTitle(context.tr('Screen time', 'اسکرین ٹائم')),
              KindoCard(child: _WeekBars(week: week)),
            ],
            if (d['limited'] == true)
              KindoCard(
                color: KindoColors.butter,
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(context.tr('You are seeing today only.', 'آپ صرف آج کا دن دیکھ رہے ہیں۔'), style: const TextStyle(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  Text(context.tr('Kindo Pro shows the last two weeks and screen time.', 'کنڈو پرو پچھلے دو ہفتے اور اسکرین ٹائم دکھاتا ہے۔')),
                  const SizedBox(height: 10),
                  FilledButton(onPressed: () => push(context, const UpgradeScreen()), child: Text(context.tr('Get Pro', 'پرو لیں'))),
                ]),
              ),
            SectionTitle(context.tr('Timeline', 'ترتیب')),
            if (items.isEmpty) EmptyState(title: context.tr('Nothing yet', 'ابھی کچھ نہیں'), message: context.tr('When your child plays, practises or asks for something, it shows here.', 'جب آپ کا بچہ کھیلے، مشق کرے یا کچھ مانگے تو یہاں نظر آئے گا۔'), icon: Icons.timeline_rounded),
            for (final i in items)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: KindoCard(
                child: Row(children: [
                  Text(_icon('${i['kind']}'), style: const TextStyle(fontSize: 26)),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${i['title']}', style: const TextStyle(fontWeight: FontWeight.w800)),
                      if (i['detail'] != null) Text('${i['detail']}', style: Theme.of(context).textTheme.bodySmall),
                      Text(_when('${i['at']}') + (i['minutes'] != null && i['minutes'] != 0 ? '  ·  ${i['minutes']} min' : ''), style: Theme.of(context).textTheme.bodySmall?.copyWith(color: KindoColors.gray)),
                    ]),
                  ),
                ]),
              ),
              ),
          ]);
        },
      ),
    );
  }

  static String _when(String iso) {
    final t = DateTime.tryParse(iso)?.toLocal();
    if (t == null) return '';
    String two(int n) => n.toString().padLeft(2, '0');
    return '${t.day}/${t.month}  ${two(t.hour)}:${two(t.minute)}';
  }
}

class _Stat extends StatelessWidget {
  const _Stat(this.emoji, this.value, this.label);
  final String emoji;
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => SizedBox(
        width: 130,
        child: Row(children: [
          Text(emoji, style: const TextStyle(fontSize: 24)),
          const SizedBox(width: 8),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(value, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 20)), Text(label, style: Theme.of(context).textTheme.bodySmall)])),
        ]),
      );
}

class _WeekBars extends StatelessWidget {
  const _WeekBars({required this.week});
  final List<Json> week;

  @override
  Widget build(BuildContext context) {
    final most = week.fold<int>(1, (m, d) => ((d['seconds'] as num?)?.toInt() ?? 0) > m ? (d['seconds'] as num).toInt() : m);
    return SizedBox(
      height: 120,
      child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
        for (final d in week)
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 3),
              child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
                Text('${((d['seconds'] as num?)?.toInt() ?? 0) ~/ 60}', style: Theme.of(context).textTheme.bodySmall),
                Container(height: 8 + 70 * (((d['seconds'] as num?)?.toInt() ?? 0) / most), decoration: BoxDecoration(color: KindoColors.lilac, borderRadius: BorderRadius.circular(8))),
                const SizedBox(height: 4),
                Text('${d['date']}'.substring(8), style: Theme.of(context).textTheme.bodySmall),
              ]),
            ),
          ),
      ]),
    );
  }
}

/// Screen time (Pro): a daily allowance, quiet hours, today's use, and extra minutes. The child's app enforces it, even offline.
class ScreenTimeScreen extends StatefulWidget {
  const ScreenTimeScreen({super.key, required this.child});
  final Child child;

  @override
  State<ScreenTimeScreen> createState() => _ScreenTimeScreenState();
}

class _ScreenTimeScreenState extends State<ScreenTimeScreen> {
  KindoSession get _s => context.read<KindoSession>();
  int _reload = 0;

  Future<void> _save(Json changes) async {
    final r = await guarded(context, () => _s.api.saveScreenTime(widget.child.id, changes));
    if (r != null && mounted) {
      await _s.syncNow();
      setState(() => _reload++);
    }
  }

  Future<void> _time(Json settings, String key) async {
    final cur = '${settings[key] ?? (key == 'quiet_start' ? '20:00' : '07:00')}'.split(':');
    final t = await showTimePicker(context: context, initialTime: TimeOfDay(hour: int.tryParse(cur[0]) ?? 20, minute: int.tryParse(cur.length > 1 ? cur[1] : '0') ?? 0));
    if (t == null || !mounted) return;
    String two(int n) => n.toString().padLeft(2, '0');
    final v = '${two(t.hour)}:${two(t.minute)}';
    final other = key == 'quiet_start' ? 'quiet_end' : 'quiet_start';
    await _save({key: v, other: settings[other] ?? (key == 'quiet_start' ? '07:00' : '20:00')});
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Screen time', 'اسکرین ٹائم'))),
      body: Load<Json>(
        keyValue: _reload,
        future: () => _s.api.screenTime(widget.child.id),
        lockedBuilder: (context) => const UpgradePrompt(),
        builder: (context, d, reload) {
          final set = asJson(d['settings']);
          final today = asJson(d['today']);
          final week = asJsonList(d['week']);
          final on = set['enabled'] == true;
          final mins = (set['daily_minutes'] as num?)?.toInt() ?? 60;
          return ListView(padding: pagePadding, children: [
            KindoCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(context.tr('Limit ${widget.child.name}\'s screen time', '${widget.child.name} کا اسکرین ٹائم محدود کریں'), style: const TextStyle(fontWeight: FontWeight.w800)),
                  subtitle: Text(context.tr('When time is up, the app shows a calm "cool time" screen. The needs board still works.', 'وقت ختم ہونے پر ایپ آرام کی اسکرین دکھاتی ہے۔ ضرورت کا بورڈ پھر بھی چلتا ہے۔')),
                  value: on,
                  onChanged: (v) => _save({'enabled': v}),
                ),
                if (on) ...[
                  const Divider(),
                  Text(context.tr('Daily time: $mins minutes', 'روزانہ وقت: $mins منٹ')),
                  Slider(value: mins.toDouble().clamp(10, 240), min: 10, max: 240, divisions: 23, label: '$mins', onChanged: (v) => setState(() => set['daily_minutes'] = v.round()), onChangeEnd: (v) => _save({'daily_minutes': v.round()})),
                  const SizedBox(height: 4),
                  Text(context.tr('Quiet hours (no screen at all)', 'خاموش اوقات (اسکرین بالکل بند)'), style: const TextStyle(fontWeight: FontWeight.w800)),
                  Row(children: [
                    Expanded(child: OutlinedButton(onPressed: () => _time(set, 'quiet_start'), child: Text(context.tr('From ${set['quiet_start'] ?? 'not set'}', 'سے ${set['quiet_start'] ?? 'طے نہیں'}')))),
                    const SizedBox(width: 10),
                    Expanded(child: OutlinedButton(onPressed: () => _time(set, 'quiet_end'), child: Text(context.tr('Until ${set['quiet_end'] ?? 'not set'}', 'تک ${set['quiet_end'] ?? 'طے نہیں'}')))),
                  ]),
                  if (set['quiet_start'] != null) TextButton(onPressed: () => _save({'quiet_start': null, 'quiet_end': null}), child: Text(context.tr('Remove quiet hours', 'خاموش اوقات ہٹائیں'))),
                ],
              ]),
            ),
            if (on) ...[
              SectionTitle(context.tr('Today', 'آج')),
              KindoCard(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(context.tr('Used ${((today['used_seconds'] as num?)?.toInt() ?? 0) ~/ 60} of ${((today['allowed_seconds'] as num?)?.toInt() ?? 0) ~/ 60} minutes', 'استعمال ${((today['used_seconds'] as num?)?.toInt() ?? 0) ~/ 60} منٹ از ${((today['allowed_seconds'] as num?)?.toInt() ?? 0) ~/ 60}'), style: const TextStyle(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 8),
                  LinearProgressIndicator(value: ((today['allowed_seconds'] as num?)?.toInt() ?? 0) == 0 ? 0 : (((today['used_seconds'] as num?)?.toInt() ?? 0) / ((today['allowed_seconds'] as num).toInt())).clamp(0, 1).toDouble(), minHeight: 10, borderRadius: BorderRadius.circular(8)),
                  if (today['locked'] == true) Padding(padding: const EdgeInsets.only(top: 8), child: Text(context.tr('Cool time has started.', 'آرام کا وقت شروع ہو گیا ہے۔'))),
                  const SizedBox(height: 12),
                  Wrap(spacing: 8, children: [
                    for (final m in [10, 15, 30])
                      OutlinedButton(
                        onPressed: () async {
                          final r = await guarded(context, () => _s.api.extendScreenTime(widget.child.id, m));
                          if (r != null && mounted) setState(() => _reload++);
                        },
                        child: Text(context.tr('+$m more minutes', '+$m منٹ مزید')),
                      ),
                  ]),
                ]),
              ),
              SectionTitle(context.tr('This week', 'اس ہفتے')),
              KindoCard(child: _WeekBars(week: week)),
            ],
            const SizedBox(height: 12),
            Text(context.tr('The child\'s app counts the time and keeps working without internet. A child can always tell you what they need.', 'بچے کی ایپ وقت گنتی ہے اور انٹرنیٹ کے بغیر بھی چلتی ہے۔ بچہ پھر بھی بتا سکتا ہے کہ اسے کیا چاہیے۔'), style: Theme.of(context).textTheme.bodySmall),
          ]);
        },
      ),
    );
  }
}

class UpgradePrompt extends StatelessWidget {
  const UpgradePrompt({super.key});

  @override
  Widget build(BuildContext context) => ListView(padding: pagePadding, children: [
        KindoCard(
          color: KindoColors.butter,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(context.tr('This is part of Kindo Pro', 'یہ کنڈو پرو کا حصہ ہے'), style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            Text(context.tr('Screen time limits, quiet hours, two weeks of activity and the parent guides.', 'اسکرین ٹائم کی حد، خاموش اوقات، دو ہفتوں کی سرگرمی اور والدین کے رہنما۔')),
            const SizedBox(height: 12),
            FilledButton(onPressed: () => push(context, const UpgradeScreen()), child: Text(context.tr('Get Pro', 'پرو لیں'))),
          ]),
        ),
      ]);
}

/// Ask for Pro. We email how to pay; once the parent has paid they tell us the details here, and the owner switches the account to Pro.
class UpgradeScreen extends StatefulWidget {
  const UpgradeScreen({super.key});

  @override
  State<UpgradeScreen> createState() => _UpgradeScreenState();
}

class _UpgradeScreenState extends State<UpgradeScreen> {
  final _phone = TextEditingController();
  final _ref = TextEditingController();
  final _method = TextEditingController();
  int _reload = 0;
  KindoSession get _s => context.read<KindoSession>();

  @override
  void dispose() {
    _phone.dispose();
    _ref.dispose();
    _method.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Get Kindo Pro', 'کنڈو پرو لیں'))),
      body: Load<Json>(
        keyValue: _reload,
        future: () => _s.api.upgradeStatus(),
        builder: (context, d, reload) {
          final r = d['request'] == null ? null : asJson(d['request']);
          final status = r == null ? null : '${r['status']}';
          final pro = _s.user?.isPro ?? false;
          return ListView(padding: pagePadding, children: [
            if (pro && status != 'requested' && status != 'paid_reported')
              KindoCard(color: KindoColors.butter, child: Text(context.tr('Your account is Pro. Thank you!', 'آپ کا اکاؤنٹ پرو ہے۔ شکریہ!'), style: Theme.of(context).textTheme.titleMedium))
            else ...[
              KindoCard(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(context.tr('With Pro you get', 'پرو کے ساتھ آپ کو ملتا ہے'), style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  for (final f in [
                    context.tr('Screen time limits and quiet hours', 'اسکرین ٹائم کی حد اور خاموش اوقات'),
                    context.tr('Two weeks of what your child did, and reports', 'دو ہفتوں کی سرگرمی اور رپورٹیں'),
                    context.tr('More children, lessons and picture cards', 'مزید بچے، اسباق اور تصویری کارڈ'),
                    context.tr('The autism guide for parents', 'آٹزم: والدین کے لیے رہنما'),
                    context.tr('No adverts', 'کوئی اشتہار نہیں'),
                  ])
                    Padding(padding: const EdgeInsets.only(top: 4), child: Text('✓  $f')),
                ]),
              ),
              const SizedBox(height: 12),
              if (r == null || status == 'approved' || status == 'rejected') ...[
                if (status == 'rejected') KindoCard(color: KindoColors.butter, child: Text(context.tr('Your last request was not approved: ${r?['admin_note'] ?? ''}. You can ask again.', 'آپ کی پچھلی درخواست منظور نہیں ہوئی۔ آپ دوبارہ درخواست کر سکتے ہیں۔'))),
                TextField(controller: _phone, keyboardType: TextInputType.phone, decoration: InputDecoration(labelText: context.tr('Phone number (optional)', 'فون نمبر (اختیاری)'))),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: () async {
                    final res = await guarded(context, () => _s.api.requestUpgrade(phone: _phone.text.trim().isEmpty ? null : _phone.text.trim()));
                    if (res != null && mounted) setState(() => _reload++);
                  },
                  child: Text(context.tr('Make me Pro', 'مجھے پرو بنائیں')),
                ),
              ] else if (status == 'requested') ...[
                KindoCard(
                  color: KindoColors.lavenderTint,
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(context.tr('We emailed you how to pay.', 'ہم نے آپ کو ای میل کیا ہے کہ ادائیگی کیسے کریں۔'), style: const TextStyle(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 6),
                    Text('${asJson(d['payment'])['instructions'] ?? ''}'),
                    if (asJson(d['payment'])['contact_email'] != null) Text('${asJson(d['payment'])['contact_email']}'),
                  ]),
                ),
                const SizedBox(height: 12),
                Text(context.tr('After you have paid, tell us how:', 'ادائیگی کے بعد ہمیں بتائیں:'), style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                TextField(controller: _method, decoration: InputDecoration(labelText: context.tr('How did you pay? (e.g. bank, Easypaisa, JazzCash)', 'آپ نے کیسے ادائیگی کی؟ (مثلاً بینک، ایزی پیسہ، جاز کیش)'))),
                const SizedBox(height: 8),
                TextField(controller: _ref, decoration: InputDecoration(labelText: context.tr('Transaction number or name on the receipt', 'ٹرانزیکشن نمبر یا رسید پر نام'))),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: () async {
                    final res = await guarded(context, () => _s.api.reportPayment(method: _method.text.trim(), reference: _ref.text.trim()));
                    if (res != null && mounted) setState(() => _reload++);
                  },
                  child: Text(context.tr('I have paid', 'میں نے ادائیگی کر دی ہے')),
                ),
              ] else
                KindoCard(color: KindoColors.butter, child: Text(context.tr('Thank you. We are checking your payment and will switch your account to Pro. You will get an email.', 'شکریہ۔ ہم آپ کی ادائیگی چیک کر رہے ہیں اور آپ کا اکاؤنٹ پرو کر دیں گے۔ آپ کو ای میل ملے گی۔'))),
            ],
          ]);
        },
      ),
    );
  }
}

/// Where a Google advert goes. Adverts are for the free plan in the parent app only, never in the child's app, child-directed and not
/// personalised (see docs/ADS.md). Until the owner turns adverts on (`ADS_ENABLED`) and the AdMob SDK is added, nothing shows.
class AdSlot extends StatelessWidget {
  const AdSlot({super.key});

  @override
  Widget build(BuildContext context) {
    final u = context.watch<KindoSession>().user;
    if (u == null || u.isPro || u.raw['is_demo'] == true || u.raw['ads'] == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: Semantics(
        label: context.tr('Advertisement', 'اشتہار'),
        child: Container(
          height: 60,
          alignment: Alignment.center,
          decoration: BoxDecoration(border: Border.all(color: KindoColors.line), borderRadius: BorderRadius.circular(16)),
          child: Text(context.tr('Ad (removed with Pro)', 'اشتہار (پرو میں نہیں ہوتا)'), style: Theme.of(context).textTheme.bodySmall),
        ),
      ),
    );
  }
}
