import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:staff_mobile_app/features/auth/models/auth_user.dart';
import 'package:staff_mobile_app/features/home/models/attendance_day_record.dart';
import 'package:staff_mobile_app/features/home/pages/attendance_day_detail_page.dart';
import 'package:staff_mobile_app/features/home/pages/attendance_history_page.dart';
import 'package:staff_mobile_app/features/home/pages/home/attendance_status.dart';
import 'package:staff_mobile_app/features/home/services/home_attendance_service.dart';

final _today = DateTime(2026, 10, 30, 9);

Map<String, dynamic> _session(
  String day,
  String name,
  String sIn,
  String sOut, {
  String? inTime,
  String? outTime,
  int worked = 0,
  int late = 0,
  int early = 0,
  bool complete = true,
  bool duty = false,
  String? outDay,
}) => {
  'name': name,
  'is_duty': duty,
  'scheduled_in': '$day $sIn:00',
  'scheduled_out': '${outDay ?? day} $sOut:00',
  'in_time': inTime == null ? null : '$day $inTime:00',
  'out_time': outTime == null ? null : '${outDay ?? day} $outTime:00',
  'worked_minutes': worked,
  'overtime_minutes': 0,
  'late_minutes': late,
  'early_leave_minutes': early,
  'early_arrival_minutes': 0,
  'complete': complete,
  'punch_count': (inTime == null ? 0 : 1) + (outTime == null ? 0 : 1),
};

Map<String, dynamic> _day(
  int d,
  String status, {
  List<Map<String, dynamic>> sessions = const [],
  int worked = 0,
  int late = 0,
  int early = 0,
  bool provisional = false,
  String? holiday,
  String? note,
}) {
  final day = '2026-10-${d.toString().padLeft(2, '0')}';
  final ins = sessions.map((s) => s['in_time']).whereType<String>();
  final outs = sessions.map((s) => s['out_time']).whereType<String>();
  return {
    'date': day,
    'unit': {'id': 54, 'name': 'ការិយាល័យបុគ្គលិក'},
    'attendance_status': status,
    'is_provisional': provisional,
    'in_time': ins.isEmpty ? null : ins.first,
    'out_time': outs.isEmpty ? null : outs.last,
    'worked_minutes': worked,
    'overtime_minutes': 0,
    'late_minutes': late,
    'early_leave_minutes': early,
    'early_arrival_minutes': 0,
    'punch_count': ins.length + outs.length,
    'is_day_off': status == 'day_off',
    'is_holiday': status == 'holiday',
    'holiday_name': holiday,
    'adjustment_note': note,
    'shift': {'name': 'ម៉ោងរដ្ឋបាល', 'is_duty': false},
    'sessions': sessions,
  };
}

List<Map<String, dynamic>> _workday(
  int d, {
  String morningIn = '07:30',
  String? afternoonOut = '17:00',
  int late = 0,
  int early = 0,
}) {
  final day = '2026-10-${d.toString().padLeft(2, '0')}';
  return [
    _session(
      day,
      'morning',
      '07:30',
      '11:30',
      inTime: morningIn,
      outTime: '11:30',
      worked: 240 - late,
      late: late,
    ),
    _session(
      day,
      'afternoon',
      '14:00',
      '17:00',
      inTime: '14:00',
      outTime: afternoonOut,
      worked: afternoonOut == null ? 0 : 180 - early,
      early: early,
      complete: afternoonOut != null,
    ),
  ];
}

final _october = <Map<String, dynamic>>[
  _day(30, 'absent', provisional: true), // today, still under way
  _day(
    29,
    'late_and_early_leave',
    sessions: _workday(
      29,
      morningIn: '07:45',
      afternoonOut: '16:50',
      late: 15,
      early: 10,
    ),
    worked: 395,
    late: 15,
    early: 10,
    note: 'ម៉ាស៊ីនស្កេនខូច',
  ),
  _day(
    28,
    'early_leave',
    sessions: _workday(28, afternoonOut: '16:30', early: 30),
    worked: 390,
    early: 30,
  ),
  _day(
    27,
    'late',
    sessions: _workday(27, morningIn: '07:50', late: 20),
    worked: 400,
    late: 20,
  ),
  _day(26, 'on_time', sessions: _workday(26), worked: 420),
  _day(25, 'day_off'),
  _day(
    23,
    'incomplete',
    sessions: _workday(23, afternoonOut: null),
    worked: 240,
  ),
  _day(
    22,
    'absent',
    sessions: [
      _session('2026-10-22', 'morning', '07:30', '11:30', complete: false),
    ],
  ),
  _day(21, 'leave'),
  _day(20, 'mission'),
  _day(15, 'holiday', holiday: 'ព្រះរាជពិធីបុណ្យអុំទូក'),
];

