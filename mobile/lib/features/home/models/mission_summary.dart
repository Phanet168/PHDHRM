class MissionSummary {
  MissionSummary({
    required this.id,
    required this.title,
    required this.destination,
    required this.startDate,
    required this.endDate,
    required this.status,
    required this.employeeCount,
  });

  final int id;
  final String title;
  final String destination;
  final String startDate;
  final String endDate;
  final String status;
  final int employeeCount;

  factory MissionSummary.fromMap(Map<String, dynamic> map) {
    final assignmentsRaw = map['assignments'];
    int computedEmployeeCount = 0;
    if (assignmentsRaw is List) {
      computedEmployeeCount = assignmentsRaw.length;
    }

    return MissionSummary(
      id: _toInt(map['id']) ?? 0,
      title: (map['title'] ?? '').toString().trim(),
      destination: (map['destination'] ?? '').toString().trim(),
      startDate: (map['start_date'] ?? '').toString().trim(),
      endDate: (map['end_date'] ?? '').toString().trim(),
      status: (map['status'] ?? 'pending').toString().trim(),
      employeeCount:
          _toInt(map['assignments_count']) ??
          _toInt(map['employee_count']) ??
          computedEmployeeCount,
    );
  }

  static int? _toInt(dynamic value) {
    if (value == null) {
      return null;
    }
    if (value is int) {
      return value;
    }
    if (value is num) {
      return value.toInt();
    }

    return int.tryParse(value.toString().trim());
  }
}
