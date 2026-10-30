import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../auth/models/auth_user.dart';
import '../models/attendance_day_record.dart';
import 'attendance_calendar_page.dart';
import 'attendance_day_detail_page.dart';
import 'attendance_filter_sheet.dart';
import '../services/home_attendance_service.dart';
import 'home/attendance_format.dart';
import 'home/attendance_status.dart';
import 'home/home_menu.dart';
import 'home/home_nav_widgets.dart';
import 'home/home_theme.dart';
import '../../../core/network/api_exception.dart';

/// Attendance History ("ប្រវត្តិវត្តមាន"): the period's summary (hours
/// worked, attendance rate, days present / late / absent / on leave / on
/// mission) with month arrows, status chips with counts, and one card per
/// day showing each session's scans against its schedule. Statuses follow
/// the server's codes; a day still under way reads "កំពុងបន្ត", never
/// absent. The header links to the calendar view and the filter sheet.
class AttendanceHistoryPage extends StatefulWidget {
  const AttendanceHistoryPage({
    super.key,
    required this.user,
    required this.attendanceService,
    required this.language,
    this.today,
    this.openUrl,
  });

  final AuthUser user;
  final HomeAttendanceService attendanceService;
  final Map<String, String> language;

  /// Opens the report link (the browser by default; replaced in tests).
  final Future<bool> Function(Uri url)? openUrl;

  /// Fixed "today" for tests; the real clock otherwise.
  final DateTime? today;

  @override
  State<AttendanceHistoryPage> createState() => _AttendanceHistoryPageState();
}

class _AttendanceHistoryPageState extends State<AttendanceHistoryPage> {
  late AttendanceFilterState _filter;
  late Future<List<AttendanceDayRecord>> _recordsFuture;
  bool _openingReport = false;

  DateTime get _today => widget.today ?? DateTime.now();

  @override
  void initState() {
    super.initState();
    _filter = AttendanceFilterState.forMonth(_today);
    _recordsFuture = _loadRecords();
  }

  Future<List<AttendanceDayRecord>> _loadRecords() {
    return widget.attendanceService.fetchAttendanceHistory(
      widget.user,
      fromDate: _filter.fromDate,
      toDate: _filter.toDate,
    );
  }

  /// The last copy of this period, shown at once while the network loads.
  List<AttendanceDayRecord>? _cached() {
    try {
      return widget.attendanceService.cachedAttendanceHistory(
        widget.user,
        fromDate: _filter.fromDate,
        toDate: _filter.toDate,
      );
    } catch (_) {
      return null;
    }
  }

  Future<void> _refresh() async {
    setState(() {
      _recordsFuture = _loadRecords();
    });
    await _recordsFuture;
  }

  Future<void> _openFilter() async {
    final result = await showAttendanceFilterSheet(context, _filter);
    if (result != null && mounted) {
      setState(() {
        _filter = result;
        _recordsFuture = _loadRecords();
      });
    }
  }

  void _shiftMonth(int delta) {
    final from = _filter.fromDate;
    setState(() {
      _filter = AttendanceFilterState.forMonth(
        DateTime(from.year, from.month + delta),
      ).copyWith(statusCodes: _filter.statusCodes);
      _recordsFuture = _loadRecords();
    });
  }

  bool get _isCurrentMonth =>
      _filter.fromDate.year == _today.year &&
      _filter.fromDate.month == _today.month &&
      !_filter.isCustomRange;

  void _setQuickStatus(String code) {
    setState(() {
      _filter = _filter.copyWith(
        statusCodes: code.isEmpty ? <String>{} : <String>{code},
      );
    });
  }

