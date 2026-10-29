import 'dart:math';

import 'tracing_engine.dart';

/// Simple geometric outlines to trace: the digits 0 to 9, the English capitals A to Z and small letters a to z, and the Urdu alphabet (letters of one family share a shape and differ by their dots).
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
    return _glyphs[letter] ?? _glyphs[letter.toUpperCase()];   // a lower-case letter has its own shape; the capital is the fallback
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
    // Small letters. Each has its own simple shape (the tall ones reach the top, the others sit in the lower two thirds).
    final low = <String, List<List<Pt>>>{
      'a': [_arc(.45, .62, .22, .25, 270, 630, 22), _l([.67, .38, .67, .9])],
      'b': [_l([.3, .1, .3, .9]), _arc(.52, .65, .22, .25, 270, 630, 22)],
      'c': [_arc(.55, .62, .25, .27, -40, -320, 20)],
      'd': [_arc(.45, .62, .22, .25, 270, 630, 22), _l([.67, .1, .67, .9])],
      'e': [[..._l([.26, .62, .74, .62]), ..._arc(.5, .62, .24, .27, 0, -320, 20)]],
      'f': [_l([.7, .14, .55, .1, .45, .2, .45, .9]), _l([.28, .4, .65, .4])],
      'g': [_arc(.45, .6, .22, .24, 270, 630, 22), [..._l([.67, .38, .67, .76]), ..._arc(.45, .76, .22, .15, 0, 180, 10)]],
      'h': [_l([.3, .1, .3, .9]), _l([.3, .58, .4, .44, .55, .4, .68, .5, .7, .9])],
      'i': [_l([.5, .4, .5, .9])],
      'j': [[..._l([.58, .4, .58, .8]), ..._arc(.42, .8, .16, .12, 0, 150, 10)]],
      'k': [_l([.3, .1, .3, .9]), _l([.72, .4, .3, .66, .74, .9])],
      'l': [_l([.5, .1, .5, .9])],
      'm': [_l([.2, .9, .2, .4]), _l([.2, .55, .33, .4, .5, .46, .5, .9]), _l([.5, .55, .65, .4, .8, .46, .8, .9])],
      'n': [_l([.3, .9, .3, .4]), _l([.3, .56, .45, .4, .62, .42, .7, .56, .7, .9])],
      'o': [_arc(.5, .62, .24, .27, 270, 630, 24)],
      'p': [_l([.3, .4, .3, .95]), _arc(.52, .62, .22, .24, 270, 630, 22)],
      'q': [_arc(.48, .62, .22, .24, 270, 630, 22), _l([.7, .4, .7, .95])],
      'r': [_l([.3, .4, .3, .9]), _l([.3, .56, .4, .43, .58, .4, .7, .46])],
      's': [[..._arc(.5, .5, .2, .12, -30, -270, 10), ..._arc(.5, .76, .22, .13, -90, 150, 10)]],
      't': [_l([.45, .15, .45, .8, .55, .9, .68, .88]), _l([.28, .4, .68, .4])],
      'u': [[..._l([.3, .4, .3, .7]), ..._arc(.5, .7, .2, .2, 180, 0, 10)], _l([.7, .4, .7, .9])],
      'v': [_l([.25, .4, .5, .9, .75, .4])],
      'w': [_l([.1, .4, .28, .9, .5, .55, .72, .9, .9, .4])],
      'x': [_l([.25, .4, .75, .9]), _l([.75, .4, .25, .9])],
      'y': [_l([.25, .4, .5, .86]), _l([.75, .4, .45, .95])],
      'z': [_l([.25, .4, .75, .4, .25, .9, .75, .9])],
    };
    const topDot = Pt(.5, .22);
    for (final e in low.entries) {
      out[e.key] = GlyphShape(e.key, e.value, dots: (e.key == 'i' || e.key == 'j') ? [e.key == 'j' ? const Pt(.58, .22) : topDot] : const []);
    }

    // Urdu, drawn from the right as Urdu is written. Letters of one family share a shape and differ by their dots.
    // These are simple geometric outlines, not calligraphy; the final letter forms are design content.
    final bowl = _arc(.5, .42, .4, .36, 0, 180, 18);
    final jeem = [[..._l([.85, .3, .52, .36]), ..._arc(.52, .58, .25, .22, 270, 90, 16)]];
    final dal = [_l([.4, .3, .4, .6, .72, .76])];
    final re = [_l([.66, .25, .62, .6, .36, .86])];
    final seen = [_l([.9, .5, .8, .35, .7, .5, .6, .35, .5, .5, .4, .35, .3, .5]), _arc(.5, .5, .4, .3, 0, 180, 16)];
    final swad = [_arc(.35, .45, .2, .15, 0, 360, 14), _arc(.5, .55, .4, .28, 0, 180, 16)];
    final toe = [_arc(.35, .55, .2, .2, 0, 360, 14), _l([.62, .12, .62, .85])];
    final ain = [[..._arc(.55, .35, .22, .15, 20, 340, 12), ..._arc(.5, .66, .3, .24, 270, 90, 14)]];
    final fe = [_arc(.62, .35, .2, .17, 0, 360, 14), _l([.8, .5, .8, .6]), _arc(.45, .6, .35, .25, 0, 180, 14)];
    final kaf = [_l([.78, .1, .3, .35, .78, .6]), _l([.3, .35, .25, .85, .8, .85])];
    final lam = [[..._l([.55, .1, .55, .75]), ..._arc(.35, .75, .2, .15, 0, 180, 10)]];
    final meem = [_arc(.52, .35, .2, .17, 0, 360, 14), _l([.52, .52, .52, .9])];
    final waw = [[..._arc(.58, .3, .15, .13, 0, 360, 14), ..._l([.58, .43, .5, .62, .3, .85])]];
    final he = [_arc(.5, .5, .24, .3, 0, 360, 20)];
    final ye = [[..._l([.85, .45, .7, .62]), ..._arc(.5, .6, .22, .25, 20, 180, 12), ..._l([.28, .65, .4, .85, .85, .85])]];
    final letters = <String, (List<List<Pt>>, List<Pt>)>{
      'ا': ([_l([.55, .1, .45, .9])], const []),
      'آ': ([_l([.55, .22, .45, .9]), _l([.4, .1, .5, .03, .6, .1])], const []),
      'ب': ([bowl], const [Pt(.5, .9)]),
      'پ': ([bowl], const [Pt(.38, .9), Pt(.62, .9), Pt(.5, .8)]),
      'ت': ([bowl], const [Pt(.4, .2), Pt(.6, .2)]),
      'ٹ': ([bowl], const [Pt(.5, .1), Pt(.4, .2), Pt(.6, .2)]),
      'ث': ([bowl], const [Pt(.4, .22), Pt(.6, .22), Pt(.5, .1)]),
      'ن': ([bowl], const [Pt(.5, .2)]),
      'ی': ([bowl], const [Pt(.4, .9), Pt(.6, .9)]),
      'ج': (jeem, const [Pt(.55, .66)]),
      'چ': (jeem, const [Pt(.45, .66), Pt(.65, .66), Pt(.55, .78)]),
      'ح': (jeem, const []),
      'خ': (jeem, const [Pt(.6, .12)]),
      'د': (dal, const []),
      'ڈ': (dal, const [Pt(.4, .12)]),
      'ذ': (dal, const [Pt(.55, .15)]),
      'ر': (re, const []),
      'ڑ': (re, const [Pt(.7, .12)]),
      'ز': (re, const [Pt(.5, .12)]),
      'ژ': (re, const [Pt(.4, .12), Pt(.6, .12), Pt(.5, .05)]),
      'س': (seen, const []),
      'ش': (seen, const [Pt(.4, .12), Pt(.55, .12), Pt(.47, .04)]),
      'ص': (swad, const []),
      'ض': (swad, const [Pt(.5, .14)]),
      'ط': (toe, const []),
      'ظ': (toe, const [Pt(.4, .14)]),
      'ع': (ain, const []),
      'غ': (ain, const [Pt(.55, .1)]),
      'ف': (fe, const [Pt(.62, .2)]),
      'ق': (fe, const [Pt(.52, .2), Pt(.72, .2)]),
      'ک': (kaf, const []),
      'گ': (kaf, const [Pt(.6, .05)]),
      'ل': (lam, const []),
      'م': (meem, const []),
      'ں': ([_arc(.5, .42, .4, .36, 0, 180, 18)], const [Pt(.5, .2)]),
      'و': (waw, const []),
      'ہ': (he, const []),
      'ھ': (he, const []),
      'ے': (ye, const []),
    };
    for (final e in letters.entries) {
      out[e.key] = GlyphShape(e.key, e.value.$1, dots: e.value.$2);
    }

    return out;
  }
}
