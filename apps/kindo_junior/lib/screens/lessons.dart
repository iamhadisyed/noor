import 'dart:async';

import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets/celebrate.dart';
import 'home.dart';

class LessonsList extends StatelessWidget {
  const LessonsList({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final child = s.currentChild!;
    final lessons = s.sync.lessonsFor(child.id, publishedOnly: true).where((l) => l.tasks.isNotEmpty).toList();

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Learn', 'سیکھیں'))),
      body: lessons.isEmpty
          ? EmptyState(title: context.tr('No lessons yet', 'ابھی کوئی سبق نہیں'), message: context.tr('Ask a grown-up to make a lesson.', 'کسی بڑے سے سبق بنوائیں۔'), icon: Icons.menu_book_outlined)
          : ListView(padding: const EdgeInsets.all(16), children: [
              for (final l in lessons)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: KindoCard(
                    color: KindoColors.lilac,
                    onTap: () => goTo(context, LessonPlayer(lesson: l)),
                    child: Row(children: [
                      const Icon(Icons.play_circle_fill_rounded, size: 52),
                      const SizedBox(width: 16),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(l.title, style: Theme.of(context).textTheme.titleLarge, textDirection: directionOf(l.title)), Text(context.tr('${l.tasks.length} things to do', '${l.tasks.length} کام'), style: Theme.of(context).textTheme.bodySmall)])),
                    ]),
                  ),
                ),
            ]),
    );
  }
}

/// How a whole lesson went: collected as the child goes and sent as one practice session at the end.
class LessonRun {
  LessonRun() : _started = DateTime.now();
  final DateTime _started;
  final Map<String, PracticeLog> _logs = {};
  final Map<String, List<int>> _extra = {};

  PracticeLog logFor(String lang) => _logs.putIfAbsent(lang, () => PracticeLog(language: lang));

  /// A chosen or spelled answer: only the first try counts.
  void addAnswer(String lang, bool firstTryRight) {
    final e = _extra.putIfAbsent(lang, () => [0, 0]);
    e[0]++;
    if (firstTryRight) e[1]++;
  }

  List<Json> bodies() {
    final langs = {..._logs.keys, ..._extra.keys};
    final secs = DateTime.now().difference(_started).inSeconds.clamp(0, 43200);
    final out = <Json>[];
    for (final lang in langs) {
      final b = _logs.containsKey(lang) ? _logs[lang]!.build() : <String, dynamic>{'language': lang, 'total_attempts': 0, 'successful_attempts': 0, 'letters': <Json>[]};
      final e = _extra[lang] ?? [0, 0];
      b['total_attempts'] = (b['total_attempts'] as int) + e[0];
      b['successful_attempts'] = (b['successful_attempts'] as int) + e[1];
      b['duration_seconds'] = (secs / langs.length).round();
      if ((b['total_attempts'] as int) > 0) out.add(b);
    }
    return out;
  }
}

class LessonPlayer extends StatefulWidget {
  const LessonPlayer({super.key, required this.lesson});
  final Lesson lesson;

  @override
  State<LessonPlayer> createState() => _LessonPlayerState();
}

class _LessonPlayerState extends State<LessonPlayer> {
  final _run = LessonRun();
  int _i = 0;
  bool _finished = false;
  int _earned = 0;

  List<LessonTask> get _tasks => widget.lesson.tasks;

