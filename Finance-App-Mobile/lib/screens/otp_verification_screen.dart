import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import '../services/auth_service.dart';
import '../theme/app_colors.dart';
import '../widgets/app_button.dart';
import '../widgets/app_card.dart';

TextStyle _inter(double size, FontWeight weight, Color color) =>
    GoogleFonts.inter(fontSize: size, fontWeight: weight, color: color);

class OtpVerificationScreen extends StatefulWidget {
  const OtpVerificationScreen({
    super.key,
    this.targetPhone = '',
    required this.onVerificationSuccess,
    this.isWhatsApp = true,
    this.userId,
    this.targetEmail,
  });

  final String targetPhone;
  final VoidCallback onVerificationSuccess;
  final bool isWhatsApp;
  final int? userId;
  final String? targetEmail;

  @override
  State<OtpVerificationScreen> createState() => _OtpVerificationScreenState();
}

class _OtpVerificationScreenState extends State<OtpVerificationScreen> {
  static const int _otpLength = 6;
  late final List<TextEditingController> _controllers;
  late final List<FocusNode> _focusNodes;

  bool _isLoading = false;
  String? _errorMessage;
  int _countdown = 60;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _controllers = List.generate(_otpLength, (_) => TextEditingController());
    _focusNodes = List.generate(_otpLength, (_) => FocusNode());
    _startCountdown();
  }

  @override
  void dispose() {
    _timer?.cancel();
    for (final c in _controllers) {
      c.dispose();
    }
    for (final f in _focusNodes) {
      f.dispose();
    }
    super.dispose();
  }

  void _startCountdown() {
    _timer?.cancel();
    setState(() => _countdown = 60);
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_countdown > 0) {
        setState(() => _countdown--);
      } else {
        timer.cancel();
      }
    });
  }

  String get _otpCode =>
      _controllers.map((c) => c.text.trim()).join();

  bool get _isComplete => _otpCode.length == _otpLength;

  String _maskPhone(String phone) {
    final clean = phone.trim();
    if (clean.length < 7) return clean;
    final start = clean.substring(0, 4);
    final end = clean.substring(clean.length - 3);
    return '$start****$end';
  }

  void _onDigitChanged(int index, String value) {
    if (value.length > 1) {
      // Handle paste 6 digit code
      final digits = value.replaceAll(RegExp(r'\D'), '');
      for (int i = 0; i < _otpLength && i < digits.length; i++) {
        _controllers[i].text = digits[i];
      }
      final nextIndex = digits.length < _otpLength ? digits.length : _otpLength - 1;
      _focusNodes[nextIndex].requestFocus();
      setState(() {});
      if (_isComplete) {
        _handleVerify();
      }
      return;
    }

    if (value.isNotEmpty) {
      if (index < _otpLength - 1) {
        _focusNodes[index + 1].requestFocus();
      } else {
        _focusNodes[index].unfocus();
      }
    }
    setState(() {});

    if (_isComplete) {
      _handleVerify();
    }
  }

  void _onKeyDown(int index, RawKeyEvent event) {
    if (event is RawKeyDownEvent &&
        event.logicalKey == LogicalKeyboardKey.backspace) {
      if (_controllers[index].text.isEmpty && index > 0) {
        _controllers[index - 1].clear();
        _focusNodes[index - 1].requestFocus();
        setState(() {});
      }
    }
  }

  Future<void> _handleVerify() async {
    if (!_isComplete || _isLoading) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final bool success;
    if (widget.userId != null) {
      success = await AuthService.verifyRegisterOtp(
        userId: widget.userId!,
        code: _otpCode,
      );
    } else {
      success = await AuthService.verifyOtp(
        widget.targetPhone,
        _otpCode,
      );
    }

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (success) {
      widget.onVerificationSuccess();
    } else {
      setState(() {
        _errorMessage = (widget.userId != null ? AuthService.lastRegisterError : null) ??
            'Invalid verification code. Please check and try again.';
      });
    }
  }

  Future<void> _handleResend() async {
    if (_countdown > 0 || _isLoading) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    if (widget.userId != null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Please check your inbox or spam folder for your 6-digit OTP code.'),
            duration: Duration(seconds: 3),
          ),
        );
      }
    } else {
      await AuthService.resendOtp(widget.targetPhone);
    }

    if (!mounted) return;
    setState(() => _isLoading = false);
    _startCountdown();
  }

  @override
  Widget build(BuildContext context) {
    final isEmail = widget.targetEmail != null && widget.targetEmail!.isNotEmpty;
    final masked = isEmail ? widget.targetEmail! : _maskPhone(widget.targetPhone);
    final kanal = isEmail ? 'email' : (widget.isWhatsApp ? 'WhatsApp' : 'phone');

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
                  // Back button
                  Align(
                    alignment: Alignment.centerLeft,
                    child: InkWell(
                      onTap: () => Navigator.of(context).pop(),
                      borderRadius: BorderRadius.circular(8),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          vertical: 4,
                          horizontal: 2,
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.arrow_back_rounded,
                              size: 16,
                              color: AppColors.muted,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              'Back',
                              style: _inter(
                                13,
                                FontWeight.w500,
                                AppColors.muted,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Top Icon (matches auth/otp.blade.php)
                  Center(
                    child: Container(
                      width: 52,
                      height: 52,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF3F4F6),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Icon(
                        isEmail
                            ? Icons.mail_outline_rounded
                            : (widget.isWhatsApp
                                ? Icons.chat_bubble_outline_rounded
                                : Icons.phone_android_rounded),
                        size: 26,
                        color: AppColors.dark,
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Title & Subtitle (matches auth/otp.blade.php)
                  Text(
                    isEmail ? 'Verify your email' : 'Verify your $kanal',
                    textAlign: TextAlign.center,
                    style: _inter(24, FontWeight.w800, AppColors.ink),
                  ),
                  const SizedBox(height: 8),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                    child: RichText(
                      textAlign: TextAlign.center,
                      text: TextSpan(
                        style: _inter(14, FontWeight.w400, AppColors.muted),
                        children: [
                          const TextSpan(text: "We've sent a 6-digit code to "),
                          TextSpan(
                            text: masked,
                            style: _inter(14, FontWeight.w600, AppColors.ink),
                          ),
                          TextSpan(
                            text: isEmail
                                ? '. Enter it below to activate your account.'
                                : '. Enter it below to verify your $kanal number.',
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 28),

                  // 6 OTP Box Inputs (matches auth/otp.blade.php)
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: List.generate(_otpLength, (i) {
                      return SizedBox(
                        width: 44,
                        height: 54,
                        child: RawKeyboardListener(
                          focusNode: FocusNode(),
                          onKey: (event) => _onKeyDown(i, event),
                          child: TextField(
                            controller: _controllers[i],
                            focusNode: _focusNodes[i],
                            autofocus: i == 0,
                            textAlign: TextAlign.center,
                            keyboardType: TextInputType.number,
                            inputFormatters: [
                              FilteringTextInputFormatter.digitsOnly,
                            ],
                            style: _inter(22, FontWeight.w700, AppColors.ink),
                            decoration: InputDecoration(
                              counterText: '',
                              contentPadding: EdgeInsets.zero,
                              filled: true,
                              fillColor: Colors.white,
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide:
                                    const BorderSide(color: AppColors.line),
                              ),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide:
                                    const BorderSide(color: AppColors.line),
                              ),
                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: const BorderSide(
                                  color: AppColors.brand,
                                  width: 2,
                                ),
                              ),
                            ),
                            onChanged: (val) => _onDigitChanged(i, val),
                          ),
                        ),
                      );
                    }),
                  ),

                  // Error Message
                  if (_errorMessage != null) ...[
                    const SizedBox(height: 14),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.expenseSoft,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        _errorMessage!,
                        style: _inter(13, FontWeight.w500, AppColors.expense),
                        textAlign: TextAlign.center,
                      ),
                    ),
                  ],

                  const SizedBox(height: 24),

                  // Submit Button (matches auth/otp.blade.php)
                  AppButton(
                    label: _isLoading
                        ? 'Verifying...'
                        : 'Verify $kanal',
                    onPressed: _isComplete && !_isLoading ? _handleVerify : () {},
                    background:
                        _isComplete && !_isLoading ? AppColors.dark : AppColors.muted,
                  ),

                  const SizedBox(height: 20),

                  // Resend countdown (matches auth/otp.blade.php)
                  Center(
                    child: _countdown > 0
                        ? Text(
                            "Didn't get the code? Resend (${_countdown}s)",
                            style: _inter(13, FontWeight.w500, AppColors.muted),
                          )
                        : GestureDetector(
                            onTap: _isLoading ? null : _handleResend,
                            child: Text(
                              "Didn't get the code? Resend",
                              style: _inter(
                                13,
                                FontWeight.w600,
                                AppColors.ink,
                              ).copyWith(
                                decoration: TextDecoration.underline,
                              ),
                            ),
                          ),
                  ),

                  const SizedBox(height: 14),

                  // Wrong phone number? Change number
                  Center(
                    child: GestureDetector(
                      onTap: () => Navigator.of(context).pop(),
                      child: Text(
                        'Wrong phone number? Change number',
                        style: _inter(12, FontWeight.w400, AppColors.muted),
                      ),
                    ),
                  ),
                  const SizedBox(height: 4),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

