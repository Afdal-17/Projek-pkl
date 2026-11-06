import 'api_config.dart';
import 'api_service.dart';

/// Service untuk pengelolaan tabungan (Savings) melalui RESTful API backend Laravel
class SavingService {
  SavingService._();

  /// Mengambil semua target tabungan dari backend (`GET /api/saving`)
  /// Response: [{id_tabungan, nama_target, keterangan, jumlah_target, jumlah_terkumpul, nama_dompet}]
  static Future<ApiResponse> fetchSavings() async {
    return await ApiService.get(ApiConfig.savingEndpoint);
  }

  /// Membuat target tabungan baru (`POST /api/saving`)
  static Future<ApiResponse> createSaving({
    required String name,
    required double targetAmount,
    required String walletName,
    double initialAmount = 0,
    String? description,
  }) async {
    final body = <String, dynamic>{
      'nama_target': name,
      'jumlah_target': targetAmount,
      'nama_dompet': walletName,
      'jumlah_terkumpul': initialAmount,
      if (description != null && description.isNotEmpty)
        'keterangan': description,
    };
    return await ApiService.post(ApiConfig.savingEndpoint, body: body);
  }

  /// Hapus target tabungan (`DELETE /api/saving/{id}`)
  /// Backend mungkin belum punya route ini — handle secara graceful
  static Future<ApiResponse> deleteSaving(dynamic id) async {
    return await ApiService.get('${ApiConfig.savingEndpoint}/$id/delete');
  }
}

