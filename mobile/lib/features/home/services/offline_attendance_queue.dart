import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/network/api_exception.dart';
import '../../auth/models/auth_user.dart';
import '../models/attendance_scan_result.dart';
import 'home_attendance_service.dart';

/// An attendance scan taken without internet, waiting to be sent.
class PendingScan {
  const PendingScan({
    required this.requestId,
    required this.userId,
    required this.latitude,
    required this.longitude,
    required this.capturedAt,
    required this.clockSource,
    required this.scanType,
    this.qrToken,
    this.rejectedCode,
    this.rejectedMessage,
  });

  /// Also the server's idempotency key: resending never makes a second punch.
  final String requestId;
  final int userId;
  final double latitude;
  final double longitude;
  final DateTime capturedAt;

  /// `server_anchored` or `device` (see TrustedClock).
  final String clockSource;

  /// `GPS` or `QR`, for display.
  final String scanType;
  final String? qrToken;

  /// Set when the server refused it (outside the area, too late …).
  final String? rejectedCode;
  final String? rejectedMessage;

  bool get isRejected => rejectedCode != null || rejectedMessage != null;

  String get rejectionReason =>
      scanFailureReason(rejectedCode) ?? rejectedMessage ?? '';

  PendingScan rejected(String? code, String message) => PendingScan(
    requestId: requestId,
    userId: userId,
    latitude: latitude,
    longitude: longitude,
    capturedAt: capturedAt,
    clockSource: clockSource,
    scanType: scanType,
    qrToken: qrToken,
    rejectedCode: code ?? 'rejected',
    rejectedMessage: message,
  );

  factory PendingScan.fromJson(Map<String, dynamic> json) => PendingScan(
    requestId: json['request_id'] as String,
    userId: (json['user_id'] as num).toInt(),
    latitude: (json['latitude'] as num).toDouble(),
    longitude: (json['longitude'] as num).toDouble(),
    capturedAt: DateTime.parse(json['captured_at'] as String),
    clockSource: json['clock_source'] as String? ?? 'device',
    scanType: json['scan_type'] as String? ?? 'GPS',
    qrToken: json['qr_token'] as String?,
    rejectedCode: json['rejected_code'] as String?,
    rejectedMessage: json['rejected_message'] as String?,
  );

  Map<String, dynamic> toJson() => {
    'request_id': requestId,
    'user_id': userId,
    'latitude': latitude,
    'longitude': longitude,
    'captured_at': capturedAt.toUtc().toIso8601String(),
    'clock_source': clockSource,
    'scan_type': scanType,
    if (qrToken != null) 'qr_token': qrToken,
    if (rejectedCode != null) 'rejected_code': rejectedCode,
    if (rejectedMessage != null) 'rejected_message': rejectedMessage,
  };
}

/// What one [OfflineAttendanceQueue.sync] pass did.
class OfflineSyncReport {
  const OfflineSyncReport({
    this.sent = 0,
    this.rejected = 0,
    this.stillOffline = false,
  });

  final int sent;
  final int rejected;

  /// Stopped because the server was unreachable; the rest stay queued.
  final bool stillOffline;
}

/// Scans saved while offline, kept on the phone and sent oldest-first once
/// the server is reachable — before any new scan, so IN/OUT stay in order.
/// Each scan keeps its own request id, so a resend never duplicates a punch.
class OfflineAttendanceQueue {
  OfflineAttendanceQueue._();

  static final OfflineAttendanceQueue instance = OfflineAttendanceQueue._();

  static const _storageKey = 'offline_attendance_queue_v1';

  /// Every queued scan (all accounts on this phone), oldest first.
  final ValueNotifier<List<PendingScan>> items = ValueNotifier(const []);

  bool _loaded = false;
  Future<OfflineSyncReport>? _running;

  /// Forget the in-memory state so the next [load] re-reads storage.
  @visibleForTesting
  void debugReset() {
    _loaded = false;
    _running = null;
    items.value = const [];
  }

  Future<void> load() async {
    if (_loaded) return;
    _loaded = true;
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_storageKey);
      if (raw == null) return;
      items.value = List.unmodifiable(
        (jsonDecode(raw) as List).map(
          (e) => PendingScan.fromJson(Map<String, dynamic>.from(e)),
        ),
      );
    } catch (_) {
      items.value = const [];
    }
  }

  List<PendingScan> forUser(int userId) =>
      items.value.where((scan) => scan.userId == userId).toList();

  Future<void> add(PendingScan scan) async {
    await load();
    await _save([...items.value, scan]);
  }

  Future<void> remove(String requestId) async {
    await load();
    await _save(items.value.where((s) => s.requestId != requestId).toList());
  }

  Future<void> _save(List<PendingScan> next) async {
    items.value = List.unmodifiable(next);
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
        _storageKey,
        jsonEncode(next.map((s) => s.toJson()).toList()),
      );
    } catch (_) {
      // Kept in memory; retried on the next change.
    }
  }

  /// Sends [user]'s waiting scans in order. Stops at the first unreachable-
  /// server error (still offline); a scan the server refuses is kept, marked
  /// rejected, so the officer can see why. Concurrent calls share one run.
  Future<OfflineSyncReport> sync(AuthUser user, HomeAttendanceService service) {
    return _running ??= _sync(user, service).whenComplete(() {
      _running = null;
    });
  }

  Future<OfflineSyncReport> _sync(
    AuthUser user,
    HomeAttendanceService service,
  ) async {
    await load();
    var sent = 0;
    var rejected = 0;
    for (final scan in forUser(user.userId)) {
      if (scan.isRejected) continue;
      final AttendanceScanResult result;
      try {
        result = await service.submitQueuedScan(user, scan);
      } on ApiException catch (error) {
        final status = error.statusCode ?? 0;
        if (status == 403 || status == 422) {
          final response = error.body?['response'];
          await _replace(
            scan.rejected(
              response is Map ? response['error_code']?.toString() : null,
              error.message,
            ),
          );
          rejected++;
          continue;
        }
        // Offline, signed out or a server error: try again later, in order.
        return OfflineSyncReport(
          sent: sent,
          rejected: rejected,
          stillOffline: true,
        );
      } catch (_) {
        return OfflineSyncReport(
          sent: sent,
          rejected: rejected,
          stillOffline: true,
        );
      }
      if (result.isSuccess) {
        await remove(scan.requestId);
        sent++;
      } else {
        await _replace(scan.rejected(result.errorCode, result.message));
        rejected++;
      }
    }
    return OfflineSyncReport(sent: sent, rejected: rejected);
  }

  Future<void> _replace(PendingScan scan) => _save([
    for (final s in items.value) s.requestId == scan.requestId ? scan : s,
  ]);
}
