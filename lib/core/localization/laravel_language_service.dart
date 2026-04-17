import '../network/api_service.dart';

class LaravelLanguageService {
  LaravelLanguageService._();

  static final LaravelLanguageService instance = LaravelLanguageService._();

  final ApiService _apiService = ApiService();
  Map<String, String>? _cache;
  Future<Map<String, String>>? _inFlight;

  Future<Map<String, String>> load({bool forceRefresh = false}) {
    if (!forceRefresh && _cache != null) {
      return Future<Map<String, String>>.value(_cache);
    }

    final pending = forceRefresh ? null : _inFlight;
    if (pending != null) {
      return pending;
    }

    if (forceRefresh) {
      _cache = null;
    }

    _inFlight = _fetch();
    return _inFlight!;
  }

  Future<Map<String, String>> _fetch() async {
    try {
      final raw = await _apiService.get('/language', requiresAuth: false);
      final response = raw['response'];
      if (response is Map<String, dynamic>) {
        final data = response.map(
          (key, value) => MapEntry(key, value?.toString() ?? ''),
        );
        _cache = data;
        return data;
      }
    } catch (_) {
      // Ignore network/parser issues and keep UI usable with fallback text.
    } finally {
      _inFlight = null;
    }

    _cache = <String, String>{};
    return _cache!;
  }
}
