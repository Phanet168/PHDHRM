import 'package:flutter/material.dart';

import '../../auth/models/auth_user.dart';
import '../models/attendance_day_record.dart';
import 'attendance_day_detail_page.dart';
import 'attendance_filter_sheet.dart';
import 'attendance_scan_page.dart';
import '../services/home_attendance_service.dart';
import 'home/attendance_status.dart';
import 'home/home_menu.dart';
import 'home/home_nav_widgets.dart';
import 'home/home_theme.dart';

/// Attendance Calendar (Figma "ប្រតិទិនវត្តមាន") — a monthly grid where each
/// day's fill height reflects its worked-hours percent, plus a monthly
/// summary card (counts + attendance-rate ring). Reached from the History
/// screen's "ប្រតិទិន" link.
class AttendanceCalendarPage extends StatefulWidget {
  const AttendanceCalendarPage({
    super.key,
    required this.user,
    required this.attendanceService,
    required this.language,
    required this.initialFilter,
  });

  final AuthUser user;
  final HomeAttendanceService attendanceService;
  final Map<String, String> language;
  final AttendanceFilterState initialFilter;

  @override
  State<AttendanceCalendarPage> createState() => _AttendanceCalendarPageState();
}

class _AttendanceCalendarPageState extends State<AttendanceCalendarPage> {
  late AttendanceFilterState _filter;
  late DateTime _month;
  late Future<List<AttendanceDayRecord>> _recordsFuture;
  late Future<Map<String, String>> _holidaysFuture;

