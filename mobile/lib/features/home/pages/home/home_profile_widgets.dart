import 'package:flutter/material.dart';

import 'home_theme.dart';

class HomeProfileRow {
  const HomeProfileRow({required this.label, required this.value});

  final String label;
  final String value;
}

class ProfileLoadingSkeleton extends StatelessWidget {
  const ProfileLoadingSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFFF3F8F6), Color(0xFFFAFCFB)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: ListView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(14, 16, 14, 24),
        children: const [
          SkeletonBlock(height: 264, radius: 24),
          SizedBox(height: 14),
          SkeletonBlock(height: 104, radius: 20),
          SizedBox(height: 10),
          SkeletonBlock(height: 170, radius: 20),
          SizedBox(height: 14),
          SkeletonBlock(height: 104, radius: 20),
          SizedBox(height: 10),
          SkeletonBlock(height: 132, radius: 20),
          SizedBox(height: 14),
          SkeletonBlock(height: 104, radius: 20),
        ],
      ),
    );
  }
}

class SkeletonBlock extends StatelessWidget {
  const SkeletonBlock({super.key, required this.height, required this.radius});

  final double height;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(radius),
        gradient: const LinearGradient(
          colors: [Color(0xFFE8F2EE), Color(0xFFF2F7F5), Color(0xFFE8F2EE)],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
        border: Border.all(color: const Color(0xFFDDE9E4)),
      ),
    );
  }
}

class HomeInfoBadge extends StatelessWidget {
  const HomeInfoBadge({super.key, required this.icon, required this.text});

  final IconData icon;
  final String? text;