  Future<void> _next() async {
    if (_i + 1 < _tasks.length) {
      setState(() => _i++);
      return;
    }
    setState(() => _finished = true);
    final s = context.read<KindoSession>();
    final child = s.currentChild!;
    var earned = 0;
    for (final b in _run.bodies()) {
      try {
        final r = await s.api.uploadSession(child.id, b, idempotencyKey: newIdempotencyKey('session'));
        earned += (asJson(r['reward'])['earned'] as num?)?.toInt() ?? 0;
      } on ApiException catch (e) {
        // no connection: keep it and send it later (the same key makes a double send harmless)
        if (e.isRetryable) await s.queue('POST', '/children/${child.id}/sessions', body: b, keyPrefix: 'session');
      }
    }
    if (mounted) setState(() => _earned = earned);
    s.refresh();
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final child = s.currentChild!;
    final support = child.support;

    if (_finished) {
      return Scaffold(
        appBar: AppBar(),
        body: Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Celebrate(support: support, message: context.tr('You finished!', 'آپ نے مکمل کر لیا!')),
            if (_earned > 0) ...[const SizedBox(height: 18), Pill(context.tr('+$_earned play minutes', '+$_earned کھیل کے منٹ'), color: KindoColors.butter)],
            const SizedBox(height: 28),
            FilledButton(onPressed: () => Navigator.pop(context), child: Text(context.tr('Done', 'ہو گیا'))),
          ]),
        ),
      );
    }

    final task = _tasks[_i];
    final next = _i + 1 < _tasks.length ? _tasks[_i + 1] : null;

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.lesson.title, textDirection: directionOf(widget.lesson.title)),
        bottom: PreferredSize(preferredSize: const Size.fromHeight(6), child: LinearProgressIndicator(value: _i / _tasks.length, minHeight: 6, backgroundColor: KindoColors.line, color: KindoColors.ink)),
      ),
      body: SafeArea(
        child: Column(children: [
          // "first, then": a child who needs to know what comes next sees it
          if (support.firstThen && next != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
              child: Row(children: [Pill(context.tr('First: ${task.title}', 'پہلے: ${task.title}'), color: KindoColors.butter), const SizedBox(width: 8), const Icon(Icons.arrow_forward_rounded, size: 18), const SizedBox(width: 8), Flexible(child: Pill(context.tr('Then: ${next.title}', 'پھر: ${next.title}')))]),
            ),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              // a new key per task so each one starts fresh
              child: TaskView(key: ValueKey(task.id), task: task, lesson: widget.lesson, run: _run, onNext: _next),
            ),
          ),
        ]),
      ),
    );
  }
}

String _langOf(LessonTask t, Lesson l) {
  final basis = (t.target ?? '').isNotEmpty ? t.target! : t.title;
  if (RegExp('[\\u0600-\\u06FF]').hasMatch(basis)) return 'urdu';
  if (l.language == 'urdu') return 'urdu';
  return 'english';
}

/// One task of a lesson, shown the way its type needs.
class TaskView extends StatefulWidget {
  const TaskView({super.key, required this.task, required this.lesson, required this.run, required this.onNext});
  final LessonTask task;
  final Lesson lesson;
  final LessonRun run;
  final VoidCallback onNext;

  @override
  State<TaskView> createState() => _TaskViewState();
}

class _TaskViewState extends State<TaskView> {
  bool _done = false;
  bool _tried = false; // a wrong answer was given already on this task
  int _picked = -1;
  final Set<int> _wrong = {};

