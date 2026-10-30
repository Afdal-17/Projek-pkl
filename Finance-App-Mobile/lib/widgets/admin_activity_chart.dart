import 'dart:math' as math;
import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Chart garis aktivitas pengguna 7 hari terakhir (User Activity — Last 7 Days).
/// Mengikuti koordinat dan kurva bezier persis seperti dashboard.blade.php di Laravel.
class AdminActivityChart extends StatelessWidget {
  final List<String> days;
  final List<double> totalUsers;
  final List<double> newUsers;

  const AdminActivityChart({
    super.key,
    required this.days,
    required this.totalUsers,
    required this.newUsers,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 180,
      width: double.infinity,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      padding: const EdgeInsets.fromLTRB(10, 14, 14, 10),
      child: CustomPaint(
        painter: _ActivityChartPainter(
          days: days,
          totalUsers: totalUsers,
          newUsers: newUsers,
        ),
      ),
    );
  }
}

class _ActivityChartPainter extends CustomPainter {
  final List<String> days;
  final List<double> totalUsers;
  final List<double> newUsers;

  _ActivityChartPainter({
    required this.days,
    required this.totalUsers,
    required this.newUsers,
  });

  @override
  void paint(Canvas canvas, Size size) {
    if (days.isEmpty || totalUsers.isEmpty || newUsers.isEmpty) return;

    final padL = 28.0;
    final padR = 12.0;
    final padT = 12.0;
    final padB = 22.0;

    final chartW = size.width - padL - padR;
    final chartH = size.height - padT - padB;

    // Nilai tertinggi
    final maxVal = [
      ...totalUsers,
      ...newUsers,
    ].fold<double>(15.0, (m, v) => math.max(m, v));
    final chartMax = (maxVal / 5).ceil() * 5.0; // kelipatan 5

    // Ticks horizontal
    final ticks = [
      chartMax,
      (chartMax * 0.75).roundToDouble(),
      (chartMax * 0.5).roundToDouble(),
      (chartMax * 0.25).roundToDouble(),
    ];

    final gridPaint = Paint()
      ..color = const Color(0xFFE5E7EB)
      ..strokeWidth = 1;

    final tickStyle = const TextStyle(
      fontSize: 9,
      color: Color(0xFF9CA3AF),
      fontWeight: FontWeight.w500,
    );

    for (final tick in ticks) {
      final y = padT + (1 - tick / chartMax) * chartH;
      canvas.drawLine(Offset(padL, y), Offset(size.width - padR, y), gridPaint);

      final textSpan = TextSpan(text: tick.toInt().toString(), style: tickStyle);
      final textPainter = TextPainter(
        text: textSpan,
        textDirection: TextDirection.ltr,
      )..layout();
      textPainter.paint(canvas, Offset(2, y - 6));
    }

    double getX(int i) => padL + i * (chartW / (days.length - 1));
    double getY(double v) => padT + (1 - v / chartMax) * chartH;

    // Gambar kurva bezier halus
    Path buildSplinePath(List<double> values) {
      final path = Path();
      if (values.isEmpty) return path;

      final points = <Offset>[];
      for (int i = 0; i < values.length; i++) {
        points.add(Offset(getX(i), getY(values[i])));
      }

      path.moveTo(points[0].dx, points[0].dy);

      for (int i = 0; i < points.length - 1; i++) {
        final p0 = i > 0 ? points[i - 1] : points[i];
        final p1 = points[i];
        final p2 = points[i + 1];
        final p3 = i < points.length - 2 ? points[i + 2] : p2;

        final c1x = p1.dx + (p2.dx - p0.dx) / 6;
        final c1y = p1.dy + (p2.dy - p0.dy) / 6;
        final c2x = p2.dx - (p3.dx - p1.dx) / 6;
        final c2y = p2.dy - (p3.dy - p1.dy) / 6;

        path.cubicTo(c1x, c1y, c2x, c2y, p2.dx, p2.dy);
      }

      return path;
    }

    // Garis Total Users (Hitam/Dark)
    final totalPaint = Paint()
      ..color = AppColors.dark
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.0
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    canvas.drawPath(buildSplinePath(totalUsers), totalPaint);

    // Garis New Users (Ungu/Brand)
    final newPaint = Paint()
      ..color = AppColors.brand
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.0
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    canvas.drawPath(buildSplinePath(newUsers), newPaint);

    // Label hari di bawah
    final dayStyle = const TextStyle(
      fontSize: 10,
      color: Color(0xFF6B7280),
      fontWeight: FontWeight.w500,
    );

    for (int i = 0; i < days.length; i++) {
      final textSpan = TextSpan(text: days[i], style: dayStyle);
      final textPainter = TextPainter(
        text: textSpan,
        textDirection: TextDirection.ltr,
      )..layout();
      textPainter.paint(
        canvas,
        Offset(getX(i) - textPainter.width / 2, size.height - padB + 6),
      );
    }
  }

  @override
  bool shouldRepaint(covariant _ActivityChartPainter oldDelegate) => true;
}

