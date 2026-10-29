import 'dart:async';
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import 'needs.dart';

/// Counts how long the child uses the app and, when the parent's screen time rules say it is time to stop, shows the "cool time" screen
/// over everything. It works with no connection: the rules arrive with the child's synced data and the tally is kept on the device.
/// Parents give extra minutes from their own app; "check again" picks that up.
class ScreenTimeGuard extends ChangeNotifier with WidgetsBindingObserver {
  ScreenTimeGuard({required this.navigator, DateTime Function()? clock, this.tickEvery = const Duration(seconds: 5), this.reportEvery = const Duration(minutes: 1)}) : _clock = clock ?? DateTime.now;

  final GlobalKey<NavigatorState> navigator;
  final DateTime Function() _clock;
  final Duration tickEvery;
  final Duration reportEvery;

  KindoSession? _session;
  ScreenTimeTracker? _tracker0;
  ScreenTimeTracker get _tracker => _tracker0!;
  bool _observing = false;
  Route<void>? _route;
  Timer? _timer;
  int _loadedFor = -1;
  bool _foreground = true;
  bool _showing = false;
  DateTime _lastReport = DateTime.fromMillisecondsSinceEpoch(0);
  ScreenTimeStatus _status = const ScreenTimeStatus(ScreenLock.none, 1 << 30);

  ScreenTimeStatus get status => _status;
  bool get locked => _status.locked;

  /// Called whenever the session changes (sign-in, child chosen, new rules synced).
  void attach(KindoSession s) {
    _session = s;
    _tracker0 ??= ScreenTimeTracker(s.store);
    if (!_observing) {
      _observing = true;
      WidgetsBinding.instance.addObserver(this);
    }
    _timer ??= Timer.periodic(tickEvery, (_) => tick(tickEvery));
    unawaited(_refresh());
  }

  Child? get _child => _session?.signedIn == true ? _session!.currentChild : null;

  Future<void> _refresh() async {
    final c = _child;
    if (c == null) {
      _status = const ScreenTimeStatus(ScreenLock.none, 1 << 30);
      _sync();
      return;
    }
    if (_loadedFor != c.id) {
      _loadedFor = c.id;
      await _tracker.load(c.id, _clock());
    }
    _status = _tracker.status(ScreenTimeRules.of(c), _clock());
    _sync();
  }

  /// One step of the clock (public so tests can drive it). Counts [elapsed] only while the app is open and not locked.
  Future<void> tick(Duration elapsed) async {
    final c = _child;
    if (c == null) return;
    if (_loadedFor != c.id) await _refresh();
    final rules = ScreenTimeRules.of(c);
    final now = _clock();
    if (rules != null && _foreground && !_status.locked) {
      await _tracker.add(elapsed.inSeconds, now);
    }
    _status = _tracker.status(rules, now);
    if (rules != null && now.difference(_lastReport) >= reportEvery) await _report(c);
    _sync();
  }

  Future<void> _report(Child c) async {
    final n = _tracker.nextReport();
    _lastReport = _clock();
    if (n < 1) return;
    await _tracker.markReported(n);
    await _session!.queue('POST', '/children/${c.id}/screen-time/usage', body: {'seconds': n, 'date': ScreenTimeTracker.dayOf(_clock())}, keyPrefix: 'screen');
  }

  /// Ask the server how today stands (a parent may have given extra minutes).
  Future<bool> checkAgain() async {
    final c = _child;
    if (c == null) return false;
    try {
      final r = await _session!.api.screenTime(c.id);
      final t = asJson(r['today']);
      await _tracker.applyServer(usedSeconds: (t['used_seconds'] as num?)?.toInt() ?? 0, bonusSeconds: (t['bonus_seconds'] as num?)?.toInt() ?? 0);
    } on ApiException {
      // no connection, or screen time was switched off: the synced rules decide
    }
    await _refresh();
    return !_status.locked;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _foreground = state == AppLifecycleState.resumed;
    if (_foreground) unawaited(checkAgain());
  }

  void _sync() {
    final nav = navigator.currentState;
    if (_status.locked && !_showing && nav != null) {
      _showing = true;
      final route = PageRouteBuilder<void>(opaque: true, pageBuilder: (_, _, _) => ChangeNotifierProvider<ScreenTimeGuard>.value(value: this, child: const CoolTimeScreen()), transitionsBuilder: (_, a, _, child) => FadeTransition(opacity: a, child: child));
      _route = route;
      nav.push(route);
    } else if (!_status.locked && _showing && nav != null) {
      _showing = false;
      if (_route != null) nav.removeRoute(_route!);
      _route = null;
    }
    notifyListeners();
  }

  @override
  void dispose() {
    _timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }
}