  Future<void> _openCalendar() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder:
            (_) => AttendanceCalendarPage(
              user: widget.user,
              attendanceService: widget.attendanceService,
              language: widget.language,
              initialFilter: _filter,
            ),
      ),
    );
  }

  void _openDayDetail(AttendanceDayRecord record) {
    Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => AttendanceDayDetailPage(record: record),
      ),
    );
  }

  /// Opens this period's printable report in the browser, where it can be
  /// printed or saved as PDF.
  Future<void> _downloadReport() async {
    if (_openingReport) return;
    setState(() => _openingReport = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      final url = await widget.attendanceService.reportUrl(
        widget.user,
        fromDate: _filter.fromDate,
        toDate: _filter.toDate,
      );
      final open =
          widget.openUrl ??
          (Uri uri) => launchUrl(uri, mode: LaunchMode.externalApplication);
      final opened = await open(url);
      if (!opened) throw ApiException(message: 'មិនអាចបើកកម្មវិធីរុករកបានទេ។');
    } catch (error) {
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(extractApiErrorMessage(error))));
    } finally {
      if (mounted) setState(() => _openingReport = false);
    }
  }

  String _periodLabel() {
    final from = _filter.fromDate;
    final to = _filter.toDate;
    if (!_filter.isCustomRange) {
      return 'ខែ${attendanceMonthNames[from.month - 1]} ${kmDigits(from.year)}';
    }
    String short(DateTime d) =>
        '${kmDigits(d.day)} ${attendanceMonthNames[d.month - 1]}';
    return '${short(from)} – ${short(to)} ${kmDigits(to.year)}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F6F8),
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _TopBar(onCalendar: _openCalendar, onFilter: _openFilter),
            Expanded(
              child: RefreshIndicator(
                onRefresh: _refresh,
                child: FutureBuilder<List<AttendanceDayRecord>>(
                  // A new period starts from its own cache, never the
                  // previous period's days; a refresh keeps them on screen.
                  key: ValueKey('${_filter.fromDate}|${_filter.toDate}'),
                  future: _recordsFuture,
                  initialData: _cached(),
                  builder: (context, snapshot) {
                    final waiting =
                        snapshot.connectionState == ConnectionState.waiting;
                    final all = snapshot.data ?? const <AttendanceDayRecord>[];
                    final summary = _PeriodSummary.of(all);
                    final records =
                        all
                            .where(
                              (r) =>
                                  _filter.statusCodes.isEmpty ||
                                  _filter.statusCodes.contains(
                                    attendanceDayStyle(r).code,
                                  ),
                            )
                            .toList()
                          ..sort((a, b) => b.date.compareTo(a.date));

                    return ListView(
                      padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
                      children: [
                        _SummaryHero(
                          period: _periodLabel(),
                          summary: summary,
                          loading: waiting && snapshot.data == null,
                          onPeriodTap: _openFilter,
                          onPrevious: () => _shiftMonth(-1),
                          onNext: _isCurrentMonth ? null : () => _shiftMonth(1),
                        ),
                        const SizedBox(height: 14),
                        _StatusChips(
                          counts: summary.byStatus,
                          total: all.length,
                          selected: _filter.statusCodes,
                          onSelect: _setQuickStatus,
                        ),
                        const SizedBox(height: 12),
                        if (waiting && snapshot.data == null)
                          const Padding(
                            padding: EdgeInsets.symmetric(vertical: 60),
                            child: Center(child: CircularProgressIndicator()),
                          )
                        else if (snapshot.hasError && snapshot.data == null)
                          _Message(
                            icon: Icons.cloud_off_rounded,
                            text: extractApiErrorMessage(snapshot.error!),
                            action: FilledButton(
                              onPressed: _refresh,
                              child: const Text('ព្យាយាមម្ដងទៀត'),
                            ),
                          )
                        else if (records.isEmpty)
                          const _Message(
                            icon: Icons.event_busy_rounded,
                            text: 'មិនទាន់មានទិន្នន័យវត្តមានតាមលក្ខខណ្ឌនេះ',
                          )
                        else
                          for (final record in records) ...[
                            _DayCard(
                              record: record,
                              isToday: _isSameDay(record.date, _today),
                              onTap: () => _openDayDetail(record),
                            ),
                            const SizedBox(height: 10),
                          ],
                        const SizedBox(height: 6),
                        OutlinedButton.icon(
                          onPressed: _downloadReport,
                          style: OutlinedButton.styleFrom(
                            foregroundColor: dashboardHeaderStart,
                            side: BorderSide(
                              color: dashboardHeaderStart.withAlpha(90),
                            ),
                            minimumSize: const Size.fromHeight(50),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          icon:
                              _openingReport
                                  ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                  : const Icon(Icons.download_rounded),
                          label: const Text('ទាញយករបាយការណ៍'),
                        ),
                      ],
                    );
                  },
                ),
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: HomeBottomNavigation(
        currentIndex: 1,
        showScanButton: false,
        onTap: (index) async {
          switch (index) {
            case 0:
              Navigator.of(context).pop();
              return;
            case 3:
              Navigator.of(context).pop(HomeMenuItem.mission);
              return;
            case 4:
              Navigator.of(context).pop(HomeMenuItem.profile);
              return;
          }
        },
      ),
    );
  }

  static bool _isSameDay(String date, DateTime day) {
    final d = DateTime.tryParse(date);
    return d != null &&
        d.year == day.year &&
        d.month == day.month &&
        d.day == day.day;
  }
}

/// The period's figures, from the server's statuses and minutes.
class _PeriodSummary {
  const _PeriodSummary({
    required this.byStatus,
    required this.present,
    required this.workedMinutes,
    required this.lateDays,
    required this.lateMinutes,
    required this.earlyDays,
  });

  factory _PeriodSummary.of(List<AttendanceDayRecord> records) {
    final byStatus = <String, int>{};
    var worked = 0, lateDays = 0, lateMinutes = 0, earlyDays = 0;
    for (final r in records) {
      final code = attendanceDayStyle(r).code;
      byStatus[code] = (byStatus[code] ?? 0) + 1;
      worked += r.workedMinutes;
      if ((r.lateMinutes ?? 0) > 0) {
        lateDays++;
        lateMinutes += r.lateMinutes!;
      }
      if ((r.earlyLeaveMinutes ?? 0) > 0) earlyDays++;
    }
    final present =
        (byStatus['on_time'] ?? 0) +
        (byStatus['late'] ?? 0) +
        (byStatus['early_leave'] ?? 0);
    return _PeriodSummary(
      byStatus: byStatus,
      present: present,
      workedMinutes: worked,
      lateDays: lateDays,
      lateMinutes: lateMinutes,
      earlyDays: earlyDays,
    );
  }

  final Map<String, int> byStatus;
  final int present;
  final int workedMinutes;
  final int lateDays;
  final int lateMinutes;
  final int earlyDays;

  int count(String code) => byStatus[code] ?? 0;

  /// Present out of the finished working days (absent and incomplete
  /// included; leave, mission, days off and today's open day left out).
  double? get rate {
    final base = present + count('absent') + count('incomplete');
    return base == 0 ? null : present / base;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Header
// ─────────────────────────────────────────────────────────────────────────────

class _TopBar extends StatelessWidget {
  const _TopBar({required this.onCalendar, required this.onFilter});

  final VoidCallback onCalendar;
  final VoidCallback onFilter;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(8, 8, 8, 8),
      color: Colors.white,
      child: Row(
        children: [
          IconButton(
            onPressed: () => Navigator.of(context).maybePop(),
            tooltip: 'ត្រឡប់ក្រោយ',
            icon: const Icon(
              Icons.arrow_back_ios_new_rounded,
              size: 18,
              color: Color(0xFF17221D),
            ),
          ),
          const Expanded(
            child: Text(
              'ប្រវត្តិវត្តមាន',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                color: Color(0xFF17221D),
              ),
            ),
          ),
          Flexible(
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: TextButton.icon(
                onPressed: onCalendar,
                style: TextButton.styleFrom(
                  foregroundColor: dashboardHeaderStart,
                  backgroundColor: dashboardHeaderStart.withAlpha(18),
                  shape: const StadiumBorder(),
                ),
                icon: const Icon(Icons.calendar_month_rounded, size: 18),
                label: const Text('ប្រតិទិន'),
              ),
            ),
          ),
          IconButton(
            onPressed: onFilter,
            tooltip: 'តម្រង',
            icon: Icon(Icons.tune_rounded, color: dashboardHeaderStart),
          ),
        ],
      ),
    );
  }
}

