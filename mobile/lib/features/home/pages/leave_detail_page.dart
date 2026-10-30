import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../auth/models/auth_user.dart';
import '../models/leave_request_models.dart';
import '../services/home_leave_service.dart';
import 'home/leave_balance_widgets.dart';

// ─────────────────────────────────────────────────────────────────────────────
//  Leave Detail Screen  –  ព័ត៌មានសំណើ
//  A header coloured by status (type, dates, days), the request's details,
//  the reason and attachment, and the approval flow as a step timeline.
// ─────────────────────────────────────────────────────────────────────────────

String _km(int value) =>
    value
        .toString()
        .split('')
        .map((c) => int.tryParse(c) == null ? c : '០១២៣៤៥៦៧៨៩'[int.parse(c)])
        .join();

String _km2(int value) => _km(value).padLeft(2, '០');

const _months = <String>[
  'មករា',
  'កុម្ភៈ',
  'មីនា',
  'មេសា',
  'ឧសភា',
  'មិថុនា',
  'កក្កដា',
  'សីហា',
  'កញ្ញា',
  'តុលា',
  'វិច្ឆិកា',
  'ធ្នូ',
];

DateTime? _parseDate(String? value) {
  final text = value?.trim() ?? '';
  if (text.isEmpty) return null;
  final parsed =
      DateTime.tryParse(text) ?? DateTime.tryParse(text.replaceFirst(' ', 'T'));
  return parsed != null && parsed.isUtc ? parsed.toLocal() : parsed;
}

