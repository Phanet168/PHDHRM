import 'dart:math' as math;

import 'package:flutter/material.dart';

/// Where the officer is relative to the unit's scan area.
enum GpsZoneStatus { locating, inside, outside, unconfigured }

Color gpsZoneColor(GpsZoneStatus status) => switch (status) {
  GpsZoneStatus.inside => const Color(0xFF1E9E5A),
  GpsZoneStatus.outside => const Color(0xFFE53935),
  GpsZoneStatus.locating => const Color(0xFFF39C12),
  GpsZoneStatus.unconfigured => const Color(0xFF6F7C76),
};

IconData gpsZoneIcon(GpsZoneStatus status) => switch (status) {
  GpsZoneStatus.inside => Icons.where_to_vote_rounded,
  GpsZoneStatus.outside => Icons.wrong_location_rounded,
  GpsZoneStatus.locating => Icons.location_searching_rounded,
  GpsZoneStatus.unconfigured => Icons.location_on_rounded,
};

/// Live radar of the scan area: the unit at the centre, the allowed radius as
/// a coloured zone, and the officer as a bouncing, pulsing pin that glides to
/// each new GPS fix. Green inside the zone, red outside, amber while locating.
class GpsZoneRadar extends StatefulWidget {
  const GpsZoneRadar({
    super.key,
    required this.status,
    this.offsetMeters,
    this.radiusMeters,
    this.size = 260,
  });

  final GpsZoneStatus status;

  /// The officer's (east, north) offset in metres from the zone centre.
  final ({double east, double north})? offsetMeters;
  final double? radiusMeters;
  final double size;

  @override
  State<GpsZoneRadar> createState() => _GpsZoneRadarState();
}

class _GpsZoneRadarState extends State<GpsZoneRadar>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2200),
  )..repeat();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final size = widget.size;
    final color = gpsZoneColor(widget.status);
    final radius = widget.radiusMeters;
    final hasZone = radius != null && radius > 0;
    final zonePx = size * 0.30;
    final edgePx = size * 0.42;

    // Scale so the allowed radius fills the inner zone; anything farther is
    // pinned to the radar's rim in the right direction.
    var point = Offset.zero;
    final offset = widget.offsetMeters;
    if (hasZone && offset != null) {
      point = Offset(offset.east, -offset.north) * (zonePx / radius);
      if (point.distance > edgePx) {
        point = point / point.distance * edgePx;
      }
    }
    const pinBox = 84.0;
    final half = (size - pinBox) / 2;

    return SizedBox.square(
      dimension: size,
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) {
          final t = _controller.value;
          return Stack(
            clipBehavior: Clip.none,
            children: [
              Positioned.fill(
                child: CustomPaint(
                  painter: _RadarPainter(
                    t: t,
                    color: color,
                    zonePx: hasZone ? zonePx : null,
                    edgePx: edgePx,
                  ),
                ),
              ),
              if (hasZone) const Center(child: _UnitMarker()),
              AnimatedAlign(
                duration: const Duration(milliseconds: 700),
                curve: Curves.easeOutCubic,
                alignment: Alignment(point.dx / half, point.dy / half),
                child: SizedBox.square(
                  dimension: pinBox,
                  child: _PulsingPin(
                    t: t,
                    color: color,
                    icon: gpsZoneIcon(widget.status),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _RadarPainter extends CustomPainter {
  _RadarPainter({
    required this.t,
    required this.color,
    required this.zonePx,
    required this.edgePx,
  });

  final double t;
  final Color color;
  final double? zonePx;
  final double edgePx;

  @override
  void paint(Canvas canvas, Size size) {
    final c = size.center(Offset.zero);
    final outer = edgePx + 10;

    canvas.drawCircle(c, outer, Paint()..color = const Color(0xFFF2F6F4));

    final grid =
        Paint()
          ..color = const Color(0x1F17352B)
          ..style = PaintingStyle.stroke
          ..strokeWidth = 1;
    for (final f in [0.34, 0.67, 1.0]) {
      canvas.drawCircle(c, outer * f, grid);
    }
    canvas.drawLine(c.translate(-outer, 0), c.translate(outer, 0), grid);
    canvas.drawLine(c.translate(0, -outer), c.translate(0, outer), grid);

    final zone = zonePx;
    if (zone != null) {
      canvas.drawCircle(c, zone, Paint()..color = color.withAlpha(36));
      canvas.drawCircle(
        c,
        zone,
        Paint()
          ..color = color.withAlpha(170)
          ..style = PaintingStyle.stroke
          ..strokeWidth = 2,
      );
    }

    // Rotating sweep: the "live" cue.
    final rect = Rect.fromCircle(center: c, radius: outer);
    canvas.drawCircle(
      c,
      outer,
      Paint()
        ..shader = SweepGradient(
          colors: [color.withAlpha(0), color.withAlpha(70)],
          stops: const [0.78, 1.0],
          transform: GradientRotation(t * 2 * math.pi),
        ).createShader(rect),
    );
  }

  @override
  bool shouldRepaint(_RadarPainter old) =>
      old.t != t ||
      old.color != color ||
      old.zonePx != zonePx ||
      old.edgePx != edgePx;
}

class _UnitMarker extends StatelessWidget {
  const _UnitMarker();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 34,
      height: 34,
      decoration: const BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            color: Color(0x2617352B),
            blurRadius: 8,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: const Icon(
        Icons.apartment_rounded,
        size: 18,
        color: Color(0xFF17352B),
      ),
    );
  }
}

/// The officer: a dot on the exact fix with two expanding rings, and a pin
/// bobbing just above it.
class _PulsingPin extends StatelessWidget {
  const _PulsingPin({required this.t, required this.color, required this.icon});

  final double t;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final bob = 4 * math.sin(t * 4 * math.pi);
    return Stack(
      alignment: Alignment.center,
      clipBehavior: Clip.none,
      children: [
        for (final phase in [t, (t + 0.5) % 1])
          Container(
            width: 14 + phase * 64,
            height: 14 + phase * 64,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: color.withAlpha((70 * (1 - phase)).round()),
            ),
          ),
        Container(
          width: 14,
          height: 14,
          decoration: BoxDecoration(
            color: color,
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white, width: 3),
            boxShadow: [BoxShadow(color: color.withAlpha(110), blurRadius: 8)],
          ),
        ),
        Transform.translate(
          offset: Offset(0, -24 + bob),
          child: Icon(
            icon,
            size: 36,
            color: color,
            shadows: const [
              Shadow(
                color: Color(0x40000000),
                blurRadius: 6,
                offset: Offset(0, 3),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// A small "LIVE" badge whose dot blinks while the position stream is on.
class GpsLiveBadge extends StatefulWidget {
  const GpsLiveBadge({super.key, required this.color, required this.label});

  final Color color;
  final String label;

  @override
  State<GpsLiveBadge> createState() => _GpsLiveBadgeState();
}

class _GpsLiveBadgeState extends State<GpsLiveBadge>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: widget.color.withAlpha(26),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          FadeTransition(
            opacity: Tween(begin: 0.25, end: 1.0).animate(_controller),
            child: Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(
                color: widget.color,
                shape: BoxShape.circle,
              ),
            ),
          ),
          const SizedBox(width: 6),
          Text(
            widget.label,
            style: TextStyle(
              color: widget.color,
              fontWeight: FontWeight.w800,
              fontSize: 11,
              letterSpacing: 0.6,
            ),
          ),
        ],
      ),
    );
  }
}
