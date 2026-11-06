import 'package:flutter/material.dart';
import '../data/admin_dummy_data.dart';
import '../theme/app_colors.dart';
import 'app_logo.dart';

/// Navigation Drawer khusus Admin (mengikuti layouts/admin.blade.php di web).
class AdminDrawer extends StatelessWidget {
  final String activeRoute;
  final ValueChanged<String> onNavigate;
  final VoidCallback onExitAdmin;

  const AdminDrawer({
    super.key,
    required this.activeRoute,
    required this.onNavigate,
    required this.onExitAdmin,
  });

  @override
  Widget build(BuildContext context) {
    return Drawer(
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.horizontal(left: Radius.circular(20)),
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Header Drawer
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const AppBrandHeader(fontSize: 16),
                  IconButton(
                    icon: const Icon(Icons.close_rounded, color: AppColors.muted),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
              const SizedBox(height: 24),

              // Nav items
              _navItem(
                context,
                title: 'Dashboard',
                icon: Icons.dashboard_outlined,
                activeIcon: Icons.dashboard_rounded,
                route: 'dashboard',
              ),
              const SizedBox(height: 6),
              _navItem(
                context,
                title: 'Verify Users',
                icon: Icons.verified_user_outlined,
                activeIcon: Icons.verified_user_rounded,
                route: 'verify-users',
              ),
              const SizedBox(height: 6),
              _navItem(
                context,
                title: 'Manage Users',
                icon: Icons.people_outline_rounded,
                activeIcon: Icons.people_rounded,
                route: 'users',
              ),
              const SizedBox(height: 6),
              _navItem(
                context,
                title: 'Profile',
                icon: Icons.person_outline_rounded,
                activeIcon: Icons.person_rounded,
                route: 'profile',
              ),

              const Spacer(),

              // Sign Out button
              OutlinedButton.icon(
                onPressed: () {
                  Navigator.of(context).pop();
                  onExitAdmin();
                },
                icon: const Icon(Icons.logout_rounded, size: 18, color: Color(0xFFDC2626)),
                label: const Text(
                  'Sign out',
                  style: TextStyle(
                    color: Color(0xFFDC2626),
                    fontWeight: FontWeight.w700,
                  ),
                ),
                style: OutlinedButton.styleFrom(
                  foregroundColor: const Color(0xFFDC2626),
                  side: const BorderSide(color: Color(0xFFFDECEC)),
                  backgroundColor: const Color(0xFFFFF5F5),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                ),
              ),

              const SizedBox(height: 12),
              const Divider(color: Color(0xFFE5E7EB), height: 1),
              const SizedBox(height: 12),

              // Admin Profile Card (Clickable to open profile)
              InkWell(
                borderRadius: BorderRadius.circular(10),
                onTap: () {
                  Navigator.of(context).pop();
                  if (activeRoute != 'profile') {
                    onNavigate('profile');
                  }
                },
                child: Padding(
                  padding: const EdgeInsets.all(4.0),
                  child: Row(
                    children: [
                      Container(
                        width: 40,
                        height: 40,
                        decoration: const BoxDecoration(
                          color: AppColors.brandSoft,
                          shape: BoxShape.circle,
                        ),
                        alignment: Alignment.center,
                        child: Text(
                          AdminDummyData.adminName.isNotEmpty
                              ? AdminDummyData.adminName.substring(0, 1).toUpperCase()
                              : 'A',
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.bold,
                            color: AppColors.brand,
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              AdminDummyData.adminName,
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w700,
                                color: AppColors.ink,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            Text(
                              AdminDummyData.adminEmail,
                              style: const TextStyle(
                                fontSize: 12,
                                color: AppColors.muted,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }

  Widget _navItem(
    BuildContext context, {
    required String title,
    required IconData icon,
    required IconData activeIcon,
    required String route,
  }) {
    final isCurrent = activeRoute == route;
    return Material(
      color: isCurrent ? AppColors.brandSoft : Colors.transparent,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: () {
          Navigator.of(context).pop();
          if (!isCurrent) {
            onNavigate(route);
          }
        },
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            children: [
              Icon(
                isCurrent ? activeIcon : icon,
                size: 20,
                color: isCurrent ? AppColors.brand : AppColors.muted,
              ),
              const SizedBox(width: 12),
              Text(
                title,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: isCurrent ? FontWeight.w700 : FontWeight.w500,
                  color: isCurrent ? AppColors.brand : AppColors.ink,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
