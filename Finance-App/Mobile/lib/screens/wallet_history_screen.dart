import 'package:flutter/material.dart';
import '../data/dummy_data.dart';
import '../models/wallet.dart';
import '../models/wallet_transaction.dart';
import '../theme/app_colors.dart';
import '../widgets/app_card.dart';

/// Riwayat saldo satu dompet, meniru halaman "Wallet balance history" di web.
class WalletHistoryScreen extends StatelessWidget {
  const WalletHistoryScreen({
    super.key,
    required this.wallet,
    required this.transactions,
  });

  final Wallet wallet;
  final List<WalletTransaction> transactions;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.page,
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          children: [
            InkWell(
              borderRadius: BorderRadius.circular(8),
              onTap: () => Navigator.of(context).pop(),
              child: const Padding(
                padding: EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  children: [
                    Icon(Icons.arrow_back_rounded,
                        size: 18, color: AppColors.muted),
                    SizedBox(width: 8),
                    Text(
                      'Back',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: AppColors.muted,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            Text(
              wallet.name,
              style: const TextStyle(
                fontSize: 26,
                fontWeight: FontWeight.w800,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Wallet balance history',
              style: TextStyle(fontSize: 14, color: AppColors.muted),
            ),
            const SizedBox(height: 16),
            const Text(
              'Current balance',
              style: TextStyle(fontSize: 13, color: AppColors.muted),
            ),
            const SizedBox(height: 2),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                DummyData.money(wallet.balanceIdr).text,
                style: const TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w800,
                  color: AppColors.ink,
                ),
              ),
            ),
            const SizedBox(height: 20),
            if (transactions.isEmpty)
              const AppCard(
                child: Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: Center(
                    child: Text(
                      'No transactions for this wallet yet.',
                      style: TextStyle(fontSize: 14, color: AppColors.muted),
                    ),
                  ),
                ),
              )
            else
              AppCard(
                padding: EdgeInsets.zero,
                child: Column(
                  children: [
                    for (int i = 0; i < transactions.length; i++) ...[
                      _TransactionRow(item: transactions[i]),
                      if (i < transactions.length - 1)
                        const Divider(height: 1, color: AppColors.line),
                    ],
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _TransactionRow extends StatelessWidget {
  const _TransactionRow({required this.item});

  final WalletTransaction item;

  @override
  Widget build(BuildContext context) {
    final isIncome = item.amount >= 0;
    final amountColor = isIncome ? AppColors.income : AppColors.expense;
    final sign = isIncome ? '+' : '-';
    final amountText =
        '$sign ${DummyData.money(item.amount.abs()).text}';

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: item.isTransfer ? AppColors.brandSoft : AppColors.expenseSoft,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(
              item.icon,
              size: 20,
              color: item.isTransfer ? AppColors.brand : AppColors.expense,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ink,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '${item.category} · ${item.date}',
                  style: const TextStyle(fontSize: 13, color: AppColors.muted),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Text(
            amountText,
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: amountColor,
            ),
          ),
        ],
      ),
    );
  }
}
