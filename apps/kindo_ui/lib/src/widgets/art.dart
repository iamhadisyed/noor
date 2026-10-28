import 'dart:math';

import 'package:flutter/material.dart';

import '../theme.dart';

/// The pictures the games ask for by name (`apple`, `circle`, `happy` ...). The server sends only names; these are simple original drawings
/// for shapes and faces, and emoji for everyday objects until the designer supplies final artwork (replace [_emoji] or draw in [ArtIcon]).
class ArtIcon extends StatelessWidget {
  const ArtIcon(this.name, {super.key, this.size = 72, this.color});
  final String name;
  final double size;
  final Color? color;

  static const _shapes = {'circle', 'square', 'triangle', 'star', 'heart'};
  static const _faces = {'happy', 'sad', 'angry', 'scared', 'tired', 'surprised'};

  static const _emoji = {
    'apple': '🍎', 'banana': '🍌', 'orange': '🍊', 'grapes': '🍇', 'mango': '🥭',
    'cat': '🐱', 'dog': '🐶', 'cow': '🐮', 'fish': '🐟', 'bird': '🐦',
    'car': '🚗', 'bus': '🚌', 'bike': '🚲', 'plane': '✈️', 'boat': '⛵',
    'ball': '⚽', 'kite': '🪁', 'doll': '🪆', 'drum': '🥁', 'blocks': '🧱',
    'wake_up': '⏰', 'wash_face': '🧼', 'brush_teeth': '🪥', 'eat_breakfast': '🥣', 'get_dressed': '👕', 'pack_bag': '🎒',
    'bath': '🛁', 'pajamas': '🩳', 'story': '📖', 'sleep': '😴',
    'wet': '💧', 'soap': '🧼', 'scrub': '👐', 'rinse': '🚿', 'dry': '🧻',
    'wash_hands': '🧼', 'sit_down': '🪑', 'eat': '🍽️', 'drink': '🥛', 'clean_up': '🧹',
  };

  @override
  Widget build(BuildContext context) {
    if (_shapes.contains(name) || _faces.contains(name)) {
      return SizedBox(width: size, height: size, child: CustomPaint(painter: _ArtPainter(name, color ?? _defaultColor(name))));
    }
    final e = _emoji[name];
    if (e != null) return SizedBox(width: size, height: size, child: FittedBox(child: Text(e, style: const TextStyle(fontSize: 100))));
    return Container(width: size, height: size, decoration: const BoxDecoration(color: KindoColors.lavenderTint, shape: BoxShape.circle), alignment: Alignment.center, child: Text(name.isEmpty ? '?' : name[0].toUpperCase(), style: TextStyle(fontSize: size * .45, fontWeight: FontWeight.w800)));
  }

  static Color _defaultColor(String n) => switch (n) {
        'circle' => const Color(0xFF5FA8E8),
        'square' => const Color(0xFF6DC08A),
        'triangle' => const Color(0xFFF0A35E),
        'star' => const Color(0xFFF2C94C),
        'heart' => const Color(0xFFF08FB0),
        'happy' => const Color(0xFFF2C94C),
        'sad' => const Color(0xFF7FB0E0),
        'angry' => const Color(0xFFE8604F),
        'scared' => const Color(0xFFB79BE0),
        'tired' => const Color(0xFFA9A5C4),
        _ => const Color(0xFFF2A65A),
      };
}

class _ArtPainter extends CustomPainter {
  _ArtPainter(this.name, this.color);
  final String name;
  final Color color;

