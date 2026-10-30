import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:staff_mobile_app/core/network/api_service.dart';
import 'package:staff_mobile_app/features/auth/models/auth_user.dart';
import 'package:staff_mobile_app/features/home/services/home_mission_service.dart';
import 'package:staff_mobile_app/features/home/models/mission_detail.dart';
import 'package:staff_mobile_app/features/home/pages/mission_detail_page.dart';
import 'package:staff_mobile_app/features/home/pages/mission_report_form_page.dart';
import 'package:staff_mobile_app/features/home/pages/mission_request_page.dart';

class MissionApiFake extends ApiService {
  final calls = <String>[];
  Map<String, dynamic>? lastBody;
  bool started = false;
  Map<String, dynamic>? report;
  bool fail = false;

  Map<String, dynamic> get requestOptions => {
    'requester': {
      'employee_id': 1,
      'name': 'លាង ផានិត្យ',
      'position': 'អនុប្រធានការិយាល័យ',
    },
    'mission_types': [
      {'id': 4, 'name': 'បណ្ដុះបណ្ដាល'},
    ],
    'transport_types': [
      {'id': 2, 'name': 'រថយន្តជួល'},
    ],
    'funding_sources': [
      {'id': 2, 'name': 'ដៃគូរជំនួយ'},
    ],
    'approval_route': [
      {
        'label': 'ប្រធានការិយាល័យឯកភាព',
        'names': ['Office Head'],
      },
      {'label': 'ប្រធានមន្ទីរអនុម័ត', 'names': <String>[]},
    ],
  };

