// Plain Dart classes for what the server sends. Each keeps the original JSON in `raw`, so a screen can read a field
// the class does not name, and so nothing is lost when a record is saved on the phone and read back.

typedef Json = Map<String, dynamic>;

Json asJson(Object? v) => v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
List<Json> asJsonList(Object? v) => v is List ? v.map(asJson).toList() : <Json>[];
int _i(Object? v, [int d = 0]) => v is num ? v.toInt() : (int.tryParse('$v') ?? d);
double _d(Object? v, [double d = 0]) => v is num ? v.toDouble() : (double.tryParse('$v') ?? d);
bool _b(Object? v, [bool d = false]) => v is bool ? v : (v is num ? v != 0 : d);
String _s(Object? v, [String d = '']) => v == null ? d : '$v';
String? _sn(Object? v) => v == null ? null : '$v';
DateTime? _t(Object? v) => v == null ? null : DateTime.tryParse('$v');

/// A name in English and Urdu.
class Bilingual {
  const Bilingual(this.en, this.ur);
  factory Bilingual.fromJson(Object? v) {
    final m = asJson(v);
    return Bilingual(_s(m['en']), _s(m['ur']));
  }
  final String en;
  final String ur;

  /// The text for a language code (`en` or `ur`), falling back to the other one if it is empty.
  String of(String lang) => lang == 'ur' ? (ur.isNotEmpty ? ur : en) : (en.isNotEmpty ? en : ur);
}

/// How a child's screens should behave: from the child's support profile.
class Support {
  const Support({
    this.magnetPx = 24,
    this.pace = 'normal',
    this.maxChoices = 4,
    this.noTimers = false,
    this.firstThen = false,
    this.praise = 'lively',
    this.guide = 'dotted',
    this.fadeAssist = true,
  });
  factory Support.fromJson(Object? v) {
    final m = asJson(v);
    return Support(
      magnetPx: _i(m['magnet_px'], 24),
      pace: _s(m['pace'], 'normal'),
      maxChoices: _i(m['max_choices'], 4),
      noTimers: _b(m['no_timers']),
      firstThen: _b(m['first_then']),
      praise: _s(m['praise'], 'lively'),
      guide: _s(m['guide'], 'dotted'),
      fadeAssist: _b(m['fade_assist'], true),
    );
  }
  final int magnetPx;
  final String pace;
  final int maxChoices;
  final bool noTimers;
  final bool firstThen;
  final String praise;
  final String guide;
  final bool fadeAssist;

  bool get slow => pace == 'slow';
  bool get quiet => praise == 'quiet';

  Json toJson() => {
        'magnet_px': magnetPx,
        'pace': pace,
        'max_choices': maxChoices,
        'no_timers': noTimers,
        'first_then': firstThen,
        'praise': praise,
        'guide': guide,
        'fade_assist': fadeAssist,
      };
}

class Child {
  Child({required this.id, required this.name, required this.track, required this.support, this.dateOfBirth, this.neurotype, this.sensoryMode = false, this.balanceMinutes = 0, Json? raw}) : raw = raw ?? {};
  factory Child.fromJson(Object? v) {
    final m = asJson(v);
    return Child(
      id: _i(m['id']),
      name: _s(m['name']),
      track: _s(m['track'], 'standard'),
      support: Support.fromJson(m['support']),
      dateOfBirth: _t(m['date_of_birth']),
      neurotype: _sn(m['neurotype']),
      sensoryMode: _b(m['sensory_mode']),
      balanceMinutes: _i(asJson(m['rewards'])['balance_minutes']),
      raw: m,
    );
  }
  final int id;
  final String name;

  /// `standard` or `special`.
  final String track;
  final Support support;
  final DateTime? dateOfBirth;
  final String? neurotype;
  final bool sensoryMode;
  final int balanceMinutes;
  final Json raw;

  bool get isSpecial => track == 'special';
  int? ageYears([DateTime? now]) {
    final b = dateOfBirth;
    if (b == null) return null;
    final n = now ?? DateTime.now();
    var a = n.year - b.year;
    if (n.month < b.month || (n.month == b.month && n.day < b.day)) a--;
    return a;
  }
}

