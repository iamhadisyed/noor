import 'package:flutter/material.dart';
import 'package:kindo_core/kindo_core.dart';

import '../theme.dart';

/// A slow bubble that grows on "breathe in", holds, and shrinks on "breathe out" (or just shows the cue for other calm activities).
/// There is no countdown shown: the step text and the bubble are all the child sees. [onDone] runs after the last cycle.
class BreathingBubble extends StatefulWidget {
  const BreathingBubble({super.key, required this.activity, required this.lang, required this.onDone, this.slow = false});
  final CalmActivity activity;
  final String lang;
  final void Function(int seconds) onDone;

  /// Stretch every step by half again for children who need a gentler pace.
  final bool slow;

  @override
  State<BreathingBubble> createState() => _BreathingBubbleState();
}

class _BreathingBubbleState extends State<BreathingBubble> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this);
  int _cycle = 0, _step = 0;
  bool _running = false;
  bool _stopped = false;
  int _elapsed = 0;

  List<ActivityStep> get _steps => widget.activity.steps;

  @override
  void initState() {
    super.initState();
    _c.value = .45;
  }

  Future<void> _run() async {
    setState(() => _running = true);
    for (_cycle = 0; _cycle < widget.activity.cycles && !_stopped; _cycle++) {
      for (_step = 0; _step < _steps.length && !_stopped; _step++) {
        final s = _steps[_step];
        final secs = widget.slow ? (s.seconds * 1.5).round() : s.seconds;
        if (mounted) setState(() {});
        final target = switch (s.cue) { 'grow' => 1.0, 'shrink' => .45, _ => _c.value };
        await _c.animateTo(target, duration: Duration(seconds: secs), curve: Curves.easeInOut);
        _elapsed += secs;
      }
    }
    if (mounted && !_stopped) widget.onDone(_elapsed);
  }

  @override
  void dispose() {
    _stopped = true;
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final step = _step < _steps.length ? _steps[_step] : null;
    final text = step == null ? '' : (widget.lang == 'ur' ? step.text.ur : step.text.en);

    return Column(mainAxisSize: MainAxisSize.min, children: [
      SizedBox(
        height: 280,
        child: Center(
          child: AnimatedBuilder(
            animation: _c,
            builder: (_, _) => Container(
              width: 280 * _c.value,
              height: 280 * _c.value,
              decoration: BoxDecoration(shape: BoxShape.circle, gradient: const RadialGradient(colors: [KindoColors.lilac, KindoColors.lavender]), boxShadow: [BoxShadow(color: KindoColors.lavender.withValues(alpha: .5), blurRadius: 40 * _c.value)]),
            ),
          ),
        ),
      ),
      const SizedBox(height: 12),
      SizedBox(height: 44, child: Text(_running ? text : '', style: Theme.of(context).textTheme.headlineSmall, textAlign: TextAlign.center)),
      const SizedBox(height: 12),
      if (!_running) FilledButton.icon(onPressed: _run, icon: const Icon(Icons.play_arrow_rounded), label: Text(widget.lang == 'ur' ? 'شروع کریں' : 'Start')),
    ]);
  }
}
