import 'package:flutter/material.dart';
import '../data/dummy_data.dart';
import '../models/notification_item.dart';
import '../services/notification_service.dart';
import '../theme/app_colors.dart';
import '../utils/currency.dart';
import '../widgets/app_header.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  String _filterTab = 'all'; // 'all', 'unread', 'read'
  String _selectedCategory = 'All'; // 'All', 'Goal', 'Transfer', 'Transaction', 'Verification', 'System'
  bool _isCategoryDropdownOpen = false;
  final TextEditingController _searchCtrl = TextEditingController();
  final Set<String> _expandedIds = {'notif-1'};

  final List<String> _categories = [
    'All',
    'Goal',
    'Transfer',
    'Transaction',
    'Verification',
    'System',
  ];

  @override
  void initState() {
    super.initState();
    NotificationService.fetchNotifications();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<String>(
      valueListenable: preferredCurrency,
      builder: (context, currCode, _) {
        return ValueListenableBuilder<List<NotificationItem>>(
          valueListenable: NotificationService.notifications,
          builder: (context, items, _) {
            final allCount = items.length;
            final unreadCount = items.where((n) => !n.isRead).length;

            final query = _searchCtrl.text.trim().toLowerCase();

            // Filter logic
            final filtered = items.where((n) {
              if (_filterTab == 'unread' && n.isRead) return false;
              if (_filterTab == 'read' && !n.isRead) return false;

              if (_selectedCategory != 'All' &&
                  n.category.toLowerCase() != _selectedCategory.toLowerCase()) {
                return false;
              }

              if (query.isNotEmpty) {
                final matchTitle = n.title.toLowerCase().contains(query);
                final matchMsg = n.message.toLowerCase().contains(query);
                final matchCat = n.category.toLowerCase().contains(query);
                if (!matchTitle && !matchMsg && !matchCat) return false;
              }

              return true;
            }).toList();

            return Scaffold(
              backgroundColor: AppColors.page,
              appBar: AppHeader(
                showBackButton: true,
                action: unreadCount > 0
                    ? InkWell(
                        onTap: () => NotificationService.markAllAsRead(),
                        borderRadius: BorderRadius.circular(8),
                        child: const Padding(
                          padding: EdgeInsets.symmetric(horizontal: 4, vertical: 6),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.done_all_rounded, size: 16, color: AppColors.brand),
                              SizedBox(width: 4),
                              Text(
                                'Mark all read',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.brand,
                                ),
                              ),
                            ],
                          ),
                        ),
                      )
                    : const SizedBox.shrink(),
              ),
              body: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
                children: [
                  const Text(
                    'Notifications',
                    style: TextStyle(
                      fontSize: 26,
                      fontWeight: FontWeight.w800,
                      color: AppColors.ink,
                      letterSpacing: -0.5,
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Alerts for your financial activities and system.',
                    style: TextStyle(fontSize: 14, color: AppColors.muted),
                  ),
                  const SizedBox(height: 18),

                  // ─── Filter Tabs (All, Unread, Read) ───
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: const Color(0xFFE5E7EB)),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          _segmentTab(
                            title: 'All',
                            count: allCount,
                            badgeColor: const Color(0xFF4F46E5),
                            badgeTextColor: Colors.white,
                            isSelected: _filterTab == 'all',
                            onTap: () => setState(() => _filterTab = 'all'),
                          ),
                          Container(
                            height: 16,
                            width: 1,
                            color: const Color(0xFFE5E7EB),
                            margin: const EdgeInsets.symmetric(horizontal: 3),
                          ),
                          _segmentTab(
                            title: 'Unread',
                            count: unreadCount,
                            badgeColor: const Color(0xFFDC2626),
                            badgeTextColor: Colors.white,
                            isSelected: _filterTab == 'unread',
                            onTap: () => setState(() => _filterTab = 'unread'),
                          ),
                          Container(
                            height: 16,
                            width: 1,
                            color: const Color(0xFFE5E7EB),
                            margin: const EdgeInsets.symmetric(horizontal: 3),
                          ),
                          _segmentTab(
                            title: 'Read',
                            isSelected: _filterTab == 'read',
                            onTap: () => setState(() => _filterTab = 'read'),
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 16),

                  // ─── Search Bar & Category Dropdown ───
                  Row(
                    children: [
                      Expanded(
                        child: Container(
                          height: 44,
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFE5E7EB)),
                          ),
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          child: Row(
                            children: [
                              const Icon(Icons.search_rounded, size: 20, color: AppColors.muted),
                              const SizedBox(width: 8),
                              Expanded(
                                child: TextField(
                                  controller: _searchCtrl,
                                  onChanged: (_) => setState(() {}),
                                  style: const TextStyle(fontSize: 14, color: AppColors.ink),
                                  decoration: const InputDecoration(
                                    hintText: 'Search notifications...',
                                    hintStyle: TextStyle(fontSize: 14, color: AppColors.muted),
                                    border: InputBorder.none,
                                    isDense: true,
                                    contentPadding: EdgeInsets.zero,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      GestureDetector(
                        onTap: () => setState(() {
                          _isCategoryDropdownOpen = !_isCategoryDropdownOpen;
                        }),
                        child: Container(
                          height: 44,
                          padding: const EdgeInsets.symmetric(horizontal: 14),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: _isCategoryDropdownOpen ? AppColors.brand : const Color(0xFFE5E7EB),
                              width: _isCategoryDropdownOpen ? 1.5 : 1,
                            ),
                          ),
                          child: Row(
                            children: [
                              Text(
                                _selectedCategory == 'All' ? 'Category' : _selectedCategory,
                                style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.ink,
                                ),
                              ),
                              const SizedBox(width: 6),
                              Icon(
                                _isCategoryDropdownOpen
                                    ? Icons.keyboard_arrow_up_rounded
                                    : Icons.keyboard_arrow_down_rounded,
                                size: 18,
                                color: AppColors.muted,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),

                  // Category Dropdown Popup
                  if (_isCategoryDropdownOpen) ...[
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
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      child: Column(
                        children: _categories.map((cat) {
                          final isSelected = _selectedCategory == cat;
                          return InkWell(
                            onTap: () => setState(() {
                              _selectedCategory = cat;
                              _isCategoryDropdownOpen = false;
                            }),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                              color: isSelected ? const Color(0xFFEEF2FF) : Colors.transparent,
                              child: Row(
                                children: [
                                  Text(
                                    cat,
                                    style: TextStyle(
                                      fontSize: 14,
                                      fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                                      color: isSelected ? AppColors.brand : AppColors.ink,
                                    ),
                                  ),
                                  const Spacer(),
                                  if (isSelected)
                                    const Icon(
                                      Icons.check_rounded,
                                      size: 18,
                                      color: AppColors.brand,
                                    ),
                                ],
                              ),
                            ),
                          );
                        }).toList(),
                      ),
                    ),
                  ],

                  const SizedBox(height: 18),

                  // ─── Notification Items ───
                  if (filtered.isEmpty)
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(vertical: 48, horizontal: 20),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: const Color(0xFFE5E7EB)),
                      ),
                      alignment: Alignment.center,
                      child: Column(
                        children: [
                          const Icon(Icons.notifications_off_outlined, size: 48, color: AppColors.muted),
                          const SizedBox(height: 12),
                          Text(
                            query.isNotEmpty
                                ? 'No notifications found matching "$query".'
                                : 'No notifications to show.',
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              fontSize: 14,
                              color: AppColors.muted,
                            ),
                          ),
                        ],
                      ),
                    )
                  else
                    Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: const Color(0xFFE5E7EB)),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: Column(
                        children: [
                          for (int i = 0; i < filtered.length; i++) ...[
                            if (i > 0)
                              const Divider(height: 1, thickness: 1, color: Color(0xFFE5E7EB)),
                            _notificationRow(
                              item: filtered[i],
                              isExpanded: _expandedIds.contains(filtered[i].id),
                              currCode: currCode,
                              onToggleExpand: () {
                                setState(() {
                                  if (_expandedIds.contains(filtered[i].id)) {
                                    _expandedIds.remove(filtered[i].id);
                                  } else {
                                    _expandedIds.add(filtered[i].id);
                                    NotificationService.markAsRead(filtered[i].id);
                                  }
                                });
                              },
                            ),
                          ],
                        ],
                      ),
                    ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _segmentTab({
    required String title,
    int? count,
    Color? badgeColor,
    Color? badgeTextColor,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFEEF2FF) : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              title,
              style: TextStyle(
                fontSize: 14,
                fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                color: isSelected ? AppColors.brand : AppColors.muted,
              ),
            ),
            if (count != null && count > 0) ...[
              const SizedBox(width: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                decoration: BoxDecoration(
                  color: badgeColor ?? AppColors.brand,
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  count.toString(),
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    color: badgeTextColor ?? Colors.white,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _notificationRow({
    required NotificationItem item,
    required bool isExpanded,
    required String currCode,
    required VoidCallback onToggleExpand,
  }) {
    final isGoal = item.category.toLowerCase() == 'goal' || item.goal != null;

    return InkWell(
      onTap: onToggleExpand,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Row 1: Title + [Goal badge] ... timeAgo
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    if (!item.isRead) ...[
                      Container(
                        width: 7,
                        height: 7,
                        margin: const EdgeInsets.only(right: 8),
                        decoration: const BoxDecoration(
                          color: AppColors.brand,
                          shape: BoxShape.circle,
                        ),
                      ),
                    ],
                    Expanded(
                      child: Row(
                        children: [
                          Flexible(
                            child: Text(
                              (isGoal && !item.title.contains('🤩'))
                                  ? '${item.title} 🤩'
                                  : item.title,
                              style: TextStyle(
                                fontSize: 15,
                                fontWeight: item.isRead ? FontWeight.w700 : FontWeight.w800,
                                color: AppColors.ink,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          if (isGoal) ...[
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFFEEF2FF),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: const Text(
                                'Goal',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.brand,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      item.timeAgo,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.muted,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                // Row 2: Message ... Accordion Chevron
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    Expanded(
                      child: Text(
                        item.message,
                        style: const TextStyle(
                          fontSize: 13,
                          color: Color(0xFF4B5563),
                          height: 1.4,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Icon(
                      isExpanded
                          ? Icons.keyboard_arrow_up_rounded
                          : Icons.keyboard_arrow_down_rounded,
                      color: AppColors.muted,
                      size: 20,
                    ),
                  ],
                ),
              ],
            ),
          ),

          // Expanded Details (Goal or Activity Detail)
          if (isExpanded)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: isGoal
                  ? _buildGoalDetailCard(item.goal, currCode)
                  : _buildActivityDetailCard(item.activityDetail),
            ),
        ],
      ),
    );
  }

  Widget _buildGoalDetailCard(dynamic goal, String currCode) {
    final goalName = (goal?.name != null && goal.name.toString().isNotEmpty)
        ? goal.name.toString()
        : 'New Laptop';
    final goalNote = (goal?.description != null && goal.description.toString().isNotEmpty)
        ? goal.description.toString()
        : 'butuh laptop buat belajar';

    double savedDisplay;
    double targetDisplay;
    if (goal != null && goal.targetIdr > 0) {
      savedDisplay = DummyData.money(goal.savedIdr).displayAmount;
      targetDisplay = DummyData.money(goal.targetIdr).displayAmount;
    } else {
      savedDisplay = 98.88;
      targetDisplay = 98.88;
    }

    final progress = targetDisplay > 0 ? (savedDisplay / targetDisplay).clamp(0.0, 1.0) : 1.0;
    final percent = (progress * 100).round();
    final rawSaved = formatCurrency(savedDisplay, currCode);
    final rawTarget = formatCurrency(targetDisplay, currCode);
    final savedStr = currCode == 'EUR'
        ? '${rawSaved.replaceAll('€', '').trim()} €'
        : rawSaved;
    final targetStr = currCode == 'EUR'
        ? '${rawTarget.replaceAll('€', '').trim()} €'
        : rawTarget;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFF3F4F6),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFE5E7EB)),
                ),
                child: const Icon(
                  Icons.track_changes_rounded,
                  size: 22,
                  color: AppColors.brand,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      goalName,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: AppColors.ink,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      goalNote,
                      style: const TextStyle(fontSize: 12, color: AppColors.muted),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(color: const Color(0xFFE5E7EB)),
                ),
                child: Text(
                  '$percent%',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: AppColors.brand,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            '$savedStr saved',
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: Color(0xFF6B7280),
            ),
          ),
          const SizedBox(height: 6),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 8,
              backgroundColor: const Color(0xFFE5E7EB),
              valueColor: const AlwaysStoppedAnimation<Color>(AppColors.brand),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    savedStr,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: AppColors.ink,
                    ),
                  ),
                  Text(
                    'of $targetStr',
                    style: const TextStyle(
                      fontSize: 11.5,
                      color: AppColors.muted,
                    ),
                  ),
                ],
              ),
              const Icon(
                Icons.check_rounded,
                size: 22,
                color: Color(0xFF10B981),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildActivityDetailCard(String? activityDetail) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: const Color(0xFFF3F4F6),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Text(
        activityDetail ??
            'This activity was added to your history. You can view it again in Transactions.',
        style: const TextStyle(
          fontSize: 13,
          color: Color(0xFF4B5563),
          height: 1.45,
        ),
      ),
    );
  }
}
