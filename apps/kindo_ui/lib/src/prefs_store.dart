import 'package:kindo_core/kindo_core.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// [LocalStore] on the phone's preferences. Fine for the sizes involved (a family's lessons, a queue of a few writes).
class PrefsStore implements LocalStore {
  PrefsStore(this._prefs);
  final SharedPreferences _prefs;

  static Future<PrefsStore> open() async => PrefsStore(await SharedPreferences.getInstance());

  @override
  Future<String?> read(String key) async => _prefs.getString(key);

  @override
  Future<void> write(String key, String value) async {
    await _prefs.setString(key, value);
  }

  @override
  Future<void> remove(String key) async {
    await _prefs.remove(key);
  }
}
