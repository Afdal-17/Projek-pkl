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

/// Bentuk nilai uang sesuai kontrak API: base (USD) + display + rate.
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

  static Money? tryParse(dynamic value) {
    if (value is! Map) return null;
    final json = Map<String, dynamic>.from(value);
    final baseAmount = _number(json['base_amount']);
    final displayAmount = _number(json['display_amount']);
    final rateJson = json['rate'];
    if (baseAmount == null ||
        displayAmount == null ||
        json['base_currency'] == null ||
        json['display_currency'] == null ||
        rateJson is! Map ||
        rateJson['from'] == null ||
        rateJson['to'] == null) {
      return null;
    }

    final rateValue = _number(rateJson['value']);
    if (rateValue == null) return null;

    return Money(
      baseAmount: baseAmount,
      baseCurrency: json['base_currency'].toString(),
      displayAmount: displayAmount,
      displayCurrency: json['display_currency'].toString(),
      rate: Rate(
        from: rateJson['from'].toString(),
        to: rateJson['to'].toString(),
        value: rateValue,
        fetchedAt: rateJson['fetched_at']?.toString(),
      ),
      formatted: json['formatted']?.toString(),
    );
  }

  static double? _number(dynamic value) {
    if (value is num) return value.toDouble();
    return value == null ? null : double.tryParse(value.toString());
  }

  String get text =>
      formatted ?? formatCurrency(displayAmount, displayCurrency);
}
