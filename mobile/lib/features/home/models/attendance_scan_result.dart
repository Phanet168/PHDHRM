import 'attendance_day_record.dart';

/// Khmer reasons for rejections not tied to the scan screen itself (e.g. an
/// offline scan refused when it was finally sent); null for unknown codes.
String? scanFailureReason(String? errorCode) => switch (errorCode) {
  'out_of_range' => 'អ្នកនៅក្រៅតំបន់ស្កេនវត្តមាន',
  'invalid_qr' => 'កូដ QR មិនត្រឹមត្រូវ ឬផុតកំណត់',
  'wrong_workplace' => 'សូមប្រើកូដ QR របស់អង្គភាពអ្នក',
  'offline_scan_expired' => 'ការស្កេនក្រៅបណ្ដាញ បញ្ជូនយឺតជាង ២៤ ម៉ោង',
  'offline_time_invalid' => 'ម៉ោងក្នុងទូរសព្ទមិនត្រឹមត្រូវ',
  'geofence_not_configured' => 'អង្គភាពមិនទាន់កំណត់ទីតាំងស្កេនវត្តមាន',
  _ => null,
};

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
    this.workDate,
    this.duplicate = false,
    this.attendance,
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
  final String? workDate;
  final bool duplicate;
  final AttendanceDayRecord? attendance;

  bool get isSuccess => status.toLowerCase() == 'ok';

  /// Saved on the phone without internet; sent once it is back online.
  bool get isQueued => status == queuedStatus;

  static const queuedStatus = 'queued';

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
      workDate: _toStringOrNull(payload['work_date']),
      duplicate: payload['duplicate'] == true,
      attendance:
          payload['data'] is Map<String, dynamic>
              ? AttendanceDayRecord.fromApi(
                payload['data'] as Map<String, dynamic>,
              )
              : null,
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
