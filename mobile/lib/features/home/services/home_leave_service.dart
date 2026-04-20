import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;

import '../../../core/config/api_config.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/api_service.dart';
import '../../../core/storage/token_storage_service.dart';
import '../../auth/models/auth_user.dart';
import '../models/leave_request_models.dart';

class HomeLeaveService {
  HomeLeaveService({ApiService? apiService})
    : _apiService = apiService ?? ApiService();

  final ApiService _apiService;
  final TokenStorageService _tokenStorageService = TokenStorageService();

  Future<List<LeaveTypeOption>> fetchTypes(AuthUser user) async {
    _ensureSession(user);

    final response = _responseMap(await _apiService.get('/v1/leave-types'));
    final status = (response['status'] ?? '').toString().toLowerCase();
    if (status != 'ok') {
      return <LeaveTypeOption>[];
    }

    final payload = response['data'];
    if (payload is! List) {
      return <LeaveTypeOption>[];
    }

    final rows = <LeaveTypeOption>[];
    for (final item in payload) {
      if (item is Map<String, dynamic>) {
        rows.add(LeaveTypeOption.fromMap(item));
      } else if (item is Map) {
        rows.add(
          LeaveTypeOption.fromMap(
            item.map((key, value) => MapEntry(key.toString(), value)),
          ),
        );
      }
    }

    return rows;
  }

  Future<LeaveSummary> fetchSummary(AuthUser user) async {
    _ensureSession(user);

    final response = _responseMap(await _apiService.get('/v1/leave-requests/summary'));
    final status = (response['status'] ?? '').toString().toLowerCase();
    if (status != 'ok') {
      return const LeaveSummary(totalRemaining: 0, types: <LeaveBalanceItem>[]);
    }

    final payload = response['data'];
    if (payload is! Map<String, dynamic>) {
      return const LeaveSummary(totalRemaining: 0, types: <LeaveBalanceItem>[]);
    }

    return LeaveSummary.fromMap(payload);
  }

  Future<List<LeaveRequestItem>> fetchRequests(AuthUser user) async {
    _ensureSession(user);

    final response = _responseMap(await _apiService.get('/v1/leave-requests'));
    final status = (response['status'] ?? '').toString().toLowerCase();
    if (status != 'ok') {
      return <LeaveRequestItem>[];
    }

    final payload = response['data'];
    List<dynamic> rows = <dynamic>[];

    if (payload is List<dynamic>) {
      rows = payload;
    } else if (payload is Map<String, dynamic> && payload['data'] is List) {
      rows = (payload['data'] as List).cast<dynamic>();
    }

    final requests = <LeaveRequestItem>[];
    for (final row in rows) {
      if (row is Map<String, dynamic>) {
        requests.add(LeaveRequestItem.fromMap(row));
      } else if (row is Map) {
        requests.add(
          LeaveRequestItem.fromMap(
            row.map((key, value) => MapEntry(key.toString(), value)),
          ),
        );
      }
    }

    return requests;
  }

  Future<void> submitRequest({
    required AuthUser user,
    required int leaveTypeId,
    required DateTime startDate,
    required DateTime endDate,
    required String reason,
    String? attachmentPath,
    Uint8List? attachmentBytes,
    String? attachmentName,
  }) async {
    _ensureSession(user);

    final hasAttachment =
        (attachmentPath != null && attachmentPath.trim().isNotEmpty) ||
        (attachmentBytes != null && attachmentBytes.isNotEmpty);

    if (!hasAttachment) {
      await _apiService.post(
        '/v1/leave-requests',
        body: <String, dynamic>{
          'leave_type_id': leaveTypeId,
          'start_date': _formatDateOnly(startDate),
          'end_date': _formatDateOnly(endDate),
          'reason': reason.trim(),
        },
      );
      return;
    }

    await _submitMultipartRequest(
      leaveTypeId: leaveTypeId,
      startDate: startDate,
      endDate: endDate,
      reason: reason,
      attachmentPath: attachmentPath,
      attachmentBytes: attachmentBytes,
      attachmentName: attachmentName,
    );
  }

  Future<void> _submitMultipartRequest({
    required int leaveTypeId,
    required DateTime startDate,
    required DateTime endDate,
    required String reason,
    String? attachmentPath,
    Uint8List? attachmentBytes,
    String? attachmentName,
  }) async {
    final token = await _tokenStorageService.readToken();
    ApiException? lastError;

    for (final base in ApiConfig.baseUrls) {
      final uri = ApiConfig.buildUriForBase(base, '/v1/leave-requests');
      final request = http.MultipartRequest('POST', uri)
        ..headers['Accept'] = 'application/json'
        ..fields['leave_type_id'] = leaveTypeId.toString()
        ..fields['start_date'] = _formatDateOnly(startDate)
        ..fields['end_date'] = _formatDateOnly(endDate)
        ..fields['reason'] = reason.trim();

      if (token != null && token.isNotEmpty) {
        request.headers['Authorization'] = 'Bearer $token';
      }

      if (attachmentBytes != null && attachmentBytes.isNotEmpty) {
        request.files.add(
          http.MultipartFile.fromBytes(
            'attachment',
            attachmentBytes,
            filename: (attachmentName == null || attachmentName.trim().isEmpty)
                ? 'attachment.bin'
                : attachmentName.trim(),
          ),
        );
      } else if (attachmentPath != null && attachmentPath.trim().isNotEmpty) {
        request.files.add(
          await http.MultipartFile.fromPath(
            'attachment',
            attachmentPath.trim(),
            filename: (attachmentName == null || attachmentName.trim().isEmpty)
                ? null
                : attachmentName.trim(),
          ),
        );
      }

      try {
        final streamed = await request.send().timeout(ApiConfig.connectTimeout);
        final response = await http.Response.fromStream(streamed);
        final body = _tryDecodeMap(response.body);

        if (response.statusCode >= 200 && response.statusCode < 300) {
          return;
        }

        lastError = ApiException(
          message: _extractMessage(body, fallback: 'Request failed'),
          statusCode: response.statusCode,
        );
      } catch (_) {
        lastError = ApiException(message: 'Connection timeout. Please try again.');
      }
    }

    throw lastError ?? ApiException(message: 'Unable to submit leave request');
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
    } catch (_) {
      // Ignore parse errors and fallback to default message.
    }

    return <String, dynamic>{};
  }

  String _extractMessage(
    Map<String, dynamic> body, {
    String fallback = 'Request failed',
  }) {
    final response = body['response'];
    if (response is Map<String, dynamic>) {
      final nested = response['message'];
      if (nested is String && nested.trim().isNotEmpty) {
        return nested;
      }
    }

    final direct = body['message'] ?? body['error'];
    if (direct is String && direct.trim().isNotEmpty) {
      return direct;
    }

    return fallback;
  }

  Future<void> cancelRequest({required AuthUser user, required int requestId}) async {
    _ensureSession(user);
    await _apiService.post('/v1/leave-requests/$requestId/cancel');
  }

  void _ensureSession(AuthUser user) {
    if (user.userId <= 0) {
      throw ApiException(message: 'Invalid user session');
    }
  }

  Map<String, dynamic> _responseMap(Map<String, dynamic> raw) {
    final response = raw['response'];
    if (response is Map<String, dynamic>) {
      return response;
    }

    throw ApiException(message: 'Invalid leave API response format');
  }

  String _formatDateOnly(DateTime date) {
    final y = date.year.toString().padLeft(4, '0');
    final m = date.month.toString().padLeft(2, '0');
    final d = date.day.toString().padLeft(2, '0');
    return '$y-$m-$d';
  }
}
