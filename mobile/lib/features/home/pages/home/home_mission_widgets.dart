import 'package:flutter/material.dart';

import '../../../../core/theme/app_design_system.dart';
import '../../models/mission_detail.dart';
import 'home_common_widgets.dart';
import 'home_theme.dart';

class MissionRecordCard extends StatelessWidget {
  const MissionRecordCard({
    super.key,
    required this.title,
    required this.destination,
    required this.dateRange,
    required this.status,
    required this.employeeCount,
    required this.language,
  });

  final String title;
  final String destination;
  final String dateRange;
  final String status;
  final int employeeCount;
  final Map<String, String> language;

  String _tr(String key, String fallback) {
    final value = language[key]?.trim();
    if (value == null || value.isEmpty) {
      return fallback;
    }

    return value;
  }

  Color _statusColor(String value) {
    final normalized = value.trim().toLowerCase();
    switch (normalized) {
      case 'approved':
      case 'in_progress':
      case 'completed':
        return homeAccentColor();
      case 'pending':
        return const Color(0xFFA85C00);
      case 'rejected':
      case 'cancelled':
        return const Color(0xFFD34B5F);
      default:
        return const Color(0xFF3D495A);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tone = _statusColor(status);
    final normalizedStatus = status.trim().isEmpty ? '-' : missionStatusLabel(status);

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppDesignSystem.border),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A14211D),
            blurRadius: 16,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF3FF),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.work_outline,
                    color: Color(0xFF1D4F91),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    title,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF14211D),
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: tone.withAlpha(24),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    normalizedStatus,
                    style: TextStyle(
                      color: tone,
                      fontWeight: FontWeight.w800,
                      fontSize: 11,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                HomeSoftPill(
                  icon: Icons.apartment_outlined,
                  label: '${_tr('destination', 'គោលដៅ')}: $destination',
                  backgroundColor: const Color(0xFFEFF3FF),
                ),
                HomeSoftPill(
                  icon: Icons.date_range_outlined,
                  label: dateRange,
                  backgroundColor: const Color(0xFFE9F4F1),
                ),
                HomeSoftPill(
                  icon: Icons.group_outlined,
                  label: '${_tr('employee', 'បុគ្គលិក')}: $employeeCount',
                  backgroundColor: const Color(0xFFFFF1E5),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
