class LoginRequest {
  LoginRequest({
    required this.email,
    required this.password,
    this.tokenId,
    this.deviceName,
  });

  final String email;
  final String password;
  final String? tokenId;
  final String? deviceName;

  LoginRequest copyWith({
    String? email,
    String? password,
    String? tokenId,
    String? deviceName,
  }) {
    return LoginRequest(
      email: email ?? this.email,
      password: password ?? this.password,
      tokenId: tokenId ?? this.tokenId,
      deviceName: deviceName ?? this.deviceName,
    );
  }

  Map<String, dynamic> toQueryParameters() {
    return <String, dynamic>{
      // Support both APIs: modern endpoint expects `email`, legacy web can use `login`.
      'email': email,
      'login': email,
      'password': password,
      'token_id': tokenId ?? '',
      'device_name': deviceName ?? '',
    };
  }

  Map<String, dynamic> toBody() {
    return <String, dynamic>{
      'email': email,
      'password': password,
      if (tokenId != null && tokenId!.isNotEmpty) 'device_id': tokenId,
      if (tokenId != null && tokenId!.isNotEmpty) 'token_id': tokenId,
      if (deviceName != null && deviceName!.isNotEmpty)
        'device_name': deviceName,
    };
  }
}
