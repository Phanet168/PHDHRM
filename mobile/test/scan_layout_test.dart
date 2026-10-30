import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:staff_mobile_app/features/auth/models/auth_user.dart';
import 'package:staff_mobile_app/features/home/models/attendance_day_record.dart';
import 'package:staff_mobile_app/features/home/models/attendance_scan_result.dart';
import 'package:staff_mobile_app/features/home/pages/attendance_calendar_page.dart';
import 'package:staff_mobile_app/features/home/pages/attendance_filter_sheet.dart';
import 'package:staff_mobile_app/features/home/pages/attendance_scan_result_page.dart';
import 'package:staff_mobile_app/features/home/services/home_attendance_service.dart';
import 'package:staff_mobile_app/features/home/pages/home/home_dashboard_widgets.dart';
import 'package:staff_mobile_app/features/home/pages/home/home_nav_widgets.dart';
import 'package:staff_mobile_app/features/home/pages/home/home_profile_widgets.dart';
import 'package:staff_mobile_app/features/home/pages/scan/gps_zone_radar.dart';
import 'package:staff_mobile_app/features/home/pages/scan/qr_scan_overlay.dart';

/// Large system font + large display size (narrow logical width) must never
/// overflow the scan screens — Flutter reports any overflow as a test error.
Future<void> pumpLarge(WidgetTester tester, Widget child) async {
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
      home: child,
    ),
  );
  await tester.pump(const Duration(milliseconds: 500));
}

String _ymd(DateTime d) =>
    '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

/// A month of history up to today plus a few holidays, served offline.
class _FakeCalendarService extends HomeAttendanceService {
  @override
  Future<List<AttendanceDayRecord>> fetchAttendanceHistory(
    AuthUser user, {
    DateTime? fromDate,
    DateTime? toDate,
    int? start,
  }) async {
    final now = DateTime.now();
    return [
      for (var d = 1; d <= now.day; d++)
        AttendanceDayRecord.fromApi({
          'date': _ymd(DateTime(now.year, now.month, d)),
          'worked_minutes': d.isEven ? 480 : 0,
          'attendance_status': d.isEven ? 'on_time' : 'absent',
        }),
    ];
  }

  @override
  Future<List<Map<String, dynamic>>> fetchSchedule(
    AuthUser user, {
    required DateTime fromDate,
    required DateTime toDate,
  }) async {
    final now = DateTime.now();
    return [
      for (final d in [1, now.day, 28])
        {
          'date': _ymd(DateTime(now.year, now.month, d)),
          'is_holiday': true,
          'holiday_name': 'ទិវាប្រារព្ធពិធីគោរពព្រះវិញ្ញាណក្ខន្ធ ព្រះករុណា',
        },
    ];
  }
}

