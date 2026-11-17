import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:google_sign_in/google_sign_in.dart';
import '../data/admin_dummy_data.dart';
import '../services/auth_service.dart';
import '../theme/app_colors.dart';
import '../widgets/app_button.dart';
import '../widgets/app_card.dart';
import '../widgets/google_logo.dart';
import 'otp_verification_screen.dart';
import 'reset_password_screen.dart';

TextStyle _inter(double size, FontWeight weight, Color color) =>
    GoogleFonts.inter(fontSize: size, fontWeight: weight, color: color);

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.onLoginSuccess});

  final VoidCallback onLoginSuccess;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();

  /// Mode login: true = Phone Number, false = Email / Gmail (Default)
  bool _isPhoneMode = false;

  bool _isLoading = false;
  bool _obscurePassword = true;
  bool _rememberMe = false;
  String? _errorMessage;

  @override
  void dispose() {
    _emailController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    setState(() {
      _errorMessage = null;
    });

    final password = _passwordController.text;
    if (password.isEmpty) {
      setState(() => _errorMessage = 'Please enter your password.');
      return;
    }

    if (_isPhoneMode) {
      final phone = _phoneController.text.trim();
      if (phone.isEmpty) {
        setState(() => _errorMessage = 'Please enter your phone number.');
        return;
      }

      setState(() => _isLoading = true);
      final success = await AuthService.login(phone, password, isPhone: true);
      if (!mounted) return;
      setState(() => _isLoading = false);

      if (success) {
        widget.onLoginSuccess();
      } else {
        final err = AuthService.lastLoginError ?? 'Invalid phone number or password.';
        setState(() {
          _errorMessage = err;
        });
        if (err.toLowerCase().contains('suspended') ||
            err.toLowerCase().contains('ditangguhkan')) {
          _showSuspendedAccountDialog(err);
        }
      }
    } else {
      final email = _emailController.text.trim();
      if (email.isEmpty || !email.contains('@')) {
        setState(() => _errorMessage = 'Please enter a valid email address.');
        return;
      }

      setState(() => _isLoading = true);
      final success = await AuthService.login(email, password);
      if (!mounted) return;
      setState(() => _isLoading = false);

      if (success) {
        widget.onLoginSuccess();
      } else {
        final err = AuthService.lastLoginError ?? 'Invalid email or password.';
        setState(() {
          _errorMessage = err;
        });
        if (err.toLowerCase().contains('suspended') ||
            err.toLowerCase().contains('ditangguhkan')) {
          _showSuspendedAccountDialog(err);
        }
      }
    }
  }

  Future<void> _handleGoogleLogin() async {
    setState(() {
      _errorMessage = null;
      _isLoading = true;
    });

    try {
      final success = await AuthService.loginWithGoogle();
      if (!mounted) return;
      setState(() => _isLoading = false);

      if (success) {
        widget.onLoginSuccess();
      } else if (AuthService.lastGoogleRequiresOtp && AuthService.lastGoogleUserId != null) {
        final userId = AuthService.lastGoogleUserId!;
        final email = AuthService.lastGoogleEmail ?? '';
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => OtpVerificationScreen(
              userId: userId,
              targetEmail: email,
              isWhatsApp: false,
              onVerificationSuccess: () {
                Navigator.of(context).pop();
                widget.onLoginSuccess();
              },
            ),
          ),
        );
      } else {
        final err = AuthService.lastLoginError ?? 'Google sign-in failed.';
        final googleAcc = AuthService.lastGoogleAccount;
        if (googleAcc != null &&
            (err.toLowerCase().contains('not found') ||
                err.toLowerCase().contains('tidak ditemukan') ||
                err.toLowerCase().contains('sign up first') ||
                err.toLowerCase().contains('belum terdaftar'))) {
          setState(() => _errorMessage = err);
          _showGoogleSignUpPrompt(googleAcc);
        } else {
          setState(() => _errorMessage = err);
          if (err.toLowerCase().contains('suspended') ||
              err.toLowerCase().contains('ditangguhkan')) {
            _showSuspendedAccountDialog(err);
          }
        }
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = 'Google sign-in error: ${e.toString()}';
      });
    }
  }

  void _showGoogleSignUpPrompt(GoogleSignInAccount googleAcc) {
    showDialog(
      context: context,
      barrierDismissible: true,
      builder: (ctx) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        backgroundColor: Colors.white,
        insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
        child: Padding(
          padding: const EdgeInsets.all(22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: const Color(0xFFEFF6FF),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.person_add_alt_1_rounded,
                      color: Color(0xFF2563EB),
                      size: 22,
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Create New Account?',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                        ),
                        SizedBox(height: 3),
                        Text(
                          'No account was found for this Google email',
                          style: TextStyle(
                            fontSize: 12,
                            color: AppColors.muted,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Text(
                'Would you like to automatically create and register an account for ${googleAcc.email}?',
                style: const TextStyle(
                  fontSize: 14,
                  color: AppColors.dark,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.of(ctx).pop(),
                      style: OutlinedButton.styleFrom(
                        side: const BorderSide(color: AppColors.line),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text(
                        'Cancel',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: AppColors.ink,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () async {
                        Navigator.of(ctx).pop();
                        setState(() => _isLoading = true);
                        final regSuccess =
                            await AuthService.registerWithGoogle();
                        if (!mounted) return;
                        setState(() => _isLoading = false);
                        if (regSuccess) {
                          widget.onLoginSuccess();
                        } else if (AuthService.lastGoogleRequiresOtp && AuthService.lastGoogleUserId != null) {
                          final userId = AuthService.lastGoogleUserId!;
                          final email = AuthService.lastGoogleEmail ?? '';
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => OtpVerificationScreen(
                                userId: userId,
                                targetEmail: email,
                                isWhatsApp: false,
                                onVerificationSuccess: () {
                                  Navigator.of(context).pop();
                                  widget.onLoginSuccess();
                                },
                              ),
                            ),
                          );
                        } else {
                          setState(() {
                            _errorMessage = AuthService.lastLoginError ??
                                'Failed to create Google account.';
                          });
                        }
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.dark,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text(
                        'Create Account',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: Colors.white,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showSuspendedAccountDialog(String rawMessage) {
    String reason = 'Violation of terms of service';
    if (rawMessage.contains(':')) {
      final parts = rawMessage.split(':');
      if (parts.length > 1 && parts.sublist(1).join(':').trim().isNotEmpty) {
        reason = parts.sublist(1).join(':').trim();
      }
    }

    showDialog(
      context: context,
      barrierDismissible: true,
      builder: (ctx) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        backgroundColor: Colors.white,
        insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
        child: Padding(
          padding: const EdgeInsets.all(22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEE2E2),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.block_rounded,
                      color: Color(0xFFDC2626),
                      size: 22,
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Account Suspended',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                        ),
                        SizedBox(height: 3),
                        Text(
                          'Your account access has been temporarily restricted',
                          style: TextStyle(
                            fontSize: 12,
                            color: AppColors.muted,
                          ),
                        ),
                      ],
                    ),
                  ),
                  InkWell(
                    onTap: () => Navigator.of(ctx).pop(),
                    borderRadius: BorderRadius.circular(8),
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        border: Border.all(color: AppColors.line),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Icon(
                        Icons.close_rounded,
                        size: 16,
                        color: AppColors.muted,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF2F2),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFFECACA)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'REASON FROM ADMIN',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.5,
                        color: Color(0xFFDC2626),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      reason,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF7F1D1D),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF9FAFB),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.line),
                ),
                child: const Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Suspension Duration:',
                          style: TextStyle(fontSize: 12, color: AppColors.muted),
                        ),
                        Text(
                          '30 Days',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: AppColors.ink,
                          ),
                        ),
                      ],
                    ),
                    SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Remaining Time:',
                          style: TextStyle(fontSize: 12, color: AppColors.muted),
                        ),
                        Text(
                          '29 days left',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFFDC2626),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () {
                        Navigator.of(ctx).pop();
                      },
                      icon: const Icon(
                        Icons.mail_outline_rounded,
                        size: 16,
                        color: Colors.white,
                      ),
                      label: const Text(
                        'Contact Admin',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: Colors.white,
                        ),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.dark,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  OutlinedButton(
                    onPressed: () => Navigator.of(ctx).pop(),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: AppColors.line),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 18,
                        vertical: 12,
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text(
                      'Close',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: AppColors.ink,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  InputDecoration _fieldDecoration({required String hint, Widget? suffixIcon}) {
    final border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppColors.line),
    );

    return InputDecoration(
      hintText: hint,
      hintStyle: _inter(15, FontWeight.w400, AppColors.muted),
      filled: true,
      fillColor: Colors.white,
      suffixIcon: suffixIcon,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: border,
      enabledBorder: border,
      focusedBorder: border.copyWith(
        borderSide: const BorderSide(color: AppColors.ink, width: 1.5),
      ),
    );
  }

  Widget _label(String text) =>
      Text(text, style: _inter(14, FontWeight.w500, AppColors.ink));

  Widget _outlineButton({
    required Widget icon,
    required String label,
    required VoidCallback onPressed,
  }) {
    return SizedBox(
      height: 48,
      child: OutlinedButton(
        onPressed: _isLoading ? null : onPressed,
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.ink,
          side: const BorderSide(color: AppColors.line),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            icon,
            const SizedBox(width: 10),
            Text(label, style: _inter(15, FontWeight.w500, AppColors.ink)),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.page,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
            child: AppCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Welcome back',
                    textAlign: TextAlign.center,
                    style: _inter(26, FontWeight.w700, AppColors.ink),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Sign in to continue to your finances.',
                    textAlign: TextAlign.center,
                    style: _inter(15, FontWeight.w400, AppColors.muted),
                  ),
                  const SizedBox(height: 28),

                  // PHONE NUMBER OR EMAIL INPUT (Image 3)
                  if (_isPhoneMode) ...[
                    _label('Phone Number'),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _phoneController,
                      enabled: !_isLoading,
                      keyboardType: TextInputType.phone,
                      style: _inter(15, FontWeight.w400, AppColors.ink),
                      decoration: _fieldDecoration(
                        hint: '08123456789 or +628123456789',
                      ),
                    ),
                  ] else ...[
                    _label('Email'),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _emailController,
                      enabled: !_isLoading,
                      keyboardType: TextInputType.emailAddress,
                      style: _inter(15, FontWeight.w400, AppColors.ink),
                      decoration: _fieldDecoration(hint: 'alex@example.com'),
                    ),
                  ],
                  const SizedBox(height: 16),

                  // PASSWORD
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      _label('Password'),
                      GestureDetector(
                        onTap: _isLoading
                            ? null
                            : () => _goToForgotPassword(context),
                        child: Text(
                          'Forgot password?',
                          style: _inter(13, FontWeight.w500, AppColors.muted),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _passwordController,
                    enabled: !_isLoading,
                    obscureText: _obscurePassword,
                    style: _inter(15, FontWeight.w400, AppColors.ink),
                    decoration: _fieldDecoration(
                      hint: 'Enter your password',
                      suffixIcon: IconButton(
                        icon: Icon(
                          _obscurePassword
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                          color: AppColors.muted,
                          size: 20,
                        ),
                        onPressed: () => setState(
                          () => _obscurePassword = !_obscurePassword,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),

                  // REMEMBER ME
                  Row(
                    children: [
                      SizedBox(
                        width: 20,
                        height: 20,
                        child: Checkbox(
                          value: _rememberMe,
                          activeColor: AppColors.dark,
                          materialTapTargetSize:
                              MaterialTapTargetSize.shrinkWrap,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(4),
                          ),
                          onChanged: _isLoading
                              ? null
                              : (v) => setState(() => _rememberMe = v ?? false),
                        ),
                      ),
                      const SizedBox(width: 8),
                      GestureDetector(
                        onTap: _isLoading
                            ? null
                            : () => setState(() => _rememberMe = !_rememberMe),
                        child: Text(
                          'Remember me',
                          style: _inter(14, FontWeight.w400, AppColors.ink),
                        ),
                      ),
                    ],
                  ),

                  // ERROR
                  if (_errorMessage != null) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.expenseSoft,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        _errorMessage!,
                        style: _inter(13, FontWeight.w500, AppColors.expense),
                      ),
                    ),
                  ],

                  const SizedBox(height: 20),

                  // SIGN IN
                  AppButton(
                    label: _isLoading ? 'Signing in...' : 'Sign in',
                    onPressed: _isLoading ? () {} : _handleLogin,
                    background: _isLoading ? AppColors.muted : AppColors.dark,
                  ),

                  const SizedBox(height: 20),

                  // OR DIVIDER
                  Row(
                    children: [
                      const Expanded(child: Divider(color: AppColors.line)),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        child: Text(
                          'OR',
                          style: _inter(12, FontWeight.w500, AppColors.muted),
                        ),
                      ),
                      const Expanded(child: Divider(color: AppColors.line)),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // GOOGLE BUTTON
                  _outlineButton(
                    icon: const GoogleLogo(size: 20),
                    label: 'Continue with Google',
                    onPressed: _isLoading ? () {} : _handleGoogleLogin,
                  ),
                  const SizedBox(height: 12),

                  // TOGGLE BUTTON: Continue with Email or Continue with Phone
                  if (_isPhoneMode) ...[
                    _outlineButton(
                      icon: const Icon(
                        Icons.mail_outline_rounded,
                        size: 20,
                        color: AppColors.ink,
                      ),
                      label: 'Continue with Email',
                      onPressed: () {
                        setState(() {
                          _isPhoneMode = false;
                          _errorMessage = null;
                        });
                      },
                    ),
                  ] else ...[
                    _outlineButton(
                      icon: const Icon(
                        Icons.phone_iphone_outlined,
                        size: 20,
                        color: AppColors.ink,
                      ),
                      label: 'Continue with Phone',
                      onPressed: () {
                        setState(() {
                          _isPhoneMode = true;
                          _errorMessage = null;
                        });
                      },
                    ),
                  ],

                  const SizedBox(height: 24),

                  // FOOTER
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        "Don't have an account? ",
                        style: _inter(14, FontWeight.w400, AppColors.muted),
                      ),
                      GestureDetector(
                        onTap: _isLoading ? null : () => _goToRegister(context),
                        child: Text(
                          'Sign up',
                          style: _inter(14, FontWeight.w600, AppColors.ink),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _goToForgotPassword(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const ForgotPasswordScreen()),
    );
  }

  void _goToRegister(BuildContext context, {String? initialName, String? initialEmail}) {
    setState(() => _errorMessage = null);
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => RegisterScreen(
          initialName: initialName,
          initialEmail: initialEmail,
          onRegisterSuccess: () {
            Navigator.of(context).pop();
            widget.onLoginSuccess();
          },
        ),
      ),
    );
  }
}

