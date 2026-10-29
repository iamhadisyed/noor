import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kindo_ui/kindo_ui.dart';

void main() {
  testWidgets('the Kindo mark is announced as Kindo and fills its square', (t) async {
    await t.pumpWidget(const MaterialApp(home: Scaffold(body: Center(child: KindoMark(size: 90)))));
    expect(find.bySemanticsLabel('Kindo'), findsOneWidget);
    expect(t.getSize(find.byType(KindoMark)), const Size(90, 90));
    await t.pumpWidget(const MaterialApp(home: Scaffold(body: Center(child: KindoMark(size: 40, soft: true)))));
    expect(t.getSize(find.byType(KindoMark)), const Size(40, 40));
  });
}
