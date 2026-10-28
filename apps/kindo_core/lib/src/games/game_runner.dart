import 'dart:math';

import '../keyboard/keyboards.dart';
import '../models/models.dart';

/// How a round is answered, worked out from what the server sent.
enum RoundKind {
  /// Pick one of `options` (the right one is `answer_index`).
  choice,

  /// Type the word `target` on the alphabet keyboard (`spell_picture`).
  spell,

  /// Tap the `steps` in the order given by `answer_order` (`routine_order`).
  order,

  /// A board of `pairs` to flip and match (`memory_match`).
  memory,
}

class RoundResult {
  const RoundResult({required this.correct, required this.firstTry});

  /// This answer is right.
  final bool correct;

  /// It was right on the first attempt at this round (only that counts towards the score).
  final bool firstTry;
}

/// Plays one game session the server created. The whole session arrives at once so it works offline; this keeps the score and the clock,
/// then [report] gives the numbers for `POST /game-sessions/{id}/finish`.
class GameRunner {
  GameRunner(this.start, {DateTime Function()? now})
      : _now = now ?? DateTime.now,
        _startedAt = (now ?? DateTime.now)();

  final GameStart start;
  final DateTime Function() _now;
  final DateTime _startedAt;

  int _index = 0;
  int _correct = 0;
  int _played = 0;
  bool _attempted = false;
  bool _firstTryDone = false;

  List<Json> get rounds => start.rounds;
  int get roundCount => rounds.length;
  int get index => _index;
  bool get finished => _index >= rounds.length;
  Json get current => rounds[_index];
  int get correct => _correct;

  /// Rounds the child has finished (moved past).
  int get played => _played;

  RoundKind get kind => kindOf(current);

  static RoundKind kindOf(Json round) {
    if (round.containsKey('answer_order')) return RoundKind.order;
    if (round.containsKey('pairs')) return RoundKind.memory;
    if (round.containsKey('target') && round.containsKey('keyboard')) return RoundKind.spell;
    return RoundKind.choice;
  }

  /// Seconds since the game began.
  int get secondsPlayed => _now().difference(_startedAt).inSeconds;

  /// True once the play time the parent allows has run out. The app should finish the game kindly, not cut it off mid-answer.
  bool get timeIsUp => secondsPlayed >= start.allowedSeconds;

  int get secondsLeft => (start.allowedSeconds - secondsPlayed).clamp(0, start.allowedSeconds);

  RoundResult _record(bool right) {
    final first = !_firstTryDone;
    if (first) {
      _firstTryDone = true;
      if (right) _correct++;
    }
    _attempted = true;

    return RoundResult(correct: right, firstTry: first && right);
  }

  /// A picture, number, letter, colour or word was chosen.
  RoundResult answerChoice(int optionIndex) {
    assert(kind == RoundKind.choice);
    return _record(optionIndex == (current['answer_index'] as num).toInt());
  }

  /// The child typed a word.
  RoundResult answerSpelling(String typed) {
    assert(kind == RoundKind.spell);
    return _record(sameWord(typed, '${current['target']}'));
  }

  /// The child tapped the steps in this order (a list of step keys).
  RoundResult answerOrder(List<String> keysInOrder) {
    assert(kind == RoundKind.order);
    final want = (current['answer_order'] as List).map((e) => '$e').toList();
    return _record(keysInOrder.length == want.length && List.generate(want.length, (i) => keysInOrder[i] == want[i]).every((x) => x));
  }

  /// A memory board was cleared with this many wrong flips. Counts as right when the wrong flips were no more than the number of pairs.
  RoundResult finishMemory(int mistakes) {
    assert(kind == RoundKind.memory);
    final pairs = (current['pairs'] as List).length;
    return _record(mistakes <= pairs);
  }

  /// Is the child allowed to try again on this round after a wrong answer? Always, so no round is a dead end; only the first try scores.
  bool get canRetry => _attempted;

  /// Move to the next round. Rounds left unanswered still count as played (and not correct).
  void next() {
    if (finished) return;
    _played++;
    _index++;
    _attempted = false;
    _firstTryDone = false;
  }

  /// The numbers for `finishGame`. Rounds not reached are not counted.
  ({int roundsPlayed, int correct, int secondsPlayed}) report() => (roundsPlayed: _played, correct: _correct.clamp(0, _played), secondsPlayed: secondsPlayed);
}

/// One board of the memory game. The app shows [cards] face down and calls [flip].
class MemoryBoard {
  MemoryBoard(List<Json> pairs, {Random? random}) : cards = _deal(pairs, random ?? Random());

  /// Two cards per pair, shuffled. Each is the icon Json; two cards with the same `key` match.
  final List<Json> cards;
  final Set<int> _matched = {};
  final List<int> _faceUp = [];
  int mistakes = 0;

  static List<Json> _deal(List<Json> pairs, Random r) => ([...pairs, ...pairs]..shuffle(r));

  Set<int> get matched => Set.unmodifiable(_matched);
  List<int> get faceUp => List.unmodifiable(_faceUp);
  bool get done => _matched.length == cards.length;

  /// Flip card [i]. Returns `match`, `miss`, or `first` (the first card of a try). A miss leaves both cards face up until [resolve] is called,
  /// so the child can see them.
  String flip(int i) {
    if (_matched.contains(i) || _faceUp.contains(i) || _faceUp.length >= 2) return 'ignored';
    _faceUp.add(i);
    if (_faceUp.length == 1) return 'first';
    final a = _faceUp[0], b = _faceUp[1];
    if (cards[a]['key'] == cards[b]['key']) {
      _matched..add(a)..add(b);
      _faceUp.clear();

      return 'match';
    }
    mistakes++;

    return 'miss';
  }

  /// Turn the two missed cards back over.
  void resolve() => _faceUp.clear();
}
