class AuthUser {
  AuthUser({
    required this.employeeId,
    required this.name,
    required this.email,
    required this.userId,
    this.departmentName,
    this.profilePic,
    this.fcmToken,
    this.role,
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
    this.cardNo,
    this.employeeCode,
    this.position,
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
    this.skillName,
  });

  final int employeeId;
  final String name;
  final String email;
  final int userId;
  final String? departmentName;
  final String? profilePic;
  final String? fcmToken;
  final String? role;
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
  final String? cardNo;
  final String? employeeCode;
  final String? position;
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
  final String? skillName;

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    final roles = json['roles'];
    String? role;
    if (json['role'] is String) {
      role = json['role'] as String;
    } else if (roles is List && roles.isNotEmpty && roles.first is String) {
      role = roles.first as String;
    }

    final firstName = (json['first_name'] as String?) ?? '';
    final lastName = (json['last_name'] as String?) ?? '';
    final combinedName = ('$firstName $lastName').trim();

    // Gender mapping from gender_id
    final genderId = json['gender_id'];
    String? gender;
    if (genderId == 1 || genderId == '1') {
      gender = 'ប្រុស';
    } else if (genderId == 2 || genderId == '2') {
      gender = 'ស្រី';
    }

    // Marital status mapping from marital_status_id
    final maritalId = json['marital_status_id'];
    String? maritalStatus;
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
      employeeId: (json['id'] as num?)?.toInt() ?? 0,
      name:
          combinedName.isNotEmpty
              ? combinedName
              : (json['name'] as String?) ??
                  (json['full_name'] as String?) ??
                  '',
      email: (json['email'] as String?) ?? '',
      userId:
          (json['user_id'] as num?)?.toInt() ??
          (json['id'] as num?)?.toInt() ??
          0,
      departmentName: json['department_name'] as String?,
      profilePic: json['profile_pic'] as String?,
      fcmToken: json['token_id'] as String?,
      role: role,
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
      cardNo: json['card_no'] as String?,
      employeeCode: json['employee_code'] as String?,
      position: json['position_name'] as String?,
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
      workStatusName: json['work_status_name'] as String?,
      employeeGrade: json['employee_grade'] as String?,
      skillName:
          (json['skill_name'] as String?)?.isNotEmpty == true
              ? json['skill_name'] as String
              : json['current_work_skill'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return <String, dynamic>{
      'id': employeeId,
      'name': name,
      'email': email,
      'user_id': userId,
      if (departmentName != null) 'department_name': departmentName,
      if (profilePic != null) 'profile_pic': profilePic,
      if (fcmToken != null) 'token_id': fcmToken,
      if (role != null) 'role': role,
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
      if (cardNo != null) 'card_no': cardNo,
      if (employeeCode != null) 'employee_code': employeeCode,
      if (position != null) 'position_name': position,
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
