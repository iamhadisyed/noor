import 'dart:convert';

import 'models/models.dart';
import 'store/local_store.dart';

/// A parent's screen time rules for one child. The server only sends them while the household has Pro, so a child with no rules is never locked.
class ScreenTimeRules {
  const ScreenTimeRules({required this.dailyMinutes, this.quietStart, this.quietEnd});

  /// Rules from a synced child, or null when screen time is off (or the plan lapsed).
  static ScreenTimeRules? of(Child child) {
    final m = child.raw['screen_time'];
    if (m is! Map || m['enabled'] != true) return null;
    return ScreenTimeRules(
      dailyMinutes: (m['daily_minutes'] as num?)?.toInt() ?? 60,
      quietStart: _minutes(m['quiet_start']),
      quietEnd: _minutes(m['quiet_end']),
    );
  }

  final int dailyMinutes;

  /// Minutes after midnight, or null for no quiet hours.
  final int? quietStart;
  final int? quietEnd;

  static int? _minutes(Object? v) {
    final p = '$v'.split(':');
    if (p.length != 2) return null;
    final h = int.tryParse(p[0]), m = int.tryParse(p[1]);
    return h == null || m == null ? null : h * 60 + m;
  }

  /// Is [now] inside the quiet hours? They may run past midnight (20:00 to 07:00).
  bool isQuiet(DateTime now) {
    final s = quietStart, e = quietEnd;
    if (s == null || e == null || s == e) return false;
    final t = now.hour * 60 + now.minute;
    return s < e ? (t >= s && t < e) : (t >= s || t < e);
  }
}

enum ScreenLock { none, limit, quiet }

class ScreenTimeStatus {
  const ScreenTimeStatus(this.lock, this.remainingSeconds);
  final ScreenLock lock;

  /// Seconds left today (meaningful when not locked by quiet hours).
  final int remainingSeconds;
  bool get locked => lock != ScreenLock.none;
}

/// Counts the time a child uses the app, per day, and works out when it is "cool time". It keeps counting with no network and
/// reports the unreported seconds when it can. A parent's extra minutes (from the server) are added to today's allowance.
class ScreenTimeTracker {
  ScreenTimeTracker(this.store);
  final LocalStore store;

  int _child = 0;
  String _day = '';
  int _used = 0;
  int _reported = 0;
  int _bonus = 0;

  int get usedSeconds => _used;
  int get unreportedSeconds => _used - _reported;
  int get bonusSeconds => _bonus;

  static String dayOf(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
  String get _key => 'kindo.screen_time.$_child';

  /// Load today's tally for a child. A new day starts from zero.
  Future<void> load(int childId, DateTime now) async {
    _child = childId;
    _day = dayOf(now);
    _used = _reported = _bonus = 0;
    final raw = await store.read(_key);
    if (raw != null) {
      try {
        final j = jsonDecode(raw) as Map;
        if (j['day'] == _day) {
          _used = (j['used'] as num?)?.toInt() ?? 0;
          _reported = (j['reported'] as num?)?.toInt() ?? 0;
          _bonus = (j['bonus'] as num?)?.toInt() ?? 0;
        }
      } catch (_) {/* a damaged tally starts again from zero */}
    }
  }

  Future<void> _save() => store.write(_key, jsonEncode({'day': _day, 'used': _used, 'reported': _reported, 'bonus': _bonus}));

  /// Add time used. Rolls over to a new tally when the date has changed.
  Future<void> add(int seconds, DateTime now) async {
    if (dayOf(now) != _day) {
      _day = dayOf(now);
      _used = _reported = _bonus = 0;
    }
    _used += seconds;
    await _save();
  }

  /// Take the seconds not yet sent to the server (at most an hour: the server's limit for one report). Call [markReported] when sent.
  int nextReport() => unreportedSeconds.clamp(0, 3600);

  Future<void> markReported(int seconds) async {
    _reported += seconds;
    await _save();
  }

  /// The server's view of today (from the usage answer or the screen time screen): its used time wins when larger, and it carries extra minutes.
  Future<void> applyServer({required int usedSeconds, required int bonusSeconds}) async {
    if (usedSeconds > _used) {
      _used = usedSeconds;
      if (_reported < usedSeconds) _reported = usedSeconds;
    }
    _bonus = bonusSeconds;
    await _save();
  }

  ScreenTimeStatus status(ScreenTimeRules? rules, DateTime now) {
    if (rules == null) return const ScreenTimeStatus(ScreenLock.none, 1 << 30);
    final left = (rules.dailyMinutes * 60 + _bonus - _used).clamp(0, 1 << 30);
    if (rules.isQuiet(now)) return ScreenTimeStatus(ScreenLock.quiet, left);
    return ScreenTimeStatus(left == 0 ? ScreenLock.limit : ScreenLock.none, left);
  }
}
