import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

class MachineNumberStorageService {
  static const String _machineNumberKey = 'machine_number';

  Future<String> getMachineNumber() async {
    final prefs = await SharedPreferences.getInstance();
    final existing = prefs.getString(_machineNumberKey);
    if (existing != null && existing.trim().isNotEmpty) {
      return existing;
    }

    final created = _generateMachineNumber();
    await prefs.setString(_machineNumberKey, created);
    return created;
  }

  String _generateMachineNumber() {
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
}
