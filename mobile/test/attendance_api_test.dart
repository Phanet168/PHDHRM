import 'package:flutter_test/flutter_test.dart';
import 'package:staff_mobile_app/core/network/api_exception.dart';
import 'package:staff_mobile_app/core/network/api_service.dart';
import 'package:staff_mobile_app/features/auth/models/auth_user.dart';
import 'package:staff_mobile_app/features/home/models/attendance_day_record.dart';
import 'package:staff_mobile_app/features/home/services/home_attendance_service.dart';

class FakeAttendanceApi extends ApiService {
  final queries = <Map<String, dynamic>?>[];
  String? path;
  Map<String, dynamic>? payload;
  bool? authenticated;
  Map<String, dynamic> result = {};
  @override
  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
    bool requiresAuth = true,
    bool throwOnError = true,
  }) async {
    this.path = path;
    queries.add(queryParameters);
    payload = queryParameters;
    authenticated = requiresAuth;
    return result;
  }

  @override
  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
    bool throwOnError = true,
  }) async {
    this.path = path;
    payload = body;
    authenticated = requiresAuth;
    return result;
  }
}

void main() {
  final user = AuthUser(
    employeeId: 7,
    userId: 9,
    name: 'Officer',
    email: 'test@example.test',
    userTypeId: 3,
  );
  final day = <String, dynamic>{
    'date': '2026-09-08',
    'in_time': '2026-09-08 08:00:00',
    'out_time': '2026-09-08 17:00:00',
    'worked_minutes': 0,
    'punch_count': 2,
    'attendance_status': 'incomplete',
    'unit': {'id': 1, 'name': 'Unit A'},
    'sessions': [
      {
        'name': 'morning',
        'scheduled_in': '2026-09-08 08:00:00',
        'scheduled_out': '2026-09-08 12:00:00',
        'in_time': '2026-09-08 08:00:00',
        'out_time': null,
        'complete': false,
      },
    ],
  };

  test('server zero work minutes never become a fabricated full workday', () {
    final record = AttendanceDayRecord.fromApi(day);
    expect(record.totalHours, '0:00:00');
    expect(record.timeIn, '08:00:00');
    expect(record.sessions.single.timeOut, '-');
    expect(record.unitName, 'Unit A');
  });

  test(
    'history uses authenticated self endpoint without employee selector',
    () async {
      final api =
          FakeAttendanceApi()
            ..result = {
              'response': {
                'status': 'ok',
                'data': [day],
              },
            };
      final rows = await HomeAttendanceService(
        apiService: api,
      ).fetchAttendanceHistory(
        user,
        fromDate: DateTime(2026, 9, 1),
        toDate: DateTime(2026, 9, 30),
      );
      expect(api.path, '/v1/attendance/history');
      expect(api.authenticated, true);
      expect(api.payload!.containsKey('employee_id'), false);
      expect(rows.single.totalHours, '0:00:00');
    },
  );

  test(
    'next action comes from server even when punch count suggests otherwise',
    () async {
      final api =
          FakeAttendanceApi()
            ..result = {
              'response': {
                'status': 'ok',
                'data': {'punch_count': 0},
                'meta': {'next_punch_type': 'out'},
              },
            };
      expect(
        await HomeAttendanceService(apiService: api).predictNextPunchType(user),
        'out',
      );
      expect(api.path, '/v1/attendance/today');
    },
  );

  test(
    'scan sends retry key and location without client identity or time',
    () async {
      final api =
          FakeAttendanceApi()
            ..result = {
              'response': {
                'status': 'ok',
                'message': 'Saved',
                'work_date': '2026-09-08',
                'data': day,
              },
            };
      final result = await HomeAttendanceService(
        apiService: api,
      ).submitAttendanceScan(
        user,
        latitude: 11.55,
        longitude: 104.92,
        scanTime: DateTime(2000),
        requestId: 'sample-retry-key',
      );
      expect(api.path, '/v1/attendance/scan');
      expect(api.authenticated, true);
      expect(api.payload, {
        'request_id': 'sample-retry-key',
        'latitude': 11.55,
        'longitude': 104.92,
      });
      expect(result.workDate, '2026-09-08');
      expect(result.attendance!.totalHours, '0:00:00');
    },
  );

  test('custom date range is split into bounded API requests', () async {
    final api =
        FakeAttendanceApi()
          ..result = {
            'response': {'status': 'ok', 'data': []},
          };
    await HomeAttendanceService(apiService: api).fetchAttendanceHistory(
      user,
      fromDate: DateTime(2026, 8, 1),
      toDate: DateTime(2026, 9, 5),
    );
    expect(api.queries, [
      {'from_date': '2026-08-01', 'to_date': '2026-08-31'},
      {'from_date': '2026-09-01', 'to_date': '2026-09-05'},
    ]);
  });

  test(
    'malformed history fails visibly instead of becoming an empty calendar',
    () async {
      final api =
          FakeAttendanceApi()
            ..result = {
              'response': {'status': 'ok'},
            };
      await expectLater(
        HomeAttendanceService(apiService: api).fetchAttendanceHistory(user),
        throwsA(isA<ApiException>()),
      );
    },
  );
}
