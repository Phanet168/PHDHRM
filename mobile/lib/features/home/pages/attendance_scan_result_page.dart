import 'package:flutter/material.dart';

import '../../../core/theme/app_design_system.dart';
import '../models/attendance_scan_result.dart';

Color _dynamicPrimary() =>
    AppDesignSystem.colorForWeekday(DateTime.now().weekday);

const _failureColor = Color(0xFFE53935);
const _failureBg = Color(0xFFFDECEB);

/// Figma "ជោគជ័យ / Scan Success" and "បរាជ័យ / Check-in Failed" — a single
/// page that renders either state from the same [AttendanceScanResult],
/// centered icon + detail card + full-width action.
class AttendanceScanResultPage extends StatelessWidget {
  const AttendanceScanResultPage({
    super.key,
    required this.result,
    required this.language,
    required this.scannedAt,
    this.latitude,
    this.longitude,
    this.scanType = 'QR',
  });

  final AttendanceScanResult result;
  final Map<String, String> language;
  final DateTime scannedAt;
  final double? latitude;
  final double? longitude;
  final String scanType;

  String _tr(String key, String fallback) {
    final value = language[key]?.trim();
    if (value == null || value.isEmpty) {
      return fallback;
    }

    return value;
  }

  String _formatDate(DateTime value) {
    String two(int input) => input.toString().padLeft(2, '0');
    return '${two(value.day)}-${two(value.month)}-${value.year}';
  }

  String _formatTime(DateTime value) {
    String two(int input) => input.toString().padLeft(2, '0');
    return '${two(value.hour)}:${two(value.minute)}:${two(value.second)}';
  }

  String _formatMeters(double? meters) {
    if (meters == null) {
      return '-';
    }

    return '${meters.toStringAsFixed(1)} m';
  }

  String _formatPunchType(String? value) {
    final normalized = value?.trim().toLowerCase();
    if (normalized == null || normalized.isEmpty) {
      return '-';
    }

    if (normalized == 'in') {
      return _tr('time_in', 'IN');
    }
    if (normalized == 'out') {
      return _tr('time_out', 'OUT');
    }

    return value!.toUpperCase();
  }

  String _formatCoordinates() {
    if (latitude == null || longitude == null) {
      return '-';
    }

    return '${latitude!.toStringAsFixed(6)}, ${longitude!.toStringAsFixed(6)}';
  }