  /// Results line(s) of the submitted report, as the server stores them.
  List<String> get reportResults =>
      (report?['results'] as List? ?? const []).cast<String>();

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
      if (report != null)
        {
          'summary': reportResults.join('\n'),
          'is_mine': true,
          'employee_name': 'លោក Officer 1',
          'activities': report!['activities'],
          'results': reportResults,
          'recommendations': report!['recommendations'],
          'submitted_at': '2026-09-14T10:00:00+07:00',
        },
      {
        'summary': 'Older free-text report',
        'employee_name': 'Officer 2',
        'results': <String>[],
      },
    ],
    'actions': {'can_start': !started, 'can_report': started},
    'lifecycle_label': 'បានចេញលិខិត',
    'order': {
      'issued': true,
      'number': '123',
      'letter_code': 'មសរខ',
      'issued_on': '2026-09-12',
      'references': [
        'លិខិតលេខ ១១៤១ ប្រជឆ ចុះថ្ងៃទី២៤ ខែសីហា ឆ្នាំ២០២៦ របស់ក្រសួងសុខាភិបាល',
      ],
      'transport': 'រថយន្តជួល',
      'funding': 'US-CDC',
      'signatory_title': 'ប្រធានមន្ទីរសុខាភិបាលខេត្ត',
    },
    'order_officers': [
      {
        'honorific': 'លោកវេជ្ជ.',
        'name': 'ទន វុទ្ធី',
        'position': 'អនុប្រធានមន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត',
      },
      {'honorific': 'លោក', 'name': 'ឆាយ ម៉េងឆុន', 'position': ''},
    ],
    'timeline': [
      {
        'label': 'រង់ចាំប្រធានមន្ទីរ',
        'actor': 'Officer',
        'at': '2026-09-10T08:00:00+07:00',
      },
      {
        'label': 'បានចេញលិខិត',
        'actor': 'Letter manager',
        'note': 'Signed',
        'at': '2026-09-12T09:30:00+07:00',
      },
    ],
    'letters': {'order': true, 'travel_visa': true, 'report': report != null},
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
            : path == '/v1/missions/request-options'
            ? requestOptions
            : path == '/v1/missions/colleagues'
            ? [
              {'id': 7, 'name': 'ចាន់ សុភា', 'position': 'មន្ត្រី'},
              {'id': 8, 'name': 'Second Colleague', 'position': ''},
            ]
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
    if (path.endsWith('/report')) report = body;
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
      final reported = await service.report(
        1,
        const MissionReportInput(
          activities: ' Visited 3 centers ',
          results: ['  Completed  ', ' '],
        ),
      );
      expect(api.lastBody, {
        'activities': 'Visited 3 centers',
        'results': ['Completed'],
        'recommendations': '',
      });
      expect(reported.myReport?.results, ['Completed']);
      expect(reported.canViewReport, isTrue);
      expect(reported.reports.last.isStructured, isFalse);
      expect((await service.documentUrl(1, 1)).scheme, 'https');
      expect(
        (await service.letterUrl(1, MissionLetter.travelVisa)).scheme,
        'https',
      );
      expect(api.calls.last, '/v1/missions/1/letters/travel-visa/signed-url');
      expect(detail.order.numberLabel, '123 មសរខ');
      expect(detail.officers.first.honorific, 'លោកវេជ្ជ.');
      expect(detail.timeline.last.note, 'Signed');
      expect(detail.canViewTravelVisa, isTrue);
    },
  );

  testWidgets('mission order letter card fits a narrow screen at 2x text', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 3;
    tester.view.physicalSize = const Size(320 * 3, 640 * 3);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      MaterialApp(
        builder:
            (context, app) => MediaQuery(
              data: MediaQuery.of(
                context,
              ).copyWith(textScaler: const TextScaler.linear(2)),
              child: app!,
            ),
        home: MissionDetailPage(
          missionId: 1,
          service: HomeMissionService(apiService: MissionApiFake()),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('បានចេញលិខិត'), findsWidgets);
    await tester.scrollUntilVisible(
      find.text('លិខិតចុះទិដ្ឋការរបស់ខ្ញុំ'),
      300,
    );
    expect(find.text('លោកវេជ្ជ. ទន វុទ្ធី'), findsOneWidget);
    expect(find.text('ឧបត្ថម្ភថវិកា'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.scrollUntilVisible(find.text('ដំណើរការបេសកកម្ម'), 300);
    await tester.pumpAndSettle();
    expect(find.textContaining('Letter manager · '), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

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
    await tester.scrollUntilVisible(find.text('mission-plan.pdf'), 300);
    expect(find.text('mission-plan.pdf'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.ensureVisible(find.text('ចាប់ផ្ដើម'));
    await tester.tap(find.text('ចាប់ផ្ដើម'));
    await tester.pumpAndSettle();
    expect(api.started, isTrue);
    await tester.scrollUntilVisible(find.text('រាយការណ៍'), 300);
    await tester.ensureVisible(find.text('រាយការណ៍'));
    await tester.tap(find.text('រាយការណ៍'));
    await tester.pumpAndSettle();
    // The official form: an empty report is refused before any request.
    expect(find.text('៥'), findsOneWidget);
    await tester.tap(find.text('ដាក់ស្នើរបាយការណ៍'));
    await tester.pumpAndSettle();
    expect(
      find.text('សូមបញ្ចូលលទ្ធផលការងារ ឬសកម្មភាពការងារ យ៉ាងហោចណាស់មួយ'),
      findsOneWidget,
    );
    expect(api.report, isNull);
    await tester.enterText(
      find.byKey(const ValueKey('report-result-0')),
      'Inspection completed',
    );
    await tester.tap(find.text('បន្ថែមលទ្ធផល'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey('report-result-1')),
      'Staffing gaps noted',
    );
    await tester.tap(find.byTooltip('លុប').first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('ដាក់ស្នើរបាយការណ៍'));
    await tester.pumpAndSettle();
    expect(api.reportResults, ['Staffing gaps noted']);
    // Back on the detail page, the report shows by section.
    await tester.scrollUntilVisible(find.text('របាយការណ៍របស់ខ្ញុំ'), 300);
    expect(find.text('• Staffing gaps noted'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Older free-text report'), 300);
    expect(find.text('កែរបាយការណ៍'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('mission request validates, adds a colleague and submits', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 1400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    final api = MissionApiFake();
    MissionDetail? created;
    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder:
              (context) => Scaffold(
                body: Center(
                  child: TextButton(
                    onPressed: () async {
                      created = await Navigator.of(context).push(
                        MaterialPageRoute(
                          builder:
                              (_) => MissionRequestPage(
                                service: HomeMissionService(apiService: api),
                              ),
                        ),
                      );
                    },
                    child: const Text('open'),
                  ),
                ),
              ),
        ),
      ),
    );
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(find.text('លាង ផានិត្យ (ខ្ញុំ)'), findsOneWidget);
    expect(find.text('Office Head'), findsOneWidget);
    expect(find.text('មិនទាន់កំណត់អ្នកទទួលបន្ទុក'), findsOneWidget);

    // Nothing filled in: required fields and dates are flagged, no request.
    await tester.tap(find.text('ដាក់ស្នើសំណើ'));
    await tester.pumpAndSettle();
    expect(find.text('សូមបំពេញ'), findsNWidgets(2));
    expect(find.text('សូមជ្រើសរើសថ្ងៃចេញដំណើរ និងថ្ងៃត្រឡប់'), findsOneWidget);
    expect(api.calls.contains('/v1/missions/request'), isFalse);

    await tester.enterText(
      find.byKey(const ValueKey('request-title')),
      'Training',
    );
    await tester.enterText(
      find.byKey(const ValueKey('request-destination')),
      'Kampot',
    );
    for (final label in ['ចេញដំណើរ *', 'ត្រឡប់មកវិញ *']) {
      // The floating label itself ignores pointers; the tap lands on the field.
      await tester.tap(find.text(label), warnIfMissed: false);
      await tester.pumpAndSettle();
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
    }
    expect(find.text('រយៈពេល ១ ថ្ងៃ'), findsOneWidget);

    await tester.tap(find.text('បន្ថែមមន្ត្រី'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('ចាន់ សុភា'));
    await tester.pumpAndSettle();
    expect(find.text('ចាន់ សុភា'), findsOneWidget);

    await tester.tap(find.text('ដាក់ស្នើសំណើ'));
    await tester.pumpAndSettle();
    expect(api.calls.last, '/v1/missions/request');
    expect(api.lastBody?['title'], 'Training');
    expect(api.lastBody?['employee_ids'], [1, 7]);
    expect(api.lastBody?['start_date'], api.lastBody?['end_date']);
    expect(created?.mission.id, 1);
    expect(tester.takeException(), isNull);
  });

  testWidgets('mission request form fits a narrow screen at 2x text', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 3;
    tester.view.physicalSize = const Size(320 * 3, 640 * 3);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      MaterialApp(
        builder:
            (context, app) => MediaQuery(
              data: MediaQuery.of(
                context,
              ).copyWith(textScaler: const TextScaler.linear(2)),
              child: app!,
            ),
        home: MissionRequestPage(
          service: HomeMissionService(apiService: MissionApiFake()),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    final page = find.byType(Scrollable).first;
    for (final label in ['មន្ត្រីចូលរួម', 'លំហូរអនុម័ត']) {
      await tester.scrollUntilVisible(find.text(label), 300, scrollable: page);
      expect(tester.takeException(), isNull);
    }
  });

  testWidgets('report form fits a narrow screen at 2x text', (tester) async {
    tester.view.devicePixelRatio = 3;
    tester.view.physicalSize = const Size(320 * 3, 640 * 3);
    addTearDown(tester.view.reset);
    final api = MissionApiFake()..started = true;
    final service = HomeMissionService(apiService: api);
    final detail = await service.fetchDetail(1);
    await tester.pumpWidget(
      MaterialApp(
        builder:
            (context, app) => MediaQuery(
              data: MediaQuery.of(
                context,
              ).copyWith(textScaler: const TextScaler.linear(2)),
              child: app!,
            ),
        home: MissionReportFormPage(detail: detail, service: service),
      ),
    );
    await tester.pumpAndSettle();
    // Text fields are scrollables too; drive the page's own list.
    final page = find.byType(Scrollable).first;
    await tester.scrollUntilVisible(
      find.text('ទីកន្លែងនិងសកម្មភាពការងារ'),
      300,
      scrollable: page,
    );
    expect(tester.takeException(), isNull);
    await tester.scrollUntilVisible(
      find.text('សំណូមពរ និងសេចក្ដីសន្និដ្ឋាន'),
      300,
      scrollable: page,
    );
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
