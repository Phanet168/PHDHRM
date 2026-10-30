import 'package:flutter_test/flutter_test.dart';
import 'package:staff_mobile_app/core/network/api_exception.dart';
import 'package:staff_mobile_app/core/network/api_service.dart';
import 'package:staff_mobile_app/features/auth/models/auth_user.dart';
import 'package:staff_mobile_app/features/home/models/attendance_day_record.dart';
import 'package:staff_mobile_app/features/home/models/attendance_geofence.dart';
import 'package:staff_mobile_app/features/home/services/home_attendance_service.dart';

class FakeAttendanceApi extends ApiService {
  final queries = <Map<String, dynamic>?>[];
  String? path;
  Map<String, dynamic>? payload;
  bool? authenticated;
  Map<String, dynamic> result = {};
  ApiException? postError;
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
    if (postError != null) throw postError!;
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
    expect(record.sessions.single.displayOut, '-');
    expect(record.isDuty, isFalse);
  });

  test(
    'weekday night duty is read from duty_shift and hand-over shows continued',
    () {
      final record = AttendanceDayRecord.fromApi({
        ...day,
        'shift': {'name': 'Office', 'is_duty': false},
        'duty_shift': {'name': 'Night', 'is_duty': true},
        'sessions': [
          {
            'name': 'afternoon',
            'in_time': '2026-09-08 14:00:00',
            'out_time': null,
            'complete': true,
            'out_continued': true,
          },
          {
            'name': 'duty',
            'in_time': null,
            'out_time': '2026-09-09 07:00:00',
            'complete': true,
            'in_continued': true,
          },
        ],
      });
      expect(record.isDuty, isTrue);
      expect(record.shiftName, 'Office + Night');
      final [afternoon, duty] = record.sessions;
      expect(afternoon.displayIn, '14:00:00');
      expect(afternoon.displayOut, AttendanceSessionRecord.continuedLabel);
      expect(duty.displayIn, AttendanceSessionRecord.continuedLabel);
      expect(duty.displayOut, '07:00:00');
    },
  );

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

  test('scan context reads the enforced geofence and next punch', () async {
    final api =
        FakeAttendanceApi()
          ..result = {
            'response': {
              'status': 'ok',
              'data': day,
              'meta': {
                'next_punch_type': 'in',
                'geofence': {
                  'latitude': 13.5305709,
                  'longitude': 105.9688991,
                  'radius_meters': 500,
                  'source': 'workplace',
                },
              },
            },
          };
    final scanContext = await HomeAttendanceService(
      apiService: api,
    ).fetchScanContext(user);
    final fence = scanContext.geofence!;
    expect(scanContext.nextPunchType, 'in');
    expect(fence.radiusMeters, 500);
    // The phone fix from the real out-of-range report: ~629 m away.
    expect(fence.distanceTo(13.5262811, 105.9651094), closeTo(629, 1));
    final offset = fence.offsetOf(13.5262811, 105.9651094);
    expect(offset.north, lessThan(0));
    expect(offset.east, lessThan(0));
    expect(AttendanceGeofence.fromApi(null), isNull);
    expect(AttendanceGeofence.fromApi({'latitude': 1, 'longitude': 2}), isNull);
  });

  test(
    'out-of-range 422 becomes a failed scan result with distances',
    () async {
      final api =
          FakeAttendanceApi()
            ..postError = ApiException(
              message: 'You are outside the attendance area.',
              statusCode: 422,
              body: {
                'response': {
                  'status': 'error',
                  'error_code': 'out_of_range',
                  'message': 'You are outside the attendance area.',
                  'range': 629.4,
                  'acceptable_range': 500,
                },
              },
            );
      final result = await HomeAttendanceService(
        apiService: api,
      ).submitAttendanceScan(user, latitude: 13.526, longitude: 105.965);
      expect(result.isSuccess, isFalse);
      expect(result.errorCode, 'out_of_range');
      expect(result.rangeMeters, 629.4);
      expect(result.acceptableRangeMeters, 500);
    },
  );

  test('other scan API errors still throw', () async {
    final api =
        FakeAttendanceApi()
          ..postError = ApiException(message: 'Server error', statusCode: 500);
    expect(
      HomeAttendanceService(
        apiService: api,
      ).submitAttendanceScan(user, latitude: 13.526, longitude: 105.965),
      throwsA(isA<ApiException>()),
    );
  });

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
