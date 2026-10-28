import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets.dart';

/// Picture cards (what the child taps to ask for something) and family cards (photo and voice of people they know).
class CardsScreen extends StatelessWidget {
  const CardsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(title: Text(context.tr('Cards', 'کارڈ')), bottom: TabBar(tabs: [Tab(text: context.tr('Picture cards', 'تصویری کارڈ')), Tab(text: context.tr('Family', 'خاندان'))])),
        body: const TabBarView(children: [_PictureCards(), _Family()]),
      ),
    );
  }
}

const _categories = ['needs', 'food', 'emotions', 'activities'];

class _PictureCards extends StatelessWidget {
  const _PictureCards();

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final child = s.currentChild;
    if (child == null) return const SizedBox.shrink();
    final all = s.sync.aacCardsFor(child.id);
    final mine = all.where((c) => c.custom).toList();
    final stock = s.stockCards;

    return ListView(padding: pagePadding, children: [
      Row(children: [
        Expanded(child: Text(context.tr('Your own cards', 'آپ کے اپنے کارڈ'), style: Theme.of(context).textTheme.titleLarge)),
        FilledButton.icon(onPressed: () => _edit(context, child), icon: const Icon(Icons.add_rounded), label: Text(context.tr('New card', 'نیا کارڈ'))),
      ]),
      const SizedBox(height: 10),
      if (mine.isEmpty) KindoCard(child: Text(context.tr('Add a picture and your own voice for things your child asks for, like their favourite toy.', 'اپنے بچے کی مانگی جانے والی چیزوں کے لیے تصویر اور اپنی آواز شامل کریں۔'))),
      _Grid(cards: mine, onTap: (c) => _edit(context, child, c)),
      SectionTitle(context.tr('Ready-made cards', 'تیار کارڈ')),
      _Grid(cards: stock),
      const SizedBox(height: 12),
      OutlinedButton.icon(
        onPressed: () async {
          final bytes = await guarded(context, () => s.api.pecsPdf());
          if (bytes != null) await shareBytes(bytes, 'kindo-picture-cards.pdf', 'application/pdf');
        },
        icon: const Icon(Icons.print_outlined),
        label: Text(context.tr('Print a picture card sheet', 'تصویری کارڈ کی شیٹ پرنٹ کریں')),
      ),
    ]);
  }

  Future<void> _edit(BuildContext context, Child child, [AacCard? card]) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => Provider<KindoSession>.value(value: context.read<KindoSession>(), child: _CardForm(child: child, card: card)),
    );
    if (context.mounted) await context.read<KindoSession>().syncNow();
  }
}

class _Grid extends StatelessWidget {
  const _Grid({required this.cards, this.onTap});
  final List<AacCard> cards;
  final void Function(AacCard)? onTap;

  @override
  Widget build(BuildContext context) {
    final ur = context.watch<KindoSession>().lang == 'ur';
    return GridView.count(
      crossAxisCount: 3,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      childAspectRatio: .85,
      children: [
        for (final c in cards)
          KindoCard(
            padding: const EdgeInsets.all(10),
            radius: 22,
            onTap: onTap == null ? null : () => onTap!(c),
            child: Column(children: [
              Expanded(child: CardPicture(c)),
              const SizedBox(height: 6),
              Text(ur && c.urduLabel.isNotEmpty ? c.urduLabel : c.englishLabel, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
            ]),
          ),
      ],
    );
  }
}

class _CardForm extends StatefulWidget {
  const _CardForm({required this.child, this.card});
  final Child child;
  final AacCard? card;

  @override
  State<_CardForm> createState() => _CardFormState();
}

class _CardFormState extends State<_CardForm> {
  late final _en = TextEditingController(text: widget.card?.englishLabel ?? '');
  late final _ur = TextEditingController(text: widget.card?.urduLabel ?? '');
  late String _category = widget.card?.category ?? 'needs';
  UploadFile? _icon, _audio;
  bool _busy = false;

