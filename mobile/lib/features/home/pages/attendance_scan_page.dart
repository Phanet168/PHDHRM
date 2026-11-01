import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../auth/models/auth_user.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/connection_status.dart';
import '../../../core/theme/app_design_system.dart';
import '../../../shared/widgets/connection_banner.dart';
import '../../../core/time/trusted_clock.dart';
import '../models/attendance_geofence.dart';
import '../models/attendance_scan_result.dart';
import 'attendance_scan_result_page.dart';
import 'scan/gps_zone_radar.dart';
import 'scan/qr_scan_overlay.dart';
import '../services/home_attendance_service.dart';
import '../services/offline_attendance_queue.dart';

enum _ScanMode { qr, gps }

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
  _ScanMode _mode = _ScanMode.qr;
  bool _isSubmitting = false;
  bool _torchEnabled = false;
  String _statusMessage = '';
  late Color _statusColor;

  Position? _currentPosition;
  bool _loadingPosition = false;
  String? _positionError;
  StreamSubscription<Position>? _positionSub;

  // The server-enforced scan area, for the live in/out-of-zone indicator.
  AttendanceGeofence? _geofence;
  bool _scanContextLoaded = false;
  String? _nextPunchType;

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
      // QR scans are geofenced too, so both tabs show the live zone status.
      _loadScanContext();
      _startLiveLocation();
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!mounted) {
      return;
    }

    if (state == AppLifecycleState.resumed && !_isSubmitting) {
      if (_mode == _ScanMode.qr) {
        _scannerController.start();
      }
      if (_positionSub == null) {
        _startLiveLocation();
      }
    }

    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused) {
      _scannerController.stop();
      _stopLiveLocation();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _stopLiveLocation();
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

  Future<void> _setMode(_ScanMode mode) async {
    if (_mode == mode) {
      return;
    }

    setState(() => _mode = mode);

    if (mode == _ScanMode.qr) {
      await _scannerController.start();
    } else {
      await _scannerController.stop();
      _loadScanContext();
      if (_positionSub == null) {
        await _startLiveLocation();
      }
    }
  }

  /// One fresh fix (which also asks for permission), then a live stream so
  /// the zone indicator follows the officer while the scan page is open.
  Future<void> _startLiveLocation() async {
    _stopLiveLocation();
    await _loadCurrentPosition();
    if (!mounted || _positionError != null) {
      return;
    }
    _positionSub = Geolocator.getPositionStream(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 2,
      ),
    ).listen(
      (position) {
        if (!mounted) return;
        setState(() {
          _currentPosition = position;
          _positionError = null;
        });
      },
      onError: (Object error) {
        if (!mounted) return;
        setState(() => _positionError = _normalizeErrorMessage(error));
      },
    );
  }

  void _stopLiveLocation() {
    _positionSub?.cancel();
    _positionSub = null;
  }

  Future<void> _loadScanContext() async {
    try {
      final scanContext = await widget.attendanceService.fetchScanContext(
        widget.user,
      );
      if (!mounted) return;
      setState(() {
        _geofence = scanContext.geofence;
        _nextPunchType = scanContext.nextPunchType;
        _scanContextLoaded = true;
      });
    } catch (_) {
      // Without the zone the server still decides at scan time; the
      // indicator just can't say in/out.
      if (mounted) setState(() => _scanContextLoaded = true);
    }
  }

  GpsZoneStatus get _zoneStatus {
    final position = _currentPosition;
    final fence = _geofence;
    if (position == null || (fence == null && !_scanContextLoaded)) {
      return GpsZoneStatus.locating;
    }
    if (fence == null) {
      return GpsZoneStatus.unconfigured;
    }
    return fence.distanceTo(position.latitude, position.longitude) <=
            fence.radiusMeters
        ? GpsZoneStatus.inside
        : GpsZoneStatus.outside;
  }

  String _formatDistance(double meters) =>
      meters < 1000
          ? '${meters.round()} m'
          : '${(meters / 1000).toStringAsFixed(2)} km';

  Future<void> _loadCurrentPosition() async {
    setState(() {
      _loadingPosition = true;
      _positionError = null;
    });

    try {
      final position = await _resolveCurrentPosition();
      if (!mounted) return;
      setState(() {
        _currentPosition = position;
        _loadingPosition = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _positionError = _normalizeErrorMessage(error);
        _loadingPosition = false;
      });
    }
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

    await _submitCheckIn(qrToken: qrToken);
  }

  Future<void> _submitCheckIn({required String? qrToken}) async {
    setState(() {
      _isSubmitting = true;
      _statusMessage = _tr(
        'checking_attendance',
        'កំពុងពិនិត្យទីតាំង និងរក្សាទុកវត្តមាន...',
      );
      _statusColor = _dp();
    });

    await _scannerController.stop();

    // The moment of the scan (server-anchored when possible) and its retry
    // key: if it has to wait offline, this is what gets recorded.
    final scannedAt = await TrustedClock.instance.now();
    final requestId = widget.attendanceService.newRequestId();
    Position? position;
    try {
      position = _currentPosition ?? await _resolveCurrentPosition();
      // Earlier offline scans go first so IN/OUT stay in order; if they
      // still cannot be sent, this one waits behind them.
      if (!await _sendQueuedFirst()) {
        throw NetworkException();
      }
      final result = await widget.attendanceService
          .submitAttendanceScan(
            widget.user,
            qrToken: qrToken,
            latitude: position.latitude,
            longitude: position.longitude,
            requestId: requestId,
          )
          .timeout(const Duration(seconds: 12));

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

      if (position != null && _isOffline(error)) {
        await OfflineAttendanceQueue.instance.add(
          PendingScan(
            requestId: requestId,
            userId: widget.user.userId,
            latitude: position.latitude,
            longitude: position.longitude,
            capturedAt: scannedAt.time,
            clockSource: scannedAt.source,
            scanType: _mode == _ScanMode.qr ? 'QR' : 'GPS',
            qrToken: qrToken,
          ),
        );
        await _openScanResultScreen(
          const AttendanceScanResult(
            status: AttendanceScanResult.queuedStatus,
            message: 'បានរក្សាទុកក្រៅបណ្ដាញ',
          ),
          latitude: position.latitude,
          longitude: position.longitude,
        );
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

  /// Sends this officer's queued offline scans; false while any still wait.
  Future<bool> _sendQueuedFirst() async {
    final queue = OfflineAttendanceQueue.instance;
    await queue.load();
    bool waiting() =>
        queue.forUser(widget.user.userId).any((scan) => !scan.isRejected);
    if (!waiting()) return true;
    await queue
        .sync(widget.user, widget.attendanceService)
        .timeout(
          const Duration(seconds: 10),
          onTimeout: () => const OfflineSyncReport(stillOffline: true),
        );
    return !waiting();
  }

  static bool _isOffline(Object error) =>
      error is TimeoutException ||
      error is NetworkException ||
      (error is ApiException
          ? error.statusCode == null && isNetworkErrorMessage(error.message)
          : isNetworkErrorMessage(error.toString()));

  Future<void> _handleScanResult(
    AttendanceScanResult result, {
    required String? qrToken,
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
              scanType: _mode == _ScanMode.qr ? 'QR' : 'GPS',
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

  String _currentTimeLabel() {
    final now = DateTime.now();
    final hour24 = now.hour;
    final hour12 = hour24 % 12 == 0 ? 12 : hour24 % 12;
    final period = hour24 < 12 ? 'AM' : 'PM';
    final minute = now.minute.toString().padLeft(2, '0');
    return '${hour12.toString().padLeft(2, '0')}:$minute $period';
  }

  String _currentShiftLabel() {
    final hour = DateTime.now().hour;
    if (hour < 12) {
      return _tr('shift_morning', 'ព្រឹក');
    }
    if (hour < 18) {
      return _tr('shift_afternoon', 'ល្ងាច');
    }
    return _tr('shift_night', 'យប់');
  }

  @override
  Widget build(BuildContext context) {
    final accent = _dp();

    return Scaffold(
      backgroundColor: const Color(0xFFF5F7F6),
      appBar: AppBar(
        title: Text(
          _mode == _ScanMode.qr
              ? _tr('qr_scan_attendance', 'ស្កេន QR វត្តមាន')
              : _tr('gps_scan_attendance', 'ស្កេនវត្តមាន GPS'),
        ),
        actions: [
          IconButton(
            onPressed: _isSubmitting ? null : _openManualTokenDialog,
            tooltip: _tr('manual_token', 'បញ្ចូលដោយដៃ'),
            icon: const Icon(Icons.keyboard_alt_outlined),
          ),
          if (_mode == _ScanMode.qr)
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
              _MethodTabs(
                accent: accent,
                mode: _mode,
                qrLabel: _tr('qr_scan', 'ស្កេន QR'),
                gpsLabel: _tr('gps', 'GPS'),
                onChanged: _setMode,
              ),
              const SizedBox(height: 10),
              const _ConnectionHint(),
              const SizedBox(height: 12),
              Expanded(
                child:
                    _mode == _ScanMode.qr
                        ? _buildQrTab(accent)
                        : _buildGpsTab(accent),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Short live zone label for the pill over the camera.
  String _zoneLabel(GpsZoneStatus status, double? distance) {
    return switch (status) {
      GpsZoneStatus.inside =>
        '${_tr('gps_in_zone_short', 'នៅក្នុងតំបន់')} · ${_formatDistance(distance!)}',
      GpsZoneStatus.outside =>
        '${_tr('gps_out_of_zone_short', 'នៅក្រៅតំបន់')} · ${_formatDistance(distance!)}',
      GpsZoneStatus.locating => _tr('locating', 'កំពុងស្វែងរកទីតាំង...'),
      GpsZoneStatus.unconfigured => _tr(
        'gps_zone_unconfigured',
        'មិនទាន់កំណត់តំបន់ស្កេន',
      ),
    };
  }

  Widget _buildQrTab(Color accent) {
    final status = _zoneStatus;
    final position = _currentPosition;
    final fence = _geofence;
    final distance =
        position != null && fence != null
            ? fence.distanceTo(position.latitude, position.longitude)
            : null;

    return Column(
      children: [
        Expanded(
          child: ListView(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(24),
                child: SizedBox(
                  height: 380,
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      Container(color: const Color(0xFF0F1914)),
                      MobileScanner(
                        controller: _scannerController,
                        onDetect: _onDetect,
                        errorBuilder: (context, error, child) {
                          return Container(
                            color: const Color(0xFF0F1914),
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
                      QrScanOverlay(color: accent, scanning: !_isSubmitting),
                      Positioned(
                        top: 12,
                        left: 12,
                        right: 12,
                        child: Align(
                          alignment: Alignment.centerLeft,
                          child: GpsZonePill(
                            status: status,
                            label: _zoneLabel(status, distance),
                          ),
                        ),
                      ),
                      AnimatedSwitcher(
                        duration: const Duration(milliseconds: 250),
                        child:
                            _isSubmitting
                                ? Container(
                                  color: Colors.black.withAlpha(150),
                                  alignment: Alignment.center,
                                  child: Column(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      const CircularProgressIndicator(
                                        color: Colors.white,
                                      ),
                                      const SizedBox(height: 14),
                                      Text(
                                        _statusMessage,
                                        textAlign: TextAlign.center,
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ],
                                  ),
                                )
                                : const SizedBox.shrink(),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              AnimatedSwitcher(
                duration: const Duration(milliseconds: 250),
                child: Text(
                  _statusMessage,
                  key: ValueKey(_statusMessage),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: _statusColor,
                    fontWeight: FontWeight.w800,
                    fontSize: 15,
                  ),
                ),
              ),
              const SizedBox(height: 6),
              Text(
                _tr('scan_qr_instruction', 'សូមស្កេនកូដ QR នៅទីតាំងការិយាល័យ'),
                style: const TextStyle(color: Color(0xFF6F7C76), fontSize: 13),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: _isSubmitting ? null : _restartScan,
          style: OutlinedButton.styleFrom(
            foregroundColor: accent,
            side: BorderSide(color: accent.withAlpha(120)),
            minimumSize: const Size.fromHeight(52),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
          ),
          icon: const Icon(Icons.qr_code_scanner_rounded),
          label: Text(
            _tr('scan_again', 'ស្កេនម្ដងទៀត'),
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
          ),
        ),
      ],
    );
  }

  Widget _buildGpsTab(Color accent) {
    final org = widget.user.departmentName?.trim();
    final status = _zoneStatus;
    final color = gpsZoneColor(status);
    final position = _currentPosition;
    final fence = _geofence;
    final distance =
        position != null && fence != null
            ? fence.distanceTo(position.latitude, position.longitude)
            : null;
    final (title, subtitle) = switch (status) {
      GpsZoneStatus.inside => (
        _tr('gps_in_zone', 'អ្នកនៅក្នុងតំបន់ស្កេន'),
        _tr('gps_in_zone_hint', 'អាចស្កេនវត្តមានបាន'),
      ),
      GpsZoneStatus.outside => (
        _tr('gps_out_of_zone', 'អ្នកនៅក្រៅតំបន់ស្កេន'),
        '${_tr('gps_move_closer', 'សូមខិតចូលជិតប្រហែល')} '
            '${_formatDistance(distance! - fence!.radiusMeters)}',
      ),
      GpsZoneStatus.locating => (
        _tr('locating', 'កំពុងស្វែងរកទីតាំង...'),
        _positionError ??
            _tr('gps_locating_hint', 'សូមបើក GPS និងនៅកន្លែងមានមេឃ'),
      ),
      GpsZoneStatus.unconfigured => (
        _tr('gps_zone_unconfigured', 'មិនទាន់កំណត់តំបន់ស្កេន'),
        _tr('gps_zone_unconfigured_hint', 'ប្រព័ន្ធនឹងពិនិត្យទីតាំងពេលស្កេន'),
      ),
    };
    final buttonLabel =
        status == GpsZoneStatus.outside
            ? _tr('gps_out_of_zone', 'អ្នកនៅក្រៅតំបន់ស្កេន')
            : switch (_nextPunchType) {
              'in' => _tr('scan_in', 'ស្កេនចូល'),
              'out' => _tr('scan_out', 'ស្កេនចេញ'),
              _ => _tr('check_in', 'ចូលវត្តមាន'),
            };
    final canScan =
        !_isSubmitting && position != null && status != GpsZoneStatus.outside;
    final buttonColor = status == GpsZoneStatus.inside ? color : accent;

    // The scan button stays pinned below the scrolling zone details so it
    // is always reachable on any screen height.
    return Column(
      children: [
        Expanded(
          child: ListView(
            children: [
              Container(
                width: double.infinity,
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  boxShadow: const [
                    BoxShadow(
                      color: Color(0x0A17352B),
                      blurRadius: 10,
                      offset: Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            (org?.isNotEmpty ?? false) ? org! : '-',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Color(0xFF17352B),
                              fontWeight: FontWeight.w700,
                              fontSize: 13,
                            ),
                          ),
                        ),
                        GpsLiveBadge(
                          color: color,
                          label: _tr('gps_live', 'ផ្ទាល់'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    // Shrinks with the screen (large display size = narrow width).
                    LayoutBuilder(
                      builder:
                          (context, constraints) => GpsZoneRadar(
                            size:
                                constraints.maxWidth < 260
                                    ? constraints.maxWidth
                                    : 260,
                            status: status,
                            offsetMeters:
                                position != null && fence != null
                                    ? fence.offsetOf(
                                      position.latitude,
                                      position.longitude,
                                    )
                                    : null,
                            radiusMeters: fence?.radiusMeters,
                          ),
                    ),
                    const SizedBox(height: 8),
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 300),
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 12,
                      ),
                      decoration: BoxDecoration(
                        color: color.withAlpha(24),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: color.withAlpha(90)),
                      ),
                      child: Row(
                        children: [
                          Icon(gpsZoneIcon(status), color: color, size: 28),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  title,
                                  style: TextStyle(
                                    color: color,
                                    fontWeight: FontWeight.w800,
                                    fontSize: 15,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  subtitle,
                                  style: const TextStyle(
                                    color: Color(0xFF6F7C76),
                                    fontSize: 11,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        _gpsMetric(
                          _tr('distance', 'ចម្ងាយ'),
                          distance == null ? '-' : _formatDistance(distance),
                          color,
                        ),
                        _gpsMetric(
                          _tr('allowed_range', 'ចម្ងាយអនុញ្ញាត'),
                          fence == null
                              ? '-'
                              : _formatDistance(fence.radiusMeters),
                          const Color(0xFF17352B),
                        ),
                        _gpsMetric(
                          _tr('gps_accuracy', 'ភាពត្រឹមត្រូវ'),
                          position == null
                              ? '-'
                              : '±${position.accuracy.round()} m',
                          const Color(0xFF17352B),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            position == null
                                ? ''
                                : '${position.latitude.toStringAsFixed(5)}°N, '
                                    '${position.longitude.toStringAsFixed(5)}°E',
                            style: const TextStyle(
                              color: Color(0xFF6F7C76),
                              fontSize: 11,
                            ),
                          ),
                        ),
                        IconButton(
                          onPressed:
                              _loadingPosition ? null : _startLiveLocation,
                          tooltip: _tr(
                            'refresh_location',
                            'ធ្វើបច្ចុប្បន្នភាពទីតាំង',
                          ),
                          color: const Color(0xFF17352B),
                          icon: const Icon(Icons.my_location_rounded, size: 20),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Flexible(
                      child: FittedBox(
                        fit: BoxFit.scaleDown,
                        alignment: Alignment.centerLeft,
                        child: Text(
                          _currentTimeLabel(),
                          style: const TextStyle(
                            color: Color(0xFF17352B),
                            fontWeight: FontWeight.w800,
                            fontSize: 22,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 5,
                      ),
                      decoration: BoxDecoration(
                        color: accent.withAlpha(28),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        _currentShiftLabel(),
                        style: TextStyle(
                          color: accent,
                          fontWeight: FontWeight.w600,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: canScan ? () => _submitCheckIn(qrToken: null) : null,
          style: FilledButton.styleFrom(
            backgroundColor: buttonColor,
            disabledBackgroundColor: color.withAlpha(50),
            disabledForegroundColor: color,
            minimumSize: const Size.fromHeight(54),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
          ),
          icon:
              _isSubmitting
                  ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                  : Icon(
                    status == GpsZoneStatus.outside
                        ? Icons.block_rounded
                        : Icons.fingerprint_rounded,
                  ),
          label: Text(
            buttonLabel,
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
          ),
        ),
      ],
    );
  }

  Widget _gpsMetric(String label, String value, Color valueColor) {
    return Expanded(
      child: Column(
        children: [
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              value,
              style: TextStyle(
                color: valueColor,
                fontWeight: FontWeight.w800,
                fontSize: 16,
              ),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Color(0xFF6F7C76), fontSize: 10),
          ),
        ],
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

/// Figma "Method tabs" — a two-way segmented control switching between the
/// camera scanner and the GPS-only check-in flow.
/// Online: the scan goes to the server at once. Offline: it is kept on the
/// phone and sent when the connection returns.
class _ConnectionHint extends StatelessWidget {
  const _ConnectionHint();

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: ConnectionStatus.instance,
      builder:
          (context, _) => Row(
            children: [
              Flexible(
                child: GestureDetector(
                  onTap: () => showConnectionSheet(context),
                  child: const ConnectionPill(),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                flex: 3,
                child: Text(
                  ConnectionStatus.instance.isOffline
                      ? 'ស្កេនបាន — រក្សាទុកក្នុងទូរសព្ទ ហើយបញ្ជូនពេលអនឡាញវិញ'
                      : 'ការស្កេននឹងបញ្ជូនទៅម៉ាស៊ីនមេភ្លាមៗ',
                  style: const TextStyle(
                    fontSize: 12,
                    color: Color(0xFF64748B),
                  ),
                ),
              ),
            ],
          ),
    );
  }
}

class _MethodTabs extends StatelessWidget {
  const _MethodTabs({
    required this.accent,
    required this.mode,
    required this.qrLabel,
    required this.gpsLabel,
    required this.onChanged,
  });

  final Color accent;
  final _ScanMode mode;
  final String qrLabel;
  final String gpsLabel;
  final ValueChanged<_ScanMode> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: const Color(0xFFE7ECE9),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Expanded(
            child: _MethodTabButton(
              label: qrLabel,
              selected: mode == _ScanMode.qr,
              accent: accent,
              onTap: () => onChanged(_ScanMode.qr),
            ),
          ),
          Expanded(
            child: _MethodTabButton(
              label: gpsLabel,
              selected: mode == _ScanMode.gps,
              accent: accent,
              onTap: () => onChanged(_ScanMode.gps),
            ),
          ),
        ],
      ),
    );
  }
}

class _MethodTabButton extends StatelessWidget {
  const _MethodTabButton({
    required this.label,
    required this.selected,
    required this.accent,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final Color accent;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? accent : Colors.transparent,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 6),
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              label,
              textAlign: TextAlign.center,
              maxLines: 1,
              style: TextStyle(
                color: selected ? Colors.white : const Color(0xFF6F7C76),
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                fontSize: 14,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
