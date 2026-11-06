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

final List<TransactionItem> sampleTransactions = [
  _tx(title: 'Transfer from Dzaky family', category: 'Transfer', dateLabel: '24 October', amount: 0.49, wallet: 'BCA'),
  _tx(title: 'Transfer from Rofi Ng...', category: 'Transfer', dateLabel: '24 October', amount: 0.00, wallet: 'BRI'),
  _tx(title: 'Transfer from Rofi Ng...', category: 'Transfer', dateLabel: '24 October', amount: 0.10, wallet: 'BRI'),
  _tx(title: 'Transfer from Rofi N...', category: 'Transfer', dateLabel: '24 October', amount: 98.43, wallet: 'BRI'),
  _tx(title: 'Transfer from 07 Dzaky', category: 'Transfer', dateLabel: '24 October', amount: 0.49, wallet: 'BRI'),
  _tx(title: 'Transfer from Rofi', category: 'Transfer', dateLabel: '24 October', amount: 20086.94, wallet: 'BRI'),
  _tx(title: 'Transfer from Afdal', category: 'Transfer', dateLabel: '24 October', amount: 0.59, wallet: 'Cash'),
  _tx(title: 'Grocery shopping', category: 'Makan', dateLabel: '15 October', amount: -0.98, wallet: 'opo'),
  _tx(title: 'Transfer to opo', category: 'Transfer', dateLabel: '15 October', amount: -9.84, wallet: 'Cash'),
  _tx(title: 'Transfer to opo', category: 'Transfer', dateLabel: '15 October', amount: -9.84, wallet: 'Cash'),
  _tx(title: 'Ticket Music', category: 'Music Conser', dateLabel: '14 October', amount: -246.07, wallet: 'Cash'),

];
