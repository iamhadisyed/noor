import 'dart:async';

import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets/celebrate.dart';

/// Games are the reward for practice: minutes earned in lessons unlock them.
class GamesHub extends StatefulWidget {
  const GamesHub({super.key});

  @override
  State<GamesHub> createState() => _GamesHubState();
}

class _GamesHubState extends State<GamesHub> {
  Json? _data;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final s = context.read<KindoSession>();
    try {
      final d = await s.api.rewards(s.currentChild!.id);
      if (mounted) setState(() { _data = d; _error = null; });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  Future<void> _play(GameInfo g) async {
    final s = context.read<KindoSession>();
    final child = s.currentChild!;
    try {
      final start = await s.api.startGame(child.id, g.game, language: s.lang);
      if (!mounted) return;
      await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => LangDirection(child: GameScreen(start: start, info: g))));
      await _load();
      s.refresh();
    } on ApiException catch (e) {
      if (!mounted) return;
      final why = e.lockedReason;
      showMessage(context, why == 'no_minutes' ? context.tr('Practise a lesson to earn play minutes.', 'کھیل کے منٹ کمانے کے لیے سبق کی مشق کریں۔') : e.isNetwork ? context.tr('You need the internet to start a game.', 'کھیل شروع کرنے کے لیے انٹرنیٹ چاہیے۔') : e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final child = s.currentChild!;
    final ur = s.lang == 'ur';
    final d = _data;
    final games = d == null ? <GameInfo>[] : (asJsonList(d['games']).map(GameInfo.fromJson).where((g) => g.enabled && g.available).toList()..sort((a, b) => (b.forTracks.contains(child.track) ? 1 : 0) - (a.forTracks.contains(child.track) ? 1 : 0)));
    final minutes = (d?['balance_minutes'] as num?)?.toInt() ?? child.balanceMinutes;

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Play', 'کھیلیں'))),
      body: _error != null
          ? ErrorBox(_error!, onRetry: _load)
          : d == null
              ? const Loading()
              : ListView(padding: const EdgeInsets.all(16), children: [
                  KindoCard(
                    color: KindoColors.butter,
                    child: Row(children: [
                      const Icon(Icons.timer_outlined, size: 40),
                      const SizedBox(width: 14),
                      Expanded(child: Text(minutes > 0 ? context.tr('$minutes play minutes saved', '$minutes منٹ کھیلنے کے لیے جمع ہیں') : context.tr('Practise a lesson to earn play time', 'کھیل کا وقت کمانے کے لیے سبق کی مشق کریں'), style: Theme.of(context).textTheme.titleLarge)),
                    ]),
                  ),
                  const SizedBox(height: 14),
                  GridView.count(
                    crossAxisCount: 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    mainAxisSpacing: 12,
                    crossAxisSpacing: 12,
                    childAspectRatio: 1.15,
                    children: [
                      for (final g in games)
                        Material(
                          color: g.unlocked ? KindoColors.lavender : KindoColors.mist,
                          borderRadius: BorderRadius.circular(28),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(28),
                            onTap: () => _play(g),
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                                Icon(g.unlocked ? Icons.extension_rounded : Icons.lock_outline_rounded, size: 40),
                                const SizedBox(height: 8),
                                Text(ur ? g.title.ur : g.title.en, textAlign: TextAlign.center, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
                              ]),
                            ),
                          ),
                        ),
                    ],
                  ),
                ]),
    );
  }
}

String _name(Json m, String lang) => '${lang == 'ur' ? (m['name_ur'] ?? m['name_en'] ?? '') : (m['name_en'] ?? m['name_ur'] ?? '')}';

Color _hex(String? h) {
  final v = int.tryParse((h ?? '').replaceAll('#', ''), radix: 16);
  return v == null ? KindoColors.gray : Color(0xFF000000 | v);
}

/// One picture, number, letter, colour or sound from a round, drawn the way its shape says.
class RoundThing extends StatelessWidget {
  const RoundThing(this.value, {super.key, this.size = 72, this.lang = 'en', this.showName = false, this.nameOnly = false});
  final Object? value;
  final double size;
  final String lang;
  final bool showName;
  final bool nameOnly;

