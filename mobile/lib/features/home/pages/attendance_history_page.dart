import 'package:flutter/material.dart';
import 'package:motion_tab_bar_v2/motion-tab-bar.dart';

import '../../auth/models/auth_user.dart';
import '../models/attendance_day_record.dart';
import 'attendance_scan_page.dart';
import '../services/home_attendance_service.dart';

// ─── Design tokens ────────────────────────────────────────────────────────────
const _kBg = Color(0xFFF6F7FB);
const _kCardBg = Colors.white;
const _kNavy = Color(0xFF0F1D2E);
const _kGray = Color(0xFF8A9BB5);
const _kDivider = Color(0xFFEAEFF6);

// Status colours
const _kHolidayBg = Color(0xFFFFEBEB);
const _kHolidayFill = Color(0xFFEF5350);
const _kLeaveBg = Color(0xFFFFF0E6);
const _kLeaveText = Color(0xFFBF4802);
const _kMissionBg = Color(0xFFE8F0FE);
const _kMissionText = Color(0xFF1A56DB);
const _kDayOffBg = Color(0xFFF0F0F5);
const _kDayOffText = Color(0xFF616E8A);
const _kOnTimeDot = Color(0xFF2E7D32);
const _kLateDot = Color(0xFFE65100);
const _kIncompleteDot = Color(0xFFD32F2F);
const _kAbsentDot = Color(0xFFD32F2F);
const _kEarlyLeaveDot = Color(0xFFD07A00);
const _kSelectedFill = Color(0xFF1565C0);
const _kTodayDot = Color(0xFF1565C0);
// ─────────────────────────────────────────────────────────────────────────────

class AttendanceHistoryPage extends StatefulWidget {
  const AttendanceHistoryPage({
    super.key,
    required this.user,
    required this.attendanceService,
    required this.language,
  });

  final AuthUser user;
  final HomeAttendanceService attendanceService;
  final Map<String, String> language;

  @override
  State<AttendanceHistoryPage> createState() => _AttendanceHistoryPageState();
}

