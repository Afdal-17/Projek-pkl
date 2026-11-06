import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Teks "Showing x–y of n transactions" dan indikator halaman seperti di web.
class PaginationBar extends StatelessWidget {
  const PaginationBar({
    super.key,
    required this.page,
    required this.totalPages,
    required this.shownFrom,
    required this.shownTo,
    required this.total,
    required this.onPageChanged,
  });

  /// Halaman aktif, dimulai dari 0.
  final int page;
  final int totalPages;
  final int shownFrom;
  final int shownTo;
  final int total;
  final ValueChanged<int> onPageChanged;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          'Showing $shownFrom–$shownTo of $total transactions',
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 13, color: AppColors.muted),
        ),
        const SizedBox(height: 12),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            for (int i = 0; i < totalPages; i++)
              GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () => onPageChanged(i),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    width: i == page ? 24 : 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: i == page ? AppColors.brand : AppColors.line,
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}