  @override
  void paint(Canvas canvas, Size s) {
    final p = Paint()..color = color..style = PaintingStyle.fill;
    final w = s.width, h = s.height;
    switch (name) {
      case 'circle':
        canvas.drawCircle(Offset(w / 2, h / 2), w * .42, p);
      case 'square':
        canvas.drawRRect(RRect.fromRectAndRadius(Rect.fromLTWH(w * .12, h * .12, w * .76, h * .76), Radius.circular(w * .08)), p);
      case 'triangle':
        canvas.drawPath(Path()..moveTo(w / 2, h * .1)..lineTo(w * .92, h * .88)..lineTo(w * .08, h * .88)..close(), p);
      case 'star':
        final path = Path();
        for (var i = 0; i < 10; i++) {
          final r = (i.isEven ? .46 : .2) * w;
          final a = -pi / 2 + i * pi / 5;
          final pt = Offset(w / 2 + r * cos(a), h / 2 + r * sin(a));
          i == 0 ? path.moveTo(pt.dx, pt.dy) : path.lineTo(pt.dx, pt.dy);
        }
        canvas.drawPath(path..close(), p);
      case 'heart':
        final path = Path()
          ..moveTo(w / 2, h * .86)
          ..cubicTo(w * -.05, h * .5, w * .15, h * .05, w / 2, h * .32)
          ..cubicTo(w * .85, h * .05, w * 1.05, h * .5, w / 2, h * .86)
          ..close();
        canvas.drawPath(path, p);
      default:
        _face(canvas, s, p);
    }
  }

  void _face(Canvas canvas, Size s, Paint fill) {
    final w = s.width, h = s.height;
    canvas.drawCircle(Offset(w / 2, h / 2), w * .46, fill);
    final ink = Paint()..color = KindoColors.ink..style = PaintingStyle.fill;
    final line = Paint()..color = KindoColors.ink..style = PaintingStyle.stroke..strokeWidth = w * .045..strokeCap = StrokeCap.round;
    void eyes({double dy = .4, double r = .05}) {
      canvas.drawCircle(Offset(w * .35, h * dy), w * r, ink);
      canvas.drawCircle(Offset(w * .65, h * dy), w * r, ink);
    }

    switch (name) {
      case 'happy':
        eyes();
        canvas.drawArc(Rect.fromCenter(center: Offset(w / 2, h * .56), width: w * .46, height: h * .3), .2, pi - .4, false, line);
      case 'sad':
        eyes();
        canvas.drawArc(Rect.fromCenter(center: Offset(w / 2, h * .72), width: w * .4, height: h * .24), pi + .3, pi - .6, false, line);
        canvas.drawOval(Rect.fromCenter(center: Offset(w * .7, h * .56), width: w * .06, height: h * .1), Paint()..color = const Color(0xFF5FA8E8));
      case 'angry':
        eyes(dy: .45);
        canvas.drawLine(Offset(w * .26, h * .3), Offset(w * .42, h * .38), line);
        canvas.drawLine(Offset(w * .74, h * .3), Offset(w * .58, h * .38), line);
        canvas.drawLine(Offset(w * .36, h * .72), Offset(w * .64, h * .72), line);
      case 'scared':
        eyes(r: .075);
        canvas.drawOval(Rect.fromCenter(center: Offset(w / 2, h * .7), width: w * .14, height: h * .18), ink);
      case 'tired':
        canvas.drawLine(Offset(w * .26, h * .42), Offset(w * .42, h * .42), line);
        canvas.drawLine(Offset(w * .58, h * .42), Offset(w * .74, h * .42), line);
        canvas.drawLine(Offset(w * .4, h * .7), Offset(w * .6, h * .7), line);
      case 'surprised':
        eyes(r: .06);
        canvas.drawOval(Rect.fromCenter(center: Offset(w / 2, h * .68), width: w * .16, height: h * .2), ink);
    }
  }

  @override
  bool shouldRepaint(_ArtPainter old) => old.name != name || old.color != color;
}

/// A group of [count] pictures, in neat rows, to count.
class CountGrid extends StatelessWidget {
  const CountGrid({super.key, required this.icon, required this.count, this.size = 44});
  final String icon;
  final int count;
  final double size;

  @override
  Widget build(BuildContext context) => Wrap(
        alignment: WrapAlignment.center,
        spacing: 8,
        runSpacing: 8,
        children: [for (var i = 0; i < count; i++) ArtIcon(icon, size: size)],
      );
}
