import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:staff_mobile_app/core/network/api_exception.dart';
import 'package:staff_mobile_app/core/network/api_service.dart';
import 'package:staff_mobile_app/core/network/connection_status.dart';
import 'package:staff_mobile_app/shared/widgets/connection_banner.dart';

final _status = ConnectionStatus.instance;

ApiService _api(Future<http.Response> Function(http.Request) handler) =>
    ApiService(client: MockClient(handler));

Future<void> _pumpBanner(
  WidgetTester tester, {
  double textScale = 1,
  Size size = const Size(390, 800),
}) async {
  tester.view.devicePixelRatio = 1;
  tester.view.physicalSize = size;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    MaterialApp(
      builder:
          (context, child) => MediaQuery(
            data: MediaQuery.of(
              context,
            ).copyWith(textScaler: TextScaler.linear(textScale)),
            child: ConnectionBanner(child: child!),
          ),
      home: const Scaffold(body: Center(child: ConnectionPill())),
    ),
  );
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
    ApiService.resetRoutingState();
    _status.reset();
  });
  tearDown(_status.reset);

  test(
    'an unreachable server means offline; any API answer means online',
    () async {
      final down = _api(
        (_) async => throw http.ClientException('Connection refused'),
      );
      await expectLater(
        down.get('/v1/ping', requiresAuth: false),
        throwsA(isA<NetworkException>()),
      );
      expect(_status.isOffline, isTrue);
      expect(_status.offlineSince, isNotNull);

      // A refusal (401) is still the server answering.
      final refusing = _api(
        (_) async => http.Response(
          '{"message":"Unauthenticated."}',
          401,
          headers: {'content-type': 'application/json'},
        ),
      );
      await expectLater(
        refusing.get('/v1/attendance/today'),
        throwsA(isA<UnauthorizedException>()),
      );
      expect(_status.isOnline, isTrue);
      expect(_status.reconnections, 1);
      expect(_status.lastOnlineAt, isNotNull);
    },
  );

  test('a captive Wi-Fi page (HTML) is not the server', () async {
    final portal = _api(
      (_) async => http.Response(
        '<html>Login to Wi-Fi</html>',
        200,
        headers: {'content-type': 'text/html'},
      ),
    );
    await portal.get('/v1/ping', requiresAuth: false, throwOnError: false);
    expect(_status.isOnline, isFalse);
  });

  testWidgets('offline shows the bar; reconnecting turns it green', (
    tester,
  ) async {
    var probes = 0;
    _status.startWatching(() async => probes++);
    await _pumpBanner(tester);
    expect(find.text('កំពុងពិនិត្យ'), findsOneWidget);

    _status.markOffline();
    await tester.pumpAndSettle();
    expect(find.text('អហ្វឡាញ — មិនអាចភ្ជាប់ម៉ាស៊ីនមេ'), findsOneWidget);
    expect(find.text('ការស្កេនវត្តមាននឹងរក្សាទុកក្នុងទូរសព្ទ'), findsOneWidget);
    expect(find.text('អហ្វឡាញ'), findsOneWidget); // the pill

    await tester.tap(find.text('ព្យាយាម'));
    await tester.pump();
    expect(probes, 1);

    _status.markOnline();
    await tester.pumpAndSettle();
    expect(find.text('អនឡាញវិញ — បានភ្ជាប់ម៉ាស៊ីនមេ'), findsOneWidget);
    expect(find.text('អនឡាញ'), findsOneWidget);
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();
    expect(find.textContaining('អនឡាញវិញ'), findsNothing);
    _status.stopWatching();
  });

  testWidgets('the connection sheet explains offline scanning', (tester) async {
    _status.markOffline();
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(390, 900);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder:
              (context) => TextButton(
                onPressed: () => showConnectionSheet(context),
                child: const Text('open'),
              ),
        ),
      ),
    );
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(find.text('មិនអាចភ្ជាប់ម៉ាស៊ីនមេបានទេ'), findsOneWidget);
    expect(find.textContaining('អ្នកនៅតែស្កេនវត្តមានបាន'), findsOneWidget);
    expect(find.text('ពិនិត្យការតភ្ជាប់ឥឡូវ'), findsOneWidget);
  });

  testWidgets('the offline bar fits a narrow screen at 2x text', (
    tester,
  ) async {
    _status.markOffline();
    await _pumpBanner(tester, textScale: 2, size: const Size(320, 800));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  test('time labels: today shows the clock, other days the date too', () {
    final now = DateTime(2026, 11, 1, 9);
    expect(connectionTimeLabel(DateTime(2026, 11, 1, 8, 5), now: now), '០៨:០៥');
    expect(
      connectionTimeLabel(DateTime(2026, 10, 31, 17, 30), now: now),
      '៣១/១០ ១៧:៣០',
    );
  });
}
