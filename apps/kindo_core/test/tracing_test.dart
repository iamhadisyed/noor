import 'dart:math';

import 'package:kindo_core/kindo_core.dart';
import 'package:test/test.dart';

const engine = TracingEngine();
const size = 360.0;

/// A child's careful stroke: the reference outline, a little wobble, as canvas pixels.
List<Pt> trace(List<Pt> ref, {double wobble = 3, int seed = 1}) {
  final r = Random(seed);
  return TracingEngine.resample(ref.map((p) => p.scaled(size)).toList(), 60).map((p) => Pt(p.x + (r.nextDouble() - .5) * wobble, p.y + (r.nextDouble() - .5) * wobble)).toList();
}

List<List<Pt>> carefulTrace(GlyphShape g) => [for (final s in g.strokes) trace(s), for (final d in g.dots) [d.scaled(size)]];

void main() {
  group('StrokeLibrary', () {
    test('every digit, every English capital and four Urdu letters have a shape', () {
      for (final c in '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')) {
        expect(StrokeLibrary.lookup(c), isNotNull, reason: c);
      }
      for (final c in ['ا', 'ب', 'پ', 'ت']) {
        expect(StrokeLibrary.lookup(c), isNotNull, reason: c);
      }
      expect(StrokeLibrary.lookup('a')!.id, 'A', reason: 'lower case finds the capital');
      expect(StrokeLibrary.lookup('ز'), isNull);
      expect(StrokeLibrary.lookup(''), isNull);
    });

    test('every shape stays inside the square and has strokes with real length', () {
      for (final id in StrokeLibrary.available) {
        final g = StrokeLibrary.lookup(id)!;
        expect(g.strokes, isNotEmpty, reason: id);
        for (final s in g.strokes) {
          expect(s.length, greaterThan(1), reason: id);
          for (final p in s) {
            expect(p.x, inInclusiveRange(-0.01, 1.01), reason: '$id x');
            expect(p.y, inInclusiveRange(-0.01, 1.01), reason: '$id y');
          }
        }
        for (final d in g.dots) {
          expect(d.x, inInclusiveRange(0, 1));
          expect(d.y, inInclusiveRange(0, 1));
        }
      }
    });
  });

  group('TracingEngine', () {
    test('a careful trace of every shape succeeds', () {
      for (final id in StrokeLibrary.available) {
        final g = StrokeLibrary.lookup(id)!;
        final r = engine.evaluate(userStrokes: carefulTrace(g), glyph: g, canvasSize: size, magnetPx: 24);
        expect(r.success, isTrue, reason: '$id: coverage ${r.coverage}, deviation ${r.meanDeviationPx}, mistake ${r.mistake}');
        expect(r.mistake, isNull);
        expect(r.meanDeviationPx, lessThan(6));
      }
    });

    test('drawing nothing is an omission', () {
      final g = StrokeLibrary.lookup('A')!;
      final r = engine.evaluate(userStrokes: [], glyph: g, canvasSize: size, magnetPx: 24);
      expect(r.success, isFalse);
      expect(r.mistake, 'letter_omission');
    });

    test('a line far from the shape does not count', () {
      final g = StrokeLibrary.lookup('L')!;
      final scribble = [[for (var i = 0; i < 40; i++) Pt(300, 20.0 + i * 3)]];
      final r = engine.evaluate(userStrokes: scribble, glyph: g, canvasSize: size, magnetPx: 24);
      expect(r.success, isFalse);
      expect(r.coverage, lessThan(.4));
    });

    test('only the first stroke of a two-stroke letter is an omission', () {
      final g = StrokeLibrary.lookup('H')!;
      final r = engine.evaluate(userStrokes: [trace(g.strokes[0])], glyph: g, canvasSize: size, magnetPx: 24);
      expect(r.success, isFalse);
      expect(r.mistake, 'letter_omission');
    });

    test('a stroke drawn from the wrong end is an inversion', () {
      final g = StrokeLibrary.lookup('7')!;
      final backwards = trace(g.strokes[0]).reversed.toList();
      final r = engine.evaluate(userStrokes: [backwards], glyph: g, canvasSize: size, magnetPx: 24);
      expect(r.success, isFalse);
      expect(r.mistake, 'stroke_inversion');
    });

    test('missing or misplaced dots on an Urdu letter are a dot mistake', () {
      final g = StrokeLibrary.lookup('ب')!;
      final noDot = engine.evaluate(userStrokes: [trace(g.strokes[0])], glyph: g, canvasSize: size, magnetPx: 24);
      expect(noDot.success, isFalse);
      expect(noDot.mistake, 'dot_misplacement');

      final dotAbove = engine.evaluate(userStrokes: [trace(g.strokes[0]), [const Pt(180, 40)]], glyph: g, canvasSize: size, magnetPx: 24);
      expect(dotAbove.mistake, 'dot_misplacement', reason: 'bay has its dot below, ta has them above');

      final two = engine.evaluate(userStrokes: [trace(g.strokes[0]), [g.dots[0].scaled(size)], [const Pt(100, 320)]], glyph: g, canvasSize: size, magnetPx: 24);
      expect(two.mistake, 'dot_misplacement', reason: 'one dot too many');
    });

    test('a bigger magnet forgives a wobblier line', () {
      final g = StrokeLibrary.lookup('L')!;
      final shaky = [trace(g.strokes[0], wobble: 44)];
      final tight = engine.evaluate(userStrokes: shaky, glyph: g, canvasSize: size, magnetPx: 12);
      final loose = engine.evaluate(userStrokes: shaky, glyph: g, canvasSize: size, magnetPx: 40);
      expect(loose.coverage, greaterThanOrEqualTo(tight.coverage));
      expect(loose.success, isTrue);
    });

    test('the magnet pulls a nearby point onto the shape and leaves a far one alone', () {
      final g = StrokeLibrary.lookup('I')!;   // a vertical bar at x = 0.5
      final near = engine.snap(const Pt(190, 200), g, size, 24);
      expect(near.x, closeTo(180, .001));
      expect(near.y, closeTo(200, .001));
      final far = engine.snap(const Pt(300, 200), g, size, 24);
      expect(far, const Pt(300, 200));
    });

    test('resampling keeps the ends and spaces points evenly', () {
      final r = TracingEngine.resample([const Pt(0, 0), const Pt(10, 0)], 6);
      expect(r, hasLength(6));
      expect(r.first, const Pt(0, 0));
      expect(r.last, const Pt(10, 0));
      expect(r[2].x, closeTo(4, .01));
    });
  });

  group('PracticeLog', () {
    test('it builds a session the server accepts: counts add up and ranges hold', () {
      var t = DateTime(2026, 10, 28, 10);
      final log = PracticeLog(language: 'urdu', now: () => t);
      final g = StrokeLibrary.lookup('ب')!;
      final good = engine.evaluate(userStrokes: carefulTrace(g), glyph: g, canvasSize: size, magnetPx: 24);
      final bad = engine.evaluate(userStrokes: [trace(g.strokes[0])], glyph: g, canvasSize: size, magnetPx: 24);

      log.addTry('ب', bad, assisted: true);
      log.addTry('ب', good, assisted: true);
      log.addTry('ا', engine.evaluate(userStrokes: carefulTrace(StrokeLibrary.lookup('ا')!), glyph: StrokeLibrary.lookup('ا')!, canvasSize: size, magnetPx: 24), assisted: false);
      t = t.add(const Duration(minutes: 4));
      final body = log.build();

      expect(body['language'], 'urdu');
      expect(body['total_attempts'], 3);
      expect(body['successful_attempts'], 2);
      expect(body['duration_seconds'], 240);
      final letters = (body['letters'] as List).cast<Map>();
      final ba = letters.firstWhere((l) => l['letter'] == 'ب');
      expect(ba['attempts'], 2);
      expect(ba['successes'], 1);
      expect(ba['assisted'], 2);
      expect((body['mistakes'] as List).single['mistake_type'], 'dot_misplacement');
      expect(log.accuracyPct, 67);
      for (final l in letters) {
        expect(l['successes'] as int, lessThanOrEqualTo(l['attempts'] as int));
        expect(l['assisted'] as int, lessThanOrEqualTo(l['attempts'] as int));
      }
    });

    test('an empty log is empty', () {
      expect(PracticeLog(language: 'english').isEmpty, isTrue);
      expect(PracticeLog(language: 'english').accuracyPct, isNull);
    });
  });
}
