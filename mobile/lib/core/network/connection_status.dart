import 'dart:async';

import 'package:flutter/foundation.dart';

/// Whether the app can reach its server right now.
enum ServerReach { unknown, online, offline }

/// The app's view of its server connection, for the online/offline
/// indicators. [ApiService] reports every request: an answer from the API
/// means online, a network failure means offline. While watching, a light
/// ping runs every minute (every 20 s while offline) so the indicator
/// follows a Wi-Fi drop or return even when nothing else is loading.
class ConnectionStatus extends ChangeNotifier {
  ConnectionStatus._();

  static final ConnectionStatus instance = ConnectionStatus._();

  static const Duration onlineEvery = Duration(seconds: 60);
  static const Duration offlineEvery = Duration(seconds: 20);

  ServerReach _reach = ServerReach.unknown;
  DateTime? _lastOnlineAt;
  DateTime? _offlineSince;
  int _reconnections = 0;
  bool _checking = false;
  Future<void> Function()? _probe;
  Timer? _timer;

  ServerReach get reach => _reach;
  bool get isOnline => _reach == ServerReach.online;
  bool get isOffline => _reach == ServerReach.offline;

  /// When the server last answered.
  DateTime? get lastOnlineAt => _lastOnlineAt;

  /// When the current offline spell began.
  DateTime? get offlineSince => _offlineSince;

  /// Counts returns from offline to online; listeners use it to send
  /// what was saved while offline.
  int get reconnections => _reconnections;

  /// A check is under way (the "try again" button shows a spinner).
  bool get checking => _checking;

  void markOnline() {
    _lastOnlineAt = DateTime.now();
    if (_reach == ServerReach.online) return;
    if (_reach == ServerReach.offline) _reconnections++;
    _reach = ServerReach.online;
    _offlineSince = null;
    _reschedule();
    notifyListeners();
  }

  void markOffline() {
    if (_reach == ServerReach.offline) return;
    _reach = ServerReach.offline;
    _offlineSince = DateTime.now();
    _reschedule();
    notifyListeners();
  }

  /// Starts the periodic ping; [probe] makes one light request (its result
  /// reaches this class through [ApiService]).
  void startWatching(Future<void> Function() probe) {
    _probe = probe;
    _reschedule();
  }

  void stopWatching() {
    _timer?.cancel();
    _timer = null;
    _probe = null;
  }

  /// Pings now (also from the banner's "try again").
  Future<void> check() async {
    final probe = _probe;
    if (probe == null || _checking) return;
    _checking = true;
    notifyListeners();
    try {
      await probe();
    } catch (_) {
      // The outcome was already recorded by ApiService.
    } finally {
      _checking = false;
      notifyListeners();
    }
  }

  void _reschedule() {
    if (_probe == null) return;
    _timer?.cancel();
    _timer = Timer.periodic(isOffline ? offlineEvery : onlineEvery, (_) {
      check();
    });
  }

  @visibleForTesting
  void reset() {
    stopWatching();
    _reach = ServerReach.unknown;
    _lastOnlineAt = null;
    _offlineSince = null;
    _reconnections = 0;
    _checking = false;
  }
}
