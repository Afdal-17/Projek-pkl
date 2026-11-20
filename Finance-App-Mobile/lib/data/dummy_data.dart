import '../models/money.dart';
import '../models/saving_goal.dart';
import '../models/transaction_item.dart';
import '../models/wallet.dart';
import '../services/api_config.dart';
import '../services/api_service.dart';
import '../utils/currency.dart';

/// ==========================================================
/// DATA DUMMY - HANYA UNTUK MEMBANGUN UI.
/// Nominal dasar finansial mengikuti kontrak backend: USD.
/// ==========================================================
class DummyData {
  /// URL Endpoint RESTful API Backend Laravel
  static String get apiBaseUrl => ApiConfig.baseUrl;
  DummyData._();

  static String userName = "User";
  static String userInitials = 'U';
  static String userEmail = 'user@finance.app';
  static String userPhone = '';
  static String userLocation = '';
  static dynamic userAvatarFile; // File? jika dipilih dari device

  /// Reset data profil user di memori saat logout / ganti akun
  static void reset() {
    userName = "User";
    userInitials = 'U';
    userEmail = 'user@finance.app';
    userPhone = '';
    userLocation = '';
    userAvatarFile = null;
  }

  static Map<String, double> _ratesFromUsd = <String, double>{'USD': 1.0};

  /// Mengambil kurs mata uang langsung secara real-time dari backend (/api/currency/rates)
  static Future<void> fetchLiveRates() async {
    try {
      final res = await ApiService.get(ApiConfig.currencyRatesEndpoint);
      if (res.success && res.data is Map) {
        final map = res.data as Map;
        final rates = map['data'] is Map ? map['data'] as Map : map;
        final newRates = <String, double>{'USD': 1.0};
        for (final entry in rates.entries) {
          final val = (entry.value as num?)?.toDouble();
          if (val != null && val > 0) {
            newRates[entry.key.toString().toUpperCase()] = val;
          }
        }
        _ratesFromUsd = newRates;
      }
    } catch (_) {}
  }

  /// Uses the backend's per-item conversion when present; fallback amounts are USD.
  static Money money(num usd, {dynamic apiMoney}) {
    final parsed = Money.tryParse(apiMoney);
    if (parsed != null) return parsed;

    final target = preferredCurrency.value;
    final rate = _ratesFromUsd[target];
    if (rate == null) {
      return Money(
        baseAmount: usd.toDouble(),
        baseCurrency: 'USD',
        displayAmount: usd.toDouble(),
        displayCurrency: 'USD',
        rate: const Rate(from: 'USD', to: 'USD', value: 1.0),
      );
    }

    return Money(
      baseAmount: usd.toDouble(),
      baseCurrency: 'USD',
      displayAmount: usd.toDouble() * rate,
      displayCurrency: target,
      rate: Rate(from: 'USD', to: target, value: rate),
    );
  }

  static List<Wallet> get wallets => const [];

  static const monthlyIncomeUsd = 0;
  static const monthlyExpenseUsd = 0;

  static List<TransactionItem> get transactions => const [];

  static final List<SavingGoal> savings = const [];
}
