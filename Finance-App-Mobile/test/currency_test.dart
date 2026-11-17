import 'package:flutter_test/flutter_test.dart';

import 'package:finance_app_mobile/utils/currency.dart';
import 'package:finance_app_mobile/utils/app_translator.dart';
import 'package:finance_app_mobile/utils/amount_format.dart';
import 'package:finance_app_mobile/utils/refresh_notifier.dart';

void main() {
  test('AmountFormat.parse mengurai nominal Rupiah dengan benar', () {
    expect(AmountFormat.parse('20.000'), 20000.0);
    expect(AmountFormat.parse('200.000'), 200000.0);
    expect(AmountFormat.parse('2.000.000'), 2000000.0);
    expect(AmountFormat.parse('2.800.000'), 2800000.0);
    expect(AmountFormat.parse('364.890,26'), 364890.26);
  });
  test('formatCurrency memakai format sesuai mata uang', () {
    expect(formatCurrency(1234.5, 'USD'), r'$1,234.50');
    expect(formatCurrency(1234.5, 'EUR'), '€1.234,50');
    expect(formatCurrency(1234567, 'IDR'), 'Rp 1.234.567');
    expect(formatCurrency(0, 'USD'), r'$0.00');
    expect(formatCurrency(-45000, 'IDR'), '-Rp 45.000');
  });

  test('mata uang default adalah USD', () {
    expect(preferredCurrency.value, 'USD');
  });

  test('mengubah preferredCurrency memicu AppRefreshNotifier secara otomatis', () {
    final initialTick = AppRefreshNotifier.refreshTick.value;
    preferredCurrency.value = 'EUR';
    expect(AppRefreshNotifier.refreshTick.value, greaterThan(initialTick));
    preferredCurrency.value = 'IDR';
  });

  test('AppTranslator translates transfer transaction names into English', () {
    expect(
      AppTranslator.translateTransactionName('Transfer ke Gakt Tau'),
      'Transfer to Gakt Tau',
    );
    expect(
      AppTranslator.translateTransactionName('Transfer ke Gakt ...'),
      'Transfer to Gakt ...',
    );
    expect(
      AppTranslator.translateTransactionName('Transfer dari Gakt Tau'),
      'Transfer from Gakt Tau',
    );
    expect(
      AppTranslator.translateTransactionName('Transfer antardompet'),
      'Transfer between wallets',
    );
    expect(
      AppTranslator.translateTransactionName('Saldo awal'),
      'Initial balance',
    );
  });

  test('AppTranslator translates categories into English', () {
    expect(AppTranslator.translateCategory('Gaji'), 'Salary');
    expect(AppTranslator.translateCategory('Makan'), 'Food & Dining');
    expect(AppTranslator.translateCategory('Transportasi'), 'Transportation');
  });

  test('AppTranslator translates transfer notification with dot-separated numbers', () {
    expect(
      AppTranslator.translateNotification('Anda di transfer oleh Afdal Firmansyah of Rp 444.'),
      'You received a transfer of Rp 444 from Afdal Firmansyah.',
    );
    expect(
      AppTranslator.translateNotification('Anda di transfer oleh Afout Gang 5 of Rp 12.345.'),
      'You received a transfer of Rp 12.345 from Afout Gang 5.',
    );
    expect(
      AppTranslator.translateNotification('Anda di transfer oleh Afout Gang 5 sebesar Rp 20.000.000.'),
      'You received a transfer of Rp 20.000.000 from Afout Gang 5.',
    );
  });
}
