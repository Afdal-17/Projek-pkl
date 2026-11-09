import 'dart:typed_data';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../data/admin_dummy_data.dart';
import '../../theme/app_colors.dart';
import '../../widgets/admin_header.dart';
import '../../widgets/app_card.dart';

/// Halaman Admin Profile.
/// Tampilannya disamakan persis seperti tampilan Account/Profile User:
/// - Menu list kartu: Profile, Security, About, Sign out (tanpa notifikasi & tukar uang).
/// - Ada fitur Tambah Foto, Ganti Foto, dan Hapus Foto.
/// - Fitur About & Update Password lengkap.
class AdminProfileScreen extends StatefulWidget {
  const AdminProfileScreen({super.key, required this.onExitAdmin});

  final VoidCallback onExitAdmin;

  @override
  State<AdminProfileScreen> createState() => _AdminProfileScreenState();
}

class _AdminProfileScreenState extends State<AdminProfileScreen> {
  String _activeTab = 'profile';

  late final TextEditingController _nameCtrl;
  late final TextEditingController _emailCtrl;
  late final TextEditingController _phoneCtrl;
  late final TextEditingController _locationCtrl;

  final TextEditingController _currentPassCtrl = TextEditingController();
  final TextEditingController _newPassCtrl = TextEditingController();
  final TextEditingController _confirmPassCtrl = TextEditingController();

  bool _obscureCurrent = true;
  bool _obscureNew = true;
  bool _obscureConfirm = true;

  Uint8List? _avatarFile;
  final _picker = ImagePicker();

  bool _isSaving = false;
  bool _isSaved = false;
  bool _isUpdatingPassword = false;
  bool _isPasswordUpdated = false;

