import 'package:flutter/material.dart';
import '../models/transfer_user.dart';

/// Service untuk penanganan proses transfer antar dompet atau antar pengguna.
/// Berisi data dummy sesuai dengan tampilan web.
class TransferService {
  TransferService._();

  static const List<TransferUser> dummyUsers = [
    TransferUser(
      id: '1',
      name: 'Dzaky',
      email: 'dzaky@gmail.com',
      avatar: Icons.person_outline_rounded,
      verified: false,
      walletName: null,
      statusText: "Don't have a wallet",
    ),
    TransferUser(
      id: '2',
      name: '25_ Muhammad Rofi...',
      email: 'rofi25@gmail.com',
      avatar: Icons.person_outline_rounded,
      verified: false,
      walletName: null,
      statusText: "Don't have a wallet",
    ),
    TransferUser(
      id: '3',
      name: 'Rofi_ Ngeditz',
      email: 'rofi@madtive.com',
      avatar: Icons.person_rounded,
      verified: true,
      walletName: 'SHOPEE',
      statusText: 'SHOPEE',
    ),
    TransferUser(
      id: '4',
      name: '07 Dzaky Fadhillah Ra...',
      email: 'dzaky07@gmail.com',
      avatar: Icons.person_outline_rounded,
      verified: false,
      walletName: null,
      statusText: 'Inactive a wallet',
    ),
    TransferUser(
      id: '5',
      name: 'Chris Simatupang',
      email: 'chris@gmail.com',
      avatar: Icons.person_outline_rounded,
      verified: false,
      walletName: null,
      statusText: 'Inactive a wallet',
    ),
  ];

  /// Cari pengguna berdasarkan nama atau email.
  static List<TransferUser> searchUsers(String query) {
    if (query.isEmpty) return dummyUsers;
    final q = query.toLowerCase();
    return dummyUsers.where((u) {
      return u.name.toLowerCase().contains(q) ||
          u.email.toLowerCase().contains(q) ||
          (u.walletName ?? '').toLowerCase().contains(q);
    }).toList();
  }

  /// Transfer antar dompet milik pengguna sendiri.
  static Future<bool> transferWallet({
    required String fromWallet,
    required String toWallet,
    required double amount,
  }) async {
    await Future.delayed(const Duration(milliseconds: 500));
    return true;
  }

  /// Transfer ke dompet pengguna lain.
  static Future<bool> transferUser({
    required String fromWallet,
    required String toUserId,
    required String toWalletName,
    required double amount,
  }) async {
    await Future.delayed(const Duration(milliseconds: 500));
    return true;
  }
}
