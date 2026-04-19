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
    int? start,
  }) async {
    final employeeId = user.employeeId;
    if (employeeId <= 0) {
      throw ApiException(message: 'Employee ID មិនត្រឹមត្រូវ');
    }

    final raw = await _apiService.get(
      '/attendance_history',
      queryParameters: <String, dynamic>{
        'employee_id': employeeId,
        if (start != null) 'start': start,
      },
      requiresAuth: false,
    );

    final response = raw['response'];
    if (response is! Map<String, dynamic>) {
      throw ApiException(message: 'Response format មិនត្រឹមត្រូវ');
    }

    final status = (response['status'] ?? '').toString().toLowerCase();
    if (status != 'ok') {
      return <AttendanceDayRecord>[];
    }

    final groups = response['historydata'];
    if (groups is! List<dynamic>) {
      return <AttendanceDayRecord>[];
    }

    final records = <AttendanceDayRecord>[];
    for (final group in groups) {
      records.add(_parseGroup(group));
    }

    return records;
  }

  Future<AttendanceScanResult> submitAttendanceScan(
    AuthUser user, {
    required String qrToken,
    required double latitude,
    required double longitude,
    DateTime? scanTime,
  }) async {
    final employeeId = user.employeeId;
    if (employeeId <= 0) {
      throw ApiException(message: 'Invalid employee ID');
    }

    if (qrToken.trim().isEmpty) {
      throw ApiException(message: 'QR token is required');
    }

    final now = scanTime ?? DateTime.now();
    final raw = await _apiService.post(
      '/add_attendance',
      body: <String, dynamic>{
        'employee_id': employeeId,
        'user_id': user.userId,
        'datetime': _formatDateTime(now),
        'latitude': latitude.toStringAsFixed(7),
        'longitude': longitude.toStringAsFixed(7),
        'qr_token': qrToken.trim(),
      },
      requiresAuth: false,
    );

    final response = raw['response'];
    if (response is! Map<String, dynamic>) {
      throw ApiException(message: 'Invalid attendance response format');
    }

    return AttendanceScanResult.fromApi(response);
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
    final now = scanTime ?? DateTime.now();

    try {
      await _apiService.post(
        '/attendance_scan_log',
        body: <String, dynamic>{
          'employee_id': user.employeeId > 0 ? user.employeeId : null,
          'user_id': user.userId > 0 ? user.userId : null,
          if (workplaceId != null && workplaceId > 0)
            'workplace_id': workplaceId,
          'status': status,
          'error_code': errorCode,
          'message': message,
          if (qrToken != null && qrToken.trim().isNotEmpty)
            'qr_token': qrToken.trim(),
          if (latitude != null) 'latitude': latitude.toStringAsFixed(7),
          if (longitude != null) 'longitude': longitude.toStringAsFixed(7),
          if (rangeMeters != null) 'range': rangeMeters.toStringAsFixed(1),
          if (acceptableRangeMeters != null)
            'acceptable_range': acceptableRangeMeters.toStringAsFixed(1),
          if (geofenceSource != null && geofenceSource.trim().isNotEmpty)
            'geofence_source': geofenceSource.trim(),
          'datetime': _formatDateTime(now),
        },
        requiresAuth: false,
      );
    } catch (_) {
      // Do not block primary flow if logging endpoint fails.
    }
  }

  AttendanceDayRecord _parseGroup(dynamic group) {
    if (group is List<dynamic> && group.isNotEmpty) {
      final first = _asMap(group.first);
      final last = _asMap(group.last);
      final date = (first['date'] ?? first['mydate'] ?? '').toString().trim();
      final totalHours = (first['totalhours'] ?? '0:00:00').toString();
      final timeIn = (first['time'] ?? '-').toString();
      final timeOut = (last['time'] ?? timeIn).toString();

      return AttendanceDayRecord(
        date: date.isEmpty ? '-' : date,
        totalHours: totalHours,
        timeIn: timeIn,
        timeOut: timeOut,
        punchCount: group.length,
      );
    }

    if (group is Map<String, dynamic>) {
      final date = (group['date'] ?? group['mydate'] ?? '').toString();
      final totalHours = (group['totalhours'] ?? '0:00:00').toString();
      final timeIn = (group['time'] ?? '-').toString();

      return AttendanceDayRecord(
        date: date.isEmpty ? '-' : date,
        totalHours: totalHours,
        timeIn: timeIn,
        timeOut: timeIn,
        punchCount: 1,
      );
    }

    return AttendanceDayRecord(
      date: '-',
      totalHours: '0:00:00',
      timeIn: '-',
      timeOut: '-',
      punchCount: 0,
    );
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map<String, dynamic>) {
      return value;
    }

    return <String, dynamic>{};
  }

  String _formatDateTime(DateTime value) {
    String two(int input) => input.toString().padLeft(2, '0');

    return '${value.year}-${two(value.month)}-${two(value.day)} '
        '${two(value.hour)}:${two(value.minute)}:${two(value.second)}';
  }
}