class _SummaryHero extends StatelessWidget {
  const _SummaryHero({
    required this.period,
    required this.summary,
    required this.loading,
    required this.onPeriodTap,
    required this.onPrevious,
    required this.onNext,
  });

  final String period;
  final _PeriodSummary summary;
  final bool loading;
  final VoidCallback onPeriodTap;
  final VoidCallback onPrevious;
  final VoidCallback? onNext;

  @override
  Widget build(BuildContext context) {
    final rate = summary.rate;
    return ClipRRect(
      borderRadius: BorderRadius.circular(26),
      child: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [dashboardHeaderStart, dashboardHeaderEnd],
          ),
        ),
        child: Stack(
          children: [
            Positioned(
              right: -40,
              top: -60,
              child: _Blob(size: 170, opacity: 0.10),
            ),
            Positioned(
              left: -30,
              bottom: -70,
              child: _Blob(size: 150, opacity: 0.07),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 12, 10, 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      _Arrow(
                        icon: Icons.chevron_left_rounded,
                        tooltip: 'ខែមុន',
                        onTap: onPrevious,
                      ),
                      Expanded(
                        child: InkWell(
                          onTap: onPeriodTap,
                          borderRadius: BorderRadius.circular(12),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(vertical: 4),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                const Icon(
                                  Icons.event_note_rounded,
                                  size: 17,
                                  color: Colors.white,
                                ),
                                const SizedBox(width: 6),
                                Flexible(
                                  child: Text(
                                    period,
                                    textAlign: TextAlign.center,
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 15,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                      _Arrow(
                        icon: Icons.chevron_right_rounded,
                        tooltip: 'ខែបន្ទាប់',
                        onTap: onNext,
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 8),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'ម៉ោងធ្វើការសរុប',
                                style: TextStyle(
                                  color: Colors.white.withAlpha(215),
                                  fontSize: 12,
                                ),
                              ),
                              const SizedBox(height: 2),
                              FittedBox(
                                fit: BoxFit.scaleDown,
                                alignment: Alignment.centerLeft,
                                child: Text(
                                  loading
                                      ? '...'
                                      : kmDuration(summary.workedMinutes),
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 24,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                summary.lateMinutes > 0
                                    ? 'យឺតសរុប ${kmDuration(summary.lateMinutes)}'
                                    : 'គ្មានការមកយឺត',
                                style: TextStyle(
                                  color: Colors.white.withAlpha(215),
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),
                        _RateRing(rate: rate),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  Container(
                    margin: const EdgeInsets.symmetric(horizontal: 6),
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    decoration: BoxDecoration(
                      color: Colors.white.withAlpha(30),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: Colors.white.withAlpha(45)),
                    ),
                    child: Column(
                      children: [
                        _StatRow(
                          stats: [
                            (
                              Icons.how_to_reg_rounded,
                              summary.present,
                              'មកធ្វើការ',
                            ),
                            (
                              Icons.watch_later_rounded,
                              summary.lateDays,
                              'មកយឺត',
                            ),
                            (Icons.logout_rounded, summary.earlyDays, 'ចេញមុន'),
                          ],
                        ),
                        Divider(
                          height: 16,
                          indent: 14,
                          endIndent: 14,
                          color: Colors.white.withAlpha(45),
                        ),
                        _StatRow(
                          stats: [
                            (
                              Icons.person_off_rounded,
                              summary.count('absent'),
                              'អវត្តមាន',
                            ),
                            (
                              Icons.beach_access_rounded,
                              summary.count('leave'),
                              'ច្បាប់',
                            ),
                            (
                              Icons.directions_car_rounded,
                              summary.count('mission'),
                              'បេសកកម្ម',
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Arrow extends StatelessWidget {
  const _Arrow({required this.icon, required this.tooltip, this.onTap});

  final IconData icon;
  final String tooltip;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      onPressed: onTap,
      tooltip: tooltip,
      color: Colors.white,
      disabledColor: Colors.white.withAlpha(70),
      style: IconButton.styleFrom(
        backgroundColor: Colors.white.withAlpha(onTap == null ? 12 : 34),
      ),
      icon: Icon(icon),
    );
  }
}

class _RateRing extends StatelessWidget {
  const _RateRing({required this.rate});

  final double? rate;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 84,
      height: 84,
      child: TweenAnimationBuilder<double>(
        tween: Tween<double>(begin: 0, end: rate ?? 0),
        duration: const Duration(milliseconds: 900),
        curve: Curves.easeOutCubic,
        builder:
            (context, value, _) => Stack(
              fit: StackFit.expand,
              children: [
                CircularProgressIndicator(
                  value: value,
                  strokeWidth: 8,
                  strokeCap: StrokeCap.round,
                  backgroundColor: Colors.white.withAlpha(45),
                  valueColor: const AlwaysStoppedAnimation(Colors.white),
                ),
                Center(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            rate == null
                                ? '—'
                                : '${kmDigits((value * 100).round())}%',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const Text(
                            'វត្តមាន',
                            style: TextStyle(color: Colors.white, fontSize: 10),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
      ),
    );
  }
}

class _StatRow extends StatelessWidget {
  const _StatRow({required this.stats});

  final List<(IconData, int, String)> stats;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (final (icon, value, label) in stats)
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(icon, size: 15, color: Colors.white),
                      const SizedBox(width: 4),
                      Flexible(
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Text(
                            kmDigits(value),
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 17,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  Text(
                    label,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white.withAlpha(215),
                      fontSize: 11.5,
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _Blob extends StatelessWidget {
  const _Blob({required this.size, required this.opacity});

  final double size;
  final double opacity;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withValues(alpha: opacity),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Status chips
// ─────────────────────────────────────────────────────────────────────────────

class _StatusChips extends StatelessWidget {
  const _StatusChips({
    required this.counts,
    required this.total,
    required this.selected,
    required this.onSelect,
  });

  final Map<String, int> counts;
  final int total;
  final Set<String> selected;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    final chips = <(String, String, int, Color)>[
      ('', 'ទាំងអស់', total, dashboardHeaderStart),
      for (final style in attendanceStatusPalette)
        if ((counts[style.code] ?? 0) > 0 || selected.contains(style.code))
          (style.code, style.shortLabel, counts[style.code] ?? 0, style.fg),
    ];
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final (code, label, count, color) in chips) ...[
            _Chip(
              label: label,
              count: count,
              color: color,
              selected:
                  code.isEmpty ? selected.isEmpty : selected.contains(code),
              onTap: () => onSelect(code),
            ),
            const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({
    required this.label,
    required this.count,
    required this.color,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final int count;
  final Color color;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? color : Colors.white,
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.fromLTRB(12, 6, 6, 6),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected ? color : const Color(0xFFE2E8F0),
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label,
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: selected ? Colors.white : const Color(0xFF475569),
                ),
              ),
              const SizedBox(width: 6),
              Container(
                constraints: const BoxConstraints(minWidth: 22),
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                decoration: BoxDecoration(
                  color:
                      selected
                          ? Colors.white.withAlpha(50)
                          : color.withAlpha(26),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  kmDigits(count),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w800,
                    color: selected ? Colors.white : color,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Day card
// ─────────────────────────────────────────────────────────────────────────────

/// One day: a date tile coloured by status, the status and hours worked,
/// what kind of day it was (shift, holiday, leave, ...), and each session's
/// in → out scans with any late / early-leave / missing-scan flags.
class _DayCard extends StatelessWidget {
  const _DayCard({
    required this.record,
    required this.isToday,
    required this.onTap,
  });

  final AttendanceDayRecord record;
  final bool isToday;
  final VoidCallback onTap;

  static const _restDays = {'holiday', 'day_off', 'leave', 'mission'};

  String? _context(AttendanceStatusStyle style) {
    switch (style.code) {
      case 'holiday':
        return record.holidayName ?? 'ថ្ងៃបុណ្យ';
      case 'day_off':
        return 'ថ្ងៃឈប់សម្រាកប្រចាំសប្តាហ៍';
      case 'leave':
        return 'ច្បាប់ដែលបានអនុម័ត';
      case 'mission':
        return 'បេសកកម្មដែលបានអនុម័ត';
    }
    return record.shiftName;
  }

  @override
  Widget build(BuildContext context) {
    final date = DateTime.tryParse(record.date);
    final style = attendanceDayStyle(record);
    final contextLine = _context(style);
    final sessions = record.sessions;
    final hasScans = hasClock(record.timeIn) || hasClock(record.timeOut);
    final note = record.adjustmentNote?.trim() ?? '';

    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A0F172A),
            blurRadius: 14,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _DateTile(date: date, style: style, isToday: isToday),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          // The pill keeps most of the row so its label
                          // stays on one line; hours take what is left.
                          Expanded(
                            flex: 3,
                            child: Align(
                              alignment: Alignment.centerLeft,
                              child: AttendanceStatusPill(style: style),
                            ),
                          ),
                          if (record.workedMinutes > 0)
                            Flexible(
                              flex: 2,
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.end,
                                children: [
                                  const Icon(
                                    Icons.schedule_rounded,
                                    size: 14,
                                    color: Color(0xFF94A3B8),
                                  ),
                                  const SizedBox(width: 3),
                                  Flexible(
                                    child: Text(
                                      kmDuration(record.workedMinutes),
                                      textAlign: TextAlign.end,
                                      style: const TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                        color: Color(0xFF334155),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                        ],
                      ),
                      if (contextLine != null && contextLine.isNotEmpty) ...[
                        const SizedBox(height: 5),
                        Text(
                          contextLine,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12,
                            color: Color(0xFF64748B),
                          ),
                        ),
                      ],
                      // A rest day (holiday, day off, leave, mission) only
                      // lists sessions when the officer scanned anyway.
                      if (sessions.isNotEmpty &&
                          (hasScans || !_restDays.contains(style.code)))
                        for (final session in sessions)
                          _SessionLine(
                            session: session,
                            provisional: record.isProvisional,
                          )
                      else if (hasScans)
                        _ScanLine(
                          label: 'ម៉ោងធ្វើការ',
                          timeIn: record.timeIn,
                          timeOut: record.timeOut,
                        ),
                      if (note.isNotEmpty || record.overtimeMinutes > 0) ...[
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 6,
                          runSpacing: 6,
                          children: [
                            if (record.overtimeMinutes > 0)
                              _Flag(
                                icon: Icons.more_time_rounded,
                                text:
                                    'បន្ថែម ${kmDuration(record.overtimeMinutes)}',
                                color: const Color(0xFF0E7490),
                              ),
                            if (note.isNotEmpty)
                              const _Flag(
                                icon: Icons.edit_note_rounded,
                                text: 'HR បានកែតម្រូវ',
                                color: Color(0xFF7C3AED),
                              ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
                const Padding(
                  padding: EdgeInsets.only(top: 4, left: 2),
                  child: Icon(
                    Icons.chevron_right_rounded,
                    color: Color(0xFFCBD5E1),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _DateTile extends StatelessWidget {
  const _DateTile({
    required this.date,
    required this.style,
    required this.isToday,
  });

  final DateTime? date;
  final AttendanceStatusStyle style;
  final bool isToday;

  // "ព្រហស្បតិ៍" is too wide for the tile; "ព្រហ" is its usual short form.
  static const _shortWeekdays = [
    'ចន្ទ',
    'អង្គារ',
    'ពុធ',
    'ព្រហ',
    'សុក្រ',
    'សៅរ៍',
    'អាទិត្យ',
  ];

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 58,
      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
      decoration: BoxDecoration(
        color: style.bg,
        borderRadius: BorderRadius.circular(16),
        border: isToday ? Border.all(color: style.fg, width: 2) : null,
      ),
      child: Column(
        children: [
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              date == null ? '-' : kmDigits(date!.day),
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w900,
                color: style.fg,
                height: 1.2,
              ),
            ),
          ),
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              isToday
                  ? 'ថ្ងៃនេះ'
                  : date == null
                  ? ''
                  : _shortWeekdays[date!.weekday - 1],
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: style.fg,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// "ព្រឹក  ០៧:៣១ → ១១:៣០" with late / early-leave / missing-scan flags and
/// the scheduled window underneath.
class _SessionLine extends StatelessWidget {
  const _SessionLine({required this.session, required this.provisional});

  final AttendanceSessionRecord session;
  final bool provisional;

  @override
  Widget build(BuildContext context) {
    final missingIn = !hasClock(session.timeIn) && !session.inContinued;
    final missingOut = !hasClock(session.timeOut) && !session.outContinued;
    final flags = <Widget>[
      if (session.lateMinutes > 0)
        _Flag(
          text: 'យឺត ${kmDigits(session.lateMinutes)}នាទី',
          color: const Color(0xFFD99A00),
        ),
      if (session.earlyLeaveMinutes > 0)
        _Flag(
          text: 'ចេញមុន ${kmDigits(session.earlyLeaveMinutes)}នាទី',
          color: const Color(0xFFD9622B),
        ),
      if (!provisional && !session.complete && (missingIn != missingOut))
        _Flag(
          text: missingIn ? 'ខ្វះស្កេនចូល' : 'ខ្វះស្កេនចេញ',
          color: const Color(0xFFC94444),
        ),
    ];
    return _ScanLine(
      label: attendanceSessionLabel(session.name),
      timeIn: session.displayIn,
      timeOut: session.displayOut,
      scheduled:
          hasClock(session.scheduledIn) && hasClock(session.scheduledOut)
              ? '${kmClock(session.scheduledIn)} – ${kmClock(session.scheduledOut)}'
              : null,
      duty: session.isDuty || session.name == 'duty',
      flags: flags,
    );
  }
}

class _ScanLine extends StatelessWidget {
  const _ScanLine({
    required this.label,
    required this.timeIn,
    required this.timeOut,
    this.scheduled,
    this.duty = false,
    this.flags = const [],
  });

  final String label;
  final String timeIn;
  final String timeOut;
  final String? scheduled;
  final bool duty;
  final List<Widget> flags;

  String _time(String value) =>
      value == AttendanceSessionRecord.continuedLabel ? value : kmClock(value);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Container(
        padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
        decoration: BoxDecoration(
          color: duty ? const Color(0xFFF5F3FF) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  duty ? Icons.nightlight_round : Icons.wb_sunny_rounded,
                  size: 14,
                  color:
                      duty ? const Color(0xFF7C3AED) : const Color(0xFFF59E0B),
                ),
                const SizedBox(width: 5),
                Flexible(
                  child: Text(
                    label,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF475569),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Align(
                    alignment: Alignment.centerRight,
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.login_rounded,
                            size: 14,
                            color: Color(0xFF16A34A),
                          ),
                          const SizedBox(width: 3),
                          Text(
                            _time(timeIn),
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF0F172A),
                            ),
                          ),
                          const Padding(
                            padding: EdgeInsets.symmetric(horizontal: 6),
                            child: Icon(
                              Icons.arrow_forward_rounded,
                              size: 13,
                              color: Color(0xFF94A3B8),
                            ),
                          ),
                          const Icon(
                            Icons.logout_rounded,
                            size: 14,
                            color: Color(0xFFEA580C),
                          ),
                          const SizedBox(width: 3),
                          Text(
                            _time(timeOut),
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF0F172A),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
            if (scheduled != null || flags.isNotEmpty) ...[
              const SizedBox(height: 5),
              Wrap(
                spacing: 6,
                runSpacing: 4,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  if (scheduled != null)
                    Text(
                      'កាលវិភាគ $scheduled',
                      style: const TextStyle(
                        fontSize: 11,
                        color: Color(0xFF94A3B8),
                      ),
                    ),
                  ...flags,
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Flag extends StatelessWidget {
  const _Flag({required this.text, required this.color, this.icon});

  final String text;
  final Color color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
      decoration: BoxDecoration(
        color: color.withAlpha(24),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 12, color: color),
            const SizedBox(width: 3),
          ],
          Flexible(
            child: Text(
              text,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: color,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.icon, required this.text, this.action});

  final IconData icon;
  final String text;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 12),
      child: Column(
        children: [
          Icon(icon, size: 44, color: const Color(0xFFCBD5E1)),
          const SizedBox(height: 10),
          Text(
            text,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Color(0xFF64748B)),
          ),
          if (action != null) ...[const SizedBox(height: 12), action!],
        ],
      ),
    );
  }
}
