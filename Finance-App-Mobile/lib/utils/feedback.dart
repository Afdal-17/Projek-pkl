import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

enum PopupType { info, warning, error, success }

/// Displays an elegant centered modal popup dialog instead of a bottom notification.
void showAppPopup(
  BuildContext context,
  String message, {
  String? title,
  PopupType type = PopupType.warning,
  VoidCallback? onConfirm,
}) {
  final cleanMsg = _translateToEnglish(message);
  final resolvedTitle = title ?? _defaultTitleFor(type, cleanMsg);

  showDialog<void>(
    context: context,
    barrierDismissible: true,
    builder: (ctx) {
      Color iconBg;
      Color iconColor;
      IconData icon;

      switch (type) {
        case PopupType.error:
          iconBg = const Color(0xFFFEE2E2);
          iconColor = const Color(0xFFDC2626);
          icon = Icons.error_outline_rounded;
          break;
        case PopupType.success:
          iconBg = const Color(0xFFDCFCE7);
          iconColor = const Color(0xFF16A34A);
          icon = Icons.check_circle_outline_rounded;
          break;
        case PopupType.warning:
          iconBg = const Color(0xFFFEF3C7);
          iconColor = const Color(0xFFD97706);
          icon = Icons.warning_amber_rounded;
          break;
        case PopupType.info:
          iconBg = const Color(0xFFEEF2FF);
          iconColor = AppColors.brand;
          icon = Icons.info_outline_rounded;
          break;
      }

      return Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        backgroundColor: Colors.white,
        elevation: 10,
        insetPadding: const EdgeInsets.symmetric(horizontal: 28, vertical: 24),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(22, 24, 22, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: iconBg,
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, color: iconColor, size: 28),
              ),
              const SizedBox(height: 16),
              Text(
                resolvedTitle,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ink,
                  letterSpacing: -0.3,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text(
                cleanMsg,
                style: const TextStyle(
                  fontSize: 14,
                  color: AppColors.muted,
                  height: 1.45,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 22),
              SizedBox(
                width: double.infinity,
                height: 46,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.of(ctx).pop();
                    onConfirm?.call();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.ink,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: const Text(
                    'Got it',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    },
  );
}

/// Alias for [showAppPopup] ensuring backwards compatibility with existing callers.
void showComingSoon(
  BuildContext context,
  String message, {
  String? title,
  PopupType type = PopupType.warning,
}) {
  showAppPopup(context, message, title: title, type: type);
}

String _defaultTitleFor(PopupType type, String message) {
  final lower = message.toLowerCase();
  if (lower.contains('insufficient') || lower.contains('mencukupi') || lower.contains('balance')) {
    return 'Insufficient Balance';
  }
  if (lower.contains('wallet') || lower.contains('dompet')) {
    return 'Wallet Notice';
  }
  if (lower.contains('target') || lower.contains('saving') || lower.contains('tabungan')) {
    return 'Savings Goal';
  }
  if (lower.contains('transfer')) {
    return 'Transfer Notice';
  }
  switch (type) {
    case PopupType.error:
      return 'Action Failed';
    case PopupType.success:
      return 'Success';
    case PopupType.warning:
      return 'Notice';
    case PopupType.info:
      return 'Information';
  }
}

String _translateToEnglish(String raw) {
  final text = raw.trim();

  if (text.contains('tidak mencukupi untuk transaksi ini')) {
    return 'Insufficient wallet balance for this transaction.';
  }
  if (text.contains('Saldo dompet asal tidak mencukupi')) {
    return 'Insufficient source wallet balance.';
  }
  if (text.contains('tidak mencukupi')) {
    return 'Insufficient balance for this action.';
  }
  if (text.contains('baru saja dicatat')) {
    return 'This entry was recently recorded. Please wait a moment before trying again.';
  }
  if (text.contains('Target tabungan berhasil dibuat')) {
    return 'Savings goal successfully created.';
  }
  if (text.contains('Target tabungan berhasil diperbarui')) {
    return 'Savings goal successfully updated.';
  }
  if (text.contains('Target tabungan berhasil dihapus')) {
    return 'Savings goal successfully deleted.';
  }
  if (text.contains('Dompet berhasil dibuat') || text.contains('berhasil ditambahkan')) {
    return 'Wallet successfully created.';
  }
  if (text.contains('Dompet berhasil diperbarui')) {
    return 'Wallet successfully updated.';
  }
  if (text.contains('Dompet berhasil dihapus')) {
    return 'Wallet successfully deleted.';
  }
  if (text.contains('Transaksi berhasil')) {
    return 'Transaction successfully created.';
  }
  if (text.contains('Transfer berhasil')) {
    return 'Transfer completed successfully.';
  }
  if (text.contains('Initial savings tidak boleh melebihi')) {
    return 'Initial savings cannot exceed wallet balance or target amount.';
  }
  if (text.contains('Silakan masukkan nama target dan nominal target')) {
    return 'Please enter target name and target amount.';
  }
  if (text.contains('Silakan pilih dompet terlebih dahulu')) {
    return 'Please select a wallet first.';
  }
  if (text.contains('Gagal membuat target tabungan')) {
    return 'Failed to create savings goal.';
  }
  if (text.contains('Gagal menambahkan dompet ke server')) {
    return 'Failed to add wallet to server.';
  }
  if (text.contains('Gagal memperbarui dompet')) {
    return 'Failed to update wallet.';
  }
  if (text.contains('Koneksi gagal:')) {
    return text.replaceAll('Koneksi gagal:', 'Connection failed:');
  }
  if (text.contains('tidak ditemukan') ||
      text.contains('registrasi terlebih dahulu') ||
      text.contains('belum terdaftar')) {
    return 'Account not found. Please sign up first.';
  }
  if (text.contains('Target class') || text.contains('does not exist')) {
    return 'Server configuration error: Controller not found. Please report to backend administrator.';
  }
  if (text.contains('Silakan masukkan email') || text.contains('masukkan email')) {
    return 'Please enter your email or phone number.';
  }
  if (text.contains('Kredensial') || text.contains('tidak cocok')) {
    return 'Invalid email or password.';
  }
  if (text.contains('Kata sandi salah') || text.contains('password salah')) {
    return 'Incorrect password.';
  }
  if (text.contains('Mata uang berhasil diubah')) {
    return 'Currency changed successfully.';
  }
  if (text.contains('Profil berhasil diperbarui')) {
    return 'Profile updated successfully.';
  }
  if (text.contains('Password berhasil diubah') || text.contains('Kata sandi berhasil diubah')) {
    return 'Password changed successfully.';
  }
  if (text.contains('Kategori berhasil ditambahkan')) {
    return 'Category created successfully.';
  }
  if (text.contains('Kategori berhasil diperbarui')) {
    return 'Category updated successfully.';
  }
  if (text.contains('Kategori berhasil dihapus')) {
    return 'Category deleted successfully.';
  }
  if (text.contains('Akun user telah diaktifkan kembali')) {
    return 'User account has been reactivated.';
  }
  if (text.contains('Akun user telah diblokir')) {
    return 'User account has been blocked.';
  }
  if (text.contains('Tidak ada perubahan status')) {
    return 'No status change.';
  }
  if (text.contains('Admin tidak dapat menonaktifkan akunnya sendiri') ||
      text.contains('Admin tidak dapat memblokir akunnya sendiri')) {
    return 'Admin cannot modify or block their own account.';
  }
  if (text.contains('Status akun admin tidak dapat diubah') ||
      text.contains('Akun admin tidak dapat diblokir')) {
    return 'Admin account status cannot be modified.';
  }
  if (text.contains('Anda tidak memiliki akses')) {
    return 'You do not have access to this page.';
  }
  if (text.contains('Email sudah terdaftar') || text.contains('sudah digunakan')) {
    return 'This email address is already registered.';
  }
  if (text.contains('Nomor telepon sudah terdaftar') || text.contains('telepon sudah')) {
    return 'This phone number is already registered.';
  }

  return text;
}
