import 'dart:async';
import 'dart:convert';

import '../api/kindo_api.dart';
import '../models/models.dart';
import '../store/local_store.dart';

/// The things the server keeps in step with the phone (see docs/SYNC.md).
const syncEntities = ['child', 'lesson', 'family_member', 'aac_card', 'need', 'assignment'];

class SyncResult {
  SyncResult({required this.pages, required this.changes, required this.reset});

  /// How many requests it took.
  final int pages;

  /// How many changes were applied.
  final int changes;

  /// True when the phone had to start over from a fresh snapshot.
  final bool reset;
}

/// Keeps a copy of the family's data on the phone, brought up to date with `GET /sync`.
/// Changes are applied in order and the cursor is saved after each page, so an interrupted sync simply carries on next time.
class SyncEngine {
  SyncEngine(this.api, this.store, {this.storeKey = 'kindo.sync'});

  final KindoApi api;
  final LocalStore store;
  final String storeKey;

  final Map<String, Map<int, Json>> _data = {for (final e in syncEntities) e: {}};
  int? _cursor;
  final _changes = StreamController<void>.broadcast();
  bool _running = false;

  /// The newest change this phone has seen.
  int? get cursor => _cursor;

  /// Fires after every sync that changed something.
  Stream<void> get onChange => _changes.stream;

  /// Read what was saved last time. Call once at start.
  Future<void> load() async {
    final raw = await store.read(storeKey);
    if (raw == null) return;
    try {
      final j = jsonDecode(raw) as Map<String, dynamic>;
      _cursor = (j['cursor'] as num?)?.toInt();
      final d = asJson(j['data']);
      for (final e in syncEntities) {
        final m = asJson(d[e]);
        _data[e] = {for (final entry in m.entries) int.parse(entry.key): asJson(entry.value)};
      }
    } on FormatException {
      await clear();   // a damaged copy is not worth keeping; the next sync rebuilds it
    }
  }

  Future<void> _save() => store.write(storeKey, jsonEncode({'cursor': _cursor, 'data': {for (final e in syncEntities) e: {for (final x in _data[e]!.entries) '${x.key}': x.value}}}));

  /// Forget everything (sign-out, or the family changed).
  Future<void> clear() async {
    for (final e in syncEntities) {
      _data[e] = {};
    }
    _cursor = null;
    await store.remove(storeKey);
  }

  /// Catch up with the server. Safe to call often; a second call while one is running waits for nothing and returns an empty result.
  Future<SyncResult> sync() async {
    if (_running) return SyncResult(pages: 0, changes: 0, reset: false);
    _running = true;
    var pages = 0, applied = 0;
    var didReset = false;
    try {
      while (true) {
        final r = await api.sync(cursor: _cursor);
        pages++;
        if (r['snapshot'] == true || r['reset'] == true) {
          for (final e in syncEntities) {
            _data[e] = {};
          }
          didReset = true;
        }
        for (final c in asJsonList(r['changes'])) {
          _apply(c);
          applied++;
        }
        _cursor = (r['cursor'] as num?)?.toInt() ?? _cursor;
        await _save();
        if (r['has_more'] != true) break;
      }
    } finally {
      _running = false;
    }
    if (applied > 0 || didReset) _changes.add(null);

    return SyncResult(pages: pages, changes: applied, reset: didReset);
  }

  void _apply(Json c) {
    final entity = '${c['entity']}';
    final bucket = _data[entity];
    if (bucket == null) return;   // a kind of thing this version does not know: ignore it
    final id = (c['id'] as num).toInt();
    if (c['op'] == 'delete') {
      bucket.remove(id);
    } else {
      bucket[id] = asJson(c['data']);
    }
  }

  // ---------- reading what is saved ----------

  List<Child> get children => (_data['child']!.values.map(Child.fromJson).toList()..sort((a, b) => a.id.compareTo(b.id)));
  Child? childById(int id) => _data['child']![id] == null ? null : Child.fromJson(_data['child']![id]);

  /// Lessons for a child, newest week last. Archived ones are left out.
  List<Lesson> lessonsFor(int childId, {bool publishedOnly = false}) {
    final l = _data['lesson']!.values.map(Lesson.fromJson).where((x) => x.childId == childId && !x.isArchived && (!publishedOnly || x.isPublished)).toList();
    l.sort((a, b) => a.weekNumber != b.weekNumber ? a.weekNumber.compareTo(b.weekNumber) : a.id.compareTo(b.id));

    return l;
  }

  Lesson? lessonById(int id) => _data['lesson']![id] == null ? null : Lesson.fromJson(_data['lesson']![id]);
  List<FamilyMember> familyFor(int childId) => _data['family_member']!.values.map(FamilyMember.fromJson).where((m) => m.childId == childId).toList()..sort((a, b) => a.id.compareTo(b.id));

  /// A parent's own cards for this child. Stock cards come from [KindoApi.aacCards].
  List<AacCard> aacCardsFor(int childId) => _data['aac_card']!.values.map(AacCard.fromJson).where((c) => c.childId == childId).toList()..sort((a, b) => a.id.compareTo(b.id));

  /// Newest first.
  List<Need> needsFor(int childId) => _data['need']!.values.map(Need.fromJson).where((n) => n.childId == childId).toList()..sort((a, b) => b.id.compareTo(a.id));
  List<Json> assignmentsFor(int childId) => _data['assignment']!.values.where((a) => (a['child_id'] as num?)?.toInt() == childId).toList()..sort((a, b) => ((b['id'] as num?) ?? 0).compareTo((a['id'] as num?) ?? 0));

  void dispose() => _changes.close();
}
