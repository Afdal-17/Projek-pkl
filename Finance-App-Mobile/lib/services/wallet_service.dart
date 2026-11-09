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
  /// StoreDompetRequest Laravel mengharuskan:
  /// - nama_dompet (string)
  /// - jenis ('physical' / 'digital' / 'bank')
  /// - saldo_awal (numeric) -> otomatis menjadi saldo di controller
  /// (HINDARI mengirim saldo dan saldo_awal sekaligus karena ada prohibits:saldo_awal)
  static Future<ApiResponse> createWallet({
    required String name,
    required String category,
    required double initialBalance,
    String? currency,
    String? color,
  }) async {
    String jenis = 'digital';
    final catLower = category.toLowerCase();
    if (catLower.contains('physical') || catLower.contains('cash')) {
      jenis = 'physical';
    } else if (catLower.contains('bank')) {
      jenis = 'bank';
    }

    final body = <String, dynamic>{
      'nama_dompet': name,
      'jenis': jenis,
      'saldo_awal': initialBalance,
      if (color != null && color.isNotEmpty) 'warna': color,
    };

    return await ApiService.post(
      ApiConfig.dompetEndpoint,
      body: body,
    );
  }

  /// Kelola / perbarui dompet (`PATCH /api/dompet/{id}/manage`)
  /// Validator backend mengharuskan nama_dompet, jenis, warna, saldo
  static Future<ApiResponse> manageWallet(
    dynamic walletId, {
    required String name,
    required String category,
    required double balance,
    String? color,
    String? description,
  }) async {
    String jenis = 'digital';
    final catLower = category.toLowerCase();
    if (catLower.contains('physical') || catLower.contains('cash')) {
      jenis = 'physical';
    } else if (catLower.contains('bank')) {
      jenis = 'bank';
    }

    final body = <String, dynamic>{
      'nama_dompet': name,
      'jenis': jenis,
      'saldo': balance,
      'warna': (color != null && color.isNotEmpty) ? color : 'brand',
      if (description != null) 'deskripsi': description,
    };

    return await ApiService.patch(
      ApiConfig.manageDompetEndpoint(walletId),
      body: body,
    );
  }

  /// Hapus dompet (`DELETE /api/dompet/{id}`)
  static Future<ApiResponse> deleteWallet(dynamic walletId) async {
    return await ApiService.delete('${ApiConfig.dompetEndpoint}/$walletId');
  }
}
