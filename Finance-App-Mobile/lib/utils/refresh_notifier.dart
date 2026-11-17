import 'dart:async';
import 'package:flutter/foundation.dart';

/// Event bus reaktif untuk memicu pembaruan data otomatis di seluruh layar aplikasi
/// tanpa mengharuskan pengguna melakukan pull-to-refresh secara manual.
class AppRefreshNotifier {
  AppRefreshNotifier._();

  /// Counter tick yang memicu listener saat nilainya bertambah
  static final ValueNotifier<int> refreshTick = ValueNotifier<int>(0);

  static Timer? _debounceTimer;

  /// Panggil fungsi ini setiap kali ada perubahan data (transaksi, transfer, dompet, target tabungan).
  /// Dilengkapi debounce 300ms agar pemanggilan beruntun tidak membanjiri server (request storm).
  static void notifyDataChanged({bool immediate = false}) {
    if (immediate) {
      _debounceTimer?.cancel();
      refreshTick.value++;
      return;
    }
    _debounceTimer?.cancel();
    _debounceTimer = Timer(const Duration(milliseconds: 300), () {
      refreshTick.value++;
    });
  }
}