class _AttendanceHistoryPageState extends State<AttendanceHistoryPage> {
  late DateTime _selectedMonth;
  late Future<List<AttendanceDayRecord>> _recordsFuture;
  DateTime? _selectedDay;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _selectedMonth = DateTime(now.year, now.month, 1);
    _selectedDay = DateTime(now.year, now.month, now.day);
    _recordsFuture = _loadRecords();
  }

  @override
  void dispose() {
    super.dispose();
  }

  Future<List<AttendanceDayRecord>> _loadRecords() {
    final fromDate = DateTime(_selectedMonth.year, _selectedMonth.month, 1);
    final toDate = DateTime(_selectedMonth.year, _selectedMonth.month + 1, 0);
    return widget.attendanceService.fetchAttendanceHistory(
      widget.user,
      fromDate: fromDate,
      toDate: toDate,
    );
  }

  Future<void> _openAttendanceScanner() async {
    if (!widget.user.hasEmployee) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'គណនីនេះមិនទាន់ភ្ជាប់ប្រវត្តិបុគ្គលិក ដូច្នេះមិនអាចស្កេនវត្តមានបានទេ។',
          ),
        ),
      );
      return;
    }

    final shouldRefresh = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder:
            (_) => AttendanceScanPage(
              user: widget.user,
              attendanceService: widget.attendanceService,
              language: widget.language,
            ),
      ),
    );

    if (shouldRefresh == true && mounted) {
      setState(() {
        _recordsFuture = _loadRecords();
      });
    }
  }

  // ─── Helpers ────────────────────────────────────────────────────────────────

  static const _kMonthNames = [
    'មករា',
    'កុម្ភៈ',
    'មីនា',
    'មេសា',
    'ឧសភា',
    'មិថុនា',
    'កក្កដា',
    'សីហា',
    'កញ្ញា',
    'តុលា',
    'វិច្ឆិកា',
    'ធ្នូ',
  ];

  static const _kWeekdayNames = [
    'ចន្ទ',
    'អង្គារ',
    'ពុធ',
    'ព្រហស្បតិ៍',
    'សុក្រ',
    'សៅរ៍',
    'អាទិត្យ',
  ];

  String _monthName(int m) => _kMonthNames[m - 1];

  bool _isToday(int day) {
    final now = DateTime.now();
    return now.year == _selectedMonth.year &&
        now.month == _selectedMonth.month &&
        now.day == day;
  }

  bool _isSelected(int day) {
    final sel = _selectedDay;
    if (sel == null) return false;
    return sel.year == _selectedMonth.year &&
        sel.month == _selectedMonth.month &&
        sel.day == day;
  }

  _CellStyle _cellStyle(AttendanceDayRecord? record, int day) {
    if (record == null) return const _CellStyle();
    final code = record.attendanceStatus?.trim().toLowerCase() ?? '';
    switch (code) {
      case 'holiday':
      case 'h':
        return const _CellStyle(bgColor: _kHolidayBg, textColor: _kHolidayFill);
      case 'leave':
      case 'lv':
        return const _CellStyle(bgColor: _kLeaveBg, textColor: _kLeaveText);
      case 'mission':
      case 'm':
        return const _CellStyle(bgColor: _kMissionBg, textColor: _kMissionText);
      case 'day_off':
      case 'd':
        return const _CellStyle(bgColor: _kDayOffBg, textColor: _kDayOffText);
      case 'on_time':
      case 'present':
      case 'p':
        return const _CellStyle(dotColor: _kOnTimeDot);
      case 'late':
      case 'late_and_early_leave':
        return const _CellStyle(dotColor: _kLateDot);
      case 'early_leave':
        return const _CellStyle(dotColor: _kEarlyLeaveDot);
      case 'incomplete':
        return const _CellStyle(dotColor: _kIncompleteDot);
      case 'absent':
      case 'a':
        return const _CellStyle(dotColor: _kAbsentDot);
      default:
        return const _CellStyle();
    }
  }

  String _statusLabel(String? code) {
    switch (code?.trim().toLowerCase()) {
      case 'on_time':
        return 'ទាន់ពេល';
      case 'late':
        return 'មកយឺត';
      case 'early_leave':
        return 'ចេញមុន';
      case 'late_and_early_leave':
        return 'យឺត & ចេញមុន';
      case 'incomplete':
        return 'មិនគ្រប់';
      case 'mission':
      case 'm':
        return 'បេសកកម្ម';
      case 'leave':
      case 'lv':
        return 'សុំច្បាប់';
      case 'absent':
      case 'a':
        return 'អវត្តមាន';
      case 'holiday':
      case 'h':
        return 'ថ្ងៃឈប់';
      case 'day_off':
      case 'd':
        return 'ថ្ងៃឈប់';
      default:
        return '-';
    }
  }

  Color _statusColor(String? code) {
    switch (code?.trim().toLowerCase()) {
      case 'on_time':
      case 'present':
      case 'p':
        return _kOnTimeDot;
      case 'late':
      case 'late_and_early_leave':
        return _kLateDot;
      case 'early_leave':
        return _kEarlyLeaveDot;
      case 'incomplete':
        return _kIncompleteDot;
      case 'mission':
      case 'm':
        return _kMissionText;
      case 'leave':
      case 'lv':
        return _kLeaveText;
      case 'absent':
      case 'a':
        return _kAbsentDot;
      case 'holiday':
      case 'h':
        return _kHolidayFill;
      case 'day_off':
      case 'd':
        return _kDayOffText;
      default:
        return _kGray;
    }
  }

  String _weekdayLabel(DateTime d) => _kWeekdayNames[d.weekday - 1];

  String _formatDate(DateTime d) {
    final dd = d.day.toString().padLeft(2, '0');
    final mm = d.month.toString().padLeft(2, '0');
    return '$dd-$mm-${d.year}';
  }

  String _dateKey(int y, int m, int d) =>
      '$y-${m.toString().padLeft(2, '0')}-${d.toString().padLeft(2, '0')}';

  String _normalizedStatus(String? code) {
    return code?.trim().toLowerCase() ?? '';
  }

  bool _isHolidayOrDayOff(String? code) {
    final normalized = _normalizedStatus(code);
    return normalized == 'holiday' ||
        normalized == 'h' ||
        normalized == 'day_off' ||
        normalized == 'd';
  }

  bool _isMissionStatus(String? code) {
    final normalized = _normalizedStatus(code);
    return normalized == 'mission' || normalized == 'm';
  }

  bool _isLeaveStatus(String? code) {
    final normalized = _normalizedStatus(code);
    return normalized == 'leave' || normalized == 'lv';
  }

  bool _isWorkingStatus(String? code) {
    final normalized = _normalizedStatus(code);
    return normalized == 'on_time' ||
        normalized == 'present' ||
        normalized == 'p' ||
        normalized == 'late' ||
        normalized == 'early_leave' ||
        normalized == 'late_and_early_leave' ||
        normalized == 'incomplete';
  }

  double _parseWorkedHours(String raw) {
    final text = raw.trim();
    if (text.isEmpty || text == '-') {
      return 0;
    }

    if (text.contains(':')) {
      final parts = text.split(':');
      if (parts.length >= 2) {
        final hour = int.tryParse(parts[0].trim()) ?? 0;
        final minute = int.tryParse(parts[1].trim()) ?? 0;
        final second =
            parts.length >= 3 ? (int.tryParse(parts[2].trim()) ?? 0) : 0;
        return hour + (minute / 60) + (second / 3600);
      }
    }

    return double.tryParse(text) ?? 0;
  }

  String _formatDecimalHours(double value) {
    final rounded = value.toStringAsFixed(1);
    if (rounded.endsWith('.0')) {
      return rounded.substring(0, rounded.length - 2);
    }
    return rounded;
  }

  String _activityTypeLabel(String? code) {
    if (_isHolidayOrDayOff(code)) {
      return 'ថ្ងៃសម្រាក';
    }
    if (_isMissionStatus(code)) {
      return 'បេសកកម្ម';
    }
    if (_isLeaveStatus(code)) {
      return 'សុំច្បាប់';
    }
    if (_isWorkingStatus(code)) {
      return 'ថ្ងៃធ្វើការ';
    }

    final normalized = _normalizedStatus(code);
    if (normalized == 'absent' || normalized == 'a') {
      return 'អវត្តមាន';
    }

    return 'សកម្មភាព';
  }

  void _showDayDetail(AttendanceDayRecord record) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        final code = record.attendanceStatus;
        final color = _statusColor(code);
        final label = _statusLabel(code);
        DateTime date;
        try {
          date = DateTime.parse(record.date);
        } catch (_) {
          date = DateTime.now();
        }

        return DraggableScrollableSheet(
          initialChildSize: 0.6,
          minChildSize: 0.4,
          maxChildSize: 0.85,
          expand: false,
          builder: (context, controller) {
            return Container(
              decoration: const BoxDecoration(
                color: _kBg,
                borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              ),
              child: ListView(
                controller: controller,
                padding: EdgeInsets.fromLTRB(
                  16,
                  10,
                  16,
                  MediaQuery.of(context).padding.bottom + 24,
                ),
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 5,
                      decoration: BoxDecoration(
                        color: const Color(0xFFCDD5E0),
                        borderRadius: BorderRadius.circular(99),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'ព័ត៌មានលម្អិតវត្តមាន',
                              style: TextStyle(
                                fontSize: 17,
                                fontWeight: FontWeight.w900,
                                color: _kNavy,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${_formatDate(date)} \u00b7 ${_weekdayLabel(date)}',
                              style: const TextStyle(
                                fontSize: 13,
                                color: _kGray,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: color.withAlpha(24),
                          borderRadius: BorderRadius.circular(99),
                        ),
                        child: Text(
                          label,
                          style: TextStyle(
                            color: color,
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  _DetailSheet(
                    timeIn: record.timeIn,
                    timeOut: record.timeOut,
                    lateMinutes: record.lateMinutes ?? 0,
                    earlyLeaveMinutes: record.earlyLeaveMinutes ?? 0,
                    punchCount: record.punchCount,
                    totalHours: record.totalHours,
                    exceptionReason: record.exceptionReason,
                    hasException: record.hasException == true,
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _refresh() async {
    setState(() {
      _recordsFuture = _loadRecords();
    });
    await _recordsFuture;
  }

  @override
  Widget build(BuildContext context) {
    final bottomPad = MediaQuery.of(context).padding.bottom;

    return Scaffold(
      backgroundColor: _kBg,
      appBar: AppBar(
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        backgroundColor: _kCardBg,
        surfaceTintColor: Colors.transparent,
        leading: Padding(
          padding: const EdgeInsets.only(left: 12),
          child: IconButton(
            onPressed: () => Navigator.of(context).maybePop(),
            icon: const Icon(Icons.arrow_back_ios_rounded, size: 20),
            style: IconButton.styleFrom(
              backgroundColor: _kBg,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
        ),
        title: const Text(
          'ប្រតិទិនវត្តមាន',
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w900,
            color: _kNavy,
          ),
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: IconButton(
              onPressed: _refresh,
              icon: const Icon(Icons.refresh_rounded, size: 20),
              style: IconButton.styleFrom(
                backgroundColor: _kBg,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ),
        ],
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(1),
          child: Divider(height: 1, thickness: 1, color: _kDivider),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<AttendanceDayRecord>>(
          future: _recordsFuture,
          builder: (context, snapshot) {
            final isLoading =
                snapshot.connectionState == ConnectionState.waiting;
            final records = snapshot.data ?? const <AttendanceDayRecord>[];
            final recordMap = <String, AttendanceDayRecord>{
              for (final r in records)
                (r.date.length >= 10 ? r.date.substring(0, 10) : r.date): r,
            };

            final activities = List<AttendanceDayRecord>.from(records)
              ..sort((a, b) => b.date.compareTo(a.date));

            final workingDays = activities
                .where((r) => _isWorkingStatus(r.attendanceStatus))
                .toList(growable: false);
            final holidayDays =
                activities
                    .where((r) => _isHolidayOrDayOff(r.attendanceStatus))
                    .length;
            final missionDays =
                activities
                    .where((r) => _isMissionStatus(r.attendanceStatus))
                    .length;
            final totalWorkedHours = workingDays.fold<double>(
              0,
              (sum, record) => sum + _parseWorkedHours(record.totalHours),
            );
            final averageDailyHours =
                workingDays.isEmpty
                    ? 0.0
                    : totalWorkedHours / workingDays.length;

            return ListView(
              padding: EdgeInsets.fromLTRB(14, 12, 14, bottomPad + 24),
              children: [
                // ── Month selector ─────────────────────────────────────────
                _MonthSelectorCard(
                  monthName: _monthName(_selectedMonth.month),
                  year: _selectedMonth.year,
                  onPrev:
                      () => setState(() {
                        _selectedMonth = DateTime(
                          _selectedMonth.year,
                          _selectedMonth.month - 1,
                          1,
                        );
                        _selectedDay = null;
                        _recordsFuture = _loadRecords();
                      }),
                  onNext: () {
                    final now = DateTime.now();
                    final next = DateTime(
                      _selectedMonth.year,
                      _selectedMonth.month + 1,
                      1,
                    );
                    if (next.isBefore(DateTime(now.year, now.month + 1, 1))) {
                      setState(() {
                        _selectedMonth = next;
                        _selectedDay = null;
                        _recordsFuture = _loadRecords();
                      });
                    }
                  },
                ),
                const SizedBox(height: 10),

                // ── Legend ─────────────────────────────────────────────────
                const _LegendRow(),
                const SizedBox(height: 10),

                // ── Calendar card ──────────────────────────────────────────
                Container(
                  decoration: BoxDecoration(
                    color: _kCardBg,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: const [
                      BoxShadow(
                        color: Color(0x0D14202B),
                        blurRadius: 18,
                        offset: Offset(0, 6),
                      ),
                    ],
                  ),
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(8, 12, 8, 14),
                    child: Column(
                      children: [
                        const _WeekdayHeader(),
                        const SizedBox(height: 6),
                        if (isLoading)
                          const Padding(
                            padding: EdgeInsets.symmetric(vertical: 40),
                            child: Center(child: CircularProgressIndicator()),
                          )
                        else
                          _CalendarGrid(
                            year: _selectedMonth.year,
                            month: _selectedMonth.month,
                            recordMap: recordMap,
                            cellStyleFn: _cellStyle,
                            isToday: _isToday,
                            isSelected: _isSelected,
                            onDayTap: (day) {
                              setState(() {
                                _selectedDay = DateTime(
                                  _selectedMonth.year,
                                  _selectedMonth.month,
                                  day,
                                );
                              });
                              final key = _dateKey(
                                _selectedMonth.year,
                                _selectedMonth.month,
                                day,
                              );
                              final rec = recordMap[key];
                              if (rec != null) _showDayDetail(rec);
                            },
                          ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),

                // ── Selected day summary ───────────────────────────────────
                if (_selectedDay != null)
                  Builder(
                    builder: (context) {
                      final key = _dateKey(
                        _selectedDay!.year,
                        _selectedDay!.month,
                        _selectedDay!.day,
                      );
                      final rec = recordMap[key];
                      return _SelectedDayCard(
                        date: _selectedDay!,
                        record: rec,
                        statusLabel: _statusLabel(rec?.attendanceStatus),
                        statusColor: _statusColor(rec?.attendanceStatus),
                        weekdayLabel: _weekdayLabel(_selectedDay!),
                      );
                    },
                  ),
                const SizedBox(height: 12),

                // Daily activity summary & list
                if (snapshot.hasError)
                  _ErrorCard(message: '${snapshot.error}', onRetry: _refresh)
                else if (!isLoading) ...[
                  Row(
                    children: [
                      const Text(
                        'ព័ត៌មានសកម្មភាពប្រចាំថ្ងៃ',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w900,
                          color: _kNavy,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        '${activities.length} ថ្ងៃ',
                        style: const TextStyle(
                          fontSize: 12,
                          color: _kGray,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  _ActivityOverviewCard(
                    holidayDays: holidayDays,
                    missionDays: missionDays,
                    workingDays: workingDays.length,
                    totalWorkedHoursLabel: _formatDecimalHours(
                      totalWorkedHours,
                    ),
                    averageDailyHoursLabel: _formatDecimalHours(
                      averageDailyHours,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (activities.isEmpty)
                    const _EmptyEventsCard(
                      message: 'មិនទាន់មានទិន្នន័យសកម្មភាពប្រចាំថ្ងៃក្នុងខែនេះ',
                    )
                  else
                    ...activities.map(
                      (r) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _EventCard(
                          record: r,
                          activityTypeLabel: _activityTypeLabel(
                            r.attendanceStatus,
                          ),
                          statusLabel: _statusLabel(r.attendanceStatus),
                          statusColor: _statusColor(r.attendanceStatus),
                          onView: () => _showDayDetail(r),
                        ),
                      ),
                    ),
                ],
              ],
            );
          },
        ),
      ),
      bottomNavigationBar: _CalendarBottomNav(
        onHomeTap: () => Navigator.of(context).maybePop(),
        onScanTap: _openAttendanceScanner,
      ),
    );
  }
}

// ─── Cell style ───────────────────────────────────────────────────────────────
class _CellStyle {
  const _CellStyle({this.bgColor, this.textColor, this.dotColor});

  final Color? bgColor;
  final Color? textColor;
  final Color? dotColor;
}

// ─── Month selector ───────────────────────────────────────────────────────────
class _MonthSelectorCard extends StatelessWidget {
  const _MonthSelectorCard({
    required this.monthName,
    required this.year,
    required this.onPrev,
    required this.onNext,
  });

  final String monthName;
  final int year;
  final VoidCallback onPrev;
  final VoidCallback onNext;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
      decoration: BoxDecoration(
        color: _kCardBg,
        borderRadius: BorderRadius.circular(18),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0814202B),
            blurRadius: 14,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          _NavArrow(icon: Icons.chevron_left_rounded, onTap: onPrev),
          Expanded(
            child: RichText(
              textAlign: TextAlign.center,
              text: TextSpan(
                children: [
                  TextSpan(
                    text: 'ខែ $monthName ',
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF1A56DB),
                    ),
                  ),
                  TextSpan(
                    text: 'ឆ្នាំ$year',
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF3D5088),
                    ),
                  ),
                ],
              ),
            ),
          ),
          _NavArrow(icon: Icons.chevron_right_rounded, onTap: onNext),
        ],
      ),
    );
  }
}

class _NavArrow extends StatelessWidget {
  const _NavArrow({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFFF0F3FA),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Icon(icon, size: 22, color: const Color(0xFF3D5088)),
        ),
      ),
    );
  }
}

// ─── Legend row ───────────────────────────────────────────────────────────────
class _LegendRow extends StatelessWidget {
  const _LegendRow();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: _kCardBg,
        borderRadius: BorderRadius.circular(14),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0814202B),
            blurRadius: 8,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: const Wrap(
        spacing: 14,
        runSpacing: 6,
        children: [
          _LegendDot(color: _kHolidayFill, label: 'ថ្ងៃឈប់'),
          _LegendDot(color: _kLeaveText, label: 'សុំច្បាប់'),
          _LegendDot(color: _kMissionText, label: 'បេសកកម្ម'),
          _LegendDot(color: _kOnTimeDot, label: 'ទាន់ពេល'),
          _LegendDot(color: _kLateDot, label: 'យឺត'),
          _LegendDot(color: _kTodayDot, label: 'ថ្ងៃនេះ', outlined: true),
        ],
      ),
    );
  }
}

class _LegendDot extends StatelessWidget {
  const _LegendDot({
    required this.color,
    required this.label,
    this.outlined = false,
  });

  final Color color;
  final String label;
  final bool outlined;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: outlined ? Colors.transparent : color,
            border: outlined ? Border.all(color: color, width: 2) : null,
          ),
        ),
        const SizedBox(width: 5),
        Text(
          label,
          style: const TextStyle(
            fontSize: 11.5,
            fontWeight: FontWeight.w600,
            color: Color(0xFF4A5568),
          ),
        ),
      ],
    );
  }
}

