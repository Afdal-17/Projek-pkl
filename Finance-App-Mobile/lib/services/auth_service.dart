import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:google_sign_in/google_sign_in.dart';
import '../data/admin_dummy_data.dart';
import '../utils/currency.dart';
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

  /// Pesan error terakhir dari server saat percobaan login gagal
  static String? lastLoginError;

  /// Pesan error terakhir dari server saat percobaan registrasi gagal
  static String? lastRegisterError;

  /// URL Base API Backend
  static const String baseUrl = ApiConfig.baseUrl;

  /// Dummy token untuk fallback testing offline
  static const _dummyToken = 'dummy_token_12345678';

  // Client ID Web dari Google OAuth Backend teman
  static const String _webClientId = '247859906027-4bcrj4ec74evugic0ccb6brl775s56ft.apps.googleusercontent.com';

  static final GoogleSignIn _googleSignIn = GoogleSignIn(
    scopes: <String>[
      'email',
      'profile',
    ],
  );

  /// Alur Google Sign-In: mengambil profil Google dan verifikasi ke Laravel Backend (/api/auth/google)
  static Future<String?> signInWithGoogle() async {
    try {
      final GoogleSignInAccount? account = await _googleSignIn.signIn();
      if (account == null) {
        return null; // Pengguna membatalkan dialog
      }

      final GoogleSignInAuthentication auth = await account.authentication;
      final email = account.email;
      final idToken = auth.idToken;

      // 1. Kirim data profil & Google ID ke endpoint API Laravel Backend (/api/auth/google)
      //    untuk ditukar dengan Sanctum Bearer Token resmi
      try {
        final payload = {
          'email': account.email,
          'google_id': account.id,
          'nama': account.displayName ?? 'Google User',
          'avatar_path': account.photoUrl,
          if (idToken != null && idToken.isNotEmpty) 'id_token': idToken,
        };

        final res = await ApiService.post(
          ApiConfig.googleAuthEndpoint,
          body: payload,
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

            final userName = (user is Map ? (user['nama'] ?? user['name'])?.toString() : null) ??
                account.displayName;
            if (userName != null) {
              await _storage.write(key: _userNameKey, value: userName);
            }

            final role = (user is Map ? user['role']?.toString() : null) ?? 'user';
            await _storage.write(key: _roleKey, value: role);

            if (user is Map && user['mata_uang'] != null) {
              final curr = user['mata_uang'].toString().toUpperCase();
              if (supportedCurrencies.contains(curr)) {
                preferredCurrency.value = curr;
              }
            }

            return sanctumToken;
          }
        } else {
          lastLoginError = res.message ?? 'Google authentication failed on server.';
          return null;
        }
      } catch (e) {
        // Backend endpoint tidak dapat dihubungi
        lastLoginError = 'Connection failed: ${e.toString()}';
        return null;
      }

      // Backend merespons tapi tidak mengembalikan Sanctum token yang valid
      lastLoginError = 'Invalid response from authentication server.';
      return null;
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

    lastLoginError = null;
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

            if (user is Map && user['mata_uang'] != null) {
              final curr = user['mata_uang'].toString().toUpperCase();
              if (supportedCurrencies.contains(curr)) {
                preferredCurrency.value = curr;
              }
            }

            return true;
          }
        }

        // Cek apakah server backend merespons (bukan kegagalan jaringan).
        // Jika server menolak (401, 403, 422, atau error server lainnya),
        // jangan pernah menggunakan dummy token atau meloloskan login.
        final isNetworkFailure = res.message != null && res.message!.startsWith('Connection failed:');
        if (!isNetworkFailure) {
          lastLoginError = res.message ?? 'Invalid email or password.';
          return false;
        }
      } catch (e) {
        // Backend tidak dapat dihubungi (SocketException, timeout, dll.)
        lastLoginError = 'Connection failed: ${e.toString()}';
        return false;
      }
    }

    // Untuk mode phone (OTP), tidak ada backend endpoint — tetap gunakan dummy
    if (isPhone) {
      await _storage.write(key: _tokenKey, value: _dummyToken);
      await _storage.write(key: _phoneKey, value: identifier);
      await _storage.write(key: _roleKey, value: 'user');
      return true;
    }

    return false;
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

  /// Registrasi akun baru ke REST API backend Laravel (/api/register).
  /// Mengembalikan userId (int) jika registrasi berhasil dan kode OTP dikirim ke email.
  /// Mengembalikan null jika validasi gagal atau terjadi error.
  static Future<int?> registerUser(
    String name,
    String email,
    String password,
  ) async {
    lastRegisterError = null;
    final cleanName = name.trim();
    final cleanEmail = email.trim().toLowerCase();

    if (cleanName.isEmpty) {
      lastRegisterError = 'Full name is required.';
      return null;
    }
    if (cleanEmail.isEmpty || !cleanEmail.contains('@')) {
      lastRegisterError = 'A valid email address is required.';
      return null;
    }
    if (password.length < 8) {
      lastRegisterError = 'Password must be at least 8 characters.';
      return null;
    }

    try {
      final res = await ApiService.post(
        ApiConfig.registerEndpoint,
        body: {
          'nama': cleanName,
          'email': cleanEmail,
          'password': password,
          'password_confirmation': password,
        },
        requiresAuth: false,
      );

      if (res.success && res.data is Map) {
        final dataMap = res.data as Map;
        final rawUserId = dataMap['user_id'];
        if (rawUserId != null) {
          final userId = (rawUserId is num)
              ? rawUserId.toInt()
              : int.tryParse(rawUserId.toString());
          if (userId != null) {
            return userId;
          }
        }
      }

      if (res.message != null && res.message!.isNotEmpty) {
        lastRegisterError = res.message;
      } else {
        lastRegisterError = 'Registration failed. Please check your data and try again.';
      }
      return null;
    } catch (e) {
      lastRegisterError = 'Connection error: ${e.toString()}';
      return null;
    }
  }

  /// Verifikasi kode OTP 6-digit untuk aktivasi akun registrasi email.
  /// Endpoint: POST /api/register/verify-otp
  /// Mengembalikan true dan menyimpan Sanctum Bearer token jika berhasil.
  static Future<bool> verifyRegisterOtp({
    required int userId,
    required String code,
  }) async {
    lastRegisterError = null;
    final cleanCode = code.trim();

    if (cleanCode.length != 6) {
      lastRegisterError = 'Please enter the 6-digit verification code.';
      return false;
    }

    try {
      final res = await ApiService.post(
        ApiConfig.registerVerifyOtpEndpoint,
        body: {
          'user_id': userId,
          'kode': cleanCode,
        },
        requiresAuth: false,
      );

      if (res.success && res.data is Map) {
        final dataMap = res.data as Map;
        final token = dataMap['token']?.toString();
        final user = dataMap['user'];

        if (token != null && token.isNotEmpty) {
          await _storage.write(key: _tokenKey, value: token);
          final userEmail = (user is Map ? user['email']?.toString() : null);
          if (userEmail != null) {
            await _storage.write(key: _userKey, value: userEmail);
          }
          final userName = (user is Map ? (user['nama'] ?? user['name'])?.toString() : null);
          if (userName != null) {
            await _storage.write(key: _userNameKey, value: userName);
          }
          final role = (user is Map ? user['role']?.toString() : null) ?? 'user';
          await _storage.write(key: _roleKey, value: role);

          if (user is Map && user['mata_uang'] != null) {
            final curr = user['mata_uang'].toString().toUpperCase();
            if (supportedCurrencies.contains(curr)) {
              preferredCurrency.value = curr;
            }
          }

          return true;
        }
      }

      if (res.message != null && res.message!.isNotEmpty) {
        lastRegisterError = res.message;
      } else {
        lastRegisterError = 'Invalid verification code. Please check and try again.';
      }
      return false;
    } catch (e) {
      lastRegisterError = 'Connection error: ${e.toString()}';
      return false;
    }
  }

  /// Simpan sesi user lokal (fallback offline testing)
  static Future<void> saveLocalUser({required String name, required String email}) async {
    await _storage.write(key: _tokenKey, value: _dummyToken);
    await _storage.write(key: _userKey, value: email);
    await _storage.write(key: _userNameKey, value: name);
    await _storage.write(key: _roleKey, value: 'user');
    AdminDummyData.registerUser(name: name, email: email);
  }

  /// Register dengan nama, email, dan password.
  static Future<bool> register(
    String name,
    String email,
    String password,
  ) async {
    final userId = await registerUser(name, email, password);
    if (userId != null) {
      AdminDummyData.registerUser(name: name, email: email);
      return true;
    }
    return false;
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

  /// Sinkronisasikan mata uang user ke database backend Laravel (/api/account/currency)
  static Future<bool> syncCurrencyToBackend([String currency = 'IDR']) async {
    try {
      final token = await getToken();
      if (token != null && token.isNotEmpty) {
        final res = await ApiService.patch(
          ApiConfig.switchCurrencyEndpoint,
          body: {'mata_uang': currency},
        );
        return res.success;
      }
      return false;
    } catch (_) {
      return false;
    }
  }
}
