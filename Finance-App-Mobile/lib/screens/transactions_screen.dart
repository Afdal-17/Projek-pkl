import 'dart:math' as math;

import 'package:flutter/material.dart';
import '../data/dummy_data.dart';
import '../data/transaction_samples.dart';
import '../models/transaction_item.dart';
import '../theme/app_colors.dart';
import '../widgets/app_button.dart';
import '../widgets/app_card.dart';
import '../widgets/app_header.dart';
import '../widgets/pagination_bar.dart';
import 'add_transaction_screen.dart';
import 'manage_categories_screen.dart';
import 'transfer_screen.dart';

const _slate = Color(0xFF64748B);
const _greyTag = Color(0xFFF1F5F9);
const _allCategories = 'All categories';
const _allWallets = 'All wallets';

/// Lebar maksimum menu dropdown, sama dengan ukuran menu di web.
const _menuMaxWidth = 240.0;
const _datePanelWidth = 260.0;

/// Jumlah transaksi per halaman, sama seperti di web.
const _pageSize = 6;

class TransactionsScreen extends StatefulWidget {
  const TransactionsScreen({super.key});

  @override
  State<TransactionsScreen> createState() => _TransactionsScreenState();
}

class _TransactionsScreenState extends State<TransactionsScreen> {
  final _searchCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  final _filterBoundsKey = GlobalKey();
  String _query = '';
  String? _category;
  String? _wallet;
  DateTime? _from;
  DateTime? _to;
  int _page = 0;