// ─── Weekday header ───────────────────────────────────────────────────────────
class _WeekdayHeader extends StatelessWidget {
  const _WeekdayHeader();

  // Sun … Sat display labels
  static const _labels = ['អា', 'ចន្ទ', 'អង្', 'ពុ', 'ព្រ', 'សុ', 'សៅ'];

  @override
  Widget build(BuildContext context) {
    return Row(
      children: List.generate(7, (i) {
        final isWeekend = i == 0 || i == 6;
        return Expanded(
          child: Center(
            child: Text(
              _labels[i],
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color:
                    isWeekend
                        ? const Color(0xFFD32F2F)
                        : const Color(0xFF64748B),
              ),
            ),
          ),
        );
      }),
    );
  }
}

// ─── Calendar grid ────────────────────────────────────────────────────────────
class _CalendarGrid extends StatelessWidget {
  const _CalendarGrid({
    required this.year,
    required this.month,
    required this.recordMap,
    required this.cellStyleFn,
    required this.isToday,
    required this.isSelected,
    required this.onDayTap,
  });

  final int year;
  final int month;
  final Map<String, AttendanceDayRecord> recordMap;
  final _CellStyle Function(AttendanceDayRecord? record, int day) cellStyleFn;
  final bool Function(int day) isToday;
  final bool Function(int day) isSelected;
  final void Function(int day) onDayTap;

