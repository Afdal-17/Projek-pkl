import '../utils/currency.dart';

/// Kurs yang dipakai untuk konversi. Dikirim backend bersama nilai uang.
class Rate {
  const Rate({
    required this.from,
    required this.to,
    required this.value,
    this.fetchedAt,
  });

  final String from;
  final String to;
  final double value;
  final String? fetchedAt;
}

/// Bentuk nilai uang sesuai kontrak API: base (asli, IDR) + display (hasil konversi) + rate.
class Money {
  const Money({
    required this.baseAmount,
    required this.baseCurrency,
    required this.displayAmount,
    required this.displayCurrency,
    required this.rate,
    this.formatted,
  });

  final double baseAmount;
  final String baseCurrency;
  final double displayAmount;
  final String displayCurrency;
  final Rate rate;

  /// Teks siap tampil. Pakai dari backend jika ada, kalau tidak pakai formatter lokal.
  final String? formatted;

  String get text => formatted ?? formatCurrency(displayAmount, displayCurrency);
}
