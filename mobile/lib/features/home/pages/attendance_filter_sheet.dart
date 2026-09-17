import 'package:flutter/material.dart';

import '../../../core/theme/app_design_system.dart';
import 'home/attendance_status.dart';

/// The date range + status filter applied to an attendance list/calendar/
/// stats screen. An empty [statusCodes] means "all statuses".
class AttendanceFilterState {
  const AttendanceFilterState({
    required this.fromDate,
    required this.toDate,
    this.statusCodes = const <String>{},
  });

  factory AttendanceFilterState.forMonth(DateTime month) {
    final from = DateTime(month.year, month.month, 1);
    final to = DateTime(month.year, month.month + 1, 0);
    return AttendanceFilterState(fromDate: from, toDate: to);
  }

  final DateTime fromDate;
  final DateTime toDate;
  final Set<String> statusCodes;

  bool get isCustomRange {
    final wholeMonth = AttendanceFilterState.forMonth(fromDate);
    return fromDate != wholeMonth.fromDate || toDate != wholeMonth.toDate;
  }

  AttendanceFilterState copyWith({
    DateTime? fromDate,
    DateTime? toDate,
    Set<String>? statusCodes,
  }) {
    return AttendanceFilterState(
      fromDate: fromDate ?? this.fromDate,
      toDate: toDate ?? this.toDate,
      statusCodes: statusCodes ?? this.statusCodes,
    );
  }

  bool matchesStatus(String? code) {
    if (statusCodes.isEmpty) return true;
    return statusCodes.contains(attendanceStatusStyle(code).code);
  }
}

/// Opens the Figma "តម្រង" filter bottom sheet. Returns the applied filter,
/// or null if the user dismissed the sheet without applying.
Future<AttendanceFilterState?> showAttendanceFilterSheet(
  BuildContext context,
  AttendanceFilterState initial,
) {
  return showModalBottomSheet<AttendanceFilterState>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (context) => _AttendanceFilterSheet(initial: initial),
  );
}

class _AttendanceFilterSheet extends StatefulWidget {
  const _AttendanceFilterSheet({required this.initial});

  final AttendanceFilterState initial;

  @override
  State<_AttendanceFilterSheet> createState() => _AttendanceFilterSheetState();
}

class _AttendanceFilterSheetState extends State<_AttendanceFilterSheet> {
  late DateTime _fromDate;
  late DateTime _toDate;
  late Set<String> _statusCodes;

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
    _fromDate = widget.initial.fromDate;
    _toDate = widget.initial.toDate;
    _statusCodes = Set<String>.from(widget.initial.statusCodes);
  }

  String _formatShort(DateTime d) =>
      '${d.day.toString().padLeft(2, '0')} ${_monthNames[d.month - 1]}';

  Future<void> _pickDateRange() async {
    final now = DateTime.now();
    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(now.year - 3),
      lastDate: DateTime(now.year + 1),
      initialDateRange: DateTimeRange(start: _fromDate, end: _toDate),
    );
    if (picked != null) {
      setState(() {
        _fromDate = DateTime(
          picked.start.year,
          picked.start.month,
          picked.start.day,
        );
        _toDate = DateTime(picked.end.year, picked.end.month, picked.end.day);
      });
    }
  }

  void _toggleStatus(String code) {
    setState(() {
      if (_statusCodes.contains(code)) {
        _statusCodes.remove(code);
      } else {
        _statusCodes.add(code);
      }
    });
  }

  void _clear() {
    setState(() {
      final wholeMonth = AttendanceFilterState.forMonth(DateTime.now());
      _fromDate = wholeMonth.fromDate;
      _toDate = wholeMonth.toDate;
      _statusCodes = <String>{};
    });
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).padding.bottom;

    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: Container(
        padding: EdgeInsets.fromLTRB(20, 10, 20, bottomInset + 20),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 44,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFE2E8E6),
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'តម្រង',
              style: TextStyle(
                fontSize: 19,
                fontWeight: FontWeight.bold,
                color: Color(0xFF17201E),
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'កាលបរិច្ឆេទ',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Color(0xFF17201E),
              ),
            ),
            const SizedBox(height: 6),
            InkWell(
              onTap: _pickDateRange,
              borderRadius: BorderRadius.circular(12),
              child: Container(
                height: 50,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: BoxDecoration(
                  border: Border.all(color: const Color(0xFFE2E8E6)),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${_formatShort(_fromDate)} – ${_formatShort(_toDate)} ${_toDate.year}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF66736F),
                        ),
                      ),
                    ),
                    const Icon(
                      Icons.calendar_today_outlined,
                      size: 16,
                      color: Color(0xFF66736F),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'ស្ថានភាព',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Color(0xFF17201E),
              ),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final style in attendanceStatusPalette)
                  GestureDetector(
                    onTap: () => _toggleStatus(style.code),
                    child: AttendanceStatusPill(
                      style: style,
                      selected: _statusCodes.contains(style.code),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 20),
            GestureDetector(
              onTap: () {
                Navigator.of(context).pop(
                  AttendanceFilterState(
                    fromDate: _fromDate,
                    toDate: _toDate,
                    statusCodes: _statusCodes,
                  ),
                );
              },
              child: Container(
                width: double.infinity,
                height: 50,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppDesignSystem.primary,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Text(
                  'អនុវត្ត',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 10),
            Center(
              child: GestureDetector(
                onTap: _clear,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 8,
                  ),
                  child: const Text(
                    'សម្អាត',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: AppDesignSystem.primary,
                    ),
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
