import 'package:flutter/material.dart';
import 'package:kindo_core/kindo_core.dart';

import '../theme.dart';

/// The spelling keyboard: letters in alphabet order (a, b, c ... or Urdu alif, bay, pay ...), never QWERTY.
/// Shows what the child has typed above the keys. Urdu reads from the right, so the layout is built right to left.
class SpellBoard extends StatefulWidget {
  const SpellBoard({super.key, required this.layout, required this.onSubmit, this.slots, this.onChanged, this.submitLabel = 'Done', this.hint});
  final KeyboardLayout layout;

  /// Called with the typed word when the child taps the tick.
  final void Function(String typed) onSubmit;

  /// How many boxes to show for the word (the target's length). Null shows only the letters typed.
  final int? slots;
  final void Function(String typed)? onChanged;
  final String submitLabel;
  final String? hint;

  @override
  State<SpellBoard> createState() => SpellBoardState();
}

class SpellBoardState extends State<SpellBoard> {
  final List<String> _typed = [];

  String get typed => _typed.join();

  void clear() => setState(_typed.clear);

  void _add(String l) {
    setState(() => _typed.add(l));
    widget.onChanged?.call(typed);
  }

  void _back() {
    if (_typed.isEmpty) return;
    setState(_typed.removeLast);
    widget.onChanged?.call(typed);
  }

  @override
  Widget build(BuildContext context) {
    final rtl = widget.layout.isRtl;
    final shown = [..._typed];
    final slots = widget.slots ?? shown.length;

    return Directionality(
      textDirection: rtl ? TextDirection.rtl : TextDirection.ltr,
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          constraints: const BoxConstraints(minHeight: 72),
          alignment: Alignment.center,
          child: Wrap(spacing: 8, runSpacing: 8, alignment: WrapAlignment.center, children: [
            for (var i = 0; i < (slots > shown.length ? slots : shown.length); i++)
              Container(
                width: 52,
                height: 62,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: i < shown.length ? KindoColors.butter : Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: i == shown.length ? KindoColors.ink : KindoColors.line, width: 2),
                ),
                child: Text(i < shown.length ? shown[i] : '', style: const TextStyle(fontSize: 30, fontWeight: FontWeight.w800)),
              ),
          ]),
        ),
        if (widget.hint != null) Padding(padding: const EdgeInsets.only(top: 4), child: Text(widget.hint!, style: Theme.of(context).textTheme.bodySmall)),
        const SizedBox(height: 14),
        for (final row in widget.layout.rows())
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              for (final l in row)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 3),
                  child: _Key(label: l, onTap: () => _add(l)),
                ),
            ]),
          ),
        const SizedBox(height: 4),
        Row(mainAxisAlignment: MainAxisAlignment.center, children: [
          OutlinedButton.icon(onPressed: _typed.isEmpty ? null : _back, icon: const Icon(Icons.backspace_outlined), label: const Text('Back')),
          const SizedBox(width: 12),
          FilledButton.icon(onPressed: _typed.isEmpty ? null : () => widget.onSubmit(typed), icon: const Icon(Icons.check_rounded), label: Text(widget.submitLabel)),
        ]),
      ]),
    );
  }
}

class _Key extends StatelessWidget {
  const _Key({required this.label, required this.onTap});
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
        button: true,
        label: label,
        child: Material(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          child: InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: onTap,
            child: Container(
              width: 44,
              height: 50,
              alignment: Alignment.center,
              decoration: BoxDecoration(borderRadius: BorderRadius.circular(14), border: Border.all(color: KindoColors.line, width: 2)),
              child: Text(label, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
            ),
          ),
        ),
      );
}
