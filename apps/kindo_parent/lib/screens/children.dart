import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets.dart';

const _neurotypes = {
  'neurotypical': ('No diagnosis', 'کوئی تشخیص نہیں'),
  'autism_spectrum': ('Autism', 'آٹزم'),
  'speech_delay': ('Speech delay', 'بولنے میں تاخیر'),
  'adhd': ('ADHD', 'اے ڈی ایچ ڈی'),
  'down_syndrome': ('Down syndrome', 'ڈاؤن سنڈروم'),
};

/// Add a child, or change how the app treats one: the pace, the help with tracing, how many choices, how praise sounds.
class ChildFormScreen extends StatefulWidget {
  const ChildFormScreen({super.key, this.child});
  final Child? child;

  @override
  State<ChildFormScreen> createState() => _ChildFormScreenState();
}

class _ChildFormScreenState extends State<ChildFormScreen> {
  late final _name = TextEditingController(text: widget.child?.name ?? '');
  late DateTime? _dob = widget.child?.dateOfBirth;
  late String _neuro = widget.child?.neurotype ?? 'neurotypical';
  late String _track = widget.child?.track ?? 'standard';
  late Support _support = widget.child?.support ?? const Support();
  bool _busy = false;

  bool get _editing => widget.child != null;

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  void _useDefaultsFor(String track) {
    final s = context.read<KindoSession>();
    final d = asJson(asJson(s.meta['support_defaults'])[track]);
    setState(() {
      _track = track;
      if (d.isNotEmpty) _support = Support.fromJson(d);
    });
  }

