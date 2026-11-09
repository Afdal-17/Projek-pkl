import 'api_config.dart';
import 'api_service.dart';

/// Service untuk pengelolaan tabungan (Savings) melalui RESTful API backend Laravel
class SavingService {
  SavingService._();

  /// Mengambil semua target tabungan dari backend (`GET /api/saving`)
  /// Response: [{id, name, note, wallet, saved, target, progress, achieved}]
  static Future<ApiResponse> fetchSavings() async {
    return await ApiService.get(ApiConfig.savingEndpoint);
  }

  /// Membuat target tabungan baru (`POST /api/saving`)
  /// StoreTargetTabunganRequest Laravel mengharuskan:
  /// - id_dompet (integer)
  /// - nama_target (string)
  /// - nominal_target (numeric)
  /// - initial_savings (numeric)
  /// - deskripsi (nullable string)
  static Future<ApiResponse> createSaving({
    required dynamic walletId,
    required String name,
    required double targetAmount,
    double initialAmount = 0,
    String? description,
  }) async {
    final body = <String, dynamic>{
      'id_dompet': int.tryParse(walletId.toString()) ?? walletId,
      'nama_target': name,
      'nominal_target': targetAmount,
      'initial_savings': initialAmount,
      if (description != null && description.isNotEmpty)
        'deskripsi': description,
    };
    return await ApiService.post(ApiConfig.savingEndpoint, body: body);
  }

  /// Hapus target tabungan (`DELETE /api/saving/{id}`)
  static Future<ApiResponse> deleteSaving(dynamic id) async {
    return await ApiService.delete('${ApiConfig.savingEndpoint}/$id');
  }
}
