import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:kindo_parent/app.dart';
import 'package:kindo_parent/screens/family_tools.dart' show AdSlot;
import 'package:kindo_ui/kindo_ui.dart';

http.Response _j(Object body, [int status = 200]) => http.Response(jsonEncode(body), status, headers: {'content-type': 'application/json'});

Map<String, dynamic> _ch(String e, int id, Map<String, dynamic> d) => {'entity': e, 'id': id, 'op': 'upsert', 'data': d};

/// A tiny pretend server: just enough for the parent app to show a family.
String? upgrade;
bool withAds = false;

MockClient _server(List<String> log, {bool pro = true}) => MockClient((r) async {
      log.add('${r.method} ${r.url.path}');
      final p = r.url.path.replaceFirst('/api/v1', '');
      if (p == '/me') return _j({'id': 1, 'email': 'mum@example.com', 'name': 'Sana', 'role': 'parent', 'plan': 'free', if (withAds) 'ads': {'child_directed': true, 'personalized': false, 'parent_app_only': true, 'units': {'banner_android': 'ca-app-pub-1/2', 'banner_ios': 'ca-app-pub-1/3'}}});
      if (p == '/meta') return _j({'keyboards': {'english': {'letters': 'abcdefghijklmnopqrstuvwxyz'.split(''), 'direction': 'ltr', 'columns': 7}}});
      if (p == '/sync') {
        return _j({'snapshot': true, 'cursor': 5, 'has_more': false, 'changes': [
          _ch('child', 1, {'id': 1, 'name': 'Ayan', 'track': 'special', 'neurotype': 'autism_spectrum', 'support': {'pace': 'slow'}}),
          _ch('lesson', 3, {'id': 3, 'child_id': 1, 'title': 'Colours week', 'status': 'published', 'week_number': 1, 'tasks': []}),
          _ch('need', 9, {'id': 9, 'child_id': 1, 'need_type': 'water', 'status': 'sent'}),
        ]});
      }
      if (p == '/children/1/insights/summary') return _j({'practice': {'minutes': 12, 'accuracy_pct': 80, 'sessions': 3}, 'needs': {'total': 4}, 'calm': {'minutes': 2}});
      if (p == '/announcements') return _j([]);
      if (p == '/guidance/autism') {
        const title = {'en': 'A guide for parents of a child with autism', 'ur': 'رہنما'};
        if (!pro) {
          return _j({'message': 'Pro', 'upgrade': true, 'title': title, 'preview': [{'key': 'tests', 'title': {'en': 'Health checks doctors often ask about', 'ur': 'معائنے'}}, {'key': 'food', 'title': {'en': 'Food and eating', 'ur': 'خوراک'}}]}, 402);
        }
        return _j({
          'guide': 'autism',
          'title': title,
          'disclaimer': {'en': 'This is general information only. It is not medical advice. Please talk to your own child\'s doctor.', 'ur': 'یہ صرف عمومی معلومات ہیں۔'},
          'closing': {'en': 'This guide is not a replacement for your child\'s doctor.', 'ur': 'ڈاکٹر کی جگہ نہیں'},
          'sections': [
            {'key': 'tests', 'title': {'en': 'Health checks doctors often ask about', 'ur': 'معائنے'}, 'intro': {'en': 'There is no blood test that can diagnose autism.', 'ur': 'x'}, 'items': [
              {'title': {'en': 'Iron and ferritin', 'ur': 'آئرن'}, 'text': {'en': 'Low iron is common in children.', 'ur': 'x'}},
            ]},
            {'key': 'food', 'title': {'en': 'Food and eating', 'ur': 'خوراک'}, 'intro': {'en': 'No food or diet treats or cures autism.', 'ur': 'x'}, 'items': []},
          ],
        });
      }
      if (p == '/children/1/screen-time' && r.method == 'GET') {
        if (!pro) return _j({'message': 'Pro', 'upgrade': true}, 402);
        return _j({'settings': {'enabled': true, 'daily_minutes': 60, 'quiet_start': '20:00', 'quiet_end': '07:00'}, 'today': {'date': '2025-01-02', 'used_seconds': 1200, 'allowed_seconds': 3600, 'locked': false}, 'week': [{'date': '2025-01-01', 'seconds': 600}, {'date': '2025-01-02', 'seconds': 1200}]});
      }
      if (p == '/children/1/screen-time/extend') return _j({'ok': true});
      if (p == '/children/1/activity') {
        return _j({'days': 1, 'limited': !pro, 'summary': {'practice_minutes': 7, 'game_minutes': 5, 'calm_sessions': 1, 'asked_for': 2}, 'items': [
          {'kind': 'game', 'at': '2025-01-02T10:00:00+00:00', 'title': 'Played: Space quiz', 'minutes': 5, 'detail': '4 of 5 right first time'},
        ], if (!pro) 'upgrade_hint': 'More with Pro'});
      }
      if (p == '/billing/upgrade' && r.method == 'GET') return _j({'request': upgrade == null ? null : {'status': upgrade}, 'payment': {'instructions': 'Pay by bank transfer', 'contact_email': 'pay@kindo.example'}});
      if (p == '/billing/upgrade' && r.method == 'POST') { upgrade = 'requested'; return _j({'request': {'status': 'requested'}}, 201); }
      if (p == '/billing/upgrade/paid') { upgrade = 'paid_reported'; return _j({'request': {'status': 'paid_reported'}}); }
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

  testWidgets('the autism guide (Pro): the warning comes first and last, and the sections open with plain words', (t) async {
    await t.binding.setSurfaceSize(const Size(500, 1600));
    addTearDown(() => t.binding.setSurfaceSize(null));
    final s = await _signedIn([]);
    await t.pumpWidget(ParentApp(session: s));
    await t.pumpAndSettle();
    // a child with autism gets the guide on the home screen
    await t.scrollUntilVisible(find.text('A guide for parents'), 200, scrollable: find.byType(Scrollable).first);
    await t.tap(find.text('A guide for parents'));
    await t.pumpAndSettle();
    expect(find.text('Please read first'), findsOneWidget);
    expect(find.textContaining('not medical advice'), findsOneWidget);
    expect(find.textContaining('Please talk to your own child'), findsOneWidget);
    expect(t.getTopLeft(find.text('Please read first')).dy < t.getTopLeft(find.text('Health checks doctors often ask about')).dy, isTrue);
    await t.tap(find.text('Health checks doctors often ask about'));
    await t.pumpAndSettle();
    expect(find.textContaining('no blood test that can diagnose autism'), findsOneWidget);
    expect(find.text('Iron and ferritin'), findsOneWidget);
    await t.scrollUntilVisible(find.text('Remember'), 300, scrollable: find.byType(Scrollable).last);
    expect(find.textContaining('not a replacement for your child'), findsOneWidget);
    s.stopAutoSync();
  });

  testWidgets('the autism guide on the free plan says it is part of Pro and lists what is inside', (t) async {
    final store = MemoryStore();
    await store.write('kindo.token', 'tok');
    final s = KindoSession(store: store, defaultBaseUrl: 'https://k.test/api/v1', httpClient: _server([], pro: false));
    await s.init();
    await t.binding.setSurfaceSize(const Size(500, 1600));
    addTearDown(() => t.binding.setSurfaceSize(null));
    await t.pumpWidget(ParentApp(session: s));
    await t.pumpAndSettle();
    await t.tap(find.text('More'));
    await t.pumpAndSettle();
    await t.tap(find.text('Autism guide for parents'));
    await t.pumpAndSettle();
    expect(find.text('This guide is part of Kindo Pro.'), findsOneWidget);
    expect(find.text('- Food and eating'), findsOneWidget);
    expect(find.textContaining('not medical advice'), findsNothing, reason: 'nothing of the guide is given away');
    expect(find.text('See plans'), findsOneWidget);
    s.stopAutoSync();
  });

  testWidgets('screen time (Pro): shows the rules and today, and extra minutes can be given', (t) async {
    await t.binding.setSurfaceSize(const Size(500, 1800));
    addTearDown(() => t.binding.setSurfaceSize(null));
    final log = <String>[];
    final s = await _signedIn(log);
    await t.pumpWidget(ParentApp(session: s));
    await t.pumpAndSettle();
    await t.scrollUntilVisible(find.text('Screen time'), 200, scrollable: find.byType(Scrollable).first);
    await t.tap(find.text('Screen time'));
    await t.pumpAndSettle();
    expect(find.textContaining('Daily time: 60 minutes'), findsOneWidget);
    expect(find.textContaining('Used 20 of 60 minutes'), findsOneWidget);
    expect(find.text('From 20:00'), findsOneWidget);
    await t.tap(find.text('+15 more minutes'));
    await t.pumpAndSettle();
    expect(log.any((l) => l.contains('/children/1/screen-time/extend')), isTrue);
    s.stopAutoSync();
  });

  testWidgets('screen time on the free plan says it is Pro and offers the Pro request', (t) async {
    await t.binding.setSurfaceSize(const Size(500, 1800));
    addTearDown(() => t.binding.setSurfaceSize(null));
    upgrade = null;
    final store = MemoryStore();
    await store.write('kindo.token', 'tok');
    final s = KindoSession(store: store, defaultBaseUrl: 'https://k.test/api/v1', httpClient: _server([], pro: false));
    await s.init();
    await t.pumpWidget(ParentApp(session: s));
    await t.pumpAndSettle();
    await t.scrollUntilVisible(find.text('Screen time'), 200, scrollable: find.byType(Scrollable).first);
    await t.tap(find.text('Screen time'));
    await t.pumpAndSettle();
    expect(find.text('This is part of Kindo Pro'), findsOneWidget);
    await t.tap(find.text('Get Pro'));
    await t.pumpAndSettle();
    await t.tap(find.text('Make me Pro'));
    await t.pumpAndSettle();
    expect(find.text('We emailed you how to pay.'), findsOneWidget);
    expect(find.text('Pay by bank transfer'), findsOneWidget);
    await t.enterText(find.widgetWithText(TextField, 'How did you pay? (e.g. bank, Easypaisa, JazzCash)'), 'Easypaisa');
    await t.enterText(find.widgetWithText(TextField, 'Transaction number or name on the receipt'), 'TX12345');
    await t.tap(find.text('I have paid'));
    await t.pumpAndSettle();
    expect(find.textContaining('We are checking your payment'), findsOneWidget);
    s.stopAutoSync();
  });

  testWidgets('what the child did: a timeline of today, with a nudge to Pro on the free plan', (t) async {
    await t.binding.setSurfaceSize(const Size(500, 1800));
    addTearDown(() => t.binding.setSurfaceSize(null));
    final store = MemoryStore();
    await store.write('kindo.token', 'tok');
    final s = KindoSession(store: store, defaultBaseUrl: 'https://k.test/api/v1', httpClient: _server([], pro: false));
    await s.init();
    await t.pumpWidget(ParentApp(session: s));
    await t.pumpAndSettle();
    await t.scrollUntilVisible(find.text('What Ayan did'), 200, scrollable: find.byType(Scrollable).first);
    await t.tap(find.text('What Ayan did'));
    await t.pumpAndSettle();
    expect(find.text('Played: Space quiz'), findsOneWidget);
    expect(find.text('You are seeing today only.'), findsOneWidget);
    s.stopAutoSync();
  });

  testWidgets('adverts: a free parent with ads switched on loads the home screen safely (no ad on a test machine), and never in the child text', (t) async {
    withAds = true;
    addTearDown(() => withAds = false);
    await t.binding.setSurfaceSize(const Size(500, 1800));
    addTearDown(() => t.binding.setSurfaceSize(null));
    final s = await _signedIn([]);
    await t.pumpWidget(ParentApp(session: s));
    await t.pumpAndSettle();
    expect(s.user?.raw['ads'], isNotNull);
    expect(find.text('Hello'), findsNothing);
    expect(find.byType(AdSlot), findsOneWidget);
    expect(find.textContaining('Ad (removed with Pro)'), findsNothing, reason: 'nothing is shown until an ad has really loaded');
    s.stopAutoSync();
  });
}
