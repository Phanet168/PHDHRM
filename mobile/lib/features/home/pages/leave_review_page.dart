import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_design_system.dart';
import '../../auth/models/auth_user.dart';
import '../models/leave_request_models.dart';
import '../services/home_leave_service.dart';
import 'home/home_profile_widgets.dart';
import 'home/home_theme.dart';

/// "ពិនិត្យ/អនុម័តសំណើ": leave requests waiting for this reviewer. Each card
/// shows who, which leave, when, why and where it is in the approval chain,
/// with approve / reject (+ an optional note) and the attachment.
class LeaveReviewPage extends StatefulWidget {
  const LeaveReviewPage({
    super.key,
    required this.user,
    required this.language,
    required this.leaveService,
    this.embedded = false,
  });

  final AuthUser user;
  final Map<String, String> language;
  final HomeLeaveService leaveService;

  /// Shown inside the home screen, which already has its own app bar.
  final bool embedded;

  @override
  State<LeaveReviewPage> createState() => _LeaveReviewPageState();
}

class _LeaveReviewPageState extends State<LeaveReviewPage> {
  List<LeaveRequestItem> _requests = <LeaveRequestItem>[];
  bool _loading = true;
  String? _loadError;

  /// The request whose decision is being sent (its buttons show progress).
  int? _submittingId;

  static const _approveColor = AppDesignSystem.success;
  static const _rejectColor = Color(0xFFD34B5F);

  bool _canReviewItem(LeaveRequestItem item) {
    final employeeUserId = item.employeeUserId;
    if (employeeUserId != null &&
        employeeUserId > 0 &&
        employeeUserId == widget.user.userId) {
      return false;
    }

    return item.status.trim().toLowerCase() == 'pending';
  }

  @override
  void initState() {
    super.initState();
    _loadPendingReviews();
  }

