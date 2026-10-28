import 'dart:math';

/// A point. Reference shapes use the unit square (0 to 1, y pointing down); a child's strokes use canvas pixels.
class Pt {
  const Pt(this.x, this.y);
  final double x;
  final double y;

  double distanceTo(Pt o) => sqrt((x - o.x) * (x - o.x) + (y - o.y) * (y - o.y));
  Pt scaled(double s) => Pt(x * s, y * s);

  @override
  bool operator ==(Object other) => other is Pt && other.x == x && other.y == y;

  @override
  int get hashCode => Object.hash(x, y);

  @override
  String toString() => 'Pt($x, $y)';
}

/// What to trace: one or more strokes, each drawn from first point to last, and (for Urdu letters) dots to tap.
class GlyphShape {
  const GlyphShape(this.id, this.strokes, {this.dots = const []});
  final String id;
  final List<List<Pt>> strokes;
  final List<Pt> dots;
}

/// How a try at one letter went.
class TraceResult {
  const TraceResult({required this.success, required this.coverage, required this.meanDeviationPx, this.mistake});

  /// Enough of the shape was followed, close enough, in the right direction, with its dots.
  final bool success;

  /// 0 to 1: how much of the shape the child's line reached.
  final double coverage;

  /// Average distance of the child's line from the shape, in pixels.
  final double meanDeviationPx;

  /// One of the server's mistake types (`letter_omission`, `stroke_inversion`, `dot_misplacement`), or null.
  final String? mistake;
}

/// Judges a child's tracing against a reference shape and helps them stay on it.
/// Pure geometry, so it is tested without a screen. `magnetPx` comes from the child's support profile.
class TracingEngine {
  const TracingEngine();

  static const _perStroke = 28;

  /// [n] points evenly spaced along the polyline (by length).
  static List<Pt> resample(List<Pt> pts, int n) {
    if (pts.length < 2 || n < 2) return List.of(pts);
    final total = _length(pts);
    if (total == 0) return List.filled(n, pts.first);
    final out = <Pt>[pts.first];
    final step = total / (n - 1);
    var need = step, acc = 0.0;
    for (var i = 1; i < pts.length; i++) {
      var a = pts[i - 1];
      final b = pts[i];
      var seg = a.distanceTo(b);
      while (acc + seg >= need && out.length < n - 1) {
        final t = (need - acc) / seg;
        final p = Pt(a.x + (b.x - a.x) * t, a.y + (b.y - a.y) * t);
        out.add(p);
        a = p;
        seg = a.distanceTo(b);
        acc = 0;
        need = step;
      }
      acc += seg;
    }
    out.add(pts.last);

    return out;
  }

  static double _length(List<Pt> p) {
    var l = 0.0;
    for (var i = 1; i < p.length; i++) {
      l += p[i - 1].distanceTo(p[i]);
    }
    return l;
  }

  /// Distance from [p] to the nearest point of a polyline.
  static double distanceToPolyline(Pt p, List<Pt> poly) {
    if (poly.isEmpty) return double.infinity;
    if (poly.length == 1) return p.distanceTo(poly.first);
    var best = double.infinity;
    for (var i = 1; i < poly.length; i++) {
      best = min(best, _distToSegment(p, poly[i - 1], poly[i]));
    }

    return best;
  }

  static double _distToSegment(Pt p, Pt a, Pt b) {
    final dx = b.x - a.x, dy = b.y - a.y;
    final len2 = dx * dx + dy * dy;
    if (len2 == 0) return p.distanceTo(a);
    final t = (((p.x - a.x) * dx + (p.y - a.y) * dy) / len2).clamp(0.0, 1.0);

    return p.distanceTo(Pt(a.x + dx * t, a.y + dy * t));
  }

