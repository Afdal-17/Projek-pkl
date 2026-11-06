import 'api_config.dart';
import 'api_service.dart';

/// Service untuk pengelolaan transaksi melalui RESTful API backend Laravel
class TransactionService {
  TransactionService._();

  /// Mengambil daftar transaksi dari backend (`GET /api/transactions`)
  /// Response: paginated {data: [{id, name, type, category, wallet, to, amount, date, jenis, icon}]}
  static Future<ApiResponse> fetchTransactions({int page = 1}) async {
    return await ApiService.get('${ApiConfig.transactionsEndpoint}?page=$page');
  }

  /// Membuat transaksi baru (`POST /api/transactions`)
  static Future<ApiResponse> createTransaction({
    required String name,
    required double amount,
    required String type, // 'income' atau 'expense'
    required String category,
    required String walletName,
    required String date, // format: 'YYYY-MM-DD'
    String? note,
  }) async {
    final body = <String, dynamic>{
      'name': name,
      'amount': amount,
      'type': type,
      'category': category,
      'wallet': walletName,
      'date': date,
      if (note != null && note.isNotEmpty) 'note': note,
    };
    return await ApiService.post(ApiConfig.transactionsEndpoint, body: body);
  }
}

