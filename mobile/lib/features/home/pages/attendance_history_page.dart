import 'package:flutter/material.dart';

import '../../auth/models/auth_user.dart';
import '../models/attendance_day_record.dart';
import '../services/home_attendance_service.dart';

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
  static const String _filterAll = 'all';

  late DateTime _selectedMonth;
  String _statusFilter = _filterAll;
  late Future<List<AttendanceDayRecord>> _recordsFuture;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _selectedMonth = DateTime(now.year, now.month, 1);
    _recordsFuture = _loadRecords();
  }

  String _tr(String key, String fallback) {
    final value = widget.language[key]?.trim();
    if (value == null || value.isEmpty) {
      return fallback;
    }

    return value;
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

  List<DateTime> _monthOptions() {
    final now = DateTime.now();
    return List<DateTime>.generate(12, (index) {
      return DateTime(now.year, now.month - index, 1);
    });
  }

  String _monthLabel(DateTime month) {
    const monthNames = <String>[
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

    return '${monthNames[month.month - 1]} ${month.year}';
  }

  String _statusLabel(String? statusCode) {
    final code = statusCode?.trim().toLowerCase();
    if (code == null || code.isEmpty) {
      return '-';
    }

    switch (code) {
      case 'on_time':
        return _tr('on_time', 'ទាន់ពេល');
      case 'late':
        return _tr('late', 'យឺត');
      case 'early_leave':
        return _tr('early_leave', 'ចេញមុន');
      case 'late_and_early_leave':
        return _tr('late_and_early_leave', 'យឺត និងចេញមុន');
      case 'incomplete':
        return _tr('incomplete', 'មិនពេញលេញ');
      default:
        return statusCode!.replaceAll('_', ' ').trim();
    }
  }

  Color _statusColor(String? statusCode) {
    final code = statusCode?.trim().toLowerCase();
    switch (code) {
      case 'on_time':
        return const Color(0xFF0B6B58);
      case 'late':
        return const Color(0xFFA85C00);
      case 'early_leave':
      case 'late_and_early_leave':
        return const Color(0xFFD98500);
      case 'incomplete':
        return const Color(0xFFD34B5F);
      default:
        return const Color(0xFF66746E);
    }
  }

  List<AttendanceDayRecord> _applyFilter(List<AttendanceDayRecord> records) {
    if (_statusFilter == _filterAll) {
      return records;
    }

    return records.where((record) {
      final normalized = record.attendanceStatus?.trim().toLowerCase();
      return normalized == _statusFilter;
    }).toList();
  }

  _AttendanceMonthSummary _summarize(List<AttendanceDayRecord> records) {
    var onTime = 0;
    var late = 0;
    var incomplete = 0;
    var earlyLeave = 0;
    var lateMinutes = 0;
    var earlyLeaveMinutes = 0;

    for (final record in records) {
      final status = record.attendanceStatus?.trim().toLowerCase();
      if (status == 'on_time') {
        onTime += 1;
      }
      if (status == 'late' || status == 'late_and_early_leave') {
        late += 1;
      }
      if (status == 'incomplete' || record.hasException == true) {
        incomplete += 1;
      }
      if (status == 'early_leave' || status == 'late_and_early_leave') {
        earlyLeave += 1;
      }
      lateMinutes += record.lateMinutes ?? 0;
      earlyLeaveMinutes += record.earlyLeaveMinutes ?? 0;
    }

    return _AttendanceMonthSummary(
      totalDays: records.length,
      onTimeDays: onTime,
      lateDays: late,
      earlyLeaveDays: earlyLeave,
      incompleteDays: incomplete,
      lateMinutes: lateMinutes,
      earlyLeaveMinutes: earlyLeaveMinutes,
    );
  }

  Future<void> _refresh() async {
    setState(() {
      _recordsFuture = _loadRecords();
    });
    await _recordsFuture;
  }

  String _formatDate(String raw) {
    try {
      final date = DateTime.parse(raw);
      final dd = date.day.toString().padLeft(2, '0');
      final mm = date.month.toString().padLeft(2, '0');
      return '$dd/$mm/${date.year}';
    } catch (_) {
      return raw;
    }
  }

  void _showRecordDetails(AttendanceDayRecord record) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        final statusColor = _statusColor(record.attendanceStatus);

        return DraggableScrollableSheet(
          initialChildSize: 0.58,
          minChildSize: 0.42,
          maxChildSize: 0.82,
          expand: false,
          builder: (context, controller) {
            final sheetBottomPadding = MediaQuery.of(context).padding.bottom + 24;

            return Container(
              decoration: const BoxDecoration(
                color: Color(0xFFF8FBFA),
                borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              ),
              child: ListView(
                controller: controller,
                padding: EdgeInsets.fromLTRB(16, 10, 16, sheetBottomPadding),
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 5,
                      decoration: BoxDecoration(
                        color: const Color(0xFFC9D8D1),
                        borderRadius: BorderRadius.circular(999),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    _tr('attendance_detail', 'ព័ត៌មានលម្អិតវត្តមាន'),
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF112A23),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFE2EAE7)),
                    ),
                    child: Column(
                      children: [
                        _DetailRow(
                          label: _tr('date', 'កាលបរិច្ឆេទ'),
                          value: _formatDate(record.date),
                        ),
                        _DetailRow(
                          label: _tr('status', 'ស្ថានភាព'),
                          value: _statusLabel(record.attendanceStatus),
                          valueColor: statusColor,
                        ),
                        _DetailRow(
                          label: _tr('in_time', 'ម៉ោងចូល'),
                          value: record.timeIn,
                        ),
                        _DetailRow(
                          label: _tr('out_time', 'ម៉ោងចេញ'),
                          value: record.timeOut,
                        ),
                        _DetailRow(
                          label: _tr('total_hours', 'ម៉ោងសរុប'),
                          value: record.totalHours,
                        ),
                        _DetailRow(
                          label: _tr('punches', 'ចំនួនស្កេន'),
                          value: '${record.punchCount}',
                        ),
                        _DetailRow(
                          label: _tr('late', 'យឺត'),
                          value: '${record.lateMinutes ?? 0} នាទី',
                        ),
                        _DetailRow(
                          label: _tr('early_leave', 'ចេញមុន'),
                          value: '${record.earlyLeaveMinutes ?? 0} នាទី',
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final monthOptions = _monthOptions();
    final listBottomPadding = MediaQuery.of(context).padding.bottom + 28;
    final filterItems = <_StatusFilterItem>[
      _StatusFilterItem(code: _filterAll, label: _tr('all', 'ទាំងអស់')),
      _StatusFilterItem(code: 'on_time', label: _tr('on_time', 'ទាន់ពេល')),
      _StatusFilterItem(code: 'late', label: _tr('late', 'យឺត')),
      _StatusFilterItem(
        code: 'early_leave',
        label: _tr('early_leave', 'ចេញមុន'),
      ),
      _StatusFilterItem(
        code: 'incomplete',
        label: _tr('incomplete', 'មិនពេញលេញ'),
      ),
    ];

    return Scaffold(
      appBar: AppBar(title: Text(_tr('attendance_history', 'ប្រវត្តិវត្តមាន'))),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<AttendanceDayRecord>>(
          future: _recordsFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return ListView(
                padding: EdgeInsets.fromLTRB(16, 16, 16, listBottomPadding),
                children: const [
                  SizedBox(height: 120),
                  Center(child: CircularProgressIndicator()),
                ],
              );
            }

            if (snapshot.hasError) {
              return ListView(
                padding: EdgeInsets.fromLTRB(16, 16, 16, listBottomPadding),
                children: [
                  _HistoryErrorCard(
                    title: _tr('attendance_history', 'ប្រវត្តិវត្តមាន'),
                    message: '${snapshot.error}',
                    onRetry: _refresh,
                  ),
                ],
              );
            }

            final allRecords = snapshot.data ?? const <AttendanceDayRecord>[];
            final records = _applyFilter(allRecords);
            final summary = _summarize(allRecords);

            return ListView(
              padding: EdgeInsets.fromLTRB(16, 12, 16, listBottomPadding),
              children: [
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2EAE7)),
                    boxShadow: const [
                      BoxShadow(
                        color: Color(0x0F12352C),
                        blurRadius: 16,
                        offset: Offset(0, 8),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _tr('filter', 'តម្រង'),
                        style: const TextStyle(
                          color: Color(0xFF123C32),
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<DateTime>(
                        initialValue: _selectedMonth,
                        decoration: InputDecoration(
                          labelText: _tr('month', 'ខែ'),
                          filled: true,
                          fillColor: const Color(0xFFF7FBF9),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(
                              color: Color(0xFFDCEAE3),
                            ),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(
                              color: Color(0xFFDCEAE3),
                            ),
                          ),
                        ),
                        items:
                            monthOptions.map((month) {
                              return DropdownMenuItem<DateTime>(
                                value: month,
                                child: Text(_monthLabel(month)),
                              );
                            }).toList(),
                        onChanged: (value) {
                          if (value == null) {
                            return;
                          }

                          setState(() {
                            _selectedMonth = value;
                            _recordsFuture = _loadRecords();
                          });
                        },
                      ),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          for (final item in filterItems)
                            ChoiceChip(
                              label: Text(item.label),
                              selected: _statusFilter == item.code,
                              selectedColor: const Color(0xFFDDF2E8),
                              side: const BorderSide(color: Color(0xFFCFE4DA)),
                              labelStyle: TextStyle(
                                color:
                                    _statusFilter == item.code
                                        ? const Color(0xFF0B6B58)
                                        : const Color(0xFF42554E),
                                fontWeight: FontWeight.w700,
                              ),
                              onSelected: (_) {
                                setState(() {
                                  _statusFilter = item.code;
                                });
                              },
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FBFA),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFFE2EAE7)),
                  ),
                  child: Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _LegendChip(
                        label: _tr('on_time', 'ទាន់ពេល'),
                        color: const Color(0xFF15803D),
                      ),
                      _LegendChip(
                        label: _tr('late', 'យឺត'),
                        color: const Color(0xFFA85C00),
                      ),
                      _LegendChip(
                        label: _tr('early_leave', 'ចេញមុន'),
                        color: const Color(0xFFD98500),
                      ),
                      _LegendChip(
                        label: _tr('incomplete', 'មិនពេញលេញ'),
                        color: const Color(0xFFD34B5F),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                GridView.count(
                  crossAxisCount: 2,
                  crossAxisSpacing: 10,
                  mainAxisSpacing: 10,
                  childAspectRatio: 1.42,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  children: [
                    _SummaryCard(
                      title: _tr('total', 'សរុប'),
                      value: '${summary.totalDays}',
                      icon: Icons.calendar_month_outlined,
                      color: const Color(0xFF0B6B58),
                    ),
                    _SummaryCard(
                      title: _tr('on_time', 'ទាន់ពេល'),
                      value: '${summary.onTimeDays}',
                      icon: Icons.verified_outlined,
                      color: const Color(0xFF0B6B58),
                    ),
                    _SummaryCard(
                      title: _tr('late', 'យឺត'),
                      value: '${summary.lateDays}',
                      icon: Icons.timer_outlined,
                      color: const Color(0xFFA85C00),
                    ),
                    _SummaryCard(
                      title: _tr('incomplete', 'មិនពេញលេញ'),
                      value: '${summary.incompleteDays}',
                      icon: Icons.error_outline,
                      color: const Color(0xFFD34B5F),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2EAE7)),
                    boxShadow: const [
                      BoxShadow(
                        color: Color(0x0E15332B),
                        blurRadius: 14,
                        offset: Offset(0, 7),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: _MiniStat(
                          label: _tr('late_minutes', 'នាទីយឺត'),
                          value: '${summary.lateMinutes}',
                          color: const Color(0xFFA85C00),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _MiniStat(
                          label: _tr('early_leave_minutes', 'នាទីចេញមុន'),
                          value: '${summary.earlyLeaveMinutes}',
                          color: const Color(0xFFD98500),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  _tr('attendance_list', 'បញ្ជីវត្តមាន'),
                  style: const TextStyle(
                    color: Color(0xFF123B32),
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                if (records.isEmpty)
                  _HistoryEmptyCard(
                    message: _tr('no_record_found', 'មិនមានទិន្នន័យវត្តមាន'),
                  )
                else
                  ...records.map((record) {
                    final statusColor = _statusColor(record.attendanceStatus);
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(14),
                        onTap: () => _showRecordDetails(record),
                        child: Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: const Color(0xFFE2EAE7)),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      _formatDate(record.date),
                                      style: const TextStyle(
                                        color: Color(0xFF18312A),
                                        fontWeight: FontWeight.w800,
                                        fontSize: 15,
                                      ),
                                    ),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 9,
                                      vertical: 5,
                                    ),
                                    decoration: BoxDecoration(
                                      color: statusColor.withAlpha(30),
                                      borderRadius: BorderRadius.circular(999),
                                    ),
                                    child: Text(
                                      _statusLabel(record.attendanceStatus),
                                      style: TextStyle(
                                        color: statusColor,
                                        fontWeight: FontWeight.w800,
                                        fontSize: 11,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: [
                                  _HistoryPill(
                                    icon: Icons.login,
                                    text:
                                        '${_tr('in_time', 'ចូល')}: ${record.timeIn}',
                                  ),
                                  _HistoryPill(
                                    icon: Icons.logout,
                                    text:
                                        '${_tr('out_time', 'ចេញ')}: ${record.timeOut}',
                                  ),
                                  _HistoryPill(
                                    icon: Icons.timer_outlined,
                                    text:
                                        '${_tr('total_hours', 'សរុប')}: ${record.totalHours}',
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                    );
                  }),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _StatusFilterItem {
  const _StatusFilterItem({required this.code, required this.label});

  final String code;
  final String label;
}

class _AttendanceMonthSummary {
  const _AttendanceMonthSummary({
    required this.totalDays,
    required this.onTimeDays,
    required this.lateDays,
    required this.earlyLeaveDays,
    required this.incompleteDays,
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
  });

  final int totalDays;
  final int onTimeDays;
  final int lateDays;
  final int earlyLeaveDays;
  final int incompleteDays;
  final int lateMinutes;
  final int earlyLeaveMinutes;
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({
    required this.title,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String title;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2EAE7)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0D13342B),
            blurRadius: 12,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: color.withAlpha(30),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: color, size: 18),
          ),
          const Spacer(),
          Text(
            value,
            style: TextStyle(
              color: color,
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            title,
            style: const TextStyle(
              color: Color(0xFF445A52),
              fontWeight: FontWeight.w700,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}

class _MiniStat extends StatelessWidget {
  const _MiniStat({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
      decoration: BoxDecoration(
        color: color.withAlpha(18),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: Color(0xFF4D645C),
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: TextStyle(
              color: color,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _HistoryPill extends StatelessWidget {
  const _HistoryPill({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: const Color(0xFFF2F8F5),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFDCEAE3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: const Color(0xFF0B6B58)),
          const SizedBox(width: 5),
          Text(
            text,
            style: const TextStyle(
              color: Color(0xFF1A3930),
              fontSize: 12,
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _LegendChip extends StatelessWidget {
  const _LegendChip({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withAlpha(20),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withAlpha(40)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _HistoryErrorCard extends StatelessWidget {
  const _HistoryErrorCard({
    required this.title,
    required this.message,
    required this.onRetry,
  });

  final String title;
  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFF0CED5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.error_outline, color: Color(0xFFD34B5F)),
          const SizedBox(height: 8),
          Text(
            title,
            style: const TextStyle(
              color: Color(0xFF153029),
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Text(message, style: const TextStyle(color: Color(0xFF5D726A))),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: const Text('Retry'),
          ),
        ],
      ),
    );
  }
}

class _HistoryEmptyCard extends StatelessWidget {
  const _HistoryEmptyCard({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2EAE7)),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: const Color(0xFFEFF6F3),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.info_outline, color: Color(0xFF0B6B58)),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                color: Color(0xFF3E554D),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value, this.valueColor});

  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 115,
            child: Text(
              label,
              style: const TextStyle(
                color: Color(0xFF5B6E67),
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value.isEmpty ? '-' : value,
              style: TextStyle(
                color: valueColor ?? const Color(0xFF132D25),
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
