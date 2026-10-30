import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import '../utils/amount_format.dart';
import '../utils/currency.dart';

/// Komponen input nominal terstandarisasi untuk semua form finansial.
/// - Menjamin presisi nominal besar (IDR jutaan/miliaran).
/// - Menggunakan [AmountInputFormatter] dan [AmountFormat] yang kompatibel multi-mata uang.
/// - Menyesuaikan fokus border (#4F46E5) dan gaya desain web.
class AmountInputField extends StatelessWidget {
  const AmountInputField({
    super.key,
    required this.controller,
    this.label,
    this.currencySymbol,
    this.onAmountChanged,
    this.onChanged,
    this.hintText = '0',
    this.enabled = true,
    this.errorText,
    this.helperWidget,
    this.autofocus = false,
  });

  final TextEditingController controller;
  final String? label;

  /// Simbol mata uang di depan input (contoh: $, Rp, €).
  /// Jika null, otomatis diambil dari preferensi mata uang aktif.
  final String? currencySymbol;

  /// Callback yang mengembalikan nilai numerik [double] dengan presisi penuh.
  final ValueChanged<double>? onAmountChanged;

  /// Callback yang mengembalikan teks string yang telah diformat.
  final ValueChanged<String>? onChanged;

  final String hintText;
  final bool enabled;
  final String? errorText;
  final Widget? helperWidget;
  final bool autofocus;

  String _resolveSymbol() {
    if (currencySymbol != null && currencySymbol!.trim().isNotEmpty) {
      return currencySymbol!.trim();
    }
    final code = preferredCurrency.value;
    switch (code) {
      case 'IDR':
        return 'Rp';
      case 'EUR':
        return '€';
      case 'GBP':
        return '£';
      case 'JPY':
        return '¥';
      case 'USD':
      default:
        return r'$';
    }
  }

  @override
  Widget build(BuildContext context) {
    final sym = _resolveSymbol();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (label != null) ...[
          Text(
            label!,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: AppColors.ink,
            ),
          ),
          const SizedBox(height: 6),
        ],
        Container(
          decoration: BoxDecoration(
            color: enabled ? Colors.white : const Color(0xFFF9FAFB),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: errorText != null ? AppColors.expense : AppColors.line,
              width: 1,
            ),
          ),
          child: Row(
            children: [
              Padding(
                padding: const EdgeInsets.only(left: 14, right: 10),
                child: Text(
                  sym,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: AppColors.muted,
                  ),
                ),
              ),
              Expanded(
                child: TextField(
                  controller: controller,
                  enabled: enabled,
                  autofocus: autofocus,
                  keyboardType:
                      const TextInputType.numberWithOptions(decimal: true),
                  inputFormatters: [AmountInputFormatter()],
                  textAlignVertical: TextAlignVertical.center,
                  onChanged: (val) {
                    onChanged?.call(val);
                    if (onAmountChanged != null) {
                      final parsed = AmountFormat.parse(val);
                      onAmountChanged!(parsed);
                    }
                  },
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ink,
                  ),
                  decoration: InputDecoration(
                    hintText: hintText,
                    hintStyle: const TextStyle(
                      color: Color(0xFF9CA3AF),
                      fontSize: 16,
                      fontWeight: FontWeight.w500,
                    ),
                    border: InputBorder.none,
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
              const SizedBox(width: 8),
            ],
          ),
        ),
        if (errorText != null) ...[
          const SizedBox(height: 4),
          Text(
            errorText!,
            style: const TextStyle(fontSize: 12, color: AppColors.expense),
          ),
        ],
        if (helperWidget != null) ...[
          const SizedBox(height: 6),
          helperWidget!,
        ],
      ],
    );
  }
}

