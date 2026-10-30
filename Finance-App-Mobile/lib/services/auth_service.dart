import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:google_sign_in/google_sign_in.dart';

/// Service untuk autentikasi. Sesuai dengan alur Laravel Auth di projek PKL.
class AuthService {
  static const _storage = FlutterSecureStorage();
  static const _tokenKey = 'auth_token';
  static const _userKey = 'user_email';
  static const _phoneKey = 'user_phone';
  static const _roleKey = 'user_role';

  /// Dummy token untuk testing mobile. Backend akan mengirim token asli.
  static const _dummyToken = 'dummy_token_12345678';

  // Client ID Web dari 'Finance App Local' (akhiran .apps.googleusercontent.com)
  static const String _webClientId = '757572462289-h597c8r3iku5574pllb5lha5t8a5362q.apps.googleusercontent.com';

  static final GoogleSignIn _googleSignIn = GoogleSignIn(
    serverClientId: _webClientId,
    scopes: <String>[
      'email',
      'profile',
    ],
  );

  /// Alur Google Sign-In: mengambil idToken Google
  static Future<String?> signInWithGoogle() async {
    try {
      final GoogleSignInAccount? account = await _googleSignIn.signIn();
      if (account == null) {
        return null; // Pengguna membatalkan dialog
      }

      final GoogleSignInAuthentication auth = await account.authentication;
      final email = account.email;
      final token = auth.idToken ?? _dummyToken;

      await _storage.write(key: _tokenKey, value: token);
      await _storage.write(key: _userKey, value: email);

      final cleanId = email.trim().toLowerCase();
      if (cleanId == 'admin@finance.test' || cleanId == 'admin@finance.app') {
        await _storage.write(key: _roleKey, value: 'admin');
      } else {
        await _storage.write(key: _roleKey, value: 'user');
      }

      return auth.idToken;
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
  /// Sesuai LoginRequest di Laravel.
  static Future<bool> login(
    String identifier,
    String password, {
    bool isPhone = false,
  }) async {
    if (identifier.isEmpty || password.isEmpty) return false;
    if (password.length < 6) return false;

    await _storage.write(key: _tokenKey, value: _dummyToken);
    final cleanId = identifier.trim().toLowerCase();

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

  /// Ambil nomor telepon user yang login.
  static Future<String?> getUserPhone() async {
    return await _storage.read(key: _phoneKey);
  }
}