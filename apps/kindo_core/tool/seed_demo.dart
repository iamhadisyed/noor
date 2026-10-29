// Fills a test server with a demo family so both apps have something to show.
//   KINDO_BASE=http://127.0.0.1:8098/api/v1 KINDO_LOG=/path/to/laravel.log dart run tool/seed_demo.dart [email]
// The server must send mail to its log (MAIL_MAILER=log). Prints the token, so only run it against a throwaway server.
import 'dart:convert';
import 'dart:io';
import 'dart:math';
import 'dart:typed_data';

import 'package:kindo_core/kindo_core.dart';

Future<String> lastCode(String logPath) async {
  final text = await File(logPath).readAsString();
  return RegExp(r'letter-spacing:10px[^>]*>(\d{6})<').allMatches(text).last.group(1)!;
}

/// A short soft tone as a WAV, so voices and sounds exist without a recording.
Uint8List tone(double hz, [double seconds = .5]) {
  const rate = 8000;
  final n = (rate * seconds).round();
  final data = ByteData(44 + n * 2);
  void s(int o, String t) {
    for (var i = 0; i < t.length; i++) {
      data.setUint8(o + i, t.codeUnitAt(i));
    }
  }

  s(0, 'RIFF');
  data.setUint32(4, 36 + n * 2, Endian.little);
  s(8, 'WAVEfmt ');
  data.setUint32(16, 16, Endian.little);
  data.setUint16(20, 1, Endian.little);
  data.setUint16(22, 1, Endian.little);
  data.setUint32(24, rate, Endian.little);
  data.setUint32(28, rate * 2, Endian.little);
  data.setUint16(32, 2, Endian.little);
  data.setUint16(34, 16, Endian.little);
  s(36, 'data');
  data.setUint32(40, n * 2, Endian.little);
  for (var i = 0; i < n; i++) {
    final env = min(1.0, min(i / 400, (n - i) / 400));
    data.setInt16(44 + i * 2, (sin(2 * pi * hz * i / rate) * 9000 * env).round(), Endian.little);
  }

  return data.buffer.asUint8List();
}

Future<void> main(List<String> args) async {
  final base = Platform.environment['KINDO_BASE'] ?? 'http://127.0.0.1:8098/api/v1';
  final logPath = Platform.environment['KINDO_LOG'] ?? 'storage/logs/laravel.log';
  final email = args.isNotEmpty ? args.first : 'demo@kindo.test';
  final pics = Platform.environment['KINDO_PICS'] ?? '../../public/aac';

  var token = '';
  final api = KindoApi(ApiClient(baseUrl: base, token: () => token));
  await api.requestCode(email);
  final code = await lastCode(logPath);
  try {
    token = (await api.verify(email, code)).token;
  } on ApiException catch (e) {
    if (!e.termsRequired) rethrow;
    token = (await api.verify(email, code, acceptTerms: true)).token;
  }
  await api.updateMe(name: 'Sana');

  UploadFile pic(String name) => UploadFile(File('$pics/$name.png').readAsBytesSync(), '$name.png', contentType: 'image/png');
  UploadFile voice(double hz) => UploadFile(tone(hz), 'voice.wav', contentType: 'audio/wav');

  final ayan = await api.createChild(name: 'Ayan', dateOfBirth: '2018-05-04', neurotype: 'autism_spectrum');
  final lesson = await api.createLesson(ayan.id, title: 'Week 1: letters and numbers', language: 'both');
  await api.addTask(lesson.id, {'type': 'trace_letter', 'title': 'Trace the letter A', 'target': 'A', 'instruction': 'Follow the dots'});
  await api.addTask(lesson.id, {'type': 'trace_number', 'title': 'Trace the number 7', 'target': '7'});
  final water = await api.addTask(lesson.id, {'type': 'pick_picture', 'title': 'Which one is water?', 'options': {'choices': ['Water', 'Milk', 'Bed'], 'correct': 0}});
  await api.uploadTaskMedia(water.id, 'image', pic('water'));
  final spell = await api.addTask(lesson.id, {'type': 'spell_word', 'title': 'Spell bed', 'target': 'bed'});
  await api.uploadTaskMedia(spell.id, 'image', pic('bed'));
  await api.uploadTaskMedia(spell.id, 'audio', voice(330));
  await api.addTask(lesson.id, {'type': 'social_story', 'title': 'A friend takes your toy', 'instruction': 'How do you feel?', 'options': {'choices': ['Sad', 'Happy'], 'correct': 0}});
  await api.publishLesson(lesson.id);

  for (final m in [('Mama', 'Sana', 392.0), ('Baba', 'Imran', 262.0)]) {
    await api.addFamilyMember(ayan.id, relationTitle: m.$1, displayName: m.$2, photo: pic(m.$1 == 'Mama' ? 'hug' : 'happy'), audio: voice(m.$3));
  }
  await api.createAacCard(ayan.id, englishLabel: 'Blue ball', urduLabel: 'نیلی گیند', category: 'activities', icon: pic('play'), audio: voice(440));

  // a request from the child and a finished practice session, so the parent's screens are not empty
  final alert = await api.sendAlert(ayan.id, 'water');
  await api.markSeen(alert);
  await api.uploadSession(ayan.id, {
    'language': 'english',
    'total_attempts': 6,
    'successful_attempts': 5,
    'duration_seconds': 300,
    'letters': [
      {'letter': 'A', 'attempts': 3, 'successes': 2, 'assisted': 3, 'deviation': 6.5},
      {'letter': '7', 'attempts': 3, 'successes': 3, 'assisted': 1, 'deviation': 4.0},
    ],
  });
  await api.sendAlert(ayan.id, 'help');

  stdout.writeln(jsonEncode({'email': email, 'token': token, 'child_id': ayan.id}));
}