  @override
  Widget build(BuildContext context) {
    final v = value;
    if (v == null) return Container(width: size, height: size, alignment: Alignment.center, decoration: BoxDecoration(color: KindoColors.mist, borderRadius: BorderRadius.circular(18), border: Border.all(color: KindoColors.gray, width: 2)), child: Text('?', style: TextStyle(fontSize: size * .55, fontWeight: FontWeight.w800)));
    if (v is! Map) return Text('$v', style: TextStyle(fontSize: size * .7, fontWeight: FontWeight.w800), textDirection: directionOf('$v'));
    final m = asJson(v);

    if (nameOnly && (m['name_en'] != null || m['name_ur'] != null)) return Text(_name(m, lang), style: TextStyle(fontSize: size * .45, fontWeight: FontWeight.w800), textDirection: lang == 'ur' ? TextDirection.rtl : TextDirection.ltr);
    if (m['count'] != null && m['icon'] is Map) return CountGrid(icon: '${asJson(m['icon'])['key']}', count: (m['count'] as num).toInt(), size: size * .45);
    if (m['hex'] != null) {
      return Column(mainAxisSize: MainAxisSize.min, children: [
        Container(width: size, height: size, decoration: BoxDecoration(color: _hex('${m['hex']}'), shape: BoxShape.circle, border: Border.all(color: KindoColors.ink, width: 2))),
        if (showName) Text(_name(m, lang), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
      ]);
    }
    if (m['audio_url'] != null && m['image_url'] == null) return SoundButton(url: '${m['audio_url']}', size: size);
    if (m['image_url'] != null) return Column(mainAxisSize: MainAxisSize.min, children: [SizedBox(width: size * 1.6, height: size * 1.6, child: NetImage('${m['image_url']}', radius: 20)), if (showName) Text(_name(m, lang), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18))]);
    if (m['key'] != null) {
      return Column(mainAxisSize: MainAxisSize.min, children: [ArtIcon('${m['key']}', size: size), if (showName && (m['name_en'] != null || m['name_ur'] != null)) Text(_name(m, lang), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18))]);
    }
    if (m['en'] != null || m['ur'] != null) {
      final t = '${lang == 'ur' ? (m['ur'] ?? m['en']) : (m['en'] ?? m['ur'])}';
      return Text(t, style: TextStyle(fontSize: size * .4, fontWeight: FontWeight.w800), textDirection: directionOf(t));
    }
    return const SizedBox.shrink();
  }
}

class GameScreen extends StatefulWidget {
  const GameScreen({super.key, required this.start, required this.info});
  final GameStart start;
  final GameInfo info;

  @override
  State<GameScreen> createState() => _GameScreenState();
}

class _GameScreenState extends State<GameScreen> {
  late final GameRunner _run = GameRunner(widget.start);
  final Set<int> _wrong = {};
  int _picked = -1;
  bool _right = false;
  bool _ended = false;
  bool _reported = false;
  List<String> _order = [];
  MemoryBoard? _board;
  Timer? _tick;

  @override
  void initState() {
    super.initState();
    _prepare();
    _tick = Timer.periodic(const Duration(seconds: 1), (_) => mounted ? setState(() {}) : null);
  }

  @override
  void dispose() {
    _tick?.cancel();
    super.dispose();
  }

  void _prepare() {
    _wrong.clear();
    _picked = -1;
    _right = false;
    _order = [];
    _board = _run.finished || _run.kind != RoundKind.memory ? null : MemoryBoard(((_run.current['pairs']) as List).map(asJson).toList());
    if (!_run.finished) {
      final a = _run.current['audio_url'];
      if (a != null && mounted) WidgetsBinding.instance.addPostFrameCallback((_) => AudioHub.instance.play(context.read<KindoSession>(), '$a'));
    }
  }

  Future<void> _end() async {
    if (_ended) return;
    setState(() => _ended = true);
    final s = context.read<KindoSession>();
    final r = _run.report();
    try {
      await s.api.finishGame(widget.start.sessionId, roundsPlayed: r.roundsPlayed, correct: r.correct, secondsPlayed: r.secondsPlayed, idempotencyKey: 'game-${widget.start.sessionId}-finish');
    } on ApiException catch (e) {
      if (e.isRetryable) await s.queue('POST', '/game-sessions/${widget.start.sessionId}/finish', body: {'rounds_played': r.roundsPlayed, 'correct': r.correct, 'seconds_played': r.secondsPlayed}, keyPrefix: 'game');
    }
    if (mounted) setState(() => _reported = true);
  }

  void _advance() {
    _run.next();
    // when the time the parent allowed is up, the game ends kindly after the answer, never in the middle of one
    if (_run.finished || _run.timeIsUp) {
      _end();
    } else {
      setState(_prepare);
    }
  }

  void _right_() {
    setState(() => _right = true);
    final slow = context.read<KindoSession>().currentChild!.support.slow;
    Future<void>.delayed(Duration(milliseconds: slow ? 1200 : 700), () => mounted ? _advance() : null);
  }

