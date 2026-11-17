import 'package:flutter/material.dart';
// ignore: unused_import
import '../theme/app_colors.dart';

class WalletTransaction {
  const WalletTransaction({
    required this.title,
    required this.category,
    required this.date,
    required this.amount,
    required this.icon,
    this.isTransfer = false,
  });

  final String title;
  final String category;
  final String date;

  /// Positif = uang masuk, negatif = uang keluar.
  final double amount;
  final IconData icon;
  final bool isTransfer;
}

/// Data contoh sementara. Nanti diganti dengan data dari dummy_data.dart
/// atau dari backend.
List<WalletTransaction> dummyTransactionsFor(String walletName) => const [];