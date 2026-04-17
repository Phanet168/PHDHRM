class DeviceAccessRequestResult {
  DeviceAccessRequestResult({
    required this.requestId,
    required this.status,
    required this.message,
  });

  final int requestId;
  final String status;
  final String message;

  factory DeviceAccessRequestResult.fromJson(Map<String, dynamic> json) {
    return DeviceAccessRequestResult(
      requestId: (json['request_id'] as num?)?.toInt() ?? 0,
      status:
          (json['request_status'] as String?) ??
          (json['status'] as String?) ??
          'pending',
      message: (json['message'] as String?) ?? 'Request submitted.',
    );
  }
}
