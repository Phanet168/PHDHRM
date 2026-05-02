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

double _toDouble(dynamic value) {
  if (value is double) return value;
  if (value is int) return value.toDouble();
  if (value is num) return value.toDouble();
  if (value is String) {
    final text = value.trim();
    if (text.isEmpty) return 0;
    return double.tryParse(text) ?? 0;
  }
  return 0;
}

bool _toBool(dynamic value) {
  if (value is bool) return value;
  if (value is num) return value != 0;
  if (value is String) {
    final normalized = value.trim().toLowerCase();
    return normalized == '1' ||
        normalized == 'true' ||
        normalized == 'yes' ||
        normalized == 'on';
  }
  return false;
}

class LeaveTypeOption {
  const LeaveTypeOption({
    required this.id,
    required this.name,
    required this.nameKm,
    required this.days,
    required this.scope,
    required this.maxPerRequest,
    required this.requiresAttachment,
    required this.isPaid,
  });

  final int id;
  final String name;
  final String nameKm;
  final int days;
  final String scope;
  final double maxPerRequest;
  final bool requiresAttachment;
  final bool isPaid;

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
    final requiresAttachment =
        _toBool(map['requires_attachment']) ||
        _toBool(map['requires_medical_certificate']);

    return LeaveTypeOption(
      id: _toInt(map['id']),
      name: (map['leave_type'] ?? '').toString(),
      nameKm: (map['leave_type_km'] ?? '').toString(),
      days: _toInt(map['entitlement_value']) > 0
          ? _toInt(map['entitlement_value'])
          : _toInt(map['leave_days']),
      scope: (map['entitlement_scope'] ?? map['scope'] ?? 'per_year').toString(),
      maxPerRequest: _toDouble(map['max_per_request']),
      requiresAttachment: requiresAttachment,
      isPaid: _toBool(map['is_paid']),
    );
  }
}

class HandoverEmployeeOption {
  const HandoverEmployeeOption({
    required this.id,
    required this.employeeNo,
    required this.fullName,
    required this.fullNameLatin,
  });

  final int id;
  final String employeeNo;
  final String fullName;
  final String fullNameLatin;

  String displayLabel() {
    final name = fullName.trim().isNotEmpty ? fullName.trim() : fullNameLatin.trim();
    if (employeeNo.trim().isEmpty) {
      return name;
    }
    return '$employeeNo - $name';
  }

  String get searchText {
    return [
      employeeNo.trim(),
      fullName.trim(),
      fullNameLatin.trim(),
    ].where((value) => value.isNotEmpty).join(' ').toLowerCase();
  }

  factory HandoverEmployeeOption.fromMap(Map<String, dynamic> map) {
    return HandoverEmployeeOption(
      id: _toInt(map['id']),
      employeeNo: (map['employee_no'] ?? '').toString(),
      fullName: (map['full_name'] ?? '').toString(),
      fullNameLatin: (map['full_name_latin'] ?? '').toString(),
    );
  }
}

class LeaveBalanceItem {
  const LeaveBalanceItem({
    required this.leaveTypeId,
    required this.leaveType,
    required this.leaveTypeKm,
    required this.scope,
    required this.entitlement,
    required this.used,
    required this.pending,
    required this.remaining,
    required this.maxPerRequest,
  });