class _FakeAttendanceService extends HomeAttendanceService {
  final requested = <DateTime>[];
  final reports = <(DateTime, DateTime)>[];
  final opened = <Uri>[];

  @override
  Future<Uri> reportUrl(
    AuthUser user, {
    required DateTime fromDate,
    required DateTime toDate,
  }) async {
    reports.add((fromDate, toDate));
    return Uri.parse('https://example.test/report');
  }

  @override
  Future<List<AttendanceDayRecord>> fetchAttendanceHistory(
    AuthUser user, {
    DateTime? fromDate,
    DateTime? toDate,
    int? start,
  }) async {
    requested.add(fromDate!);
    if (fromDate.month != 10) return const [];
    return _october.map(AttendanceDayRecord.fromApi).toList();
  }

  @override
  List<AttendanceDayRecord>? cachedAttendanceHistory(
    AuthUser user, {
    required DateTime fromDate,
    required DateTime toDate,
  }) => null;
}

final _user = AuthUser(
  employeeId: 7,
  userId: 9,
  name: 'Officer',
  email: 'o@example.test',
  userTypeId: 3,
);

Future<void> _pumpHistory(
  WidgetTester tester,
  _FakeAttendanceService service, {
  double textScale = 1,
  Size size = const Size(390, 6000),
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
      home: AttendanceHistoryPage(
        user: _user,
        attendanceService: service,
        language: const {},
        today: _today,
        openUrl: (url) async {
          service.opened.add(url);
          return true;
        },
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _pumpDetail(
  WidgetTester tester,
  Map<String, dynamic> day, {
  double textScale = 1,
  Size size = const Size(390, 3000),
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
      home: AttendanceDayDetailPage(record: AttendanceDayRecord.fromApi(day)),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  test('statuses read exactly as the server decides them', () {
    expect(attendanceStatusStyle('early_leave').label, 'ចេញមុនម៉ោង');
    expect(attendanceStatusStyle('early_leave').code, 'early_leave');
    expect(
      attendanceStatusStyle('late_and_early_leave').label,
      'មកយឺត និងចេញមុន',
    );
    // Still found by the "late" filter.
    expect(attendanceStatusStyle('late_and_early_leave').code, 'late');
    expect(attendanceStatusStyle('holiday').label, 'ថ្ងៃបុណ្យ');
    expect(attendanceStatusStyle('day_off').label, 'ថ្ងៃឈប់សម្រាក');
    expect(attendanceIsPresent('early_leave'), isTrue);
    expect(attendanceIsPresent('absent'), isFalse);

    final today = AttendanceDayRecord.fromApi(_october.first);
    expect(attendanceDayStyle(today).code, 'in_progress');
    final finished = AttendanceDayRecord.fromApi(_day(22, 'absent'));
    expect(attendanceDayStyle(finished).code, 'absent');
  });

  test('reads worked minutes, flags and a duty across midnight', () {
    final record = AttendanceDayRecord.fromApi({
      ..._day(15, 'holiday', holiday: 'បុណ្យ'),
      'worked_minutes': 840,
      'sessions': [
        _session(
          '2026-10-15',
          'duty',
          '17:00',
          '07:00',
          inTime: '17:00',
          outTime: '07:00',
          worked: 840,
          duty: true,
          outDay: '2026-10-16',
        ),
      ],
    });
    expect(record.workedMinutes, 840);
    expect(record.isHoliday, isTrue);
    expect(record.holidayName, 'បុណ្យ');
    expect(record.sessions.single.isDuty, isTrue);
    expect(record.sessions.single.scheduledMinutes, 840);
    expect(record.scheduledMinutes, 840);
  });

  testWidgets('history sums the month and filters by status', (tester) async {
    final service = _FakeAttendanceService();
    await _pumpHistory(tester, service);

    expect(find.text('ខែតុលា ២០២៦'), findsOneWidget);
    // 395 + 390 + 400 + 420 + 240 minutes.
    expect(find.text('៣០ម៉ោង ៤៥នាទី'), findsOneWidget);
    // Present: on time, late, early leave, late + early = 4 of 6 finished
    // working days (incomplete and the past absence count; today doesn't).
    expect(find.text('៦៧%'), findsOneWidget);
    expect(find.text('ព្រះរាជពិធីបុណ្យអុំទូក'), findsOneWidget);
    expect(find.text('ថ្ងៃនេះ'), findsOneWidget);
    expect(find.text('កំពុងបន្ត'), findsWidgets);
    expect(find.text('HR បានកែតម្រូវ'), findsOneWidget);
    expect(find.text('ខ្វះស្កេនចេញ'), findsOneWidget);

    // Only the finished absence, not today's open day.
    final absentChip = find.descendant(
      of: find.byType(SingleChildScrollView),
      matching: find.text('អវត្តមាន'),
    );
    await tester.ensureVisible(absentChip);
    await tester.pumpAndSettle();
    await tester.tap(absentChip);
    await tester.pumpAndSettle();
    expect(find.text('ព្រះរាជពិធីបុណ្យអុំទូក'), findsNothing);
    expect(find.text('ថ្ងៃនេះ'), findsNothing);
    expect(find.text('២២'), findsOneWidget);

    // The month arrows load the previous month; this is the latest one.
    expect(
      tester
          .widget<IconButton>(
            find.widgetWithIcon(IconButton, Icons.chevron_right_rounded),
          )
          .onPressed,
      isNull,
    );
    await tester.tap(find.byTooltip('ខែមុន'));
    await tester.pumpAndSettle();
    expect(service.requested.last, DateTime(2026, 9));
    expect(find.text('ខែកញ្ញា ២០២៦'), findsOneWidget);
    expect(
      find.text('មិនទាន់មានទិន្នន័យវត្តមានតាមលក្ខខណ្ឌនេះ'),
      findsOneWidget,
    );
  });

  testWidgets('the report button asks for this period\'s report link', (
    tester,
  ) async {
    final service = _FakeAttendanceService();
    await _pumpHistory(tester, service);
    await tester.tap(find.text('ទាញយករបាយការណ៍'));
    await tester.pumpAndSettle();
    expect(service.reports.single, (
      DateTime(2026, 10),
      DateTime(2026, 10, 31),
    ));
    expect(service.opened.single, Uri.parse('https://example.test/report'));
    expect(find.textContaining('កំពុងអភិវឌ្ឍ'), findsNothing);
  });

  testWidgets('a day card opens its detail', (tester) async {
    await _pumpHistory(tester, _FakeAttendanceService());
    await tester.tap(find.text('២៩'));
    await tester.pumpAndSettle();
    expect(find.byType(AttendanceDayDetailPage), findsOneWidget);
  });

  testWidgets('detail explains the day like the server', (tester) async {
    await _pumpDetail(tester, _october[1]);
    expect(find.text('ថ្ងៃព្រហស្បតិ៍ ២៩ តុលា ២០២៦'), findsOneWidget);
    expect(find.text('មកយឺត និងចេញមុន'), findsOneWidget);
    expect(find.text('មកយឺត ១៥នាទី · ចេញមុន ១០នាទី'), findsOneWidget);
    expect(find.text('ការិយាល័យបុគ្គលិក'), findsOneWidget);
    expect(find.text('វេនព្រឹក'), findsOneWidget);
    expect(find.text('កាលវិភាគ ០៧:៣០ – ១១:៣០'), findsOneWidget);
    expect(find.text('យឺត ១៥នាទី'), findsOneWidget);
    expect(find.text('ចេញមុន ១០នាទី'), findsOneWidget);
    expect(find.text('ក្នុងចំណោម ៧ម៉ោង'), findsOneWidget);
    expect(find.text('ម៉ាស៊ីនស្កេនខូច'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await _pumpDetail(tester, _october[0]);
    expect(find.text('កំពុងបន្ត'), findsOneWidget);
    expect(find.textContaining('មិនទាន់ចប់'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await _pumpDetail(tester, _october.last);
    expect(find.text('ថ្ងៃបុណ្យ'), findsOneWidget);
    expect(find.text('ព្រះរាជពិធីបុណ្យអុំទូក'), findsOneWidget);
  });

  testWidgets('history and detail fit a narrow screen at 2x text', (
    tester,
  ) async {
    await _pumpHistory(
      tester,
      _FakeAttendanceService(),
      textScale: 2,
      size: const Size(320, 9000),
    );
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
    await _pumpDetail(
      tester,
      _october[1],
      textScale: 2,
      size: const Size(320, 5000),
    );
    expect(tester.takeException(), isNull);
  });
}
