import 'package:flutter/material.dart';
import '../data/dummy_data.dart';
import '../models/wallet.dart';
import '../models/wallet_transaction.dart';
import '../services/wallet_service.dart';
import '../services/transaction_service.dart';
import '../theme/app_colors.dart';
import '../widgets/app_button.dart';
import '../widgets/app_card.dart';
import '../widgets/balance_distribution.dart';
import '../widgets/app_header.dart';
import '../widgets/wallet_card.dart';
import '../widgets/wallet_form_sheet.dart';
import 'wallet_history_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<Wallet> _wallets = [];
  bool _isLoading = true;
  String? _primaryName;
  String _lastUpdatedText = 'Updated just now';
  double _monthlyIncome = 0;
  double _monthlyExpense = 0;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    try {
      final walletRes = await WalletService.fetchWallets();
      if (!mounted) return;

      List<Wallet> loadedWallets = [];
      if (walletRes.success && walletRes.data != null) {
        dynamic rawList;
        if (walletRes.data is Map && walletRes.data['data'] != null) {
          rawList = walletRes.data['data'];
        } else if (walletRes.data is List) {
          rawList = walletRes.data;
        }

        if (rawList is List) {
          loadedWallets = rawList.map((item) {
            final map = Map<String, dynamic>.from(item as Map);
            return _walletFromJson(map);
          }).toList();
        }
      }

      // Fetch transactions for monthly summary
      double inc = 0;
      double exp = 0;
      final txRes = await TransactionService.fetchTransactions();
      if (txRes.success && txRes.data != null) {
        dynamic rawTxList;
        if (txRes.data is Map && txRes.data['data'] != null) {
          rawTxList = txRes.data['data'];
        } else if (txRes.data is List) {
          rawTxList = txRes.data;
        }

        if (rawTxList is List) {
          for (final t in rawTxList) {
            final amtRaw = t['amount'] ?? t['nominal'] ?? 0;
            final amt = (amtRaw is num)
                ? amtRaw.toDouble()
                : (double.tryParse(amtRaw.toString()) ?? 0.0);
            final type = (t['type'] ?? t['jenis'] ?? '').toString().toLowerCase();
            if (type == 'income') {
              inc += amt;
            } else if (type == 'expense') {
              exp += amt;
            }
          }
        }
      }

      if (mounted) {
        setState(() {
          _wallets = loadedWallets;
          _monthlyIncome = inc;
          _monthlyExpense = exp;
          _isLoading = false;
          _lastUpdatedText = 'Updated just now';

          for (final w in _wallets) {
            if (w.isPrimary) {
              _primaryName = w.name;
              break;
            }
          }
          if (_primaryName == null && _wallets.isNotEmpty) {
            _primaryName = _wallets.first.name;
          }
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Wallet _walletFromJson(Map<String, dynamic> json) {
    final id = json['id_dompet'] ?? json['id'];
    final name = json['nama_dompet'] ?? json['nama'] ?? json['name'] ?? 'Wallet';
    final type = json['kategori'] ?? json['type'] ?? 'Physical wallet';
    final saldoRaw = json['saldo'] ?? json['balance'] ?? 0;
    final saldo = (saldoRaw is num)
        ? saldoRaw.toDouble()
        : (double.tryParse(saldoRaw.toString()) ?? 0.0);
    final isPrimary = json['is_primary'] == true || json['is_primary'] == 1;

    final icon = _iconFor(type.toString());
    final color = _colorFor(type.toString());

    return Wallet(
      id: id,
      name: name.toString(),
      type: type.toString(),
      balanceIdr: saldo,
      isPrimary: isPrimary,
      icon: icon,
      color: color,
      background: color.withValues(alpha: 0.12),
    );
  }

  IconData _iconFor(String type) {
    switch (type.toLowerCase()) {
      case 'physical wallet':
      case 'cash':
        return Icons.payments_outlined;
      case 'bank account':
      case 'bank':
        return Icons.account_balance_outlined;
      default:
        return Icons.smartphone_outlined;
    }
  }

  Color _colorFor(String type) {
    switch (type.toLowerCase()) {
      case 'physical wallet':
      case 'cash':
        return AppColors.income;
      case 'bank account':
      case 'bank':
        return AppColors.brand;
      default:
        return AppColors.warn;
    }
  }

  Future<void> _handleRefresh() async {
    await _loadData();
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

    setState(() => _isLoading = true);

    if (wallet == null) {
      await WalletService.createWallet(
        name: result.name,
        category: result.type,
        initialBalance: result.balance,
      );
    } else {
      if (wallet.id != null) {
        await WalletService.manageWallet(
          wallet.id,
          name: result.name,
          category: result.type,
          balance: result.balance,
        );
      }
    }

    await _loadData();
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
              child: _isLoading
                  ? const Center(
                      child: CircularProgressIndicator(color: AppColors.brand),
                    )
                  : ListView(
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
                        if (_wallets.isEmpty)
                          AppCard(
                            padding: const EdgeInsets.symmetric(vertical: 28, horizontal: 16),
                            child: Center(
                              child: Column(
                                children: [
                                  Icon(Icons.account_balance_wallet_outlined,
                                      size: 40, color: AppColors.muted.withValues(alpha: 0.6)),
                                  const SizedBox(height: 8),
                                  const Text(
                                    'No wallets yet',
                                    style: TextStyle(
                                      fontSize: 15,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.ink,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  const Text(
                                    'Tap "Add Wallet" above to create your first wallet.',
                                    style: TextStyle(fontSize: 13, color: AppColors.muted),
                                    textAlign: TextAlign.center,
                                  ),
                                ],
                              ),
                            ),
                          )
                        else
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
                                        transactions: const <WalletTransaction>[],
                                      ),
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
                        if (_wallets.isNotEmpty) ...[
                          const SizedBox(height: 20),
                          BalanceDistribution(wallets: _wallets),
                        ],
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _totalCard(int activeWallets, double totalIdr) {
    final income = DummyData.money(_monthlyIncome);
    final expense = DummyData.money(_monthlyExpense);

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
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
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

// ─── Delete Wallet Confirmation Dialog ────────────────────────
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
