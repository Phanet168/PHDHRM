import '../../../core/network/api_service.dart';
import '../models/device_access_request_result.dart';

class DeviceAccessRequestService {
  DeviceAccessRequestService({ApiService? apiService})
    : _apiService = apiService ?? ApiService();

  final ApiService _apiService;

  Future<DeviceAccessRequestResult> submitRequest({
    required String fullName,
    required String email,
    required String machineNumber,
    Map<String, dynamic>? deviceInfo,
    String? deviceSummary,
    String? phone,
    String? reason,
  }) async {
    final payload = <String, dynamic>{
      'full_name': fullName.trim(),
      'email': email.trim().isEmpty ? null : email.trim(),
      'phone': (phone ?? '').trim().isEmpty ? null : phone!.trim(),
      'machine_number': machineNumber.trim(),
      'device_info': deviceInfo,
      'device_summary':
          (deviceSummary ?? '').trim().isEmpty ? null : deviceSummary!.trim(),
      'reason': (reason ?? '').trim().isEmpty ? null : reason!.trim(),
    };

    final response = await _apiService.post(
      '/device-access-requests',
      requiresAuth: false,
      body: payload,
    );

    return DeviceAccessRequestResult.fromJson(response);
  }
}
