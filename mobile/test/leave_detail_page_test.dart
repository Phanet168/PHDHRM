import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:staff_mobile_app/features/auth/models/auth_user.dart';
import 'package:staff_mobile_app/features/home/models/leave_request_models.dart';
import 'package:staff_mobile_app/features/home/pages/leave_detail_page.dart';
import 'package:staff_mobile_app/features/home/services/home_leave_service.dart';

final _user = AuthUser(
  employeeId: 7,
  userId: 9,
  name: 'Officer',
  email: 'o@example.test',
  userTypeId: 3,
);

LeaveRequestItem _request(String status, {int? currentStep = 2}) =>
    LeaveRequestItem.fromMap({
      'id': 41,
      'leave_type_km': 'ឈប់សម្រាករយៈពេលខ្លី',
      'start_date': '2026-10-29',
      'end_date': '2026-10-30',
      'requested_days': 2,
      'approved_days': status == 'approved' ? 2 : 0,
      'reason': 'ធ្វើដំណើរទៅលេងស្រុកកំណើត',
      'status': status,
      'handover_employee_name': 'ចាន់ សុភា',
      'attachment_url': 'https://example.test/a.pdf',
      'submitted_at': '2026-10-28T08:05:00',
      'workflow_current_step_order': currentStep,
      'workflow_current_step_name': 'ប្រធានមន្ទីរ',
      'workflow_current_actor_name': 'លោក ប្រធាន',
      'workflow_steps': [
        {
          'step_order': 1,
          'step_name': 'ប្រធានការិយាល័យ',
          'actor_name': 'លោក សុខ',
        },
        {'step_order': 2, 'step_name': 'ប្រធានមន្ទីរ', 'is_current': true},
        {'step_order': 3, 'step_name': 'ការិយាល័យបុគ្គលិក'},
      ],
    });

class _FakeLeaveService extends HomeLeaveService {
  final cancelled = <int>[];

  @override
  Future<void> cancelRequest({
    required AuthUser user,
    required int requestId,
  }) async {
    cancelled.add(requestId);
  }
}

Future<void> _pump(
  WidgetTester tester,
  LeaveRequestItem request, {
  _FakeLeaveService? service,
  VoidCallback? onCancelled,
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
      home: Builder(
        builder:
            (context) => Scaffold(
              body: Center(
                child: TextButton(
                  onPressed:
                      () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder:
                              (_) => LeaveDetailPage(
                                request: request,
                                language: const {},
                                leaveService: service ?? _FakeLeaveService(),
                                user: _user,
                                onCancelled: onCancelled,
                              ),
                        ),
                      ),
                  child: const Text('open'),
                ),
              ),
            ),
      ),
    ),
  );
  await tester.tap(find.text('open'));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('a pending request shows dates, details and where it waits', (
    tester,
  ) async {
    await _pump(tester, _request('pending'));

    expect(find.text('ព័ត៌មានសំណើ'), findsOneWidget);
    expect(find.text('កំពុងរង់ចាំ លោក ប្រធាន ពិនិត្យ'), findsOneWidget);
    // Date card: from 29, to 30 October, 2 days.
    expect(find.text('២៩'), findsOneWidget);
    expect(find.text('៣០'), findsOneWidget);
    expect(find.text('តុលា ២០២៦'), findsNWidgets(2));
    expect(find.text('ចាន់ សុភា'), findsOneWidget);
    expect(find.text('២៨ តុលា ២០២៦ · ០៨:០៥'), findsNWidgets(2));
    expect(find.text('ធ្វើដំណើរទៅលេងស្រុកកំណើត'), findsOneWidget);
    expect(find.text('មើលឯកសារ'), findsOneWidget);
    // Flow: step 1 done (with its approver), step 2 waiting, step 3 next.
    expect(find.text('លោក សុខ'), findsOneWidget);
    expect(find.text('កំពុងរង់ចាំ'), findsOneWidget);
    expect(find.text('៣'), findsOneWidget);
    expect(find.text('បោះបង់សំណើ'), findsOneWidget);
  });

  testWidgets('approved and rejected requests read as finished', (
    tester,
  ) async {
    await _pump(tester, _request('approved'));
    expect(find.text('សំណើត្រូវបានអនុម័ត'), findsOneWidget);
    expect(find.text('បានអនុម័ត'), findsOneWidget);
    expect(find.text('ចំនួនថ្ងៃបានអនុម័ត'), findsOneWidget);
    expect(find.text('កំពុងរង់ចាំ'), findsNothing);
    expect(find.text('បោះបង់សំណើ'), findsNothing);

    await tester.pumpWidget(const SizedBox());
    await _pump(tester, _request('rejected'));
    expect(find.text('សំណើត្រូវបានបដិសេធ'), findsOneWidget);
    expect(find.text('បានបដិសេធ'), findsOneWidget);
  });

  testWidgets('cancel asks first, then cancels and closes', (tester) async {
    final service = _FakeLeaveService();
    var refreshed = 0;
    await _pump(
      tester,
      _request('pending'),
      service: service,
      onCancelled: () => refreshed++,
    );

    await tester.tap(find.text('បោះបង់សំណើ'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('ទេ'));
    await tester.pumpAndSettle();
    expect(service.cancelled, isEmpty);

    await tester.tap(find.text('បោះបង់សំណើ'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('បោះបង់សំណើ').last);
    await tester.pumpAndSettle();
    expect(service.cancelled, [41]);
    expect(refreshed, 1);
    expect(find.byType(LeaveDetailPage), findsNothing);
  });

  testWidgets('fits a narrow screen at 2x text', (tester) async {
    await _pump(
      tester,
      _request('pending'),
      textScale: 2,
      size: const Size(320, 4000),
    );
    expect(tester.takeException(), isNull);
  });
}
