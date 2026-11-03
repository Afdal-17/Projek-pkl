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
    return await ApiService.post(
      ApiConfig.dompetEndpoint,
      body: {
        'nama': name,
        'kategori': category,
        'saldo': initialBalance,
        if (currency != null) 'mata_uang': currency,
      },
    );
  }

  /// Kelola / perbarui dompet (`PATCH /api/dompet/{id}/manage`)
  static Future<ApiResponse> manageWallet(
    dynamic walletId, {
    String? name,
    String? category,
    double? balance,
  }) async {
    return await ApiService.patch(
      ApiConfig.manageDompetEndpoint(walletId),
      body: {
        if (name != null) 'nama': name,
        if (category != null) 'kategori': category,
        if (balance != null) 'saldo': balance,
      },
    );
  }
}