  @override
  Widget build(BuildContext context) {
    // weekday % 7 → Sun=0, Mon=1 … Sat=6
    final firstOffset = DateTime(year, month, 1).weekday % 7;
    final daysInMonth = DateTime(year, month + 1, 0).day;
    final totalSlots = ((firstOffset + daysInMonth) / 7).ceil() * 7;

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 7,
        mainAxisSpacing: 4,
        crossAxisSpacing: 2,
        childAspectRatio: 0.82,
      ),
      itemCount: totalSlots,
      itemBuilder: (context, index) {
        final day = index - firstOffset + 1;
        if (day < 1 || day > daysInMonth) return const SizedBox.shrink();
        final key =
            '$year-${month.toString().padLeft(2, '0')}-${day.toString().padLeft(2, '0')}';
        final record = recordMap[key];
        final style = cellStyleFn(record, day);
        final col = index % 7;
        return _CalendarCell(
          day: day,
          style: style,
          isToday: isToday(day),
          isSelected: isSelected(day),
          isWeekend: col == 0 || col == 6,
          onTap: () => onDayTap(day),
        );
      },
    );
  }
}

// ─── Calendar cell ────────────────────────────────────────────────────────────
class _CalendarCell extends StatelessWidget {
  const _CalendarCell({
    required this.day,
    required this.style,
    required this.isToday,
    required this.isSelected,
    required this.isWeekend,
    required this.onTap,
  });

