import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../auth/models/auth_user.dart';
import '../models/leave_request_models.dart';
import '../services/home_leave_service.dart';

// ─────────────────────────────────────────────────────────────────────────────
//  Leave Detail Screen  –  ព័ត៌មានលម្អិតសំណើ
// ─────────────────────────────────────────────────────────────────────────────

class LeaveDetailPage extends StatefulWidget {
  const LeaveDetailPage({
    super.key,
    required this.request,
    required this.language,
    required this.leaveService,
    required this.user,
    this.onCancelled,
  });

  final LeaveRequestItem request;
  final Map<String, String> language;
  final HomeLeaveService leaveService;
  final AuthUser user;
  final VoidCallback? onCancelled;

  @override
  State<LeaveDetailPage> createState() => _LeaveDetailPageState();
}

class _LeaveDetailPageState extends State<LeaveDetailPage> {
  bool _cancelling = false;

  String _tr(String key, String fallback) {
    final v = widget.language[key]?.trim();
    return (v == null || v.isEmpty) ? fallback : v;
  }

  Future<void> _cancel() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text(
          'បោះបង់សំណើ',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        content: const Text('តើអ្នកចង់បោះបង់សំណើច្បាប់នេះពិតមែនទេ?'),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: Text(_tr('cancel', 'ទេ')),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFEF4444),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('បោះបង់សំណើ'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    setState(() => _cancelling = true);
    try {
      await widget.leaveService.cancelRequest(
        user: widget.user,
        requestId: widget.request.id,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('បោះបង់សំណើបានជោគជ័យ'),
          backgroundColor: Color(0xFF10B981),
        ),
      );
      widget.onCancelled?.call();
      Navigator.of(context).pop();
    } catch (e) {
      if (mounted) {
        final msg = extractApiErrorMessage(e);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(msg),
            backgroundColor: const Color(0xFFEF4444),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _cancelling = false);
    }
  }

  Future<void> _openAttachment() async {
    final url = widget.request.attachmentUrl;
    if (url == null || url.trim().isEmpty) return;
    final uri = Uri.tryParse(url.trim());
    if (uri == null) return;
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  // ── Build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final req = widget.request;
    final typeLabel =
        req.leaveTypeKm.trim().isNotEmpty ? req.leaveTypeKm : req.leaveType;
    final si = _statusInfo(req.status);
    final canCancel = req.canCancel;

    return Scaffold(
      backgroundColor: const Color(0xFFF4F6F8),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: const Text(
          'ព័ត៌មានសំណើ',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
        ),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Container(height: 1, color: const Color(0xFFF0F0F0)),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
        children: <Widget>[
          // ── Status hero card ──────────────────────────────────────────
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              boxShadow: const <BoxShadow>[
                BoxShadow(
                  color: Color(0x0C000000),
                  blurRadius: 16,
                  offset: Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: si.color.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Icon(Icons.event_available_outlined,
                          size: 24, color: si.color),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        typeLabel,
                        style: const TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0F172A),
                        ),
                      ),
                    ),
                    _StatusBadge(
                      label: si.label(widget.language),
                      color: si.color,
                      large: true,
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                const Divider(height: 1, color: Color(0xFFF1F5F9)),
                const SizedBox(height: 16),

                // Date range
                _DetailRow(
                  icon: Icons.calendar_today_outlined,
                  label: 'ថ្ងៃចាប់ផ្តើម',
                  value: req.startDate.isNotEmpty ? req.startDate : '-',
                ),
                const SizedBox(height: 10),
                _DetailRow(
                  icon: Icons.event_outlined,
                  label: 'ថ្ងៃបញ្ចប់',
                  value: req.endDate.isNotEmpty ? req.endDate : '-',
                ),
                const SizedBox(height: 10),
                _DetailRow(
                  icon: Icons.schedule_outlined,
                  label: 'ចំនួនថ្ងៃ',
                  value: '${req.requestedDays} ថ្ងៃ',
                  valueStyle: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF0B6B58),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),

          // ── Reason card ───────────────────────────────────────────────
          if (req.reason.trim().isNotEmpty)
            _InfoCard(
              title: 'មូលហេតុ',
              icon: Icons.notes_rounded,
              child: Text(
                req.reason,
                style: const TextStyle(
                  fontSize: 14,
                  color: Color(0xFF334155),
                  height: 1.5,
                ),
              ),
            ),

          if (req.reason.trim().isNotEmpty) const SizedBox(height: 12),

          // ── Attachment card ───────────────────────────────────────────
          if (req.attachmentUrl?.trim().isNotEmpty == true)
            _InfoCard(
              title: 'ឯកសារភ្ជាប់',
              icon: Icons.attach_file_rounded,
              child: GestureDetector(
                onTap: _openAttachment,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF6FF),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                        color: const Color(0xFF3B82F6).withValues(alpha: 0.3)),
                  ),
                  child: Row(
                    children: <Widget>[
                      const Icon(Icons.description_outlined,
                          size: 20, color: Color(0xFF3B82F6)),
                      const SizedBox(width: 10),
                      const Expanded(
                        child: Text(
                          'មើលឯកសារ',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF3B82F6),
                          ),
                        ),
                      ),
                      const Icon(Icons.open_in_new_rounded,
                          size: 16, color: Color(0xFF3B82F6)),
                    ],
                  ),
                ),
              ),
            ),

          if (req.attachmentUrl?.trim().isNotEmpty == true)
            const SizedBox(height: 12),

          // ── Request meta ──────────────────────────────────────────────
          _InfoCard(
            title: 'ព័ត៌មានបន្ថែម',
            icon: Icons.info_outline_rounded,
            child: Column(
              children: <Widget>[
                _DetailRow(
                  icon: Icons.tag,
                  label: 'លេខសំណើ',
                  value: '#${req.id}',
                ),
                const SizedBox(height: 8),
                _DetailRow(
                  icon: Icons.category_outlined,
                  label: 'ប្រភេទ',
                  value: typeLabel,
                ),
              ],
            ),
          ),
        ],
      ),

      // ── Action bar ────────────────────────────────────────────────────
      bottomNavigationBar: canCancel
          ? Container(
              padding: EdgeInsets.fromLTRB(
                  16, 12, 16, 12 + MediaQuery.of(context).padding.bottom),
              decoration: const BoxDecoration(
                color: Colors.white,
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: Color(0x10000000),
                    blurRadius: 12,
                    offset: Offset(0, -4),
                  ),
                ],
              ),
              child: SizedBox(
                height: 50,
                child: OutlinedButton.icon(
                  onPressed: _cancelling ? null : _cancel,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: const Color(0xFFEF4444),
                    side: const BorderSide(
                        color: Color(0xFFEF4444), width: 1.5),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(13)),
                  ),
                  icon: _cancelling
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Color(0xFFEF4444),
                          ),
                        )
                      : const Icon(Icons.cancel_outlined, size: 18),
                  label: Text(
                    _cancelling ? 'កំពុងដំណើរការ...' : 'បោះបង់សំណើ',
                    style: const TextStyle(
                        fontSize: 15, fontWeight: FontWeight.w600),
                  ),
                ),
              ),
            )
          : null,
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Sub-widgets
// ─────────────────────────────────────────────────────────────────────────────

