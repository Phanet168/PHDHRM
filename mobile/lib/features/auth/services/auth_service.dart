import '../../../core/network/api_exception.dart';
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
      imei: await _machineNumberStorageService.getImei(),
      legacyDeviceId:
          await _machineNumberStorageService.getLegacyMachineNumber(),
    );

    final response = await _apiService.post(
      '/auth/login',
      requiresAuth: false,
      body: enrichedRequest.toBody(),
    );

    final loginResponse = LoginResponse.fromJson(response);
    final token = loginResponse.tokenId?.trim();
    if (token == null || token.isEmpty) {
      throw const FormatException('Login response missing access token.');
    }

    await _tokenStorageService.saveToken(token);
    await _userSessionStorageService.saveUser(loginResponse.user);

    return loginResponse;
  }

  Future<void> logout() async {
    await _apiService.post('/auth/logout', throwOnError: false);
    await _tokenStorageService.clearToken();
    await _userSessionStorageService.clearUser();
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    await _apiService.post(
      '/auth/change-password',
      body: {
        'current_password': currentPassword,
        'new_password': newPassword,
        'new_password_confirmation': newPassword,
      },
    );
  }

  /// The profile saved at the last login/refresh — lets the app open
  /// instantly (and offline) without waiting for the server.
  Future<AuthUser?> cachedUser() => _userSessionStorageService.readUser();

  /// The latest profile from the server, saved for next launch. Throws
  /// [UnauthorizedException] when the token is no longer valid; network
  /// trouble surfaces as other [ApiException]s.
  Future<AuthUser> refreshCurrentUser() async {
    final response = await _apiService.get('/auth/profile');
    final rawUser = response['user'];
    if ((response['status'] ?? '').toString().toLowerCase() != 'ok' ||
        rawUser is! Map<String, dynamic>) {
      throw ApiException(message: 'Invalid profile response');
    }
    final user = AuthUser.fromJson(rawUser);
    await _userSessionStorageService.saveUser(user);
    return user;
  }

  /// Forgets this device's session without calling the server (it already
  /// rejected the token).
  Future<void> clearLocalSession() async {
    await _tokenStorageService.clearToken();
    await _userSessionStorageService.clearUser();
  }
}
