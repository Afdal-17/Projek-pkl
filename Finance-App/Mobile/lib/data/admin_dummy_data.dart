import 'package:flutter/foundation.dart';
import '../models/admin_user.dart';

/// Data dummy untuk Admin Dashboard, Verify Users, Manage Users & Admin Profile,
/// disesuaikan persis dengan backend Laravel (AdminController & AdminProfileController).
class AdminDummyData {
  /// URL Endpoint RESTful API Backend Laravel
  static String get apiBaseUrl => 'https://sinuous-fame-jazz.ngrok-free.dev/api';
  AdminDummyData._();

  static int totalUsers = 14;
  static int onlineUsers = 1;
  static int newUsersThisWeek = 5;

  static const List<String> chartDays = [
    'Fri',
    'Sat',
    'Sun',
    'Mon',
    'Tue',
    'Wed',
    'Thu',
  ];

  static const List<double> chartTotalUsers = [9, 11, 12, 12, 12, 12, 14];
  static const List<double> chartNewUsers = [2, 4, 3, 2, 2, 2, 3];

  // ─── Admin Profile State ───
  static String adminName = 'Admin';
  static String adminEmail = 'admin@finance.app';
  static String adminPhone = '081234567890';
  static String adminLocation = 'Indonesia';
  static dynamic adminAvatarFile; // File? jika dipilih dari device

  /// Daftar semua pengguna untuk "Admin - User Management" (All Users: 12 users)
  static final ValueNotifier<List<AdminUser>> managedUsers = ValueNotifier<List<AdminUser>>([
    AdminUser(
      id: '1',
      name: '07 Dzaky Fadhillah Rahmawan _',
      email: 'dzakyfadhilahrahmawan@gmail.com',
      isActive: true,
      registeredAt: '12 Oct 2026 10:15',
      registeredDiff: '17 days ago',
      isVerified: true,
      isBanned: false,
    ),
    AdminUser(
      id: '2',
      name: '17_M Afdal Firmansyah',
      email: 'fafdal46@gmail.com',
      isActive: true,
      registeredAt: '20 Oct 2026 14:10',
      registeredDiff: '9 days ago',
      isVerified: true,
      isBanned: false,
    ),
    AdminUser(
      id: '3',
      name: "25_ Muhammad Rofi'i Alawi",
      email: 'rofiialawi@gmail.com',
      isActive: true,
      registeredAt: '21 Oct 2026 08:30',
      registeredDiff: '8 days ago',
      isVerified: true,
      isBanned: false,
    ),
    AdminUser(
      id: '4',
      name: 'Chris Simatupang',
      email: 'zab.com2004@gmail.com',
      isActive: true,
      registeredAt: '24 Oct 2026 09:20',
      registeredDiff: '5 days ago',
      isVerified: true,
      isBanned: false,
    ),
    AdminUser(
      id: '5',
      name: 'Dzaky',
      email: 'rofim623@gmail.com',
      isActive: false,
      registeredAt: '22 Oct 2026 16:40',
      registeredDiff: '7 days ago',
      isVerified: true,
      isBanned: true,
      banReason: 'gak aktif',
    ),
    AdminUser(
      id: '6',
      name: 'Dzaky fadhilah',
      email: 'dzakyosamu@gmail.com',
      isActive: true,
      registeredAt: '25 Oct 2026 11:45',
      registeredDiff: '4 days ago',
      isVerified: true,
      isBanned: false,
    ),
    AdminUser(
      id: '7',
      name: "Muhammad Rofi'i Alawi",
      email: 'gaktaubanh68@gmail.com',
      isActive: true,
      registeredAt: '28 Oct 2026 18:00',
      registeredDiff: '1 day ago',
      isVerified: true,
      isBanned: false,
    ),
    AdminUser(
      id: '8',
      name: 'Rofi_ Ngeditz',
      email: 'rofingeditz@gmail.com',
      isActive: true,
      registeredAt: '26 Oct 2026 19:12',
      registeredDiff: '3 days ago',
      isVerified: true,
      isBanned: false,
    ),
    AdminUser(
      id: '9',
      name: 'Afout Gang 5',
      email: 'afoutgang@gmail.com',
      isActive: true,
      registeredAt: '27 Oct 2026 13:05',
      registeredDiff: '2 days ago',
      isVerified: true,
      isBanned: false,
    ),
    AdminUser(
      id: '10',
      name: 'odol',
      email: 'dodol@gmail.com',
      isActive: false,
      registeredAt: '29 Oct 2026 03:39',
      registeredDiff: '20 hours ago',
      isVerified: false,
      isBanned: false,
    ),
    AdminUser(
      id: '11',
      name: 'Nasabah BCA',
      email: 'nasabah@bca.co.id',
      isActive: true,
      registeredAt: '23 Oct 2026 11:00',
      registeredDiff: '6 days ago',
      isVerified: true,
      isBanned: false,
    ),
    AdminUser(
      id: '12',
      name: 'Administrator',
      email: 'admin@finance.app',
      isActive: true,
      registeredAt: '01 Oct 2026 00:00',
      registeredDiff: '29 days ago',
      isVerified: true,
      isBanned: false,
    ),
  ]);

  /// Daftar pengguna yang menunggu verifikasi
  static final ValueNotifier<List<AdminUser>> unverifiedUsers = ValueNotifier<List<AdminUser>>([
    AdminUser(
      id: '10',
      name: 'odol',
      email: 'dodol@gmail.com',
      isActive: false,
      registeredAt: '29 Oct 2026 03:39',
      registeredDiff: '20 hours ago',
      isVerified: false,
    ),
  ]);

  /// Verifikasi pengguna (meniru AdminController@verify di Laravel)
  static bool verifyUser(String userId) {
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
  static bool suspendUser(String userId, String reason) {
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
  static bool unbanUser(String userId) {
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
  static bool deleteUser(String userId) {
    final mList = List<AdminUser>.from(managedUsers.value);
    final index = mList.indexWhere((u) => u.id == userId);
    if (index == -1) return false;

    mList.removeAt(index);
    managedUsers.value = mList;
    totalUsers = mList.length;
    return true;
  }
}
