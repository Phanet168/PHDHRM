class AuthUser {
  AuthUser({
    required this.employeeId,
    required this.name,
    required this.email,
    required this.userId,
    required this.userTypeId,
    this.hasEmployeeProfile = true,
    this.departmentName,
    this.profilePic,
    this.fcmToken,
    this.role,
    this.canReviewLeaveRequestsFlag,
    // Personal
    this.phone,
    this.alternatePhone,
    this.dateOfBirth,
    this.gender,
    this.maritalStatus,
    this.nationality,
    this.religion,
    this.ethnicGroup,
    this.presentAddress,
    this.permanentAddress,
    // Identity
    this.nationalId,
    // Work
    this.employeeNo,
    this.cardNo,
    this.employeeCode,
    this.position,
    this.positionKm,
    this.joiningDate,
    this.hireDate,
    this.serviceStartDate,
    this.contractStartDate,
    this.contractEndDate,
    this.fullRightDate,
    this.isFullRightOfficer,
    this.legalDocumentType,
    this.legalDocumentNumber,
    this.workStatusName,
    this.employeeGrade,
    this.employeeGradeKm,
    this.skillName,
  });

  final int employeeId;
  final String name;
  final String email;
  final int userId;
  final int userTypeId;

  /// false = admin/technical user who has no employee record
  final bool hasEmployeeProfile;
  final String? departmentName;

  /// User is a system admin (user_type_id = 1 or Super Admin role)
  bool get isSystemAdmin =>
      userTypeId == 1 || (role?.toLowerCase().contains('super admin') == true);

  /// User has an employee profile linked
  bool get hasEmployee => hasEmployeeProfile && employeeId > 0;
  final String? profilePic;
  final String? fcmToken;
  final String? role;
  final bool? canReviewLeaveRequestsFlag;
  // Personal
  final String? phone;
  final String? alternatePhone;
  final String? dateOfBirth;
  final String? gender;
  final String? maritalStatus;
  final String? nationality;
  final String? religion;
  final String? ethnicGroup;
  final String? presentAddress;
  final String? permanentAddress;
  // Identity
  final String? nationalId;
  // Work
  final String? employeeNo;
  final String? cardNo;
  final String? employeeCode;
  final String? position;
  final String? positionKm;
  final String? joiningDate;
  final String? hireDate;
  final String? serviceStartDate;
  final String? contractStartDate;
  final String? contractEndDate;
  final String? fullRightDate;
  final bool? isFullRightOfficer;
  final String? legalDocumentType;
  final String? legalDocumentNumber;
  final String? workStatusName;
  final String? employeeGrade;
  final String? employeeGradeKm;
  final String? skillName;

  bool get canReviewLeaveRequests {
    if (canReviewLeaveRequestsFlag != null) {
      return canReviewLeaveRequestsFlag!;
    }

    final normalized = (role ?? '').trim().toLowerCase();
    if (normalized.isEmpty) {
      return false;
    }

    return normalized.contains('admin') ||
        normalized.contains('manager') ||
        normalized.contains('hr');
  }

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    final roles = json['roles'];
    String? role;
    if (json['role'] is String) {
      role = json['role'] as String;
    } else if (roles is List && roles.isNotEmpty && roles.first is String) {
      role = roles.first as String;
    }

    final firstName = (json['first_name'] as String?)?.trim() ?? '';
    final middleName = (json['middle_name'] as String?)?.trim() ?? '';
    final lastName = (json['last_name'] as String?)?.trim() ?? '';
    final combinedName = ('$firstName $lastName').trim();
    final combinedWithMiddle = ('$firstName $middleName $lastName').trim();

    // Gender mapping from payload
    final genderId = json['gender_id'];
    String? gender = (json['gender_display'] as String?)?.trim();
    if (genderId == 1 || genderId == '1') {
      gender = 'ប្រុស';
    } else if (genderId == 2 || genderId == '2') {
      gender = 'ស្រី';
    } else if ((gender == null || gender.isEmpty) &&
        json['gender_name'] is String) {
      gender = (json['gender_name'] as String).trim();
    }

    // Marital status mapping from payload
    final maritalId = json['marital_status_id'];
    String? maritalStatus = (json['marital_status_name'] as String?)?.trim();
    if (maritalId == 1 || maritalId == '1') {
      maritalStatus = 'អវីវាហៈ';
    } else if (maritalId == 2 || maritalId == '2') {
      maritalStatus = 'មានប្ដី/ប្រពន្ធ';
    } else if (maritalId == 3 || maritalId == '3') {
      maritalStatus = 'លែងលះ';
    } else if (maritalId == 4 || maritalId == '4') {
      maritalStatus = 'មេម៉ាយ';
    }

    // Full-right officer flag
    bool? isFullRight;
    final fro = json['is_full_right_officer'];
    if (fro != null) {
      isFullRight = fro == 1 || fro == '1' || fro == true;
    }

    return AuthUser(
      employeeId: _toInt(
        json['employee_record_id'] ?? json['employee_db_id'] ?? json['id'],
      ),
      userTypeId: _toInt(json['user_type_id']),
      hasEmployeeProfile: json['has_employee_profile'] != false,
      name:
          (json['full_name'] as String?)?.trim().isNotEmpty == true
              ? (json['full_name'] as String).trim()
              : combinedWithMiddle.isNotEmpty
              ? combinedWithMiddle
              : combinedName.isNotEmpty
              ? combinedName
              : ((json['name'] as String?)?.trim() ?? ''),
      email: (json['email'] as String?) ?? '',
      userId:
          _toIntOrNull(json['user_id']) ??
          _toIntOrNull(json['auth_user_id']) ??
          _toIntOrNull(json['id']) ??
          0,
      departmentName:
          (json['sub_department_name'] as String?)?.isNotEmpty == true
              ? json['sub_department_name'] as String
              : ((json['department_name'] as String?)?.isNotEmpty == true
                  ? json['department_name'] as String
                  : json['unit_name'] as String?),
      profilePic: json['profile_pic'] as String?,
      fcmToken: json['token_id'] as String?,
      role: role,
      canReviewLeaveRequestsFlag:
          json['can_review_leave_requests'] == true ||
          json['can_review_leave_requests'] == 1 ||
          json['can_review_leave_requests'] == '1',
      // Personal
      phone:
          (json['phone'] as String?)?.isNotEmpty == true
              ? json['phone'] as String
              : (json['cell_phone'] as String?),
      alternatePhone: json['alternate_phone'] as String?,
      dateOfBirth: json['date_of_birth'] as String?,
      gender: gender,
      maritalStatus: maritalStatus,
      nationality:
          (json['nationality'] as String?)?.isNotEmpty == true
              ? json['nationality'] as String
              : json['citizenship'] as String?,
      religion: json['religion'] as String?,
      ethnicGroup: json['ethnic_group'] as String?,
      presentAddress: json['present_address'] as String?,
      permanentAddress: json['permanent_address'] as String?,
      // Identity
      nationalId:
          (json['national_id_no'] as String?)?.isNotEmpty == true
              ? json['national_id_no'] as String
              : json['national_id'] as String?,
      // Work
      employeeNo:
          (json['employee_id'] as String?)?.isNotEmpty == true
              ? json['employee_id'] as String
              : (json['official_id_10'] as String?),
      cardNo: json['card_no'] as String?,
      employeeCode: json['employee_code'] as String?,
      position:
          (json['position_name'] as String?)?.isNotEmpty == true
              ? json['position_name'] as String
              : (json['role_display'] as String?),
      positionKm: json['position_name_km'] as String?,
      joiningDate: json['joining_date'] as String?,
      hireDate: json['hire_date'] as String?,
      serviceStartDate:
          (json['service_start_date'] as String?)?.isNotEmpty == true
              ? json['service_start_date'] as String
              : json['joining_date'] as String?,
      contractStartDate: json['contract_start_date'] as String?,
      contractEndDate: json['contract_end_date'] as String?,
      fullRightDate: json['full_right_date'] as String?,
      isFullRightOfficer: isFullRight,
      legalDocumentType: json['legal_document_type'] as String?,
      legalDocumentNumber: json['legal_document_number'] as String?,
      workStatusName:
          (json['work_status_name'] as String?)?.isNotEmpty == true
              ? json['work_status_name'] as String
              : (json['work_status'] as String?),
      employeeGrade:
          (json['employee_grade'] as String?)?.isNotEmpty == true
              ? json['employee_grade'] as String
              : (json['pay_level'] as String?),
      employeeGradeKm: json['employee_grade_km'] as String?,
      skillName:
          (json['skill_name'] as String?)?.isNotEmpty == true
              ? json['skill_name'] as String
              : ((json['current_work_skill'] as String?)?.isNotEmpty == true
                  ? json['current_work_skill'] as String
                  : json['skill'] as String?),
    );
  }

  static int _toInt(dynamic value) {
    return _toIntOrNull(value) ?? 0;
  }

  static int? _toIntOrNull(dynamic value) {
    if (value is num) {
      return value.toInt();
    }

    if (value is String) {
      return int.tryParse(value.trim());
    }

    return null;
  }

  Map<String, dynamic> toJson() {
    return <String, dynamic>{
      'id': employeeId,
      'name': name,
      'email': email,
      'user_id': userId,
      'user_type_id': userTypeId,
      'has_employee_profile': hasEmployeeProfile,
      if (departmentName != null) 'department_name': departmentName,
      if (profilePic != null) 'profile_pic': profilePic,
      if (fcmToken != null) 'token_id': fcmToken,
      if (role != null) 'role': role,
      if (canReviewLeaveRequestsFlag != null)
        'can_review_leave_requests': canReviewLeaveRequestsFlag! ? 1 : 0,
      if (phone != null) 'phone': phone,
      if (alternatePhone != null) 'alternate_phone': alternatePhone,
      if (dateOfBirth != null) 'date_of_birth': dateOfBirth,
      if (gender != null) 'gender_id': gender == 'ប្រុស' ? 1 : 2,
      if (maritalStatus != null) 'marital_status_id': _maritalId(),
      if (nationality != null) 'nationality': nationality,
      if (religion != null) 'religion': religion,
      if (ethnicGroup != null) 'ethnic_group': ethnicGroup,
      if (presentAddress != null) 'present_address': presentAddress,
      if (permanentAddress != null) 'permanent_address': permanentAddress,
      if (nationalId != null) 'national_id': nationalId,
      if (employeeNo != null) 'employee_id': employeeNo,
      if (cardNo != null) 'card_no': cardNo,
      if (employeeCode != null) 'employee_code': employeeCode,
      if (position != null) 'position_name': position,
      if (positionKm != null) 'position_name_km': positionKm,
      if (joiningDate != null) 'joining_date': joiningDate,
      if (hireDate != null) 'hire_date': hireDate,
      if (serviceStartDate != null) 'service_start_date': serviceStartDate,
      if (contractStartDate != null) 'contract_start_date': contractStartDate,
      if (contractEndDate != null) 'contract_end_date': contractEndDate,
      if (fullRightDate != null) 'full_right_date': fullRightDate,
      if (isFullRightOfficer != null)
        'is_full_right_officer': isFullRightOfficer! ? 1 : 0,
      if (legalDocumentType != null) 'legal_document_type': legalDocumentType,
      if (legalDocumentNumber != null)
        'legal_document_number': legalDocumentNumber,
      if (workStatusName != null) 'work_status_name': workStatusName,
      if (employeeGrade != null) 'employee_grade': employeeGrade,
      if (employeeGradeKm != null) 'employee_grade_km': employeeGradeKm,
      if (skillName != null) 'skill_name': skillName,
    };
  }

  int? _maritalId() {
    switch (maritalStatus) {
      case 'អវីវាហៈ':
        return 1;
      case 'មានប្ដី/ប្រពន្ធ':
        return 2;
      case 'លែងលះ':
        return 3;
      case 'មេម៉ាយ':
        return 4;
      default:
        return null;
    }
  }
}
