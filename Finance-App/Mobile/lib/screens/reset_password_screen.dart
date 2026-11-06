import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../services/auth_service.dart';
import '../theme/app_colors.dart';
import '../widgets/app_button.dart';
import '../widgets/app_card.dart';

TextStyle _inter(double size, FontWeight weight, Color color) =>
    GoogleFonts.inter(fontSize: size, fontWeight: weight, color: color);

class ResetPasswordScreen extends StatefulWidget {
  const ResetPasswordScreen({
    super.key,
    this.initialEmail,
  });

  final String? initialEmail;

  @override
  State<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends State<ResetPasswordScreen> {
  late final TextEditingController _emailController;
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  bool _obscurePassword = true;
  bool _obscureConfirm = true;
  bool _isLoading = false;
  String? _errorMessage;
  bool _isSuccess = false;

  @override
  void initState() {
    super.initState();
    _emailController = TextEditingController(
      text: widget.initialEmail ?? 'gaktaubanh68@gmail.com',
    );
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _handleResetPassword() async {
    final email = _emailController.text.trim();
    final newPassword = _passwordController.text;
    final confirmPassword = _confirmPasswordController.text;

    setState(() => _errorMessage = null);

    if (email.isEmpty || !email.contains('@')) {
      setState(() => _errorMessage = 'Please enter a valid email address.');
      return;
    }

    if (newPassword.length < 8) {
      setState(
        () => _errorMessage = 'Password must be at least 8 characters long.',
      );
      return;
    }

    if (newPassword != confirmPassword) {
      setState(() => _errorMessage = 'Passwords do not match.');
      return;
    }

    setState(() => _isLoading = true);

    final success = await AuthService.resetPassword(
      email,
      newPassword,
      confirmPassword,
    );

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (success) {
      setState(() => _isSuccess = true);
    } else {
      setState(() {
        _errorMessage = 'Failed to reset password. Please try again.';
      });
    }
  }

  InputDecoration _fieldDecoration({
    required String hint,
    Widget? suffixIcon,
    bool readOnly = false,
  }) {
    final border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppColors.line),
    );

    return InputDecoration(
      hintText: hint,
      hintStyle: _inter(14, FontWeight.w400, AppColors.muted),
      filled: true,
      fillColor: readOnly ? const Color(0xFFF9FAFB) : Colors.white,
      suffixIcon: suffixIcon,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: border,
      enabledBorder: border,
      focusedBorder: border.copyWith(
        borderSide: const BorderSide(color: AppColors.ink, width: 1.5),
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
              child: _isSuccess ? _buildSuccessView() : _buildFormView(),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildSuccessView() {
    return Column(
      children: [
        const SizedBox(height: 24),
        Container(
          width: 64,
          height: 64,
          decoration: BoxDecoration(
            color: AppColors.incomeSoft,
            borderRadius: BorderRadius.circular(16),
          ),
          child: const Icon(
            Icons.check_circle_rounded,
            color: AppColors.income,
            size: 36,
          ),
        ),
        const SizedBox(height: 20),
        Text(
          'Password Reset Successful',
          textAlign: TextAlign.center,
          style: _inter(22, FontWeight.w700, AppColors.ink),
        ),
        const SizedBox(height: 8),
        Text(
          'Your password has been successfully updated. You can now sign in with your new password.',
          textAlign: TextAlign.center,
          style: _inter(14, FontWeight.w400, AppColors.muted),
        ),
        const SizedBox(height: 28),
        AppButton(
          label: 'Back',
          icon: Icons.arrow_back_rounded,
          onPressed: () => Navigator.of(context).pop(),
          background: AppColors.dark,
        ),
        const SizedBox(height: 12),
      ],
    );
  }

  Widget _buildFormView() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // Back to login button (Image 1)
        Align(
          alignment: Alignment.centerLeft,
          child: InkWell(
            onTap: () => Navigator.of(context).pop(),
            borderRadius: BorderRadius.circular(8),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 2),
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
                    style: _inter(13, FontWeight.w500, AppColors.muted),
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 16),

        // Title & Subtitle (Image 1)
        Text(
          'Reset Password',
          textAlign: TextAlign.center,
          style: _inter(26, FontWeight.w800, AppColors.ink),
        ),
        const SizedBox(height: 6),
        Text(
          'Enter a new password for your account.',
          textAlign: TextAlign.center,
          style: _inter(14, FontWeight.w400, AppColors.muted),
        ),
        const SizedBox(height: 24),

        // Email field
        Text(
          'Email',
          style: _inter(14, FontWeight.w500, AppColors.ink),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _emailController,
          enabled: !_isLoading,
          keyboardType: TextInputType.emailAddress,
          style: _inter(15, FontWeight.w400, AppColors.ink),
          decoration: _fieldDecoration(
            hint: 'name@example.com',
          ),
        ),
        const SizedBox(height: 16),

        // New Password field
        Text(
          'New Password',
          style: _inter(14, FontWeight.w500, AppColors.ink),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _passwordController,
          enabled: !_isLoading,
          obscureText: _obscurePassword,
          style: _inter(15, FontWeight.w400, AppColors.ink),
          decoration: _fieldDecoration(
            hint: 'Minimum 8 characters',
            suffixIcon: IconButton(
              icon: Icon(
                _obscurePassword
                    ? Icons.visibility_outlined
                    : Icons.visibility_off_outlined,
                color: AppColors.muted,
                size: 20,
              ),
              onPressed: () =>
                  setState(() => _obscurePassword = !_obscurePassword),
            ),
          ),
        ),
        const SizedBox(height: 6),
        Text(
          'Minimum 8 characters, containing letters and numbers.',
          style: _inter(12, FontWeight.w400, AppColors.muted),
        ),
        const SizedBox(height: 16),

        // Confirm Password field
        Text(
          'Confirm Password',
          style: _inter(14, FontWeight.w500, AppColors.ink),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _confirmPasswordController,
          enabled: !_isLoading,
          obscureText: _obscureConfirm,
          style: _inter(15, FontWeight.w400, AppColors.ink),
          decoration: _fieldDecoration(
            hint: 'Repeat new password',
            suffixIcon: IconButton(
              icon: Icon(
                _obscureConfirm
                    ? Icons.visibility_outlined
                    : Icons.visibility_off_outlined,
                color: AppColors.muted,
                size: 20,
              ),
              onPressed: () =>
                  setState(() => _obscureConfirm = !_obscureConfirm),
            ),
          ),
        ),

        // Error Banner
        if (_errorMessage != null) ...[
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.expenseSoft,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(
                color: AppColors.expense.withValues(alpha: 0.2),
              ),
            ),
            child: Text(
              _errorMessage!,
              style: _inter(13, FontWeight.w500, AppColors.expense),
            ),
          ),
        ],

        const SizedBox(height: 22),

        // Reset Password Button (Dark black button matching web)
        AppButton(
          label: _isLoading ? 'Resetting...' : 'Reset Password',
          onPressed: _isLoading ? () {} : _handleResetPassword,
          background: _isLoading ? AppColors.muted : AppColors.dark,
        ),

        const SizedBox(height: 22),

        // Footer: Remember your password? Sign in (Image 1)
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              'Remember your password? ',
              style: _inter(14, FontWeight.w400, AppColors.muted),
            ),
            GestureDetector(
              onTap: () => Navigator.of(context).pop(),
              child: Text(
                'Sign in',
                style: _inter(14, FontWeight.w700, AppColors.ink),
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
      ],
    );
  }
}
