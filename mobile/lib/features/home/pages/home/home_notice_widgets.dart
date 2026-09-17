import 'package:flutter/material.dart';

class NoticeFeedCard extends StatelessWidget {
  const NoticeFeedCard({
    super.key,
    required this.title,
    required this.description,
    required this.meta,
    required this.typeLabel,
    required this.dateLabel,
    required this.audienceLabel,
    required this.contextLabel,
    required this.stepName,
    required this.source,
    required this.unread,
    this.onTap,
    this.onMarkRead,
    this.actionLabel,
    this.compact = false,
  });

  final String title;
  final String description;
  final String meta;
  final String typeLabel;
  final String dateLabel;
  final String audienceLabel;
  final String contextLabel;
  final String stepName;
  final String source;
  final bool unread;
  final VoidCallback? onTap;
  final VoidCallback? onMarkRead;
  final String? actionLabel;

  /// Simple mode for previews (e.g. the Dashboard's "ការជូនដំណឹងថ្មី"
  /// section): shows only icon/title/description/date/unread, dropping the
  /// type/audience/context tag row and the mark-as-read action. The full
  /// Notice tab keeps the richer default display.
  final bool compact;

  IconData _iconForSource() {
    final normalizedContext = contextLabel.trim().toLowerCase();

    if (normalizedContext.contains('អនុម័ត') ||
        normalizedContext.contains('approve')) {
      return Icons.task_alt_rounded;
    }
    if (normalizedContext.contains('បដិសេធ') ||
        normalizedContext.contains('reject')) {
      return Icons.cancel_outlined;
    }
    if (normalizedContext.contains('ផ្ទេរ') ||
        normalizedContext.contains('forward')) {
      return Icons.swap_horiz_rounded;
    }
    if (normalizedContext.contains('រង់ចាំ') ||
        normalizedContext.contains('pending')) {
      return Icons.pending_actions_rounded;
    }

    switch (source) {
      case 'leave_workflow':
        return Icons.event_note_rounded;
      case 'attendance_workflow':
        return Icons.fact_check_outlined;
      case 'correspondence_workflow':
        return Icons.mail_outline_rounded;
      default:
        return unread
            ? Icons.notifications_active_outlined
            : Icons.notifications_none;
    }
  }

  Color _iconTint() {
    switch (source) {
      case 'leave_workflow':
        return const Color(0xFF2563EB);
      case 'attendance_workflow':
        return const Color(0xFFF59E0B);
      case 'correspondence_workflow':
        return const Color(0xFF059669);
      default:
        return unread ? const Color(0xFF1D4F91) : const Color(0xFF64748B);
    }
  }

  Color _iconBg() {
    switch (source) {
      case 'leave_workflow':
        return const Color(0xFFEAF1FF);
      case 'attendance_workflow':
        return const Color(0xFFFFF4E5);
      case 'correspondence_workflow':
        return const Color(0xFFECFDF3);
      default:
        return unread ? const Color(0xFFEAF1FF) : const Color(0xFFF2F4F7);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: unread ? const Color(0xFFD6E6FF) : const Color(0xFFE2EAE7),
            ),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0A14211D),
                blurRadius: 10,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: _iconBg(),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(_iconForSource(), color: _iconTint(), size: 18),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              title,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Color(0xFF10211B),
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          if (unread)
                            Container(
                              width: 8,
                              height: 8,
                              decoration: const BoxDecoration(
                                color: Color(0xFF1D4F91),
                                shape: BoxShape.circle,
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        description,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF334155),
                          fontSize: 13,
                          height: 1.45,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      if (compact) ...[
                        if (dateLabel.trim().isNotEmpty) ...[
                          const SizedBox(height: 6),
                          Text(
                            dateLabel,
                            style: const TextStyle(
                              color: Color(0xFF64748B),
                              fontSize: 11.5,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ] else ...[
                        const SizedBox(height: 7),
                        Wrap(
                          spacing: 8,
                          runSpacing: 6,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF2F4F7),
                                borderRadius: BorderRadius.circular(999),
                              ),
                              child: Text(
                                typeLabel,
                                style: const TextStyle(
                                  color: Color(0xFF475467),
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            if (audienceLabel.trim().isNotEmpty)
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFEAF7F2),
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: Text(
                                  audienceLabel,
                                  style: const TextStyle(
                                    color: Color(0xFF0F766E),
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            if (contextLabel.trim().isNotEmpty)
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFFFF4E5),
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: Text(
                                  contextLabel,
                                  style: const TextStyle(
                                    color: Color(0xFFB54708),
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            if (dateLabel.trim().isNotEmpty)
                              Text(
                                dateLabel,
                                style: const TextStyle(
                                  color: Color(0xFF64748B),
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 7),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (stepName.trim().isNotEmpty)
                                    Text(
                                      stepName,
                                      style: const TextStyle(
                                        color: Color(0xFF334155),
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  if (meta.trim().isNotEmpty)
                                    Text(
                                      meta,
                                      style: const TextStyle(
                                        color: Color(0xFF64748B),
                                        fontSize: 12,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                ],
                              ),
                            ),
                            if (onMarkRead != null)
                              TextButton(
                                onPressed: onMarkRead,
                                style: TextButton.styleFrom(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 8,
                                  ),
                                  minimumSize: const Size(0, 30),
                                  tapTargetSize:
                                      MaterialTapTargetSize.shrinkWrap,
                                ),
                                child: Text(
                                  (actionLabel ?? 'Mark as read').trim(),
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