class _InfoCard extends StatelessWidget {
  const _InfoCard({
    required this.title,
    required this.icon,
    required this.child,
  });

  final String title;
  final IconData icon;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x08000000),
            blurRadius: 10,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(icon, size: 15, color: const Color(0xFF94A3B8)),
              const SizedBox(width: 6),
              Text(
                title,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF64748B),
                  letterSpacing: 0.3,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.icon,
    required this.label,
    required this.value,
    this.valueStyle,
  });

  final IconData icon;
  final String label;
  final String value;
  final TextStyle? valueStyle;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: <Widget>[
        Container(
          width: 30,
          height: 30,
          decoration: BoxDecoration(
            color: const Color(0xFFF8FAFC),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, size: 15, color: const Color(0xFF64748B)),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                label,
                style: const TextStyle(
                  fontSize: 11,
                  color: Color(0xFF94A3B8),
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 1),
              Text(
                value,
                style: valueStyle ??
                    const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF0F172A),
                    ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({
    required this.label,
    required this.color,
    this.large = false,
  });

  final String label;
  final Color color;
  final bool large;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: large ? 14 : 10,
        vertical: large ? 6 : 4,
      ),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: large ? 13 : 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Status helpers
// ─────────────────────────────────────────────────────────────────────────────

class _StatusInfo {
  const _StatusInfo(this._s);
  final String _s;

  Color get color {
    switch (_s.trim().toLowerCase()) {
      case 'approved': return const Color(0xFF10B981);
      case 'rejected': return const Color(0xFFEF4444);
      case 'cancelled': return const Color(0xFF9CA3AF);
      default: return const Color(0xFFF59E0B);
    }
  }

  String label(Map<String, String> language) {
    switch (_s.trim().toLowerCase()) {
      case 'approved': return language['approved'] ?? 'អនុម័ត';
      case 'rejected': return language['rejected'] ?? 'បដិសេធ';
      case 'cancelled': return language['cancelled'] ?? 'បោះបង់';
      default: return language['pending'] ?? 'រង់ចាំ';
    }
  }
}

_StatusInfo _statusInfo(String status) => _StatusInfo(status);