  final int day;
  final _CellStyle style;
  final bool isToday;
  final bool isSelected;
  final bool isWeekend;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final textColor =
        isSelected
            ? Colors.white
            : (style.textColor ??
                (isWeekend
                    ? const Color(0xFFD32F2F)
                    : const Color(0xFF1E2D3D)));

    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        margin: const EdgeInsets.all(1.5),
        decoration: BoxDecoration(
          color:
              isSelected
                  ? _kSelectedFill
                  : (style.bgColor ?? Colors.transparent),
          borderRadius: BorderRadius.circular(10),
          border:
              isToday && !isSelected
                  ? Border.all(color: _kTodayDot, width: 1.8)
                  : null,
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              '$day',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: textColor,
                height: 1.1,
              ),
            ),
            if (isToday && !isSelected)
              Container(
                width: 5,
                height: 5,
                margin: const EdgeInsets.only(top: 2),
                decoration: const BoxDecoration(
                  color: _kTodayDot,
                  shape: BoxShape.circle,
                ),
              )
            else if (style.dotColor != null && !isSelected)
              Container(
                width: 5,
                height: 5,
                margin: const EdgeInsets.only(top: 2),
                decoration: BoxDecoration(
                  color: style.dotColor,
                  shape: BoxShape.circle,
                ),
              )
            else
              const SizedBox(height: 7),
          ],
        ),
      ),
    );
  }
}

