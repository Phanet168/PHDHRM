import 'package:flutter/material.dart';

import '../../models/mission_stats.dart';
import '../../models/mission_summary.dart';
import 'home_theme.dart';

String _km(int value) =>
    value
        .toString()
        .split('')
        .map((c) => int.tryParse(c) == null ? c : '០១២៣៤៥៦៧៨៩'[int.parse(c)])
        .join();

const _months = <String>[
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

DateTime? _day(String value) {
  final parsed = DateTime.tryParse(value.trim());
  return parsed == null
      ? null
      : DateTime(parsed.year, parsed.month, parsed.day);
}

/// "២ – ៥ វិច្ឆិកា" / "២៨ តុលា – ២ វិច្ឆិកា" / "២ វិច្ឆិកា".
String _shortRange(MissionSummary mission) {
  final start = _day(mission.startDate);
  final end = _day(mission.endDate);
  if (start == null || end == null) return '';
  if (start == end) return '${_km(start.day)} ${_months[start.month - 1]}';
  if (start.year == end.year && start.month == end.month) {
    return '${_km(start.day)} – ${_km(end.day)} ${_months[end.month - 1]}';
  }
  return '${_km(start.day)} ${_months[start.month - 1]} – '
      '${_km(end.day)} ${_months[end.month - 1]}';
}

/// The Missions tab's personal dashboard: the employee's own mission days in
/// a year (done and still planned), per month (tap a month for its
/// missions), per mission type, and the mission under way or coming next.
class MissionDashboard extends StatefulWidget {
  const MissionDashboard({
    super.key,
    required this.missions,
    required this.employeeId,
    required this.onOpenMission,
    this.today,
  });

  final List<MissionSummary> missions;
  final int employeeId;
  final ValueChanged<MissionSummary> onOpenMission;

  /// Fixed "today" for tests; the real clock otherwise.
  final DateTime? today;

  @override
  State<MissionDashboard> createState() => _MissionDashboardState();
}

class _MissionDashboardState extends State<MissionDashboard> {
  late DateTime _today;
  late int _year;
  late int _month;

  @override
  void initState() {
    super.initState();
    _today = widget.today ?? DateTime.now();
    _year = _today.year;
    _month = _today.month;
  }

  MissionDayStats _stats(int year) => MissionDayStats.compute(
    widget.missions,
    employeeId: widget.employeeId,
    year: year,
    today: _today,
  );

  void _changeYear(int year) {
    final stats = _stats(year);
    setState(() {
      _year = year;
      // The current month this year; otherwise the busiest month.
      _month =
          year == _today.year
              ? _today.month
              : (stats.months.toList()
                    ..sort((a, b) => b.total.compareTo(a.total)))
                  .first
                  .month;
    });
  }

  @override
  Widget build(BuildContext context) {
    final stats = _stats(_year);
    final years = stats.years;
    final index = years.indexOf(_year);
    final older =
        index >= 0 && index < years.length - 1 ? years[index + 1] : null;
    final newer = index > 0 ? years[index - 1] : null;
    final accent = homeAccentColor();
    final highlight = stats.current ?? stats.next;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        _DaysHero(
          stats: stats,
          monthDays:
              _year == _today.year
                  ? stats.months[_today.month - 1].total
                  : null,
          onOlder: older == null ? null : () => _changeYear(older),
          onNewer: newer == null ? null : () => _changeYear(newer),
        ),
        if (highlight != null) ...<Widget>[
          const SizedBox(height: 12),
          _NowNextCard(
            mission: highlight,
            underway: identical(highlight, stats.current),
            today: _today,
            onTap: () => widget.onOpenMission(highlight),
          ),
        ],
        const SizedBox(height: 12),
        _Card(
          title: 'ថ្ងៃបេសកកម្មតាមខែ',
          icon: Icons.bar_chart_rounded,
          trailing: Wrap(
            spacing: 10,
            children: <Widget>[
              _Legend(color: accent, label: 'បានបំពេញ'),
              _Legend(color: accent.withAlpha(90), label: 'គ្រោងទុក'),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              _MonthBars(
                key: ValueKey<int>(_year),
                months: stats.months,
                selected: _month,
                currentMonth: _year == _today.year ? _today.month : null,
                accent: accent,
                onSelect: (month) => setState(() => _month = month),
              ),
              const SizedBox(height: 12),
              _MonthDetail(
                year: _year,
                month: stats.months[_month - 1],
                accent: accent,
                onOpenMission: widget.onOpenMission,
              ),
            ],
          ),
        ),
        if (stats.byType.isNotEmpty) ...<Widget>[
          const SizedBox(height: 12),
          _Card(
            title: 'តាមប្រភេទបេសកកម្ម',
            icon: Icons.donut_small_rounded,
            child: _TypeBars(rows: stats.byType, total: stats.totalDays),
          ),
        ],
      ],
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Year hero
// ─────────────────────────────────────────────────────────────────────────────

class _DaysHero extends StatelessWidget {
  const _DaysHero({
    required this.stats,
    required this.monthDays,
    required this.onOlder,
    required this.onNewer,
  });

  final MissionDayStats stats;

  /// This month's days, only while showing the current year.
  final int? monthDays;
  final VoidCallback? onOlder;
  final VoidCallback? onNewer;

  @override
  Widget build(BuildContext context) {
    final total = stats.totalDays;
    final doneShare = total == 0 ? 0.0 : stats.doneDays / total;

    return ClipRRect(
      borderRadius: BorderRadius.circular(26),
      child: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[dashboardHeaderStart, dashboardHeaderEnd],
          ),
        ),
        child: Stack(
          children: <Widget>[
            const Positioned(
              right: -40,
              top: -50,
              child: _Blob(size: 170, opacity: 0.10),
            ),
            const Positioned(
              left: -30,
              bottom: -60,
              child: _Blob(size: 140, opacity: 0.07),
            ),
            Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: Colors.white.withAlpha(40),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: const Icon(
                          Icons.luggage_rounded,
                          color: Colors.white,
                          size: 24,
                        ),
                      ),
                      const SizedBox(width: 10),
                      const Expanded(
                        child: Text(
                          'បេសកកម្មរបស់ខ្ញុំ',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      _YearSwitch(
                        year: stats.year,
                        onOlder: onOlder,
                        onNewer: onNewer,
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: <Widget>[
                      Flexible(
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          alignment: Alignment.bottomLeft,
                          child: Text(
                            _km(total),
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 46,
                              fontWeight: FontWeight.w900,
                              height: 1.1,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        flex: 3,
                        child: Padding(
                          padding: const EdgeInsets.only(bottom: 6),
                          child: Text(
                            'ថ្ងៃបេសកកម្ម ឆ្នាំ${_km(stats.year)}',
                            style: TextStyle(
                              color: Colors.white.withAlpha(225),
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(6),
                    child: Stack(
                      children: <Widget>[
                        Container(height: 8, color: Colors.white.withAlpha(50)),
                        TweenAnimationBuilder<double>(
                          tween: Tween<double>(begin: 0, end: doneShare),
                          duration: const Duration(milliseconds: 800),
                          curve: Curves.easeOutCubic,
                          builder:
                              (context, value, _) => FractionallySizedBox(
                                widthFactor: value,
                                child: Container(
                                  height: 8,
                                  color: Colors.white,
                                ),
                              ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    decoration: BoxDecoration(
                      color: Colors.white.withAlpha(30),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: Colors.white.withAlpha(45)),
                    ),
                    child: IntrinsicHeight(
                      child: Row(
                        children: <Widget>[
                          monthDays == null
                              ? _HeroStat(
                                icon: Icons.flag_rounded,
                                value: stats.missionCount,
                                unit: 'ដង',
                                label: 'បេសកកម្ម',
                              )
                              : _HeroStat(
                                icon: Icons.today_rounded,
                                value: monthDays!,
                                unit: 'ថ្ងៃ',
                                label: 'ខែនេះ',
                              ),
                          _divider(),
                          _HeroStat(
                            icon: Icons.task_alt_rounded,
                            value: stats.doneDays,
                            unit: 'ថ្ងៃ',
                            label: 'បានបំពេញ',
                          ),
                          _divider(),
                          _HeroStat(
                            icon: Icons.event_rounded,
                            value: stats.plannedDays,
                            unit: 'ថ្ងៃ',
                            label: 'គ្រោងទុក',
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    <String>[
                      'បេសកកម្ម ${_km(stats.missionCount)} ដង',
                      if (stats.pendingRequests > 0)
                        'សំណើរង់ចាំ ${_km(stats.pendingRequests)}',
                    ].join(' · '),
                    style: TextStyle(
                      color: Colors.white.withAlpha(215),
                      fontSize: 12,
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

  Widget _divider() => VerticalDivider(
    width: 1,
    thickness: 1,
    color: Colors.white.withAlpha(50),
  );
}

class _YearSwitch extends StatelessWidget {
  const _YearSwitch({
    required this.year,
    required this.onOlder,
    required this.onNewer,
  });

  final int year;
  final VoidCallback? onOlder;
  final VoidCallback? onNewer;

  Widget _arrow(IconData icon, VoidCallback? onTap, String tooltip) =>
      IconButton(
        onPressed: onTap,
        tooltip: tooltip,
        visualDensity: VisualDensity.compact,
        constraints: const BoxConstraints(minWidth: 34, minHeight: 34),
        padding: EdgeInsets.zero,
        iconSize: 20,
        color: Colors.white,
        disabledColor: Colors.white.withAlpha(70),
        icon: Icon(icon),
      );

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withAlpha(36),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          _arrow(Icons.chevron_left_rounded, onOlder, 'ឆ្នាំមុន'),
          Text(
            _km(year),
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w800,
              fontSize: 13,
            ),
          ),
          _arrow(Icons.chevron_right_rounded, onNewer, 'ឆ្នាំបន្ទាប់'),
        ],
      ),
    );
  }
}

class _HeroStat extends StatelessWidget {
  const _HeroStat({
    required this.icon,
    required this.value,
    required this.unit,
    required this.label,
  });

  final IconData icon;
  final int value;
  final String unit;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4),
        child: Column(
          children: <Widget>[
            Icon(icon, color: Colors.white, size: 18),
            const SizedBox(height: 4),
            FittedBox(
              fit: BoxFit.scaleDown,
              child: Text.rich(
                TextSpan(
                  children: <InlineSpan>[
                    TextSpan(
                      text: _km(value),
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    TextSpan(
                      text: ' $unit',
                      style: const TextStyle(fontSize: 11),
                    ),
                  ],
                ),
                style: const TextStyle(color: Colors.white),
              ),
            ),
            Text(
              label,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: Colors.white.withAlpha(215),
                fontSize: 11.5,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Blob extends StatelessWidget {
  const _Blob({required this.size, required this.opacity});

  final double size;
  final double opacity;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withValues(alpha: opacity),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Under way / next
// ─────────────────────────────────────────────────────────────────────────────

class _NowNextCard extends StatelessWidget {
  const _NowNextCard({
    required this.mission,
    required this.underway,
    required this.today,
    required this.onTap,
  });

  final MissionSummary mission;
  final bool underway;
  final DateTime today;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final start = _day(mission.startDate);
    final end = _day(mission.endDate);
    final day0 = DateTime(today.year, today.month, today.day);
    final color = underway ? const Color(0xFF7C3AED) : const Color(0xFF0EA5E9);
    final String badge;
    double? progress;
    if (underway && start != null && end != null) {
      final total = end.difference(start).inDays + 1;
      final nth = day0.difference(start).inDays + 1;
      badge = 'ថ្ងៃទី ${_km(nth)}/${_km(total)}';
      progress = nth / total;
    } else if (start != null) {
      final away = start.difference(day0).inDays;
      badge = away == 1 ? 'ថ្ងៃស្អែក' : 'ក្នុង ${_km(away)} ថ្ងៃ';
    } else {
      badge = '';
    }

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(20),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          decoration: BoxDecoration(
            border: Border(left: BorderSide(color: color, width: 5)),
          ),
          padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: color.withAlpha(26),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      underway
                          ? Icons.directions_car_rounded
                          : Icons.upcoming_rounded,
                      color: color,
                      size: 21,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          underway ? 'កំពុងបំពេញបេសកកម្ម' : 'បេសកកម្មបន្ទាប់',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: color,
                          ),
                        ),
                        Text(
                          mission.title.isEmpty ? '-' : mission.title,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 14.5,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: Color(0xFF94A3B8),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 12,
                runSpacing: 4,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: <Widget>[
                  if (mission.destination.isNotEmpty)
                    _Meta(icon: Icons.place_rounded, text: mission.destination),
                  _Meta(
                    icon: Icons.calendar_month_rounded,
                    text: _shortRange(mission),
                  ),
                  if (badge.isNotEmpty)
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: color.withAlpha(24),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        badge,
                        style: TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w800,
                          color: color,
                        ),
                      ),
                    ),
                ],
              ),
              if (progress != null) ...<Widget>[
                const SizedBox(height: 10),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: progress.clamp(0.0, 1.0),
                    minHeight: 6,
                    color: color,
                    backgroundColor: color.withAlpha(30),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(icon, size: 14, color: const Color(0xFF94A3B8)),
        const SizedBox(width: 3),
        Flexible(
          child: Text(
            text,
            style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
          ),
        ),
      ],
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Cards, month bars, month detail, type bars
// ─────────────────────────────────────────────────────────────────────────────

class _Card extends StatelessWidget {
  const _Card({
    required this.title,
    required this.icon,
    required this.child,
    this.trailing,
  });

  final String title;
  final IconData icon;
  final Widget child;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x0A0F172A),
            blurRadius: 14,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(icon, size: 18, color: homeAccentColor()),
              const SizedBox(width: 7),
              Expanded(
                flex: 3,
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF0F172A),
                  ),
                ),
              ),
              if (trailing != null)
                Flexible(
                  flex: 2,
                  child: Align(
                    alignment: Alignment.centerRight,
                    child: FittedBox(fit: BoxFit.scaleDown, child: trailing),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 14),
          child,
        ],
      ),
    );
  }
}

class _Legend extends StatelessWidget {
  const _Legend({required this.color, required this.label});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(3),
          ),
        ),
        const SizedBox(width: 4),
        Text(
          label,
          style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
        ),
      ],
    );
  }
}

class _MonthBars extends StatelessWidget {
  const _MonthBars({
    super.key,
    required this.months,
    required this.selected,
    required this.currentMonth,
    required this.accent,
    required this.onSelect,
  });

  final List<MissionMonth> months;
  final int selected;
  final int? currentMonth;
  final Color accent;
  final ValueChanged<int> onSelect;

  static const _trackHeight = 110.0;

  @override
  Widget build(BuildContext context) {
    final max = months.fold<int>(0, (m, e) => e.total > m ? e.total : m);
    final scale = max == 0 ? 0.0 : _trackHeight / max;

    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0, end: 1),
      duration: const Duration(milliseconds: 700),
      curve: Curves.easeOutCubic,
      builder:
          (context, grow, _) => Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: <Widget>[
              for (final month in months)
                Expanded(
                  child: Semantics(
                    button: true,
                    selected: month.month == selected,
                    excludeSemantics: true,
                    label:
                        'ខែ${_months[month.month - 1]} ${_km(month.total)} ថ្ងៃ',
                    onTap: () => onSelect(month.month),
                    child: GestureDetector(
                      behavior: HitTestBehavior.opaque,
                      onTap: () => onSelect(month.month),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 2),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: <Widget>[
                            SizedBox(
                              height: 16,
                              child:
                                  month.total == 0
                                      ? null
                                      : FittedBox(
                                        fit: BoxFit.scaleDown,
                                        child: Text(
                                          _km(month.total),
                                          style: TextStyle(
                                            fontSize: 11,
                                            fontWeight: FontWeight.w800,
                                            color:
                                                month.month == selected
                                                    ? accent
                                                    : const Color(0xFF64748B),
                                          ),
                                        ),
                                      ),
                            ),
                            Container(
                              height: _trackHeight,
                              alignment: Alignment.bottomCenter,
                              decoration: BoxDecoration(
                                color:
                                    month.month == selected
                                        ? accent.withAlpha(18)
                                        : Colors.transparent,
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: FractionallySizedBox(
                                widthFactor: 0.62,
                                child: Column(
                                  mainAxisAlignment: MainAxisAlignment.end,
                                  children: <Widget>[
                                    if (month.planned > 0)
                                      Container(
                                        height: month.planned * scale * grow,
                                        decoration: BoxDecoration(
                                          color: accent.withAlpha(90),
                                          borderRadius: BorderRadius.vertical(
                                            top: const Radius.circular(5),
                                            bottom: Radius.circular(
                                              month.done > 0 ? 0 : 3,
                                            ),
                                          ),
                                        ),
                                      ),
                                    if (month.done > 0)
                                      Container(
                                        height: month.done * scale * grow,
                                        decoration: BoxDecoration(
                                          color: accent,
                                          borderRadius: BorderRadius.vertical(
                                            top: Radius.circular(
                                              month.planned > 0 ? 0 : 5,
                                            ),
                                            bottom: const Radius.circular(3),
                                          ),
                                        ),
                                      ),
                                    if (month.total == 0)
                                      Container(
                                        height: 3,
                                        decoration: BoxDecoration(
                                          color: const Color(0xFFE2E8F0),
                                          borderRadius: BorderRadius.circular(
                                            2,
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                            ),
                            const SizedBox(height: 6),
                            SizedBox(
                              height: 18,
                              child: FittedBox(
                                fit: BoxFit.scaleDown,
                                child: Text(
                                  _km(month.month),
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight:
                                        month.month == selected ||
                                                month.month == currentMonth
                                            ? FontWeight.w800
                                            : FontWeight.w500,
                                    color:
                                        month.month == selected
                                            ? accent
                                            : month.month == currentMonth
                                            ? const Color(0xFF0F172A)
                                            : const Color(0xFF94A3B8),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
    );
  }
}

class _MonthDetail extends StatelessWidget {
  const _MonthDetail({
    required this.year,
    required this.month,
    required this.accent,
    required this.onOpenMission,
  });

  final int year;
  final MissionMonth month;
  final Color accent;
  final ValueChanged<MissionSummary> onOpenMission;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFEEF2F7)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                flex: 3,
                child: Text(
                  'ខែ${_months[month.month - 1]} ${_km(year)}',
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF0F172A),
                  ),
                ),
              ),
              Flexible(
                child: Align(
                  alignment: Alignment.centerRight,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: accent.withAlpha(26),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      '${_km(month.total)} ថ្ងៃ',
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w800,
                        color: accent,
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            month.total == 0
                ? 'មិនមានបេសកកម្មក្នុងខែនេះ'
                : <String>[
                  'បេសកកម្ម ${_km(month.missions.length)} ដង',
                  if (month.done > 0) 'បានបំពេញ ${_km(month.done)} ថ្ងៃ',
                  if (month.planned > 0) 'គ្រោងទុក ${_km(month.planned)} ថ្ងៃ',
                ].join(' · '),
            style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
          ),
          for (final mission in month.missions) ...<Widget>[
            const SizedBox(height: 8),
            Material(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              child: InkWell(
                borderRadius: BorderRadius.circular(12),
                onTap: () => onOpenMission(mission),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(10, 8, 6, 8),
                  child: Row(
                    children: <Widget>[
                      Container(
                        width: 4,
                        height: 34,
                        decoration: BoxDecoration(
                          color: accent,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text(
                              mission.title.isEmpty ? '-' : mission.title,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF0F172A),
                              ),
                            ),
                            Text(
                              _shortRange(mission),
                              style: const TextStyle(
                                fontSize: 11.5,
                                color: Color(0xFF64748B),
                              ),
                            ),
                          ],
                        ),
                      ),
                      Text(
                        '${_km(missionDaysInMonth(mission, year, month.month))} ថ្ងៃ',
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF334155),
                        ),
                      ),
                      const Icon(
                        Icons.chevron_right_rounded,
                        size: 18,
                        color: Color(0xFFCBD5E1),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _TypeBars extends StatelessWidget {
  const _TypeBars({required this.rows, required this.total});

  final List<(String, int)> rows;
  final int total;

  static const _colors = <Color>[
    Color(0xFF3B82F6),
    Color(0xFF10B981),
    Color(0xFF8B5CF6),
    Color(0xFFF59E0B),
    Color(0xFFEC4899),
    Color(0xFF0EA5E9),
  ];

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        for (var i = 0; i < rows.length; i++) ...<Widget>[
          if (i > 0) const SizedBox(height: 12),
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  rows[i].$1,
                  style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF334155),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Text(
                '${_km(rows[i].$2)} ថ្ងៃ',
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w800,
                  color: _colors[i % _colors.length],
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: total == 0 ? 0 : rows[i].$2 / total,
              minHeight: 8,
              color: _colors[i % _colors.length],
              backgroundColor: _colors[i % _colors.length].withAlpha(30),
            ),
          ),
        ],
      ],
    );
  }
}
