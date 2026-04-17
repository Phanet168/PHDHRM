import '../../../core/network/api_service.dart';
import '../../../core/storage/machine_number_storage_service.dart';
import '../../../core/storage/token_storage_service.dart';
import '../../../core/storage/user_session_storage_service.dart';
import '../models/auth_user.dart';
import '../models/login_request.dart';
import '../models/login_response.dart';

class AuthService {
  AuthService({
    ApiService? apiService,
    TokenStorageService? tokenStorageService,
    UserSessionStorageService? userSessionStorageService,
    MachineNumberStorageService? machineNumberStorageService,
  }) : _apiService = apiService ?? ApiService(),
       _tokenStorageService = tokenStorageService ?? TokenStorageService(),
       _userSessionStorageService =
           userSessionStorageService ?? UserSessionStorageService(),
       _machineNumberStorageService =
           machineNumberStorageService ?? MachineNumberStorageService();

  final ApiService _apiService;
  final TokenStorageService _tokenStorageService;
  final UserSessionStorageService _userSessionStorageService;
  final MachineNumberStorageService _machineNumberStorageService;

  Future<LoginResponse> login(LoginRequest request) async {
    final machineNumber = await _machineNumberStorageService.getMachineNumber();
    final enrichedRequest = request.copyWith(
      tokenId: machineNumber,
      deviceName: machineNumber,
    );

    final response = await _apiService.post(
      '/auth/login',
      requiresAuth: false,
      body: enrichedRequest.toBody(),
    );

    final loginResponse = LoginResponse.fromJson(response);
    await _tokenStorageService.saveToken(
      loginResponse.tokenId ??
          '${loginResponse.userId}:${loginResponse.user.employeeId}',
    );
    await _userSessionStorageService.saveUser(loginResponse.user);

    return loginResponse;
  }

  Future<void> logout() async {
    await _tokenStorageService.clearToken();
    await _userSessionStorageService.clearUser();
  }

  Future<AuthUser?> getCurrentUser() {
    return _userSessionStorageService.readUser();
  }
}
