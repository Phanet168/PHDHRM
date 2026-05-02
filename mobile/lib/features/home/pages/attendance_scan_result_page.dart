import 'package:flutter/material.dart';

import '../models/attendance_scan_result.dart';

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

  String _formatDateTime(DateTime value) {
    String two(int input) => input.toString().padLeft(2, '0');

    return '${two(value.day)}-${two(value.month)}-${value.year} '
        '${two(value.hour)}:${two(value.minute)}:${two(value.second)}';
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

  String _statusLabel() {
    return result.isSuccess
        ? _tr('scan_success', 'ជោគជ័យ')
        : _tr('scan_failed', 'បរាជ័យ');
  }

  Color _statusColor() {
    return result.isSuccess ? const Color(0xFF0B6B58) : const Color(0xFFD34B5F);
  }

  Color _statusBgColor() {
    return result.isSuccess ? const Color(0xFFE9F4F1) : const Color(0xFFFFEEF1);
  }

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor();
    final statusBgColor = _statusBgColor();

    return Scaffold(
      appBar: AppBar(title: Text(_tr('scan_result', 'លទ្ធផលស្កេន'))),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: statusBgColor,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(
                    color:
                        result.isSuccess
                            ? const Color(0xFFCDE4DB)
                            : const Color(0xFFF0CED5),
                  ),
                  boxShadow: const [
                    BoxShadow(
                      color: Color(0x140F2D26),
                      blurRadius: 18,
                      offset: Offset(0, 10),
                    ),
                  ],
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Icon(
                        result.isSuccess
                            ? Icons.check_circle_outline
                            : Icons.error_outline,
                        color: statusColor,
                        size: 28,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _statusLabel(),
                            style: TextStyle(
                              color: statusColor,
                              fontWeight: FontWeight.w800,
                              fontSize: 18,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            result.message,
                            style: const TextStyle(
                              color: Color(0xFF1B2D28),
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Expanded(
                child: Container(
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: const Color(0xFFE2EAE7)),
                    boxShadow: const [
                      BoxShadow(
                        color: Color(0x0F12352C),
                        blurRadius: 16,
                        offset: Offset(0, 8),
                      ),
                    ],
                  ),
                  child: ListView(
                    padding: const EdgeInsets.all(14),
                    children: [
                      _ResultInfoRow(
                        icon: Icons.schedule_outlined,
                        label: _tr('scan_time', 'ម៉ោងស្កេន'),
                        value: _formatDateTime(scannedAt),
                      ),
                      _ResultInfoRow(
                        icon: Icons.qr_code_scanner_outlined,
                        label: _tr('scan_type', 'ប្រភេទស្កេន'),
                        value: scanType,
                      ),
                      _ResultInfoRow(
                        icon: Icons.sync_alt_outlined,
                        label: _tr('attendance_type', 'ប្រភេទវត្តមាន'),
                        value: _formatPunchType(result.punchType),
                      ),
                      _ResultInfoRow(
                        icon: Icons.flag_outlined,
                        label: _tr('status', 'ស្ថានភាព'),
                        value: _statusLabel(),
                        valueColor: statusColor,
                      ),
                      _ResultInfoRow(
                        icon: Icons.apartment_outlined,
                        label: _tr('department', 'អង្គភាព'),
                        value: result.workplaceName ?? '-',
                      ),
                      _ResultInfoRow(
                        icon: Icons.place_outlined,
                        label: _tr('location', 'ទីតាំង'),
                        value: _formatCoordinates(),
                      ),
                      _ResultInfoRow(
                        icon: Icons.straighten_outlined,
                        label: _tr('distance', 'ចម្ងាយ'),
                        value: _formatMeters(result.rangeMeters),
                      ),
                      _ResultInfoRow(
                        icon: Icons.rule_outlined,
                        label: _tr('allowed_range', 'ចម្ងាយអនុញ្ញាត'),
                        value: _formatMeters(result.acceptableRangeMeters),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: () => Navigator.of(context).pop(result.isSuccess),
                icon: const Icon(Icons.done_all_outlined),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(50),
                  backgroundColor: const Color(0xFF0B6B58),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
                label: Text(_tr('done', 'រួចរាល់')),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ResultInfoRow extends StatelessWidget {
  const _ResultInfoRow({
    required this.icon,
    required this.label,
    required this.value,
    this.valueColor,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFF7FBF9),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFE3ECE7)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFDCEAE3)),
              ),
              child: Icon(icon, size: 18, color: const Color(0xFF0B6B58)),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: const TextStyle(
                      color: Color(0xFF4E615A),
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    value.isEmpty ? '-' : value,
                    style: TextStyle(
                      color: valueColor ?? const Color(0xFF152A24),
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
