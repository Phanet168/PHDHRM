import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../auth/models/auth_user.dart';
import '../../../core/theme/app_design_system.dart';
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

class _AttendanceScanPageState extends State<AttendanceScanPage>
    with WidgetsBindingObserver {
  late final MobileScannerController _scannerController;
  bool _isSubmitting = false;
  bool _torchEnabled = false;
  String _statusMessage = '';
  late Color _statusColor;

  Color _dp() => AppDesignSystem.colorForWeekday(DateTime.now().weekday);

  @override
  void initState() {
    super.initState();
    _statusColor = _dp();
    WidgetsBinding.instance.addObserver(this);
    _statusMessage = _tr('qr_scan', 'ស្កេនកូដ QR');
    _scannerController = MobileScannerController(
      detectionSpeed: DetectionSpeed.unrestricted,
      detectionTimeoutMs: 300,
      facing: CameraFacing.back,
      torchEnabled: false,
    );

    // Some Android devices don't auto-start scanner stream reliably.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _scannerController.start();
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!mounted) {
      return;
    }

    if (state == AppLifecycleState.resumed && !_isSubmitting) {
      _scannerController.start();
    }

    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused) {
      _scannerController.stop();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
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
    if (_isSubmitting) {
      return;
    }

    String? rawValue;
    for (final barcode in capture.barcodes) {
      final value = _barcodeText(barcode);
      if (value != null && value.isNotEmpty) {
        rawValue = value;
        break;
      }
    }

    if (rawValue == null) {
      return;
    }

    setState(() {
      _statusMessage = _tr('qr_detected', 'បានរកឃើញ QR កំពុងបញ្ជូនវត្តមាន...');
      _statusColor = _dp();
    });

    await _submitAttendance(rawValue);
  }

  String? _barcodeText(Barcode barcode) {
    final fromRaw = barcode.rawValue?.trim();
    if (fromRaw != null && fromRaw.isNotEmpty) {
      return fromRaw;
    }

    final fromDisplay = barcode.displayValue?.trim();
    if (fromDisplay != null && fromDisplay.isNotEmpty) {
      return fromDisplay;
    }

    final bytes = barcode.rawBytes;
    if (bytes != null && bytes.isNotEmpty) {
      final decoded = utf8.decode(bytes, allowMalformed: true).trim();
      if (decoded.isNotEmpty) {
        return decoded;
      }
    }

    return null;
  }

  Future<void> _submitAttendance(String rawValue) async {
    final qrToken = _extractQrToken(rawValue);
    if (qrToken == null || qrToken.isEmpty) {
      final invalidQrMessage = _tr('invalid_qr', 'ទិន្នន័យ QR មិនត្រឹមត្រូវ');
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

    setState(() {
      _isSubmitting = true;
      _statusMessage = _tr(
        'checking_attendance',
        'កំពុងពិនិត្យទីតាំង និងរក្សាទុកវត្តមាន...',
      );
      _statusColor = _dp();
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

    final normalized =
        text
            .replaceAll('\n', '&')
            .replaceAll('\r', '&')
            .replaceAll(';', '&')
            .trim();

    if (normalized.startsWith('{')) {
      try {
        final decoded = jsonDecode(normalized);
        if (decoded is Map<String, dynamic>) {
          final token = decoded['qr_token']?.toString().trim();
          if (token != null && token.isNotEmpty) {
            return token;
          }

          final altToken = decoded['token']?.toString().trim();
          if (altToken != null && altToken.isNotEmpty) {
            return altToken;
          }
        }
      } catch (_) {
        // Keep fallback parsing below.
      }
    }

    final uri = Uri.tryParse(normalized);
    if (uri != null) {
      final queryToken = uri.queryParameters['qr_token']?.trim();
      if (queryToken != null && queryToken.isNotEmpty) {
        return queryToken;
      }

      final altQueryToken = uri.queryParameters['token']?.trim();
      if (altQueryToken != null && altQueryToken.isNotEmpty) {
        return altQueryToken;
      }
    }

    final fromRegex = RegExp(
      r'(?:qr_token|token)=([^&\s]+)',
    ).firstMatch(normalized);
    if (fromRegex != null) {
      return Uri.decodeQueryComponent(fromRegex.group(1)!);
    }

    return normalized;
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
      _statusMessage = _tr('qr_scan', 'ស្កេនកូដ QR');
      _statusColor = _dp();
    });
    await _scannerController.start();
  }

  String _normalizeErrorMessage(Object error) {
    final text = error.toString().replaceFirst('Exception: ', '').trim();
    if (text == 'ERR_NO_GPS_SERVICE') {
      return _tr('location_service_disabled', 'សេវាទីតាំងត្រូវបានបិទ');
    }
    if (text == 'ERR_NO_GPS_PERMISSION') {
      return _tr('location_permission_denied', 'មិនបានអនុញ្ញាតប្រើទីតាំង');
    }
    if (text == 'ERR_NO_GPS_PERMISSION_PERMANENT') {
      return _tr(
        'location_permission_denied_permanent',
        'មិនបានអនុញ្ញាតប្រើទីតាំងជាអចិន្ត្រៃយ៍',
      );
    }

    if (text.isEmpty) {
      return _tr('unexpected_error', 'កំហុសដែលមិននឹកស្មាន');
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
        title: Text(_tr('qr_scan', 'ស្កេនកូដ QR')),
        actions: [
          IconButton(
            onPressed: _isSubmitting ? null : _openManualTokenDialog,
            tooltip: _tr('manual_token', 'បញ្ចូលដោយដៃ'),
            icon: const Icon(Icons.keyboard_alt_outlined),
          ),
          IconButton(
            onPressed: _toggleTorch,
            tooltip: _tr('flashlight', 'ភ្លើងពិល'),
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
                  color: _dp().withAlpha(28),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: _dp().withAlpha(60)),
                ),
                child: Text(
                  _tr(
                    'scan_qr_instruction',
                    'ដាក់កូដ QR របស់អង្គភាពនៅក្នុងស៊ុម ហើយរង់ចាំការបញ្ជូនដោយស្វ័យប្រវត្តិ',
                  ),
                  style: TextStyle(color: _dp(), fontWeight: FontWeight.w600),
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
                                    'មិនអាចប្រើកាមេរ៉ា',
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
                label: Text(_tr('scan_again', 'ស្កេនម្ដងទៀត')),
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
          title: Text(_tr('manual_qr_token', 'បញ្ចូល QR Token ដោយដៃ')),
          content: TextField(
            controller: controller,
            maxLines: 3,
            minLines: 1,
            decoration: InputDecoration(
              hintText: _tr(
                'manual_qr_hint',
                'ចម្លង qr_token ឬអត្ថបទ QR មកបញ្ចូលទីនេះ',
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: Text(_tr('cancel', 'បោះបង់')),
            ),
            FilledButton(
              onPressed:
                  () => Navigator.of(context).pop(controller.text.trim()),
              child: Text(_tr('submit', 'បញ្ជូន')),
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
