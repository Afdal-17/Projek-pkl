import 'package:flutter/material.dart';
import '../models/transaction_item.dart';
import '../theme/app_colors.dart';
import 'dummy_data.dart';

TransactionItem _tx({
  required String title,
  required String category,
  required String dateLabel,
  required double amount,
  required String wallet,
}) {
  final isIncome = amount >= 0;
  final isTransfer = category == 'Transfer';

  final Color iconColor;
  final Color iconBackground;
  if (isTransfer) {
    iconColor = AppColors.brand;
    iconBackground = AppColors.brandSoft;
  } else if (isIncome) {
    iconColor = AppColors.income;
    iconBackground = AppColors.incomeSoft;
  } else {
    iconColor = AppColors.expense;
    iconBackground = AppColors.expenseSoft;
  }

    return TransactionItem(
      title: title,
      category: category,
      dateLabel: dateLabel,
      amount: DummyData.money(amount.abs()),
      type: isIncome ? TransactionType.income : TransactionType.expense,
      icon: isTransfer
          ? Icons.swap_horiz_rounded
          : Icons.receipt_long_outlined,
      iconColor: iconColor,
      iconBackground: iconBackground,
      walletName: wallet,
      value: amount,
      date: DateTime(2026, 10, int.parse(dateLabel.split(' ').first)),
  );
}

final List<TransactionItem> sampleTransactions = const [];