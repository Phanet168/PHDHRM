import 'package:flutter/material.dart';

import '../../auth/models/auth_user.dart';
import '../models/attendance_day_record.dart';
import 'attendance_calendar_page.dart';
import 'attendance_day_detail_page.dart';
import 'attendance_filter_sheet.dart';
import '../services/home_attendance_service.dart';
import 'home/attendance_status.dart';
import 'home/home_menu.dart';
import 'home/home_nav_widgets.dart';
import 'home/home_theme.dart';

/// Attendance History (Figma "ប្រវត្តិវត្តមាន / Improved") — quick status
/// chips + a 3-stat summary over a flat, zebra-striped day list. The
/// header's "ប្រតិទិន" link pushes the month-calendar view of the same data.
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
  late AttendanceFilterState _filter;
  late Future<List<AttendanceDayRecord>> _recordsFuture;

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

  static const _weekdayNames = [
    'ចន្ទ',
    'អង្គារ',
    'ពុធ',
    'ព្រហស្បតិ៍',
    'សុក្រ',
    'សៅរ៍',
    'អាទិត្យ',
  ];

  static const _quickChips = [
    MapEntry('', 'ទាំងអស់'),
    MapEntry('on_time', 'វត្តមាន'),
    MapEntry('absent', 'អវត្តមាន'),
    MapEntry('leave', 'ច្បាប់'),
    MapEntry('late', 'យឺត'),
  ];

  @override
  void initState() {
    super.initState();
    _filter = AttendanceFilterState.forMonth(DateTime.now());
    _recordsFuture = _loadRecords();
  }

  Future<List<AttendanceDayRecord>> _loadRecords() {
    return widget.attendanceService.fetchAttendanceHistory(
      widget.user,
      fromDate: _filter.fromDate,
      toDate: _filter.toDate,
    );
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

  void _downloadReport() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('មុខងារនេះកំពុងអភិវឌ្ឍ')),
    );
  }

  String _filterLabel() {
    if (!_filter.isCustomRange) {
      return '${_monthNames[_filter.fromDate.month - 1]} ${_filter.fromDate.year}';
    }
    String short(DateTime d) => '${d.day} ${_monthNames[d.month - 1]}';
    return '${short(_filter.fromDate)} – ${short(_filter.toDate)} ${_filter.toDate.year}';
  }

  String _statusFilterLabel() {
    if (_filter.statusCodes.isEmpty) {
      return 'ទាំងអស់';
    }
    return _filter.statusCodes
        .map(
          (code) => attendanceStatusPalette
              .firstWhere(
                (style) => style.code == code,
                orElse: () => attendanceStatusPalette.first,
              )
              .shortLabel,
        )
        .join(', ');
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
                        color: Color(0xFF17221D),
                      ),
                    ),
                  ),
                  const Expanded(
                    child: Text(
                      'ប្រវត្តិវត្តមាន',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF17221D),
                      ),
                    ),
                  ),
                  InkWell(
                    onTap: _openCalendar,
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 4,
                        vertical: 8,
                      ),
                      child: Text(
                        'ប្រតិទិន',
                        style: TextStyle(
                          fontSize: 12,
                          color: dashboardHeaderStart,
                        ),
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
                    final all = snapshot.data ?? const <AttendanceDayRecord>[];
                    final records =
                        all
                            .where(
                              (r) => _filter.matchesStatus(r.attendanceStatus),
                            )
                            .toList()
                          ..sort((a, b) => b.date.compareTo(a.date));

                    int countOf(String code) =>
                        all
                            .where(
                              (r) =>
                                  attendanceStatusStyle(
                                    r.attendanceStatus,
                                  ).code ==
                                  code,
                            )
                            .length;

                    return ListView(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: _FilterChip(
                                label: _filterLabel(),
                                onTap: _openFilter,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: _FilterChip(
                                label: _statusFilterLabel(),
                                onTap: _openFilter,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          child: Row(
                            children: [
                              for (var i = 0; i < _quickChips.length; i++) ...[
                                _QuickStatusChip(
                                  label: _quickChips[i].value,
                                  selected:
                                      _quickChips[i].key.isEmpty
                                          ? _filter.statusCodes.isEmpty
                                          : _filter.statusCodes.contains(
                                            _quickChips[i].key,
                                          ),
                                  onTap:
                                      () => _setQuickStatus(_quickChips[i].key),
                                ),
                                if (i != _quickChips.length - 1)
                                  const SizedBox(width: 6),
                              ],
                            ],
                          ),
                        ),
                        const SizedBox(height: 10),
                        if (!isLoading)
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceAround,
                              children: [
                                _SummaryStat(
                                  value: countOf('on_time'),
                                  label: 'វត្តមាន',
                                  color: const Color(0xFF23845B),
                                ),
                                _SummaryStat(
                                  value: countOf('absent'),
                                  label: 'អវត្តមាន',
                                  color: const Color(0xFFE65F5C),
                                ),
                                _SummaryStat(
                                  value: countOf('leave'),
                                  label: 'ច្បាប់',
                                  color: const Color(0xFFE6943B),
                                ),
                              ],
                            ),
                          ),
                        const SizedBox(height: 10),
                        if (isLoading)
                          const Padding(
                            padding: EdgeInsets.symmetric(vertical: 60),
                            child: Center(child: CircularProgressIndicator()),
                          )
                        else if (snapshot.hasError)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 40),
                            child: Center(
                              child: Column(
                                children: [
                                  Text('${snapshot.error}'),
                                  const SizedBox(height: 10),
                                  FilledButton(
                                    onPressed: _refresh,
                                    child: const Text('ព្យាយាមម្ដងទៀត'),
                                  ),
                                ],
                              ),
                            ),
                          )
                        else if (records.isEmpty)
                          const Padding(
                            padding: EdgeInsets.symmetric(vertical: 60),
                            child: Center(
                              child: Text(
                                'មិនទាន់មានទិន្នន័យវត្តមានតាមលក្ខខណ្ឌនេះ',
                                style: TextStyle(color: Color(0xFF66736F)),
                              ),
                            ),
                          )
                        else
                          Column(
                            children: [
                              for (var i = 0; i < records.length; i++) ...[
                                _HistoryRow(
                                  record: records[i],
                                  weekdayNames: _weekdayNames,
                                  onTap: () => _openDayDetail(records[i]),
                                ),
                                if (i != records.length - 1)
                                  const Padding(
                                    padding: EdgeInsets.symmetric(
                                      vertical: 6,
                                    ),
                                    child: Divider(
                                      height: 1,
                                      color: Color(0xFFE1E8E4),
                                    ),
                                  ),
                              ],
                            ],
                          ),
                        const SizedBox(height: 16),
                        FilledButton.icon(
                          onPressed: _downloadReport,
                          style: FilledButton.styleFrom(
                            backgroundColor: dashboardHeaderStart,
                            minimumSize: const Size.fromHeight(52),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                          icon: const Icon(Icons.download_outlined),
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
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        height: 42,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border.all(color: const Color(0xFFE1E8E4)),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF17221D),
                ),
              ),
            ),
            const Icon(
              Icons.keyboard_arrow_down_rounded,
              size: 18,
              color: Color(0xFF2E7D5B),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuickStatusChip extends StatelessWidget {
  const _QuickStatusChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? dashboardHeaderStart : Colors.white,
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
          child: Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: selected ? Colors.white : const Color(0xFF6F7C76),
            ),
          ),
        ),
      ),
    );
  }
}

