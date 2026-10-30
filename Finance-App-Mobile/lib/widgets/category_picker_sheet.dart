import 'package:flutter/material.dart';
import '../models/category_item.dart';
import '../theme/app_colors.dart';

/// Membuka modal bottom sheet untuk memilih kategori transaksi.
/// Mengembalikan objek [CategoryItem] yang dipilih, atau `null` jika dibatalkan.
Future<CategoryItem?> showCategoryPickerSheet({
  required BuildContext context,
  required List<CategoryItem> categories,
  CategoryItem? selectedCategory,
  String title = 'Select Category',
  bool? isIncome,
  VoidCallback? onManageCategories,
}) {
  final filtered = isIncome != null
      ? categories.where((c) => c.isIncome == isIncome).toList()
      : categories;

  return showModalBottomSheet<CategoryItem>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (ctx) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Drag handle
            Center(
              child: Container(
                width: 38,
                height: 4,
                margin: const EdgeInsets.only(bottom: 16),
                decoration: BoxDecoration(
                  color: const Color(0xFFD1D5DB),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),

            // Header title & manage link
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: AppColors.ink,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        isIncome == null
                            ? 'Choose an income or expense category'
                            : (isIncome
                                ? 'Choose an income category'
                                : 'Choose an expense category'),
                        style: const TextStyle(
                          fontSize: 13,
                          color: AppColors.muted,
                        ),
                      ),
                    ],
                  ),
                ),
                if (onManageCategories != null) ...[
                  TextButton.icon(
                    onPressed: () {
                      Navigator.of(ctx).pop();
                      onManageCategories();
                    },
                    icon: const Icon(Icons.settings_outlined, size: 16),
                    label: const Text('Manage'),
                    style: TextButton.styleFrom(
                      foregroundColor: AppColors.brand,
                      textStyle: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
                IconButton(
                  onPressed: () => Navigator.of(ctx).pop(),
                  icon: const Icon(Icons.close_rounded, color: AppColors.muted),
                  visualDensity: VisualDensity.compact,
                ),
              ],
            ),
            const SizedBox(height: 16),

            // Category list
            if (filtered.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 32),
                child: Center(
                  child: Text(
                    'No categories found',
                    style: TextStyle(color: AppColors.muted, fontSize: 14),
                  ),
                ),
              )
            else
              ConstrainedBox(
                constraints: BoxConstraints(
                  maxHeight: MediaQuery.of(context).size.height * 0.45,
                ),
                child: ListView.separated(
                  shrinkWrap: true,
                  itemCount: filtered.length,
                  separatorBuilder: (context, index) => const SizedBox(height: 8),
                  itemBuilder: (ctx, index) {
                    final item = filtered[index];
                    final isSelected = selectedCategory?.id == item.id;

                    Widget iconWidget;
                    if (item.emoji != null && item.emoji!.isNotEmpty) {
                      iconWidget = Text(
                        item.emoji!,
                        style: const TextStyle(fontSize: 18),
                      );
                    } else if (item.iconKey != null &&
                        categoryIcons.containsKey(item.iconKey!)) {
                      iconWidget = Icon(
                        categoryIcons[item.iconKey!]!.icon,
                        size: 20,
                        color: item.isIncome
                            ? AppColors.income
                            : AppColors.expense,
                      );
                    } else {
                      iconWidget = Icon(
                        item.isIncome
                            ? Icons.arrow_downward_rounded
                            : Icons.arrow_upward_rounded,
                        size: 20,
                        color: item.isIncome
                            ? AppColors.income
                            : AppColors.expense,
                      );
                    }

                    return Material(
                      color: isSelected
                          ? (item.isIncome
                              ? AppColors.incomeSoft
                              : AppColors.expenseSoft)
                          : const Color(0xFFF9FAFB),
                      borderRadius: BorderRadius.circular(14),
                      child: InkWell(
                        onTap: () => Navigator.of(ctx).pop(item),
                        borderRadius: BorderRadius.circular(14),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 12,
                          ),
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(
                              color: isSelected
                                  ? (item.isIncome
                                      ? AppColors.income
                                      : AppColors.expense)
                                  : const Color(0xFFE5E7EB),
                              width: isSelected ? 1.5 : 1,
                            ),
                          ),
                          child: Row(
                            children: [
                              Container(
                                width: 38,
                                height: 38,
                                decoration: BoxDecoration(
                                  color: item.isIncome
                                      ? AppColors.incomeSoft
                                      : AppColors.expenseSoft,
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                alignment: Alignment.center,
                                child: iconWidget,
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Text(
                                  item.name,
                                  style: TextStyle(
                                    fontSize: 15,
                                    fontWeight: isSelected
                                        ? FontWeight.w700
                                        : FontWeight.w600,
                                    color: AppColors.ink,
                                  ),
                                ),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 3,
                                ),
                                decoration: BoxDecoration(
                                  color: item.isIncome
                                      ? AppColors.incomeSoft
                                      : AppColors.expenseSoft,
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  item.isIncome ? 'Income' : 'Expense',
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                    color: item.isIncome
                                        ? AppColors.income
                                        : AppColors.expense,
                                  ),
                                ),
                              ),
                              if (isSelected) ...[
                                const SizedBox(width: 8),
                                Icon(
                                  Icons.check_circle_rounded,
                                  color: item.isIncome
                                      ? AppColors.income
                                      : AppColors.expense,
                                  size: 20,
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    ),
  );
}

/// Field selector kategori yang dapat diklik untuk membuka [showCategoryPickerSheet].
class CategorySelectorField extends StatelessWidget {
  const CategorySelectorField({
    super.key,
    required this.selectedCategory,
    required this.categories,
    required this.onChanged,
    this.label = 'Category',
    this.isIncome,
    this.onManageCategories,
  });

  final CategoryItem? selectedCategory;
  final List<CategoryItem> categories;
  final ValueChanged<CategoryItem> onChanged;
  final String label;
  final bool? isIncome;
  final VoidCallback? onManageCategories;

  @override
  Widget build(BuildContext context) {
    final cat = selectedCategory;

    Widget? leadingIcon;
    if (cat != null) {
      if (cat.emoji != null && cat.emoji!.isNotEmpty) {
        leadingIcon = Text(cat.emoji!, style: const TextStyle(fontSize: 16));
      } else if (cat.iconKey != null &&
          categoryIcons.containsKey(cat.iconKey!)) {
        leadingIcon = Icon(
          categoryIcons[cat.iconKey!]!.icon,
          size: 16,
          color: cat.isIncome ? AppColors.income : AppColors.expense,
        );
      }
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (label.isNotEmpty) ...[
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                label,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ink,
                ),
              ),
              if (onManageCategories != null) ...[
                InkWell(
                  onTap: onManageCategories,
                  child: const Text(
                    'Manage categories',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.brand,
                    ),
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 8),
        ],
        Material(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          child: InkWell(
            onTap: () async {
              final picked = await showCategoryPickerSheet(
                context: context,
                categories: categories,
                selectedCategory: cat,
                isIncome: isIncome,
                onManageCategories: onManageCategories,
              );
              if (picked != null) {
                onChanged(picked);
              }
            },
            borderRadius: BorderRadius.circular(12),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppColors.line),
              ),
              child: Row(
                children: [
                  if (cat != null) ...[
                    Container(
                      width: 28,
                      height: 28,
                      decoration: BoxDecoration(
                        color: cat.isIncome
                            ? AppColors.incomeSoft
                            : AppColors.expenseSoft,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      alignment: Alignment.center,
                      child: leadingIcon,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        cat.name,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                          color: AppColors.ink,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ] else ...[
                    const Expanded(
                      child: Text(
                        'Select category',
                        style: TextStyle(
                          fontSize: 15,
                          color: AppColors.muted,
                        ),
                      ),
                    ),
                  ],
                  const Icon(
                    Icons.keyboard_arrow_down_rounded,
                    color: AppColors.muted,
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

