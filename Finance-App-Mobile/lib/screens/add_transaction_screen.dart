import 'package:flutter/material.dart';
import '../data/dummy_data.dart';
import '../models/category_item.dart';
import '../models/wallet.dart';
import '../services/category_service.dart';
import '../services/notification_service.dart';
import '../services/transaction_service.dart';
import '../services/wallet_service.dart';
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

  List<Wallet> _wallets = [];
  List<CategoryItem> _categories = [];
  bool _isLoading = true;
  bool _isSubmitting = false;

  CategoryItem? _selectedCategory;
  Wallet? _selectedWallet;
  DateTime _selectedDate = DateTime.now();

  bool _isCategoryDropdownOpen = false;
  bool _isWalletDropdownOpen = false;

  @override
  void initState() {
    super.initState();
    _loadDependencies();
  }

  Future<void> _loadDependencies() async {
    try {
      // 1. Fetch Wallets
      final wRes = await WalletService.fetchWallets();
      List<Wallet> loadedWallets = [];
      if (wRes.success && wRes.data != null) {
        dynamic rawW;
        if (wRes.data is Map && wRes.data['data'] != null) {
          rawW = wRes.data['data'];
        } else if (wRes.data is List) {
          rawW = wRes.data;
        }
        if (rawW is List) {
          loadedWallets = rawW.map((item) {
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

      if (loadedWallets.isEmpty) {
        loadedWallets = [
          const Wallet(
            name: 'Cash',
            type: 'Physical wallet',
            balanceIdr: 0,
            isPrimary: true,
            icon: Icons.payments_outlined,
            color: AppColors.income,
            background: AppColors.incomeSoft,
          )
        ];
      }

      // 2. Fetch Categories
      final catRes = await CategoryService.fetchCategories();
      List<CategoryItem> loadedCats = [];
      if (catRes.success && catRes.data != null) {
        dynamic rawCat;
        if (catRes.data is Map && catRes.data['data'] != null) {
          rawCat = catRes.data['data'];
        } else if (catRes.data is List) {
          rawCat = catRes.data;
        }
        if (rawCat is List) {
          for (final c in rawCat) {
            final map = Map<String, dynamic>.from(c as Map);
            final id = (map['id'] ?? map['id_kategori'] ?? '').toString();
            final name = (map['name'] ?? map['nama_kategori'] ?? map['nama'] ?? '').toString();
            final typeStr = (map['type'] ?? map['jenis'] ?? '').toString().toLowerCase();
            final type = typeStr == 'income' ? CategoryType.income : CategoryType.expense;
            final iconKey = map['icon']?.toString();
            final emoji = map['emoji']?.toString();

            loadedCats.add(CategoryItem(
              id: id.isNotEmpty ? id : DateTime.now().microsecondsSinceEpoch.toString(),
              name: name,
              type: type,
              iconKey: iconKey,
              emoji: emoji,
            ));
          }
        }
      }

      if (loadedCats.isEmpty) {
        loadedCats = List<CategoryItem>.of(sampleCategories);
      }

      if (mounted) {
        setState(() {
          _wallets = loadedWallets;
          _selectedWallet = _wallets.first;
          _categories = loadedCats;
          _isLoading = false;
          _syncCategoryWithType();
        });
      }
    } catch (_) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _syncCategoryWithType() {
    final available = _categories.where((c) => c.isIncome == _isIncome).toList();
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
            colorScheme: const ColorScheme.light(
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

  Future<void> _submit() async {
    if (_isSubmitting) return;

    final name = _nameCtrl.text.trim();
    if (name.isEmpty) {
      showComingSoon(context, 'Please enter a transaction name.');
      return;
    }

    if (_amount <= 0) {
      showComingSoon(context, 'Please enter a valid amount.');
      return;
    }

    if (_selectedWallet == null) {
      showComingSoon(context, 'Please select a wallet.');
      return;
    }

    setState(() => _isSubmitting = true);

    final categoryName = _selectedCategory?.name ?? (_isIncome ? 'Income' : 'Expense');
    final dateStr =
        '${_selectedDate.year}-${_selectedDate.month.toString().padLeft(2, '0')}-${_selectedDate.day.toString().padLeft(2, '0')}';

    final res = await TransactionService.createTransaction(
      name: name,
      amount: _amount,
      type: _isIncome ? 'income' : 'expense',
      category: categoryName,
      walletName: _selectedWallet!.name,
      date: dateStr,
    );

    if (!mounted) return;
    setState(() => _isSubmitting = false);

    if (res.success) {
      final currCode = preferredCurrency.value;
      final formattedAmt = formatCurrency(_amount, currCode);

      NotificationService.notifyTransaction(
        context: context,
        name: name,
        amountStr: formattedAmt,
        isIncome: _isIncome,
      );

      Navigator.of(context).pop(true);
    } else {
      showComingSoon(context, res.message ?? 'Failed to save transaction to server.');
    }
  }

  String _formatDate(DateTime d) {
    final dd = d.day.toString().padLeft(2, '0');
    final mm = d.month.toString().padLeft(2, '0');
    final yyyy = d.year.toString();
    return '$dd/$mm/$yyyy';
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
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
        final baseBalance = _selectedWallet != null
            ? DummyData.money(_selectedWallet!.balanceIdr).displayAmount
            : 0.0;
        final newBalance = _isIncome ? baseBalance + _amount : baseBalance - _amount;

        final availableCategories = _categories
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
                                _loadDependencies();
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
                                if (_selectedWallet != null) ...[
                                  Container(
                                    padding: const EdgeInsets.all(4),
                                    decoration: BoxDecoration(
                                      color: _selectedWallet!.background,
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Icon(
                                      _selectedWallet!.icon,
                                      size: 16,
                                      color: _selectedWallet!.color,
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Text(
                                    _selectedWallet!.name,
                                    style: const TextStyle(
                                      fontSize: 15,
                                      fontWeight: FontWeight.w600,
                                      color: AppColors.ink,
                                    ),
                                  ),
                                ] else ...[
                                  const Text('Select wallet', style: TextStyle(color: AppColors.muted)),
                                ],
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
                              children: _wallets.map((w) {
                                final isSelected = _selectedWallet?.name == w.name;
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
                                      : Text(
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
                              _selectedWallet?.name ?? '—',
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
