import 'package:flutter/material.dart';
import '../models/notification_item.dart';
import '../screens/notifications_screen.dart';
import '../services/notification_service.dart';
import '../theme/app_colors.dart';

/// A notification bell icon button with an animated pulsing red dot
/// when there are unread notifications. Clicking it navigates directly
/// to [NotificationsScreen].
class NotificationBellButton extends StatefulWidget {
  const NotificationBellButton({super.key});

  @override
  State<NotificationBellButton> createState() => _NotificationBellButtonState();
}

class _NotificationBellButtonState extends State<NotificationBellButton>
    with SingleTickerProviderStateMixin {
  late AnimationController _pulseCtrl;
  late Animation<double> _scaleAnim;
  late Animation<double> _opacityAnim;

  @override
  void initState() {
    super.initState();
    _pulseCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    )..repeat(reverse: true);

    _scaleAnim = Tween<double>(begin: 0.7, end: 1.4).animate(
      CurvedAnimation(parent: _pulseCtrl, curve: Curves.easeInOut),
    );
    _opacityAnim = Tween<double>(begin: 0.9, end: 0.3).animate(
      CurvedAnimation(parent: _pulseCtrl, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _pulseCtrl.dispose();
    super.dispose();
  }

  void _navigate() {
    Navigator.of(context, rootNavigator: true).push(
      MaterialPageRoute(builder: (_) => const NotificationsScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<List<NotificationItem>>(
      valueListenable: NotificationService.notifications,
      builder: (context, notifs, _) {
        final unread = notifs.where((n) => !n.isRead).length;
        return Stack(
          clipBehavior: Clip.none,
          children: [
            IconButton(
              tooltip: 'Notifications',
              onPressed: _navigate,
              icon: const Icon(
                Icons.notifications_none_rounded,
                color: AppColors.ink,
              ),
            ),
            if (unread > 0)
              Positioned(
                right: 8,
                top: 8,
                child: AnimatedBuilder(
                  animation: _pulseCtrl,
                  builder: (_, child) {
                    return Stack(
                      alignment: Alignment.center,
                      children: [
                        // Pulsing ring
                        Transform.scale(
                          scale: _scaleAnim.value,
                          child: Opacity(
                            opacity: _opacityAnim.value,
                            child: Container(
                              width: 12,
                              height: 12,
                              decoration: BoxDecoration(
                                color: AppColors.expense.withValues(alpha: 0.4),
                                shape: BoxShape.circle,
                              ),
                            ),
                          ),
                        ),
                        // Solid dot
                        Container(
                          width: 9,
                          height: 9,
                          decoration: const BoxDecoration(
                            color: AppColors.expense,
                            shape: BoxShape.circle,
                          ),
                        ),
                      ],
                    );
                  },
                ),
              ),
          ],
        );
      },
    );
  }
}
