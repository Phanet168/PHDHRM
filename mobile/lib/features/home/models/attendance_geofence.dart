import 'dart:math' as math;

/// The scan area the server enforces for the officer's unit (centre +
/// radius), sent with `/v1/attendance/today` so the GPS tab can show a live
/// in/out-of-zone indicator that matches the server's decision.
class AttendanceGeofence {
  const AttendanceGeofence({
    required this.latitude,
    required this.longitude,
    required this.radiusMeters,
    this.source,
  });

  final double latitude;
  final double longitude;
  final double radiusMeters;
  final String? source;

  static AttendanceGeofence? fromApi(dynamic json) {
    if (json is! Map) return null;
    final lat = _toDouble(json['latitude']);
    final lng = _toDouble(json['longitude']);
    final radius = _toDouble(json['radius_meters']);
    if (lat == null || lng == null || radius == null || radius <= 0) {
      return null;
    }
    return AttendanceGeofence(
      latitude: lat,
      longitude: lng,
      radiusMeters: radius,
      source: json['source']?.toString(),
    );
  }

  /// Great-circle distance in metres, the same formula the server uses.
  double distanceTo(double lat, double lng) {
    double rad(double deg) => deg * math.pi / 180;
    final a =
        math.pow(math.sin(rad(lat - latitude) / 2), 2) +
        math.cos(rad(latitude)) *
            math.cos(rad(lat)) *
            math.pow(math.sin(rad(lng - longitude) / 2), 2);
    return 6371000 * 2 * math.asin(math.sqrt(a.clamp(0, 1)));
  }

  /// (east, north) offset in metres of a point from the centre — enough
  /// precision for drawing a position on a radar a few km across.
  ({double east, double north}) offsetOf(double lat, double lng) {
    const metersPerDegree = 111320.0;
    return (
      east:
          (lng - longitude) *
          metersPerDegree *
          math.cos(latitude * math.pi / 180),
      north: (lat - latitude) * metersPerDegree,
    );
  }
}

double? _toDouble(dynamic value) =>
    value is num ? value.toDouble() : double.tryParse('${value ?? ''}');
