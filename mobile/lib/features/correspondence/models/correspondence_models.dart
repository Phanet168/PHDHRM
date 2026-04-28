// Correspondence (លិខិតរដ្ឋបាល) data models.

import 'dart:convert';

class CorrespondenceLetter {
  CorrespondenceLetter({
    required this.id,
    required this.letterType,
    required this.subject,
    required this.status,
    required this.currentStep,
    this.letterNo,
    this.registryNo,
    this.priority,
    this.fromOrg,
    this.toOrg,
    this.letterDate,
    this.receivedDate,
    this.sentDate,
    this.dueDate,
    this.attachments,
    this.currentHandlerUserId,
    this.currentHandlerName,
    this.originDepartmentId,
    this.originDepartmentName,
    this.assignedDepartmentId,
    this.assignedDepartmentName,
    this.parentLetterId,
    this.createdByUserId,
    this.createdByName,
    this.createdAt,
    this.decision,
    this.distributionsCount,
    this.completedCount,
    this.actions,
    this.distributions,
  });

  final int id;
  final String letterType; // 'incoming' | 'outgoing'
  final String subject;
  final String status; // 'pending', 'in_progress', 'completed', 'archived'
  final String currentStep; // workflow step key
  final String? letterNo;
  final String? registryNo;
  final String? priority; // 'normal', 'urgent', 'confidential'
  final String? fromOrg;
  final String? toOrg;
  final DateTime? letterDate;
  final DateTime? receivedDate;
  final DateTime? sentDate;
  final DateTime? dueDate;
  final List<String>? attachments;
  final int? currentHandlerUserId;
  final String? currentHandlerName;
  final int? originDepartmentId;
  final String? originDepartmentName;
  final int? assignedDepartmentId;
  final String? assignedDepartmentName;
  final int? parentLetterId;
  final int? createdByUserId;
  final String? createdByName;
  final DateTime? createdAt;
  final String? decision; // 'approved', 'rejected' (for incoming)
  final int? distributionsCount;
  final int? completedCount;
  final List<CorrespondenceLetterAction>? actions;
  final List<CorrespondenceLetterDistribution>? distributions;

  bool get isIncoming => letterType == 'incoming';
  bool get isOutgoing => letterType == 'outgoing';
  bool get isPending => status == 'pending';
  bool get isInProgress => status == 'in_progress';
  bool get isCompleted => status == 'completed';
  bool get isArchived => status == 'archived';
  bool get isUrgent => priority == 'urgent';

  String getLocalizedType(Map<String, String> language) {
    return isIncoming
        ? (language['incoming_letter'] ?? 'លិខិតចូល')
        : (language['outgoing_letter'] ?? 'លិខិតចេញ');
  }

  String getLocalizedStatus(Map<String, String> language) {
    switch (status) {
      case 'pending':
        return language['pending'] ?? 'ស្ថិតក្នុងរង្វង់ចាប់ផ្តើម';
      case 'in_progress':
        return language['in_progress'] ?? 'កំពុងដំណើរការ';
      case 'completed':
        return language['completed'] ?? 'បានបញ្ចប់';
      case 'archived':
        return language['archived'] ?? 'បានរក្សាទុក';
      default:
        return status;
    }
  }

