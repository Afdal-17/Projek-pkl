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
import '../utils/refresh_notifier.dart';
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
  int? _toWalletId;
  List<Map<String, dynamic>> _toRecipientWallets = [];
  bool _isRecipientWalletDropdownOpen = false;
  double _amount = 0;

  bool _isFromDropdownOpen = false;
  bool _isToDropdownOpen = false;

  final _amountCtrl = TextEditingController();
  final _searchCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  List<TransferUser> _searchResults = [];
  bool _isSearching = false;
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
    } catch (_) {}
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
      _toWalletId = null;
      _toRecipientWallets = [];
      _isRecipientWalletDropdownOpen = false;
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
      setState(() {
        _searchResults = [];
        _isSearching = false;
      });
      return;
    }
    setState(() => _isSearching = true);
    final results = await TransferService.searchUsersFromApi(trimmed);
    if (mounted) {
      setState(() {
        _searchResults = results;
        _isSearching = false;
      });
    }
  }

  void _selectUser(TransferUser user) {
    if (!user.verified || user.targetWalletId == null) {
      return;
    }

    final walletsList = <Map<String, dynamic>>[];
    for (final w in user.wallets) {
      if (w is Map) {
        walletsList.add(Map<String, dynamic>.from(w));
      }
    }

    final firstWallet = walletsList.isNotEmpty ? walletsList.first : null;
    final firstWalletId = firstWallet != null
        ? (firstWallet['id'] is int
            ? firstWallet['id'] as int
            : int.tryParse(firstWallet['id'].toString()))
        : user.targetWalletId;
    final firstWalletName = firstWallet != null
        ? firstWallet['name']?.toString()
        : user.walletName;

    setState(() {
      _toUserId = user.id;
      _toUserName = user.name;
      _toUserWallet = firstWalletName ?? 'Wallet';
      _toWalletId = firstWalletId;
      _toRecipientWallets = walletsList.isNotEmpty
          ? walletsList
          : [
              {'id': firstWalletId ?? 1, 'name': firstWalletName ?? 'Wallet'}
            ];
      _isRecipientWalletDropdownOpen = false;
      _searchResults = [];
      _isSearching = false;
      _searchCtrl.text = user.name;
    });
  }

  void _clearRecipient() {
    setState(() {
      _toUserId = null;
      _toUserName = null;
      _toUserWallet = null;
      _toWalletId = null;
      _toRecipientWallets = [];
      _isRecipientWalletDropdownOpen = false;
      _searchCtrl.clear();
      _searchResults = [];
      _isSearching = false;
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

    final fromWalletObj = _findWallet(_fromWallet);
    if (fromWalletObj == null || fromWalletObj.id == null) {
      showComingSoon(context, 'Please select a source wallet.');
      return;
    }

    int? destinationWalletId;
    String destDisplayName;

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
      final toWalletObj = _findWallet(_toWallet);
      if (toWalletObj == null || toWalletObj.id == null) {
        showComingSoon(context, 'Destination wallet not found.');
        return;
      }
      destinationWalletId = toWalletObj.id as int?;
      destDisplayName = _toWallet!;
    } else {
      if (_toUserId == null || _toWalletId == null) {
        showComingSoon(
          context,
          'Please select a verified recipient with an active receiving wallet.',
        );
        return;
      }
      destinationWalletId = _toWalletId;
      destDisplayName = '$_toUserName (${_toUserWallet ?? "Wallet"})';
    }

    if (destinationWalletId == null) {
      showComingSoon(context, 'Destination wallet could not be resolved.');
      return;
    }

    final parsedAmount = AmountFormat.parse(_amountCtrl.text.trim());
    _amount = parsedAmount;
    if (_amount <= 0) {
      showComingSoon(context, 'Please enter a valid amount.');
      return;
    }

    final availableDisplayBalance =
        DummyData.money(fromWalletObj.balanceIdr).displayAmount;
    if (_amount > availableDisplayBalance) {
      showAppPopup(
        context,
        'Insufficient balance in $_fromWallet (Available: ${DummyData.money(fromWalletObj.balanceIdr).text}).',
        title: 'Insufficient Balance',
        type: PopupType.warning,
      );
      return;
    }

    setState(() => _isLoading = true);

    final fromWalletId = fromWalletObj.id is int
        ? fromWalletObj.id as int
        : int.tryParse(fromWalletObj.id.toString()) ?? 1;

    final res = await TransferService.executeTransfer(
      fromWalletId: fromWalletId,
      toWalletId: destinationWalletId,
      amount: _amount,
      note: 'Transfer to $destDisplayName',
    );

    if (!mounted) return;
    setState(() => _isLoading = false);

    final currCode = preferredCurrency.value;
    final amtStr = formatCurrency(_amount, currCode);

    if (res.success) {
      AppRefreshNotifier.notifyDataChanged();
      NotificationService.notifyTransfer(
        context: context,
        title: 'Transfer success!',
        message: 'Transfer of $amtStr from $_fromWallet to $destDisplayName completed.',
      );
      showAppPopup(
        context,
        'Transfer of $amtStr to $destDisplayName was completed successfully.',
        title: 'Transfer Success',
        type: PopupType.success,
        onConfirm: () {
          if (mounted) Navigator.of(context).pop(true);
        },
      );
    } else {
      showAppPopup(
        context,
        res.message ?? 'Transfer failed. Please check your balance or recipient details.',
        title: 'Transfer Failed',
        type: PopupType.error,
      );
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
              if (_isFromDropdownOpen ||
                  _isToDropdownOpen ||
                  _isRecipientWalletDropdownOpen) {
                setState(() {
                  _isFromDropdownOpen = false;
                  _isToDropdownOpen = false;
                  _isRecipientWalletDropdownOpen = false;
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

  /// Bagian pencarian / kartu penerima di mode Transfer User (Matches Web Screenshot 1 & 2)
  Widget _userDestinationSection() {
    if (_toUserId != null) {
      // Penerima Terpilih (Verified Recipient Card - Matches Web Screenshot 2)
      return Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: const Color(0xFFF5F7FF),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.brand.withValues(alpha: 0.25)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top Row: Avatar + Name + Subtitle + Verified Badge + Close 'X'
            Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.brand,
                  ),
                  child: const Icon(
                    Icons.person_rounded,
                    size: 20,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _toUserName ?? '',
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: AppColors.ink,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 2),
                      const Text(
                        'Recipient found & verified',
                        style: TextStyle(
                          fontSize: 12,
                          color: Color(0xFF64748B),
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFDCFCE7),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: const Text(
                    'Verified',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF15803D),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                InkWell(
                  onTap: _clearRecipient,
                  borderRadius: BorderRadius.circular(999),
                  child: const Padding(
                    padding: EdgeInsets.all(4),
                    child: Icon(
                      Icons.close_rounded,
                      size: 18,
                      color: Color(0xFF64748B),
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 12),
            const Divider(height: 1, color: Color(0xFFE2E8F0)),
            const SizedBox(height: 12),

            // Destination Wallet Selector Dropdown (Matches Screenshot 2)
            const Text(
              'Destination wallet',
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w500,
                color: Color(0xFF64748B),
              ),
            ),
            const SizedBox(height: 6),

            // Dropdown trigger button
            GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: () {
                if (_toRecipientWallets.length > 1) {
                  setState(() {
                    _isRecipientWalletDropdownOpen = !_isRecipientWalletDropdownOpen;
                  });
                }
              },
              child: Container(
                height: 44,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: _isRecipientWalletDropdownOpen
                        ? AppColors.brand
                        : const Color(0xFFCBD5E1),
                    width: _isRecipientWalletDropdownOpen ? 1.5 : 1.0,
                  ),
                ),
                child: Row(
                  children: [
                    const Icon(
                      Icons.account_balance_wallet_outlined,
                      size: 16,
                      color: AppColors.brand,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        _toUserWallet ?? 'Select wallet',
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: AppColors.ink,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (_toRecipientWallets.length > 1)
                      Icon(
                        _isRecipientWalletDropdownOpen
                            ? Icons.keyboard_arrow_up_rounded
                            : Icons.keyboard_arrow_down_rounded,
                        size: 20,
                        color: const Color(0xFF64748B),
                      ),
                  ],
                ),
              ),
            ),

            // Dropdown Popup List (Matches Screenshot 2)
            if (_isRecipientWalletDropdownOpen && _toRecipientWallets.isNotEmpty) ...[
              const SizedBox(height: 6),
              Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.08),
                      blurRadius: 12,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  children: _toRecipientWallets.map((w) {
                    final id = w['id'] is int ? w['id'] as int : int.tryParse(w['id'].toString());
                    final name = (w['name'] ?? 'Wallet').toString();
                    final isSelected = id == _toWalletId;

                    return InkWell(
                      onTap: () {
                        setState(() {
                          _toWalletId = id;
                          _toUserWallet = name;
                          _isRecipientWalletDropdownOpen = false;
                        });
                      },
                      borderRadius: BorderRadius.circular(8),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        decoration: BoxDecoration(
                          color: isSelected ? const Color(0xFFEEF2FF) : Colors.transparent,
                        ),
                        child: Row(
                          children: [
                            Text(
                              name,
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                                color: isSelected ? AppColors.brand : AppColors.ink,
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
          ],
        ),
      );
    }

    // Input Pencarian (Pill Input - rounded-full di web - Matches Screenshot 1)
    return Column(
      children: [
        Container(
          height: 46,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: _searchResults.isNotEmpty || _searchCtrl.text.isNotEmpty
                  ? AppColors.brand
                  : const Color(0xFFCBD5E1),
              width: _searchResults.isNotEmpty || _searchCtrl.text.isNotEmpty ? 1.5 : 1.0,
            ),
            boxShadow: _searchResults.isNotEmpty || _searchCtrl.text.isNotEmpty
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
              hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
              prefixIcon: const Icon(
                Icons.person_outline_rounded,
                color: AppColors.brand,
                size: 18,
              ),
              suffixIcon: _isSearching
                  ? const Padding(
                      padding: EdgeInsets.all(13),
                      child: SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: AppColors.brand,
                        ),
                      ),
                    )
                  : const Icon(
                      Icons.search_rounded,
                      color: Color(0xFF94A3B8),
                      size: 18,
                    ),
              border: InputBorder.none,
              contentPadding: const EdgeInsets.symmetric(vertical: 12),
            ),
          ),
        ),

        // Live Search Results List (Matches Screenshot 1)
        if (_searchResults.isNotEmpty) ...[
          const SizedBox(height: 6),
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFE2E8F0)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.08),
                  blurRadius: 16,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: Column(
                children: [
                  for (int i = 0; i < _searchResults.length; i++) ...[
                    _searchUserTile(_searchResults[i]),
                    if (i < _searchResults.length - 1)
                      const Divider(height: 1, color: Color(0xFFF1F5F9)),
                  ],
                ],
              ),
            ),
          ),
        ] else if (!_isSearching &&
            _searchCtrl.text.trim().isNotEmpty &&
            _toUserId == null) ...[
          const SizedBox(height: 6),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Row(
              children: [
                const Icon(Icons.info_outline_rounded, size: 18, color: AppColors.muted),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'No recipient found for "${_searchCtrl.text.trim()}".',
                    style: const TextStyle(fontSize: 13, color: AppColors.muted),
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  /// Tile hasil pencarian user (Matches Screenshot 1)
  Widget _searchUserTile(TransferUser user) {
    final canTransfer = user.verified && user.targetWalletId != null;

    return InkWell(
      onTap: () {
        if (canTransfer) {
          _selectUser(user);
        } else if (user.isBanned) {
          showAppPopup(
            context,
            'This user account is suspended.',
            type: PopupType.warning,
          );
        } else if (!user.hasAnyWallet) {
          showAppPopup(
            context,
            'This user does not have a wallet yet.',
            type: PopupType.warning,
          );
        } else {
          showAppPopup(
            context,
            'This user has not enabled any wallet to receive transfers.',
            type: PopupType.warning,
          );
        }
      },
      child: Opacity(
        opacity: canTransfer ? 1.0 : 0.55,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: user.isBanned
                      ? const Color(0xFFFEE2E2)
                      : const Color(0xFFEEF2FF),
                ),
                child: Icon(
                  Icons.person_outline_rounded,
                  size: 18,
                  color: user.isBanned
                      ? const Color(0xFFEF4444)
                      : AppColors.brand,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user.name,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: AppColors.ink,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (user.email.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        user.email,
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF64748B),
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 8),
              // Pill status / wallet di sebelah kanan (Matches Screenshot 1)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: canTransfer
                      ? const Color(0xFFF1F5F9)
                      : const Color(0xFFFEE2E2),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  user.statusText ?? (canTransfer ? user.walletName! : "Don't have a wallet"),
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: canTransfer
                        ? const Color(0xFF475569)
                        : const Color(0xFFDC2626),
                  ),
                ),
              ),
            ],
          ),
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
            final parsed = AmountFormat.parse(val);
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
