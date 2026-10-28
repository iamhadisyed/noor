import 'dart:async';
import 'dart:io' show File;

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:kindo_core/kindo_core.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

import '../theme.dart';

/// Pick a picture from the gallery or take one, as an upload for the server.
Future<UploadFile?> pickPicture({bool camera = false}) async {
  final x = await ImagePicker().pickImage(source: camera ? ImageSource.camera : ImageSource.gallery, maxWidth: 1600, imageQuality: 88);
  if (x == null) return null;
  final name = x.name.isEmpty ? 'picture.jpg' : x.name;
  final lower = name.toLowerCase();
  final type = lower.endsWith('.png') ? 'image/png' : lower.endsWith('.webp') ? 'image/webp' : 'image/jpeg';
  return UploadFile(await x.readAsBytes(), name, contentType: type);
}

/// A button that records a short voice clip (up to [maxSeconds]) and hands it back. Tap to start, tap to stop.
class VoiceRecorderButton extends StatefulWidget {
  const VoiceRecorderButton({super.key, required this.onRecorded, this.maxSeconds = 20, this.label = 'Record'});
  final void Function(UploadFile file) onRecorded;
  final int maxSeconds;
  final String label;

  @override
  State<VoiceRecorderButton> createState() => _VoiceRecorderButtonState();
}

class _VoiceRecorderButtonState extends State<VoiceRecorderButton> {
  final _rec = AudioRecorder();
  bool _on = false;
  Timer? _stopper;

  @override
  void dispose() {
    _stopper?.cancel();
    _rec.dispose();
    super.dispose();
  }

  Future<void> _toggle() async {
    if (_on) return _stop();
    if (!await _rec.hasPermission()) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please allow the microphone to record.')));
      return;
    }
    final path = kIsWeb ? '' : '${(await getTemporaryDirectory()).path}/kindo_${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _rec.start(const RecordConfig(encoder: AudioEncoder.aacLc), path: path);
    setState(() => _on = true);
    _stopper = Timer(Duration(seconds: widget.maxSeconds), _stop);
  }

  Future<void> _stop() async {
    _stopper?.cancel();
    final out = await _rec.stop();
    if (mounted) setState(() => _on = false);
    if (out == null) return;
    final bytes = kIsWeb ? await _fetchWeb(out) : await File(out).readAsBytes();
    widget.onRecorded(UploadFile(bytes, kIsWeb ? 'voice.webm' : 'voice.m4a', contentType: kIsWeb ? 'audio/webm' : 'audio/mp4'));
  }

  Future<List<int>> _fetchWeb(String url) async => ApiClient(baseUrl: url).download(url);

  @override
  Widget build(BuildContext context) => FilledButton.icon(
        style: _on ? FilledButton.styleFrom(backgroundColor: KindoColors.warn) : null,
        onPressed: _toggle,
        icon: Icon(_on ? Icons.stop_rounded : Icons.mic_rounded),
        label: Text(_on ? 'Stop' : widget.label),
      );
}

