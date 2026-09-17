import 'package:flutter/material.dart';

import '../../models/attendance_day_record.dart';
import 'attendance_status.dart';
import 'home_theme.dart';

/// Full-bleed weekday-accent gradient header for the Dashboard tab (Figma
/// "04 - Dashboard" / Profile header) — hamburger + app name + notification
/// bell, avatar + organization + two quick-action shortcuts, name/position
/// line, and two live status pills.
class DashboardProfileHeader extends StatelessWidget {
  const DashboardProfileHeader({
    super.key,
    required this.appName,
    required this.organization,
    required this.nameAndPosition,
    required this.initial,
    required this.unreadNotifications,
    required this.onMenuTap,
    required this.onNotificationsTap,
    required this.onAvatarTap,
    required this.onLanguageTap,
    required this.onHelpTap,
    required this.statusPrimaryLabel,
    required this.statusSecondaryLabel,
    this.profileImageUrl,
  });

  final String appName;
  final String organization;
  final String nameAndPosition;
  final String initial;
  final int unreadNotifications;
  final VoidCallback onMenuTap;
  final VoidCallback onNotificationsTap;
  final VoidCallback onAvatarTap;
  final VoidCallback onLanguageTap;
  final VoidCallback onHelpTap;
  final String statusPrimaryLabel;
  final String statusSecondaryLabel;
  final String? profileImageUrl;

