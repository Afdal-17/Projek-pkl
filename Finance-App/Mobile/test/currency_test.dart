import 'package:flutter_test/flutter_test.dart';

import 'package:finance_app_mobile/utils/currency.dart';

void main() {
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
}
