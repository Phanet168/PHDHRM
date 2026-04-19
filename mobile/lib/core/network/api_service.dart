import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../storage/token_storage_service.dart';
import 'api_exception.dart';

class ApiService {
  ApiService({http.Client? client, TokenStorageService? tokenStorageService})
    : _client = client ?? http.Client(),
      _tokenStorageService = tokenStorageService ?? TokenStorageService();

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

    final uriCandidates = ApiConfig.buildUriCandidates(path, queryParameters);
    final attemptedUris = <String>[];
    ApiException? lastNetworkError;

    for (var i = 0; i < uriCandidates.length; i++) {
      final uri = uriCandidates[i];
      final isLastCandidate = i == uriCandidates.length - 1;
      attemptedUris.add(uri.toString());

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

        return _decodeResponse(response);
      } on TimeoutException {
        lastNetworkError = ApiException(
          message: 'Connection timeout. Tried: ${attemptedUris.join(' | ')}',
        );
        if (isLastCandidate) {
          throw lastNetworkError;
        }
      } on http.ClientException catch (error) {
        lastNetworkError = ApiException(
          message: '${error.message}. Tried: ${attemptedUris.join(' | ')}',
        );
        if (isLastCandidate) {
          throw lastNetworkError;
        }
      }
    }

    throw lastNetworkError ??
        ApiException(
          message: 'Request failed. Tried: ${attemptedUris.join(' | ')}',
        );
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
