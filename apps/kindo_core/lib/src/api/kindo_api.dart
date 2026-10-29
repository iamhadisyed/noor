import 'dart:typed_data';

import '../models/models.dart';
import 'api_client.dart';

/// Every call the two apps make, named for what it does. One method per endpoint in `README.md`.
/// Writes that a child's phone may need to send again after losing the connection take an [idempotencyKey].
class KindoApi {
  KindoApi(this.client);
  final ApiClient client;

  // ---------- sign-in and account ----------

  /// Email a 6-digit code. The answer is always the same, so nobody can tell whether an address has an account.
  Future<void> requestCode(String email) => client.post('/auth/code', body: {'email': email});

  /// Exchange the code for a token. When the server answers 422 with [ApiException.termsRequired], show the terms and call again with `acceptTerms: true`.
  Future<({String token, UserProfile user, bool isNew})> verify(String email, String code, {bool acceptTerms = false}) async {
    final r = asJson(await client.post('/auth/verify', body: {'email': email, 'code': code, if (acceptTerms) 'accept_terms': true}));
    return (token: '${r['token']}', user: UserProfile.fromJson(r['user']), isNew: r['is_new'] == true);
  }

  /// Sign in with an email and a 6-digit PIN (accounts given a PIN by the owner, such as the demo accounts). Wrong tries are limited by the server.
  Future<({String token, UserProfile user})> signInWithPin(String email, String pin, {String? deviceName}) async {
    final r = asJson(await client.post('/auth/pin', body: {'email': email, 'pin': pin, if (deviceName != null) 'device_name': deviceName}));
    return (token: '${r['token']}', user: UserProfile.fromJson(r['user']));
  }

  Future<void> logout() => client.post('/auth/logout');
  Future<UserProfile> me() async => UserProfile.fromJson(await client.get('/me'));
  Future<UserProfile> updateMe({required String name, String? phone}) async => UserProfile.fromJson(await client.patch('/me', body: {'name': name, 'phone': phone}));
  Future<Json> terms() async => asJson(await client.get('/legal/terms'));
  Future<void> acceptTerms() => client.post('/me/terms');
  Future<void> registerDevice(String fcmToken, String deviceType) => client.post('/devices', body: {'token': fcmToken, 'device_type': deviceType});
  Future<List<Json>> mySessions() async => asJsonList(await client.get('/me/sessions'));
  Future<void> revokeSession(int id) => client.delete('/me/sessions/$id');
  Future<Json> notificationPrefs() async => asJson(await client.get('/me/notifications'));
  Future<Json> saveNotificationPrefs(Json prefs) async => asJson(await client.patch('/me/notifications', body: prefs));
  Future<Uint8List> exportMyData() => client.getBytes('/me/export');
  Future<void> requestDeleteCode() => client.post('/me/delete-code');
  Future<void> deleteAccount(String code) => client.delete('/me', body: {'code': code});

  // ---------- reference data ----------

  Future<Json> meta() async => asJson(await client.get('/meta'));
  Future<List<Json>> taskTypes() async => asJsonList(await client.get('/task-types'));

  // ---------- children ----------

  Future<List<Child>> children() async => asJsonList(await client.get('/children')).map(Child.fromJson).toList();
  Future<Child> child(int id) async => Child.fromJson(await client.get('/children/$id'));
  Future<Child> createChild({required String name, required String dateOfBirth, String? neurotype, String? track, Json? supportProfile}) async =>
      Child.fromJson(await client.post('/children', body: {'name': name, 'date_of_birth': dateOfBirth, if (neurotype != null) 'neurotype': neurotype, if (track != null) 'track': track, if (supportProfile != null) 'support_profile': supportProfile}));
  Future<Child> updateChild(int id, Json changes) async => Child.fromJson(await client.patch('/children/$id', body: changes));
  Future<void> deleteChild(int id) => client.delete('/children/$id');

  // ---------- lessons and tasks ----------

  Future<List<Lesson>> lessons(int childId) async => asJsonList(await client.get('/children/$childId/lessons')).map(Lesson.fromJson).toList();
  Future<Lesson> lesson(int id) async => Lesson.fromJson(await client.get('/lessons/$id'));
  Future<Lesson> createLesson(int childId, {required String title, String language = 'both', int weekNumber = 1, String? description, String? idempotencyKey}) async =>
      Lesson.fromJson(await client.post('/children/$childId/lessons', body: {'title': title, 'language': language, 'week_number': weekNumber, if (description != null) 'description': description}, idempotencyKey: idempotencyKey));
  Future<Lesson> updateLesson(int id, Json changes) async => Lesson.fromJson(await client.patch('/lessons/$id', body: changes));
  Future<void> archiveLesson(int id) => client.delete('/lessons/$id');
  Future<Lesson> duplicateLesson(int id, int toChildId) async => Lesson.fromJson(await client.post('/lessons/$id/duplicate', body: {'child_id': toChildId}));

