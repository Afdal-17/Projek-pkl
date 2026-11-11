import 'package:flutter/foundation.dart';
import '../models/admin_user.dart';
import '../services/admin_service.dart';

/// Data untuk Admin Dashboard, Verify Users, Manage Users & Admin Profile,
/// terhubung langsung ke REST API backend Laravel dengan fallback lokal.
class AdminDummyData {
  /// URL Endpoint RESTful API Backend Laravel
  static String get apiBaseUrl => 'https://sinuous-fame-jazz.ngrok-free.dev/api';
  AdminDummyData._();

  static int totalUsers = 0;
  static int onlineUsers = 0;
  static int newUsersThisWeek = 0;

  static List<String> chartDays = [
    'Mon',
    'Tue',
    'Wed',
    'Thu',
    'Fri',
    'Sat',
    'Sun',
  ];

  static List<double> chartTotalUsers = [0, 0, 0, 0, 0, 0, 0];
  static List<double> chartNewUsers = [0, 0, 0, 0, 0, 0, 0];

  // ─── Admin Profile State ───
  static String adminName = 'Admin';
  static String adminEmail = 'admin@finance.app';
  static String adminPhone = '081234567890';
  static String adminLocation = 'Indonesia';
  static dynamic adminAvatarFile; // File? jika dipilih dari device

  /// Daftar semua pengguna untuk "Admin - User Management" (Dimuat langsung dari API)
  static final ValueNotifier<List<AdminUser>> managedUsers =
      ValueNotifier<List<AdminUser>>([]);

  /// Daftar pengguna yang menunggu verifikasi (Dimuat langsung dari API)
  static final ValueNotifier<List<AdminUser>> unverifiedUsers =
      ValueNotifier<List<AdminUser>>([]);

  /// Memuat data live dari REST API backend Laravel
  static Future<void> loadFromApi() async {
    try {
      // 1. Dashboard summary & chart
      final dash = await AdminService.fetchDashboard();
      if (dash != null) {
        final summary = dash['summary'] as Map<String, dynamic>?;
        if (summary != null) {
          totalUsers = (summary['total_user'] as num?)?.toInt() ?? 0;
          onlineUsers = (summary['user_online'] as num?)?.toInt() ?? 0;
          newUsersThisWeek = (summary['user_baru_minggu_ini'] as num?)?.toInt() ?? 0;
        }

        final chart = dash['chart'] as Map<String, dynamic>?;
        if (chart != null) {
          final totalList = (chart['total'] as List?)?.map((e) => (e as num).toDouble()).toList();
          final freshList = (chart['fresh'] as List?)?.map((e) => (e as num).toDouble()).toList();
          if (totalList != null && totalList.isNotEmpty) chartTotalUsers = totalList;
          if (freshList != null && freshList.isNotEmpty) chartNewUsers = freshList;
        }
        final days = (dash['days'] as List?)?.map((e) => e.toString()).toList();
        if (days != null && days.isNotEmpty) chartDays = days;
      } else {
        // Fallback jika API belum ada respons, sinkronkan dengan jumlah user aktual
        totalUsers = managedUsers.value.length;
        onlineUsers = managedUsers.value.where((u) => u.isActive && !u.isBanned).length;
        if (totalUsers == 0) {
          onlineUsers = 0;
          newUsersThisWeek = 0;
          chartTotalUsers = [0, 0, 0, 0, 0, 0, 0];
          chartNewUsers = [0, 0, 0, 0, 0, 0, 0];
        }
      }

      // 2. All managed users
      final users = await AdminService.fetchUsers();
      if (users.isNotEmpty) {
        managedUsers.value = users;
      }

      // 3. Unverified users
      final unverified = await AdminService.fetchVerifyUsers();
      if (unverified.isNotEmpty) {
        unverifiedUsers.value = unverified;
      }
    } catch (_) {}
  }