  Future<void> _handleRefresh() async {
    await Future.delayed(const Duration(milliseconds: 700));
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  List<String> get _categories =>
      sampleTransactions.map((t) => t.category).toSet().toList()..sort();

  List<String> get _walletNames => DummyData.wallets.map((w) => w.name).toList();

  bool get _hasFilter =>
      _query.isNotEmpty ||
      _category != null ||
      _wallet != null ||
      _from != null ||
      _to != null;

  List<TransactionItem> get _filtered {
    final q = _query.toLowerCase();
    return sampleTransactions.where((t) {
      if (q.isNotEmpty &&
          !t.title.toLowerCase().contains(q) &&
          !t.category.toLowerCase().contains(q) &&
          !(t.walletName ?? '').toLowerCase().contains(q)) {
        return false;
      }
      if (_category != null && t.category != _category) return false;
      if (_wallet != null && t.walletName != _wallet) return false;

      final d = t.date;
      if (_from != null && (d == null || d.isBefore(_from!))) return false;
      if (_to != null && (d == null || d.isAfter(_to!))) return false;
      return true;
    }).toList();
  }

  void _resetFilters() {
    setState(() {
      _searchCtrl.clear();
      _query = '';
      _category = null;
      _wallet = null;
      _from = null;
      _to = null;
      _page = 0;
    });
  }

  /// Pindah halaman dan naik ke atas daftar.
  void _goToPage(int page) {
    setState(() => _page = page);
    if (_scrollCtrl.hasClients) {
      _scrollCtrl.animateTo(
        0,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final items = _filtered;

    // Jika filter mempersempit hasil, halaman dikunci ke yang valid.
    final totalPages = items.isEmpty ? 1 : (items.length / _pageSize).ceil();
    final page = _page.clamp(0, totalPages - 1);
    final start = page * _pageSize;
    final end = (start + _pageSize).clamp(0, items.length);
    final pageItems = items.sublist(start, end);

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
          controller: _scrollCtrl,
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          children: [

          const SizedBox(height: 20),
          const Text(
            'Transactions',
            style: TextStyle(
              fontSize: 26,
              fontWeight: FontWeight.w800,
              color: AppColors.ink,
              ),
            ),
          const SizedBox(height: 4),
          const Text(
            'Review every income and expense in one place.',
            style: TextStyle(fontSize: 14, color: AppColors.muted),
            ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _colorButton(
                  label: 'Manage Category',
                  color: _slate,
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const ManageCategoriesScreen(),
                      ),
                    ),
                  ),
                ),
              const SizedBox(width: 12),
              Expanded(
                child: _colorButton(
                  label: 'Transfer',
                  color: AppColors.brand,
                  icon: Icons.arrow_forward_rounded,
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const TransferScreen(),
                      ),
                    ),
                  ),
                ),
            ],
            ),
          const SizedBox(height: 10),
          AppButton(
            label: 'Add Transaction',
            icon: Icons.add_rounded,
            onPressed: () => Navigator.of(context)
                .push(
                  MaterialPageRoute(
                    builder: (_) => const AddTransactionScreen(),
                    ),
                )
                .then((_) => setState(() {})),
            ),
          const SizedBox(height: 16),
          _filterCard(items.length),
          const SizedBox(height: 16),
          GestureDetector(
            behavior: HitTestBehavior.translucent,
            onHorizontalDragEnd: (details) {
              final v = details.primaryVelocity ?? 0;
              if (v < -300 && page < totalPages - 1) _goToPage(page + 1);
              if (v > 300 && page > 0) _goToPage(page - 1);
            },
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                ..._results(pageItems),
                if (items.isNotEmpty)
                  PaginationBar(
                    page: page,
                    totalPages: totalPages,
                    shownFrom: start + 1,
                    shownTo: end,
                    total: items.length,
                    onPageChanged: _goToPage,
                  ),
              ],
            ),
          ),
        ],
      ),
    ),
  ),
],
),
        );
  }


  /// Tombol berwarna. Teks dikecilkan otomatis, bukan dipotong.
  Widget _colorButton({
    required String label,
    required Color color,
    required VoidCallback onPressed,
    IconData? icon,
  }) {
    return SizedBox(
      height: 48,
      child: ElevatedButton(
        onPressed: onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: color,
          foregroundColor: Colors.white,
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 10),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
            ),
          ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icon != null) ...[
              Icon(icon, size: 18),
              const SizedBox(width: 6),
            ],
            Flexible(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  label,
                  style: const TextStyle(
                    fontSize: 14,
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

  Widget _filterCard(int resultCount) {
    return AppCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        key: _filterBoundsKey,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: _searchCtrl,
            onChanged: (v) => setState(() {
              _query = v;
              _page = 0;
            }),
            decoration: InputDecoration(
              hintText: 'Search transactions...',
              hintStyle: const TextStyle(color: AppColors.muted),
              prefixIcon: const Icon(
                Icons.search_rounded,
                color: AppColors.muted,
                ),
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              border: _inputBorder(),
              enabledBorder: _inputBorder(),
              focusedBorder: _inputBorder(color: AppColors.brand, width: 2),
              ),
            ),

          // Hasil dan Reset ada tepat di bawah search, seperti di web.
          if (_hasFilter) ...[
            const SizedBox(height: 10),
            Row(
              children: [
                Text(
                  '$resultCount results',
                  style: const TextStyle(fontSize: 13, color: AppColors.muted),
                  ),
                const Spacer(),
                GestureDetector(
                  onTap: _resetFilters,
                  child: const Text(
                    'Reset',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.brand,
                      ),
                    ),
                  ),
              ],
              ),
          ],

          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              filterChip(
                label: 'All',
                active: !_hasFilter,
                dark: true,
                onTap: _resetFilters,
                ),
              _FilterDropdown(
                label: _category ?? 'Category',
                active: _category != null,
                allLabel: _allCategories,
                options: _categories,
                current: _category,
                boundsKey: _filterBoundsKey,
                onSelected: (v) => setState(() {
                  _category = v == _allCategories ? null : v;
                  _page = 0;
                }),
                ),
              _FilterDropdown(
                label: _wallet ?? 'Wallet',
                active: _wallet != null,
                allLabel: _allWallets,
                options: _walletNames,
                current: _wallet,
                boundsKey: _filterBoundsKey,
                onSelected: (v) => setState(() {
                  _wallet = v == _allWallets ? null : v;
                  _page = 0;
                }),
                ),
              _DateFilter(
                from: _from,
                to: _to,
                boundsKey: _filterBoundsKey,
                onChanged: (from, to) => setState(() {
                  _from = from;
                  _to = to;
                  _page = 0;
                }),
                ),
            ],
            ),
        ],
        ),
    );
  }

  InputBorder _inputBorder({Color color = AppColors.line, double width = 1}) {
    return OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: BorderSide(color: color, width: width),
    );
  }

  /// Daftar transaksi untuk halaman aktif, dikelompokkan per tanggal.
  List<Widget> _results(List<TransactionItem> items) {
    if (items.isEmpty) {
      return [
        const AppCard(
          child: Padding(
            padding: EdgeInsets.symmetric(vertical: 24),
            child: Center(
              child: Text(
                'No transactions match your filters.',
                style: TextStyle(fontSize: 14, color: AppColors.muted),
                ),
              ),
            ),
          ),
      ];
    }

    final groups = <String, List<TransactionItem>>{};
    for (final t in items) {
      groups.putIfAbsent(t.dateLabel, () => []).add(t);
    }

    final widgets = <Widget>[];
    for (final entry in groups.entries) {
      final total = entry.value.fold<double>(0, (s, t) => s + t.amount.displayAmount);
      final totalSign = total < 0 ? '-' : '';

      widgets.add(
        AppCard(
          padding: EdgeInsets.zero,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        entry.key,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: AppColors.ink,
                          ),
                        ),
                      ),
                    Text(
                      '$totalSign${DummyData.money(total.abs()).text}',
                      style: const TextStyle(
                        fontSize: 14,
                        color: AppColors.muted,
                        ),
                      ),
                  ],
                  ),
                ),
              for (int i = 0; i < entry.value.length; i++) ...[
                _TransactionRow(item: entry.value[i]),
                if (i < entry.value.length - 1)
                  const Divider(height: 1, color: AppColors.line),
              ],
            ],
            ),
          ),
      );
      widgets.add(const SizedBox(height: 12));
    }
    return widgets;
  }
}

