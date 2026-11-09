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
  /// StoreTransaksiRequest Laravel mengharuskan:
  /// - id_dompet (integer)
  /// - id_kategori (integer)
  /// - nama_transaksi (string)
  /// - jumlah (numeric)
  /// - jenis ('pemasukan' / 'pengeluaran')
  /// - tanggal ('YYYY-MM-DD')
  static Future<ApiResponse> createTransaction({
    required dynamic walletId,
    required dynamic categoryId,
    required String name,
    required double amount,
    required String type, // 'income'/'pemasukan' atau 'expense'/'pengeluaran'
    required String date, // format: 'YYYY-MM-DD'
    String? note,
  }) async {
    final jenis = (type.toLowerCase() == 'income' || type.toLowerCase() == 'pemasukan')
        ? 'pemasukan'
        : 'pengeluaran';

    final body = <String, dynamic>{
      'id_dompet': int.tryParse(walletId.toString()) ?? walletId,
      'id_kategori': int.tryParse(categoryId.toString()) ?? categoryId,
      'nama_transaksi': name,
      'jumlah': amount,
      'jenis': jenis,
      'tanggal': date,
    };
    return await ApiService.post(ApiConfig.transactionsEndpoint, body: body);
  }

  /// Hapus transaksi (`DELETE /api/transactions/{id}`)
  static Future<ApiResponse> deleteTransaction(dynamic id) async {
    return await ApiService.delete('${ApiConfig.transactionsEndpoint}/$id');
  }
}
