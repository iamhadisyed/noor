import 'package:kindo_core/kindo_core.dart';
import 'package:test/test.dart';

Child child(Map<String, dynamic>? st) => Child.fromJson({'id': 1, 'name': 'Ayan', 'track': 'standard', 'screen_time': st});

void main() {
  group('ScreenTimeRules', () {
    test('rules exist only when screen time is switched on', () {
      expect(ScreenTimeRules.of(child(null)), isNull);
      expect(ScreenTimeRules.of(child({'enabled': false, 'daily_minutes': 30})), isNull);
      final r = ScreenTimeRules.of(child({'enabled': true, 'daily_minutes': 45, 'quiet_start': '20:00', 'quiet_end': '07:00'}))!;
      expect(r.dailyMinutes, 45);
      expect(r.quietStart, 20 * 60);
    });

    test('quiet hours can run past midnight', () {
      final r = ScreenTimeRules.of(child({'enabled': true, 'daily_minutes': 45, 'quiet_start': '20:00', 'quiet_end': '07:00'}))!;
      expect(r.isQuiet(DateTime(2025, 1, 1, 21)), isTrue);
      expect(r.isQuiet(DateTime(2025, 1, 1, 3)), isTrue);
      expect(r.isQuiet(DateTime(2025, 1, 1, 7)), isFalse);
      expect(r.isQuiet(DateTime(2025, 1, 1, 12)), isFalse);
      final day = ScreenTimeRules.of(child({'enabled': true, 'daily_minutes': 45, 'quiet_start': '13:00', 'quiet_end': '15:00'}))!;
      expect(day.isQuiet(DateTime(2025, 1, 1, 14)), isTrue);
      expect(day.isQuiet(DateTime(2025, 1, 1, 16)), isFalse);
      expect(ScreenTimeRules.of(child({'enabled': true, 'daily_minutes': 45}))!.isQuiet(DateTime(2025, 1, 1, 2)), isFalse);
    });
  });

  group('ScreenTimeTracker', () {
    final noon = DateTime(2025, 3, 3, 12);
    final rules = ScreenTimeRules.of(child({'enabled': true, 'daily_minutes': 10}));

    test('no rules never locks', () async {
      final t = ScreenTimeTracker(MemoryStore());
      await t.load(1, noon);
      await t.add(99999, noon);
      expect(t.status(null, noon).locked, isFalse);
    });

    test('locks when the daily allowance is used, and extra minutes open it again', () async {
      final t = ScreenTimeTracker(MemoryStore());
      await t.load(1, noon);
      await t.add(9 * 60, noon);
      expect(t.status(rules, noon).locked, isFalse);
      expect(t.status(rules, noon).remainingSeconds, 60);
      await t.add(60, noon);
      expect(t.status(rules, noon).lock, ScreenLock.limit);
      await t.applyServer(usedSeconds: 600, bonusSeconds: 15 * 60);
      expect(t.status(rules, noon).locked, isFalse);
      expect(t.status(rules, noon).remainingSeconds, 15 * 60);
    });

    test('quiet hours lock even with time left', () async {
      final t = ScreenTimeTracker(MemoryStore());
      final night = DateTime(2025, 3, 3, 21);
      final q = ScreenTimeRules.of(child({'enabled': true, 'daily_minutes': 60, 'quiet_start': '20:00', 'quiet_end': '07:00'}));
      await t.load(1, night);
      expect(t.status(q, night).lock, ScreenLock.quiet);
    });

    test('the tally survives a restart, starts again the next day, and unreported time is sent once', () async {
      final store = MemoryStore();
      var t = ScreenTimeTracker(store);
      await t.load(1, noon);
      await t.add(120, noon);
      expect(t.nextReport(), 120);
      await t.markReported(120);
      await t.add(30, noon);
      expect(t.nextReport(), 30);

      t = ScreenTimeTracker(store);
      await t.load(1, noon);
      expect(t.usedSeconds, 150);
      expect(t.nextReport(), 30);
      await t.load(1, DateTime(2025, 3, 4, 8));
      expect(t.usedSeconds, 0);
      await t.add(5, noon);   // a tick after midnight rolls to a clean day
      await t.add(5, DateTime(2025, 3, 4, 8));
      expect(t.usedSeconds, 5);
    });

    test('a report is never bigger than an hour; each child has their own tally', () async {
      final store = MemoryStore();
      final t = ScreenTimeTracker(store);
      await t.load(1, noon);
      await t.add(7200, noon);
      expect(t.nextReport(), 3600);
      final other = ScreenTimeTracker(store);
      await other.load(2, noon);
      expect(other.usedSeconds, 0);
    });
  });
}
