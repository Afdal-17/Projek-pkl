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
  /// Backend mungkin belum punya route ini — handle 404/405 secara graceful
  static Future<ApiResponse> createCategory({
    required String name,
    required String type, // 'income' atau 'expense'
    String? icon,
  }) async {
    final body = <String, dynamic>{
      'name': name,
      'type': type,
      if (icon != null) 'icon': icon,
    };
    return await ApiService.post(_categoriesEndpoint, body: body);
  }

  /// Edit kategori (`PUT /api/categories/{id}`)
  /// Backend mungkin belum punya route ini — handle secara graceful
  static Future<ApiResponse> updateCategory(
    dynamic id, {
    String? name,
    String? type,
    String? icon,
  }) async {
    final body = <String, dynamic>{
      if (name != null) 'name': name,
      if (type != null) 'type': type,
      if (icon != null) 'icon': icon,
    };
    return await ApiService.patch('$_categoriesEndpoint/$id', body: body);
  }

  /// Hapus kategori (`DELETE /api/categories/{id}`)
  /// Backend mungkin belum punya route ini — handle secara graceful
  static Future<ApiResponse> deleteCategory(dynamic id) async {
    return await ApiService.get('$_categoriesEndpoint/$id/delete');
  }
}

