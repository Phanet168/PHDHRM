import 'package:flutter/material.dart';

import '../models/attendance_day_record.dart';
import 'home/attendance_status.dart';
import 'home/home_theme.dart';

/// Full-page daily attendance detail (Figma "វត្តមានប្រចាំថ្ងៃ / Daily
/// Detail") — one card per attendance session (morning/afternoon/duty),
/// each showing its scheduled vs. actual check-in/check-out, plus a
/// worked-hours progress bar and any HR adjustment note.
class AttendanceDayDetailPage extends StatelessWidget {
  const AttendanceDayDetailPage({super.key, required this.record});

  final AttendanceDayRecord record;

  static const _monthNames = [
    'មករា',
    'កុម្ភៈ',
    'មីនា',
    'មេសា',
    'ឧសភា',
    'មិថុនា',
    'កក្កដា',
    'សីហា',
    'កញ្ញា',
    'តុលា',
    'វិច្ឆិកា',
    'ធ្នូ',
  ];

  static const _weekdayNames = [
    'ចន្ទ',
    'អង្គារ',
    'ពុធ',
    'ព្រហស្បតិ៍',
    'សុក្រ',
    'សៅរ៍',
    'អាទិត្យ',
  ];

  String _formatHours(String raw) {
    final text = raw.trim();
    if (text.isEmpty || text == '-') return '0 ម៉ោង';
    final parts = text.split(':');
    final hours = int.tryParse(parts.elementAt(0)) ?? 0;
    final minutes = parts.length > 1 ? int.tryParse(parts[1]) ?? 0 : 0;
    if (minutes == 0) return '$hours ម៉ោង';
    return '$hours ម៉ោង $minutes នាទី';
  }

  String _formatMinutes(int totalMinutes) {
    final hours = totalMinutes ~/ 60;
    final minutes = totalMinutes % 60;
    if (hours == 0) return '$minutes នាទី';
    if (minutes == 0) return '$hours ម៉ោង';
    return '$hours ម៉ោង $minutes នាទី';
  }

  double _workedPercent() {
    final raw = record.totalHours.trim();
    if (raw.isEmpty || raw == '-') return 0;
    final parts = raw.split(':');
    final hours = int.tryParse(parts.elementAt(0)) ?? 0;
    final minutes = parts.length > 1 ? int.tryParse(parts[1]) ?? 0 : 0;
    const expectedHours = 8;
    return ((hours + minutes / 60) / expectedHours).clamp(0.0, 1.0);
  }

