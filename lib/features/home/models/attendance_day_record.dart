class AttendanceDayRecord {
  AttendanceDayRecord({
    required this.date,
    required this.totalHours,
    required this.timeIn,
    required this.timeOut,
    required this.punchCount,
  });

  final String date;
  final String totalHours;
  final String timeIn;
  final String timeOut;
  final int punchCount;
}
