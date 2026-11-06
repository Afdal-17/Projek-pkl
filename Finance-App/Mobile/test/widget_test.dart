import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:finance_app_mobile/main.dart';

void main() {
  testWidgets('Bottom bar memiliki empat menu dan bisa berpindah tab',
    (tester) async {

      await tester.pumpWidget(
        MaterialApp(
          home: MainShell(onLogout: () {}),
        ),
      );
      await tester.pump();
      await tester.pump(const Duration(seconds: 1));

      expect(tester.takeException(), isNull);
      expect(find.byType(Scaffold), findsWidgets);
      expect(find.byType(NavigationBar), findsOneWidget);

      final before = tester.widget<NavigationBar>(find.byType(NavigationBar));
      expect(before.destinations.length, 4);
      expect(before.selectedIndex, 0);
      expect(find.text('Dashboard'), findsOneWidget);

      await tester.tap(find.byIcon(Icons.savings_outlined));
      await tester.pump(const Duration(milliseconds: 500));

      final after = tester.widget<NavigationBar>(find.byType(NavigationBar));
      expect(after.selectedIndex, 2);
  });
}
