import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:finance_app_mobile/widgets/app_button.dart';
import 'package:finance_app_mobile/widgets/app_confirm_dialog.dart';
import 'package:finance_app_mobile/widgets/amount_input_field.dart';
import 'package:finance_app_mobile/widgets/wallet_picker_sheet.dart';
import 'package:finance_app_mobile/data/dummy_data.dart';
import 'package:finance_app_mobile/models/wallet.dart';
import 'package:finance_app_mobile/utils/currency.dart';

void main() {
  setUp(() {
    preferredCurrency.value = 'USD';
  });

  group('AppButton Tests', () {
    testWidgets('AppButton renders label and handles tap', (tester) async {
      var tapped = false;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: AppButton(
              label: 'Submit Test',
              onPressed: () => tapped = true,
            ),
          ),
        ),
      );

      expect(find.text('Submit Test'), findsOneWidget);
      await tester.tap(find.text('Submit Test'));
      await tester.pump();
      expect(tapped, isTrue);
    });

    testWidgets('AppButton shows loading spinner and disables clicks when isLoading is true', (tester) async {
      var tapped = false;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: AppButton(
              label: 'Save',
              isLoading: true,
              onPressed: () => tapped = true,
            ),
          ),
        ),
      );

      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      await tester.tap(find.byType(AppButton));
      await tester.pump();
      expect(tapped, isFalse);
    });
  });

  group('AppConfirmDialog Tests', () {
    testWidgets('showAppConfirmDialog returns true on confirm', (tester) async {
      bool? result;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () async {
                  result = await showAppConfirmDialog(
                    context: context,
                    title: 'Delete Item',
                    message: 'Are you sure you want to delete this?',
                    confirmLabel: 'Yes, Delete',
                    isDestructive: true,
                  );
                },
                child: const Text('Open Dialog'),
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Open Dialog'));
      await tester.pumpAndSettle();

      expect(find.text('Delete Item'), findsOneWidget);
      expect(find.text('Are you sure you want to delete this?'), findsOneWidget);
      expect(find.text('Yes, Delete'), findsOneWidget);

      await tester.tap(find.text('Yes, Delete'));
      await tester.pumpAndSettle();

      expect(result, isTrue);
    });

    testWidgets('showAppConfirmDialog returns false on cancel', (tester) async {
      bool? result;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () async {
                  result = await showAppConfirmDialog(
                    context: context,
                    title: 'Logout',
                    message: 'Are you sure?',
                  );
                },
                child: const Text('Open Dialog'),
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Open Dialog'));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();

      expect(result, isFalse);
    });
  });

  group('AmountInputField Tests', () {
    testWidgets('AmountInputField formats number and delivers numeric double accurately', (tester) async {
      final ctrl = TextEditingController();
      double received = 0;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: AmountInputField(
              controller: ctrl,
              currencySymbol: r'$',
              onAmountChanged: (amt) => received = amt,
            ),
          ),
        ),
      );

      await tester.enterText(find.byType(TextField), '1250000');
      await tester.pump();

      expect(received, 1250000.0);
    });
  });

  group('WalletPickerSheet Tests', () {
    testWidgets('WalletPickerSheet displays only user wallets and allows selection', (tester) async {
      Wallet? pickedWallet;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () async {
                  pickedWallet = await showWalletPickerSheet(
                    context: context,
                    showBalance: true,
                  );
                },
                child: const Text('Pick Wallet'),
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Pick Wallet'));
      await tester.pumpAndSettle();

      expect(find.text('Select Wallet'), findsOneWidget);
      for (final w in DummyData.wallets) {
        expect(find.text(w.name), findsOneWidget);
      }

      // Tap on 'Cash'
      await tester.tap(find.text('Cash'));
      await tester.pumpAndSettle();

      expect(pickedWallet, isNotNull);
      expect(pickedWallet!.name, 'Cash');
    });
  });
}

