import 'package:flutter/material.dart';

import '../models/attendance_day_record.dart';
import 'home/attendance_format.dart';
import 'home/attendance_status.dart';

/// One day's attendance ("ព័ត៌មានវត្តមាន"): a header coloured by status that
/// explains it the way the server decided it (late / early minutes, holiday
/// name, approved leave or mission, a day still under way), the day's
/// figures (worked vs scheduled, late, early leave, overtime, early arrival,
/// scans), each session's scans against its schedule on a time bar, and
/// HR's correction note.
class AttendanceDayDetailPage extends StatelessWidget {
  const AttendanceDayDetailPage({super.key, required this.record});

  final AttendanceDayRecord record;

  String _explanation(AttendanceStatusStyle style) {
    final late = record.lateMinutes ?? 0;
    final early = record.earlyLeaveMinutes ?? 0;
    switch (style.code) {
      case 'in_progress':
        return 'ថ្ងៃនេះមិនទាន់ចប់ទេ — ស្ថានភាពនឹងកំណត់ពេលចប់ម៉ោងធ្វើការ';
      case 'on_time':
        return 'មកធ្វើការទាន់ពេល និងចេញតាមម៉ោងកំណត់';
      case 'late':
        return early > 0
            ? 'មកយឺត ${kmDuration(late)} · ចេញមុន ${kmDuration(early)}'
            : 'មកយឺតសរុប ${kmDuration(late)}';
      case 'early_leave':
        return 'ចេញមុនម៉ោងសរុប ${kmDuration(early)}';
      case 'absent':
        return 'គ្មានការស្កេនវត្តមាននៅថ្ងៃធ្វើការនេះ';
      case 'incomplete':
        return record.sessions.isEmpty && record.shiftName == null
            ? 'មិនមានវេនក្នុងតារាងវេនសម្រាប់ថ្ងៃនេះ'
            : 'ការស្កេនមិនគ្រប់ — ខ្វះស្កេនចូល ឬស្កេនចេញ';
      case 'leave':
        return 'ថ្ងៃឈប់សម្រាកដែលបានអនុម័ត — មិនគិតយឺត ឬអវត្តមាន';
      case 'mission':
        return 'បំពេញបេសកកម្មដែលបានអនុម័ត — មិនគិតយឺត ឬអវត្តមាន';
      case 'holiday':
        return record.holidayName ?? 'ថ្ងៃបុណ្យជាតិ';
      case 'day_off':
        return 'ថ្ងៃឈប់សម្រាកប្រចាំសប្តាហ៍';
    }
    return '';
  }

  IconData _icon(String code) => switch (code) {
    'on_time' => Icons.verified_rounded,
    'late' => Icons.watch_later_rounded,
    'early_leave' => Icons.logout_rounded,
    'absent' => Icons.person_off_rounded,
    'incomplete' => Icons.rule_rounded,
    'leave' => Icons.beach_access_rounded,
    'mission' => Icons.directions_car_rounded,
    'holiday' => Icons.celebration_rounded,
    'day_off' => Icons.weekend_rounded,
    'in_progress' => Icons.timelapse_rounded,
    _ => Icons.event_note_rounded,
  };

