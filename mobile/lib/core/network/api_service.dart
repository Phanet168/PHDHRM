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

  final http.Client _client;
  final TokenStorageService _tokenStorageService;

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
    bool requiresAuth = true,
  }) {
    return _send(
      method: 'GET',
      path: path,
      queryParameters: queryParameters,
      requiresAuth: requiresAuth,
    );
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
  }) {
    return _send(
      method: 'POST',
      path: path,
      body: body,
      requiresAuth: requiresAuth,
    );
  }

  Future<Map<String, dynamic>> put(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
  }) {
    return _send(
      method: 'PUT',
      path: path,
      body: body,
      requiresAuth: requiresAuth,
    );
  }

  Future<Map<String, dynamic>> delete(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
  }) {
    return _send(
      method: 'DELETE',
      path: path,
      body: body,
      requiresAuth: requiresAuth,
    );
  }

  Future<Map<String, dynamic>> _send({
    required String method,
    required String path,
    Map<String, dynamic>? queryParameters,
    Map<String, dynamic>? body,
    required bool requiresAuth,
  }) async {
    final headers = await _buildHeaders(requiresAuth: requiresAuth);
    final totalWatch = Stopwatch()..start();

    final baseCandidates = _orderedBaseUrls(ApiConfig.baseUrls);
    final attemptedUris = <String>[];
    ApiException? lastNetworkError;

    for (var i = 0; i < baseCandidates.length; i++) {
      final base = baseCandidates[i];
      final uri = ApiConfig.buildUriForBase(base, path, queryParameters);
      final isLastCandidate = i == baseCandidates.length - 1;
      attemptedUris.add(uri.toString());
      final attemptWatch = Stopwatch()..start();

      try {
        late final http.Response response;

        switch (method) {
          case 'GET':
            response = await _client
                .get(uri, headers: headers)
                .timeout(ApiConfig.connectTimeout);
          case 'POST':
            response = await _client
                .post(
                  uri,
                  headers: headers,
                  body: jsonEncode(body ?? <String, dynamic>{}),
                )
                .timeout(ApiConfig.connectTimeout);
          case 'PUT':
            response = await _client
                .put(
                  uri,
                  headers: headers,
                  body: jsonEncode(body ?? <String, dynamic>{}),
                )
                .timeout(ApiConfig.connectTimeout);
          case 'DELETE':
            response = await _client
                .delete(
                  uri,
                  headers: headers,
                  body: jsonEncode(body ?? <String, dynamic>{}),
                )
                .timeout(ApiConfig.connectTimeout);
          default:
            throw ApiException(message: 'Unsupported method: $method');
        }

        _registerSuccess(base);
        attemptWatch.stop();
        totalWatch.stop();
        _perfLog(
          '$method $path -> ${response.statusCode} in '
          '${attemptWatch.elapsedMilliseconds}ms '
          '(total ${totalWatch.elapsedMilliseconds}ms) '
          '[base: $base]'
        );

        return _decodeResponse(response);
      } on TimeoutException {
        attemptWatch.stop();
        _registerFailure(base);
        _perfLog(
          '$method $path timeout after '
          '${attemptWatch.elapsedMilliseconds}ms [base: $base]'
        );
        lastNetworkError = ApiException(
          message: 'Connection timeout. Tried: ${attemptedUris.join(' | ')}',
        );
        if (isLastCandidate) {
          totalWatch.stop();
          _perfLog(
            '$method $path failed in ${totalWatch.elapsedMilliseconds}ms '
            '[timeout after ${attemptedUris.length} attempt(s)]'
          );
          throw lastNetworkError;
        }
      } on http.ClientException catch (error) {
        attemptWatch.stop();
        _registerFailure(base);
        _perfLog(
          '$method $path client error in '
          '${attemptWatch.elapsedMilliseconds}ms '
          '[base: $base, ${error.message}]'
        );
        lastNetworkError = ApiException(
          message: '${error.message}. Tried: ${attemptedUris.join(' | ')}',
        );
        if (isLastCandidate) {
          totalWatch.stop();
          _perfLog(
            '$method $path failed in ${totalWatch.elapsedMilliseconds}ms '
            '[client error after ${attemptedUris.length} attempt(s)]'
          );
          throw lastNetworkError;
        }
      }
    }

    totalWatch.stop();
    _perfLog(
      '$method $path failed in ${totalWatch.elapsedMilliseconds}ms '
      '[no successful candidate]'
    );

    throw lastNetworkError ??
        ApiException(
          message: 'Request failed. Tried: ${attemptedUris.join(' | ')}',
        );
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

  Map<String, dynamic> _decodeResponse(http.Response response) {
    final responseBody = _tryDecodeMap(response.body);

    if (response.statusCode == 401) {
      throw UnauthorizedException(
        message: _extractMessage(responseBody, fallback: 'Session expired'),
      );
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(
        message: _extractMessage(
          responseBody,
          fallback:
              response.statusCode >= 500
                  ? 'Server error (${response.statusCode})'
                  : 'Request failed',
        ),
        statusCode: response.statusCode,
      );
    }

    return responseBody;
  }

  Map<String, dynamic> _tryDecodeMap(String body) {
    if (body.isEmpty) {
      return <String, dynamic>{};
    }

    try {
      final decoded = jsonDecode(body);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
    } on FormatException {
      // Ignore parse errors for non-JSON responses such as HTML server errors.
    }

    return <String, dynamic>{};
  }

  String _extractMessage(
    Map<String, dynamic> body, {
    String fallback = 'Request failed',
  }) {
    final message = body['message'] ?? body['error'];
    if (message is String && message.trim().isNotEmpty) {
      return message;
    }

    return fallback;
  }
}