  factory CorrespondenceLetter.fromJson(Map<String, dynamic> json) {
    return CorrespondenceLetter(
      id: (json['id'] as num?)?.toInt() ?? 0,
      letterType: (json['letter_type'] ?? 'incoming').toString(),
      subject: (json['subject'] ?? '').toString(),
      status: (json['status'] ?? 'pending').toString(),
      currentStep: (json['current_step'] ?? '').toString(),
      letterNo: (json['letter_no'] as String?)?.trim(),
      registryNo: (json['registry_no'] as String?)?.trim(),
      priority: (json['priority'] as String?)?.trim(),
      fromOrg: (json['from_org'] as String?)?.trim(),
      toOrg: (json['to_org'] as String?)?.trim(),
      letterDate: _parseDateTime(json['letter_date']),
      receivedDate: _parseDateTime(json['received_date']),
      sentDate: _parseDateTime(json['sent_date']),
      dueDate: _parseDateTime(json['due_date']),
      attachments: _parseAttachments(json['attachment_path']),
      currentHandlerUserId: (json['current_handler_user_id'] as num?)?.toInt(),
      currentHandlerName: (json['current_handler_name'] as String?)?.trim(),
      originDepartmentId: (json['origin_department_id'] as num?)?.toInt(),
      originDepartmentName: (json['origin_department_name'] as String?)?.trim(),
      assignedDepartmentId: (json['assigned_department_id'] as num?)?.toInt(),
      assignedDepartmentName:
          (json['assigned_department_name'] as String?)?.trim(),
      parentLetterId: (json['parent_letter_id'] as num?)?.toInt(),
      createdByUserId: (json['created_by_user_id'] as num?)?.toInt(),
      createdByName: (json['created_by_name'] as String?)?.trim(),
      createdAt: _parseDateTime(json['created_at']),
      decision: (json['decision'] as String?)?.trim(),
      distributionsCount: (json['distributions_count'] as num?)?.toInt(),
      completedCount: (json['completed_count'] as num?)?.toInt(),
      actions:
          (json['actions'] as List<dynamic>?)
              ?.whereType<Map<String, dynamic>>()
              .map(CorrespondenceLetterAction.fromJson)
              .toList(),
      distributions:
          (json['distributions'] as List<dynamic>?)
              ?.whereType<Map<String, dynamic>>()
              .map(CorrespondenceLetterDistribution.fromJson)
              .toList(),
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'letter_type': letterType,
    'subject': subject,
    'status': status,
    'current_step': currentStep,
    'letter_no': letterNo,
    'registry_no': registryNo,
    'priority': priority,
    'from_org': fromOrg,
    'to_org': toOrg,
    'letter_date': letterDate?.toIso8601String(),
    'received_date': receivedDate?.toIso8601String(),
    'sent_date': sentDate?.toIso8601String(),
    'due_date': dueDate?.toIso8601String(),
    'current_handler_user_id': currentHandlerUserId,
    'origin_department_id': originDepartmentId,
    'assigned_department_id': assignedDepartmentId,
    'parent_letter_id': parentLetterId,
    'created_by_user_id': createdByUserId,
    'decision': decision,
  };

  static DateTime? _parseDateTime(dynamic value) {
    if (value == null) return null;
    try {
      return DateTime.parse(value.toString()).toLocal();
    } catch (_) {
      return null;
    }
  }

  static List<String> _parseAttachments(dynamic value) {
    if (value == null) return [];
    if (value is String) {
      final trimmed = value.trim();
      if (trimmed.isEmpty) return [];
      try {
        final decoded = jsonDecode(trimmed);
        if (decoded is List) {
          return decoded
              .map((v) => v.toString().trim())
              .where((s) => s.isNotEmpty)
              .toList();
        }
      } catch (_) {
        // Not valid JSON; treat as single path.
      }
      return [trimmed];
    }
    if (value is List) {
      return value
          .map((v) => v.toString().trim())
          .where((s) => s.isNotEmpty)
          .toList();
    }
    return [];
  }
}

class CorrespondenceLetterAction {
  CorrespondenceLetterAction({
    required this.id,
    required this.letterId,
    required this.stepKey,
    required this.actionType,
    this.actedByUserId,
    this.actedByName,
    this.targetUserId,
    this.targetUserName,
    this.targetDepartmentId,
    this.targetDepartmentName,
    this.note,
    this.metaJson,
    this.createdAt,
  });

