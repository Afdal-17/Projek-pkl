import 'package:flutter/material.dart';
import '../models/transfer_user.dart';
import '../utils/currency.dart';
import 'api_config.dart';
import 'api_service.dart';
import 'auth_service.dart';

/// Service untuk penanganan proses transfer antar dompet atau antar pengguna.
/// Terhubung dengan RESTful API Backend Laravel endpoint `/api/transfer`.
class TransferService {
  TransferService._();

  /// Cari pengguna dari RESTful API backend (`/api/transfer/recipients/search?query=...`)
  static Future<List<TransferUser>> searchUsersFromApi(String query) async {
    final trimmed = query.trim();
    if (trimmed.isEmpty) return [];

    try {
      final res = await ApiService.get(
        '${ApiConfig.searchRecipientsEndpoint}?query=${Uri.encodeComponent(trimmed)}',
      );

      List rawList = [];
      if (res.success && res.data != null) {
        if (res.data is List) {
          rawList = res.data as List;
        } else if (res.data is Map) {
          final dataMap = res.data as Map;
          if (dataMap['data'] is List) {
            rawList = dataMap['data'] as List;
          } else if (dataMap['users'] is List) {
            rawList = dataMap['users'] as List;
          } else if (dataMap['recipients'] is List) {
            rawList = dataMap['recipients'] as List;
          }
        }
      }

      if (rawList.isEmpty) {
        return [];
      }

      final results = <TransferUser>[];
      for (final item in rawList) {
        if (item is Map) {
          final rawMap = Map<String, dynamic>.from(item);
          final idUser = (rawMap['id_user'] ?? rawMap['id'] ?? '0').toString();
          final name = (rawMap['nama'] ?? rawMap['user_name'] ?? rawMap['name'] ?? 'User').toString();
          final email = (rawMap['email'] ?? '').toString();
          final hasWallet = rawMap['has_wallet'] == true || rawMap['has_wallet'] == 1;
          final isBanned = rawMap['is_banned'] == true || rawMap['is_banned'] == 1;
          final hasAnyWallet = rawMap['has_any_wallet'] == true || rawMap['has_any_wallet'] == 1;

          final wallets = rawMap['wallets'] is List ? (rawMap['wallets'] as List) : [];
          String? targetWalletName;
          int? targetWalletId = rawMap['id_dompet_tujuan'] as int? ??
              rawMap['id_dompet_tujuan_default'] as int?;

          if (wallets.isNotEmpty) {
            final firstW = wallets.first;
            if (firstW is Map) {
              targetWalletName = firstW['name']?.toString();
              targetWalletId ??= int.tryParse(firstW['id']?.toString() ?? '');
            }
          }

          String statusText;
          if (isBanned) {
            statusText = 'Banned Account';
          } else if (hasWallet && targetWalletName != null) {
            final extra = wallets.length > 1 ? ' +${wallets.length - 1}' : '';
            statusText = '$targetWalletName$extra';
          } else if (hasAnyWallet) {
            statusText = 'No active receiving wallet';
          } else {
            statusText = "Don't have a wallet";
          }

          results.add(TransferUser(
            id: idUser,
            name: name,
            email: email,
            avatar: Icons.person_rounded,
            verified: hasWallet && !isBanned && targetWalletId != null,
            hasAnyWallet: hasAnyWallet,
            isBanned: isBanned,
            walletName: targetWalletName,
            statusText: statusText,
            targetWalletId: targetWalletId,
            wallets: wallets,
          ));
        }
      }
      return results;
    } catch (_) {
      return [];
    }
  }

  /// Cari pengguna berdasarkan query
  static List<TransferUser> searchUsers(String query) => [];

  /// Eksekusi transfer melalui RESTful API backend (`POST /api/transfer`)
  /// Payload sesuai dengan StoreTransferRequest Laravel:
  /// - id_dompet_asal (integer)
  /// - id_dompet_tujuan (integer)
  /// - jumlah (numeric)
  /// - tanggal_transfer (date)
  /// - catatan (string, optional)
  static Future<ApiResponse> executeTransfer({
    required int fromWalletId,
    required int toWalletId,
    required double amount,
    String? note,
    String? date,
  }) async {
    // Pastikan preferensi mata uang user di backend sama dengan mata uang mobile
    try {
      await AuthService.syncCurrencyToBackend(preferredCurrency.value);
    } catch (_) {}

    final dateStr = date ??
        '${DateTime.now().year}-${DateTime.now().month.toString().padLeft(2, '0')}-${DateTime.now().day.toString().padLeft(2, '0')}';

    final body = <String, dynamic>{
      'id_dompet_asal': fromWalletId,
      'id_dompet_tujuan': toWalletId,
      'jumlah': double.parse(amount.toStringAsFixed(2)),
      'tanggal_transfer': dateStr,
      if (note != null && note.isNotEmpty) 'catatan': note,
    };

    return await ApiService.post(ApiConfig.transferEndpoint, body: body);
  }
}
