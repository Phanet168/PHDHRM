import 'package:flutter/material.dart';

/// One status category's display style (Figma "ស្ថានភាព" pill): background
/// tint, text/accent color, and label. Shared by the History, Calendar,
/// Statistics, Filter and Daily Detail attendance screens so every screen
/// reads the same status the same way.
class AttendanceStatusStyle {
  const AttendanceStatusStyle({
    required this.code,
    required this.label,
    required this.shortLabel,
    required this.bg,
    required this.fg,
  });

  /// Canonical status bucket this style represents (on_time, late, ...).
  final String code;
  final String label;
  final String shortLabel;
  final Color bg;
  final Color fg;
}

const _onTime = AttendanceStatusStyle(
  code: 'on_time',
  label: 'ទាន់ពេល',
  shortLabel: 'ទាន់ពេល',
  bg: Color(0xFFE8F5EF),
  fg: Color(0xFF23845B),
);
const _late = AttendanceStatusStyle(
  code: 'late',
  label: 'យឺត',
  shortLabel: 'យឺត',
  bg: Color(0xFFFFF5D9),
  fg: Color(0xFFD99A00),
);
const _incomplete = AttendanceStatusStyle(
  code: 'incomplete',
  label: 'វត្តមានមិនពេញ',
  shortLabel: 'មិនពេញ',
  bg: Color(0xFFEEF1F0),
  fg: Color(0xFF7B8582),
);
const _absent = AttendanceStatusStyle(
  code: 'absent',
  label: 'អវត្តមាន',
  shortLabel: 'អវត្តមាន',
  bg: Color(0xFFFDECEC),
  fg: Color(0xFFC94444),
);
const _leave = AttendanceStatusStyle(
  code: 'leave',
  label: 'ច្បាប់',
  shortLabel: 'ច្បាប់',
  bg: Color(0xFFFFF1DE),
  fg: Color(0xFFE6943B),
);
const _mission = AttendanceStatusStyle(
  code: 'mission',
  label: 'បេសកកម្ម',
  shortLabel: 'បេសកកម្ម',
  bg: Color(0xFFF0EBFA),
  fg: Color(0xFF7656B5),
);
const _dayOff = AttendanceStatusStyle(
  code: 'day_off',
  label: 'ថ្ងៃឈប់សម្រាក',
  shortLabel: 'ថ្ងៃឈប់',
  bg: Color(0xFFEEF1F0),
  fg: Color(0xFF7B8582),
);

/// The 6 filterable/legend-worthy statuses, in the order the Figma legend
/// and filter chips show them.
const List<AttendanceStatusStyle> attendanceStatusPalette = [
  _onTime,
  _late,
  _absent,
  _leave,
  _mission,
  _incomplete,
];

String _normalize(String? code) => code?.trim().toLowerCase() ?? '';

/// Resolves a raw backend status code (and its many aliases) to one of the
/// canonical [attendanceStatusPalette] styles.
AttendanceStatusStyle attendanceStatusStyle(String? code) {
  final normalized = _normalize(code);
  switch (normalized) {
    case 'on_time':
    case 'present':
    case 'p':
      return _onTime;
    case 'late':
    case 'early_leave':
    case 'late_and_early_leave':
      return _late;
    case 'incomplete':
    case 'partial':
    case 'unpaired_punch':
    case 'unpaired':
      return _incomplete;
    case 'absent':
    case 'a':
      return _absent;
    case 'leave':
    case 'lv':
      return _leave;
    case 'mission':
    case 'm':
      return _mission;
    case 'holiday':
    case 'h':
    case 'day_off':
    case 'd':
      return _dayOff;
    default:
      return _incomplete;
  }
}

const _sessionLabels = <String, String>{
  'morning': 'ព្រឹក',
  'afternoon': 'ល្ងាច',
  'duty': 'វេនយាម',
  'work': 'វេនធ្វើការ',
};

/// Khmer label for an attendance session's backend `name` (morning/
/// afternoon/duty/work) — shared by the Daily Detail sessions panel and the
/// Dashboard's work-status card so both read the same session the same way.
String attendanceSessionLabel(String name) => _sessionLabels[name] ?? name;

/// A rounded status pill matching the Figma "ស្ថានភាព" component.
class AttendanceStatusPill extends StatelessWidget {
  const AttendanceStatusPill({
    super.key,
    required this.style,
    this.short = false,
    this.selected = true,
  });

  final AttendanceStatusStyle style;
  final bool short;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: selected ? style.bg : Colors.white,
        borderRadius: BorderRadius.circular(999),
        border: selected ? null : Border.all(color: const Color(0xFFE2E8E6)),
      ),
      child: Text(
        short ? style.shortLabel : style.label,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.bold,
          color: selected ? style.fg : const Color(0xFF66736F),
        ),
      ),
    );
  }
}