  Future<void> _save() async {
    final s = context.read<KindoSession>();
    final editing = widget.card != null;
    if (_en.text.trim().isEmpty || (!editing && _icon == null)) {
      showMessage(context, context.tr('Add a name and a picture.', 'نام اور تصویر شامل کریں۔'));
      return;
    }
    setState(() => _busy = true);
    final nav = Navigator.of(context);
    final ok = await guarded(context, () async {
      if (editing) {
        await s.api.updateAacCard(widget.card!.id, englishLabel: _en.text.trim(), urduLabel: _ur.text.trim(), category: _category, icon: _icon, audio: _audio);
      } else {
        await s.api.createAacCard(widget.child.id, englishLabel: _en.text.trim(), urduLabel: _ur.text.trim().isEmpty ? null : _ur.text.trim(), category: _category, icon: _icon!, audio: _audio);
      }
      return true;
    }, onUpgrade: () {});
    if (ok == true) {
      nav.pop();
    } else if (mounted) {
      setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.of(context).viewInsets.bottom + 20),
      child: SingleChildScrollView(
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: _en, decoration: InputDecoration(labelText: context.tr('Name in English', 'انگریزی میں نام'))),
          const SizedBox(height: 10),
          TextField(controller: _ur, textDirection: TextDirection.rtl, decoration: InputDecoration(labelText: context.tr('Name in Urdu (optional)', 'اردو میں نام (اختیاری)'))),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: _category,
            decoration: InputDecoration(labelText: context.tr('Group', 'گروپ')),
            items: [for (final c in _categories) DropdownMenuItem(value: c, child: Text(c[0].toUpperCase() + c.substring(1)))],
            onChanged: (v) => setState(() => _category = v ?? _category),
          ),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            OutlinedButton.icon(onPressed: () async { final f = await pickPicture(); if (f != null) setState(() => _icon = f); }, icon: Icon(_icon == null ? Icons.image_outlined : Icons.check_circle_outline_rounded), label: Text(context.tr('Picture', 'تصویر'))),
            OutlinedButton.icon(onPressed: () async { final f = await pickPicture(camera: true); if (f != null) setState(() => _icon = f); }, icon: const Icon(Icons.photo_camera_outlined), label: Text(context.tr('Take photo', 'تصویر کھینچیں'))),
            VoiceRecorderButton(label: _audio == null ? context.tr('Record voice', 'آواز ریکارڈ کریں') : context.tr('Recorded', 'ریکارڈ ہو گئی'), onRecorded: (f) => setState(() => _audio = f)),
          ]),
          const SizedBox(height: 18),
          Row(children: [
            if (widget.card != null)
              TextButton(
                onPressed: _busy
                    ? null
                    : () async {
                        final s = context.read<KindoSession>();
                        final nav = Navigator.of(context);
                        await guarded(context, () => s.api.deleteAacCard(widget.card!.id));
                        nav.pop();
                      },
                child: Text(context.tr('Delete', 'حذف کریں'), style: const TextStyle(color: KindoColors.warn)),
              ),
            const Spacer(),
            FilledButton(onPressed: _busy ? null : _save, child: Text(context.tr('Save', 'محفوظ کریں'))),
          ]),
        ]),
      ),
    );
  }
}

class _Family extends StatelessWidget {
  const _Family();

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final child = s.currentChild;
    if (child == null) return const SizedBox.shrink();
    final people = s.sync.familyFor(child.id);

