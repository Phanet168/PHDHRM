import '../../../core/network/api_exception.dart';
import '../../../core/network/api_service.dart';
import '../../auth/models/auth_user.dart';
import '../models/attendance_day_record.dart';

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
}
