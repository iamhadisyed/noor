import '../models/models.dart';
import 'tracing_engine.dart';

/// Collects how each letter went during one tracing session and builds the body for `POST /children/{id}/sessions`,
/// including the per-letter results (`letters[]`) behind the parent's letter-by-letter report.
class PracticeLog {
  PracticeLog({required this.language, DateTime Function()? now})
      : _now = now ?? DateTime.now,
        _startedAt = (now ?? DateTime.now)();

  /// `urdu` or `english`.
  final String language;
  final DateTime Function() _now;
  final DateTime _startedAt;

  final Map<String, _Letter> _letters = {};
  final List<Json> _mistakes = [];
  int _total = 0, _ok = 0;

  /// Record one try. [assisted] is true when the guide (dotted outline or arrows) was showing.
  void addTry(String letter, TraceResult result, {required bool assisted}) {
    final l = _letters.putIfAbsent(letter, _Letter.new);
    l.attempts++;
    _total++;
    if (assisted) l.assisted++;
    if (result.success) {
      l.successes++;
      _ok++;
    } else if (result.mistake != null && _mistakes.length < 200) {
      _mistakes.add({'target_string': letter, 'attempted_string': '?', 'stroke_deviation_score': double.parse(result.meanDeviationPx.clamp(0, 999).toStringAsFixed(2)), 'mistake_type': result.mistake});
    }
    l.deviation.add(result.meanDeviationPx);
  }

  bool get isEmpty => _total == 0;
  int get tries => _total;
  int get successes => _ok;

  /// Percent right so far, or null before the first try.
  int? get accuracyPct => _total == 0 ? null : (_ok * 100 / _total).round();

  /// The session as the server expects it. Nothing is sent for an empty session.
  Json build() => {
        'language': language,
        'total_attempts': _total,
        'successful_attempts': _ok,
        'duration_seconds': _now().difference(_startedAt).inSeconds.clamp(0, 43200),
        if (_mistakes.isNotEmpty) 'mistakes': _mistakes,
        'letters': [
          for (final e in _letters.entries)
            {
              'letter': e.key,
              'attempts': e.value.attempts.clamp(1, 200),
              'successes': e.value.successes.clamp(0, e.value.attempts),
              'assisted': e.value.assisted.clamp(0, e.value.attempts),
              if (e.value.deviation.isNotEmpty) 'deviation': double.parse((e.value.deviation.reduce((a, b) => a + b) / e.value.deviation.length).clamp(0, 999).toStringAsFixed(2)),
            }
        ],
      };
}

class _Letter {
  int attempts = 0, successes = 0, assisted = 0;
  final List<double> deviation = [];
}
