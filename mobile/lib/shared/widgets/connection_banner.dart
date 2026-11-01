import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/connection_status.dart';
import '../../features/home/services/offline_attendance_queue.dart';

String _kmNumber(int value) =>
    value.toString().split('').map((c) => '០១២៣៤៥៦៧៨៩'[int.parse(c)]).join();

/// Two Khmer digits: 5 → "០៥".
String _km(int value) => _kmNumber(value).padLeft(2, '០');

/// "១០:២៥" today, "២៩/១០ ១០:២៥" on an earlier day.
String connectionTimeLabel(DateTime time, {DateTime? now}) {
  final today = now ?? DateTime.now();
  final clock = '${_km(time.hour)}:${_km(time.minute)}';
  final sameDay =
      time.year == today.year &&
      time.month == today.month &&
      time.day == today.day;
  return sameDay ? clock : '${_km(time.day)}/${_km(time.month)} $clock';
}

/// Scans saved on the phone that still wait to be sent.
int _waitingScans() =>
    OfflineAttendanceQueue.instance.items.value
        .where((scan) => !scan.isRejected)
        .length;

/// Wraps every screen: while the server cannot be reached, a dark bar at the
/// top says so, that scans are kept on the phone (and how many wait), when
/// the app last reached the server, and offers "try again"; when the
/// connection returns it turns green for a moment. Checks again whenever the
/// app comes back to the foreground.
class ConnectionBanner extends StatefulWidget {
  const ConnectionBanner({super.key, required this.child, this.status});

  final Widget child;

  /// Injected in tests; the app-wide instance otherwise.
  final ConnectionStatus? status;

  @override
  State<ConnectionBanner> createState() => _ConnectionBannerState();
}

class _ConnectionBannerState extends State<ConnectionBanner>
    with WidgetsBindingObserver {
  late final ConnectionStatus _status =
      widget.status ?? ConnectionStatus.instance;
  late int _seenReconnections = _status.reconnections;
  bool _justReconnected = false;
  Timer? _hideTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _status.addListener(_onStatus);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _status.removeListener(_onStatus);
    _hideTimer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _status.check();
  }

  void _onStatus() {
    if (_status.reconnections != _seenReconnections) {
      _seenReconnections = _status.reconnections;
      _hideTimer?.cancel();
      setState(() => _justReconnected = true);
      _hideTimer = Timer(const Duration(seconds: 4), () {
        if (mounted) setState(() => _justReconnected = false);
      });
    } else {
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    final offline = _status.isOffline;
    final showBar = offline || _justReconnected;
    return Column(
      children: [
        AnimatedSize(
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOutCubic,
          alignment: Alignment.topCenter,
          // A slim status bar: large system fonts are capped here so it
          // never takes over the screen.
          child: MediaQuery.withClampedTextScaling(
            maxScaleFactor: 1.35,
            child:
                !showBar
                    ? const SizedBox(width: double.infinity)
                    : offline
                    ? _OfflineBar(status: _status)
                    : const _ReconnectedBar(),
          ),
        ),
        Expanded(
          child: MediaQuery.removePadding(
            context: context,
            removeTop: showBar,
            child: widget.child,
          ),
        ),
      ],
    );
  }
}

class _OfflineBar extends StatelessWidget {
  const _OfflineBar({required this.status});

  final ConnectionStatus status;