// ─── Selected day card ────────────────────────────────────────────────────────
class _SelectedDayCard extends StatelessWidget {
  const _SelectedDayCard({
    required this.date,
    required this.record,
    required this.statusLabel,
    required this.statusColor,
    required this.weekdayLabel,
  });

  final DateTime date;
  final AttendanceDayRecord? record;
  final String statusLabel;
  final Color statusColor;
  final String weekdayLabel;

  @override
  Widget build(BuildContext context) {
    final rec = record;
    final dd = date.day.toString().padLeft(2, '0');
    final mm = date.month.toString().padLeft(2, '0');
    final dateStr = '$dd-$mm-${date.year}';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _kCardBg,
        borderRadius: BorderRadius.circular(18),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0D14202B),
            blurRadius: 14,
            offset: Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.event_note_outlined, size: 16, color: _kGray),
              const SizedBox(width: 6),
              const Text(
                'ថ្ងៃដែលបានជ្រើស',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: _kGray,
                ),
              ),
              const Spacer(),
              if (statusLabel != '-')
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: statusColor.withAlpha(22),
                    borderRadius: BorderRadius.circular(99),
                  ),
                  child: Text(
                    statusLabel,
                    style: TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w800,
                      color: statusColor,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            '$weekdayLabel ទី $dateStr',
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w900,
              color: _kNavy,
            ),
          ),
          if (rec != null) ...[
            const SizedBox(height: 10),
            const Divider(height: 1, color: _kDivider),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: _MiniInfoTile(
                    icon: Icons.login_rounded,
                    label: 'ចូល',
                    value: rec.timeIn,
                  ),
                ),
                Container(width: 1, height: 36, color: _kDivider),
                Expanded(
                  child: _MiniInfoTile(
                    icon: Icons.logout_rounded,
                    label: 'ចេញ',
                    value: rec.timeOut,
                  ),
                ),
                Container(width: 1, height: 36, color: _kDivider),
                Expanded(
                  child: _MiniInfoTile(
                    icon: Icons.schedule_outlined,
                    label: 'ម៉ោង',
                    value: rec.totalHours,
                  ),
                ),
              ],
            ),
            if ((rec.lateMinutes ?? 0) > 0) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(
                    Icons.warning_amber_rounded,
                    size: 14,
                    color: _kLateDot,
                  ),
                  const SizedBox(width: 5),
                  Text(
                    'មកយឺត ${rec.lateMinutes} នាទី',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: _kLateDot,
                    ),
                  ),
                ],
              ),
            ],
          ] else ...[
            const SizedBox(height: 8),
            const Text(
              'មិនទាន់មានទិន្នន័យសម្រាប់ថ្ងៃនេះ',
              style: TextStyle(
                fontSize: 13,
                color: _kGray,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _MiniInfoTile extends StatelessWidget {
  const _MiniInfoTile({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Icon(icon, size: 15, color: _kGray),
        const SizedBox(height: 3),
        Text(
          label,
          style: const TextStyle(
            fontSize: 10,
            color: _kGray,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          (value.isEmpty || value == '-') ? '-' : value,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w800,
            color: _kNavy,
          ),
        ),
      ],
    );
  }
}

class _ActivityOverviewCard extends StatelessWidget {
  const _ActivityOverviewCard({
    required this.holidayDays,
    required this.missionDays,
    required this.workingDays,
    required this.totalWorkedHoursLabel,
    required this.averageDailyHoursLabel,
  });

  final int holidayDays;
  final int missionDays;
  final int workingDays;
  final String totalWorkedHoursLabel;
  final String averageDailyHoursLabel;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: _kCardBg,
        borderRadius: BorderRadius.circular(16),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A14202B),
            blurRadius: 12,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          _ActivityStatChip(
            icon: Icons.beach_access_outlined,
            label: 'ថ្ងៃសម្រាក',
            value: '$holidayDays ថ្ងៃ',
            color: _kHolidayFill,
          ),
          _ActivityStatChip(
            icon: Icons.work_history_outlined,
            label: 'បេសកកម្ម',
            value: '$missionDays ថ្ងៃ',
            color: _kMissionText,
          ),
          _ActivityStatChip(
            icon: Icons.work_outline_rounded,
            label: 'ថ្ងៃធ្វើការ',
            value: '$workingDays ថ្ងៃ',
            color: _kOnTimeDot,
          ),
          _ActivityStatChip(
            icon: Icons.schedule_rounded,
            label: 'ម៉ោងសរុប',
            value: '$totalWorkedHoursLabel ម៉.',
            color: const Color(0xFF355AA8),
          ),
          _ActivityStatChip(
            icon: Icons.timelapse_outlined,
            label: 'មធ្យម/ថ្ងៃ',
            value: '$averageDailyHoursLabel ម៉.',
            color: const Color(0xFF7C3AED),
          ),
        ],
      ),
    );
  }
}

