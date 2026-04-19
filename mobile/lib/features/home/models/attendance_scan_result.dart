class AttendanceScanResult {
  const AttendanceScanResult({
    required this.status,
    required this.message,
    this.errorCode,
    this.scanLogId,
    this.rangeMeters,
    this.acceptableRangeMeters,
    this.workplaceId,
    this.workplaceName,
    this.geofenceSource,
    this.machineState,
    this.punchType,
  });

  final String status;
  final String message;
  final String? errorCode;
  final int? scanLogId;
  final double? rangeMeters;
  final double? acceptableRangeMeters;
  final int? workplaceId;
  final String? workplaceName;
  final String? geofenceSource;
  final int? machineState;
  final String? punchType;

  bool get isSuccess => status.toLowerCase() == 'ok';

  factory AttendanceScanResult.fromApi(Map<String, dynamic> payload) {
    return AttendanceScanResult(
      status: (payload['status'] ?? '').toString(),
      message: (payload['message'] ?? '').toString(),
      errorCode: _toStringOrNull(payload['error_code']),
      scanLogId: _toInt(payload['scan_log_id'] ?? payload['log_id']),
      rangeMeters: _toDouble(payload['range']),
      acceptableRangeMeters: _toDouble(payload['acceptable_range']),
      workplaceId: _toInt(payload['workplace_id']),
      workplaceName: _toStringOrNull(payload['workplace_name']),
      geofenceSource: _toStringOrNull(payload['geofence_source']),
      machineState: _toInt(payload['machine_state']),
      punchType: _toStringOrNull(payload['punch_type']),
    );
  }

  static double? _toDouble(dynamic value) {
    if (value == null) {
      return null;
    }

    if (value is num) {
      return value.toDouble();
    }

    return double.tryParse(value.toString().trim());
  }

  static int? _toInt(dynamic value) {
    if (value == null) {
      return null;
    }

    if (value is int) {
      return value;
    }

    if (value is num) {
      return value.toInt();
    }

    return int.tryParse(value.toString().trim());
  }

  static String? _toStringOrNull(dynamic value) {
    final text = value?.toString().trim();
    if (text == null || text.isEmpty) {
      return null;
    }

    return text;
  }
}
