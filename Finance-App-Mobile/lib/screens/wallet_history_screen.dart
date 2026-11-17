import 'package:flutter/material.dart';
import '../data/dummy_data.dart';
import '../models/wallet.dart';
import '../models/wallet_transaction.dart';
import '../services/wallet_service.dart';
import '../theme/app_colors.dart';
import '../utils/app_translator.dart';
import '../utils/currency.dart';
import '../widgets/app_card.dart';

/// Riwayat saldo satu dompet, meniru halaman "Wallet balance history" di web.
class WalletHistoryScreen extends StatefulWidget {
  const WalletHistoryScreen({
    super.key,
    required this.wallet,
    this.transactions = const <WalletTransaction>[],
  });

  final Wallet wallet;
  final List<WalletTransaction> transactions;

  @override
  State<WalletHistoryScreen> createState() => _WalletHistoryScreenState();
}

class _WalletHistoryScreenState extends State<WalletHistoryScreen> {
  late List<WalletTransaction> _transactions;
  bool _isLoading = false;
  bool _isFetching = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _transactions = List<WalletTransaction>.from(widget.transactions);
    _loadHistory();
  }

  Future<void> _loadHistory() async {
    final walletId = widget.wallet.id;
    if (walletId == null) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _errorMessage = 'Wallet ID not found.';
        });
      }
      return;
    }

    if (_isFetching) return;
    _isFetching = true;

    if (_transactions.isEmpty) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final res = await WalletService.fetchWalletDetails(walletId);
      if (!mounted) return;

      if (res.success && res.data is Map && res.data['transactions'] is List) {
        final rawList = res.data['transactions'] as List;
        final list = <WalletTransaction>[];
        for (final item in rawList) {
          if (item is! Map) continue;
          final rawName = (item['name'] ?? 'Transaction').toString();
          final rawCat = (item['category'] ?? 'General').toString();
          final title = AppTranslator.translateTransactionName(rawName);
          final category = AppTranslator.translateCategory(rawCat);
          final rawDate = (item['date'] ?? '').toString();
          String date = rawDate;
          if (rawDate.isNotEmpty) {
            try {
              final d = DateTime.parse(rawDate);
              const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
              ];
              date = '${d.day} ${monthNames[d.month - 1]} ${d.year}';
            } catch (_) {}
          }
          final rawAmt = (item['amount'] is num)
              ? (item['amount'] as num).toDouble()
              : (double.tryParse(item['amount']?.toString() ?? '') ?? 0.0);
          final isIncome = item['jenis'] == 'pemasukan';
          final isTransfer = item['type'] == 'transfer';
          final amount = isIncome ? rawAmt : -rawAmt;

          IconData icon = Icons.receipt_long_outlined;
          if (isTransfer) {
            icon = Icons.swap_horiz_rounded;
          } else if (isIncome) {
            icon = Icons.account_balance_wallet_outlined;
          } else {
            icon = Icons.shopping_bag_outlined;
          }

          list.add(WalletTransaction(
            title: title,
            category: category,
            date: date,
            amount: amount,
            icon: icon,
            isTransfer: isTransfer,
          ));
        }

        setState(() {
          _transactions = list;
          _isLoading = false;
          _errorMessage = null;
        });
        return;
      } else if (!res.success) {
        if (mounted) {
          setState(() {
            if (_transactions.isEmpty) {
              _errorMessage = res.message ?? 'Unable to load wallet history. Please check your connection.';
            }
            _isLoading = false;
          });
        }
        return;
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          if (_transactions.isEmpty) {
            _errorMessage = 'Connection error. Please try again.';
          }
          _isLoading = false;
        });
      }
    } finally {
      _isFetching = false;
      if (mounted && _isLoading) {
        setState(() => _isLoading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<String>(
      valueListenable: preferredCurrency,
      builder: (context, _, _) {
        return Scaffold(
          backgroundColor: AppColors.page,
          body: SafeArea(
            child: RefreshIndicator(
              color: AppColors.brand,
              backgroundColor: Colors.white,
              onRefresh: _loadHistory,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
                physics: const AlwaysScrollableScrollPhysics(),
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
                    widget.wallet.name,
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
                  DummyData.money(widget.wallet.balanceIdr).text,
                  style: const TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                  ),
                ),
              ),
              const SizedBox(height: 20),
              if (_isLoading && _transactions.isEmpty)
                const Center(
                  child: Padding(
                    padding: EdgeInsets.symmetric(vertical: 24),
                    child: CircularProgressIndicator(color: AppColors.brand),
                  ),
                )
              else if (_errorMessage != null && _transactions.isEmpty)
                AppCard(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.cloud_off_rounded, size: 36, color: AppColors.muted),
                        const SizedBox(height: 8),
                        Text(
                          _errorMessage!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontSize: 14, color: AppColors.muted),
                        ),
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          onPressed: _loadHistory,
                          icon: const Icon(Icons.refresh_rounded, size: 16),
                          label: const Text('Retry'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: AppColors.brand,
                            side: const BorderSide(color: AppColors.brand),
                          ),
                        ),
                      ],
                    ),
                  ),
                )
              else if (_transactions.isEmpty)
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
                      for (int i = 0; i < _transactions.length; i++) ...[
                        _TransactionRow(item: _transactions[i]),
                        if (i < _transactions.length - 1)
                          const Divider(height: 1, color: AppColors.line),
                      ],
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  },
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