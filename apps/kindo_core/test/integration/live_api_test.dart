@Tags(['live'])
library;

import 'dart:io';
import 'dart:typed_data';

import 'package:kindo_core/kindo_core.dart';
import 'package:test/test.dart';

/// Runs the apps' whole contract against a real server.
///   KINDO_BASE=http://127.0.0.1:8098/api/v1 KINDO_LOG=/path/to/laravel.log dart test -t live
/// The server must send mail to its log (MAIL_MAILER=log) so the sign-in code can be read back.
final base = Platform.environment['KINDO_BASE'];
final logPath = Platform.environment['KINDO_LOG'];

Future<String> lastCode() async {
  final text = await File(logPath!).readAsString();
  return RegExp(r'letter-spacing:10px[^>]*>(\d{6})<').allMatches(text).last.group(1)!;
}

/// A small valid PNG, built here so the test does not depend on hand-typed bytes.
Uint8List makePng([int w = 16, int h = 16]) {
  final crcTable = List<int>.generate(256, (n) {
    var c = n;
    for (var k = 0; k < 8; k++) {
      c = (c & 1) == 1 ? 0xEDB88320 ^ (c >> 1) : c >> 1;
    }
    return c;
  });
  int crc(List<int> bytes) {
    var c = 0xFFFFFFFF;
    for (final b in bytes) {
      c = crcTable[(c ^ b) & 0xFF] ^ (c >> 8);
    }
    return c ^ 0xFFFFFFFF;
  }

  List<int> u32(int v) => [(v >> 24) & 255, (v >> 16) & 255, (v >> 8) & 255, v & 255];
  List<int> chunk(String type, List<int> data) {
    final body = [...type.codeUnits, ...data];
    return [...u32(data.length), ...body, ...u32(crc(body))];
  }

  final raw = <int>[];
  for (var y = 0; y < h; y++) {
    raw.add(0);
    for (var x = 0; x < w; x++) {
      raw.addAll([240, 130 + (x * 4), 200]);
    }
  }
  return Uint8List.fromList([
    0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A,
    ...chunk('IHDR', [...u32(w), ...u32(h), 8, 2, 0, 0, 0]),
    ...chunk('IDAT', ZLibCodec().encode(raw)),
    ...chunk('IEND', []),
  ]);
}

