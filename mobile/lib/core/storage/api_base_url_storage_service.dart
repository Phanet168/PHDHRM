import 'package:shared_preferences/shared_preferences.dart';

class ApiBaseUrlStorageService {
  static const String _baseUrlsKey = 'api_base_urls';

  Future<List<String>> readConfiguredBaseUrls() async {
    final prefs = await SharedPreferences.getInstance();
    final values = prefs.getStringList(_baseUrlsKey);
    if (values == null || values.isEmpty) {
      return const <String>[];
    }

    return values
        .map((value) => value.trim())
        .where((value) => value.isNotEmpty)
        .toList(growable: false);
  }

  Future<void> saveConfiguredBaseUrls(List<String> baseUrls) async {
    final prefs = await SharedPreferences.getInstance();
    final values = baseUrls
        .map((value) => value.trim())
        .where((value) => value.isNotEmpty)
        .toList(growable: false);

    if (values.isEmpty) {
      await prefs.remove(_baseUrlsKey);
      return;
    }

    await prefs.setStringList(_baseUrlsKey, values);
  }

  Future<void> clearConfiguredBaseUrls() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_baseUrlsKey);
  }
}