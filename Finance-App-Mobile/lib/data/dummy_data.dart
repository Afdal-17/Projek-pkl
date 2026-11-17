import 'package:flutter/material.dart';
import '../models/money.dart';
import '../models/saving_goal.dart';
import '../models/transaction_item.dart';
import '../models/wallet.dart';
import '../services/api_config.dart';
import '../services/api_service.dart';
import '../theme/app_colors.dart';
import '../utils/currency.dart';

/// ==========================================================
/// DATA DUMMY - HANYA UNTUK MEMBANGUN UI.
/// Semua nilai disimpan dalam IDR (sama seperti backend).
/// Kurs di bawah adalah KURS CONTOH, bukan kurs asli.
/// Ganti semuanya dengan data dan kurs dari API Laravel.
/// ==========================================================
class DummyData {
  /// URL Endpoint RESTful API Backend Laravel
  static String get apiBaseUrl => ApiConfig.baseUrl;
  DummyData._();

  static String userName = "User";
  static String userInitials = 'U';
  static String userEmail = 'user@finance.app';
  static String userPhone = '';
  static String userLocation = '';
  static dynamic userAvatarFile; // File? jika dipilih dari device

  /// Reset data profil user di memori saat logout / ganti akun
  static void reset() {
    userName = "User";
    userInitials = 'U';
    userEmail = 'user@finance.app';
    userPhone = '';
    userLocation = '';
    userAvatarFile = null;
  }

  static Map<String, double> _dummyRateFromIdr = <String, double>{
    'IDR': 1.0,
    'USD': 1.0 / 18000.0, // 1 USD = Rp 18.000 (18k)
    'EUR': 1.0 / 19500.0, // 1 EUR = Rp 19.500
  };

  /// Mengambil kurs mata uang langsung secara real-time dari backend (/api/currency/rates)
  static Future<void> fetchLiveRates() async {
    try {
      final res = await ApiService.get(ApiConfig.currencyRatesEndpoint);
      if (res.success && res.data is Map) {
        final map = res.data as Map;
        final newRates = <String, double>{'IDR': 1.0};
        for (final entry in map.entries) {
          final val = (entry.value as num?)?.toDouble();
          if (val != null && val > 0) {
            newRates[entry.key.toString().toUpperCase()] = 1.0 / val;
          }
        }
        if (newRates.length > 1) {
          _dummyRateFromIdr = newRates;
        }
      }
    } catch (_) {}
  }

  /// Membuat Money dari nilai IDR, dikonversi ke mata uang pilihan user.
  static Money money(num idr) {
    final target = preferredCurrency.value;
    final rate = _dummyRateFromIdr[target] ?? 1.0;
    return Money(
      baseAmount: idr.toDouble(),
      baseCurrency: 'IDR',
      displayAmount: idr.toDouble() * rate,
      displayCurrency: target,
      rate: Rate(from: 'IDR', to: target, value: rate),
    );
  }

  static List<Wallet> get wallets => const [];

  static const monthlyIncomeIdr = 0;
  static const monthlyExpenseIdr = 0;

  static List<TransactionItem> get transactions => const [];

  static final List<SavingGoal> savings = const [];
}