  /// Publish. A 422 lists exactly what is missing in [ApiException.body] under `problems`.
  Future<Lesson> publishLesson(int id) async => Lesson.fromJson(await client.post('/lessons/$id/publish'));
  Future<Lesson> unpublishLesson(int id) async => Lesson.fromJson(await client.post('/lessons/$id/unpublish'));
  Future<Json> readiness(int id) async => asJson(await client.get('/lessons/$id/readiness'));
  Future<List<Lesson>> lessonTemplates() async => asJsonList(await client.get('/lesson-templates')).map(Lesson.fromJson).toList();
  Future<Lesson> lessonFromTemplate(int childId, int templateId) async => Lesson.fromJson(await client.post('/children/$childId/lessons/from-template/$templateId'));

  /// Pro: a photo of a syllabus page becomes a draft lesson.
  Future<Lesson> importLessonFromPhoto(int childId, UploadFile photo, {String language = 'both'}) async =>
      Lesson.fromJson(await client.postMultipart('/children/$childId/lessons/import', fields: {'language': language}, files: {'photo': photo}));

  Future<LessonTask> addTask(int lessonId, Json task) async => LessonTask.fromJson(await client.post('/lessons/$lessonId/tasks', body: task));
  Future<LessonTask> updateTask(int taskId, Json changes) async => LessonTask.fromJson(await client.patch('/tasks/$taskId', body: changes));
  Future<void> deleteTask(int taskId) => client.delete('/tasks/$taskId');
  Future<void> reorderTasks(int lessonId, List<int> ids) => client.post('/lessons/$lessonId/tasks/reorder', body: {'ids': ids});

  /// [kind] is `image` or `audio`.
  Future<LessonTask> uploadTaskMedia(int taskId, String kind, UploadFile file) async =>
      LessonTask.fromJson(await client.postMultipart('/tasks/$taskId/media', fields: {'kind': kind}, files: {'file': file}));
  Future<void> removeTaskMedia(int taskId, String kind) => client.delete('/tasks/$taskId/media/$kind');

  // ---------- picture cards, family cards ----------

  Future<List<AacCard>> aacCards() async => asJsonList(await client.get('/aac-cards')).map(AacCard.fromJson).toList();
  Future<AacCard> createAacCard(int childId, {required String englishLabel, String? urduLabel, required String category, required UploadFile icon, UploadFile? audio}) async =>
      AacCard.fromJson(await client.postMultipart('/children/$childId/aac-cards', fields: {'english_label': englishLabel, if (urduLabel != null) 'urdu_label': urduLabel, 'category': category}, files: {'icon': icon, if (audio != null) 'audio': audio}));
  Future<AacCard> updateAacCard(int id, {String? englishLabel, String? urduLabel, String? category, UploadFile? icon, UploadFile? audio}) async =>
      AacCard.fromJson(await client.postMultipart('/aac-cards/$id', fields: {if (englishLabel != null) 'english_label': englishLabel, if (urduLabel != null) 'urdu_label': urduLabel, if (category != null) 'category': category}, files: {if (icon != null) 'icon': icon, if (audio != null) 'audio': audio}));
  Future<void> deleteAacCard(int id) => client.delete('/aac-cards/$id');
  Future<Uint8List> pecsPdf({String size = 'a4'}) => client.getBytes('/pecs.pdf', query: {'size': size});

  Future<List<FamilyMember>> familyMembers(int childId) async => asJsonList(await client.get('/children/$childId/family')).map(FamilyMember.fromJson).toList();
  Future<FamilyMember> addFamilyMember(int childId, {required String relationTitle, required String displayName, required UploadFile photo, required UploadFile audio}) async =>
      FamilyMember.fromJson(await client.postMultipart('/children/$childId/family', fields: {'relation_title': relationTitle, 'display_name': displayName, 'consent': '1'}, files: {'photo': photo, 'audio': audio}));
  Future<FamilyMember> updateFamilyMember(int id, {String? relationTitle, String? displayName, UploadFile? photo, UploadFile? audio}) async =>
      FamilyMember.fromJson(await client.postMultipart('/family/$id', fields: {if (relationTitle != null) 'relation_title': relationTitle, if (displayName != null) 'display_name': displayName}, files: {if (photo != null) 'photo': photo, if (audio != null) 'audio': audio}));
  Future<void> deleteFamilyMember(int id) => client.delete('/family/$id');

  // ---------- alerts ----------

  /// The child's one-tap alert. Send with an [idempotencyKey] so a retry never makes a second alert.
  Future<int> sendAlert(int childId, String needType, {int? cardId, String? idempotencyKey}) async {
    final r = asJson(await client.post('/child/alert', body: {'child_id': childId, 'need_type': needType, if (cardId != null) 'card_id': cardId}, idempotencyKey: idempotencyKey));
    return (r['id'] as num).toInt();
  }

