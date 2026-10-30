import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:staff_mobile_app/features/auth/models/auth_user.dart';
import 'package:staff_mobile_app/features/home/models/leave_request_models.dart';
import 'package:staff_mobile_app/features/home/pages/leave_review_page.dart';
import 'package:staff_mobile_app/features/home/services/home_leave_service.dart';

final _reviewer = AuthUser(
  employeeId: 1,
  userId: 25,
  name: 'Reviewer',
  email: 'r@example.test',
  userTypeId: 1,
);

LeaveRequestItem _request(int id, {int userId = 40, String type = ''}) =>
    LeaveRequestItem.fromMap({
      'id': id,
      'employee': {
        'full_name': 'សំនៀង ជួន',
        'employee_no': '000944',
        'user_id': userId,
      },
      'leave_type_km': type,
      'start_date': '2026-10-30',
      'end_date': '2026-10-31',
      'requested_days': 2,
      'reason': 'ធ្វើដំណើរទៅលេងស្រុកកំណើត',
      'status': 'pending',
      'handover_employee_name': 'ចាន់ សុភា',
      'attachment_url': 'https://example.test/a.pdf',
      'submitted_at': '2026-10-29T08:00:00+07:00',
      'workflow_steps': [
        {'step_order': 1, 'step_name': 'ប្រធានការិយាល័យ', 'is_current': false},
        {'step_order': 2, 'step_name': 'ប្រធានមន្ទីរ', 'is_current': true},
      ],
    });

class _FakeLeaveService extends HomeLeaveService {
  _FakeLeaveService(this.pending);
  List<LeaveRequestItem> pending;
  final reviews = <(int, String, String?)>[];

  @override
  Future<List<LeaveRequestItem>> fetchPendingReviews(AuthUser user) async =>
      pending;

  @override
  Future<String> reviewRequest({
    required AuthUser user,
    required int requestId,
    required String action,
    String? note,
  }) async {
    reviews.add((requestId, action, note));
    pending = pending.where((r) => r.id != requestId).toList();
    return 'Approved successfully';
  }
}

Future<void> _pump(
  WidgetTester tester,
  _FakeLeaveService service, {
  double textScale = 1,
  Size size = const Size(390, 1600),
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
      home: LeaveReviewPage(
        user: _reviewer,
        // The server's language map once turned the approve button into
        // "ពិនិត្យ/អនុម័តសំណើ"; the buttons must not depend on it.
        language: const {'approve_leave': 'ពិនិត្យ/អនុម័តសំណើ'},
        leaveService: service,
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('lists others\' pending requests with clear approve / reject', (
    tester,
  ) async {
    await _pump(
      tester,
      _FakeLeaveService([_request(1), _request(2, userId: 25)]),
    );
    expect(find.text('១ សំណើ'), findsOneWidget, reason: 'own request hidden');
    expect(find.text('សំនៀង ជួន'), findsOneWidget);
    expect(find.text('ប្រភេទច្បាប់មិនបញ្ជាក់'), findsOneWidget);
    expect(find.text('៣០ តុលា ២០២៦ → ៣១ តុលា ២០២៦'), findsOneWidget);
    expect(find.text('អនុម័ត'), findsOneWidget);
    expect(find.text('បដិសេធ'), findsOneWidget);
    expect(find.text('មើលឯកសារភ្ជាប់'), findsOneWidget);
    expect(find.text('ប្រធានមន្ទីរ'), findsOneWidget);
  });

  testWidgets('approve asks to confirm, sends the note and drops the card', (
    tester,
  ) async {
    final service = _FakeLeaveService([_request(1, type: 'ឈប់ប្រចាំឆ្នាំ')]);
    await _pump(tester, service);

    await tester.tap(find.text('បដិសេធ'));
    await tester.pumpAndSettle();
    expect(find.text('បដិសេធសំណើច្បាប់?'), findsOneWidget);
    await tester.tap(find.text('បោះបង់'));
    await tester.pumpAndSettle();
    expect(service.reviews, isEmpty);

    await tester.tap(find.text('អនុម័ត'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const ValueKey('review-note')), 'OK');
    await tester.tap(find.text('បញ្ជាក់ការអនុម័ត'));
    await tester.pumpAndSettle();
    expect(service.reviews.single, (1, 'approve', 'OK'));
    expect(find.text('សំនៀង ជួន'), findsNothing);
    expect(find.text('មិនមានសំណើរង់ចាំទេ'), findsOneWidget);
  });

  testWidgets('cards fit a narrow screen at 2x text', (tester) async {
    await _pump(
      tester,
      _FakeLeaveService([_request(1, type: 'ឈប់សម្រាករយៈពេលខ្លី')]),
      textScale: 2,
      size: const Size(320, 2400),
    );
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('អនុម័ត'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });
}
