import 'dart:math';

import '../../../core/network/api_exception.dart';
import '../../../core/network/api_service.dart';
import '../../auth/models/auth_user.dart';
import '../models/attendance_day_record.dart';
import '../models/attendance_scan_result.dart';

class HomeAttendanceService {
  HomeAttendanceService({ApiService? apiService})
    : _apiService = apiService ?? ApiService();

  final ApiService _apiService;

  Future<List<AttendanceDayRecord>> fetchAttendanceHistory(
    AuthUser user, {
    DateTime? fromDate,
    DateTime? toDate,
    int? start,
  }) async {
    if (user.employeeId <= 0) return <AttendanceDayRecord>[];
    final now = DateTime.now();
    final from = fromDate ?? DateTime(now.year, now.month, 1);
    final to = toDate ?? DateTime(from.year, from.month + 1, 0);
    if (to.isBefore(from)) {
      throw ApiException(message: 'End date must be on or after start date.');
    }
    final records = <AttendanceDayRecord>[];
    var cursor = DateTime(from.year, from.month, from.day);
    while (!cursor.isAfter(to)) {
      final limit = DateTime(cursor.year, cursor.month, cursor.day + 30);
      final end = limit.isBefore(to) ? limit : to;
      final response = _response(
        await _apiService.get(
          '/v1/attendance/history',
          queryParameters: {'from_date': _date(cursor), 'to_date': _date(end)},
        ),
      );
      final data = response['data'];
      if (data is! List) {
        throw ApiException(message: 'Invalid attendance history response');
      }
      records.addAll(
        data.whereType<Map<String, dynamic>>().map(AttendanceDayRecord.fromApi),
      );
      cursor = DateTime(end.year, end.month, end.day + 1);
    }
    records.sort((a, b) => b.date.compareTo(a.date));
    return records;
  }

  Future<Map<String, dynamic>> fetchToday(AuthUser user) async {
    _requireEmployee(user);
    return _response(await _apiService.get('/v1/attendance/today'));
  }

  Future<List<Map<String, dynamic>>> fetchSchedule(
    AuthUser user, {
    required DateTime fromDate,
    required DateTime toDate,
  }) async {
    _requireEmployee(user);
    final response = _response(
      await _apiService.get(
        '/v1/attendance/schedule',
        queryParameters: {
          'from_date': _date(fromDate),
          'to_date': _date(toDate),
        },
      ),
    );
    final data = response['data'];
    if (data is! List) {
      throw ApiException(message: 'Invalid attendance schedule response');
    }
    return data.whereType<Map<String, dynamic>>().toList(growable: false);
  }

  Future<AttendanceScanResult> submitAttendanceScan(
    AuthUser user, {
    String? qrToken,
    required double latitude,
    required double longitude,
    DateTime? scanTime,
    String? requestId,
  }) async {
    _requireEmployee(user);
    // ApiService reuses this body for transport retries. Server time and token
    // identity are authoritative; the phone cannot backdate or choose an officer.
    final raw = await _apiService.post(
      '/v1/attendance/scan',
      body: {
        'request_id': requestId ?? _requestId(),
        'latitude': latitude,
        'longitude': longitude,
        if (qrToken?.trim().isNotEmpty == true) 'qr_token': qrToken!.trim(),
      },
    );
    return AttendanceScanResult.fromApi(_response(raw));
  }

  Future<String> predictNextPunchType(AuthUser user, {DateTime? day}) async {
    final response = await fetchToday(user);
    final meta = response['meta'];
    final type = meta is Map ? meta['next_punch_type'] : null;
    if (type != 'in' && type != 'out') {
      throw ApiException(message: 'Invalid next attendance action');
    }
    return type as String;
  }

  Future<void> reportScanIssue(
    AuthUser user, {
    required String errorCode,
    required String message,
    String status = 'client_error',
    String? qrToken,
    double? latitude,
    double? longitude,
    int? workplaceId,
    DateTime? scanTime,
    double? rangeMeters,
    double? acceptableRangeMeters,
    String? geofenceSource,
  }) async {
    if (user.employeeId <= 0) return;
    try {
      await _apiService.post(
        '/v1/attendance/scan-issues',
        body: {
          'error_code':
              errorCode.length > 80 ? errorCode.substring(0, 80) : errorCode,
          'message':
              message.length > 1000 ? message.substring(0, 1000) : message,
          if (latitude != null) 'latitude': latitude,
          if (longitude != null) 'longitude': longitude,
        },
      );
    } catch (_) {
      // Issue logging must not block the scan result.
    }
  }

  void _requireEmployee(AuthUser user) {
    if (user.employeeId <= 0) {
      throw ApiException(
        message: 'This account does not have an employee profile.',
      );
    }
  }

  Map<String, dynamic> _response(Map<String, dynamic> raw) {
    final response = raw['response'];
    if (response is! Map<String, dynamic>) {
      throw ApiException(message: 'Invalid attendance response format');
    }
    if (response['status'] != 'ok') {
      throw ApiException(
        message: response['message']?.toString() ?? 'Attendance request failed',
      );
    }
    return response;
  }

  String _date(DateTime date) =>
      '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  String _requestId() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }
}
