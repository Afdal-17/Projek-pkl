import 'package:flutter/foundation.dart';
import 'refresh_notifier.dart';

/// Mata uang yang bisa dipilih user (sesuai halaman Finance preferences di web).
const List<String> supportedCurrencies = ['USD', 'IDR', 'EUR'];

/// Mata uang default mobile adalah USD ($). Nilainya berubah saat user memilih di Profil.
final ValueNotifier<String> preferredCurrency = ValueNotifier<String>('USD')
  ..addListener(() {
    AppRefreshNotifier.notifyDataChanged(immediate: true);
  });

String currencyName(String code) => switch (code) {
      'USD' => 'US Dollar',
      'IDR' => 'Indonesian Rupiah',
      _ => 'Euro',
    };

/// Simbol mata uang (public helper)
String getCurrencySymbol(String code) => _symbol(code);

String _symbol(String code) => switch (code) {
      'USD' => r'$',
      'EUR' => '€',
      _ => 'Rp ',
    };

String _group(String digits, String separator) {
  final buffer = StringBuffer();
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) buffer.write(separator);
    buffer.write(digits[i]);
  }
  return buffer.toString();
}

/// Format nominal sesuai mata uang standar perbankan & akuntansi:
/// - USD: pemisah ribuan ',' dan desimal '.' -> $1,234.50
/// - EUR: pemisah ribuan '.' dan desimal ',' -> €1.234,50
/// - IDR: pemisah ribuan '.' dan desimal ',' -> Rp 1.234.567 (atau Rp 20.000,05 jika ada desimal/sen)
String formatCurrency(double amount, String code, {bool alwaysShowDecimals = false}) {
  final hasCents = (amount.abs() * 100).round() % 100 != 0;
  final decimals = (code == 'IDR' && !hasCents && !alwaysShowDecimals) ? 0 : 2;
  final thousands = code == 'USD' ? ',' : '.';
  final decimal = code == 'USD' ? '.' : ',';
  final negative = amount < 0;
  final parts = amount.abs().toStringAsFixed(decimals).split('.');
  final grouped = _group(parts[0], thousands);
  final number = decimals == 0 ? grouped : '$grouped$decimal${parts[1]}';
  return '${negative ? '-' : ''}${_symbol(code)}$number';
}
