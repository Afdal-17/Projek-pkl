import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:google_sign_in/google_sign_in.dart';
import '../data/admin_dummy_data.dart';
import '../data/dummy_data.dart';
import '../utils/currency.dart';
import 'api_config.dart';
import 'api_service.dart';
import 'notification_service.dart';

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

  /// Pesan error terakhir dari server saat request link forgot password
  static String? lastForgotPasswordError;

  /// Pesan error terakhir dari server saat request reset password
  static String? lastResetPasswordError;

  /// Pesan error terakhir dari server saat request OTP pemulihan via phone
  static String? lastForgotPasswordPhoneError;

  /// Pesan error terakhir dari server saat reset password via phone
  static String? lastResetPasswordPhoneError;

  /// URL Base API Backend
  static const String baseUrl = ApiConfig.baseUrl;

  /// Dummy token untuk fallback testing offline
  static const _dummyToken = 'dummy_token_12345678';

  static GoogleSignInAccount? lastGoogleAccount;
  static bool lastGoogleRequiresOtp = false;
  static int? lastGoogleUserId;
  static String? lastGoogleEmail;

  static final GoogleSignIn _googleSignIn = GoogleSignIn(
    scopes: <String>[
      'email',
      'profile',
    ],
  );

  /// Alur Google Sign-In: mengambil profil Google dan verifikasi ke Laravel Backend (/api/auth/google)
  /// [mode]: 'login' (untuk halaman Sign In) atau 'register' (untuk halaman Sign Up)
  static Future<String?> signInWithGoogle({String mode = 'login'}) async {
    try {
      lastGoogleRequiresOtp = false;
      lastGoogleUserId = null;
      lastGoogleEmail = null;

      final GoogleSignInAccount? account = await _googleSignIn.signIn();
      if (account == null) {
        return null; // Pengguna membatalkan dialog
      }
      lastGoogleAccount = account;

      final GoogleSignInAuthentication auth = await account.authentication;
      final email = account.email;
      final idToken = auth.idToken;

      // 1. Kirim data profil & Google ID ke endpoint API Laravel Backend (/api/auth/google)
      //    untuk ditukar dengan Sanctum Bearer Token resmi
      try {
        final payload = {
          'email': account.email,
          'google_id': account.id,
          'mode': mode,
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

          if (dataMap['requires_otp'] == true) {
            lastGoogleRequiresOtp = true;
            final rawUserId = dataMap['user_id'];
            if (rawUserId != null) {
              lastGoogleUserId = (rawUserId is num)
                  ? rawUserId.toInt()
                  : int.tryParse(rawUserId.toString());
            }
            lastGoogleEmail = dataMap['email']?.toString() ?? email;
            lastLoginError = null;
            return null;
          }

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

            lastGoogleAccount = null;
            return sanctumToken;
          }
        } else {
          final raw = res.message ?? '';
          if (raw.toLowerCase().contains('tidak ditemukan') ||
              raw.toLowerCase().contains('registrasi terlebih dahulu') ||
              raw.toLowerCase().contains('belum terdaftar') ||
              raw.toLowerCase().contains('not found')) {
            lastLoginError = 'No account found for this Google email. Please sign up first.';
          } else {
            lastLoginError = res.message ?? 'Google authentication failed on server.';
          }
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

  /// Login langsung menggunakan akun Google (mode = 'login')
  static Future<bool> loginWithGoogle() async {
    final token = await signInWithGoogle(mode: 'login');
    return token != null;
  }

  /// Registrasi akun baru langsung menggunakan akun Google (mode = 'register')
  static Future<bool> registerWithGoogle() async {
    final token = await signInWithGoogle(mode: 'register');
    return token != null;
  }

  /// Login dengan email atau nomor telepon dan password.
  /// Memanggil RESTful API backend /api/login Laravel Sanctum.
  static Future<bool> login(
    String identifier,
    String password, {
    bool isPhone = false,
  }) async {
    if (identifier.isEmpty || password.isEmpty) return false;
    if (password.length < 6) return false;

    lastLoginError = null;
    final cleanId = identifier.trim().toLowerCase();

    try {
      final body = <String, dynamic>{
        'password': password,
      };
      if (isPhone) {
        body['phone'] = identifier.trim();
        body['email'] = identifier.trim();
      } else {
        body['email'] = identifier.trim();
      }

      final res = await ApiService.post(
        ApiConfig.loginEndpoint,
        body: body,
        requiresAuth: false,
      );

      if (res.success && res.data is Map) {
        final dataMap = res.data as Map;
        final token = dataMap['token']?.toString();
        final user = dataMap['user'];

        if (token != null && token.isNotEmpty) {
          DummyData.reset();
          AdminDummyData.reset();
          await _storage.write(key: _tokenKey, value: token);
          await _storage.write(
            key: _userKey,
            value: (user is Map ? user['email']?.toString() : null) ?? identifier,
          );

          final userName = (user is Map ? (user['nama'] ?? user['name'])?.toString() : null);
          if (userName != null) {
            await _storage.write(key: _userNameKey, value: userName);
          }

          if (isPhone) {
            await _storage.write(key: _phoneKey, value: identifier);
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

      final isNetworkFailure = res.message != null && res.message!.startsWith('Connection failed:');
      if (!isNetworkFailure) {
        final raw = res.message ?? '';
        if (raw.toLowerCase().contains('tidak ditemukan') ||
            raw.toLowerCase().contains('registrasi terlebih dahulu') ||
            raw.toLowerCase().contains('belum terdaftar') ||
            raw.toLowerCase().contains('not found')) {
          lastLoginError = 'Account not found. Please sign up first.';
        } else {
          lastLoginError = res.message ?? 'Invalid email or password.';
        }
        return false;
      }
    } catch (e) {
      // Backend tidak dapat dihubungi
      lastLoginError = 'Connection failed: ${e.toString()}';
      return false;
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

  /// Request link/token reset password ke email menggunakan Backend API (/api/forgot-password).
  static Future<bool> forgotPassword(String email) async {
    lastForgotPasswordError = null;
    final cleanEmail = email.trim().toLowerCase();
    if (cleanEmail.isEmpty || !cleanEmail.contains('@')) {
      lastForgotPasswordError = 'A valid email address is required.';
      return false;
    }

    try {
      final res = await ApiService.post(
        ApiConfig.forgotPasswordEndpoint,
        body: {'email': cleanEmail},
        requiresAuth: false,
      );

      if (res.success) {
        return true;
      }

      lastForgotPasswordError = res.message ?? 'Failed to send reset link. Please try again.';
      return false;
    } catch (e) {
      lastForgotPasswordError = 'Connection failed: ${e.toString()}';
      return false;
    }
  }

  /// Reset password dengan token dari email melalui Backend API (/api/reset-password).
  static Future<bool> resetPassword({
    required String email,
    required String token,
    required String newPassword,
    required String confirmPassword,
  }) async {
    lastResetPasswordError = null;
    final cleanEmail = email.trim().toLowerCase();
    final cleanToken = token.trim();

    if (cleanEmail.isEmpty || !cleanEmail.contains('@')) {
      lastResetPasswordError = 'A valid email address is required.';
      return false;
    }
    if (cleanToken.isEmpty) {
      lastResetPasswordError = 'Verification token is required.';
      return false;
    }
    if (newPassword.length < 8) {
      lastResetPasswordError = 'Password must be at least 8 characters.';
      return false;
    }
    if (newPassword != confirmPassword) {
      lastResetPasswordError = 'Passwords do not match.';
      return false;
    }

    try {
      final res = await ApiService.post(
        ApiConfig.resetPasswordEndpoint,
        body: {
          'email': cleanEmail,
          'token': cleanToken,
          'password': newPassword,
          'password_confirmation': confirmPassword,
        },
        requiresAuth: false,
      );

      if (res.success) {
        return true;
      }

      lastResetPasswordError = res.message ?? 'Failed to reset password. Please check your token.';
      return false;
    } catch (e) {
      lastResetPasswordError = 'Connection failed: ${e.toString()}';
      return false;
    }
  }

  /// Request kode OTP pemulihan password ke nomor WhatsApp melalui Backend API (/api/forgot-password/phone).
  static Future<bool> requestPhonePasswordResetOtp(String phone) async {
    lastForgotPasswordPhoneError = null;
    final cleanPhone = phone.trim();
    final digits = cleanPhone.replaceAll(RegExp(r'\D'), '');

    if (cleanPhone.isEmpty || digits.length < 8 || digits.length > 15) {
      lastForgotPasswordPhoneError = 'Please enter a valid phone number (8-15 digits).';
      return false;
    }

    try {
      final res = await ApiService.post(
        ApiConfig.forgotPasswordPhoneEndpoint,
        body: {'phone': cleanPhone},
        requiresAuth: false,
      );

      if (res.success) {
        return true;
      }

      if (res.message != null && res.message!.isNotEmpty) {
        lastForgotPasswordPhoneError = res.message;
      } else {
        lastForgotPasswordPhoneError = 'Failed to send recovery OTP. Please try again.';
      }
      return false;
    } catch (e) {
      lastForgotPasswordPhoneError = 'Connection failed: ${e.toString()}';
      return false;
    }
  }

  /// Verifikasi kode OTP 6-digit untuk pemulihan password via WhatsApp (/api/forgot-password/phone/verify).
  /// Mengembalikan reset_token (String) jika verifikasi berhasil, atau null jika gagal.
  static Future<String?> verifyPhonePasswordResetOtp(String phone, String code) async {
    lastForgotPasswordPhoneError = null;
    final cleanPhone = phone.trim();
    final cleanCode = code.trim();

    if (cleanPhone.isEmpty) {
      lastForgotPasswordPhoneError = 'Phone number is required.';
      return null;
    }
    if (cleanCode.length != 6 || int.tryParse(cleanCode) == null) {
      lastForgotPasswordPhoneError = 'Please enter a valid 6-digit verification code.';
      return null;
    }

    try {
      final res = await ApiService.post(
        ApiConfig.forgotPasswordPhoneVerifyEndpoint,
        body: {
          'phone': cleanPhone,
          'code': cleanCode,
        },
        requiresAuth: false,
      );

      if (res.success && res.data is Map) {
        final data = res.data as Map;
        final token = data['reset_token']?.toString();
        if (token != null && token.isNotEmpty) {
          return token;
        }
      }

      if (res.message != null && res.message!.isNotEmpty) {
        if (res.message!.contains('tidak valid') || res.message!.contains('kedaluwarsa')) {
          lastForgotPasswordPhoneError = 'Recovery code is invalid or has expired.';
        } else {
          lastForgotPasswordPhoneError = res.message;
        }
      } else {
        lastForgotPasswordPhoneError = 'Invalid or expired verification code.';
      }
      return null;
    } catch (e) {
      lastForgotPasswordPhoneError = 'Connection failed: ${e.toString()}';
      return null;
    }
  }

  /// Reset password baru dengan nomor telepon dan reset_token via Backend API (/api/reset-password/phone).
  static Future<bool> resetPasswordWithPhone({
    required String phone,
    required String resetToken,
    required String newPassword,
    required String confirmPassword,
  }) async {
    lastResetPasswordPhoneError = null;
    final cleanPhone = phone.trim();
    final cleanToken = resetToken.trim();

    if (cleanPhone.isEmpty) {
      lastResetPasswordPhoneError = 'Phone number is required.';
      return false;
    }
    if (cleanToken.isEmpty) {
      lastResetPasswordPhoneError = 'Reset token is required.';
      return false;
    }
    if (newPassword.length < 8) {
      lastResetPasswordPhoneError = 'Password must be at least 8 characters.';
      return false;
    }
    if (newPassword != confirmPassword) {
      lastResetPasswordPhoneError = 'Passwords do not match.';
      return false;
    }

    try {
      final res = await ApiService.post(
        ApiConfig.resetPasswordPhoneEndpoint,
        body: {
          'phone': cleanPhone,
          'reset_token': cleanToken,
          'password': newPassword,
          'password_confirmation': confirmPassword,
        },
        requiresAuth: false,
      );

      if (res.success) {
        return true;
      }

      if (res.message != null && res.message!.isNotEmpty) {
        if (res.message!.contains('tidak valid') || res.message!.contains('kedaluwarsa')) {
          lastResetPasswordPhoneError = 'Recovery token is invalid or has expired. Please try again.';
        } else {
          lastResetPasswordPhoneError = res.message;
        }
      } else {
        lastResetPasswordPhoneError = 'Failed to reset password. Please try again.';
      }
      return false;
    } catch (e) {
      lastResetPasswordPhoneError = 'Connection failed: ${e.toString()}';
      return false;
    }
  }

  /// Registrasi akun baru ke REST API backend Laravel (/api/register).
  /// Menerima nama, email dan/atau nomor telepon, dan password.
  /// Mengembalikan userId (int) jika registrasi berhasil dan kode OTP dikirim.
  /// Mengembalikan null jika validasi gagal atau terjadi error.
  static Future<int?> registerUser({
    required String name,
    String? email,
    String? phone,
    required String password,
  }) async {
    lastRegisterError = null;
    final cleanName = name.trim();
    final cleanEmail = email?.trim().toLowerCase();
    final cleanPhone = phone?.trim();

    if (cleanName.isEmpty) {
      lastRegisterError = 'Full name is required.';
      return null;
    }

    final hasEmail = cleanEmail != null && cleanEmail.isNotEmpty;
    final hasPhone = cleanPhone != null && cleanPhone.isNotEmpty;

    if (!hasEmail && !hasPhone) {
      lastRegisterError = 'An email address or phone number is required.';
      return null;
    }

    if (hasEmail && !cleanEmail.contains('@')) {
      lastRegisterError = 'A valid email address is required.';
      return null;
    }

    if (password.length < 8) {
      lastRegisterError = 'Password must be at least 8 characters.';
      return null;
    }

    try {
      final body = <String, dynamic>{
        'nama': cleanName,
        'password': password,
        'password_confirmation': password,
      };

      if (hasPhone) {
        body['phone'] = cleanPhone;
        body['nomor_telepon'] = cleanPhone;
      }
      if (hasEmail) {
        body['email'] = cleanEmail;
      }

      final res = await ApiService.post(
        ApiConfig.registerEndpoint,
        body: body,
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
        final raw = res.message!;
        if (raw.toLowerCase().contains('target class') ||
            raw.toLowerCase().contains('does not exist')) {
          lastRegisterError =
              'Server error: Registration controller is not configured on backend. Please report to the backend developer.';
        } else {
          lastRegisterError = raw;
        }
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
          DummyData.reset();
          AdminDummyData.reset();
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

  /// Kirim ulang kode OTP aktivasi akun registrasi email/Google.
  /// Endpoint: POST /api/register/resend-otp
  static Future<bool> resendRegisterOtp(int userId) async {
    lastRegisterError = null;
    try {
      final res = await ApiService.post(
        ApiConfig.registerResendOtpEndpoint,
        body: {'user_id': userId},
        requiresAuth: false,
      );

      if (res.success) {
        return true;
      }

      if (res.message != null && res.message!.isNotEmpty) {
        lastRegisterError = res.message;
      } else {
        lastRegisterError = 'Failed to resend verification code.';
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
    final userId = await registerUser(name: name, email: email, password: password);
    if (userId != null) {
      AdminDummyData.registerUser(name: name, email: email);
      return true;
    }
    return false;
  }

  /// Logout (sekaligus mencabut Sanctum token di backend dan membersihkan sesi Google Sign-In jika ada).
  static Future<void> logout() async {
    try {
      final token = await getToken();
      if (token != null && token.isNotEmpty && token != _dummyToken) {
        await ApiService.post(ApiConfig.logoutEndpoint);
      }
    } catch (_) {}

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

    // Bersihkan data profil & foto dari RAM agar tidak bocor ke akun berikutnya
    DummyData.reset();
    AdminDummyData.reset();
    NotificationService.reset();
    lastGoogleAccount = null;
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
