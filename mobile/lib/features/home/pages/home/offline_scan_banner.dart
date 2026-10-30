import 'package:flutter/material.dart';

import '../../services/offline_attendance_queue.dart';

/// Dashboard notice for scans saved on the phone while offline: how many
/// still wait to be sent, a "send now" button, and — if the server refused
/// any (outside the area, sent too late …) — a way to see why and dismiss.
/// Hidden when the queue is empty.
class OfflineScanBanner extends StatelessWidget {
  const OfflineScanBanner({
    super.key,
    required this.userId,
    required this.onSendNow,
  });

  final int userId;
  final Future<void> Function() onSendNow;

  static const _amber = Color(0xFFE6943B);
  static const _red = Color(0xFFE65F5C);

  @override
  Widget build(BuildContext context) {
    final queue = OfflineAttendanceQueue.instance;
    return ValueListenableBuilder<List<PendingScan>>(
      valueListenable: queue.items,
      builder: (context, _, _) {
        final mine = queue.forUser(userId);
        if (mine.isEmpty) return const SizedBox.shrink();
        final waiting = mine.where((s) => !s.isRejected).length;
        final rejected = mine.length - waiting;
        final tone = waiting > 0 ? _amber : _red;

        return Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
          child: Material(
            color: tone.withAlpha(24),
            borderRadius: BorderRadius.circular(18),
            child: InkWell(
              borderRadius: BorderRadius.circular(18),
              onTap: () => _showQueue(context),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
                child: Row(
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: tone,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Icon(
                        waiting > 0
                            ? Icons.cloud_upload_rounded
                            : Icons.error_outline_rounded,
                        color: Colors.white,
                        size: 22,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            waiting > 0
                                ? 'ការស្កេន ${_km(waiting)} រង់ចាំបញ្ជូន'
                                : 'ការស្កេន ${_km(rejected)} ត្រូវបានបដិសេធ',
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Text(
                            waiting > 0
                                ? (rejected > 0
                                    ? 'បដិសេធ ${_km(rejected)} · ចុចដើម្បីមើល'
                                    : 'នឹងបញ្ជូនស្វ័យប្រវត្តិពេលមានអ៊ីនធឺណិត')
                                : 'ចុចដើម្បីមើលមូលហេតុ',
                            style: const TextStyle(
                              fontSize: 11,
                              color: Color(0xFF6F7C76),
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (waiting > 0)
                      TextButton(
                        onPressed: onSendNow,
                        style: TextButton.styleFrom(foregroundColor: tone),
                        child: const Text('បញ្ជូនឥឡូវ'),
                      )
                    else
                      Icon(Icons.chevron_right_rounded, color: tone),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  static String _km(int value) =>
      value.toString().split('').map((c) => '០១២៣៤៥៦៧៨៩'[int.parse(c)]).join();

  static String _time(DateTime value) {
    final t = value.toLocal();
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(t.day)}/${two(t.month)}/${t.year} ${two(t.hour)}:${two(t.minute)}';
  }

  void _showQueue(BuildContext context) {
    final queue = OfflineAttendanceQueue.instance;
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder:
          (context) => ValueListenableBuilder<List<PendingScan>>(
            valueListenable: queue.items,
            builder: (context, _, _) {
              final mine = queue.forUser(userId);
              return SafeArea(
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    maxHeight: MediaQuery.sizeOf(context).height * 0.7,
                  ),
                  child: ListView(
                    shrinkWrap: true,
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                    children: [
                      const Text(
                        'វត្តមានរក្សាទុកក្នុងទូរសព្ទ',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        'ស្កេនពេលគ្មានអ៊ីនធឺណិត — បញ្ជូនជាមួយម៉ោងដែលបានស្កេនពិតប្រាកដ។',
                        style: TextStyle(
                          fontSize: 12,
                          color: Color(0xFF6F7C76),
                        ),
                      ),
                      const SizedBox(height: 12),
                      if (mine.isEmpty)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 24),
                          child: Center(child: Text('បានបញ្ជូនទាំងអស់ហើយ ✓')),
                        ),
                      for (final scan in mine)
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: Icon(
                            scan.isRejected
                                ? Icons.error_outline_rounded
                                : Icons.schedule_send_rounded,
                            color: scan.isRejected ? _red : _amber,
                          ),
                          title: Text(
                            '${scan.scanType} · ${_time(scan.capturedAt)}',
                          ),
                          subtitle: Text(
                            scan.isRejected
                                ? scan.rejectionReason
                                : 'រង់ចាំបញ្ជូន',
                            style: TextStyle(
                              color: scan.isRejected ? _red : null,
                            ),
                          ),
                          trailing:
                              scan.isRejected
                                  ? IconButton(
                                    tooltip: 'លុបចេញ',
                                    icon: const Icon(
                                      Icons.delete_outline_rounded,
                                    ),
                                    onPressed:
                                        () => queue.remove(scan.requestId),
                                  )
                                  : null,
                        ),
                    ],
                  ),
                ),
              );
            },
          ),
    );
  }
}
