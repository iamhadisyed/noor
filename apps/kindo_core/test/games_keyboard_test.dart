import 'package:kindo_core/kindo_core.dart';
import 'package:test/test.dart';

GameStart session(List<Map<String, dynamic>> rounds, {int allowed = 300}) => GameStart(sessionId: 1, game: 'x', level: 1, allowedSeconds: allowed, balanceMinutes: 5, rounds: rounds);

void main() {
  group('GameRunner', () {
    test('it tells the kinds of round apart', () {
      expect(GameRunner.kindOf({'options': ['a'], 'answer_index': 0}), RoundKind.choice);
      expect(GameRunner.kindOf({'target': 'cat', 'keyboard': 'english'}), RoundKind.spell);
      expect(GameRunner.kindOf({'steps': [], 'answer_order': ['a']}), RoundKind.order);
      expect(GameRunner.kindOf({'pairs': [], 'cards': 4}), RoundKind.memory);
      expect(GameRunner.kindOf({'mode': 'scramble', 'target': 'cat', 'keyboard': 'english', 'letters': ['t', 'a', 'c']}), RoundKind.scramble);
      expect(GameRunner.kindOf({'mode': 'guess', 'target': 'cat', 'keyboard': 'english'}), RoundKind.guess);
    });

    test('only the first try counts, but a child can always try again', () {
      final g = GameRunner(session([
        {'options': ['a', 'b', 'c'], 'answer_index': 1},
        {'options': ['a', 'b'], 'answer_index': 0},
        {'options': ['x', 'y'], 'answer_index': 1},
      ]));

      final wrong = g.answerChoice(0);
      expect(wrong.correct, isFalse);
      expect(g.canRetry, isTrue);
      expect(g.answerChoice(1).correct, isTrue, reason: 'the retry is right');
      expect(g.correct, 0, reason: 'but a retry does not score');
      g.next();
      expect(g.answerChoice(0).firstTry, isTrue);
      g.next();
      g.next();

      expect(g.finished, isTrue);
      expect(g.report(), (roundsPlayed: 3, correct: 1, secondsPlayed: 0));
    });

    test('spelling ignores case and Urdu vowel marks', () {
      final g = GameRunner(session([
        {'target': 'cat', 'keyboard': 'english'},
        {'target': 'کِتاب', 'keyboard': 'urdu'},
      ]));
      expect(g.answerSpelling('CAT').correct, isTrue);
      g.next();
      expect(g.answerSpelling('کتاب').correct, isTrue);
      expect(g.answerSpelling('کتب').correct, isFalse);
    });

    test('a word puzzle and a guessing game are scored like spelling: first try only', () {
      final r = GameRunner(session([
        {'mode': 'scramble', 'target': 'cat', 'keyboard': 'english', 'letters': ['t', 'a', 'c']},
        {'mode': 'guess', 'target': 'dog', 'keyboard': 'english'},
      ]));
      expect(r.kind, RoundKind.scramble);
      expect(r.answerSpelling('act').correct, isFalse);
      expect(r.answerSpelling('cat').firstTry, isFalse, reason: 'a second try never scores');
      r.next();
      expect(r.kind, RoundKind.guess);
      expect(r.answerSpelling('').correct, isFalse, reason: 'the game ended without the word being found');
      r.next();
      expect(r.report().correct, 0);
    });

    test('a game says which ages it suits, and a child of any age or an unknown age fits sensibly', () {
      final g = GameInfo.fromJson({'game': 'scramble', 'category': 'language', 'title': {'en': 'Word puzzle', 'ur': ''}, 'age': [7, 10]});
      expect(g.ageLabel, '7-10');
      expect(g.fitsAge(8), isTrue);
      expect(g.fitsAge(4), isFalse);
      expect(g.fitsAge(null), isTrue);
      final old = GameInfo.fromJson({'game': 'count', 'category': 'math', 'title': {'en': 'x', 'ur': ''}});
      expect(old.fitsAge(9), isTrue, reason: 'an older server sends no ages: every game fits');
      final calm = CalmActivity.fromJson({'activity': 'balloon_belly', 'kind': 'breathing', 'emoji': '🎈', 'age': [3, 7], 'steps': [], 'title': {}, 'about': {}});
      expect(calm.emoji, '🎈');
      expect(calm.fitsAge(9), isFalse);
    });

    test('a routine must be tapped in the right order', () {
      final g = GameRunner(session([
        {'steps': [{'key': 'b'}, {'key': 'a'}, {'key': 'c'}], 'answer_order': ['a', 'b', 'c']},
      ]));
      expect(g.answerOrder(['b', 'a', 'c']).correct, isFalse);
      expect(g.answerOrder(['a', 'b']).correct, isFalse, reason: 'a short list is not the routine');
      expect(g.answerOrder(['a', 'b', 'c']).correct, isTrue);
      expect(g.correct, 0, reason: 'the first try was wrong');
    });

    test('a memory board counts as right when the wrong flips are few', () {
      final g = GameRunner(session([{'pairs': [{'key': 'a'}, {'key': 'b'}], 'cards': 4}]));
      expect(g.finishMemory(2).correct, isTrue);
      final h = GameRunner(session([{'pairs': [{'key': 'a'}, {'key': 'b'}], 'cards': 4}]));
      expect(h.finishMemory(5).correct, isFalse);
    });

    test('the clock decides when play time is up, and the report carries the seconds', () {
      var t = DateTime(2026, 10, 28, 10);
      final g = GameRunner(session([{'options': ['a', 'b'], 'answer_index': 0}], allowed: 120), now: () => t);
      expect(g.timeIsUp, isFalse);
      t = t.add(const Duration(seconds: 90));
      expect(g.secondsLeft, 30);
      t = t.add(const Duration(seconds: 40));
      expect(g.timeIsUp, isTrue);
      expect(g.secondsLeft, 0);
      expect(g.report().secondsPlayed, 130);
    });

    test('rounds not reached are not counted', () {
      final g = GameRunner(session([{'options': ['a', 'b'], 'answer_index': 0}, {'options': ['a', 'b'], 'answer_index': 0}]));
      g.answerChoice(0);
      g.next();
      expect(g.report().roundsPlayed, 1);
      expect(g.report().correct, 1);
    });
  });

  group('MemoryBoard', () {
    test('matching flips stay up, a miss waits to be turned back, and the board finishes', () {
      final board = MemoryBoard([{'key': 'a'}, {'key': 'b'}]);
      final byKey = <String, List<int>>{};
      for (var i = 0; i < board.cards.length; i++) {
        byKey.putIfAbsent('${board.cards[i]['key']}', () => []).add(i);
      }
      final a = byKey['a']!, b = byKey['b']!;

      expect(board.flip(a[0]), 'first');
      expect(board.flip(b[0]), 'miss');
      expect(board.mistakes, 1);
      expect(board.flip(a[1]), 'ignored', reason: 'two cards are up: turn them back first');
      board.resolve();
      expect(board.flip(a[0]), 'first');
      expect(board.flip(a[1]), 'match');
      expect(board.flip(a[0]), 'ignored');
      board.flip(b[0]);
      expect(board.flip(b[1]), 'match');
      expect(board.done, isTrue);
      expect(board.mistakes, 1);
    });

    test('there are two cards for every pair', () {
      expect(MemoryBoard([{'key': 'a'}, {'key': 'b'}, {'key': 'c'}]).cards, hasLength(6));
    });
  });

  group('keyboards', () {
    final meta = {
      'keyboards': {
        'english': {'direction': 'ltr', 'columns': 7, 'letters': [for (var c = 97; c <= 122; c++) String.fromCharCode(c)]},
        'urdu': {'direction': 'rtl', 'columns': 8, 'letters': ['ا', 'آ', 'ب', 'پ', 'ت', 'ٹ', 'ث', 'ج', 'چ']},
      },
    };

    test('both layouts come from the server and are in alphabet order', () {
      final k = KeyboardLayout.fromMeta(meta);
      expect(k['english']!.letters.take(4), ['a', 'b', 'c', 'd']);
      expect(k['english']!.isRtl, isFalse);
      expect(k['urdu']!.isRtl, isTrue);
      final rows = k['english']!.rows();
      expect(rows.map((r) => r.length), [7, 7, 7, 5]);
      expect(rows.expand((r) => r).join(), 'abcdefghijklmnopqrstuvwxyz');
    });

    test('it knows which words the child can type', () {
      final k = KeyboardLayout.fromMeta(meta);
      expect(k['english']!.canType('Cat'), isTrue);
      expect(k['english']!.canType('ice-cream'), isFalse);
      expect(k['english']!.missing('a1b2'), ['1', '2']);
      expect(k['urdu']!.canType('باب'), isTrue);
      expect(k['urdu']!.canType('بِپ'), isTrue, reason: 'vowel marks are ignored');
      expect(k['urdu']!.canType('کتاب'), isFalse);
    });

    test('the right keyboard is chosen from the script', () {
      expect(keyboardFor('cat'), 'english');
      expect(keyboardFor('باپ'), 'urdu');
      expect(sameWord('Cat', 'cat'), isTrue);
    });
  });
}