/// "២៩ តុលា ២០២៦ · ០៨:០០" (time only when the value carries one).
String _khmerDateTime(String? value) {
  final date = _parseDate(value);
  if (date == null) return (value ?? '').trim();
  final day = '${_km(date.day)} ${_months[date.month - 1]} ${_km(date.year)}';
  return value!.contains(':')
      ? '$day · ${_km2(date.hour)}:${_km2(date.minute)}'
      : day;
}

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
      builder:
          (ctx) => AlertDialog(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(20),
            ),
            icon: Container(
              width: 56,
              height: 56,
              decoration: const BoxDecoration(
                color: Color(0xFFFEE2E2),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.event_busy_rounded,
                color: Color(0xFFEF4444),
                size: 28,
              ),
            ),
            title: const Text(
              'បោះបង់សំណើ',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            content: const Text(
              'តើអ្នកចង់បោះបង់សំណើច្បាប់នេះពិតមែនទេ?',
              textAlign: TextAlign.center,
            ),
            actionsAlignment: MainAxisAlignment.center,
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.of(ctx).pop(false),
                child: Text(_tr('cancel', 'ទេ')),
              ),
              FilledButton(
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFFEF4444),
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
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

  /// One line under the title saying where the request stands.
  String _statusMessage(LeaveRequestItem req) {
    switch (req.status.trim().toLowerCase()) {
      case 'approved':
        return req.approvedDays > 0 && req.approvedDays != req.requestedDays
            ? 'បានអនុម័ត ${_km(req.approvedDays)} ថ្ងៃ'
            : 'សំណើត្រូវបានអនុម័ត';
      case 'rejected':
        return 'សំណើត្រូវបានបដិសេធ';
      case 'cancelled':
        return 'សំណើត្រូវបានបោះបង់';
      default:
        final actor = req.workflowCurrentActorName?.trim() ?? '';
        final step = req.workflowCurrentStepName?.trim() ?? '';
        if (actor.isNotEmpty) return 'កំពុងរង់ចាំ $actor ពិនិត្យ';
        if (step.isNotEmpty) return 'កំពុងរង់ចាំ៖ $step';
        return 'កំពុងរង់ចាំការអនុម័ត';
    }
  }

  /// The approval flow: the submission, every workflow step (done / current
  /// / rejected / upcoming), and how the request ended.
  List<_FlowNode> _flowNodes(LeaveRequestItem req) {
    final status = req.status.trim().toLowerCase();
    final steps = req.workflowSteps;
    final currentOrder =
        req.workflowCurrentStepOrder ??
        steps.where((s) => s.isCurrent).map((s) => s.stepOrder).firstOrNull;
    final currentActor = req.workflowCurrentActorName?.trim() ?? '';

    final nodes = <_FlowNode>[
      _FlowNode(
        title: 'ដាក់សំណើ',
        subtitle: _khmerDateTime(req.submittedAt),
        state: _FlowState.done,
        icon: Icons.send_rounded,
      ),
    ];
    var rejectionShown = false;
    for (final step in steps) {
      final isCurrent =
          step.isCurrent ||
          (currentOrder != null && step.stepOrder == currentOrder);
      final before = currentOrder != null && step.stepOrder < currentOrder;
      final _FlowState state;
      if (status == 'approved') {
        state = _FlowState.done;
      } else if (before) {
        state = _FlowState.done;
      } else if (isCurrent && status == 'rejected') {
        state = _FlowState.rejected;
        rejectionShown = true;
      } else if (isCurrent && status != 'cancelled') {
        state = _FlowState.current;
      } else {
        state = _FlowState.upcoming;
      }
      final actor =
          step.actorName.trim().isNotEmpty
              ? step.actorName.trim()
              : (state == _FlowState.current ? currentActor : '');
      nodes.add(
        _FlowNode(
          title:
              step.stepName.trim().isEmpty
                  ? 'ជំហានទី ${_km(step.stepOrder)}'
                  : step.stepName.trim(),
          subtitle: actor,
          state: state,
          order: step.stepOrder,
        ),
      );
    }
    if (steps.isEmpty && status == 'pending') {
      final step = req.workflowCurrentStepName?.trim() ?? '';
      nodes.add(
        _FlowNode(
          title: step.isEmpty ? 'ការពិនិត្យ និងអនុម័ត' : step,
          subtitle: currentActor,
          state: _FlowState.current,
        ),
      );
    }
    switch (status) {
      case 'approved':
        nodes.add(
          const _FlowNode(
            title: 'បានអនុម័ត',
            state: _FlowState.done,
            icon: Icons.verified_rounded,
          ),
        );
      case 'rejected' when !rejectionShown:
        nodes.add(
          const _FlowNode(title: 'បានបដិសេធ', state: _FlowState.rejected),
        );
      case 'cancelled':
        nodes.add(
          const _FlowNode(
            title: 'បានបោះបង់ដោយអ្នកស្នើ',
            state: _FlowState.cancelled,
          ),
        );
    }
    return nodes;
  }

  // ── Build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final req = widget.request;
    final typeLabel =
        req.leaveTypeKm.trim().isNotEmpty
            ? req.leaveTypeKm.trim()
            : (req.leaveType.trim().isNotEmpty
                ? req.leaveType.trim()
                : 'ប្រភេទច្បាប់មិនបញ្ជាក់');
    final theme = _StatusTheme.of(req.status);
    final workflowSource =
        (req.workflowSourcePolicyModuleKey ?? '').trim().toLowerCase() ==
                'attendance'
            ? 'វត្តមាន'
            : 'ច្បាប់';
    final handover = req.handoverEmployeeName.trim();
    final submitted = _khmerDateTime(req.submittedAt);
    final showApproved =
        req.status.trim().toLowerCase() == 'approved' && req.approvedDays > 0;

    return Scaffold(
      backgroundColor: const Color(0xFFF4F6F8),
      appBar: AppBar(
        backgroundColor: const Color(0xFFF4F6F8),
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: const Text(
          'ព័ត៌មានសំណើ',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
        ),
      ),
      body: ListView(
        // Without the cancel bar, clear Android's navigation bar ourselves.
        padding: EdgeInsets.fromLTRB(
          16,
          4,
          16,
          32 + (req.canCancel ? 0 : MediaQuery.paddingOf(context).bottom),
        ),
        children: <Widget>[
          _Hero(
            theme: theme,
            typeLabel: typeLabel,
            typeIcon: leaveTypeIcon(<String>[req.leaveType, req.leaveTypeKm]),
            statusLabel: theme.label(widget.language),
            message: _statusMessage(req),
            requestId: req.id,
            startDate: req.startDate,
            endDate: req.endDate,
            days: req.requestedDays,
          ),
          const SizedBox(height: 14),
          _Section(
            title: 'ព័ត៌មានលម្អិត',
            icon: Icons.info_outline_rounded,
            child: Column(
              children: <Widget>[
                _InfoTile(
                  icon: Icons.category_rounded,
                  color: const Color(0xFF6366F1),
                  label: 'ប្រភេទច្បាប់',
                  value: typeLabel,
                ),
                _InfoTile(
                  icon: Icons.date_range_rounded,
                  color: const Color(0xFF0EA5E9),
                  label: 'ចំនួនថ្ងៃស្នើសុំ',
                  value: '${_km(req.requestedDays)} ថ្ងៃ',
                ),
                if (showApproved)
                  _InfoTile(
                    icon: Icons.task_alt_rounded,
                    color: const Color(0xFF10B981),
                    label: 'ចំនួនថ្ងៃបានអនុម័ត',
                    value: '${_km(req.approvedDays)} ថ្ងៃ',
                  ),
                if (handover.isNotEmpty)
                  _InfoTile(
                    icon: Icons.handshake_rounded,
                    color: const Color(0xFFF59E0B),
                    label: 'អ្នកទទួលការងារជំនួស',
                    value: handover,
                  ),
                if (submitted.isNotEmpty)
                  _InfoTile(
                    icon: Icons.schedule_send_rounded,
                    color: const Color(0xFF8B5CF6),
                    label: 'ថ្ងៃដាក់សំណើ',
                    value: submitted,
                  ),
                _InfoTile(
                  icon: Icons.tag_rounded,
                  color: const Color(0xFF64748B),
                  label: 'លេខសំណើ',
                  value: '#${req.id}',
                  last: true,
                ),
              ],
            ),
          ),
          if (req.reason.trim().isNotEmpty) ...<Widget>[
            const SizedBox(height: 14),
            _Section(
              title: 'មូលហេតុ',
              icon: Icons.notes_rounded,
              child: _ReasonBlock(text: req.reason.trim(), color: theme.color),
            ),
          ],
          if (req.attachmentUrl?.trim().isNotEmpty == true) ...<Widget>[
            const SizedBox(height: 14),
            _Section(
              title: 'ឯកសារភ្ជាប់',
              icon: Icons.attach_file_rounded,
              child: _AttachmentTile(onTap: _openAttachment),
            ),
          ],
          const SizedBox(height: 14),
          _Section(
            title: 'លំហូរអនុម័ត',
            icon: Icons.route_rounded,
            trailing: _SourceChip(label: 'ប្រភព៖ $workflowSource'),
            child: _FlowTimeline(nodes: _flowNodes(req)),
          ),
        ],
      ),

      // ── Action bar ────────────────────────────────────────────────────
      bottomNavigationBar:
          req.canCancel
              ? Container(
                padding: EdgeInsets.fromLTRB(
                  16,
                  12,
                  16,
                  12 + MediaQuery.of(context).padding.bottom,
                ),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: Color(0x14000000),
                      blurRadius: 18,
                      offset: Offset(0, -4),
                    ),
                  ],
                ),
                child: FilledButton.icon(
                  onPressed: _cancelling ? null : _cancel,
                  style: FilledButton.styleFrom(
                    backgroundColor: const Color(0xFFFEE2E2),
                    foregroundColor: const Color(0xFFDC2626),
                    disabledBackgroundColor: const Color(0xFFFEF2F2),
                    minimumSize: const Size(double.infinity, 52),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                  ),
                  icon:
                      _cancelling
                          ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Color(0xFFDC2626),
                            ),
                          )
                          : const Icon(Icons.event_busy_rounded, size: 20),
                  label: Text(
                    _cancelling ? 'កំពុងដំណើរការ...' : 'បោះបង់សំណើ',
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              )
              : null,
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Header
// ─────────────────────────────────────────────────────────────────────────────

