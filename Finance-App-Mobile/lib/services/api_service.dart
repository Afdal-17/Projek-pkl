import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
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
/// Menggunakan package:http yang 100% kompatibel dan aman untuk Flutter Web (Chrome/Edge)
/// maupun Flutter Mobile (Android/iOS) tanpa dependensi dart:io yang memicu crash Platform._version.
class ApiService {
  ApiService._();

  static final http.Client _client = http.Client();

  static String? _extractMessage(dynamic decodedData) {
    if (decodedData is! Map) return null;
    if (decodedData['message'] != null) {
      return decodedData['message'].toString();
    }
    if (decodedData['errors'] is Map) {
      final errors = decodedData['errors'] as Map;
      if (errors.isNotEmpty) {
        final firstKey = errors.keys.first;
        final firstVal = errors[firstKey];
        if (firstVal is List && firstVal.isNotEmpty) {
          return firstVal.first.toString();
        }
        return firstVal.toString();
      }
    }
    return null;
  }

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
      final headers = await _prepareHeaders(requiresAuth: requiresAuth);

      final response = await _client.get(uri, headers: headers).timeout(const Duration(seconds: 20));
      final responseBody = utf8.decode(response.bodyBytes);

      dynamic decodedData;
      try {
        decodedData = jsonDecode(responseBody);
      } catch (_) {
        decodedData = responseBody;
      }

      final isSuccess = response.statusCode >= 200 && response.statusCode < 300;
      final message = _extractMessage(decodedData);

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
      final headers = await _prepareHeaders(requiresAuth: requiresAuth);
      final String? jsonBody = body != null ? jsonEncode(body) : null;

      final response = await _client.post(
        uri,
        headers: headers,
        body: jsonBody,
      ).timeout(const Duration(seconds: 20));

      final responseBody = utf8.decode(response.bodyBytes);

      dynamic decodedData;
      try {
        decodedData = jsonDecode(responseBody);
      } catch (_) {
        decodedData = responseBody;
      }

      final isSuccess = response.statusCode >= 200 && response.statusCode < 300;
      final message = _extractMessage(decodedData);

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
      final headers = await _prepareHeaders(requiresAuth: requiresAuth);
      final String? jsonBody = body != null ? jsonEncode(body) : null;

      final response = await _client.patch(
        uri,
        headers: headers,
        body: jsonBody,
      ).timeout(const Duration(seconds: 20));

      final responseBody = utf8.decode(response.bodyBytes);

      dynamic decodedData;
      try {
        decodedData = jsonDecode(responseBody);
      } catch (_) {
        decodedData = responseBody;
      }

      final isSuccess = response.statusCode >= 200 && response.statusCode < 300;
      final message = _extractMessage(decodedData);

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
      final headers = await _prepareHeaders(requiresAuth: requiresAuth);

      final response = await _client.delete(uri, headers: headers).timeout(const Duration(seconds: 20));
      final responseBody = utf8.decode(response.bodyBytes);

      dynamic decodedData;
      try {
        decodedData = jsonDecode(responseBody);
      } catch (_) {
        decodedData = responseBody;
      }

      final isSuccess = response.statusCode >= 200 && response.statusCode < 300;
      final message = _extractMessage(decodedData);

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
