import 'auth_user.dart';

class LoginResponse {
  LoginResponse({
    required this.status,
    required this.message,
    required this.user,
    required this.userId,
    this.tokenId,
  });

  final String status;
  final String message;
  final AuthUser user;
  final int userId;
  final String? tokenId;

  factory LoginResponse.fromJson(Map<String, dynamic> json) {
    if (json['status'] is String && json['user'] is Map<String, dynamic>) {
      final status = (json['status'] as String).trim();
      final message = (json['message'] as String?) ?? '';
      if (status.toLowerCase() != 'ok') {
        throw FormatException(message.isNotEmpty ? message : 'Login failed');
      }

      final rawUser = json['user'] as Map<String, dynamic>;
      final normalizedUser = <String, dynamic>{
        ...rawUser,
        'user_id': (rawUser['id'] as num?)?.toInt() ?? 0,
      };

      return LoginResponse(
        status: status,
        message: message,
        user: AuthUser.fromJson(normalizedUser),
        userId: (rawUser['id'] as num?)?.toInt() ?? 0,
        tokenId: json['access_token'] as String?,
      );
    }

    final response = json['response'];
    if (response is! Map<String, dynamic>) {
      throw const FormatException(
        'Login response does not contain response object',
      );
    }

    final status = response['status'];
    if (status is! String) {
      throw const FormatException('Login response does not contain status');
    }

    final message = (response['message'] as String?) ?? '';

    if (status.toLowerCase() != 'ok') {
      throw FormatException(message.isNotEmpty ? message : 'Login failed');
    }

    final userData = response['user_data'];
    if (userData is! Map<String, dynamic>) {
      throw const FormatException('Login response does not contain user_data');
    }

    final rawUser = userData['userdata'];
    if (rawUser is! Map<String, dynamic>) {
      throw const FormatException('Login response does not contain userdata');
    }

    final userId = (userData['user_id'] as num?)?.toInt() ?? 0;
    final tokenId = userData['tokendata'] as String?;
    final userPayload = <String, dynamic>{
      ...rawUser,
      'user_id': userId,
      if (tokenId != null) 'token_id': tokenId,
    };

    return LoginResponse(
      status: status,
      message: message,
      user: AuthUser.fromJson(userPayload),
      userId: userId,
      tokenId: tokenId,
    );
  }
}
