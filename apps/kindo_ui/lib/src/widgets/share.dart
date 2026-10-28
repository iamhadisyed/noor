import 'dart:typed_data';

import 'package:share_plus/share_plus.dart';

/// Open the phone's share sheet with a file (a PDF report, a milestone card). WhatsApp, email and Files all appear there.
Future<void> shareBytes(Uint8List bytes, String filename, String mimeType, {String? text}) async {
  await SharePlus.instance.share(ShareParams(files: [XFile.fromData(bytes, name: filename, mimeType: mimeType)], fileNameOverrides: [filename], text: text));
}
