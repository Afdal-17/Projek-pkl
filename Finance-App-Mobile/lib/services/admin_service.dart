import '../models/admin_user.dart';
import 'api_config.dart';
import 'api_service.dart';

/// Service untuk menangani seluruh proses komunikasi API Admin ke backend Laravel
class AdminService {
  AdminService._();

  /// Mengambil data dashboard admin (statistik user, user aktif, chart & user terbaru)
  static Future<Map<String, dynamic>?> fetchDashboard() async {
    try {
      final res = await ApiService.get(ApiConfig.adminDashboardEndpoint);
      if (res.success && res.data is Map<String, dynamic>) {
        return res.data as Map<String, dynamic>;
      }
    } catch (_) {}
    return null;
  }

  /// Mengambil daftar pengguna terverifikasi untuk tab "All Users"
  static Future<List<AdminUser>?> fetchUsers({String? query, int page = 1}) async {
    try {
      final url = StringBuffer('${ApiConfig.adminUsersEndpoint}?page=$page');
      if (query != null && query.trim().isNotEmpty) {
        url.write('&q=${Uri.encodeComponent(query.trim())}');
      }
      final res = await ApiService.get(url.toString());
      if (res.success && res.data != null) {
        dynamic listRaw;
        if (res.data is Map && res.data['data'] != null) {
          listRaw = res.data['data'];
        } else if (res.data is List) {
          listRaw = res.data;
        }

        if (listRaw is List) {
          return listRaw
              .whereType<Map>()
              .map((m) => AdminUser.fromJson(Map<String, dynamic>.from(m)))
              .toList();
        }
        return [];
      }
    } catch (_) {}
    return null;
  }

  /// Mengambil daftar pengguna yang butuh verifikasi admin ("Verify Users")
  static Future<List<AdminUser>?> fetchVerifyUsers({String? query, int page = 1}) async {
    try {
      final url = StringBuffer('${ApiConfig.adminVerifyUsersEndpoint}?page=$page');
      if (query != null && query.trim().isNotEmpty) {
        url.write('&q=${Uri.encodeComponent(query.trim())}');
      }
      final res = await ApiService.get(url.toString());
      if (res.success && res.data != null) {
        dynamic listRaw;
        if (res.data is Map && res.data['data'] != null) {
          listRaw = res.data['data'];
        } else if (res.data is List) {
          listRaw = res.data;
        }

        if (listRaw is List) {
          return listRaw
              .whereType<Map>()
              .map((m) => AdminUser.fromJson(Map<String, dynamic>.from(m)))
              .toList();
        }
        return [];
      }
    } catch (_) {}
    return null;
  }

  /// Toggle status aktif/nonaktif user
  static Future<ApiResponse> toggleStatus(String userId) async {
    return await ApiService.patch(ApiConfig.adminUserStatusEndpoint(userId));
  }

  /// Ban atau unban user
  static Future<ApiResponse> banUser(String userId, {String? reason}) async {
    final body = (reason != null && reason.trim().isNotEmpty)
        ? {'ban_reason': reason.trim()}
        : null;
    return await ApiService.patch(ApiConfig.adminUserBanEndpoint(userId), body: body);
  }

  /// Verifikasi akun user oleh admin
  static Future<ApiResponse> verifyUser(String userId) async {
    return await ApiService.patch(ApiConfig.adminUserVerifyEndpoint(userId));
  }

  /// Update data user (nama / email) oleh admin
  static Future<ApiResponse> updateUser(
    String userId, {
    required String name,
    required String email,
  }) async {
    return await ApiService.patch(
      ApiConfig.adminUserManageEndpoint(userId),
      body: {
        'name': name.trim(),
        'email': email.trim(),
      },
    );
  }

  /// Hapus user oleh admin
  static Future<ApiResponse> deleteUser(String userId) async {
    return await ApiService.delete(ApiConfig.adminUserManageEndpoint(userId));
  }
}
