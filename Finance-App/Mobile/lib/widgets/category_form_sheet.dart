import 'package:flutter/material.dart';
import '../models/category_item.dart';
import '../theme/app_colors.dart';
import 'app_button.dart';

/// Form Add/Edit Category versi mobile.
/// Emoji diletakkan sebagai kotak terakhir di grid ikon, seperti di web.
class CategoryFormSheet extends StatefulWidget {
  const CategoryFormSheet({super.key, this.category, this.onDelete});

  final CategoryItem? category;
  final VoidCallback? onDelete;

  @override
  State<CategoryFormSheet> createState() => _CategoryFormSheetState();
}

class _CategoryFormSheetState extends State<CategoryFormSheet> {
  late final TextEditingController _nameCtrl;
  late CategoryType _type;
  String? _iconKey;
  String? _emoji;
  String? _error;

  bool get _isEdit => widget.category != null;

  @override
  void initState() {
    super.initState();
    final c = widget.category;
    _nameCtrl = TextEditingController(text: c?.name ?? '');
    _type = c?.type ?? CategoryType.income;
    _iconKey = c?.iconKey;
    _emoji = c?.emoji;
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    super.dispose();
  }

  String get _iconLabel {
    if (_emoji != null) return 'Custom emoji';
    final key = _iconKey;
    if (key != null) return categoryIcons[key]?.label ?? 'Choose an icon';
    return 'Choose an icon';
  }

  void _save() {
    final name = _nameCtrl.text.trim();
    if (name.isEmpty) {
      setState(() => _error = 'Category name is required.');
      return;
    }
    if (_emoji == null && _iconKey == null) {
      setState(() => _error = 'Choose an icon or type an emoji.');
      return;
    }

    Navigator.of(context).pop(
      CategoryItem(
        id: widget.category?.id ??
            DateTime.now().microsecondsSinceEpoch.toString(),
        name: name,
        type: _type,
        iconKey: _emoji != null ? null : _iconKey,
        emoji: _emoji,
      ),
    );
  }

  void _delete() {
    Navigator.of(context).pop();
    widget.onDelete?.call();
  }