  @override
  Widget build(BuildContext context) {
    final date = DateTime.tryParse(record.date) ?? DateTime.now();
    final style = attendanceDayStyle(record);
    final note = record.adjustmentNote?.trim() ?? '';
    final scheduled = record.scheduledMinutes;
    final provisional = style.code == 'in_progress';

    return Scaffold(
      backgroundColor: const Color(0xFFF4F6F8),
      appBar: AppBar(
        backgroundColor: const Color(0xFFF4F6F8),
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          tooltip: 'ត្រឡប់ក្រោយ',
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
          onPressed: () => Navigator.of(context).maybePop(),
        ),
        title: const Text(
          'ព័ត៌មានវត្តមាន',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
        ),
      ),
      body: ListView(
        // Clear Android's edge-to-edge navigation bar at the end.
        padding: EdgeInsets.fromLTRB(
          16,
          4,
          16,
          28 + MediaQuery.paddingOf(context).bottom,
        ),
        children: [
          _Hero(
            date: date,
            style: style,
            icon: _icon(style.code),
            explanation: _explanation(style),
            unit: record.unitName,
            shift: record.shiftName,
          ),
          const SizedBox(height: 14),
          _MetricsGrid(
            metrics: [
              _Metric(
                icon: Icons.schedule_rounded,
                label: 'ម៉ោងធ្វើការ',
                value: kmDuration(record.workedMinutes),
                hint:
                    scheduled > 0
                        ? 'ក្នុងចំណោម ${kmDuration(scheduled)}'
                        : null,
                progress:
                    scheduled > 0
                        ? (record.workedMinutes / scheduled).clamp(0.0, 1.0)
                        : null,
                color: const Color(0xFF2563EB),
              ),
              _Metric(
                icon: Icons.watch_later_rounded,
                label: 'មកយឺត',
                value: kmDuration(record.lateMinutes ?? 0),
                color: const Color(0xFFD99A00),
                muted: (record.lateMinutes ?? 0) == 0,
              ),
              _Metric(
                icon: Icons.logout_rounded,
                label: 'ចេញមុន',
                value: kmDuration(record.earlyLeaveMinutes ?? 0),
                color: const Color(0xFFD9622B),
                muted: (record.earlyLeaveMinutes ?? 0) == 0,
              ),
              _Metric(
                icon: Icons.more_time_rounded,
                label: 'ម៉ោងបន្ថែម',
                value: kmDuration(record.overtimeMinutes),
                color: const Color(0xFF0E7490),
                muted: record.overtimeMinutes == 0,
              ),
              _Metric(
                icon: Icons.wb_twilight_rounded,
                label: 'មកមុនម៉ោង',
                value: kmDuration(record.earlyArrivalMinutes),
                color: const Color(0xFF16A34A),
                muted: record.earlyArrivalMinutes == 0,
              ),
              _Metric(
                icon: Icons.qr_code_scanner_rounded,
                label: 'ចំនួនស្កេន',
                value: '${kmDigits(record.punchCount)} ដង',
                color: const Color(0xFF7C3AED),
                muted: record.punchCount == 0,
              ),
            ],
          ),
          const SizedBox(height: 18),
          const _SectionTitle(
            icon: Icons.view_timeline_rounded,
            title: 'វេនធ្វើការ',
          ),
          const SizedBox(height: 10),
          if (record.sessions.isEmpty)
            _NoSessions(timeIn: record.timeIn, timeOut: record.timeOut)
          else
            for (final session in record.sessions) ...[
              _SessionCard(session: session, provisional: provisional),
              const SizedBox(height: 10),
            ],
          const SizedBox(height: 8),
          const _SectionTitle(
            icon: Icons.sticky_note_2_rounded,
            title: 'កំណត់សម្គាល់',
          ),
          const SizedBox(height: 10),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              border:
                  note.isEmpty
                      ? null
                      : const Border(
                        left: BorderSide(color: Color(0xFF7C3AED), width: 4),
                      ),
            ),
            child:
                note.isEmpty
                    ? const Text(
                      'គ្មានកំណត់ចំណាំ',
                      style: TextStyle(fontSize: 13, color: Color(0xFF94A3B8)),
                    )
                    : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'HR បានកែតម្រូវម៉ោងស្កេន',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF7C3AED),
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          note,
                          style: const TextStyle(
                            fontSize: 13.5,
                            height: 1.55,
                            color: Color(0xFF334155),
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

// ─────────────────────────────────────────────────────────────────────────────
//  Header
// ─────────────────────────────────────────────────────────────────────────────

class _Hero extends StatelessWidget {
  const _Hero({
    required this.date,
    required this.style,
    required this.icon,
    required this.explanation,
    required this.unit,
    required this.shift,
  });

  final DateTime date;
  final AttendanceStatusStyle style;
  final IconData icon;
  final String explanation;
  final String? unit;
  final String? shift;

  @override
  Widget build(BuildContext context) {
    final dark = Color.lerp(style.fg, Colors.black, 0.25)!;
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(
            color: style.fg.withAlpha(60),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(26),
        child: Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [style.fg, dark],
            ),
          ),
          child: Stack(
            children: [
              Positioned(
                right: -36,
                top: -46,
                child: Container(
                  width: 160,
                  height: 160,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white.withAlpha(30),
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      kmLongDate(date),
                      style: TextStyle(
                        color: Colors.white.withAlpha(225),
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Container(
                          width: 50,
                          height: 50,
                          decoration: BoxDecoration(
                            color: Colors.white.withAlpha(45),
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: Icon(icon, color: Colors.white, size: 27),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            style.label,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 22,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (explanation.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      Text(
                        explanation,
                        style: TextStyle(
                          color: Colors.white.withAlpha(235),
                          fontSize: 13,
                          height: 1.5,
                        ),
                      ),
                    ],
                    if ((unit ?? '').isNotEmpty ||
                        (shift ?? '').isNotEmpty) ...[
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          if ((unit ?? '').isNotEmpty)
                            _GlassChip(
                              icon: Icons.apartment_rounded,
                              label: unit!,
                            ),
                          if ((shift ?? '').isNotEmpty)
                            _GlassChip(
                              icon: Icons.schedule_rounded,
                              label: shift!,
                            ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _GlassChip extends StatelessWidget {
  const _GlassChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: Colors.white.withAlpha(42),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withAlpha(60)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: Colors.white),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              label,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Figures
// ─────────────────────────────────────────────────────────────────────────────

class _Metric {
  const _Metric({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
    this.hint,
    this.progress,
    this.muted = false,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color color;
  final String? hint;
  final double? progress;
  final bool muted;
}

class _MetricsGrid extends StatelessWidget {
  const _MetricsGrid({required this.metrics});

  final List<_Metric> metrics;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (var i = 0; i < metrics.length; i += 2) ...[
          if (i > 0) const SizedBox(height: 10),
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(child: _MetricTile(metric: metrics[i])),
                const SizedBox(width: 10),
                Expanded(
                  child:
                      i + 1 < metrics.length
                          ? _MetricTile(metric: metrics[i + 1])
                          : const SizedBox(),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

class _MetricTile extends StatelessWidget {
  const _MetricTile({required this.metric});

  final _Metric metric;

  @override
  Widget build(BuildContext context) {
    final color = metric.muted ? const Color(0xFF94A3B8) : metric.color;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A0F172A),
            blurRadius: 12,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 30,
                height: 30,
                decoration: BoxDecoration(
                  color: color.withAlpha(26),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(metric.icon, size: 17, color: color),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  metric.label,
                  style: const TextStyle(
                    fontSize: 12,
                    color: Color(0xFF64748B),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            metric.value,
            style: TextStyle(
              fontSize: 15.5,
              fontWeight: FontWeight.w800,
              color:
                  metric.muted
                      ? const Color(0xFF94A3B8)
                      : const Color(0xFF0F172A),
            ),
          ),
          if (metric.hint != null)
            Text(
              metric.hint!,
              style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
            ),
          if (metric.progress != null) ...[
            const SizedBox(height: 6),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: metric.progress,
                minHeight: 6,
                color: color,
                backgroundColor: color.withAlpha(30),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.icon, required this.title});

  final IconData icon;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 18, color: const Color(0xFF475569)),
        const SizedBox(width: 7),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: Color(0xFF0F172A),
            ),
          ),
        ),
      ],
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Sessions
// ─────────────────────────────────────────────────────────────────────────────

class _NoSessions extends StatelessWidget {
  const _NoSessions({required this.timeIn, required this.timeOut});

  final String timeIn;
  final String timeOut;

  @override
  Widget build(BuildContext context) {
    final scanned = hasClock(timeIn) || hasClock(timeOut);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
      ),
      child:
          scanned
              ? Row(
                children: [
                  Expanded(
                    child: _Punch(label: 'ចូល', time: timeIn, isIn: true),
                  ),
                  Expanded(
                    child: _Punch(label: 'ចេញ', time: timeOut, isIn: false),
                  ),
                ],
              )
              : const Text(
                'មិនមានវេនធ្វើការសម្រាប់ថ្ងៃនេះ',
                style: TextStyle(color: Color(0xFF64748B)),
              ),
    );
  }
}

class _SessionCard extends StatelessWidget {
  const _SessionCard({required this.session, required this.provisional});

  final AttendanceSessionRecord session;
  final bool provisional;

  @override
  Widget build(BuildContext context) {
    final duty = session.isDuty || session.name == 'duty';
    final accent = duty ? const Color(0xFF7C3AED) : const Color(0xFF2563EB);
    final (String state, Color stateColor) =
        session.complete
            ? ('គ្រប់', const Color(0xFF16A34A))
            : provisional
            ? ('កំពុងបន្ត', const Color(0xFF2F6FD6))
            : ('មិនគ្រប់', const Color(0xFFC94444));
    final hasSchedule =
        hasClock(session.scheduledIn) && hasClock(session.scheduledOut);

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A0F172A),
            blurRadius: 12,
            offset: Offset(0, 4),
          ),
        ],
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: accent.withAlpha(24),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  duty
                      ? Icons.nightlight_round
                      : session.name == 'afternoon'
                      ? Icons.wb_twilight_rounded
                      : Icons.wb_sunny_rounded,
                  size: 19,
                  color: accent,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'វេន${attendanceSessionLabel(session.name)}',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    if (hasSchedule)
                      Text(
                        'កាលវិភាគ ${kmClock(session.scheduledIn)} – ${kmClock(session.scheduledOut)}',
                        style: const TextStyle(
                          fontSize: 11.5,
                          color: Color(0xFF64748B),
                        ),
                      ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
                decoration: BoxDecoration(
                  color: stateColor.withAlpha(24),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  state,
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w800,
                    color: stateColor,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _Punch(
                  label: 'ចូល',
                  time: session.displayIn,
                  isIn: true,
                  flags: [
                    if (session.lateMinutes > 0)
                      (
                        'យឺត ${kmDuration(session.lateMinutes)}',
                        const Color(0xFFD99A00),
                      ),
                    if (session.earlyArrivalMinutes > 0)
                      (
                        'មកមុន ${kmDuration(session.earlyArrivalMinutes)}',
                        const Color(0xFF16A34A),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _Punch(
                  label: 'ចេញ',
                  time: session.displayOut,
                  isIn: false,
                  flags: [
                    if (session.earlyLeaveMinutes > 0)
                      (
                        'ចេញមុន ${kmDuration(session.earlyLeaveMinutes)}',
                        const Color(0xFFD9622B),
                      ),
                    if (session.overtimeMinutes > 0)
                      (
                        'បន្ថែម ${kmDuration(session.overtimeMinutes)}',
                        const Color(0xFF0E7490),
                      ),
                  ],
                ),
              ),
            ],
          ),
          if (hasSchedule) ...[
            const SizedBox(height: 14),
            _TimeBar(session: session, color: accent),
          ],
          const SizedBox(height: 10),
          Row(
            children: [
              const Icon(
                Icons.timer_outlined,
                size: 14,
                color: Color(0xFF94A3B8),
              ),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  'ធ្វើការ ${kmDuration(session.workedMinutes)}'
                  '${session.scheduledMinutes > 0 ? ' / ${kmDuration(session.scheduledMinutes)}' : ''}'
                  ' · ស្កេន ${kmDigits(session.punchCount)} ដង',
                  style: const TextStyle(
                    fontSize: 11.5,
                    color: Color(0xFF64748B),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Punch extends StatelessWidget {
  const _Punch({
    required this.label,
    required this.time,
    required this.isIn,
    this.flags = const [],
  });

  final String label;
  final String time;
  final bool isIn;
  final List<(String, Color)> flags;

  @override
  Widget build(BuildContext context) {
    final color = isIn ? const Color(0xFF16A34A) : const Color(0xFFEA580C);
    final continued = time == AttendanceSessionRecord.continuedLabel;
    final scanned = hasClock(time);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(
              isIn ? Icons.login_rounded : Icons.logout_rounded,
              size: 15,
              color: color,
            ),
            const SizedBox(width: 4),
            Text(
              label,
              style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
            ),
          ],
        ),
        const SizedBox(height: 2),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text(
            continued ? time : kmClock(time),
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w900,
              color:
                  scanned || continued
                      ? const Color(0xFF0F172A)
                      : const Color(0xFFCBD5E1),
            ),
          ),
        ),
        for (final (text, flagColor) in flags)
          Container(
            margin: const EdgeInsets.only(top: 4),
            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
            decoration: BoxDecoration(
              color: flagColor.withAlpha(24),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Text(
              text,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: flagColor,
              ),
            ),
          ),
      ],
    );
  }
}

/// The scheduled window as a light track, the time actually present on it
/// as a solid bar (late arrival / early leave show as gaps), and the
/// scheduled start and end underneath.
class _TimeBar extends StatelessWidget {
  const _TimeBar({required this.session, required this.color});

  final AttendanceSessionRecord session;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final start = clockMinutes(session.scheduledIn)!;
    var end = clockMinutes(session.scheduledOut)!;
    if (end <= start) end += 24 * 60; // a duty across midnight

    int? onScale(String clock) {
      final m = clockMinutes(clock);
      if (m == null) return null;
      // Times after midnight on a cross-day window belong to the next day.
      return m < start - 12 * 60 ? m + 24 * 60 : m;
    }

    final inAt = session.inContinued ? start : onScale(session.timeIn);
    final outAt = session.outContinued ? end : onScale(session.timeOut);
    final lo = <int>[
      start,
      if (inAt != null) inAt,
    ].reduce((a, b) => a < b ? a : b);
    final hi = <int>[
      end,
      if (outAt != null) outAt,
    ].reduce((a, b) => a > b ? a : b);
    final span = (hi - lo).clamp(1, 48 * 60);
    double at(int m) => (m - lo) / span;

    return Column(
      children: [
        LayoutBuilder(
          builder: (context, box) {
            final w = box.maxWidth;
            return SizedBox(
              height: 14,
              child: Stack(
                children: [
                  Positioned(
                    left: at(start) * w,
                    width: (at(end) - at(start)) * w,
                    top: 3,
                    bottom: 3,
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        color: color.withAlpha(30),
                        borderRadius: BorderRadius.circular(6),
                      ),
                    ),
                  ),
                  if (inAt != null && outAt != null && outAt > inAt)
                    Positioned(
                      left: at(inAt) * w,
                      width: (at(outAt) - at(inAt)) * w,
                      top: 0,
                      bottom: 0,
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          color: color,
                          borderRadius: BorderRadius.circular(7),
                        ),
                      ),
                    )
                  else
                    for (final m in [inAt, outAt])
                      if (m != null)
                        Positioned(
                          left: (at(m) * w - 5).clamp(0, w - 10),
                          top: 2,
                          child: Container(
                            width: 10,
                            height: 10,
                            decoration: BoxDecoration(
                              color: color,
                              shape: BoxShape.circle,
                            ),
                          ),
                        ),
                ],
              ),
            );
          },
        ),
        const SizedBox(height: 4),
        Row(
          children: [
            Text(
              kmClock(session.scheduledIn),
              style: const TextStyle(fontSize: 10.5, color: Color(0xFF94A3B8)),
            ),
            const Spacer(),
            Text(
              kmClock(session.scheduledOut),
              style: const TextStyle(fontSize: 10.5, color: Color(0xFF94A3B8)),
            ),
          ],
        ),
      ],
    );
  }
}
