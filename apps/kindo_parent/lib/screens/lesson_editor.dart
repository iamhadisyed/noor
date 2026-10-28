import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets.dart';

/// Edit one lesson: its tasks, their pictures and voices, and publish it so the child sees it.
class LessonEditorScreen extends StatefulWidget {
  const LessonEditorScreen({super.key, required this.lessonId});
  final int lessonId;

  @override
  State<LessonEditorScreen> createState() => _LessonEditorScreenState();
}

class _LessonEditorScreenState extends State<LessonEditorScreen> {
  Lesson? _lesson;
  Object? _error;
  List<Json> _types = [];
  List<Json> _problems = [];

  KindoSession get _s => context.read<KindoSession>();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final l = await _s.api.lesson(widget.lessonId);
      final types = _types.isEmpty ? await _s.api.taskTypes() : _types;
      if (!mounted) return;
      setState(() {
        _lesson = l;
        _types = types;
        _error = null;
      });
      _s.syncNow();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  Json? _type(String t) => _types.cast<Json?>().firstWhere((x) => x?['type'] == t, orElse: () => null);

  Future<void> _publish() async {
    final l = _lesson!;
    try {
      await _s.api.publishLesson(l.id);
      _problems = [];
      if (mounted) showMessage(context, context.tr('Published. ${l.title} is now on your child\'s app.', 'شائع ہو گیا۔ اب بچے کی ایپ میں ہے۔'));
    } on ApiException catch (e) {
      final probs = asJsonList(e.body['problems']);
      if (e.status == 422 && probs.isNotEmpty) {
        setState(() => _problems = probs);
      } else if (mounted) {
        showError(context, e);
      }
    }
    await _load();
  }

  Future<void> _unpublish() async {
    await guarded(context, () => _s.api.unpublishLesson(_lesson!.id));
    await _load();
  }

  Future<void> _rename() async {
    final l = _lesson!;
    final c = TextEditingController(text: l.title);
    final name = await showDialog<String>(
      context: context,
      builder: (d) => AlertDialog(
        title: Text(context.tr('Lesson name', 'سبق کا نام')),
        content: TextField(controller: c, autofocus: true),
        actions: [TextButton(onPressed: () => Navigator.pop(d), child: Text(context.tr('Cancel', 'منسوخ'))), FilledButton(onPressed: () => Navigator.pop(d, c.text.trim()), child: Text(context.tr('Save', 'محفوظ کریں')))],
      ),
    );
    if (name == null || name.isEmpty || !mounted) return;
    await guarded(context, () => _s.api.updateLesson(l.id, {'title': name}));
    await _load();
  }

  Future<void> _archive() async {
    final yes = await confirm(context, title: context.tr('Remove this lesson?', 'یہ سبق ہٹائیں؟'), yes: context.tr('Remove', 'ہٹائیں'), no: context.tr('Cancel', 'منسوخ'), danger: true);
    if (!yes || !mounted) return;
    await guarded(context, () => _s.api.archiveLesson(_lesson!.id));
    await _s.syncNow();
    if (mounted) Navigator.pop(context);
  }

