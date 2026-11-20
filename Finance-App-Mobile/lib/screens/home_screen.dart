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
import '../utils/feedback.dart';
import '../utils/currency.dart';
import '../utils/refresh_notifier.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<Wallet> _wallets = [];
  bool _isLoading = true;
  bool _isFetching = false;
  String? _primaryName;
  String _lastUpdatedText = 'Updated just now';
  double _monthlyIncome = 0;
  double _monthlyExpense = 0;

  @override
  void initState() {
    super.initState();
    AppRefreshNotifier.refreshTick.addListener(_onAutoRefresh);
    preferredCurrency.addListener(_onCurrencyChanged);
    _loadData();
  }

  void _onCurrencyChanged() {
    if (mounted) {
      setState(() {});
    }
  }

  void _onAutoRefresh() {
    if (mounted) {
      _loadData(isRefresh: true);
    }
  }

  @override
  void dispose() {
    AppRefreshNotifier.refreshTick.removeListener(_onAutoRefresh);
    preferredCurrency.removeListener(_onCurrencyChanged);
    super.dispose();
  }

  Future<void> _loadData({bool isRefresh = false}) async {
    if (_isFetching) return;
    _isFetching = true;

    if (!isRefresh && _wallets.isEmpty) {
      setState(() => _isLoading = true);
    }
    try {
      // Ambil kurs secara asynchronous tanpa memblokir pemuatan dompet & transaksi
      DummyData.fetchLiveRates().then((_) {
        if (mounted) setState(() {});
      });

      // Panggil API dompet dan transaksi secara paralel untuk mempercepat loading
      final results = await Future.wait([
        WalletService.fetchWallets(),
        TransactionService.fetchTransactions(perPage: 100),
      ]);
      if (!mounted) return;

      final walletRes = results[0];
      final txRes = results[1];

      List<Wallet>? newWallets;
      if (walletRes.success && walletRes.data != null) {
        dynamic rawList;
        if (walletRes.data is Map && walletRes.data['data'] != null) {
          rawList = walletRes.data['data'];
        } else if (walletRes.data is List) {
          rawList = walletRes.data;
        }

        if (rawList is List) {
          newWallets = rawList.map((item) {
            final map = Map<String, dynamic>.from(item as Map);
            return _walletFromJson(map);
          }).toList();
        }
      }

      // Pertahankan dompet yang sudah ada jika request refresh sementara gagal
      final activeWallets = newWallets ?? _wallets;

      // Hitung ringkasan transaksi bulanan
      double? newInc;
      double? newExp;

      if (txRes.success && txRes.data != null) {
        dynamic rawTxList;
        if (txRes.data is Map && txRes.data['data'] != null) {
          rawTxList = txRes.data['data'];
        } else if (txRes.data is List) {
          rawTxList = txRes.data;
        }

        if (rawTxList is List) {
          double inc = 0;
          double exp = 0;
          final seenTxIds = <dynamic>{};

          final now = DateTime.now();
          final currentYear = now.year;
          final currentMonth = now.month;

          final myWalletNames = activeWallets
              .map((w) => w.name.trim().toLowerCase())
              .toSet();

          for (final t in rawTxList) {
            if (t is! Map) continue;

            // 1. Hindari duplikasi perhitungan
            final txId = t['id'] ?? t['id_transaksi'];
            if (txId != null) {
              if (seenTxIds.contains(txId)) continue;
              seenTxIds.add(txId);
            }

            // 2. Pastikan hanya transaksi bulan dan tahun berjalan yang dihitung
            final dateStr = (t['date'] ?? t['tanggal'])?.toString();
            if (dateStr != null && dateStr.isNotEmpty) {
              final parsed = DateTime.tryParse(dateStr);
              if (parsed != null) {
                if (parsed.year != currentYear ||
                    parsed.month != currentMonth) {
                  continue; // Lewati transaksi bulan sebelumnya
                }
              }
            }

            final amtRaw = t['amount'] ?? t['nominal'] ?? 0;
            final amt = (amtRaw is num)
                ? amtRaw.toDouble().abs()
                : (double.tryParse(amtRaw.toString())?.abs() ?? 0.0);

            final type = (t['type'] ?? '').toString().toLowerCase();
            final jenis = (t['jenis'] ?? '').toString().toLowerCase();
            final category = (t['category'] ?? t['kategori'] ?? '')
                .toString()
                .toLowerCase();

            final isTransfer = type == 'transfer' || category == 'transfer';

            if (isTransfer) {
              final to = (t['to'] ?? '').toString().trim().toLowerCase();
              final wallet = (t['wallet'] ?? '')
                  .toString()
                  .trim()
                  .toLowerCase();

              final isToMyWallet = myWalletNames.contains(to);
              final isFromMyWallet = myWalletNames.contains(wallet);

              // Transfer antar dompet milik sendiri -> TIDAK dihitung sebagai pengeluaran maupun pemasukan
              if (isFromMyWallet && isToMyWallet) {
                continue;
              }

              // Transfer ke pengguna lain (pengeluaran):
              // Sumber adalah dompet user, tujuan bukan dompet user
              if (jenis == 'pengeluaran' || (!isToMyWallet && isFromMyWallet)) {
                exp += amt;
              } else if (jenis == 'pemasukan' ||
                  (isToMyWallet && !isFromMyWallet)) {
                // Transfer masuk dari pengguna lain (pemasukan)
                inc += amt;
              }
            } else {
              // Transaksi biasa (non-transfer)
              final isIncome = type == 'income' || jenis == 'pemasukan';
              final isExpense = type == 'expense' || jenis == 'pengeluaran';
              if (isIncome) {
                inc += amt;
              } else if (isExpense) {
                exp += amt;
              }
            }
          }

          newInc = inc;
          newExp = exp;
        }
      }

      if (mounted) {
        setState(() {
          if (newWallets != null) {
            _wallets = newWallets;
          }
          if (newInc != null) {
            _monthlyIncome = newInc;
          }
          if (newExp != null) {
            _monthlyExpense = newExp;
          }
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
    } finally {
      _isFetching = false;
    }
  }

  Wallet _walletFromJson(Map<String, dynamic> json) {
    final id = json['id_dompet'] ?? json['id'];
    final name =
        (json['nama_dompet'] ?? json['nama'] ?? json['name'] ?? 'Wallet')
            .toString();
    final rawJenis =
        (json['jenis'] ?? json['kategori'] ?? json['type'] ?? 'physical')
            .toString()
            .toLowerCase();

    String type = 'Physical wallet';
    if (rawJenis.contains('bank')) {
      type = 'Bank account';
    } else if (rawJenis.contains('digital') || rawJenis.contains('e-wallet')) {
      type = 'Digital wallet';
    }

    final saldoRaw = json['saldo'] ?? json['balance'] ?? 0;
    final saldo = (saldoRaw is num)
        ? saldoRaw.toDouble()
        : (double.tryParse(saldoRaw.toString()) ?? 0.0);

    final isPrimary =
        json['is_primary'] == true ||
        json['is_primary'] == 1 ||
        json['terima_transfer'] == true ||
        json['terima_transfer'] == 1;

    final icon = _iconFor(type);
    Color color = _colorFor(type);

    final warnaRaw = json['warna']?.toString();
    if (warnaRaw != null && warnaRaw.isNotEmpty) {
      if (warnaRaw.startsWith('#') &&
          (warnaRaw.length == 7 || warnaRaw.length == 9)) {
        try {
          color = Color(int.parse(warnaRaw.replaceFirst('#', '0xFF')));
        } catch (_) {}
      } else if (warnaRaw == 'brand') {
        color = AppColors.brand;
      } else if (warnaRaw == 'income') {
        color = AppColors.income;
      } else if (warnaRaw == 'warn') {
        color = AppColors.warn;
      } else if (warnaRaw == 'expense') {
        color = AppColors.expense;
      } else if (warnaRaw == 'dark') {
        color = AppColors.ink;
      }
    }

    final balance = DummyData.money(saldo, apiMoney: json['saldo_money']);

    return Wallet(
      id: id,
      name: name,
      type: type,
      balance: balance,
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
    await _loadData(isRefresh: true);
  }

  void _setPrimary(Wallet wallet) {
    setState(() => _primaryName = wallet.name);
  }

  String _hexFromColor(Color color) {
    final hex = color.toARGB32().toRadixString(16).padLeft(8, '0');
    return '#${hex.substring(2)}';
  }

  Future<void> _openForm({Wallet? wallet}) async {
    final mediaQuery = MediaQuery.of(context);
    final maxSheetHeight =
        (mediaQuery.size.height - mediaQuery.padding.top) * 0.84;

    final result = await showModalBottomSheet<WalletFormResult>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      constraints: BoxConstraints(maxHeight: maxSheetHeight),
      builder: (_) => WalletFormSheet(
        wallet: wallet,
        onDelete: wallet == null ? null : () => _confirmDelete(wallet),
      ),
    );

    if (result == null || !mounted) return;

    setState(() => _isLoading = true);

    if (wallet == null) {
      final res = await WalletService.createWallet(
        name: result.name,
        category: result.type,
        initialBalance: result.balance,
        color: _hexFromColor(result.color),
      );
      if (!res.success && mounted) {
        showAppPopup(
          context,
          res.message ?? 'Failed to add wallet to server.',
          type: PopupType.error,
        );
      }
    } else {
      if (wallet.id != null) {
        final res = await WalletService.manageWallet(
          wallet.id,
          name: result.name,
          category: result.type,
          balance: result.balance,
          color: _hexFromColor(result.color),
        );
        if (!res.success && mounted) {
          showAppPopup(
            context,
            res.message ?? 'Failed to update wallet.',
            type: PopupType.error,
          );
        }
      }
    }

    await _loadData();
    AppRefreshNotifier.notifyDataChanged();
  }

  Future<void> _confirmDelete(Wallet wallet) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => _DeleteWalletDialog(walletName: wallet.name),
    );

    if (ok == true) {
      setState(() {
        if (_primaryName == wallet.name) _primaryName = null;
        _wallets.remove(wallet);
      });
      if (wallet.id != null) {
        await WalletService.deleteWallet(wallet.id);
      }
      await _loadData();
      AppRefreshNotifier.notifyDataChanged();
    }
  }

  @override
  Widget build(BuildContext context) {
    final totalUsd = _wallets.fold<double>(0, (sum, w) => sum + w.balanceUsd);

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
              child: (_isLoading && _wallets.isEmpty)
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
                          style: TextStyle(
                            fontSize: 14,
                            color: AppColors.muted,
                          ),
                        ),
                        const SizedBox(height: 16),
                        AppButton(
                          label: 'Add Wallet',
                          icon: Icons.add_rounded,
                          onPressed: () => _openForm(),
                        ),
                        const SizedBox(height: 16),
                        _totalCard(_wallets.length, totalUsd),
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
                            padding: const EdgeInsets.symmetric(
                              vertical: 28,
                              horizontal: 16,
                            ),
                            child: Center(
                              child: Column(
                                children: [
                                  Icon(
                                    Icons.account_balance_wallet_outlined,
                                    size: 40,
                                    color: AppColors.muted.withValues(
                                      alpha: 0.6,
                                    ),
                                  ),
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
                                    style: TextStyle(
                                      fontSize: 13,
                                      color: AppColors.muted,
                                    ),
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
                              separatorBuilder: (_, _) =>
                                  const SizedBox(width: 12),
                              itemBuilder: (context, i) {
                                final wallet = _wallets[i];
                                return WalletCard(
                                  wallet: wallet,
                                  balance: wallet.balance,
                                  isPrimary: wallet.name == _primaryName,
                                  onEdit: () => _openForm(wallet: wallet),
                                  onDelete: () => _confirmDelete(wallet),
                                  onSetPrimary: () => _setPrimary(wallet),
                                  onHistory: () => Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) => WalletHistoryScreen(
                                        wallet: wallet,
                                        transactions:
                                            const <WalletTransaction>[],
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

  Widget _totalCard(int activeWallets, double totalUsd) {
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
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.muted,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 5,
                ),
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
              DummyData.money(totalUsd).text,
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
  const _DeleteWalletDialog({required this.walletName});

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
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
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
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
