import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../models/attendance_day_record.dart';
import 'attendance_status.dart';
import 'home_theme.dart';

enum DashboardLiveState { notStarted, working, done }

/// Dashboard hero header: weekday-accent gradient with soft decorative
/// circles and rounded bottom corners. Top bar (menu, app name + today's
/// date, language, help, notification bell), a greeting with the avatar,
/// name and position, then organization / work-status / live-status chips.
class DashboardProfileHeader extends StatelessWidget {
  const DashboardProfileHeader({
    super.key,
    required this.appName,
    required this.organization,
    required this.name,
    required this.position,
    required this.initial,
    required this.unreadNotifications,
    required this.onMenuTap,
    required this.onNotificationsTap,
    required this.onAvatarTap,
    required this.onLanguageTap,
    required this.onHelpTap,
    required this.statusPrimaryLabel,
    required this.statusSecondaryLabel,
    this.liveState = DashboardLiveState.notStarted,
    this.profileImageUrl,
  });

  final String appName;
  final String organization;
  final String name;
  final String position;
  final String initial;
  final int unreadNotifications;
  final VoidCallback onMenuTap;
  final VoidCallback onNotificationsTap;
  final VoidCallback onAvatarTap;
  final VoidCallback onLanguageTap;
  final VoidCallback onHelpTap;
  final String statusPrimaryLabel;
  final String statusSecondaryLabel;

  /// Today's progress: the live status chip shows an amber dot (not
  /// started), a pulsing green dot (session open) or a check (done).
  final DashboardLiveState liveState;
  final String? profileImageUrl;

