/// Model data pengguna untuk panel Admin (sesuai backend Laravel User model).
class AdminUser {
  final String id;
  final String name;
  final String email;
  final String? avatar;
  bool isActive;
  final String registeredAt;
  final String registeredDiff;
  bool isVerified;
  bool isBanned;
  String? banReason;

  AdminUser({
    required this.id,
    required this.name,
    required this.email,
    this.avatar,
    this.isActive = true,
    required this.registeredAt,
    required this.registeredDiff,
    this.isVerified = true,
    this.isBanned = false,
    this.banReason,
  });

  String get initial {
    final trimmed = name.trim();
    if (trimmed.isEmpty) return '?';
    return trimmed.substring(0, 1).toUpperCase();
  }
}
