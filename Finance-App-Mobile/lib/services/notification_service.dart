import 'dart:async';
import 'package:flutter/material.dart';
import '../models/notification_item.dart';
import '../models/saving_goal.dart';
import '../screens/notifications_screen.dart';
import '../theme/app_colors.dart';

class NotificationService {
  NotificationService._();

  static final ValueNotifier<List<NotificationItem>> notifications =
      ValueNotifier<List<NotificationItem>>([
    NotificationItem(
      id: '1',
      title: 'Transfer success!',
      message: 'You received a transfer of 0,49 € from Dzaky fadhilah rahmawan.',
      category: 'Transfer',
      timestamp: DateTime.now().subtract(const Duration(days: 3)),
      isRead: false,
      icon: Icons.swap_horiz_rounded,
      iconBg: const Color(0xFFEEF2FF),
      iconColor: AppColors.brand,
      activityDetail:
          'This activity was added to your history. You can view it again in Transactions.',
    ),
    NotificationItem(
      id: '2',
      title: 'Savings goal reached',
      message: 'Savings goal Lunasin hutang Indonesia has been reached.',
      category: 'Goal',
      timestamp: DateTime.now().subtract(const Duration(days: 3, hours: 2)),
      isRead: false,
      icon: Icons.track_changes_rounded,
      iconBg: const Color(0xFFEEF2FF),
      iconColor: AppColors.brand,
      goal: SavingGoal(
        name: 'New Laptop',
        description: 'butuh laptop buat belajar',
        walletName: 'BCA',
        savedIdr: 988900,
        targetIdr: 988900,
      ),
    ),
    NotificationItem(
      id: '3',
      title: 'Savings goal reached',
      message: 'Savings goal Laptop has been reached.',
      category: 'Goal',
      timestamp: DateTime.now().subtract(const Duration(days: 4)),
      isRead: false,
      icon: Icons.track_changes_rounded,
      iconBg: const Color(0xFFEEF2FF),
      iconColor: AppColors.brand,
      goal: SavingGoal(
        name: 'Laptop',
        description: 'Laptop for study',
        walletName: 'BCA',
        savedIdr: 500000,
        targetIdr: 500000,
      ),
    ),
    NotificationItem(
      id: '4',
      title: 'Transfer success!',
      message: 'Transfer of Rp 50.000 to SHOPEE completed.',
      category: 'Transfer',
      timestamp: DateTime.now().subtract(const Duration(days: 5)),
      isRead: false,
      icon: Icons.swap_horiz_rounded,
      iconBg: const Color(0xFFEEF2FF),
      iconColor: AppColors.brand,
      activityDetail:
          'This activity was added to your history. You can view it again in Transactions.',
    ),
    NotificationItem(
      id: '5',
      title: 'Verification approved',
      message: 'Your account identity has been verified successfully.',
      category: 'Verification',
      timestamp: DateTime.now().subtract(const Duration(days: 6)),
      isRead: false,
      icon: Icons.verified_user_outlined,
      iconBg: const Color(0xFFE8F7EE),
      iconColor: AppColors.income,
    ),
    NotificationItem(
      id: '6',
      title: 'Transaction added',
      message: 'Salary payment of Rp 8.500.000 recorded in Cash.',
      category: 'Transaction',
      timestamp: DateTime.now().subtract(const Duration(days: 7)),
      isRead: true,
      icon: Icons.receipt_long_outlined,
      iconBg: const Color(0xFFE8F7EE),
      iconColor: AppColors.income,
      activityDetail:
          'This activity was added to your history. You can view it again in Transactions.',
    ),
    NotificationItem(
      id: '7',
      title: 'System update',
      message: 'Finance App updated to version 2.0 with enhanced security.',
      category: 'System',
      timestamp: DateTime.now().subtract(const Duration(days: 10)),
      isRead: true,
      icon: Icons.info_outline_rounded,
      iconBg: const Color(0xFFF3F4F6),
      iconColor: AppColors.muted,
    ),
  ]);

  static int get unreadCount =>
      notifications.value.where((n) => !n.isRead).length;

  static void markAsRead(String id) {
    final list = List<NotificationItem>.from(notifications.value);
    final idx = list.indexWhere((n) => n.id == id);
    if (idx != -1) {
      list[idx].isRead = true;
      notifications.value = list;
    }
  }

  static void markAllAsRead() {
    final list = List<NotificationItem>.from(notifications.value);
    for (final item in list) {
      item.isRead = true;
    }
    notifications.value = list;
  }

  static void addNotification(
    NotificationItem item, {
    BuildContext? context,
  }) {
    final list = List<NotificationItem>.from(notifications.value);
    list.insert(0, item);
    notifications.value = list;

    if (context != null) {
      showTopNotificationBanner(context, item);
    }
  }

  static void notifyTransfer({
    required BuildContext context,
    required String title,
    required String message,
  }) {
    final notif = NotificationItem(
      id: DateTime.now().millisecondsSinceEpoch.toString(),
      title: title,
      message: message,
      category: 'Transfer',
      timestamp: DateTime.now(),
      isRead: false,
      icon: Icons.swap_horiz_rounded,
      iconBg: const Color(0xFFEEF2FF),
      iconColor: AppColors.brand,
      activityDetail:
          'This activity was added to your history. You can view it again in Transactions.',
    );
    addNotification(notif, context: context);
  }

