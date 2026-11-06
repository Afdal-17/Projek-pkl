import 'package:flutter/material.dart';
import '../data/dummy_data.dart';
import '../models/saving_goal.dart';
import '../theme/app_colors.dart';
import '../utils/currency.dart';
import '../widgets/app_button.dart';
import '../widgets/app_card.dart';
import '../widgets/app_header.dart';
import 'add_saving_target_screen.dart';

class SavingsScreen extends StatefulWidget {
  const SavingsScreen({super.key});

  @override
  State<SavingsScreen> createState() => _SavingsScreenState();
}

class _SavingsScreenState extends State<SavingsScreen> {
  Future<void> _handleRefresh() async {
    await Future.delayed(const Duration(milliseconds: 700));
    setState(() {});
  }

  void _openAddTarget() async {
    final added = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const AddSavingTargetScreen()),
    );
    if (added == true) setState(() {});
  }

  void _confirmDelete(SavingGoal goal) {
    showDialog<void>(
      context: context,
      builder: (ctx) => _DeleteDialog(
        goalName: goal.name,
        onConfirm: () {
          setState(() => DummyData.savings.remove(goal));
          Navigator.of(ctx).pop();
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<String>(
      valueListenable: preferredCurrency,
      builder: (context, currCode, _) {
        final goals = DummyData.savings;
        final totalSavedIdr =
            goals.fold<double>(0, (s, g) => s + g.savedIdr);
        final totalTargetIdr =
            goals.fold<double>(0, (s, g) => s + g.targetIdr);
        final funded = totalTargetIdr == 0
            ? 0
            : (totalSavedIdr / totalTargetIdr * 100).round();

        // Find next incomplete milestone
        final incomplete = goals.where((g) => g.progress < 1).toList();
        final nextGoal = incomplete.isNotEmpty ? incomplete.first : null;
        final nextSavedDisplay =
            nextGoal != null ? DummyData.money(nextGoal.savedIdr).displayAmount : 0.0;
        final nextTargetDisplay =
            nextGoal != null ? DummyData.money(nextGoal.targetIdr).displayAmount : 0.0;
        final nextRemaining = nextTargetDisplay - nextSavedDisplay;

        final totalSaved = DummyData.money(totalSavedIdr).displayAmount;
        final totalTarget = DummyData.money(totalTargetIdr).displayAmount;

        return Scaffold(
          backgroundColor: AppColors.page,
          body: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const AppHeader(),
              Expanded(
                child: RefreshIndicator(
                  onRefresh: _handleRefresh,
                  color: AppColors.brand,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
                    children: [
                      const Text(
                  'Savings Goals',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Set targets and follow your progress over time.',
                  style: TextStyle(fontSize: 14, color: AppColors.muted),
                ),
                const SizedBox(height: 16),
                AppButton(
                  label: 'Add Saving Target',
                  icon: Icons.add_rounded,
                  onPressed: _openAddTarget,
                ),
                const SizedBox(height: 16),

                // ─── Total Saved Card ───
                _StatCard(
                  label: 'Total Saved',
                  value: formatCurrency(totalSaved, currCode),
                  iconBg: AppColors.incomeSoft,
                  iconColor: AppColors.income,
                  icon: Icons.attach_money_rounded,
                ),
                const SizedBox(height: 12),

                // ─── Combined Target Card ───
                _StatCard(
                  label: 'Combined Target',
                  value: formatCurrency(totalTarget, currCode),
                  subtitle:
                      '$funded% funded across ${goals.length} targets',
                  iconBg: AppColors.brandSoft,
                  iconColor: AppColors.brand,
                  icon: Icons.track_changes_rounded,
                ),
                const SizedBox(height: 12),

                // ─── Next Milestone Card ───
                _StatCard(
                  label: 'Next Milestone',
                  value: nextGoal != null
                      ? formatCurrency(nextRemaining, currCode)
                      : '—',
                  subtitle: nextGoal != null
                      ? '${nextGoal.name}: ${formatCurrency(nextSavedDisplay, currCode)} / ${formatCurrency(nextTargetDisplay, currCode)}'
                      : 'All targets completed!',
                  iconBg: AppColors.warnSoft,
                  iconColor: AppColors.warn,
                  icon: Icons.history_rounded,
                ),
                const SizedBox(height: 20),

                // ─── Goal Cards ───
                for (final goal in goals) ...[
                  _SavingCard(
                    goal: goal,
                    currCode: currCode,
                    onDelete: () => _confirmDelete(goal),
                  ),
                  const SizedBox(height: 12),
                ],

                // Footer
                Center(
                  child: Text(
                    '${goals.length} active targets',
                    style: const TextStyle(
                        fontSize: 13, color: AppColors.muted),
                  ),
                ),
                const SizedBox(height: 20),
              ],
            ),
          ),
        ),
      ],
    ),
  );
      },
    );
  }
}

