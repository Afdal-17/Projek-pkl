import 'package:flutter/material.dart';
import '../../data/admin_dummy_data.dart';
import '../../models/admin_user.dart';
import '../../theme/app_colors.dart';
import '../../widgets/admin_header.dart';
import '../../widgets/app_card.dart';

/// Halaman Admin - User Management (mengikuti Image 1, 2, 3, dan 4).
class AdminUsersScreen extends StatefulWidget {
  const AdminUsersScreen({super.key, required this.onExitAdmin});

  final VoidCallback onExitAdmin;

  @override
  State<AdminUsersScreen> createState() => _AdminUsersScreenState();
}

class _AdminUsersScreenState extends State<AdminUsersScreen> {
  final TextEditingController _searchCtrl = TextEditingController();
  String _query = '';

  @override
  void initState() {
    super.initState();
    AdminDummyData.loadFromApi();
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
    await AdminDummyData.loadFromApi();
    if (mounted) {
      setState(() {});
    }
  }

  /// Dialog untuk Suspend User (Image 3)
  Future<void> _openSuspendModal(AdminUser user) async {
    final reasonCtrl = TextEditingController();

    final result = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        contentPadding: const EdgeInsets.fromLTRB(22, 20, 22, 22),
        content: SizedBox(
          width: 360,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header modal
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Suspend User',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                      color: AppColors.ink,
                    ),
                  ),
                  InkWell(
                    onTap: () => Navigator.of(ctx).pop(false),
                    borderRadius: BorderRadius.circular(8),
                    child: Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF3F4F6),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Icon(Icons.close_rounded, size: 18, color: AppColors.muted),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              const Text(
                'Provide the reason for suspending this account.',
                style: TextStyle(fontSize: 13, color: AppColors.muted),
              ),
              const SizedBox(height: 18),

