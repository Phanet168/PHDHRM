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
          .map(normalizeBaseUrl)
          .whereType<String>()
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
        .map(normalizeBaseUrl)
        .whereType<String>()
        .toList(growable: false);

    if (parts.isEmpty) {
      return const <String>[_primaryServerBaseUrl, _primaryServerArtisanUrl];
    }

    return _dedupe(parts);
  }

  static String? normalizeBaseUrl(String raw) {
    final trimmed = raw.trim();
    if (trimmed.isEmpty) {
      return null;
    }

    final withScheme = RegExp(r'^https?://', caseSensitive: false).hasMatch(trimmed)
        ? trimmed
        : 'http://$trimmed';

    final parsed = Uri.tryParse(withScheme);
    if (parsed == null || parsed.host.trim().isEmpty) {
      return null;
    }

    final segments = parsed.pathSegments
        .where((segment) => segment.trim().isNotEmpty)
        .toList(growable: true);

    if (segments.isEmpty) {
      segments.add('api');
    } else {
      final lastSegment = segments.last.toLowerCase();
      if (lastSegment != 'api') {
        if (lastSegment == 'backend') {
          segments.add('api');
        } else {
          segments.add('api');
        }
      }
    }

    final normalized = parsed.replace(
      pathSegments: segments,
      query: null,
      fragment: null,
    ).toString();

    return normalized.endsWith('/')
        ? normalized.substring(0, normalized.length - 1)
        : normalized;
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
