import 'package:flutter/material.dart';

import '../../../core/theme/app_design_system.dart';
import '../models/correspondence_models.dart';

Color _dynamicPrimary() =>
    AppDesignSystem.colorForWeekday(DateTime.now().weekday);

/// Letter Tracking (Figma "តាមដានសំបុត្រ / Letter Tracking") — built entirely
/// from data the detail fetch already returns (`letter.actions` for the
/// timeline, `letter.distributions` for per-recipient acknowledgement
/// status), so no new backend endpoint is needed.
class CorrespondenceTrackingPage extends StatelessWidget {
  const CorrespondenceTrackingPage({super.key, required this.letter});

  final CorrespondenceLetter letter;

  // Matches every action_type value the backend's logAction() calls emit
  // (CorrespondenceController), so the timeline never falls back to a raw
  // English key.
  static const Map<String, String> _actionLabels = {
    'created': 'បង្កើត',
    'delegate': 'ប្រគល់',
    'office_comment': 'ចំណាំការិយាល័យ',
    'office_comment_related': 'ចំណាំការិយាល័យ (អ្នកពាក់ព័ន្ធ)',
    'deputy_review': 'ពិនិត្យអនុប្រធាន',
    'director_approved': 'សម្រេចអនុម័ត',
    'director_rejected': 'សម្រេចមិនអនុម័ត',
    'distribute': 'ចែកចាយ',
    'acknowledge': 'ទទួលស្គាល់',
    'feedback': 'ផ្ញើមតិ',
    'feedback_to_parent': 'ផ្ញើមតិទៅអង្គភាពម្ដាយ',
    'auto_created_from_parent': 'បង្កើតស្វ័យប្រវត្តិពីលិខិតម្ដាយ',
    'child_acknowledged': 'អង្គភាពកូនទទួលស្គាល់',
    'child_feedback_received': 'អង្គភាពកូនផ្ញើមតិ',
    'closed': 'បិទ',
  };

  String _actionLabel(String type) => _actionLabels[type] ?? type;

  String _formatDateTime(DateTime? dt) {
    if (dt == null) return '';
    final day = dt.day.toString().padLeft(2, '0');
    final month = dt.month.toString().padLeft(2, '0');
    final hour = dt.hour.toString().padLeft(2, '0');
    final minute = dt.minute.toString().padLeft(2, '0');
    return '$day-$month-${dt.year} · $hour:$minute';
  }

  String _distStatusLabel(String status) {
    switch (status) {
      case 'pending_ack':
        return 'រង់ចាំ';
      case 'acknowledged':
        return 'ទទួលស្គាល់';
      case 'feedback_sent':
        return 'ផ្ញើមតិហើយ';
      case 'closed':
        return 'បិទហើយ';
      default:
        return status;
    }
  }

  bool _isDone(String status) =>
      status == 'acknowledged' ||
      status == 'feedback_sent' ||
      status == 'closed';

