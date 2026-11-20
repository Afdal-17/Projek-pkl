import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:finance_app_mobile/services/api_config.dart';
import 'package:finance_app_mobile/services/auth_service.dart';

void main() {
  String createSyntheticJwt(Map<String, dynamic> payload) {
    final header = base64Url.encode(utf8.encode(jsonEncode({'alg': 'RS256', 'typ': 'JWT'})));
    final body = base64Url.encode(utf8.encode(jsonEncode(payload)));
    const signature = 'synthetic_signature_for_local_testing_only';
    return '$header.$body.$signature';
  }

  group('Google ID Token Claim Verification Test', () {
    const validBackendAud = ApiConfig.googleServerClientId;

    test('Mengembalikan available: false jika idToken bernilai null atau kosong', () {
      final resNull = AuthService.verifyIdTokenClaims(null);
      expect(resNull['available'], isFalse);

      final resEmpty = AuthService.verifyIdTokenClaims('');
      expect(resEmpty['available'], isFalse);
    });

    test('Validasi berhasil penuh jika klaim token Google sesuai spesifikasi Backend', () {
      final validPayload = {
        'iss': 'https://accounts.google.com',
        'aud': validBackendAud,
        'sub': '123456789012345678901',
        'email': 'user@gmail.com',
        'email_verified': true,
        'exp': (DateTime.now().millisecondsSinceEpoch ~/ 1000) + 3600,
        'iat': (DateTime.now().millisecondsSinceEpoch ~/ 1000) - 60,
      };

      final jwt = createSyntheticJwt(validPayload);
      final res = AuthService.verifyIdTokenClaims(jwt);

      expect(res['available'], isTrue);
      expect(res['format_valid'], isTrue);
      expect(res['aud_valid'], isTrue);
      expect(res['iss_valid'], isTrue);
      expect(res['exp_valid'], isTrue);
      expect(res['email_verified'], isTrue);
    });

    test('Mendeteksi ketidakcocokan audience (aud)', () {
      final wrongAudPayload = {
        'iss': 'https://accounts.google.com',
        'aud': 'wrong-audience-client-id.apps.googleusercontent.com',
        'sub': '123456789012345678901',
        'email': 'user@gmail.com',
        'email_verified': true,
        'exp': (DateTime.now().millisecondsSinceEpoch ~/ 1000) + 3600,
      };

      final jwt = createSyntheticJwt(wrongAudPayload);
      final res = AuthService.verifyIdTokenClaims(jwt);

      expect(res['available'], isTrue);
      expect(res['aud_valid'], isFalse);
    });

    test('Mendeteksi token yang sudah kedaluwarsa (exp expired)', () {
      final expiredPayload = {
        'iss': 'accounts.google.com',
        'aud': validBackendAud,
        'sub': '123456789012345678901',
        'email': 'user@gmail.com',
        'email_verified': true,
        'exp': (DateTime.now().millisecondsSinceEpoch ~/ 1000) - 300, // 5 menit yang lalu
      };

      final jwt = createSyntheticJwt(expiredPayload);
      final res = AuthService.verifyIdTokenClaims(jwt);

      expect(res['available'], isTrue);
      expect(res['exp_valid'], isFalse);
    });

    test('Mendeteksi email yang belum diverifikasi Google', () {
      final unverifiedPayload = {
        'iss': 'https://accounts.google.com',
        'aud': validBackendAud,
        'sub': '123456789012345678901',
        'email': 'unverified@gmail.com',
        'email_verified': false,
        'exp': (DateTime.now().millisecondsSinceEpoch ~/ 1000) + 3600,
      };

      final jwt = createSyntheticJwt(unverifiedPayload);
      final res = AuthService.verifyIdTokenClaims(jwt);

      expect(res['available'], isTrue);
      expect(res['email_verified'], isFalse);
    });
  });
}
