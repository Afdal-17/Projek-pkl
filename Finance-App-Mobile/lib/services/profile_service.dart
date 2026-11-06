import 'api_config.dart';
import 'api_service.dart';

/// Service untuk pengelolaan profil user melalui RESTful API backend Laravel
class ProfileService {
  ProfileService._();

  /// Mengambil data profil user yang sedang login (`GET /api/user`)
  /// Response: {id_user, nama, email, role, google_id, mata_uang, ...}
  static Future<ApiResponse> fetchProfile() async {
    return await ApiService.get(ApiConfig.userEndpoint);
  }

  /// Memperbarui profil user (`PATCH /api/user`)
  /// Backend mungkin belum punya route ini — handle secara graceful
  static Future<ApiResponse> updateProfile({
    String? name,
    String? phone,
    String? location,
    String? currency,
  }) async {
    final body = <String, dynamic>{
      if (name != null) 'nama': name,
      if (phone != null) 'phone': phone,
      if (location != null) 'location': location,
      if (currency != null) 'mata_uang': currency,
    };
    return await ApiService.patch(ApiConfig.userEndpoint, body: body);
  }
}

