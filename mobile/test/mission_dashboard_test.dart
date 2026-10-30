import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:staff_mobile_app/features/home/models/mission_stats.dart';
import 'package:staff_mobile_app/features/home/models/mission_summary.dart';
import 'package:staff_mobile_app/features/home/pages/home/mission_dashboard.dart';

const _me = 7;
final _today = DateTime(2026, 10, 30, 9);

MissionSummary _mission(
  int id,
  String start,
  String end, {
  String status = 'approved',
  List<int> team = const [_me],
  bool? participant,
  String type = 'បណ្ដុះបណ្ដាល',
}) => MissionSummary.fromMap({
  'id': id,
  'title': 'Mission $id',
  'destination': 'កំពត',
  'start_date': start,
  'end_date': end,
  'display_status': status,
  'mission_type_label': type,
  if (participant != null) 'is_participant': participant,
  'team_members': [
    for (final member in team) {'id': member},
  ],
});

final _missions = <MissionSummary>[
  // Crosses into November: 29–30 Oct done, 31 Oct + 1–2 Nov planned.
  _mission(1, '2026-10-29', '2026-11-02'),
  // Done in March, another type.
  _mission(2, '2026-03-10', '2026-03-12', type: 'ប្រជុំ/សិក្ខាសាលា'),
  // Last year, completed.
  _mission(3, '2025-12-30', '2026-01-01', status: 'completed'),
  // Not counted: someone else's team, a pending request, a rejection.
  _mission(4, '2026-05-01', '2026-05-03', team: const [99]),
  _mission(5, '2026-12-01', '2026-12-02', status: 'pending'),
  _mission(6, '2026-06-01', '2026-06-02', status: 'rejected'),
  // The server's flag wins over the team list.
  _mission(7, '2026-07-01', '2026-07-01', participant: false),
];

void main() {
  test('counts my approved days per month, done vs planned', () {
    final stats = MissionDayStats.compute(
      _missions,
      employeeId: _me,
      year: 2026,
      today: _today,
    );
    expect(stats.months[9].done, 2); // 29–30 October
    expect(stats.months[9].planned, 1); // 31 October
    expect(stats.months[10].planned, 2); // 1–2 November
    expect(stats.months[2].done, 3); // March
    expect(stats.months[0].done, 1); // 1 January from the 2025 mission
    expect(stats.months[4].total, 0); // not on that team
    expect(stats.months[6].total, 0); // server says not a participant
    expect(stats.totalDays, 9);
    expect(stats.doneDays, 6);
    expect(stats.plannedDays, 3);
    expect(stats.missionCount, 3);
    expect(stats.pendingRequests, 1);
    expect(stats.current?.id, 1);
    expect(stats.next, isNull);
    expect(stats.years, [2026, 2025]);
    // 2026 training days: 5 of Mission 1 + 1 January of Mission 3.
    expect(stats.byType.first, ('បណ្ដុះបណ្ដាល', 6));
    expect(missionDaysInMonth(_missions.first, 2026, 11), 2);

    final lastYear = MissionDayStats.compute(
      _missions,
      employeeId: _me,
      year: 2025,
      today: _today,
    );
    expect(lastYear.totalDays, 2);
    expect(lastYear.months[11].done, 2);
  });

  Future<void> pump(
    WidgetTester tester, {
    double textScale = 1,
    Size size = const Size(390, 2000),
    ValueChanged<MissionSummary>? onOpen,
  }) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = size;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      MaterialApp(
        builder:
            (context, app) => MediaQuery(
              data: MediaQuery.of(
                context,
              ).copyWith(textScaler: TextScaler.linear(textScale)),
              child: app!,
            ),
        home: Scaffold(
          body: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: MissionDashboard(
              missions: _missions,
              employeeId: _me,
              today: _today,
              onOpenMission: onOpen ?? (_) {},
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('shows the year, this month and a month\'s missions', (
    tester,
  ) async {
    final semantics = tester.ensureSemantics();
    MissionSummary? opened;
    await pump(tester, onOpen: (m) => opened = m);

    expect(find.text('បេសកកម្មរបស់ខ្ញុំ'), findsOneWidget);
    // The year's total in the header (the chart also labels month ៩).
    expect(
      find.byWidgetPredicate(
        (w) => w is Text && w.data == '៩' && w.style?.fontSize == 46,
      ),
      findsOneWidget,
    );
    expect(find.text('ថ្ងៃបេសកកម្ម ឆ្នាំ២០២៦'), findsOneWidget);
    expect(find.text('កំពុងបំពេញបេសកកម្ម'), findsOneWidget);
    expect(find.text('ថ្ងៃទី ២/៥'), findsOneWidget);
    // October is selected: 2 days of Mission 1.
    expect(find.text('ខែតុលា ២០២៦'), findsOneWidget);

    // Tap March's bar.
    await tester.tap(find.bySemanticsLabel('ខែមីនា ៣ ថ្ងៃ'));
    await tester.pumpAndSettle();
    expect(find.text('ខែមីនា ២០២៦'), findsOneWidget);
    expect(find.text('Mission 2'), findsOneWidget);
    await tester.tap(find.text('Mission 2'));
    expect(opened?.id, 2);

    // Back one year: only the 2025 days.
    await tester.tap(find.byTooltip('ឆ្នាំមុន'));
    await tester.pumpAndSettle();
    expect(find.text('ថ្ងៃបេសកកម្ម ឆ្នាំ២០២៥'), findsOneWidget);
    expect(find.text('ខែធ្នូ ២០២៥'), findsOneWidget);
    semantics.dispose();
  });

  testWidgets('fits a narrow screen at 2x text', (tester) async {
    await pump(tester, textScale: 2, size: const Size(320, 4000));
    expect(tester.takeException(), isNull);
  });
}