  static const _monthNames = [
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

  @override
  void initState() {
    super.initState();
    _filter = widget.initialFilter;
    _month = DateTime(_filter.fromDate.year, _filter.fromDate.month, 1);
    _recordsFuture = _loadRecords();
    _holidaysFuture = _loadHolidays();
  }

  Future<List<AttendanceDayRecord>> _loadRecords() {
    return widget.attendanceService.fetchAttendanceHistory(
      widget.user,
      fromDate: _filter.fromDate,
      toDate: _filter.toDate,
    );
  }

  /// Public holidays for the visible range, keyed by "yyyy-MM-dd". Sourced
  /// from `/v1/attendance/schedule` rather than history, since history caps
  /// at today and would silently drop upcoming holidays later in the month.
  Future<Map<String, String>> _loadHolidays() async {
    if (!widget.user.hasEmployee) {
      return const <String, String>{};
    }
    final days = await widget.attendanceService.fetchSchedule(
      widget.user,
      fromDate: _filter.fromDate,
      toDate: _filter.toDate,
    );
    final map = <String, String>{};
    for (final day in days) {
      if (day['is_holiday'] != true) continue;
      final name = (day['holiday_name'] as String?)?.trim();
      final date = day['date']?.toString();
      if (name == null || name.isEmpty || date == null) continue;
      map[date.length >= 10 ? date.substring(0, 10) : date] = name;
    }
    return map;
  }

  Future<void> _refresh() async {
    setState(() {
      _recordsFuture = _loadRecords();
      _holidaysFuture = _loadHolidays();
    });
    await _recordsFuture;
  }

  void _stepMonth(int delta) {
    setState(() {
      _month = DateTime(_month.year, _month.month + delta, 1);
      _filter = AttendanceFilterState.forMonth(_month);
      _recordsFuture = _loadRecords();
      _holidaysFuture = _loadHolidays();
    });
  }

  Future<void> _openFilter() async {
    final result = await showAttendanceFilterSheet(context, _filter);
    if (result != null && mounted) {
      setState(() {
        _filter = result;
        _month = DateTime(_filter.fromDate.year, _filter.fromDate.month, 1);
        _recordsFuture = _loadRecords();
        _holidaysFuture = _loadHolidays();
      });
    }
  }

  Future<void> _openAttendanceScanner() async {
    if (!widget.user.hasEmployee) {
      if (!mounted) return;
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
      await _refresh();
    }
  }

  void _openDayDetail(AttendanceDayRecord record) {
    Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => AttendanceDayDetailPage(record: record),
      ),
    );
  }

  /// One row per distinct public holiday in the visible month, oldest first
  /// — hidden entirely when there are none, rather than showing an empty
  /// "Events" box.
  Widget _buildHolidaySection(Map<String, String> byDate) {
    if (byDate.isEmpty) {
      return const SizedBox.shrink();
    }
    final dates = byDate.keys.toList()..sort();

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'ព្រឹត្តិការណ៍ខែនេះ',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.bold,
              color: Color(0xFF17201E),
            ),
          ),
          const SizedBox(height: 8),
          for (final date in dates) ...[
            _HolidayEventCard(dateKey: date, name: byDate[date]!),
            if (date != dates.last) const SizedBox(height: 8),
          ],
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F7F6),
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            Container(
              height: 72,
              padding: const EdgeInsets.fromLTRB(16, 20, 16, 12),
              color: Colors.white,
              child: Row(
                children: [
                  InkWell(
                    onTap: () => Navigator.of(context).maybePop(),
                    borderRadius: BorderRadius.circular(11),
                    child: const Padding(
                      padding: EdgeInsets.all(4),
                      child: Icon(
                        Icons.arrow_back_ios_new_rounded,
                        size: 18,
                        color: Color(0xFF17201E),
                      ),
                    ),
                  ),
                  const Expanded(
                    child: Text(
                      'ប្រតិទិនវត្តមាន',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF17201E),
                      ),
                    ),
                  ),
                  InkWell(
                    onTap: _openFilter,
                    borderRadius: BorderRadius.circular(11),
                    child: const Padding(
                      padding: EdgeInsets.all(4),
                      child: Icon(
                        Icons.tune_rounded,
                        size: 20,
                        color: Color(0xFF66736F),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: RefreshIndicator(
                onRefresh: _refresh,
                child: FutureBuilder<List<AttendanceDayRecord>>(
                  future: _recordsFuture,
                  builder: (context, snapshot) {
                    final isLoading =
                        snapshot.connectionState == ConnectionState.waiting;
                    final records =
                        snapshot.data ?? const <AttendanceDayRecord>[];
                    final recordMap = <String, AttendanceDayRecord>{
                      for (final r in records)
                        (r.date.length >= 10
                                ? r.date.substring(0, 10)
                                : r.date):
                            r,
                    };

                    int countOf(String code) =>
                        records
                            .where(
                              (r) =>
                                  attendanceStatusStyle(
                                    r.attendanceStatus,
                                  ).code ==
                                  code,
                            )
                            .length;

                    final present = countOf('on_time') + countOf('late');
                    final absent = countOf('absent');
                    final leave = countOf('leave');
                    final dayOff = countOf('day_off');
                    final rateBase = present + absent + countOf('leave');
                    final rate = rateBase > 0 ? present / rateBase : 0.0;

                    return ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Container(
                          height: 42,
                          padding: const EdgeInsets.symmetric(horizontal: 14),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              InkWell(
                                onTap: () => _stepMonth(-1),
                                child: const Padding(
                                  padding: EdgeInsets.all(4),
                                  child: Text(
                                    '‹',
                                    style: TextStyle(
                                      fontSize: 20,
                                      color: Color(0xFF2E7D5B),
                                    ),
                                  ),
                                ),
                              ),
                              Text(
                                '${_monthNames[_month.month - 1]} ${_month.year}',
                                style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.bold,
                                  color: Color(0xFF17201E),
                                ),
                              ),
                              InkWell(
                                onTap: () => _stepMonth(1),
                                child: const Padding(
                                  padding: EdgeInsets.all(4),
                                  child: Text(
                                    '›',
                                    style: TextStyle(
                                      fontSize: 20,
                                      color: Color(0xFF2E7D5B),
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 12),
                        Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child:
                              isLoading
                                  ? const Padding(
                                    padding: EdgeInsets.symmetric(
                                      vertical: 60,
                                    ),
                                    child: Center(
                                      child: CircularProgressIndicator(),
                                    ),
                                  )
                                  : FutureBuilder<Map<String, String>>(
                                    future: _holidaysFuture,
                                    builder: (context, holidaySnapshot) {
                                      return _CalendarGrid(
                                        month: _month,
                                        recordMap: recordMap,
                                        holidayMap:
                                            holidaySnapshot.data ??
                                            const <String, String>{},
                                        filter: _filter,
                                        onDayTap:
                                            (record) => _openDayDetail(record),
                                      );
                                    },
                                  ),
                        ),
                        const SizedBox(height: 12),
                        if (!isLoading)
                          Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(18),
                            ),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      const Text(
                                        'សង្ខេបប្រចាំខែ',
                                        style: TextStyle(
                                          fontSize: 13,
                                          fontWeight: FontWeight.bold,
                                          color: Color(0xFF17201E),
                                        ),
                                      ),
                                      const SizedBox(height: 10),
                                      Row(
                                        children: [
                                          _MiniStat(
                                            value: present,
                                            label: 'វត្តមាន',
                                            color: const Color(0xFF2E7D5B),
                                          ),
                                          const SizedBox(width: 14),
                                          _MiniStat(
                                            value: absent,
                                            label: 'អវត្តមាន',
                                            color: const Color(0xFFE65F5C),
                                          ),
                                          const SizedBox(width: 14),
                                          _MiniStat(
                                            value: leave,
                                            label: 'ច្បាប់',
                                            color: const Color(0xFFE6943B),
                                          ),
                                          const SizedBox(width: 14),
                                          _MiniStat(
                                            value: dayOff,
                                            label: 'ថ្ងៃឈប់',
                                            color: const Color(0xFF6F7C76),
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                ),
                                Container(
                                  width: 76,
                                  height: 76,
                                  alignment: Alignment.center,
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    border: Border.all(
                                      color: dashboardHeaderStart,
                                      width: 7,
                                    ),
                                  ),
                                  child: Column(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Text(
                                        '${(rate * 100).round()}%',
                                        style: TextStyle(
                                          fontSize: 16,
                                          fontWeight: FontWeight.w800,
                                          color: dashboardHeaderStart,
                                        ),
                                      ),
                                      const Text(
                                        'អត្រាវត្តមាន',
                                        style: TextStyle(
                                          fontSize: 6,
                                          color: Color(0xFF6F7C76),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        if (!isLoading)
                          FutureBuilder<Map<String, String>>(
                            future: _holidaysFuture,
                            builder: (context, holidaySnapshot) {
                              return _buildHolidaySection(
                                holidaySnapshot.data ??
                                    const <String, String>{},
                              );
                            },
                          ),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            for (final style in attendanceStatusPalette)
                              AttendanceStatusPill(style: style, short: true),
                          ],
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
        onTap: (index) async {
          switch (index) {
            case 0:
              Navigator.of(context).pop();
              return;
            case 2:
              await _openAttendanceScanner();
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
}

class _MiniStat extends StatelessWidget {
  const _MiniStat({
    required this.value,
    required this.label,
    required this.color,
  });

  final int value;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          '$value',
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w800,
            color: color,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: const TextStyle(fontSize: 8, color: Color(0xFF6F7C76)),
        ),
      ],
    );
  }
}

class _CalendarGrid extends StatelessWidget {
  const _CalendarGrid({
    required this.month,
    required this.recordMap,
    required this.holidayMap,
    required this.filter,
    required this.onDayTap,
  });

  final DateTime month;
  final Map<String, AttendanceDayRecord> recordMap;
  final Map<String, String> holidayMap;
  final AttendanceFilterState filter;
  final void Function(AttendanceDayRecord record) onDayTap;

  static const _weekHeaders = ['អា', 'ច', 'អ', 'ព', 'ព្រ', 'សុ', 'សៅ'];
  static const _holidayColor = Color(0xFFE65F5C);
  static const _holidayBackground = Color(0xFFFDECEB);

  double _dayPercent(AttendanceDayRecord? record) {
    final raw = record?.totalHours.trim();
    if (raw == null || raw.isEmpty || raw == '-') {
      return 0;
    }
    final parts = raw.split(':');
    final hours = int.tryParse(parts.elementAt(0)) ?? 0;
    final minutes = parts.length > 1 ? int.tryParse(parts[1]) ?? 0 : 0;
    const expectedHours = 8;
    return ((hours + minutes / 60) / expectedHours).clamp(0.0, 1.0);
  }

  @override
  Widget build(BuildContext context) {
    final year = month.year;
    final m = month.month;
    final firstOffset = DateTime(year, m, 1).weekday % 7;
    final daysInMonth = DateTime(year, m + 1, 0).day;
    final totalSlots = ((firstOffset + daysInMonth) / 7).ceil() * 7;
    final now = DateTime.now();

    return Column(
      children: [
        Row(
          children:
              _weekHeaders
                  .map(
                    (h) => Expanded(
                      child: Center(
                        child: Text(
                          h,
                          style: const TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF6F7C76),
                          ),
                        ),
                      ),
                    ),
                  )
                  .toList(),
        ),
        const SizedBox(height: 8),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 7,
            mainAxisSpacing: 4,
            crossAxisSpacing: 3,
            childAspectRatio: 0.9,
          ),
          itemCount: totalSlots,
          itemBuilder: (context, index) {
            final day = index - firstOffset + 1;
            if (day < 1 || day > daysInMonth) {
              return const SizedBox.shrink();
            }
            final key =
                '$year-${m.toString().padLeft(2, '0')}-${day.toString().padLeft(2, '0')}';
            final record = recordMap[key];
            final isHoliday = holidayMap.containsKey(key);
            final isToday =
                now.year == year && now.month == m && now.day == day;
            final style =
                record == null
                    ? null
                    : attendanceStatusStyle(record.attendanceStatus);
            final matchesFilter =
                record == null || filter.matchesStatus(record.attendanceStatus);
            final percent = _dayPercent(record);
            final fillColor =
                (style == null || !matchesFilter)
                    ? Colors.transparent
                    : style.fg.withAlpha(64);

            return GestureDetector(
              onTap: record == null ? null : () => onDayTap(record),
              child: Container(
                clipBehavior: Clip.antiAlias,
                decoration: BoxDecoration(
                  color:
                      isHoliday
                          ? _holidayBackground
                          : const Color(0xFFF5F7F6),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(
                    color:
                        isToday
                            ? dashboardHeaderStart
                            : isHoliday
                            ? _holidayColor
                            : const Color(0xFFE1E8E4),
                    width: isToday ? 2 : 1,
                  ),
                ),
                child: Stack(
                  children: [
                    if (percent > 0)
                      Align(
                        alignment: Alignment.bottomCenter,
                        child: FractionallySizedBox(
                          heightFactor: percent,
                          widthFactor: 1,
                          child: Container(color: fillColor),
                        ),
                      ),
                    Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            '$day',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color:
                                  isHoliday
                                      ? _holidayColor
                                      : style == null || !matchesFilter
                                      ? const Color(0xFF17201E)
                                      : style.fg,
                            ),
                          ),
                          if (record != null)
                            Text(
                              '${(percent * 100).round()}%',
                              style: TextStyle(
                                fontSize: 7,
                                fontWeight: FontWeight.w600,
                                color:
                                    matchesFilter
                                        ? const Color(0xFF6F7C76)
                                        : const Color(0xFFB6BEBA),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ],
    );
  }
}

/// Figma "Holiday" event card — a red date tile, the holiday's name, and
/// whether it's upcoming or already past.
class _HolidayEventCard extends StatelessWidget {
  const _HolidayEventCard({required this.dateKey, required this.name});

  final String dateKey;
  final String name;

  @override
  Widget build(BuildContext context) {
    DateTime? date;
    try {
      date = DateTime.parse(dateKey);
    } catch (_) {}
    final today = DateTime.now();
    final isUpcoming =
        date != null &&
        !date.isBefore(DateTime(today.year, today.month, today.day));

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFDECEB),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: const Color(0xFFE65F5C),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              date == null ? '-' : '${date.day}',
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: Colors.white,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF17221D),
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  isUpcoming
                      ? 'ថ្ងៃឈប់សម្រាក • ខាងមុខ'
                      : 'ថ្ងៃឈប់សម្រាក • កន្លងទៅ',
                  style: const TextStyle(
                    fontSize: 9,
                    color: Color(0xFFE65F5C),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