  Future<List<Need>> needs(int childId) async => asJsonList(await client.get('/children/$childId/needs')).map(Need.fromJson).toList();
  Future<Need> need(int id) async => Need.fromJson(await client.get('/needs/$id'));
  Future<Need> markSeen(int id) async => Need.fromJson(await client.post('/needs/$id/seen'));
  Future<Need> acknowledge(int id) async => Need.fromJson(await client.post('/needs/$id/acknowledge'));

  // ---------- practice, rewards, games, activities ----------

  /// Upload a finished practice session. `letters` (optional) feeds the letter-by-letter report.
  Future<Json> uploadSession(int childId, Json session, {String? idempotencyKey}) async => asJson(await client.post('/children/$childId/sessions', body: session, idempotencyKey: idempotencyKey));

  Future<Json> rewards(int childId) async => asJson(await client.get('/children/$childId/rewards'));
  Future<List<GameInfo>> games(int childId) async => asJsonList(asJson(await client.get('/children/$childId/rewards'))['games']).map(GameInfo.fromJson).toList();
  Future<Json> saveRewardSettings(int childId, Json settings) async => asJson(await client.patch('/children/$childId/rewards/settings', body: settings));
  Future<Json> addBonusMinutes(int childId, int minutes, {String? note}) async => asJson(await client.post('/children/$childId/rewards/bonus', body: {'minutes': minutes, if (note != null) 'note': note}));
  Future<Json> gameLibrary() async => asJson(await client.get('/games/library'));

  /// Start a game. Throws a 423 [ApiException] (see [ApiException.lockedReason]) when it is locked.
  Future<GameStart> startGame(int childId, String game, {int? rounds, String language = 'en'}) async =>
      GameStart.fromJson(await client.post('/children/$childId/games/$game/start', body: {if (rounds != null) 'rounds': rounds, 'language': language}));
  Future<Json> finishGame(int sessionId, {required int roundsPlayed, required int correct, required int secondsPlayed, String? idempotencyKey}) async =>
      asJson(await client.post('/game-sessions/$sessionId/finish', body: {'rounds_played': roundsPlayed, 'correct': correct, 'seconds_played': secondsPlayed}, idempotencyKey: idempotencyKey));

  Future<List<CalmActivity>> activities() async => asJsonList(asJson(await client.get('/activities'))['activities']).map(CalmActivity.fromJson).toList();
  Future<void> calmDone(int childId, String activity, {int seconds = 0, String? idempotencyKey}) => client.post('/children/$childId/activities/$activity/done', body: {'seconds': seconds}, idempotencyKey: idempotencyKey);

  // ---------- reports ----------

  Future<Json> insightsSummary(int childId, {int days = 7, String? tz}) async => asJson(await client.get('/children/$childId/insights/summary', query: {'days': days, 'tz': tz}));
  Future<List<Json>> insightsDaily(int childId, {int days = 7, String? tz}) async => asJsonList(await client.get('/children/$childId/insights/daily', query: {'days': days, 'tz': tz}));
  Future<List<LetterStat>> letters(int childId, {int days = 30}) async => asJsonList(asJson(await client.get('/children/$childId/letters', query: {'days': days}))['letters']).map(LetterStat.fromJson).toList();
  Future<Uint8List> reportPdf(int childId, {int days = 14}) => client.getBytes('/children/$childId/insights/report.pdf', query: {'days': days});
  Future<Uint8List> reportCsv(int childId, {int days = 30}) => client.getBytes('/children/$childId/insights/export.csv', query: {'days': days});
  Future<List<Milestone>> milestones(int childId) async => asJsonList(asJson(await client.get('/children/$childId/milestones'))['milestones']).map(Milestone.fromJson).toList();

  /// The shareable picture for a milestone (1080 x 1080 PNG).
  Future<Uint8List> milestoneCard(int childId, String key, {String lang = 'en', bool showName = true}) => client.getBytes('/children/$childId/milestones/$key/card.png', query: {'lang': lang, 'name': showName ? 1 : 0});

  /// Pro: ask for an AI report (queued), then read the latest one.
  Future<void> requestAiReport(int childId) => client.post('/children/$childId/reports');
  Future<Json?> latestAiReport(int childId) async {
    try {
      return asJson(await client.get('/children/$childId/reports/latest'));
    } on ApiException catch (e) {
      if (e.status == 404) return null;
      rethrow;
    }
  }

  // ---------- family sharing, specialists ----------

  Future<Json> household() async => asJson(await client.get('/household'));
  Future<void> inviteToHousehold(String email) => client.post('/household/invite', body: {'email': email});
  Future<void> acceptInvitation(int id) => client.post('/household/invitations/$id/accept');
  Future<void> declineInvitation(int id) => client.post('/household/invitations/$id/decline');
  Future<void> removeHouseholdMember(int id) => client.delete('/household/members/$id');
  Future<void> leaveHousehold(int id) => client.delete('/household/joined/$id');

