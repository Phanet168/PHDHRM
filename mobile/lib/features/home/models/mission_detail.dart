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

/// One officer as printed on the mission order ("១-លោកវេជ្ជ. ទន វុទ្ធី").
class MissionOfficer {
  const MissionOfficer({
    required this.honorific,
    required this.name,
    required this.position,
  });
  final String honorific;
  final String name;
  final String position;

  factory MissionOfficer.fromMap(Map<String, dynamic> map) => MissionOfficer(
    honorific: map['honorific']?.toString() ?? '',
    name: map['name']?.toString() ?? '',
    position: map['position']?.toString() ?? '',
  );
}

/// The facts on the official order letter (លិខិតបញ្ជាបេសកកម្ម), already
/// resolved by the server (e.g. transport type → "រថយន្តជួល").
class MissionOrderInfo {
  const MissionOrderInfo({
    this.issued = false,
    this.number = '',
    this.letterCode = '',
    this.issuedOn = '',
    this.lunarDate = '',
    this.issuePlace = '',
    this.references = const [],
    this.transport = '',
    this.funding = '',
    this.signatoryTitle = '',
  });
  final bool issued;
  final String number;
  final String letterCode;
  final String issuedOn;
  final String lunarDate;
  final String issuePlace;
  final List<String> references;
  final String transport;
  final String funding;
  final String signatoryTitle;

  /// "១២៣ មសរខ", or just the code while the number is still blank.
  String get numberLabel =>
      [number, letterCode].where((s) => s.isNotEmpty).join(' ');

  factory MissionOrderInfo.fromMap(Map<String, dynamic> map) {
    String text(String key) => map[key]?.toString().trim() ?? '';
    return MissionOrderInfo(
      issued: map['issued'] == true,
      number: text('number'),
      letterCode: text('letter_code'),
      issuedOn: text('issued_on'),
      lunarDate: text('lunar_date'),
      issuePlace: text('issue_place'),
      references:
          (map['references'] as List? ?? const [])
              .map((e) => e.toString())
              .where((e) => e.trim().isNotEmpty)
              .toList(),
      transport: text('transport'),
      funding: text('funding'),
      signatoryTitle: text('signatory_title'),
    );
  }
}

/// An officer's mission report, laid out like the official template:
/// ៤ activities, ៥ results (one per line), ៦ recommendations. Reports from
/// older app versions only carry [summary].
class MissionReportEntry {
  const MissionReportEntry({
    required this.summary,
    this.employeeName = '',
    this.isMine = false,
    this.activities = '',
    this.results = const [],
    this.recommendations = '',
    this.submittedAt,
  });
  final String summary;
  final String employeeName;
  final bool isMine;
  final String activities;
  final List<String> results;
  final String recommendations;
  final DateTime? submittedAt;

  bool get isStructured =>
      activities.isNotEmpty || results.isNotEmpty || recommendations.isNotEmpty;

  factory MissionReportEntry.fromMap(Map<String, dynamic> map) =>
      MissionReportEntry(
        summary: map['summary']?.toString() ?? '',
        employeeName: map['employee_name']?.toString() ?? '',
        isMine: map['is_mine'] == true,
        activities: map['activities']?.toString().trim() ?? '',
        results:
            (map['results'] as List? ?? const [])
                .map((e) => e.toString().trim())
                .where((e) => e.isNotEmpty)
                .toList(),
        recommendations: map['recommendations']?.toString().trim() ?? '',
        submittedAt:
            DateTime.tryParse(map['submitted_at']?.toString() ?? '')?.toLocal(),
      );
}

/// What the officer types into the report form (sections ៤–៦).
class MissionReportInput {
  const MissionReportInput({
    this.activities = '',
    this.results = const [],
    this.recommendations = '',
  });
  final String activities;
  final List<String> results;
  final String recommendations;

  Map<String, dynamic> toBody() => {
    'activities': activities.trim(),
    'results': [
      for (final line in results)
        if (line.trim().isNotEmpty) line.trim(),
    ],
    'recommendations': recommendations.trim(),
  };
}

/// One step of the request → approval → order → report history.
class MissionTimelineEntry {
  const MissionTimelineEntry({
    required this.label,
    required this.at,
    this.actor = '',
    this.note = '',
  });
  final String label;
  final DateTime? at;
  final String actor;
  final String note;

  factory MissionTimelineEntry.fromMap(Map<String, dynamic> map) =>
      MissionTimelineEntry(
        label: map['label']?.toString() ?? '',
        at: DateTime.tryParse(map['at']?.toString() ?? '')?.toLocal(),
        actor: map['actor']?.toString() ?? '',
        note: map['note']?.toString() ?? '',
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
    this.lifecycleLabel = '',
    this.order = const MissionOrderInfo(),
    this.officers = const [],
    this.timeline = const [],
    this.canViewOrder = false,
    this.canViewTravelVisa = false,
    this.canViewReport = false,
  });
  final MissionSummary mission;
  final String purpose;
  final int durationDays;
  final MissionMember? assigner;
  final List<MissionMember> members;
  final List<MissionDocument> documents;
  final List<MissionReportEntry> reports;
  final bool canStart;
  final bool canReport;
  final String missionTypeLabel;
  final String orderNumber;

  /// Workflow stage ("រង់ចាំប្រធានមន្ទីរ", "បានចេញលិខិត" ...); empty for
  /// older single-step missions.
  final String lifecycleLabel;
  final MissionOrderInfo order;
  final List<MissionOfficer> officers;
  final List<MissionTimelineEntry> timeline;

  /// Printable letters, available once the order is issued.
  final bool canViewOrder;
  final bool canViewTravelVisa;

  /// The caller's own submitted report, printable on the official template.
  final bool canViewReport;

  MissionReportEntry? get myReport =>
      reports.where((r) => r.isMine).firstOrNull;

  factory MissionDetail.fromMap(Map<String, dynamic> map) {
    final actions = map['actions'] as Map? ?? {};
    final letters = map['letters'] as Map? ?? {};
    return MissionDetail(
      mission: MissionSummary.fromMap(map),
      purpose: map['purpose']?.toString() ?? '',
      missionTypeLabel: map['mission_type_label']?.toString() ?? '',
      orderNumber: map['order_number']?.toString() ?? '',
      lifecycleLabel: map['lifecycle_label']?.toString() ?? '',
      order:
          map['order'] is Map
              ? MissionOrderInfo.fromMap(
                Map<String, dynamic>.from(map['order']),
              )
              : const MissionOrderInfo(),
      officers:
          (map['order_officers'] as List? ?? [])
              .map((e) => MissionOfficer.fromMap(Map<String, dynamic>.from(e)))
              .toList(),
      timeline:
          (map['timeline'] as List? ?? [])
              .map(
                (e) =>
                    MissionTimelineEntry.fromMap(Map<String, dynamic>.from(e)),
              )
              .toList(),
      canViewOrder: letters['order'] == true,
      canViewTravelVisa: letters['travel_visa'] == true,
      canViewReport: letters['report'] == true,
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
              .map(
                (e) => MissionReportEntry.fromMap(Map<String, dynamic>.from(e)),
              )
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
