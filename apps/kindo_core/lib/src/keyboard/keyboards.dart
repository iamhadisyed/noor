import '../models/models.dart';

/// The child's spelling keyboard: the alphabet in order, never QWERTY. The layouts come from `GET /meta` (`keyboards`).
class KeyboardLayout {
  const KeyboardLayout({required this.name, required this.letters, required this.direction, required this.columns});
  factory KeyboardLayout.fromJson(String name, Object? v) {
    final m = asJson(v);
    return KeyboardLayout(
      name: name,
      letters: (m['letters'] as List? ?? []).map((e) => '$e').toList(),
      direction: '${m['direction'] ?? 'ltr'}',
      columns: (m['columns'] as num?)?.toInt() ?? 7,
    );
  }

  /// Both layouts out of the `meta` response.
  static Map<String, KeyboardLayout> fromMeta(Json meta) {
    final k = asJson(meta['keyboards']);
    return {for (final e in k.entries) e.key: KeyboardLayout.fromJson(e.key, e.value)};
  }

  /// `english` or `urdu`.
  final String name;
  final List<String> letters;

  /// `ltr` or `rtl`.
  final String direction;
  final int columns;

  bool get isRtl => direction == 'rtl';

  /// The letters in rows of [columns] (or fewer for the last row), in reading order. For an RTL layout a row reads from the right.
  List<List<String>> rows([int? columns]) {
    final n = columns ?? this.columns;
    return [for (var i = 0; i < letters.length; i += n) letters.sublist(i, i + n > letters.length ? letters.length : i + n)];
  }

  /// Does the keyboard have every letter of [word]? Spaces and Urdu vowel marks are ignored, and English is case-insensitive.
  bool canType(String word) => missing(word).isEmpty;

  List<String> missing(String word) {
    final marks = RegExp('[\\s\\u200c\\u200d\\u064b-\\u065f\\u0670]');
    final clean = word.replaceAll(marks, '');
    final set = letters.toSet();
    final bad = <String>{};
    for (final r in (name == 'english' ? clean.toLowerCase() : clean).runes) {
      final ch = String.fromCharCode(r);
      if (!set.contains(ch)) bad.add(ch);
    }

    return bad.toList();
  }
}

/// Which keyboard a word needs: Urdu when it is written in Arabic script, otherwise English.
String keyboardFor(String word) => RegExp('[\\u0600-\\u06FF\\u0750-\\u077F\\uFB50-\\uFDFF\\uFE70-\\uFEFF]').hasMatch(word) ? 'urdu' : 'english';

/// Compare what the child typed with the target word, ignoring case and Urdu vowel marks.
bool sameWord(String typed, String target) {
  String norm(String s) => s.replaceAll(RegExp('[\\s\\u200c\\u200d\\u064b-\\u065f\\u0670]'), '').toLowerCase();
  return norm(typed) == norm(target);
}