class _Hero extends StatelessWidget {
  const _Hero({
    required this.theme,
    required this.typeLabel,
    required this.typeIcon,
    required this.statusLabel,
    required this.message,
    required this.requestId,
    required this.startDate,
    required this.endDate,
    required this.days,
  });

  final _StatusTheme theme;
  final String typeLabel;
  final IconData typeIcon;
  final String statusLabel;
  final String message;
  final int requestId;
  final String startDate;
  final String endDate;
  final int days;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(26),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: theme.color.withAlpha(60),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(26),
        child: Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: theme.gradient,
            ),
          ),
          child: Stack(
            children: <Widget>[
              const Positioned(
                right: -36,
                top: -46,
                child: _Blob(size: 160, opacity: 0.12),
              ),
              const Positioned(
                left: -40,
                bottom: -70,
                child: _Blob(size: 150, opacity: 0.08),
              ),
              Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Container(
                          width: 50,
                          height: 50,
                          decoration: BoxDecoration(
                            color: Colors.white.withAlpha(45),
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: Icon(typeIcon, color: Colors.white, size: 26),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                typeLabel,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                  height: 1.4,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                message,
                                style: TextStyle(
                                  color: Colors.white.withAlpha(225),
                                  fontSize: 12.5,
                                  height: 1.4,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: <Widget>[
                        _GlassChip(icon: theme.icon, label: statusLabel),
                        _GlassChip(
                          icon: Icons.tag_rounded,
                          label: 'សំណើលេខ $requestId',
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    _DateTicket(start: startDate, end: endDate, days: days),
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
  const _Blob({required this.size, required this.opacity});

  final double size;
  final double opacity;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withValues(alpha: opacity),
      ),
    );
  }
}

class _GlassChip extends StatelessWidget {
  const _GlassChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: Colors.white.withAlpha(42),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withAlpha(60)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 15, color: Colors.white),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              label,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// From → (days) → To, as a frosted card inside the header.
class _DateTicket extends StatelessWidget {
  const _DateTicket({
    required this.start,
    required this.end,
    required this.days,
  });

  final String start;
  final String end;
  final int days;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 14),
      decoration: BoxDecoration(
        color: Colors.white.withAlpha(36),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Colors.white.withAlpha(55)),
      ),
      child: Row(
        children: <Widget>[
          Expanded(child: _DateColumn(label: 'ចាប់ពីថ្ងៃ', value: start)),
          Container(
            width: 62,
            height: 62,
            decoration: BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: Colors.black.withAlpha(25),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            padding: const EdgeInsets.all(6),
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  Text(
                    _km(days),
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                      color: Color(0xFF0F172A),
                      height: 1.1,
                    ),
                  ),
                  const Text(
                    'ថ្ងៃ',
                    style: TextStyle(
                      fontSize: 11,
                      color: Color(0xFF64748B),
                      height: 1.2,
                    ),
                  ),
                ],
              ),
            ),
          ),
          Expanded(child: _DateColumn(label: 'ដល់ថ្ងៃ', value: end)),
        ],
      ),
    );
  }
}

