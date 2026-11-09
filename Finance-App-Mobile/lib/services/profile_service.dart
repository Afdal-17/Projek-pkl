import 'api_config.dart';
import 'api_service.dart';

/// Service untuk pengelolaan profil user melalui RESTful API backend Laravel
class ProfileService {
  ProfileService._();

  static const String _accountEndpoint = '${ApiConfig.baseUrl}/account';

  /// Mengambil data profil user yang sedang login (`GET /api/account` atau `GET /api/user`)
  static Future<ApiResponse> fetchProfile() async {
    final res = await ApiService.get(_accountEndpoint);
    if (res.success) return res;
    // Fallback ke /api/user
    return await ApiService.get(ApiConfig.userEndpoint);
  }

  /// Memperbarui profil user (`PATCH /api/account`)
  /// ProfileUpdateRequest Laravel menerima:
  /// - nama (string)
  /// - nomor_telepon / phone (string)
  /// - lokasi / location (string)
  /// - mata_uang / currency ('IDR' / 'USD' / 'EUR')
  /// - email (string)
  static Future<ApiResponse> updateProfile({
    String? name,
    String? phone,
    String? location,
    String? currency,
    String? email,
  }) async {
    final body = <String, dynamic>{
      if (name != null) 'nama': name,
      if (phone != null) 'nomor_telepon': phone,
      if (location != null) 'lokasi': location,
      if (currency != null) 'mata_uang': currency,
      if (email != null) 'email': email,
    };
    return await ApiService.patch(_accountEndpoint, body: body);
  }
}
