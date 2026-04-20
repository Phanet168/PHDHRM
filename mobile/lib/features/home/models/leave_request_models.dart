class LeaveTypeOption {
  const LeaveTypeOption({
    required this.id,
    required this.name,
    required this.nameKm,
    required this.days,
  });

  final int id;
  final String name;
  final String nameKm;
  final int days;

  String displayName(Map<String, String> language) {
    if (nameKm.trim().isNotEmpty) {
      return nameKm;
    }
    if (name.trim().isNotEmpty) {
      return name;
    }
    return language['leave_type'] ?? 'Leave type';
  }

  factory LeaveTypeOption.fromMap(Map<String, dynamic> map) {
    return LeaveTypeOption(
      id: (map['id'] as num?)?.toInt() ?? 0,
      name: (map['leave_type'] ?? '').toString(),
      nameKm: (map['leave_type_km'] ?? '').toString(),
      days: (map['leave_days'] as num?)?.toInt() ?? 0,
    );
  }
}

class LeaveBalanceItem {
  const LeaveBalanceItem({
    required this.leaveTypeId,
    required this.leaveType,
    required this.leaveTypeKm,
    required this.entitlement,
    required this.used,
    required this.remaining,
  });

  final int leaveTypeId;
  final String leaveType;
  final String leaveTypeKm;
  final int entitlement;
  final int used;
  final int remaining;

  String displayName(Map<String, String> language) {
    if (leaveTypeKm.trim().isNotEmpty) {
      return leaveTypeKm;
    }
    if (leaveType.trim().isNotEmpty) {
      return leaveType;
    }
    return language['leave_type'] ?? 'Leave type';
  }

  factory LeaveBalanceItem.fromMap(Map<String, dynamic> map) {
    return LeaveBalanceItem(
      leaveTypeId: (map['leave_type_id'] as num?)?.toInt() ?? 0,
      leaveType: (map['leave_type'] ?? '').toString(),
      leaveTypeKm: (map['leave_type_km'] ?? '').toString(),
      entitlement: (map['entitlement'] as num?)?.toInt() ?? 0,
      used: (map['used'] as num?)?.toInt() ?? 0,
      remaining: (map['remaining'] as num?)?.toInt() ?? 0,
    );
  }
}

class LeaveSummary {
  const LeaveSummary({required this.totalRemaining, required this.types});

  final int totalRemaining;
  final List<LeaveBalanceItem> types;

  factory LeaveSummary.fromMap(Map<String, dynamic> map) {
    final rawTypes = map['types'];
    final rows = <LeaveBalanceItem>[];

    if (rawTypes is List) {
      for (final row in rawTypes) {
        if (row is Map<String, dynamic>) {
          rows.add(LeaveBalanceItem.fromMap(row));
        } else if (row is Map) {
          rows.add(
            LeaveBalanceItem.fromMap(
              row.map((key, value) => MapEntry(key.toString(), value)),
            ),
          );
        }
      }
    }

    return LeaveSummary(
      totalRemaining: (map['total_remaining'] as num?)?.toInt() ?? 0,
      types: rows,
    );
  }
}

class LeaveRequestItem {
  const LeaveRequestItem({
    required this.id,
    required this.leaveType,
    required this.leaveTypeKm,
    required this.startDate,
    required this.endDate,
    required this.requestedDays,
    required this.reason,
    required this.status,
  });

  final int id;
  final String leaveType;
  final String leaveTypeKm;
  final String startDate;
  final String endDate;
  final int requestedDays;
  final String reason;
  final String status;

  bool get canCancel {
    final normalized = status.trim().toLowerCase();
    return normalized == 'pending' || normalized == 'draft';
  }

  String displayStatus(Map<String, String> language) {
    switch (status.trim().toLowerCase()) {
      case 'approved':
        return language['approved'] ?? 'Approved';
      case 'rejected':
        return language['rejected'] ?? 'Rejected';
      case 'cancelled':
        return language['cancelled'] ?? 'Cancelled';
      default:
        return language['pending'] ?? 'Pending';
    }
  }

  factory LeaveRequestItem.fromMap(Map<String, dynamic> map) {
    return LeaveRequestItem(
      id: (map['id'] as num?)?.toInt() ?? 0,
      leaveType: (map['leave_type'] ?? '').toString(),
      leaveTypeKm: (map['leave_type_km'] ?? '').toString(),
      startDate: (map['start_date'] ?? '').toString(),
      endDate: (map['end_date'] ?? '').toString(),
      requestedDays: (map['requested_days'] as num?)?.toInt() ?? 0,
      reason: (map['reason'] ?? '').toString(),
      status: (map['status'] ?? '').toString(),
    );
  }
}
