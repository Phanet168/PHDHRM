import 'package:flutter/foundation.dart';

import '../storage/api_base_url_storage_service.dart';

class ApiConfig {
  static const String _primaryServerBaseUrl =
      'http://phdhrm.local/PHDHRM/backend/api';
  static const String _primaryServerArtisanUrl = 'http://phdhrm.local:8000/api';
  static final ApiBaseUrlStorageService _storageService =
      ApiBaseUrlStorageService();
  static List<String>? _storedBaseUrls;

  static Future<void> initialize() async {
    final storedBaseUrls = await _storageService.readConfiguredBaseUrls();
    _storedBaseUrls = storedBaseUrls.isEmpty ? null : _dedupe(storedBaseUrls);
  }

  static bool get hasStoredBaseUrls {
    return _storedBaseUrls != null && _storedBaseUrls!.isNotEmpty;
  }

  static String get baseUrl {
    return baseUrls.first;
  }

  static List<String> get baseUrls {
    final stored = _storedBaseUrls;
    if (stored != null && stored.isNotEmpty) {
      return stored;
    }

    const configured = String.fromEnvironment('API_BASE_URL');
    if (configured.trim().isNotEmpty) {
      return normalizeConfiguredBaseUrls(configured);
    }

    if (kIsWeb) {
      final host = Uri.base.host.isEmpty ? '127.0.0.1' : Uri.base.host;
      return _dedupe(<String>[
        _primaryServerBaseUrl,
        _primaryServerArtisanUrl,
        'http://$host/PHDHRM/backend/api',
        'http://$host:8000/api',
        'http://127.0.0.1/PHDHRM/backend/api',
        'http://127.0.0.1:8000/api',
      ]);
    }

    if (defaultTargetPlatform == TargetPlatform.android) {
      return _dedupe(const <String>[
        _primaryServerBaseUrl,
        _primaryServerArtisanUrl,
        'http://10.0.2.2/PHDHRM/backend/api',
        'http://10.0.2.2:8000/api',
      ]);
    }

    return _dedupe(const <String>[
      _primaryServerBaseUrl,
      _primaryServerArtisanUrl,
      'http://127.0.0.1/PHDHRM/backend/api',
      'http://127.0.0.1:8000/api',
    ]);
  }

  static Future<void> saveConfiguredBaseUrls(List<String> values) async {
    final normalized = _dedupe(
      values
          .expand(_expandBaseUrlsFromInput)
          .toList(growable: false),
    );

    _storedBaseUrls = normalized.isEmpty ? null : normalized;
    await _storageService.saveConfiguredBaseUrls(normalized);
  }

  static Future<void> clearConfiguredBaseUrls() async {
    _storedBaseUrls = null;
    await _storageService.clearConfiguredBaseUrls();
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

  static List<String> normalizeConfiguredBaseUrls(String raw) {
    final parts = raw
        .split(RegExp(r'[,;\n]'))
        .expand(_expandBaseUrlsFromInput)
        .toList(growable: false);

    if (parts.isEmpty) {
      return const <String>[_primaryServerBaseUrl, _primaryServerArtisanUrl];
    }

    return _dedupe(parts);
  }

  static String? normalizeBaseUrl(String raw) {
    final expanded = _expandBaseUrlsFromInput(raw);
    if (expanded.isEmpty) {
      return null;
    }

    return expanded.first;
  }

  static List<String> _expandBaseUrlsFromInput(String raw) {
    final trimmed = raw.trim();
    if (trimmed.isEmpty) {
      return const <String>[];
    }

    final withScheme = RegExp(r'^https?://', caseSensitive: false).hasMatch(trimmed)
        ? trimmed
        : 'http://$trimmed';

    final parsed = Uri.tryParse(withScheme);
    if (parsed == null || parsed.host.trim().isEmpty) {
      return const <String>[];
    }

    final scheme = parsed.scheme.isEmpty ? 'http' : parsed.scheme;
    final authority = parsed.hasPort
        ? '$scheme://${parsed.host}:${parsed.port}'
        : '$scheme://${parsed.host}';
    final pathSegments = parsed.pathSegments
        .where((segment) => segment.trim().isNotEmpty)
        .toList(growable: false);

    final candidates = <String>[];

    if (pathSegments.isEmpty) {
      // Most operators enter only host/IP. Provide practical project defaults.
      candidates.add('$authority/PHDHRM/backend/api');
      candidates.add('$authority/api');
      if (!parsed.hasPort) {
        candidates.add('$scheme://${parsed.host}:8000/api');
      }
    } else {
      final joinedPath = '/${pathSegments.join('/')}';
      final normalizedPath = joinedPath.endsWith('/')
          ? joinedPath.substring(0, joinedPath.length - 1)
          : joinedPath;

      if (pathSegments.last.toLowerCase() == 'api') {
        candidates.add('$authority$normalizedPath');
      } else {
        candidates.add('$authority$normalizedPath/api');
      }

      if (!parsed.hasPort &&
          pathSegments.length >= 2 &&
          pathSegments[pathSegments.length - 2].toLowerCase() == 'backend') {
        candidates.add('$scheme://${parsed.host}:8000/api');
      }
    }

    return _dedupe(candidates);
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
