import 'package:flutter/foundation.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/storage/token_storage_service.dart';
import '../models/auth_user.dart';
import '../models/login_request.dart';
import '../services/auth_service.dart';

enum AuthStatus { initializing, authenticated, unauthenticated }

class AuthController extends ChangeNotifier {
  AuthController({
    AuthService? authService,
    TokenStorageService? tokenStorageService,
  }) : _authService = authService ?? AuthService(),
       _tokenStorageService = tokenStorageService ?? TokenStorageService();

  final AuthService _authService;
  final TokenStorageService _tokenStorageService;

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

    final token = await _tokenStorageService.readToken();
    if (token == null || token.isEmpty) {
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return;
    }

    try {
      final user = await _authService.getCurrentUser();
      if (user == null) {
        await _tokenStorageService.clearToken();
        _currentUser = null;
        _status = AuthStatus.unauthenticated;
      } else {
        _currentUser = user;
        _status = AuthStatus.authenticated;
      }
    } on ApiException catch (error) {
      await _tokenStorageService.clearToken();
      _currentUser = null;
      _status = AuthStatus.unauthenticated;
      _errorMessage = error.message;
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
      return true;
    } on ApiException catch (error) {
      _errorMessage = error.message;
      return false;
    } on FormatException catch (error) {
      _errorMessage = error.message;
      return false;
    } finally {
      _isSubmitting = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    _isSubmitting = true;
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
}
