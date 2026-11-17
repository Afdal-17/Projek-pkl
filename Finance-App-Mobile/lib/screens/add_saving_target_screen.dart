import 'package:flutter/material.dart';
import '../data/dummy_data.dart';
import '../models/saving_goal.dart';
import '../models/wallet.dart';
import '../services/notification_service.dart';
import '../services/saving_service.dart';
import '../services/wallet_service.dart';
import '../services/auth_service.dart';
import '../theme/app_colors.dart';
import '../utils/amount_format.dart';
import '../utils/currency.dart';
import '../utils/feedback.dart';
import '../utils/refresh_notifier.dart';
import '../widgets/app_card.dart';
import '../widgets/app_header.dart';

class AddSavingTargetScreen extends StatefulWidget {
  const AddSavingTargetScreen({super.key});

  @override
  State<AddSavingTargetScreen> createState() => _AddSavingTargetScreenState();
}

class _AddSavingTargetScreenState extends State<AddSavingTargetScreen> {
  final _nameCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  final _targetCtrl = TextEditingController();
  final _initialCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();

  List<Wallet> _wallets = [];
  bool _isLoadingWallets = true;
  bool _isSubmitting = false;

  double _targetAmount = 0;
  double _initialAmount = 0;
  bool _isWalletDropdownOpen = false;
  Wallet? _selectedWallet;

  @override
  void initState() {
    super.initState();
    _loadWallets();
  }