class LessonTask {
  LessonTask({
    required this.id,
    required this.position,
    required this.type,
    required this.title,
    this.instruction,
    this.target,
    this.keyboard,
    this.choices = const [],
    this.correct = 0,
    this.hasImage = false,
    this.hasSound = false,
    this.requiresImage = false,
    this.requiresSound = false,
    this.imageUrl,
    this.audioUrl,
    this.imageAlt,
    Json? raw,
  }) : raw = raw ?? {};
  factory LessonTask.fromJson(Object? v) {
    final m = asJson(v);
    final o = asJson(m['options']);
    return LessonTask(
      id: _i(m['id']),
      position: _i(m['position']),
      type: _s(m['type']),
      title: _s(m['title']),
      instruction: _sn(m['instruction']),
      target: _sn(m['target']),
      keyboard: _sn(m['keyboard']),
      choices: (o['choices'] is List) ? (o['choices'] as List).map((e) => '$e').toList() : const [],
      correct: _i(o['correct']),
      hasImage: _b(m['has_image']),
      hasSound: _b(m['has_sound']),
      requiresImage: _b(m['requires_image']),
      requiresSound: _b(m['requires_sound']),
      imageUrl: _sn(m['image_url']),
      audioUrl: _sn(m['audio_url']),
      imageAlt: _sn(m['image_alt']),
      raw: m,
    );
  }
  final int id;
  final int position;
  final String type;
  final String title;
  final String? instruction;
  final String? target;

  /// For `spell_word`: `english` or `urdu`.
  final String? keyboard;
  final List<String> choices;
  final int correct;
  final bool hasImage;
  final bool hasSound;
  final bool requiresImage;
  final bool requiresSound;
  final String? imageUrl;
  final String? audioUrl;
  final String? imageAlt;
  final Json raw;
}

class Lesson {
  Lesson({required this.id, required this.childId, required this.title, required this.status, required this.language, required this.tasks, this.description, this.weekNumber = 1, this.ready = false, this.problems = const [], this.settings, Json? raw}) : raw = raw ?? {};
  factory Lesson.fromJson(Object? v) {
    final m = asJson(v);
    return Lesson(
      id: _i(m['id']),
      childId: _i(m['child_id']),
      title: _s(m['title']),
      status: _s(m['status'], 'draft'),
      language: _s(m['language'], 'both'),
      description: _sn(m['description']),
      weekNumber: _i(m['week_number'], 1),
      ready: _b(m['ready']),
      problems: asJsonList(m['problems']),
      settings: m['settings'] is Map ? asJson(m['settings']) : null,
      tasks: asJsonList(m['tasks']).map(LessonTask.fromJson).toList(),
      raw: m,
    );
  }
  final int id;
  final int childId;
  final String title;
  final String status;
  final String language;
  final String? description;
  final int weekNumber;
  final bool ready;

  /// What stops it being published: each has `message`, `missing` and `task_id`.
  final List<Json> problems;
  final Json? settings;
  final List<LessonTask> tasks;
  final Json raw;

  bool get isPublished => status == 'published';
  bool get isArchived => status == 'archived';
}

class AacCard {
  AacCard({required this.id, required this.category, required this.englishLabel, required this.urduLabel, this.childId, this.iconPath, this.iconUrl, this.audioUrl, this.isEmergency = false, this.custom = false, Json? raw}) : raw = raw ?? {};
  factory AacCard.fromJson(Object? v) {
    final m = asJson(v);
    return AacCard(
      id: _i(m['id']),
      childId: m['child_id'] == null ? null : _i(m['child_id']),
      category: _s(m['category']),
      englishLabel: _s(m['english_label']),
      urduLabel: _s(m['urdu_label']),
      iconPath: _sn(m['icon_path']),
      iconUrl: _sn(m['icon_url']),
      audioUrl: _sn(m['audio_url']),
      isEmergency: _b(m['is_emergency']),
      custom: _b(m['custom']),
      raw: m,
    );
  }
  final int id;
  final int? childId;
  final String category;
  final String englishLabel;
  final String urduLabel;

