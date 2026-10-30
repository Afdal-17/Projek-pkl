import 'package:flutter/material.dart';
import '../data/dummy_data.dart';
import '../models/wallet.dart';
import '../theme/app_colors.dart';

/// Membuka modal bottom sheet untuk memilih dompet milik pengguna yang sedang aktif.
/// Mengembalikan objek [Wallet] yang dipilih, atau `null` jika dibatalkan.
Future<Wallet?> showWalletPickerSheet({
  required BuildContext context,
  List<Wallet>? wallets,
  Wallet? selectedWallet,
  String title = 'Select Wallet',
  String subtitle = 'Choose a wallet from your personal account',
  bool showBalance = true,
}) {
  final userWallets = wallets ?? DummyData.wallets;

  return showModalBottomSheet<Wallet>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (ctx) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Drag handle bar
            Center(
              child: Container(
                width: 38,
                height: 4,
                margin: const EdgeInsets.only(bottom: 16),
                decoration: BoxDecoration(
                  color: const Color(0xFFD1D5DB),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),

            // Header title & close button
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: AppColors.ink,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          fontSize: 13,
                          color: AppColors.muted,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.of(ctx).pop(),
                  icon: const Icon(Icons.close_rounded, color: AppColors.muted),
                  visualDensity: VisualDensity.compact,
                ),
              ],
            ),
            const SizedBox(height: 16),

            // List dompet pengguna (hanya dompet milik user)
            ConstrainedBox(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.of(context).size.height * 0.45,
              ),
              child: ListView.separated(
                shrinkWrap: true,
                itemCount: userWallets.length,
                separatorBuilder: (context, index) => const SizedBox(height: 8),
                itemBuilder: (ctx, index) {
                  final wallet = userWallets[index];
                  final isSelected = selectedWallet?.name == wallet.name;

                  return Material(
                    color: isSelected
                        ? AppColors.brandSoft
                        : const Color(0xFFF9FAFB),
                    borderRadius: BorderRadius.circular(14),
                    child: InkWell(
                      onTap: () => Navigator.of(ctx).pop(wallet),
                      borderRadius: BorderRadius.circular(14),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 12,
                        ),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: isSelected
                                ? AppColors.brand
                                : const Color(0xFFE5E7EB),
                            width: isSelected ? 1.5 : 1,
                          ),
                        ),
                        child: Row(
                          children: [
                            Container(
                              width: 40,
                              height: 40,
                              decoration: BoxDecoration(
                                color: wallet.background,
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Icon(
                                wallet.icon,
                                color: wallet.color,
                                size: 20,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    wallet.name,
                                    style: TextStyle(
                                      fontSize: 15,
                                      fontWeight: isSelected
                                          ? FontWeight.w700
                                          : FontWeight.w600,
                                      color: AppColors.ink,
                                    ),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    wallet.type,
                                    style: const TextStyle(
                                      fontSize: 12,
                                      color: AppColors.muted,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            if (showBalance) ...[
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    DummyData.money(wallet.balanceIdr).text,
                                    style: TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w700,
                                      color: isSelected
                                          ? AppColors.brand
                                          : AppColors.ink,
                                    ),
                                  ),
                                  if (wallet.isPrimary)
                                    const Text(
                                      'Primary',
                                      style: TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w600,
                                        color: AppColors.income,
                                      ),
                                    ),
                                ],
                              ),
                            ],
                            if (isSelected) ...[
                              const SizedBox(width: 8),
                              const Icon(
                                Icons.check_circle_rounded,
                                color: AppColors.brand,
                                size: 20,
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

/// Field selector dompet yang dapat diklik untuk membuka [showWalletPickerSheet].
class WalletSelectorField extends StatelessWidget {
  const WalletSelectorField({
    super.key,
    required this.selectedWallet,
    required this.onChanged,
    this.label = 'Wallet',
    this.wallets,
    this.showBalance = true,
  });

  final Wallet? selectedWallet;
  final ValueChanged<Wallet> onChanged;
  final String label;
  final List<Wallet>? wallets;
  final bool showBalance;

  @override
  Widget build(BuildContext context) {
    final wallet = selectedWallet;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (label.isNotEmpty) ...[
          Text(
            label,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: AppColors.ink,
            ),
          ),
          const SizedBox(height: 8),
        ],
        Material(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          child: InkWell(
            onTap: () async {
              final picked = await showWalletPickerSheet(
                context: context,
                wallets: wallets,
                selectedWallet: wallet,
                showBalance: showBalance,
              );
              if (picked != null) {
                onChanged(picked);
              }
            },
            borderRadius: BorderRadius.circular(12),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppColors.line),
              ),
              child: Row(
                children: [
                  if (wallet != null) ...[
                    Container(
                      width: 28,
                      height: 28,
                      decoration: BoxDecoration(
                        color: wallet.background,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Icon(
                        wallet.icon,
                        color: wallet.color,
                        size: 16,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Row(
                        children: [
                          Flexible(
                            child: Text(
                              wallet.name,
                              style: const TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w600,
                                color: AppColors.ink,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          if (showBalance) ...[
                            const SizedBox(width: 6),
                            Text(
                              '(${DummyData.money(wallet.balanceIdr).text})',
                              style: const TextStyle(
                                fontSize: 13,
                                color: AppColors.muted,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ] else ...[
                    const Expanded(
                      child: Text(
                        'Select wallet',
                        style: TextStyle(
                          fontSize: 15,
                          color: AppColors.muted,
                        ),
                      ),
                    ),
                  ],
                  const Icon(
                    Icons.keyboard_arrow_down_rounded,
                    color: AppColors.muted,
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

