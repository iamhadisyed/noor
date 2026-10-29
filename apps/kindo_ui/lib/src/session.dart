import 'dart:async';
import 'dart:convert' as conv;

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:kindo_core/kindo_core.dart';

import 'media_cache.dart';
import 'push.dart';

/// Everything both apps share about "who is signed in and what do we know": the token, the server address, the saved copy of the
/// family's data, the queue of writes made offline, and which child is on screen. Screens read it with `context.watch<KindoSession>()`.
class KindoSession extends ChangeNotifier {
  KindoSession({required this.store, required this.defaultBaseUrl, http.Client? httpClient, PushService? push}) : push = push ?? const NoPush(), _http = httpClient {
    _build(defaultBaseUrl);
  }

  final LocalStore store;
  final String defaultBaseUrl;
  final PushService push;
  final http.Client? _http;

  late ApiClient client;
  late KindoApi api;
  late SyncEngine sync;
  late Outbox outbox;
  late MediaCache media;

  String baseUrl = '';
  String? token;
  UserProfile? user;
  Json meta = {};
  Map<String, KeyboardLayout> keyboards = {};

  /// The ready-made picture cards (they are not part of the change feed, so they are fetched and kept separately).
  List<AacCard> stockCards = [];
  String lang = 'en';
  int? currentChildId;

  /// False after a request fails for lack of a connection; true again after one succeeds.
  bool online = true;
  bool ready = false;
  Timer? _timer;
  StreamSubscription<PushMessage>? _pushSub;
  StreamSubscription<void>? _syncSub;

  static const _kToken = 'kindo.token';
  static const _kBase = 'kindo.base_url';
  static const _kLang = 'kindo.lang';
  static const _kChild = 'kindo.child';
  static const _kMeta = 'kindo.meta';
  static const _kStock = 'kindo.stock_cards';

  void _build(String base) {
    baseUrl = base;
    client = ApiClient(baseUrl: base, client: _http, token: () => token, onUnauthorized: _tokenRejected);
    api = KindoApi(client);
    sync = SyncEngine(api, store);
    outbox = Outbox(client, store);
    media = MediaCache(client);
    _syncSub?.cancel();
    _syncSub = sync.onChange.listen((_) => notifyListeners());
  }

  /// The site address without `/api/v1`, where public files such as the stock picture cards live.
  String get origin => baseUrl.replaceFirst(RegExp(r'/api/v1/?$'), '');

  String publicUrl(String path) => '$origin/${path.startsWith('/') ? path.substring(1) : path}';

  bool get signedIn => token != null && token!.isNotEmpty;
  List<Child> get children => sync.children;
  Child? get currentChild {
    final all = children;
    if (all.isEmpty) return null;
    return all.firstWhere((c) => c.id == currentChildId, orElse: () => all.first);
  }

  /// Read what was saved last time and, if signed in, refresh in the background. Call once at start.
  Future<void> init() async {
    final base = await store.read(_kBase);
    if (base != null && base.isNotEmpty && base != baseUrl) _build(base);
    token = await store.read(_kToken);
    lang = await store.read(_kLang) ?? 'en';
    currentChildId = int.tryParse(await store.read(_kChild) ?? '');
    final m = await store.read(_kMeta);
    if (m != null) _setMeta(m);
    final st = await store.read(_kStock);
    if (st != null) {
      try {
        stockCards = (_decode(st) as List).map(AacCard.fromJson).toList();
      } catch (_) {
        stockCards = [];
      }
    }
    await sync.load();
    await outbox.load();
    ready = true;
    notifyListeners();
    if (signedIn) unawaited(refresh());
  }

  void _setMeta(String json) {
    try {
      meta = asJson(_decode(json));
      keyboards = KeyboardLayout.fromMeta(meta);
    } catch (_) {
      meta = {};
    }
  }

  dynamic _decode(String s) => const JsonCodecShim().decode(s);

  void _tokenRejected() {
    if (signedIn) unawaited(signOut(callServer: false));
  }

  // ---------- signing in and out ----------

  Future<void> requestCode(String email) => api.requestCode(email.trim());

  /// Throws an [ApiException]; when its `termsRequired` is true, show the terms and call again with `acceptTerms: true`.
  Future<void> verify(String email, String code, {bool acceptTerms = false}) async {
    final r = await api.verify(email.trim(), code.trim(), acceptTerms: acceptTerms);
    token = r.token;
    user = r.user;
    await store.write(_kToken, r.token);
    notifyListeners();
    unawaited(refresh());
  }

  /// Sign in with a 6-digit PIN (accounts given one by the owner, such as the demo accounts).
  Future<void> signInWithPin(String email, String pin) async {
    final r = await api.signInWithPin(email.trim(), pin.trim());
    token = r.token;
    user = r.user;
    await store.write(_kToken, r.token);
    notifyListeners();
    unawaited(refresh());
  }

