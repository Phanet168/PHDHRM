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
    final employeeId = user.employeeId;
    if (employeeId <= 0) {
      throw ApiException(message: 'Invalid employee ID');
    }

    final now = DateTime.now();
    final resolvedFromDate =
        fromDate ?? DateTime(now.year, now.month, 1, 0, 0, 0);
    final resolvedToDate = toDate ?? DateTime(now.year, now.month + 1, 0);

    final raw = await _apiService.get(
      '/attendance_datewise',
      queryParameters: <String, dynamic>{
        'employee_id': employeeId,
        'from_date': _formatDateOnly(resolvedFromDate),
        'to_date': _formatDateOnly(resolvedToDate),
        if (start != null) 'start': start,
      },
      requiresAuth: false,
    );

    final response = raw['response'];
    if (response is! Map<String, dynamic>) {
      throw ApiException(message: 'Invalid response format');
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

    records.sort((a, b) => b.date.compareTo(a.date));
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

  Future<String> predictNextPunchType(AuthUser user, {DateTime? day}) async {
    final targetDay = day ?? DateTime.now();
    final startOfDay = DateTime(targetDay.year, targetDay.month, targetDay.day);
    final endOfDay = DateTime(targetDay.year, targetDay.month, targetDay.day);

    try {
      final records = await fetchAttendanceHistory(
        user,
        fromDate: startOfDay,
        toDate: endOfDay,
      );
      final todayKey = _formatDateOnly(targetDay);
      AttendanceDayRecord? todayRecord;

      for (final record in records) {
        if (record.date.startsWith(todayKey)) {
          todayRecord = record;
          break;
        }
      }

      final count = todayRecord?.punchCount ?? 0;
      return count % 2 == 0 ? 'in' : 'out';
    } catch (_) {
      // Fallback if history endpoint fails.
      return 'in';
    }
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
    final parsedDatewise = _parseDatewiseGroup(group);
    if (parsedDatewise != null) {
      return parsedDatewise;
    }

    if (group is List<dynamic> && group.isNotEmpty) {
      final first = _asMap(group.first);
      final last = _asMap(group.last);
      final date = (first['date'] ?? first['mydate'] ?? '').toString().trim();
      final totalHours = (first['totalhours'] ?? '0:00:00').toString();
      final timeIn = _toTimeDisplay(first['time']);
      final timeOut = _toTimeDisplay(last['time'] ?? first['time']);

      return AttendanceDayRecord(
        date: date.isEmpty ? '-' : date,
        totalHours: totalHours,
        timeIn: timeIn,
        timeOut: timeOut,
        punchCount: group.length,
      );
    }

    if (group is Map<String, dynamic> || group is Map) {
      final map = _asMap(group);
      final date = (map['date'] ?? map['mydate'] ?? '').toString().trim();
      final totalHours = (map['totalhours'] ?? '0:00:00').toString();
      final timeIn = _toTimeDisplay(map['time']);

      return AttendanceDayRecord(
        date: date.isEmpty ? '-' : date,
        totalHours: totalHours,
        timeIn: timeIn,
        timeOut: timeIn,
        punchCount: _toInt(map['punch_count']) ?? 1,
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

  AttendanceDayRecord? _parseDatewiseGroup(dynamic group) {
    final map = _asMap(group);
    if (map.isEmpty) {
      return null;
    }

    final baseRow = _extractBaseRow(map);
    final date = _toStringOrNull(
      map['date'] ?? baseRow['date'] ?? baseRow['mydate'],
    );
    if (date == null || date.isEmpty) {
      return null;
    }

    final totalHours = _toStringOrNull(
      map['nethours'] ?? map['totalhours'] ?? baseRow['totalhours'],
    );
    final timeIn = _toTimeDisplay(
      map['first_punch'] ??
          baseRow['intime'] ??
          baseRow['time'] ??
          baseRow['in_time'],
    );
    final timeOut = _toTimeDisplay(
      map['last_punch'] ??
          baseRow['outtime'] ??
          baseRow['time'] ??
          baseRow['out_time'],
    );

    return AttendanceDayRecord(
      date: date,
      totalHours: totalHours ?? '0:00:00',
      timeIn: timeIn,
      timeOut: timeOut,
      punchCount:
          _toInt(map['punch_count']) ?? _resolvePunchCount(baseRow, map),
      attendanceStatus: _toStringOrNull(map['attendance_status']),
      lateMinutes: _toInt(map['late_minutes']),
      earlyLeaveMinutes: _toInt(map['early_leave_minutes']),
      hasException: _toBool(map['has_exception']),
      exceptionReason: _toStringOrNull(map['exception_reason']),
    );
  }

  Map<String, dynamic> _extractBaseRow(Map<String, dynamic> entry) {
    final candidates = <dynamic>[entry['0'], entry['row'], entry['first']];

    for (final candidate in candidates) {
      final row = _asMap(candidate);
      if (row.isNotEmpty) {
        return row;
      }
    }

    return <String, dynamic>{};
  }

  int _resolvePunchCount(
    Map<String, dynamic> baseRow,
    Map<String, dynamic> entry,
  ) {
    final fromEntry = _toInt(entry['punch_count']);
    if (fromEntry != null && fromEntry > 0) {
      return fromEntry;
    }

    final fromBase = _toInt(baseRow['punch_count']);
    if (fromBase != null && fromBase > 0) {
      return fromBase;
    }

    return 1;
  }

  String _toTimeDisplay(dynamic value) {
    final text = value?.toString().trim();
    if (text == null || text.isEmpty) {
      return '-';
    }

    final parsed = DateTime.tryParse(text);
    if (parsed != null) {
      return '${_two(parsed.hour)}:${_two(parsed.minute)}:${_two(parsed.second)}';
    }

    if (text.contains(' ')) {
      return text.split(' ').last;
    }

    return text;
  }

  int? _toInt(dynamic value) {
    if (value == null) {
      return null;
    }

    if (value is int) {
      return value;
    }

    if (value is num) {
      return value.toInt();
    }

    return int.tryParse(value.toString().trim());
  }

  bool? _toBool(dynamic value) {
    if (value == null) {
      return null;
    }

    if (value is bool) {
      return value;
    }

    if (value is num) {
      return value != 0;
    }

    final text = value.toString().trim().toLowerCase();
    if (text == 'true' || text == '1' || text == 'yes') {
      return true;
    }
    if (text == 'false' || text == '0' || text == 'no') {
      return false;
    }

    return null;
  }

  String? _toStringOrNull(dynamic value) {
    final text = value?.toString().trim();
    if (text == null || text.isEmpty) {
      return null;
    }

    return text;
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map<String, dynamic>) {
      return value;
    }

    if (value is Map) {
      return value.map((key, val) => MapEntry(key.toString(), val));
    }

    return <String, dynamic>{};
  }

  String _formatDateOnly(DateTime value) {
    String two(int input) => input.toString().padLeft(2, '0');

    return '${value.year}-${two(value.month)}-${two(value.day)}';
  }

  String _formatDateTime(DateTime value) {
    String two(int input) => input.toString().padLeft(2, '0');

    return '${value.year}-${two(value.month)}-${two(value.day)} '
        '${two(value.hour)}:${two(value.minute)}:${two(value.second)}';
  }

  String _two(int input) {
    return input.toString().padLeft(2, '0');
  }
}
