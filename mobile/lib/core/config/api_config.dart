import 'package:flutter/foundation.dart';

class ApiConfig {
  static String get baseUrl {
    return baseUrls.first;
  }

  static List<String> get baseUrls {
    const configured = String.fromEnvironment('API_BASE_URL');
    if (configured.trim().isNotEmpty) {
      return <String>[configured.trim()];
    }

    if (kIsWeb) {
      final host = Uri.base.host.isEmpty ? '127.0.0.1' : Uri.base.host;
      return <String>[
        'http://192.168.1.14:8000/api',
        'http://$host:8000/api',
        'http://127.0.0.1:8000/api',
      ];
    }

    if (defaultTargetPlatform == TargetPlatform.android) {
      return const <String>[
        'http://10.0.2.2:8000/api',
        'http://192.168.1.14:8000/api',
        'http://127.0.0.1:8000/api',
      ];
    }

    return const <String>[
      'http://192.168.1.14:8000/api',
      'http://127.0.0.1:8000/api',
    ];
  }

  static const Duration connectTimeout = Duration(seconds: 30);

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
    final normalizedPath = path.startsWith('/') ? path : '/$path';

    return Uri.parse('$base$normalizedPath').replace(
      queryParameters: queryParameters?.map(
        (key, value) => MapEntry(key, value?.toString()),
      ),
    );
  }

  const ApiConfig._();
}
