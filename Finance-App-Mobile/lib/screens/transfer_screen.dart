import 'package:flutter/material.dart';
import '../data/dummy_data.dart';
import '../models/transfer_user.dart';
import '../models/wallet.dart';
import '../services/notification_service.dart';
import '../services/transfer_service.dart';
import '../services/wallet_service.dart';
import '../theme/app_colors.dart';
import '../utils/amount_format.dart';
import '../utils/currency.dart';
import '../utils/feedback.dart';
import '../widgets/app_header.dart';

enum _TransferMode { wallet, user }

class TransferScreen extends StatefulWidget {
  const TransferScreen({super.key});

  @override
  State<TransferScreen> createState() => _TransferScreenState();
}

class _TransferScreenState extends State<TransferScreen> {
  _TransferMode _mode = _TransferMode.wallet;
  String? _fromWallet;
  String? _toWallet;
  String? _toUserId;
  String? _toUserName;
  String? _toUserWallet;
  double _amount = 0;

  bool _isFromDropdownOpen = false;
  bool _isToDropdownOpen = false;

  final _amountCtrl = TextEditingController();
  final _searchCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  List<TransferUser> _searchResults = [];
  List<Wallet> _wallets = [];
  bool _isLoading = false;

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

      if (loaded.isEmpty) {
        loaded = List<Wallet>.of(DummyData.wallets);
      }