class _DateColumn extends StatelessWidget {
  const _DateColumn({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final date = _parseDate(value);
    return Column(
      children: <Widget>[
        Text(
          label,
          textAlign: TextAlign.center,
          style: TextStyle(color: Colors.white.withAlpha(215), fontSize: 11),
        ),
        const SizedBox(height: 2),
        if (date == null)
          Text(
            value.trim().isEmpty ? '-' : value.trim(),
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
          )
        else ...<Widget>[
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              _km(date.day),
              style: const TextStyle(
                color: Colors.white,
                fontSize: 28,
                fontWeight: FontWeight.w900,
                height: 1.2,
              ),
            ),
          ),
          Text(
            '${_months[date.month - 1]} ${_km(date.year)}',
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ],
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Sections
// ─────────────────────────────────────────────────────────────────────────────

class _Section extends StatelessWidget {
  const _Section({
    required this.title,
    required this.icon,
    required this.child,
    this.trailing,
  });

  final String title;
  final IconData icon;
  final Widget child;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x0A0F172A),
            blurRadius: 14,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(icon, size: 17, color: const Color(0xFF64748B)),
              const SizedBox(width: 7),
              Expanded(
                flex: 3,
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF0F172A),
                  ),
                ),
              ),
              if (trailing != null)
                Flexible(
                  flex: 2,
                  child: Align(
                    alignment: Alignment.centerRight,
                    child: FittedBox(fit: BoxFit.scaleDown, child: trailing),
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

class _InfoTile extends StatelessWidget {
  const _InfoTile({
    required this.icon,
    required this.color,
    required this.label,
    required this.value,
    this.last = false,
  });

  final IconData icon;
  final Color color;
  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        border:
            last
                ? null
                : const Border(bottom: BorderSide(color: Color(0xFFF1F5F9))),
      ),
      child: Row(
        children: <Widget>[
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: color.withAlpha(26),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, size: 19, color: color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 11.5,
                    color: Color(0xFF94A3B8),
                  ),
                ),
                const SizedBox(height: 1),
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF0F172A),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ReasonBlock extends StatelessWidget {
  const _ReasonBlock({required this.text, required this.color});

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border(left: BorderSide(color: color, width: 4)),
      ),
      child: Text(
        text,
        style: const TextStyle(
          fontSize: 14,
          color: Color(0xFF334155),
          height: 1.6,
        ),
      ),
    );
  }
}

