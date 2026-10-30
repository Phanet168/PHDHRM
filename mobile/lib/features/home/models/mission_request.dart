/// A pick-list entry from the server (mission type, transport, funding).
class MissionOption {
  const MissionOption({required this.id, required this.name});
  final int id;
  final String name;

  factory MissionOption.fromMap(Map<String, dynamic> map) => MissionOption(
    id: (map['id'] as num?)?.toInt() ?? 0,
    name: map['name']?.toString() ?? '',
  );
}

/// A colleague the requester may add to the team.
class MissionColleague {
  const MissionColleague({
    required this.id,
    required this.name,
    this.position = '',
  });
  final int id;
  final String name;
  final String position;

  factory MissionColleague.fromMap(Map<String, dynamic> map) =>
      MissionColleague(
        id: (map['id'] as num?)?.toInt() ?? 0,
        name: map['name']?.toString() ?? '',
        position: map['position']?.toString() ?? '',
      );
}

/// One approval step the request will go through, with who holds it.
class MissionApprovalStep {
  const MissionApprovalStep({required this.label, required this.names});
  final String label;
  final List<String> names;

  factory MissionApprovalStep.fromMap(Map<String, dynamic> map) =>
      MissionApprovalStep(
        label: map['label']?.toString() ?? '',
        names:
            (map['names'] as List? ?? const [])
                .map((e) => e.toString())
                .where((e) => e.isNotEmpty)
                .toList(),
      );
}

/// Everything the request form needs: the requester, pick-lists and the
/// approval route (`GET /v1/missions/request-options`).
class MissionRequestOptions {
  const MissionRequestOptions({
    required this.requester,
    this.missionTypes = const [],
    this.transportTypes = const [],
    this.fundingSources = const [],
    this.approvalRoute = const [],
  });
  final MissionColleague requester;
  final List<MissionOption> missionTypes;
  final List<MissionOption> transportTypes;
  final List<MissionOption> fundingSources;
  final List<MissionApprovalStep> approvalRoute;

  factory MissionRequestOptions.fromMap(Map<String, dynamic> map) {
    List<MissionOption> options(String key) =>
        (map[key] as List? ?? const [])
            .map((e) => MissionOption.fromMap(Map<String, dynamic>.from(e)))
            .toList();
    final requester = Map<String, dynamic>.from(map['requester'] as Map? ?? {});
    return MissionRequestOptions(
      requester: MissionColleague(
        id: (requester['employee_id'] as num?)?.toInt() ?? 0,
        name: requester['name']?.toString() ?? '',
        position: requester['position']?.toString() ?? '',
      ),
      missionTypes: options('mission_types'),
      transportTypes: options('transport_types'),
      fundingSources: options('funding_sources'),
      approvalRoute:
          (map['approval_route'] as List? ?? const [])
              .map(
                (e) =>
                    MissionApprovalStep.fromMap(Map<String, dynamic>.from(e)),
              )
              .toList(),
    );
  }
}

/// The filled-in request (`POST /v1/missions/request`).
class MissionRequestInput {
  const MissionRequestInput({
    required this.title,
    required this.destination,
    required this.startDate,
    required this.endDate,
    required this.employeeIds,
    this.purpose = '',
    this.missionTypeId,
    this.transportTypeId,
    this.transportDescription = '',
    this.fundingSourceId,
    this.sponsorName = '',
  });
  final String title;
  final String destination;
  final DateTime startDate;
  final DateTime endDate;
  final List<int> employeeIds;
  final String purpose;
  final int? missionTypeId;
  final int? transportTypeId;
  final String transportDescription;
  final int? fundingSourceId;
  final String sponsorName;

  static String _ymd(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Map<String, dynamic> toBody() => {
    'title': title.trim(),
    'destination': destination.trim(),
    'start_date': _ymd(startDate),
    'end_date': _ymd(endDate),
    'employee_ids': employeeIds,
    'purpose': purpose.trim(),
    if (missionTypeId != null) 'mission_type_id': missionTypeId,
    if (transportTypeId != null) 'transport_type_id': transportTypeId,
    if (transportDescription.trim().isNotEmpty)
      'transport_description': transportDescription.trim(),
    if (fundingSourceId != null) 'funding_source_id': fundingSourceId,
    if (sponsorName.trim().isNotEmpty) 'sponsor_name': sponsorName.trim(),
  };
}
