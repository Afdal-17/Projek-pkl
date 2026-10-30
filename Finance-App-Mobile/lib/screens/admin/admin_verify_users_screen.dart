import 'package:flutter/material.dart';
import '../../data/admin_dummy_data.dart';
import '../../models/admin_user.dart';
import '../../theme/app_colors.dart';
import '../../widgets/admin_header.dart';
import '../../widgets/app_card.dart';

/// Halaman Admin Verify Users (mengikuti Image 3 dari verify-users.blade.php web).
class AdminVerifyUsersScreen extends StatefulWidget {
  const AdminVerifyUsersScreen({super.key, required this.onExitAdmin});

  final VoidCallback onExitAdmin;

  @override
  State<AdminVerifyUsersScreen> createState() => _AdminVerifyUsersScreenState();
}

class _AdminVerifyUsersScreenState extends State<AdminVerifyUsersScreen> {
  final TextEditingController _searchCtrl = TextEditingController();
  String _query = '';

  @override
  void initState() {
    super.initState();
    _searchCtrl.addListener(() {
      setState(() {
        _query = _searchCtrl.text.trim().toLowerCase();
      });
    });
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _handleRefresh() async {
    await Future.delayed(const Duration(milliseconds: 600));
    if (mounted) {
      setState(() {});
    }
  }

  Future<void> _confirmVerify(AdminUser user) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text(
          'Verify User?',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: AppColors.ink),
        ),
        content: Text(
          "Are you sure you want to verify ${user.name}'s account to enable access?",
          style: const TextStyle(fontSize: 14, color: Color(0xFF4B5563)),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Cancel', style: TextStyle(color: AppColors.muted, fontWeight: FontWeight.w600)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF16A34A),
              foregroundColor: Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            child: const Text('Verify', style: TextStyle(fontWeight: FontWeight.w700)),
          ),
        ],
      ),
    );

    if (confirmed == true && mounted) {
      AdminDummyData.verifyUser(user.id);
    }
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
                // Title & Subtitle
                const Text(
                  'Verify Users',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Verify registered users to activate their accounts.',
                  style: TextStyle(fontSize: 14, color: AppColors.muted),
                ),
                const SizedBox(height: 24),

                // ─── Card Unverified Users ───
                ValueListenableBuilder<List<AdminUser>>(
                  valueListenable: AdminDummyData.unverifiedUsers,
                  builder: (context, users, _) {
                    final filtered = users.where((u) {
                      if (_query.isEmpty) return true;
                      return u.name.toLowerCase().contains(_query) ||
                          u.email.toLowerCase().contains(_query);
                    }).toList();

                    return AppCard(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Card Header
                          const Text(
                            'Unverified Users',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                              color: AppColors.ink,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            '${filtered.length} ${filtered.length == 1 ? "user" : "users"} waiting for verification',
                            style: const TextStyle(fontSize: 13, color: AppColors.muted),
                          ),
                          const SizedBox(height: 16),

                          // Search Box
                          Container(
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: const Color(0xFFE5E7EB)),
                            ),
                            child: TextField(
                              controller: _searchCtrl,
                              style: const TextStyle(fontSize: 14, color: AppColors.ink),
                              decoration: const InputDecoration(
                                hintText: 'Search by name or email...',
                                hintStyle: TextStyle(fontSize: 14, color: Color(0xFF9CA3AF)),
                                prefixIcon: Icon(Icons.search_rounded, color: Color(0xFF9CA3AF), size: 20),
                                border: InputBorder.none,
                                contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                              ),
                            ),
                          ),
                          const SizedBox(height: 18),

                          // List or Empty
                          if (filtered.isEmpty)
                            Padding(
                              padding: const EdgeInsets.symmetric(vertical: 32),
                              child: Center(
                                child: Text(
                                  _query.isNotEmpty
                                      ? 'No users found matching your search.'
                                      : 'No users waiting for verification.',
                                  style: const TextStyle(fontSize: 14, color: AppColors.muted),
                                ),
                              ),
                            )
                          else
                            ListView.separated(
                              shrinkWrap: true,
                              physics: const NeverScrollableScrollPhysics(),
                              itemCount: filtered.length,
                              separatorBuilder: (context, index) => const Divider(
                                color: Color(0xFFF1F5F9),
                                height: 24,
                              ),
                              itemBuilder: (context, i) {
                                final u = filtered[i];
                                return _unverifiedUserItem(u);
                              },
                            ),
                        ],
                      ),
                    );
                  },
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
  }

  Widget _unverifiedUserItem(AdminUser u) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Avatar peach
            Container(
              width: 38,
              height: 38,
              decoration: const BoxDecoration(
                color: Color(0xFFFFEDD5), // bg-warn-soft di web
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: Text(
                u.initial.toLowerCase(),
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFFEA580C), // text-warn
                ),
              ),
            ),
            const SizedBox(width: 12),

            // Name
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    u.name,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: AppColors.ink,
                    ),
                  ),
                ],
              ),
            ),

            // Verify Button
            Material(
              color: const Color(0xFFE8F7EE), // bg-income-soft
              borderRadius: BorderRadius.circular(8),
              child: InkWell(
                onTap: () => _confirmVerify(u),
                borderRadius: BorderRadius.circular(8),
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.check_rounded,
                        color: Color(0xFF16A34A),
                        size: 15,
                      ),
                      SizedBox(width: 4),
                      Text(
                        'Verify',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF16A34A),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),

        // Email & registered time
        Padding(
          padding: const EdgeInsets.only(left: 50, top: 4),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                u.email,
                style: const TextStyle(fontSize: 13, color: AppColors.muted),
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  Text(
                    u.registeredDiff,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF4B5563),
                    ),
                  ),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 6),
                    child: Text('•', style: TextStyle(color: Color(0xFFCBD5E1))),
                  ),
                  Text(
                    u.registeredAt,
                    style: const TextStyle(fontSize: 12, color: AppColors.muted),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}

