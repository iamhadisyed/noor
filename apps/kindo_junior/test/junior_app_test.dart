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
MockClient _server(List<String> log, {Map<String, dynamic>? support}) => MockClient((r) async {
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
          _ch('child', 1, {'id': 1, 'name': 'Ayan', 'track': 'special', 'rewards': {'balance_minutes': 5}, 'support': support ?? {'pace': 'slow', 'max_choices': 2, 'praise': 'quiet'}}),
          _ch('lesson', 3, {'id': 3, 'child_id': 1, 'title': 'Fruit', 'status': 'published', 'week_number': 1, 'language': 'english', 'tasks': [
            {'id': 30, 'position': 1, 'type': 'pick_picture', 'title': 'Which is the apple?', 'options': {'choices': ['Banana', 'Apple', 'Mango'], 'correct': 1}},
            {'id': 31, 'position': 2, 'type': 'spell_word', 'title': 'Spell cat', 'target': 'cat', 'keyboard': 'english'},
          ]}),
        ]});
      }
      if (p == '/rewards' || p == '/children/1/rewards') {
        return _j({'balance_minutes': 5, 'settings': {'enabled': true}, 'games': [
          {'game': 'count', 'category': 'maths', 'title': {'en': 'Count it', 'ur': 'گنو'}, 'for': ['standard', 'special'], 'enabled': true, 'available': true, 'unlocked': true, 'level': 1, 'plays': 0},
        ]});
      }
      if (p == '/children/1/games/count/start') {
        return _j({'session_id': 77, 'game': 'count', 'level': 1, 'allowed_seconds': 300, 'balance_minutes': 5, 'rounds': [
          {'prompt': {'en': 'How many?', 'ur': 'کتنے ہیں؟'}, 'icon': {'key': 'apple'}, 'count': 2, 'options': [1, 2, 3], 'answer_index': 1},
        ]});
      }
      if (p == '/children/1/sessions') return _j({'id': 1, 'reward': {'earned': 2}}, 201);
      if (p == '/child/alert') return _j({'id': 9}, 201);
      return _j({});
    });

Future<KindoSession> _signedIn(List<String> log, {Map<String, dynamic>? support}) async {
  final store = MemoryStore();
  await store.write('kindo.token', 'tok');
  final s = KindoSession(store: store, defaultBaseUrl: 'https://k.test/api/v1', httpClient: _server(log, support: support));
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
}
