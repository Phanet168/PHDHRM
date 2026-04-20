import 'dart:math';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class MachineNumberStorageService {
  static const String _machineNumberKey = 'machine_number';
  static const FlutterSecureStorage _secureStorage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  Future<String> getMachineNumber() async {
    final secureExisting = await _secureStorage.read(key: _machineNumberKey);
    if (secureExisting != null && secureExisting.trim().isNotEmpty) {
      return secureExisting;
    }

    final prefs = await SharedPreferences.getInstance();
    final existing = prefs.getString(_machineNumberKey);
    if (existing != null && existing.trim().isNotEmpty) {
      await _secureStorage.write(key: _machineNumberKey, value: existing);
      return existing;
    }

    final created = await _generateMachineNumber();
    await _secureStorage.write(key: _machineNumberKey, value: created);
    await prefs.setString(_machineNumberKey, created);
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

  Future<String?> _readStablePlatformIdentifier() async {
    if (kIsWeb) {
      return null;
    }

    final deviceInfo = DeviceInfoPlugin();

    try {
      final android = await deviceInfo.androidInfo;
      final androidId = android.id.trim().isNotEmpty
          ? android.id.trim()
          : android.fingerprint.trim();
      if (androidId.isNotEmpty) {
        return 'ANDROID-$androidId';
      }
    } catch (_) {
      // Continue and try iOS below.
    }

    try {
      final ios = await deviceInfo.iosInfo;
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
