import '../../../core/network/api_service.dart';
import '../../auth/models/auth_user.dart';

class HomeProfileService {
  HomeProfileService({ApiService? apiService})
    : _apiService = apiService ?? ApiService();

  final ApiService _apiService;
  static const Duration _cacheLifetime = Duration(minutes: 5);

  AuthUser? _cachedProfile;
  DateTime? _cachedAt;

  /// Fetch fresh user profile from backend, with optional caching
  Future<AuthUser> fetchProfile({bool forceRefresh = false}) async {
    if (!forceRefresh && _cachedProfile != null && _cachedAt != null) {
      final age = DateTime.now().difference(_cachedAt!);
      if (age <= _cacheLifetime) {
        return _cachedProfile!;
      }
    }

    try {
      final response = await _apiService.get(
        '/auth/profile',
        requiresAuth: true,
      );

      // Map the response to AuthUser model
      final user = _mapProfileResponse(_extractProfilePayload(response));

      _cachedProfile = user;
      _cachedAt = DateTime.now();

      return user;
    } catch (_) {
      if (_cachedProfile != null) {
        return _cachedProfile!;
      }
      rethrow;
    }
  }

  Map<String, dynamic> _extractProfilePayload(Map<String, dynamic> response) {
    final payload = response['user'];
    if (payload is Map<String, dynamic>) {
      return payload;
    }

    // Backward compatibility for legacy endpoints that return direct fields.
    return response;
  }

  /// Convert API response to AuthUser model
  AuthUser _mapProfileResponse(Map<String, dynamic> response) {
    final employeeId =
        _toInt(response['id']) ?? _toInt(response['employee_id']) ?? 0;
    final userId =
        _toInt(response['user_id']) ?? _toInt(response['auth_user_id']) ?? 0;

    return AuthUser(
      employeeId: employeeId,
      userId: userId,
      userTypeId: _toInt(response['user_type_id']) ?? 0,
      name:
          response['full_name'] as String? ??
          '${response['first_name'] ?? ''} ${response['last_name'] ?? ''}'
              .trim(),
      email: response['email'] as String? ?? '',
      hasEmployeeProfile:
          (_toBool(response['has_employee_profile']) ?? false) ||
          employeeId > 0,
      profilePic: response['profile_pic'] as String?,
      fcmToken: null,
      role:
          response['roles'] != null
              ? (response['roles'] as List?)?.join(', ')
              : null,
      canReviewLeaveRequestsFlag: _toBool(
        response['can_review_leave_requests'],
      ),
      // Personal
      phone: response['phone'] as String?,
      alternatePhone: response['alternate_phone'] as String?,
      dateOfBirth: response['date_of_birth'] as String?,
      gender: response['gender_name'] as String?,
      maritalStatus: response['marital_status_name'] as String?,
      nationality: response['nationality'] as String?,
      religion: response['religion'] as String?,
      ethnicGroup: response['ethnic_group'] as String?,
      presentAddress: response['present_address'] as String?,
      permanentAddress: response['permanent_address'] as String?,
      // Identity
      nationalId: response['national_id_no'] as String?,
      // Work
      employeeNo: response['employee_code'] as String?,
      cardNo: response['card_no'] as String?,
      employeeCode: response['employee_code'] as String?,
      position: response['position_name'] as String?,
      positionKm: response['position_name_km'] as String?,
      joiningDate: response['joining_date'] as String?,
      hireDate: response['hire_date'] as String?,
      serviceStartDate: response['service_start_date'] as String?,
      contractStartDate: response['contract_start_date'] as String?,
      contractEndDate: response['contract_end_date'] as String?,
      fullRightDate: response['full_right_date'] as String?,
      isFullRightOfficer: _toBool(response['is_full_right_officer']),
      legalDocumentType: response['legal_document_type'] as String?,
      legalDocumentNumber: response['legal_document_number'] as String?,
      workStatusName: response['work_status_name'] as String?,
      employeeGrade: response['employee_grade'] as String?,
      employeeGradeKm: response['employee_grade_km'] as String?,
      skillName: response['skill_name'] as String?,
      departmentName: response['department_name'] as String?,
    );
  }

  int? _toInt(dynamic value) {
    if (value == null) {
      return null;
    }
    if (value is int) {
      return value;
    }
    if (value is num) {
      return value.toInt();
    }
    final text = value.toString().trim();
    if (text.isEmpty) {
      return null;
    }
    return int.tryParse(text);
  }

  bool? _toBool(dynamic value) {
    if (value == null) {
      return null;
    }
    if (value is bool) {
      return value;
    }
    if (value is num) {
      return value != 0;
    }
    final text = value.toString().trim().toLowerCase();
    if (text == 'true' || text == '1' || text == 'yes') {
      return true;
    }
    if (text == 'false' || text == '0' || text == 'no') {
      return false;
    }
    return null;
  }

  /// Clear cached profile
  void clearCache() {
    _cachedProfile = null;
    _cachedAt = null;
  }
}
