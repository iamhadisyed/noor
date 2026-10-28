import 'dart:math';

import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';

/// A sum a young child cannot do yet, so only a grown-up gets past it. Returns true when answered correctly.
Future<bool> parentGate(BuildContext context) async {
  final r = Random();
  final a = 6 + r.nextInt(7), b = 4 + r.nextInt(6);
  final c = TextEditingController();
  final ok = await showDialog<bool>(
    context: context,
    builder: (d) => AlertDialog(
      title: Text(context.tr('Grown-ups only', 'صرف بڑوں کے لیے')),
      content: Column(mainAxisSize: MainAxisSize.min, children: [
        Text('$a + $b = ?', style: Theme.of(context).textTheme.headlineMedium),
        const SizedBox(height: 12),
        TextField(controller: c, keyboardType: TextInputType.number, autofocus: true, textAlign: TextAlign.center),
      ]),
      actions: [
        TextButton(onPressed: () => Navigator.pop(d, false), child: Text(context.tr('Back', 'واپس'))),
        FilledButton(onPressed: () => Navigator.pop(d, int.tryParse(c.text.trim()) == a + b), child: Text(context.tr('Open', 'کھولیں'))),
      ],
    ),
  );
  return ok ?? false;
}
