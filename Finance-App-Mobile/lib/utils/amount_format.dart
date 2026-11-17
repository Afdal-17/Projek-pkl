import 'package:flutter/services.dart';
import 'currency.dart';

/// Format angka sesuai mata uang yang dipilih.
class AmountFormat {
  AmountFormat._();

  /// Mata uang yang memakai titik sebagai desimal (contoh: USD 1,374.49).
  /// Mata uang lain memakai koma sebagai desimal (contoh: EUR 120.620,65 | IDR 120.620).
  static const _dotDecimalCurrencies = {'USD', 'GBP'};

  static bool get _dotDecimal =>
      _dotDecimalCurrencies.contains(preferredCurrency.value);

  static String get thousandSep => _dotDecimal ? ',' : '.';
  static String get decimalSep => _dotDecimal ? '.' : ',';

  /// Memberi pemisah ribuan pada string digit, contoh: "120620" -> "120.620" (IDR/EUR) atau "120,620" (USD).
  static String group(String digits) {
    final buf = StringBuffer();
    for (var i = 0; i < digits.length; i++) {
      if (i > 0 && (digits.length - i) % 3 == 0) buf.write(thousandSep);
      buf.write(digits[i]);
    }
    return buf.toString();
  }

  /// Angka ke teks dengan 2 desimal, contoh: 226.97 -> "226,97" atau "226.97".
  static String fromDouble(double value) {
    final parts = value.abs().toStringAsFixed(2).split('.');
    return '${group(parts[0])}$decimalSep${parts[1]}';
  }

  /// Teks yang sudah diformat kembali ke angka numerik (double).
  /// Menghilangkan pemisah ribuan dan menyelaraskan koma/titik desimal secara aman.
  static double parse(String text) {
    if (text.trim().isEmpty) return 0;
    String clean = text.trim();

    final commaCount = ','.allMatches(clean).length;
    final dotCount = '.'.allMatches(clean).length;

    if (commaCount > 1) {
      clean = clean.replaceAll(',', '');
    }
    if (dotCount > 1) {
      clean = clean.replaceAll('.', '');
    }

    if (clean.contains(',') && clean.contains('.')) {
      final lastComma = clean.lastIndexOf(',');
      final lastDot = clean.lastIndexOf('.');
      if (lastComma > lastDot) {
        clean = clean.replaceAll('.', '').replaceAll(',', '.');
      } else {
        clean = clean.replaceAll(',', '');
      }
    } else if (clean.contains(',')) {
      final parts = clean.split(',');
      if (parts.length == 2 && parts[1].length == 3) {
        clean = clean.replaceAll(',', '');
      } else {
        clean = clean.replaceAll(',', '.');
      }
    } else if (clean.contains('.')) {
      final parts = clean.split('.');
      if (parts.length == 2 && parts[1].length == 3) {
        clean = clean.replaceAll('.', '');
      }
    }

    return double.tryParse(clean) ?? 0;
  }
}

/// Memformat angka saat diketik: pemisah ribuan otomatis sesuai mata uang,
/// maksimal 2 digit desimal, fleksibel mengenali input titik maupun koma dari keyboard.
class AmountInputFormatter extends TextInputFormatter {
  static final _digit = RegExp(r'^\d$');

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    if (newValue.text.isEmpty) {
      return newValue;
    }

    final sep = AmountFormat.decimalSep;
    final intPart = StringBuffer();
    final decPart = StringBuffer();
    var hasDecimal = false;

    // Dukung tombol pemisah desimal alternatif jika keyboard HP hanya ada '.' atau ','
    final altSep = sep == '.' ? ',' : '.';
    String textToScan = newValue.text;
    if (newValue.text.length > oldValue.text.length &&
        newValue.text.endsWith(altSep) &&
        !oldValue.text.contains(sep)) {
      textToScan = '${oldValue.text}$sep';
    }

    for (final ch in textToScan.split('')) {
      if (_digit.hasMatch(ch)) {
        if (hasDecimal) {
          if (decPart.length < 2) decPart.write(ch);
        } else {
          intPart.write(ch);
        }
      } else if (ch == sep && !hasDecimal) {
        hasDecimal = true;
      }
    }

    var intDigits = intPart.toString().replaceFirst(RegExp(r'^0+(?=\d)'), '');
    if (intDigits.isEmpty && hasDecimal) intDigits = '0';

    final text = hasDecimal
        ? '${AmountFormat.group(intDigits)}$sep$decPart'
        : AmountFormat.group(intDigits);

    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}