  @override
  Widget build(BuildContext context) {
    final actions = List<CorrespondenceLetterAction>.from(
      letter.actions ?? const <CorrespondenceLetterAction>[],
    )..sort((a, b) {
      final ad = a.createdAt;
      final bd = b.createdAt;
      if (ad == null || bd == null) return 0;
      return ad.compareTo(bd);
    });
    final distributions = letter.distributions ?? const [];
    final doneCount = distributions.where((d) => _isDone(d.status)).length;
    final rate = distributions.isEmpty ? 0.0 : doneCount / distributions.length;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F9F8),
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Color(0xFF2E7D5B), Color(0xFF1B5E40)],
                ),
              ),
              padding: const EdgeInsets.fromLTRB(4, 8, 16, 16),
              child: Row(
                children: [
                  IconButton(
                    onPressed: () => Navigator.of(context).maybePop(),
                    icon: const Icon(
                      Icons.arrow_back_ios_new_rounded,
                      size: 18,
                      color: Colors.white,
                    ),
                  ),
                  const Expanded(
                    child: Text(
                      'តាមដានលិខិត',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: const [
                        BoxShadow(
                          color: Color(0x2117233B),
                          blurRadius: 10,
                          offset: Offset(0, 3),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (letter.letterNo != null)
                          Text(
                            'លេខ ${letter.letterNo}',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: _dynamicPrimary(),
                            ),
                          ),
                        const SizedBox(height: 5),
                        Text(
                          letter.subject,
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF17231D),
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (distributions.isNotEmpty) ...[
                    const SizedBox(height: 14),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x2117233B),
                            blurRadius: 10,
                            offset: Offset(0, 3),
                          ),
                        ],
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text(
                                    'ស្ថានភាពអ្នកទទួល',
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: Color(0xFF6D7973),
                                    ),
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    'អត្រាទទួលស្គាល់: ${(rate * 100).round()}%',
                                    style: const TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.w800,
                                      color: Color(0xFF17231D),
                                    ),
                                  ),
                                ],
                              ),
                              Container(
                                width: 44,
                                height: 44,
                                alignment: Alignment.center,
                                decoration: BoxDecoration(
                                  color: const Color(0xFFE8F4EE),
                                  shape: BoxShape.circle,
                                ),
                                child: Icon(
                                  Icons.insights_rounded,
                                  color: _dynamicPrimary(),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(999),
                            child: LinearProgressIndicator(
                              value: rate,
                              minHeight: 7,
                              backgroundColor: const Color(0xFFDCE5E0),
                              valueColor: AlwaysStoppedAnimation<Color>(
                                _dynamicPrimary(),
                              ),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'ទទួលស្គាល់ $doneCount នាក់ ក្នុងចំណោម ${distributions.length} នាក់',
                            style: const TextStyle(
                              fontSize: 11,
                              color: Color(0xFF6D7973),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  if (actions.isNotEmpty) ...[
                    const SizedBox(height: 14),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x2117233B),
                            blurRadius: 10,
                            offset: Offset(0, 3),
                          ),
                        ],
                      ),
                      child: Column(
                        children: [
                          for (var i = 0; i < actions.length; i++)
                            _TimelineEvent(
                              label: _actionLabel(actions[i].actionType),
                              subtitle: [
                                _formatDateTime(actions[i].createdAt),
                                if (actions[i].actedByName != null)
                                  'ដោយ ${actions[i].actedByName}',
                              ].where((s) => s.isNotEmpty).join(' · '),
                              isLast: i == actions.length - 1,
                            ),
                        ],
                      ),
                    ),
                  ],
                  if (distributions.isNotEmpty) ...[
                    const SizedBox(height: 14),
                    const Text(
                      'ស្ថានភាពអ្នកទទួល',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF17231D),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x2117233B),
                            blurRadius: 10,
                            offset: Offset(0, 3),
                          ),
                        ],
                      ),
                      child: Column(
                        children: [
                          for (var i = 0; i < distributions.length; i++)
                            _RecipientRow(
                              distribution: distributions[i],
                              label: _distStatusLabel(distributions[i].status),
                              isDone: _isDone(distributions[i].status),
                              isLast: i == distributions.length - 1,
                            ),
                        ],
                      ),
                    ),
                  ],
                  if (actions.isEmpty && distributions.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 60),
                      child: Center(
                        child: Text(
                          'មិនទាន់មានប្រវត្តិតាមដានទេ',
                          style: TextStyle(color: Colors.grey[600]),
                        ),
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

class _TimelineEvent extends StatelessWidget {
  const _TimelineEvent({
    required this.label,
    required this.subtitle,
    required this.isLast,
  });

  final String label;
  final String subtitle;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Column(
          children: [
            Container(
              width: 22,
              height: 22,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: _dynamicPrimary(),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.check_rounded,
                size: 14,
                color: Colors.white,
              ),
            ),
            if (!isLast)
              Container(width: 2, height: 40, color: _dynamicPrimary()),
          ],
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(bottom: isLast ? 0 : 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF17231D),
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 12,
                    color: Color(0xFF6D7973),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _RecipientRow extends StatelessWidget {
  const _RecipientRow({
    required this.distribution,
    required this.label,
    required this.isDone,
    required this.isLast,
  });

  final CorrespondenceLetterDistribution distribution;
  final String label;
  final bool isDone;
  final bool isLast;

  String _initials(String name) {
    final trimmed = name.trim();
    if (trimmed.isEmpty) return '?';
    final parts = trimmed.split(RegExp(r'\s+'));
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
        .toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final name = distribution.getTarget();

    return Padding(
      padding: EdgeInsets.only(bottom: isLast ? 0 : 10),
      child: Row(
        children: [
          CircleAvatar(
            radius: 16,
            backgroundColor: const Color(0xFFE8F4EE),
            child: Text(
              _initials(name),
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: _dynamicPrimary(),
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF17231D),
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 11,
                    color: Color(0xFF6D7973),
                  ),
                ),
              ],
            ),
          ),
          Icon(
            isDone ? Icons.check_circle_rounded : Icons.access_time_rounded,
            size: 20,
            color: isDone ? _dynamicPrimary() : const Color(0xFFAEB8B3),
          ),
        ],
      ),
    );
  }
}
