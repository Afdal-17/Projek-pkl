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
List<WalletTransaction> dummyTransactionsFor(String walletName) {
  if (walletName != 'Cash') return const [];

  return const [
    WalletTransaction(
      title: 'Transfer from Afdal',
      category: 'Transfer',
      date: '2026-10-24',
      amount: 0.59,
      icon: Icons.swap_horiz_rounded,
      isTransfer: true,
    ),
    WalletTransaction(
      title: 'Transfer to opo',
      category: 'Transfer',
      date: '2026-10-15',
      amount: -9.84,
      icon: Icons.swap_horiz_rounded,
      isTransfer: true,
    ),
    WalletTransaction(
      title: 'Transfer to opo',
      category: 'Transfer',
      date: '2026-10-15',
      amount: -9.84,
      icon: Icons.swap_horiz_rounded,
      isTransfer: true,
    ),
    WalletTransaction(
      title: 'Ticket Music',
      category: 'Music Conser',
      date: '2026-10-14',
      amount: -246.07,
      icon: Icons.confirmation_number_outlined,
    ),
  ];
}