  LessonTask get t => widget.task;
  KindoSession get _s => context.read<KindoSession>();
  Support get _support => _s.currentChild!.support;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _hear());
  }

  void _hear() {
    if (!mounted) return;
    if (t.audioUrl != null) {
      AudioHub.instance.play(_s, t.audioUrl, version: t.audioV);
    } else if ((t.instruction ?? '').isNotEmpty) {
      Speaker.instance.speak(t.instruction!, lang: directionOf(t.instruction!) == TextDirection.rtl ? 'ur' : 'en', slow: _support.slow);
    }
  }

  void _finish() => setState(() => _done = true);

  Widget _prompt() => Column(children: [
        if (t.hasImage) Container(constraints: const BoxConstraints(maxHeight: 260), height: 240, width: double.infinity, margin: const EdgeInsets.only(bottom: 14), child: NetImage(t.imageUrl, version: t.imageV, fit: BoxFit.contain, radius: 28, semanticLabel: t.imageAlt ?? t.title)),
        Text(t.title, style: Theme.of(context).textTheme.headlineMedium, textAlign: TextAlign.center, textDirection: directionOf(t.title)),
        if ((t.instruction ?? '').isNotEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: Text(t.instruction!, style: Theme.of(context).textTheme.titleMedium, textAlign: TextAlign.center, textDirection: directionOf(t.instruction!))),
        const SizedBox(height: 10),
        IconButton.filledTonal(iconSize: 36, tooltip: context.tr('Hear it', 'سنیں'), onPressed: _hear, icon: const Icon(Icons.volume_up_rounded)),
      ]);

  @override
  Widget build(BuildContext context) {
    if (_done) {
      return Column(children: [
        const SizedBox(height: 40),
        Celebrate(support: _support),
        const SizedBox(height: 28),
        FilledButton.icon(style: FilledButton.styleFrom(minimumSize: const Size(180, 60)), onPressed: widget.onNext, icon: const Icon(Icons.arrow_forward_rounded), label: Text(context.tr('Next', 'آگے'))),
      ]);
    }

    switch (t.type) {
      case 'trace_letter':
      case 'trace_number':
        return _Trace(task: t, lesson: widget.lesson, run: widget.run, support: _support, onDone: _finish, prompt: _prompt());
      case 'spell_word':
        return _spell();
      case 'pick_picture':
      case 'count_objects':
      case 'social_story':
        return _choices();
      default:
        // listen_repeat, match_face, routine_step, say_it: no right or wrong, so they are not scored
        return Column(children: [
          _prompt(),
          if (t.type == 'say_it' && (t.target ?? '').isNotEmpty) Padding(padding: const EdgeInsets.only(top: 12), child: Text(t.target!, style: Theme.of(context).textTheme.displaySmall, textDirection: directionOf(t.target!))),
          const SizedBox(height: 22),
          FilledButton.icon(
            style: FilledButton.styleFrom(minimumSize: const Size(200, 60)),
            onPressed: _finish,
            icon: const Icon(Icons.check_rounded),
            label: Text(switch (t.type) { 'say_it' || 'listen_repeat' => context.tr('I said it', 'میں نے کہہ دیا'), 'routine_step' => context.tr('I did it', 'میں نے کر لیا'), _ => context.tr('That\'s it', 'یہی ہے') }),
          ),
        ]);
    }
  }

  Widget _choices() {
    var opts = [for (var i = 0; i < t.choices.length; i++) (i, t.choices[i])];
    // a child who needs fewer choices gets them: keep the right answer and the first others
    final max = _support.maxChoices;
    if (opts.length > max) {
      final right = opts.firstWhere((o) => o.$1 == t.correct, orElse: () => opts.first);
      opts = [right, ...opts.where((o) => o.$1 != right.$1).take(max - 1)]..sort((a, b) => a.$1.compareTo(b.$1));
    }
    return Column(children: [
      _prompt(),
      const SizedBox(height: 18),
      Wrap(spacing: 14, runSpacing: 14, alignment: WrapAlignment.center, children: [
        for (final o in opts)
          _Big(
            label: o.$2,
            color: _wrong.contains(o.$1) ? KindoColors.mist : (_picked == o.$1 ? KindoColors.sage : Colors.white),
            onTap: _wrong.contains(o.$1)
                ? null
                : () {
                    final right = o.$1 == t.correct;
                    if (!_tried) widget.run.addAnswer(_langOf(t, widget.lesson), right);
                    _tried = true;
                    if (right) {
                      setState(() => _picked = o.$1);
                      Future<void>.delayed(Duration(milliseconds: _support.slow ? 900 : 500), () => mounted ? _finish() : null);
                    } else {
                      setState(() => _wrong.add(o.$1));
                      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.tr('Try another one', 'دوسرا آزمائیں')), duration: const Duration(seconds: 2)));
                    }
                  },
          ),
      ]),
    ]);
  }

  Widget _spell() {
    final kb = _s.keyboards[t.keyboard ?? keyboardFor(t.target ?? '')];
    final target = t.target ?? '';
    if (kb == null) {
      return Column(children: [_prompt(), const SizedBox(height: 16), Text(context.tr('The keyboard is not ready. Open the app once while online.', 'کی بورڈ تیار نہیں۔ ایپ ایک بار آن لائن کھولیں۔')), const SizedBox(height: 12), FilledButton(onPressed: widget.onNext, child: Text(context.tr('Skip', 'چھوڑیں')))]);
    }
    final slots = target.replaceAll(RegExp('[\\s\\u200c\\u200d\\u064b-\\u065f\\u0670]'), '').runes.length;
    return Column(children: [
      _prompt(),
      const SizedBox(height: 14),
      SpellBoard(
        key: ValueKey('spell-$_tried-${_wrong.length}'),
        layout: kb,
        slots: slots,
        onSubmit: (typed) {
          final right = sameWord(typed, target);
          if (!_tried) widget.run.addAnswer(_langOf(t, widget.lesson), right);
          _tried = true;
          if (right) {
            _finish();
          } else {
            setState(() => _wrong.add(_wrong.length + 100));
            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.tr('Nearly! Try again', 'تقریباً! دوبارہ کوشش کریں')), duration: const Duration(seconds: 2)));
          }
        },
      ),
    ]);
  }
}