              // Field
              const Text(
                'Reason for Suspension (30 days)',
                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.ink),
              ),
              const SizedBox(height: 8),
              Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFE5E7EB)),
                ),
                child: TextField(
                  controller: reasonCtrl,
                  maxLines: 4,
                  style: const TextStyle(fontSize: 14, color: AppColors.ink),
                  decoration: const InputDecoration(
                    hintText: 'Explain the reason for suspending this account...',
                    hintStyle: TextStyle(fontSize: 13, color: Color(0xFF9CA3AF)),
                    border: InputBorder.none,
                    contentPadding: EdgeInsets.all(12),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'The user will see: "Your account has been suspended for 30 days due to [this reason]"',
                style: TextStyle(fontSize: 11, color: AppColors.muted),
              ),
              const SizedBox(height: 22),

              // Action buttons
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.of(ctx).pop(false),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.ink,
                        side: const BorderSide(color: Color(0xFFE5E7EB)),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                      child: const Text('Cancel', style: TextStyle(fontWeight: FontWeight.w600)),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () => Navigator.of(ctx).pop(true),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFDC2626),
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                      child: const Text('Suspend User', style: TextStyle(fontWeight: FontWeight.w700)),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );

    if (result == true && mounted) {
      final reason = reasonCtrl.text.trim();
      final success = await AdminDummyData.suspendUser(user.id, reason);
      if (success && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${user.name} has been suspended.'),
            backgroundColor: const Color(0xFFDC2626),
          ),
        );
      } else if (!success && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to suspend ${user.name}. Please try again.'),
            backgroundColor: Colors.orange,
          ),
        );
      }
    }
  }

  /// Dialog untuk Suspension Reason (Image 4)
  void _openReasonModal(AdminUser user) {
    showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        contentPadding: const EdgeInsets.fromLTRB(22, 22, 22, 22),
        content: SizedBox(
          width: 320,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF1E6),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.block_rounded,
                      color: Color(0xFFEA580C),
                      size: 22,
                    ),
                  ),
                  InkWell(
                    onTap: () => Navigator.of(ctx).pop(),
                    borderRadius: BorderRadius.circular(8),
                    child: Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF3F4F6),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Icon(Icons.close_rounded, size: 18, color: AppColors.muted),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Text(
                'Suspension Reason (${user.name})',
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: AppColors.ink,
                ),
              ),
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF9FAFB),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFE5E7EB)),
                ),
                child: Text(
                  user.banReason ?? 'No reason provided.',
                  style: const TextStyle(fontSize: 14, color: Color(0xFF374151)),
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () => Navigator.of(ctx).pop(),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.brand,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                  child: const Text('OK', style: TextStyle(fontWeight: FontWeight.w700)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _unbanUser(AdminUser user) async {
    final success = await AdminDummyData.unbanUser(user.id);
    if (success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${user.name} has been unbanned and can log in now.'),
          backgroundColor: const Color(0xFF16A34A),
        ),
      );
    } else if (!success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to unban ${user.name}. Please try again.'),
          backgroundColor: Colors.orange,
        ),
      );
    }
  }

  Future<void> _deleteUser(AdminUser user) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Delete User?', style: TextStyle(fontWeight: FontWeight.w800)),
        content: Text('Are you sure you want to permanently delete ${user.name}? This cannot be undone.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Cancel', style: TextStyle(color: AppColors.muted)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFDC2626),
              foregroundColor: Colors.white,
              elevation: 0,
            ),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirm == true && mounted) {
      final success = await AdminDummyData.deleteUser(user.id);
      if (success && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${user.name} has been deleted.'),
            backgroundColor: AppColors.dark,
          ),
        );
      } else if (!success && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to delete ${user.name}. Please try again.'),
            backgroundColor: Colors.orange,
          ),
        );
      }
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
                // Title & Subtitle (Image 1)
                const Text(
                  'Admin - User\nManagement',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                    letterSpacing: -0.5,
                    height: 1.2,
                  ),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Manage user accounts, suspensions, and bans.',
                  style: TextStyle(fontSize: 14, color: AppColors.muted),
                ),
                const SizedBox(height: 20),

                // Card All Users
                ValueListenableBuilder<List<AdminUser>>(
                  valueListenable: AdminDummyData.managedUsers,
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
                          // Header
                          Row(
                            children: [
                              const Text(
                                'All Users',
                                style: TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.ink,
                                ),
                              ),
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF1F5F9),
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: Text(
                                  '${filtered.length} users',
                                  style: const TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w600,
                                    color: Color(0xFF64748B),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 16),

                          // Search Box (Image 1)
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
                                hintText: 'Search users...',
                                hintStyle: TextStyle(fontSize: 14, color: Color(0xFF9CA3AF)),
                                prefixIcon: Icon(Icons.search_rounded, color: Color(0xFF9CA3AF), size: 20),
                                border: InputBorder.none,
                                contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                              ),
                            ),
                          ),
                          const SizedBox(height: 16),

                          // User List Items
                          if (filtered.isEmpty)
                            Padding(
                              padding: const EdgeInsets.symmetric(vertical: 36),
                              child: Center(
                                child: Column(
                                  children: [
                                    const Icon(
                                      Icons.people_outline_rounded,
                                      size: 40,
                                      color: AppColors.muted,
                                    ),
                                    const SizedBox(height: 10),
                                    Text(
                                      _query.isNotEmpty
                                          ? 'No users matching "$_query"'
                                          : 'No users registered yet.',
                                      style: const TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w500,
                                        color: AppColors.muted,
                                      ),
                                    ),
                                  ],
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
                                return _buildUserCard(u);
                              },
                            ),
                          const SizedBox(height: 20),

                          // Footer "Showing X of X users" (Image 2)
                          Center(
                            child: Column(
                              children: [
                                Text(
                                  'Showing ${filtered.length} of ${users.length} users',
                                  style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                                ),
                                const SizedBox(height: 10),
                                Container(
                                  width: 22,
                                  height: 6,
                                  decoration: BoxDecoration(
                                    color: AppColors.brand,
                                    borderRadius: BorderRadius.circular(999),
                                  ),
                                ),
                              ],
                            ),
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

  Widget _buildUserCard(AdminUser u) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Avatar
            Container(
              width: 38,
              height: 38,
              decoration: const BoxDecoration(
                color: Color(0xFFEEF0FF),
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: Text(
                u.initial,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: AppColors.brand,
                ),
              ),
            ),
            const SizedBox(width: 12),

            // Name & Email
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    u.name,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: AppColors.ink,
                      height: 1.25,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    u.email,
                    style: const TextStyle(fontSize: 12, color: AppColors.muted),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),

            // Status Pill (Active / Banned / Inactive)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
              decoration: BoxDecoration(
                color: u.isBanned
                    ? const Color(0xFFFDECEC)
                    : (u.isActive ? const Color(0xFFE8F7EE) : const Color(0xFFF1F5F9)),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                u.isBanned ? 'Banned' : (u.isActive ? 'Active' : 'Inactive'),
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: u.isBanned
                      ? const Color(0xFFDC2626)
                      : (u.isActive ? const Color(0xFF16A34A) : const Color(0xFF64748B)),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),

        // Action Buttons Row
        if (u.isBanned) ...[
          // Banned state: Unban, Reason, Delete (Image 3 top)
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              // Unban
              Material(
                color: const Color(0xFFE8F7EE),
                borderRadius: BorderRadius.circular(8),
                child: InkWell(
                  onTap: () => _unbanUser(u),
                  borderRadius: BorderRadius.circular(8),
                  child: const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    child: Row(
                      children: [
                        Icon(Icons.check_rounded, color: Color(0xFF16A34A), size: 14),
                        SizedBox(width: 4),
                        Text(
                          'Unban',
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
              const SizedBox(width: 8),

              // Reason
              Material(
                color: const Color(0xFFFFF1E6),
                borderRadius: BorderRadius.circular(8),
                child: InkWell(
                  onTap: () => _openReasonModal(u),
                  borderRadius: BorderRadius.circular(8),
                  child: const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    child: Row(
                      children: [
                        Icon(Icons.error_outline_rounded, color: Color(0xFFEA580C), size: 14),
                        SizedBox(width: 4),
                        Text(
                          'Reason',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFFEA580C),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),

              // Delete
              Material(
                color: const Color(0xFFFDECEC),
                borderRadius: BorderRadius.circular(8),
                child: InkWell(
                  onTap: () => _deleteUser(u),
                  borderRadius: BorderRadius.circular(8),
                  child: const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    child: Row(
                      children: [
                        Icon(Icons.delete_outline_rounded, color: Color(0xFFDC2626), size: 14),
                        SizedBox(width: 4),
                        Text(
                          'Delete',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFFDC2626),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ] else ...[
          // Normal state: Suspend Button (Image 1 & 2)
          Align(
            alignment: Alignment.centerRight,
            child: Material(
              color: const Color(0xFFFFF1F2), // bg soft red
              borderRadius: BorderRadius.circular(8),
              child: InkWell(
                onTap: () => _openSuspendModal(u),
                borderRadius: BorderRadius.circular(8),
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.block_rounded,
                        color: Color(0xFFDC2626),
                        size: 14,
                      ),
                      SizedBox(width: 5),
                      Text(
                        'Suspend',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFFDC2626),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

