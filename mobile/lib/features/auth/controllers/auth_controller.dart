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

  /// Signs in from the saved session. With a saved token and profile the app
  /// opens at once — even offline — and the profile is refreshed in the
  /// background. Only the server rejecting the token (401) signs the user out;
  /// no internet or a slow server never does.
  Future<void> restoreSession() async {
    _status = AuthStatus.initializing;
    _errorMessage = null;
    notifyListeners();

    final token = await _tokenStorageService.readToken().timeout(
      const Duration(seconds: 4),
      onTimeout: () => null,
    );
    if (token == null || token.isEmpty) {
      _signedOut();
      return;
    }

    final cached = await _authService.cachedUser();
    if (cached != null) {
      _currentUser = cached;
      _status = AuthStatus.authenticated;
      unawaited(_deviceHeartbeatService.start());
      notifyListeners();
      unawaited(_refreshProfile());
      return;
    }

    // A token without a saved profile (e.g. after an app update): the server
    // has to supply it once.
    try {
      _currentUser = await _authService.refreshCurrentUser().timeout(
        const Duration(seconds: 20),
      );
      _status = AuthStatus.authenticated;
      unawaited(_deviceHeartbeatService.start());
      notifyListeners();
    } on UnauthorizedException {
      await _authService.clearLocalSession();
      _signedOut();
    } catch (_) {
      // Unreachable server: keep the token so the next launch can still restore.
      _signedOut();
    }
  }

  Future<void> _refreshProfile() async {
    try {
      final user = await _authService.refreshCurrentUser();
      if (_status != AuthStatus.authenticated) return;
      _currentUser = user;
      notifyListeners();
    } on UnauthorizedException {
      await _authService.clearLocalSession();
      _signedOut(message: 'សម័យប្រើប្រាស់បានផុតកំណត់ សូមចូលម្ដងទៀត។');
    } catch (_) {
      // Offline or slow: carry on with the saved profile.
    }
  }

  void _signedOut({String? message}) {
    _currentUser = null;
    _status = AuthStatus.unauthenticated;
    _errorMessage = message;
    _deviceHeartbeatService.stop();
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
      _errorMessage = extractApiErrorMessage(error);
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

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) {
    return _authService.changePassword(
      currentPassword: currentPassword,
      newPassword: newPassword,
    );
  }

  @override
  void dispose() {
    _deviceHeartbeatService.dispose();
    super.dispose();
  }
}