  void _wrongTap(int i) {
    setState(() => _wrong.add(i));
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.tr('Try again', 'دوبارہ کوشش کریں')), duration: const Duration(seconds: 1)));
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final support = s.currentChild!.support;
    final lang = s.lang;

    if (_ended || _run.finished) {
      if (!_ended) WidgetsBinding.instance.addPostFrameCallback((_) => _end());
      return Scaffold(
        appBar: AppBar(),
        body: Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Celebrate(support: support, message: context.tr('Great playing!', 'بہت اچھا کھیلا!')),
            const SizedBox(height: 10),
            Text(context.tr('${_run.correct} right first time', '${_run.correct} پہلی بار درست'), style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 24),
            FilledButton(onPressed: _reported ? () => Navigator.pop(context) : null, child: Text(context.tr('Done', 'ہو گیا'))),
          ]),
        ),
      );
    }

    final round = _run.current;
    final prompt = asJson(round['prompt']);
    final kind = _run.kind;

    return Scaffold(
      appBar: AppBar(
        title: Text(lang == 'ur' ? widget.info.title.ur : widget.info.title.en),
        bottom: PreferredSize(preferredSize: const Size.fromHeight(6), child: LinearProgressIndicator(value: _run.index / _run.roundCount, minHeight: 6, backgroundColor: KindoColors.line, color: KindoColors.ink)),
        actions: [
          // a child who finds clocks stressful sees none
          if (!support.noTimers) Padding(padding: const EdgeInsets.only(right: 16), child: Center(child: Text('${_run.secondsLeft ~/ 60}:${(_run.secondsLeft % 60).toString().padLeft(2, '0')}', style: const TextStyle(fontWeight: FontWeight.w700)))),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(children: [
            Text('${lang == 'ur' ? prompt['ur'] : prompt['en']}', style: Theme.of(context).textTheme.headlineSmall, textAlign: TextAlign.center),
            const SizedBox(height: 18),
            ..._stimulus(round, lang),
            const SizedBox(height: 20),
            switch (kind) {
              RoundKind.choice => _options(round, lang),
              RoundKind.spell => _spell(round, s),
              RoundKind.order => _orderRound(round, lang),
              RoundKind.memory => _memory(lang),
            },
          ]),
        ),
      ),
    );
  }

  List<Widget> _stimulus(Json r, String lang) {
    final out = <Widget>[];
    final lg = (r['lang'] as String?) == 'ur' ? 'ur' : (r['lang'] == 'en' ? 'en' : lang);
    if (r['expression'] != null) out.add(Text('${r['expression']}', style: Theme.of(context).textTheme.displayMedium));
    if (r['count'] != null && r['icon'] is Map) {
      out.add(CountGrid(icon: '${asJson(r['icon'])['key']}', count: (r['count'] as num).toInt(), size: 52));
    } else if (r['icon'] is Map) {
      out.add(RoundThing(r['icon'], size: 110, lang: lg));
    }
    if (r['sequence'] is List) out.add(Wrap(alignment: WrapAlignment.center, spacing: 10, runSpacing: 10, crossAxisAlignment: WrapCrossAlignment.center, children: [for (final e in r['sequence'] as List) RoundThing(e, size: 56, lang: lg)]));
    if (r['audio_url'] != null) out.add(SoundButton(url: '${r['audio_url']}', size: 96));
    if (r['image_url'] != null) out.add(SizedBox(width: 200, height: 200, child: NetImage('${r['image_url']}', radius: 28)));
    if (r['target'] is Map && r['icon'] == null) {
      final t = asJson(r['target']);
      out.add(r['mode'] == 'find' ? Text(_name(t, lg), style: Theme.of(context).textTheme.displaySmall) : RoundThing(t, size: 110, lang: lg));
    }
    if (r['word'] is Map) out.add(RoundThing(r['word'], size: 90, lang: lg));
    if (r['given'] is Map) out.add(RoundThing(r['given'], size: 90, lang: lg));
    return out;
  }

  Widget _options(Json r, String lang) {
    final opts = (r['options'] as List?) ?? const [];
    final lg = (r['lang'] as String?) == 'ur' ? 'ur' : (r['lang'] == 'en' ? 'en' : lang);
    // 'name' mode asks for a word (what colour is this? how does this face feel?), so the choices are words
    final names = r['mode'] == 'name';
    return Wrap(spacing: 14, runSpacing: 14, alignment: WrapAlignment.center, children: [
      for (var i = 0; i < opts.length; i++)
        Opacity(
          opacity: _wrong.contains(i) ? .25 : 1,
          child: Material(
            color: _picked == i && _right ? KindoColors.sage : Colors.white,
            borderRadius: BorderRadius.circular(28),
            child: InkWell(
              borderRadius: BorderRadius.circular(28),
              onTap: _right || _wrong.contains(i)
                  ? null
                  : () {
                      final res = _run.answerChoice(i);
                      setState(() => _picked = i);
                      res.correct ? _right_() : _wrongTap(i);
                    },
              child: Container(
                constraints: const BoxConstraints(minWidth: 110, minHeight: 110),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(borderRadius: BorderRadius.circular(28), border: Border.all(color: KindoColors.line, width: 2)),
                alignment: Alignment.center,
                child: RoundThing(opts[i], size: 64, lang: lg, nameOnly: names, showName: r['icon'] == null && opts[i] is Map && (opts[i] as Map)['hex'] == null && r['mode'] == null),
              ),
            ),
          ),
        ),
    ]);
  }

  Widget _spell(Json r, KindoSession s) {
    final kb = s.keyboards['${r['keyboard']}'];
    if (kb == null) return TextButton(onPressed: _advance, child: Text(context.tr('Skip', 'چھوڑیں')));
    final target = '${r['target']}';
    return SpellBoard(
      key: ValueKey('${_run.index}-${_wrong.length}'),
      layout: kb,
      slots: target.replaceAll(RegExp('[\\s\\u200c\\u200d\\u064b-\\u065f\\u0670]'), '').runes.length,
      onSubmit: (typed) {
        final res = _run.answerSpelling(typed);
        res.correct ? _right_() : _wrongTap(100 + _wrong.length);
      },
    );
  }

  Widget _orderRound(Json r, String lang) {
    final steps = asJsonList(r['steps']);
    return Column(children: [
      Wrap(spacing: 14, runSpacing: 14, alignment: WrapAlignment.center, children: [
        for (final st in steps)
          Opacity(
            opacity: _order.contains('${st['key']}') ? .4 : 1,
            child: Material(
              color: Colors.white,
              borderRadius: BorderRadius.circular(28),
              child: InkWell(
                borderRadius: BorderRadius.circular(28),
                onTap: _right || _order.contains('${st['key']}')
                    ? null
                    : () {
                        setState(() => _order.add('${st['key']}'));
                        if (_order.length == steps.length) {
                          final res = _run.answerOrder(_order);
                          if (res.correct) {
                            _right_();
                          } else {
                            // wrong order: start this one again, gently
                            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.tr('Nearly! Try again', 'تقریباً! دوبارہ کوشش کریں')), duration: const Duration(seconds: 2)));
                            Future<void>.delayed(const Duration(milliseconds: 700), () => mounted ? setState(() => _order = []) : null);
                          }
                        }
                      },
                child: Container(
                  width: 130,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(borderRadius: BorderRadius.circular(28), border: Border.all(color: KindoColors.line, width: 2)),
                  child: Column(children: [
                    Stack(alignment: Alignment.topRight, children: [ArtIcon('${st['key']}', size: 70), if (_order.contains('${st['key']}')) CircleAvatar(radius: 14, backgroundColor: KindoColors.ink, child: Text('${_order.indexOf('${st['key']}') + 1}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)))]),
                    const SizedBox(height: 6),
                    Text(_name(st, lang), textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w700)),
                  ]),
                ),
              ),
            ),
          ),
      ]),
    ]);
  }

  Widget _memory(String lang) {
    final b = _board!;
    return GridView.count(
      crossAxisCount: b.cards.length <= 6 ? 3 : 4,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      children: [
        for (var i = 0; i < b.cards.length; i++)
          Material(
            color: b.matched.contains(i) ? KindoColors.sage.withValues(alpha: .4) : b.faceUp.contains(i) ? Colors.white : KindoColors.lavender,
            borderRadius: BorderRadius.circular(22),
            child: InkWell(
              borderRadius: BorderRadius.circular(22),
              onTap: () {
                final slow = context.read<KindoSession>().currentChild!.support.slow;
                final res = b.flip(i);
                setState(() {});
                if (res == 'miss') {
                  Future<void>.delayed(Duration(milliseconds: slow ? 1600 : 900), () {
                    if (!mounted) return;
                    setState(b.resolve);
                  });
                }
                if (b.done) {
                  _run.finishMemory(b.mistakes);
                  Future<void>.delayed(const Duration(milliseconds: 600), () => mounted ? _advance() : null);
                }
              },
              child: Center(child: b.matched.contains(i) || b.faceUp.contains(i) ? RoundThing(b.cards[i], size: 52, lang: lang) : const Icon(Icons.help_outline_rounded, size: 36)),
            ),
          ),
      ],
    );
  }
}
