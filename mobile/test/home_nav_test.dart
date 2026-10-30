import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:staff_mobile_app/features/home/pages/home/home_nav_widgets.dart';

void main() {
  testWidgets('tab switch slides the pill and reports the tapped tab', (
    tester,
  ) async {
    var current = 0;
    await tester.pumpWidget(
      MaterialApp(
        home: StatefulBuilder(
          builder:
              (context, setState) => Scaffold(
                bottomNavigationBar: HomeBottomNavigation(
                  currentIndex: current,
                  onTap: (index) async => setState(() => current = index),
                ),
              ),
        ),
      ),
    );
    await tester.tap(find.text('គណនី'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 200));
    expect(current, 4);
    expect(tester.takeException(), isNull);
    await tester.pump(const Duration(seconds: 1));
    await tester.tap(find.text('ទំព័រដើម'));
    await tester.pump(const Duration(seconds: 1));
    expect(current, 0);
    expect(tester.takeException(), isNull);
  });

  testWidgets('tab bodies cross-fade and the old one is removed', (
    tester,
  ) async {
    Widget body(String tab, int direction) => MaterialApp(
      home: Scaffold(
        body: HomeTabTransition(
          tabKey: tab,
          direction: direction,
          child: Center(child: Text(tab)),
        ),
      ),
    );

    await tester.pumpWidget(body('home', 0));
    await tester.pumpWidget(body('work', 1));
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.text('home'), findsOneWidget);
    expect(find.text('work'), findsOneWidget);
    await tester.pump(const Duration(seconds: 1));
    expect(find.text('home'), findsNothing);
    expect(find.text('work'), findsOneWidget);
  });
}