  final int id;
  final int letterId;
  final String stepKey;
  final String actionType; // 'created', 'delegate', 'office_comment', etc.
  final int? actedByUserId;
  final String? actedByName;
  final int? targetUserId;
  final String? targetUserName;
  final int? targetDepartmentId;
  final String? targetDepartmentName;
  final String? note;
  final Map<String, dynamic>? metaJson;
  final DateTime? createdAt;

  factory CorrespondenceLetterAction.fromJson(Map<String, dynamic> json) {
    return CorrespondenceLetterAction(
      id: (json['id'] as num?)?.toInt() ?? 0,
      letterId: (json['letter_id'] as num?)?.toInt() ?? 0,
      stepKey: (json['step_key'] ?? '').toString(),
      actionType: (json['action_type'] ?? '').toString(),
      actedByUserId: (json['acted_by_user_id'] as num?)?.toInt(),
      actedByName: (json['acted_by_name'] as String?)?.trim(),
      targetUserId: (json['target_user_id'] as num?)?.toInt(),
      targetUserName: (json['target_user_name'] as String?)?.trim(),
      targetDepartmentId: (json['target_department_id'] as num?)?.toInt(),
      targetDepartmentName: (json['target_department_name'] as String?)?.trim(),
      note: (json['note'] as String?)?.trim(),
      metaJson:
          json['meta_json'] is Map<String, dynamic> ? json['meta_json'] : null,
      createdAt: _parseDateTime(json['created_at']),
    );
  }

  static DateTime? _parseDateTime(dynamic value) {
    if (value == null) return null;
    try {
      return DateTime.parse(value.toString()).toLocal();
    } catch (_) {
      return null;
    }
  }
}

class CorrespondenceLetterDistribution {
  CorrespondenceLetterDistribution({
    required this.id,
    required this.letterId,
    this.targetDepartmentId,
    this.targetDepartmentName,
    this.targetUserId,
    this.targetUserName,
    required this.distributionType, // 'to' | 'cc'
    required this.status, // 'pending_ack', 'acknowledged', 'feedback_sent', 'closed'
    this.acknowledgedAt,
    this.feedbackNote,
    this.childLetterId,
    this.createdAt,
  });

  final int id;
  final int letterId;
  final int? targetDepartmentId;
  final String? targetDepartmentName;
  final int? targetUserId;
  final String? targetUserName;
  final String distributionType;
  final String status;
  final DateTime? acknowledgedAt;
  final String? feedbackNote;
  final int? childLetterId;
  final DateTime? createdAt;

  bool get isPendingAck => status == 'pending_ack';
  bool get isAcknowledged => status == 'acknowledged';
  bool get isFeedbackSent => status == 'feedback_sent';
  bool get isClosed => status == 'closed';
  bool get isTo => distributionType == 'to';
  bool get isCc => distributionType == 'cc';

  String getTarget() => targetUserName ?? targetDepartmentName ?? '-';

  factory CorrespondenceLetterDistribution.fromJson(Map<String, dynamic> json) {
    return CorrespondenceLetterDistribution(
      id: (json['id'] as num?)?.toInt() ?? 0,
      letterId: (json['letter_id'] as num?)?.toInt() ?? 0,
      targetDepartmentId: (json['target_department_id'] as num?)?.toInt(),
      targetDepartmentName: (json['target_department_name'] as String?)?.trim(),
      targetUserId: (json['target_user_id'] as num?)?.toInt(),
      targetUserName: (json['target_user_name'] as String?)?.trim(),
      distributionType: (json['distribution_type'] ?? 'to').toString(),
      status: (json['status'] ?? 'pending_ack').toString(),
      acknowledgedAt: _parseDateTime(json['acknowledged_at']),
      feedbackNote: (json['feedback_note'] as String?)?.trim(),
      childLetterId: (json['child_letter_id'] as num?)?.toInt(),
      createdAt: _parseDateTime(json['created_at']),
    );
  }