  Future<void> signOut({bool callServer = true}) async {
    if (callServer && signedIn) {
      try {
        await api.logout();
      } on ApiException {
        // the token goes either way
      }
    }
    token = null;
    user = null;
    currentChildId = null;
    await store.remove(_kToken);
    await store.remove(_kChild);
    await sync.clear();
    await store.remove('kindo.outbox');
    await store.remove(_kStock);
    stockCards = [];
    await media.clear();
    outbox = Outbox(client, store);
    stopAutoSync();
    notifyListeners();
  }

  // ---------- keeping up to date ----------

  /// Profile, reference data, the writes waiting to go, then the change feed. Never throws: a failure only flips [online].
  Future<void> refresh() async {
    if (!signedIn) return;
    try {
      user = await api.me();
      meta = await api.meta();
      keyboards = KeyboardLayout.fromMeta(meta);
      await store.write(_kMeta, const JsonCodecShim().encode(meta));
      await flushOutbox();
      await sync.sync();
      final cards = await api.aacCards();
      stockCards = cards.where((c) => !c.custom).toList();
      await store.write(_kStock, const JsonCodecShim().encode([for (final c in stockCards) c.raw]));
      online = true;
      unawaited(_prefetch());
    } on ApiException catch (e) {
      online = !e.isNetwork;
    }
    notifyListeners();
  }

  /// Send what is waiting, then catch up. Cheap enough to call after every action.
  Future<void> flushOutbox() async {
    final r = await outbox.flush();
    if (r.stoppedBy != null) online = !r.stoppedBy!.isNetwork;
    if (r.sent > 0) notifyListeners();
  }

  Future<void> syncNow() async {
    if (!signedIn) return;
    try {
      await sync.sync();
      online = true;
    } on ApiException catch (e) {
      online = !e.isNetwork;
    }
    notifyListeners();
  }

  /// Download the pictures and sounds a child needs offline.
  Future<void> _prefetch() async {
    final items = <({String url, String? version})>[];
    for (final c in children) {
      for (final l in sync.lessonsFor(c.id, publishedOnly: true)) {
        for (final t in l.tasks) {
          if (t.imageUrl != null) items.add((url: t.imageUrl!, version: t.imageV));
          if (t.audioUrl != null) items.add((url: t.audioUrl!, version: t.audioV));
        }
      }
      for (final f in sync.familyFor(c.id)) {
        if (f.photoUrl != null) items.add((url: f.photoUrl!, version: f.photoV));
        if (f.audioUrl != null) items.add((url: f.audioUrl!, version: f.audioV));
      }
      for (final k in stockCards) {
        if (k.iconPath != null) items.add((url: publicUrl(k.iconPath!), version: null));
      }
      for (final k in sync.aacCardsFor(c.id)) {
        if (k.iconUrl != null) items.add((url: k.iconUrl!, version: k.iconV));
        if (k.audioUrl != null) items.add((url: k.audioUrl!, version: k.audioV));
      }
    }
    await media.prefetch(items);
  }

  /// Sync now and then every [every], and flush the queue each time. Call [stopAutoSync] when the app goes to the background.
  void startAutoSync({Duration every = const Duration(seconds: 20)}) {
    _timer?.cancel();
    _timer = Timer.periodic(every, (_) async {
      await flushOutbox();
      await syncNow();
    });
    _pushSub ??= push.messages.listen((_) => syncNow());
  }

  void stopAutoSync() {
    _timer?.cancel();
    _timer = null;
  }

  /// Register this phone for pushes. Does nothing when push is not set up.
  Future<void> startPush() async {
    await push.start((tok, type) async {
      try {
        await api.registerDevice(tok, type);
      } on ApiException {
        // retried at the next start
      }
    });
    _pushSub ??= push.messages.listen((_) => syncNow());
  }

  // ---------- choices the app remembers ----------

  Future<void> selectChild(int id) async {
    currentChildId = id;
    await store.write(_kChild, '$id');
    notifyListeners();
  }

  /// Back to "who is playing?" without signing the parent out.
  Future<void> signOutChild() async {
    currentChildId = null;
    await store.remove(_kChild);
    notifyListeners();
  }

  Future<void> setLang(String l) async {
    lang = l == 'ur' ? 'ur' : 'en';
    await store.write(_kLang, lang);
    notifyListeners();
  }

  /// Point the app at another server (development, a test site).
  Future<void> setBaseUrl(String url) async {
    var u = url.trim();
    if (u.endsWith('/')) u = u.substring(0, u.length - 1);
    if (u.isEmpty || u == baseUrl) return;
    await store.write(_kBase, u);
    await signOut(callServer: false);
    _build(u);
    notifyListeners();
  }

  /// Send a write that must not be lost (an alert, a practice session): it is saved at once and goes out as soon as there is a connection.
  Future<void> queue(String method, String path, {Object? body, String keyPrefix = 'app'}) async {
    await outbox.add(method, path, body: body, keyPrefix: keyPrefix);
    unawaited(flushOutbox());
    notifyListeners();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _pushSub?.cancel();
    _syncSub?.cancel();
    sync.dispose();
    super.dispose();
  }
}

/// `dart:convert` under a short name, kept here so the file reads top to bottom.
class JsonCodecShim {
  const JsonCodecShim();
  dynamic decode(String s) => conv.json.decode(s);
  String encode(Object? o) => conv.json.encode(o);
}
