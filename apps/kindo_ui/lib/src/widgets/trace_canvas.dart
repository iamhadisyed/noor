import 'package:flutter/material.dart';
import 'package:kindo_core/kindo_core.dart';

import '../theme.dart';

/// A square to trace a letter or number on. The guide (`none`, `dotted`, `arrows`) and the magnet pull come from the child's
/// support profile. When the child lifts their finger the stroke stays; tap the tick to have the try judged.
class TraceCanvas extends StatefulWidget {
  const TraceCanvas({super.key, required this.glyph, required this.support, required this.onResult, this.showGuide = true, this.size});
  final GlyphShape glyph;
  final Support support;

  /// Called with the verdict and whether the guide was showing (an "assisted" try).
  final void Function(TraceResult result, bool assisted) onResult;

  /// False for a try without help (the guide fades when `fade_assist` is on and the child is doing well).
  final bool showGuide;
  final double? size;

  @override
  State<TraceCanvas> createState() => TraceCanvasState();
}

class TraceCanvasState extends State<TraceCanvas> {
  static const _engine = TracingEngine();
  final List<List<Pt>> _strokes = [];
  double _side = 300;

  void clear() => setState(_strokes.clear);

  bool get isEmpty => _strokes.isEmpty;

  /// Judge what has been drawn.
  TraceResult check() => _engine.evaluate(userStrokes: _strokes, glyph: widget.glyph, canvasSize: _side, magnetPx: widget.support.magnetPx);

  void submit() {
    final r = check();
    widget.onResult(r, widget.showGuide && widget.support.guide != 'none');
  }

  Pt _snapped(Offset o) => _engine.snap(Pt(o.dx, o.dy), widget.glyph, _side, widget.support.magnetPx);

  @override
  void didUpdateWidget(TraceCanvas oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.glyph.id != widget.glyph.id) _strokes.clear();
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, c) {
      final side = widget.size ?? (c.maxWidth.isFinite ? c.maxWidth.clamp(180.0, 420.0) : 320.0);
      _side = side;
      final guide = widget.showGuide ? widget.support.guide : 'none';

      return Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: side,
          height: side,
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(28), border: Border.all(color: KindoColors.line, width: 2)),
          clipBehavior: Clip.antiAlias,
          child: GestureDetector(
            onPanStart: (d) => setState(() => _strokes.add([_snapped(d.localPosition)])),
            onPanUpdate: (d) => setState(() => _strokes.last.add(_snapped(d.localPosition))),
            onTapDown: (d) => setState(() => _strokes.add([_snapped(d.localPosition)])),
            child: CustomPaint(size: Size(side, side), painter: _TracePainter(widget.glyph, _strokes, guide, side)),
          ),
        ),
        const SizedBox(height: 14),
        Row(mainAxisSize: MainAxisSize.min, children: [
          OutlinedButton.icon(onPressed: _strokes.isEmpty ? null : clear, icon: const Icon(Icons.refresh_rounded), label: const Text('Again')),
          const SizedBox(width: 12),
          FilledButton.icon(onPressed: _strokes.isEmpty ? null : submit, icon: const Icon(Icons.check_rounded), label: const Text('Done')),
        ]),
      ]);
    });
  }
}

class _TracePainter extends CustomPainter {
  _TracePainter(this.glyph, this.strokes, this.guide, this.side);
  final GlyphShape glyph;
  final List<List<Pt>> strokes;
  final String guide;
  final double side;

  Path _path(List<Pt> pts) {
    final p = Path();
    for (var i = 0; i < pts.length; i++) {
      i == 0 ? p.moveTo(pts[i].x, pts[i].y) : p.lineTo(pts[i].x, pts[i].y);
    }
    return p;
  }

  @override
  void paint(Canvas canvas, Size size) {
    if (guide != 'none') {
      final dot = Paint()..color = KindoColors.lavender;
      for (final s in glyph.strokes) {
        final pts = s.map((p) => p.scaled(side)).toList();
        // dotted: little dots along the line
        final poly = TracingEngine.resample(pts, 36);
        for (final q in poly) {
          canvas.drawCircle(Offset(q.x, q.y), 4, dot);
        }
        if (guide == 'arrows' && poly.length > 4) {
          // a green dot where to start and a short line showing which way to go
          final a = poly.first, b = poly[4];
          final go = Paint()
            ..color = KindoColors.sageDeep
            ..strokeWidth = 5
            ..strokeCap = StrokeCap.round;
          canvas.drawLine(Offset(a.x, a.y), Offset(b.x, b.y), go);
          canvas.drawCircle(Offset(a.x, a.y), 11, Paint()..color = KindoColors.sageDeep);
        }
      }
      for (final d in glyph.dots) {
        canvas.drawCircle(Offset(d.x * side, d.y * side), 9, Paint()..color = KindoColors.lavender);
      }
    }
    final ink = Paint()
      ..color = KindoColors.ink
      ..strokeWidth = 12
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    for (final s in strokes) {
      if (s.length == 1) {
        canvas.drawCircle(Offset(s.first.x, s.first.y), 7, Paint()..color = KindoColors.ink);
      } else {
        canvas.drawPath(_path(s), ink);
      }
    }
  }

  @override
  bool shouldRepaint(_TracePainter old) => true;
}
