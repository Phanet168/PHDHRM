import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:staff_mobile_app/features/auth/models/auth_user.dart';
import 'package:staff_mobile_app/features/home/models/leave_request_models.dart';
import 'package:staff_mobile_app/features/home/pages/leave_detail_page.dart';
import 'package:staff_mobile_app/features/home/pages/leave_request_page.dart';
import 'package:staff_mobile_app/features/home/services/home_leave_service.dart';

final _year = DateTime.now().year;

LeaveBalanceItem _balance(String km, int entitlement, int used) =>
    LeaveBalanceItem.fromMap({
      'leave_type_id': km.length,
      'leave_type_km': km,
      'entitlement': entitlement,
      'used': used,
      'pending': 0,
      'remaining': entitlement - used,
    });

LeaveRequestItem _request(int id, String status, int days) =>
    LeaveRequestItem.fromMap({
      'id': id,
      'leave_type_km': 'ឈប់សម្រាករយៈពេលខ្លី',
      'start_date': '$_year-05-20',
      'end_date': '$_year-05-22',
      'requested_days': days,
      'approved_days': status == 'approved' ? days : 0,
      'status': status,
    });

class _FakeLeaveService extends HomeLeaveService {
  @override
  Future<List<LeaveTypeOption>> fetchTypes(AuthUser user) async => const [];

  @override
  Future<List<HandoverEmployeeOption>> fetchHandoverEmployees(
    AuthUser user,
  ) async => const [];

  @override
  Future<LeaveSummary> fetchSummary(AuthUser user) async => LeaveSummary(
    totalRemaining: 478,
    types: [
      _balance('ឈប់សម្រាកលំហែមាតុភាព', 90, 0),
      _balance('ឈប់សម្រាកព្យាបាលជំងឺ', 360, 0),
      _balance('ឈប់ប្រចាំឆ្នាំ', 15, 0),
      _balance('ឈប់សម្រាករយៈពេលខ្លី', 15, 2),
    ],
  );

  @override
  Future<List<LeaveRequestItem>> fetchRequests(AuthUser user) async => [
    _request(1, 'pending', 1),
    _request(2, 'approved', 2),
    _request(3, 'cancelled', 3),
  ];
}

Future<void> _pump(
  WidgetTester tester, {
  bool reviewer = false,
  double textScale = 1,
  Size size = const Size(390, 2400),
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
        body: LeaveRequestPage(
          user: AuthUser(
            employeeId: 7,
            userId: 9,
            name: 'Officer',
            email: 'o@example.test',
            userTypeId: 3,
            canReviewLeaveRequestsFlag: reviewer,
          ),
          language: const {},
          leaveService: _FakeLeaveService(),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('shows this year at a glance, each balance and recent requests', (
    tester,
  ) async {
    await _pump(tester);
    // Hero: 1 pending, 1 approved, 2 approved days — no cross-type total.
    expect(find.text('បានអនុម័ត'), findsOneWidget);
    expect(find.text('ថ្ងៃបានឈប់'), findsOneWidget);
    expect(find.text('៤៧៨'), findsNothing);
    expect(find.text('ដាក់សំណើច្បាប់ថ្មី'), findsOneWidget);
    // Each leave type keeps its own balance.
    expect(find.text('នៅសល់ ៩០ ថ្ងៃ · ប្រើ ០/៩០'), findsOneWidget);
    expect(find.text('នៅសល់ ១៣ ថ្ងៃ · ប្រើ ២/១៥'), findsOneWidget);
    expect(find.text('២០ ឧសភា → ២២ ឧសភា ${_km(_year)}'), findsNWidgets(3));
    expect(find.text('ពិនិត្យ/អនុម័តសំណើ'), findsNothing);
  });

  testWidgets('a recent request opens its detail', (tester) async {
    await _pump(tester, reviewer: true);
    expect(find.text('ពិនិត្យ/អនុម័តសំណើ'), findsOneWidget);
    await tester.tap(find.text('ឈប់សម្រាករយៈពេលខ្លី').last);
    await tester.pumpAndSettle();
    expect(find.byType(LeaveDetailPage), findsOneWidget);
  });

  testWidgets('fits a narrow screen at 2x text', (tester) async {
    await _pump(
      tester,
      reviewer: true,
      textScale: 2,
      size: const Size(320, 4000),
    );
    expect(tester.takeException(), isNull);
  });
}

String _km(int value) =>
    value.toString().split('').map((c) => '០១២៣៤៥៦៧៨៩'[int.parse(c)]).join();
