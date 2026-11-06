import 'package:flutter/material.dart';
import '../data/dummy_data.dart';
import '../models/wallet.dart';
import '../theme/app_colors.dart';
import '../widgets/app_button.dart';
import '../widgets/app_card.dart';
import '../widgets/balance_distribution.dart';
import '../widgets/app_header.dart';
import '../widgets/wallet_card.dart';
import '../widgets/wallet_form_sheet.dart';
import '../models/wallet_transaction.dart';
import 'wallet_history_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late final List<Wallet> _wallets = List<Wallet>.of(DummyData.wallets);

  /// Nama dompet utama. Sementara dicocokkan dengan name,
  /// nanti sebaiknya diganti id.
  String? _primaryName;
  String _lastUpdatedText = 'Updated just now';

  Future<void> _handleRefresh() async {
    await Future.delayed(const Duration(milliseconds: 700));
    if (!mounted) return;
    setState(() {
      _lastUpdatedText = 'Updated just now';
    });
  }

  @override
  void initState() {
    super.initState();
    for (final w in _wallets) {
      if (w.isPrimary) {
        _primaryName = w.name;
        break;
      }
    }
  }

  void _setPrimary(Wallet wallet) {
    setState(() => _primaryName = wallet.name);
  }

  Future<void> _openForm({Wallet? wallet}) async {
    final result = await showModalBottomSheet<WalletFormResult>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => WalletFormSheet(
        wallet: wallet,
        onDelete: wallet == null ? null : () => _confirmDelete(wallet),
      ),
    );

    if (result == null || !mounted) return;

    setState(() {
      if (wallet == null) {
        _wallets.add(_buildWallet(result));
      } else {
        final index = _wallets.indexOf(wallet);
        if (index != -1) {
          _wallets[index] = _buildWallet(result, isPrimary: wallet.isPrimary);
        }
        // Jika dompet utama diganti namanya, nama baru ikut ditandai utama
        if (_primaryName == wallet.name) {
          _primaryName = result.name;
        }
      }
    });
  }

  Wallet _buildWallet(WalletFormResult r, {bool isPrimary = false}) {
    return Wallet(
      name: r.name,
      type: r.type,
      balanceIdr: r.balance,
      color: r.color,
      background: r.color.withValues(alpha: 0.12),
      icon: _iconFor(r.type),
      isPrimary: isPrimary,
    );
  }

  /// Ikon mengikuti jenis dompet, sama seperti di web.
  IconData _iconFor(String type) {
    switch (type) {
      case 'Physical wallet':
        return Icons.payments_outlined;
      case 'Bank account':
        return Icons.account_balance_outlined;
      default:
        return Icons.smartphone_outlined;
    }
  }

  Future<void> _confirmDelete(Wallet wallet) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => _DeleteWalletDialog(
        walletName: wallet.name,
      ),
    );

    if (ok == true) {
      setState(() {
        if (_primaryName == wallet.name) _primaryName = null;
        _wallets.remove(wallet);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final totalIdr = _wallets.fold<double>(0, (sum, w) => sum + w.balanceIdr);

    return Scaffold(
      backgroundColor: AppColors.page,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const AppHeader(),
          Expanded(
            child: RefreshIndicator(
              color: AppColors.brand,
              backgroundColor: Colors.white,
              strokeWidth: 3.2,
              displacement: 40.0,
              onRefresh: _handleRefresh,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
                children: [
                  const Text(
                    'Dashboard',
              style: TextStyle(
                fontSize: 26,
                fontWeight: FontWeight.w800,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Overall summary of your finances and assets.',
              style: TextStyle(fontSize: 14, color: AppColors.muted),
            ),
            const SizedBox(height: 16),
            AppButton(
              label: 'Add Wallet',
              icon: Icons.add_rounded,
              onPressed: () => _openForm(),
            ),
            const SizedBox(height: 16),
            _totalCard(_wallets.length, totalIdr),
            const SizedBox(height: 20),
            const Text(
              'Your wallets',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              height: 300,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: _wallets.length,
                separatorBuilder: (_, _) => const SizedBox(width: 12),
                itemBuilder: (context, i) {
                  final wallet = _wallets[i];
                  return WalletCard(
                    wallet: wallet,
                    balance: DummyData.money(wallet.balanceIdr),
                    isPrimary: wallet.name == _primaryName,
                    onEdit: () => _openForm(wallet: wallet),
                    onDelete: () => _confirmDelete(wallet),
                    onSetPrimary: () => _setPrimary(wallet),
                    onHistory: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => WalletHistoryScreen(
                          wallet: wallet,
                          transactions: dummyTransactionsFor(wallet.name),
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
            const SizedBox(height: 20),
            BalanceDistribution(wallets: _wallets),
          ],
        ),
      ),
    ),
  ],
),
    );
  }

  Widget _totalCard(int activeWallets, double totalIdr) {
    final income = DummyData.money(DummyData.monthlyIncomeIdr);
    final expense = DummyData.money(DummyData.monthlyExpenseIdr);

    return AppCard(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.brandSoft,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.account_balance_wallet_outlined,
                  color: AppColors.brand,
                  size: 22,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Total balance',
                      style: TextStyle(fontSize: 14, color: AppColors.muted),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _lastUpdatedText,
                      style: const TextStyle(fontSize: 12, color: AppColors.muted),
                    ),
                  ],
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: AppColors.brandSoft,
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  '$activeWallets active wallets',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: AppColors.brand,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              DummyData.money(totalIdr).text,
              style: const TextStyle(
                fontSize: 30,
                fontWeight: FontWeight.w800,
                color: AppColors.ink,
              ),
            ),
          ),
          const SizedBox(height: 14),
          _flowRow(
            Icons.arrow_upward_rounded,
            AppColors.income,
            income.text,
            'this month',
          ),
          const SizedBox(height: 6),
          _flowRow(
            Icons.arrow_downward_rounded,
            AppColors.expense,
            expense.text,
            'this month',
          ),
        ],
      ),
    );
  }

  Widget _flowRow(IconData icon, Color color, String amount, String label) {
    return Row(
      children: [
        Icon(icon, size: 18, color: color),
        const SizedBox(width: 6),
        Text(
          amount,
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w700,
            color: color,
          ),
        ),
        const SizedBox(width: 6),
        Text(
          label,
          style: const TextStyle(fontSize: 13, color: AppColors.muted),
        ),
      ],
    );
  }
}

