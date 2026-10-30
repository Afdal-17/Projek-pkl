import 'package:flutter/material.dart';

class Wallet {
  const Wallet({
    required this.name,
    required this.type,
    required this.balanceIdr,
    required this.isPrimary,
    required this.icon,
    required this.color,
    required this.background,
  });

  final String name;
  final String type;
  final double balanceIdr;
  final bool isPrimary;
  final IconData icon;
  final Color color;
  final Color background;
}