class _SummaryStat extends StatelessWidget {
  const _SummaryStat({
    required this.value,
    required this.label,
    required this.color,
  });

  final int value;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 7,
          height: 7,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 6),
        Text(
          '$value',
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w800,
            color: Color(0xFF17221D),
          ),
        ),
        const SizedBox(width: 4),
        Text(
          label,
          style: const TextStyle(fontSize: 10, color: Color(0xFF6F7C76)),
        ),
      ],
    );
  }
}

class _HistoryRow extends StatelessWidget {
  const _HistoryRow({
    required this.record,
    required this.weekdayNames,
    required this.onTap,
  });

  final AttendanceDayRecord record;
  final List<String> weekdayNames;
  final VoidCallback onTap;

  String _formatHours(String raw) {
    final text = raw.trim();
    if (text.isEmpty || text == '-') return '0h 00m';
    final parts = text.split(':');
    final hours = int.tryParse(parts.elementAt(0)) ?? 0;
    final minutes = parts.length > 1 ? int.tryParse(parts[1]) ?? 0 : 0;
    return '${hours}h ${minutes.toString().padLeft(2, '0')}m';
  }

  @override
  Widget build(BuildContext context) {
    DateTime? date;
    try {
      date = DateTime.parse(record.date);
    } catch (_) {}
    final style = attendanceStatusStyle(record.attendanceStatus);
    final sessions = record.sessions;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  date == null ? '-' : '${date.day}',
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF17221D),
                  ),
                ),
                const SizedBox(width: 6),
                Text(
                  date == null ? '' : weekdayNames[date.weekday - 1],
                  style: const TextStyle(
                    fontSize: 11,
                    color: Color(0xFF6F7C76),
                  ),
                ),
                const Spacer(),
                Text(
                  _formatHours(record.totalHours),
                  style: const TextStyle(
                    fontSize: 10,
                    color: Color(0xFF6F7C76),
                  ),
                ),
                const SizedBox(width: 8),
                AttendanceStatusPill(style: style, short: true),
              ],
            ),
            const SizedBox(height: 8),
            if (sessions.isEmpty)
              _SessionShiftCard(
                title: 'ម៉ោងធ្វើការ',
                timeIn: record.timeIn,
                timeOut: record.timeOut,
                scheduledIn: '',
                scheduledOut: '',
                lateMinutes: record.lateMinutes ?? 0,
                earlyLeaveMinutes: record.earlyLeaveMinutes ?? 0,
              )
            else
              for (final session in sessions) ...[
                _SessionShiftCard(
                  title: 'ពេល៖ ${attendanceSessionLabel(session.name)}',
                  timeIn: session.timeIn,
                  timeOut: session.timeOut,
                  scheduledIn: session.scheduledIn,
                  scheduledOut: session.scheduledOut,
                  lateMinutes: session.lateMinutes,
                  earlyLeaveMinutes: session.earlyLeaveMinutes,
                ),
                if (session != sessions.last) const SizedBox(height: 6),
              ],
          ],
        ),
      ),
    );
  }
}