class _AttachmentTile extends StatelessWidget {
  const _AttachmentTile({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFFEFF6FF),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: <Widget>[
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.description_rounded,
                  color: Color(0xFF3B82F6),
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'មើលឯកសារ',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF1D4ED8),
                      ),
                    ),
                    Text(
                      'ចុចដើម្បីបើកឯកសារភ្ជាប់',
                      style: TextStyle(
                        fontSize: 11.5,
                        color: Color(0xFF64748B),
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(
                Icons.open_in_new_rounded,
                size: 18,
                color: Color(0xFF3B82F6),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SourceChip extends StatelessWidget {
  const _SourceChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: const TextStyle(
          fontSize: 11.5,
          fontWeight: FontWeight.w600,
          color: Color(0xFF475569),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Approval flow timeline
// ─────────────────────────────────────────────────────────────────────────────

enum _FlowState { done, current, rejected, upcoming, cancelled }

class _FlowNode {
  const _FlowNode({
    required this.title,
    required this.state,
    this.subtitle = '',
    this.icon,
    this.order,
  });

  final String title;
  final String subtitle;
  final _FlowState state;
  final IconData? icon;
  final int? order;
}

class _FlowTimeline extends StatelessWidget {
  const _FlowTimeline({required this.nodes});

  final List<_FlowNode> nodes;

  static const _green = Color(0xFF10B981);
  static const _amber = Color(0xFFF59E0B);
  static const _red = Color(0xFFEF4444);
  static const _grey = Color(0xFFCBD5E1);

  Color _color(_FlowState state) => switch (state) {
    _FlowState.done => _green,
    _FlowState.current => _amber,
    _FlowState.rejected => _red,
    _FlowState.upcoming || _FlowState.cancelled => _grey,
  };

  Widget _dot(_FlowNode node) {
    final color = _color(node.state);
    if (node.state == _FlowState.upcoming && node.icon == null) {
      return Container(
        width: 30,
        height: 30,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
          border: Border.all(color: _grey, width: 2),
        ),
        child: Text(
          node.order == null ? '' : _km(node.order!),
          style: const TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w800,
            color: Color(0xFF94A3B8),
          ),
        ),
      );
    }
    final icon =
        node.icon ??
        switch (node.state) {
          _FlowState.done => Icons.check_rounded,
          _FlowState.current => Icons.hourglass_top_rounded,
          _FlowState.rejected => Icons.close_rounded,
          _FlowState.cancelled => Icons.block_rounded,
          _FlowState.upcoming => Icons.more_horiz_rounded,
        };
    return Container(
      width: 30,
      height: 30,
      decoration: BoxDecoration(
        color:
            node.state == _FlowState.cancelled
                ? const Color(0xFF94A3B8)
                : color,
        shape: BoxShape.circle,
        boxShadow:
            node.state == _FlowState.current
                ? <BoxShadow>[
                  BoxShadow(color: color.withAlpha(70), spreadRadius: 5),
                ]
                : null,
      ),
      child: Icon(icon, size: 17, color: Colors.white),
    );
  }

  Widget? _badge(_FlowState state) {
    final (String label, Color color) = switch (state) {
      _FlowState.current => ('កំពុងរង់ចាំ', const Color(0xFFB45309)),
      _FlowState.rejected => ('បានបដិសេធ', const Color(0xFFB91C1C)),
      _ => ('', Colors.transparent),
    };
    if (label.isEmpty) return null;
    return Container(
      margin: const EdgeInsets.only(top: 6),
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
      decoration: BoxDecoration(
        color: color.withAlpha(24),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: color,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        for (var i = 0; i < nodes.length; i++)
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                SizedBox(
                  width: 34,
                  child: Column(
                    children: <Widget>[
                      _dot(nodes[i]),
                      if (i < nodes.length - 1)
                        Expanded(
                          child: Container(
                            width: 2.5,
                            margin: const EdgeInsets.symmetric(vertical: 3),
                            decoration: BoxDecoration(
                              color:
                                  nodes[i].state == _FlowState.done &&
                                          nodes[i + 1].state !=
                                              _FlowState.upcoming
                                      ? _green
                                      : const Color(0xFFE2E8F0),
                              borderRadius: BorderRadius.circular(2),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Padding(
                    padding: EdgeInsets.only(
                      top: 4,
                      bottom: i < nodes.length - 1 ? 18 : 0,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          nodes[i].title,
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color:
                                nodes[i].state == _FlowState.upcoming
                                    ? const Color(0xFF94A3B8)
                                    : const Color(0xFF0F172A),
                          ),
                        ),
                        if (nodes[i].subtitle.isNotEmpty)
                          Text(
                            nodes[i].subtitle,
                            style: const TextStyle(
                              fontSize: 12,
                              color: Color(0xFF64748B),
                            ),
                          ),
                        if (_badge(nodes[i].state) case final badge?) badge,
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Status theme
// ─────────────────────────────────────────────────────────────────────────────

class _StatusTheme {
  const _StatusTheme(this._status, this.color, this.gradient, this.icon);

  final String _status;
  final Color color;
  final List<Color> gradient;
  final IconData icon;

  static _StatusTheme of(String status) {
    final s = status.trim().toLowerCase();
    switch (s) {
      case 'approved':
        return const _StatusTheme('approved', Color(0xFF10B981), <Color>[
          Color(0xFF10B981),
          Color(0xFF0F766E),
        ], Icons.check_circle_rounded);
      case 'rejected':
        return const _StatusTheme('rejected', Color(0xFFEF4444), <Color>[
          Color(0xFFF43F5E),
          Color(0xFFB91C1C),
        ], Icons.cancel_rounded);
      case 'cancelled':
        return const _StatusTheme('cancelled', Color(0xFF94A3B8), <Color>[
          Color(0xFF94A3B8),
          Color(0xFF475569),
        ], Icons.block_rounded);
      default:
        return const _StatusTheme('pending', Color(0xFFF59E0B), <Color>[
          Color(0xFFF59E0B),
          Color(0xFFEA580C),
        ], Icons.hourglass_top_rounded);
    }
  }

  String label(Map<String, String> language) {
    switch (_status) {
      case 'approved':
        return language['approved'] ?? 'អនុម័ត';
      case 'rejected':
        return language['rejected'] ?? 'បដិសេធ';
      case 'cancelled':
        return language['cancelled'] ?? 'បោះបង់';
      default:
        return language['pending'] ?? 'រង់ចាំ';
    }
  }
}
