import 'package:flutter/material.dart';

import '../../auth/models/auth_user.dart';
import '../models/attendance_day_record.dart';
import 'attendance_filter_sheet.dart';
import 'attendance_scan_page.dart';
import '../services/home_attendance_service.dart';
import 'home/attendance_status.dart';
import 'home/home_menu.dart';
import 'home/home_nav_widgets.dart';

/// Attendance Statistics (Figma "ស្ថិតិវត្តមាន / Improved") — an attendance
/// rate ring, day-count summary cards, and a proportional breakdown of
/// on-time / late / absent days for the selected period. Reached from the
/// Dashboard's attendance ring card.
class AttendanceStatisticsPage extends StatefulWidget {
  const AttendanceStatisticsPage({
    super.key,
    required this.user,
    required this.attendanceService,
    required this.language,
  });

  final AuthUser user;
  final HomeAttendanceService attendanceService;
  final Map<String, String> language;

  @override
  State<AttendanceStatisticsPage> createState() =>
      _AttendanceStatisticsPageState();
}

class _AttendanceStatisticsPageState extends State<AttendanceStatisticsPage> {
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

  String _filterLabel() {
    if (!_filter.isCustomRange) {
      return 'ខែ${_monthNames[_filter.fromDate.month - 1]} ${_filter.fromDate.year}';
    }
    String short(DateTime d) => '${d.day} ${_monthNames[d.month - 1]}';
    return '${short(_filter.fromDate)} – ${short(_filter.toDate)} ${_filter.toDate.year}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F7FA),
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
                      'ស្ថិតិវត្តមាន',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF17201E),
                      ),
                    ),
                  ),
                  const SizedBox(width: 22),
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

                    final onTime = countOf('on_time');
                    final late = countOf('late');
                    final absent = countOf('absent');
                    final incomplete = countOf('incomplete');
                    final workingTotal = onTime + late + absent + incomplete;
                    final rate =
                        workingTotal == 0
                            ? 0.0
                            : (onTime + late) / workingTotal;
                    final maxCount = [
                      onTime,
                      late,
                      absent,
                    ].fold<int>(0, (m, v) => v > m ? v : m);

                    return ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        InkWell(
                          onTap: _openFilter,
                          borderRadius: BorderRadius.circular(12),
                          child: Container(
                            height: 48,
                            padding: const EdgeInsets.symmetric(horizontal: 14),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              border: Border.all(
                                color: const Color(0xFFE2E8E6),
                              ),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  _filterLabel(),
                                  style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: Color(0xFF17201E),
                                  ),
                                ),
                                const Icon(
                                  Icons.tune_rounded,
                                  size: 20,
                                  color: Color(0xFF66736F),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 14),
                        if (isLoading)
                          const Padding(
                            padding: EdgeInsets.symmetric(vertical: 60),
                            child: Center(child: CircularProgressIndicator()),
                          )
                        else ...[
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(20),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(16),
                              boxShadow: const [
                                BoxShadow(
                                  color: Color(0x14102A24),
                                  blurRadius: 16,
                                  offset: Offset(0, 4),
                                ),
                              ],
                            ),
                            child: Center(
                              child: SizedBox(
                                width: 142,
                                height: 142,
                                child: Stack(
                                  alignment: Alignment.center,
                                  children: [
                                    SizedBox(
                                      width: 142,
                                      height: 142,
                                      child: CircularProgressIndicator(
                                        value: rate,
                                        strokeWidth: 12,
                                        backgroundColor: const Color(
                                          0xFFE6EFEC,
                                        ),
                                        valueColor:
                                            const AlwaysStoppedAnimation<Color>(
                                              Color(0xFF23845B),
                                            ),
                                      ),
                                    ),
                                    Column(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Text(
                                          '${(rate * 100).round()}%',
                                          style: const TextStyle(
                                            fontSize: 32,
                                            fontWeight: FontWeight.bold,
                                            color: Color(0xFF23845B),
                                          ),
                                        ),
                                        const Text(
                                          'អត្រាវត្តមាន',
                                          style: TextStyle(
                                            fontSize: 12,
                                            color: Color(0xFF66736F),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: 14),
                          _SummaryRow(
                            value: onTime,
                            label: 'វត្តមាន',
                            color: const Color(0xFF23845B),
                          ),
                          const SizedBox(height: 10),
                          _SummaryRow(
                            value: late,
                            label: 'យឺត',
                            color: const Color(0xFFD99A00),
                          ),
                          const SizedBox(height: 10),
                          _SummaryRow(
                            value: absent,
                            label: 'អវត្តមាន',
                            color: const Color(0xFFC94444),
                          ),
                          const SizedBox(height: 14),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(16),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'ការបែងចែកវត្តមាន',
                                  style: TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.bold,
                                    color: Color(0xFF17201E),
                                  ),
                                ),
                                const SizedBox(height: 14),
                                _BreakdownBar(
                                  label: 'វត្តមាន',
                                  value: onTime,
                                  maxValue: maxCount,
                                  color: const Color(0xFF23845B),
                                ),
                                const SizedBox(height: 12),
                                _BreakdownBar(
                                  label: 'យឺត',
                                  value: late,
                                  maxValue: maxCount,
                                  color: const Color(0xFFD99A00),
                                ),
                                const SizedBox(height: 12),
                                _BreakdownBar(
                                  label: 'អវត្តមាន',
                                  value: absent,
                                  maxValue: maxCount,
                                  color: const Color(0xFFC94444),
                                ),
                              ],
                            ),
                          ),
                        ],
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

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({
    required this.value,
    required this.label,
    required this.color,
  });

  final int value;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            '$value ថ្ងៃ',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: color,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: const TextStyle(fontSize: 12, color: Color(0xFF66736F)),
          ),
        ],
      ),
    );
  }
}

class _BreakdownBar extends StatelessWidget {
  const _BreakdownBar({
    required this.label,
    required this.value,
    required this.maxValue,
    required this.color,
  });

  final String label;
  final int value;
  final int maxValue;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final fraction = maxValue == 0 ? 0.0 : value / maxValue;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(fontSize: 12, color: Color(0xFF17201E)),
        ),
        const SizedBox(height: 5),
        LayoutBuilder(
          builder: (context, constraints) {
            return Container(
              height: 12,
              width: constraints.maxWidth * fraction.clamp(0.0, 1.0),
              constraints: const BoxConstraints(minWidth: 12),
              decoration: BoxDecoration(
                color: color,
                borderRadius: BorderRadius.circular(999),
              ),
            );
          },
        ),
      ],
    );
  }
}