  final int leaveTypeId;
  final String leaveType;
  final String leaveTypeKm;
  final String scope;
  final int entitlement;
  final int used;
  final int pending;
  final int remaining;
  final int maxPerRequest;

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
      scope: (map['scope'] ?? 'per_year').toString(),
      entitlement: _toInt(map['entitlement']),
      used: _toInt(map['used']),
      pending: _toInt(map['pending']),
      remaining: _toInt(map['remaining']),
      maxPerRequest: _toInt(map['max_per_request']),
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
    required this.uuid,
    required this.leaveTypeId,
    required this.leaveType,
    required this.leaveTypeKm,
    required this.handoverEmployeeId,
    required this.handoverEmployeeName,
    required this.startDate,
    required this.endDate,
    required this.requestedDays,
    required this.approvedDays,
    required this.reason,
    required this.status,
    required this.workflowStatus,
    this.workflowCurrentStepOrder,
    this.workflowCurrentStepName,
    this.workflowCurrentActorName,
    this.workflowSourcePolicyModuleKey,
    this.workflowSourcePolicyName,
    this.workflowSteps = const <LeaveWorkflowStepItem>[],
    this.attachmentUrl,
    this.employeeName,
    this.employeeNo,
    this.employeeUserId,
    this.submittedAt,
    this.updatedAt,
  });

  final int id;
  final String uuid;
  final int leaveTypeId;
  final String leaveType;
  final String leaveTypeKm;
  final int handoverEmployeeId;
  final String handoverEmployeeName;
  final String startDate;
  final String endDate;
  final int requestedDays;
  final int approvedDays;
  final String reason;
  final String status;
  final String workflowStatus;
  final int? workflowCurrentStepOrder;
  final String? workflowCurrentStepName;
  final String? workflowCurrentActorName;
  final String? workflowSourcePolicyModuleKey;
  final String? workflowSourcePolicyName;
  final List<LeaveWorkflowStepItem> workflowSteps;
  final String? attachmentUrl;
  final String? employeeName;
  final String? employeeNo;
  final int? employeeUserId;
  final String? submittedAt;
  final String? updatedAt;

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
    int? employeeUserId;
    if (employee is Map<String, dynamic>) {
      employeeName = (employee['full_name'] ?? '').toString();
      employeeNo = (employee['employee_no'] ?? '').toString();
      employeeUserId = _toInt(employee['user_id']);
    } else if (employee is Map) {
      employeeName = (employee['full_name'] ?? '').toString();
      employeeNo = (employee['employee_no'] ?? '').toString();
      employeeUserId = _toInt(employee['user_id']);
    }

    final workflowSteps = <LeaveWorkflowStepItem>[];
    final rawWorkflowSteps = map['workflow_steps'];
    if (rawWorkflowSteps is List) {
      for (final row in rawWorkflowSteps) {
        if (row is Map<String, dynamic>) {
          workflowSteps.add(LeaveWorkflowStepItem.fromMap(row));
        } else if (row is Map) {
          workflowSteps.add(
            LeaveWorkflowStepItem.fromMap(
              row.map((key, value) => MapEntry(key.toString(), value)),
            ),
          );
        }
      }
    }

    return LeaveRequestItem(
      id: _toInt(map['id']),
      uuid: (map['uuid'] ?? '').toString(),
      leaveTypeId: _toInt(map['leave_type_id']),
      leaveType: (map['leave_type'] ?? '').toString(),
      leaveTypeKm: (map['leave_type_km'] ?? '').toString(),
      handoverEmployeeId: _toInt(map['handover_employee_id']),
      handoverEmployeeName: (map['handover_employee_name'] ?? '').toString(),
      startDate: (map['start_date'] ?? '').toString(),
      endDate: (map['end_date'] ?? '').toString(),
      requestedDays: _toInt(map['requested_days']),
      approvedDays: _toInt(map['approved_days']),
      reason: (map['reason'] ?? '').toString(),
      status: (map['status'] ?? '').toString(),
      workflowStatus: (map['workflow_status'] ?? '').toString(),
      workflowCurrentStepOrder: map['workflow_current_step_order'] == null
          ? null
          : _toInt(map['workflow_current_step_order']),
      workflowCurrentStepName:
          (map['workflow_current_step_name'] as String?)?.trim(),
      workflowCurrentActorName:
          (map['workflow_current_actor_name'] as String?)?.trim(),
      workflowSourcePolicyModuleKey:
          (map['workflow_source_policy_module_key'] as String?)?.trim(),
      workflowSourcePolicyName:
          (map['workflow_source_policy_name'] as String?)?.trim(),
      workflowSteps: workflowSteps,
      attachmentUrl: (map['attachment_url'] as String?)?.trim(),
      employeeName:
          employeeName?.trim().isEmpty == true ? null : employeeName?.trim(),
      employeeNo:
          employeeNo?.trim().isEmpty == true ? null : employeeNo?.trim(),
      employeeUserId: employeeUserId == null || employeeUserId <= 0
          ? null
          : employeeUserId,
      submittedAt: (map['submitted_at'] as String?)?.trim(),
      updatedAt: (map['updated_at'] as String?)?.trim(),
    );
  }
}

class LeaveWorkflowStepItem {
  const LeaveWorkflowStepItem({
    required this.stepOrder,
    required this.stepName,
    required this.actionType,
    required this.actorName,
    required this.isFinalApproval,
    required this.isCurrent,
  });

  final int stepOrder;
  final String stepName;
  final String actionType;
  final String actorName;
  final bool isFinalApproval;
  final bool isCurrent;

  factory LeaveWorkflowStepItem.fromMap(Map<String, dynamic> map) {
    return LeaveWorkflowStepItem(
      stepOrder: _toInt(map['step_order']),
      stepName: (map['step_name'] ?? '').toString(),
      actionType: (map['action_type'] ?? '').toString(),
      actorName: (map['actor_name'] ?? '').toString(),
      isFinalApproval: _toBool(map['is_final_approval']),
      isCurrent: _toBool(map['is_current']),
    );
  }
}
