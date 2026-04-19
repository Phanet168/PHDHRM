import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../auth/models/auth_user.dart';
import '../models/attendance_scan_result.dart';
import '../services/home_attendance_service.dart';

class AttendanceScanPage extends StatefulWidget {
  const AttendanceScanPage({
    super.key,
    required this.user,
    required this.attendanceService,
  });

  final AuthUser user;
  final HomeAttendanceService attendanceService;

  @override
  State<AttendanceScanPage> createState() => _AttendanceScanPageState();
}

class _AttendanceScanPageState extends State<AttendanceScanPage> {
  late final MobileScannerController _scannerController;
  bool _isSubmitting = false;
  bool _torchEnabled = false;
  bool _scanSucceeded = false;
  String _statusMessage = 'Scan QR attendance code';
  Color _statusColor = const Color(0xFF0B6B58);
  AttendanceScanResult? _latestResult;

  @override
  void initState() {
    super.initState();
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

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_isSubmitting) {
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
      await widget.attendanceService.reportScanIssue(
        widget.user,
        status: 'client_error',
        errorCode: 'invalid_qr_data',
        message: 'Invalid QR data',
      );
      setState(() {
        _scanSucceeded = false;
        _statusMessage = 'Invalid QR data';
        _statusColor = const Color(0xFFD34B5F);
      });
      return;
    }

    setState(() {
      _isSubmitting = true;
      _scanSucceeded = false;
      _latestResult = null;
      _statusMessage = 'Checking location and saving attendance...';
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

      final errorMessage = _normalizeErrorMessage(error);
      await widget.attendanceService.reportScanIssue(
        widget.user,
        status: 'client_error',
        errorCode: _clientErrorCode(errorMessage),
        message: errorMessage,
        qrToken: qrToken,
      );

      setState(() {
        _isSubmitting = false;
        _scanSucceeded = false;
        _statusMessage = errorMessage;
        _statusColor = const Color(0xFFD34B5F);
      });
      await _scannerController.start();
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
      setState(() {
        _isSubmitting = false;
        _scanSucceeded = true;
        _latestResult = result;
        _statusMessage = result.message;
        _statusColor = const Color(0xFF0B6B58);
      });
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

    setState(() {
      _isSubmitting = false;
      _scanSucceeded = false;
      _latestResult = result;
      _statusMessage = result.message;
      _statusColor = const Color(0xFFD34B5F);
    });
    await _scannerController.start();
  }

  Future<Position> _resolveCurrentPosition() async {
    final isEnabled = await Geolocator.isLocationServiceEnabled();
    if (!isEnabled) {
      throw Exception('Location service is disabled');
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.denied) {
      throw Exception('Location permission denied');
    }

    if (permission == LocationPermission.deniedForever) {
      throw Exception('Location permission permanently denied');
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

  Future<void> _restartScan() async {
    setState(() {
      _isSubmitting = false;
      _scanSucceeded = false;
      _latestResult = null;
      _statusMessage = 'Scan QR attendance code';
      _statusColor = const Color(0xFF0B6B58);
    });
    await _scannerController.start();
  }

  String _normalizeErrorMessage(Object error) {
    final text = error.toString().replaceFirst('Exception: ', '').trim();
    if (text.isEmpty) {
      return 'Unexpected error';
    }

    return text;
  }

  String _clientErrorCode(String message) {
    final normalized = message.toLowerCase();
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

  String _formatMeters(double? meters) {
    if (meters == null) {
      return '-';
    }

    return '${meters.toStringAsFixed(1)} m';
  }

  Widget _buildResultCard(AttendanceScanResult result) {
    final successColor = const Color(0xFF0B6B58);
    final dangerColor = const Color(0xFFD34B5F);
    final isSuccess = result.isSuccess;

    return Container(
      margin: const EdgeInsets.only(top: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: isSuccess ? const Color(0xFFE9F4F1) : const Color(0xFFFFEEF1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: isSuccess ? const Color(0xFFCDE4DB) : const Color(0xFFF0CED5),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                isSuccess ? Icons.check_circle_outline : Icons.error_outline,
                color: isSuccess ? successColor : dangerColor,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  result.message,
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    color: isSuccess ? successColor : dangerColor,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _MetricPill(
                icon: Icons.apartment_outlined,
                label: 'Workplace',
                value: result.workplaceName ?? '-',
              ),
              _MetricPill(
                icon: Icons.straighten_outlined,
                label: 'Distance',
                value: _formatMeters(result.rangeMeters),
              ),
              _MetricPill(
                icon: Icons.rule_outlined,
                label: 'Allowed',
                value: _formatMeters(result.acceptableRangeMeters),
              ),
            ],
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Scan Attendance'),
        actions: [
          IconButton(
            onPressed: _isSubmitting ? null : _openManualTokenDialog,
            tooltip: 'Manual token',
            icon: const Icon(Icons.keyboard_alt_outlined),
          ),
          IconButton(
            onPressed: _toggleTorch,
            tooltip: 'Torch',
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
                child: const Text(
                  'Align the unit QR code inside the frame, then wait for auto submit.',
                  style: TextStyle(
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
                                  'Unable to access camera',
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
              if (_latestResult != null) _buildResultCard(_latestResult!),
              const SizedBox(height: 10),
              if (_scanSucceeded)
                FilledButton.icon(
                  onPressed: () => Navigator.of(context).pop(true),
                  icon: const Icon(Icons.done_all_outlined),
                  label: const Text('Done'),
                ),
              if (_scanSucceeded) const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: _isSubmitting ? null : _restartScan,
                icon: const Icon(Icons.qr_code_scanner_outlined),
                label: const Text('Scan Again'),
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
          title: const Text('Manual QR token'),
          content: TextField(
            controller: controller,
            maxLines: 3,
            minLines: 1,
            decoration: const InputDecoration(
              hintText: 'Paste qr_token or QR payload text',
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed:
                  () => Navigator.of(context).pop(controller.text.trim()),
              child: const Text('Submit'),
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
}

class _MetricPill extends StatelessWidget {
  const _MetricPill({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withAlpha(200),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFD8E2DF)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: const Color(0xFF0B6B58)),
          const SizedBox(width: 6),
          Text(
            '$label: $value',
            style: const TextStyle(
              color: Color(0xFF24332E),
              fontWeight: FontWeight.w700,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}
