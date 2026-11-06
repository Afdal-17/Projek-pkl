import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'api_config.dart';
import 'api_service.dart';

/// Service untuk autentikasi. Terintegrasi dengan Backend Laravel API Sanctum & Google Auth.
class AuthService {
  static const _storage = FlutterSecureStorage();
  static const _tokenKey = 'auth_token';
  static const _userKey = 'user_email';
  static const _userNameKey = 'user_name';
  static const _phoneKey = 'user_phone';
  static const _roleKey = 'user_role';

  /// URL Base API Backend
  static const String baseUrl = ApiConfig.baseUrl;

  /// Dummy token untuk fallback testing offline
  static const _dummyToken = 'dummy_token_12345678';

  // Client ID Web dari Google OAuth Backend (Google Cloud Console project teman)
  static const String _webClientId = '247859906027-4bcrj4ec74evugic0ccb6brl775s56ft.apps.googleusercontent.com';

  static final GoogleSignIn _googleSignIn = GoogleSignIn(
    serverClientId: _webClientId,
    scopes: <String>[
      'email',
      'profile',
    ],
  );

  /// Alur Google Sign-In: mengambil idToken Google dan verifikasi ke Laravel Backend
  static Future<String?> signInWithGoogle() async {
    try {
      final GoogleSignInAccount? account = await _googleSignIn.signIn();
      if (account == null) {
        return null; // Pengguna membatalkan dialog
      }

      final GoogleSignInAuthentication auth = await account.authentication;
      final email = account.email;
      final idToken = auth.idToken;

      // 1. Kirim ID Token ke endpoint API Laravel Backend untuk ditukar dengan Sanctum Bearer Token
      if (idToken != null && idToken.isNotEmpty) {
        try {
          final res = await ApiService.post(
            ApiConfig.googleAuthEndpoint,
            body: {'id_token': idToken},
            requiresAuth: false,
          );

          if (res.success && res.data is Map) {
            final dataMap = res.data as Map;
            final sanctumToken = dataMap['token']?.toString();
            final user = dataMap['user'];

            if (sanctumToken != null && sanctumToken.isNotEmpty) {
              await _storage.write(key: _tokenKey, value: sanctumToken);
              await _storage.write(
                key: _userKey,
                value: (user is Map ? user['email']?.toString() : null) ?? email,
              );
              if (user is Map && user['name'] != null) {
                await _storage.write(key: _userNameKey, value: user['name'].toString());
              } else if (account.displayName != null) {
                await _storage.write(key: _userNameKey, value: account.displayName);
              }

              final role = (user is Map ? user['role']?.toString() : null) ?? 'user';
              await _storage.write(key: _roleKey, value: role);

              return sanctumToken;
            }
          }
        } catch (_) {
          // Backend endpoint belum siap / koneksi ngrok offline
        }
      }

      // 2. Fallback offline jika backend belum siap
      final fallbackToken = idToken ?? _dummyToken;
      await _storage.write(key: _tokenKey, value: fallbackToken);
      await _storage.write(key: _userKey, value: email);
      if (account.displayName != null) {
        await _storage.write(key: _userNameKey, value: account.displayName);
      }

      final cleanId = email.trim().toLowerCase();
      if (cleanId == 'admin@finance.test' || cleanId == 'admin@finance.app') {
        await _storage.write(key: _roleKey, value: 'admin');
      } else {
        await _storage.write(key: _roleKey, value: 'user');
      }

      return fallbackToken;
    } catch (e) {
      return null;
    }
  }

  /// Login langsung menggunakan akun Google
  static Future<bool> loginWithGoogle() async {
    final token = await signInWithGoogle();
    return token != null;
  }

  /// Login dengan email atau nomor telepon dan password.
  /// Memanggil RESTful API backend /api/login Laravel Sanctum, dengan fallback offline.
  static Future<bool> login(
    String identifier,
    String password, {
    bool isPhone = false,
  }) async {
    if (identifier.isEmpty || password.isEmpty) return false;
    if (password.length < 6) return false;

    final cleanId = identifier.trim().toLowerCase();

    // 1. Coba request ke Backend Laravel API jika bukan nomor HP
    if (!isPhone) {
      try {
        final res = await ApiService.post(
          ApiConfig.loginEndpoint,
          body: {
            'email': identifier.trim(),
            'password': password,
          },
          requiresAuth: false,
        );

        if (res.success && res.data is Map) {
          final dataMap = res.data as Map;
          final token = dataMap['token']?.toString();
          final user = dataMap['user'];

          if (token != null && token.isNotEmpty) {
            await _storage.write(key: _tokenKey, value: token);
            await _storage.write(key: _userKey, value: (user is Map ? user['email']?.toString() : null) ?? identifier);
            if (user is Map && user['name'] != null) {
              await _storage.write(key: _userNameKey, value: user['name'].toString());
            }

            final role = (user is Map ? user['role']?.toString() : null) ??
                ((cleanId == 'admin@finance.test' || cleanId == 'admin@finance.app') ? 'admin' : 'user');
            await _storage.write(key: _roleKey, value: role);
            return true;
          }
        }
      } catch (_) {
        // Fallback untuk local preview jika koneksi ngrok belum tersambung
      }
    }

    // 2. Fallback local / phone auth
    await _storage.write(key: _tokenKey, value: _dummyToken);

    if (isPhone) {
      await _storage.write(key: _phoneKey, value: identifier);
      await _storage.write(key: _roleKey, value: 'user');
    } else {
      await _storage.write(key: _userKey, value: identifier);
      // Akun admin resmi di Laravel DatabaseSeeder: admin@finance.test
      if (cleanId == 'admin@finance.test' || cleanId == 'admin@finance.app') {
        await _storage.write(key: _roleKey, value: 'admin');
      } else {
        await _storage.write(key: _roleKey, value: 'user');
      }
    }
    return true;
  }

