import 'package:flutter/material.dart';
import '../data/dummy_data.dart';
import '../data/transaction_samples.dart';
import '../models/category_item.dart';
import '../models/transaction_item.dart';
import '../models/wallet.dart';
import '../services/notification_service.dart';
import '../theme/app_colors.dart';
import '../utils/amount_format.dart';
import '../utils/currency.dart';
import '../utils/feedback.dart';
import '../widgets/app_card.dart';
import '../widgets/app_header.dart';
import '../widgets/transaction_type_toggle.dart';
import 'manage_categories_screen.dart';

class AddTransactionScreen extends StatefulWidget {
  const AddTransactionScreen({super.key});

  @override
  State<AddTransactionScreen> createState() => _AddTransactionScreenState();
}

class _AddTransactionScreenState extends State<AddTransactionScreen> {
  bool _isIncome = true;
  double _amount = 0;
  final _amountCtrl = TextEditingController();
  final _nameCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();

  CategoryItem? _selectedCategory;
  late Wallet _selectedWallet;
  DateTime _selectedDate = DateTime.now();

  bool _isCategoryDropdownOpen = false;
  bool _isWalletDropdownOpen = false;

  @override
  void initState() {
    super.initState();
    // Default wallet: first wallet or Cash
    final wallets = DummyData.wallets;
    _selectedWallet = wallets.isNotEmpty
        ? wallets.firstWhere((w) => w.name == 'Cash', orElse: () => wallets.first)
        : const Wallet(
            name: 'Cash',
            type: 'Physical wallet',
            balanceIdr: 0,
            isPrimary: true,
            icon: Icons.payments_outlined,
            color: AppColors.income,
            background: AppColors.incomeSoft,
          );

    // Default category matching initial type
    _syncCategoryWithType();
  }

