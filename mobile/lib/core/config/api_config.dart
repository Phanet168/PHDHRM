import 'package:flutter/foundation.dart';

import '../storage/api_base_url_storage_service.dart';

class ApiConfig {
  static const String _primaryServerBaseUrl =
      'http://127.0.0.1/PHDHRM/backend/api';
  static const String _primaryServerArtisanUrl = 'http://127.0.0.1:8000/api';
  static const List<String> _androidLanBaseUrls = <String>[
    'http://phdhrm.local/PHDHRM/backend/api',
    'http://phdhrm.local:8000/api',
  ];
  static const List<String> _legacyAndroidLanBaseUrls = <String>[
    'http://192.168.1.4/PHDHRM/backend/api',
    'http://192.168.1.9/PHDHRM/backend/api',
    'http://192.168.1.7/PHDHRM/backend/api',
    'http://192.168.1.2/PHDHRM/backend/api',
  ];
  static const List<String> _androidEmulatorBaseUrls = <String>[
    'http://10.0.2.2/PHDHRM/backend/api',
    'http://10.0.2.2:8000/api',
  ];
  static final ApiBaseUrlStorageService _storageService =
      ApiBaseUrlStorageService();
  static List<String>? _storedBaseUrls;
  static String? _lastSuccessfulBaseUrl;

  static Future<void> initialize() async {
    final storedBaseUrls = await _storageService.readConfiguredBaseUrls();
    _lastSuccessfulBaseUrl = normalizeBaseUrl(
      await _storageService.readLastSuccessfulBaseUrl() ?? '',
    );
    if (storedBaseUrls.isEmpty) {
      _storedBaseUrls = null;
      return;
    }

    final normalizedStored = _normalizePersistedBaseUrls(storedBaseUrls);
    _storedBaseUrls = normalizedStored.isEmpty ? null : normalizedStored;

    if (!listEquals(storedBaseUrls, normalizedStored)) {
      await _storageService.saveConfiguredBaseUrls(normalizedStored);
    }
  }

  static bool get hasStoredBaseUrls {
    return _storedBaseUrls != null && _storedBaseUrls!.isNotEmpty;
  }

  static String? get lastSuccessfulBaseUrl => _lastSuccessfulBaseUrl;

  static String get baseUrl {
    return baseUrls.first;
  }

  static List<String> get configuredBaseUrls {
    final stored = _storedBaseUrls;
    if (stored != null && stored.isNotEmpty) {
      return List<String>.unmodifiable(stored);
    }

    const configured = String.fromEnvironment('API_BASE_URL');
    if (configured.trim().isNotEmpty) {
      return _dedupe(normalizeConfiguredBaseUrls(configured));
    }

    return const <String>[];
  }

  static List<String> get baseUrls {
    final lastSuccessful = _lastSuccessfulBaseUrl;
    final stored = _storedBaseUrls;
    if (stored != null && stored.isNotEmpty) {
      if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
        // For physical Android phones: use the last successful base first,
        // then explicit server settings, then stable local hostnames.
        return _androidCompatibleBaseUrls(<String>[
          if (lastSuccessful != null) lastSuccessful,
          ...stored,
          ..._androidLanBaseUrls,
          ..._androidEmulatorBaseUrls,
        ]);
      }

      return _dedupe(<String>[
        if (lastSuccessful != null) lastSuccessful,
        ...stored,
        _primaryServerBaseUrl,
        _primaryServerArtisanUrl,
      ]);
    }

    const configured = String.fromEnvironment('API_BASE_URL');
    if (configured.trim().isNotEmpty) {
      final configuredBaseUrls = normalizeConfiguredBaseUrls(configured);
      if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
        // For physical Android phones: prefer previous success, then configured,
        // then stable local hostnames, then emulator fallback.
        return _androidCompatibleBaseUrls(<String>[
          if (lastSuccessful != null) lastSuccessful,
          ...configuredBaseUrls,
          ..._androidLanBaseUrls,
          ..._androidEmulatorBaseUrls,
        ]);
      }

