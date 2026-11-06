import 'api_config.dart';
import 'api_service.dart';

/// Service untuk pengelolaan dompet (Wallets) melalui RESTful API backend Laravel
class WalletService {
  WalletService._();

  /// Mengambil daftar dompet user dari backend (`/api/dompet`)
  static Future<ApiResponse> fetchWallets() async {
    return await ApiService.get(ApiConfig.dompetEndpoint);
  }

  /// Toggle status penerima transfer dompet (`/api/dompet/{id}/toggle-transfer`)
  static Future<ApiResponse> toggleTransferReceiver(dynamic walletId) async {
    return await ApiService.patch(ApiConfig.toggleTransferEndpoint(walletId));
  }

  /// Tambah dompet baru (`POST /api/dompet`)
  static Future<ApiResponse> createWallet({
    required String name,
    required String category,
    required double initialBalance,
    String? currency,
  }) async {
    final body = <String, dynamic>{
      'nama': name,
      'kategori': category,
      'saldo': initialBalance,
    };
    if (currency != null) {
      body['mata_uang'] = currency;
    }

    return await ApiService.post(
      ApiConfig.dompetEndpoint,
      body: body,
    );
  }

  /// Kelola / perbarui dompet (`PATCH /api/dompet/{id}/manage`)
  static Future<ApiResponse> manageWallet(
    dynamic walletId, {
    String? name,
    String? category,
    double? balance,
  }) async {
    final body = <String, dynamic>{};
    if (name != null) {
      body['nama'] = name;
    }
    if (category != null) {
      body['kategori'] = category;
    }
    if (balance != null) {
      body['saldo'] = balance;
    }

    return await ApiService.patch(
      ApiConfig.manageDompetEndpoint(walletId),
      body: body,
    );
  }
}