void main() {
  testWidgets('scan success page fits at 2x text and says check-out for OUT', (
    tester,
  ) async {
    await pumpLarge(
      tester,
      AttendanceScanResultPage(
        result: const AttendanceScanResult(
          status: 'ok',
          message: 'Attendance saved.',
          punchType: 'out',
          rangeMeters: 3.5,
          workplaceName: 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្តស្ទឹងត្រែង',
        ),
        language: const {},
        scannedAt: DateTime(2026, 10, 29, 16, 29, 39),
        latitude: 13.526254,
        longitude: 105.965125,
        scanType: 'GPS',
      ),
    );
    expect(find.text('អ្នកបានចុះវត្តមានចេញដោយជោគជ័យ'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('scan failure page fits at 2x text', (tester) async {
    await pumpLarge(
      tester,
      AttendanceScanResultPage(
        result: const AttendanceScanResult(
          status: 'error',
          message: 'You are outside the attendance area.',
          errorCode: 'out_of_range',
          rangeMeters: 629.4,
          acceptableRangeMeters: 500,
        ),
        language: const {},
        scannedAt: DateTime(2026, 10, 29, 15, 30),
        latitude: 13.5262811,
        longitude: 105.9651094,
        scanType: 'GPS',
      ),
    );
    expect(find.text('អ្នកនៅក្រៅតំបន់ស្កេនវត្តមាន'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('profile hero and sections fit a narrow screen at 2x text', (
    tester,
  ) async {
    await pumpLarge(
      tester,
      Scaffold(
        body: SingleChildScrollView(
          padding: const EdgeInsets.all(14),
          child: Column(
            children: [
              StaggeredEntrance(
                index: 0,
                child: ProfileHeroCard(
                  avatar: const CircleAvatar(radius: 44, child: Text('ល')),
                  name: 'លាង ផានិត្យ',
                  position: 'អនុប្រធានការិយាល័យ-ខេត្ត.ក្រុង',
                  department: 'ការិយាល័យរដ្ឋបាល-បុគ្គលិក',
                  role: 'Super Admin',
                  chips: const [
                    ProfileHighlightChip(
                      icon: Icons.badge_outlined,
                      label: 'អត្តលេខ',
                      value: '23307',
                    ),
                    ProfileHighlightChip(
                      icon: Icons.account_tree_outlined,
                      label: 'កាំប្រាក់',
                      value: 'ក.២-២',
                    ),
                    ProfileHighlightChip(
                      icon: Icons.calendar_month_outlined,
                      label: 'ថ្ងៃចូលបម្រើ',
                      value: '07-01-2012',
                    ),
                    ProfileHighlightChip(
                      icon: Icons.call_outlined,
                      label: 'ទំនាក់ទំនង',
                      value: 'phanet@gmail.com',
                    ),
                  ],
                  badges: const [
                    HomeInfoBadge(
                      icon: Icons.email_outlined,
                      text: 'phanet@gmail.com',
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              const ProfileSection(
                icon: Icons.person_outline,
                title: 'ព័ត៌មានផ្ទាល់ខ្លួន',
                subtitle: 'ព័ត៌មានបុគ្គល',
                initiallyExpanded: true,
                rows: [
                  HomeProfileRow(
                    label: 'ស្ថានភាពអាពាហ៍ពិពាហ៍',
                    value: 'រៀបការ',
                  ),
                  HomeProfileRow(label: 'ភេទ', value: 'ប្រុស'),
                ],
                subsections: [
                  ProfileSubsection(
                    label: 'អាសយដ្ឋាន',
                    rows: [
                      HomeProfileRow(
                        label: 'បច្ចុប្បន្ន',
                        value:
                            'ភូមិ ស្រែឫស្សី សង្កាត់ ស្ទឹងត្រែង ក្រុងស្ទឹងត្រែង ខេត្តស្ទឹងត្រែង',
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
    await tester.pump(const Duration(milliseconds: 600));
    expect(find.text('23307'), findsOneWidget);
    expect(find.text('រៀបការ'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('dashboard sections fit a narrow screen at 2x text', (
    tester,
  ) async {
    void noop() {}
    await pumpLarge(
      tester,
      Scaffold(
        body: ListView(
          children: [
            DashboardProfileHeader(
              appName: 'PHD HRM',
              organization: 'ការិយាល័យរដ្ឋបាល-បុគ្គលិក',
              name: 'លាង ផានិត្យ',
              position: 'អនុប្រធានការិយាល័យ-ខេត្ត.ក្រុង',
              initial: 'ល',
              unreadNotifications: 120,
              onMenuTap: noop,
              onNotificationsTap: noop,
              onAvatarTap: noop,
              onLanguageTap: noop,
              onHelpTap: noop,
              statusPrimaryLabel: 'Pay grade promotion',
              statusSecondaryLabel: 'មិនទាន់ចូលធ្វើការ',
              liveState: DashboardLiveState.working,
            ),
            DashboardWeekStrip(
              days: [
                for (var d = 26; d <= 31; d++)
                  DashboardWeekDay(
                    date: DateTime(2026, 10, d),
                    label: 'ព្រហស្បតិ៍',
                    percent: d == 29 ? 63 : 0,
                    isToday: d == 29,
                  ),
                DashboardWeekDay(
                  date: DateTime(2026, 11, 1),
                  label: 'អាទិត្យ',
                  percent: 100,
                  isToday: false,
                ),
              ],
            ),
            DashboardActionsGrid(
              actions: [
                DashboardActionItem(
                  icon: Icons.qr_code_scanner_rounded,
                  label: 'ស្កេន QR កូដវត្តមាន',
                  subtitle: 'ស្កេន QR ឬ GPS ដើម្បីចុះវត្តមានចូល/ចេញ',
                  color: const Color(0xFF6C5CE7),
                  badgeLabel: 'ONLINE',
                  onTap: noop,
                ),
                for (final label in [
                  'ដាក់សំណើសុំច្បាប់',
                  'លិខិតផ្លូវការ',
                  'ព័ត៌មានមន្ត្រី',
                  'បេសកកម្មរបស់ខ្ញុំ',
                ])
                  DashboardActionItem(
                    icon: Icons.article_rounded,
                    label: label,
                    color: const Color(0xFF00838F),
                    onTap: noop,
                  ),
              ],
            ),
            const DashboardWorkStatusCard(
              title: 'ស្ថានភាពការធ្វើការថ្ងៃនេះ',
              sessions: [],
              emptyLabel: 'មិនទាន់មានវេនបង្ហាញ',
              progressLabel: 'វឌ្ឍភាពការងារ',
              progressValue: '0.0ម៉ / 8ម៉',
              percent: 0.2,
              checkInLabel: 'ចូល',
              checkOutLabel: 'ចេញ',
              waitingLabel: 'រង់ចាំ',
              onTimeLabel: 'ទាន់ពេល',
              lateLabel: 'មកយឺត',
              earlyLeaveLabel: 'ចេញមុនម៉ោង',
            ),
          ],
        ),
      ),
    );
    await tester.pump(const Duration(seconds: 1));
    expect(find.text('លាង ផានិត្យ'), findsOneWidget);
    expect(find.text('99+'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('attendance calendar fits a narrow screen at 2x text', (
    tester,
  ) async {
    await pumpLarge(
      tester,
      AttendanceCalendarPage(
        user: AuthUser(
          employeeId: 7,
          userId: 9,
          name: 'Officer',
          email: 'test@example.test',
          userTypeId: 3,
        ),
        attendanceService: _FakeCalendarService(),
        language: const {},
        initialFilter: AttendanceFilterState.forMonth(DateTime.now()),
      ),
    );
    await tester.pump(const Duration(seconds: 1));
    expect(find.text('សង្ខេបប្រចាំខែ'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('bottom navigation fits a narrow screen at 2x text', (
    tester,
  ) async {
    final taps = <int>[];
    Future<void> record(int index) async => taps.add(index);
    await pumpLarge(
      tester,
      Scaffold(
        body: Column(
          children: [
            const Spacer(),
            HomeBottomNavigation(currentIndex: 0, onTap: record),
            HomeBottomNavigation(
              currentIndex: 4,
              showScanButton: false,
              onTap: record,
            ),
          ],
        ),
      ),
    );
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('ស្កេន'));
    await tester.tap(find.byIcon(Icons.qr_code_scanner_rounded));
    await tester.tap(find.text('ការងារ').first);
    expect(taps, [2, 2, 3]);
  });

  testWidgets('zone radar and pill fit a narrow screen at 2x text', (
    tester,
  ) async {
    await pumpLarge(
      tester,
      Scaffold(
        body: Column(
          children: [
            const GpsZoneRadar(
              size: 220,
              status: GpsZoneStatus.outside,
              offsetMeters: (east: -410, north: -477),
              radiusMeters: 500,
            ),
            SizedBox(
              width: 200,
              child: Align(
                alignment: Alignment.centerLeft,
                child: GpsZonePill(
                  status: GpsZoneStatus.outside,
                  label: 'នៅក្រៅតំបន់ · 629 m',
                ),
              ),
            ),
          ],
        ),
      ),
    );
    expect(tester.takeException(), isNull);
  });
}
