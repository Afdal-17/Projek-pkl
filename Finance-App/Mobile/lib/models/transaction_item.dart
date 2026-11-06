import 'package:flutter/material.dart';
import 'money.dart';

enum TransactionType { income, expense }

class TransactionItem {
  const TransactionItem({
    required this.title,
    required this.category,
    required this.dateLabel,
    required this.amount,
    required this.type,
    required this.icon,
    required this.iconColor,
    required this.iconBackground,
    this.walletName,
    this.value = 0,
    this.date,
  });

  final String title;
  final String category;
  final String dateLabel;
  final Money amount;
  final TransactionType type;
  final IconData icon;
  final Color iconColor;
  final Color iconBackground;

  /// Nama dompet untuk tag dan filter.
  final String? walletName;

  /// Nilai angka dengan tanda (negatif = keluar). Dipakai untuk total per tanggal.
  final double value;

  /// Tanggal asli untuk filter Date. Nanti diisi dari backend.
  final DateTime? date;

  bool get isIncome => type == TransactionType.income;
}
