import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../data/dummy_data.dart';
import '../theme/app_colors.dart';
import '../utils/currency.dart';
import '../widgets/app_card.dart';
import '../widgets/app_header.dart';
import 'notifications_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key, required this.onLogout});

  final VoidCallback onLogout;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  String _activeTab = 'profile';

  late final TextEditingController _nameCtrl;
  late final TextEditingController _emailCtrl;
  late final TextEditingController _phoneCtrl;
  late final TextEditingController _locationCtrl;

  late String _selectedCurrency;
  bool _isCurrencyDropdownOpen = false;

  File? _profileImage;
  final _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _nameCtrl = TextEditingController(text: DummyData.userName);
    _emailCtrl = TextEditingController(text: DummyData.userEmail);
    _phoneCtrl = TextEditingController(text: DummyData.userPhone);
    _locationCtrl = TextEditingController(text: DummyData.userLocation);
    _selectedCurrency = preferredCurrency.value;
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    _locationCtrl.dispose();
    super.dispose();
  }

  Future<void> _handleRefresh() async {
    await Future.delayed(const Duration(milliseconds: 600));
    if (mounted) {
      setState(() {
        _nameCtrl.text = DummyData.userName;
        _emailCtrl.text = DummyData.userEmail;
        _phoneCtrl.text = DummyData.userPhone;
        _locationCtrl.text = DummyData.userLocation;
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
              subtitle: const Text('Use your camera to take a new photo'),
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
            if (_profileImage != null) ...[
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
                subtitle: const Text('Go back to the default avatar'),
                contentPadding: EdgeInsets.zero,
                onTap: () {
                  Navigator.of(ctx).pop();
                  setState(() => _profileImage = null);
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
      setState(() => _profileImage = File(picked.path));
    }
  }

  void _saveProfile() {
    setState(() {
      DummyData.userName = _nameCtrl.text.trim().isEmpty ? DummyData.userName : _nameCtrl.text.trim();
      DummyData.userEmail = _emailCtrl.text.trim().isEmpty ? DummyData.userEmail : _emailCtrl.text.trim();
      DummyData.userPhone = _phoneCtrl.text.trim();
      DummyData.userLocation = _locationCtrl.text.trim();
    });
  }

  void _savePreferences() {
    preferredCurrency.value = _selectedCurrency;
    setState(() {
      _isCurrencyDropdownOpen = false;
    });
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
          'Are you sure you want to sign out of your account?',
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
              widget.onLogout();
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

  void _showUpdatePasswordDialog() {
    final currentCtrl = TextEditingController();
    final newCtrl = TextEditingController();
    final confirmCtrl = TextEditingController();

    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return Padding(
          padding: EdgeInsets.only(
            left: 20,
            right: 20,
            top: 24,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Update Password',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.ink),
                  ),
                  IconButton(
                    onPressed: () => Navigator.of(ctx).pop(),
                    icon: const Icon(Icons.close_rounded, color: AppColors.muted),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              _formLabel('Current Password'),
              const SizedBox(height: 6),
              _passwordField(currentCtrl, 'Enter current password'),
              const SizedBox(height: 14),
              _formLabel('New Password'),
              const SizedBox(height: 6),
              _passwordField(newCtrl, 'Enter new password'),
              const SizedBox(height: 14),
              _formLabel('Confirm New Password'),
              const SizedBox(height: 6),
              _passwordField(confirmCtrl, 'Confirm new password'),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton.icon(
                  onPressed: () {
                    Navigator.of(ctx).pop();
                  },
                  icon: const Icon(Icons.lock_outline_rounded, size: 16, color: Colors.white),
                  label: const Text('Update Password', style: TextStyle(fontWeight: FontWeight.w700)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.dark,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    elevation: 0,
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.page,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const AppHeader(),
          Expanded(
            child: RefreshIndicator(
              color: AppColors.brand,
              backgroundColor: Colors.white,
              strokeWidth: 3.0,
              displacement: 40.0,
              onRefresh: _handleRefresh,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
                children: [
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
            'Manage your profile, security, and finance preferences.',
            style: TextStyle(fontSize: 14, color: AppColors.muted),
          ),
          const SizedBox(height: 18),

          // ─── Navigation Tabs Card ───
          AppCard(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
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
                  tabKey: 'preferences',
                  icon: Icons.settings_outlined,
                  label: 'Finance preferences',
                  isSelected: _activeTab == 'preferences',
                  onTap: () => setState(() => _activeTab = 'preferences'),
                ),
                _navItem(
                  tabKey: 'notifications',
                  icon: Icons.notifications_none_rounded,
                  label: 'Notifications',
                  isSelected: false,
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => const NotificationsScreen(),
                      ),
                    );
                  },
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

          // ─── Active Tab Content ───
          if (_activeTab == 'profile') ...[
            _buildProfileSection(),
          ] else if (_activeTab == 'security') ...[
            _buildSecuritySection(),
          ] else if (_activeTab == 'preferences') ...[
            _buildFinancePreferencesSection(),
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

  // ─── Tab 1: Profile Section ───
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
          Center(
            child: Column(
              children: [
                GestureDetector(
                  onTap: _pickPhoto,
                  child: Stack(
                    clipBehavior: Clip.none,
                    children: [
                      Container(
                        width: 86,
                        height: 86,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: const Color(0xFFF3ECE4),
                          border: Border.all(color: const Color(0xFFE5E7EB), width: 3),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.05),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ],
                          image: _profileImage != null
                              ? DecorationImage(
                                  image: FileImage(_profileImage!),
                                  fit: BoxFit.cover,
                                )
                              : null,
                        ),
                        child: _profileImage == null
                            ? const Center(
                                child: Icon(
                                  Icons.person_rounded,
                                  size: 52,
                                  color: Color(0xFF8C7E72),
                                ),
                              )
                            : null,
                      ),
                      // Camera badge
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
                  _nameCtrl.text.isEmpty ? DummyData.userName : _nameCtrl.text,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                  ),
                ),
                const SizedBox(height: 4),
                GestureDetector(
                  onTap: _pickPhoto,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEEF2FF),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: const Text(
                      'Change photo',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: AppColors.brand,
                      ),
                    ),
                  ),
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

          // Form fields data profile
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
              onPressed: _saveProfile,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.dark,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: const Text(
                'Save changes',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ─── Tab: About Section ───
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
                      'Application & system details',
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
          _aboutItem('App Version', '2.0.0'),
          _aboutItem('Platform', 'Mobile App (Flutter)'),
          _aboutItem('Developer', 'PKL Madtive Studio'),
          _aboutItem('Backend API', 'Laravel Framework'),
          const SizedBox(height: 12),
          const Divider(color: Color(0xFFF3F4F6), height: 1),
          const SizedBox(height: 14),
          const Text(
            'Finance App is a personal finance management application that helps you track expenses, manage wallets, set saving targets, and monitor your financial health with ease.',
            style: TextStyle(
              fontSize: 13,
              color: AppColors.muted,
              height: 1.5,
            ),
          ),
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

  // ─── Tab 2: Security Section ───
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
                      'Security',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Password & authentication settings',
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
          const SizedBox(height: 16),
          const Text(
            'Your password was last changed 3 months ago. Enable two-step verification for extra protection.',
            style: TextStyle(
              fontSize: 14,
              color: AppColors.muted,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 20),
          SizedBox(
            width: double.infinity,
            height: 46,
            child: OutlinedButton(
              onPressed: _showUpdatePasswordDialog,
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.ink,
                backgroundColor: Colors.white,
                side: const BorderSide(color: Color(0xFFE5E7EB)),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                elevation: 0,
              ),
              child: const Text(
                'Update password',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ─── Tab 3: Finance Preferences Section ───
  Widget _buildFinancePreferencesSection() {
    final currencyDetails = <String, Map<String, String>>{
      'IDR': {'symbol': 'Rp', 'title': 'IDR (Rp)', 'name': 'Indonesian Rupiah'},
      'USD': {'symbol': r'$', 'title': 'USD (\$)', 'name': 'US Dollar'},
      'EUR': {'symbol': '€', 'title': 'EUR (€)', 'name': 'Euro'},
    };

    final currentInfo = currencyDetails[_selectedCurrency] ?? {
      'symbol': _selectedCurrency,
      'title': _selectedCurrency,
      'name': '',
    };

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
                  Icons.settings_outlined,
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
                      'Finance preferences',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Set your reporting currency',
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
          const SizedBox(height: 18),

          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFE5E7EB)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Default currency',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ink,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Primary currency for transactions and balances',
                  style: TextStyle(
                    fontSize: 13,
                    color: AppColors.muted,
                  ),
                ),
                const SizedBox(height: 14),

                // Currency selector trigger button
                GestureDetector(
                  behavior: HitTestBehavior.opaque,
                  onTap: () => setState(() {
                    _isCurrencyDropdownOpen = !_isCurrencyDropdownOpen;
                  }),
                  child: Container(
                    height: 48,
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: _isCurrencyDropdownOpen ? AppColors.brand : const Color(0xFFE5E7EB),
                        width: _isCurrencyDropdownOpen ? 1.5 : 1,
                      ),
                    ),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: const Color(0xFFEEF2FF),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            currentInfo['symbol']!,
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: AppColors.brand,
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            currentInfo['title']!,
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                              color: AppColors.ink,
                            ),
                          ),
                        ),
                        Icon(
                          _isCurrencyDropdownOpen
                              ? Icons.keyboard_arrow_up_rounded
                              : Icons.keyboard_arrow_down_rounded,
                          color: AppColors.muted,
                        ),
                      ],
                    ),
                  ),
                ),

                // Currency dropdown popup
                if (_isCurrencyDropdownOpen) ...[
                  const SizedBox(height: 8),
                  Container(
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: const Color(0xFFE5E7EB)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.08),
                          blurRadius: 16,
                          offset: const Offset(0, 6),
                        ),
                      ],
                    ),
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Padding(
                          padding: EdgeInsets.fromLTRB(16, 6, 16, 8),
                          child: Text(
                            'SELECT CURRENCY',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.5,
                              color: Color(0xFF9CA3AF),
                            ),
                          ),
                        ),
                        for (final entry in currencyDetails.entries) ...[
                          _currencyOption(
                            code: entry.key,
                            symbol: entry.value['symbol']!,
                            title: entry.value['title']!,
                            name: entry.value['name']!,
                            isSelected: _selectedCurrency == entry.key,
                            onTap: () {
                              setState(() {
                                _selectedCurrency = entry.key;
                                _isCurrencyDropdownOpen = false;
                              });
                            },
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),

          const SizedBox(height: 20),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: ElevatedButton(
              onPressed: _savePreferences,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.dark,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: const Text(
                'Save preferences',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _currencyOption({
    required String code,
    required String symbol,
    required String title,
    required String name,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        color: isSelected ? const Color(0xFFF0F3FF) : Colors.transparent,
        child: Row(
          children: [
            Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(
                color: isSelected ? AppColors.brand : const Color(0xFFF3F4F6),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Center(
                child: Text(
                  symbol,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: isSelected ? Colors.white : AppColors.ink,
                  ),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: isSelected ? AppColors.brand : AppColors.ink,
                    ),
                  ),
                  const SizedBox(height: 1),
                  Text(
                    name,
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.muted,
                    ),
                  ),
                ],
              ),
            ),
            if (isSelected)
              const Icon(
                Icons.check_rounded,
                size: 20,
                color: AppColors.brand,
              ),
          ],
        ),
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
    void Function(String)? onChanged,
  }) {
    return Container(
      height: 48,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 14),
      child: TextField(
        controller: controller,
        keyboardType: keyboardType,
        onChanged: onChanged,
        style: const TextStyle(fontSize: 15, color: AppColors.ink),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: const TextStyle(color: AppColors.muted),
          border: InputBorder.none,
          isDense: true,
          contentPadding: const EdgeInsets.symmetric(vertical: 13),
        ),
      ),
    );
  }

  Widget _passwordField(TextEditingController controller, String hint) {
    return Container(
      height: 48,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 14),
      child: TextField(
        controller: controller,
        obscureText: true,
        style: const TextStyle(fontSize: 15, color: AppColors.ink),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: const TextStyle(color: AppColors.muted),
          border: InputBorder.none,
          isDense: true,
          contentPadding: const EdgeInsets.symmetric(vertical: 13),
        ),
      ),
    );
  }
}