  Future<void> _editTask([LessonTask? task]) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => Provider<KindoSession>.value(value: _s, child: _TaskForm(lesson: _lesson!, task: task, types: _types)),
    );
    if (saved == true) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final l = _lesson;
    return Scaffold(
      appBar: AppBar(
        title: Text(l?.title ?? ''),
        actions: [
          if (l != null) IconButton(tooltip: context.tr('Rename', 'نام بدلیں'), icon: const Icon(Icons.edit_outlined), onPressed: _rename),
          if (l != null) IconButton(tooltip: context.tr('Remove', 'ہٹائیں'), icon: const Icon(Icons.delete_outline_rounded), onPressed: _archive),
        ],
      ),
      body: _error != null
          ? ErrorBox(_error!, onRetry: _load)
          : l == null
              ? const Loading()
              : ListView(padding: pagePadding.copyWith(bottom: 100), children: [
                  Row(children: [
                    Pill(l.isPublished ? context.tr('Live on your child\'s app', 'بچے کی ایپ میں جاری') : context.tr('Draft - your child cannot see it yet', 'مسودہ - بچہ ابھی نہیں دیکھ سکتا'), color: l.isPublished ? KindoColors.sage.withValues(alpha: .3) : KindoColors.butter),
                  ]),
                  if (_problems.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    KindoCard(
                      color: KindoColors.warnTint,
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(context.tr('Before you publish', 'شائع کرنے سے پہلے'), style: const TextStyle(fontWeight: FontWeight.w800)),
                        const SizedBox(height: 6),
                        for (final p in _problems) Padding(padding: const EdgeInsets.only(top: 4), child: Text('- ${p['message']}')),
                      ]),
                    ),
                  ],
                  SectionTitle(context.tr('Tasks', 'کام'), trailing: TextButton.icon(onPressed: () => _editTask(), icon: const Icon(Icons.add_rounded), label: Text(context.tr('Add', 'شامل کریں')))),
                  if (l.tasks.isEmpty) KindoCard(child: Text(context.tr('No tasks yet. Tap Add.', 'ابھی کوئی کام نہیں۔ شامل کریں دبائیں۔'))),
                  for (final t in l.tasks)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: KindoCard(
                        onTap: () => _editTask(t),
                        child: Row(children: [
                          SizedBox(width: 56, height: 56, child: t.hasImage ? NetImage(t.imageUrl, version: t.imageV, radius: 14) : Container(decoration: BoxDecoration(color: KindoColors.lavenderTint, borderRadius: BorderRadius.circular(14)), child: const Icon(Icons.text_fields_rounded))),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(t.title, style: const TextStyle(fontWeight: FontWeight.w800)),
                              Text('${_type(t.type)?['label'] ?? t.type}${t.target == null || t.target!.isEmpty ? '' : ' - ${t.target}'}', style: Theme.of(context).textTheme.bodySmall),
                              Wrap(spacing: 6, children: [
                                if (t.requiresImage && !t.hasImage) Pill(context.tr('needs picture', 'تصویر چاہیے'), color: KindoColors.warnTint),
                                if (t.requiresSound && !t.hasSound) Pill(context.tr('needs voice', 'آواز چاہیے'), color: KindoColors.warnTint),
                                if (t.hasSound) const Icon(Icons.volume_up_rounded, size: 18),
                              ]),
                            ]),
                          ),
                          const Icon(Icons.chevron_right_rounded),
                        ]),
                      ),
                    ),
                ]),
      bottomNavigationBar: l == null
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: l.isPublished
                    ? OutlinedButton(onPressed: _unpublish, child: Text(context.tr('Take it off my child\'s app', 'بچے کی ایپ سے ہٹائیں')))
                    : FilledButton(onPressed: l.tasks.isEmpty ? null : _publish, child: Text(context.tr('Publish to my child\'s app', 'بچے کی ایپ میں شائع کریں'))),
              ),
            ),
    );
  }
}

class _TaskForm extends StatefulWidget {
  const _TaskForm({required this.lesson, required this.types, this.task});
  final Lesson lesson;
  final LessonTask? task;
  final List<Json> types;

  @override
  State<_TaskForm> createState() => _TaskFormState();
}

class _TaskFormState extends State<_TaskForm> {
  late String _type = widget.task?.type ?? 'pick_picture';
  late final _title = TextEditingController(text: widget.task?.title ?? '');
  late final _instruction = TextEditingController(text: widget.task?.instruction ?? '');
  late final _target = TextEditingController(text: widget.task?.target ?? '');
  late final List<TextEditingController> _choices = [for (var i = 0; i < 4; i++) TextEditingController(text: i < (widget.task?.choices.length ?? 0) ? widget.task!.choices[i] : '')];
  late int _correct = widget.task?.correct ?? 0;
  late int _taskId = widget.task?.id ?? 0;
  bool _busy = false;
  bool _hasImage = false, _hasSound = false;

  @override
  void initState() {
    super.initState();
    _hasImage = widget.task?.hasImage ?? false;
    _hasSound = widget.task?.hasSound ?? false;
  }

  Json get _rule => widget.types.firstWhere((t) => t['type'] == _type, orElse: () => {});
  bool get _hasTarget => _rule['target'] == true;
  bool get _hasChoices => _rule['choices'] == true;

  KindoSession get _s => context.read<KindoSession>();

  Json _body() {
    final choices = _choices.map((c) => c.text.trim()).where((c) => c.isNotEmpty).toList();
    return {
      'type': _type,
      'title': _title.text.trim(),
      'instruction': _instruction.text.trim().isEmpty ? null : _instruction.text.trim(),
      'target': _hasTarget && _target.text.trim().isNotEmpty ? _target.text.trim() : null,
      if (_hasChoices && choices.length >= 2) 'options': {'choices': choices, 'correct': _correct.clamp(0, choices.length - 1)},
    };
  }

  /// Save the task text. Returns false on a problem (shown to the parent).
  Future<bool> _save() async {
    if (_title.text.trim().isEmpty) {
      showMessage(context, context.tr('Give the task a title.', 'کام کا عنوان لکھیں۔'));
      return false;
    }
    final r = await guarded(context, () async {
      if (_taskId == 0) {
        final t = await _s.api.addTask(widget.lesson.id, _body());
        _taskId = t.id;
      } else {
        await _s.api.updateTask(_taskId, _body());
      }
      return true;
    });
    return r == true;
  }

