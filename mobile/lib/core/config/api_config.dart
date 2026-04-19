import 'package:flutter/foundation.dart';

class ApiConfig {
  static String get baseUrl {
    return baseUrls.first;
  }

  static List<String> get baseUrls {
    const configured = String.fromEnvironment('API_BASE_URL');
    if (configured.trim().isNotEmpty) {
      return _splitConfiguredBaseUrls(configured);
    }

    if (kIsWeb) {
      final host = Uri.base.host.isEmpty ? '127.0.0.1' : Uri.base.host;
      return _dedupe(<String>[
        'http://$host/PHDHRM/backend/api',
        'http://$host:8000/api',
        'http://192.168.1.14/PHDHRM/backend/api',
        'http://192.168.1.14:8000/api',
        'http://127.0.0.1/PHDHRM/backend/api',
        'http://127.0.0.1:8000/api',
      ]);
    }

    if (defaultTargetPlatform == TargetPlatform.android) {
      return _dedupe(const <String>[
        'http://10.0.2.2/PHDHRM/backend/api',
        'http://10.0.2.2:8000/api',
        'http://192.168.1.14/PHDHRM/backend/api',
        'http://192.168.1.14:8000/api',
      ]);
    }

    return _dedupe(const <String>[
      'http://192.168.1.14/PHDHRM/backend/api',
      'http://192.168.1.14:8000/api',
      'http://127.0.0.1/PHDHRM/backend/api',
      'http://127.0.0.1:8000/api',
    ]);
  }

  static const Duration connectTimeout = Duration(seconds: 12);

  static Uri buildUri(String path, [Map<String, dynamic>? queryParameters]) {
    return buildUriForBase(baseUrl, path, queryParameters);
  }

  static List<Uri> buildUriCandidates(
    String path, [
    Map<String, dynamic>? queryParameters,
  ]) {
    return baseUrls
        .map((base) => buildUriForBase(base, path, queryParameters))
        .toList(growable: false);
  }

  static Uri buildUriForBase(
    String base,
    String path, [
    Map<String, dynamic>? queryParameters,
  ]) {
    final normalizedBase =
        base.endsWith('/') ? base.substring(0, base.length - 1) : base;
    final normalizedPath = path.startsWith('/') ? path : '/$path';

    return Uri.parse('$normalizedBase$normalizedPath').replace(
      queryParameters: queryParameters?.map(
        (key, value) => MapEntry(key, value?.toString()),
      ),
    );
  }

  static List<String> _splitConfiguredBaseUrls(String raw) {
    final parts = raw
        .split(RegExp(r'[,;\n]'))
        .map((item) => item.trim())
        .where((item) => item.isNotEmpty)
        .toList(growable: false);

    if (parts.isEmpty) {
      return const <String>['http://10.0.2.2/PHDHRM/backend/api'];
    }

    return _dedupe(parts);
  }

  static List<String> _dedupe(List<String> values) {
    final seen = <String>{};
    final result = <String>[];

    for (final raw in values) {
      final value = raw.trim();
      if (value.isEmpty) {
        continue;
      }

      final normalized =
          value.endsWith('/') ? value.substring(0, value.length - 1) : value;
      if (seen.add(normalized)) {
        result.add(normalized);
      }
    }

    return result;
  }

  const ApiConfig._();
}