      if (mounted) {
        setState(() {
          _wallets = loaded;
          if (_wallets.isNotEmpty) {
            _fromWallet = _wallets.first.name;
            if (_wallets.length > 1) {
              _toWallet = _wallets[1].name;
            }
          }
        });
      }
    } catch (_) {
      if (mounted && _wallets.isEmpty) {
        setState(() {
          _wallets = List<Wallet>.of(DummyData.wallets);
          if (_wallets.isNotEmpty) {
            _fromWallet = _wallets.first.name;
            if (_wallets.length > 1) {
              _toWallet = _wallets[1].name;
            }
          }
        });
      }
    }
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    _searchCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  void _switchMode(_TransferMode mode) {
    if (_mode == mode) return;
    setState(() {
      _mode = mode;
      _isFromDropdownOpen = false;
      _isToDropdownOpen = false;
      _toUserId = null;
      _toUserName = null;
      _toUserWallet = null;
      _searchCtrl.clear();
      _searchResults = [];
    });
  }

  void _toggleMode() {
    _switchMode(
      _mode == _TransferMode.wallet
          ? _TransferMode.user
          : _TransferMode.wallet,
    );
  }

  Future<void> _searchUsers(String query) async {
    final trimmed = query.trim();
    if (trimmed.isEmpty) {
      setState(() => _searchResults = []);
      return;
    }
    final results = await TransferService.searchUsersFromApi(trimmed);
    if (mounted) {
      setState(() => _searchResults = results);
    }
  }

  void _selectUser(TransferUser user) {
    if (user.walletName == null) return;
    setState(() {
      _toUserId = user.id;
      _toUserName = user.name;
      _toUserWallet = user.walletName!;
      _searchResults = [];
      _searchCtrl.text = user.name;
    });
  }

  void _clearRecipient() {
    setState(() {
      _toUserId = null;
      _toUserName = null;
      _toUserWallet = null;
      _searchCtrl.clear();
      _searchResults = [];
    });
  }

  Wallet? _findWallet(String? name) {
    if (name == null) return null;
    try {
      return _wallets.firstWhere((w) => w.name == name);
    } catch (_) {
      return null;
    }
  }

  Future<void> _handleTransfer() async {
    if (_isLoading) return;

    if (_fromWallet == null) {
      showComingSoon(context, 'Please select a source wallet.');
      return;
    }

    if (_mode == _TransferMode.wallet) {
      if (_toWallet == null) {
        showComingSoon(context, 'Please select a destination wallet.');
        return;
      }
      if (_fromWallet == _toWallet) {
        showComingSoon(
          context,
          'Source and destination wallets must be different.',
        );
        return;
      }
    } else {
      if (_toUserId == null || _toUserWallet == null) {
        showComingSoon(context, 'Please select a verified recipient.');
        return;
      }
    }

    if (_amount <= 0) {
      showComingSoon(context, 'Please enter a valid amount.');
      return;
    }

    final fromWalletObj = _findWallet(_fromWallet);
    if (fromWalletObj != null) {
      final availableDisplayBalance =
          DummyData.money(fromWalletObj.balanceIdr).displayAmount;
      if (_amount > availableDisplayBalance) {
        showComingSoon(
          context,
          'Insufficient balance in $_fromWallet (Available: ${DummyData.money(fromWalletObj.balanceIdr).text}).',
        );
        return;
      }
    }

    setState(() => _isLoading = true);

    final success = _mode == _TransferMode.wallet
        ? await TransferService.transferWallet(
            fromWallet: _fromWallet!,
            toWallet: _toWallet!,
            amount: _amount,
          )
        : await TransferService.transferUser(
            fromWallet: _fromWallet!,
            toUserId: _toUserId!,
            toWalletName: _toUserWallet!,
            amount: _amount,
          );

    if (!mounted) return;
    setState(() => _isLoading = false);

    final currCode = preferredCurrency.value;
    final amtStr = formatCurrency(_amount, currCode);
    final destName = _mode == _TransferMode.wallet
        ? _toWallet!
        : '$_toUserName ($_toUserWallet)';

    if (success) {
      NotificationService.notifyTransfer(
        context: context,
        title: 'Transfer success!',
        message: 'Transfer of $amtStr from $_fromWallet to $destName completed.',
      );
      Navigator.of(context).pop();
    } else {
      showComingSoon(context, 'Transfer failed. Try again.');
    }
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<String>(
      valueListenable: preferredCurrency,
      builder: (context, _, _) {
        return Scaffold(
          backgroundColor: AppColors.page,
          appBar: const AppHeader(showBackButton: true),
          body: GestureDetector(
            onTap: () {
              if (_isFromDropdownOpen || _isToDropdownOpen) {
                setState(() {
                  _isFromDropdownOpen = false;
                  _isToDropdownOpen = false;
                });
              }
            },
            child: ListView(
              controller: _scrollCtrl,
              physics: const ClampingScrollPhysics(),
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 40),
              children: [
                // Header (matches web)
                const Text(
                  'Transfer Wallet & User',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Transfer from a specific wallet to another wallet or user.',
                  style: TextStyle(fontSize: 14, color: AppColors.muted),
                ),
                const SizedBox(height: 20),

                // Main Form Card
                _mainFormCard(),
                const SizedBox(height: 16),

                // Summary Card
                _summaryCard(),

                SizedBox(
                  height: MediaQuery.of(context).viewInsets.bottom > 0 ? 200 : 0,
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  /// Card utama yang berisi Form (meniru x-card transfer.blade.php)
  Widget _mainFormCard() {
    final fromWalletObj = _findWallet(_fromWallet);
    final balanceText = fromWalletObj != null
        ? DummyData.money(fromWalletObj.balanceIdr).text
        : '';

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header Bar: "Transfer Wallet" / "Transfer User" + Switch Button (matches web)
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                _mode == _TransferMode.wallet
                    ? 'Transfer Wallet'
                    : 'Transfer User',
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: AppColors.ink,
                ),
              ),
              InkWell(
                onTap: _toggleMode,
                borderRadius: BorderRadius.circular(999),
                child: Container(
                  width: 36,
                  height: 36,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.transparent,
                  ),
                  child: Icon(
                    _mode == _TransferMode.wallet
                        ? Icons.arrow_forward_rounded
                        : Icons.arrow_back_rounded,
                    size: 18,
                    color: AppColors.ink,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Container Dari ... ke ... (bg-page di web)
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppColors.page,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              children: [
                // Selector: Dari Wallet
                _walletCustomDropdown(
                  value: _fromWallet,
                  isOpen: _isFromDropdownOpen,
                  onToggle: () {
                    setState(() {
                      _isFromDropdownOpen = !_isFromDropdownOpen;
                      _isToDropdownOpen = false;
                    });
                  },
                  items: _wallets,
                  onSelect: (val) {
                    setState(() {
                      _fromWallet = val;
                      _isFromDropdownOpen = false;
                      if (_toWallet == val) _toWallet = null;
                    });
                  },
                ),

                // Separator "(↓) to"
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 24,
                        height: 24,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          border: Border.all(color: AppColors.ink, width: 1.2),
                        ),
                        child: const Icon(
                          Icons.arrow_downward_rounded,
                          size: 14,
                          color: AppColors.ink,
                        ),
                      ),
                      const SizedBox(width: 6),
                      const Text(
                        'to',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                          color: AppColors.ink,
                        ),
                      ),
                    ],
                  ),
                ),

                // Tujuan: Wallet atau User
                if (_mode == _TransferMode.wallet) ...[
                  _walletCustomDropdown(
                    value: _toWallet,
                    hint: 'Select wallet',
                    isOpen: _isToDropdownOpen,
                    onToggle: () {
                      setState(() {
                        _isToDropdownOpen = !_isToDropdownOpen;
                        _isFromDropdownOpen = false;
                      });
                    },
                    items: _wallets
                        .where((w) => w.name != _fromWallet)
                        .toList(),
                    onSelect: (val) {
                      setState(() {
                        _toWallet = val;
                        _isToDropdownOpen = false;
                      });
                    },
                  ),
                ] else ...[
                  _userDestinationSection(),
                ],
              ],
            ),
          ),
          const SizedBox(height: 18),

          // Amount Field (matches web: label left, balance right, large number)
          _amountSection(balanceText: balanceText),
          const SizedBox(height: 18),

          // Category Field (matches web: w-56 box)
          _categorySection(),
          const SizedBox(height: 22),

          // Action Buttons: Cancel + Transfer (matches web: justify-end)
          _actionButtons(),
        ],
      ),
    );
  }

  /// Custom Dropdown Dompet yang persis dengan tampilan web (Image 1)
  Widget _walletCustomDropdown({
    required String? value,
    String hint = 'Select wallet',
    required bool isOpen,
    required VoidCallback onToggle,
    required List<Wallet> items,
    required ValueChanged<String> onSelect,
  }) {
    return Column(
      children: [
        // Dropdown Trigger Button
        GestureDetector(
          onTap: onToggle,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(
                color: isOpen ? AppColors.brand : AppColors.line,
                width: isOpen ? 1.5 : 1.0,
              ),
              boxShadow: isOpen
                  ? [
                      BoxShadow(
                        color: AppColors.brand.withValues(alpha: 0.18),
                        blurRadius: 6,
                        spreadRadius: 1,
                      ),
                    ]
                  : [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.02),
                        blurRadius: 2,
                        offset: const Offset(0, 1),
                      ),
                    ],
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.account_balance_wallet_outlined,
                  size: 18,
                  color: AppColors.muted,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    value ?? hint,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w500,
                      color: value != null ? AppColors.ink : AppColors.muted,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                Icon(
                  isOpen
                      ? Icons.keyboard_arrow_up_rounded
                      : Icons.keyboard_arrow_down_rounded,
                  size: 20,
                  color: AppColors.muted,
                ),
              ],
            ),
          ),
        ),

        // Dropdown Popup List (Image 1)
        if (isOpen) ...[
          const SizedBox(height: 6),
          Container(
            constraints: const BoxConstraints(maxHeight: 240),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.line),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.08),
                  blurRadius: 16,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: ListView.separated(
                shrinkWrap: true,
                padding: const EdgeInsets.all(6),
                itemCount: items.length,
                separatorBuilder: (context, index) => const SizedBox(height: 2),
                itemBuilder: (context, index) {
                  final w = items[index];
                  final isSelected = w.name == value;
                  return InkWell(
                    onTap: () => onSelect(w.name),
                    borderRadius: BorderRadius.circular(8),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 10,
                      ),
                      decoration: BoxDecoration(
                        color: isSelected
                            ? AppColors.brandSoft
                            : Colors.transparent,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              w.name,
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: isSelected
                                    ? FontWeight.w600
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
                            DummyData.money(w.balanceIdr).text,
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: isSelected
                                  ? FontWeight.w600
                                  : FontWeight.normal,
                              color: isSelected
                                  ? AppColors.brand
                                  : AppColors.muted,
                            ),
                          ),
                          if (isSelected) ...[
                            const SizedBox(width: 8),
                            const Icon(
                              Icons.check_rounded,
                              size: 16,
                              color: AppColors.brand,
                            ),
                          ],
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
          ),
        ],
      ],
    );
  }

  /// Bagian pencarian / kartu penerima di mode Transfer User (Image 3 & Image 5)
  Widget _userDestinationSection() {
    if (_toUserId != null) {
      // Penerima Terpilih (Verified Recipient Card)
      return Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AppColors.brandSoft,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.brand.withValues(alpha: 0.3)),
        ),
        child: Column(
          children: [
            Row(
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.brand,
                  ),
                  child: const Icon(
                    Icons.person_rounded,
                    size: 18,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _toUserName ?? '',
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: AppColors.ink,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 2),
                      const Text(
                        'Recipient found & verified',
                        style: TextStyle(fontSize: 11, color: AppColors.muted),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.incomeSoft,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: const Text(
                    'Verified',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: AppColors.income,
                    ),
                  ),
                ),
                const SizedBox(width: 6),
                GestureDetector(
                  onTap: _clearRecipient,
                  child: const Padding(
                    padding: EdgeInsets.all(4),
                    child: Icon(
                      Icons.close_rounded,
                      size: 18,
                      color: AppColors.muted,
                    ),
                  ),
                ),
              ],
            ),
            if (_toUserWallet != null) ...[
              const SizedBox(height: 10),
              Row(
                children: [
                  const Text(
                    'Destination wallet',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                      color: AppColors.muted,
                    ),
                  ),
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: AppColors.line),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.account_balance_wallet_outlined,
                          size: 14,
                          color: AppColors.brand,
                        ),
                        const SizedBox(width: 6),
                        Text(
                          _toUserWallet!,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: AppColors.ink,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      );
    }

    // Input Pencarian (Pill Input - rounded-full di web - Image 3 & Image 5)
    return Column(
      children: [
        Container(
          height: 44,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: _searchResults.isNotEmpty
                  ? AppColors.brand
                  : AppColors.line,
              width: _searchResults.isNotEmpty ? 1.5 : 1.0,
            ),
            boxShadow: _searchResults.isNotEmpty
                ? [
                    BoxShadow(
                      color: AppColors.brand.withValues(alpha: 0.15),
                      blurRadius: 6,
                      spreadRadius: 1,
                    ),
                  ]
                : null,
          ),
          child: TextField(
            controller: _searchCtrl,
            onChanged: _searchUsers,
            style: const TextStyle(fontSize: 14, color: AppColors.ink),
            decoration: InputDecoration(
              hintText: 'Search username or email',
              hintStyle: const TextStyle(color: AppColors.muted, fontSize: 13),
              prefixIcon: const Icon(
                Icons.person_outline_rounded,
                color: AppColors.brand,
                size: 18,
              ),
              suffixIcon: const Icon(
                Icons.search_rounded,
                color: AppColors.muted,
                size: 18,
              ),
              border: InputBorder.none,
              contentPadding: const EdgeInsets.symmetric(vertical: 12),
            ),
          ),
        ),

        // Live Search Results List (Image 5)
        if (_searchResults.isNotEmpty) ...[
          const SizedBox(height: 6),
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.line),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.08),
                  blurRadius: 16,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Column(
                children: [
                  for (int i = 0; i < _searchResults.length; i++) ...[
                    _searchUserTile(_searchResults[i]),
                    if (i < _searchResults.length - 1)
                      const Divider(height: 1, color: AppColors.line),
                  ],
                ],
              ),
            ),
          ),
        ],
      ],
    );
  }

  /// Tile hasil pencarian user (Image 5)
  Widget _searchUserTile(TransferUser user) {
    final canTransfer = user.verified && user.walletName != null;

    return InkWell(
      onTap: canTransfer ? () => _selectUser(user) : null,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
        child: Row(
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.brandSoft,
              ),
              child: const Icon(
                Icons.person_outline_rounded,
                size: 18,
                color: AppColors.brand,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                user.name,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.ink,
                ),
                overflow: TextOverflow.ellipsis,
              ),
            ),
            const SizedBox(width: 8),
            // Pill status / wallet di sebelah kanan
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: canTransfer
                    ? const Color(0xFFF1F5F9)
                    : AppColors.expenseSoft,
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                user.statusText ??
                    (canTransfer ? user.walletName! : "Don't have a wallet"),
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: canTransfer
                      ? const Color(0xFF475569)
                      : AppColors.expense,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Amount input (Image 2, 3, 4: label kiri, balance kanan, prefix simbol, angka besar)
  Widget _amountSection({required String balanceText}) {
    final currCode = preferredCurrency.value;
    final symbol = getCurrencySymbol(currCode);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Amount',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w500,
                color: AppColors.ink,
              ),
            ),
            if (balanceText.isNotEmpty)
              RichText(
                text: TextSpan(
                  style: const TextStyle(fontSize: 12, color: AppColors.muted),
                  children: [
                    const TextSpan(text: 'Balance: '),
                    TextSpan(
                      text: balanceText,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        color: AppColors.ink,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _amountCtrl,
          keyboardType: const TextInputType.numberWithOptions(
            decimal: true,
          ),
          inputFormatters: [AmountInputFormatter()],
          textAlignVertical: TextAlignVertical.center,
          style: const TextStyle(
            fontSize: 22,
            fontWeight: FontWeight.w700,
            color: AppColors.ink,
          ),
          decoration: InputDecoration(
            filled: true,
            fillColor: Colors.white,
            hintText: '0',
            hintStyle: const TextStyle(
              color: AppColors.muted,
              fontSize: 22,
              fontWeight: FontWeight.w600,
            ),
            prefixIcon: Padding(
              padding: const EdgeInsets.only(left: 14, right: 10),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    symbol,
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w600,
                      color: AppColors.muted,
                    ),
                  ),
                ],
              ),
            ),
            prefixIconConstraints: const BoxConstraints(
              minWidth: 0,
              minHeight: 0,
            ),
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 14,
              vertical: 12,
            ),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: AppColors.line),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: AppColors.line),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(
                color: AppColors.brand,
                width: 1.5,
              ),
            ),
          ),
          onChanged: (val) {
            final parsed =
                double.tryParse(val.replaceAll(',', '')) ?? 0;
            setState(() => _amount = parsed);
          },
        ),
      ],
    );
  }

  /// Category section (Image 2, 3, 4: box w-56 putih)
  Widget _categorySection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Category',
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w500,
            color: AppColors.ink,
          ),
        ),
        const SizedBox(height: 8),
        Container(
          width: 220,
          height: 44,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: AppColors.line),
          ),
          child: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.account_balance_wallet_outlined,
                size: 16,
                color: AppColors.muted,
              ),
              SizedBox(width: 8),
              Text(
                'Transfer',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                  color: AppColors.ink,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  /// Action Buttons (Image 2, 3, 4: Cancel outline putih & Transfer gelap di kanan)
  Widget _actionButtons() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.end,
      children: [
        InkWell(
          onTap: _isLoading ? null : () => Navigator.of(context).pop(),
          borderRadius: BorderRadius.circular(10),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppColors.line),
            ),
            child: const Text(
              'Cancel',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppColors.ink,
              ),
            ),
          ),
        ),
        const SizedBox(width: 12),
        InkWell(
          onTap: _isLoading ? null : _handleTransfer,
          borderRadius: BorderRadius.circular(10),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
            decoration: BoxDecoration(
              color: AppColors.dark,
              borderRadius: BorderRadius.circular(10),
            ),
            child: _isLoading
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text(
                    'Transfer',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: Colors.white,
                    ),
                  ),
          ),
        ),
      ],
    );
  }

  /// Summary Card (Image 2 & Image 4)
  Widget _summaryCard() {
    final currCode = preferredCurrency.value;
    final formattedAmt = formatCurrency(_amount, currCode);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Summary',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: AppColors.ink,
            ),
          ),
          const SizedBox(height: 18),

          // From Wallet row
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'From Wallet',
                style: TextStyle(fontSize: 14, color: AppColors.muted),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 4,
                ),
                decoration: BoxDecoration(
                  color: AppColors.expenseSoft,
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  _fromWallet ?? '-',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    color: AppColors.ink,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // To Wallet / To User row
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                _mode == _TransferMode.wallet ? 'To Wallet' : 'To User',
                style: const TextStyle(fontSize: 14, color: AppColors.muted),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 4,
                ),
                decoration: BoxDecoration(
                  color: AppColors.expenseSoft,
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  _mode == _TransferMode.wallet
                      ? (_toWallet ?? '-')
                      : (_toUserName ??
                          (_searchCtrl.text.isNotEmpty
                              ? _searchCtrl.text
                              : '-')),
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    color: AppColors.ink,
                  ),
                ),
              ),
            ],
          ),

          // To Wallet (User mode destination wallet if recipient selected)
          if (_mode == _TransferMode.user && _toUserWallet != null) ...[
            const SizedBox(height: 14),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'To Wallet',
                  style: TextStyle(fontSize: 14, color: AppColors.muted),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.page,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    _toUserWallet!,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                      color: AppColors.ink,
                    ),
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 14),

          // Amount row
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Amount',
                style: TextStyle(fontSize: 14, color: AppColors.muted),
              ),
              Text(
                formattedAmt,
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                  color: AppColors.ink,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
