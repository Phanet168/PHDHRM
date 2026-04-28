import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../storage/token_storage_service.dart';
import 'api_exception.dart';

class ApiService {
  ApiService({http.Client? client, TokenStorageService? tokenStorageService})
    : _client = client ?? http.Client(),
      _tokenStorageService = tokenStorageService ?? TokenStorageService();

  static String? _preferredBaseUrl;
  static final Map<String, int> _baseFailures = <String, int>{};
  static Future<String?>? _warmingBaseFuture;

  final http.Client _client;
  final TokenStorageService _tokenStorageService;

  static void resetRoutingState() {
    _preferredBaseUrl = null;
    _baseFailures.clear();
    _warmingBaseFuture = null;
  }

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
    bool requiresAuth = true,
    bool throwOnError = true,
  }) {
    return _send(
      method: 'GET',
      path: path,
      queryParameters: queryParameters,
      requiresAuth: requiresAuth,
      throwOnError: throwOnError,
    );
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
    bool throwOnError = true,
  }) {
    return _send(
      method: 'POST',
      path: path,
      body: body,
      requiresAuth: requiresAuth,
      throwOnError: throwOnError,
    );
  }

  Future<Map<String, dynamic>> put(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
    bool throwOnError = true,
  }) {
    return _send(
      method: 'PUT',
      path: path,
      body: body,
      requiresAuth: requiresAuth,
      throwOnError: throwOnError,
    );
  }

  Future<Map<String, dynamic>> delete(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
    bool throwOnError = true,
  }) {
    return _send(
      method: 'DELETE',
      path: path,
      body: body,
      requiresAuth: requiresAuth,
      throwOnError: throwOnError,
    );
  }

  Future<Map<String, dynamic>> _send({
    required String method,
    required String path,
    Map<String, dynamic>? queryParameters,
    Map<String, dynamic>? body,
    required bool requiresAuth,
    required bool throwOnError,
  }) async {
    final headers = await _buildHeaders(requiresAuth: requiresAuth);
    final totalWatch = Stopwatch()..start();

    final baseCandidates = _orderedBaseUrls(ApiConfig.baseUrls);
    await _warmPreferredBaseIfNeeded(baseCandidates, headers);
    final orderedCandidates = _orderedBaseUrls(ApiConfig.baseUrls);
    final attemptedUris = <String>[];
    ApiException? lastNetworkError;

    for (var i = 0; i < orderedCandidates.length; i++) {
      final base = orderedCandidates[i];
      final uri = ApiConfig.buildUriForBase(base, path, queryParameters);
      final isLastCandidate = i == orderedCandidates.length - 1;
      attemptedUris.add(uri.toString());
      final attemptWatch = Stopwatch()..start();
      final timeout = _timeoutForAttempt(base: base, attemptIndex: i);

      try {
        final http.Response? response;

        switch (method) {
          case 'GET':
            response = await _requestWithTimeout(
              _client.get(uri, headers: headers),
              timeout,
            );
          case 'POST':
            response = await _requestWithTimeout(
              _client.post(
                uri,
                headers: headers,
                body: jsonEncode(body ?? <String, dynamic>{}),
              ),
              timeout,
            );
          case 'PUT':
            response = await _requestWithTimeout(
              _client.put(
                uri,
                headers: headers,
                body: jsonEncode(body ?? <String, dynamic>{}),
              ),
              timeout,
            );
          case 'DELETE':
            response = await _requestWithTimeout(
              _client.delete(
                uri,
                headers: headers,
                body: jsonEncode(body ?? <String, dynamic>{}),
              ),
              timeout,
            );
          default:
            throw ApiException(message: 'Unsupported method: $method');
        }

        if (response == null) {
          attemptWatch.stop();
          _registerFailure(base);
          _perfLog(
            '$method $path timeout after '
            '${attemptWatch.elapsedMilliseconds}ms [base: $base]',
          );
          lastNetworkError = NetworkException();
          if (isLastCandidate) {
            totalWatch.stop();
            _perfLog(
              '$method $path failed in ${totalWatch.elapsedMilliseconds}ms '
              '[timeout after ${attemptedUris.length} attempt(s)]',
            );
            throw lastNetworkError;
          }
          continue;
        }

        final decodedResponse = _decodeResponse(
          response,
          requestUri: uri.toString(),
          throwOnError: throwOnError,
        );

        _registerSuccess(base);
        attemptWatch.stop();
        totalWatch.stop();
        _perfLog(
          '$method $path -> ${response.statusCode} in '
          '${attemptWatch.elapsedMilliseconds}ms '
          '(total ${totalWatch.elapsedMilliseconds}ms) '
          '[base: $base]',
        );

        return decodedResponse;
      } on ApiException catch (error) {
        attemptWatch.stop();

        final statusCode = error.statusCode ?? 0;
        final shouldTryNextBase =
            !isLastCandidate &&
            (statusCode == 404 || statusCode == 419 || statusCode >= 500);

        if (shouldTryNextBase) {
          _registerFailure(base);
          _perfLog(
            '$method $path api error ${error.statusCode} in '
            '${attemptWatch.elapsedMilliseconds}ms '
            '[base: $base, trying next base]',
          );
          lastNetworkError = ApiException(
            message: '${error.message}. Tried: ${attemptedUris.join(' | ')}',
            statusCode: error.statusCode,
          );
          continue;
        }

        _perfLog(
          '$method $path api error ${error.statusCode} in '
          '${attemptWatch.elapsedMilliseconds}ms [base: $base]',
        );
        rethrow;
      } on TimeoutException {
        attemptWatch.stop();
        _registerFailure(base);
        _perfLog(
          '$method $path timeout after '
          '${attemptWatch.elapsedMilliseconds}ms [base: $base]',
        );
        lastNetworkError = NetworkException();
        if (isLastCandidate) {
          totalWatch.stop();
          _perfLog(
            '$method $path failed in ${totalWatch.elapsedMilliseconds}ms '
            '[timeout after ${attemptedUris.length} attempt(s)]',
          );
          throw lastNetworkError;
        }
      } on http.ClientException catch (error) {
        attemptWatch.stop();
        _registerFailure(base);
        _perfLog(
          '$method $path client error in '
          '${attemptWatch.elapsedMilliseconds}ms '
          '[base: $base, ${error.message}]',
        );
        lastNetworkError = NetworkException();
        if (isLastCandidate) {
          totalWatch.stop();
          _perfLog(
            '$method $path failed in ${totalWatch.elapsedMilliseconds}ms '
            '[client error after ${attemptedUris.length} attempt(s)]',
          );
          throw lastNetworkError;
        }
      } catch (error) {
        attemptWatch.stop();
        if (!isNetworkErrorMessage(error.toString())) {
          rethrow;
        }

        _registerFailure(base);
        _perfLog(
          '$method $path network error in '
          '${attemptWatch.elapsedMilliseconds}ms [base: $base]',
        );
        lastNetworkError = NetworkException();
        if (isLastCandidate) {
          totalWatch.stop();
          _perfLog(
            '$method $path failed in ${totalWatch.elapsedMilliseconds}ms '
            '[network error after ${attemptedUris.length} attempt(s)]',
          );
          throw lastNetworkError;
        }
      }
    }

    totalWatch.stop();
    _perfLog(
      '$method $path failed in ${totalWatch.elapsedMilliseconds}ms '
      '[no successful candidate]',
    );

    throw lastNetworkError ?? NetworkException();
  }

  Duration _timeoutForAttempt({
    required String base,
    required int attemptIndex,
  }) {
    final isPreferred = _preferredBaseUrl == base;
    if (isPreferred) {
      return ApiConfig.connectTimeout;
    }

    if (attemptIndex <= 0) {
      return ApiConfig.connectTimeout;
    }

    return ApiConfig.fallbackConnectTimeout;
  }

  Future<http.Response?> _requestWithTimeout(
    Future<http.Response> request,
    Duration timeout,
  ) {
    return request
        .then<http.Response?>((response) => response)
        .timeout(timeout, onTimeout: () => null);
  }

  Future<void> _warmPreferredBaseIfNeeded(
    List<String> baseCandidates,
    Map<String, String> headers,
  ) async {
    if (_preferredBaseUrl != null || baseCandidates.length <= 1) {
      return;
    }

    final existingFuture = _warmingBaseFuture;
    if (existingFuture != null) {
      await existingFuture;
      return;
    }

    final warming = _runBaseWarmup(baseCandidates, headers);
    _warmingBaseFuture = warming;
    try {
      await warming;
    } finally {
      if (identical(_warmingBaseFuture, warming)) {
        _warmingBaseFuture = null;
      }
    }
  }

  Future<String?> _runBaseWarmup(
    List<String> baseCandidates,
    Map<String, String> headers,
  ) async {
    for (final base in baseCandidates) {
      // Probe a concrete API endpoint so generic 404 pages are not treated
      // as healthy API bases.
      final uri = ApiConfig.buildUriForBase(base, '/auth/profile');
      try {
        final response = await _client
            .get(uri, headers: headers)
            .then<http.Response?>((response) => response)
            .timeout(ApiConfig.warmupProbeTimeout, onTimeout: () => null);
        if (response == null) {
          _registerFailure(base);
          continue;
        }

        final code = response.statusCode;
        final isHealthy =
            (code >= 200 && code < 400) ||
            code == 401 ||
            code == 403 ||
            code == 405 ||
            code == 422;

        if (isHealthy) {
          _registerSuccess(base);
          _perfLog('Warmup selected base: $base (HTTP $code)');
          return base;
        }

        _registerFailure(base);
      } on http.ClientException {
        _registerFailure(base);
      }
    }

    return null;
  }

  List<String> _orderedBaseUrls(List<String> baseUrls) {
    if (baseUrls.length <= 1) {
      return baseUrls;
    }

    final ordered = List<String>.from(baseUrls);

    ordered.sort((a, b) {
      if (_preferredBaseUrl == a && _preferredBaseUrl != b) {
        return -1;
      }
      if (_preferredBaseUrl == b && _preferredBaseUrl != a) {
        return 1;
      }

      final failA = _baseFailures[a] ?? 0;
      final failB = _baseFailures[b] ?? 0;
      if (failA != failB) {
        return failA.compareTo(failB);
      }

      final idxA = baseUrls.indexOf(a);
      final idxB = baseUrls.indexOf(b);
      return idxA.compareTo(idxB);
    });

    return ordered;
  }

  void _registerSuccess(String base) {
    _preferredBaseUrl = base;
    _baseFailures[base] = 0;
  }

  void _registerFailure(String base) {
    final failures = (_baseFailures[base] ?? 0) + 1;
    _baseFailures[base] = failures;

    // If the preferred base fails repeatedly, allow fallback URLs to take over.
    if (_preferredBaseUrl == base && failures >= 2) {
      _preferredBaseUrl = null;
    }
  }

  void _perfLog(String message) {
    if (!kDebugMode) {
      return;
    }

    debugPrint('[ApiService][Perf] $message');
  }

  Future<Map<String, String>> _buildHeaders({
    required bool requiresAuth,
  }) async {
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };

    if (!requiresAuth) {
      return headers;
    }

    final token = await _tokenStorageService.readToken();
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }

    return headers;
  }

  Map<String, dynamic> _decodeResponse(
    http.Response response, {
    required String requestUri,
    required bool throwOnError,
  }) {
    final contentType = response.headers['content-type']?.toLowerCase() ?? '';
    final rawBody = response.body.trim();
    final responseBody = _tryDecodeMap(response.body);
    final isJsonResponse =
        contentType.contains('application/json') ||
        contentType.contains('+json');
    final isHtmlResponse = _looksLikeHtmlPayload(rawBody, contentType);

    if (!throwOnError &&
        (response.statusCode < 200 || response.statusCode >= 300)) {
      return responseBody;
    }

    if (response.statusCode == 401) {
      throw UnauthorizedException(
        message: _extractMessage(responseBody, fallback: 'Session expired'),
      );
    }

    if (response.statusCode == 419) {
      throw ApiException(
        message: _extractMessage(
          responseBody,
          fallback:
              'Session expired (419). Please verify API base URL is correct.',
        ),
        statusCode: response.statusCode,
      );
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(
        message: _extractMessage(
          responseBody,
          fallback: _buildHttpErrorMessage(
            statusCode: response.statusCode,
            requestUri: requestUri,
            isJsonResponse: isJsonResponse,
            isHtmlResponse: isHtmlResponse,
          ),
        ),
        statusCode: response.statusCode,
      );
    }

    final hasBody = rawBody.isNotEmpty;
    if (hasBody && responseBody.isEmpty && !isJsonResponse) {
      throw ApiException(
        message:
            'Unexpected non-JSON response from server. Please check API base URL. URL: $requestUri',
        statusCode: 502,
      );
    }

    return responseBody;
  }

  Map<String, dynamic> _tryDecodeMap(String body) {
    if (body.isEmpty) {
      return <String, dynamic>{};
    }

    final trimmed = body.trimLeft();
    if (!_looksLikeJsonPayload(trimmed)) {
      return <String, dynamic>{};
    }

    try {
      final decoded = jsonDecode(trimmed);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
      if (decoded is Map) {
        return decoded.map((key, value) => MapEntry(key.toString(), value));
      }
    } on FormatException {
      // Ignore parse errors for non-JSON responses such as HTML server errors.
    }

    return <String, dynamic>{};
  }

  bool _looksLikeJsonPayload(String body) {
    if (body.isEmpty) {
      return false;
    }

    return body.startsWith('{') || body.startsWith('[');
  }

  bool _looksLikeHtmlPayload(String body, String contentType) {
    if (contentType.contains('text/html')) {
      return true;
    }

    if (body.isEmpty) {
      return false;
    }

    final normalized = body.toLowerCase();
    return normalized.startsWith('<!doctype html') ||
        normalized.startsWith('<html') ||
        normalized.startsWith('<!doctype');
  }

  String _buildHttpErrorMessage({
    required int statusCode,
    required String requestUri,
    required bool isJsonResponse,
    required bool isHtmlResponse,
  }) {
    if (statusCode == 403) {
      return 'Permission denied';
    }

    if (statusCode == 404) {
      if (isHtmlResponse || !isJsonResponse) {
        return 'API endpoint not found (404). Please check API base URL or route. URL: $requestUri';
      }
      return 'Resource not found (404). URL: $requestUri';
    }

    if (statusCode >= 500) {
      if (isHtmlResponse || !isJsonResponse) {
        return 'Server returned an invalid response ($statusCode). Please check API base URL or backend route. URL: $requestUri';
      }
      return 'Server error ($statusCode)';
    }

    if (isHtmlResponse || !isJsonResponse) {
      return 'Unexpected server response ($statusCode). Please check API base URL. URL: $requestUri';
    }

    return 'Request failed ($statusCode)';
  }

  String _extractMessage(
    Map<String, dynamic> body, {
    String fallback = 'Request failed',
  }) {
    final response = body['response'];
    final candidates = <dynamic>[
      body['message'],
      body['error'],
      if (response is Map) response['message'],
      if (response is Map) response['error'],
    ];

    for (final candidate in candidates) {
      final normalized = _asNonEmptyString(candidate);
      if (normalized != null) {
        return normalized;
      }
    }

    final errors =
        body['errors'] ?? (response is Map ? response['errors'] : null);
    final firstValidationError = _extractFirstValidationMessage(errors);
    if (firstValidationError != null) {
      return firstValidationError;
    }

    return fallback;
  }

  String? _extractFirstValidationMessage(dynamic rawErrors) {
    if (rawErrors is List) {
      for (final entry in rawErrors) {
        final normalized = _asNonEmptyString(entry);
        if (normalized != null) {
          return normalized;
        }
      }

      return null;
    }

    if (rawErrors is Map) {
      for (final value in rawErrors.values) {
        final normalized = _extractFirstValidationMessage(value);
        if (normalized != null) {
          return normalized;
        }
      }
    }

    return null;
  }

  String? _asNonEmptyString(dynamic value) {
    if (value is String) {
      final trimmed = value.trim();
      if (trimmed.isNotEmpty) {
        return trimmed;
      }
    }

    return null;
  }
}
