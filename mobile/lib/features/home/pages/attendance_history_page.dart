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
  late final TextEditingController _dateSearchController;
  bool _showSearchField = false;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _selectedMonth = DateTime(now.year, now.month, 1);
    _dateSearchController = TextEditingController();
    _recordsFuture = _loadRecords();
  }

  @override
  void dispose() {
    _dateSearchController.dispose();
    super.dispose();
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

    return monthNames[month.month - 1];
  }

  List<int> _yearOptions() {
    final now = DateTime.now();
    return List<int>.generate(4, (index) => now.year - index);
  }

  String _weekdayLabel(String raw) {
    try {
      final date = DateTime.parse(raw);
      const names = <String>[
        'ចន្ទ',
        'អង្គារ',
        'ពុធ',
        'ព្រហស្បតិ៍',
        'សុក្រ',
        'សៅរ៍',
        'អាទិត្យ',
      ];
      return names[date.weekday - 1];
    } catch (_) {
      return '-';
    }
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
      case 'mission':
      case 'm':
        return _tr('mission', 'បេសកកម្ម');
      case 'leave':
      case 'lv':
        return _tr('leave_type', 'សុំច្បាប់');
      case 'absent':
      case 'a':
        return _tr('absent', 'អវត្តមាន');
      case 'present':
      case 'p':
        return _tr('checked_in', 'បានចេញហើយ');
      default:
        return statusCode!.replaceAll('_', ' ').trim();
    }
  }

  Color _statusColor(String? statusCode) {
    final code = statusCode?.trim().toLowerCase();
    switch (code) {
      case 'on_time':
      case 'present':
      case 'p':
        return const Color(0xFF0B6B58);
      case 'late':
        return const Color(0xFFA85C00);
      case 'early_leave':
      case 'late_and_early_leave':
        return const Color(0xFFD98500);
      case 'mission':
      case 'm':
        return const Color(0xFF5B79B7);
      case 'leave':
      case 'lv':
        return const Color(0xFF9B7AD6);
      case 'absent':
      case 'a':
        return const Color(0xFFD36B61);
      case 'incomplete':
        return const Color(0xFFD34B5F);
      default:
        return const Color(0xFF66746E);
    }
  }

  Color _statusBackground(String? statusCode) {
    return _statusColor(statusCode).withAlpha(22);
  }

  String _resolvedShift(AttendanceDayRecord record) {
    if (record.timeIn != '-' && record.timeOut != '-') {
      return 'ព្រឹក-រសៀល';
    }
    if (record.timeIn != '-') {
      return 'ព្រឹក';
    }
    if (record.timeOut != '-') {
      return 'រសៀល';
    }
    return '-';
  }

  String _scanWindowText(AttendanceDayRecord record) {
    if (record.timeIn != '-' || record.timeOut != '-') {
      return 'អាស្រ័យតាមវេណការងារ';
    }
    return '-';
  }

  bool _matchesSearch(AttendanceDayRecord record) {
    final query = _dateSearchController.text.trim().toLowerCase();
    if (query.isEmpty) {
      return true;
    }

    final formattedDate = _formatDate(record.date).toLowerCase();
    final weekday = _weekdayLabel(record.date).toLowerCase();
    return formattedDate.contains(query) ||
        record.date.toLowerCase().contains(query) ||
        weekday.contains(query);
  }

  List<AttendanceDayRecord> _applyFilter(List<AttendanceDayRecord> records) {
    return records.where((record) {
      if (!_matchesSearch(record)) {
        return false;
      }
      if (_statusFilter == _filterAll) {
        return true;
      }
      final normalized = record.attendanceStatus?.trim().toLowerCase();
      return normalized == _statusFilter;
    }).toList();
  }

  _AttendanceMonthSummary _summarize(List<AttendanceDayRecord> records) {
    var onTime = 0;
    var late = 0;
    var incomplete = 0;
    var earlyLeave = 0;
    var leaveDays = 0;
    var missionDays = 0;
    var absentDays = 0;
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
      if (status == 'leave' || status == 'lv') {
        leaveDays += 1;
      }
      if (status == 'mission' || status == 'm') {
        missionDays += 1;
      }
      if (status == 'absent' || status == 'a') {
        absentDays += 1;
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
      leaveDays: leaveDays,
      missionDays: missionDays,
      absentDays: absentDays,
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

  void _requestAttendanceAdjustment(AttendanceDayRecord record) {
    Navigator.of(context).pop();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          '${_tr('attendance_adjustment', 'កែវត្តមាន')}: ${_formatDate(record.date)}',
        ),
      ),
    );
  }

  void _showRecordDetails(AttendanceDayRecord record) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        final statusColor = _statusColor(record.attendanceStatus);
        final statusBackground = _statusBackground(record.attendanceStatus);
        final shiftLabel = _resolvedShift(record);
        final weekday = _weekdayLabel(record.date);

        return DraggableScrollableSheet(
          initialChildSize: 0.64,
          minChildSize: 0.42,
          maxChildSize: 0.86,
          expand: false,
          builder: (context, controller) {
            final sheetBottomPadding =
                MediaQuery.of(context).padding.bottom + 24;

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
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _tr('attendance_detail', 'ព័ត៌មានលម្អិតវត្តមាន'),
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF112A23),
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${_formatDate(record.date)} · $weekday',
                              style: const TextStyle(
                                color: Color(0xFF60707C),
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: statusBackground,
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          _statusLabel(record.attendanceStatus),
                          style: TextStyle(
                            color: statusColor,
                            fontWeight: FontWeight.w800,
                            fontSize: 12,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: _HistoryPill(
                          icon: Icons.calendar_today_outlined,
                          text: '${_tr('shift', 'វេណ')}: $shiftLabel',
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _HistoryPill(
                          icon: Icons.qr_code_scanner_outlined,
                          text:
                              '${_tr('scan', 'ស្កេន')}: ${_scanWindowText(record)}',
                        ),
                      ),
                    ],
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
                          label: _tr('in_time', 'ម៉ោងចូល'),
                          value: record.timeIn,
                        ),
                        _DetailRow(
                          label: _tr('out_time', 'ម៉ោងចេញ'),
                          value: record.timeOut,
                        ),
                        _DetailRow(
                          label: _tr('shift', 'វេណការងារ'),
                          value: shiftLabel,
                        ),
                        _DetailRow(
                          label: _tr('allowed_scan_window', 'ពេលអនុញ្ញាតស្កេន'),
                          value: _scanWindowText(record),
                        ),
                        _DetailRow(
                          label: _tr('late', 'ពេលយឺត'),
                          value: '${record.lateMinutes ?? 0} នាទី',
                          valueColor:
                              (record.lateMinutes ?? 0) > 0
                                  ? const Color(0xFFA85C00)
                                  : const Color(0xFF132D25),
                        ),
                        _DetailRow(
                          label: _tr('status', 'ស្ថានភាព'),
                          value: _statusLabel(record.attendanceStatus),
                          valueColor: statusColor,
                        ),
                      ],
                    ),
                  ),
                  if ((record.exceptionReason ?? '').trim().isNotEmpty ||
                      record.hasException == true) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF7F7),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: const Color(0xFFF2D6D6)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'កំណត់សម្គាល់',
                            style: TextStyle(
                              color: Color(0xFF8A3D3D),
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            (record.exceptionReason ?? '').trim().isEmpty
                                ? _tr('incomplete', 'មិនគ្រប់ទិន្នន័យ')
                                : record.exceptionReason!.trim(),
                            style: const TextStyle(
                              color: Color(0xFF6D4C4C),
                              height: 1.45,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 14),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: () => _requestAttendanceAdjustment(record),
                      icon: const Icon(Icons.edit_calendar_outlined),
                      label: Text(
                        _tr('attendance_adjustment', 'ស្នើកែវត្តមាន'),
                      ),
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
    final yearOptions = _yearOptions();
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
      _StatusFilterItem(code: 'leave', label: _tr('leave_type', 'សុំច្បាប់')),
      _StatusFilterItem(code: 'mission', label: _tr('mission', 'បេសកកម្ម')),
      _StatusFilterItem(code: 'absent', label: _tr('absent', 'អវត្តមាន')),
    ];

    return Scaffold(
      backgroundColor: const Color(0xFFF4F7FB),
      appBar: AppBar(
        elevation: 0,
        centerTitle: true,
        backgroundColor: const Color(0xFFF4F7FB),
        surfaceTintColor: Colors.transparent,
        flexibleSpace: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              colors: [Color(0xFFF7FAFF), Color(0xFFEFF5FF)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
          ),
        ),
        leading: Padding(
          padding: const EdgeInsets.only(left: 10),
          child: IconButton(
            onPressed: () => Navigator.of(context).maybePop(),
            style: IconButton.styleFrom(
              backgroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
            icon: const Icon(Icons.arrow_back_rounded),
          ),
        ),
        title: Text(
          _tr('attendance_history', 'ប្រវត្តិវត្តមាន'),
          style: const TextStyle(
            fontWeight: FontWeight.w900,
            color: Color(0xFF143545),
            letterSpacing: 0.2,
          ),
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: IconButton(
              onPressed: () {
                setState(() {
                  _showSearchField = !_showSearchField;
                  if (!_showSearchField) {
                    _dateSearchController.clear();
                  }
                });
              },
              style: IconButton.styleFrom(
                backgroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              icon: Icon(
                _showSearchField ? Icons.close_rounded : Icons.tune_rounded,
              ),
            ),
          ),
        ],
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(1),
          child: Divider(height: 1, thickness: 1, color: Color(0xFFE4ECF6)),
        ),
      ),
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
                    gradient: const LinearGradient(
                      colors: [Color(0xFFFFFFFF), Color(0xFFF8FBFF)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: const Color(0xFFE1EAF5)),
                    boxShadow: const [
                      BoxShadow(
                        color: Color(0x1014202B),
                        blurRadius: 18,
                        offset: Offset(0, 8),
                      ),
                    ],
                  ),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          Container(
                            width: 30,
                            height: 30,
                            decoration: BoxDecoration(
                              color: const Color(0xFFEAF1FF),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: const Icon(
                              Icons.calendar_month_rounded,
                              size: 18,
                              color: Color(0xFF355A8A),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            _tr('filter', 'តម្រង'),
                            style: const TextStyle(
                              color: Color(0xFF1F3953),
                              fontWeight: FontWeight.w900,
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Expanded(
                            child: _CompactDropdownField<DateTime>(
                              value: _selectedMonth,
                              items:
                                  monthOptions
                                      .map(
                                        (month) => DropdownMenuItem<DateTime>(
                                          value: month,
                                          child: Text(_monthLabel(month)),
                                        ),
                                      )
                                      .toList(),
                              onChanged: (value) {
                                if (value == null) {
                                  return;
                                }
                                setState(() {
                                  _selectedMonth = DateTime(
                                    _selectedMonth.year,
                                    value.month,
                                    1,
                                  );
                                  _recordsFuture = _loadRecords();
                                });
                              },
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: _CompactDropdownField<int>(
                              value: _selectedMonth.year,
                              items:
                                  yearOptions
                                      .map(
                                        (year) => DropdownMenuItem<int>(
                                          value: year,
                                          child: Text('$year'),
                                        ),
                                      )
                                      .toList(),
                              onChanged: (value) {
                                if (value == null) {
                                  return;
                                }
                                setState(() {
                                  _selectedMonth = DateTime(
                                    value,
                                    _selectedMonth.month,
                                    1,
                                  );
                                  _recordsFuture = _loadRecords();
                                });
                              },
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: _CompactDropdownField<String>(
                              value: _statusFilter,
                              items:
                                  filterItems
                                      .map(
                                        (item) => DropdownMenuItem<String>(
                                          value: item.code,
                                          child: Text(item.label),
                                        ),
                                      )
                                      .toList(),
                              onChanged: (value) {
                                if (value == null) {
                                  return;
                                }
                                setState(() {
                                  _statusFilter = value;
                                });
                              },
                            ),
                          ),
                          const SizedBox(width: 8),
                          _FilterSearchButton(
                            active: _showSearchField,
                            onTap: () {
                              setState(() {
                                _showSearchField = !_showSearchField;
                                if (!_showSearchField) {
                                  _dateSearchController.clear();
                                }
                              });
                            },
                          ),
                        ],
                      ),
                      if (_showSearchField) ...[
                        const SizedBox(height: 10),
                        TextField(
                          controller: _dateSearchController,
                          onChanged: (_) => setState(() {}),
                          decoration: InputDecoration(
                            hintText: _tr(
                              'search_by_date',
                              'ស្វែងរកតាមកាលបរិច្ឆេទ ឬថ្ងៃ',
                            ),
                            prefixIcon: const Icon(Icons.search_rounded),
                            suffixIcon:
                                _dateSearchController.text.trim().isEmpty
                                    ? null
                                    : IconButton(
                                      onPressed: () {
                                        _dateSearchController.clear();
                                        setState(() {});
                                      },
                                      icon: const Icon(Icons.close_rounded),
                                    ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _SummaryCard(
                        title: _tr('days_present', 'ថ្ងៃមកធ្វើការ'),
                        value: '${summary.totalDays}',
                        icon: Icons.calendar_month_outlined,
                        color: const Color(0xFF2E7D61),
                      ),
                      const SizedBox(width: 10),
                      _SummaryCard(
                        title: _tr('late', 'ថ្ងៃមកយឺត'),
                        value: '${summary.lateDays}',
                        icon: Icons.schedule_outlined,
                        color: const Color(0xFFD08A2E),
                      ),
                      const SizedBox(width: 10),
                      _SummaryCard(
                        title: _tr('leave_type', 'ថ្ងៃសុំច្បាប់'),
                        value: '${summary.leaveDays}',
                        icon: Icons.assignment_outlined,
                        color: const Color(0xFF9B7AD6),
                      ),
                      const SizedBox(width: 10),
                      _SummaryCard(
                        title: _tr('mission', 'ថ្ងៃបេសកកម្ម'),
                        value: '${summary.missionDays}',
                        icon: Icons.work_history_outlined,
                        color: const Color(0xFF5B79B7),
                      ),
                      const SizedBox(width: 10),
                      _SummaryCard(
                        title: _tr('absent', 'ថ្ងៃអវត្តមាន'),
                        value: '${summary.absentDays}',
                        icon: Icons.event_busy_outlined,
                        color: const Color(0xFFD36B61),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFF),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE1EAF5)),
                  ),
                  child: Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _LegendChip(
                        label: _tr('on_time', 'ទាន់ពេល'),
                        color: const Color(0xFF2E7D61),
                      ),
                      _LegendChip(
                        label: _tr('late', 'មកយឺត'),
                        color: const Color(0xFFD08A2E),
                      ),
                      _LegendChip(
                        label: _tr('leave_type', 'សុំច្បាប់'),
                        color: const Color(0xFF9B7AD6),
                      ),
                      _LegendChip(
                        label: _tr('mission', 'បេសកកម្ម'),
                        color: const Color(0xFF5B79B7),
                      ),
                      _LegendChip(
                        label: _tr('absent', 'អវត្តមាន'),
                        color: const Color(0xFFD36B61),
                      ),
                      _LegendChip(
                        label: _tr('incomplete', 'មិនគ្រប់ទិន្នន័យ'),
                        color: const Color(0xFF7A8087),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        _tr('attendance_list', 'បញ្ជីវត្តមាន'),
                        style: const TextStyle(
                          color: Color(0xFF123B32),
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    Text(
                      '${records.length} ${_tr('results', 'លទ្ធផល')}',
                      style: const TextStyle(
                        color: Color(0xFF6B7D88),
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                if (records.isEmpty)
                  _HistoryEmptyCard(
                    message: _tr(
                      'no_record_found',
                      'មិនទាន់មានប្រវត្តិវត្តមាន',
                    ),
                  )
                else
                  ...records.map((record) {
                    final statusColor = _statusColor(record.attendanceStatus);
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _AttendanceHistoryCard(
                        date: _formatDate(record.date),
                        weekday: _weekdayLabel(record.date),
                        timeIn: record.timeIn,
                        timeOut: record.timeOut,
                        shift: _resolvedShift(record),
                        statusLabel: _statusLabel(record.attendanceStatus),
                        statusColor: statusColor,
                        statusBackground: _statusBackground(
                          record.attendanceStatus,
                        ),
                        onTap: () => _showRecordDetails(record),
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
    required this.leaveDays,
    required this.missionDays,
    required this.absentDays,
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
  });

  final int totalDays;
  final int onTimeDays;
  final int lateDays;
  final int earlyLeaveDays;
  final int incompleteDays;
  final int leaveDays;
  final int missionDays;
  final int absentDays;
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
    return SizedBox(
      width: 100,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFFFFFFFF), Color(0xFFF8FBFF)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFE1EAF5)),
          boxShadow: const [
            BoxShadow(
              color: Color(0x0D14211D),
              blurRadius: 14,
              offset: Offset(0, 6),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: color.withAlpha(28),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: color, size: 18),
            ),
            const SizedBox(height: 8),
            Text(
              title,
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Color(0xFF516778),
                fontWeight: FontWeight.w700,
                fontSize: 11,
                height: 1.3,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              value,
              style: TextStyle(
                color: color,
                fontSize: 19,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CompactDropdownField<T> extends StatelessWidget {
  const _CompactDropdownField({
    required this.value,
    required this.items,
    required this.onChanged,
  });

  final T value;
  final List<DropdownMenuItem<T>> items;
  final ValueChanged<T?> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 46,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFF7FAFF),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFDCE6F3)),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<T>(
          value: value,
          isExpanded: true,
          icon: const Icon(Icons.keyboard_arrow_down_rounded),
          style: const TextStyle(
            color: Color(0xFF2A3B45),
            fontSize: 13,
            fontWeight: FontWeight.w700,
          ),
          items: items,
          onChanged: onChanged,
        ),
      ),
    );
  }
}

class _FilterSearchButton extends StatelessWidget {
  const _FilterSearchButton({required this.active, required this.onTap});

  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: active ? const Color(0xFFEAF1FF) : const Color(0xFFF7FAFF),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          width: 46,
          height: 46,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: active ? const Color(0xFFC8D8F5) : const Color(0xFFDCE6F3),
            ),
          ),
          child: Icon(
            Icons.search_rounded,
            color: active ? const Color(0xFF1D4F91) : const Color(0xFF5E7180),
          ),
        ),
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

class _AttendanceHistoryCard extends StatelessWidget {
  const _AttendanceHistoryCard({
    required this.date,
    required this.weekday,
    required this.timeIn,
    required this.timeOut,
    required this.shift,
    required this.statusLabel,
    required this.statusColor,
    required this.statusBackground,
    required this.onTap,
  });

  final String date;
  final String weekday;
  final String timeIn;
  final String timeOut;
  final String shift;
  final String statusLabel;
  final Color statusColor;
  final Color statusBackground;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Ink(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFFE5EBF0)),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0A14211D),
                blurRadius: 14,
                offset: Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '$date · $weekday',
                      style: const TextStyle(
                        color: Color(0xFF1E3441),
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 7,
                    ),
                    decoration: BoxDecoration(
                      color: statusBackground,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      statusLabel,
                      style: TextStyle(
                        color: statusColor,
                        fontWeight: FontWeight.w800,
                        fontSize: 12,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _HistoryInfoCell(label: 'ចូល', value: timeIn),
                  ),
                  Container(
                    width: 1,
                    height: 36,
                    color: const Color(0xFFEDF1F4),
                  ),
                  Expanded(
                    child: _HistoryInfoCell(label: 'ចេញ', value: timeOut),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                'វេណ: $shift',
                style: const TextStyle(
                  color: Color(0xFF70808C),
                  fontSize: 12.5,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _HistoryInfoCell extends StatelessWidget {
  const _HistoryInfoCell({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: Color(0xFF8A98A3),
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              color: Color(0xFF1E3441),
              fontSize: 16,
              fontWeight: FontWeight.w800,
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
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2EAE7)),
      ),
      child: Column(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: const Color(0xFFEFF6F3),
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(
              Icons.history_toggle_off_rounded,
              color: Color(0xFF0B6B58),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Color(0xFF3E554D),
              fontWeight: FontWeight.w700,
              fontSize: 14,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'សូមសាកល្បងប្តូរតម្រង ឬជ្រើសខែផ្សេង',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Color(0xFF72818C),
              height: 1.45,
              fontWeight: FontWeight.w600,
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
