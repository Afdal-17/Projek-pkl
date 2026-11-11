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
  static String get apiBaseUrl => 'https://sinuous-fame-jazz.ngrok-free.dev/api';
  DummyData._();

  static String userName = "User";
  static String userInitials = 'U';
  static String userEmail = 'user@finance.app';
  static String userPhone = '';
  static String userLocation = 'Indonesia';
  static dynamic userAvatarFile; // File? jika dipilih dari device

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

  static List<Wallet> get wallets => const [
        Wallet(
          name: 'Cash',
          type: 'Physical wallet',
          balanceIdr: 3500000,
          isPrimary: true,
          icon: Icons.payments_outlined,
          color: AppColors.income,
          background: AppColors.incomeSoft,
        ),
        Wallet(
          name: 'BCA',
          type: 'Bank account',
          balanceIdr: 15200000,
          isPrimary: false,
          icon: Icons.account_balance_outlined,
          color: AppColors.brand,
          background: AppColors.brandSoft,
        ),
        Wallet(
          name: 'BNI',
          type: 'Bank account',
          balanceIdr: 760000,
          isPrimary: false,
          icon: Icons.account_balance_wallet_outlined,
          color: AppColors.warn,
          background: AppColors.warnSoft,
        ),
        Wallet(
          name: 'BRI',
          type: 'Bank account',
          balanceIdr: 1850000,
          isPrimary: false,
          icon: Icons.credit_card_rounded,
          color: AppColors.dark,
          background: AppColors.page,
        ),
      ];

  static const monthlyIncomeIdr = 5800000;
  static const monthlyExpenseIdr = 2350000;

  static List<TransactionItem> get transactions => [
        TransactionItem(
          title: 'Monthly salary',
          category: 'Income',
          dateLabel: 'Today',
          amount: money(5000000),
          type: TransactionType.income,
          icon: Icons.account_balance_rounded,
          iconColor: AppColors.income,
          iconBackground: AppColors.incomeSoft,
        ),
        TransactionItem(
          title: 'Groceries',
          category: 'Shopping',
          dateLabel: 'Today',
          amount: money(350000),
          type: TransactionType.expense,
          icon: Icons.shopping_bag_outlined,
          iconColor: AppColors.expense,
          iconBackground: AppColors.expenseSoft,
        ),
        TransactionItem(
          title: 'Lunch',
          category: 'Food',
          dateLabel: 'Yesterday',
          amount: money(45000),
          type: TransactionType.expense,
          icon: Icons.restaurant_outlined,
          iconColor: AppColors.warn,
          iconBackground: AppColors.warnSoft,
        ),
        TransactionItem(
          title: 'Freelance project',
          category: 'Income',
          dateLabel: 'Yesterday',
          amount: money(800000),
          type: TransactionType.income,
          icon: Icons.laptop_mac_rounded,
          iconColor: AppColors.brand,
          iconBackground: AppColors.brandSoft,
        ),
      ];

  static final List<SavingGoal> savings = [
        SavingGoal(
          name: 'Lunasin hutang Indonesia',
          description: 'Cash',
          walletName: 'Cash',
          savedIdr: 15800,
          targetIdr: 15800,
        ),
        SavingGoal(
          name: 'Laptop',
          description: 'BCA',
          walletName: 'BCA',
          savedIdr: 760,
          targetIdr: 760,
        ),
        SavingGoal(
          name: 'New Laptop',
          description: 'butuh laptop buat belajar',
          walletName: 'BCA',
          savedIdr: 1490000,
          targetIdr: 1540000,
        ),
      ];
}
