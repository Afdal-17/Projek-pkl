import 'package:flutter/material.dart';

/// Model pengguna untuk fitur transfer ke pengguna lain.
class TransferUser {
  const TransferUser({
    required this.id,
    required this.name,
    required this.email,
    this.avatar = Icons.person_rounded,
    this.verified = false,
    this.walletName,
    this.statusText,
  });

  final String id;
  final String name;
  final String email;
  final IconData avatar;
  final bool verified;
  final String? walletName;
  final String? statusText;
}