  void _syncCategoryWithType() {
    final available = sampleCategories.where((c) => c.isIncome == _isIncome).toList();
    if (available.isNotEmpty) {
      if (_selectedCategory == null || _selectedCategory!.isIncome != _isIncome) {
        _selectedCategory = available.first;
      }
    } else {
      _selectedCategory = null;
    }
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    _nameCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  void _onTypeChanged(bool isIncome) {
    setState(() {
      _isIncome = isIncome;
      _syncCategoryWithType();
      _isCategoryDropdownOpen = false;
      _isWalletDropdownOpen = false;
    });
  }

  void _updateAmount(String text) {
    setState(() {
      _amount = AmountFormat.parse(text);
    });
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime(2020),
      lastDate: DateTime(2035),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: ColorScheme.light(
              primary: AppColors.brand,
              onPrimary: Colors.white,
              surface: Colors.white,
              onSurface: AppColors.ink,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null) {
      setState(() => _selectedDate = picked);
    }
  }

  void _submit() {
    final name = _nameCtrl.text.trim();
    if (name.isEmpty) {
      showComingSoon(context, 'Please enter a transaction name.');
      return;
    }

    if (_amount <= 0) {
      showComingSoon(context, 'Please enter a valid amount.');
      return;
    }

    final categoryName = _selectedCategory?.name ?? (_isIncome ? 'Income' : 'Expense');
    final monthNames = [
      'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'
    ];
    final dateLabel = '${_selectedDate.day} ${monthNames[_selectedDate.month - 1]}';

    IconData icon = Icons.receipt_long_outlined;
    if (_selectedCategory?.iconKey != null && categoryIcons.containsKey(_selectedCategory!.iconKey!)) {
      icon = categoryIcons[_selectedCategory!.iconKey!]!.icon;
    }

    final newTx = TransactionItem(
      title: name,
      category: categoryName,
      dateLabel: dateLabel,
      amount: DummyData.money(_amount),
      type: _isIncome ? TransactionType.income : TransactionType.expense,
      icon: icon,
      iconColor: _isIncome ? AppColors.income : AppColors.expense,
      iconBackground: _isIncome ? AppColors.incomeSoft : AppColors.expenseSoft,
      walletName: _selectedWallet.name,
      value: _isIncome ? _amount : -_amount,
      date: _selectedDate,
    );

    sampleTransactions.insert(0, newTx);

    final currCode = preferredCurrency.value;
    final formattedAmt = formatCurrency(_amount, currCode);

    NotificationService.notifyTransaction(
      context: context,
      name: name,
      amountStr: formattedAmt,
      isIncome: _isIncome,
    );

    Navigator.of(context).pop();
  }

  String _formatDate(DateTime d) {
    final dd = d.day.toString().padLeft(2, '0');
    final mm = d.month.toString().padLeft(2, '0');
    final yyyy = d.year.toString();
    return '$dd/$mm/$yyyy';
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<String>(
      valueListenable: preferredCurrency,
      builder: (context, currCode, _) {
        final symbol = currCode == 'USD' ? r'$' : currCode == 'EUR' ? '€' : 'Rp ';
        final baseBalance = DummyData.money(_selectedWallet.balanceIdr).displayAmount;
        final newBalance = _isIncome ? baseBalance + _amount : baseBalance - _amount;

        final availableCategories = sampleCategories
            .where((c) => c.isIncome == _isIncome)
            .toList();

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
                  'Add Transaction',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Record income or expenses to keep your balance accurate.',
                  style: TextStyle(
                    fontSize: 14,
                    color: AppColors.muted,
                  ),
                ),
                const SizedBox(height: 18),

                // Form Card
                AppCard(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // 1. Transaction type
                        const Text(
                          'Transaction type',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                        ),
                        const SizedBox(height: 8),
                        TransactionTypeToggle(
                          isIncome: _isIncome,
                          onChanged: _onTypeChanged,
                        ),
                        const SizedBox(height: 16),

                        // 2. Amount
                        const Text(
                          'Amount',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Container(
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
                                  symbol.trim(),
                                  style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w600,
                                    color: AppColors.muted,
                                  ),
                                ),
                              ),
                              Expanded(
                                child: TextField(
                                  controller: _amountCtrl,
                                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                  inputFormatters: [AmountInputFormatter()],
                                  onChanged: _updateAmount,
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
                        ),
                        const SizedBox(height: 16),

                        // 3. Transaction name
                        const Text(
                          'Transaction name',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Container(
                          height: 48,
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFE5E7EB)),
                          ),
                          padding: const EdgeInsets.symmetric(horizontal: 14),
                          child: TextField(
                            controller: _nameCtrl,
                            style: const TextStyle(
                              fontSize: 15,
                              color: AppColors.ink,
                            ),
                            decoration: const InputDecoration(
                              hintText: 'Grocery shopping',
                              hintStyle: TextStyle(color: AppColors.muted),
                              border: InputBorder.none,
                              isDense: true,
                              contentPadding: EdgeInsets.symmetric(vertical: 13),
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),

                        // 4. Category with Manage link
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Category',
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w700,
                                color: AppColors.ink,
                              ),
                            ),
                            GestureDetector(
                              onTap: () async {
                                await Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => const ManageCategoriesScreen(),
                                  ),
                                );
                                setState(() {
                                  _syncCategoryWithType();
                                });
                              },
                              child: const Text(
                                'Manage',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.brand,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),

                        // Category Selector
                        GestureDetector(
                          behavior: HitTestBehavior.opaque,
                          onTap: () {
                            setState(() {
                              _isCategoryDropdownOpen = !_isCategoryDropdownOpen;
                              _isWalletDropdownOpen = false;
                            });
                          },
                          child: Container(
                            height: 48,
                            padding: const EdgeInsets.symmetric(horizontal: 14),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: _isCategoryDropdownOpen
                                    ? AppColors.brand
                                    : const Color(0xFFE5E7EB),
                                width: _isCategoryDropdownOpen ? 1.5 : 1,
                              ),
                            ),
                            child: Row(
                              children: [
                                if (_selectedCategory != null) ...[
                                  if (_selectedCategory!.emoji != null) ...[
                                    Text(
                                      _selectedCategory!.emoji!,
                                      style: const TextStyle(fontSize: 16),
                                    ),
                                    const SizedBox(width: 8),
                                  ] else if (_selectedCategory!.iconKey != null &&
                                      categoryIcons.containsKey(_selectedCategory!.iconKey!)) ...[
                                    Icon(
                                      categoryIcons[_selectedCategory!.iconKey!]!.icon,
                                      size: 18,
                                      color: AppColors.brand,
                                    ),
                                    const SizedBox(width: 8),
                                  ],
                                  Text(
                                    _selectedCategory!.name,
                                    style: const TextStyle(
                                      fontSize: 15,
                                      fontWeight: FontWeight.w600,
                                      color: AppColors.ink,
                                    ),
                                  ),
                                ] else ...[
                                  const Text(
                                    'Select category',
                                    style: TextStyle(
                                      fontSize: 15,
                                      color: AppColors.muted,
                                    ),
                                  ),
                                ],
                                const Spacer(),
                                Icon(
                                  _isCategoryDropdownOpen
                                      ? Icons.keyboard_arrow_up_rounded
                                      : Icons.keyboard_arrow_down_rounded,
                                  color: AppColors.muted,
                                ),
                              ],
                            ),
                          ),
                        ),

                        // Category Dropdown List
                        if (_isCategoryDropdownOpen) ...[
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
                            child: availableCategories.isEmpty
                                ? const Padding(
                                    padding: EdgeInsets.all(14),
                                    child: Text(
                                      'No categories found for this type.',
                                      style: TextStyle(fontSize: 13, color: AppColors.muted),
                                    ),
                                  )
                                : Column(
                                    children: availableCategories.map((c) {
                                      final isSelected = _selectedCategory?.id == c.id;
                                      return InkWell(
                                        onTap: () {
                                          setState(() {
                                            _selectedCategory = c;
                                            _isCategoryDropdownOpen = false;
                                          });
                                        },
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
                                              if (c.emoji != null) ...[
                                                Text(c.emoji!,
                                                    style: const TextStyle(fontSize: 16)),
                                                const SizedBox(width: 8),
                                              ] else if (c.iconKey != null &&
                                                  categoryIcons.containsKey(c.iconKey!)) ...[
                                                Icon(
                                                  categoryIcons[c.iconKey!]!.icon,
                                                  size: 18,
                                                  color: isSelected
                                                      ? AppColors.brand
                                                      : AppColors.muted,
                                                ),
                                                const SizedBox(width: 8),
                                              ],
                                              Text(
                                                c.name,
                                                style: TextStyle(
                                                  fontSize: 14,
                                                  fontWeight: isSelected
                                                      ? FontWeight.w700
                                                      : FontWeight.w500,
                                                  color: isSelected
                                                      ? AppColors.brand
                                                      : AppColors.ink,
                                                ),
                                              ),
                                              const Spacer(),
                                              if (isSelected)
                                                const Icon(
                                                  Icons.check_rounded,
                                                  size: 18,
                                                  color: AppColors.brand,
                                                ),
                                            ],
                                          ),
                                        ),
                                      );
                                    }).toList(),
                                  ),
                          ),
                        ],
                        const SizedBox(height: 16),

                        // 5. Wallet
                        const Text(
                          'Wallet',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                        ),
                        const SizedBox(height: 8),

                        // Wallet Selector
                        GestureDetector(
                          behavior: HitTestBehavior.opaque,
                          onTap: () {
                            setState(() {
                              _isWalletDropdownOpen = !_isWalletDropdownOpen;
                              _isCategoryDropdownOpen = false;
                            });
                          },
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
                                Container(
                                  padding: const EdgeInsets.all(4),
                                  decoration: BoxDecoration(
                                    color: _selectedWallet.background,
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Icon(
                                    _selectedWallet.icon,
                                    size: 16,
                                    color: _selectedWallet.color,
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Text(
                                  _selectedWallet.name,
                                  style: const TextStyle(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w600,
                                    color: AppColors.ink,
                                  ),
                                ),
                                const Spacer(),
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

                        // Wallet Dropdown List
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
                              children: DummyData.wallets.map((w) {
                                final isSelected = _selectedWallet.name == w.name;
                                final b = DummyData.money(w.balanceIdr).displayAmount;
                                final formattedBal = formatCurrency(b, currCode);
                                return InkWell(
                                  onTap: () {
                                    setState(() {
                                      _selectedWallet = w;
                                      _isWalletDropdownOpen = false;
                                    });
                                  },
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
                                        Container(
                                          padding: const EdgeInsets.all(4),
                                          decoration: BoxDecoration(
                                            color: w.background,
                                            borderRadius: BorderRadius.circular(6),
                                          ),
                                          child: Icon(
                                            w.icon,
                                            size: 16,
                                            color: w.color,
                                          ),
                                        ),
                                        const SizedBox(width: 10),
                                        Text(
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
                                        ),
                                        const Spacer(),
                                        Text(
                                          formattedBal,
                                          style: TextStyle(
                                            fontSize: 13,
                                            fontWeight: isSelected
                                                ? FontWeight.w700
                                                : FontWeight.w500,
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

                        // 6. Date
                        const Text(
                          'Date',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                        ),
                        const SizedBox(height: 8),
                        GestureDetector(
                          behavior: HitTestBehavior.opaque,
                          onTap: _pickDate,
                          child: Container(
                            height: 48,
                            padding: const EdgeInsets.symmetric(horizontal: 14),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: const Color(0xFFE5E7EB)),
                            ),
                            child: Row(
                              children: [
                                Text(
                                  _formatDate(_selectedDate),
                                  style: const TextStyle(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w600,
                                    color: AppColors.ink,
                                  ),
                                ),
                                const Spacer(),
                                const Icon(
                                  Icons.calendar_today_outlined,
                                  size: 18,
                                  color: AppColors.muted,
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 24),

                        // Cancel & Submit Buttons
                        Row(
                          children: [
                            Expanded(
                              flex: 1,
                              child: SizedBox(
                                height: 46,
                                child: OutlinedButton(
                                  onPressed: () => Navigator.of(context).pop(),
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: AppColors.ink,
                                    side: const BorderSide(color: Color(0xFFE5E7EB)),
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
                                  onPressed: _submit,
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: AppColors.dark,
                                    foregroundColor: Colors.white,
                                    elevation: 0,
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                  ),
                                  child: Text(
                                    _isIncome ? 'Add Income' : 'Add Expense',
                                    style: const TextStyle(
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
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Transaction type',
                              style: TextStyle(
                                fontSize: 14,
                                color: AppColors.muted,
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: _isIncome
                                    ? AppColors.incomeSoft
                                    : AppColors.expenseSoft,
                                borderRadius: BorderRadius.circular(999),
                              ),
                              child: Text(
                                _isIncome ? 'Income' : 'Expense',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                  color: _isIncome
                                      ? AppColors.income
                                      : AppColors.expense,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Wallet',
                              style: TextStyle(
                                fontSize: 14,
                                color: AppColors.muted,
                              ),
                            ),
                            Text(
                              _selectedWallet.name,
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w700,
                                color: AppColors.ink,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'New balance',
                              style: TextStyle(
                                fontSize: 14,
                                color: AppColors.muted,
                              ),
                            ),
                            Text(
                              formatCurrency(newBalance, currCode),
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: AppColors.ink,
                              ),
                            ),
                          ],
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
}
