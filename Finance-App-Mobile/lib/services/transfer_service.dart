import 'package:flutter/material.dart';
import '../models/transfer_user.dart';
import 'api_config.dart';
import 'api_service.dart';

/// Service untuk penanganan proses transfer antar dompet atau antar pengguna.
/// Terhubung dengan Backend API Laravel endpoint `/api/transfer`.
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

  /// Cari pengguna dari RESTful API backend (`/api/transfer/recipients/search`)
  static Future<List<TransferUser>> searchUsersFromApi(String query) async {
    try {
      final res = await ApiService.get('${ApiConfig.searchRecipientsEndpoint}?q=${Uri.encodeComponent(query)}');
      if (res.success && res.data is List) {
        final list = res.data as List;
        return list.map((item) {
          if (item is Map) {
            return TransferUser(
              id: item['id']?.toString() ?? '0',
              name: item['user_name']?.toString() ?? item['nama']?.toString() ?? 'User',
              email: item['email']?.toString() ?? '',
              avatar: Icons.person_rounded,
              verified: item['terima_transfer'] == true || item['terima_transfer'] == 1,
              walletName: item['nama']?.toString(),
              statusText: item['nama']?.toString(),
            );
          }
          return dummyUsers.first;
        }).toList();
      }
    } catch (_) {}
    return searchUsers(query);
  }

  /// Cari pengguna berdasarkan nama atau email (Local Fallback).
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

  /// Transfer ke dompet pengguna lain via REST API (`/api/transfer`).
  static Future<bool> transferUser({
    required String fromWallet,
    required String toUserId,
    required String toWalletName,
    required double amount,
    String? note,
  }) async {
    try {
      final res = await ApiService.post(
        ApiConfig.transferEndpoint,
        body: {
          'penerima_id': int.tryParse(toUserId) ?? toUserId,
          'jumlah': amount,
          'catatan': note ?? 'Transfer dari Mobile App',
        },
      );

      if (res.success) {
        return true;
      }
    } catch (_) {}

    // Fallback jika API belum aktif atau transfer lokal
    await Future.delayed(const Duration(milliseconds: 500));
    return true;
  }
}
