import 'dart:math';

import 'tracing_engine.dart';

/// Simple geometric outlines to trace: the digits 0 to 9, the English capitals A to Z, and four Urdu letters with dots.
///
/// These are plain drawn shapes for testing the tracing engine and for a first release. They are NOT calligraphic Nastaliq outlines:
/// the final Urdu letter shapes are design content and can be added here (or loaded from the server later) without changing any code
/// that uses [StrokeLibrary.lookup].
class StrokeLibrary {
  const StrokeLibrary._();

  /// An arc of an ellipse as points. Angles are degrees: 0 is right, 90 is down, 180 is left, 270 is up (y points down).
  /// [from] may be larger than [to] to draw the other way round.
  static List<Pt> _arc(double cx, double cy, double rx, double ry, double from, double to, [int n = 16]) =>
      [for (var i = 0; i <= n; i++) Pt(cx + rx * cos((from + (to - from) * i / n) * pi / 180), cy + ry * sin((from + (to - from) * i / n) * pi / 180))];

  static List<Pt> _l(List<double> xy) => [for (var i = 0; i < xy.length; i += 2) Pt(xy[i], xy[i + 1])];

  static final Map<String, GlyphShape> _glyphs = _build();

  /// The shape for a letter or digit, or null when there is none. English is looked up case-insensitively.
  static GlyphShape? lookup(String letter) {
    if (letter.isEmpty) return null;
    return _glyphs[letter] ?? _glyphs[letter.toUpperCase()];
  }

  /// Everything that can be traced.
  static List<String> get available => _glyphs.keys.toList();

  static Map<String, GlyphShape> _build() {
    final g = <String, List<List<Pt>>>{
      '0': [_arc(.5, .5, .28, .38, 270, 630, 28)],
      '1': [_l([.38, .25, .55, .1, .55, .9])],
      '2': [[..._arc(.5, .32, .27, .22, 200, 400, 14), ..._l([.25, .9, .78, .9])]],
      '3': [[..._arc(.48, .3, .25, .2, 210, 450, 12), ..._arc(.48, .7, .28, .22, 270, 510, 14)]],
      '4': [_l([.68, .9, .68, .1, .18, .65, .85, .65])],
      '5': [_l([.75, .1, .32, .1, .28, .45]), _arc(.5, .67, .27, .24, 250, 510, 16)],
      '6': [[..._l([.68, .12, .4, .35, .28, .65]), ..._arc(.5, .68, .24, .22, 180, 540, 20)]],
      '7': [_l([.2, .1, .8, .1, .4, .9])],
      '8': [_arc(.5, .3, .22, .2, 270, 630, 20), _arc(.5, .69, .27, .22, 270, 630, 20)],
      '9': [_arc(.5, .32, .24, .22, 0, 360, 20), _l([.74, .32, .6, .9])],
      'A': [_l([.2, .9, .5, .1, .8, .9]), _l([.32, .62, .68, .62])],
      'B': [_l([.28, .1, .28, .9]), _arc(.28, .3, .3, .2, 270, 450), _arc(.28, .7, .35, .2, 270, 450)],
      'C': [_arc(.55, .5, .32, .4, -40, -320, 22)],
      'D': [_l([.25, .1, .25, .9]), _arc(.25, .5, .5, .4, -90, 90, 18)],
      'E': [_l([.75, .1, .28, .1, .28, .9, .75, .9]), _l([.28, .5, .65, .5])],
      'F': [_l([.75, .1, .28, .1, .28, .9]), _l([.28, .5, .65, .5])],
      'G': [[..._arc(.52, .5, .32, .4, -40, -360, 24), ..._l([.84, .5, .55, .5])]],
      'H': [_l([.27, .1, .27, .9]), _l([.73, .1, .73, .9]), _l([.27, .5, .73, .5])],
      'I': [_l([.5, .1, .5, .9]), _l([.3, .1, .7, .1]), _l([.3, .9, .7, .9])],
      'J': [[..._l([.62, .1, .62, .7]), ..._arc(.4, .7, .22, .2, 0, 150, 10)]],
      'K': [_l([.28, .1, .28, .9]), _l([.75, .1, .28, .55, .78, .9])],
      'L': [_l([.3, .1, .3, .9, .75, .9])],
      'M': [_l([.2, .9, .2, .1, .5, .6, .8, .1, .8, .9])],
      'N': [_l([.25, .9, .25, .1, .75, .9, .75, .1])],
      'O': [_arc(.5, .5, .32, .4, 270, 630, 28)],
      'P': [_l([.28, .9, .28, .1]), _arc(.28, .32, .38, .22, -90, 90, 14)],
      'Q': [_arc(.5, .5, .32, .4, 270, 630, 28), _l([.58, .62, .8, .9])],
      'R': [_l([.28, .9, .28, .1]), _arc(.28, .32, .38, .22, -90, 90, 14), _l([.4, .54, .75, .9])],
      'S': [[..._arc(.5, .3, .26, .2, -30, -270, 12), ..._arc(.5, .7, .28, .2, -90, 150, 12)]],
      'T': [_l([.2, .1, .8, .1]), _l([.5, .1, .5, .9])],
      'U': [[..._l([.25, .1, .25, .6]), ..._arc(.5, .6, .25, .3, 180, 0, 12), ..._l([.75, .6, .75, .1])]],
      'V': [_l([.2, .1, .5, .9, .8, .1])],
      'W': [_l([.1, .1, .3, .9, .5, .35, .7, .9, .9, .1])],
      'X': [_l([.2, .1, .8, .9]), _l([.8, .1, .2, .9])],
      'Y': [_l([.2, .1, .5, .5, .8, .1]), _l([.5, .5, .5, .9])],
      'Z': [_l([.2, .1, .8, .1, .2, .9, .8, .9])],
    };
    final out = <String, GlyphShape>{for (final e in g.entries) e.key: GlyphShape(e.key, e.value)};
    // Urdu: a tall stroke and three letters made of the same bowl with different dots (drawn from the right, as Urdu is written)
    final bowl = _arc(.5, .42, .4, .36, 0, 180, 18);
    out['ا'] = GlyphShape('ا', [_l([.55, .1, .45, .9])]);
    out['ب'] = GlyphShape('ب', [bowl], dots: [const Pt(.5, .9)]);
    out['پ'] = GlyphShape('پ', [bowl], dots: [const Pt(.38, .9), const Pt(.62, .9), const Pt(.5, .8)]);
    out['ت'] = GlyphShape('ت', [bowl], dots: [const Pt(.4, .2), const Pt(.6, .2)]);

    return out;
  }
}
