import 'dart:convert';
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import 'api_config.dart';
import 'api_service.dart';
import 'auth_service.dart';

/// Service untuk pengelolaan profil user melalui RESTful API backend Laravel
class ProfileService {
  ProfileService._();

  /// Mengonversi avatar_path atau avatar_url dari backend ke URL publik yang valid untuk diakses mobile
  static String? resolveAvatarUrl(dynamic avatarUrlOrPath) {
    if (avatarUrlOrPath == null) return null;
    final str = avatarUrlOrPath.toString().trim();
    if (str.isEmpty || str == 'null') return null;

    final baseUri = Uri.parse(ApiConfig.baseUrl);
    final portStr = baseUri.hasPort ? ':${baseUri.port}' : '';
    final origin = '${baseUri.scheme}://${baseUri.host}$portStr';

    if (str.startsWith('http://') || str.startsWith('https://')) {
      final uri = Uri.tryParse(str);
      if (uri != null && (uri.host == 'localhost' || uri.host == '127.0.0.1')) {
        return '$origin${uri.path}';
      }
      return str;
    }

    final cleanPath = str.startsWith('/')
        ? str
        : (str.startsWith('storage/') ? '/$str' : '/storage/$str');
    return '$origin$cleanPath';
  }

  /// Mengambil data profil user yang sedang login (`GET /api/account` atau `GET /api/user`)
  static Future<ApiResponse> fetchProfile() async {
    final res = await ApiService.get(ApiConfig.accountEndpoint);
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
      'nama': ?name,
      'nomor_telepon': ?phone,
      'lokasi': ?location,
      'mata_uang': ?currency,
      'email': ?email,
    };
    return await ApiService.patch(ApiConfig.accountEndpoint, body: body);
  }

  /// Upload foto avatar pengguna (`POST /api/account/avatar`) multipart/form-data
  static Future<ApiResponse> uploadAvatar(Uint8List bytes, {String filename = 'avatar.jpg'}) async {
    try {
      final token = await AuthService.getToken();
      final uri = Uri.parse(ApiConfig.accountAvatarEndpoint);
      final request = http.MultipartRequest('POST', uri);

      // Jangan sertakan Content-Type: application/json karena menimpa multipart/form-data
      final headers = <String, String>{
        'Accept': 'application/json',
        ApiConfig.ngrokHeaderKey: ApiConfig.ngrokHeaderValue,
      };
      if (token != null && token.isNotEmpty) {
        headers['Authorization'] = 'Bearer $token';
      }
      request.headers.addAll(headers);

      request.files.add(
        http.MultipartFile.fromBytes('avatar', bytes, filename: filename),
      );

      final streamedResponse = await request.send().timeout(const Duration(seconds: 30));
      final response = await http.Response.fromStream(streamedResponse);
      final isSuccess = response.statusCode >= 200 && response.statusCode < 300;

      dynamic responseData;
      try {
        responseData = jsonDecode(response.body);
      } catch (_) {}

      return ApiResponse(
        success: isSuccess,
        statusCode: response.statusCode,
        message: isSuccess
            ? 'Avatar uploaded successfully'
            : (responseData is Map && responseData['message'] != null
                ? responseData['message'].toString()
                : 'Failed to upload avatar'),
        data: responseData,
      );
    } catch (e) {
      return ApiResponse(
        success: false,
        statusCode: 500,
        message: 'Connection failed: $e',
      );
    }
  }

  /// Hapus foto avatar pengguna (`DELETE /api/account/avatar`)
  static Future<ApiResponse> deleteAvatar() async {
    return await ApiService.delete(ApiConfig.accountAvatarEndpoint);
  }
}