/// Posisi menu agar tetap di dalam area kartu filter.
class _Placement {
  const _Placement(this.width, this.dx);

  final double width;
  final double dx;
}

_Placement _placeMenu(GlobalKey chipKey, GlobalKey boundsKey, double maxWidth) {
  final chipBox = chipKey.currentContext?.findRenderObject() as RenderBox?;
  final boundsBox = boundsKey.currentContext?.findRenderObject() as RenderBox?;
  if (chipBox == null || boundsBox == null) return _Placement(maxWidth, 0);

  final boundsLeft = boundsBox.localToGlobal(Offset.zero).dx;
  final chipLeft = chipBox.localToGlobal(Offset.zero).dx;
  final width = math.min(maxWidth, boundsBox.size.width);
  final maxLeft = boundsLeft + boundsBox.size.width - width;
  final left = math.max(boundsLeft, math.min(chipLeft, maxLeft));
  return _Placement(width, left - chipLeft);
}

/// Chip filter. Tampilan sama untuk tombol biasa, dropdown, dan All.
Widget filterChip({
  Key? key,
  required String label,
  required bool active,
  required VoidCallback onTap,
  bool dark = false,
  bool arrow = false,
}) {
  final Color bg;
  final Color fg;
  final Color border;

  if (dark && active) {
    bg = AppColors.dark;
    fg = Colors.white;
    border = AppColors.dark;
  } else if (active) {
    bg = AppColors.brandSoft;
    fg = AppColors.brand;
    border = AppColors.brand;
  } else {
    bg = Colors.white;
    fg = AppColors.ink;
    border = AppColors.line;
  }

  return InkWell(
    key: key,
    borderRadius: BorderRadius.circular(999),
    onTap: onTap,
    child: Container(
      height: 36,
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: border),
        ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Flexible(
            child: Text(
              label,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: fg,
                ),
              ),
            ),
          if (arrow) ...[
            const SizedBox(width: 4),
            Icon(Icons.keyboard_arrow_down_rounded, size: 18, color: fg),
          ],
        ],
        ),
    ),
  );
}