class _ActivityStatChip extends StatelessWidget {
  const _ActivityStatChip({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minWidth: 138),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: color.withAlpha(18),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 6),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label,
                style: const TextStyle(
                  fontSize: 10.5,
                  fontWeight: FontWeight.w600,
                  color: _kGray,
                ),
              ),
              const SizedBox(height: 1),
              Text(
                value,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  color: color,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ─── Event card ───────────────────────────────────────────────────────────────
class _EventCard extends StatelessWidget {
  const _EventCard({
    required this.record,
    required this.activityTypeLabel,
    required this.statusLabel,
    required this.statusColor,
    required this.onView,
  });

  final AttendanceDayRecord record;
  final String activityTypeLabel;
  final String statusLabel;
  final Color statusColor;
  final VoidCallback onView;

  static const _monthKhmer = [
    'មករា',
    'កុម្ភៈ',
    'មីនា',
    'មេសា',
    'ឧសភា',
    'មិថុនា',
    'កក្កដា',
    'សីហា',
    'កញ្ញា',
    'តុលា',
    'វិច្ឆិកា',
    'ធ្នូ',
  ];

  static const _weekdayKhmer = [
    'ចន្ទ',
    'អង្គារ',
    'ពុធ',
    'ព្រហស្បតិ៍',
    'សុក្រ',
    'សៅរ៍',
    'អាទិត្យ',
  ];

  @override
  Widget build(BuildContext context) {
    DateTime? date;
    try {
      date = DateTime.parse(record.date);
    } catch (_) {}
    final dayNum = date?.day ?? 0;
    final monthName = date != null ? _monthKhmer[date.month - 1] : '';
    final weekday = date != null ? _weekdayKhmer[date.weekday - 1] : '';
    final year = date?.year ?? 0;
    final totalHours =
        (record.totalHours.trim().isEmpty || record.totalHours == '-')
            ? '0:00:00'
            : record.totalHours;
    final hasTimeRange = record.timeIn != '-' || record.timeOut != '-';
    final hasLate = (record.lateMinutes ?? 0) > 0;
    final hasEarlyLeave = (record.earlyLeaveMinutes ?? 0) > 0;

    return Container(
      decoration: BoxDecoration(
        color: _kCardBg,
        borderRadius: BorderRadius.circular(18),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A14202B),
            blurRadius: 12,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          // Left date block
          Container(
            width: 62,
            padding: const EdgeInsets.symmetric(vertical: 14),
            decoration: BoxDecoration(
              color: statusColor.withAlpha(230),
              borderRadius: const BorderRadius.horizontal(
                left: Radius.circular(18),
              ),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  weekday,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '$dayNum',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                    height: 1.1,
                  ),
                ),
                Text(
                  monthName,
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                Text(
                  '$year',
                  style: const TextStyle(
                    color: Colors.white60,
                    fontSize: 9,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          // Content
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 12, 8, 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: statusColor.withAlpha(20),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.calendar_today_rounded,
                              size: 10,
                              color: _kGray,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              statusLabel,
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                                color: statusColor,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF1F5FF),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          activityTypeLabel,
                          style: const TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF355AA8),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    record.date.length >= 10
                        ? record.date.substring(0, 10)
                        : record.date,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: _kNavy,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(
                        Icons.schedule_outlined,
                        size: 13,
                        color: _kGray,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        'ម៉ោងបំពេញ: $totalHours',
                        style: const TextStyle(
                          fontSize: 11.5,
                          color: _kGray,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                  if (hasTimeRange) ...[
                    const SizedBox(height: 3),
                    Text(
                      '${record.timeIn} \u2192 ${record.timeOut}',
                      style: const TextStyle(
                        fontSize: 11.5,
                        color: _kGray,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                  if (hasLate || hasEarlyLeave) ...[
                    const SizedBox(height: 4),
                    Wrap(
                      spacing: 8,
                      runSpacing: 4,
                      children: [
                        if (hasLate)
                          Text(
                            'មកយឺត ${record.lateMinutes} នាទី',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: _kLateDot,
                            ),
                          ),
                        if (hasEarlyLeave)
                          Text(
                            'ចេញមុន ${record.earlyLeaveMinutes} នាទី',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: _kEarlyLeaveDot,
                            ),
                          ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ),
          // Action icon
          Padding(
            padding: const EdgeInsets.only(right: 10),
            child: IconButton(
              onPressed: onView,
              icon: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
              color: statusColor,
              style: IconButton.styleFrom(
                backgroundColor: statusColor.withAlpha(18),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
                minimumSize: const Size(36, 36),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Empty events ─────────────────────────────────────────────────────────────
class _EmptyEventsCard extends StatelessWidget {
  const _EmptyEventsCard({
    this.message = 'មិនទាន់មានទិន្នន័យសកម្មភាពប្រចាំថ្ងៃក្នុងខែនេះ',
  });

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: _kCardBg,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Center(
        child: Text(
          message,
          style: const TextStyle(
            color: _kGray,
            fontSize: 13,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }
}

// ─── Error card ───────────────────────────────────────────────────────────────
class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _kCardBg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFF0CED5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.error_outline, color: Color(0xFFD34B5F), size: 20),
          const SizedBox(height: 8),
          Text(
            message,
            style: const TextStyle(color: Color(0xFF5D726A), fontSize: 13),
          ),
          const SizedBox(height: 10),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh, size: 16),
            label: const Text('ព្យាយាមម្ដងទៀត'),
          ),
        ],
      ),
    );
  }
}

// ─── Detail bottom sheet ──────────────────────────────────────────────────────
class _DetailSheet extends StatelessWidget {
  const _DetailSheet({
    required this.timeIn,
    required this.timeOut,
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
    required this.punchCount,
    required this.totalHours,
    required this.exceptionReason,
    required this.hasException,
  });

  final String timeIn;
  final String timeOut;
  final int lateMinutes;
  final int earlyLeaveMinutes;
  final int punchCount;
  final String totalHours;
  final String? exceptionReason;
  final bool hasException;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: _kCardBg,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: _kDivider),
          ),
          child: Column(
            children: [
              _SheetRow(label: 'ម៉ោងចូល', value: timeIn),
              _SheetRow(label: 'ម៉ោងចេញ', value: timeOut),
              _SheetRow(label: 'ម៉ោងធ្វើការ', value: totalHours),
              _SheetRow(label: 'ចំនួនស្កេន', value: '$punchCount ដង'),
              if (lateMinutes > 0)
                _SheetRow(
                  label: 'ពន្យារ',
                  value: '$lateMinutes នាទី',
                  valueColor: _kLateDot,
                ),
              if (earlyLeaveMinutes > 0)
                _SheetRow(
                  label: 'ចេញមុន',
                  value: '$earlyLeaveMinutes នាទី',
                  valueColor: _kEarlyLeaveDot,
                ),
            ],
          ),
        ),
        if (hasException || (exceptionReason?.trim().isNotEmpty == true)) ...[
          const SizedBox(height: 10),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFFFF7F7),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFF2D6D6)),
            ),
            child: Text(
              exceptionReason?.trim().isNotEmpty == true
                  ? exceptionReason!.trim()
                  : 'ព័ត៌មានស្កេនមិនគ្រប់',
              style: const TextStyle(
                color: Color(0xFF6D4C4C),
                height: 1.45,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _SheetRow extends StatelessWidget {
  const _SheetRow({required this.label, required this.value, this.valueColor});

  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: const TextStyle(
                color: _kGray,
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value.isEmpty ? '-' : value,
              style: TextStyle(
                color: valueColor ?? _kNavy,
                fontWeight: FontWeight.w800,
                fontSize: 13,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Bottom navigation ────────────────────────────────────────────────────────
class _CalendarBottomNav extends StatelessWidget {
  const _CalendarBottomNav({required this.onHomeTap, required this.onScanTap});

  final VoidCallback onHomeTap;
  final VoidCallback onScanTap;

  @override
  Widget build(BuildContext context) {
    final bottomPad = MediaQuery.of(context).padding.bottom;
    const labels = ['ព័ត៌មាន', 'ស្កេន', 'វត្តមាន'];

    return SafeArea(
      top: false,
      child: Padding(
        padding: EdgeInsets.fromLTRB(0, 0, 0, bottomPad > 0 ? 6 : 10),
        child: MotionTabBar(
          initialSelectedTab: labels[2],
          labels: labels,
          icons: const [
            Icons.newspaper_outlined,
            Icons.qr_code_scanner_rounded,
            Icons.calendar_month_outlined,
          ],
          tabSize: 52,
          tabBarHeight: 60,
          textStyle: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800),
          tabIconSize: 22,
          tabIconSelectedSize: 26,
          tabSelectedColor: const Color(0xFF16A34A),
          tabIconSelectedColor: Colors.white,
          tabIconColor: const Color(0xFF9CA3AF),
          tabBarColor: Colors.white,
          onTabItemSelected: (index) {
            if (index == 0) {
              onHomeTap();
              return;
            }
            if (index == 1) {
              onScanTap();
            }
          },
        ),
      ),
    );
  }
}