  /// Stock cards: a path under the site's public folder (for example `aac/water.png`).
  final String? iconPath;

  /// Your own cards: a signed link.
  final String? iconUrl;
  final String? audioUrl;
  final bool isEmergency;
  final bool custom;
  final Json raw;

  String label(String lang) => lang == 'ur' && urduLabel.isNotEmpty ? urduLabel : englishLabel;

  /// The word sent as `need_type` when a child taps this card.
  String get needType => englishLabel.toLowerCase();
}

/// One alert from a child.
class Need {
  Need({required this.id, required this.childId, required this.needType, required this.status, this.createdAt, this.seenAt, this.acknowledgedAt, this.repliedBy, this.replySeconds, Json? raw}) : raw = raw ?? {};
  factory Need.fromJson(Object? v) {
    final m = asJson(v);
    return Need(
      id: _i(m['id']),
      childId: _i(m['child_id']),
      needType: _s(m['need_type']),
      status: _s(m['status'], 'sent'),
      createdAt: _t(m['created_at']),
      seenAt: _t(m['seen_at']),
      acknowledgedAt: _t(m['acknowledged_at']),
      repliedBy: _sn(m['replied_by']),
      replySeconds: m['reply_seconds'] == null ? null : _i(m['reply_seconds']),
      raw: m,
    );
  }
  final int id;
  final int childId;
  final String needType;

  /// `sent`, `seen` or `on_the_way`.
  final String status;
  final DateTime? createdAt;
  final DateTime? seenAt;
  final DateTime? acknowledgedAt;
  final String? repliedBy;
  final int? replySeconds;
  final Json raw;

  bool get answered => status == 'on_the_way';
}

class FamilyMember {
  FamilyMember({required this.id, required this.childId, required this.relationTitle, required this.displayName, this.photoUrl, this.audioUrl, Json? raw}) : raw = raw ?? {};
  factory FamilyMember.fromJson(Object? v) {
    final m = asJson(v);
    return FamilyMember(id: _i(m['id']), childId: _i(m['child_id']), relationTitle: _s(m['relation_title']), displayName: _s(m['display_name']), photoUrl: _sn(m['photo_url']), audioUrl: _sn(m['audio_url']), raw: m);
  }
  final int id;
  final int childId;
  final String relationTitle;
  final String displayName;
  final String? photoUrl;
  final String? audioUrl;
  final Json raw;
}

/// A game in the catalogue, with the child's progress in it.
class GameInfo {
  GameInfo({required this.game, required this.category, required this.title, required this.forTracks, this.enabled = true, this.available = true, this.unlocked = false, this.level = 1, this.plays = 0, this.lastAccuracy});
  factory GameInfo.fromJson(Object? v) {
    final m = asJson(v);
    return GameInfo(
      game: _s(m['game']),
      category: _s(m['category']),
      title: Bilingual.fromJson(m['title']),
      forTracks: (m['for'] is List) ? (m['for'] as List).map((e) => '$e').toList() : const ['standard', 'special'],
      enabled: _b(m['enabled'], true),
      available: _b(m['available'], true),
      unlocked: _b(m['unlocked']),
      level: _i(m['level'], 1),
      plays: _i(m['plays']),
      lastAccuracy: m['last_accuracy'] == null ? null : _i(m['last_accuracy']),
    );
  }
  final String game;
  final String category;
  final Bilingual title;
  final List<String> forTracks;
  final bool enabled;
  final bool available;
  final bool unlocked;
  final int level;
  final int plays;
  final int? lastAccuracy;

  /// True when this game was made with the given track in mind.
  bool suits(String track) => forTracks.contains(track);
}

class ActivityStep {
  const ActivityStep({required this.cue, required this.seconds, required this.text});
  factory ActivityStep.fromJson(Object? v) {
    final m = asJson(v);
    return ActivityStep(cue: _s(m['cue'], 'do'), seconds: _i(m['seconds'], 5), text: Bilingual.fromJson(m['text']));
  }

  /// `in`, `hold`, `out`, `rest` or `do`.
  final String cue;
  final int seconds;
  final Bilingual text;
}

