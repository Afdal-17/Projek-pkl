import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Painter vektor untuk ikon dollar berlingkar persis seperti di web
/// (`<x-logo />` di logo.blade.php & favicon.svg).
class CircleDollarPainter extends CustomPainter {
  final Color color;
  final double strokeWidth;

  const CircleDollarPainter({
    this.color = Colors.white,
    this.strokeWidth = 2.0,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final scale = size.width / 24.0;
    canvas.save();
    canvas.scale(scale, scale);

    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeWidth
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;

    // Circle cx="12" cy="12" r="10"
    canvas.drawCircle(const Offset(12, 12), 10, paint);

    // Dollar path: M15 9.5c0-1.1-1.3-2-3-2s-3 .9-3 2 1.3 2 3 2 3 .9 3 2-1.3 2-3 2-3-.9-3-2M12 6v1.5M12 16.5V18
    final path = Path();
    path.moveTo(15, 9.5);
    path.cubicTo(15, 8.4, 13.7, 7.5, 12, 7.5);
    path.cubicTo(10.3, 7.5, 9, 8.4, 9, 9.5);
    path.cubicTo(9, 10.8, 10.3, 11.5, 12, 11.5);
    path.cubicTo(13.7, 11.5, 15, 12.4, 15, 13.5);
    path.cubicTo(15, 14.6, 13.7, 15.5, 12, 15.5);
    path.cubicTo(10.3, 15.5, 9, 14.6, 9, 13.5);

    // Ticks atas & bawah
    path.moveTo(12, 6);
    path.lineTo(12, 7.5);
    path.moveTo(12, 16.5);
    path.lineTo(12, 18);

    canvas.drawPath(path, paint);
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant CircleDollarPainter oldDelegate) =>
      oldDelegate.color != color || oldDelegate.strokeWidth != strokeWidth;
}

/// Logo aplikasi resmi yang diambil dari projek web (Finance-App).
class AppLogo extends StatelessWidget {
  final double size;
  final double iconSize;
  final double borderRadius;

  const AppLogo({
    super.key,
    this.size = 40,
    this.iconSize = 20,
    this.borderRadius = 12,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: const Color(0xFF111827), // bg-dark di web
        borderRadius: BorderRadius.circular(borderRadius),
      ),
      alignment: Alignment.center,
      child: CustomPaint(
        size: Size(iconSize, iconSize),
        painter: const CircleDollarPainter(color: Colors.white, strokeWidth: 2.0),
      ),
    );
  }
}

/// Header baris brand: Logo + "Finance App"
class AppBrandHeader extends StatelessWidget {
  final double logoSize;
  final double fontSize;

  const AppBrandHeader({
    super.key,
    this.logoSize = 40,
    this.fontSize = 17,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        AppLogo(size: logoSize),
        const SizedBox(width: 10),
        Text(
          'Finance App',
          style: TextStyle(
            fontSize: fontSize,
            fontWeight: FontWeight.w800,
            color: AppColors.ink,
            letterSpacing: -0.2,
          ),
        ),
      ],
    );
  }
}
