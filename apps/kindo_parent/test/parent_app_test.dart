import 'dart:convert';

import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:kindo_parent/app.dart';
import 'package:kindo_ui/kindo_ui.dart';

http.Response _j(Object body, [int status = 200]) => http.Response(jsonEncode(body), status, headers: {'content-type': 'application/json'});

Map<String, dynamic> _ch(String e, int id, Map<String, dynamic> d) => {'entity': e, 'id': id, 'op': 'upsert', 'data': d};

/// A tiny pretend server: just enough for the parent app to show a family.
MockClient _server(List<String> log) => MockClient((r) async {
      log.add('${r.method} ${r.url.path}');
      final p = r.url.path.replaceFirst('/api/v1', '');
      if (p == '/me') return _j({'id': 1, 'email': 'mum@example.com', 'name': 'Sana', 'role': 'parent', 'plan': 'free'});
      if (p == '/meta') return _j({'keyboards': {'english': {'letters': 'abcdefghijklmnopqrstuvwxyz'.split(''), 'direction': 'ltr', 'columns': 7}}});
      if (p == '/sync') {
        return _j({'snapshot': true, 'cursor': 5, 'has_more': false, 'changes': [
          _ch('child', 1, {'id': 1, 'name': 'Ayan', 'track': 'special', 'support': {'pace': 'slow'}}),
          _ch('lesson', 3, {'id': 3, 'child_id': 1, 'title': 'Colours week', 'status': 'published', 'week_number': 1, 'tasks': []}),
          _ch('need', 9, {'id': 9, 'child_id': 1, 'need_type': 'water', 'status': 'sent'}),
        ]});
      }
      if (p == '/children/1/insights/summary') return _j({'practice': {'minutes': 12, 'accuracy_pct': 80, 'sessions': 3}, 'needs': {'total': 4}, 'calm': {'minutes': 2}});
      if (p == '/announcements') return _j([]);
      if (p.endsWith('/acknowledge')) return _j({'id': 9, 'child_id': 1, 'need_type': 'water', 'status': 'on_the_way'});
      if (p.endsWith('/seen')) return _j({'id': 9, 'child_id': 1, 'need_type': 'water', 'status': 'seen'});
      return _j({});
    });

Future<KindoSession> _signedIn(List<String> log) async {
  final store = MemoryStore();
  await store.write('kindo.token', 'tok');
  final s = KindoSession(store: store, defaultBaseUrl: 'https://k.test/api/v1', httpClient: _server(log));
  await s.init();
  return s;
}

void main() {
  testWidgets('a signed-out parent sees the sign-in screen', (t) async {
    final s = KindoSession(store: MemoryStore(), defaultBaseUrl: 'https://k.test/api/v1', httpClient: _server([]));
    await s.init();
    await t.pumpWidget(ParentApp(session: s));
    await t.pumpAndSettle();
    expect(find.text('Email me a code'), findsOneWidget);
  });

  testWidgets('home shows the child, a waiting request and the week in numbers', (t) async {
    final log = <String>[];
    final s = await _signedIn(log);
    await t.pumpWidget(ParentApp(session: s));
    await t.pumpAndSettle();
    expect(find.text('Ayan'), findsWidgets);
    expect(find.text('Extra support'), findsOneWidget);
    expect(find.text('Your child is asking'), findsOneWidget);
    expect(find.text('Water'), findsOneWidget);
    expect(find.text('12'), findsOneWidget);
    s.stopAutoSync();
  });

  testWidgets("tapping I'm coming tells the server and the request is answered", (t) async {
    final log = <String>[];
    final s = await _signedIn(log);
    await t.pumpWidget(ParentApp(session: s));
    await t.pumpAndSettle();
    await t.tap(find.text("I'm coming").first);
    await t.pumpAndSettle();
    expect(log, contains('POST /api/v1/needs/9/acknowledge'));
    s.stopAutoSync();
  });

  testWidgets('the lessons and alerts tabs show what was synced', (t) async {
    final s = await _signedIn([]);
    await t.pumpWidget(ParentApp(session: s));
    await t.pumpAndSettle();
    await t.tap(find.text('Lessons'));
    await t.pumpAndSettle();
    expect(find.text('Colours week'), findsOneWidget);
    expect(find.text('Live'), findsOneWidget);
    await t.tap(find.text('Alerts').last);
    await t.pumpAndSettle();
    expect(find.text('Water'), findsWidgets);
    s.stopAutoSync();
  });

  testWidgets('Urdu switches the tabs and reads right to left', (t) async {
    final s = await _signedIn([]);
    await s.setLang('ur');
    await t.pumpWidget(ParentApp(session: s));
    await t.pumpAndSettle();
    expect(find.text('اسباق'), findsOneWidget);
    expect(Directionality.of(t.element(find.text('اسباق'))), TextDirection.rtl);
    s.stopAutoSync();
  });
}