/// A calm or movement activity.
class CalmActivity {
  CalmActivity({required this.activity, required this.kind, required this.title, required this.about, required this.cycles, required this.steps});
  factory CalmActivity.fromJson(Object? v) {
    final m = asJson(v);
    return CalmActivity(
      activity: _s(m['activity']),
      kind: _s(m['kind']),
      title: Bilingual.fromJson(m['title']),
      about: Bilingual.fromJson(m['about']),
      cycles: _i(m['cycles'], 1),
      steps: asJsonList(m['steps']).map(ActivityStep.fromJson).toList(),
    );
  }
  final String activity;
  final String kind;
  final Bilingual title;
  final Bilingual about;
  final int cycles;
  final List<ActivityStep> steps;

  /// How long one full run takes, in seconds, if followed by the clock.
  int get totalSeconds => cycles * steps.fold<int>(0, (a, s) => a + s.seconds);
}

class Milestone {
  Milestone({required this.key, required this.kind, required this.value, required this.title, this.earnedOn});
  factory Milestone.fromJson(Object? v) {
    final m = asJson(v);
    return Milestone(key: _s(m['key']), kind: _s(m['kind']), value: _i(m['value']), title: Bilingual.fromJson(m['title']), earnedOn: _sn(m['earned_on']));
  }
  final String key;
  final String kind;
  final int value;
  final Bilingual title;
  final String? earnedOn;
}

/// How one letter is going.
class LetterStat {
  LetterStat({required this.letter, required this.language, required this.attempts, required this.status, this.accuracyPct, this.assistedPct = 0, this.avgDeviation, this.trend});
  factory LetterStat.fromJson(Object? v) {
    final m = asJson(v);
    return LetterStat(
      letter: _s(m['letter']),
      language: _s(m['language']),
      attempts: _i(m['attempts']),
      status: _s(m['status']),
      accuracyPct: m['accuracy_pct'] == null ? null : _i(m['accuracy_pct']),
      assistedPct: _i(m['assisted_pct']),
      avgDeviation: m['avg_deviation'] == null ? null : _d(m['avg_deviation']),
      trend: _sn(m['trend']),
    );
  }
  final String letter;
  final String language;
  final int attempts;

  /// `new`, `needs_help`, `practising` or `mastered`.
  final String status;
  final int? accuracyPct;
  final int assistedPct;
  final double? avgDeviation;
  final String? trend;
}

class UserProfile {
  UserProfile({required this.id, required this.email, required this.name, required this.role, required this.plan, this.termsRequired = false, this.sharedChildren = 0, this.termsVersion, Json? raw}) : raw = raw ?? {};
  factory UserProfile.fromJson(Object? v) {
    final m = asJson(v);
    return UserProfile(
      id: _i(m['id']),
      email: _s(m['email']),
      name: _s(m['name']),
      role: _s(m['role'], 'parent'),
      plan: _s(m['plan'], 'free'),
      termsRequired: _b(m['terms_required']),
      sharedChildren: _i(m['shared_children']),
      termsVersion: _sn(m['terms_version']),
      raw: m,
    );
  }
  final int id;
  final String email;
  final String name;
  final String role;
  final String plan;
  final bool termsRequired;
  final int sharedChildren;
  final String? termsVersion;
  final Json raw;

  bool get isPro => plan == 'pro';
  bool get isAdmin => role == 'super_admin';
}

/// What the server sends when starting a game: the whole session at once.
class GameStart {
  GameStart({required this.sessionId, required this.game, required this.level, required this.allowedSeconds, required this.balanceMinutes, required this.rounds});
  factory GameStart.fromJson(Object? v) {
    final m = asJson(v);
    return GameStart(sessionId: _i(m['session_id']), game: _s(m['game']), level: _i(m['level'], 1), allowedSeconds: _i(m['allowed_seconds']), balanceMinutes: _i(m['balance_minutes']), rounds: asJsonList(m['rounds']));
  }
  final int sessionId;
  final String game;
  final int level;
  final int allowedSeconds;
  final int balanceMinutes;
  final List<Json> rounds;
}
