import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import 'app_logo.dart';
import 'notification_bell.dart';

/// Top Header dengan efek bayangan dan border bawah (seperti versi web di logo.blade.php & navbar web).
/// Menggunakan latar putih (#FFFFFF) dengan drop shadow lembut di atas latar abu (#F5F5F5) halaman,
/// sehingga header terpisah jelas dan tidak kelihatan menyatu dengan halaman.
class AppHeader extends StatelessWidget implements PreferredSizeWidget {
  final Widget? leading;
  final Widget? title;
  final Widget? action;
  final bool showBackButton;

  const AppHeader({
    super.key,
    this.leading,
    this.title,
    this.action,
    this.showBackButton = false,
  });

  @override
  Size get preferredSize => const Size.fromHeight(60);

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: Colors.white,
        border: const Border(
          bottom: BorderSide(
            color: Color(0xFFE5E7EB), // border-b line
            width: 1,
          ),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: SafeArea(
        bottom: false,
        child: SizedBox(
          height: 60,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Row(
              children: [
                if (showBackButton) ...[
                  IconButton(
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                    icon: const Icon(Icons.arrow_back_rounded, color: AppColors.ink),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                  const SizedBox(width: 12),
                ] else if (leading != null) ...[
                  leading!,
                  const SizedBox(width: 12),
                ],
                title ?? const AppBrandHeader(),
                const Spacer(),
                action ?? const NotificationBellButton(),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
