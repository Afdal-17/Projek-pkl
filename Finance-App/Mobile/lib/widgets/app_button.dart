import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Varian gaya tombol yang konsisten dengan sistem desain web.
enum AppButtonVariant {
  /// Warna gelap (#262626) dengan teks putih (standar utama web).
  primary,

  /// Warna indigo (#4F46E5) dengan teks putih.
  brand,

  /// Latar belakang abu/indigo lembut dengan teks gelap.
  secondary,

  /// Tombol berbingkai border garis halus tanpa latar belakang pekat.
  outlined,

  /// Tombol aksi berisiko/destruktif warna merah (#DC2626) dengan teks putih.
  destructive,
}

/// Tombol terstandarisasi untuk Finance App:
/// - Mengikuti sistem desain web: radius 14px, tinggi 48px, elevasi 0.
/// - Mendukung multi-varian (primary, brand, secondary, outlined, destructive).
/// - Mendukung state loading, disabled, dan full-width / inline.
class AppButton extends StatelessWidget {
  const AppButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.variant = AppButtonVariant.primary,
    this.icon,
    this.background,
    this.foreground,
    this.outlinedBorderColor,
    this.isLoading = false,
    this.isFullWidth = true,
    this.height = 48,
    this.borderRadius = 14,
    this.fontSize = 15,
  });

  final String label;
  final VoidCallback? onPressed;
  final AppButtonVariant variant;
  final IconData? icon;

  /// Override warna background eksplisit (tetap kompatibel dengan kode lama).
  final Color? background;

  /// Override warna foreground teks/icon eksplisit.
  final Color? foreground;

  /// Warna border jika memakai varian [AppButtonVariant.outlined].
  final Color? outlinedBorderColor;

  /// Menampilkan spinner loading saat bernilai true dan menonaktifkan klik.
  final bool isLoading;

  /// Apakah tombol mengisi lebar penuh kontainer (default: true).
  final bool isFullWidth;

  final double height;
  final double borderRadius;
  final double fontSize;

  Color _resolveBackground() {
    if (background != null) return background!;
    switch (variant) {
      case AppButtonVariant.primary:
        return AppColors.dark;
      case AppButtonVariant.brand:
        return AppColors.brand;
      case AppButtonVariant.secondary:
        return AppColors.brandSoft;
      case AppButtonVariant.outlined:
        return Colors.transparent;
      case AppButtonVariant.destructive:
        return AppColors.expense;
    }
  }

  Color _resolveForeground() {
    if (foreground != null) return foreground!;
    switch (variant) {
      case AppButtonVariant.primary:
      case AppButtonVariant.brand:
      case AppButtonVariant.destructive:
        return Colors.white;
      case AppButtonVariant.secondary:
        return AppColors.brand;
      case AppButtonVariant.outlined:
        return AppColors.ink;
    }
  }

  @override
  Widget build(BuildContext context) {
    final effectiveBg = _resolveBackground();
    final effectiveFg = _resolveForeground();
    final isOutlined = variant == AppButtonVariant.outlined;

    final childWidget = Row(
      mainAxisSize: isFullWidth ? MainAxisSize.max : MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        if (isLoading) ...[
          SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(
              strokeWidth: 2.2,
              valueColor: AlwaysStoppedAnimation<Color>(effectiveFg),
            ),
          ),
          const SizedBox(width: 10),
        ] else if (icon != null) ...[
          Icon(icon, size: 20, color: effectiveFg),
          const SizedBox(width: 8),
        ],
        Flexible(
          child: Text(
            label,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: fontSize,
              fontWeight: FontWeight.w700,
              color: effectiveFg,
            ),
          ),
        ),
      ],
    );

    final buttonStyle = ElevatedButton.styleFrom(
      backgroundColor: effectiveBg,
      foregroundColor: effectiveFg,
      disabledBackgroundColor: effectiveBg.withValues(alpha: 0.5),
      disabledForegroundColor: effectiveFg.withValues(alpha: 0.6),
      elevation: 0,
      shadowColor: Colors.transparent,
      padding: const EdgeInsets.symmetric(horizontal: 20),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(borderRadius),
        side: isOutlined
            ? BorderSide(
                color: outlinedBorderColor ?? AppColors.line,
                width: 1.2,
              )
            : BorderSide.none,
      ),
    );

    return SizedBox(
      height: height,
      width: isFullWidth ? double.infinity : null,
      child: ElevatedButton(
        onPressed: isLoading ? null : onPressed,
        style: buttonStyle,
        child: childWidget,
      ),
    );
  }
}