    return ListView(padding: pagePadding, children: [
      Row(children: [
        Expanded(child: Text(context.tr('People your child knows', 'وہ لوگ جنہیں آپ کا بچہ جانتا ہے'), style: Theme.of(context).textTheme.titleLarge)),
        FilledButton.icon(onPressed: () => _add(context, child), icon: const Icon(Icons.add_rounded), label: Text(context.tr('Add', 'شامل کریں'))),
      ]),
      const SizedBox(height: 10),
      if (people.isEmpty) KindoCard(child: Text(context.tr('Add a photo and a recording of their voice. Your child plays a matching game with them.', 'تصویر اور آواز کی ریکارڈنگ شامل کریں۔ بچہ ان کے ساتھ ملانے کا کھیل کھیلتا ہے۔'))),
      for (final p in people)
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: KindoCard(
            padding: const EdgeInsets.all(12),
            child: Row(children: [
              SizedBox(width: 64, height: 64, child: NetImage(p.photoUrl, version: p.photoV, radius: 32)),
              const SizedBox(width: 14),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(p.displayName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17)), Text(p.relationTitle, style: Theme.of(context).textTheme.bodySmall)])),
              SoundButton(url: p.audioUrl, version: p.audioV, size: 44),
              IconButton(
                tooltip: context.tr('Remove', 'ہٹائیں'),
                icon: const Icon(Icons.delete_outline_rounded),
                onPressed: () async {
                  if (!await confirm(context, title: context.tr('Remove ${p.displayName}?', 'ہٹائیں؟'), yes: context.tr('Remove', 'ہٹائیں'), no: context.tr('Cancel', 'منسوخ'), danger: true) || !context.mounted) return;
                  await guarded(context, () => s.api.deleteFamilyMember(p.id));
                  await s.syncNow();
                },
              ),
            ]),
          ),
        ),
    ]);
  }

  Future<void> _add(BuildContext context, Child child) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => Provider<KindoSession>.value(value: context.read<KindoSession>(), child: _FamilyForm(child: child)),
    );
    if (context.mounted) await context.read<KindoSession>().syncNow();
  }
}

class _FamilyForm extends StatefulWidget {
  const _FamilyForm({required this.child});
  final Child child;

  @override
  State<_FamilyForm> createState() => _FamilyFormState();
}

class _FamilyFormState extends State<_FamilyForm> {
  final _relation = TextEditingController();
  final _name = TextEditingController();
  UploadFile? _photo, _voice;
  bool _consent = false, _busy = false;

  Future<void> _save() async {
    if (_relation.text.trim().isEmpty || _name.text.trim().isEmpty || _photo == null || _voice == null || !_consent) {
      showMessage(context, context.tr('Add who they are, a photo, a voice, and confirm you may use them.', 'نام، تصویر، آواز شامل کریں اور اجازت کی تصدیق کریں۔'));
      return;
    }
    final s = context.read<KindoSession>();
    final nav = Navigator.of(context);
    setState(() => _busy = true);
    final ok = await guarded(context, () async {
      await s.api.addFamilyMember(widget.child.id, relationTitle: _relation.text.trim(), displayName: _name.text.trim(), photo: _photo!, audio: _voice!);
      return true;
    });
    if (ok == true) {
      nav.pop();
    } else if (mounted) {
      setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.of(context).viewInsets.bottom + 20),
      child: SingleChildScrollView(
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: _relation, decoration: InputDecoration(labelText: context.tr('Who are they to your child? (Mama, Nani...)', 'یہ بچے کے کون ہیں؟ (ماما، نانی...)'))),
          const SizedBox(height: 10),
          TextField(controller: _name, decoration: InputDecoration(labelText: context.tr('Their name', 'ان کا نام'))),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            OutlinedButton.icon(onPressed: () async { final f = await pickPicture(); if (f != null) setState(() => _photo = f); }, icon: Icon(_photo == null ? Icons.image_outlined : Icons.check_circle_outline_rounded), label: Text(context.tr('Photo', 'تصویر'))),
            OutlinedButton.icon(onPressed: () async { final f = await pickPicture(camera: true); if (f != null) setState(() => _photo = f); }, icon: const Icon(Icons.photo_camera_outlined), label: Text(context.tr('Take photo', 'تصویر کھینچیں'))),
            VoiceRecorderButton(label: _voice == null ? context.tr('Record their voice', 'ان کی آواز ریکارڈ کریں') : context.tr('Recorded', 'ریکارڈ ہو گئی'), onRecorded: (f) => setState(() => _voice = f)),
          ]),
          CheckboxListTile(contentPadding: EdgeInsets.zero, value: _consent, onChanged: (v) => setState(() => _consent = v ?? false), title: Text(context.tr('I may use this person\'s photo and voice in this app.', 'مجھے اس شخص کی تصویر اور آواز اس ایپ میں استعمال کرنے کی اجازت ہے۔'))),
          FilledButton(onPressed: _busy ? null : _save, child: Text(context.tr('Save', 'محفوظ کریں'))),
        ]),
      ),
    );
  }
}
