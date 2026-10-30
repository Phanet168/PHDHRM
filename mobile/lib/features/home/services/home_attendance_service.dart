import 'dart:math';

import '../../../core/network/api_exception.dart';
import '../../../core/network/api_service.dart';
import '../../../core/storage/json_cache.dart';
import '../../auth/models/auth_user.dart';
import '../models/attendance_day_record.dart';
import '../models/attendance_geofence.dart';
import '../models/attendance_scan_result.dart';
import 'offline_attendance_queue.dart';

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
    final cacheKey = _historyKey(user, from, to);
    final rows = <Map<String, dynamic>>[];
    try {
      var cursor = DateTime(from.year, from.month, from.day);
      while (!cursor.isAfter(to)) {
        final limit = DateTime(cursor.year, cursor.month, cursor.day + 30);
        final end = limit.isBefore(to) ? limit : to;
        final response = _response(
          await _apiService.get(
            '/v1/attendance/history',
            queryParameters: {
              'from_date': _date(cursor),
              'to_date': _date(end),
            },
          ),
        );
        final data = response['data'];
        if (data is! List) {
          throw ApiException(message: 'Invalid attendance history response');
        }
        rows.addAll(data.whereType<Map<String, dynamic>>());
        cursor = DateTime(end.year, end.month, end.day + 1);
      }
    } on ApiException catch (error) {
      // Offline: show the last copy rather than an error.
      final cached = cachedAttendanceHistory(user, fromDate: from, toDate: to);
      if (cached != null && _isOffline(error)) return cached;
      rethrow;
    }
    await JsonCache.instance.write(cacheKey, rows);
    return _records(rows);
  }

  /// The last fetched copy of this exact range, if any — for showing the
  /// dashboard instantly while the network catches up.
  List<AttendanceDayRecord>? cachedAttendanceHistory(
    AuthUser user, {
    required DateTime fromDate,
    required DateTime toDate,
  }) {
    final raw = JsonCache.instance.read(_historyKey(user, fromDate, toDate));
    if (raw is! List) return null;
    return _records(raw.whereType<Map>().map(Map<String, dynamic>.from));
  }

  String _historyKey(AuthUser user, DateTime from, DateTime to) =>
      'u${user.userId}:history:${_date(from)}:${_date(to)}';

  List<AttendanceDayRecord> _records(Iterable<Map<String, dynamic>> rows) =>
      rows.map(AttendanceDayRecord.fromApi).toList()
        ..sort((a, b) => b.date.compareTo(a.date));

  static bool _isOffline(ApiException error) =>
      error is NetworkException || isNetworkErrorMessage(error.message);

  /// Today's record and scan context (geofence, next punch). Falls back to the
  /// last copy offline, so the scan screen can still check the zone.
  Future<Map<String, dynamic>> fetchToday(AuthUser user) async {
    _requireEmployee(user);
    final key = 'u${user.userId}:today';
    try {
      final response = _response(await _apiService.get('/v1/attendance/today'));
      await JsonCache.instance.write(key, response);
      return response;
    } on ApiException catch (error) {
      final cached = JsonCache.instance.read(key);
      if (cached is Map && _isOffline(error)) {
        return Map<String, dynamic>.from(cached);
      }
      rethrow;
    }
  }

  /// A short-lived link to the officer's printable attendance report for a
  /// range (at most 31 days); it opens in the browser to print or save as PDF.
  Future<Uri> reportUrl(
    AuthUser user, {
    required DateTime fromDate,
    required DateTime toDate,
  }) async {
    _requireEmployee(user);
    final response = _response(
      await _apiService.get(
        '/v1/attendance/report/signed-url',
        queryParameters: {
          'from_date': _date(fromDate),
          'to_date': _date(toDate),
        },
      ),
    );
    final data = response['data'];
    final uri = Uri.tryParse(data is Map ? '${data['url'] ?? ''}' : '');
    if (uri == null ||
        !uri.hasAuthority ||
        !['http', 'https'].contains(uri.scheme)) {
      throw ApiException(message: 'Invalid report link');
    }
    return uri;
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
    return _postScan({
      'request_id': requestId ?? _requestId(),
      'latitude': latitude,
      'longitude': longitude,
      if (qrToken?.trim().isNotEmpty == true) 'qr_token': qrToken!.trim(),
    });
  }

  /// Sends a scan saved while offline, stamped with when it was captured.
  /// The server accepts it only within its offline window (and, for QR,
  /// only if the code was valid at that time).
  Future<AttendanceScanResult> submitQueuedScan(
    AuthUser user,
    PendingScan scan,
  ) {
    _requireEmployee(user);
    return _postScan({
      'request_id': scan.requestId,
      'latitude': scan.latitude,
      'longitude': scan.longitude,
      if (scan.qrToken?.trim().isNotEmpty == true)
        'qr_token': scan.qrToken!.trim(),
      'offline': true,
      'captured_at': scan.capturedAt.toUtc().toIso8601String(),
      'clock_source': scan.clockSource,
    });
  }

  /// A fresh idempotency key for a scan.
  String newRequestId() => _requestId();

  Future<AttendanceScanResult> _postScan(Map<String, dynamic> body) async {
    final Map<String, dynamic> raw;
    try {
      raw = await _apiService.post('/v1/attendance/scan', body: body);
    } on ApiException catch (error) {
      // A rejected scan (outside the area, invalid QR, ...) comes back as 422
      // with its details; show it as a failed result, not a raw exception.
      final response = error.body?['response'];
      if (error.statusCode == 422 &&
          response is Map<String, dynamic> &&
          response['status'] == 'error') {
        return AttendanceScanResult.fromApi(response);
      }
      rethrow;
    }
    return AttendanceScanResult.fromApi(_response(raw));
  }

  /// The enforced scan area and the next punch direction, for the GPS tab's
  /// live in/out-of-zone indicator. Either may be null (not configured).
  Future<({AttendanceGeofence? geofence, String? nextPunchType})>
  fetchScanContext(AuthUser user) async {
    final response = await fetchToday(user);
    final meta = response['meta'];
    final type = meta is Map ? meta['next_punch_type'] : null;
    return (
      geofence: AttendanceGeofence.fromApi(
        meta is Map ? meta['geofence'] : null,
      ),
      nextPunchType: type == 'in' || type == 'out' ? type as String : null,
    );
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
