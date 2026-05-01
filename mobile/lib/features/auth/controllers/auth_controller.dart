import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/device_heartbeat_service.dart';
import '../../../core/storage/token_storage_service.dart';
import '../models/auth_user.dart';
import '../models/login_request.dart';
import '../services/auth_service.dart';

enum AuthStatus { initializing, authenticated, unauthenticated }

class AuthController extends ChangeNotifier {
  AuthController({
    AuthService? authService,
    TokenStorageService? tokenStorageService,
    DeviceHeartbeatService? deviceHeartbeatService,
  }) : _authService = authService ?? AuthService(),
       _tokenStorageService = tokenStorageService ?? TokenStorageService(),
       _deviceHeartbeatService =
           deviceHeartbeatService ?? DeviceHeartbeatService();

  final AuthService _authService;
  final TokenStorageService _tokenStorageService;
  final DeviceHeartbeatService _deviceHeartbeatService;

  AuthStatus _status = AuthStatus.initializing;
  AuthUser? _currentUser;
  bool _isSubmitting = false;
  String? _errorMessage;

  AuthStatus get status => _status;
  AuthUser? get currentUser => _currentUser;
  bool get isSubmitting => _isSubmitting;
  String? get errorMessage => _errorMessage;

  Future<void> restoreSession() async {
    _status = AuthStatus.initializing;
    _errorMessage = null;
    notifyListeners();

    try {
      final token = await _tokenStorageService.readToken().timeout(
        const Duration(seconds: 4),
        onTimeout: () => null,
      );
      if (token == null || token.isEmpty) {
        _currentUser = null;
        _status = AuthStatus.unauthenticated;
        _deviceHeartbeatService.stop();
        notifyListeners();
        return;
      }

      final user = await _authService.getCurrentUser().timeout(
        const Duration(seconds: 15),
        onTimeout: () => null,
      );
      if (user == null) {
        await _tokenStorageService.clearToken();
        _currentUser = null;
        _status = AuthStatus.unauthenticated;
        _deviceHeartbeatService.stop();
      } else {
        _currentUser = user;
        _status = AuthStatus.authenticated;
        unawaited(_deviceHeartbeatService.start());
      }
    } on ApiException catch (error) {
      if (error is NetworkException || isNetworkErrorMessage(error.message)) {
        _currentUser = null;
        _status = AuthStatus.unauthenticated;
        _errorMessage = null;
        _deviceHeartbeatService.stop();
      } else {
        await _tokenStorageService.clearToken();
        _currentUser = null;
        _status = AuthStatus.unauthenticated;
        _errorMessage = error.message;
        _deviceHeartbeatService.stop();
      }
    } catch (error) {
      if (isNetworkErrorMessage(error.toString())) {
        _currentUser = null;
        _status = AuthStatus.unauthenticated;
        _errorMessage = null;
        _deviceHeartbeatService.stop();
      } else {
        _currentUser = null;
        _status = AuthStatus.unauthenticated;
        _errorMessage = extractApiErrorMessage(error);
        _deviceHeartbeatService.stop();
      }
    }

    notifyListeners();
  }

  Future<bool> login({required String email, required String password}) async {
    _isSubmitting = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _authService.login(
        LoginRequest(email: email.trim(), password: password),
      );

      _currentUser = response.user;
      _status = AuthStatus.authenticated;
      unawaited(_deviceHeartbeatService.start());
      return true;
    } on ApiException catch (error) {
      _errorMessage = extractApiErrorMessage(error);
      _deviceHeartbeatService.stop();
      return false;
    } on FormatException catch (error) {
      _errorMessage = error.message;
      _deviceHeartbeatService.stop();
      return false;
    } catch (error) {
      _errorMessage = extractApiErrorMessage(error);
      _deviceHeartbeatService.stop();
      return false;
    } finally {
      _isSubmitting = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    _isSubmitting = true;
    _deviceHeartbeatService.stop();
    notifyListeners();

    try {
      await _authService.logout();
    } finally {
      _currentUser = null;
      _status = AuthStatus.unauthenticated;
      _isSubmitting = false;
      notifyListeners();
    }
  }

  @override
  void dispose() {
    _deviceHeartbeatService.dispose();
    super.dispose();
  }
}