  static DateTime? _parseDateTime(dynamic value) {
    if (value == null) return null;
    try {
      return DateTime.parse(value.toString()).toLocal();
    } catch (_) {
      return null;
    }
  }
}

class CorrespondenceListResponse {
  CorrespondenceListResponse({
    required this.letters,
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
  });

  final List<CorrespondenceLetter> letters;
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;

  factory CorrespondenceListResponse.fromJson(Map<String, dynamic> json) {
    final data =
        json['response'] is Map<String, dynamic>
            ? json['response']['data']
            : json['data'];
    final payload = data is Map<String, dynamic> ? data : <String, dynamic>{};

    final rows = payload['data'] as List<dynamic>? ?? const <dynamic>[];
    final letters =
        rows
            .whereType<Map<String, dynamic>>()
            .map(CorrespondenceLetter.fromJson)
            .toList();

    return CorrespondenceListResponse(
      letters: letters,
      currentPage: (payload['current_page'] as num?)?.toInt() ?? 1,
      perPage: (payload['per_page'] as num?)?.toInt() ?? 20,
      total: (payload['total'] as num?)?.toInt() ?? 0,
      lastPage: (payload['last_page'] as num?)?.toInt() ?? 1,
    );
  }
}

class CorrespondenceLookupOption {
  CorrespondenceLookupOption({
    required this.id,
    required this.text,
    this.subtitle,
  });

  final int id;
  final String text;
  final String? subtitle;

  factory CorrespondenceLookupOption.fromJson(Map<String, dynamic> json) {
    return CorrespondenceLookupOption(
      id: (json['id'] as num?)?.toInt() ?? 0,
      text: (json['text'] ?? json['label'] ?? '').toString(),
      subtitle: (json['subtitle'] ?? json['path'])?.toString(),
    );
  }
}

class CorrespondenceAttachmentInput {
  CorrespondenceAttachmentInput({required this.name, this.path, this.bytes});

  final String name;
  final String? path;
  final List<int>? bytes;
}

class CorrespondenceCreateRequest {
  CorrespondenceCreateRequest({
    required this.letterType,
    required this.subject,
    this.letterNo,
    this.registryNo,
    this.priority,
    this.fromOrg,
    this.toOrg,
    this.letterDate,
    this.receivedDate,
    this.sentDate,
    this.dueDate,
    this.summary,
    this.originDepartmentId,
    this.toDepartmentIds,
    this.ccDepartmentIds,
    this.toUserIds,
    this.ccUserIds,
    this.sendAction, // 'draft' or 'send'
    this.attachmentPaths,
  });

  final String letterType;
  final String subject;
  final String? letterNo;
  final String? registryNo;
  final String? priority;
  final String? fromOrg;
  final String? toOrg;
  final DateTime? letterDate;
  final DateTime? receivedDate;
  final DateTime? sentDate;
  final DateTime? dueDate;
  final String? summary;
  final int? originDepartmentId;
  final List<int>? toDepartmentIds;
  final List<int>? ccDepartmentIds;
  final List<int>? toUserIds;
  final List<int>? ccUserIds;
  final String? sendAction;
  final List<String>? attachmentPaths;

  Map<String, dynamic> toJson() => {
    'letter_type': letterType,
    'subject': subject,
    'letter_no': letterNo,
    'registry_no': registryNo,
    'priority': priority,
    'from_org': fromOrg,
    'to_org': toOrg,
    'letter_date': letterDate?.toIso8601String().split('T')[0],
    'received_date': receivedDate?.toIso8601String().split('T')[0],
    'sent_date': sentDate?.toIso8601String().split('T')[0],
    'due_date': dueDate?.toIso8601String().split('T')[0],
    'summary': summary,
    'origin_department_id': originDepartmentId,
    'to_department_ids': toDepartmentIds,
    'cc_department_ids': ccDepartmentIds,
    'to_user_ids': toUserIds,
    'cc_user_ids': ccUserIds,
    'send_action': sendAction ?? 'draft',
  };
}
