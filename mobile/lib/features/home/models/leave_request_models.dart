int _toInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) {
    final text = value.trim();
    if (text.isEmpty) return 0;
    final asInt = int.tryParse(text);
    if (asInt != null) return asInt;
    final asDouble = double.tryParse(text);
    if (asDouble != null) return asDouble.toInt();
  }
  return 0;
}

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
      id: _toInt(map['id']),
      name: (map['leave_type'] ?? '').toString(),
      nameKm: (map['leave_type_km'] ?? '').toString(),
      days: _toInt(map['leave_days']),
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
      leaveTypeId: _toInt(map['leave_type_id']),
      leaveType: (map['leave_type'] ?? '').toString(),
      leaveTypeKm: (map['leave_type_km'] ?? '').toString(),
      entitlement: _toInt(map['entitlement']),
      used: _toInt(map['used']),
      remaining: _toInt(map['remaining']),
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
      totalRemaining: _toInt(map['total_remaining']),
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
    this.attachmentUrl,
    this.employeeName,
    this.employeeNo,
  });

  final int id;
  final String leaveType;
  final String leaveTypeKm;
  final String startDate;
  final String endDate;
  final int requestedDays;
  final String reason;
  final String status;
  final String? attachmentUrl;
  final String? employeeName;
  final String? employeeNo;

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
    final employee = map['employee'];
    String? employeeName;
    String? employeeNo;
    if (employee is Map<String, dynamic>) {
      employeeName = (employee['full_name'] ?? '').toString();
      employeeNo = (employee['employee_no'] ?? '').toString();
    } else if (employee is Map) {
      employeeName = (employee['full_name'] ?? '').toString();
      employeeNo = (employee['employee_no'] ?? '').toString();
    }

    return LeaveRequestItem(
      id: _toInt(map['id']),
      leaveType: (map['leave_type'] ?? '').toString(),
      leaveTypeKm: (map['leave_type_km'] ?? '').toString(),
      startDate: (map['start_date'] ?? '').toString(),
      endDate: (map['end_date'] ?? '').toString(),
      requestedDays: _toInt(map['requested_days']),
      reason: (map['reason'] ?? '').toString(),
      status: (map['status'] ?? '').toString(),
      attachmentUrl: (map['attachment_url'] as String?)?.trim(),
      employeeName:
          employeeName?.trim().isEmpty == true ? null : employeeName?.trim(),
      employeeNo:
          employeeNo?.trim().isEmpty == true ? null : employeeNo?.trim(),
    );
  }
}