  /// The magnet: if [p] is within [magnetPx] of the shape, the nearest point on it; otherwise [p] unchanged.
  Pt snap(Pt p, GlyphShape glyph, double canvasSize, int magnetPx) {
    Pt? best;
    var bestD = magnetPx.toDouble();
    for (final s in glyph.strokes) {
      final poly = s.map((q) => q.scaled(canvasSize)).toList();
      for (var i = 1; i < poly.length; i++) {
        final a = poly[i - 1], b = poly[i];
        final dx = b.x - a.x, dy = b.y - a.y;
        final len2 = dx * dx + dy * dy;
        final t = len2 == 0 ? 0.0 : (((p.x - a.x) * dx + (p.y - a.y) * dy) / len2).clamp(0.0, 1.0);
        final q = Pt(a.x + dx * t, a.y + dy * t);
        final d = p.distanceTo(q);
        if (d <= bestD) {
          bestD = d;
          best = q;
        }
      }
    }

    return best ?? p;
  }

  /// Judge the finished tries. [userStrokes] are canvas pixels; strokes shorter than a few pixels count as dot taps.
  TraceResult evaluate({required List<List<Pt>> userStrokes, required GlyphShape glyph, required double canvasSize, required int magnetPx}) {
    final tol = magnetPx.toDouble();
    final ref = [for (final s in glyph.strokes) resample(s.map((p) => p.scaled(canvasSize)).toList(), _perStroke)];
    final dotTaps = <Pt>[];
    final lines = <List<Pt>>[];
    for (final s in userStrokes) {
      if (s.isEmpty) continue;
      if (_length(s) < canvasSize * 0.06) {
        dotTaps.add(s.first);
      } else {
        lines.add(s);
      }
    }
    final all = [for (final l in lines) ...l];
    if (all.isEmpty) return const TraceResult(success: false, coverage: 0, meanDeviationPx: 0, mistake: 'letter_omission');

    var covered = 0, total = 0;
    for (final r in ref) {
      for (final p in r) {
        total++;
        if (all.any((u) => u.distanceTo(p) <= tol)) covered++;
      }
    }
    final coverage = total == 0 ? 1.0 : covered / total;
    final refPoly = [for (final r in ref) ...r];
    final dev = all.map((u) => ref.map((r) => distanceToPolyline(u, r)).reduce(min)).reduce((a, b) => a + b) / all.length;
    // `refPoly` keeps the analyzer honest about the flattened reference being used for direction below
    assert(refPoly.isNotEmpty);

    String? mistake;
    if (coverage < 0.4 || lines.length < glyph.strokes.length && coverage < 0.8) {
      mistake = 'letter_omission';
    } else if (_reversed(lines, ref)) {
      mistake = 'stroke_inversion';
    } else if (glyph.dots.isNotEmpty && !_dotsPlaced(dotTaps, glyph.dots, canvasSize, tol)) {
      mistake = 'dot_misplacement';
    }
    final success = mistake == null && coverage >= 0.8 && dev <= tol;

    return TraceResult(success: success, coverage: coverage, meanDeviationPx: dev, mistake: success ? null : (mistake ?? 'letter_omission'));
  }

  /// True when the child drew the main stroke from the wrong end.
  bool _reversed(List<List<Pt>> lines, List<List<Pt>> ref) {
    if (lines.isEmpty || ref.isEmpty) return false;
    // compare the longest drawn line with the reference stroke it follows most closely
    final u = lines.reduce((a, b) => _length(a) >= _length(b) ? a : b);
    final r = ref.reduce((a, b) => _length(a) >= _length(b) ? a : b);
    if (_length(u) < _length(r) * 0.5) return false;
    final fwd = u.first.distanceTo(r.first) + u.last.distanceTo(r.last);
    final rev = u.first.distanceTo(r.last) + u.last.distanceTo(r.first);

    return rev + 1e-6 < fwd * 0.7;
  }

  bool _dotsPlaced(List<Pt> taps, List<Pt> dots, double size, double tol) {
    final ref = dots.map((d) => d.scaled(size)).toList();
    if (taps.length < ref.length) return false;
    // every reference dot needs a tap near it, and no extra taps may sit far from all of them
    final near = ref.every((r) => taps.any((t) => t.distanceTo(r) <= tol * 1.6));
    final extras = taps.every((t) => ref.any((r) => t.distanceTo(r) <= tol * 1.6));

    return near && extras && taps.length == ref.length;
  }
}
