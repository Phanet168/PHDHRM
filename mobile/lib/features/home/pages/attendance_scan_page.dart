import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../auth/models/auth_user.dart';
import '../models/attendance_scan_result.dart';
import 'attendance_scan_result_page.dart';
import '../services/home_attendance_service.dart';

class AttendanceScanPage extends StatefulWidget {
  const AttendanceScanPage({
    super.key,
    required this.user,
    required this.attendanceService,
    required this.language,
  });

  final AuthUser user;
  final HomeAttendanceService attendanceService;
  final Map<String, String> language;

  @override
  State<AttendanceScanPage> createState() => _AttendanceScanPageState();
}

class _AttendanceScanPageState extends State<AttendanceScanPage> {
  late final MobileScannerController _scannerController;
  bool _isSubmitting = false;
  bool _isPreviewing = false;
  bool _torchEnabled = false;
  String _statusMessage = '';
  Color _statusColor = const Color(0xFF0B6B58);

  @override
  void initState() {
    super.initState();
    _statusMessage = _tr('qr_scan', 'Scan QR attendance code');
    _scannerController = MobileScannerController(
      detectionSpeed: DetectionSpeed.noDuplicates,
      facing: CameraFacing.back,
      formats: const <BarcodeFormat>[BarcodeFormat.qrCode],
      torchEnabled: false,
    );
  }

  @override
  void dispose() {
    _scannerController.dispose();
    super.dispose();
  }

  String _tr(String key, String fallback) {
    final value = widget.language[key]?.trim();
    if (value == null || value.isEmpty) {
      return fallback;
    }

    return value;
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_isSubmitting || _isPreviewing) {
      return;
    }

    String? rawValue;
    for (final barcode in capture.barcodes) {
      final value = barcode.rawValue?.trim();
      if (value != null && value.isNotEmpty) {
        rawValue = value;
        break;
      }
    }

    if (rawValue == null) {
      return;
    }