  Future<void> _loadPendingReviews() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });

    try {
      final requests = await widget.leaveService.fetchPendingReviews(
        widget.user,
      );
      if (!mounted) {
        return;
      }

      setState(() {
        _requests = requests.where(_canReviewItem).toList();
      });
    } catch (error) {
      if (mounted) {
        setState(() => _loadError = extractApiErrorMessage(error));
      }
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
        });
      }
    }
  }

  Future<void> _submitReview(LeaveRequestItem item, String action) async {
    if (!_canReviewItem(item)) {
      _showMessage(
        _tr('permission_denied', 'មិនអាចពិនិត្យសំណើរបស់ខ្លួនឯងបានទេ'),
        isError: true,
      );
      return;
    }

    final note = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder:
          (_) => _DecisionSheet(
            item: item,
            approve: action == 'approve',
            leaveName: _leaveName(item),
            dateRange: _dateRange(item),
          ),
    );
    if (note == null || !mounted) {
      return;
    }

    setState(() => _submittingId = item.id);
    try {
      final message = await widget.leaveService.reviewRequest(
        user: widget.user,
        requestId: item.id,
        action: action,
        note: note,
      );
      if (!mounted) return;
      // The decided request leaves the list at once; a reload follows.
      setState(() => _requests.removeWhere((r) => r.id == item.id));
      _showMessage(message);
      await _loadPendingReviews();
    } catch (error) {
      if (mounted) {
        _showMessage(extractApiErrorMessage(error), isError: true);
      }
    } finally {
      if (mounted) {
        setState(() => _submittingId = null);
      }
    }
  }

  String _tr(String key, String fallback) {
    return widget.language[key] ?? fallback;
  }

  static const _months = [
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

  static String _km(int value) =>
      value.toString().split('').map((c) => '០១២៣៤៥៦៧៨៩'[int.parse(c)]).join();

  static DateTime? _parse(String value) {
    final text = value.trim();
    if (text.isEmpty) return null;
    return DateTime.tryParse(text) ??
        DateTime.tryParse(text.replaceFirst(' ', 'T'));
  }

  /// "៣០ តុលា ២០២៦" (or the raw text when it is not a date).
  static String _formatDateDisplay(String value) {
    final parsed = _parse(value);
    if (parsed == null) {
      return value.trim().isEmpty ? '-' : value.trim();
    }
    return '${_km(parsed.day)} ${_months[parsed.month - 1]} ${_km(parsed.year)}';
  }

  static String _dateRange(LeaveRequestItem item) {
    final start = _formatDateDisplay(item.startDate);
    final end = _formatDateDisplay(item.endDate);
    return start == end ? start : '$start → $end';
  }

  static String _leaveName(LeaveRequestItem item) {
    if (item.leaveTypeKm.trim().isNotEmpty) return item.leaveTypeKm.trim();
    if (item.leaveType.trim().isNotEmpty) return item.leaveType.trim();
    return 'ប្រភេទច្បាប់មិនបញ្ជាក់';
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? _rejectColor : null,
      ),
    );
  }

  Future<void> _openAttachment(String url) async {
    final uri = Uri.tryParse(url.trim());
    if (uri == null) {
      _showMessage(
        _tr('attachment', 'តំណឯកសារភ្ជាប់មិនត្រឹមត្រូវ'),
        isError: true,
      );
      return;
    }

    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched && mounted) {
      _showMessage(_tr('attachment', 'មិនអាចបើកឯកសារភ្ជាប់បាន'), isError: true);
    }
  }

  Widget _summary() {
    final totalDays = _requests.fold<int>(0, (sum, r) => sum + r.requestedDays);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [dashboardHeaderStart, dashboardHeaderEnd],
        ),
        boxShadow: [
          BoxShadow(
            color: dashboardHeaderStart.withAlpha(60),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: Colors.white.withAlpha(40),
              borderRadius: BorderRadius.circular(16),
            ),
            child: const Icon(
              Icons.fact_check_rounded,
              color: Colors.white,
              size: 28,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'សំណើរង់ចាំការពិនិត្យ',
                  style: TextStyle(color: Colors.white70, fontSize: 12),
                ),
                Text(
                  '${_km(_requests.length)} សំណើ',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (_requests.isNotEmpty)
                  Text(
                    'សរុប ${_km(totalDays)} ថ្ងៃឈប់',
                    style: const TextStyle(color: Colors.white, fontSize: 12),
                  ),
              ],
            ),
          ),
          if (widget.embedded)
            IconButton(
              tooltip: _tr('refresh', 'ធ្វើបច្ចុប្បន្នភាព'),
              onPressed: _loading ? null : _loadPendingReviews,
              icon: const Icon(Icons.refresh_rounded, color: Colors.white),
            ),
        ],
      ),
    );
  }

  Widget _emptyState() {
    final failed = _loadError != null;
    return Padding(
      padding: const EdgeInsets.only(top: 48),
      child: Column(
        children: [
          Container(
            width: 96,
            height: 96,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: (failed ? _rejectColor : _approveColor).withAlpha(24),
            ),
            child: Icon(
              failed ? Icons.cloud_off_rounded : Icons.task_alt_rounded,
              size: 48,
              color: failed ? _rejectColor : _approveColor,
            ),
          ),
          const SizedBox(height: 16),
          Text(
            failed ? 'មិនអាចផ្ទុកសំណើបានទេ' : 'មិនមានសំណើរង់ចាំទេ',
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 6),
          Text(
            failed ? _loadError! : 'អ្នកបានពិនិត្យសំណើទាំងអស់រួចហើយ ✓',
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 12,
              color: AppDesignSystem.textSecondary,
            ),
          ),
          if (failed) ...[
            const SizedBox(height: 14),
            OutlinedButton.icon(
              onPressed: _loadPendingReviews,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('ព្យាយាមម្ដងទៀត'),
            ),
          ],
        ],
      ),
    );
  }

  Widget _body() {
    if (_loading && _requests.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    return RefreshIndicator(
      onRefresh: _loadPendingReviews,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        children: [
          StaggeredEntrance(index: 0, child: _summary()),
          const SizedBox(height: 16),
          if (_requests.isEmpty)
            _emptyState()
          else
            for (var i = 0; i < _requests.length; i++)
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: StaggeredEntrance(
                  index: i + 1,
                  child: _ReviewCard(
                    item: _requests[i],
                    leaveName: _leaveName(_requests[i]),
                    dateRange: _dateRange(_requests[i]),
                    submittedAt: _requests[i].submittedAt,
                    busy: _submittingId == _requests[i].id,
                    locked: _submittingId != null,
                    onApprove: () => _submitReview(_requests[i], 'approve'),
                    onReject: () => _submitReview(_requests[i], 'reject'),
                    onOpenAttachment:
                        _requests[i].attachmentUrl?.trim().isNotEmpty == true
                            ? () => _openAttachment(_requests[i].attachmentUrl!)
                            : null,
                  ),
                ),
              ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (widget.embedded) {
      return ColoredBox(color: AppDesignSystem.bg, child: _body());
    }
    return Scaffold(
      backgroundColor: AppDesignSystem.bg,
      appBar: AppBar(
        title: Text(
          _tr('approve_leave', 'ពិនិត្យ/អនុម័តសំណើ'),
          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
        ),
        foregroundColor: Colors.white,
        flexibleSpace: Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [dashboardHeaderStart, dashboardHeaderEnd],
            ),
          ),
        ),
        actions: [
          IconButton(
            tooltip: _tr('refresh', 'ធ្វើបច្ចុប្បន្នភាព'),
            onPressed: _loading ? null : _loadPendingReviews,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: _body(),
    );
  }
}

/// One pending request: requester, leave + dates + days, reason, approval
/// chain, attachment, and the approve / reject buttons.
class _ReviewCard extends StatelessWidget {
  const _ReviewCard({
    required this.item,
    required this.leaveName,
    required this.dateRange,
    required this.submittedAt,
    required this.busy,
    required this.locked,
    required this.onApprove,
    required this.onReject,
    required this.onOpenAttachment,
  });

  final LeaveRequestItem item;
  final String leaveName;
  final String dateRange;
  final String? submittedAt;

  /// This request's decision is being sent.
  final bool busy;

  /// Some request's decision is being sent (disables every button).
  final bool locked;
  final VoidCallback onApprove;
  final VoidCallback onReject;
  final VoidCallback? onOpenAttachment;

  static String _submitted(String? value) {
    final at = DateTime.tryParse(value ?? '')?.toLocal();
    if (at == null) return '';
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(at.day)}/${two(at.month)}/${at.year} ${two(at.hour)}:${two(at.minute)}';
  }

  Widget _info(IconData icon, String label, String value) => Padding(
    padding: const EdgeInsets.only(top: 8),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 16, color: AppDesignSystem.textSecondary),
        const SizedBox(width: 8),
        Expanded(
          child: Text.rich(
            TextSpan(
              children: [
                TextSpan(
                  text: '$label៖ ',
                  style: const TextStyle(color: AppDesignSystem.textSecondary),
                ),
                TextSpan(
                  text: value,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ],
            ),
            style: const TextStyle(fontSize: 12, height: 1.45),
          ),
        ),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    final accent = homeAccentColor();
    final name =
        item.employeeName?.trim().isNotEmpty == true
            ? item.employeeName!.trim()
            : '-';
    final submitted = _submitted(submittedAt);
    final steps = [...item.workflowSteps]
      ..sort((a, b) => a.stepOrder.compareTo(b.stepOrder));

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppDesignSystem.surface,
        borderRadius: BorderRadius.circular(20),
        boxShadow: const [
          BoxShadow(
            color: Color(0x12173D2D),
            blurRadius: 16,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    colors: [dashboardHeaderStart, dashboardHeaderEnd],
                  ),
                ),
                child: Text(
                  name == '-' ? '?' : name.characters.first,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 17,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    if (item.employeeNo?.trim().isNotEmpty == true)
                      Text(
                        'អត្តលេខ ${item.employeeNo!.trim()}',
                        style: const TextStyle(
                          fontSize: 11,
                          color: AppDesignSystem.textSecondary,
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Flexible(
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: AppDesignSystem.warningBg,
                    borderRadius: BorderRadius.circular(99),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.hourglass_top_rounded,
                        size: 13,
                        color: AppDesignSystem.warningIcon,
                      ),
                      SizedBox(width: 4),
                      Flexible(
                        child: Text(
                          'រង់ចាំ',
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: AppDesignSystem.warningIcon,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: accent.withAlpha(14),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Row(
              children: [
                Icon(Icons.event_note_rounded, color: accent, size: 26),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        leaveName,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        dateRange,
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppDesignSystem.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  constraints: const BoxConstraints(minWidth: 54),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: AppDesignSystem.surface,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    children: [
                      Text(
                        _LeaveReviewPageState._km(item.requestedDays),
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: accent,
                        ),
                      ),
                      const Text(
                        'ថ្ងៃ',
                        style: TextStyle(
                          fontSize: 10,
                          color: AppDesignSystem.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          if (item.reason.trim().isNotEmpty) ...[
            const SizedBox(height: 12),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.format_quote_rounded,
                  size: 20,
                  color: accent.withAlpha(150),
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    item.reason.trim(),
                    style: const TextStyle(fontSize: 13, height: 1.5),
                  ),
                ),
              ],
            ),
          ],
          if (item.handoverEmployeeName.trim().isNotEmpty)
            _info(
              Icons.swap_horiz_rounded,
              'អ្នកជំនួស',
              item.handoverEmployeeName.trim(),
            ),
          if (item.workflowCurrentActorName?.trim().isNotEmpty == true)
            _info(
              Icons.person_pin_rounded,
              'អ្នកទទួលបន្ទុក',
              item.workflowCurrentActorName!.trim(),
            ),
          if (submitted.isNotEmpty)
            _info(Icons.schedule_rounded, 'ដាក់ស្នើ', submitted),
          if (steps.length > 1) ...[
            const SizedBox(height: 12),
            _StepTrail(steps: steps, accent: accent),
          ] else if (item.workflowCurrentStepName?.trim().isNotEmpty == true)
            _info(
              Icons.alt_route_rounded,
              'ជំហាន',
              item.workflowCurrentStepName!.trim(),
            ),
          if (onOpenAttachment != null) ...[
            const SizedBox(height: 10),
            OutlinedButton.icon(
              onPressed: locked ? null : onOpenAttachment,
              style: OutlinedButton.styleFrom(
                foregroundColor: accent,
                side: BorderSide(color: accent.withAlpha(90)),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              icon: const Icon(Icons.attach_file_rounded, size: 18),
              label: const Text('មើលឯកសារភ្ជាប់'),
            ),
          ],
          const SizedBox(height: 14),
          const Divider(height: 1, color: AppDesignSystem.border),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: locked ? null : onReject,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: _LeaveReviewPageState._rejectColor,
                    side: const BorderSide(
                      color: _LeaveReviewPageState._rejectColor,
                    ),
                    minimumSize: const Size(0, 46),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  icon: const Icon(Icons.close_rounded, size: 20),
                  label: const Text(
                    'បដិសេធ',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton.icon(
                  onPressed: locked ? null : onApprove,
                  style: FilledButton.styleFrom(
                    backgroundColor: _LeaveReviewPageState._approveColor,
                    minimumSize: const Size(0, 46),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  icon:
                      busy
                          ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                          : const Icon(Icons.check_rounded, size: 20),
                  label: const Text(
                    'អនុម័ត',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// The approval chain as numbered steps; the current one is highlighted.
class _StepTrail extends StatelessWidget {
  const _StepTrail({required this.steps, required this.accent});

  final List<LeaveWorkflowStepItem> steps;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    final currentIndex = steps.indexWhere((s) => s.isCurrent);
    return Wrap(
      spacing: 6,
      runSpacing: 6,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        for (var i = 0; i < steps.length; i++) ...[
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color:
                  i == currentIndex
                      ? accent
                      : i < currentIndex
                      ? accent.withAlpha(30)
                      : AppDesignSystem.bg,
              borderRadius: BorderRadius.circular(99),
              border: Border.all(
                color:
                    i <= currentIndex
                        ? accent.withAlpha(120)
                        : AppDesignSystem.border,
              ),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (i < currentIndex) ...[
                  Icon(Icons.check_rounded, size: 12, color: accent),
                  const SizedBox(width: 3),
                ],
                Flexible(
                  child: Text(
                    steps[i].stepName.trim().isEmpty
                        ? 'ជំហាន ${i + 1}'
                        : steps[i].stepName.trim(),
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight:
                          i == currentIndex ? FontWeight.bold : FontWeight.w500,
                      color:
                          i == currentIndex
                              ? Colors.white
                              : AppDesignSystem.textSecondary,
                    ),
                  ),
                ),
              ],
            ),
          ),
          if (i != steps.length - 1)
            const Icon(
              Icons.chevron_right_rounded,
              size: 14,
              color: AppDesignSystem.textSecondary,
            ),
        ],
      ],
    );
  }
}

/// Confirms an approve / reject with an optional note; pops with the note
/// (possibly empty) or null when cancelled.
class _DecisionSheet extends StatefulWidget {
  const _DecisionSheet({
    required this.item,
    required this.approve,
    required this.leaveName,
    required this.dateRange,
  });

  final LeaveRequestItem item;
  final bool approve;
  final String leaveName;
  final String dateRange;

  @override
  State<_DecisionSheet> createState() => _DecisionSheetState();
}

class _DecisionSheetState extends State<_DecisionSheet> {
  final _note = TextEditingController();

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final approve = widget.approve;
    final color =
        approve
            ? _LeaveReviewPageState._approveColor
            : _LeaveReviewPageState._rejectColor;
    final item = widget.item;

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: color.withAlpha(26),
                ),
                child: Icon(
                  approve ? Icons.check_rounded : Icons.close_rounded,
                  color: color,
                  size: 34,
                ),
              ),
              const SizedBox(height: 12),
              Text(
                approve ? 'អនុម័តសំណើច្បាប់?' : 'បដិសេធសំណើច្បាប់?',
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppDesignSystem.bg,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.employeeName?.trim().isNotEmpty == true
                          ? item.employeeName!.trim()
                          : '-',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${widget.leaveName} · ${widget.dateRange} · '
                      '${_LeaveReviewPageState._km(item.requestedDays)} ថ្ងៃ',
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppDesignSystem.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              TextField(
                key: const ValueKey('review-note'),
                controller: _note,
                minLines: 2,
                maxLines: 4,
                maxLength: 1000,
                decoration: InputDecoration(
                  labelText:
                      approve
                          ? 'កំណត់សម្គាល់ (ស្រេចចិត្ត)'
                          : 'មូលហេតុនៃការបដិសេធ (ស្រេចចិត្ត)',
                  counterText: '',
                  filled: true,
                  fillColor: AppDesignSystem.bg,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: TextButton(
                      onPressed: () => Navigator.of(context).pop(),
                      style: TextButton.styleFrom(
                        minimumSize: const Size(0, 48),
                      ),
                      child: const Text('បោះបង់'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    flex: 2,
                    child: FilledButton.icon(
                      onPressed: () => Navigator.of(context).pop(_note.text),
                      style: FilledButton.styleFrom(
                        backgroundColor: color,
                        minimumSize: const Size(0, 48),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      icon: Icon(
                        approve ? Icons.check_rounded : Icons.close_rounded,
                      ),
                      label: Text(
                        approve ? 'បញ្ជាក់ការអនុម័ត' : 'បញ្ជាក់ការបដិសេធ',
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