  Future<void> _save() async {
    final s = context.read<KindoSession>();
    if (_name.text.trim().isEmpty || _dob == null) {
      showMessage(context, context.tr("Please enter your child's name and birthday.", 'بچے کا نام اور تاریخ پیدائش لکھیں۔'));
      return;
    }
    setState(() => _busy = true);
    final dob = '${_dob!.year.toString().padLeft(4, '0')}-${_dob!.month.toString().padLeft(2, '0')}-${_dob!.day.toString().padLeft(2, '0')}';
    final ok = await guarded(context, () async {
      if (_editing) {
        await s.api.updateChild(widget.child!.id, {'name': _name.text.trim(), 'date_of_birth': dob, 'neurotype': _neuro, 'track': _track, 'support_profile': _support.toJson()});
      } else {
        final c = await s.api.createChild(name: _name.text.trim(), dateOfBirth: dob, neurotype: _neuro, track: _track, supportProfile: _support.toJson());
        await s.selectChild(c.id);
      }
      await s.syncNow();
      return true;
    });
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok == true) Navigator.pop(context);
  }

  Future<void> _delete() async {
    final s = context.read<KindoSession>();
    final yes = await confirm(context, title: context.tr('Delete ${widget.child!.name}?', 'حذف کریں؟'), body: context.tr('All their lessons, cards and reports will be deleted for good.', 'ان کے تمام اسباق، کارڈ اور رپورٹیں ہمیشہ کے لیے حذف ہو جائیں گی۔'), yes: context.tr('Delete', 'حذف کریں'), no: context.tr('Cancel', 'منسوخ'), danger: true);
    if (!yes || !mounted) return;
    final ok = await guarded(context, () async {
      await s.api.deleteChild(widget.child!.id);
      await s.syncNow();
      return true;
    });
    if (ok == true && mounted) Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_editing ? context.tr('How ${widget.child!.name} learns', 'سیکھنے کا انداز') : context.tr('Add a child', 'بچہ شامل کریں'))),
      body: ListView(padding: pagePadding, children: [
        TextField(controller: _name, textCapitalization: TextCapitalization.words, decoration: InputDecoration(labelText: context.tr('Name', 'نام'))),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          icon: const Icon(Icons.cake_outlined),
          label: Text(_dob == null ? context.tr('Birthday', 'تاریخ پیدائش') : '${_dob!.day}/${_dob!.month}/${_dob!.year}'),
          onPressed: () async {
            final now = DateTime.now();
            final d = await showDatePicker(context: context, initialDate: _dob ?? DateTime(now.year - 5), firstDate: DateTime(now.year - 24), lastDate: now.subtract(const Duration(days: 1)));
            if (d != null) setState(() => _dob = d);
          },
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _neuro,
          decoration: InputDecoration(labelText: context.tr('Does your child have a diagnosis?', 'کیا بچے کی کوئی تشخیص ہے؟')),
          items: [for (final e in _neurotypes.entries) DropdownMenuItem(value: e.key, child: Text(context.tr(e.value.$1, e.value.$2)))],
          onChanged: (v) {
            if (v == null) return;
            setState(() => _neuro = v);
            if (!_editing) _useDefaultsFor(v == 'neurotypical' ? 'standard' : 'special');
          },
        ),
        SectionTitle(context.tr('Learning style', 'سیکھنے کا انداز')),
        SegmentedButton<String>(
          segments: [
            ButtonSegment(value: 'standard', label: Text(context.tr('Standard', 'عام'))),
            ButtonSegment(value: 'special', label: Text(context.tr('Extra support', 'اضافی مدد'))),
          ],
          selected: {_track},
          onSelectionChanged: (v) => _useDefaultsFor(v.first),
        ),
        const SizedBox(height: 6),
        Text(
          _track == 'special'
              ? context.tr('Slower pace, no timers, quiet praise and fewer choices. You can change each one below.', 'سست رفتار، کوئی ٹائمر نہیں، خاموش تعریف اور کم انتخاب۔ ہر ایک نیچے بدل سکتے ہیں۔')
              : context.tr('Normal pace with lively praise.', 'عام رفتار اور پرجوش تعریف۔'),
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 12),
        KindoCard(
          child: Column(children: [
            _Choice(
              title: context.tr('Pace', 'رفتار'),
              value: _support.pace,
              options: {'normal': context.tr('Normal', 'عام'), 'slow': context.tr('Slow', 'سست')},
              onChanged: (v) => setState(() => _support = _copy(pace: v)),
            ),
            _Choice(
              title: context.tr('Tracing guide', 'لکھائی کی رہنمائی'),
              value: _support.guide,
              options: {'none': context.tr('None', 'کوئی نہیں'), 'dotted': context.tr('Dotted', 'نقطے'), 'arrows': context.tr('Arrows', 'تیر')},
              onChanged: (v) => setState(() => _support = _copy(guide: v)),
            ),
            _Choice(
              title: context.tr('Praise', 'تعریف'),
              value: _support.praise,
              options: {'lively': context.tr('Lively', 'پرجوش'), 'quiet': context.tr('Quiet', 'خاموش')},
              onChanged: (v) => setState(() => _support = _copy(praise: v)),
            ),
            _Choice(
              title: context.tr('Answer choices', 'جواب کے انتخاب'),
              value: '${_support.maxChoices}',
              options: {'2': '2', '3': '3', '4': '4'},
              onChanged: (v) => setState(() => _support = _copy(maxChoices: int.parse(v))),
            ),
            SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(context.tr('No timers', 'کوئی ٹائمر نہیں')), value: _support.noTimers, onChanged: (v) => setState(() => _support = _copy(noTimers: v))),
            SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(context.tr('First, then (show the next step)', 'پہلے، پھر (اگلا قدم دکھائیں)')), value: _support.firstThen, onChanged: (v) => setState(() => _support = _copy(firstThen: v))),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(context.tr('Fade the help as they improve', 'بہتری کے ساتھ مدد کم کریں')),
              value: _support.fadeAssist,
              onChanged: (v) => setState(() => _support = _copy(fadeAssist: v)),
            ),
            Row(children: [
              Expanded(child: Text(context.tr('Tracing magnet', 'لکھائی کا مقناطیس'))),
              SizedBox(
                width: 190,
                child: Slider(value: _support.magnetPx.toDouble(), min: 15, max: 40, divisions: 25, label: '${_support.magnetPx}', onChanged: (v) => setState(() => _support = _copy(magnetPx: v.round()))),
              ),
            ]),
          ]),
        ),
        const SizedBox(height: 20),
        FilledButton(onPressed: _busy ? null : _save, child: Text(_busy ? '...' : context.tr('Save', 'محفوظ کریں'))),
        if (_editing) ...[
          const SizedBox(height: 8),
          TextButton(onPressed: _delete, child: Text(context.tr('Delete this child', 'اس بچے کو حذف کریں'), style: const TextStyle(color: KindoColors.warn))),
        ],
      ]),
    );
  }

  Support _copy({int? magnetPx, String? pace, int? maxChoices, bool? noTimers, bool? firstThen, String? praise, String? guide, bool? fadeAssist}) => Support(
        magnetPx: magnetPx ?? _support.magnetPx,
        pace: pace ?? _support.pace,
        maxChoices: maxChoices ?? _support.maxChoices,
        noTimers: noTimers ?? _support.noTimers,
        firstThen: firstThen ?? _support.firstThen,
        praise: praise ?? _support.praise,
        guide: guide ?? _support.guide,
        fadeAssist: fadeAssist ?? _support.fadeAssist,
      );
}

class _Choice extends StatelessWidget {
  const _Choice({required this.title, required this.value, required this.options, required this.onChanged});
  final String title;
  final String value;
  final Map<String, String> options;
  final void Function(String) onChanged;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(children: [
          Expanded(child: Text(title)),
          SegmentedButton<String>(
            showSelectedIcon: false,
            segments: [for (final e in options.entries) ButtonSegment(value: e.key, label: Text(e.value))],
            selected: {options.containsKey(value) ? value : options.keys.first},
            onSelectionChanged: (v) => onChanged(v.first),
          ),
        ]),
      );
}