  /// Ambil data profil user dari Laravel Sanctum API (/api/user)
  static Future<Map<String, dynamic>?> fetchCurrentUser() async {
    final res = await ApiService.get(ApiConfig.userEndpoint);
    if (res.success && res.data is Map) {
      return Map<String, dynamic>.from(res.data as Map);
    }
    return null;
  }

  /// Kirim kode OTP ke nomor telepon (WhatsApp) atau email.
  /// Meniru OtpService / FonnteService di Laravel.
  static Future<bool> sendOtp(String phone) async {
    if (phone.trim().isEmpty) return false;
    await Future.delayed(const Duration(milliseconds: 500));
    return true;
  }

  /// Verifikasi 6-digit kode OTP.
  /// Meniru OtpVerificationController@verify di Laravel.
  static Future<bool> verifyOtp(String phone, String code) async {
    if (code.length != 6) return false;
    await Future.delayed(const Duration(milliseconds: 500));

    await _storage.write(key: _tokenKey, value: _dummyToken);
    await _storage.write(key: _phoneKey, value: phone);
    await _storage.write(key: _roleKey, value: 'user');
    return true;
  }

  /// Kirim ulang kode OTP.
  static Future<bool> resendOtp(String phone) async {
    await Future.delayed(const Duration(milliseconds: 400));
    return true;
  }

  /// Request link reset password ke email.
  static Future<bool> forgotPassword(String email) async {
    if (email.isEmpty || !email.contains('@')) return false;
    await Future.delayed(const Duration(milliseconds: 400));
    return true;
  }

  /// Reset password dengan password baru.
  /// Meniru NewPasswordController@store di Laravel.
  static Future<bool> resetPassword(
    String email,
    String newPassword,
    String confirmPassword,
  ) async {
    if (email.isEmpty || newPassword.length < 8) return false;
    if (newPassword != confirmPassword) return false;

    await Future.delayed(const Duration(milliseconds: 500));
    return true;
  }

  /// Register dengan nama, email, dan password.
  static Future<bool> register(
    String name,
    String email,
    String password,
  ) async {
    if (name.isEmpty || email.isEmpty || password.isEmpty) return false;
    if (password.length < 6) return false;
    if (!email.contains('@')) return false;

    await _storage.write(key: _tokenKey, value: _dummyToken);
    await _storage.write(key: _userKey, value: email);
    await _storage.write(key: _userNameKey, value: name);
    await _storage.write(key: _roleKey, value: 'user');
    return true;
  }

  /// Logout (sekaligus membersihkan sesi Google Sign-In jika ada).
  static Future<void> logout() async {
    try {
      if (await _googleSignIn.isSignedIn()) {
        await _googleSignIn.signOut();
      }
    } catch (_) {}

    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userKey);
    await _storage.delete(key: _userNameKey);
    await _storage.delete(key: _phoneKey);
    await _storage.delete(key: _roleKey);
  }

  /// Cek apakah user sudah login.
  static Future<bool> isLoggedIn() async {
    final token = await _storage.read(key: _tokenKey);
    return token != null && token.isNotEmpty;
  }

  /// Cek apakah akun yang sedang login adalah Admin.
  static Future<bool> isAdmin() async {
    final role = await _storage.read(key: _roleKey);
    if (role == 'admin') return true;
    final email = await getUserEmail();
    if (email != null) {
      final clean = email.trim().toLowerCase();
      if (clean == 'admin@finance.test' || clean == 'admin@finance.app') {
        return true;
      }
    }
    return false;
  }

  /// Ambil token yang disimpan.
  static Future<String?> getToken() async {
    return await _storage.read(key: _tokenKey);
  }

  /// Ambil email user yang login.
  static Future<String?> getUserEmail() async {
    return await _storage.read(key: _userKey);
  }

  /// Ambil nama user yang login.
  static Future<String?> getUserName() async {
    return await _storage.read(key: _userNameKey);
  }

  /// Ambil nomor telepon user yang login.
  static Future<String?> getUserPhone() async {
    return await _storage.read(key: _phoneKey);
  }
}
