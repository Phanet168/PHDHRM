import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:staff_mobile_app/core/network/api_exception.dart';
import 'package:staff_mobile_app/core/network/api_service.dart';
import 'package:staff_mobile_app/core/network/device_heartbeat_service.dart';
import 'package:staff_mobile_app/core/storage/json_cache.dart';
import 'package:staff_mobile_app/core/storage/token_storage_service.dart';
import 'package:staff_mobile_app/core/time/trusted_clock.dart';
import 'package:staff_mobile_app/features/auth/controllers/auth_controller.dart';
import 'package:staff_mobile_app/features/auth/models/auth_user.dart';
import 'package:staff_mobile_app/features/auth/services/auth_service.dart';
import 'package:staff_mobile_app/features/home/services/home_attendance_service.dart';
import 'package:staff_mobile_app/features/home/services/offline_attendance_queue.dart';

final _user = AuthUser(
  employeeId: 7,
  userId: 9,
  name: 'Officer',
  email: 'test@example.test',
  userTypeId: 3,
);

/// Scripted server: each POST to /scan answers from [scanReplies] in order.
class _ScanApi extends ApiService {
  final posts = <Map<String, dynamic>>[];
  final scanReplies = <Object>[];
  Object? getReply;

  @override
  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
    bool requiresAuth = true,
    bool throwOnError = true,
  }) async {
    final reply = getReply;
    if (reply is Exception) throw reply;
    return reply as Map<String, dynamic>;
  }

  @override
  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
    bool throwOnError = true,
  }) async {
    posts.add(body!);
    final reply = scanReplies.removeAt(0);
    if (reply is Exception) throw reply;
    return reply as Map<String, dynamic>;
  }
}

Map<String, dynamic> _ok(String punch) => {
  'response': {
    'status': 'ok',
    'message': 'Attendance saved.',
    'punch_type': punch,
  },
};

PendingScan _scan(String id, DateTime at, {int userId = 9}) => PendingScan(
  requestId: id,
  userId: userId,
  latitude: 11.55,
  longitude: 104.92,
  capturedAt: at,
  clockSource: TrustedTime.serverAnchored,
  scanType: 'GPS',
);

class _Tokens extends TokenStorageService {
  _Tokens(this.token);
  String? token;
  @override
  Future<String?> readToken() async => token;
  @override
  Future<void> clearToken() async => token = null;
}

class _Auth extends AuthService {
  _Auth({this.cached, required this.refresh, required this.tokens});
  AuthUser? cached;
  Future<AuthUser> Function() refresh;
  final _Tokens tokens;
  @override
  Future<AuthUser?> cachedUser() async => cached;
  @override
  Future<AuthUser> refreshCurrentUser() => refresh();
  @override
  Future<void> clearLocalSession() async {
    tokens.token = null;
    cached = null;
  }
}

