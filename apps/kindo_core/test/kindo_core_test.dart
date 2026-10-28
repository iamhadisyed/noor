import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:kindo_core/kindo_core.dart';
import 'package:test/test.dart';

http.Response jsonRes(Object body, [int status = 200]) => http.Response(jsonEncode(body), status, headers: {'content-type': 'application/json'});

/// Runs [call] and returns the [ApiException] it throws (or null if it does not throw).
Future<ApiException?> fails(Future<Object?> Function() call) async {
  try {
    await call();
  } on ApiException catch (e) {
    return e;
  }
  return null;
}

ApiClient clientWith(MockClient mock, {String? token}) => ApiClient(baseUrl: 'https://kindo.test/api/v1/', client: mock, token: () => token);

void main() {
  group('ApiClient', () {
    test('asks for JSON, sends the bearer token and joins the base url cleanly', () async {
      late http.Request seen;
      final c = clientWith(MockClient((r) async {
        seen = r;
        return jsonRes({'ok': true});
      }), token: 'abc');

      await c.get('/me', query: {'days': 7, 'tz': null});

      expect(seen.url.toString(), 'https://kindo.test/api/v1/me?days=7');
      expect(seen.headers['Accept'], 'application/json');
      expect(seen.headers['Authorization'], 'Bearer abc');
    });

    test('sends no Authorization header when signed out', () async {
      late http.Request seen;
      final c = clientWith(MockClient((r) async {
        seen = r;
        return jsonRes({});
      }));
      await c.get('/legal/terms');
      expect(seen.headers.containsKey('Authorization'), isFalse);
    });

    test('post sends a JSON body and the idempotency key', () async {
      late http.Request seen;
      final c = clientWith(MockClient((r) async {
        seen = r;
        return jsonRes({'id': 5}, 201);
      }), token: 't');

      final r = await c.post('/child/alert', body: {'child_id': 1, 'need_type': 'water'}, idempotencyKey: 'dev1-0001');

      expect(r['id'], 5);
      expect(seen.headers['Idempotency-Key'], 'dev1-0001');
      expect(jsonDecode(seen.body), {'child_id': 1, 'need_type': 'water'});
      expect(seen.headers['Content-Type'], contains('application/json'));
    });

    test('a 422 keeps the message and the field errors', () async {
      final c = clientWith(MockClient((_) async => jsonRes({'message': 'The name field is required.', 'errors': {'name': ['The name field is required.']}}, 422)));
      final e = await fails(() => c.post('/children', body: {}));
      expect(e!.status, 422);
      expect(e.message, 'The name field is required.');
      expect(e.errors['name'], ['The name field is required.']);
      expect(e.isRetryable, isFalse);
    });

    test('402 is a plan limit, 423 says why a game is locked, terms_required is recognised', () async {
      final plan = ApiException(402, 'The free plan allows 3 lessons per child.', body: {'upgrade': true});
      expect(plan.needsUpgrade, isTrue);

      final c = clientWith(MockClient((r) async {
        if (r.url.path.endsWith('start')) return jsonRes({'message': 'No play time yet.', 'locked': true, 'reason': 'no_minutes'}, 423);
        return jsonRes({'message': 'Please accept.', 'terms_required': true}, 422);
      }));
      final locked = await fails(() => c.post('/children/1/games/count/start'));
      expect(locked!.lockedReason, 'no_minutes');
      final terms = await fails(() => c.post('/auth/verify'));
      expect(terms!.termsRequired, isTrue);
    });

    test('401 tells the app to sign out, once per answer, and has a plain message', () async {
      var calls = 0;
      final c = clientWith(MockClient((_) async => jsonRes({'message': 'Unauthenticated.'}, 401)));
      c.onUnauthorized = () => calls++;
      final e = await fails(() => c.get('/me'));
      expect(e!.isUnauthorized, isTrue);
      expect(calls, 1);
    });

    test('a page that is not JSON becomes a calm, generic error', () async {
      final c = clientWith(MockClient((_) async => http.Response('<html>Bad gateway</html>', 502)));
      final e = await fails(() => c.get('/me'));
      expect(e!.status, 502);
      expect(e.message, contains('our side'));
      expect(e.isRetryable, isTrue);
    });

    test('no connection becomes status 0 and is retryable', () async {
      final c = clientWith(MockClient((_) async => throw http.ClientException('Failed host lookup')));
      final e = await fails(() => c.get('/me'));
      expect(e!.isNetwork, isTrue);
      expect(e.isRetryable, isTrue);
      expect(e.message, contains('Cannot reach Kindo'));
    });

    test('429 is retryable', () {
      expect(ApiException(429, 'slow down').isRetryable, isTrue);
      expect(ApiException(404, 'gone').isRetryable, isFalse);
    });

    test('files go as multipart with the fields and the file name', () async {
      late http.BaseRequest seen;
      late String body;
      final c = clientWith(MockClient.streaming((req, stream) async {
        seen = req;
        body = utf8.decode(await stream.expand((x) => x).toList(), allowMalformed: true);
        return http.StreamedResponse(Stream.value(utf8.encode(jsonEncode({'id': 9}))), 201, headers: {'content-type': 'application/json'});
      }), token: 'tok');

      final r = await c.postMultipart('/children/1/aac-cards', fields: {'english_label': 'Car', 'category': 'activities'}, files: {'icon': UploadFile([1, 2, 3], 'car.jpg', contentType: 'image/jpeg')});

      expect(r['id'], 9);
      expect(seen.headers['Authorization'], 'Bearer tok');
      expect(seen.headers['content-type'], startsWith('multipart/form-data'));
      expect(body, contains('name="english_label"'));
      expect(body, contains('filename="car.jpg"'));
    });

    test('downloads return the bytes and turn errors into exceptions', () async {
      final ok = clientWith(MockClient((_) async => http.Response.bytes([37, 80, 68, 70], 200)));
      expect(await ok.getBytes('/pecs.pdf'), [37, 80, 68, 70]);
      final bad = clientWith(MockClient((_) async => jsonRes({'message': 'Pro only'}, 402)));
      expect((await fails(() => bad.getBytes('/x')))!.status, 402);
    });

    test('idempotency keys are unique and safe', () {
      final keys = {for (var i = 0; i < 200; i++) newIdempotencyKey('pixel 7!')};
      expect(keys.length, 200);
      for (final k in keys) {
        expect(k.length, inInclusiveRange(8, 80));
        expect(k, matches(RegExp(r'^[A-Za-z0-9._:-]+$')));
      }
    });
  });

  group('KindoApi', () {
    test('verify sends accept_terms only when asked and parses the user', () async {
      final bodies = <Map>[];
      final api = KindoApi(clientWith(MockClient((r) async {
        bodies.add(jsonDecode(r.body) as Map);
        return jsonRes({'token': '1|abc', 'is_new': true, 'user': {'id': 1, 'email': 'a@b.c', 'name': 'a', 'role': 'parent', 'plan': 'free'}});
      })));

      final r = await api.verify('a@b.c', '123456');
      await api.verify('a@b.c', '123456', acceptTerms: true);

      expect(r.token, '1|abc');
      expect(r.isNew, isTrue);
      expect(r.user.isPro, isFalse);
      expect(bodies[0].containsKey('accept_terms'), isFalse);
      expect(bodies[1]['accept_terms'], isTrue);
    });

    test('startGame sends the language and parses the whole session', () async {
      late Map body;
      final api = KindoApi(clientWith(MockClient((r) async {
        body = jsonDecode(r.body) as Map;
        expect(r.url.path, '/api/v1/children/3/games/first_letter/start');
        return jsonRes({'session_id': 7, 'game': 'first_letter', 'level': 2, 'allowed_seconds': 300, 'balance_minutes': 5, 'rounds': [{'n': 1, 'options': ['a', 'b'], 'answer_index': 0}]}, 201);
      })));

      final s = await api.startGame(3, 'first_letter', rounds: 5, language: 'ur');

      expect(body, {'rounds': 5, 'language': 'ur'});
      expect(s.sessionId, 7);
      expect(s.rounds, hasLength(1));
      expect(s.allowedSeconds, 300);
    });

    test('latest AI report is null when there is none yet', () async {
      final api = KindoApi(clientWith(MockClient((_) async => jsonRes({'message': 'No report yet.'}, 404))));
      expect(await api.latestAiReport(1), isNull);
    });

    test('the milestone card asks for the language and whether to show the name', () async {
      late Uri url;
      final api = KindoApi(clientWith(MockClient((r) async {
        url = r.url;
        return http.Response.bytes([1, 2], 200);
      })));
      await api.milestoneCard(2, 'streak_7', lang: 'ur', showName: false);
      expect(url.path, '/api/v1/children/2/milestones/streak_7/card.png');
      expect(url.queryParameters, {'lang': 'ur', 'name': '0'});
    });
  });

  group('models', () {
    test('a child reads its support profile and balance', () {
      final c = Child.fromJson({'id': 1, 'name': 'Ayan', 'track': 'special', 'date_of_birth': '2020-05-04T00:00:00.000000Z', 'support': {'magnet_px': 32, 'pace': 'slow', 'max_choices': 3, 'no_timers': true, 'first_then': true, 'praise': 'quiet', 'guide': 'arrows', 'fade_assist': false}, 'rewards': {'balance_minutes': 12}});
      expect(c.isSpecial, isTrue);
      expect(c.support.maxChoices, 3);
      expect(c.support.noTimers, isTrue);
      expect(c.support.slow, isTrue);
      expect(c.support.guide, 'arrows');
      expect(c.balanceMinutes, 12);
      expect(c.ageYears(DateTime(2026, 10, 28)), 6);
      expect(Child.fromJson({'id': 2, 'name': 'x'}).support.maxChoices, 4);
    });

    test('a lesson task reads its choices, keyboard and media', () {
      final t = LessonTask.fromJson({'id': 1, 'position': 1, 'type': 'spell_word', 'title': 'Cat', 'target': 'cat', 'keyboard': 'english', 'options': {'choices': ['a', 'b'], 'correct': 1}, 'has_image': true, 'image_url': 'https://x/y.jpg', 'requires_sound': true});
      expect(t.keyboard, 'english');
      expect(t.choices, ['a', 'b']);
      expect(t.correct, 1);
      expect(t.requiresSound, isTrue);
      expect(t.imageUrl, 'https://x/y.jpg');
    });

    test('a lesson reads its tasks, status and what stops it publishing', () {
      final l = Lesson.fromJson({'id': 4, 'child_id': 1, 'title': 'W1', 'status': 'draft', 'language': 'urdu', 'ready': false, 'problems': [{'task_id': 9, 'missing': ['sound'], 'message': 'Task 1 needs a sound.'}], 'tasks': [{'id': 9, 'type': 'spell_word', 'title': 'x', 'position': 1}]});
      expect(l.isPublished, isFalse);
      expect(l.problems.single['message'], 'Task 1 needs a sound.');
      expect(l.tasks.single.id, 9);
    });

    test('a calm activity adds up its time', () {
      final a = CalmActivity.fromJson({'activity': 'breathing_bubble', 'kind': 'breathing', 'title': {'en': 'Breathe', 'ur': 'سانس'}, 'about': {'en': '', 'ur': ''}, 'cycles': 5, 'steps': [{'cue': 'in', 'seconds': 4, 'text': {'en': 'In', 'ur': 'اندر'}}, {'cue': 'out', 'seconds': 6, 'text': {'en': 'Out', 'ur': 'باہر'}}]});
      expect(a.totalSeconds, 50);
      expect(a.steps.first.text.of('ur'), 'اندر');
    });

    test('games say which track they suit', () {
      final g = GameInfo.fromJson({'game': 'feelings', 'category': 'social', 'title': {'en': 'Feelings', 'ur': 'احساسات'}, 'for': ['special'], 'unlocked': true, 'level': 2});
      expect(g.suits('special'), isTrue);
      expect(g.suits('standard'), isFalse);
      expect(g.title.of('en'), 'Feelings');
    });

    test('Bilingual falls back to the other language when one is empty', () {
      expect(const Bilingual('Hello', '').of('ur'), 'Hello');
      expect(const Bilingual('', 'سلام').of('en'), 'سلام');
    });

    test('a stock card names the need a child sends', () {
      final c = AacCard.fromJson({'id': 1, 'category': 'needs', 'english_label': 'Water', 'urdu_label': 'پانی', 'icon_path': 'aac/water.png', 'custom': false});
      expect(c.needType, 'water');
      expect(c.label('ur'), 'پانی');
    });
  });
}
