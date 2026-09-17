import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:staff_mobile_app/core/network/api_service.dart';
import 'package:staff_mobile_app/features/auth/models/auth_user.dart';
import 'package:staff_mobile_app/features/home/services/home_mission_service.dart';
import 'package:staff_mobile_app/features/home/pages/mission_detail_page.dart';

class MissionApiFake extends ApiService {
  final calls = <String>[];
  Map<String, dynamic>? lastBody;
  bool started = false;
  String? reportText;
  bool fail = false;

  Map<String, dynamic> get detail => {
    'id': 1,
    'title': 'Health center inspection',
    'destination': 'Sesan health center',
    'start_date': '2026-09-13',
    'end_date': '2026-09-15',
    'duration_days': 3,
    'status': 'approved',
    'display_status': started ? 'in_progress' : 'approved',
    'purpose': 'Review service quality and staffing.',
    'assignments_count': 4,
    'assigner': {'id': 10, 'name': 'Director', 'initial': 'D'},
    'team_members': [
      for (var i = 0; i < 4; i++)
        {'id': i + 1, 'name': 'Officer ${i + 1}', 'initial': 'O'},
    ],
    'documents': [
      {'id': 1, 'name': 'mission-plan.pdf', 'size': 1258291},
    ],
    'reports': [
      if (reportText != null) {'summary': reportText},
    ],
    'actions': {'can_start': !started, 'can_report': started},
  };

  @override
  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
    bool requiresAuth = true,
    bool throwOnError = true,
  }) async {
    calls.add(path);
    expect(requiresAuth, isTrue);
    if (fail) throw Exception('Network unavailable');
    final Object data =
        path.endsWith('signed-url')
            ? {'url': 'https://example.test/file?signature=test'}
            : path == '/v1/missions'
            ? {
              'data': [detail],
              'last_page': 1,
            }
            : detail;
    return {
      'response': {'status': 'ok', 'data': data},
    };
  }

  @override
  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
    bool throwOnError = true,
  }) async {
    calls.add(path);
    lastBody = body;
    expect(requiresAuth, isTrue);
    if (path.endsWith('/start')) started = true;
    if (path.endsWith('/report')) reportText = body?['summary'];
    return {
      'response': {'status': 'ok', 'data': detail},
    };
  }
}

void main() {
  test(
    'mission service consumes Laravel envelope and sends authenticated actions',
    () async {
      final api = MissionApiFake();
      final service = HomeMissionService(apiService: api);
      final user = AuthUser(
        employeeId: 1,
        userId: 2,
        name: 'Officer',
        email: '',
        userTypeId: 3,
      );
      final rows = await service.fetchMissions(user);
      expect(rows.single.employeeCount, 4);
      final detail = await service.fetchDetail(1);
      expect(detail.durationDays, 3);
      expect(detail.documents.single.sizeLabel, '1.2 MB');
      expect((await service.start(1)).canReport, isTrue);
      expect(
        (await service.report(1, '  Completed  ')).reports.single,
        'Completed',
      );
      expect(api.lastBody, {'summary': 'Completed'});
      expect((await service.documentUrl(1, 1)).scheme, 'https');
    },
  );

  testWidgets('detail start and report buttons complete the API flow', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = MissionApiFake();
    await tester.pumpWidget(
      MaterialApp(
        home: MissionDetailPage(
          missionId: 1,
          service: HomeMissionService(apiService: api),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Health center inspection'), findsOneWidget);
    expect(find.text('mission-plan.pdf'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.ensureVisible(find.text('ចាប់ផ្ដើម'));
    await tester.tap(find.text('ចាប់ផ្ដើម'));
    await tester.pumpAndSettle();
    expect(api.started, isTrue);
    await tester.ensureVisible(find.text('រាយការណ៍'));
    await tester.tap(find.text('រាយការណ៍'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('រក្សាទុក'));
    await tester.pumpAndSettle();
    expect(find.text('សូមបញ្ចូលរបាយការណ៍'), findsOneWidget);
    await tester.enterText(find.byType(TextFormField), 'Inspection completed');
    await tester.tap(find.text('រក្សាទុក'));
    await tester.pumpAndSettle();
    expect(api.reportText, 'Inspection completed');
    expect(tester.takeException(), isNull);
  });

  testWidgets('detail displays a recoverable loading error', (tester) async {
    final api = MissionApiFake()..fail = true;
    await tester.pumpWidget(
      MaterialApp(
        home: MissionDetailPage(
          missionId: 1,
          service: HomeMissionService(apiService: api),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.textContaining('Network unavailable'), findsOneWidget);
    api.fail = false;
    await tester.tap(find.text('ព្យាយាមម្ដងទៀត'));
    await tester.pumpAndSettle();
    expect(find.text('Health center inspection'), findsOneWidget);
  });
}
