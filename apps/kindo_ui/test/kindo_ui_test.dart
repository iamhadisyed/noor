import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kindo_ui/kindo_ui.dart';

const _english = KeyboardLayout(name: 'english', letters: ['a', 'b', 'c', 'd', 'e', 'f', 'g'], direction: 'ltr', columns: 4);
const _urdu = KeyboardLayout(name: 'urdu', letters: ['ا', 'ب', 'پ', 'ت'], direction: 'rtl', columns: 4);

Widget _wrap(Widget child) => MaterialApp(home: Scaffold(body: SingleChildScrollView(child: child)));

void main() {
  group('SpellBoard', () {
    testWidgets('keys are in alphabet order and typing fills the boxes', (t) async {
      String? sent;
      await t.pumpWidget(_wrap(SpellBoard(layout: _english, slots: 3, onSubmit: (w) => sent = w)));
      final xs = ['a', 'b', 'c', 'd'].map((k) => t.getTopLeft(find.text(k)).dx).toList();
      expect(xs[0] < xs[1] && xs[1] < xs[2] && xs[2] < xs[3], isTrue);
      expect(t.getTopLeft(find.text('e')).dy > t.getTopLeft(find.text('a')).dy, isTrue, reason: 'the second row starts below the first');
      for (final k in ['c', 'a', 'b']) {
        await t.tap(find.text(k));
        await t.pump();
      }
      await t.tap(find.text('Done'));
      expect(sent, 'cab');
    });

    testWidgets('back removes the last letter and done does nothing when empty', (t) async {
      var calls = 0;
      await t.pumpWidget(_wrap(SpellBoard(layout: _english, onSubmit: (_) => calls++)));
      await t.tap(find.text('Done'), warnIfMissed: false);
      expect(calls, 0);
      await t.tap(find.text('a'));
      await t.tap(find.text('b'));
      await t.pump();
      await t.tap(find.text('Back'));
      await t.pump();
      await t.tap(find.text('Done'));
      expect(calls, 1);
    });

    testWidgets('the Urdu keyboard reads from the right', (t) async {
      await t.pumpWidget(_wrap(SpellBoard(layout: _urdu, onSubmit: (_) {})));
      expect(t.getTopLeft(find.text('ا')).dx > t.getTopLeft(find.text('ب')).dx, isTrue);
      expect(t.getTopLeft(find.text('ب')).dx > t.getTopLeft(find.text('پ')).dx, isTrue);
    });
  });

  group('TraceCanvas', () {
    testWidgets('drawing along the shape succeeds and the verdict is reported', (t) async {
      TraceResult? result;
      bool? assisted;
      final glyph = StrokeLibrary.lookup('1')!;
      await t.pumpWidget(_wrap(Center(child: TraceCanvas(glyph: glyph, support: const Support(magnetPx: 30), size: 300, onResult: (r, a) { result = r; assisted = a; }))));
      final box = t.getTopLeft(find.byType(TraceCanvas));
      final pts = glyph.strokes.first.map((p) => box + Offset(p.x * 300, p.y * 300)).toList();
      final g = await t.startGesture(pts.first);
      for (final p in pts.skip(1)) {
        await g.moveTo(p);
      }
      await g.up();
      await t.pump();
      await t.tap(find.text('Done'));
      expect(result, isNotNull);
      expect(result!.success, isTrue);
      expect(assisted, isTrue, reason: 'the dotted guide was showing');
    });

    testWidgets('a scribble in the wrong place does not succeed, and Again clears it', (t) async {
      TraceResult? result;
      final glyph = StrokeLibrary.lookup('1')!;
      await t.pumpWidget(_wrap(Center(child: TraceCanvas(glyph: glyph, support: const Support(magnetPx: 15, guide: 'none'), size: 300, onResult: (r, a) => result = r))));
      final box = t.getTopLeft(find.byType(TraceCanvas));
      final g = await t.startGesture(box + const Offset(20, 280));
      await g.moveTo(box + const Offset(60, 285));
      await g.moveTo(box + const Offset(120, 280));
      await g.up();
      await t.pump();
      await t.tap(find.text('Done'));
      expect(result!.success, isFalse);
      await t.tap(find.text('Again'));
      await t.pump();
      expect(t.state<TraceCanvasState>(find.byType(TraceCanvas)).isEmpty, isTrue);
    });
  });

  test('a card picture uses the parent\'s own picture or the stock one', () {
    final stock = AacCard.fromJson({'id': 1, 'category': 'needs', 'english_label': 'Water', 'urdu_label': '', 'icon_path': 'aac/water.png'});
    expect(stock.iconPath, 'aac/water.png');
    expect(stock.custom, isFalse);
  });

  test('the media cache key changes only when the version code changes', () {
    final a = MediaCache.keyFor('https://k.test/api/v1/tasks/5/image?signature=abc&expires=1', 'v1');
    final b = MediaCache.keyFor('https://k.test/api/v1/tasks/5/image?signature=zzz&expires=9', 'v1');
    final c = MediaCache.keyFor('https://k.test/api/v1/tasks/5/image?signature=abc&expires=1', 'v2');
    expect(a, b, reason: 'a new signature must not refetch the same picture');
    expect(a, isNot(c));
  });
}
