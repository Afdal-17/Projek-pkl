import 'package:flutter/foundation.dart';

import '../utils/currency.dart';
import 'api_config.dart';
import 'api_service.dart';
import 'auth_service.dart';

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
    // Sinkronisasi preferensi mata uang ke backend sebelum mengirim nominal target (best-effort)
    try {
      final synced = await AuthService.syncCurrencyToBackend(preferredCurrency.value);
      if (!synced) {
        debugPrint(
          '[SavingService] Peringatan: Sinkronisasi mata uang ke backend gagal. Pembuatan target tabungan tetap dilanjutkan.',
        );
      }
    } catch (e, st) {
      debugPrint('[SavingService] Exception saat sinkronisasi mata uang: $e\n$st');
      // Lanjutkan penyimpanan meskipun sinkronisasi mata uang gagal
    }

    final body = <String, dynamic>{
      'id_dompet': int.tryParse(walletId.toString()) ?? walletId,
      'nama_target': name,
      'nominal_target': double.parse(targetAmount.toStringAsFixed(2)),
      'initial_savings': double.parse(initialAmount.toStringAsFixed(2)),
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
