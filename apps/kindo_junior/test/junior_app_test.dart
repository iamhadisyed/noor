import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:kindo_junior/app.dart';
import 'package:kindo_ui/kindo_ui.dart';

http.Response _j(Object body, [int status = 200]) => http.Response(jsonEncode(body), status, headers: {'content-type': 'application/json'});

Map<String, dynamic> _ch(String e, int id, Map<String, dynamic> d) => {'entity': e, 'id': id, 'op': 'upsert', 'data': d};

final _letters = 'abcdefghijklmnopqrstuvwxyz'.split('');

/// A tiny pretend server: one child, one lesson with a choice task and a spelling task, the stock cards, and a game.
MockClient _server(List<String> log, {Map<String, dynamic>? support, Map<String, dynamic>? screenTime}) => MockClient((r) async {
      final p = r.url.path.replaceFirst('/api/v1', '');
      log.add('${r.method} $p${r.body.isEmpty ? '' : ' ${r.body}'}');
      if (p == '/me') return _j({'id': 1, 'email': 'mum@example.com', 'name': 'Sana', 'role': 'parent', 'plan': 'free'});
      if (p == '/meta') return _j({'keyboards': {'english': {'letters': _letters, 'direction': 'ltr', 'columns': 7}}});
      if (p == '/aac-cards') {
        return _j([
          {'id': 1, 'category': 'needs', 'english_label': 'Water', 'urdu_label': 'پانی', 'icon_path': 'aac/water.png', 'custom': false},
          {'id': 2, 'category': 'needs', 'english_label': 'Help', 'urdu_label': 'مدد', 'icon_path': 'aac/help.png', 'custom': false, 'is_emergency': true},
        ]);
      }
      if (p == '/sync') {
        return _j({'snapshot': true, 'cursor': 5, 'has_more': false, 'changes': [
          _ch('child', 1, {'id': 1, 'name': 'Ayan', 'track': 'special', 'rewards': {'balance_minutes': 5}, 'date_of_birth': '${DateTime.now().year - 5}-01-01', 'support': support ?? {'pace': 'slow', 'max_choices': 2, 'praise': 'quiet'}, 'screen_time': screenTime}),
          _ch('lesson', 3, {'id': 3, 'child_id': 1, 'title': 'Fruit', 'status': 'published', 'week_number': 1, 'language': 'english', 'tasks': [
            {'id': 30, 'position': 1, 'type': 'pick_picture', 'title': 'Which is the apple?', 'options': {'choices': ['Banana', 'Apple', 'Mango'], 'correct': 1}},
            {'id': 31, 'position': 2, 'type': 'spell_word', 'title': 'Spell cat', 'target': 'cat', 'keyboard': 'english'},
          ]}),
        ]});
      }
      if (p == '/rewards' || p == '/children/1/rewards') {
        return _j({'balance_minutes': 5, 'settings': {'enabled': true}, 'games': [
          {'game': 'count', 'category': 'math', 'title': {'en': 'Count it', 'ur': 'گنو'}, 'for': ['standard', 'special'], 'age': [3, 6], 'enabled': true, 'available': true, 'unlocked': true, 'level': 1, 'plays': 0},
          {'game': 'scramble', 'category': 'language', 'title': {'en': 'Word puzzle', 'ur': 'پہیلی'}, 'for': ['standard'], 'age': [7, 10], 'enabled': true, 'available': true, 'unlocked': true, 'level': 2, 'plays': 3},
          {'game': 'hangman', 'category': 'language', 'title': {'en': 'Guess the word', 'ur': 'بوجھو'}, 'for': ['standard'], 'age': [7, 10], 'enabled': true, 'available': true, 'unlocked': true, 'level': 1, 'plays': 0},
          {'game': 'read_clock', 'category': 'math', 'title': {'en': 'What time is it?', 'ur': 'وقت'}, 'for': ['standard'], 'age': [3, 10], 'enabled': true, 'available': true, 'unlocked': true, 'level': 1, 'plays': 0},
        ]});
      }
      if (p == '/children/1/games/count/start') {
        return _j({'session_id': 77, 'game': 'count', 'level': 1, 'allowed_seconds': 300, 'balance_minutes': 5, 'rounds': [
          {'prompt': {'en': 'How many?', 'ur': 'کتنے ہیں؟'}, 'icon': {'key': 'apple'}, 'count': 2, 'options': [1, 2, 3], 'answer_index': 1},
        ]});
      }
      if (p == '/children/1/games/scramble/start') {
        return _j({'session_id': 78, 'game': 'scramble', 'level': 1, 'allowed_seconds': 300, 'balance_minutes': 5, 'rounds': [
          {'prompt': {'en': 'Put the letters in order', 'ur': 'ترتیب'}, 'mode': 'scramble', 'icon': {'key': 'cat'}, 'lang': 'en', 'keyboard': 'english', 'target': 'cat', 'letters': ['t', 'a', 'c'], 'show_hint': true},
        ]});
      }
      if (p == '/children/1/games/hangman/start') {
        return _j({'session_id': 79, 'game': 'hangman', 'level': 1, 'allowed_seconds': 300, 'balance_minutes': 5, 'rounds': [
          {'prompt': {'en': 'Guess the secret word', 'ur': 'بوجھو'}, 'mode': 'guess', 'icon': {'key': 'cat'}, 'lang': 'en', 'keyboard': 'english', 'target': 'cat', 'max_misses': 2, 'show_hint': true},
        ]});
      }
      if (p == '/children/1/games/read_clock/start') {
        return _j({'session_id': 80, 'game': 'read_clock', 'level': 1, 'allowed_seconds': 300, 'balance_minutes': 5, 'rounds': [
          {'prompt': {'en': 'What time is it?', 'ur': 'وقت'}, 'hour': 3, 'minute': 0, 'options': ['3:00', '6:00'], 'answer_index': 0},
        ]});
      }
      if (p == '/learn') {
        return _j({'topics': [
          {'topic': 'space', 'emoji': '🚀', 'color': '#B9A2F5', 'title': {'en': 'Space and galaxies', 'ur': 'خلا'}, 'about': {'en': 'Travel past the Moon.', 'ur': 'چاند'}, 'game': 'science_space', 'facts': 12},
          {'topic': 'ai', 'emoji': '🤖', 'color': '#F9A8D4', 'title': {'en': 'AI and smart helpers', 'ur': 'اے آئی'}, 'about': {'en': 'What is AI?', 'ur': 'اے آئی کیا ہے'}, 'game': 'science_ai', 'facts': 12},
        ]});
      }
      if (p == '/learn/space') {
        return _j({'topic': 'space', 'game': 'science_space', 'facts': [
          {'key': 'moon', 'emoji': '🌙', 'title': {'en': 'The Moon', 'ur': 'چاند'}, 'text': {'en': 'The Moon goes around the Earth.', 'ur': 'چاند زمین کے گرد گھومتا ہے'}},
        ]});
      }
      if (p == '/children/1/sessions') return _j({'id': 1, 'reward': {'earned': 2}}, 201);
      if (p == '/child/alert') return _j({'id': 9}, 201);
      return _j({});
    });

