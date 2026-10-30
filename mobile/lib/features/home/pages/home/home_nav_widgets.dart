import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'home_theme.dart';

/// One grouped section in the Home drawer (Figma "section-account" /
/// "section-management" / "section-system"): a tinted icon + title header
/// over a column of [DrawerActionRow]s.
class DrawerSectionCard extends StatelessWidget {
  const DrawerSectionCard({
    super.key,
    required this.icon,
    required this.title,
    required this.rows,
  });

  final IconData icon;
  final String title;
  final List<Widget> rows;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE7ECE9)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0D173529),
            blurRadius: 8,
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
                width: 20,
                height: 20,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: const Color(0xFFE7F4EE),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, size: 13, color: dashboardHeaderStart),
              ),
              const SizedBox(width: 8),
              Text(
                title,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                  color: dashboardHeaderStart,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          for (var i = 0; i < rows.length; i++) ...[
            rows[i],
            if (i != rows.length - 1) const SizedBox(height: 8),
          ],
        ],
      ),
    );
  }
}

/// One tappable row inside a [DrawerSectionCard]: a tinted icon square, a
/// label, an optional red count badge, and a trailing chevron.
class DrawerActionRow extends StatelessWidget {
  const DrawerActionRow({
    super.key,
    required this.icon,
    required this.label,
    required this.onTap,
    this.badgeCount = 0,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final int badgeCount;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFFF7F9F8),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          height: 48,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
              Container(
                width: 32,
                height: 32,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: const Color(0xFFE7F4EE),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, size: 18, color: dashboardHeaderStart),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF17352B),
                  ),
                ),
              ),
              if (badgeCount > 0) ...[
                const SizedBox(width: 8),
                Container(
                  width: 22,
                  height: 22,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(
                    color: Color(0xFFE84D3D),
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    badgeCount > 99 ? '99+' : '$badgeCount',
                    style: const TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                    ),
                  ),
                ),
              ],
              const SizedBox(width: 4),
              const Icon(
                Icons.chevron_right_rounded,
                size: 18,
                color: Color(0xFF9CA9A4),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// The drawer's bottom "log out" row (Figma "Logout") — a red-tinted card,
/// distinct from the neutral [DrawerActionRow]s above it.
class DrawerLogoutRow extends StatelessWidget {
  const DrawerLogoutRow({super.key, required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFFFFF1F1),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          height: 52,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFF3C7C7)),
          ),
          child: Row(
            children: [
              Container(
                width: 32,
                height: 32,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: const Color(0xFFE84D3D),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(
                  Icons.logout_rounded,
                  size: 18,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFFE84D3D),
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

/// The app's Figma "footer bar": 5 slots — Home, Attendance, a raised
/// center QR-scan button, Work (missions) and Account (profile). Indices
/// map 1:1 to [onTap]'s argument; index 2 (scan) is a standalone action and
/// is never reported as the "current" tab.
///
/// The white bar is cut by a smooth notch that cradles the gradient scan
/// button. A tinted pill marks the active tab and, on a switch, stretches
/// toward the new tab and snaps after it, while that tab's icon pops.
class HomeBottomNavigation extends StatefulWidget {
  const HomeBottomNavigation({
    super.key,
    required this.currentIndex,
    required this.onTap,
    this.showScanButton = true,
  });

  /// Which of the 4 non-scan tabs (0, 1, 3, 4) is currently active, or null.
  final int? currentIndex;
  final Future<void> Function(int index) onTap;

  /// When false, the raised center QR-scan button (and its slot) is omitted
  /// and the bar becomes a plain 4-tab row — used on screens where scanning
  /// isn't a relevant action.
  final bool showScanButton;

  static const List<String> _labels = <String>[
    'ទំព័រដើម',
    'វត្តមាន',
    'ស្កេន',
    'ការងារ',
    'គណនី',
  ];

  static const List<IconData> _icons = <IconData>[
    Icons.space_dashboard_outlined,
    Icons.calendar_month_outlined,
    Icons.qr_code_scanner_rounded,
    Icons.work_outline_rounded,
    Icons.person_outline_rounded,
  ];

  static const List<IconData> _activeIcons = <IconData>[
    Icons.space_dashboard_rounded,
    Icons.calendar_month_rounded,
    Icons.qr_code_scanner_rounded,
    Icons.work_rounded,
    Icons.person_rounded,
  ];

  static const double _barHeight = 64;
  static const double _scanSize = 56;

  /// How far the scan button's center sits above the bar's top edge.
  static const double _scanLift = 2;
  static const double _notchRadius = _scanSize / 2 + 6;

  static const double _pillTop = 6;
  static const double _pillWidth = 54;
  static const double _pillHeight = 28;

  @override
  State<HomeBottomNavigation> createState() => _HomeBottomNavigationState();
}

class _HomeBottomNavigationState extends State<HomeBottomNavigation>
    with SingleTickerProviderStateMixin {
  /// The tab a bar showed when one of its tabs was tapped. The bar on the
  /// screen that tap pushes starts its pill there and slides it over, so a
  /// pushed tab screen animates like an in-place switch.
  static ({int index, DateTime at})? _handoff;

  late final AnimationController _slide = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 520),
    value: 1,
  );

  /// The pill travels from slot [_fromSlot] to [_toSlot] as [_slide] runs.
  late double _fromSlot;
  late double _toSlot;

  static int? _takeHandoff() {
    final handoff = _handoff;
    _handoff = null;
    if (handoff == null ||
        DateTime.now().difference(handoff.at) > const Duration(seconds: 2)) {
      return null;
    }
    return handoff.index;
  }

  /// Column of tab [index]; without the scan slot, tabs 3 and 4 shift left.
  int? _slotOf(int? index) {
    if (index == null) return null;
    return widget.showScanButton || index < 2 ? index : index - 1;
  }

  @override
  void initState() {
    super.initState();
    final target = _slotOf(widget.currentIndex);
    final from = target == null ? null : _slotOf(_takeHandoff());
    _fromSlot = (from ?? target ?? 0).toDouble();
    _toSlot = (target ?? _fromSlot).toDouble();
    if (from != null && from != target) {
      _slide.forward(from: 0);
    }
  }

  @override
  void didUpdateWidget(HomeBottomNavigation oldWidget) {
    super.didUpdateWidget(oldWidget);
    final target = _slotOf(widget.currentIndex);
    if (widget.currentIndex == oldWidget.currentIndex || target == null) {
      return;
    }
    // Switched in place: start from wherever the pill is right now.
    _takeHandoff();
    _fromSlot = _pillCenterSlot(_slide.value);
    _toSlot = target.toDouble();
    _slide.forward(from: 0);
  }

  @override
  void dispose() {
    _slide.dispose();
    super.dispose();
  }

  void _tap(int index) {
    final current = widget.currentIndex;
    if (index != current && current != null) {
      _handoff = (index: current, at: DateTime.now());
    }
    widget.onTap(index);
  }

  double _pillCenterSlot(double t) {
    final p =
        (Curves.easeOutCubic.transform(t) +
            Curves.easeInOutCubic.transform(t)) /
        2;
    return _fromSlot + (_toSlot - _fromSlot) * p;
  }

  /// The sliding pill: its leading edge races ahead (easeOut) while the
  /// trailing edge lags (easeInOut), so it stretches mid-way then snaps.
  Widget _buildPill(double slotWidth, Color accent) {
    return AnimatedBuilder(
      animation: _slide,
      builder: (context, _) {
        final t = _slide.value;
        final fast = Curves.easeOutCubic.transform(t);
        final slow = Curves.easeInOutCubic.transform(t);
        final movingRight = _toSlot >= _fromSlot;
        double center(double p) =>
            (_fromSlot + (_toSlot - _fromSlot) * p + 0.5) * slotWidth;
        final left = center(movingRight ? slow : fast);
        final right = center(movingRight ? fast : slow);
        const half = HomeBottomNavigation._pillWidth / 2;

        return Positioned(
          left: left - half,
          width: right - left + HomeBottomNavigation._pillWidth,
          top: HomeBottomNavigation._pillTop,
          height: HomeBottomNavigation._pillHeight,
          child: AnimatedOpacity(
            duration: const Duration(milliseconds: 200),
            opacity: widget.currentIndex == null ? 0 : 1,
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    accent.withValues(alpha: 0.20),
                    accent.withValues(alpha: 0.10),
                  ],
                ),
                borderRadius: BorderRadius.circular(
                  HomeBottomNavigation._pillHeight / 2,
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final accent = homeAccentColor();
    final showScan = widget.showScanButton;
    final notchRadius = showScan ? HomeBottomNavigation._notchRadius : 0.0;
    const tabIndexes = [0, 1, 3, 4];

    final bar = CustomPaint(
      painter: _NavBarPainter(
        notchRadius: notchRadius,
        notchLift: HomeBottomNavigation._scanLift,
      ),
      child: SizedBox(
        height: HomeBottomNavigation._barHeight,
        child: ClipPath(
          clipper: _NavBarClipper(
            notchRadius: notchRadius,
            notchLift: HomeBottomNavigation._scanLift,
          ),
          child: LayoutBuilder(
            builder: (context, constraints) {
              final slotWidth = constraints.maxWidth / (showScan ? 5 : 4);
              return Stack(
                children: [
                  _buildPill(slotWidth, accent),
                  Row(
                    children: [
                      for (final index in tabIndexes) ...[
                        Expanded(
                          child: _NavBarItem(
                            icon: HomeBottomNavigation._icons[index],
                            activeIcon:
                                HomeBottomNavigation._activeIcons[index],
                            label: HomeBottomNavigation._labels[index],
                            color: accent,
                            selected: widget.currentIndex == index,
                            onTap: () => _tap(index),
                          ),
                        ),
                        if (showScan && index == 1)
                          Expanded(
                            child: _NavBarScanLabel(
                              label: HomeBottomNavigation._labels[2],
                              color: accent,
                              onTap: () => _tap(2),
                            ),
                          ),
                      ],
                    ],
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );

    if (!showScan) {
      return SafeArea(top: false, child: bar);
    }

    const scanSize = HomeBottomNavigation._scanSize;
    return SafeArea(
      top: false,
      child: SizedBox(
        height:
            HomeBottomNavigation._barHeight +
            scanSize / 2 +
            HomeBottomNavigation._scanLift,
        child: Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.topCenter,
          children: [
            Positioned(left: 0, right: 0, bottom: 0, child: bar),
            Positioned(
              top: 0,
              child: _NavBarScanButton(
                icon: HomeBottomNavigation._icons[2],
                label: HomeBottomNavigation._labels[2],
                size: scanSize,
                color: accent,
                onTap: () => _tap(2),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// The bar's outline: rounded top corners and — when [notchRadius] > 0 — a
/// circular notch with filleted shoulders that cradles the scan button,
/// its circle centered [notchLift] above the top edge.
Path _navBarOutline(Size size, double notchRadius, double notchLift) {
  const corner = 24.0;
  const fillet = 10.0;
  final w = size.width;
  final h = size.height;
  final path =
      Path()
        ..moveTo(0, h)
        ..lineTo(0, corner)
        ..arcToPoint(
          const Offset(corner, 0),
          radius: const Radius.circular(corner),
        );
  if (notchRadius > 0) {
    final cx = w / 2;
    final r = notchRadius;
    // Each fillet circle touches the top edge and the notch circle; dx is
    // its center's distance from cx, (tx, ty) the point it meets the notch.
    final dx = math.sqrt(
      math.pow(r + fillet, 2) - math.pow(fillet + notchLift, 2),
    );
    final k = r / (r + fillet);
    final tx = dx * k;
    final ty = -notchLift + (fillet + notchLift) * k;
    path
      ..lineTo(cx - dx, 0)
      ..arcToPoint(Offset(cx - tx, ty), radius: const Radius.circular(fillet))
      ..arcToPoint(
        Offset(cx + tx, ty),
        radius: Radius.circular(r),
        clockwise: false,
      )
      ..arcToPoint(Offset(cx + dx, 0), radius: const Radius.circular(fillet));
  }
  path
    ..lineTo(w - corner, 0)
    ..arcToPoint(Offset(w, corner), radius: const Radius.circular(corner))
    ..lineTo(w, h)
    ..close();
  return path;
}

/// Paints the white bar with a soft upward shadow.
class _NavBarPainter extends CustomPainter {
  const _NavBarPainter({required this.notchRadius, required this.notchLift});

  final double notchRadius;
  final double notchLift;

  @override
  void paint(Canvas canvas, Size size) {
    final outline = _navBarOutline(size, notchRadius, notchLift);
    canvas.drawPath(
      outline.shift(const Offset(0, -2)),
      Paint()
        ..color = const Color(0x260F2A22)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 10),
    );
    canvas.drawPath(outline, Paint()..color = Colors.white);
  }

  @override
  bool shouldRepaint(_NavBarPainter oldDelegate) =>
      oldDelegate.notchRadius != notchRadius ||
      oldDelegate.notchLift != notchLift;
}

/// Keeps the sliding pill and tap ripples inside the bar, out of the notch.
class _NavBarClipper extends CustomClipper<Path> {
  const _NavBarClipper({required this.notchRadius, required this.notchLift});

  final double notchRadius;
  final double notchLift;

  @override
  Path getClip(Size size) => _navBarOutline(size, notchRadius, notchLift);

  @override
  bool shouldReclip(_NavBarClipper oldClipper) =>
      oldClipper.notchRadius != notchRadius ||
      oldClipper.notchLift != notchLift;
}

class _NavBarItem extends StatelessWidget {
  const _NavBarItem({
    required this.icon,
    required this.activeIcon,
    required this.label,
    required this.color,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final IconData activeIcon;
  final String label;
  final Color color;
  final bool selected;
  final VoidCallback onTap;

  static const _duration = Duration(milliseconds: 280);

  @override
  Widget build(BuildContext context) {
    final fg = selected ? color : const Color(0xFF9AA5A1);

    return Semantics(
      selected: selected,
      child: InkResponse(
        onTap: () {
          HapticFeedback.selectionClick();
          onTap();
        },
        radius: 34,
        highlightColor: Colors.transparent,
        splashColor: color.withValues(alpha: 0.12),
        // The icon row lines up with the bar's sliding pill; only the label
        // scales down under a large system font.
        child: Column(
          children: [
            const SizedBox(height: HomeBottomNavigation._pillTop),
            SizedBox(
              height: HomeBottomNavigation._pillHeight,
              child: Center(
                child: AnimatedSlide(
                  duration: _duration,
                  curve: Curves.easeOutCubic,
                  offset: selected ? const Offset(0, -0.06) : Offset.zero,
                  child: AnimatedScale(
                    duration: Duration(milliseconds: selected ? 650 : 220),
                    curve: selected ? Curves.elasticOut : Curves.easeOut,
                    scale: selected ? 1.14 : 1,
                    child: AnimatedSwitcher(
                      duration: _duration,
                      transitionBuilder:
                          (child, animation) => FadeTransition(
                            opacity: animation,
                            child: RotationTransition(
                              turns: Tween<double>(
                                begin: -0.08,
                                end: 0,
                              ).animate(animation),
                              child: child,
                            ),
                          ),
                      child: Icon(
                        selected ? activeIcon : icon,
                        key: ValueKey<bool>(selected),
                        size: 22,
                        color: fg,
                      ),
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 2),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(2, 0, 2, 4),
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  child: AnimatedDefaultTextStyle(
                    duration: _duration,
                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                      color: fg,
                      fontFamilyFallback: const [
                        'Noto Sans Khmer',
                        'Public Sans',
                      ],
                    ),
                    child: Text(label, maxLines: 1),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Swaps the Home screen's tab bodies shared-axis style: the old body fades
/// out sliding away, the new one fades in from the side of its tab
/// ([direction] +1 = a tab to the right, -1 = to the left).
class HomeTabTransition extends StatelessWidget {
  const HomeTabTransition({
    super.key,
    required this.tabKey,
    required this.direction,
    required this.child,
  });

  final Object tabKey;
  final int direction;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    final currentKey = ValueKey<Object>(tabKey);

    return AnimatedSwitcher(
      duration:
          reduceMotion ? Duration.zero : const Duration(milliseconds: 380),
      reverseDuration:
          reduceMotion ? Duration.zero : const Duration(milliseconds: 220),
      switchInCurve: Curves.easeOutCubic,
      switchOutCurve: Curves.easeInCubic,
      transitionBuilder: (child, animation) {
        // Outgoing children run this animation in reverse (1 → 0), so they
        // slide toward the opposite side while fading out.
        final incoming = child.key == currentKey;
        final dx = 0.08 * direction * (incoming ? 1 : -1);
        return FadeTransition(
          opacity: animation,
          child: SlideTransition(
            position: Tween<Offset>(
              begin: Offset(dx, 0),
              end: Offset.zero,
            ).animate(animation),
            child: child,
          ),
        );
      },
      child: KeyedSubtree(key: currentKey, child: child),
    );
  }
}

/// Route for screens opened from a bottom-bar tab (e.g. Attendance): a quick
/// fade and settle-in instead of the platform's drill-down transition, so it
/// feels like switching tabs.
class HomeTabRoute<T> extends PageRouteBuilder<T> {
  HomeTabRoute({required WidgetBuilder builder})
    : super(
        transitionDuration: const Duration(milliseconds: 360),
        reverseTransitionDuration: const Duration(milliseconds: 260),
        pageBuilder:
            (context, animation, secondaryAnimation) => builder(context),
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          final curved = CurvedAnimation(
            parent: animation,
            curve: Curves.easeOutCubic,
            reverseCurve: Curves.easeInCubic,
          );
          return FadeTransition(
            opacity: curved,
            child: ScaleTransition(
              scale: Tween<double>(begin: 0.97, end: 1).animate(curved),
              child: child,
            ),
          );
        },
      );
}

/// The scan slot's caption, sitting in the bar under the notch; tapping it
/// scans too, so the whole center column is one target.
class _NavBarScanLabel extends StatelessWidget {
  const _NavBarScanLabel({
    required this.label,
    required this.color,
    required this.onTap,
  });

  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Align(
        alignment: Alignment.bottomCenter,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(2, 0, 2, 6),
          child: SizedBox(
            height: 24,
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(
                label,
                maxLines: 1,
                style: TextStyle(
                  fontSize: 10.5,
                  fontWeight: FontWeight.w800,
                  color: color,
                  fontFamilyFallback: const ['Noto Sans Khmer', 'Public Sans'],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Gradient scan button with a white rim and a slow ripple ring that keeps
/// drawing the eye (paused when the system asks for reduced motion).
class _NavBarScanButton extends StatefulWidget {
  const _NavBarScanButton({
    required this.icon,
    required this.label,
    required this.size,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final double size;
  final Color color;
  final VoidCallback onTap;

  @override
  State<_NavBarScanButton> createState() => _NavBarScanButtonState();
}

class _NavBarScanButtonState extends State<_NavBarScanButton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2200),
  );

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.disableAnimationsOf(context)) {
      _pulse.stop();
    } else if (!_pulse.isAnimating) {
      _pulse.repeat();
    }
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final size = widget.size;
    final color = widget.color;

    return Semantics(
      button: true,
      label: widget.label,
      child: SizedBox(
        width: size,
        height: size,
        child: Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.center,
          children: [
            AnimatedBuilder(
              animation: _pulse,
              builder: (context, ring) {
                final t = Curves.easeOut.transform(_pulse.value);
                return Transform.scale(
                  scale: 1 + 0.32 * t,
                  child: Opacity(opacity: 0.45 * (1 - t), child: ring),
                );
              },
              child: Container(
                width: size,
                height: size,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: color, width: 3),
                ),
              ),
            ),
            DecoratedBox(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: color.withValues(alpha: 0.45),
                    blurRadius: 14,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: Material(
                shape: const CircleBorder(
                  side: BorderSide(color: Colors.white, width: 3.5),
                ),
                clipBehavior: Clip.antiAlias,
                child: Ink(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [
                        Color.lerp(color, Colors.white, 0.12)!,
                        Color.lerp(color, Colors.black, 0.28)!,
                      ],
                    ),
                  ),
                  child: InkWell(
                    onTap: () {
                      HapticFeedback.lightImpact();
                      widget.onTap();
                    },
                    splashColor: Colors.white24,
                    child: SizedBox(
                      width: size,
                      height: size,
                      child: Icon(widget.icon, color: Colors.white, size: 26),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
