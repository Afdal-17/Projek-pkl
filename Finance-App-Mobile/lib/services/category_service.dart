import 'api_config.dart';
import 'api_service.dart';

/// Service untuk pengelolaan kategori melalui RESTful API backend Laravel
class CategoryService {
  CategoryService._();

  static const String _categoriesEndpoint = '${ApiConfig.baseUrl}/categories';

  /// Mengambil semua kategori dari backend (`GET /api/categories`)
  /// Response: [{id, name, type, icon, count}]
  static Future<ApiResponse> fetchCategories() async {
    return await ApiService.get(_categoriesEndpoint);
  }

  /// Tambah kategori baru (`POST /api/categories`)
  /// Backend Laravel StoreKategoriRequest mengharuskan:
  /// - nama_kategori (string)
  /// - jenis ('pemasukan' / 'pengeluaran')
  /// - icon (nullable string)
  static Future<ApiResponse> createCategory({
    required String name,
    required String type, // 'income' atau 'expense'
    String? icon,
  }) async {
    final jenis = (type.toLowerCase() == 'income' || type.toLowerCase() == 'pemasukan')
        ? 'pemasukan'
        : 'pengeluaran';

    final body = <String, dynamic>{
      'nama_kategori': name,
      'jenis': jenis,
      if (icon != null && icon.isNotEmpty) 'icon': icon,
    };
    return await ApiService.post(_categoriesEndpoint, body: body);
  }

  /// Edit kategori (`PATCH /api/categories/{id}`)
  static Future<ApiResponse> updateCategory(
    dynamic id, {
    String? name,
    String? type,
    String? icon,
  }) async {
    final body = <String, dynamic>{};
    if (name != null) body['nama_kategori'] = name;
    if (type != null) {
      body['jenis'] = (type.toLowerCase() == 'income' || type.toLowerCase() == 'pemasukan')
          ? 'pemasukan'
          : 'pengeluaran';
    }
    if (icon != null) body['icon'] = icon;

    return await ApiService.patch('$_categoriesEndpoint/$id', body: body);
  }

  /// Hapus kategori (`DELETE /api/categories/{id}`)
  static Future<ApiResponse> deleteCategory(dynamic id) async {
    return await ApiService.delete('$_categoriesEndpoint/$id');
  }
}