      return _dedupe(<String>[
        if (lastSuccessful != null) lastSuccessful,
        ...configuredBaseUrls,
        _primaryServerBaseUrl,
        _primaryServerArtisanUrl,
      ]);
    }

    if (kIsWeb) {
      final host = Uri.base.host.isEmpty ? '127.0.0.1' : Uri.base.host;
      return _dedupe(<String>[
        if (lastSuccessful != null) lastSuccessful,
        _primaryServerBaseUrl,
        _primaryServerArtisanUrl,
        'http://$host/PHDHRM/backend/api',
        'http://$host:8000/api',
        'http://127.0.0.1/PHDHRM/backend/api',
        'http://127.0.0.1:8000/api',
      ]);
    }

    if (defaultTargetPlatform == TargetPlatform.android) {
      // For physical Android phones during development:
      // 1. previous success, 2. stable local DNS, 3. emulator fallback.
      return _dedupe(<String>[
        if (lastSuccessful != null) lastSuccessful,
        ..._androidLanBaseUrls,
        'http://10.0.2.2/PHDHRM/backend/api',
        'http://10.0.2.2:8000/api',
      ]);
    }

    return _dedupe(const <String>[
      _primaryServerBaseUrl,
      _primaryServerArtisanUrl,
      'http://phdhrm.local/PHDHRM/backend/api',
      'http://phdhrm.local:8000/api',
      'http://127.0.0.1/PHDHRM/backend/api',
      'http://127.0.0.1:8000/api',
    ]);
  }

  static Future<void> saveConfiguredBaseUrls(List<String> values) async {
    final normalized = _dedupe(
      values.expand(_expandBaseUrlsFromInput).toList(growable: false),
    );

    _storedBaseUrls = normalized.isEmpty ? null : normalized;
    await _storageService.saveConfiguredBaseUrls(normalized);
  }

  static Future<void> clearConfiguredBaseUrls() async {
    _storedBaseUrls = null;
    await _storageService.clearConfiguredBaseUrls();
  }

  static Future<void> saveLastSuccessfulBaseUrl(String value) async {
    final normalized = normalizeBaseUrl(value);
    _lastSuccessfulBaseUrl = normalized;
    if (normalized == null) {
      await _storageService.clearLastSuccessfulBaseUrl();
      return;
    }

    await _storageService.saveLastSuccessfulBaseUrl(normalized);
  }

  static List<String> get fallbackLanDiscoveryBases {
    return _dedupe(<String>[
      ..._androidLanBaseUrls,
      ..._legacyAndroidLanBaseUrls,
    ]);
  }

  static const Duration connectTimeout = Duration(seconds: 4);
  static const Duration fallbackConnectTimeout = Duration(milliseconds: 1500);
  static const Duration warmupProbeTimeout = Duration(milliseconds: 1200);

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

    return _androidCompatibleBaseUrls(parts);
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

    final withScheme =
        RegExp(r'^https?://', caseSensitive: false).hasMatch(trimmed)
            ? trimmed
            : 'http://$trimmed';

    final parsed = Uri.tryParse(withScheme);
    if (parsed == null || parsed.host.trim().isEmpty) {
      return const <String>[];
    }

    final authority = _buildAuthority(parsed);
    if (authority == null) {
      return const <String>[];
    }
    final scheme = parsed.scheme.isEmpty ? 'http' : parsed.scheme;
    final pathSegments = parsed.pathSegments
        .where((segment) => segment.trim().isNotEmpty)
        .toList(growable: false);
    final normalizedPathSegments = _normalizePathSegments(pathSegments);

    final candidates = <String>[];

    if (normalizedPathSegments.isEmpty) {
      // Most operators enter only host/IP. Provide practical project defaults.
      candidates.add('$authority/PHDHRM/backend/api');
      candidates.add('$authority/api');
      if (!parsed.hasPort) {
        candidates.add('$scheme://${parsed.host}:8000/api');
      }
    } else {
      final joinedPath = '/${normalizedPathSegments.join('/')}';
      final normalizedPath =
          joinedPath.endsWith('/')
              ? joinedPath.substring(0, joinedPath.length - 1)
              : joinedPath;

      if (normalizedPathSegments.last.toLowerCase() == 'api') {
        // If operator entered host/api, also prioritize this project's
        // common Laravel base path before the generic /api endpoint.
        if (normalizedPathSegments.length == 1) {
          candidates.add('$authority/PHDHRM/backend/api');
          if (!parsed.hasPort) {
            candidates.add('$scheme://${parsed.host}:8000/api');
          }
        }
        candidates.add('$authority$normalizedPath');
      } else {
        candidates.add('$authority$normalizedPath/api');
      }

      final backendIndex = normalizedPathSegments.lastIndexWhere(
        (segment) => segment.toLowerCase() == 'backend',
      );
      if (backendIndex >= 0) {
        final backendPath =
            '/${normalizedPathSegments.sublist(0, backendIndex + 1).join('/')}';
        candidates.add('$authority$backendPath/api');
        if (!parsed.hasPort) {
          candidates.add('$scheme://${parsed.host}:8000/api');
        }
      }
    }

    return _dedupe(candidates);
  }

  static String? _buildAuthority(Uri parsed) {
    final host = parsed.host.trim();
    if (host.isEmpty) {
      return null;
    }

    final scheme = parsed.scheme.isEmpty ? 'http' : parsed.scheme;
    if (!parsed.hasPort) {
      return '$scheme://$host';
    }

    try {
      return '$scheme://$host:${parsed.port}';
    } on FormatException {
      // Invalid ports like ":abc" should be treated as invalid input.
      return null;
    }
  }

  static List<String> _normalizePathSegments(List<String> rawSegments) {
    final segments = List<String>.from(rawSegments);
    if (segments.isEmpty) {
      return segments;
    }

    // If user pastes a login endpoint URL, move back to a service base path.
    if (segments.last.toLowerCase() == 'login') {
      segments.removeLast();
      if (segments.isNotEmpty && segments.last.toLowerCase() == 'auth') {
        segments.removeLast();
      }
    }

    // Keep only the API base if user pasted a deeper API endpoint path.
    final apiIndex = segments.indexWhere(
      (segment) => segment.toLowerCase() == 'api',
    );
    if (apiIndex >= 0 && apiIndex < segments.length - 1) {
      return segments.sublist(0, apiIndex + 1);
    }

    return segments;
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

  static List<String> _androidCompatibleBaseUrls(List<String> values) {
    final normalized = _dedupe(values);
    if (kIsWeb || defaultTargetPlatform != TargetPlatform.android) {
      return normalized;
    }

    return normalized
        .where((value) {
          final uri = Uri.tryParse(value);
          final host = uri?.host.trim().toLowerCase() ?? '';
          return !_isAndroidUnresolvableHost(host);
        })
        .toList(growable: false);
  }

  static bool _isAndroidUnresolvableHost(String host) {
    // On Android, filter out localhost/127.0.0.1 which may be unreliable.
    // Allow 192.168.x.x (LAN IPs), 10.x.x.x (emulator), and .local DNS domains as fallback.
    return host == 'localhost' || host == '127.0.0.1' || host == '::1';
  }

  static List<String> _normalizePersistedBaseUrls(List<String> values) {
    final expanded = <String>[];

    for (final raw in values) {
      final trimmed = raw.trim();
      if (trimmed.isEmpty) {
        continue;
      }

      final withScheme =
          RegExp(r'^https?://', caseSensitive: false).hasMatch(trimmed)
              ? trimmed
              : 'http://$trimmed';
      final parsed = Uri.tryParse(withScheme);

      if (parsed != null && parsed.host.trim().isNotEmpty) {
        final lowerPath = parsed.path.toLowerCase();
        final apiMarker = lowerPath.indexOf('/api/');
        if (apiMarker >= 0) {
          final authority = _buildAuthority(parsed);
          if (authority != null) {
            final apiPath = parsed.path.substring(0, apiMarker + 4);
            expanded.add('$authority$apiPath');
            continue;
          }
        }
      }

      expanded.addAll(_expandBaseUrlsFromInput(trimmed));
    }

    return _dedupe(expanded);
  }

  const ApiConfig._();
}
