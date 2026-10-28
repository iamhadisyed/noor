import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets.dart';
import 'lesson_editor.dart';

class LessonsScreen extends StatelessWidget {
  const LessonsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final child = s.currentChild;
    if (child == null) return EmptyState(title: context.tr('No child yet', 'ابھی کوئی بچہ نہیں'), message: context.tr('Add a child on the Home tab.', 'ہوم ٹیب پر بچہ شامل کریں۔'), icon: Icons.child_care_rounded);
    final lessons = s.sync.lessonsFor(child.id).where((l) => !l.isArchived).toList();

    return Stack(children: [
      RefreshIndicator(
        onRefresh: s.syncNow,
        child: ListView(padding: pagePadding.copyWith(bottom: 96), children: [
          const ChildHeader(),
          SectionTitle(context.tr('Lessons', 'اسباق')),
          if (lessons.isEmpty)
            SizedBox(height: 320, child: EmptyState(title: context.tr('No lessons yet', 'ابھی کوئی سبق نہیں'), message: context.tr('Make one yourself, start from a ready-made lesson, or photograph a page from school.', 'خود بنائیں، تیار سبق سے شروع کریں، یا اسکول کے صفحے کی تصویر لیں۔'), icon: Icons.menu_book_outlined)),
          for (final l in lessons)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: KindoCard(
                onTap: () => push(context, LessonEditorScreen(lessonId: l.id)),
                child: Row(children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(l.title, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 4),
                      Text(context.tr('${l.tasks.length} tasks - week ${l.weekNumber}', '${l.tasks.length} کام - ہفتہ ${l.weekNumber}'), style: Theme.of(context).textTheme.bodySmall),
                    ]),
                  ),
                  Pill(l.isPublished ? context.tr('Live', 'جاری') : context.tr('Draft', 'مسودہ'), color: l.isPublished ? KindoColors.sage.withValues(alpha: .3) : KindoColors.butter),
                ]),
              ),
            ),
        ]),
      ),
      Positioned(
        right: 16,
        bottom: 16,
        child: FloatingActionButton.extended(
          backgroundColor: KindoColors.ink,
          foregroundColor: Colors.white,
          onPressed: () => push(context, NewLessonScreen(child: child)),
          icon: const Icon(Icons.add_rounded),
          label: Text(context.tr('New lesson', 'نیا سبق')),
        ),
      ),
    ]);
  }
}

/// Three ways to begin a lesson: from nothing, from a ready-made one, from a photo of a page (Pro).
class NewLessonScreen extends StatefulWidget {
  const NewLessonScreen({super.key, required this.child});
  final Child child;

  @override
  State<NewLessonScreen> createState() => _NewLessonScreenState();
}

class _NewLessonScreenState extends State<NewLessonScreen> {
  final _title = TextEditingController();
  String _language = 'both';
  bool _busy = false;

  @override
  void dispose() {
    _title.dispose();
    super.dispose();
  }

  Future<void> _open(Lesson l) async {
    await context.read<KindoSession>().syncNow();
    if (!mounted) return;
    Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => LangDirection(child: LessonEditorScreen(lessonId: l.id))));
  }

  Future<void> _create() async {
    if (_title.text.trim().isEmpty) {
      showMessage(context, context.tr('Give the lesson a name first.', 'پہلے سبق کا نام لکھیں۔'));
      return;
    }
    final s = context.read<KindoSession>();
    setState(() => _busy = true);
    final l = await guarded(context, () => s.api.createLesson(widget.child.id, title: _title.text.trim(), language: _language, idempotencyKey: newIdempotencyKey('lesson')));
    if (!mounted) return;
    setState(() => _busy = false);
    if (l != null) await _open(l);
  }

  Future<void> _fromTemplate() async {
    final s = context.read<KindoSession>();
    final templates = await guarded(context, s.api.lessonTemplates);
    if (templates == null || !mounted) return;
    final pick = await showModalBottomSheet<Lesson>(
      context: context,
      showDragHandle: true,
      builder: (c) => ListView(shrinkWrap: true, children: [
        for (final t in templates) ListTile(title: Text(t.title), subtitle: Text(t.description ?? ''), onTap: () => Navigator.pop(c, t)),
      ]),
    );
    if (pick == null || !mounted) return;
    final l = await guarded(context, () => s.api.lessonFromTemplate(widget.child.id, pick.id));
    if (l != null) await _open(l);
  }

  Future<void> _fromPhoto() async {
    final s = context.read<KindoSession>();
    if (!(s.user?.isPro ?? false)) {
      showMessage(context, context.tr('Lessons from a photo are part of Kindo Pro.', 'تصویر سے سبق کنڈو پرو میں ہے۔'));
      return;
    }
    final ok = await confirm(context,
        title: context.tr('Read a page from a photo', 'تصویر سے صفحہ پڑھیں'),
        body: context.tr('The photo is read once to make a draft lesson. It is not kept. Check the draft before you publish it.', 'تصویر صرف ایک بار پڑھی جاتی ہے اور محفوظ نہیں کی جاتی۔ شائع کرنے سے پہلے مسودہ دیکھ لیں۔'),
        yes: context.tr('Choose photo', 'تصویر چنیں'),
        no: context.tr('Cancel', 'منسوخ'));
    if (!ok) return;
    final file = await pickPicture();
    if (file == null || !mounted) return;
    setState(() => _busy = true);
    final l = await guarded(context, () => s.api.importLessonFromPhoto(widget.child.id, file, language: _language));
    if (!mounted) return;
    setState(() => _busy = false);
    if (l != null) await _open(l);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('New lesson', 'نیا سبق'))),
      body: ListView(padding: pagePadding, children: [
        TextField(controller: _title, decoration: InputDecoration(labelText: context.tr('Lesson name', 'سبق کا نام'))),
        const SizedBox(height: 12),
        SegmentedButton<String>(
          segments: [
            ButtonSegment(value: 'english', label: Text(context.tr('English', 'انگریزی'))),
            ButtonSegment(value: 'urdu', label: Text(context.tr('Urdu', 'اردو'))),
            ButtonSegment(value: 'both', label: Text(context.tr('Both', 'دونوں'))),
          ],
          selected: {_language},
          onSelectionChanged: (v) => setState(() => _language = v.first),
        ),
        const SizedBox(height: 16),
        FilledButton(onPressed: _busy ? null : _create, child: Text(context.tr('Start an empty lesson', 'خالی سبق شروع کریں'))),
        SectionTitle(context.tr('Or', 'یا')),
        KindoCard(
          padding: EdgeInsets.zero,
          child: Column(children: [
            NavRow(icon: Icons.auto_awesome_motion_outlined, title: context.tr('Start from a ready-made lesson', 'تیار سبق سے شروع کریں'), onTap: _busy ? null : _fromTemplate),
            NavRow(icon: Icons.photo_camera_outlined, title: context.tr('Make one from a photo of a page', 'صفحے کی تصویر سے بنائیں'), subtitle: 'Pro', onTap: _busy ? null : _fromPhoto),
          ]),
        ),
        if (_busy) const Loading(),
      ]),
    );
  }
}
