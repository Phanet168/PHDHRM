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
    final uri = ApiConfig.buildUri(path, queryParameters);
    final headers = await _buildHeaders(requiresAuth: requiresAuth);

    late final http.Response response;

    try {
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
    } on TimeoutException {
      throw ApiException(message: 'ការភ្ជាប់ទៅ server អស់ពេលកំណត់');
    } on http.ClientException catch (error) {
      throw ApiException(message: error.message);
    }

    return _decodeResponse(response);
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
        message: _extractMessage(responseBody, fallback: 'Session ផុតសុពលភាព'),
      );
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(
        message: _extractMessage(
          responseBody,
          fallback:
              response.statusCode >= 500
                  ? 'Server error (${response.statusCode})'
                  : 'សំណើមិនបានជោគជ័យ',
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
    String fallback = 'សំណើមិនបានជោគជ័យ',
  }) {
    final message = body['message'] ?? body['error'];
    if (message is String && message.trim().isNotEmpty) {
      return message;
    }

    return fallback;
  }
}
