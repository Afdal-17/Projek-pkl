import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'api_config.dart';
import 'auth_service.dart';

/// Model hasil respon dari ApiService
class ApiResponse {
  final bool success;
  final int statusCode;
  final dynamic data;
  final String? message;

  ApiResponse({
    required this.success,
    required this.statusCode,
    this.data,
    this.message,
  });

  @override
  String toString() => 'ApiResponse(success: $success, statusCode: $statusCode, message: $message)';
}

/// Service utama untuk komunikasi HTTP dengan Backend Laravel
class ApiService {
  ApiService._();

  static final HttpClient _client = HttpClient()
    ..connectionTimeout = const Duration(seconds: 15);

  /// Helper untuk membaca token dan membuat header terotentikasi
  static Future<Map<String, String>> _prepareHeaders({bool requiresAuth = true}) async {
    final headers = Map<String, String>.from(ApiConfig.defaultHeaders);
    if (requiresAuth) {
      final token = await AuthService.getToken();
      if (token != null && token.isNotEmpty) {
        headers['Authorization'] = 'Bearer $token';
      }
    }
    return headers;
  }

  /// Request GET generik
  static Future<ApiResponse> get(String url, {bool requiresAuth = true}) async {
    try {
      final uri = Uri.parse(url);
      final request = await _client.getUrl(uri);

      final headers = await _prepareHeaders(requiresAuth: requiresAuth);
      headers.forEach((key, value) {
        request.headers.set(key, value);
      });

      final response = await request.close().timeout(const Duration(seconds: 20));
      final responseBody = await response.transform(utf8.decoder).join();

      dynamic decodedData;
      try {
        decodedData = jsonDecode(responseBody);
      } catch (_) {
        decodedData = responseBody;
      }

      final isSuccess = response.statusCode >= 200 && response.statusCode < 300;
      final message = decodedData is Map ? (decodedData['message'] as String?) : null;

      return ApiResponse(
        success: isSuccess,
        statusCode: response.statusCode,
        data: decodedData,
        message: message,
      );
    } catch (e) {
      return ApiResponse(
        success: false,
        statusCode: 500,
        message: 'Koneksi gagal: $e',
      );
    }
  }

  /// Request POST generik
  static Future<ApiResponse> post(
    String url, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
  }) async {
    try {
      final uri = Uri.parse(url);
      final request = await _client.postUrl(uri);

      final headers = await _prepareHeaders(requiresAuth: requiresAuth);
      headers.forEach((key, value) {
        request.headers.set(key, value);
      });

      if (body != null) {
        final jsonString = jsonEncode(body);
        request.write(jsonString);
      }

      final response = await request.close().timeout(const Duration(seconds: 20));
      final responseBody = await response.transform(utf8.decoder).join();

      dynamic decodedData;
      try {
        decodedData = jsonDecode(responseBody);
      } catch (_) {
        decodedData = responseBody;
      }

      final isSuccess = response.statusCode >= 200 && response.statusCode < 300;
      final message = decodedData is Map ? (decodedData['message'] as String?) : null;

      return ApiResponse(
        success: isSuccess,
        statusCode: response.statusCode,
        data: decodedData,
        message: message,
      );
    } catch (e) {
      return ApiResponse(
        success: false,
        statusCode: 500,
        message: 'Koneksi gagal: $e',
      );
    }
  }

  /// Request PATCH generik
  static Future<ApiResponse> patch(
    String url, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
  }) async {
    try {
      final uri = Uri.parse(url);
      final request = await _client.patchUrl(uri);

      final headers = await _prepareHeaders(requiresAuth: requiresAuth);
      headers.forEach((key, value) {
        request.headers.set(key, value);
      });

      if (body != null) {
        final jsonString = jsonEncode(body);
        request.write(jsonString);
      }

      final response = await request.close().timeout(const Duration(seconds: 20));
      final responseBody = await response.transform(utf8.decoder).join();

      dynamic decodedData;
      try {
        decodedData = jsonDecode(responseBody);
      } catch (_) {
        decodedData = responseBody;
      }

      final isSuccess = response.statusCode >= 200 && response.statusCode < 300;
      final message = decodedData is Map ? (decodedData['message'] as String?) : null;

      return ApiResponse(
        success: isSuccess,
        statusCode: response.statusCode,
        data: decodedData,
        message: message,
      );
    } catch (e) {
      return ApiResponse(
        success: false,
        statusCode: 500,
        message: 'Koneksi gagal: $e',
      );
    }
  }

  /// Request DELETE generik
  static Future<ApiResponse> delete(
    String url, {
    bool requiresAuth = true,
  }) async {
    try {
      final uri = Uri.parse(url);
      final request = await _client.deleteUrl(uri);

      final headers = await _prepareHeaders(requiresAuth: requiresAuth);
      headers.forEach((key, value) {
        request.headers.set(key, value);
      });

      final response = await request.close().timeout(const Duration(seconds: 20));
      final responseBody = await response.transform(utf8.decoder).join();

      dynamic decodedData;
      try {
        decodedData = jsonDecode(responseBody);
      } catch (_) {
        decodedData = responseBody;
      }

      final isSuccess = response.statusCode >= 200 && response.statusCode < 300;
      final message = decodedData is Map ? (decodedData['message'] as String?) : null;

      return ApiResponse(
        success: isSuccess,
        statusCode: response.statusCode,
        data: decodedData,
        message: message,
      );
    } catch (e) {
      return ApiResponse(
        success: false,
        statusCode: 500,
        message: 'Koneksi gagal: $e',
      );
    }
  }
}