// ─── Delete Wallet Confirmation Dialog (Style Image 2) ────────────────────────
class _DeleteWalletDialog extends StatelessWidget {
  const _DeleteWalletDialog({
    required this.walletName,
  });

  final String walletName;

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.transparent,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(22, 22, 22, 18),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: AppColors.expenseSoft,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.delete_outline_rounded,
                    color: AppColors.expense,
                    size: 24,
                  ),
                ),
                const Spacer(),
                GestureDetector(
                  onTap: () => Navigator.of(context).pop(false),
                  child: Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      color: const Color(0xFFF3F4F6),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(
                      Icons.close_rounded,
                      size: 18,
                      color: AppColors.ink,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            const Text(
              'Delete Wallet?',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 10),
            RichText(
              text: TextSpan(
                style: const TextStyle(
                  fontSize: 14,
                  color: AppColors.muted,
                  height: 1.5,
                ),
                children: [
                  const TextSpan(text: 'Are you sure you want to delete the '),
                  TextSpan(
                    text: walletName,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: AppColors.ink,
                    ),
                  ),
                  const TextSpan(
                    text: ' wallet? This action cannot be undone.',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 22),
            SizedBox(
              height: 46,
              child: ElevatedButton(
                onPressed: () => Navigator.of(context).pop(true),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.expense,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text(
                  'Delete',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              height: 46,
              child: OutlinedButton(
                onPressed: () => Navigator.of(context).pop(false),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.ink,
                  side: const BorderSide(color: Color(0xFFE5E7EB)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  elevation: 0,
                ),
                child: const Text(
                  'Cancel',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
