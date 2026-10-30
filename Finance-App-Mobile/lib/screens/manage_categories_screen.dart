import 'package:flutter/material.dart';
import '../data/transaction_samples.dart';
import '../models/category_item.dart';
import '../theme/app_colors.dart';
import '../widgets/app_button.dart';
import '../widgets/app_card.dart';
import '../widgets/category_form_sheet.dart';
import '../widgets/notification_bell.dart';

const _sortNameAz = 'Name A-Z';
const _sortRecent = 'Recently Added';
const _warnOrange = Color(0xFFEA580C);

class ManageCategoriesScreen extends StatefulWidget {
  const ManageCategoriesScreen({super.key});

  @override
  State<ManageCategoriesScreen> createState() => _ManageCategoriesScreenState();
}

class _ManageCategoriesScreenState extends State<ManageCategoriesScreen> {
  final _items = List<CategoryItem>.of(sampleCategories);
  final _searchCtrl = TextEditingController();
  String _query = '';
  String _sort = _sortNameAz;
  CategoryType? _type; // null = All

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  /// Jumlah transaksi yang memakai kategori ini (dari data contoh).
  int _usedCount(CategoryItem c) =>
      sampleTransactions.where((t) => t.category == c.name).length;

  List<CategoryItem> get _visible {
    final base = _sort == _sortNameAz
        ? ([..._items]
          ..sort((a, b) =>
              a.name.toLowerCase().compareTo(b.name.toLowerCase())))
        : _items.reversed.toList();

    final q = _query.toLowerCase();
    return base.where((c) {
      if (_type != null && c.type != _type) return false;
      if (q.isNotEmpty && !c.name.toLowerCase().contains(q)) return false;
      return true;
    }).toList();
  }

  Future<void> _openForm({CategoryItem? existing}) async {
    final result = await showModalBottomSheet<CategoryItem>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => CategoryFormSheet(
        category: existing,
        onDelete: existing == null ? null : () => _confirmDelete(existing),
      ),
    );

    if (result == null || !mounted) return;

