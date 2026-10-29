import 'dart:math';

import 'package:flutter/material.dart';

/// A short burst of coloured pieces for a right answer. A child who likes quiet gets nothing (the caller simply does not show it), and it
/// ends by itself after about a second, so it never keeps moving on the screen.
class ConfettiBurst extends StatefulWidget {
  const ConfettiBurst({super.key, this.pieces = 28, this.onDone});
  final int pieces;
  final VoidCallback? onDone;

  @override
  State<ConfettiBurst> createState() => _ConfettiBurstState();
}

class _ConfettiBurstState extends State<ConfettiBurst> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1100))
    ..forward().whenComplete(() => widget.onDone?.call());
  final _r = Random();
  late final List<_Piece> _pieces = List.generate(widget.pieces, (_) => _Piece(_r));

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => IgnorePointer(
        child: AnimatedBuilder(animation: _c, builder: (_, _) => CustomPaint(size: Size.infinite, painter: _ConfettiPainter(_pieces, _c.value))),
      );
}

class _Piece {
  _Piece(Random r)
      : angle = r.nextDouble() * 2 * pi,
        speed = .25 + r.nextDouble() * .6,
        spin = r.nextDouble() * 6,
        size = 6 + r.nextDouble() * 8,
        color = const [Color(0xFFF2C94C), Color(0xFFE697F2), Color(0xFF5FA8E8), Color(0xFF6DC08A), Color(0xFFF0A35E), Color(0xFFE8604F)][r.nextInt(6)];
  final double angle, speed, spin, size;
  final Color color;
}

class _ConfettiPainter extends CustomPainter {
  _ConfettiPainter(this.pieces, this.t);
  final List<_Piece> pieces;
  final double t;

  @override
  void paint(Canvas canvas, Size s) {
    final origin = Offset(s.width / 2, s.height * .42);
    final ease = Curves.easeOutCubic.transform(t);
    for (final p in pieces) {
      final d = ease * p.speed * s.shortestSide * .9;
      final pos = origin + Offset(cos(p.angle) * d, sin(p.angle) * d + t * t * s.height * .18);
      canvas.save();
      canvas.translate(pos.dx, pos.dy);
      canvas.rotate(p.spin * t * 3);
      canvas.drawRRect(RRect.fromRectAndRadius(Rect.fromCenter(center: Offset.zero, width: p.size, height: p.size * .55), const Radius.circular(2)), Paint()..color = p.color.withValues(alpha: (1 - t * t).clamp(0, 1)));
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(_ConfettiPainter old) => old.t != t;
}