class _Heartbeat extends DeviceHeartbeatService {
  @override
  Future<void> start() async {}
  @override
  void stop() {}
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    OfflineAttendanceQueue.instance.debugReset();
  });

  test('server Date header parses as UTC', () {
    expect(
      TrustedClock.parseHttpDate('Thu, 29 Oct 2026 14:05:09 GMT'),
      DateTime.utc(2026, 10, 29, 14, 5, 9),
    );
    expect(TrustedClock.parseHttpDate('garbage'), isNull);
  });

  test('queued scans are sent oldest first with their capture time', () async {
    final api = _ScanApi()..scanReplies.addAll([_ok('in'), _ok('out')]);
    final queue = OfflineAttendanceQueue.instance;
    await queue.add(_scan('a', DateTime.utc(2026, 10, 29, 0, 55)));
    await queue.add(_scan('b', DateTime.utc(2026, 10, 29, 5, 2)));
    await queue.add(_scan('other', DateTime.utc(2026, 10, 29, 1), userId: 5));

    final report = await queue.sync(
      _user,
      HomeAttendanceService(apiService: api),
    );

    expect(report.sent, 2);
    expect(api.posts.map((p) => p['request_id']), ['a', 'b']);
    expect(api.posts.first['offline'], isTrue);
    expect(api.posts.first['captured_at'], '2026-10-29T00:55:00.000Z');
    expect(api.posts.first['clock_source'], 'server_anchored');
    // Another account's scan on this phone waits for that account.
    expect(queue.items.value.map((s) => s.requestId), ['other']);
  });

  test(
    'offline stops the run in order; a refusal is kept with its reason',
    () async {
      final api =
          _ScanApi()
            ..scanReplies.addAll([
              ApiException(
                message: 'Offline scans must be sent within 24 hours.',
                statusCode: 422,
                body: {
                  'response': {
                    'status': 'error',
                    'error_code': 'offline_scan_expired',
                  },
                },
              ),
              NetworkException(),
            ]);
      final queue = OfflineAttendanceQueue.instance;
      await queue.add(_scan('late', DateTime.utc(2026, 10, 27)));
      await queue.add(_scan('next', DateTime.utc(2026, 10, 29)));
      await queue.add(_scan('after', DateTime.utc(2026, 10, 29, 1)));

      final report = await queue.sync(
        _user,
        HomeAttendanceService(apiService: api),
      );

      expect(report.rejected, 1);
      expect(report.stillOffline, isTrue);
      expect(api.posts.length, 2, reason: '"after" must wait behind "next"');
      final late = queue.items.value.first;
      expect(late.isRejected, isTrue);
      expect(late.rejectionReason, 'ការស្កេនក្រៅបណ្ដាញ បញ្ជូនយឺតជាង ២៤ ម៉ោង');
      // Survives an app restart.
      queue.debugReset();
      await queue.load();
      expect(queue.items.value.map((s) => s.requestId), [
        'late',
        'next',
        'after',
      ]);
    },
  );

  test('offline, attendance history falls back to the last copy', () async {
    await JsonCache.instance.preload();
    final api =
        _ScanApi()
          ..getReply = {
            'response': {
              'status': 'ok',
              'data': [
                {
                  'date': '2026-10-28',
                  'attendance_status': 'on_time',
                  'worked_minutes': 480,
                },
              ],
            },
          };
    final service = HomeAttendanceService(apiService: api);
    final from = DateTime(2026, 10, 22);
    final to = DateTime(2026, 10, 29);
    await service.fetchAttendanceHistory(_user, fromDate: from, toDate: to);

    api.getReply = NetworkException();
    final offline = await service.fetchAttendanceHistory(
      _user,
      fromDate: from,
      toDate: to,
    );
    expect(offline.single.date, '2026-10-28');
    expect(
      service.cachedAttendanceHistory(_user, fromDate: from, toDate: to),
      hasLength(1),
    );
    // A real server error is not hidden behind the cache.
    api.getReply = ApiException(message: 'Server error', statusCode: 500);
    expect(
      () => service.fetchAttendanceHistory(_user, fromDate: from, toDate: to),
      throwsA(isA<ApiException>()),
    );
  });

  group('session restore', () {
    test('opens at once from the saved profile, even offline', () async {
      final tokens = _Tokens('token');
      final controller = AuthController(
        authService: _Auth(
          cached: _user,
          refresh: () async => throw NetworkException(),
          tokens: tokens,
        ),
        tokenStorageService: tokens,
        deviceHeartbeatService: _Heartbeat(),
      );
      await controller.restoreSession();
      await Future<void>.delayed(Duration.zero);
      expect(controller.status, AuthStatus.authenticated);
      expect(controller.currentUser?.userId, 9);
      expect(tokens.token, 'token', reason: 'no internet never signs out');
    });

    test('a token the server rejects signs out', () async {
      final tokens = _Tokens('token');
      final controller = AuthController(
        authService: _Auth(
          cached: _user,
          refresh: () async => throw UnauthorizedException(),
          tokens: tokens,
        ),
        tokenStorageService: tokens,
        deviceHeartbeatService: _Heartbeat(),
      );
      await controller.restoreSession();
      await Future<void>.delayed(Duration.zero);
      expect(controller.status, AuthStatus.unauthenticated);
      expect(tokens.token, isNull);
      expect(controller.errorMessage, isNotNull);
    });

    test(
      'no saved profile and no internet keeps the token for later',
      () async {
        final tokens = _Tokens('token');
        final controller = AuthController(
          authService: _Auth(
            refresh: () async => throw NetworkException(),
            tokens: tokens,
          ),
          tokenStorageService: tokens,
          deviceHeartbeatService: _Heartbeat(),
        );
        await controller.restoreSession();
        expect(controller.status, AuthStatus.unauthenticated);
        expect(tokens.token, 'token');
      },
    );
  });
}
