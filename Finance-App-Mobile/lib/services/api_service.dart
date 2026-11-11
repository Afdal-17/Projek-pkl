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

  static String? _translateMessage(String? raw) {
    if (raw == null) return null;
    final text = raw.trim();

    if (text.contains('tidak mencukupi untuk transaksi ini')) {
      return 'Insufficient wallet balance for this transaction.';
    }
    if (text.contains('Saldo dompet asal tidak mencukupi')) {
      return 'Insufficient source wallet balance.';
    }
    if (text.contains('tidak mencukupi')) {
      return 'Insufficient balance.';
    }
    if (text.contains('baru saja dicatat')) {
      return 'This entry was recently recorded. Please wait a moment before trying again.';
    }
    if (text.contains('Target tabungan berhasil dibuat')) {
      return 'Savings goal successfully created.';
    }
    if (text.contains('Target tabungan berhasil diperbarui')) {
      return 'Savings goal successfully updated.';
    }
    if (text.contains('Target tabungan berhasil dihapus')) {
      return 'Savings goal successfully deleted.';
    }
    if (text.contains('Dompet berhasil dibuat') || text.contains('berhasil ditambahkan')) {
      return 'Wallet successfully created.';
    }
    if (text.contains('Dompet berhasil diperbarui')) {
      return 'Wallet successfully updated.';
    }
    if (text.contains('Dompet berhasil dihapus')) {
      return 'Wallet successfully deleted.';
    }
    if (text.contains('Transaksi berhasil')) {
      return 'Transaction successfully created.';
    }
    if (text.contains('Transfer berhasil')) {
      return 'Transfer successful.';
    }
    if (text.contains('belum diaktifkan untuk menerima transfer')) {
      return 'The recipient wallet is not enabled to receive transfers.';
    }
    if (text.contains('User tidak ditemukan')) {
      return 'Recipient user not found.';
    }
    if (text.contains('Transfer ini baru saja diproses')) {
      return 'This transfer was recently processed. Please wait a moment before trying again.';
    }
    if (text.contains('Initial savings tidak boleh melebihi')) {
      return 'Initial savings cannot exceed wallet balance or target amount.';
    }
    if (text.contains('The nominal target format is invalid')) {
      return 'The saving target format is invalid.';
    }
    if (text.contains('Koneksi gagal:')) {
      return text.replaceAll('Koneksi gagal:', 'Connection failed:');
    }
    if (text.contains('Akun Anda ditangguhkan:')) {
      return text.replaceAll('Akun Anda ditangguhkan:', 'Your account has been suspended:');
    }
    if (text.contains('Akun Anda ditangguhkan')) {
      return text.replaceAll('Akun Anda ditangguhkan', 'Your account has been suspended');
    }
    if (text.contains('Akun Anda telah diblokir')) {
      return 'Your account has been suspended by an administrator.';
    }
    if (text.contains('Akun Anda belum diverifikasi')) {
      return 'Your account has not been verified by an admin.';
    }
    if (text.contains('Akun Anda tidak aktif')) {
      return 'Your account is currently inactive.';
    }
    if (text == 'Unauthenticated.') {
      return 'Session expired. Please log in again.';
    }

    return text;
  }

  static String? _extractMessage(dynamic decodedData) {
    if (decodedData is! Map) return null;
    String? raw;
    if (decodedData['message'] != null) {
      raw = decodedData['message'].toString();
    } else if (decodedData['errors'] is Map) {
      final errors = decodedData['errors'] as Map;
      if (errors.isNotEmpty) {
        final firstKey = errors.keys.first;
        final firstVal = errors[firstKey];
        if (firstVal is List && firstVal.isNotEmpty) {
          raw = firstVal.first.toString();
        } else {
          raw = firstVal.toString();
        }
      }
    }
    return _translateMessage(raw);
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
        message: 'Connection failed: $e',
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
        message: 'Connection failed: $e',
      );
    }
  }

  /// Request PUT generik
  static Future<ApiResponse> put(
    String url, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
  }) async {
    try {
      final uri = Uri.parse(url);
      final headers = await _prepareHeaders(requiresAuth: requiresAuth);
      final String? jsonBody = body != null ? jsonEncode(body) : null;

      final response = await _client.put(
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
        message: 'Connection failed: $e',
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
        message: 'Connection failed: $e',
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
        message: 'Connection failed: $e',
      );
    }
  }
}
