import 'package:flutter/material.dart';
import '../models/notification_item.dart';
import '../screens/notifications_screen.dart';
import '../services/notification_service.dart';
import '../theme/app_colors.dart';

/// Menampilkan dropdown popup notifikasi persis seperti di Screenshot 1
void showNotificationDropdown(BuildContext context) {
  showDialog<void>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.15),
    builder: (dialogCtx) {
      return Stack(
        children: [
          Positioned(
            top: MediaQuery.of(dialogCtx).padding.top + 50,
            left: 20,
            right: 20,
            child: Material(
              color: Colors.transparent,
              child: ValueListenableBuilder<List<NotificationItem>>(
                valueListenable: NotificationService.notifications,
                builder: (context, items, _) {
                  final unreadCount = items.where((n) => !n.isRead).length;
                  final latestItems = items.take(3).toList();

                  return Container(
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFE5E7EB)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.12),
                          blurRadius: 20,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Header: Notifications [5]  Mark all as read
                        Row(
                          children: [
                            const Text(
                              'Notifications',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: AppColors.ink,
                              ),
                            ),
                            if (unreadCount > 0) ...[
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFEEF2FF),
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: Text(
                                  '$unreadCount',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.brand,
                                  ),
                                ),
                              ),
                            ],
                            const Spacer(),
                            InkWell(
                              onTap: () {
                                NotificationService.markAllAsRead();
                              },
                              child: const Text(
                                'Mark all as read',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.brand,
                                ),
                              ),
                            ),
                          ],
                        ),

                        const SizedBox(height: 14),
                        const Divider(height: 1, color: Color(0xFFF3F4F6)),
                        const SizedBox(height: 12),

                        // List of items
                        if (latestItems.isEmpty)
                          const Padding(
                            padding: EdgeInsets.symmetric(vertical: 16),
                            child: Center(
                              child: Text(
                                'No notifications yet',
                                style: TextStyle(
                                  fontSize: 13,
                                  color: AppColors.muted,
                                ),
                              ),
                            ),
                          )
                        else
                          ...latestItems.map((item) {
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: InkWell(
                                onTap: () {
                                  NotificationService.markAsRead(item.id);
                                  Navigator.of(dialogCtx).pop();
                                  Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) => const NotificationsScreen(),
                                    ),
                                  );
                                },
                                borderRadius: BorderRadius.circular(10),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Container(
                                      width: 36,
                                      height: 36,
                                      decoration: BoxDecoration(
                                        color: item.iconBg,
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                      child: Icon(
                                        item.icon,
                                        color: item.iconColor,
                                        size: 18,
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Row(
                                            mainAxisAlignment:
                                                MainAxisAlignment.spaceBetween,
                                            children: [
                                              Flexible(
                                                child: Text(
                                                  item.title,
                                                  style: const TextStyle(
                                                    fontSize: 14,
                                                    fontWeight: FontWeight.w700,
                                                    color: AppColors.ink,
                                                  ),
                                                  overflow:
                                                      TextOverflow.ellipsis,
                                                ),
                                              ),
                                              Text(
                                                item.timeAgo,
                                                style: const TextStyle(
                                                  fontSize: 11,
                                                  color: AppColors.muted,
                                                ),
                                              ),
                                            ],
                                          ),
                                          const SizedBox(height: 2),
                                          Text(
                                            item.message,
                                            style: const TextStyle(
                                              fontSize: 12,
                                              color: Color(0xFF6B7280),
                                              height: 1.35,
                                            ),
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            );
                          }),

                        const Divider(height: 1, color: Color(0xFFF3F4F6)),
                        const SizedBox(height: 10),

                        // View all ->
                        Center(
                          child: InkWell(
                            onTap: () {
                              Navigator.of(dialogCtx).pop();
                              Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => const NotificationsScreen(),
                                ),
                              );
                            },
                            borderRadius: BorderRadius.circular(8),
                            child: const Padding(
                              padding: EdgeInsets.symmetric(
                                  horizontal: 16, vertical: 6),
                              child: Text(
                                'View all →',
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.brand,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
            ),
          ),
        ],
      );
    },
  );
}
