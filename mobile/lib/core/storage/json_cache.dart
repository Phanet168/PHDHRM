import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// The last good copy of API data (attendance, today's context …), so screens
/// open instantly — and still show something offline — while fresh data loads.
///
/// [preload] runs once at startup; after that [read] is synchronous, which
/// lets a FutureBuilder show cached data in its very first frame.
class JsonCache {
  JsonCache._();

  static final JsonCache instance = JsonCache._();

  static const _prefix = 'json_cache_v1:';

  final Map<String, Object?> _memory = <String, Object?>{};

  Future<void> preload() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      for (final key in prefs.getKeys()) {
        if (!key.startsWith(_prefix)) continue;
        final raw = prefs.getString(key);
        if (raw == null) continue;
        try {
          _memory[key.substring(_prefix.length)] = jsonDecode(raw);
        } catch (_) {
          await prefs.remove(key);
        }
      }
    } catch (_) {
      // No cache this launch; screens simply wait for the network.
    }
  }

  Object? read(String key) => _memory[key];

  Future<void> write(String key, Object? value) async {
    _memory[key] = value;
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_prefix + key, jsonEncode(value));
    } catch (_) {
      // Still cached in memory for this session.
    }
  }

  /// Drops every cached entry whose key starts with [keyPrefix] (e.g. one
  /// user's data at logout).
  Future<void> removeWhere(String keyPrefix) async {
    _memory.removeWhere((key, _) => key.startsWith(keyPrefix));
    try {
      final prefs = await SharedPreferences.getInstance();
      for (final key in prefs.getKeys().toList()) {
        if (key.startsWith(_prefix + keyPrefix)) await prefs.remove(key);
      }
    } catch (_) {}
  }
}
