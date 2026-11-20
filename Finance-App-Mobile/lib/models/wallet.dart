import 'package:flutter/material.dart';

import 'money.dart';

class Wallet {
  const Wallet({
    this.id,
    required this.name,
    required this.type,
    required this.balance,
    required this.isPrimary,
    required this.icon,
    required this.color,
    required this.background,
  });

  final dynamic id;
  final String name;
  final String type;
  final Money balance;
  double get balanceUsd => balance.baseAmount;
  final bool isPrimary;
  final IconData icon;
  final Color color;
  final Color background;
}