  /// Verifikasi pengguna (meniru AdminController@verify di Laravel)
  static Future<bool> verifyUser(String userId) async {
    final res = await AdminService.verifyUser(userId);
    if (!res.success) return false;

    final list = List<AdminUser>.from(unverifiedUsers.value);
    final index = list.indexWhere((u) => u.id == userId);
    if (index == -1) return false;

    final user = list.removeAt(index);
    user.isVerified = true;
    user.isActive = true;
    unverifiedUsers.value = list;

    // Perbarui status di managedUsers
    final mList = List<AdminUser>.from(managedUsers.value);
    final mIndex = mList.indexWhere((u) => u.id == userId);
    if (mIndex != -1) {
      mList[mIndex].isVerified = true;
      mList[mIndex].isActive = true;
      managedUsers.value = mList;
    }

    return true;
  }

  /// Suspend / Ban user (meniru AdminController@ban di Laravel)
  static Future<bool> suspendUser(String userId, String reason) async {
    final res = await AdminService.banUser(userId, reason: reason);
    if (!res.success) return false;

    final mList = List<AdminUser>.from(managedUsers.value);
    final index = mList.indexWhere((u) => u.id == userId);
    if (index == -1) return false;

    mList[index].isBanned = true;
    mList[index].isActive = false;
    mList[index].banReason = reason.trim().isEmpty ? 'Account suspended by administrator' : reason.trim();
    managedUsers.value = mList;
    return true;
  }

  /// Unban user (meniru AdminController@status toggle unban di Laravel)
  static Future<bool> unbanUser(String userId) async {
    final res = await AdminService.toggleStatus(userId);
    if (!res.success) return false;

    final mList = List<AdminUser>.from(managedUsers.value);
    final index = mList.indexWhere((u) => u.id == userId);
    if (index == -1) return false;

    mList[index].isBanned = false;
    mList[index].isActive = true;
    mList[index].banReason = null;
    managedUsers.value = mList;
    return true;
  }

  /// Hapus user (meniru AdminController@destroy di Laravel)
  static Future<bool> deleteUser(String userId) async {
    final res = await AdminService.deleteUser(userId);
    if (!res.success) return false;

    final mList = List<AdminUser>.from(managedUsers.value);
    final index = mList.indexWhere((u) => u.id == userId);
    if (index == -1) return false;

    mList.removeAt(index);
    managedUsers.value = mList;
    totalUsers = mList.length;
    onlineUsers = mList.where((u) => u.isActive && !u.isBanned).length;
    if (totalUsers == 0) {
      onlineUsers = 0;
      newUsersThisWeek = 0;
      chartTotalUsers = [0, 0, 0, 0, 0, 0, 0];
      chartNewUsers = [0, 0, 0, 0, 0, 0, 0];
    }
    return true;
  }

  /// Tambah user baru atau daftarkan ulang user yang pernah dihapus
  static void registerUser({
    required String name,
    required String email,
  }) {
    final cleanEmail = email.trim().toLowerCase();
    final mList = List<AdminUser>.from(managedUsers.value);
    final existingIndex =
        mList.indexWhere((u) => u.email.toLowerCase() == cleanEmail);

    if (existingIndex != -1) {
      mList[existingIndex] = AdminUser(
        id: mList[existingIndex].id,
        name: name,
        email: cleanEmail,
        isActive: true,
        registeredAt: 'Just now',
        registeredDiff: 'Just now',
        isVerified: true,
        isBanned: false,
        banReason: null,
      );
    } else {
      mList.insert(
        0,
        AdminUser(
          id: (mList.length + 1).toString(),
          name: name,
          email: cleanEmail,
          isActive: true,
          registeredAt: 'Just now',
          registeredDiff: 'Just now',
          isVerified: true,
          isBanned: false,
          banReason: null,
        ),
      );
    }

    managedUsers.value = mList;
    totalUsers = mList.length;
    onlineUsers = mList.where((u) => u.isActive && !u.isBanned).length;
  }
}