class _Big extends StatelessWidget {
  const _Big({required this.label, required this.color, required this.onTap});
  final String label;
  final Color color;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Semantics(
        button: true,
        label: label,
        child: Material(
          color: color,
          borderRadius: BorderRadius.circular(28),
          child: InkWell(
            borderRadius: BorderRadius.circular(28),
            onTap: onTap,
            child: Container(
              constraints: const BoxConstraints(minWidth: 130, minHeight: 84),
              padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 18),
              decoration: BoxDecoration(borderRadius: BorderRadius.circular(28), border: Border.all(color: KindoColors.line, width: 2)),
              alignment: Alignment.center,
              child: Text(label, textDirection: directionOf(label), style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w800)),
            ),
          ),
        ),
      );
}

/// Tracing one or more letters or digits, one after another.
class _Trace extends StatefulWidget {
  const _Trace({required this.task, required this.lesson, required this.run, required this.support, required this.onDone, required this.prompt});
  final LessonTask task;
  final Lesson lesson;
  final LessonRun run;
  final Support support;
  final VoidCallback onDone;
  final Widget prompt;

  @override
  State<_Trace> createState() => _TraceState();
}

class _TraceState extends State<_Trace> {
  late final List<String> _chars = (widget.task.target ?? '').characters.where((c) => c.trim().isNotEmpty && StrokeLibrary.lookup(c) != null).toList();
  int _at = 0;
  int _fails = 0;
  int _streak = 0;
  String? _hint;
  final _key = GlobalKey<TraceCanvasState>();

  @override
  void initState() {
    super.initState();
    if (_chars.isEmpty) WidgetsBinding.instance.addPostFrameCallback((_) => widget.onDone());
  }

  void _result(TraceResult r, bool assisted) {
    final lang = _langOf(widget.task, widget.lesson);
    widget.run.logFor(lang).addTry(_chars[_at], r, assisted: assisted);
    if (r.success) {
      _fails = 0;
      _streak++;
      if (_at + 1 >= _chars.length) return widget.onDone();
      setState(() {
        _at++;
        _hint = null;
      });
      _key.currentState?.clear();
    } else {
      _fails++;
      _streak = 0;
      setState(() => _hint = switch (r.mistake) { 'stroke_inversion' => context.tr('Start at the other end', 'دوسرے سرے سے شروع کریں'), 'dot_misplacement' => context.tr('Tap where the dots go', 'نقطے لگائیں'), _ => context.tr('Follow the dots', 'نقطوں پر چلیں') });
      _key.currentState?.clear();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_chars.isEmpty) return widget.prompt;
    final ch = _chars[_at];
    final glyph = StrokeLibrary.lookup(ch)!;
    // the help fades once the child traces well, when the parent allows it
    final showGuide = !(widget.support.fadeAssist && _streak >= 2);

    return Column(children: [
      widget.prompt,
      const SizedBox(height: 8),
      Text(ch, style: const TextStyle(fontSize: 54, fontWeight: FontWeight.w800)),
      if (_hint != null) Padding(padding: const EdgeInsets.only(bottom: 8), child: Text(_hint!, style: Theme.of(context).textTheme.titleMedium)),
      TraceCanvas(key: _key, glyph: glyph, support: widget.support, showGuide: showGuide, onResult: _result),
      // never a dead end: after a few tries the child can move on
      if (_fails >= (widget.support.quiet ? 2 : 3)) Padding(padding: const EdgeInsets.only(top: 12), child: TextButton(onPressed: () => _at + 1 >= _chars.length ? widget.onDone() : setState(() { _at++; _fails = 0; _hint = null; }), child: Text(context.tr('Skip this one', 'یہ چھوڑیں')))),
    ]);
  }
}
