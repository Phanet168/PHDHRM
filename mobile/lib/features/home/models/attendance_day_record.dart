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
    this.hasException,
    this.exceptionReason,
  });

  final String date;
  final String totalHours;
  final String timeIn;
  final String timeOut;
  final int punchCount;
  final String? attendanceStatus;
  final int? lateMinutes;
  final int? earlyLeaveMinutes;
  final bool? hasException;
  final String? exceptionReason;
}
