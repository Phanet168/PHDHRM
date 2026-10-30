import 'dart:math';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../device/imei.dart';

/// The number the server knows this phone by (its "machine number").
///
/// It is the phone's IMEI, typed in once by the officer (Android 10+ does
/// not let an app read the IMEI). Until then the app keeps the number it
/// used before, so a session already signed in keeps working; the server
/// moves that registration to the IMEI at the next sign-in.
class MachineNumberStorageService {
  MachineNumberStorageService({
    FlutterSecureStorage? secureStorage,
    MethodChannel? deviceChannel,
  }) : _secureStorage = secureStorage ?? _defaultStorage,
       _deviceChannel = deviceChannel ?? const MethodChannel('phdhrm/device');

  static const String _machineNumberKey = 'machine_number';
  static const String _imeiKey = 'device_imei';
  static const FlutterSecureStorage _defaultStorage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  final FlutterSecureStorage _secureStorage;
  final MethodChannel _deviceChannel;

  /// The IMEI entered on this phone, or null.
  Future<String?> getImei() async {
    final stored = await _secureStorage.read(key: _imeiKey);
    return stored != null && isValidImei(stored) ? normalizeImei(stored) : null;
  }

  /// Keeps [imei] (any spacing) as this phone's machine number.
  Future<void> saveImei(String imei) async {
    if (!isValidImei(imei)) {
      throw ArgumentError.value(imei, 'imei', 'not a valid IMEI');
    }
    await _secureStorage.write(key: _imeiKey, value: normalizeImei(imei));
  }

  /// The machine number: the IMEI once entered, otherwise the earlier one.
  Future<String> getMachineNumber() async =>
      (await getImei()) ?? await _previousNumber();

  /// The number used before the IMEI, while it differs from the IMEI — sent
  /// with sign-in so the server can move an approved registration over.
  Future<String?> getLegacyMachineNumber() async {
    final imei = await getImei();
    final previous = await _storedPreviousNumber();
    return previous == null || previous == imei ? null : previous;
  }

  Future<String?> _storedPreviousNumber() async {
    final secure = await _secureStorage.read(key: _machineNumberKey);
    if (secure != null && secure.trim().isNotEmpty) return secure.trim();
    try {
      final prefs = await SharedPreferences.getInstance();
      final existing = prefs.getString(_machineNumberKey);
      if (existing != null && existing.trim().isNotEmpty) {
        await _secureStorage.write(key: _machineNumberKey, value: existing);
        return existing.trim();
      }
    } catch (_) {}
    return null;
  }

  Future<String> _previousNumber() async {
    final existing = await _storedPreviousNumber();
    if (existing != null) return existing;
    final created = await _generateMachineNumber();
    await _secureStorage.write(key: _machineNumberKey, value: created);
    return created;
  }

  Future<String> _generateMachineNumber() async {
    final stableId = await _readStablePlatformIdentifier();
    if (stableId != null && stableId.isNotEmpty) {
      return stableId;
    }

    final random = Random.secure();
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    final prefix = kIsWeb ? 'WEB' : 'APP';
    final timestamp =
        DateTime.now().millisecondsSinceEpoch.toRadixString(36).toUpperCase();
    final suffix =
        List<String>.generate(
          8,
          (_) => alphabet[random.nextInt(alphabet.length)],
        ).join();

    return '$prefix-$timestamp-$suffix';
  }

  /// A per-phone ID: Android's ANDROID_ID or iOS's identifierForVendor.
  /// (device_info_plus's `AndroidDeviceInfo.id` is Build.ID — the system
  /// build code, the same on every phone with that firmware — so it is not
  /// used.)
  Future<String?> _readStablePlatformIdentifier() async {
    if (kIsWeb) return null;

    if (defaultTargetPlatform == TargetPlatform.android) {
      try {
        final androidId = await _deviceChannel.invokeMethod<String>(
          'androidId',
        );
        if (androidId != null && androidId.trim().isNotEmpty) {
          return 'ANDROID-${androidId.trim().toUpperCase()}';
        }
      } catch (_) {}
      return null;
    }

    try {
      final ios = await DeviceInfoPlugin().iosInfo;
      final iosId = (ios.identifierForVendor ?? '').trim();
      if (iosId.isNotEmpty) {
        return 'IOS-$iosId';
      }
    } catch (_) {
      // Unsupported platform or plugin unavailable.
    }

    return null;
  }
}