  Future<void> _addMedia(String kind, UploadFile? file) async {
    if (file == null) return;
    if (_taskId == 0 && !await _save()) return;
    if (!mounted) return;
    final ok = await guarded(context, () => _s.api.uploadTaskMedia(_taskId, kind, file));
    if (ok != null && mounted) setState(() => kind == 'image' ? _hasImage = true : _hasSound = true);
  }

  @override
  Widget build(BuildContext context) {
    final keyboard = _type == 'spell_word' ? _s.keyboards[keyboardFor(_target.text)] : null;
    final missing = keyboard?.missing(_target.text) ?? const <String>[];

    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.of(context).viewInsets.bottom + 20),
      child: SingleChildScrollView(
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, mainAxisSize: MainAxisSize.min, children: [
          DropdownButtonFormField<String>(
            initialValue: _type,
            decoration: InputDecoration(labelText: context.tr('Kind of task', 'کام کی قسم')),
            items: [for (final t in widget.types) DropdownMenuItem(value: '${t['type']}', child: Text('${t['label']}'))],
            onChanged: widget.task == null ? (v) => setState(() => _type = v ?? _type) : null,
          ),
          const SizedBox(height: 10),
          TextField(controller: _title, decoration: InputDecoration(labelText: context.tr('Title', 'عنوان'))),
          if (_hasTarget) ...[
            const SizedBox(height: 10),
            TextField(controller: _target, onChanged: (_) => setState(() {}), decoration: InputDecoration(labelText: '${_rule['target_label'] ?? context.tr('Target', 'ہدف')}')),
          ],
          if (keyboard != null && _target.text.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                missing.isEmpty ? context.tr('Your child can type this on the ${keyboard.name == 'urdu' ? 'Urdu' : 'English'} alphabet keyboard.', 'بچہ اسے حروف تہجی کی بورڈ پر لکھ سکتا ہے۔') : context.tr('The keyboard has no: ${missing.join(' ')}', 'کی بورڈ میں یہ نہیں ہے: ${missing.join(' ')}'),
                style: TextStyle(color: missing.isEmpty ? KindoColors.sageDeep : KindoColors.warn, fontWeight: FontWeight.w600),
              ),
            ),
          const SizedBox(height: 10),
          TextField(controller: _instruction, maxLines: 2, decoration: InputDecoration(labelText: context.tr('What to say (optional)', 'کیا کہنا ہے (اختیاری)')), style: const TextStyle(), ),
          if (_hasChoices) ...[
            const SizedBox(height: 12),
            Text(context.tr('Answer choices (tap the circle for the right one)', 'جواب کے انتخاب (صحیح پر دائرہ دبائیں)'), style: const TextStyle(fontWeight: FontWeight.w700)),
            for (var i = 0; i < 4; i++)
              Row(children: [
                IconButton(icon: Icon(_correct == i ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded), onPressed: () => setState(() => _correct = i)),
                Expanded(child: Padding(padding: const EdgeInsets.symmetric(vertical: 4), child: TextField(controller: _choices[i], decoration: InputDecoration(hintText: '${context.tr('Choice', 'انتخاب')} ${i + 1}')))),
              ]),
          ],
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            OutlinedButton.icon(onPressed: () async => _addMedia('image', await pickPicture()), icon: Icon(_hasImage ? Icons.check_circle_outline_rounded : Icons.image_outlined), label: Text(_hasImage ? context.tr('Change picture', 'تصویر بدلیں') : context.tr('Add picture', 'تصویر لگائیں'))),
            OutlinedButton.icon(onPressed: () async => _addMedia('image', await pickPicture(camera: true)), icon: const Icon(Icons.photo_camera_outlined), label: Text(context.tr('Take photo', 'تصویر کھینچیں'))),
            VoiceRecorderButton(label: _hasSound ? context.tr('Record again', 'دوبارہ ریکارڈ کریں') : context.tr('Record voice', 'آواز ریکارڈ کریں'), onRecorded: (f) => _addMedia('audio', f)),
          ]),
          const SizedBox(height: 18),
          Row(children: [
            if (_taskId != 0)
              TextButton(
                onPressed: _busy
                    ? null
                    : () async {
                        final nav = Navigator.of(context);
                        await guarded(context, () => _s.api.deleteTask(_taskId));
                        nav.pop(true);
                      },
                child: Text(context.tr('Delete', 'حذف کریں'), style: const TextStyle(color: KindoColors.warn)),
              ),
            const Spacer(),
            FilledButton(
              onPressed: _busy
                  ? null
                  : () async {
                      final nav = Navigator.of(context);
                      setState(() => _busy = true);
                      final ok = await _save();
                      if (ok) {
                        nav.pop(true);
                      } else if (mounted) {
                        setState(() => _busy = false);
                      }
                    },
              child: Text(context.tr('Save', 'محفوظ کریں')),
            ),
          ]),
        ]),
      ),
    );
  }
}
