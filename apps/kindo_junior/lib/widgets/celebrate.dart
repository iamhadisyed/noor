import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';

/// A short, kind "well done". A child who likes quiet gets a calm tick and no bounce; others get a bigger star.
class Celebrate extends StatefulWidget {
  const Celebrate({super.key, required this.support, this.message, this.onDone});
  final Support support;
  final String? message;
  final VoidCallback? onDone;

  @override
  State<Celebrate> createState() => _CelebrateState();
}

class _CelebrateState extends State<Celebrate> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: Duration(milliseconds: widget.support.quiet ? 900 : 700))..forward();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final quiet = widget.support.quiet;
    final text = widget.message ?? (quiet ? context.tr('Good', 'اچھا') : context.tr('Well done!', 'شاباش!'));
    return AnimatedBuilder(
      animation: _c,
      builder: (context, _) {
        final t = Curves.easeOut.transform(_c.value);
        final scale = quiet ? 1.0 : 0.6 + 0.5 * t;
        return Opacity(
          opacity: t.clamp(0.0, 1.0),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Transform.scale(
              scale: scale,
              child: Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(color: quiet ? KindoColors.sage.withValues(alpha: .4) : KindoColors.butter, shape: BoxShape.circle),
                child: Icon(quiet ? Icons.check_rounded : Icons.star_rounded, size: 72, color: quiet ? KindoColors.sageDeep : const Color(0xFFF2B600)),
              ),
            ),
            const SizedBox(height: 14),
            Text(text, style: Theme.of(context).textTheme.headlineMedium, textAlign: TextAlign.center),
          ]),
        );
      },
    );
  }
}
