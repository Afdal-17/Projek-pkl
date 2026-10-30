import 'package:flutter/material.dart';
import '../models/wallet.dart';
import '../theme/app_colors.dart';
import 'app_card.dart';

/// Bar proporsi saldo antar dompet, sama seperti "Balance distribution" di web.
class BalanceDistribution extends StatelessWidget {
  const BalanceDistribution({super.key, required this.wallets});

  final List<Wallet> wallets;

  @override
  Widget build(BuildContext context) {
    final total = wallets.fold<double>(0, (sum, w) => sum + w.balanceIdr);
    final sorted = [...wallets]
      ..sort((a, b) => b.balanceIdr.compareTo(a.balanceIdr));
    final top = sorted.isEmpty ? null : sorted.first;
    final topShare =
        (top == null || total == 0) ? 0 : (top.balanceIdr / total * 100).round();

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Expanded(
                child: Text(
                  'Balance distribution',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ink,
                  ),
                ),
              ),
              Text(
                'All wallets',
                style: TextStyle(fontSize: 13, color: AppColors.muted),
              ),
            ],
          ),
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: SizedBox(
              height: 10,
              child: Row(
                children: [
                  for (final w in wallets)
                    Expanded(
                      flex: total == 0
                          ? 1
                          : ((w.balanceIdr / total) * 1000).round().clamp(1, 1000).toInt(),
                      child: Container(color: w.color),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          if (top != null)
            Text(
              '${top.name} currently holds the largest share of your available balance at $topShare%.',
              style: const TextStyle(
                fontSize: 13,
                height: 1.5,
                color: AppColors.muted,
              ),
            )
          else
            const Text(
              'No wallet balance available.',
              style: TextStyle(
                fontSize: 13,
                height: 1.5,
                color: AppColors.muted,
              ),
            ),
        ],
      ),
    );
  }
}
