import 'package:flutter/material.dart';
import '../models/money.dart';
import '../models/wallet.dart';
import '../theme/app_colors.dart';
import 'app_card.dart';

/// Kartu dompet untuk daftar horizontal di Beranda (versi mobile).
class WalletCard extends StatelessWidget {
  const WalletCard({
    super.key,
    required this.wallet,
    required this.balance,
    required this.isPrimary,
    this.onEdit,
    this.onDelete,
    this.onHistory,
    this.onSetPrimary,
  });

  final Wallet wallet;
  final Money balance;
  final bool isPrimary;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;
  final VoidCallback? onHistory;
  final VoidCallback? onSetPrimary;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 250,
      child: AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: wallet.background,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(wallet.icon, color: wallet.color, size: 22),
                ),
                const Spacer(),
                InkWell(
                  borderRadius: BorderRadius.circular(10),
                  onTap: onEdit,
                  child: Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      border: Border.all(color: AppColors.line),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(
                      Icons.more_horiz_rounded,
                      size: 20,
                      color: AppColors.ink,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Text(
              wallet.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              wallet.type,
              style: const TextStyle(fontSize: 13, color: AppColors.muted),
            ),
            const SizedBox(height: 14),
            const Text(
              'AVAILABLE BALANCE',
              style: TextStyle(
                fontSize: 11,
                letterSpacing: 0.6,
                color: AppColors.muted,
              ),
            ),
            SizedBox(
              width: double.infinity,
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(
                  balance.text,
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: onHistory,
              child: const Row(
                children: [
                  Icon(Icons.history_rounded, size: 16, color: AppColors.brand),
                  SizedBox(width: 6),
                  Text(
                    'View history',
                    style: TextStyle(
                      color: AppColors.brand,
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            const Divider(height: 1, color: AppColors.line),
            const SizedBox(height: 12),
            if (isPrimary)
              const _StatusPill(
                label: 'Primary recipient',
                icon: Icons.check_rounded,
                foreground: AppColors.brand,
                background: AppColors.brandSoft,
              )
            else
              _StatusPill(
                label: 'Set as recipient',
                icon: Icons.swap_horiz_rounded,
                foreground: AppColors.ink,
                background: Colors.white,
                border: Border.all(color: AppColors.line),
                onTap: onSetPrimary,
              ),
          ],
        ),
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({
    required this.label,
    required this.icon,
    required this.foreground,
    required this.background,
    this.border,
    this.onTap,
  });

  final String label;
  final IconData icon;
  final Color foreground;
  final Color background;
  final BoxBorder? border;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final pill = Container(
      height: 32,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
        border: border,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: foreground),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: foreground,
              ),
            ),
          ),
        ],
      ),
    );

    return Align(
      alignment: Alignment.centerLeft,
      child: onTap == null
          ? pill
          : Material(
              color: Colors.transparent,
              child: InkWell(
                borderRadius: BorderRadius.circular(999),
                onTap: onTap,
                child: pill,
              ),
            ),
    );
  }
}