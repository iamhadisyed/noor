import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

/// Who is playing? Big faces to tap.
class ChildPicker extends StatelessWidget {
  const ChildPicker({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    return LangDirection(
      child: Scaffold(
        body: SafeArea(
          child: s.children.isEmpty
              ? EmptyState(
                  title: context.tr('No child yet', 'ابھی کوئی بچہ نہیں'),
                  message: context.tr('Open Kindo Parent and add your child first.', 'پہلے کنڈو پیرنٹ کھول کر بچہ شامل کریں۔'),
                  icon: Icons.child_care_rounded,
                  action: OutlinedButton(onPressed: s.refresh, child: Text(context.tr('Check again', 'دوبارہ دیکھیں'))),
                )
              : Center(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.all(24),
                    child: Column(mainAxisSize: MainAxisSize.min, children: [
                      Text(context.tr('Who is playing?', 'کون کھیل رہا ہے؟'), style: Theme.of(context).textTheme.headlineLarge),
                      const SizedBox(height: 28),
                      Wrap(spacing: 20, runSpacing: 20, alignment: WrapAlignment.center, children: [
                        for (final c in s.children)
                          Semantics(
                            button: true,
                            label: c.name,
                            child: InkWell(
                              borderRadius: BorderRadius.circular(32),
                              onTap: () => s.selectChild(c.id),
                              child: Container(
                                width: 150,
                                padding: const EdgeInsets.symmetric(vertical: 22),
                                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(32)),
                                child: Column(children: [
                                  CircleAvatar(radius: 40, backgroundColor: KindoColors.lilac, child: Text(c.name.isEmpty ? '?' : c.name[0].toUpperCase(), style: const TextStyle(fontSize: 38, fontWeight: FontWeight.w800))),
                                  const SizedBox(height: 10),
                                  Text(c.name, style: Theme.of(context).textTheme.titleLarge),
                                ]),
                              ),
                            ),
                          ),
                      ]),
                    ]),
                  ),
                ),
        ),
      ),
    );
  }
}