enum ForgotPasswordMethod { email, phone }

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  ForgotPasswordMethod _method = ForgotPasswordMethod.email;

  // Controllers
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  // OTP Verification state
  static const int _otpLength = 6;
  late final List<TextEditingController> _otpControllers;
  late final List<FocusNode> _otpFocusNodes;

  // Phone steps: 0 = Input Phone, 1 = Verify OTP, 2 = Set New Password, 3 = Success
  int _phoneStep = 0;
  String? _verifiedResetToken;

  // Countdown timer for OTP resend
  Timer? _resendTimer;
  int _resendCountdown = 0;

  // Password visibility
  bool _obscureNewPassword = true;
  bool _obscureConfirmPassword = true;

  // Status
  bool _isLoading = false;
  bool _emailSuccess = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _otpControllers = List.generate(_otpLength, (_) => TextEditingController());
    _otpFocusNodes = List.generate(_otpLength, (_) => FocusNode());
  }

  @override
  void dispose() {
    _emailController.dispose();
    _phoneController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    _resendTimer?.cancel();
    for (final c in _otpControllers) {
      c.dispose();
    }
    for (final f in _otpFocusNodes) {
      f.dispose();
    }
    super.dispose();
  }

  void _startResendTimer() {
    _resendTimer?.cancel();
    setState(() => _resendCountdown = 60);
    _resendTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      if (_resendCountdown > 0) {
        setState(() => _resendCountdown--);
      } else {
        timer.cancel();
      }
    });
  }

  void _onBackPressed() {
    if (_method == ForgotPasswordMethod.phone) {
      if (_phoneStep == 1) {
        setState(() {
          _phoneStep = 0;
          _errorMessage = null;
        });
        return;
      } else if (_phoneStep == 2) {
        setState(() {
          _phoneStep = 1;
          _errorMessage = null;
        });
        return;
      }
    } else if (_emailSuccess) {
      setState(() {
        _emailSuccess = false;
        _errorMessage = null;
      });
      return;
    }
    Navigator.of(context).pop();
  }

  Future<void> _handleEmailReset() async {
    final email = _emailController.text.trim();
    if (email.isEmpty || !email.contains('@')) {
      setState(() => _errorMessage = 'Please enter a valid email address.');
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final result = await AuthService.forgotPassword(email);

    if (!mounted) return;

    if (result) {
      setState(() {
        _emailSuccess = true;
        _isLoading = false;
      });
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage = AuthService.lastForgotPasswordError ??
            'Failed to send reset link. Please check your email and try again.';
      });
    }
  }

  Future<void> _handleSendPhoneOtp({bool isResend = false}) async {
    final phone = _phoneController.text.trim();
    final digits = phone.replaceAll(RegExp(r'\D'), '');

    if (phone.isEmpty || digits.length < 8 || digits.length > 15) {
      setState(() => _errorMessage = 'Please enter a valid phone number (8-15 digits).');
      return;
    }

    if (isResend && _resendCountdown > 0) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final success = await AuthService.requestPhonePasswordResetOtp(phone);

    if (!mounted) return;

    if (success) {
      for (final c in _otpControllers) {
        c.clear();
      }
      setState(() {
        _isLoading = false;
        _phoneStep = 1;
      });
      _startResendTimer();
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _otpFocusNodes.isNotEmpty) {
          _otpFocusNodes[0].requestFocus();
        }
      });
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage = AuthService.lastForgotPasswordPhoneError ??
            'Failed to send verification code. Please try again.';
      });
    }
  }

  String get _otpCode => _otpControllers.map((c) => c.text).join();
  bool get _isOtpComplete => _otpCode.length == _otpLength;

  void _onOtpDigitChanged(int index, String value) {
    if (value.length > 1) {
      final digits = value.replaceAll(RegExp(r'\D'), '');
      for (int i = 0; i < _otpLength && i < digits.length; i++) {
        _otpControllers[i].text = digits[i];
      }
      final nextIndex = digits.length < _otpLength ? digits.length : _otpLength - 1;
      _otpFocusNodes[nextIndex].requestFocus();
      setState(() {});
      if (_isOtpComplete) {
        _handleVerifyPhoneOtp();
      }
      return;
    }

    if (value.isNotEmpty) {
      if (index < _otpLength - 1) {
        _otpFocusNodes[index + 1].requestFocus();
      } else {
        _otpFocusNodes[index].unfocus();
      }
    }
    setState(() {});

    if (_isOtpComplete) {
      _handleVerifyPhoneOtp();
    }
  }

  void _onOtpKeyDown(int index, KeyEvent event) {
    if (event is KeyDownEvent && event.logicalKey == LogicalKeyboardKey.backspace) {
      if (_otpControllers[index].text.isEmpty && index > 0) {
        _otpControllers[index - 1].clear();
        _otpFocusNodes[index - 1].requestFocus();
        setState(() {});
      }
    }
  }

  Future<void> _handleVerifyPhoneOtp() async {
    if (!_isOtpComplete || _isLoading) {
      if (!_isOtpComplete) {
        setState(() => _errorMessage = 'Please enter all 6 digits of the verification code.');
      }
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final phone = _phoneController.text.trim();
    final resetToken = await AuthService.verifyPhonePasswordResetOtp(phone, _otpCode);

    if (!mounted) return;

    if (resetToken != null && resetToken.isNotEmpty) {
      setState(() {
        _isLoading = false;
        _verifiedResetToken = resetToken;
        _phoneStep = 2;
      });
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage = AuthService.lastForgotPasswordPhoneError ??
            'Invalid or expired verification code. Please try again.';
      });
    }
  }

  Future<void> _handleResetPasswordPhone() async {
    final password = _newPasswordController.text;
    final confirm = _confirmPasswordController.text;

    if (password.length < 8) {
      setState(() => _errorMessage = 'Password must be at least 8 characters long.');
      return;
    }
    if (password != confirm) {
      setState(() => _errorMessage = 'Passwords do not match.');
      return;
    }
    if (_verifiedResetToken == null || _verifiedResetToken!.isEmpty) {
      setState(() => _errorMessage = 'Verification session expired. Please verify your phone number again.');
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final phone = _phoneController.text.trim();
    final success = await AuthService.resetPasswordWithPhone(
      phone: phone,
      resetToken: _verifiedResetToken!,
      newPassword: password,
      confirmPassword: confirm,
    );

    if (!mounted) return;

    if (success) {
      setState(() {
        _isLoading = false;
        _phoneStep = 3;
      });
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage = AuthService.lastResetPasswordPhoneError ??
            'Failed to reset password. Please try again.';
      });
    }
  }

  String get _cardTitle {
    if (_emailSuccess) return 'Check your email';
    if (_method == ForgotPasswordMethod.phone) {
      if (_phoneStep == 1) return 'Verify WhatsApp OTP';
      if (_phoneStep == 2) return 'Create New Password';
      if (_phoneStep == 3) return 'Password Reset Successful';
    }
    return 'Forgot password?';
  }

  String get _cardSubtitle {
    if (_emailSuccess) {
      return 'We sent a password reset link to ${_emailController.text.trim()}. Please check your inbox and follow the instructions.';
    }
    if (_method == ForgotPasswordMethod.phone) {
      if (_phoneStep == 1) {
        return "We've sent a 6-digit verification code to ${_phoneController.text.trim()} via WhatsApp.";
      }
      if (_phoneStep == 2) {
        return 'Your phone number has been verified. Enter and confirm your new password below.';
      }
      if (_phoneStep == 3) {
        return 'Your password has been successfully reset. You can now log in with your new password.';
      }
      return 'Enter your registered phone number and we will send you a verification code via WhatsApp.';
    }
    return 'No problem. Enter your email address and we will send you a password reset link.';
  }

  @override
  Widget build(BuildContext context) {
    final canPopDirectly = (_method == ForgotPasswordMethod.email && !_emailSuccess) ||
        (_method == ForgotPasswordMethod.phone && (_phoneStep == 0 || _phoneStep == 3));

    return PopScope(
      canPop: canPopDirectly,
      onPopInvokedWithResult: (didPop, _) {
        if (didPop) return;
        _onBackPressed();
      },
      child: Scaffold(
        backgroundColor: AppColors.page,
        appBar: AppBar(
          backgroundColor: AppColors.page,
          elevation: 0,
          scrolledUnderElevation: 0,
          leadingWidth: 84,
          leading: InkWell(
            onTap: _onBackPressed,
            child: const Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                SizedBox(width: 14),
                Icon(Icons.arrow_back_rounded, size: 20),
                SizedBox(width: 4),
                Text(
                  'Back',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
        ),
        body: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
            child: Column(
              children: [
                const SizedBox(height: 12),
                AppCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // TITLE (Inside AppCard as required - Centered)
                      Center(
                        child: Text(
                          _cardTitle,
                          textAlign: TextAlign.center,
                          style: _inter(22, FontWeight.w800, AppColors.ink),
                        ),
                      ),
                      const SizedBox(height: 8),

                      // SUBTITLE (Inside AppCard as required - Centered)
                      Center(
                        child: Text(
                          _cardSubtitle,
                          textAlign: TextAlign.center,
                          style: _inter(13, FontWeight.w400, AppColors.muted),
                        ),
                      ),

                      // METHOD SELECTOR (Inside AppCard as required)
                      // Only visible on initial step before submission
                      if (!_emailSuccess && (_method == ForgotPasswordMethod.email || _phoneStep == 0)) ...[
                        const SizedBox(height: 18),
                        Container(
                          padding: const EdgeInsets.all(4),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF3F4F6),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Row(
                            children: [
                              Expanded(
                                child: _methodTab(
                                  title: 'Email',
                                  icon: Icons.mail_outline_rounded,
                                  isSelected: _method == ForgotPasswordMethod.email,
                                  onTap: () {
                                    if (_isLoading) return;
                                    setState(() {
                                      _method = ForgotPasswordMethod.email;
                                      _errorMessage = null;
                                    });
                                  },
                                ),
                              ),
                              const SizedBox(width: 4),
                              Expanded(
                                child: _methodTab(
                                  title: 'Phone / WhatsApp',
                                  icon: Icons.chat_bubble_outline_rounded,
                                  isSelected: _method == ForgotPasswordMethod.phone,
                                  onTap: () {
                                    if (_isLoading) return;
                                    setState(() {
                                      _method = ForgotPasswordMethod.phone;
                                      _errorMessage = null;
                                    });
                                  },
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],

                      // ERROR BANNER
                      if (_errorMessage != null) ...[
                        const SizedBox(height: 14),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: AppColors.expenseSoft,
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: AppColors.expense.withValues(alpha: 0.3)),
                          ),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Icon(
                                Icons.error_outline_rounded,
                                size: 18,
                                color: AppColors.expense,
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  _errorMessage!,
                                  style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w500,
                                    color: AppColors.expense,
                                    height: 1.3,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],

                      const SizedBox(height: 20),

                      // DYNAMIC CONTENT BASED ON RECOVERY METHOD & STEP
                      if (_method == ForgotPasswordMethod.email)
                        _buildEmailFlow()
                      else
                        _buildPhoneFlow(),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildEmailFlow() {
    if (_emailSuccess) {
      return Column(
        children: [
          Center(
            child: Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: AppColors.incomeSoft,
                borderRadius: BorderRadius.circular(20),
              ),
              child: const Icon(
                Icons.mark_email_read_outlined,
                color: AppColors.income,
                size: 32,
              ),
            ),
          ),
          const SizedBox(height: 20),
          AppButton(
            label: 'Enter Reset Token / Password',
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => ResetPasswordScreen(
                    initialEmail: _emailController.text.trim(),
                  ),
                ),
              );
            },
            background: AppColors.dark,
          ),
          const SizedBox(height: 12),
          Center(
            child: TextButton(
              onPressed: () {
                setState(() {
                  _emailSuccess = false;
                  _errorMessage = null;
                });
              },
              child: const Text(
                'Try another email or method',
                style: TextStyle(
                  fontSize: 13,
                  color: AppColors.muted,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ),
        ],
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Email Address',
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: AppColors.ink,
          ),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _emailController,
          enabled: !_isLoading,
          keyboardType: TextInputType.emailAddress,
          decoration: InputDecoration(
            hintText: 'your@email.com',
            hintStyle: const TextStyle(color: AppColors.muted),
            prefixIcon: const Icon(Icons.mail_outline_rounded, size: 20, color: AppColors.muted),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: AppColors.line),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: AppColors.line),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(
                color: AppColors.brand,
                width: 2,
              ),
            ),
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 14,
              vertical: 12,
            ),
          ),
        ),
        const SizedBox(height: 20),
        AppButton(
          label: _isLoading ? 'Sending Link...' : 'Email Password Reset Link',
          onPressed: _isLoading ? () {} : _handleEmailReset,
          background: _isLoading ? AppColors.muted : AppColors.dark,
        ),
        const SizedBox(height: 12),
        Center(
          child: TextButton(
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => ResetPasswordScreen(
                    initialEmail: _emailController.text.trim().isNotEmpty
                        ? _emailController.text.trim()
                        : null,
                  ),
                ),
              );
            },
            child: const Text(
              'Have a reset token? Enter new password',
              style: TextStyle(
                fontSize: 13,
                color: AppColors.muted,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildPhoneFlow() {
    switch (_phoneStep) {
      case 0:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Phone Number (WhatsApp)',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _phoneController,
              enabled: !_isLoading,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                hintText: '08123456789 or +628123456789',
                hintStyle: const TextStyle(color: AppColors.muted),
                prefixIcon: const Icon(Icons.phone_outlined, size: 20, color: AppColors.muted),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: AppColors.line),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: AppColors.line),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(
                    color: AppColors.brand,
                    width: 2,
                  ),
                ),
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 12,
                ),
              ),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                const Icon(Icons.info_outline_rounded, size: 14, color: AppColors.muted),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    'Ensure this phone number has an active WhatsApp account.',
                    style: _inter(12, FontWeight.w400, AppColors.muted),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),
            AppButton(
              label: _isLoading ? 'Sending Code...' : 'Send WhatsApp OTP',
              onPressed: _isLoading ? () {} : () => _handleSendPhoneOtp(),
              background: _isLoading ? AppColors.muted : AppColors.dark,
            ),
          ],
        );

      case 1:
        return Column(
          children: [
            // Row displaying the phone number and change button
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFFF9FAFB),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: AppColors.line),
              ),
              child: Row(
                children: [
                  const Icon(Icons.phone_android_rounded, size: 16, color: AppColors.muted),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _phoneController.text.trim(),
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: AppColors.ink,
                      ),
                    ),
                  ),
                  InkWell(
                    onTap: _isLoading
                        ? null
                        : () {
                            setState(() {
                              _phoneStep = 0;
                              _errorMessage = null;
                            });
                          },
                    child: const Text(
                      'Change',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: AppColors.brand,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // 6 OTP Digit Boxes
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: List.generate(_otpLength, (i) {
                return SizedBox(
                  width: 42,
                  height: 52,
                  child: KeyboardListener(
                    focusNode: FocusNode(),
                    onKeyEvent: (event) => _onOtpKeyDown(i, event),
                    child: TextField(
                      controller: _otpControllers[i],
                      focusNode: _otpFocusNodes[i],
                      autofocus: i == 0,
                      textAlign: TextAlign.center,
                      keyboardType: TextInputType.number,
                      inputFormatters: [
                        FilteringTextInputFormatter.digitsOnly,
                      ],
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                        color: AppColors.ink,
                      ),
                      decoration: InputDecoration(
                        counterText: '',
                        contentPadding: EdgeInsets.zero,
                        filled: true,
                        fillColor: Colors.white,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(10),
                          borderSide: const BorderSide(color: AppColors.line),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(10),
                          borderSide: const BorderSide(color: AppColors.line),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(10),
                          borderSide: const BorderSide(
                            color: AppColors.brand,
                            width: 2,
                          ),
                        ),
                      ),
                      onChanged: (val) => _onOtpDigitChanged(i, val),
                    ),
                  ),
                );
              }),
            ),
            const SizedBox(height: 18),

            // Resend timer / button
            if (_resendCountdown > 0)
              Text(
                'Resend code in ${_resendCountdown}s',
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.muted,
                  fontWeight: FontWeight.w500,
                ),
              )
            else
              TextButton.icon(
                onPressed: _isLoading ? null : () => _handleSendPhoneOtp(isResend: true),
                icon: const Icon(Icons.refresh_rounded, size: 16, color: AppColors.brand),
                label: const Text(
                  'Resend Code via WhatsApp',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppColors.brand,
                  ),
                ),
              ),

            const SizedBox(height: 20),
            AppButton(
              label: _isLoading ? 'Verifying...' : 'Verify Code',
              onPressed: _isLoading ? () {} : _handleVerifyPhoneOtp,
              background: _isLoading ? AppColors.muted : AppColors.dark,
            ),
          ],
        );

      case 2:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'New Password',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _newPasswordController,
              enabled: !_isLoading,
              obscureText: _obscureNewPassword,
              decoration: InputDecoration(
                hintText: 'Minimum 8 characters',
                hintStyle: const TextStyle(color: AppColors.muted),
                prefixIcon: const Icon(Icons.lock_outline_rounded, size: 20, color: AppColors.muted),
                suffixIcon: IconButton(
                  icon: Icon(
                    _obscureNewPassword ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                    size: 20,
                    color: AppColors.muted,
                  ),
                  onPressed: () => setState(() => _obscureNewPassword = !_obscureNewPassword),
                ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: AppColors.line),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: AppColors.line),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: AppColors.brand, width: 2),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'Confirm New Password',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _confirmPasswordController,
              enabled: !_isLoading,
              obscureText: _obscureConfirmPassword,
              decoration: InputDecoration(
                hintText: 'Re-enter your new password',
                hintStyle: const TextStyle(color: AppColors.muted),
                prefixIcon: const Icon(Icons.lock_outline_rounded, size: 20, color: AppColors.muted),
                suffixIcon: IconButton(
                  icon: Icon(
                    _obscureConfirmPassword ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                    size: 20,
                    color: AppColors.muted,
                  ),
                  onPressed: () => setState(() => _obscureConfirmPassword = !_obscureConfirmPassword),
                ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: AppColors.line),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: AppColors.line),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: AppColors.brand, width: 2),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              ),
            ),
            const SizedBox(height: 24),
            AppButton(
              label: _isLoading ? 'Updating Password...' : 'Reset Password',
              onPressed: _isLoading ? () {} : _handleResetPasswordPhone,
              background: _isLoading ? AppColors.muted : AppColors.dark,
            ),
          ],
        );

      case 3:
        return Column(
          children: [
            Center(
              child: Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  color: AppColors.incomeSoft,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: const Icon(
                  Icons.check_rounded,
                  color: AppColors.income,
                  size: 36,
                ),
              ),
            ),
            const SizedBox(height: 24),
            AppButton(
              label: 'Back to Sign In',
              onPressed: () => Navigator.of(context).pop(),
              background: AppColors.dark,
            ),
          ],
        );

      default:
        return const SizedBox.shrink();
    }
  }

  Widget _methodTab({
    required String title,
    required IconData icon,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(9),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.symmetric(vertical: 9),
        decoration: BoxDecoration(
          color: isSelected ? Colors.white : Colors.transparent,
          borderRadius: BorderRadius.circular(9),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.06),
                    blurRadius: 4,
                    offset: const Offset(0, 1),
                  ),
                ]
              : null,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              size: 16,
              color: isSelected ? AppColors.ink : AppColors.muted,
            ),
            const SizedBox(width: 6),
            Text(
              title,
              style: _inter(
                13,
                isSelected ? FontWeight.w700 : FontWeight.w500,
                isSelected ? AppColors.ink : AppColors.muted,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({
    super.key,
    required this.onRegisterSuccess,
    this.initialName,
    this.initialEmail,
  });

  final VoidCallback onRegisterSuccess;
  final String? initialName;
  final String? initialEmail;

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  late final TextEditingController _nameController;
  late final TextEditingController _emailController;
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  bool _isLoading = false;
  bool _isPhoneMode = false;
  bool _obscurePassword = true;
  bool _obscureConfirm = true;
  String? _errorMessage;
  String? _infoMessage;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.initialName ?? '');
    _emailController = TextEditingController(text: widget.initialEmail ?? '');
    if (widget.initialEmail != null && widget.initialEmail!.isNotEmpty) {
      _infoMessage =
          'Google account connected (${widget.initialEmail}). Please enter a password to complete registration.';
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _handleGoogleSignUp() async {
    setState(() {
      _errorMessage = null;
      _infoMessage = null;
      _isLoading = true;
    });

    try {
      final success = await AuthService.registerWithGoogle();
      if (!mounted) return;
      setState(() => _isLoading = false);

      if (success) {
        widget.onRegisterSuccess();
      } else if (AuthService.lastGoogleRequiresOtp && AuthService.lastGoogleUserId != null) {
        final userId = AuthService.lastGoogleUserId!;
        final email = AuthService.lastGoogleEmail ?? '';
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => OtpVerificationScreen(
              userId: userId,
              targetEmail: email,
              isWhatsApp: false,
              onVerificationSuccess: () {
                Navigator.of(context).pop();
                widget.onRegisterSuccess();
              },
            ),
          ),
        );
      } else {
        final err = AuthService.lastLoginError ?? 'Google sign-up failed.';
        setState(() => _errorMessage = err);
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = 'Google sign-up error: ${e.toString()}';
      });
    }
  }

  Future<void> _handleRegister() async {
    final name = _nameController.text.trim();
    final email = _emailController.text.trim();
    final phone = _phoneController.text.trim();
    final password = _passwordController.text;
    final confirmPassword = _confirmPasswordController.text;

    if (name.isEmpty) {
      setState(() => _errorMessage = 'Please enter your full name.');
      return;
    }

    if (_isPhoneMode) {
      if (phone.isEmpty || phone.length < 8) {
        setState(() => _errorMessage = 'Please enter a valid phone number.');
        return;
      }
    } else {
      if (email.isEmpty || !email.contains('@')) {
        setState(() => _errorMessage = 'Please enter a valid email address.');
        return;
      }
    }

    if (password.length < 8) {
      setState(() => _errorMessage = 'Password must be at least 8 characters.');
      return;
    }
    if (password != confirmPassword) {
      setState(() => _errorMessage = 'Passwords do not match.');
      return;
    }

    setState(() {
      _errorMessage = null;
      _isLoading = true;
    });

    final userId = await AuthService.registerUser(
      name: name,
      email: _isPhoneMode ? null : email,
      phone: _isPhoneMode ? phone : null,
      password: password,
    );

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (userId != null) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => OtpVerificationScreen(
            userId: userId,
            targetEmail: _isPhoneMode ? null : email,
            targetPhone: _isPhoneMode ? phone : '',
            isWhatsApp: _isPhoneMode,
            onVerificationSuccess: () {
              AdminDummyData.registerUser(
                name: name,
                email: _isPhoneMode ? phone : email,
              );
              Navigator.of(context).pop();
              widget.onRegisterSuccess();
            },
          ),
        ),
      );
    } else {
      final err = AuthService.lastRegisterError ?? '';
      final isNetwork = err.startsWith('Connection failed:') ||
          err.contains('SocketException') ||
          err.contains('Timeout') ||
          err.contains('Failed host lookup');

      if (isNetwork) {
        if (!mounted) return;
        setState(() {
          _errorMessage = 'Cannot connect to server. Please check your internet connection and try again.';
        });
        return;
      }

      setState(() {
        _errorMessage = AuthService.lastRegisterError ??
            'Registration failed. Please check your data and try again.';
      });
    }
  }

  InputDecoration _fieldDecoration({required String hint, Widget? suffixIcon}) {
    final border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppColors.line),
    );

    return InputDecoration(
      hintText: hint,
      hintStyle: _inter(15, FontWeight.w400, AppColors.muted),
      filled: true,
      fillColor: Colors.white,
      suffixIcon: suffixIcon,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: border,
      enabledBorder: border,
      focusedBorder: border.copyWith(
        borderSide: const BorderSide(color: AppColors.ink, width: 1.5),
      ),
    );
  }

  Widget _label(String text) =>
      Text(text, style: _inter(14, FontWeight.w500, AppColors.ink));

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.page,
      appBar: AppBar(
        backgroundColor: AppColors.page,
        elevation: 0,
        scrolledUnderElevation: 0,
        leadingWidth: 84,
        leading: InkWell(
          onTap: () => Navigator.of(context).pop(),
          child: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(width: 14),
              Icon(Icons.arrow_back_rounded, size: 20),
              SizedBox(width: 4),
              Text(
                'Back',
                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
            child: AppCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Create an account',
                    textAlign: TextAlign.center,
                    style: _inter(26, FontWeight.w700, AppColors.ink),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Start building better money habits today.',
                    textAlign: TextAlign.center,
                    style: _inter(15, FontWeight.w400, AppColors.muted),
                  ),
                  const SizedBox(height: 28),
                  _label('Full Name'),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _nameController,
                    enabled: !_isLoading,
                    style: _inter(15, FontWeight.w400, AppColors.ink),
                    decoration: _fieldDecoration(hint: 'Alex Johnson'),
                  ),
                    const SizedBox(height: 16),
                  _label(_isPhoneMode ? 'Phone Number' : 'Email'),
                  const SizedBox(height: 8),
                  if (_isPhoneMode)
                    TextField(
                      controller: _phoneController,
                      enabled: !_isLoading,
                      keyboardType: TextInputType.phone,
                      style: _inter(15, FontWeight.w400, AppColors.ink),
                      decoration: _fieldDecoration(
                        hint: '08123456789 or +628123456789',
                      ),
                    )
                  else
                    TextField(
                      controller: _emailController,
                      enabled: !_isLoading,
                      keyboardType: TextInputType.emailAddress,
                      style: _inter(15, FontWeight.w400, AppColors.ink),
                      decoration: _fieldDecoration(hint: 'alex@example.com'),
                    ),
                  const SizedBox(height: 16),
                  _label('Password'),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _passwordController,
                    enabled: !_isLoading,
                    obscureText: _obscurePassword,
                    style: _inter(15, FontWeight.w400, AppColors.ink),
                    decoration: _fieldDecoration(
                      hint: 'Create a password',
                      suffixIcon: IconButton(
                        icon: Icon(
                          _obscurePassword
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                          color: AppColors.muted,
                          size: 20,
                        ),
                        onPressed: () {
                          setState(
                            () => _obscurePassword = !_obscurePassword,
                          );
                        },
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Minimum 8 characters, containing letters and numbers.',
                    style: _inter(12, FontWeight.w400, AppColors.muted),
                  ),
                  const SizedBox(height: 16),
                  _label('Confirm Password'),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _confirmPasswordController,
                    enabled: !_isLoading,
                    obscureText: _obscureConfirm,
                    style: _inter(15, FontWeight.w400, AppColors.ink),
                    decoration: _fieldDecoration(
                      hint: 'Confirm your password',
                      suffixIcon: IconButton(
                        icon: Icon(
                          _obscureConfirm
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                          color: AppColors.muted,
                          size: 20,
                        ),
                        onPressed: () {
                          setState(
                            () => _obscureConfirm = !_obscureConfirm,
                          );
                        },
                      ),
                    ),
                  ),
                    if (_infoMessage != null) ...[
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEFF6FF),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFBFDBFE)),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Icon(
                              Icons.info_outline_rounded,
                              color: Color(0xFF2563EB),
                              size: 18,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                _infoMessage!,
                                style: const TextStyle(
                                  color: Color(0xFF1E40AF),
                                  fontSize: 13,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    if (_errorMessage != null) ...[
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.expenseSoft,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          _errorMessage!,
                          style: const TextStyle(
                            color: AppColors.expense,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ],
                    const SizedBox(height: 20),
                    AppButton(
                      label:
                          _isLoading ? 'Creating account...' : 'Create account',
                      onPressed: _isLoading ? () {} : _handleRegister,
                      background:
                          _isLoading ? AppColors.muted : AppColors.dark,
                    ),
                    const SizedBox(height: 20),

                    // OR DIVIDER
                    Row(
                      children: [
                        const Expanded(child: Divider(color: AppColors.line)),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          child: Text(
                            'OR',
                            style: _inter(12, FontWeight.w500, AppColors.muted),
                          ),
                        ),
                        const Expanded(child: Divider(color: AppColors.line)),
                      ],
                    ),
                    const SizedBox(height: 20),

                    // GOOGLE SIGN UP
                    SizedBox(
                      width: double.infinity,
                      height: 48,
                      child: OutlinedButton(
                        onPressed: _isLoading ? null : _handleGoogleSignUp,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.ink,
                          backgroundColor: Colors.white,
                          side: const BorderSide(color: Color(0xFFE5E7EB)),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          elevation: 0,
                        ),
                        child: const Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            GoogleLogo(size: 20),
                            SizedBox(width: 10),
                            Text(
                              'Sign up with Google',
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                                color: AppColors.ink,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),

                    // TOGGLE PHONE / EMAIL MODE
                    SizedBox(
                      width: double.infinity,
                      height: 48,
                      child: OutlinedButton(
                        onPressed: () {
                          setState(() {
                            _isPhoneMode = !_isPhoneMode;
                            _errorMessage = null;
                            _infoMessage = null;
                          });
                        },
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.ink,
                          backgroundColor: Colors.white,
                          side: const BorderSide(color: Color(0xFFE5E7EB)),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          elevation: 0,
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              _isPhoneMode
                                  ? Icons.mail_outline_rounded
                                  : Icons.phone_android_outlined,
                              size: 20,
                              color: AppColors.ink,
                            ),
                            const SizedBox(width: 10),
                            Text(
                              _isPhoneMode
                                  ? 'Continue with Email'
                                  : 'Continue with Phone',
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                                color: AppColors.ink,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    Center(
                      child: RichText(
                        text: TextSpan(
                          text: 'Already have an account? ',
                          style: const TextStyle(color: AppColors.muted),
                          children: [
                            WidgetSpan(
                              child: GestureDetector(
                                onTap: _isLoading
                                    ? null
                                    : () => Navigator.of(context).pop(),
                                child: const Text(
                                  'Sign in',
                                  style: TextStyle(
                                    color: AppColors.ink,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
  }
}