  @override
  void initState() {
    super.initState();
    _nameCtrl = TextEditingController(text: AdminDummyData.adminName);
    _emailCtrl = TextEditingController(text: AdminDummyData.adminEmail);
    _phoneCtrl = TextEditingController(text: AdminDummyData.adminPhone);
    _locationCtrl = TextEditingController(text: AdminDummyData.adminLocation);
    if (AdminDummyData.adminAvatarFile is Uint8List) {
      _avatarFile = AdminDummyData.adminAvatarFile as Uint8List;
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    _locationCtrl.dispose();
    _currentPassCtrl.dispose();
    _newPassCtrl.dispose();
    _confirmPassCtrl.dispose();
    super.dispose();
  }

  Future<void> _handleRefresh() async {
    await Future.delayed(const Duration(milliseconds: 600));
    if (mounted) {
      setState(() {
        _nameCtrl.text = AdminDummyData.adminName;
        _emailCtrl.text = AdminDummyData.adminEmail;
        _phoneCtrl.text = AdminDummyData.adminPhone;
        _locationCtrl.text = AdminDummyData.adminLocation;
      });
    }
  }

  Future<void> _pickPhoto() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Change profile photo',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 16),
            ListTile(
              leading: Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: const Color(0xFFEEF2FF),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.photo_camera_outlined, color: AppColors.brand),
              ),
              title: const Text('Take a photo', style: TextStyle(fontWeight: FontWeight.w600)),
              subtitle: const Text('Use camera to take a new photo'),
              contentPadding: EdgeInsets.zero,
              onTap: () => Navigator.of(ctx).pop(ImageSource.camera),
            ),
            ListTile(
              leading: Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: const Color(0xFFEEF2FF),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.photo_library_outlined, color: AppColors.brand),
              ),
              title: const Text('Choose from gallery', style: TextStyle(fontWeight: FontWeight.w600)),
              subtitle: const Text('Select a photo from your gallery'),
              contentPadding: EdgeInsets.zero,
              onTap: () => Navigator.of(ctx).pop(ImageSource.gallery),
            ),
            if (_avatarFile != null) ...[
              ListTile(
                leading: Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEE2E2),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.delete_outline_rounded, color: AppColors.expense),
                ),
                title: const Text('Remove photo', style: TextStyle(fontWeight: FontWeight.w600, color: AppColors.expense)),
                subtitle: const Text('Reset back to default avatar'),
                contentPadding: EdgeInsets.zero,
                onTap: () {
                  Navigator.of(ctx).pop();
                  _removePhoto();
                },
              ),
            ],
          ],
        ),
      ),
    );

    if (source == null) return;
    final picked = await _picker.pickImage(
      source: source,
      maxWidth: 800,
      maxHeight: 800,
      imageQuality: 85,
    );
    if (picked != null && mounted) {
      final bytes = await picked.readAsBytes();
      setState(() {
        _avatarFile = bytes;
        AdminDummyData.adminAvatarFile = _avatarFile;
      });
    }
  }

  void _removePhoto() {
    setState(() {
      _avatarFile = null;
      AdminDummyData.adminAvatarFile = null;
    });
  }

  Future<void> _saveProfile() async {
    setState(() {
      _isSaving = true;
      AdminDummyData.adminName = _nameCtrl.text.trim().isEmpty ? 'Admin' : _nameCtrl.text.trim();
      AdminDummyData.adminEmail = _emailCtrl.text.trim().isEmpty ? 'admin@finance.app' : _emailCtrl.text.trim();
      AdminDummyData.adminPhone = _phoneCtrl.text.trim();
      AdminDummyData.adminLocation = _locationCtrl.text.trim();
      AdminDummyData.adminAvatarFile = _avatarFile;
    });

    await Future.delayed(const Duration(milliseconds: 300));
    if (mounted) {
      setState(() {
        _isSaving = false;
        _isSaved = true;
      });

      await Future.delayed(const Duration(milliseconds: 1500));
      if (mounted) {
        setState(() => _isSaved = false);
      }
    }
  }

  Future<void> _updatePassword() async {
    final cur = _currentPassCtrl.text;
    final np = _newPassCtrl.text;
    final cp = _confirmPassCtrl.text;

    if (cur.isEmpty || np.length < 8 || np != cp) {
      return;
    }

    setState(() => _isUpdatingPassword = true);
    await Future.delayed(const Duration(milliseconds: 300));

    if (mounted) {
      setState(() {
        _isUpdatingPassword = false;
        _isPasswordUpdated = true;
        _currentPassCtrl.clear();
        _newPassCtrl.clear();
        _confirmPassCtrl.clear();
      });

      await Future.delayed(const Duration(milliseconds: 1500));
      if (mounted) {
        setState(() => _isPasswordUpdated = false);
      }
    }
  }

  void _confirmLogout() {
    showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text(
          'Sign out',
          style: TextStyle(fontWeight: FontWeight.w800, color: AppColors.ink),
        ),
        content: const Text(
          'Are you sure you want to sign out of the Admin panel?',
          style: TextStyle(fontSize: 14, color: AppColors.muted),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Cancel', style: TextStyle(color: AppColors.muted, fontWeight: FontWeight.w600)),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.of(ctx).pop();
              widget.onExitAdmin();
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.expense,
              foregroundColor: Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            child: const Text('Sign out', style: TextStyle(fontWeight: FontWeight.w700)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.page,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const AdminHeader(),
          Expanded(
            child: RefreshIndicator(
              color: AppColors.brand,
              backgroundColor: Colors.white,
              strokeWidth: 3.0,
              displacement: 40.0,
              onRefresh: _handleRefresh,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 36),
                children: [
                // Title & Subtitle (sama persis formatnya dengan user account screen)
                const Text(
                  'Account',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Manage your profile, security, and administrative settings.',
                  style: TextStyle(fontSize: 14, color: AppColors.muted),
                ),
                const SizedBox(height: 18),

                // Card Navigasi Menu Vertikal (Style persis user: Profile, Security, About, Sign Out)
                AppCard(
                  padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 8),
                  child: Column(
                    children: [
                      _navItem(
                        tabKey: 'profile',
                        icon: Icons.person_outline_rounded,
                        label: 'Profile',
                        isSelected: _activeTab == 'profile',
                        onTap: () => setState(() => _activeTab = 'profile'),
                      ),
                      _navItem(
                        tabKey: 'security',
                        icon: Icons.shield_outlined,
                        label: 'Security',
                        isSelected: _activeTab == 'security',
                        onTap: () => setState(() => _activeTab = 'security'),
                      ),
                      _navItem(
                        tabKey: 'about',
                        icon: Icons.info_outline_rounded,
                        label: 'About',
                        isSelected: _activeTab == 'about',
                        onTap: () => setState(() => _activeTab = 'about'),
                      ),
                      _navItem(
                        tabKey: 'signout',
                        icon: Icons.logout_rounded,
                        label: 'Sign out',
                        isSelected: false,
                        isDestructive: true,
                        onTap: _confirmLogout,
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 18),

                // Konten Tab Aktif
                if (_activeTab == 'profile') ...[
                  _buildProfileSection(),
                ] else if (_activeTab == 'security') ...[
                  _buildSecuritySection(),
                ] else if (_activeTab == 'about') ...[
                  _buildAboutSection(),
                ],
              ],
            ),
          ),
        ),
      ],
    ),
  );
  }

  Widget _navItem({
    required String tabKey,
    required IconData icon,
    required String label,
    required bool isSelected,
    required VoidCallback onTap,
    bool isDestructive = false,
  }) {
    final color = isDestructive
        ? const Color(0xFFEF4444)
        : isSelected
            ? AppColors.brand
            : const Color(0xFF4B5563);

    final bgColor = isSelected ? const Color(0xFFEEF2FF) : Colors.transparent;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Material(
        color: bgColor,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(10),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: Row(
              children: [
                Icon(icon, size: 20, color: color),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    label,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                      color: color,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ─── TAB 1: Profile Section ───
  Widget _buildProfileSection() {
    return AppCard(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: const [
              Text(
                'Profile',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: AppColors.ink,
                ),
              ),
              Text(
                'Your public account details',
                style: TextStyle(
                  fontSize: 13,
                  color: AppColors.muted,
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),

          // Avatar Header dengan Foto, Tambah Foto & Hapus Foto
          Center(
            child: Column(
              children: [
                GestureDetector(
                  onTap: _pickPhoto,
                  child: Stack(
                    clipBehavior: Clip.none,
                    children: [
                      Container(
                        width: 88,
                        height: 88,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: const Color(0xFFF3ECE4),
                          border: Border.all(color: const Color(0xFFE5E7EB), width: 3),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.06),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ],
                          image: _avatarFile != null
                              ? DecorationImage(
                                  image: MemoryImage(_avatarFile!),
                                  fit: BoxFit.cover,
                                )
                              : null,
                        ),
                        alignment: Alignment.center,
                        child: _avatarFile == null
                            ? const Text(
                                'A',
                                style: TextStyle(
                                  fontSize: 34,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.brand,
                                ),
                              )
                            : null,
                      ),
                      // Camera badge kecil di sudut avatar
                      Positioned(
                        bottom: 0,
                        right: 0,
                        child: Container(
                          width: 28,
                          height: 28,
                          decoration: BoxDecoration(
                            color: AppColors.dark,
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 2),
                          ),
                          child: const Icon(
                            Icons.camera_alt_rounded,
                            size: 14,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  _nameCtrl.text.isEmpty ? AdminDummyData.adminName : _nameCtrl.text,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                  ),
                ),
                const SizedBox(height: 8),

                // Tombol aksi Tambah/Ganti Foto dan Hapus Foto
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    GestureDetector(
                      onTap: _pickPhoto,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEEF2FF),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              _avatarFile != null ? Icons.edit_outlined : Icons.add_a_photo_outlined,
                              size: 14,
                              color: AppColors.brand,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              _avatarFile != null ? 'Change photo' : 'Add photo',
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                                color: AppColors.brand,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    if (_avatarFile != null) ...[
                      const SizedBox(width: 8),
                      GestureDetector(
                        onTap: _removePhoto,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFEE2E2),
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.delete_outline_rounded, size: 14, color: AppColors.expense),
                              SizedBox(width: 4),
                              Text(
                                'Remove',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.expense,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 6),
                const Text(
                  'JPG or PNG. Maximum file size 2 MB.',
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.muted,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 24),
          const Divider(color: Color(0xFFF3F4F6), height: 1),
          const SizedBox(height: 20),

          // Form fields data admin
          _formLabel('Full name'),
          const SizedBox(height: 8),
          _inputField(_nameCtrl, 'Full name', onChanged: (_) => setState(() {})),
          const SizedBox(height: 16),

          _formLabel('Email address'),
          const SizedBox(height: 8),
          _inputField(_emailCtrl, 'Email address', keyboardType: TextInputType.emailAddress),
          const SizedBox(height: 16),

          _formLabel('Phone number'),
          const SizedBox(height: 8),
          _inputField(_phoneCtrl, 'Phone Number', keyboardType: TextInputType.phone),
          const SizedBox(height: 16),

          _formLabel('Location'),
          const SizedBox(height: 8),
          _inputField(_locationCtrl, 'Location'),
          const SizedBox(height: 24),

          SizedBox(
            width: double.infinity,
            height: 48,
            child: ElevatedButton(
              onPressed: _isSaving ? null : _saveProfile,
              style: ElevatedButton.styleFrom(
                backgroundColor: _isSaved ? const Color(0xFF10B981) : AppColors.dark,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: _isSaving
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        if (_isSaved) ...[
                          const Icon(Icons.check_circle_rounded, size: 18, color: Colors.white),
                          const SizedBox(width: 8),
                        ],
                        Text(
                          _isSaved ? 'Saved successfully' : 'Save changes',
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }

  // ─── TAB 2: Security / Update Password Section ───
  Widget _buildSecuritySection() {
    return AppCard(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: const Color(0xFFEEF2FF),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.shield_outlined,
                  color: AppColors.brand,
                  size: 22,
                ),
              ),
              const SizedBox(width: 14),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Security / Change Password',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: AppColors.ink,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Update your password to keep your admin account secure.',
                      style: TextStyle(
                        fontSize: 13,
                        color: AppColors.muted,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),

          // Form Update Password
          _formLabel('Current Password'),
          const SizedBox(height: 8),
          _passwordField(
            _currentPassCtrl,
            'Enter current password',
            _obscureCurrent,
            () => setState(() => _obscureCurrent = !_obscureCurrent),
          ),
          const SizedBox(height: 16),

          _formLabel('New Password'),
          const SizedBox(height: 8),
          _passwordField(
            _newPassCtrl,
            'Enter new password',
            _obscureNew,
            () => setState(() => _obscureNew = !_obscureNew),
          ),
          const SizedBox(height: 16),

          _formLabel('Confirm New Password'),
          const SizedBox(height: 8),
          _passwordField(
            _confirmPassCtrl,
            'Confirm new password',
            _obscureConfirm,
            () => setState(() => _obscureConfirm = !_obscureConfirm),
          ),
          const SizedBox(height: 24),

          SizedBox(
            width: double.infinity,
            height: 46,
            child: ElevatedButton(
              onPressed: _isUpdatingPassword ? null : _updatePassword,
              style: ElevatedButton.styleFrom(
                backgroundColor: _isPasswordUpdated ? const Color(0xFF10B981) : AppColors.dark,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              child: _isUpdatingPassword
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          _isPasswordUpdated ? Icons.check_circle_rounded : Icons.lock_outline_rounded,
                          size: 16,
                          color: Colors.white,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          _isPasswordUpdated ? 'Password updated!' : 'Update Password',
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                          ),
                        ),
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }

  // ─── TAB 3: About Section ───
  Widget _buildAboutSection() {
    return AppCard(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: const Color(0xFFEEF2FF),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.info_outline_rounded,
                  color: AppColors.brand,
                  size: 22,
                ),
              ),
              const SizedBox(width: 14),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'About Application',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'System & administrative details',
                      style: TextStyle(
                        fontSize: 13,
                        color: AppColors.muted,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          _aboutItem('Application Name', 'Finance App'),
          _aboutItem('System Role', 'Super Administrator'),
          _aboutItem('App Version', '2.0.0'),
          _aboutItem('Platform', 'Mobile App (Flutter)'),
          _aboutItem('Backend API', 'Laravel Framework'),
          _aboutItem('Organization', 'PKL Madtive Studio'),
        ],
      ),
    );
  }

  Widget _aboutItem(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: const TextStyle(fontSize: 14, color: AppColors.muted, fontWeight: FontWeight.w500),
          ),
          Text(
            value,
            style: const TextStyle(fontSize: 14, color: AppColors.ink, fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }

  // ─── Helper Form Widgets ───
  Widget _formLabel(String label) {
    return Text(
      label,
      style: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w700,
        color: AppColors.ink,
      ),
    );
  }

  Widget _inputField(
    TextEditingController controller,
    String hint, {
    TextInputType keyboardType = TextInputType.text,
    ValueChanged<String>? onChanged,
  }) {
    return TextField(
      controller: controller,
      keyboardType: keyboardType,
      onChanged: onChanged,
      style: const TextStyle(fontSize: 14, color: AppColors.ink, fontWeight: FontWeight.w500),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: const TextStyle(fontSize: 14, color: AppColors.muted),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: AppColors.brand, width: 1.5),
        ),
      ),
    );
  }

  Widget _passwordField(
    TextEditingController controller,
    String hint,
    bool obscure,
    VoidCallback onToggle,
  ) {
    return TextField(
      controller: controller,
      obscureText: obscure,
      style: const TextStyle(fontSize: 14, color: AppColors.ink, fontWeight: FontWeight.w500),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: const TextStyle(fontSize: 14, color: AppColors.muted),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        suffixIcon: IconButton(
          icon: Icon(
            obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined,
            color: AppColors.muted,
            size: 20,
          ),
          onPressed: onToggle,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: AppColors.brand, width: 1.5),
        ),
      ),
    );
  }
}