  @override
  Widget build(BuildContext context) {
    DateTime date;
    try {
      date = DateTime.parse(record.date);
    } catch (_) {
      date = DateTime.now();
    }
    final style = attendanceStatusStyle(record.attendanceStatus);
    final note = record.adjustmentNote?.trim();
    final hasNote = note != null && note.isNotEmpty;
    final percent = _workedPercent();

    return Scaffold(
      backgroundColor: const Color(0xFFF5F7F6),
      body: SafeArea(
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
              color: Colors.white,
              child: Row(
                children: [
                  InkWell(
                    onTap: () => Navigator.of(context).maybePop(),
                    borderRadius: BorderRadius.circular(11),
                    child: const Padding(
                      padding: EdgeInsets.all(4),
                      child: Icon(
                        Icons.arrow_back_ios_new_rounded,
                        size: 18,
                        color: Color(0xFF17221D),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'ព័ត៌មានវត្តមាន',
                          style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF17221D),
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'ថ្ងៃ${_weekdayNames[date.weekday - 1]} ${date.day} ${_monthNames[date.month - 1]} ${date.year}',
                          style: const TextStyle(
                            fontSize: 11,
                            color: Color(0xFF6F7C76),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'កាលវិភាគថ្ងៃនេះ',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF17221D),
                        ),
                      ),
                      AttendanceStatusPill(style: style),
                    ],
                  ),
                  const SizedBox(height: 10),
                  if (record.sessions.isEmpty)
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: const Text(
                        'មិនទាន់មានវេនបង្ហាញសម្រាប់ថ្ងៃនេះ',
                        style: TextStyle(color: Color(0xFF6F7C76)),
                      ),
                    )
                  else
                    for (final session in record.sessions) ...[
                      _ShiftCard(session: session),
                      const SizedBox(height: 10),
                    ],
                  const SizedBox(height: 4),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'ម៉ោងធ្វើការ',
                              style: TextStyle(
                                fontSize: 10,
                                color: Color(0xFF6F7C76),
                              ),
                            ),
                            Text(
                              _formatHours(record.totalHours),
                              style: const TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF17221D),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        ClipRRect(
                          borderRadius: BorderRadius.circular(4),
                          child: LinearProgressIndicator(
                            value: percent,
                            minHeight: 7,
                            backgroundColor: const Color(0xFFE8F4EE),
                            valueColor: AlwaysStoppedAnimation<Color>(
                              dashboardHeaderStart,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (record.overtimeMinutes > 0) ...[
                    const SizedBox(height: 10),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'ម៉ោងបន្ថែម',
                            style: TextStyle(
                              fontSize: 11,
                              color: Color(0xFF6F7C76),
                            ),
                          ),
                          Text(
                            _formatMinutes(record.overtimeMinutes),
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF17221D),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 16),
                  const Text(
                    'កំណត់សម្គាល់',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF17221D),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Container(
                    width: double.infinity,
                    constraints: const BoxConstraints(minHeight: 88),
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      hasNote ? note : 'គ្មានកំណត់ចំណាំ',
                      style: const TextStyle(
                        fontSize: 12,
                        height: 1.5,
                        color: Color(0xFF66736F),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// One attendance session as a Figma "Shift card" — a tinted header naming
/// the session, then its check-in/check-out pair with the scheduled time
/// and any late/early-leave callout underneath.
class _ShiftCard extends StatelessWidget {
  const _ShiftCard({required this.session});

  final AttendanceSessionRecord session;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            color: const Color(0xFFE8F4EE),
            child: Row(
              children: [
                const Icon(
                  Icons.calendar_month_outlined,
                  size: 16,
                  color: Color(0xFF2E7D5B),
                ),
                const SizedBox(width: 8),
                Text(
                  'វេន ${attendanceSessionLabel(session.name)}',
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF17221D),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: _PunchColumn(
                    label: 'ចូល',
                    time: session.timeIn,
                    scheduled: session.scheduledIn,
                    lateMinutes: session.lateMinutes,
                    earlyMinutes: session.earlyArrivalMinutes,
                    isCheckIn: true,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: _PunchColumn(
                    label: 'ចេញ',
                    time: session.timeOut,
                    scheduled: session.scheduledOut,
                    lateMinutes: 0,
                    earlyMinutes: session.earlyLeaveMinutes,
                    isCheckIn: false,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PunchColumn extends StatelessWidget {
  const _PunchColumn({
    required this.label,
    required this.time,
    required this.scheduled,
    required this.lateMinutes,
    required this.earlyMinutes,
    required this.isCheckIn,
  });

  final String label;
  final String time;
  final String scheduled;
  final int lateMinutes;
  final int earlyMinutes;
  final bool isCheckIn;

  @override
  Widget build(BuildContext context) {
    final hasTime = time.trim().isNotEmpty && time.trim() != '-';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(fontSize: 9, color: Color(0xFF6F7C76)),
        ),
        const SizedBox(height: 4),
        Text(
          hasTime ? time : '-',
          style: const TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: Color(0xFF17221D),
          ),
        ),
        if (scheduled.trim().isNotEmpty && scheduled.trim() != '-') ...[
          const SizedBox(height: 4),
          Text(
            'កាលវិភាគ៖ $scheduled',
            style: const TextStyle(fontSize: 8, color: Color(0xFF2E7D5B)),
          ),
        ],
        if (isCheckIn && lateMinutes > 0) ...[
          const SizedBox(height: 2),
          Text(
            'មកយឺត $lateMinutes នាទី',
            style: const TextStyle(fontSize: 8, color: Color(0xFFE65F5C)),
          ),
        ],
        if (!isCheckIn && earlyMinutes > 0) ...[
          const SizedBox(height: 2),
          Text(
            'ចេញមុន $earlyMinutes នាទី',
            style: const TextStyle(fontSize: 8, color: Color(0xFFE65F5C)),
          ),
        ],
      ],
    );
  }
}