void main() {
  if (base == null || logPath == null) {
    test('live server tests are skipped (set KINDO_BASE and KINDO_LOG)', () {}, skip: 'no server configured');
    return;
  }

  late ApiClient client;
  late KindoApi api;
  late String email;
  var token = '';
  late Child child;
  final unauthorized = <int>[];

  setUpAll(() async {
    email = 'dart${DateTime.now().millisecondsSinceEpoch}@kindo.test';
    client = ApiClient(baseUrl: base!, token: () => token, onUnauthorized: () => unauthorized.add(1));
    api = KindoApi(client);
  });

  test('sign in: the first code needs the terms, then the same code works with them accepted', () async {
    await api.requestCode(email);
    final code = await lastCode();
    final refused = await () async {
      try {
        await api.verify(email, code);
      } on ApiException catch (e) {
        return e;
      }
      return null;
    }();
    expect(refused?.termsRequired, isTrue);

    final r = await api.verify(email, code, acceptTerms: true);
    token = r.token;
    expect(r.isNew, isTrue);
    expect((await api.me()).email, email);
    expect((await api.terms())['sections'], isNotEmpty);
  });

  test('meta gives the alphabet-order keyboards and the task types', () async {
    final meta = await api.meta();
    final kb = KeyboardLayout.fromMeta(meta);
    expect(kb['english']!.letters.take(5), ['a', 'b', 'c', 'd', 'e']);
    expect(kb['urdu']!.isRtl, isTrue);
    final types = (await api.taskTypes()).map((t) => t['type']).toList();
    expect(types, containsAll(['trace_number', 'count_objects', 'say_it', 'social_story', 'spell_word']));
  });

  test('a child, a lesson with every kind of task, and a publish that lists what is missing', () async {
    child = await api.createChild(name: 'Ayan', dateOfBirth: '2020-05-04', neurotype: 'speech_delay');
    expect(child.isSpecial, isTrue);
    expect(child.support.noTimers, isTrue);

    final lesson = await api.createLesson(child.id, title: 'Week 1', language: 'both');
    expect(lesson.status, 'draft');
    expect(lesson.weekNumber, 1, reason: 'a create response carries the same values as a later read');
    await api.addTask(lesson.id, {'type': 'trace_number', 'title': 'Seven', 'target': '7'});
    await api.addTask(lesson.id, {'type': 'spell_word', 'title': 'Cat', 'target': 'cat'});
    final social = await api.addTask(lesson.id, {'type': 'social_story', 'title': 'A friend takes your toy', 'instruction': 'How do you feel?', 'options': {'choices': ['sad', 'happy'], 'correct': 0}});
    expect(social.choices, ['sad', 'happy']);

    try {
      await api.publishLesson(lesson.id);
      fail('publishing should have been refused');
    } on ApiException catch (e) {
      expect(e.status, 422);
      expect((e.body['problems'] as List).single['missing'], contains('sound'), reason: 'the spell task needs a sound');
    }
    final read = await api.lesson(lesson.id);
    expect(read.tasks.firstWhere((t) => t.type == 'spell_word').keyboard, 'english');
  });

  test('the free plan says so when a limit is reached', () async {
    try {
      await api.createChild(name: 'Second', dateOfBirth: '2021-01-01');
      fail('the free plan allows one child');
    } on ApiException catch (e) {
      expect(e.needsUpgrade, isTrue);
      expect(e.status, 402);
    }
  });

  test('an alert is sent once even if the app sends it twice, and the parent can answer it', () async {
    final key = newIdempotencyKey('qa');
    final a = await api.sendAlert(child.id, 'water', idempotencyKey: key);
    final b = await api.sendAlert(child.id, 'water', idempotencyKey: key);
    expect(b, a, reason: 'the retry returned the first answer');
    expect(await api.needs(child.id), hasLength(1));
    expect((await api.markSeen(a)).status, 'seen');
    expect((await api.acknowledge(a)).answered, isTrue);
  });

  test('tracing: a careful trace becomes a practice session the server accepts, with letters', () async {
    const engine = TracingEngine();
    final log = PracticeLog(language: 'urdu');
    for (final letter in ['ا', 'ب', 'ب']) {
      final g = StrokeLibrary.lookup(letter)!;
      final strokes = [for (final s in g.strokes) TracingEngine.resample(s.map((p) => p.scaled(360)).toList(), 50), for (final d in g.dots) [d.scaled(360)]];
      log.addTry(letter, engine.evaluate(userStrokes: strokes, glyph: g, canvasSize: 360, magnetPx: child.support.magnetPx), assisted: true);
    }
    final r = await api.uploadSession(child.id, log.build(), idempotencyKey: newIdempotencyKey('qa'));
    expect(r['reward']['earned'], greaterThan(0));
    final letters = await api.letters(child.id);
    expect(letters.map((l) => l.letter), containsAll(['ا', 'ب']));
    expect(letters.firstWhere((l) => l.letter == 'ب').attempts, 2);
  });

  test('every game can be started, played with the runner and finished', () async {
    await api.addBonusMinutes(child.id, 60);
    final games = await api.games(child.id);
    expect(games.length, greaterThanOrEqualTo(39));
    var played = 0;
    for (final info in games.where((g) => g.available)) {
      final start = await api.startGame(child.id, info.game, rounds: 5, language: 'en');
      final run = GameRunner(start);
      while (!run.finished) {
        switch (run.kind) {
          case RoundKind.choice:
            run.answerChoice((run.current['answer_index'] as num).toInt());
          case RoundKind.spell || RoundKind.scramble || RoundKind.guess:
            run.answerSpelling('${run.current['target']}');
          case RoundKind.order:
            run.answerOrder((run.current['answer_order'] as List).map((e) => '$e').toList());
          case RoundKind.memory:
            run.finishMemory(0);
        }
        run.next();
      }
      final rep = run.report();
      expect(rep.correct, rep.roundsPlayed, reason: '${info.game}: answering with the right answers must score full marks');
      final done = await api.finishGame(start.sessionId, roundsPlayed: rep.roundsPlayed, correct: rep.correct, secondsPlayed: 20);
      expect(done['accuracy'], 100);
      played++;
    }
    expect(played, greaterThanOrEqualTo(37), reason: 'the two animal sound games need an administrator to add pictures and sounds first');
  });

  test('a locked game says why', () async {
    final info = (await api.games(child.id)).firstWhere((g) => !g.available, orElse: () => throw TestFailure('expected the sound games to be unavailable'));
    try {
      await api.startGame(child.id, info.game);
      fail('should be locked');
    } on ApiException catch (e) {
      expect(e.lockedReason, 'no_content');
    }
  });

  test('calm activities are listed and counted', () async {
    final all = await api.activities();
    expect(all.map((a) => a.activity), containsAll(['breathing_bubble', 'balloon_belly', 'good_things']));
    expect(all.every((a) => a.emoji.isNotEmpty), isTrue);
    expect(all.every((a) => a.steps.isNotEmpty && a.steps.every((s) => s.text.ur.isNotEmpty)), isTrue);
    await api.calmDone(child.id, 'breathing_bubble', seconds: 60);
    final s = await api.insightsSummary(child.id);
    expect(s['calm']['sessions'], 1);
  });

  test('picture cards: make one with a picture, see it in the list and in sync, delete it', () async {
    final card = await api.createAacCard(child.id, englishLabel: 'Blue car', category: 'activities', icon: UploadFile(makePng(), 'car.png', contentType: 'image/png'));
    expect(card.custom, isTrue);
    expect(card.iconUrl, isNotNull);
    expect((await client.download(card.iconUrl!)).length, greaterThan(100), reason: 'the signed link serves the picture');
    expect((await api.aacCards()).any((c) => c.id == card.id), isTrue);

    final sync = SyncEngine(api, MemoryStore());
    await sync.sync();
    expect(sync.aacCardsFor(child.id).map((c) => c.id), contains(card.id));
    expect(sync.children.single.name, 'Ayan');
    expect(sync.lessonsFor(child.id), isNotEmpty);
    expect(sync.needsFor(child.id), hasLength(1));

    await api.deleteAacCard(card.id);
    await sync.sync();
    expect(sync.aacCardsFor(child.id), isEmpty);
  });

  test('reports: milestone card is a PNG and the report is a PDF', () async {
    final ms = await api.milestones(child.id);
    final pdf = await api.reportPdf(child.id);
    expect(String.fromCharCodes(pdf.take(4)), '%PDF');
    if (ms.isNotEmpty) {
      final card = await api.milestoneCard(child.id, ms.first.key, lang: 'ur');
      expect(card.take(4), [0x89, 0x50, 0x4E, 0x47]);
    }
  });

  test('a token that stops working is reported once and every call then fails cleanly', () async {
    final old = token;
    token = 'bad-token';
    try {
      await api.me();
      fail('should be refused');
    } on ApiException catch (e) {
      expect(e.isUnauthorized, isTrue);
    }
    expect(unauthorized, hasLength(1));
    token = old;
  });
}