class _SessionShiftCard extends StatelessWidget {
  const _SessionShiftCard({
    required this.title,
    required this.timeIn,
    required this.timeOut,
    required this.scheduledIn,
    required this.scheduledOut,
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
  });

  final String title;
  final String timeIn;
  final String timeOut;
  final String scheduledIn;
  final String scheduledOut;
  final int lateMinutes;
  final int earlyLeaveMinutes;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            color: const Color(0xFFBFD9F7),
            child: Text(
              title,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: Color(0xFF15366B),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: _SessionPunchColumn(
                    label: 'ចូល',
                    icon: Icons.arrow_downward_rounded,
                    iconColor: const Color(0xFF2E7D5B),
                    iconBackground: const Color(0xFFE8F4EE),
                    time: timeIn,
                    scheduled: scheduledIn,
                    calloutLabel: lateMinutes > 0 ? 'ចូលយឺត' : null,
                    calloutMinutes: lateMinutes,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _SessionPunchColumn(
                    label: 'ចេញ',
                    icon: Icons.arrow_upward_rounded,
                    iconColor: const Color(0xFFA85C00),
                    iconBackground: const Color(0xFFFFF6E1),
                    time: timeOut,
                    scheduled: scheduledOut,
                    calloutLabel: earlyLeaveMinutes > 0 ? 'ចេញមុន' : null,
                    calloutMinutes: earlyLeaveMinutes,
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

class _SessionPunchColumn extends StatelessWidget {
  const _SessionPunchColumn({
    required this.label,
    required this.icon,
    required this.iconColor,
    required this.iconBackground,
    required this.time,
    required this.scheduled,
    required this.calloutLabel,
    required this.calloutMinutes,
  });

  final String label;
  final IconData icon;
  final Color iconColor;
  final Color iconBackground;
  final String time;
  final String scheduled;
  final String? calloutLabel;
  final int calloutMinutes;

  @override
  Widget build(BuildContext context) {
    final hasTime = time.trim().isNotEmpty && time.trim() != '-';
    final hasScheduled = scheduled.trim().isNotEmpty && scheduled.trim() != '-';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              width: 24,
              height: 24,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: iconBackground,
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 14, color: iconColor),
            ),
            const SizedBox(width: 7),
            Text(
              label,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Color(0xFF4A5A55),
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Text(
          hasTime ? time : '-',
          style: const TextStyle(
            fontSize: 21,
            fontWeight: FontWeight.w800,
            color: Color(0xFF17221D),
          ),
        ),
        if (hasScheduled) ...[
          const SizedBox(height: 4),
          Text(
            'កាលវិភាគ៖ $scheduled',
            style: const TextStyle(fontSize: 12, color: Color(0xFF5C6B66)),
          ),
        ],
        if (calloutLabel != null && calloutMinutes > 0) ...[
          const SizedBox(height: 3),
          Text(
            '$calloutLabel $calloutMinutes នាទី',
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: Color(0xFFE65F5C),
            ),
          ),
        ],
      ],
    );
  }
}