  Future<List<Json>> specialists(int childId) async => asJsonList(await client.get('/children/$childId/specialists'));

  /// The response has a one-time `code` to give to the therapist or teacher.
  Future<Json> inviteSpecialist(int childId, {required String role, required String label}) async => asJson(await client.post('/children/$childId/specialists', body: {'role': role, 'label': label}));
  Future<Json> newSpecialistCode(int id) async => asJson(await client.post('/specialists/$id/new-code'));
  Future<void> revokeSpecialist(int id) => client.delete('/specialists/$id');
  Future<List<Json>> assignments(int childId) async => asJsonList(await client.get('/children/$childId/assignments'));
  Future<void> completeAssignment(int id) => client.post('/assignments/$id/done');

  // the therapist's or teacher's side
  Future<Json> redeemSpecialistCode(String code) async => asJson(await client.post('/specialist/redeem', body: {'code': code}));
  Future<List<Json>> sharedWithMe() async => asJsonList(await client.get('/specialist/children'));
  Future<Json> sharedSummary(int childId, {int days = 14}) async => asJson(await client.get('/specialist/children/$childId/summary', query: {'days': days}));
  Future<List<Json>> sharedAssignments(int childId) async => asJsonList(await client.get('/specialist/children/$childId/assignments'));
  Future<Json> assignExercise(int childId, {required String title, String? note}) async => asJson(await client.post('/specialist/children/$childId/assignments', body: {'title': title, if (note != null) 'note': note}));
  Future<void> stopSharing(int childId) => client.delete('/specialist/children/$childId');

  // ---------- plan, announcements ----------

  Future<Json> billing() async => asJson(await client.get('/billing'));
  Future<Json> redeemVoucher(String code) async => asJson(await client.post('/billing/redeem', body: {'code': code}));
  Future<List<Json>> announcements() async => asJsonList(await client.get('/announcements'));
  Future<void> dismissAnnouncement(int id) => client.post('/announcements/$id/dismiss');

  // ---------- keeping in step ----------

  /// The change feed. See `docs/SYNC.md`. Most callers use `SyncEngine` instead.
  // ---- parent guides (Pro) ----
  /// A parent guide such as `autism`. A 402 [ApiException] (see [ApiException.needsUpgrade]) carries a `preview` of the section titles in its body.
  Future<Json> guidance(String guide) async => asJson(await client.get('/guidance/$guide'));

  // ---- Discover (free): topics and facts by age ----
  Future<List<Json>> learnTopics() async => asJsonList(asJson(await client.get('/learn'))['topics']);
  Future<Json> learnTopic(String topic, {int? age}) async => asJson(await client.get('/learn/$topic', query: {'age': age}));

  // ---- What a child did, and screen time (the week and the rules are Pro; a free plan sees today only) ----
  Future<Json> activity(int childId, {int days = 7, String? tz}) async => asJson(await client.get('/children/$childId/activity', query: {'days': days, 'tz': tz}));
  /// Throws a 402 [ApiException] with [ApiException.needsUpgrade] on a free plan.
  Future<Json> screenTime(int childId) async => asJson(await client.get('/children/$childId/screen-time'));
  /// Any of `enabled`, `daily_minutes` (10 to 600), `quiet_start` and `quiet_end` ("HH:MM", both or neither).
  Future<Json> saveScreenTime(int childId, Json rules) async => asJson(await client.patch('/children/$childId/screen-time', body: rules));
  /// The child's app reports time used (at most an hour per report).
  Future<Json> reportScreenTime(int childId, int seconds, {String? date}) async => asJson(await client.post('/children/$childId/screen-time/usage', body: {'seconds': seconds, if (date != null) 'date': date}));
  Future<Json> extendScreenTime(int childId, int minutes) async => asJson(await client.post('/children/$childId/screen-time/extend', body: {'minutes': minutes}));

  // ---- Asking for Kindo Pro: request, then tell us once you have paid; the owner approves by hand ----
  Future<Json> upgradeStatus() async => asJson(await client.get('/billing/upgrade'));
  Future<Json> requestUpgrade({String? phone, String? note}) async => asJson(await client.post('/billing/upgrade', body: {if (phone != null) 'phone': phone, if (note != null) 'note': note}));
  Future<Json> reportPayment({required String method, required String reference, String? note}) async =>
      asJson(await client.post('/billing/upgrade/paid', body: {'payment_method': method, 'payment_reference': reference, if (note != null) 'payment_note': note}));
  Future<Json> sync({int? cursor}) async => asJson(await client.get('/sync', query: {'cursor': cursor}));
}
