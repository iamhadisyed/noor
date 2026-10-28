import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:kindo_core/kindo_core.dart';
import 'package:test/test.dart';

http.Response jsonRes(Object body, [int status = 200]) => http.Response(jsonEncode(body), status, headers: {'content-type': 'application/json'});

Map<String, dynamic> change(String entity, int id, String op, [Map<String, dynamic>? data]) => {'entity': entity, 'id': id, 'op': op, if (data != null) 'data': data};

void main() {
  group('SyncEngine', () {
    late List<Map<String, dynamic>> pages;
    late List<Uri> asked;
    late KindoApi api;

    setUp(() {
      asked = [];
      pages = [];
      api = KindoApi(ApiClient(baseUrl: 'https://k.test/api/v1', token: () => 't', client: MockClient((r) async {
        asked.add(r.url);
        return jsonRes(pages.removeAt(0));
      })));
    });

    test('the first sync is a snapshot and later ones ask only for what changed', () async {
      pages = [
        {'snapshot': true, 'cursor': 10, 'has_more': false, 'changes': [
          change('child', 1, 'upsert', {'id': 1, 'name': 'Ayan', 'track': 'standard'}),
          change('lesson', 7, 'upsert', {'id': 7, 'child_id': 1, 'title': 'W1', 'status': 'published', 'week_number': 2, 'tasks': []}),
          change('lesson', 8, 'upsert', {'id': 8, 'child_id': 1, 'title': 'Old', 'status': 'archived', 'week_number': 1, 'tasks': []}),
        ]},
        {'snapshot': false, 'cursor': 12, 'has_more': false, 'changes': [
          change('lesson', 7, 'upsert', {'id': 7, 'child_id': 1, 'title': 'W1 (renamed)', 'status': 'published', 'week_number': 2, 'tasks': []}),
          change('need', 90, 'upsert', {'id': 90, 'child_id': 1, 'need_type': 'water', 'status': 'sent'}),
        ]},
      ];
      final sync = SyncEngine(api, MemoryStore());

      final first = await sync.sync();
      final second = await sync.sync();

      expect(asked[0].queryParameters.containsKey('cursor'), isFalse);
      expect(asked[1].queryParameters['cursor'], '10');
      expect(first.changes, 3);
      expect(first.reset, isTrue);
      expect(second.reset, isFalse);
      expect(sync.cursor, 12);
      expect(sync.children.single.name, 'Ayan');
      expect(sync.lessonsFor(1).map((l) => l.title), ['W1 (renamed)'], reason: 'archived lessons are hidden');
      expect(sync.needsFor(1).single.needType, 'water');
    });

    test('deletes remove things, in order, and unknown kinds are ignored', () async {
      pages = [
        {'snapshot': true, 'cursor': 1, 'has_more': false, 'changes': [change('family_member', 5, 'upsert', {'id': 5, 'child_id': 1, 'relation_title': 'Mama', 'display_name': 'Mama'})]},
        {'cursor': 3, 'has_more': false, 'changes': [change('family_member', 5, 'delete'), change('hologram', 1, 'upsert', {'id': 1}), change('aac_card', 2, 'upsert', {'id': 2, 'child_id': 1, 'english_label': 'Car', 'urdu_label': 'x', 'category': 'activities', 'custom': true})]},
      ];
      final sync = SyncEngine(api, MemoryStore());
      await sync.sync();
      expect(sync.familyFor(1), hasLength(1));
      await sync.sync();
      expect(sync.familyFor(1), isEmpty);
      expect(sync.aacCardsFor(1).single.englishLabel, 'Car');
    });

    test('it keeps paging while the server says there is more and saves the cursor each time', () async {
      pages = [
        {'snapshot': true, 'cursor': 5, 'has_more': true, 'changes': [change('child', 1, 'upsert', {'id': 1, 'name': 'A'})]},
        {'cursor': 9, 'has_more': false, 'changes': [change('child', 2, 'upsert', {'id': 2, 'name': 'B'})]},
      ];
      final sync = SyncEngine(api, MemoryStore());
      final r = await sync.sync();
      expect(r.pages, 2);
      expect(asked[1].queryParameters['cursor'], '5');
      expect(sync.children.map((c) => c.name), ['A', 'B']);
      expect(sync.cursor, 9);
    });

    test('a reset throws away the old copy and starts from the snapshot in the same answer', () async {
      pages = [
        {'snapshot': true, 'cursor': 4, 'has_more': false, 'changes': [change('child', 1, 'upsert', {'id': 1, 'name': 'Old family'})]},
        {'snapshot': true, 'reset': true, 'cursor': 20, 'has_more': false, 'changes': [change('child', 9, 'upsert', {'id': 9, 'name': 'New family'})]},
      ];
      final sync = SyncEngine(api, MemoryStore());
      await sync.sync();
      final r = await sync.sync();
      expect(r.reset, isTrue);
      expect(sync.children.map((c) => c.name), ['New family']);
    });

    test('what was saved is there after a restart, and the next sync continues from the cursor', () async {
      final store = MemoryStore();
      pages = [
        {'snapshot': true, 'cursor': 7, 'has_more': false, 'changes': [change('child', 1, 'upsert', {'id': 1, 'name': 'Ayan'}), change('lesson', 2, 'upsert', {'id': 2, 'child_id': 1, 'title': 'L', 'status': 'published', 'tasks': []})]},
        {'cursor': 8, 'has_more': false, 'changes': []},
      ];
      await SyncEngine(api, store).sync();

      final again = SyncEngine(api, store);
      await again.load();
      expect(again.children.single.name, 'Ayan');
      expect(again.lessonsFor(1), hasLength(1));
      expect(again.cursor, 7);
      await again.sync();
      expect(asked.last.queryParameters['cursor'], '7');
    });

    test('a damaged saved copy is dropped instead of crashing', () async {
      final store = MemoryStore();
      await store.write('kindo.sync', '{not json');
      final sync = SyncEngine(api, store);
      await sync.load();
      expect(sync.children, isEmpty);
      expect(sync.cursor, isNull);
      expect(await store.read('kindo.sync'), isNull);
    });

    test('clear forgets everything (sign-out)', () async {
      pages = [{'snapshot': true, 'cursor': 1, 'has_more': false, 'changes': [change('child', 1, 'upsert', {'id': 1, 'name': 'A'})]}];
      final store = MemoryStore();
      final sync = SyncEngine(api, store);
      await sync.sync();
      await sync.clear();
      expect(sync.children, isEmpty);
      expect(await store.read('kindo.sync'), isNull);
    });

    test('onChange fires when something changed and not when nothing did', () async {
      pages = [
        {'snapshot': true, 'cursor': 1, 'has_more': false, 'changes': [change('child', 1, 'upsert', {'id': 1, 'name': 'A'})]},
        {'cursor': 1, 'has_more': false, 'changes': []},
      ];
      final sync = SyncEngine(api, MemoryStore());
      var fired = 0;
      sync.onChange.listen((_) => fired++);
      await sync.sync();
      await sync.sync();
      await Future<void>.delayed(Duration.zero);
      expect(fired, 1);
    });
  });

  group('Outbox', () {
    late List<http.Request> sent;
    late List<int> statuses;
    late ApiClient client;

    setUp(() {
      sent = [];
      statuses = [];
      client = ApiClient(baseUrl: 'https://k.test/api/v1', token: () => 't', client: MockClient((r) async {
        sent.add(r);
        final s = statuses.isEmpty ? 200 : statuses.removeAt(0);
        return jsonRes(s < 300 ? {'id': 1} : {'message': 'No ($s)'}, s);
      }));
    });

    test('writes go out in the order they were made, each with its own key', () async {
      final box = Outbox(client, MemoryStore());
      await box.add('POST', '/child/alert', body: {'child_id': 1, 'need_type': 'water'});
      await box.add('POST', '/children/1/sessions', body: {'language': 'urdu'});

      final r = await box.flush();

      expect(r.sent, 2);
      expect(r.pending, 0);
      expect(sent.map((s) => s.url.path), ['/api/v1/child/alert', '/api/v1/children/1/sessions']);
      expect(sent[0].headers['Idempotency-Key'], isNot(sent[1].headers['Idempotency-Key']));
    });

    test('no connection stops the flush and keeps everything, and the retry reuses the same key', () async {
      final box = Outbox(client, MemoryStore());
      await box.add('POST', '/child/alert', body: {'child_id': 1, 'need_type': 'water'});
      await box.add('POST', '/child/alert', body: {'child_id': 1, 'need_type': 'milk'});
      statuses = [503];

      final first = await box.flush();
      expect(first.sent, 0);
      expect(first.pending, 2);
      expect(first.stoppedBy!.isRetryable, isTrue);
      final key = sent.first.headers['Idempotency-Key'];

      final second = await box.flush();
      expect(second.sent, 2);
      expect(sent[1].headers['Idempotency-Key'], key, reason: 'a retry is the same request, so the server can recognise it');
    });

    test('a write the server refuses is set aside with its reason and the rest carry on', () async {
      final box = Outbox(client, MemoryStore());
      final bad = await box.add('POST', '/children/1/sessions', body: {'language': 'klingon'});
      await box.add('POST', '/child/alert', body: {'child_id': 1, 'need_type': 'water'});
      statuses = [422];

      final r = await box.flush();

      expect(r.sent, 1);
      expect(r.pending, 0);
      expect(r.rejected.single.id, bad.id);
      expect(r.rejected.single.error, 'No (422)');
      await box.discardRejected(bad.id);
      expect(box.rejected, isEmpty);
    });

    test('an expired sign-in stops sending but keeps the writes', () async {
      final box = Outbox(client, MemoryStore());
      await box.add('POST', '/child/alert', body: {'child_id': 1, 'need_type': 'water'});
      statuses = [401];
      final r = await box.flush();
      expect(r.pending, 1);
      expect(r.stoppedBy!.isUnauthorized, isTrue);
    });

    test('the queue survives a restart', () async {
      final store = MemoryStore();
      final a = Outbox(client, store);
      await a.add('POST', '/child/alert', body: {'child_id': 1, 'need_type': 'water'});

      final b = Outbox(client, store);
      await b.load();
      expect(b.pending, hasLength(1));
      expect((await b.flush()).sent, 1);
      expect(sent.single.body, contains('water'));
    });

    test('two flushes at once do not send anything twice', () async {
      final box = Outbox(client, MemoryStore());
      await box.add('POST', '/child/alert', body: {'child_id': 1, 'need_type': 'water'});
      await Future.wait([box.flush(), box.flush()]);
      expect(sent, hasLength(1));
    });
  });
}
