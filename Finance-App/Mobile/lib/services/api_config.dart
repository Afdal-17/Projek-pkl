/// Konfigurasi URL dan Endpoint REST API Finance App
class ApiConfig {
  ApiConfig._();

  /// URL Tunggal API Backend Ngrok
  static const String baseUrl = 'https://sinuous-fame-jazz.ngrok-free.dev/api';

  /// Header khusus untuk bypass halaman konfirmasi ngrok free tier
  static const String ngrokHeaderKey = 'ngrok-skip-browser-warning';
  static const String ngrokHeaderValue = 'true';

  // ─── Endpoints ───────────────────────────────────────────────
  // Autentikasi
  static const String loginEndpoint = '$baseUrl/login';
  static const String userEndpoint = '$baseUrl/user';

  // Dompet
  static const String dompetEndpoint = '$baseUrl/dompet';
  static String toggleTransferEndpoint(dynamic id) => '$baseUrl/dompet/$id/toggle-transfer';
  static String manageDompetEndpoint(dynamic id) => '$baseUrl/dompet/$id/manage';

  // Transfer Antar User
  static const String searchRecipientsEndpoint = '$baseUrl/transfer/recipients/search';
  static const String transferEndpoint = '$baseUrl/transfer';

  // Transaksi & Tabungan
  static const String transactionsEndpoint = '$baseUrl/transactions';
  static const String savingEndpoint = '$baseUrl/saving';

  /// Header standar untuk setiap request API
  static Map<String, String> get defaultHeaders => {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ngrokHeaderKey: ngrokHeaderValue,
  };

  /// Header terotentikasi dengan Bearer Token
  static Map<String, String> authHeaders(String token) => {
    ...defaultHeaders,
    'Authorization': 'Bearer $token',
  };
}
