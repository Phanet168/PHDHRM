import 'mission_summary.dart';

class MissionMember {
  const MissionMember({
    required this.id,
    required this.name,
    required this.initial,
  });
  final int id;
  final String name;
  final String initial;

  factory MissionMember.fromMap(Map<String, dynamic> map) => MissionMember(
    id: (map['id'] as num?)?.toInt() ?? 0,
    name: map['name']?.toString() ?? '',
    initial: map['initial']?.toString() ?? '',
  );
}

class MissionDocument {
  const MissionDocument({
    required this.id,
    required this.name,
    required this.size,
  });
  final int id;
  final String name;
  final int size;

  String get sizeLabel =>
      size >= 1024 * 1024
          ? '${(size / (1024 * 1024)).toStringAsFixed(1)} MB'
          : '${(size / 1024).toStringAsFixed(1)} KB';

  factory MissionDocument.fromMap(Map<String, dynamic> map) => MissionDocument(
    id: (map['id'] as num?)?.toInt() ?? 0,
    name: map['name']?.toString() ?? '',
    size: (map['size'] as num?)?.toInt() ?? 0,
  );
}

class MissionDetail {
  const MissionDetail({
    required this.mission,
    required this.purpose,
    required this.durationDays,
    required this.assigner,
    required this.members,
    required this.documents,
    required this.reports,
    required this.canStart,
    required this.canReport,
    this.missionTypeLabel = '',
    this.orderNumber = '',
  });
  final MissionSummary mission;
  final String purpose;
  final int durationDays;
  final MissionMember? assigner;
  final List<MissionMember> members;
  final List<MissionDocument> documents;
  final List<String> reports;
  final bool canStart;
  final bool canReport;
  final String missionTypeLabel;
  final String orderNumber;

  factory MissionDetail.fromMap(Map<String, dynamic> map) {
    final actions = map['actions'] as Map? ?? {};
    return MissionDetail(
      mission: MissionSummary.fromMap(map),
      purpose: map['purpose']?.toString() ?? '',
      missionTypeLabel: map['mission_type_label']?.toString() ?? '',
      orderNumber: map['order_number']?.toString() ?? '',
      durationDays: (map['duration_days'] as num?)?.toInt() ?? 0,
      assigner:
          map['assigner'] is Map
              ? MissionMember.fromMap(
                Map<String, dynamic>.from(map['assigner']),
              )
              : null,
      members:
          (map['team_members'] as List? ?? [])
              .map((e) => MissionMember.fromMap(Map<String, dynamic>.from(e)))
              .toList(),
      documents:
          (map['documents'] as List? ?? [])
              .map((e) => MissionDocument.fromMap(Map<String, dynamic>.from(e)))
              .toList(),
      reports:
          (map['reports'] as List? ?? [])
              .map((e) => e['summary']?.toString() ?? '')
              .toList(),
      canStart: actions['can_start'] == true,
      canReport: actions['can_report'] == true,
    );
  }
}

String missionStatusLabel(String status) =>
    const {
      'draft': 'សេចក្ដីព្រាង',
      'pending': 'រង់ចាំអនុម័ត',
      'approved': 'បានអនុម័ត',
      'in_progress': 'កំពុងដំណើរការ',
      'completed': 'បានបញ្ចប់',
      'rejected': 'បានបដិសេធ',
      'cancelled': 'បានបោះបង់',
    }[status] ??
    status;
