import 'package:flutter/material.dart';

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
class HomeBottomNavigation extends StatelessWidget {
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
    Icons.dashboard_outlined,
    Icons.calendar_month_outlined,
    Icons.qr_code_scanner_rounded,
    Icons.work_outline,
    Icons.person_outline,
  ];

  static const double _barHeight = 60;
  static const double _raiseHeight = 22;

  @override
  Widget build(BuildContext context) {
    final accent = homeAccentColor();
    const tabIndexes = [0, 1, 3, 4];

    final bar = Container(
      height: _barHeight,
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 12,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: Row(
        children: [
          for (final index in tabIndexes) ...[
            Expanded(
              child: _NavBarItem(
                icon: _icons[index],
                label: _labels[index],
                selected: currentIndex == index,
                onTap: () => onTap(index),
              ),
            ),
            if (showScanButton && index == 1)
              const Expanded(child: SizedBox.shrink()),
          ],
        ],
      ),
    );

    if (!showScanButton) {
      return SafeArea(top: false, child: bar);
    }

    return SafeArea(
      top: false,
      child: SizedBox(
        height: _barHeight + _raiseHeight,
        child: Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.topCenter,
          children: [
            Positioned(left: 0, right: 0, bottom: 0, child: bar),
            Positioned(
              top: 0,
              child: _NavBarScanButton(
                icon: _icons[2],
                label: _labels[2],
                color: accent,
                onTap: () => onTap(2),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _NavBarItem extends StatelessWidget {
  const _NavBarItem({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? const Color(0xFF17201E) : const Color(0xFF9CA3AF);

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 22, color: color),
            const SizedBox(height: 4),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 10,
                fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                color: color,
                fontFamilyFallback: const ['Noto Sans Khmer', 'Public Sans'],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _NavBarScanButton extends StatelessWidget {
  const _NavBarScanButton({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                color: color,
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 3),
                boxShadow: [
                  BoxShadow(
                    color: color.withValues(alpha: 0.35),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Icon(icon, color: Colors.white, size: 24),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w800,
                color: color,
                fontFamilyFallback: const ['Noto Sans Khmer', 'Public Sans'],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
