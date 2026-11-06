import 'package:flutter/material.dart';
import 'saving_goal.dart';

class NotificationItem {
  NotificationItem({
    required this.id,
    required this.title,
    required this.message,
    required this.category,
    required this.timestamp,
    this.isRead = false,
    this.icon = Icons.notifications_none_rounded,
    this.iconBg = const Color(0xFFEEF2FF),
    this.iconColor = const Color(0xFF4F46E5),
    this.goal,
    this.activityDetail,
  });

  final String id;
  final String title;
  final String message;
  final String category; // 'Goal', 'Transfer', 'Transaction', 'Verification', 'System'
  final DateTime timestamp;
  bool isRead;
  final IconData icon;
  final Color iconBg;
  final Color iconColor;
  final SavingGoal? goal;
  final String? activityDetail;

  String get timeAgo {
    final now = DateTime.now();
    final diff = now.difference(timestamp);

    if (diff.inSeconds < 60) {
      return 'Just now';
    } else if (diff.inMinutes < 60) {
      return '${diff.inMinutes}m ago';
    } else if (diff.inHours < 24) {
      return '${diff.inHours}h ago';
    } else if (diff.inDays == 1) {
      return 'Yesterday';
    } else if (diff.inDays < 7) {
      return '${diff.inDays} days ago';
    } else {
      return '${timestamp.day}/${timestamp.month}/${timestamp.year}';
    }
  }
}
