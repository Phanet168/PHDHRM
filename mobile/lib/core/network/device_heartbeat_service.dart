import 'dart:async';

import 'package:flutter/foundation.dart';

import '../storage/machine_number_storage_service.dart';
import 'api_service.dart';

/// Service to periodically send device heartbeat to backend.
/// This keeps the device marked as "online" in the backend system.
class DeviceHeartbeatService {
  DeviceHeartbeatService({
    ApiService? apiService,
    MachineNumberStorageService? machineNumberStorageService,
    Duration? heartbeatInterval,
  })  : _apiService = apiService ?? ApiService(),
        _machineNumberStorageService =
            machineNumberStorageService ?? MachineNumberStorageService(),
        _heartbeatInterval = heartbeatInterval ?? const Duration(minutes: 5);

  final ApiService _apiService;
  final MachineNumberStorageService _machineNumberStorageService;
  final Duration _heartbeatInterval;

  Timer? _heartbeatTimer;
  bool _isRunning = false;

  bool get isRunning => _isRunning;

  /// Start the heartbeat service.
  /// The heartbeat will be sent immediately, then at regular intervals.
  Future<void> start() async {
    if (_isRunning) return;

    _isRunning = true;

    // Send heartbeat immediately on start
    await _sendHeartbeat();

    // Schedule periodic heartbeats
    _heartbeatTimer = Timer.periodic(_heartbeatInterval, (_) async {
      await _sendHeartbeat();
    });

    if (kDebugMode) {
      print('[DeviceHeartbeat] Started (every ${_heartbeatInterval.inMinutes}min)');
    }
  }

  /// Stop the heartbeat service.
  void stop() {
    if (!_isRunning) return;

    _heartbeatTimer?.cancel();
    _heartbeatTimer = null;
    _isRunning = false;

    if (kDebugMode) {
      print('[DeviceHeartbeat] Stopped');
    }
  }

  /// Send a single heartbeat to the backend using the stored machine number.
  Future<void> _sendHeartbeat() async {
    try {
      final deviceId = await _machineNumberStorageService.getMachineNumber();
      final platform = kIsWeb ? 'web' : _getPlatformName();

      await _apiService.post(
        '/auth/device-heartbeat',
        body: <String, dynamic>{
          'device_id': deviceId,
          'device_name': deviceId,
          'platform': platform,
        },
        requiresAuth: true,
      );

      if (kDebugMode) {
        print('[DeviceHeartbeat] Sent OK (device: $deviceId)');
      }
    } catch (error) {
      // Silently ignore - heartbeat failures must not crash the app
      if (kDebugMode) {
        print('[DeviceHeartbeat] Failed (will retry): $error');
      }
    }
  }

  String _getPlatformName() {
    try {
      // ignore: import_of_legacy_library_into_null_safe
      // Use dart:io safely via conditional import
      if (kIsWeb) return 'web';
      // For non-web, we detect via defaultTargetPlatform
      switch (defaultTargetPlatform) {
        case TargetPlatform.android:
          return 'android';
        case TargetPlatform.iOS:
          return 'ios';
        default:
          return 'web';
      }
    } catch (_) {
      return 'web';
    }
  }

  /// Dispose resources.
  void dispose() {
    stop();
  }
}
