import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Menampilkan modal dialog konfirmasi terstandarisasi.
/// Mengembalikan `true` jika pengguna menekan konfirmasi, atau `false`/`null` jika dibatalkan.
Future<bool?> showAppConfirmDialog({
  required BuildContext context,
  required String title,
  required String message,
  String confirmLabel = 'Confirm',
  String cancelLabel = 'Cancel',
  bool isDestructive = false,
  IconData? icon,
  Color? iconColor,
  Color? badgeBackground,
  Widget? customContent,
}) {
  return showDialog<bool>(
    context: context,
    builder: (ctx) => AppConfirmDialog(
      title: title,
      message: message,
      confirmLabel: confirmLabel,
      cancelLabel: cancelLabel,
      isDestructive: isDestructive,
      icon: icon,
      iconColor: iconColor,
      badgeBackground: badgeBackground,
      customContent: customContent,
      onConfirm: () => Navigator.of(ctx).pop(true),
      onCancel: () => Navigator.of(ctx).pop(false),
    ),
  );
}

/// Widget dialog konfirmasi terstandarisasi:
/// - Menggunakan [Dialog] dengan radius sudut 20px sesuai style gambar 2.
/// - Top icon badge di kiri atas + tombol close di kanan atas.
/// - Mendukung mode destruktif (merah) dan mode reguler (brand).
/// - Tombol utama (merah/brand) dan Cancel tersusun vertikal penuh.
class AppConfirmDialog extends StatelessWidget {
  const AppConfirmDialog({
    super.key,
    required this.title,
    required this.message,
    this.confirmLabel = 'Confirm',
    this.cancelLabel = 'Cancel',
    this.isDestructive = false,
    this.icon,
    this.iconColor,
    this.badgeBackground,
    this.customContent,
    this.onConfirm,
    this.onCancel,
  });

  final String title;
  final String message;
  final String confirmLabel;
  final String cancelLabel;
  final bool isDestructive;
  final IconData? icon;
  final Color? iconColor;
  final Color? badgeBackground;
  final Widget? customContent;
  final VoidCallback? onConfirm;
  final VoidCallback? onCancel;

  @override
  Widget build(BuildContext context) {
    final effectiveIcon = icon ??
        (isDestructive
            ? Icons.delete_outline_rounded
            : Icons.help_outline_rounded);

    final effectiveIconColor = iconColor ??
        (isDestructive ? AppColors.expense : AppColors.brand);

    final effectiveBadgeBg = badgeBackground ??
        (isDestructive ? AppColors.expenseSoft : AppColors.brandSoft);

    return Dialog(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
      ),
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.transparent,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(22, 22, 22, 18),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: effectiveBadgeBg,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    effectiveIcon,
                    color: effectiveIconColor,
                    size: 24,
                  ),
                ),
                const Spacer(),
                GestureDetector(
                  onTap: onCancel ?? () => Navigator.of(context).pop(false),
                  child: Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      color: const Color(0xFFF3F4F6),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(
                      Icons.close_rounded,
                      size: 18,
                      color: AppColors.ink,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Text(
              title,
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              message,
              style: const TextStyle(
                fontSize: 14,
                color: AppColors.muted,
                height: 1.5,
              ),
            ),
            if (customContent != null) ...[
              const SizedBox(height: 12),
              customContent!,
            ],
            const SizedBox(height: 22),
            SizedBox(
              height: 46,
              child: ElevatedButton(
                onPressed: onConfirm ?? () => Navigator.of(context).pop(true),
                style: ElevatedButton.styleFrom(
                  backgroundColor:
                      isDestructive ? AppColors.expense : AppColors.brand,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: Text(
                  confirmLabel,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              height: 46,
              child: OutlinedButton(
                onPressed: onCancel ?? () => Navigator.of(context).pop(false),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.ink,
                  side: const BorderSide(color: Color(0xFFE5E7EB)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  elevation: 0,
                ),
                child: Text(
                  cancelLabel,
                  style: const TextStyle(
                    fontSize: 15,
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
}