  @override
  Widget build(BuildContext context) {
    final hasPhoto = profileImageUrl?.trim().isNotEmpty == true;

    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [dashboardHeaderStart, dashboardHeaderEnd],
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
        ),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(6, 8, 18, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  IconButton(
                    onPressed: onMenuTap,
                    icon: const Icon(Icons.menu_rounded, color: Colors.white),
                  ),
                  Expanded(
                    child: Text(
                      appName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Colors.white.withAlpha(184),
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  _NotificationBell(
                    count: unreadNotifications,
                    onTap: onNotificationsTap,
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  GestureDetector(
                    onTap: onAvatarTap,
                    child: Container(
                      width: 70,
                      height: 70,
                      padding: const EdgeInsets.all(3),
                      decoration: const BoxDecoration(
                        shape: BoxShape.circle,
                        color: Colors.white,
                      ),
                      child: ClipOval(
                        child:
                            hasPhoto
                                ? Image.network(
                                  profileImageUrl!,
                                  fit: BoxFit.cover,
                                  errorBuilder:
                                      (_, __, ___) => _Initial(initial),
                                )
                                : _Initial(initial),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 5,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white.withAlpha(217),
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            organization,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: dashboardHeaderEnd,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        const SizedBox(height: 8),
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            _HeaderCircleAction(
                              icon: Icons.language_rounded,
                              onTap: onLanguageTap,
                            ),
                            const SizedBox(width: 8),
                            _HeaderCircleAction(
                              icon: Icons.support_agent_rounded,
                              onTap: onHelpTap,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                nameAndPosition,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  _StatusPill(label: statusPrimaryLabel),
                  const SizedBox(width: 7),
                  _StatusPill(label: statusSecondaryLabel),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Initial extends StatelessWidget {
  const _Initial(this.initial);

  final String initial;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: dashboardHeaderStart,
      alignment: Alignment.center,
      child: Text(
        initial,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w900,
          fontSize: 20,
        ),
      ),
    );
  }
}

class _HeaderCircleAction extends StatelessWidget {
  const _HeaderCircleAction({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white.withAlpha(232),
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 34,
          height: 34,
          child: Icon(icon, size: 18, color: dashboardHeaderEnd),
        ),
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white.withAlpha(36),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withAlpha(112)),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 10,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _NotificationBell extends StatelessWidget {
  const _NotificationBell({required this.count, required this.onTap});

  final int count;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 38,
          height: 38,
          child: Stack(
            clipBehavior: Clip.none,
            alignment: Alignment.center,
            children: [
              const Icon(
                Icons.notifications_none_rounded,
                color: Colors.white,
                size: 21,
              ),
              if (count > 0)
                Positioned(
                  top: -2,
                  right: -2,
                  child: Container(
                    constraints: const BoxConstraints(minWidth: 18),
                    padding: const EdgeInsets.symmetric(horizontal: 3),
                    height: 18,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: const Color(0xFFE53935),
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(color: Colors.white, width: 2),
                    ),
                    child: Text(
                      count > 99 ? '99+' : '$count',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        height: 1,
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// One day in the Dashboard's week-day selector strip.
class DashboardWeekDay {
  const DashboardWeekDay({
    required this.date,
    required this.label,
    required this.percent,
    required this.isToday,
  });

  final DateTime date;
  final String label;
  final double percent;
  final bool isToday;
}

/// Horizontal week strip (Figma "Day selector"): each day shows its short
/// weekday label, date number, and worked-hours percent, with today
/// highlighted.
class DashboardWeekStrip extends StatelessWidget {
  const DashboardWeekStrip({super.key, required this.days});

  final List<DashboardWeekDay> days;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      color: Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (var i = 0; i < days.length; i++) ...[
              _DayCard(day: days[i]),
              if (i != days.length - 1) const SizedBox(width: 7),
            ],
          ],
        ),
      ),
    );
  }
}

class _DayCard extends StatelessWidget {
  const _DayCard({required this.day});

  final DashboardWeekDay day;

  @override
  Widget build(BuildContext context) {
    final selected = day.isToday;
    return Container(
      width: 55,
      height: 80,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: selected ? const Color(0xFFE7F4EE) : Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: selected ? dashboardHeaderStart : const Color(0xFFE7ECE9),
        ),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            day.label,
            style: TextStyle(
              fontSize: 9,
              fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
              color: selected ? dashboardHeaderStart : const Color(0xFF17352B),
            ),
          ),
          const SizedBox(height: 4),
          Container(
            width: 30,
            height: 30,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: selected ? dashboardHeaderStart : const Color(0xFFF0F2F1),
              shape: BoxShape.circle,
            ),
            child: Text(
              '${day.date.day}',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: selected ? Colors.white : const Color(0xFF17352B),
              ),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            '${day.percent.round()}%',
            style: const TextStyle(
              fontSize: 9,
              fontWeight: FontWeight.w700,
              color: Color(0xFF17352B),
            ),
          ),
        ],
      ),
    );
  }
}

/// One colored shortcut card in [DashboardActionsGrid] (Figma "Action
/// card") — an icon, a label, and an optional small status badge (used for
/// the QR scan card's "ONLINE" indicator).
class DashboardActionItem {
  const DashboardActionItem({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
    this.badgeLabel,
  });

  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  final String? badgeLabel;
}

/// Lays out 5 [DashboardActionItem]s as the Figma grid: a 2x2 block
/// followed by one full-width card.
class DashboardActionsGrid extends StatelessWidget {
  const DashboardActionsGrid({super.key, required this.actions});

  final List<DashboardActionItem> actions;

  @override
  Widget build(BuildContext context) {
    final rows = <Widget>[];
    for (var i = 0; i < actions.length - 1; i += 2) {
      final hasSecond = i + 1 < actions.length - 1;
      rows.add(
        Row(
          children: [
            Expanded(child: _ActionCard(item: actions[i])),
            if (hasSecond) ...[
              const SizedBox(width: 10),
              Expanded(child: _ActionCard(item: actions[i + 1])),
            ],
          ],
        ),
      );
      rows.add(const SizedBox(height: 10));
    }
    if (actions.isNotEmpty) {
      rows.add(_ActionCard(item: actions.last, height: 104));
    }

    return Column(children: rows);
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({required this.item, this.height = 116});

  final DashboardActionItem item;
  final double height;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: item.color,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: item.onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          height: height,
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            boxShadow: const [
              BoxShadow(
                color: Color(0x14173529),
                blurRadius: 12,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: Stack(
            children: [
              if (item.badgeLabel != null)
                Positioned(
                  top: 9,
                  right: 0,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0xFF35B76F),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      item.badgeLabel!,
                      style: const TextStyle(
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
              Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(item.icon, color: Colors.white, size: 32),
                    const SizedBox(height: 8),
                    Text(
                      item.label,
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        height: 1.3,
                      ),
                    ),
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

/// "Today's work status" card (Figma "Today's Work Status"): one mini
/// check-in/check-out pair per attendance session (morning/afternoon/duty),
/// each punch showing its own live status pill, plus a worked-hours
/// progress bar.
class DashboardWorkStatusCard extends StatelessWidget {
  const DashboardWorkStatusCard({
    super.key,
    required this.title,
    required this.sessions,
    required this.progressLabel,
    required this.progressValue,
    required this.percent,
    required this.checkInLabel,
    required this.checkOutLabel,
    required this.waitingLabel,
    required this.onTimeLabel,
    required this.lateLabel,
    required this.earlyLeaveLabel,
    this.emptyLabel,
    this.provisionalNote,
  });

  final String title;
  final List<AttendanceSessionRecord> sessions;
  final String progressLabel;
  final String progressValue;
  final double percent;
  final String checkInLabel;
  final String checkOutLabel;
  final String waitingLabel;
  final String onTimeLabel;
  final String lateLabel;
  final String earlyLeaveLabel;
  final String? emptyLabel;
  final String? provisionalNote;

  @override
  Widget build(BuildContext context) {
    final clamped = percent.clamp(0.0, 1.0);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: const [
          BoxShadow(
            color: Color(0x14173529),
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
              Icon(
                Icons.access_time_filled_rounded,
                size: 18,
                color: dashboardHeaderEnd,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                    color: dashboardHeaderEnd,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (sessions.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Text(
                emptyLabel ?? '-',
                style: const TextStyle(fontSize: 12, color: Color(0xFF66736F)),
              ),
            )
          else ...[
            if (provisionalNote != null) ...[
              Text(
                provisionalNote!,
                style: const TextStyle(fontSize: 12, color: Color(0xFF66736F)),
              ),
              const SizedBox(height: 10),
            ],
            for (final session in sessions) ...[
              _SessionRow(
                session: session,
                checkInLabel: checkInLabel,
                checkOutLabel: checkOutLabel,
                waitingLabel: waitingLabel,
                onTimeLabel: onTimeLabel,
                lateLabel: lateLabel,
                earlyLeaveLabel: earlyLeaveLabel,
              ),
              const SizedBox(height: 12),
            ],
          ],
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                progressLabel,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF17352B),
                ),
              ),
              Text(
                progressValue,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: dashboardHeaderStart,
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: clamped,
              minHeight: 8,
              backgroundColor: const Color(0xFFE7ECE9),
              valueColor: AlwaysStoppedAnimation<Color>(dashboardHeaderStart),
            ),
          ),
        ],
      ),
    );
  }
}

class _SessionRow extends StatelessWidget {
  const _SessionRow({
    required this.session,
    required this.checkInLabel,
    required this.checkOutLabel,
    required this.waitingLabel,
    required this.onTimeLabel,
    required this.lateLabel,
    required this.earlyLeaveLabel,
  });

  final AttendanceSessionRecord session;
  final String checkInLabel;
  final String checkOutLabel;
  final String waitingLabel;
  final String onTimeLabel;
  final String lateLabel;
  final String earlyLeaveLabel;

  @override
  Widget build(BuildContext context) {
    final checkInDone = session.timeIn.trim() != '-';
    final checkOutDone = session.timeOut.trim() != '-';

    final AttendanceStatusStyle checkInStatus =
        !checkInDone
            ? AttendanceStatusStyle(
              code: 'waiting',
              label: waitingLabel,
              shortLabel: waitingLabel,
              bg: const Color(0xFFFFF4E5),
              fg: const Color(0xFFF39C12),
            )
            : session.lateMinutes > 0
            ? AttendanceStatusStyle(
              code: 'late',
              label: lateLabel,
              shortLabel: lateLabel,
              bg: const Color(0xFFFFF5D9),
              fg: const Color(0xFFD99A00),
            )
            : AttendanceStatusStyle(
              code: 'on_time',
              label: onTimeLabel,
              shortLabel: onTimeLabel,
              bg: const Color(0xFFE7F4EE),
              fg: dashboardHeaderStart,
            );

    final AttendanceStatusStyle checkOutStatus =
        !checkOutDone
            ? AttendanceStatusStyle(
              code: 'waiting',
              label: waitingLabel,
              shortLabel: waitingLabel,
              bg: const Color(0xFFFFF4E5),
              fg: const Color(0xFFF39C12),
            )
            : session.earlyLeaveMinutes > 0
            ? AttendanceStatusStyle(
              code: 'early_leave',
              label: earlyLeaveLabel,
              shortLabel: earlyLeaveLabel,
              bg: const Color(0xFFFDECEC),
              fg: const Color(0xFFC94444),
            )
            : AttendanceStatusStyle(
              code: 'on_time',
              label: onTimeLabel,
              shortLabel: onTimeLabel,
              bg: const Color(0xFFE7F4EE),
              fg: dashboardHeaderStart,
            );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          attendanceSessionLabel(session.name),
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w700,
            color: dashboardHeaderEnd,
          ),
        ),
        const SizedBox(height: 6),
        Row(
          children: [
            Expanded(
              child: _PunchMiniCard(
                label: checkInLabel,
                value: session.timeIn,
                status: checkInStatus,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _PunchMiniCard(
                label: checkOutLabel,
                value: session.timeOut,
                status: checkOutStatus,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _PunchMiniCard extends StatelessWidget {
  const _PunchMiniCard({
    required this.label,
    required this.value,
    required this.status,
  });

  final String label;
  final String value;
  final AttendanceStatusStyle status;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F9F8),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(fontSize: 11, color: Color(0xFF64746E)),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: dashboardHeaderEnd,
            ),
          ),
          const SizedBox(height: 6),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(
              color: status.bg,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              status.label,
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w600,
                color: status.fg,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