// ─── Stat Card ──────────────────────────────────────────────
class _StatCard extends StatelessWidget {
  const _StatCard({
    required this.label,
    required this.value,
    required this.iconBg,
    required this.iconColor,
    required this.icon,
    this.subtitle,
  });

  final String label;
  final String value;
  final String? subtitle;
  final Color iconBg;
  final Color iconColor;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return AppCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                      fontSize: 13, color: AppColors.muted),
                ),
                const SizedBox(height: 10),
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                    letterSpacing: -0.5,
                  ),
                ),
                if (subtitle != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    subtitle!,
                    style: const TextStyle(
                        fontSize: 12, color: AppColors.muted),
                  ),
                ],
              ],
            ),
          ),
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: iconBg,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: iconColor, size: 22),
          ),
        ],
      ),
    );
  }
}

// ─── Saving Goal Card ───────────────────────────────────────
class _SavingCard extends StatelessWidget {
  const _SavingCard({
    required this.goal,
    required this.currCode,
    required this.onDelete,
  });

  final SavingGoal goal;
  final String currCode;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final percent = (goal.progress * 100).round();
    final done = goal.progress >= 1;
    final savedDisplay = DummyData.money(goal.savedIdr).displayAmount;
    final targetDisplay = DummyData.money(goal.targetIdr).displayAmount;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header row: icon + name/desc + trash
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.brandSoft,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.track_changes_rounded,
                  color: AppColors.brand,
                  size: 24,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      goal.name,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: AppColors.ink,
                      ),
                    ),
                    if (goal.description.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        goal.description,
                        style: const TextStyle(
                            fontSize: 13, color: AppColors.muted),
                      ),
                    ],
                    const SizedBox(height: 2),
                    Text(
                      'From wallet: ${goal.walletName}',
                      style: const TextStyle(
                          fontSize: 13, color: AppColors.ink),
                    ),
                  ],
                ),
              ),
              IconButton(
                tooltip: 'Delete',
                onPressed: onDelete,
                icon: const Icon(
                  Icons.delete_outline_rounded,
                  color: AppColors.expense,
                  size: 22,
                ),
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Progress amounts
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                formatCurrency(savedDisplay, currCode),
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ink,
                ),
              ),
              Text(
                'of ${formatCurrency(targetDisplay, currCode)}',
                style: const TextStyle(
                    fontSize: 13, color: AppColors.muted),
              ),
            ],
          ),
          const SizedBox(height: 8),

          // Progress bar
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(
              value: goal.progress,
              minHeight: 8,
              backgroundColor: AppColors.brandSoft,
              color: done ? AppColors.income : AppColors.brand,
            ),
          ),
          const SizedBox(height: 10),

          // Status + percent badge
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                done ? 'All targets completed!' : 'saved',
                style: TextStyle(
                  fontSize: 12,
                  color: done ? AppColors.income : AppColors.muted,
                  fontWeight:
                      done ? FontWeight.w600 : FontWeight.w400,
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                    horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: done ? AppColors.incomeSoft : AppColors.brandSoft,
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  '$percent%',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: done ? AppColors.income : AppColors.brand,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ─── Delete Confirmation Dialog ─────────────────────────────
class _DeleteDialog extends StatelessWidget {
  const _DeleteDialog({
    required this.goalName,
    required this.onConfirm,
  });

  final String goalName;
  final VoidCallback onConfirm;

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(22, 22, 22, 18),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: AppColors.expenseSoft,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.delete_outline_rounded,
                    color: AppColors.expense,
                    size: 24,
                  ),
                ),
                const Spacer(),
                GestureDetector(
                  onTap: () => Navigator.of(context).pop(),
                  child: Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      color: const Color(0xFFF3F4F6),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(
                      Icons.close_rounded,
                      size: 18,
                      color: AppColors.ink,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            const Text(
              'Delete Saving Target?',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 10),
            RichText(
              text: TextSpan(
                style: const TextStyle(
                    fontSize: 14, color: AppColors.muted, height: 1.5),
                children: [
                  const TextSpan(
                      text: 'Are you sure you want to delete the '),
                  TextSpan(
                    text: goalName,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: AppColors.ink,
                    ),
                  ),
                  const TextSpan(
                      text:
                          ' target? This action cannot be undone.'),
                ],
              ),
            ),
            const SizedBox(height: 22),
            SizedBox(
              height: 46,
              child: ElevatedButton(
                onPressed: onConfirm,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.expense,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text(
                  'Delete',
                  style: TextStyle(
                      fontSize: 15, fontWeight: FontWeight.w700),
                ),
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              height: 46,
              child: OutlinedButton(
                onPressed: () => Navigator.of(context).pop(),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.ink,
                  side: const BorderSide(color: Color(0xFFE5E7EB)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  elevation: 0,
                ),
                child: const Text(
                  'Cancel',
                  style: TextStyle(
                      fontSize: 15, fontWeight: FontWeight.w700),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