/// Menu Style yang sama untuk semua popover filter.
MenuStyle _popoverStyle(double width) {
  return MenuStyle(
    backgroundColor: const WidgetStatePropertyAll(Colors.white),
    surfaceTintColor: const WidgetStatePropertyAll(Colors.transparent),
    elevation: const WidgetStatePropertyAll(4),
    padding: const WidgetStatePropertyAll(EdgeInsets.symmetric(vertical: 6)),
    shape: WidgetStatePropertyAll(
      RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: AppColors.line),
        ),
    ),
    minimumSize: WidgetStatePropertyAll(Size(width, 0)),
    maximumSize: WidgetStatePropertyAll(Size(width, 320)),
  );
}

/// Chip Category atau Wallet. Menu muncul di bawah chip seperti dropdown di web.
class _FilterDropdown extends StatefulWidget {
  const _FilterDropdown({
    required this.label,
    required this.active,
    required this.allLabel,
    required this.options,
    required this.current,
    required this.boundsKey,
    required this.onSelected,
  });

  final String label;
  final bool active;
  final String allLabel;
  final List<String> options;
  final String? current;
  final GlobalKey boundsKey;
  final ValueChanged<String> onSelected;

  @override
  State<_FilterDropdown> createState() => _FilterDropdownState();
}

class _FilterDropdownState extends State<_FilterDropdown> {
  final _chipKey = GlobalKey();
  double _width = _menuMaxWidth;
  double _dx = 0;

  void _toggle(MenuController c) {
    if (c.isOpen) {
      c.close();
      return;
    }
    FocusScope.of(context).unfocus();

    final p = _placeMenu(_chipKey, widget.boundsKey, _menuMaxWidth);
    setState(() {
      _width = p.width;
      _dx = p.dx;
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) c.open();
    });
  }

  @override
  Widget build(BuildContext context) {
    final all = [widget.allLabel, ...widget.options];

    return MenuAnchor(
      alignmentOffset: Offset(_dx, 6),
      style: _popoverStyle(_width),
      menuChildren: [
        for (final o in all)
          Builder(
            builder: (_) {
              final selected =
                  (o == widget.allLabel && widget.current == null) ||
                      o == widget.current;
              return MenuItemButton(
                onPressed: () => widget.onSelected(o),
                trailingIcon: selected
                    ? const Icon(
                        Icons.check_rounded,
                        size: 18,
                        color: AppColors.brand,
                      )
                    : null,
                style: ButtonStyle(
                  minimumSize: WidgetStatePropertyAll(Size(_width, 44)),
                  backgroundColor: WidgetStatePropertyAll(
                    selected ? AppColors.brandSoft : Colors.transparent,
                    ),
                  foregroundColor: WidgetStatePropertyAll(
                    selected ? AppColors.brand : AppColors.ink,
                    ),
                  padding: const WidgetStatePropertyAll(
                    EdgeInsets.symmetric(horizontal: 16),
                    ),
                  shape: const WidgetStatePropertyAll(
                    RoundedRectangleBorder(),
                    ),
                  textStyle: WidgetStatePropertyAll(
                    TextStyle(
                      fontSize: 15,
                      fontWeight:
                          selected ? FontWeight.w600 : FontWeight.w400,
                      ),
                    ),
                  ),
                child: Text(o),
              );
            },
            ),
      ],
      builder: (context, controller, _) {
        return filterChip(
          key: _chipKey,
          label: widget.label,
          active: widget.active,
          arrow: true,
          onTap: () => _toggle(controller),
        );
      },
    );
  }
}

/// Chip Date. Panel From dan To muncul di bawah chip, seperti di web.
class _DateFilter extends StatefulWidget {
  const _DateFilter({
    required this.from,
    required this.to,
    required this.boundsKey,
    required this.onChanged,
  });

  final DateTime? from;
  final DateTime? to;
  final GlobalKey boundsKey;
  final void Function(DateTime? from, DateTime? to) onChanged;

  @override
  State<_DateFilter> createState() => _DateFilterState();
}

class _DateFilterState extends State<_DateFilter> {
  final _chipKey = GlobalKey();
  MenuController? _menuCtrl;
  double _width = _datePanelWidth;
  double _dx = 0;

