import 'package:flutter/foundation.dart';
import 'package:flutter_tts/flutter_tts.dart';

/// Says a word out loud with the phone's own voice, for cards that have no recorded voice. Urdu needs an Urdu voice on the phone;
/// when there is none the call quietly does nothing, so the screen never depends on it.
class Speaker {
  Speaker._();
  static final Speaker instance = Speaker._();

  final FlutterTts _tts = FlutterTts();
  bool _ready = false;

  Future<void> speak(String text, {String lang = 'en', bool slow = false}) async {
    if (text.trim().isEmpty) return;
    try {
      if (!_ready) {
        await _tts.awaitSpeakCompletion(false);
        _ready = true;
      }
      await _tts.stop();
      await _tts.setLanguage(lang == 'ur' ? 'ur-PK' : 'en-US');
      await _tts.setSpeechRate(slow ? 0.35 : 0.5);
      await _tts.speak(text);
    } catch (e) {
      debugPrint('Speech is not available: $e');
    }
  }

  Future<void> stop() async {
    try {
      await _tts.stop();
    } catch (_) {
      // nothing to stop
    }
  }
}
