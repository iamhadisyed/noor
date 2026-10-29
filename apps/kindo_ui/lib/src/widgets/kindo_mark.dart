import 'dart:math' as math;

import 'package:flutter/material.dart';

/// The Kindo mark: a bold K with a small spark ("noor" means light). Drawn in code so it is sharp at any size and needs no image file.
/// [soft] gives the lavender tile used by Kindo Junior; the default is the dark tile used by Kindo Parent and the website.
class KindoMark extends StatelessWidget {
  const KindoMark({super.key, this.size = 72, this.soft = false});
  final double size;
  final bool soft;

  @override
  Widget build(BuildContext context) => Semantics(
        label: 'Kindo',
        image: true,
        child: SizedBox(width: size, height: size, child: CustomPaint(painter: _MarkPainter(soft))),
      );
}

class _MarkPainter extends CustomPainter {
  _MarkPainter(this.soft);
  final bool soft;

  @override
  void paint(Canvas canvas, Size size) {
    final k = size.width / 100;
    final tile = Paint()..color = soft ? const Color(0xFFCABAEF) : const Color(0xFF120C15);
    canvas.drawRRect(RRect.fromRectAndRadius(Offset.zero & size, Radius.circular(26 * k)), tile);

    final stroke = Paint()
      ..color = soft ? const Color(0xFF120C15) : const Color(0xFFE697F2)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 13 * k
      ..strokeCap = StrokeCap.round;
    canvas.drawLine(Offset(34 * k, 27 * k), Offset(34 * k, 73 * k), stroke);
    canvas.drawLine(Offset(40 * k, 52 * k), Offset(62 * k, 33 * k), stroke);
    canvas.drawLine(Offset(45 * k, 50 * k), Offset(68 * k, 73 * k), stroke);

    final spark = Path();
    for (var i = 0; i < 8; i++) {
      final a = math.pi / 4 * i - math.pi / 2;
      final r = (i.isEven ? 10.0 : 3.8) * k;
      final p = Offset(79 * k + math.cos(a) * r, 21 * k + math.sin(a) * r);
      i == 0 ? spark.moveTo(p.dx, p.dy) : spark.lineTo(p.dx, p.dy);
    }
    canvas.drawPath(spark..close(), Paint()..color = soft ? const Color(0xFFE697F2) : const Color(0xFFFFF0AD));
  }

  @override
  bool shouldRepaint(_MarkPainter old) => old.soft != soft;
}