  static void notifySavingGoal({
    required BuildContext context,
    required String name,
    required String message,
    SavingGoal? goal,
  }) {
    final notif = NotificationItem(
      id: DateTime.now().millisecondsSinceEpoch.toString(),
      title: 'Savings goal updated',
      message: message,
      category: 'Goal',
      timestamp: DateTime.now(),
      isRead: false,
      icon: Icons.track_changes_rounded,
      iconBg: const Color(0xFFEEF2FF),
      iconColor: AppColors.brand,
      goal: goal,
    );
    addNotification(notif, context: context);
  }

  static void notifyTransaction({
    required BuildContext context,
    required String name,
    required String amountStr,
    required bool isIncome,
  }) {
    final notif = NotificationItem(
      id: DateTime.now().millisecondsSinceEpoch.toString(),
      title: isIncome ? 'Income received' : 'Expense recorded',
      message: '${isIncome ? "Income" : "Expense"} "$name" of $amountStr has been recorded.',
      category: 'Transaction',
      timestamp: DateTime.now(),
      isRead: false,
      icon: isIncome ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded,
      iconBg: isIncome ? AppColors.incomeSoft : AppColors.expenseSoft,
      iconColor: isIncome ? AppColors.income : AppColors.expense,
      activityDetail:
          'This activity was added to your history. You can view it again in Transactions.',
    );
    addNotification(notif, context: context);
  }

  /// Tampilan Toast / In-App Notification Banner di bagian atas layar (Screenshot 5)
  static void showTopNotificationBanner(BuildContext context, NotificationItem item) {
    final overlay = Overlay.maybeOf(context, rootOverlay: true) ?? Overlay.maybeOf(context);
    if (overlay == null) return;

    late OverlayEntry entry;
    Timer? dismissTimer;
    final key = GlobalKey<_TopBannerWidgetState>();

    Future<void> removeBanner() async {
      dismissTimer?.cancel();
      if (entry.mounted) {
        await key.currentState?.animateDismiss();
        if (entry.mounted) entry.remove();
      }
    }

    entry = OverlayEntry(
      builder: (ctx) => _TopBannerWidget(
        key: key,
        item: item,
        onDismiss: () => removeBanner(),
        onView: () {
          removeBanner();
          markAsRead(item.id);
          Navigator.of(context, rootNavigator: true).push(
            MaterialPageRoute(builder: (_) => const NotificationsScreen()),
          );
        },
      ),
    );

    overlay.insert(entry);

    dismissTimer = Timer(const Duration(seconds: 4), () => removeBanner());
  }
}

class _TopBannerWidget extends StatefulWidget {
  const _TopBannerWidget({
    super.key,
    required this.item,
    required this.onDismiss,
    required this.onView,
  });

  final NotificationItem item;
  final VoidCallback onDismiss;
  final VoidCallback onView;

  @override
  State<_TopBannerWidget> createState() => _TopBannerWidgetState();
}

class _TopBannerWidgetState extends State<_TopBannerWidget>
    with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<Offset> _offsetAnim;
  late Animation<double> _fadeAnim;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 360),
    );
    _offsetAnim = Tween<Offset>(
      begin: const Offset(0, -1.2),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: _ctrl, curve: Curves.easeOutCubic));
    _fadeAnim = Tween<double>(begin: 0.0, end: 1.0)
        .animate(CurvedAnimation(parent: _ctrl, curve: Curves.easeIn));
    _ctrl.forward();
  }

  /// Slide the banner back up — awaited before removing the overlay entry.
  Future<void> animateDismiss() async {
    if (!mounted) return;
    await _ctrl.reverse();
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final topPadding = MediaQuery.of(context).padding.top + 10;

    return Positioned(
      top: topPadding,
      left: 16,
      right: 16,
      child: Material(
        color: Colors.transparent,
        child: SlideTransition(
          position: _offsetAnim,
          child: FadeTransition(
            opacity: _fadeAnim,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFE5E7EB)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.12),
                    blurRadius: 18,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: const BoxDecoration(
                      color: Color(0xFFEEF2FF),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.notifications_none_rounded,
                      color: AppColors.brand,
                      size: 22,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Text(
                          'Notifications',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                            color: AppColors.ink,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          widget.item.message,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppColors.muted,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  TextButton(
                    onPressed: widget.onView,
                    style: TextButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                      minimumSize: Size.zero,
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                    child: const Text(
                      'View',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: AppColors.brand,
                      ),
                    ),
                  ),
                  const SizedBox(width: 4),
                  InkWell(
                    onTap: widget.onDismiss,
                    borderRadius: BorderRadius.circular(12),
                    child: const Padding(
                      padding: EdgeInsets.all(4),
                      child: Icon(
                        Icons.close_rounded,
                        size: 18,
                        color: AppColors.muted,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