    setState(() {
      if (existing == null) {
        _items.add(result);
      } else {
        final i = _items.indexWhere((c) => c.id == existing.id);
        if (i != -1) _items[i] = result;
      }
    });
  }

  Future<void> _pickSort() async {
    final picked = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(height: 8),
            for (final s in const [_sortNameAz, _sortRecent])
              _sheetOption(
                label: s,
                selected: s == _sort,
                onTap: () => Navigator.of(ctx).pop(s),
              ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (picked != null) setState(() => _sort = picked);
  }

  Widget _sheetOption({
    required String label,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return ListTile(
      tileColor: selected ? AppColors.brandSoft : null,
      title: Text(
        label,
        style: TextStyle(
          fontSize: 15,
          fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
          color: selected ? AppColors.brand : AppColors.ink,
        ),
      ),
      trailing: selected
          ? const Icon(Icons.check_rounded, color: AppColors.brand)
          : null,
      onTap: onTap,
    );
  }

  /// Konfirmasi hapus. Tombol Delete mati jika kategori masih dipakai transaksi.
  Future<void> _confirmDelete(CategoryItem c) async {
    final used = _usedCount(c);

    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Delete Category',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.of(ctx).pop(false),
                    icon: const Icon(Icons.close_rounded, color: AppColors.ink),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: AppColors.expenseSoft,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.delete_outline_rounded,
                  color: AppColors.expense,
                ),
              ),
              const SizedBox(height: 12),
              const Text(
                'Are you sure you want to delete this category?',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ink,
                ),
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.page,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppColors.line),
                ),
                child: Row(
                  children: [
                    _iconBox(c, size: 36),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            c.name,
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                              color: AppColors.ink,
                            ),
                          ),
                          Text(
                            '$used transactions',
                            style: const TextStyle(
                              fontSize: 13,
                              color: AppColors.muted,
                            ),
                          ),
                        ],
                      ),
                    ),
                    _typeBadge(c.type),
                  ],
                ),
              ),
              if (used > 0) ...[
                const SizedBox(height: 12),
                Text(
                  'This category is still used by $used transactions and cannot be deleted. Please update or delete those transactions first.',
                  style: const TextStyle(
                    fontSize: 13,
                    height: 1.5,
                    color: _warnOrange,
                  ),
                ),
              ],
              const SizedBox(height: 16),
              const Divider(height: 1, color: AppColors.line),
              const SizedBox(height: 16),
              SizedBox(
                height: 48,
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: used > 0 ? null : () => Navigator.of(ctx).pop(true),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.expense,
                    foregroundColor: Colors.white,
                    disabledBackgroundColor:
                        AppColors.expense.withValues(alpha: 0.45),
                    disabledForegroundColor: Colors.white,
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
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
                height: 48,
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: () => Navigator.of(ctx).pop(false),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.ink,
                    side: const BorderSide(color: AppColors.line),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: const Text('Cancel'),
                ),
              ),
            ],
          ),
        ),
      ),
    );

    if (ok == true && mounted) {
      setState(() => _items.removeWhere((x) => x.id == c.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    final items = _visible;

    return Scaffold(
      backgroundColor: AppColors.page,
      appBar: AppBar(
        backgroundColor: AppColors.page,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: AppColors.ink),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: const Text(
          'Finance App',
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w800,
            color: AppColors.ink,
          ),
        ),
        actions: const [
          NotificationBellButton(),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
        children: [
          const Text(
            'Manage Categories',
            style: TextStyle(
              fontSize: 26,
              fontWeight: FontWeight.w800,
              color: AppColors.ink,
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Create and manage your transaction categories.',
            style: TextStyle(fontSize: 14, color: AppColors.muted),
          ),
          const SizedBox(height: 16),
          AppButton(
            label: 'Add Category',
            icon: Icons.add_rounded,
            onPressed: () => _openForm(),
          ),
          const SizedBox(height: 16),
          _filterCard(items.length),
          const SizedBox(height: 16),
          ..._cards(items),
        ],
      ),
    );
  }

  Widget _filterCard(int count) {
    return AppCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: _searchCtrl,
            onChanged: (v) => setState(() => _query = v),
            decoration: InputDecoration(
              hintText: 'Search categories...',
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
          const SizedBox(height: 12),
          InkWell(
            borderRadius: BorderRadius.circular(12),
            onTap: _pickSort,
            child: Container(
              height: 48,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppColors.line),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      _sort,
                      style: const TextStyle(
                        fontSize: 15,
                        color: AppColors.ink,
                      ),
                    ),
                  ),
                  const Icon(
                    Icons.keyboard_arrow_down_rounded,
                    color: AppColors.ink,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _chip(
                label: 'All',
                active: _type == null,
                dark: true,
                onTap: () => setState(() => _type = null),
              ),
              const SizedBox(width: 8),
              _chip(
                label: 'Income',
                active: _type == CategoryType.income,
                onTap: () => setState(() => _type = CategoryType.income),
              ),
              const SizedBox(width: 8),
              _chip(
                label: 'Expense',
                active: _type == CategoryType.expense,
                onTap: () => setState(() => _type = CategoryType.expense),
              ),
              const Spacer(),
              Text(
                '$count results',
                style: const TextStyle(fontSize: 13, color: AppColors.muted),
              ),
            ],
          ),
        ],
      ),
    );
  }

  List<Widget> _cards(List<CategoryItem> items) {
    if (items.isEmpty) {
      return [
        const AppCard(
          child: Padding(
            padding: EdgeInsets.symmetric(vertical: 24),
            child: Center(
              child: Text(
                'No categories match your filters.',
                style: TextStyle(fontSize: 14, color: AppColors.muted),
              ),
            ),
          ),
        ),
      ];
    }

    return [
      for (final c in items) ...[
        _categoryCard(c),
        const SizedBox(height: 12),
      ],
    ];
  }

  Widget _categoryCard(CategoryItem c) {
    final used = _usedCount(c);

    return AppCard(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
            child: Row(
              children: [
                _iconBox(c),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    c.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: AppColors.ink,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                _typeBadge(c.type),
              ],
            ),
          ),
          const Divider(height: 1, color: AppColors.line),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    'Used: $used transactions',
                    style: const TextStyle(fontSize: 13, color: AppColors.muted),
                  ),
                ),
                _actionPill(
                  icon: Icons.edit_outlined,
                  label: 'Edit',
                  bg: AppColors.brandSoft,
                  fg: AppColors.brand,
                  onTap: () => _openForm(existing: c),
                ),
                const SizedBox(width: 8),
                _actionPill(
                  icon: Icons.delete_outline_rounded,
                  label: 'Delete',
                  bg: AppColors.expenseSoft,
                  fg: AppColors.expense,
                  onTap: () => _confirmDelete(c),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _iconBox(CategoryItem c, {double size = 44}) {
    final bg = c.isIncome ? AppColors.incomeSoft : AppColors.expenseSoft;
    final fg = c.isIncome ? AppColors.income : AppColors.expense;

    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(14),
      ),
      child: c.emoji != null
          ? Text(c.emoji!, style: TextStyle(fontSize: size * 0.5))
          : Icon(
              categoryIcons[c.iconKey]?.icon ?? Icons.category_outlined,
              size: size * 0.5,
              color: fg,
            ),
    );
  }

  Widget _typeBadge(CategoryType t) {
    final inc = t == CategoryType.income;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: inc ? AppColors.incomeSoft : AppColors.expenseSoft,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        inc ? 'Income' : 'Expense',
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: inc ? AppColors.income : AppColors.expense,
        ),
      ),
    );
  }

  Widget _actionPill({
    required IconData icon,
    required String label,
    required Color bg,
    required Color fg,
    required VoidCallback onTap,
  }) {
    return InkWell(
      borderRadius: BorderRadius.circular(10),
      onTap: onTap,
      child: Container(
        height: 34,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 15, color: fg),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: fg,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _chip({
    required String label,
    required bool active,
    required VoidCallback onTap,
    bool dark = false,
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
        alignment: Alignment.center,
        child: Text(
          label,
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: fg,
          ),
        ),
      ),
    );
  }

  InputBorder _inputBorder({Color color = AppColors.line, double width = 1}) {
    return OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: BorderSide(color: color, width: width),
    );
  }
}