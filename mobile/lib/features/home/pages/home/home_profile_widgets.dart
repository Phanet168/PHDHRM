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
          // Flexible so a narrow screen ellipsizes instead of overflowing.
          Flexible(
            child: ConstrainedBox(
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

/// Profile header: gradient card with the avatar in an animated ring,
/// name/position/unit centred, and the highlight facts on a white panel.
/// Nothing has a fixed height, so large system text grows the card instead
/// of overflowing it.
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
        borderRadius: BorderRadius.circular(28),
        boxShadow: [
          BoxShadow(
            color: primary.withAlpha(60),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(28),
        child: DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [
                Color.lerp(primary, Colors.white, 0.06)!,
                Color.lerp(primary, Colors.black, 0.38)!,
              ],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
          ),
          child: Stack(
            children: [
              const Positioned(
                top: -60,
                right: -50,
                child: _Blob(size: 190, alpha: 22),
              ),
              const Positioned(
                top: 120,
                left: -70,
                child: _Blob(size: 160, alpha: 14),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 22, 16, 16),
                child: Column(
                  children: [
                    _AvatarRing(child: avatar),
                    const SizedBox(height: 12),
                    const Text(
                      'ប្រវត្តិមន្ត្រី',
                      style: TextStyle(
                        color: Color(0xCCFFFFFF),
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.8,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      name,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                        height: 1.2,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      position,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Color(0xFFF4F8FA),
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.apartment_rounded,
                          size: 15,
                          color: Color(0xCCFFFFFF),
                        ),
                        const SizedBox(width: 5),
                        Flexible(
                          child: Text(
                            department,
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              color: Color(0xDDFFFFFF),
                              fontSize: 13,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if ((role ?? '').trim().isNotEmpty) ...[
                      const SizedBox(height: 10),
                      Container(
                        padding: const EdgeInsets.fromLTRB(10, 5, 12, 5),
                        decoration: BoxDecoration(
                          color: const Color(0x26FFFFFF),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(color: const Color(0x55FFFFFF)),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.verified_user_rounded,
                              size: 14,
                              color: Colors.white,
                            ),
                            const SizedBox(width: 6),
                            Flexible(
                              child: Text(
                                role!.trim(),
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: 18),
                    _HighlightPanel(chips: chips),
                    if (visibleBadges.isNotEmpty) ...[
                      const SizedBox(height: 12),
                      Wrap(
                        alignment: WrapAlignment.center,
                        spacing: 8,
                        runSpacing: 8,
                        children: visibleBadges,
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

/// A slowly rotating light ring around the avatar.
class _AvatarRing extends StatefulWidget {
  const _AvatarRing({required this.child});

  final Widget child;

  @override
  State<_AvatarRing> createState() => _AvatarRingState();
}

class _AvatarRingState extends State<_AvatarRing>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 6),
  )..repeat();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      alignment: Alignment.center,
      children: [
        RotationTransition(
          turns: _controller,
          child: Container(
            width: 108,
            height: 108,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              gradient: SweepGradient(
                colors: [
                  Colors.white,
                  Color(0x33FFFFFF),
                  Color(0xFFB9F6CA),
                  Color(0x33FFFFFF),
                  Colors.white,
                ],
              ),
            ),
          ),
        ),
        Container(
          width: 98,
          height: 98,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: Color.lerp(homeAccentColor(), Colors.black, 0.2),
          ),
        ),
        widget.child,
      ],
    );
  }
}

/// The highlight facts (ID, pay grade, service date, contact) as tiles on a
/// white panel — two per row when there's room, one per row otherwise.
class _HighlightPanel extends StatelessWidget {
  const _HighlightPanel({required this.chips});

  final List<ProfileHighlightChip> chips;

  @override
  Widget build(BuildContext context) {
    if (chips.isEmpty) return const SizedBox.shrink();
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
      ),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final perRow = constraints.maxWidth >= 250 ? 2 : 1;
          final rows = <Widget>[];
          for (var i = 0; i < chips.length; i += perRow) {
            final slice = chips.skip(i).take(perRow).toList();
            rows.add(
              IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (var j = 0; j < perRow; j++) ...[
                      if (j > 0) const SizedBox(width: 10),
                      Expanded(
                        child:
                            j < slice.length
                                ? _HighlightTile(chip: slice[j])
                                : const SizedBox.shrink(),
                      ),
                    ],
                  ],
                ),
              ),
            );
          }
          return Column(
            children: [
              for (var i = 0; i < rows.length; i++) ...[
                if (i > 0) const SizedBox(height: 10),
                rows[i],
              ],
            ],
          );
        },
      ),
    );
  }
}

class _HighlightTile extends StatelessWidget {
  const _HighlightTile({required this.chip});

  final ProfileHighlightChip chip;

  @override
  Widget build(BuildContext context) {
    final accent = homeAccentColor();
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: accent.withAlpha(16),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: accent.withAlpha(34),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(chip.icon, size: 17, color: accent),
          ),
          const SizedBox(height: 8),
          Text(
            chip.label,
            style: const TextStyle(
              color: Color(0xFF6F7C76),
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 2),
          // One line, shrunk to fit: an e-mail split mid-word reads badly.
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              chip.value,
              maxLines: 1,
              style: const TextStyle(
                color: Color(0xFF163A31),
                fontSize: 14,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Fades and slides a profile card in, staggered by [index].
class StaggeredEntrance extends StatefulWidget {
  const StaggeredEntrance({
    super.key,
    required this.index,
    required this.child,
  });

  final int index;
  final Widget child;

  @override
  State<StaggeredEntrance> createState() => _StaggeredEntranceState();
}

class _StaggeredEntranceState extends State<StaggeredEntrance>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 450),
  );
  late final Animation<double> _curve = CurvedAnimation(
    parent: _controller,
    curve: Curves.easeOutCubic,
  );

  @override
  void initState() {
    super.initState();
    Future<void>.delayed(Duration(milliseconds: 80 * widget.index), () {
      if (mounted) _controller.forward();
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: _curve,
      child: SlideTransition(
        position: Tween(
          begin: const Offset(0, 0.06),
          end: Offset.zero,
        ).animate(_curve),
        child: widget.child,
      ),
    );
  }
}

class ProfileSubsection {
  const ProfileSubsection({required this.label, required this.rows});
  final String label;
  final List<HomeProfileRow> rows;
}

/// A collapsible group of profile facts. The whole header toggles it; each
/// fact shows its label above the value so long Khmer labels and values
/// wrap cleanly at any text size.
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
  late bool _expanded = widget.initiallyExpanded;

  @override
  Widget build(BuildContext context) {
    final accent = homeAccentColor();
    final mainVisible =
        (widget.rows ?? const <HomeProfileRow>[])
            .where((r) => r.value.isNotEmpty)
            .toList();
    final subsVisible =
        (widget.subsections ?? const <ProfileSubsection>[])
            .where((s) => s.rows.any((r) => r.value.isNotEmpty))
            .toList();

    if (mainVisible.isEmpty && subsVisible.isEmpty) {
      return const SizedBox.shrink();
    }
    final factCount =
        mainVisible.length +
        subsVisible.fold<int>(
          0,
          (sum, s) => sum + s.rows.where((r) => r.value.isNotEmpty).length,
        );

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: accent.withAlpha(_expanded ? 70 : 30)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x10142721),
            blurRadius: 16,
            offset: Offset(0, 6),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          InkWell(
            onTap: () => setState(() => _expanded = !_expanded),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(14, 14, 12, 14),
              child: Row(
                children: [
                  if (widget.icon != null) ...[
                    Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(14),
                        gradient: LinearGradient(
                          colors: [
                            accent,
                            Color.lerp(accent, Colors.black, 0.3)!,
                          ],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                      ),
                      child: Icon(widget.icon, size: 21, color: Colors.white),
                    ),
                    const SizedBox(width: 12),
                  ],
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          widget.title,
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 15.5,
                            color: Color(0xFF163A31),
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          widget.subtitle,
                          style: const TextStyle(
                            fontSize: 11.5,
                            color: Color(0xFF6F7C76),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: accent.withAlpha(24),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      '$factCount',
                      style: TextStyle(
                        color: accent,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const SizedBox(width: 4),
                  AnimatedRotation(
                    turns: _expanded ? 0.5 : 0,
                    duration: const Duration(milliseconds: 250),
                    child: Icon(
                      Icons.keyboard_arrow_down_rounded,
                      color: accent,
                      size: 26,
                    ),
                  ),
                ],
              ),
            ),
          ),
          AnimatedSize(
            duration: const Duration(milliseconds: 260),
            curve: Curves.easeInOutCubic,
            alignment: Alignment.topCenter,
            child:
                _expanded
                    ? Padding(
                      padding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          if (mainVisible.isNotEmpty) _group(mainVisible),
                          for (final sub in subsVisible) ...[
                            _subsectionHeader(sub.label),
                            _group(
                              sub.rows
                                  .where((r) => r.value.isNotEmpty)
                                  .toList(),
                            ),
                          ],
                        ],
                      ),
                    )
                    : const SizedBox(width: double.infinity),
          ),
        ],
      ),
    );
  }

  Widget _subsectionHeader(String label) {
    final accent = homeAccentColor();
    return Padding(
      padding: const EdgeInsets.fromLTRB(2, 14, 2, 8),
      child: Row(
        children: [
          Container(
            width: 4,
            height: 16,
            decoration: BoxDecoration(
              color: accent,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w800,
                color: accent,
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// Facts on a soft tinted panel, separated by hairlines.
  Widget _group(List<HomeProfileRow> rows) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFF5F9F7),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (var i = 0; i < rows.length; i++) ...[
            if (i > 0) const Divider(height: 1, color: Color(0xFFE3ECE8)),
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    rows[i].label,
                    style: const TextStyle(
                      color: Color(0xFF6F7C76),
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    rows[i].value,
                    style: const TextStyle(
                      color: Color(0xFF163A31),
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
