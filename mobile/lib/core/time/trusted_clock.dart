import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// The time to stamp on an attendance scan saved without internet.
///
/// The phone's own clock can be changed by its user, so whenever the server
/// answers we remember its time (HTTP `Date` header) together with the
/// phone's monotonic uptime (MainActivity.kt / AppDelegate.swift). Later, offline, server time = anchor + uptime elapsed —
/// which changing the date/time setting cannot move. After a reboot (or
/// before the first server contact) only the phone clock is left, and the
/// scan says so ([TrustedTime.source] = `device`).
class TrustedClock {
  TrustedClock._();

  static final TrustedClock instance = TrustedClock._();

  static const _channel = MethodChannel('phdhrm/clock');
  static const _anchorKey = 'trusted_clock_anchor_v1';

  /// Re-anchor at most this often; every response carries a Date header.
  static const _refreshEvery = Duration(minutes: 1);

  _Anchor? _anchor;
  bool _loaded = false;

  Future<_Uptime?> _uptime() async {
    try {
      final raw = await _channel.invokeMapMethod<String, dynamic>('uptime');
      final elapsed = (raw?['elapsed'] as num?)?.toInt();
      if (elapsed == null) return null;
      return _Uptime(elapsed, (raw?['boot'] as num?)?.toInt() ?? -1);
    } on MissingPluginException {
      return null; // Tests, or a platform without the channel.
    } on PlatformException {
      return null;
    }
  }

  Future<_Anchor?> _load() async {
    if (_loaded) return _anchor;
    _loaded = true;
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_anchorKey);
      if (raw != null) _anchor = _Anchor.fromJson(jsonDecode(raw));
    } catch (_) {
      _anchor = null;
    }
    return _anchor;
  }

  /// Feed with each server response's `Date` header.
  Future<void> observeServerDate(String? header) async {
    final serverTime = parseHttpDate(header);
    if (serverTime == null) return;
    final current = await _load();
    final uptime = await _uptime();
    if (uptime == null) return;
    if (current != null &&
        current.boot == uptime.boot &&
        uptime.elapsed - current.elapsedMs < _refreshEvery.inMilliseconds &&
        uptime.elapsed >= current.elapsedMs) {
      return;
    }
    _anchor = _Anchor(
      serverMs: serverTime.millisecondsSinceEpoch,
      elapsedMs: uptime.elapsed,
      boot: uptime.boot,
    );
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_anchorKey, jsonEncode(_anchor!.toJson()));
    } catch (_) {
      // Keeping it in memory still helps until the app closes.
    }
  }

  /// Best-known current time, and whether it is anchored to the server.
  Future<TrustedTime> now() async {
    final anchor = await _load();
    final uptime = await _uptime();
    if (anchor != null &&
        uptime != null &&
        uptime.boot == anchor.boot &&
        uptime.elapsed >= anchor.elapsedMs) {
      return TrustedTime(
        DateTime.fromMillisecondsSinceEpoch(
          anchor.serverMs + uptime.elapsed - anchor.elapsedMs,
        ),
        TrustedTime.serverAnchored,
      );
    }
    return TrustedTime(DateTime.now(), TrustedTime.device);
  }

  /// Parses an RFC 1123 HTTP date ("Thu, 30 Oct 2026 01:02:03 GMT") as UTC.
  static DateTime? parseHttpDate(String? value) {
    final match = RegExp(
      r'(\d{1,2}) (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) (\d{4}) (\d{2}):(\d{2}):(\d{2})',
    ).firstMatch(value ?? '');
    if (match == null) return null;
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    int part(int i) => int.parse(match.group(i)!);
    return DateTime.utc(
      part(3),
      months.indexOf(match.group(2)!) + 1,
      part(1),
      part(4),
      part(5),
      part(6),
    );
  }
}

class TrustedTime {
  const TrustedTime(this.time, this.source);

  static const serverAnchored = 'server_anchored';
  static const device = 'device';

  final DateTime time;

  /// [serverAnchored] or [device]; sent with the scan as `clock_source`.
  final String source;
}

class _Uptime {
  const _Uptime(this.elapsed, this.boot);
  final int elapsed;
  final int boot;
}

class _Anchor {
  const _Anchor({
    required this.serverMs,
    required this.elapsedMs,
    required this.boot,
  });

  final int serverMs;
  final int elapsedMs;
  final int boot;

  factory _Anchor.fromJson(Map<String, dynamic> json) => _Anchor(
    serverMs: (json['server_ms'] as num).toInt(),
    elapsedMs: (json['elapsed_ms'] as num).toInt(),
    boot: (json['boot'] as num?)?.toInt() ?? -1,
  );

  Map<String, dynamic> toJson() => {
    'server_ms': serverMs,
    'elapsed_ms': elapsedMs,
    'boot': boot,
  };
}