  InputDecoration _dec({String hint = ''}) {
    final border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppColors.line),
    );
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: AppColors.muted),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      border: border,
      enabledBorder: border,
      focusedBorder: border.copyWith(
        borderSide: const BorderSide(color: AppColors.brand, width: 2),
      ),
    );
  }

  Widget _label(String text) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Text(
          text,
          style: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: AppColors.ink,
          ),
        ),
      );

  Widget _iconTile(String key) {
    final selected = _iconKey == key && _emoji == null;
    final icon = categoryIcons[key]!.icon;

    return GestureDetector(
      onTap: () => setState(() {
        _iconKey = key;
        _emoji = null;
        _error = null;
      }),
      child: Container(
        width: 56,
        height: 56,
        decoration: BoxDecoration(
          color: selected ? AppColors.brandSoft : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: selected ? AppColors.brand : AppColors.line,
            width: selected ? 1.5 : 1,
          ),
        ),
        child: Icon(
          icon,
          size: 22,
          color: selected ? AppColors.brand : AppColors.ink,
        ),
      ),
    );
  }

  /// Kotak emoji, diletakkan setelah deretan ikon bawaan, seperti di web.
  Widget _emojiTile() {
    final selected = _emoji != null;

    return GestureDetector(
      onTap: () async {
        // Buka emoji picker lewat TextField tersembunyi.
        // Di HP, keyboard emoji muncul saat field diketuk.
        final picked = await showDialog<String>(
          context: context,
          builder: (ctx) => _EmojiPickerDialog(initial: _emoji ?? ''),
        );
        if (picked != null) {
          final cleaned = picked.trim();
          if (cleaned.isNotEmpty) {
            setState(() {
              _emoji = cleaned;
              _iconKey = null;
              _error = null;
            });
          } else {
            setState(() => _emoji = null);
          }
        }
      },
      child: Container(
        width: 56,
        height: 56,
        decoration: BoxDecoration(
          color: selected ? AppColors.brandSoft : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: selected ? AppColors.brand : AppColors.line,
            width: selected ? 1.5 : 1,
          ),
        ),
        child: Center(
          child: _emoji != null
              ? Text(_emoji!, style: const TextStyle(fontSize: 24))
              : const Text('😊', style: TextStyle(fontSize: 22)),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: SafeArea(
          top: false,
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        _isEdit ? 'Edit Category' : 'Add Category',
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          color: AppColors.ink,
                        ),
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.of(context).pop(),
                      icon: const Icon(Icons.close_rounded, color: AppColors.ink),
                    ),
                  ],
                ),
                Text(
                  _isEdit
                      ? 'Update the details for your ${widget.category!.name} category.'
                      : 'Create a category to organize your transactions.',
                  style: const TextStyle(fontSize: 13, color: AppColors.muted),
                ),
                const SizedBox(height: 20),

                // Ikon + emoji dalam satu grid
                Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Category icon',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: AppColors.ink,
                        ),
                      ),
                    ),
                    Text(
                      _iconLabel,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.brand,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final key in categoryIcons.keys) _iconTile(key),
                    _emojiTile(),
                  ],
                ),
                const SizedBox(height: 20),

                _label('Category name'),
                TextField(
                  controller: _nameCtrl,
                  onChanged: (_) {
                    if (_error != null) setState(() => _error = null);
                  },
                  decoration: _dec(hint: 'Enter category name'),
                ),
                const SizedBox(height: 16),

                _label('Category type'),
                _CategoryTypeToggle(
                  isIncome: _type == CategoryType.income,
                  onChanged: (inc) => setState(() {
                    _type = inc ? CategoryType.income : CategoryType.expense;
                  }),
                ),

                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    _error!,
                    style: const TextStyle(fontSize: 13, color: AppColors.expense),
                  ),
                ],

                const SizedBox(height: 20),
                AppButton(label: 'Save Category', onPressed: _save),

                if (_isEdit) ...[
                  const SizedBox(height: 10),
                  SizedBox(
                    height: 48,
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: _delete,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.expense,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      child: const Text(
                        'Delete',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _CategoryTypeToggle extends StatelessWidget {
  const _CategoryTypeToggle({
    required this.isIncome,
    required this.onChanged,
  });

  final bool isIncome;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: const Color(0xFFF3F4F6),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Stack(
        children: [
          // Indikator putih yang bergeser kiri-kanan
          AnimatedAlign(
            duration: const Duration(milliseconds: 200),
            curve: Curves.easeInOut,
            alignment: isIncome ? Alignment.centerLeft : Alignment.centerRight,
            child: FractionallySizedBox(
              widthFactor: 0.5,
              child: Container(
                height: 44,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(8),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.08),
                      blurRadius: 6,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
              ),
            ),
          ),
          // Tombol di atas indikator
          Row(
            children: [
              Expanded(
                child: GestureDetector(
                  onTap: () => onChanged(true),
                  child: SizedBox(
                    height: 44,
                    child: Center(
                      child: Text(
                        'Income',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: AppColors.income,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              Expanded(
                child: GestureDetector(
                  onTap: () => onChanged(false),
                  child: SizedBox(
                    height: 44,
                    child: Center(
                      child: Text(
                        'Expense',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: AppColors.expense,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
/// Dialog kecil untuk input emoji. Keyboard emoji di HP terbuka otomatis
/// saat field diketuk. User mengetik emoji lalu tekan OK.
class _EmojiPickerDialog extends StatefulWidget {
  const _EmojiPickerDialog({required this.initial});

  final String initial;

  @override
  State<_EmojiPickerDialog> createState() => _EmojiPickerDialogState();
}

class _EmojiPickerDialogState extends State<_EmojiPickerDialog> {
  late final TextEditingController _ctrl;

  @override
  void initState() {
    super.initState();
    _ctrl = TextEditingController(text: widget.initial);
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      title: const Text(
        'Choose an emoji',
        style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
      ),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text(
            'Tap the field below, then open your emoji keyboard.',
            style: TextStyle(fontSize: 13, color: AppColors.muted),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _ctrl,
            autofocus: true,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 32),
            maxLength: 8,
            buildCounter: (_, {required currentLength, required isFocused, maxLength}) => null,
            decoration: InputDecoration(
              hintText: '😊',
              hintStyle: const TextStyle(fontSize: 32),
              contentPadding: const EdgeInsets.symmetric(vertical: 12),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: AppColors.line),
              ),
            ),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
        ElevatedButton(
          onPressed: () => Navigator.of(context).pop(_ctrl.text),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.dark,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
          ),
          child: const Text('OK'),
        ),
      ],
    );
  }
}
