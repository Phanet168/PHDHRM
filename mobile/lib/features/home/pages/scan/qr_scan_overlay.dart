import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'gps_zone_radar.dart';

/// Camera overlay for the QR tab: dims everything outside the scan window,
/// draws glowing corner brackets, and sweeps a laser line up and down while
/// [scanning]. Purely decorative — it ignores touches.
class QrScanOverlay extends StatefulWidget {
  const QrScanOverlay({super.key, required this.color, this.scanning = true});

  final Color color;
  final bool scanning;

  @override
  State<QrScanOverlay> createState() => _QrScanOverlayState();
}

class _QrScanOverlayState extends State<QrScanOverlay>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1800),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: AnimatedBuilder(
        animation: _controller,
        builder:
            (context, _) => CustomPaint(
              size: Size.infinite,
              painter: _QrFramePainter(
                t: Curves.easeInOut.transform(_controller.value),
                forward: _controller.status == AnimationStatus.forward,
                color: widget.color,
                scanning: widget.scanning,
              ),
            ),
      ),
    );
  }
}

class _QrFramePainter extends CustomPainter {
  _QrFramePainter({
    required this.t,
    required this.forward,
    required this.color,
    required this.scanning,
  });

  final double t;
  final bool forward;
  final Color color;
  final bool scanning;

  @override
  void paint(Canvas canvas, Size size) {
    final side = math.min(size.width, size.height) * 0.66;
    final window = Rect.fromCenter(
      center: size.center(Offset.zero),
      width: side,
      height: side,
    );
    const radius = 22.0;

    canvas.drawPath(
      Path.combine(
        PathOperation.difference,
        Path()..addRect(Offset.zero & size),
        Path()..addRRect(
          RRect.fromRectAndRadius(window, const Radius.circular(radius)),
        ),
      ),
      Paint()..color = const Color(0x8C000000),
    );

    if (scanning) {
      // Laser with a fading trail behind its direction of travel.
      final y = window.top + 14 + (window.height - 28) * t;
      final inset = window.left + 16;
      final width = window.width - 32;
      const trail = 46.0;
      final trailRect =
          forward
              ? Rect.fromLTWH(inset, y - trail, width, trail)
              : Rect.fromLTWH(inset, y, width, trail);
      canvas.drawRect(
        trailRect,
        Paint()
          ..shader = LinearGradient(
            begin: forward ? Alignment.topCenter : Alignment.bottomCenter,
            end: forward ? Alignment.bottomCenter : Alignment.topCenter,
            colors: [color.withAlpha(0), color.withAlpha(70)],
          ).createShader(trailRect),
      );
      final laser = Rect.fromLTWH(inset, y - 1.5, width, 3);
      final laserShader = LinearGradient(
        colors: [color.withAlpha(0), color, color.withAlpha(0)],
      ).createShader(laser);
      canvas.drawRRect(
        RRect.fromRectAndRadius(laser.inflate(3), const Radius.circular(4)),
        Paint()
          ..shader = laserShader
          ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6),
      );
      canvas.drawRRect(
        RRect.fromRectAndRadius(laser, const Radius.circular(2)),
        Paint()..shader = laserShader,
      );
    }

    // Corner brackets, breathing slightly, with a soft glow.
    final length = 30 + 4 * math.sin(t * math.pi);
    final corners = Path();
    void corner(Offset start, Offset arcStart, Offset arcEnd, Offset end) {
      corners
        ..moveTo(start.dx, start.dy)
        ..lineTo(arcStart.dx, arcStart.dy)
        ..arcToPoint(arcEnd, radius: const Radius.circular(radius))
        ..lineTo(end.dx, end.dy);
    }

    final l = window.left, r = window.right, tp = window.top, b = window.bottom;
    corner(
      Offset(l, tp + length),
      Offset(l, tp + radius),
      Offset(l + radius, tp),
      Offset(l + length, tp),
    );
    corner(
      Offset(r - length, tp),
      Offset(r - radius, tp),
      Offset(r, tp + radius),
      Offset(r, tp + length),
    );
    corner(
      Offset(r, b - length),
      Offset(r, b - radius),
      Offset(r - radius, b),
      Offset(r - length, b),
    );
    corner(
      Offset(l + length, b),
      Offset(l + radius, b),
      Offset(l, b - radius),
      Offset(l, b - length),
    );
    canvas.drawPath(
      corners,
      Paint()
        ..color = color.withAlpha(110)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 10
        ..strokeCap = StrokeCap.round
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 7),
    );
    canvas.drawPath(
      corners,
      Paint()
        ..color = color
        ..style = PaintingStyle.stroke
        ..strokeWidth = 5
        ..strokeCap = StrokeCap.round,
    );
  }

  @override
  bool shouldRepaint(_QrFramePainter old) =>
      old.t != t ||
      old.forward != forward ||
      old.color != color ||
      old.scanning != scanning;
}

/// Compact live zone status shown over the camera (QR scans are geofenced
/// too): coloured icon + "in zone · 120 m" style label.
class GpsZonePill extends StatelessWidget {
  const GpsZonePill({super.key, required this.status, required this.label});

  final GpsZoneStatus status;
  final String label;

  @override
  Widget build(BuildContext context) {
    final color = gpsZoneColor(status);
    return AnimatedContainer(
      duration: const Duration(milliseconds: 300),
      padding: const EdgeInsets.fromLTRB(8, 5, 12, 5),
      decoration: BoxDecoration(
        color: Colors.white.withAlpha(235),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withAlpha(120)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x33000000),
            blurRadius: 8,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(gpsZoneIcon(status), color: color, size: 18),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: color,
                fontWeight: FontWeight.w800,
                fontSize: 12,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
