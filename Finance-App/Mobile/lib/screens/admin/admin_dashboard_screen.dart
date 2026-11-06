import 'package:flutter/material.dart';
import '../../data/admin_dummy_data.dart';
import '../../models/admin_user.dart';
import '../../theme/app_colors.dart';
import '../../widgets/admin_activity_chart.dart';
import '../../widgets/admin_header.dart';
import '../../widgets/app_card.dart';
import 'admin_profile_screen.dart';
import 'admin_users_screen.dart';
import 'admin_verify_users_screen.dart';

/// Shell & Dashboard Admin dengan Navigasi Tab Bar Bawah (BottomNavigationBar).
/// Mengikuti instruksi: navigasi admin beralih ke model Tab Bar seperti user (bukan side menu drawer).
class AdminDashboardScreen extends StatefulWidget {
  const AdminDashboardScreen({super.key, required this.onExitAdmin});

  final VoidCallback onExitAdmin;

  @override
  State<AdminDashboardScreen> createState() => _AdminDashboardScreenState();
}

class _AdminDashboardScreenState extends State<AdminDashboardScreen> {
  int _currentIndex = 0;

  void _goToTab(int index) {
    setState(() => _currentIndex = index);
  }

  Future<void> _handleRefresh() async {
    await Future.delayed(const Duration(milliseconds: 600));
    if (mounted) {
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      _buildDashboardTab(),
      AdminVerifyUsersScreen(onExitAdmin: widget.onExitAdmin),
      AdminUsersScreen(onExitAdmin: widget.onExitAdmin),
      AdminProfileScreen(onExitAdmin: widget.onExitAdmin),
    ];

    return Scaffold(
      backgroundColor: AppColors.page,
      body: SafeArea(
        bottom: false,
        child: IndexedStack(
          index: _currentIndex,
          children: pages,
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: _goToTab,
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.dashboard_outlined),
            selectedIcon: Icon(Icons.dashboard_rounded),
            label: 'Dashboard',
          ),
          NavigationDestination(
            icon: Icon(Icons.verified_user_outlined),
            selectedIcon: Icon(Icons.verified_user_rounded),
            label: 'Verify',
          ),
          NavigationDestination(
            icon: Icon(Icons.people_outline_rounded),
            selectedIcon: Icon(Icons.people_rounded),
            label: 'Users',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline_rounded),
            selectedIcon: Icon(Icons.person_rounded),
            label: 'Profile',
          ),
        ],
      ),
    );
  }

  Widget _buildDashboardTab() {
    return Column(
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
                'Dashboard',
                style: TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w800,
                  color: AppColors.ink,
                  letterSpacing: -0.5,
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Monitor users, activity, and manage accounts.',
                style: TextStyle(fontSize: 14, color: AppColors.muted),
              ),
              const SizedBox(height: 20),

              // ─── Stat Card 1: Total Users ───
              AppCard(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Total Users',
                      style: TextStyle(fontSize: 14, color: AppColors.muted),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      '${AdminDummyData.totalUsers}',
                      style: const TextStyle(
                        fontSize: 34,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink,
                        letterSpacing: -0.5,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Across all platforms',
                      style: TextStyle(fontSize: 13, color: AppColors.muted),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // ─── Stat Card 2: Online Users ───
              AppCard(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Online Users',
                          style: TextStyle(fontSize: 14, color: AppColors.muted),
                        ),
                        Container(
                          width: 28,
                          height: 28,
                          decoration: const BoxDecoration(
                            color: Color(0xFFE8F7EE),
                            shape: BoxShape.circle,
                          ),
                          alignment: Alignment.center,
                          child: Container(
                            width: 10,
                            height: 10,
                            decoration: const BoxDecoration(
                              color: Color(0xFF16A34A),
                              shape: BoxShape.circle,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Text(
                      '${AdminDummyData.onlineUsers}',
                      style: const TextStyle(
                        fontSize: 34,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink,
                        letterSpacing: -0.5,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Currently active',
                      style: TextStyle(fontSize: 13, color: AppColors.muted),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // ─── Stat Card 3: New Users This Week ───
              AppCard(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'New Users This Week',
                          style: TextStyle(fontSize: 14, color: AppColors.muted),
                        ),
                        Container(
                          width: 28,
                          height: 28,
                          decoration: const BoxDecoration(
                            color: Color(0xFFEEF0FF),
                            shape: BoxShape.circle,
                          ),
                          alignment: Alignment.center,
                          child: const Icon(
                            Icons.trending_up_rounded,
                            size: 16,
                            color: AppColors.brand,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Text(
                      '${AdminDummyData.newUsersThisWeek}',
                      style: const TextStyle(
                        fontSize: 34,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink,
                        letterSpacing: -0.5,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Created in the last 7 days',
                      style: TextStyle(fontSize: 13, color: AppColors.muted),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // ─── User Activity — Last 7 Days ───
              AppCard(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'User Activity — Last 7 Days',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink,
                      ),
                    ),
                    const SizedBox(height: 4),
                    const Text(
                      'Total and new users by day',
                      style: TextStyle(fontSize: 13, color: AppColors.muted),
                    ),
                    const SizedBox(height: 12),

                    // Legend
                    Row(
                      children: [
                        _legendItem(color: AppColors.dark, label: 'Total Users'),
                        const SizedBox(width: 16),
                        _legendItem(color: AppColors.brand, label: 'New Users'),
                      ],
                    ),
                    const SizedBox(height: 16),

                    // Chart
                    AdminActivityChart(
                      days: AdminDummyData.chartDays,
                      totalUsers: AdminDummyData.chartTotalUsers,
                      newUsers: AdminDummyData.chartNewUsers,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // ─── Users Manage List Preview ───
              ValueListenableBuilder<List<AdminUser>>(
                valueListenable: AdminDummyData.managedUsers,
                builder: (context, users, _) {
                  return AppCard(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Text(
                              'Users Manage',
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                                color: AppColors.ink,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Text(
                              '${users.length} users',
                              style: const TextStyle(fontSize: 13, color: AppColors.muted),
                            ),
                            const Spacer(),
                            InkWell(
                              onTap: () => _goToTab(2),
                              borderRadius: BorderRadius.circular(6),
                              child: const Padding(
                                padding: EdgeInsets.all(4.0),
                                child: Text(
                                  'View all',
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: AppColors.brand,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),

                        // List of users preview
                        ListView.separated(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: users.length > 5 ? 5 : users.length,
                          separatorBuilder: (context, index) => const Divider(
                            color: Color(0xFFF1F5F9),
                            height: 16,
                          ),
                          itemBuilder: (context, index) {
                            final u = users[index];
                            return _userRow(u);
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
  );
  }

  Widget _legendItem({required Color color, required String label}) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(2),
          ),
        ),
        const SizedBox(width: 6),
        Text(
          label,
          style: const TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w500,
            color: AppColors.muted,
          ),
        ),
      ],
    );
  }

  Widget _userRow(AdminUser u) {
    return Row(
      children: [
        // Avatar circle / Initial
        Container(
          width: 34,
          height: 34,
          decoration: const BoxDecoration(
            color: Color(0xFFEEF0FF),
            shape: BoxShape.circle,
          ),
          alignment: Alignment.center,
          child: Text(
            u.initial,
            style: const TextStyle(
              fontSize: 13,
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
                  fontWeight: FontWeight.w600,
                  color: AppColors.ink,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 2),
              Text(
                u.email,
                style: const TextStyle(fontSize: 12, color: AppColors.muted),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),

        // Status pill
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
          decoration: BoxDecoration(
            color: u.isActive ? const Color(0xFFE8F7EE) : const Color(0xFFF1F5F9),
            borderRadius: BorderRadius.circular(999),
          ),
          child: Text(
            u.isActive ? 'Active' : 'Inactive',
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: u.isActive ? const Color(0xFF16A34A) : const Color(0xFF64748B),
            ),
          ),
        ),
      ],
    );
  }
}