    await _submitAttendance(rawValue);
  }

  Future<void> _submitAttendance(String rawValue) async {
    final qrToken = _extractQrToken(rawValue);
    if (qrToken == null || qrToken.isEmpty) {
      final invalidQrMessage = _tr('invalid_qr', 'Invalid QR data');
      await widget.attendanceService.reportScanIssue(
        widget.user,
        status: 'client_error',
        errorCode: 'invalid_qr_data',
        message: invalidQrMessage,
      );
      await _openScanResultScreen(
        AttendanceScanResult(
          status: 'error',
          message: invalidQrMessage,
          errorCode: 'invalid_qr_data',
        ),
      );
      return;
    }

    final shouldSubmit = await _confirmPunchPreview();
    if (!shouldSubmit || !mounted) {
      return;
    }

    setState(() {
      _isSubmitting = true;
      _statusMessage = _tr(
        'checking_attendance',
        'Checking location and saving attendance...',
      );
      _statusColor = const Color(0xFF0B6B58);
    });

    await _scannerController.stop();

    try {
      final position = await _resolveCurrentPosition();
      final result = await widget.attendanceService.submitAttendanceScan(
        widget.user,
        qrToken: qrToken,
        latitude: position.latitude,
        longitude: position.longitude,
      );

      if (!mounted) {
        return;
      }

      await _handleScanResult(
        result,
        qrToken: qrToken,
        latitude: position.latitude,
        longitude: position.longitude,
      );
    } catch (error) {
      if (!mounted) {
        return;
      }

      final internalErrorCode =
          error.toString().replaceFirst('Exception: ', '').trim();
      final errorMessage = _normalizeErrorMessage(error);
      await widget.attendanceService.reportScanIssue(
        widget.user,
        status: 'client_error',
        errorCode: _clientErrorCode(internalErrorCode),
        message: errorMessage,
        qrToken: qrToken,
      );

      await _openScanResultScreen(
        AttendanceScanResult(
          status: 'error',
          message: errorMessage,
          errorCode: _clientErrorCode(internalErrorCode),
        ),
      );
    }
  }

  Future<void> _handleScanResult(
    AttendanceScanResult result, {
    required String qrToken,
    required double latitude,
    required double longitude,
  }) async {
    if (!mounted) {
      return;
    }

    if (result.isSuccess) {
      await _openScanResultScreen(
        result,
        latitude: latitude,
        longitude: longitude,
      );
      return;
    }

    await widget.attendanceService.reportScanIssue(
      widget.user,
      status: 'error',
      errorCode: result.errorCode ?? 'scan_rejected',
      message: result.message,
      qrToken: qrToken,
      latitude: latitude,
      longitude: longitude,
      workplaceId: result.workplaceId,
      rangeMeters: result.rangeMeters,
      acceptableRangeMeters: result.acceptableRangeMeters,
      geofenceSource: result.geofenceSource,
    );

    await _openScanResultScreen(
      result,
      latitude: latitude,
      longitude: longitude,
    );
  }

  Future<Position> _resolveCurrentPosition() async {
    final isEnabled = await Geolocator.isLocationServiceEnabled();
    if (!isEnabled) {
      throw Exception('ERR_NO_GPS_SERVICE');
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.denied) {
      throw Exception('ERR_NO_GPS_PERMISSION');
    }

    if (permission == LocationPermission.deniedForever) {
      throw Exception('ERR_NO_GPS_PERMISSION_PERMANENT');
    }

    return Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        timeLimit: Duration(seconds: 12),
      ),
    );
  }

  String? _extractQrToken(String rawValue) {
    final text = rawValue.trim();
    if (text.isEmpty) {
      return null;
    }

    if (text.startsWith('{')) {
      try {
        final decoded = jsonDecode(text);
        if (decoded is Map<String, dynamic>) {
          final token = decoded['qr_token']?.toString().trim();
          if (token != null && token.isNotEmpty) {
            return token;
          }
        }
      } catch (_) {
        // Keep fallback parsing below.
      }
    }

    final uri = Uri.tryParse(text);
    if (uri != null) {
      final queryToken = uri.queryParameters['qr_token']?.trim();
      if (queryToken != null && queryToken.isNotEmpty) {
        return queryToken;
      }
    }

    final fromRegex = RegExp(r'qr_token=([^&\s]+)').firstMatch(text);
    if (fromRegex != null) {
      return Uri.decodeQueryComponent(fromRegex.group(1)!);
    }

    return text;
  }

  Future<void> _toggleTorch() async {
    await _scannerController.toggleTorch();
    setState(() {
      _torchEnabled = !_torchEnabled;
    });
  }

  Future<void> _openScanResultScreen(
    AttendanceScanResult result, {
    double? latitude,
    double? longitude,
  }) async {
    if (!mounted) {
      return;
    }

    await Navigator.of(context).pushReplacement<bool, bool>(
      MaterialPageRoute<bool>(
        builder:
            (_) => AttendanceScanResultPage(
              result: result,
              language: widget.language,
              scannedAt: DateTime.now(),
              latitude: latitude,
              longitude: longitude,
            ),
      ),
    );
  }

  Future<void> _restartScan() async {
    setState(() {
      _isSubmitting = false;
      _statusMessage = _tr('qr_scan', 'Scan QR attendance code');
      _statusColor = const Color(0xFF0B6B58);
    });
    await _scannerController.start();
  }

  String _normalizeErrorMessage(Object error) {
    final text = error.toString().replaceFirst('Exception: ', '').trim();
    if (text == 'ERR_NO_GPS_SERVICE') {
      return _tr('location_service_disabled', 'Location service is disabled');
    }
    if (text == 'ERR_NO_GPS_PERMISSION') {
      return _tr('location_permission_denied', 'Location permission denied');
    }
    if (text == 'ERR_NO_GPS_PERMISSION_PERMANENT') {
      return _tr(
        'location_permission_denied_permanent',
        'Location permission permanently denied',
      );
    }

    if (text.isEmpty) {
      return _tr('unexpected_error', 'Unexpected error');
    }

    return text;
  }

  String _clientErrorCode(String message) {
    final normalized = message.toLowerCase();
    if (normalized.contains('err_no_gps_service')) {
      return 'no_gps_service';
    }
    if (normalized.contains('err_no_gps_permission_permanent')) {
      return 'no_gps_permission_permanent';
    }
    if (normalized.contains('err_no_gps_permission')) {
      return 'no_gps_permission';
    }
    if (normalized.contains('location service')) {
      return 'no_gps_service';
    }
    if (normalized.contains('permission permanently denied')) {
      return 'no_gps_permission_permanent';
    }
    if (normalized.contains('permission denied')) {
      return 'no_gps_permission';
    }
    if (normalized.contains('timed out') || normalized.contains('timeout')) {
      return 'gps_timeout';
    }

    return 'client_scan_error';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_tr('qr_scan', 'Scan Attendance')),
        actions: [
          IconButton(
            onPressed: _isSubmitting ? null : _openManualTokenDialog,
            tooltip: _tr('manual_token', 'Manual token'),
            icon: const Icon(Icons.keyboard_alt_outlined),
          ),
          IconButton(
            onPressed: _toggleTorch,
            tooltip: _tr('flashlight', 'Torch'),
            icon: Icon(
              _torchEnabled
                  ? Icons.flash_on_outlined
                  : Icons.flash_off_outlined,
            ),
          ),
        ],
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFE9F4F1),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFCDE4DB)),
                ),
                child: Text(
                  _tr(
                    'scan_qr_instruction',
                    'Align the unit QR code inside the frame, then wait for auto submit.',
                  ),
                  style: const TextStyle(
                    color: Color(0xFF173F35),
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      MobileScanner(
                        controller: _scannerController,
                        onDetect: _onDetect,
                        errorBuilder: (context, error, child) {
                          return Container(
                            color: Colors.black,
                            alignment: Alignment.center,
                            child: Text(
                              error.errorDetails?.message ??
                                  _tr(
                                    'camera_access_failed',
                                    'Unable to access camera',
                                  ),
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          );
                        },
                      ),
                      IgnorePointer(
                        child: Center(
                          child: Container(
                            width: 240,
                            height: 240,
                            decoration: BoxDecoration(
                              border: Border.all(
                                color: Colors.white.withAlpha(220),
                                width: 2,
                              ),
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                        ),
                      ),
                      if (_isSubmitting)
                        Container(
                          color: Colors.black.withAlpha(90),
                          child: const Center(
                            child: CircularProgressIndicator(),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Text(
                _statusMessage,
                style: TextStyle(
                  color: _statusColor,
                  fontWeight: FontWeight.w700,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: _isSubmitting ? null : _restartScan,
                icon: const Icon(Icons.qr_code_scanner_outlined),
                label: Text(_tr('scan_again', 'Scan Again')),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _openManualTokenDialog() async {
    final controller = TextEditingController();

    final token = await showDialog<String>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: Text(_tr('manual_qr_token', 'Manual QR token')),
          content: TextField(
            controller: controller,
            maxLines: 3,
            minLines: 1,
            decoration: InputDecoration(
              hintText: _tr(
                'manual_qr_hint',
                'Paste qr_token or QR payload text',
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: Text(_tr('cancel', 'Cancel')),
            ),
            FilledButton(
              onPressed:
                  () => Navigator.of(context).pop(controller.text.trim()),
              child: Text(_tr('submit', 'Submit')),
            ),
          ],
        );
      },
    );

    if (!mounted || token == null || token.isEmpty) {
      return;
    }

    await _submitAttendance(token);
  }

  Future<bool> _confirmPunchPreview() async {
    setState(() {
      _isPreviewing = true;
    });

    try {
      final punchType = await widget.attendanceService.predictNextPunchType(
        widget.user,
      );
      if (!mounted) {
        return false;
      }

      final normalized = punchType.trim().toLowerCase();
      final isIn = normalized == 'in';
      final label = isIn ? _tr('time_in', 'IN') : _tr('time_out', 'OUT');

      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) {
          return AlertDialog(
            title: Text(_tr('punch_preview', 'Punch Preview')),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _tr(
                    'punch_preview_desc',
                    'The next attendance action is estimated as:',
                  ),
                ),
                const SizedBox(height: 10),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color:
                        isIn
                            ? const Color(0xFFE9F4F1)
                            : const Color(0xFFFFF1E5),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(
                      color:
                          isIn
                              ? const Color(0xFFCDE4DB)
                              : const Color(0xFFF0D8B6),
                    ),
                  ),
                  child: Text(
                    label,
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      color:
                          isIn
                              ? const Color(0xFF0B6B58)
                              : const Color(0xFFA85C00),
                    ),
                  ),
                ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(false),
                child: Text(_tr('cancel', 'Cancel')),
              ),
              FilledButton(
                onPressed: () => Navigator.of(context).pop(true),
                child: Text(_tr('confirm', 'Confirm')),
              ),
            ],
          );
        },
      );

      return confirmed == true;
    } finally {
      if (mounted) {
        setState(() {
          _isPreviewing = false;
        });
      }
    }
  }
}
