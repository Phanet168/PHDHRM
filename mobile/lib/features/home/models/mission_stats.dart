import 'mission_summary.dart';

/// Statuses of missions that actually go ahead (approved, under way or done).
const _wentAhead = <String>{'approved', 'in_progress', 'completed'};

DateTime? _day(String value) {
  final parsed = DateTime.tryParse(value.trim());
  return parsed == null
      ? null
      : DateTime(parsed.year, parsed.month, parsed.day);
}

/// One month of the employee's mission days.
class MissionMonth {
  const MissionMonth({
    required this.month,
    required this.done,
    required this.planned,
    required this.missions,
  });

  final int month;

  /// Days already spent on mission (up to and including today).
  final int done;

  /// Approved days still ahead.
  final int planned;

  /// The missions with at least one day in this month.
  final List<MissionSummary> missions;

  int get total => done + planned;
}

/// A mission's days that fall inside one month.
int missionDaysInMonth(MissionSummary mission, int year, int month) {
  final start = _day(mission.startDate);
  final end = _day(mission.endDate);
  if (start == null || end == null || end.isBefore(start)) return 0;
  final first = DateTime(year, month);
  final last = DateTime(year, month + 1, 0);
  final from = start.isAfter(first) ? start : first;
  final to = end.isBefore(last) ? end : last;
  return to.isBefore(from) ? 0 : to.difference(from).inDays + 1;
}

/// The signed-in employee's own mission days for one year: per month, per
/// mission type, and what is under way or coming next. Only missions that
/// went ahead and that the employee is on count; a day is counted once even
/// if two missions touch it.
class MissionDayStats {
  const MissionDayStats._({
    required this.year,
    required this.months,
    required this.missionCount,
    required this.byType,
    required this.years,
    required this.pendingRequests,
    this.current,
    this.next,
  });

  factory MissionDayStats.compute(
    List<MissionSummary> missions, {
    required int employeeId,
    required int year,
    required DateTime today,
  }) {
    final day0 = DateTime(today.year, today.month, today.day);
    final mine = <(MissionSummary, DateTime, DateTime)>[];
    for (final mission in missions) {
      if (!_wentAhead.contains(mission.status.trim().toLowerCase())) continue;
      if (!mission.includes(employeeId)) continue;
      final start = _day(mission.startDate);
      final end = _day(mission.endDate);
      if (start == null || end == null || end.isBefore(start)) continue;
      mine.add((mission, start, end));
    }

    final doneDays = List<Set<int>>.generate(12, (_) => <int>{});
    final plannedDays = List<Set<int>>.generate(12, (_) => <int>{});
    final monthMissions = List<List<MissionSummary>>.generate(
      12,
      (_) => <MissionSummary>[],
    );
    final typeDays = <String, Set<DateTime>>{};
    var missionCount = 0;
    final years = <int>{today.year};
    MissionSummary? current;
    (MissionSummary, DateTime)? next;

    for (final (mission, start, end) in mine) {
      for (var y = start.year; y <= end.year; y++) {
        years.add(y);
      }
      if (!day0.isBefore(start) && !day0.isAfter(end)) current ??= mission;
      if (start.isAfter(day0) && (next == null || start.isBefore(next.$2))) {
        next = (mission, start);
      }
      if (start.year > year || end.year < year) continue;
      missionCount++;
      final type =
          mission.missionTypeLabel.isEmpty
              ? 'បេសកកម្មផ្សេងៗ'
              : mission.missionTypeLabel;
      for (
        var d = start;
        !d.isAfter(end);
        d = DateTime(d.year, d.month, d.day + 1)
      ) {
        if (d.year != year) continue;
        (d.isAfter(day0) ? plannedDays : doneDays)[d.month - 1].add(d.day);
        typeDays.putIfAbsent(type, () => <DateTime>{}).add(d);
        final list = monthMissions[d.month - 1];
        if (list.isEmpty || !identical(list.last, mission)) list.add(mission);
      }
    }

    return MissionDayStats._(
      year: year,
      months: <MissionMonth>[
        for (var m = 0; m < 12; m++)
          MissionMonth(
            month: m + 1,
            done: doneDays[m].length,
            // A day can't be both behind and ahead of today.
            planned: plannedDays[m].difference(doneDays[m]).length,
            missions: monthMissions[m],
          ),
      ],
      missionCount: missionCount,
      byType:
          (typeDays.entries.map((e) => (e.key, e.value.length)).toList()
            ..sort((a, b) => b.$2.compareTo(a.$2))),
      years: years.toList()..sort((a, b) => b.compareTo(a)),
      pendingRequests:
          missions
              .where((m) => m.status.trim().toLowerCase() == 'pending')
              .length,
      current: current,
      next: next?.$1,
    );
  }

  final int year;
  final List<MissionMonth> months;

  /// Missions with at least one day in [year].
  final int missionCount;

  /// (mission type label, days in [year]), most days first.
  final List<(String, int)> byType;

  /// Years with mission days (and the current year), newest first.
  final List<int> years;

  /// The employee's requests still waiting for approval (any year).
  final int pendingRequests;

  /// The mission under way today, if any.
  final MissionSummary? current;

  /// The soonest approved mission still to start.
  final MissionSummary? next;

  int get doneDays => months.fold(0, (sum, m) => sum + m.done);
  int get plannedDays => months.fold(0, (sum, m) => sum + m.planned);
  int get totalDays => doneDays + plannedDays;
}