  @override
  Widget build(BuildContext context) {
    if (text == null || text!.isEmpty) return const SizedBox.shrink();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withAlpha(214),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFFD9E9E1)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: homeAccentColor()),
          const SizedBox(width: 6),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 220),
            child: Text(
              text!,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 12,
                color: Color(0xFF173C33),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class ProfileHighlightChip {
  const ProfileHighlightChip({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;
}

class ProfileHeroCard extends StatelessWidget {
  const ProfileHeroCard({
    super.key,
    required this.avatar,
    required this.name,
    required this.position,
    required this.department,
    required this.role,
    required this.chips,
    required this.badges,
  });

  final Widget avatar;
  final String name;
  final String position;
  final String department;
  final String? role;
  final List<ProfileHighlightChip> chips;
  final List<Widget> badges;

  @override
  Widget build(BuildContext context) {
    final visibleBadges =
        badges.where((widget) => widget is! SizedBox).toList();
    final primary = homeAccentColor();

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: LinearGradient(
          colors: [
            Color.lerp(primary, Colors.black, 0.30)!,
            Color.lerp(primary, Colors.black, 0.45)!,
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        border: Border.all(color: const Color(0x33000000)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1A102A24),
            blurRadius: 18,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.all(2),
                  decoration: BoxDecoration(
                    color: Colors.white.withAlpha(34),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: avatar,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'ប្រវត្តិមន្ត្រី',
                        style: TextStyle(
                          color: Color(0xFFD8EAF0),
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.6,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        name,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 21,
                          fontWeight: FontWeight.w800,
                          height: 1.15,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        position,
                        style: const TextStyle(
                          color: Color(0xFFF4F8FA),
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        department,
                        style: const TextStyle(
                          color: Color(0xFFD7E5EA),
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      if ((role ?? '').trim().isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 5,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0x1FFFFFFF),
                            borderRadius: BorderRadius.circular(999),
                            border: Border.all(color: const Color(0x44FFFFFF)),
                          ),
                          child: Text(
                            role!.trim(),
                            style: const TextStyle(
                              color: Color(0xFFF1FBFF),
                              fontSize: 11.5,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            LayoutBuilder(
              builder: (context, constraints) {
                final isCompact = constraints.maxWidth < 380;
                final isUltraCompact = constraints.maxWidth < 340;
                final crossAxisCount = isCompact ? 1 : 2;
                final cardHeight =
                    isUltraCompact ? 78.0 : (isCompact ? 82.0 : 88.0);

                return GridView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: chips.length,
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: crossAxisCount,
                    crossAxisSpacing: 8,
                    mainAxisSpacing: 8,
                    mainAxisExtent: cardHeight,
                  ),
                  itemBuilder: (context, index) {
                    final chip = chips[index];
                    return Container(
                      padding:
                          isUltraCompact
                              ? const EdgeInsets.fromLTRB(10, 8, 10, 8)
                              : const EdgeInsets.fromLTRB(10, 10, 10, 8),
                      decoration: BoxDecoration(
                        color: Colors.white.withAlpha(24),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: Colors.white.withAlpha(36)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Icon(chip.icon, size: 16, color: Colors.white),
                          Text(
                            chip.label,
                            style: const TextStyle(
                              color: Color(0xFFCFE3EA),
                              fontSize: 10.3,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          Text(
                            chip.value,
                            maxLines: isCompact ? 2 : 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 12.5,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    );
                  },
                );
              },
            ),
            if (visibleBadges.isNotEmpty) ...[
              const SizedBox(height: 12),
              Wrap(spacing: 8, runSpacing: 8, children: visibleBadges),
            ],
          ],
        ),
      ),
    );
  }
}

class ProfileSubsection {
  const ProfileSubsection({required this.label, required this.rows});
  final String label;
  final List<HomeProfileRow> rows;
}

class ProfileSection extends StatefulWidget {
  const ProfileSection({
    super.key,
    required this.title,
    required this.subtitle,
    this.icon,
    this.rows,
    this.subsections,
    this.initiallyExpanded = false,
  });

  final IconData? icon;
  final String title;
  final String subtitle;
  final List<HomeProfileRow>? rows;
  final List<ProfileSubsection>? subsections;
  final bool initiallyExpanded;

  @override
  State<ProfileSection> createState() => _ProfileSectionState();
}

class _ProfileSectionState extends State<ProfileSection> {
  bool _expanded = false;

  @override
  void initState() {
    super.initState();
    _expanded = widget.initiallyExpanded;
  }

  @override
  Widget build(BuildContext context) {
    // Determine which rows to display
    final List<HomeProfileRow> mainRows = widget.rows ?? [];
    final mainVisible = mainRows.where((r) => r.value.isNotEmpty).toList();
    final subsVisible =
        widget.subsections
            ?.where((s) => s.rows.any((r) => r.value.isNotEmpty))
            .toList() ??
        [];

    if (mainVisible.isEmpty && subsVisible.isEmpty) {
      return const SizedBox.shrink();
    }

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: homeAccentColor().withAlpha(45)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x10142721),
            blurRadius: 14,
            offset: Offset(0, 5),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(18, 14, 16, 14),
            decoration: BoxDecoration(
              color: homeAccentColor().withAlpha(18),
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(20),
                topRight: Radius.circular(20),
              ),
            ),
            child: Row(
              children: [
                if (widget.icon != null) ...[
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: homeAccentColor().withAlpha(32),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: Icon(
                      widget.icon,
                      size: 20,
                      color: homeAccentColor(),
                    ),
                  ),
                  const SizedBox(width: 12),
                ],
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        widget.title,
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 15.5,
                          color: homeAccentColor(),
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        widget.subtitle,
                        style: TextStyle(
                          fontSize: 11.5,
                          color: homeAccentColor().withAlpha(180),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                InkWell(
                  onTap: () => setState(() => _expanded = !_expanded),
                  borderRadius: BorderRadius.circular(999),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    width: 34,
                    height: 34,
                    decoration: BoxDecoration(
                      color:
                          _expanded
                              ? homeAccentColor().withAlpha(55)
                              : homeAccentColor().withAlpha(28),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      _expanded
                          ? Icons.keyboard_arrow_up_rounded
                          : Icons.keyboard_arrow_down_rounded,
                      color: homeAccentColor(),
                      size: 22,
                    ),
                  ),
                ),
              ],
            ),
          ),
          AnimatedCrossFade(
            duration: const Duration(milliseconds: 190),
            firstCurve: Curves.easeOut,
            secondCurve: Curves.easeIn,
            sizeCurve: Curves.easeInOut,
            crossFadeState:
                _expanded
                    ? CrossFadeState.showSecond
                    : CrossFadeState.showFirst,
            firstChild: const SizedBox.shrink(),
            secondChild: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Divider(height: 1, color: Color(0xFFE7EFEB)),
                const SizedBox(height: 6),
                for (final row in mainVisible) _buildRow(row),
                if (subsVisible.isNotEmpty)
                  for (final subsection in subsVisible) ...[
                    _buildSubsection(subsection),
                    const SizedBox(height: 2),
                  ],
                const SizedBox(height: 6),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRow(HomeProfileRow row) {
    final screenWidth = MediaQuery.of(context).size.width;
    final labelWidth = screenWidth < 360 ? 104.0 : 126.0;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 0),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 10),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  width: labelWidth,
                  child: Text(
                    row.label,
                    style: const TextStyle(
                      color: Color(0xFF5E746D),
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    row.value,
                    textAlign: TextAlign.right,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 12.8,
                      color: Color(0xFF163A31),
                      height: 1.35,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: Color(0xFFE8EFEB)),
        ],
      ),
    );
  }

  Widget _buildSubsection(ProfileSubsection sub) {
    final visible = sub.rows.where((r) => r.value.isNotEmpty).toList();
    if (visible.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
            decoration: BoxDecoration(
              color: homeAccentColor().withAlpha(28),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Text(
              sub.label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w800,
                color: homeAccentColor(),
                letterSpacing: 0.2,
              ),
            ),
          ),
        ),
        for (int i = 0; i < visible.length; i++) _buildRow(visible[i]),
      ],
    );
  }
}
