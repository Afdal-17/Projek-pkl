import 'package:flutter/material.dart';
import '../models/wallet.dart';
import '../theme/app_colors.dart';
import '../utils/amount_format.dart';
import 'app_button.dart';
import 'color_picker_field.dart';
import 'wallet_type_field.dart';

class WalletFormResult {
  const WalletFormResult({
    required this.name,
    required this.type,
    required this.balance,
    required this.color,
  });

  final String name;
  final String type;
  final double balance;
  final Color color;
}

const _walletTypes = ['Physical wallet', 'Digital wallet', 'Bank account'];

/// Form Add/Edit Wallet versi mobile, meniru modal di web.
/// Jika [wallet] null, form dipakai untuk menambah dompet baru.
/// [onDelete] hanya dipakai saat edit. Form ditutup dulu, lalu callback dipanggil.
class WalletFormSheet extends StatefulWidget {
  const WalletFormSheet({super.key, this.wallet, this.onDelete});

  final Wallet? wallet;
  final VoidCallback? onDelete;

  @override
  State<WalletFormSheet> createState() => _WalletFormSheetState();
}

class _WalletFormSheetState extends State<WalletFormSheet> {
  late final TextEditingController _nameCtrl;
  late final TextEditingController _currentCtrl;
  late final TextEditingController _initialCtrl;
  late String _type;
  late Color _color;
  String? _error;

  bool get _isEdit => widget.wallet != null;

  @override
  void initState() {
    super.initState();
    final w = widget.wallet;
    _nameCtrl = TextEditingController(text: w?.name ?? '');
    _currentCtrl = TextEditingController(
      text: w == null ? '' : AmountFormat.fromDouble(w.balanceIdr),
    );
    _initialCtrl = TextEditingController();
    _type = (w != null && _walletTypes.contains(w.type))
        ? w.type
        : _walletTypes.first;
    _color = w?.color ?? const Color(0xFF4F46E5);
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _currentCtrl.dispose();
    _initialCtrl.dispose();
    super.dispose();
  }

  void _save() {
    final name = _nameCtrl.text.trim();
    if (name.isEmpty) {
      setState(() => _error = 'Wallet name is required.');
      return;
    }

    final current = AmountFormat.parse(_currentCtrl.text.trim());
    final initial = AmountFormat.parse(_initialCtrl.text.trim());

    // Tambah dompet: pakai Current Balance, kalau kosong pakai Initial Balance.
    // Edit dompet: pakai Current Balance.
    final hasCurrent = _currentCtrl.text.trim().isNotEmpty;
    final balance = (!_isEdit && !hasCurrent) ? initial : current;

    Navigator.of(context).pop(
      WalletFormResult(
        name: name,
        type: _type,
        balance: balance,
        color: _color,
      ),
    );
  }

  void _delete() {
    Navigator.of(context).pop();
    widget.onDelete?.call();
  }

  InputDecoration _dec({String hint = '', String? prefix}) {
    final border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppColors.line),
    );
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: AppColors.muted),
      prefixText: prefix,
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
                        _isEdit ? 'Edit Wallet' : 'Add Wallet',
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
                      ? 'Update the details for this wallet.'
                      : 'Create a new wallet to track your money.',
                  style: const TextStyle(fontSize: 13, color: AppColors.muted),
                ),
                const SizedBox(height: 20),

                _label('Wallet name'),
                TextField(
                  controller: _nameCtrl,
                  onChanged: (_) {
                    if (_error != null) setState(() => _error = null);
                  },
                  decoration: _dec(hint: 'e.g. BCA'),
                ),
                const SizedBox(height: 16),

                _label('Wallet type'),
                WalletTypeField(
                  value: _type,
                  options: _walletTypes,
                  onChanged: (v) => setState(() => _type = v),
                ),
                const SizedBox(height: 16),

                _label('Current Balance'),
                TextField(
                  controller: _currentCtrl,
                  keyboardType:
                      const TextInputType.numberWithOptions(decimal: true),
                  inputFormatters: [AmountInputFormatter()],
                  decoration: _dec(hint: '0'),
                ),

                // Hanya saat tambah dompet, sama seperti di web
                if (!_isEdit) ...[
                  const SizedBox(height: 16),
                  _label('Initial Balance (Optional)'),
                  TextField(
                    controller: _initialCtrl,
                    keyboardType:
                        const TextInputType.numberWithOptions(decimal: true),
                    inputFormatters: [AmountInputFormatter()],
                    decoration: _dec(hint: '0'),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Only fill if this is a new wallet with an initial balance.',
                    style: TextStyle(fontSize: 12, color: AppColors.muted),
                  ),
                ],
                const SizedBox(height: 16),

                _label('Wallet color'),
                ColorPickerField(
                  color: _color,
                  onChanged: (c) => setState(() => _color = c),
                ),

                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    _error!,
                    style: const TextStyle(
                      fontSize: 13,
                      color: AppColors.expense,
                    ),
                  ),
                ],

                const SizedBox(height: 20),
                Row(
                  children: [
                    Expanded(
                      child: SizedBox(
                        height: 48,
                        child: OutlinedButton(
                          onPressed: () => Navigator.of(context).pop(),
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
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: AppButton(
                        label: _isEdit ? 'Save changes' : 'Add Wallet',
                        onPressed: _save,
                      ),
                    ),
                  ],
                ),

                // Delete hanya tampil saat edit, seperti di web
                if (_isEdit) ...[
                  const SizedBox(height: 12),
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