  @override
  Widget build(BuildContext context) {
    final isSuccess = result.isSuccess;
    final accent = isSuccess ? _dynamicPrimary() : _failureColor;
    final accentBg = isSuccess ? _dynamicPrimary().withAlpha(28) : _failureBg;

    final rows =
        isSuccess
            ? [
              _DetailRow(
                label: _tr('scan_time', 'ចូលម៉ោង'),
                value: _formatTime(scannedAt),
                emphasize: true,
                valueColor: const Color(0xFF17221D),
              ),
              _DetailRow(
                label: _tr('scan_date', 'កាលបរិច្ឆេទ'),
                value: _formatDate(scannedAt),
              ),
              _DetailRow(
                label: _tr('department', 'អង្គភាព'),
                value: result.workplaceName ?? '-',
              ),
              _DetailRow(
                label: _tr('scan_type', 'ប្រភេទស្កេន'),
                value: scanType,
              ),
              _DetailRow(
                label: _tr('attendance_type', 'ប្រភេទវត្តមាន'),
                value: _formatPunchType(result.punchType),
              ),
              _DetailRow(
                label: _tr('location_coordinates', 'ទីតាំង GPS'),
                value: _formatCoordinates(),
              ),
              _DetailRow(
                label: _tr('distance', 'ចម្ងាយ'),
                value: _formatMeters(result.rangeMeters),
              ),
              _DetailRow(
                label: _tr('status', 'ស្ថានភាព'),
                badge: true,
                badgeText: '${_tr('scan_success', 'ទាន់ពេល')} ✓',
                accent: accent,
                accentBg: accentBg,
              ),
            ]
            : [
              _DetailRow(
                label: _tr('failure_reason', 'មូលហេតុ'),
                value: result.message,
                emphasize: true,
                valueColor: _failureColor,
              ),
              _DetailRow(
                label: _tr('distance', 'ចម្ងាយ'),
                value: _formatMeters(result.rangeMeters),
              ),
              _DetailRow(
                label: _tr('scan_time', 'ពេលវេលា'),
                value: _formatTime(scannedAt),
              ),
              _DetailRow(
                label: _tr('location_coordinates', 'ទីតាំង GPS'),
                value: _formatCoordinates(),
              ),
              _DetailRow(
                label: _tr('allowed_range', 'ចម្ងាយអនុញ្ញាត'),
                value: _formatMeters(result.acceptableRangeMeters),
              ),
              _DetailRow(
                label: _tr('status', 'ស្ថានភាព'),
                badge: true,
                badgeText: _tr('scan_failed', 'បរាជ័យ'),
                accent: accent,
                accentBg: accentBg,
              ),
            ];

    return Scaffold(
      backgroundColor: const Color(0xFFF5F7F6),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 12),
              Center(
                child: Container(
                  width: 116,
                  height: 116,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: accentBg,
                    shape: BoxShape.circle,
                  ),
                  child: Container(
                    width: 82,
                    height: 82,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: accent,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      isSuccess
                          ? Icons.check_rounded
                          : Icons.close_rounded,
                      color: Colors.white,
                      size: 40,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 18),
              Text(
                isSuccess
                    ? _tr('scan_success_title', 'ជោគជ័យ!')
                    : _tr('scan_failed_title', 'បរាជ័យ!'),
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: isSuccess ? const Color(0xFF17221D) : accent,
                  fontWeight: FontWeight.w800,
                  fontSize: 26,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                isSuccess
                    ? _tr(
                      'scan_success_subtitle',
                      'អ្នកបានចុះវត្តមានចូលដោយជោគជ័យ',
                    )
                    : result.message,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Color(0xFF6F7C76),
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 18),
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18),
                  boxShadow: const [
                    BoxShadow(
                      color: Color(0x120F2D26),
                      blurRadius: 16,
                      offset: Offset(0, 8),
                    ),
                  ],
                ),
                child: Column(
                  children: [
                    for (var i = 0; i < rows.length; i++) ...[
                      rows[i],
                      if (i != rows.length - 1) const SizedBox(height: 14),
                    ],
                  ],
                ),
              ),
              const Spacer(),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => Navigator.of(context).pop(result.isSuccess),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                  backgroundColor: accent,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: Text(
                  isSuccess
                      ? _tr('back_to_home', 'ត្រឡប់ទៅទំព័រដើម')
                      : _tr('scan_retry', 'សាកល្បងម្តងទៀត'),
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 15,
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

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.label,
    this.value,
    this.emphasize = false,
    this.valueColor,
    this.badge = false,
    this.badgeText,
    this.accent,
    this.accentBg,
  });

  final String label;
  final String? value;
  final bool emphasize;
  final Color? valueColor;
  final bool badge;
  final String? badgeText;
  final Color? accent;
  final Color? accentBg;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Text(
          label,
          style: const TextStyle(color: Color(0xFF6F7C76), fontSize: 11),
        ),
        const SizedBox(width: 10),
        Expanded(
          child:
              badge
                  ? Align(
                    alignment: Alignment.centerRight,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 5,
                      ),
                      decoration: BoxDecoration(
                        color: accentBg,
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        badgeText ?? '-',
                        style: TextStyle(
                          color: accent,
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  )
                  : Text(
                    (value == null || value!.isEmpty) ? '-' : value!,
                    textAlign: TextAlign.right,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: valueColor ?? const Color(0xFF17221D),
                      fontWeight: emphasize ? FontWeight.w800 : FontWeight.w700,
                      fontSize: emphasize ? 18 : 13,
                    ),
                  ),
        ),
      ],
    );
  }
}
