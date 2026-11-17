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

  factory AdminUser.fromJson(Map<String, dynamic> json) {
    final id = (json['id_user'] ?? json['id'] ?? '').toString();
    final name = (json['nama'] ?? json['name'] ?? 'User').toString();
    final email = (json['email'] ?? '').toString();
    final avatar = json['avatar_path']?.toString();
    final isActive = json['status'] == true || json['status'] == 1;
    final isVerified = json['email_verified_at'] != null;
    final isBanned = json['banned_at'] != null;
    final banReason = json['ban_reason']?.toString();

    String registeredAt = '';
    String registeredDiff = '';
    final createdAtRaw = json['created_at']?.toString();
    if (createdAtRaw != null && createdAtRaw.isNotEmpty) {
      final parsed = DateTime.tryParse(createdAtRaw);
      if (parsed != null) {
        final d = parsed.toLocal();
        const months = [
          'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
          'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
        ];
        final monthStr = months[d.month - 1];
        final hh = d.hour.toString().padLeft(2, '0');
        final mm = d.minute.toString().padLeft(2, '0');
        registeredAt = '${d.day} $monthStr ${d.year} $hh:$mm';

        final diff = DateTime.now().difference(d);
        if (diff.inDays > 0) {
          registeredDiff = '${diff.inDays} days ago';
        } else if (diff.inHours > 0) {
          registeredDiff = '${diff.inHours} hours ago';
        } else if (diff.inMinutes > 0) {
          registeredDiff = '${diff.inMinutes} mins ago';
        } else {
          registeredDiff = 'Just now';
        }
      }
    }

    return AdminUser(
      id: id,
      name: name,
      email: email,
      avatar: avatar,
      isActive: isActive,
      registeredAt: registeredAt.isEmpty ? 'Recently' : registeredAt,
      registeredDiff: registeredDiff.isEmpty ? 'Recently' : registeredDiff,
      isVerified: isVerified,
      isBanned: isBanned,
      banReason: banReason,
    );
  }

  String get initial {
    final trimmed = name.trim();
    if (trimmed.isEmpty) return '?';
    return trimmed.substring(0, 1).toUpperCase();
  }
}