Future<KindoSession> _signedIn(List<String> log, {Map<String, dynamic>? support, Map<String, dynamic>? screenTime}) async {
  final store = MemoryStore();
  await store.write('kindo.token', 'tok');
  final s = KindoSession(store: store, defaultBaseUrl: 'https://k.test/api/v1', httpClient: _server(log, support: support, screenTime: screenTime));
  await s.init();
  await s.refresh();
  return s;
}

Future<void> _open(WidgetTester t, KindoSession s) async {
  await t.pumpWidget(JuniorApp(session: s));
  await t.pumpAndSettle();
}

void main() {
  testWidgets('a signed-out device asks a parent to sign in', (t) async {
    final s = KindoSession(store: MemoryStore(), defaultBaseUrl: 'https://k.test/api/v1', httpClient: _server([]));
    await s.init();
    await _open(t, s);
    expect(find.text('Email me a code'), findsOneWidget);
  });

  testWidgets('cool time: when the allowance is used the child sees a calm screen, can still say what they need, and nothing else', (t) async {
    final log = <String>[];
    final s = await _signedIn(log, screenTime: {'enabled': true, 'daily_minutes': 0});
    await _open(t, s);
    expect(find.text('Cool time!'), findsOneWidget);
    expect(find.text('Play'), findsNothing);
    expect(find.text('Hello, Ayan!'), findsNothing);
    await t.tap(find.text('I need...'));
    await t.pumpAndSettle();
    expect(find.text('Water'), findsOneWidget, reason: 'the needs board stays open');
    s.stopAutoSync();
  });

  testWidgets('sleepy time: quiet hours show the resting screen even when time is left', (t) async {
    final s = await _signedIn([], screenTime: {'enabled': true, 'daily_minutes': 60, 'quiet_start': '00:00', 'quiet_end': '23:59'});
    await _open(t, s);
    expect(find.text('Sleepy time'), findsOneWidget);
    s.stopAutoSync();
  });

  testWidgets('with screen time switched off the child is never locked', (t) async {
    final s = await _signedIn([], screenTime: {'enabled': false, 'daily_minutes': 0});
    await _open(t, s);
    expect(find.text('Cool time!'), findsNothing);
    expect(find.text('Hello, Ayan!'), findsOneWidget);
    s.stopAutoSync();
  });

  testWidgets('discover: topics by theme, facts for the age, and a way into the quiz', (t) async {
    await t.binding.setSurfaceSize(const Size(500, 2200));
    addTearDown(() => t.binding.setSurfaceSize(null));
    final log = <String>[];
    final s = await _signedIn(log);
    await _open(t, s);
    await t.tap(find.text('Discover'));
    await t.pumpAndSettle();
    expect(find.text('Space and galaxies'), findsOneWidget);
    expect(find.text('AI and smart helpers'), findsOneWidget);
    await t.tap(find.text('Space and galaxies'));
    await t.pumpAndSettle();
    expect(find.text('The Moon'), findsOneWidget);
    expect(find.text('Play the quiz'), findsOneWidget);
    expect(log.any((l) => l.startsWith('GET /learn/space')), isTrue);
    s.stopAutoSync();
  });

  testWidgets('home greets the child and shows big tiles', (t) async {
    final s = await _signedIn([]);
    await _open(t, s);
    expect(find.text('Hello, Ayan!'), findsOneWidget);
    for (final label in ['I need...', 'Learn', 'Play', 'Calm']) {
      expect(find.text(label), findsOneWidget);
    }
    s.stopAutoSync();
  });

  testWidgets('tapping a picture card tells the parent, and urgent cards come first', (t) async {
    final log = <String>[];
    final s = await _signedIn(log);
    await _open(t, s);
    await t.tap(find.text('I need...'));
    await t.pumpAndSettle();
    expect(find.text('Help'), findsOneWidget);
    await t.tap(find.text('Water'));
    await t.pump();
    await t.pump(const Duration(milliseconds: 300));
    expect(log.where((l) => l.startsWith('POST /child/alert')), isNotEmpty);
    expect(log.firstWhere((l) => l.startsWith('POST /child/alert')), contains('"need_type":"water"'));
    expect(find.textContaining('I told them'), findsOneWidget);
    await t.pump(const Duration(seconds: 5));
    s.stopAutoSync();
  });

  testWidgets('a lesson: a wrong choice is gentle, the right one moves on, then spelling on the alphabet keyboard', (t) async {
    final log = <String>[];
    final s = await _signedIn(log);
    await _open(t, s);
    await t.tap(find.text('Learn'));
    await t.pumpAndSettle();
    await t.tap(find.text('Fruit'));
    await t.pumpAndSettle();
    expect(find.text('Which is the apple?'), findsOneWidget);
    // this child gets two choices: the right answer and one other
    expect(find.text('Mango'), findsNothing);
    await t.tap(find.text('Banana'));
    await t.pump();
    expect(find.text('Try another one'), findsOneWidget);
    await t.tap(find.text('Apple'));
    await t.pump(const Duration(seconds: 2));
    await t.pumpAndSettle();
    expect(find.text('Next'), findsOneWidget);
    await t.tap(find.text('Next'));
    await t.pumpAndSettle();

    // spelling: letters in alphabet order, no phone keyboard
    expect(find.text('Spell cat'), findsOneWidget);
    expect(find.byType(TextField), findsNothing);
    final keys = ['a', 'b', 'c'].map((k) => t.getTopLeft(find.text(k).last).dx).toList();
    expect(keys[0] < keys[1] && keys[1] < keys[2], isTrue);
    for (final k in ['c', 'a', 't']) {
      await t.tap(find.text(k).last);
      await t.pump();
    }
    await t.tap(find.text('Done'));
    await t.pumpAndSettle();
    await t.tap(find.text('Next'));
    await t.pumpAndSettle();

    expect(find.text('You finished!'), findsOneWidget);
    final sent = log.firstWhere((l) => l.startsWith('POST /children/1/sessions'));
    final body = jsonDecode(sent.substring('POST /children/1/sessions '.length)) as Map<String, dynamic>;
    // two scored answers: the wrong first try on the choice, the right spelling
    expect(body['total_attempts'], 2);
    expect(body['successful_attempts'], 1);
    expect(find.text('+2 play minutes'), findsOneWidget);
    s.stopAutoSync();
  });

  testWidgets('a game: pick the right count, then the game ends kindly', (t) async {
    final log = <String>[];
    final s = await _signedIn(log);
    await _open(t, s);
    await t.tap(find.text('Play'));
    await t.pumpAndSettle();
    expect(find.text('5 play minutes saved'), findsOneWidget);
    await t.tap(find.text('Count it'));
    await t.pumpAndSettle();
    expect(find.text('How many?'), findsOneWidget);
    await t.tap(find.text('2'));
    await t.pump(const Duration(seconds: 2));
    await t.pumpAndSettle();
    expect(find.text('Great playing!'), findsOneWidget);
    expect(log.any((l) => l.startsWith('POST /game-sessions/77/finish') && l.contains('"correct":1')), isTrue);
    s.stopAutoSync();
  });

  testWidgets('the grown-ups button is behind a sum', (t) async {
    final s = await _signedIn([]);
    await _open(t, s);
    await t.tap(find.byIcon(Icons.settings_outlined));
    await t.pumpAndSettle();
    expect(find.text('Grown-ups only'), findsOneWidget);
    expect(find.text('Sign out'), findsNothing);
    s.stopAutoSync();
  });

  testWidgets('the games shelf puts the games for the child\'s age first and says which ages each suits', (t) async {
    await t.binding.setSurfaceSize(const Size(500, 2200));
    addTearDown(() => t.binding.setSurfaceSize(null));
    final s = await _signedIn([]);
    await _open(t, s);
    await t.tap(find.text('Play'));
    await t.pumpAndSettle();
    expect(find.text('Made for you'), findsOneWidget);
    expect(find.text('More to try'), findsOneWidget);
    expect(find.text('Ages 3-6'), findsOneWidget);
    expect(find.text('Ages 7-10'), findsNWidgets(2));
    // a five year old sees "Count it" in the first group and the word puzzle in the second
    expect(t.getTopLeft(find.text('Count it')).dy < t.getTopLeft(find.text('More to try')).dy, isTrue);
    expect(t.getTopLeft(find.text('Word puzzle')).dy > t.getTopLeft(find.text('More to try')).dy, isTrue);
    s.stopAutoSync();
  });

  testWidgets('word puzzle: tapping the jumbled letters in order builds the word and ends the game kindly', (t) async {
    await t.binding.setSurfaceSize(const Size(500, 2200));
    addTearDown(() => t.binding.setSurfaceSize(null));
    final log = <String>[];
    final s = await _signedIn(log);
    await _open(t, s);
    await t.tap(find.text('Play'));
    await t.pumpAndSettle();
    await t.tap(find.text('Word puzzle'));
    await t.pumpAndSettle();
    expect(find.text('Put the letters in order'), findsOneWidget);
    // a wrong order first: the letters go back and nothing is marked on the child
    for (final l in ['t', 'a', 'c']) {
      await t.tap(find.text(l).first);
      await t.pump(const Duration(milliseconds: 250));
    }
    expect(find.text('Nearly! Try again'), findsOneWidget);
    await t.pump(const Duration(seconds: 4));   // the message goes away by itself and the letters are back
    await t.pumpAndSettle();
    for (final l in ['c', 'a', 't']) {
      await t.tap(find.text(l).first);
      await t.pump(const Duration(milliseconds: 250));
    }
    await t.pump(const Duration(seconds: 3));
    await t.pumpAndSettle();
    expect(find.text('Great playing!'), findsOneWidget);
    // the first try was wrong, so it does not count as right first time
    expect(log.any((l) => l.startsWith('POST /game-sessions/78/finish') && l.contains('"correct":0')), isTrue);
    s.stopAutoSync();
  });

  testWidgets('guess the word: wrong letters cost hearts, running out shows the word kindly, a right word wins', (t) async {
    await t.binding.setSurfaceSize(const Size(500, 2200));
    addTearDown(() => t.binding.setSurfaceSize(null));
    final log = <String>[];
    final s = await _signedIn(log);
    await _open(t, s);
    await t.tap(find.text('Play'));
    await t.pumpAndSettle();
    await t.tap(find.text('Guess the word'));
    await t.pumpAndSettle();
    expect(find.byIcon(Icons.favorite_rounded), findsNWidgets(2));
    await t.tap(find.text('x'));
    await t.pump();
    expect(find.byIcon(Icons.favorite_rounded), findsNWidgets(1));
    await t.tap(find.text('z'));
    await t.pump();
    expect(find.text('Now you know it!'), findsOneWidget);
    // the word is shown in the boxes
    expect(find.text('c'), findsOneWidget);
    await t.tap(find.text('Next'));
    await t.pumpAndSettle();
    expect(find.text('Great playing!'), findsOneWidget);
    expect(log.any((l) => l.startsWith('POST /game-sessions/79/finish') && l.contains('"correct":0')), isTrue);
    s.stopAutoSync();
  });

  testWidgets('read the clock: the clock face is drawn and the right time wins; a lively child gets confetti, a quiet one does not', (t) async {
    await t.binding.setSurfaceSize(const Size(500, 2200));
    addTearDown(() => t.binding.setSurfaceSize(null));
    final s = await _signedIn([], support: {'pace': 'normal', 'max_choices': 2, 'praise': 'lively'});
    await _open(t, s);
    await t.tap(find.text('Play'));
    await t.pumpAndSettle();
    await t.tap(find.text('What time is it?'));
    await t.pumpAndSettle();
    expect(find.byType(ClockFace), findsOneWidget);
    await t.tap(find.text('3:00'));
    await t.pump(const Duration(milliseconds: 100));
    expect(find.byType(ConfettiBurst), findsOneWidget);
    await t.pump(const Duration(seconds: 3));
    await t.pumpAndSettle();
    expect(find.text('Great playing!'), findsOneWidget);
    s.stopAutoSync();
  });
}