/// The calm screen: no games, no lessons, a gentle message and things to do away from the screen. The needs board stays reachable.
class CoolTimeScreen extends StatefulWidget {
  const CoolTimeScreen({super.key});

  @override
  State<CoolTimeScreen> createState() => _CoolTimeScreenState();
}

class _CoolTimeScreenState extends State<CoolTimeScreen> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(seconds: 6));
  bool _checking = false;

  static const _ideas = [
    ('🧸', 'Play with your toys', 'اپنے کھلونوں سے کھیلو'),
    ('🎨', 'Draw a picture', 'تصویر بناؤ'),
    ('🏃', 'Run and jump outside', 'باہر دوڑو اور اچھلو'),
    ('📚', 'Read a story with someone', 'کسی کے ساتھ کہانی پڑھو'),
    ('🥤', 'Have a drink of water', 'پانی پیو'),
    ('🤗', 'Give someone a hug', 'کسی کو گلے لگاؤ'),
  ];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // a gently floating moon, unless the device asks for less motion or this child needs things calm
    final still = MediaQuery.disableAnimationsOf(context) || (context.read<KindoSession>().currentChild?.support.quiet ?? false);
    if (still) {
      _c.stop();
    } else if (!_c.isAnimating) {
      _c.repeat(reverse: true);
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  Future<void> _again() async {
    setState(() => _checking = true);
    final open = await context.read<ScreenTimeGuard>().checkAgain();
    if (!mounted) return;
    setState(() => _checking = false);
    if (!open) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.tr('Still cool time. Ask a grown-up.', 'ابھی آرام کا وقت ہے۔ بڑوں سے پوچھیں۔'))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final guard = context.watch<ScreenTimeGuard>();
    final quiet = guard.status.lock == ScreenLock.quiet;
    final idea = _ideas[Random(DateTime.now().day).nextInt(_ideas.length)];
    return PopScope(
      canPop: false,
      child: LangDirection(
        child: Scaffold(
          backgroundColor: const Color(0xFF1F2A4D),
          body: Container(
            decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0xFF1F2A4D), Color(0xFF4B3F8F), Color(0xFF8B6FC4)])),
            child: SafeArea(
              child: Center(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(28),
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 520),
                    child: Column(mainAxisSize: MainAxisSize.min, children: [
                      AnimatedBuilder(
                        animation: _c,
                        builder: (_, _) => Transform.translate(offset: Offset(0, -10 * _c.value), child: const Text('🌙', style: TextStyle(fontSize: 110))),
                      ),
                      const Text('✨  ⭐  ✨', style: TextStyle(fontSize: 28)),
                      const SizedBox(height: 18),
                      Text(
                        quiet ? context.tr('Sleepy time', 'سونے کا وقت') : context.tr('Cool time!', 'آرام کا وقت!'),
                        style: Theme.of(context).textTheme.headlineLarge?.copyWith(color: Colors.white, fontWeight: FontWeight.w800),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 10),
                      Text(
                        quiet
                            ? context.tr('The screen is resting now. See you in the morning.', 'اسکرین ابھی آرام کر رہی ہے۔ صبح ملتے ہیں۔')
                            : context.tr('You played so well today. Your eyes and your body need a rest from the screen.', 'آج آپ نے بہت اچھا کھیلا۔ آپ کی آنکھوں اور جسم کو اسکرین سے آرام چاہیے۔'),
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(color: Colors.white70),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 24),
                      Container(
                        padding: const EdgeInsets.all(18),
                        decoration: BoxDecoration(color: Colors.white.withValues(alpha: .14), borderRadius: BorderRadius.circular(28)),
                        child: Row(mainAxisSize: MainAxisSize.min, children: [
                          Text(idea.$1, style: const TextStyle(fontSize: 44)),
                          const SizedBox(width: 14),
                          Flexible(child: Text(context.tr(idea.$2, idea.$3), style: Theme.of(context).textTheme.titleLarge?.copyWith(color: Colors.white))),
                        ]),
                      ),
                      const SizedBox(height: 28),
                      // a child who needs something can always say so
                      FilledButton.icon(
                        style: FilledButton.styleFrom(backgroundColor: KindoColors.butter, foregroundColor: Colors.black87, minimumSize: const Size(220, 56)),
                        onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const LangDirection(child: NeedsBoard()))),
                        icon: const Icon(Icons.record_voice_over_rounded),
                        label: Text(context.tr('I need...', 'مجھے چاہیے...')),
                      ),
                      const SizedBox(height: 12),
                      TextButton(
                        onPressed: _checking ? null : _again,
                        child: Text(_checking ? '...' : context.tr('Grown-ups: check again', 'بڑے: دوبارہ دیکھیں'), style: const TextStyle(color: Colors.white70)),
                      ),
                    ]),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
