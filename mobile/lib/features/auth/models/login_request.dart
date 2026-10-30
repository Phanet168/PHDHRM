class LoginRequest {
  LoginRequest({
    required this.email,
    required this.password,
    this.tokenId,
    this.deviceName,
    this.imei,
    this.legacyDeviceId,
  });

  final String email;
  final String password;

  /// The machine number (the phone's IMEI).
  final String? tokenId;
  final String? deviceName;
  final String? imei;

  /// The machine number used before the IMEI, so the server can move an
  /// already-approved registration to the IMEI.
  final String? legacyDeviceId;

  LoginRequest copyWith({
    String? email,
    String? password,
    String? tokenId,
    String? deviceName,
    String? imei,
    String? legacyDeviceId,
  }) {
    return LoginRequest(
      email: email ?? this.email,
      password: password ?? this.password,
      tokenId: tokenId ?? this.tokenId,
      deviceName: deviceName ?? this.deviceName,
      imei: imei ?? this.imei,
      legacyDeviceId: legacyDeviceId ?? this.legacyDeviceId,
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
      'login': email,
      'password': password,
      if (tokenId != null && tokenId!.isNotEmpty) 'device_id': tokenId,
      if (tokenId != null && tokenId!.isNotEmpty) 'token_id': tokenId,
      if (deviceName != null && deviceName!.isNotEmpty)
        'device_name': deviceName,
      if (imei != null && imei!.isNotEmpty) 'imei': imei,
      if (legacyDeviceId != null && legacyDeviceId!.isNotEmpty)
        'legacy_device_id': legacyDeviceId,
    };
  }
}
