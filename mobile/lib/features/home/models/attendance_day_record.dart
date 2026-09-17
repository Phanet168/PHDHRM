class AttendanceDayRecord {
  AttendanceDayRecord({
    required this.date,
    required this.totalHours,
    required this.timeIn,
    required this.timeOut,
    required this.punchCount,
    this.attendanceStatus,
    this.lateMinutes,
    this.earlyLeaveMinutes,
    this.overtimeMinutes = 0,
    this.hasException,
    this.exceptionReason,
    this.adjustmentNote,
    this.earlyArrivalMinutes = 0,
    this.isProvisional = false,
    this.unitName,
    this.shiftName,
    this.isDuty = false,
    this.sessions = const <AttendanceSessionRecord>[],
    this.holidayName,
  });

  final String date;
  final String totalHours;
  final String timeIn;
  final String timeOut;
  final int punchCount;
  final String? attendanceStatus;
  final int? lateMinutes;
  final int? earlyLeaveMinutes;
  final int overtimeMinutes;
  final bool? hasException;
  final String? exceptionReason;

  /// The public holiday's name when this day falls on one — null on every
  /// other day, including plain weekly days off.
  final String? holidayName;

  /// HR's stated reason for an approved punch-time correction on this day —
  /// an audit note, not something the employee can edit.
  final String? adjustmentNote;
  final int earlyArrivalMinutes;
  final bool isProvisional;
  final String? unitName;
  final String? shiftName;
  final bool isDuty;
  final List<AttendanceSessionRecord> sessions;

  factory AttendanceDayRecord.fromApi(Map<String, dynamic> json) {
    final minutes = _integer(json['worked_minutes']);
    final unit = json['unit'];
    final shift = json['shift'];
    return AttendanceDayRecord(
      date: json['date'].toString(),
      // Zero is authoritative: never reconstruct work time from first/last punch.
      totalHours:
          '${minutes ~/ 60}:${(minutes % 60).toString().padLeft(2, '0')}:00',
      timeIn: _clock(json['in_time']),
      timeOut: _clock(json['out_time']),
      punchCount: _integer(json['punch_count']),
      attendanceStatus: json['attendance_status']?.toString(),
      lateMinutes: _integer(json['late_minutes']),
      earlyLeaveMinutes: _integer(json['early_leave_minutes']),
      overtimeMinutes: _integer(json['overtime_minutes']),
      earlyArrivalMinutes: _integer(json['early_arrival_minutes']),
      hasException: json['has_exception'] == true,
      exceptionReason: json['exception_reason']?.toString(),
      adjustmentNote: json['adjustment_note']?.toString(),
      isProvisional: json['is_provisional'] == true,
      unitName: unit is Map ? unit['name']?.toString() : null,
      shiftName: shift is Map ? shift['name']?.toString() : null,
      isDuty: shift is Map && shift['is_duty'] == true,
      sessions: (json['sessions'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(AttendanceSessionRecord.fromApi)
          .toList(growable: false),
      holidayName:
          (json['holiday_name'] as String?)?.trim().isNotEmpty == true
              ? (json['holiday_name'] as String).trim()
              : null,
    );
  }
}

class AttendanceSessionRecord {
  const AttendanceSessionRecord({
    required this.name,
    required this.scheduledIn,
    required this.scheduledOut,
    required this.timeIn,
    required this.timeOut,
    required this.complete,
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
    required this.earlyArrivalMinutes,
  });
  final String name;
  final String scheduledIn;
  final String scheduledOut;
  final String timeIn;
  final String timeOut;
  final bool complete;
  final int lateMinutes;
  final int earlyLeaveMinutes;
  final int earlyArrivalMinutes;

  factory AttendanceSessionRecord.fromApi(Map<String, dynamic> json) =>
      AttendanceSessionRecord(
        name: json['name']?.toString() ?? 'work',
        scheduledIn: _clock(json['scheduled_in']),
        scheduledOut: _clock(json['scheduled_out']),
        timeIn: _clock(json['in_time']),
        timeOut: _clock(json['out_time']),
        complete: json['complete'] == true,
        lateMinutes: _integer(json['late_minutes']),
        earlyLeaveMinutes: _integer(json['early_leave_minutes']),
        earlyArrivalMinutes: _integer(json['early_arrival_minutes']),
      );
}

int _integer(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

String _clock(dynamic value) {
  final text = value?.toString().trim() ?? '';
  if (text.isEmpty) return '-';
  // Server local timestamps use the timezone supplied in API metadata.
  return text.length >= 19 ? text.substring(11, 19) : text;
}