  void _toggle(MenuController c) {
    _menuCtrl = c;
    if (c.isOpen) {
      c.close();
      return;
    }
    FocusScope.of(context).unfocus();

    final p = _placeMenu(_chipKey, widget.boundsKey, _datePanelWidth);
    setState(() {
      _width = p.width;
      _dx = p.dx;
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) c.open();
    });
  }

  Future<void> _pick({required bool isFrom}) async {
    final initial = (isFrom ? widget.from : widget.to) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: DateTime(2035),
    );

    if (picked != null) {
      final day = DateTime(picked.year, picked.month, picked.day);
      widget.onChanged(
        isFrom ? day : widget.from,
        isFrom ? widget.to : day,
      );
    }

    // Pastikan panel tetap terbuka setelah date picker ditutup.
    final c = _menuCtrl;
    if (mounted && c != null && !c.isOpen) c.open();
  }

  static String _fmt(DateTime d) =>
      '${d.day.toString().padLeft(2, '0')}/'
      '${d.month.toString().padLeft(2, '0')}/${d.year}';

  Widget _dateField({
    required String label,
    required DateTime? value,
    required VoidCallback onTap,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w500,
            color: AppColors.muted,
            ),
          ),
        const SizedBox(height: 6),
        InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: onTap,
          child: Container(
            height: 44,
            padding: const EdgeInsets.symmetric(horizontal: 14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.line),
              ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    value == null ? 'dd/mm/yyyy' : _fmt(value),
                    style: TextStyle(
                      fontSize: 15,
                      color: value == null ? AppColors.muted : AppColors.ink,
                      ),
                    ),
                  ),
                const Icon(
                  Icons.calendar_today_outlined,
                  size: 18,
                  color: AppColors.muted,
                  ),
              ],
              ),
            ),
          ),
      ],
    );
  }

  Widget _panel() {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _dateField(
            label: 'From',
            value: widget.from,
            onTap: () => _pick(isFrom: true),
            ),
          const SizedBox(height: 12),
          _dateField(
            label: 'To',
            value: widget.to,
            onTap: () => _pick(isFrom: false),
            ),
          const SizedBox(height: 12),
          GestureDetector(
            behavior: HitTestBehavior.opaque,
            onTap: () {
              widget.onChanged(null, null);
              _menuCtrl?.close();
            },
            child: const Text(
              'Clear dates',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: AppColors.brand,
                ),
              ),
            ),
        ],
        ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final active = widget.from != null || widget.to != null;

    return MenuAnchor(
      alignmentOffset: Offset(_dx, 6),
      style: _popoverStyle(_width),
      menuChildren: [
        // Panel tidak memakai MenuItemButton, jadi menu tidak tertutup
        // saat field diketuk.
        SizedBox(width: _width, child: _panel()),
      ],
      builder: (context, controller, _) {
        return filterChip(
          key: _chipKey,
          label: 'Date',
          active: active,
          arrow: true,
          onTap: () => _toggle(controller),
        );
      },
    );
  }
}

class _TransactionRow extends StatelessWidget {
  const _TransactionRow({required this.item});

  final TransactionItem item;

  @override
  Widget build(BuildContext context) {
    final amountColor = item.isIncome ? AppColors.income : AppColors.expense;
    final sign = item.isIncome ? '+' : '-';

    final tags = <Widget>[
      _tag(item.category, _greyTag, AppColors.muted),
      if (item.walletName != null)
        _tag(item.walletName!, AppColors.brandSoft, AppColors.brand),
    ];

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: item.iconBackground,
              borderRadius: BorderRadius.circular(14),
              ),
            child: Icon(item.icon, size: 20, color: item.iconColor),
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
                    fontWeight: FontWeight.w600,
                    color: AppColors.ink,
                    ),
                  ),
                const SizedBox(height: 4),
                Wrap(spacing: 6, runSpacing: 4, children: tags),
              ],
              ),
            ),
          const SizedBox(width: 8),
          Text(
            '$sign${item.amount.text}',
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

  static Widget _tag(String text, Color bg, Color fg) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(6),
        ),
      child: Text(
        text,
        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w500, color: fg),
        ),
    );
  }
}