  Future<void> _loadWallets() async {
    try {
      final res = await WalletService.fetchWallets();
      List<Wallet> loaded = [];
      if (res.success && res.data != null) {
        dynamic rawW;
        if (res.data is Map && res.data['data'] != null) {
          rawW = res.data['data'];
        } else if (res.data is List) {
          rawW = res.data;
        }
        if (rawW is List) {
          loaded = rawW.map((item) {
            final map = Map<String, dynamic>.from(item as Map);
            final id = map['id_dompet'] ?? map['id'];
            final name = map['nama_dompet'] ?? map['nama'] ?? map['name'] ?? 'Wallet';
            final type = map['kategori'] ?? map['type'] ?? 'Physical wallet';
            final saldoRaw = map['saldo'] ?? map['balance'] ?? 0;
            final saldo = (saldoRaw is num)
                ? saldoRaw.toDouble()
                : (double.tryParse(saldoRaw.toString()) ?? 0.0);
            final isPrimary = map['is_primary'] == true || map['is_primary'] == 1;

            IconData icon = Icons.payments_outlined;
            Color color = AppColors.income;
            if (type.toString().toLowerCase().contains('bank')) {
              icon = Icons.account_balance_outlined;
              color = AppColors.brand;
            }

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
          }).toList();
        }
      }

      if (mounted) {
        setState(() {
          _wallets = loaded;
          _selectedWallet = _wallets.isNotEmpty ? _wallets.first : null;
          _isLoadingWallets = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _isLoadingWallets = false);
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _descCtrl.dispose();
    _targetCtrl.dispose();
    _initialCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_isSubmitting) return;

    final name = _nameCtrl.text.trim();
    if (name.isEmpty || _targetAmount <= 0) {
      showAppPopup(
        context,
        'Please enter a target name and a valid target amount.',
        type: PopupType.warning,
      );
      return;
    }

    if (_selectedWallet == null) {
      showAppPopup(
        context,
        'Please select a wallet to continue.',
        type: PopupType.warning,
      );
      return;
    }

    setState(() => _isSubmitting = true);

    final curr = preferredCurrency.value;
    await AuthService.syncCurrencyToBackend(curr);

    // Backend TargetTabunganController sudah otomatis memanggil CurrencyService::toBase()
    // berdasarkan preferensi mata uang user yang sudah disinkronkan di atas.
    final cleanTarget = double.parse(_targetAmount.toStringAsFixed(2));
    final cleanInitial = double.parse(_initialAmount.clamp(0, _targetAmount).toStringAsFixed(2));

    final res = await SavingService.createSaving(
      walletId: _selectedWallet!.id ?? 1,
      name: name,
      targetAmount: cleanTarget,
      initialAmount: cleanInitial,
      description: _descCtrl.text.trim(),
    );

    if (!mounted) return;
    setState(() => _isSubmitting = false);

    if (res.success) {
      final newGoal = SavingGoal(
        name: name,
        description: _descCtrl.text.trim(),
        walletName: _selectedWallet!.name,
        savedIdr: cleanInitial,
        targetIdr: cleanTarget,
      );

      NotificationService.notifySavingGoal(
        context: context,
        name: name,
        message: 'Savings goal "$name" has been created.',
        goal: newGoal,
      );

      AppRefreshNotifier.notifyDataChanged();
      Navigator.of(context).pop(true);
    } else {
      showAppPopup(
        context,
        res.message ?? 'Failed to create savings goal. Please try again.',
        type: PopupType.error,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoadingWallets) {
      return const Scaffold(
        backgroundColor: AppColors.page,
        appBar: AppHeader(showBackButton: true),
        body: Center(child: CircularProgressIndicator(color: AppColors.brand)),
      );
    }

    return ValueListenableBuilder<String>(
      valueListenable: preferredCurrency,
      builder: (context, currCode, _) {
        final symbol = currCode == 'USD' ? r'$' : currCode == 'EUR' ? '€' : 'Rp ';
        final walletBalance = _selectedWallet != null
            ? DummyData.money(_selectedWallet!.balanceIdr).displayAmount
            : 0.0;
        final walletBalanceStr = formatCurrency(walletBalance, currCode);

        return Scaffold(
          backgroundColor: AppColors.page,
          appBar: const AppHeader(showBackButton: true),
          body: SingleChildScrollView(
            controller: _scrollCtrl,
            physics: const ClampingScrollPhysics(),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Add Saving Target',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Set targets and follow your progress over time.',
                  style: TextStyle(fontSize: 14, color: AppColors.muted),
                ),
                const SizedBox(height: 18),

                AppCard(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // Target Name
                        const Text(
                          'Target Name',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                        ),
                        const SizedBox(height: 8),
                        _inputField(
                          controller: _nameCtrl,
                          hint: 'e.g. New Laptop',
                        ),
                        const SizedBox(height: 16),

                        // Description
                        const Text(
                          'Description',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Container(
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFE5E7EB)),
                          ),
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                          child: TextField(
                            controller: _descCtrl,
                            maxLines: 3,
                            minLines: 3,
                            style: const TextStyle(fontSize: 15, color: AppColors.ink),
                            decoration: const InputDecoration(
                              hintText: 'e.g. Work equipment',
                              hintStyle: TextStyle(color: AppColors.muted),
                              border: InputBorder.none,
                              isDense: true,
                              contentPadding: EdgeInsets.zero,
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),

                        // Wallet
                        const Text(
                          'Wallet',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                        ),
                        const SizedBox(height: 8),
                        GestureDetector(
                          behavior: HitTestBehavior.opaque,
                          onTap: () => setState(() {
                            _isWalletDropdownOpen = !_isWalletDropdownOpen;
                          }),
                          child: Container(
                            height: 48,
                            padding: const EdgeInsets.symmetric(horizontal: 14),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: _isWalletDropdownOpen
                                    ? AppColors.brand
                                    : const Color(0xFFE5E7EB),
                                width: _isWalletDropdownOpen ? 1.5 : 1,
                              ),
                            ),
                            child: Row(
                              children: [
                                if (_selectedWallet != null) ...[
                                  Icon(
                                    _selectedWallet!.icon,
                                    size: 18,
                                    color: _selectedWallet!.color,
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Text(
                                      '${_selectedWallet!.name}  ($walletBalanceStr)',
                                      style: const TextStyle(
                                        fontSize: 15,
                                        fontWeight: FontWeight.w600,
                                        color: AppColors.ink,
                                      ),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ] else ...[
                                  const Text('Select wallet', style: TextStyle(color: AppColors.muted)),
                                ],
                                const SizedBox(width: 6),
                                Icon(
                                  _isWalletDropdownOpen
                                      ? Icons.keyboard_arrow_up_rounded
                                      : Icons.keyboard_arrow_down_rounded,
                                  color: AppColors.muted,
                                ),
                              ],
                            ),
                          ),
                        ),
                        if (_isWalletDropdownOpen) ...[
                          const SizedBox(height: 6),
                          Container(
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: const Color(0xFFE5E7EB)),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.06),
                                  blurRadius: 10,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
                            child: Column(
                              children: _wallets.isEmpty
                                  ? [
                                      const Padding(
                                        padding: EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                                        child: Text(
                                          'No wallets available. Please create a wallet first.',
                                          style: TextStyle(fontSize: 13, color: AppColors.muted),
                                        ),
                                      ),
                                    ]
                                  : _wallets.map((w) {
                                final isSelected = _selectedWallet?.name == w.name;
                                final bal = DummyData.money(w.balanceIdr).displayAmount;
                                final balStr = formatCurrency(bal, currCode);
                                return InkWell(
                                  onTap: () => setState(() {
                                    _selectedWallet = w;
                                    _isWalletDropdownOpen = false;
                                  }),
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 14, vertical: 12),
                                    decoration: BoxDecoration(
                                      color: isSelected
                                          ? AppColors.brandSoft
                                          : Colors.transparent,
                                    ),
                                    child: Row(
                                      children: [
                                        Icon(w.icon,
                                            size: 18,
                                            color: isSelected
                                                ? AppColors.brand
                                                : w.color),
                                        const SizedBox(width: 10),
                                        Expanded(
                                          child: Text(
                                            w.name,
                                            style: TextStyle(
                                              fontSize: 14,
                                              fontWeight: isSelected
                                                ? FontWeight.w700
                                                : FontWeight.w500,
                                              color: isSelected
                                                  ? AppColors.brand
                                                  : AppColors.ink,
                                            ),
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ),
                                        const SizedBox(width: 8),
                                        Text(
                                          balStr,
                                          style: TextStyle(
                                            fontSize: 13,
                                            color: isSelected
                                                ? AppColors.brand
                                                : AppColors.muted,
                                          ),
                                        ),
                                        if (isSelected) ...[
                                          const SizedBox(width: 8),
                                          const Icon(
                                            Icons.check_rounded,
                                            size: 18,
                                            color: AppColors.brand,
                                          ),
                                        ],
                                      ],
                                    ),
                                  ),
                                );
                              }).toList(),
                            ),
                          ),
                        ],
                        const SizedBox(height: 16),

                        // Saving Target
                        const Text(
                          'Saving Target',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                        ),
                        const SizedBox(height: 8),
                        _amountField(
                          controller: _targetCtrl,
                          symbol: symbol.trim(),
                          onChanged: (v) =>
                              setState(() => _targetAmount = AmountFormat.parse(v)),
                        ),
                        const SizedBox(height: 16),

                        // Initial Savings (Optional)
                        SizedBox(
                          width: double.infinity,
                          child: Wrap(
                            alignment: WrapAlignment.spaceBetween,
                            crossAxisAlignment: WrapCrossAlignment.center,
                            spacing: 8,
                            runSpacing: 4,
                            children: [
                              const Text(
                                'Initial Savings (Optional)',
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.ink,
                                ),
                              ),
                              Text(
                                'Wallet balance: $walletBalanceStr',
                                style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                  color: AppColors.brand,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 8),
                        _amountField(
                          controller: _initialCtrl,
                          symbol: symbol.trim(),
                          onChanged: (v) =>
                              setState(() => _initialAmount = AmountFormat.parse(v)),
                        ),
                        const SizedBox(height: 24),

                        // Buttons
                        Row(
                          children: [
                            Expanded(
                              child: SizedBox(
                                height: 46,
                                child: OutlinedButton(
                                  onPressed: () => Navigator.of(context).pop(),
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: AppColors.ink,
                                    side: const BorderSide(
                                        color: Color(0xFFE5E7EB)),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    backgroundColor: Colors.white,
                                    elevation: 0,
                                  ),
                                  child: const Text(
                                    'Cancel',
                                    style: TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              flex: 2,
                              child: SizedBox(
                                height: 46,
                                child: ElevatedButton(
                                  onPressed: _isSubmitting ? null : _submit,
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: AppColors.dark,
                                    foregroundColor: Colors.white,
                                    elevation: 0,
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                  ),
                                  child: _isSubmitting
                                      ? const SizedBox(
                                          width: 20,
                                          height: 20,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                            color: Colors.white,
                                          ),
                                        )
                                      : const Text(
                                          'Add Target',
                                          style: TextStyle(
                                            fontSize: 13,
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),

                const SizedBox(height: 18),

                // Summary Card
                AppCard(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Text(
                          'Summary',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            color: AppColors.ink,
                          ),
                        ),
                        const SizedBox(height: 16),
                        _summaryRow(
                          label: 'Target Name',
                          trailing: _nameCtrl.text.trim().isEmpty
                              ? Container(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 10, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: AppColors.expenseSoft,
                                    borderRadius: BorderRadius.circular(999),
                                  ),
                                  child: const Text(
                                    '-',
                                    style: TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.expense,
                                    ),
                                  ),
                                )
                              : Text(
                                  _nameCtrl.text.trim(),
                                  style: const TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.ink,
                                  ),
                                ),
                        ),
                        const SizedBox(height: 14),
                        _summaryRow(
                          label: 'Wallet',
                          trailing: Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 12, vertical: 4),
                            decoration: BoxDecoration(
                              color: AppColors.expenseSoft,
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              _selectedWallet?.name ?? '—',
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: AppColors.expense,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 14),
                        _summaryRow(
                          label: 'Saving Target',
                          trailing: Text(
                            formatCurrency(_targetAmount, currCode),
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              color: AppColors.ink,
                            ),
                          ),
                        ),
                        const SizedBox(height: 14),
                        _summaryRow(
                          label: 'Initial Savings (Optional)',
                          trailing: Text(
                            formatCurrency(_initialAmount, currCode),
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              color: AppColors.ink,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 32),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _inputField({
    required TextEditingController controller,
    required String hint,
  }) {
    return Container(
      height: 48,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 14),
      child: TextField(
        controller: controller,
        onChanged: (_) => setState(() {}),
        style: const TextStyle(fontSize: 15, color: AppColors.ink),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: const TextStyle(color: AppColors.muted),
          border: InputBorder.none,
          isDense: true,
          contentPadding: const EdgeInsets.symmetric(vertical: 13),
        ),
      ),
    );
  }

  Widget _amountField({
    required TextEditingController controller,
    required String symbol,
    required void Function(String) onChanged,
  }) {
    return Container(
      height: 48,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        children: [
          Padding(
            padding: const EdgeInsets.only(left: 14, right: 8),
            child: Text(
              symbol,
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: AppColors.muted,
              ),
            ),
          ),
          Expanded(
            child: TextField(
              controller: controller,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              inputFormatters: [AmountInputFormatter()],
              onChanged: onChanged,
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                color: AppColors.ink,
              ),
              decoration: const InputDecoration(
                hintText: '0',
                border: InputBorder.none,
                isDense: true,
                contentPadding: EdgeInsets.symmetric(vertical: 12),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _summaryRow({required String label, required Widget trailing}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Flexible(
          child: Text(
            label,
            style: const TextStyle(fontSize: 14, color: AppColors.muted),
          ),
        ),
        const SizedBox(width: 12),
        Flexible(
          child: Align(
            alignment: Alignment.centerRight,
            child: trailing,
          ),
        ),
      ],
    );
  }
}