  String _greeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'អរុណសួស្តី';
    if (hour < 17) return 'ទិវាសួស្តី';
    return 'សាយណ្ហសួស្តី';
  }

  @override
  Widget build(BuildContext context) {
    final hasPhoto = profileImageUrl?.trim().isNotEmpty == true;

    return ClipRRect(
      borderRadius: const BorderRadius.vertical(bottom: Radius.circular(30)),
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [
              Color.lerp(dashboardHeaderStart, Colors.white, 0.05)!,
              dashboardHeaderEnd,
            ],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: Stack(
          children: [
            const Positioned(
              top: -50,
              right: -40,
              child: _Blob(size: 180, alpha: 24),
            ),
            const Positioned(
              bottom: -70,
              left: -50,
              child: _Blob(size: 170, alpha: 16),
            ),
            SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(14, 8, 14, 40),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        _GlassButton(
                          icon: Icons.menu_rounded,
                          onTap: onMenuTap,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            appName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 1.2,
                            ),
                          ),
                        ),
                        _GlassButton(
                          icon: Icons.translate_rounded,
                          onTap: onLanguageTap,
                        ),
                        const SizedBox(width: 8),
                        _GlassButton(
                          icon: Icons.support_agent_rounded,
                          onTap: onHelpTap,
                        ),
                        const SizedBox(width: 8),
                        _GlassButton(
                          icon: Icons.notifications_rounded,
                          onTap: onNotificationsTap,
                          badgeCount: unreadNotifications,
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: [
                        GestureDetector(
                          onTap: onAvatarTap,
                          child: Container(
                            width: 66,
                            height: 66,
                            padding: const EdgeInsets.all(3),
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: Colors.white,
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withAlpha(40),
                                  blurRadius: 12,
                                  offset: const Offset(0, 4),
                                ),
                              ],
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
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _greeting(),
                                style: const TextStyle(
                                  color: Color(0xDDFFFFFF),
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                name,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 19,
                                  fontWeight: FontWeight.w800,
                                  height: 1.2,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                position,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Color(0xE6FFFFFF),
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _HeaderChip(
                          icon: Icons.apartment_rounded,
                          label: organization,
                          solid: true,
                        ),
                        _HeaderChip(
                          icon: Icons.workspace_premium_rounded,
                          label: statusPrimaryLabel,
                        ),
                        _LiveChip(
                          label: statusSecondaryLabel,
                          state: liveState,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Blob extends StatelessWidget {
  const _Blob({required this.size, required this.alpha});

  final double size;
  final int alpha;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withAlpha(alpha),
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
          fontSize: 22,
        ),
      ),
    );
  }
}

/// Frosted rounded-square icon button for the header's top bar, with an
/// optional red count badge (unread notifications).
class _GlassButton extends StatelessWidget {
  const _GlassButton({
    required this.icon,
    required this.onTap,
    this.badgeCount = 0,
  });

  final IconData icon;
  final VoidCallback onTap;
  final int badgeCount;

  @override
  Widget build(BuildContext context) {
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Material(
          color: Colors.white.withAlpha(38),
          borderRadius: BorderRadius.circular(13),
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(13),
            child: SizedBox(
              width: 40,
              height: 40,
              child: Icon(icon, size: 21, color: Colors.white),
            ),
          ),
        ),
        if (badgeCount > 0)
          Positioned(
            top: -5,
            right: -5,
            child: IgnorePointer(
              child: Container(
                constraints: const BoxConstraints(minWidth: 19),
                height: 19,
                padding: const EdgeInsets.symmetric(horizontal: 4),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: const Color(0xFFE53935),
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(color: Colors.white, width: 2),
                ),
                child: Text(
                  badgeCount > 99 ? '99+' : '$badgeCount',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    height: 1,
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _HeaderChip extends StatelessWidget {
  const _HeaderChip({
    required this.icon,
    required this.label,
    this.solid = false,
  });

  final IconData icon;
  final String label;
  final bool solid;

  @override
  Widget build(BuildContext context) {
    final fg = solid ? dashboardHeaderEnd : Colors.white;
    return Container(
      padding: const EdgeInsets.fromLTRB(9, 5, 11, 5),
      decoration: BoxDecoration(
        color: solid ? Colors.white.withAlpha(235) : Colors.white.withAlpha(36),
        borderRadius: BorderRadius.circular(999),
        border: solid ? null : Border.all(color: Colors.white.withAlpha(90)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: fg),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: fg,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Live work-status chip: amber dot before check-in, pulsing green dot
/// while a session is open, white check once the day is done.
class _LiveChip extends StatefulWidget {
  const _LiveChip({required this.label, required this.state});

  final String label;
  final DashboardLiveState state;

  @override
  State<_LiveChip> createState() => _LiveChipState();
}

class _LiveChipState extends State<_LiveChip>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1400),
  )..repeat();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final active = widget.state == DashboardLiveState.working;
    final dot = active ? const Color(0xFF69F0AE) : const Color(0xFFFFD54F);
    return Container(
      padding: const EdgeInsets.fromLTRB(9, 5, 11, 5),
      decoration: BoxDecoration(
        color: Colors.white.withAlpha(36),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withAlpha(90)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            width: 14,
            height: 14,
            child:
                widget.state == DashboardLiveState.done
                    ? const Icon(
                      Icons.check_circle_rounded,
                      size: 14,
                      color: Colors.white,
                    )
                    : AnimatedBuilder(
                      animation: _controller,
                      builder: (context, _) {
                        final t = active ? _controller.value : 0.0;
                        return Stack(
                          alignment: Alignment.center,
                          children: [
                            if (active)
                              Container(
                                width: 7 + 7 * t,
                                height: 7 + 7 * t,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: dot.withAlpha((120 * (1 - t)).round()),
                                ),
                              ),
                            Container(
                              width: 7,
                              height: 7,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: dot,
                              ),
                            ),
                          ],
                        );
                      },
                    ),
          ),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              widget.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
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

/// Week strip on a white card that overlaps the header's rounded bottom:
/// today's full date, then each day shows its short label and its date inside a progress ring of
/// worked hours (percent below), today filled with the accent gradient.
class DashboardWeekStrip extends StatelessWidget {
  const DashboardWeekStrip({super.key, required this.days});

  final List<DashboardWeekDay> days;

  @override
  Widget build(BuildContext context) {
    return Transform.translate(
      offset: const Offset(0, -26),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(22),
            boxShadow: const [
              BoxShadow(
                color: Color(0x1A173529),
                blurRadius: 18,
                offset: Offset(0, 8),
              ),
            ],
          ),
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(10, 0, 10, 10),
                child: Row(
                  children: [
                    Icon(
                      Icons.calendar_month_rounded,
                      size: 17,
                      color: dashboardHeaderStart,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        khmerHeaderDate(DateTime.now()),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF17352B),
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Row(
                children: [
                  for (final day in days) Expanded(child: _DayCell(day: day)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DayCell extends StatelessWidget {
  const _DayCell({required this.day});

  final DashboardWeekDay day;

  @override
  Widget build(BuildContext context) {
    final today = day.isToday;
    final accent = dashboardHeaderStart;
    final fraction = (day.percent / 100).clamp(0.0, 1.0);
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        FittedBox(
          fit: BoxFit.scaleDown,
          child: Text(
            day.label,
            maxLines: 1,
            style: TextStyle(
              fontSize: 10,
              fontWeight: today ? FontWeight.w800 : FontWeight.w600,
              color: today ? accent : const Color(0xFF6F7C76),
            ),
          ),
        ),
        const SizedBox(height: 6),
        SizedBox(
          width: 38,
          height: 38,
          child: CustomPaint(
            painter: _RingPainter(
              fraction: fraction,
              color: accent,
              track: today ? accent.withAlpha(50) : const Color(0xFFE8EEEB),
            ),
            child: Center(
              child: Container(
                width: 30,
                height: 30,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient:
                      today
                          ? LinearGradient(
                            colors: [accent, dashboardHeaderEnd],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          )
                          : null,
                  color: today ? null : const Color(0xFFF4F7F5),
                ),
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(
                    '${day.date.day}',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: today ? Colors.white : const Color(0xFF17352B),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
        const SizedBox(height: 5),
        FittedBox(
          fit: BoxFit.scaleDown,
          child: Text(
            '${day.percent.round()}%',
            style: TextStyle(
              fontSize: 9.5,
              fontWeight: FontWeight.w700,
              color: today ? accent : const Color(0xFF6F7C76),
            ),
          ),
        ),
      ],
    );
  }
}

/// Worked-hours progress arc around a day's date.
class _RingPainter extends CustomPainter {
  _RingPainter({
    required this.fraction,
    required this.color,
    required this.track,
  });

  final double fraction;
  final Color color;
  final Color track;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final stroke =
        Paint()
          ..style = PaintingStyle.stroke
          ..strokeWidth = 3
          ..strokeCap = StrokeCap.round;
    canvas.drawArc(
      rect.deflate(1.5),
      0,
      2 * math.pi,
      false,
      stroke..color = track,
    );
    if (fraction > 0) {
      canvas.drawArc(
        rect.deflate(1.5),
        -math.pi / 2,
        2 * math.pi * fraction,
        false,
        stroke..color = color,
      );
    }
  }

  @override
  bool shouldRepaint(_RingPainter old) =>
      old.fraction != fraction || old.color != color || old.track != track;
}

/// One shortcut in [DashboardActionsGrid] — an icon, a label, an optional
/// subtitle (shown on the featured card) and an optional status badge (the
/// scan card's "ONLINE" indicator).
class DashboardActionItem {
  const DashboardActionItem({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
    this.subtitle,
    this.badgeLabel,
  });

  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  final String? subtitle;
  final String? badgeLabel;
}

/// The first action is the featured card (attendance scan, the most used);
/// the rest follow as compact tiles, two per row.
class DashboardActionsGrid extends StatelessWidget {
  const DashboardActionsGrid({super.key, required this.actions});

  final List<DashboardActionItem> actions;

  @override
  Widget build(BuildContext context) {
    if (actions.isEmpty) return const SizedBox.shrink();
    final rest = actions.skip(1).toList();
    return Column(
      children: [
        _FeaturedActionCard(item: actions.first),
        for (var i = 0; i < rest.length; i += 2) ...[
          const SizedBox(height: 12),
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(child: _ActionTile(item: rest[i])),
                const SizedBox(width: 12),
                Expanded(
                  child:
                      i + 1 < rest.length
                          ? _ActionTile(item: rest[i + 1])
                          : const SizedBox.shrink(),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

class _FeaturedActionCard extends StatefulWidget {
  const _FeaturedActionCard({required this.item});

  final DashboardActionItem item;

  @override
  State<_FeaturedActionCard> createState() => _FeaturedActionCardState();
}

class _FeaturedActionCardState extends State<_FeaturedActionCard>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1800),
  )..repeat();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final item = widget.item;
    final dark = Color.lerp(item.color, Colors.black, 0.28)!;
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: item.color.withAlpha(80),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(24),
            gradient: LinearGradient(
              colors: [item.color, dark],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
          ),
          child: InkWell(
            onTap: item.onTap,
            borderRadius: BorderRadius.circular(24),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 14, 16),
              child: Row(
                children: [
                  SizedBox(
                    width: 64,
                    height: 64,
                    child: AnimatedBuilder(
                      animation: _controller,
                      builder: (context, child) {
                        final t = _controller.value;
                        return Stack(
                          alignment: Alignment.center,
                          children: [
                            Container(
                              width: 52 + 12 * t,
                              height: 52 + 12 * t,
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(18 + 4 * t),
                                color: Colors.white.withAlpha(
                                  (60 * (1 - t)).round(),
                                ),
                              ),
                            ),
                            child!,
                          ],
                        );
                      },
                      child: Container(
                        width: 52,
                        height: 52,
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(18),
                          color: Colors.white.withAlpha(46),
                          border: Border.all(color: Colors.white.withAlpha(90)),
                        ),
                        child: Icon(item.icon, color: Colors.white, size: 28),
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.label,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            height: 1.25,
                          ),
                        ),
                        if (item.subtitle != null) ...[
                          const SizedBox(height: 3),
                          Text(
                            item.subtitle!,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Color(0xDDFFFFFF),
                              fontSize: 11.5,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                        if (item.badgeLabel != null) ...[
                          const SizedBox(height: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFF35B76F),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              item.badgeLabel!,
                              style: const TextStyle(
                                fontSize: 9.5,
                                fontWeight: FontWeight.w800,
                                color: Colors.white,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white.withAlpha(46),
                    ),
                    child: const Icon(
                      Icons.arrow_forward_rounded,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({required this.item});

  final DashboardActionItem item;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        boxShadow: const [
          BoxShadow(
            color: Color(0x10173529),
            blurRadius: 12,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Material(
        color: Colors.white,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(color: item.color.withAlpha(40)),
        ),
        child: InkWell(
          onTap: item.onTap,
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(15),
                    gradient: LinearGradient(
                      colors: [
                        item.color,
                        Color.lerp(item.color, Colors.black, 0.25)!,
                      ],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: item.color.withAlpha(70),
                        blurRadius: 8,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Icon(item.icon, color: Colors.white, size: 23),
                ),
                const SizedBox(height: 10),
                Text(
                  item.label,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xFF17352B),
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                    height: 1.3,
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
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        boxShadow: const [
          BoxShadow(
            color: Color(0x14173529),
            blurRadius: 16,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          DashboardCardHeader(
            icon: Icons.schedule_rounded,
            title: title,
            trailing: Icon(
              Icons.chevron_right_rounded,
              color: dashboardHeaderStart,
            ),
          ),
          const SizedBox(height: 14),
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
              Container(
                padding: const EdgeInsets.fromLTRB(10, 8, 12, 8),
                decoration: BoxDecoration(
                  color: dashboardHeaderStart.withAlpha(18),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      Icons.info_outline_rounded,
                      size: 16,
                      color: dashboardHeaderStart,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        provisionalNote!,
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF4F5E59),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
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
            children: [
              Expanded(
                child: Text(
                  progressLabel,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF17352B),
                  ),
                ),
              ),
              Text(
                progressValue,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  color: dashboardHeaderStart,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          // Gradient bar that animates to today's worked-hours share.
          TweenAnimationBuilder<double>(
            tween: Tween(begin: 0, end: clamped),
            duration: const Duration(milliseconds: 900),
            curve: Curves.easeOutCubic,
            builder:
                (context, value, _) => Container(
                  height: 10,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE9EFEC),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  alignment: Alignment.centerLeft,
                  child: FractionallySizedBox(
                    widthFactor: value,
                    child: Container(
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(999),
                        gradient: LinearGradient(
                          colors: [
                            Color.lerp(
                              dashboardHeaderStart,
                              Colors.white,
                              0.25,
                            )!,
                            dashboardHeaderStart,
                          ],
                        ),
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

/// Card title row shared by the Dashboard cards: gradient icon tile, title,
/// optional trailing widget.
class DashboardCardHeader extends StatelessWidget {
  const DashboardCardHeader({
    super.key,
    required this.icon,
    required this.title,
    this.trailing,
  });

  final IconData icon;
  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            gradient: LinearGradient(
              colors: [dashboardHeaderStart, dashboardHeaderEnd],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
          ),
          child: Icon(icon, size: 20, color: Colors.white),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: Color(0xFF17352B),
            ),
          ),
        ),
        if (trailing != null) trailing!,
      ],
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
    final checkInDone = session.displayIn.trim() != '-';
    final checkOutDone = session.displayOut.trim() != '-';

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
                value: session.displayIn,
                status: checkInStatus,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _PunchMiniCard(
                label: checkOutLabel,
                value: session.displayOut,
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
