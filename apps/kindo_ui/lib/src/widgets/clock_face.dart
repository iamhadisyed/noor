import 'dart:math';

import 'package:flutter/material.dart';

import '../theme.dart';

/// An analogue clock showing [hour] (1 to 12) and [minute] (0 to 59). Big numbers, a short fat hour hand and a long thin minute hand.
class ClockFace extends StatelessWidget {
  const ClockFace({super.key, required this.hour, required this.minute, this.size = 220});
  final int hour;
  final int minute;
  final double size;

  @override
  Widget build(BuildContext context) => Semantics(
        label: 'Clock',
        child: SizedBox(width: size, height: size, child: CustomPaint(painter: _ClockPainter(hour, minute))),
      );
}

class _ClockPainter extends CustomPainter {
  _ClockPainter(this.hour, this.minute);
  final int hour;
  final int minute;

  @override
  void paint(Canvas canvas, Size s) {
    final c = Offset(s.width / 2, s.height / 2);
    final r = s.width / 2;
    canvas.drawCircle(c, r, Paint()..color = KindoColors.butter);
    canvas.drawCircle(c, r - 4, Paint()..color = Colors.white);
    canvas.drawCircle(
        c,
        r - 4,
        Paint()
          ..color = KindoColors.ink
          ..style = PaintingStyle.stroke
          ..strokeWidth = 5);
    for (var i = 1; i <= 12; i++) {
      final a = (i / 12) * 2 * pi - pi / 2;
      final tp = TextPainter(text: TextSpan(text: '$i', style: TextStyle(fontSize: r * .22, fontWeight: FontWeight.w800, color: KindoColors.ink)), textDirection: TextDirection.ltr)..layout();
      final p = c + Offset(cos(a), sin(a)) * (r * .78) - Offset(tp.width / 2, tp.height / 2);
      tp.paint(canvas, p);
    }
    for (var i = 0; i < 60; i++) {
      final a = (i / 60) * 2 * pi - pi / 2;
      final long = i % 5 == 0;
      canvas.drawLine(
          c + Offset(cos(a), sin(a)) * (r - (long ? 18 : 10)),
          c + Offset(cos(a), sin(a)) * (r - 6),
          Paint()
            ..color = KindoColors.gray
            ..strokeWidth = long ? 3 : 1.5);
    }
    final ha = ((hour % 12) + minute / 60) / 12 * 2 * pi - pi / 2;
    final ma = minute / 60 * 2 * pi - pi / 2;
    canvas.drawLine(
        c,
        c + Offset(cos(ha), sin(ha)) * (r * .5),
        Paint()
          ..color = KindoColors.ink
          ..strokeWidth = 9
          ..strokeCap = StrokeCap.round);
    canvas.drawLine(
        c,
        c + Offset(cos(ma), sin(ma)) * (r * .72),
        Paint()
          ..color = const Color(0xFFE8604F)
          ..strokeWidth = 5
          ..strokeCap = StrokeCap.round);
    canvas.drawCircle(c, 8, Paint()..color = KindoColors.ink);
  }

  @override
  bool shouldRepaint(_ClockPainter old) => old.hour != hour || old.minute != minute;
}