  @override
  Widget build(BuildContext context) {
    final last = status.lastOnlineAt;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Material(
        color: const Color(0xFF334155),
        child: SafeArea(
          bottom: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 8, 6, 8),
            child: Row(
              children: [
                const Icon(
                  Icons.cloud_off_rounded,
                  color: Colors.white,
                  size: 22,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: ValueListenableBuilder(
                    valueListenable: OfflineAttendanceQueue.instance.items,
                    builder: (context, _, _) {
                      final waiting = _waitingScans();
                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Text(
                            'អហ្វឡាញ — មិនអាចភ្ជាប់ម៉ាស៊ីនមេ',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          Text(
                            <String>[
                              waiting > 0
                                  ? 'ស្កេន ${_kmNumber(waiting)} កំពុងរង់ចាំបញ្ជូន'
                                  : 'ការស្កេនវត្តមាននឹងរក្សាទុកក្នុងទូរសព្ទ',
                              if (last != null)
                                'ភ្ជាប់ចុងក្រោយ ${connectionTimeLabel(last)}',
                            ].join(' · '),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Color(0xFFCBD5E1),
                              fontSize: 11.5,
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                ),
                status.checking
                    ? const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 18),
                      child: SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      ),
                    )
                    : TextButton(
                      onPressed: status.check,
                      style: TextButton.styleFrom(
                        foregroundColor: Colors.white,
                      ),
                      child: const Text(
                        'ព្យាយាម',
                        style: TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ReconnectedBar extends StatelessWidget {
  const _ReconnectedBar();

  @override
  Widget build(BuildContext context) {
    final waiting = _waitingScans();
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Material(
        color: const Color(0xFF16A34A),
        child: SafeArea(
          bottom: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
            child: Row(
              children: [
                const Icon(
                  Icons.cloud_done_rounded,
                  color: Colors.white,
                  size: 22,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    waiting > 0
                        ? 'អនឡាញវិញ — កំពុងបញ្ជូនស្កេនដែលបានរក្សាទុក…'
                        : 'អនឡាញវិញ — បានភ្ជាប់ម៉ាស៊ីនមេ',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// The connection in detail: online or offline, when the server last
/// answered, scans waiting on the phone, and "check now".
Future<void> showConnectionSheet(
  BuildContext context, {
  ConnectionStatus? status,
}) {
  final connection = status ?? ConnectionStatus.instance;
  return showModalBottomSheet<void>(
    context: context,
    backgroundColor: Colors.transparent,
    builder:
        (context) => ListenableBuilder(
          listenable: Listenable.merge([
            connection,
            OfflineAttendanceQueue.instance.items,
          ]),
          builder: (context, _) {
            final offline = connection.isOffline;
            final color =
                offline ? const Color(0xFFF59E0B) : const Color(0xFF16A34A);
            final waiting = _waitingScans();
            final last = connection.lastOnlineAt;
            return Container(
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
              ),
              padding: EdgeInsets.fromLTRB(
                20,
                18,
                20,
                20 + MediaQuery.paddingOf(context).bottom,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 52,
                        height: 52,
                        decoration: BoxDecoration(
                          color: color.withAlpha(30),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Icon(
                          offline
                              ? Icons.cloud_off_rounded
                              : Icons.cloud_done_rounded,
                          color: color,
                          size: 28,
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              offline ? 'អហ្វឡាញ' : 'អនឡាញ',
                              style: TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.w900,
                                color: color,
                              ),
                            ),
                            Text(
                              offline
                                  ? 'មិនអាចភ្ជាប់ម៉ាស៊ីនមេបានទេ'
                                  : 'បានភ្ជាប់ម៉ាស៊ីនមេ',
                              style: const TextStyle(
                                fontSize: 13,
                                color: Color(0xFF475569),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  _SheetLine(
                    icon: Icons.schedule_rounded,
                    label: 'ភ្ជាប់ចុងក្រោយ',
                    value: last == null ? '—' : connectionTimeLabel(last),
                  ),
                  _SheetLine(
                    icon: Icons.cloud_upload_rounded,
                    label: 'ស្កេនកំពុងរង់ចាំបញ្ជូន',
                    value: _kmNumber(waiting),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    offline
                        ? 'អ្នកនៅតែស្កេនវត្តមានបាន។ ការស្កេនត្រូវបានរក្សាទុកក្នុងទូរសព្ទ '
                            'ជាមួយម៉ោងពិត ហើយបញ្ជូនដោយស្វ័យប្រវត្តិពេលអនឡាញវិញ។ '
                            'ទិន្នន័យដែលបង្ហាញជាទិន្នន័យចុងក្រោយ។'
                        : 'ការស្កេនវត្តមាន និងសំណើនានា ត្រូវបានបញ្ជូនទៅម៉ាស៊ីនមេភ្លាមៗ។',
                    style: const TextStyle(
                      fontSize: 13,
                      height: 1.6,
                      color: Color(0xFF475569),
                    ),
                  ),
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    onPressed: connection.checking ? null : connection.check,
                    style: FilledButton.styleFrom(
                      backgroundColor: color,
                      minimumSize: const Size.fromHeight(50),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                    icon:
                        connection.checking
                            ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                            : const Icon(Icons.refresh_rounded),
                    label: const Text(
                      'ពិនិត្យការតភ្ជាប់ឥឡូវ',
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
  );
}

class _SheetLine extends StatelessWidget {
  const _SheetLine({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Icon(icon, size: 18, color: const Color(0xFF94A3B8)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              label,
              style: const TextStyle(fontSize: 13.5, color: Color(0xFF475569)),
            ),
          ),
          Text(
            value,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: Color(0xFF0F172A),
            ),
          ),
        ],
      ),
    );
  }
}

/// A small "● អនឡាញ / ● អហ្វឡាញ" pill. [onDark] for gradient headers.
class ConnectionPill extends StatelessWidget {
  const ConnectionPill({super.key, this.onDark = false, this.status});

  final bool onDark;
  final ConnectionStatus? status;

  @override
  Widget build(BuildContext context) {
    final connection = status ?? ConnectionStatus.instance;
    return ListenableBuilder(
      listenable: connection,
      builder: (context, _) {
        final offline = connection.isOffline;
        final color =
            offline ? const Color(0xFFF59E0B) : const Color(0xFF22C55E);
        final label =
            offline
                ? 'អហ្វឡាញ'
                : connection.isOnline
                ? 'អនឡាញ'
                : 'កំពុងពិនិត្យ';
        return Semantics(
          label: 'ស្ថានភាពការតភ្ជាប់៖ $label',
          excludeSemantics: true,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: onDark ? Colors.white.withAlpha(36) : color.withAlpha(26),
              borderRadius: BorderRadius.circular(999),
              border: Border.all(
                color:
                    onDark ? Colors.white.withAlpha(60) : color.withAlpha(90),
              ),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: color,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 6),
                Flexible(
                  child: Text(
                    label,
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      color:
                          onDark
                              ? Colors.white
                              : (offline
                                  ? const Color(0xFFB45309)
                                  : const Color(0xFF15803D)),
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
