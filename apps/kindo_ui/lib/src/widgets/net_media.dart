import 'dart:typed_data';

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'package:kindo_core/kindo_core.dart';

import '../session.dart';
import '../theme.dart';

/// A picture from the server, kept for offline use (see [MediaCache]).
class NetImage extends StatefulWidget {
  const NetImage(this.url, {super.key, this.version, this.fit = BoxFit.cover, this.placeholder, this.semanticLabel, this.radius = 0});
  final String? url;
  final String? version;
  final BoxFit fit;
  final Widget? placeholder;
  final String? semanticLabel;
  final double radius;

  @override
  State<NetImage> createState() => _NetImageState();
}

class _NetImageState extends State<NetImage> {
  Future<Uint8List?>? _bytes;
  String? _for;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _load();
  }

  @override
  void didUpdateWidget(NetImage old) {
    super.didUpdateWidget(old);
    _load();
  }

  void _load() {
    final key = '${widget.url}|${widget.version}';
    if (key == _for) return;
    _for = key;
    final url = widget.url;
    _bytes = url == null ? Future.value(null) : context.read<KindoSession>().media.bytes(url, version: widget.version);
  }

  @override
  Widget build(BuildContext context) {
    final fallback = widget.placeholder ?? Container(color: KindoColors.lavenderTint, child: const Center(child: Icon(Icons.image_outlined, color: KindoColors.gray)));
    return ClipRRect(
      borderRadius: BorderRadius.circular(widget.radius),
      child: FutureBuilder<Uint8List?>(
        future: _bytes,
        builder: (context, snap) {
          final b = snap.data;
          if (b == null) return fallback;
          return Image.memory(b, fit: widget.fit, semanticLabel: widget.semanticLabel, gaplessPlayback: true, errorBuilder: (_, _, _) => fallback);
        },
      ),
    );
  }
}

/// Plays sounds from the server (from the saved copy when there is one). One sound at a time.
class AudioHub {
  AudioHub._();
  static final AudioHub instance = AudioHub._();

  final AudioPlayer _player = AudioPlayer();

  Future<void> play(KindoSession session, String? url, {String? version}) async {
    if (url == null) return;
    final bytes = await session.media.bytes(url, version: version);
    if (bytes == null) return;
    try {
      await _player.stop();
      await _player.play(BytesSource(bytes));
    } catch (_) {
      // no sound output (a test, a muted device): nothing to do
    }
  }

  Future<void> stop() async {
    try {
      await _player.stop();
    } catch (_) {}
  }
}

/// A round button that plays a sound. Big enough for small hands.
class SoundButton extends StatelessWidget {
  const SoundButton({super.key, required this.url, this.version, this.size = 64, this.label = 'Play the sound'});
  final String? url;
  final String? version;
  final double size;
  final String label;

  @override
  Widget build(BuildContext context) => Semantics(
        button: true,
        label: label,
        child: Material(
          color: KindoColors.butter,
          shape: const CircleBorder(),
          child: InkWell(
            customBorder: const CircleBorder(),
            onTap: url == null ? null : () => AudioHub.instance.play(context.read<KindoSession>(), url, version: version),
            child: SizedBox(width: size, height: size, child: Icon(Icons.volume_up_rounded, size: size * .55, color: KindoColors.ink)),
          ),
        ),
      );
}

/// The picture on a picture card: the parent's own (signed link) or the stock one shipped with the site.
class CardPicture extends StatelessWidget {
  const CardPicture(this.card, {super.key, this.radius = 18});
  final AacCard card;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final s = context.read<KindoSession>();
    final url = card.iconUrl ?? (card.iconPath == null ? null : s.publicUrl(card.iconPath!));
    return NetImage(url, version: card.iconV, fit: BoxFit.contain, radius: radius, semanticLabel: card.englishLabel);
  }
}
