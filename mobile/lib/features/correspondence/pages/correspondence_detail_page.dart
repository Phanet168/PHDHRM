import 'dart:async';

import 'package:flutter/material.dart';

// ignore_for_file: deprecated_member_use

import '../../../core/localization/laravel_language_service.dart';
import '../../../core/network/api_exception.dart';
import '../../auth/controllers/auth_controller.dart';
import '../models/correspondence_models.dart';
import '../services/correspondence_service.dart';

/// Full detail & workflow-action page for a correspondence letter.
/// Logic mirrors the Laravel CorrespondenceController workflow.
class CorrespondenceDetailPage extends StatefulWidget {
  const CorrespondenceDetailPage({
    super.key,
    required this.letterId,
    required this.service,
    required this.authController,
  });

  final int letterId;
  final CorrespondenceService service;
  final AuthController authController;

  @override
  State<CorrespondenceDetailPage> createState() =>
      _CorrespondenceDetailPageState();
}

class _CorrespondenceDetailPageState extends State<CorrespondenceDetailPage> {
  late Future<CorrespondenceLetter> _letterFuture;
  late final Future<Map<String, String>> _languageFuture;
  bool _isActing = false;

  // Step labels matching Laravel CorrespondenceLetter::stepLabels()
  static const Map<String, String> _stepLabels = {
    'incoming_received': 'ទទួលបានលិខិត',
    'incoming_delegated': 'បានប្រគល់',
    'incoming_office_comment': 'ចំណាំការិយាល័យ',
    'incoming_deputy_review': 'ពិនិត្យអនុប្រធាន',
    'incoming_director_decision': 'សម្រេចប្រធាន',
    'incoming_distributed': 'ចែកចាយហើយ',
    'outgoing_draft': 'សេចក្ដីព្រាង',
    'outgoing_distributed': 'ចែកចាយហើយ',
    'closed': 'បិទ',
  };

  // Ordered steps for incoming workflow progress display
  static const List<String> _incomingStepOrder = [
    'incoming_received',
    'incoming_delegated',
    'incoming_office_comment',
    'incoming_deputy_review',
    'incoming_director_decision',
    'incoming_distributed',
    'closed',
  ];

  static const List<String> _outgoingStepOrder = [
    'outgoing_draft',
    'outgoing_distributed',
    'closed',
  ];

  @override
  void initState() {
    super.initState();
    _languageFuture = LaravelLanguageService.instance.load();
    _letterFuture = widget.service.fetchLetterDetail(widget.letterId);
  }

  void _reload() {
    setState(() {
      _letterFuture = widget.service.fetchLetterDetail(widget.letterId);
    });
  }

  int get _currentUserId => widget.authController.currentUser?.userId ?? 0;

  bool _isCurrentHandler(CorrespondenceLetter letter) =>
      _currentUserId > 0 && letter.currentHandlerUserId == _currentUserId;

  bool _isCreatedBy(CorrespondenceLetter letter) =>
      _currentUserId > 0 && letter.createdByUserId == _currentUserId;

  /// Check if current user is a related office_comment participant
  /// (stored in meta_json of the delegate action).
  bool _isRelatedOfficeCommentUser(CorrespondenceLetter letter) {
    final actions = letter.actions;
    if (actions == null) return false;
    CorrespondenceLetterAction? delegateAction;
    for (final a in actions) {
      if (a.actionType == 'delegate') {
        delegateAction = a;
        break;
      }
    }
    if (delegateAction == null) return false;
    final meta = delegateAction.metaJson;
    final relatedIds = meta?['office_comment_related_user_ids'];
    if (relatedIds is List) {
      final uid = _currentUserId;
      return relatedIds.any(
        (id) =>
            (id is num ? id.toInt() : int.tryParse(id.toString()) ?? 0) == uid,
      );
    }
    return false;
  }

  /// Returns workflow actions the current user may perform,
  /// aligned with Laravel's permission rules.
  List<_CorrAction> _availableActions(CorrespondenceLetter letter) {
    final result = <_CorrAction>[];
    final step = letter.currentStep;
    final isHandler = _isCurrentHandler(letter);
    final isCreator = _isCreatedBy(letter);

    if (letter.isIncoming) {
      // Delegate (initial or re-delegate)
      if ((step == 'incoming_received' || step == 'incoming_delegated') &&
          isHandler) {
        result.add(_CorrAction.delegate);
      }

      // Office comment – main actor
      if (step == 'incoming_office_comment' && isHandler) {
        result.add(_CorrAction.officeComment);
      }

      // Office comment – related actor (can comment even at deputy_review step)
      if (!isHandler &&
          (step == 'incoming_office_comment' ||
              step == 'incoming_deputy_review') &&
          _isRelatedOfficeCommentUser(letter)) {
        result.add(_CorrAction.officeComment);
      }

      // Deputy review
      if (step == 'incoming_deputy_review' && isHandler) {
        result.add(_CorrAction.deputyReview);
      }

      // Director decision
      if (step == 'incoming_director_decision' && isHandler) {
        result.add(_CorrAction.directorDecision);
      }

      // Distribute – only after director approved
      if (step == 'incoming_director_decision' &&
          letter.decision == 'approved' &&
          isHandler) {
        result.add(_CorrAction.distribute);
      }
    } else {
      // Outgoing
      if ((step == 'outgoing_draft' || step == 'outgoing_distributed') &&
          (isHandler || isCreator)) {
        result.add(_CorrAction.distribute);
      }

      // Close – outgoing only, not when still draft or already closed
      if (step != 'closed' &&
          step != 'outgoing_draft' &&
          (isHandler || isCreator)) {
        result.add(_CorrAction.close);
      }
    }
    return result;
  }

  bool _canSendParentFeedback(CorrespondenceLetter letter) =>
      letter.parentLetterId != null &&
      letter.currentStep != 'closed' &&
      (_isCurrentHandler(letter) || _isCreatedBy(letter));

  bool _canAcknowledge(CorrespondenceLetterDistribution dist) =>
      dist.isPendingAck && dist.targetUserId == _currentUserId;

  bool _canFeedback(CorrespondenceLetterDistribution dist) =>
      (dist.isAcknowledged || dist.isFeedbackSent) &&
      dist.targetUserId == _currentUserId;

  // ─── Action Handlers ──────────────────────────────────────────────────────

  Future<void> _actDelegate(CorrespondenceLetter letter) async {
    if (_isActing) return;
    List<CorrespondenceLookupOption> orgUnits = const [];
    try {
      orgUnits = await widget.service.fetchOrgUnits();
    } catch (_) {}
    if (!mounted) return;

    final result = await showModalBottomSheet<_DelegateFormResult>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder:
          (ctx) => _DelegateSheet(service: widget.service, orgUnits: orgUnits),
    );
    if (result == null || !mounted) return;

    setState(() => _isActing = true);
    try {
      await widget.service.progressWorkflow(
        letterId: letter.id,
        action: 'delegate',
        assignedDepartmentId: result.assignedDeptId,
        officeUserId: result.officeUserId,
        officeRelatedUserIds:
            result.relatedUserIds.isNotEmpty ? result.relatedUserIds : null,
        deputyUserId: result.deputyUserId,
        directorUserId: result.directorUserId,
        note: result.note.isNotEmpty ? result.note : null,
      );
      _reload();
    } catch (e) {
      if (mounted) _showError(extractApiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _isActing = false);
    }
  }

  Future<void> _actOfficeComment(CorrespondenceLetter letter) async {
    if (_isActing) return;
    final note = await _showNoteDialog('ចំណាំការិយាល័យ', required: true);
    if (note == null || !mounted) return;
    setState(() => _isActing = true);
    try {
      await widget.service.progressWorkflow(
        letterId: letter.id,
        action: 'office_comment',
        note: note,
      );
      _reload();
    } catch (e) {
      if (mounted) _showError(extractApiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _isActing = false);
    }
  }

  Future<void> _actDeputyReview(CorrespondenceLetter letter) async {
    if (_isActing) return;
    final note = await _showNoteDialog('ចំណាំអនុប្រធាន', required: true);
    if (note == null || !mounted) return;
    setState(() => _isActing = true);
    try {
      await widget.service.progressWorkflow(
        letterId: letter.id,
        action: 'deputy_review',
        note: note,
      );
      _reload();
    } catch (e) {
      if (mounted) _showError(extractApiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _isActing = false);
    }
  }

  Future<void> _actDirectorDecision(CorrespondenceLetter letter) async {
    if (_isActing) return;
    final result = await showDialog<_DecisionFormResult>(
      context: context,
      builder: (ctx) => const _DirectorDecisionDialog(),
    );
    if (result == null || !mounted) return;
    setState(() => _isActing = true);
    try {
      await widget.service.progressWorkflow(
        letterId: letter.id,
        action: 'director_decision',
        decision: result.decision,
        note: result.note.isNotEmpty ? result.note : null,
      );
      _reload();
    } catch (e) {
      if (mounted) _showError(extractApiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _isActing = false);
    }
  }

  Future<void> _actDistribute(CorrespondenceLetter letter) async {
    if (_isActing) return;
    List<CorrespondenceLookupOption> orgUnits = const [];
    try {
      orgUnits = await widget.service.fetchOrgUnits();
    } catch (_) {}
    if (!mounted) return;

    final result = await showModalBottomSheet<_DistributeFormResult>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder:
          (ctx) => _DistributeSheet(
            service: widget.service,
            orgUnits: orgUnits,
            isIncoming: letter.isIncoming,
          ),
    );
    if (result == null || !mounted) return;

    setState(() => _isActing = true);
    try {
      await widget.service.distributeLetters(
        letterId: letter.id,
        // Incoming uses target_department_ids
        targetDepartmentIds:
            letter.isIncoming && result.toDeptIds.isNotEmpty
                ? result.toDeptIds
                : null,
        // Outgoing uses to_department_ids / cc_department_ids
        toDepartmentIds:
            !letter.isIncoming && result.toDeptIds.isNotEmpty
                ? result.toDeptIds
                : null,
        ccDepartmentIds: result.ccDeptIds.isNotEmpty ? result.ccDeptIds : null,
        toUserIds: result.toUserIds.isNotEmpty ? result.toUserIds : null,
        ccUserIds: result.ccUserIds.isNotEmpty ? result.ccUserIds : null,
        note: result.note.isNotEmpty ? result.note : null,
      );
      _reload();
    } catch (e) {
      if (mounted) _showError(extractApiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _isActing = false);
    }
  }

  Future<void> _actClose(CorrespondenceLetter letter) async {
    if (_isActing) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder:
          (ctx) => AlertDialog(
            title: const Text('បិទលិខិត'),
            content: const Text('តើអ្នកប្រាកដថាចង់បិទលិខិតនេះ?'),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('មិនយល់ព្រម'),
              ),
              ElevatedButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: const Text('យល់ព្រម'),
              ),
            ],
          ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _isActing = true);
    try {
      await widget.service.closeLetter(letter.id);
      _reload();
    } catch (e) {
      if (mounted) _showError(extractApiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _isActing = false);
    }
  }

  Future<void> _actAcknowledge(CorrespondenceLetterDistribution dist) async {
    if (_isActing) return;
    setState(() => _isActing = true);
    try {
      await widget.service.acknowledgeDistribution(dist.id);
      _reload();
    } catch (e) {
      if (mounted) _showError(extractApiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _isActing = false);
    }
  }

  Future<void> _actFeedback(CorrespondenceLetterDistribution dist) async {
    if (_isActing) return;
    final note = await _showNoteDialog('ផ្ញើមតិ', required: true);
    if (note == null || !mounted) return;
    setState(() => _isActing = true);
    try {
      await widget.service.sendFeedback(
        distributionId: dist.id,
        feedbackNote: note,
      );
      _reload();
    } catch (e) {
      if (mounted) _showError(extractApiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _isActing = false);
    }
  }

  Future<void> _actFeedbackParent(CorrespondenceLetter letter) async {
    if (_isActing) return;
    final note = await _showNoteDialog('ផ្ញើមតិទៅអង្គភាពម្ដាយ', required: true);
    if (note == null || !mounted) return;
    setState(() => _isActing = true);
    try {
      await widget.service.sendFeedbackToParent(
        letterId: letter.id,
        feedbackNote: note,
      );
      _reload();
    } catch (e) {
      if (mounted) _showError(extractApiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _isActing = false);
    }
  }

  Future<String?> _showNoteDialog(String title, {bool required = false}) {
    return showDialog<String>(
      context: context,
      builder: (ctx) => _NoteDialog(title: title, required: required),
    );
  }

  void _showError(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: const Color(0xFFDC2626),
      ),
    );
  }

  // ─── Build ────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, String>>(
      future: _languageFuture,
      builder: (context, langSnap) {
        final lang = langSnap.data ?? const <String, String>{};
        return Scaffold(
          appBar: AppBar(
            title: Text(lang['letter_detail'] ?? 'លម្អិតលិខិត'),
            actions: [
              IconButton(
                icon: const Icon(Icons.refresh_rounded),
                onPressed: _reload,
                tooltip: 'ធ្វើបច្ចុប្បន្នភាព',
              ),
            ],
          ),
          body: FutureBuilder<CorrespondenceLetter>(
            future: _letterFuture,
            builder: (context, snap) {
              if (snap.connectionState == ConnectionState.waiting) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snap.hasError) {
                return Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(
                          Icons.error_outline,
                          size: 48,
                          color: Colors.red,
                        ),
                        const SizedBox(height: 12),
                        Text(
                          '${snap.error}',
                          textAlign: TextAlign.center,
                          style: const TextStyle(color: Color(0xFF475569)),
                        ),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: _reload,
                          child: const Text('ព្យាយាមម្ដងទៀត'),
                        ),
                      ],
                    ),
                  ),
                );
              }
              return _buildBody(context, snap.data!, lang);
            },
          ),
        );
      },
    );
  }

  Widget _buildBody(
    BuildContext context,
    CorrespondenceLetter letter,
    Map<String, String> lang,
  ) {
    return Stack(
      children: [
        RefreshIndicator(
          onRefresh: () async => _reload(),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _buildHeaderCard(letter, lang),
              const SizedBox(height: 12),
              _buildInfoCard(letter, lang),
              const SizedBox(height: 12),
              _buildWorkflowCard(letter, lang),
              const SizedBox(height: 12),
              _buildActionsCard(letter, lang),
              if (_canSendParentFeedback(letter)) ...[
                const SizedBox(height: 12),
                _buildParentFeedbackCard(letter, lang),
              ],
              if (letter.distributions != null &&
                  letter.distributions!.isNotEmpty) ...[
                const SizedBox(height: 12),
                _buildDistributionsCard(letter, lang),
              ],
              if (letter.actions != null && letter.actions!.isNotEmpty) ...[
                const SizedBox(height: 12),
                _buildActionHistoryCard(letter, lang),
              ],
              const SizedBox(height: 32),
            ],
          ),
        ),
        if (_isActing)
          const Positioned.fill(
            child: ColoredBox(
              color: Color(0x44000000),
              child: Center(child: CircularProgressIndicator()),
            ),
          ),
      ],
    );
  }

  // ─── Header card (type, priority, status, step) ───────────────────────────

  Widget _buildHeaderCard(
    CorrespondenceLetter letter,
    Map<String, String> lang,
  ) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _cardDecoration(
        border:
            letter.isUrgent
                ? const Color(0xFFEF4444).withAlpha(80)
                : const Color(0xFFE2E8F0),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _TypeBadge(isIncoming: letter.isIncoming),
              const SizedBox(width: 8),
              if (letter.priority == 'urgent')
                _PriorityBadge(priority: 'urgent'),
              if (letter.priority == 'confidential')
                _PriorityBadge(priority: 'confidential'),
              const Spacer(),
              _StatusBadge(status: letter.status),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            letter.subject,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: Color(0xFF10211B),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _stepLabels[letter.currentStep] ?? letter.currentStep,
            style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
          ),
          if (letter.registryNo != null || letter.letterNo != null) ...[
            const SizedBox(height: 6),
            Wrap(
              spacing: 12,
              children: [
                if (letter.registryNo != null)
                  Text(
                    'ចុះបញ្ជី: ${letter.registryNo}',
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xFF94A3B8),
                    ),
                  ),
                if (letter.letterNo != null)
                  Text(
                    'លេខ: ${letter.letterNo}',
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xFF94A3B8),
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  // ─── Info card (orgs, dates, handlers) ────────────────────────────────────

  Widget _buildInfoCard(CorrespondenceLetter letter, Map<String, String> lang) {
    return _SectionCard(
      title: 'ព័ត៌មានលម្អិត',
      child: Column(
        children: [
          if (letter.fromOrg != null)
            _InfoRow(
              icon: Icons.business_outlined,
              label: 'អង្គភាពចេញ',
              value: letter.fromOrg!,
            ),
          if (letter.toOrg != null)
            _InfoRow(
              icon: Icons.business_center_outlined,
              label: 'អង្គភាពទទួល',
              value: letter.toOrg!,
            ),
          if (letter.originDepartmentName != null)
            _InfoRow(
              icon: Icons.account_tree_outlined,
              label: 'អង្គភាពដើម',
              value: letter.originDepartmentName!,
            ),
          if (letter.assignedDepartmentName != null)
            _InfoRow(
              icon: Icons.assignment_outlined,
              label: 'អង្គភាពដំណើរការ',
              value: letter.assignedDepartmentName!,
            ),
          if (letter.currentHandlerName != null)
            _InfoRow(
              icon: Icons.person_outline,
              label: 'អ្នកទទួលខុសត្រូវ',
              value: letter.currentHandlerName!,
            ),
          if (letter.createdByName != null)
            _InfoRow(
              icon: Icons.edit_outlined,
              label: 'បង្កើតដោយ',
              value: letter.createdByName!,
            ),
          if (letter.letterDate != null)
            _InfoRow(
              icon: Icons.calendar_today_outlined,
              label: 'ថ្ងៃលិខិត',
              value: _formatDate(letter.letterDate),
            ),
          if (letter.receivedDate != null)
            _InfoRow(
              icon: Icons.inbox_outlined,
              label: 'ថ្ងៃទទួល',
              value: _formatDate(letter.receivedDate),
            ),
          if (letter.sentDate != null)
            _InfoRow(
              icon: Icons.send_outlined,
              label: 'ថ្ងៃផ្ញើ',
              value: _formatDate(letter.sentDate),
            ),
          if (letter.dueDate != null)
            _InfoRow(
              icon: Icons.timer_outlined,
              label: 'ថ្ងៃកំណត់',
              value: _formatDate(letter.dueDate),
            ),
          if (letter.decision != null)
            _InfoRow(
              icon:
                  letter.decision == 'approved'
                      ? Icons.check_circle_outline
                      : Icons.cancel_outlined,
              label: 'ការសម្រេច',
              value: letter.decision == 'approved' ? 'អនុម័ត' : 'មិនអនុម័ត',
              valueColor:
                  letter.decision == 'approved'
                      ? const Color(0xFF0B6B58)
                      : const Color(0xFFDC2626),
            ),
          if (letter.attachments != null && letter.attachments!.isNotEmpty)
            _InfoRow(
              icon: Icons.attach_file_outlined,
              label: 'ឯកសារ',
              value: '${letter.attachments!.length} ឯកសារ',
            ),
        ],
      ),
    );
  }

  // ─── Workflow step progress ────────────────────────────────────────────────

  Widget _buildWorkflowCard(
    CorrespondenceLetter letter,
    Map<String, String> lang,
  ) {
    final steps = letter.isIncoming ? _incomingStepOrder : _outgoingStepOrder;
    final currentIndex = steps.indexOf(letter.currentStep);

    return _SectionCard(
      title: 'ដំណើរការ Workflow',
      child: Column(
        children: List.generate(steps.length, (i) {
          final step = steps[i];
          final isDone = i < currentIndex;
          final isCurrent = i == currentIndex;
          return _StepTile(
            label: _stepLabels[step] ?? step,
            isDone: isDone,
            isCurrent: isCurrent,
            isLast: i == steps.length - 1,
          );
        }),
      ),
    );
  }

  // ─── Available workflow actions ────────────────────────────────────────────

  Widget _buildActionsCard(
    CorrespondenceLetter letter,
    Map<String, String> lang,
  ) {
    final actions = _availableActions(letter);
    if (actions.isEmpty) return const SizedBox.shrink();

    return _SectionCard(
      title: 'សកម្មភាព',
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children:
            actions.map((action) {
              switch (action) {
                case _CorrAction.delegate:
                  return _ActionButton(
                    label: 'ប្រគល់',
                    icon: Icons.send_and_archive_outlined,
                    color: const Color(0xFF1D4F91),
                    onPressed: () => _actDelegate(letter),
                  );
                case _CorrAction.officeComment:
                  return _ActionButton(
                    label: 'ចំណាំការិយាល័យ',
                    icon: Icons.comment_outlined,
                    color: const Color(0xFF5D79C8),
                    onPressed: () => _actOfficeComment(letter),
                  );
                case _CorrAction.deputyReview:
                  return _ActionButton(
                    label: 'ពិនិត្យអនុប្រធាន',
                    icon: Icons.rate_review_outlined,
                    color: const Color(0xFF6B58D7),
                    onPressed: () => _actDeputyReview(letter),
                  );
                case _CorrAction.directorDecision:
                  return _ActionButton(
                    label: 'សម្រេច',
                    icon: Icons.gavel_outlined,
                    color: const Color(0xFF0B6B58),
                    onPressed: () => _actDirectorDecision(letter),
                  );
                case _CorrAction.distribute:
                  return _ActionButton(
                    label: 'ចែកចាយ',
                    icon: Icons.share_outlined,
                    color: const Color(0xFFD79C2E),
                    onPressed: () => _actDistribute(letter),
                  );
                case _CorrAction.close:
                  return _ActionButton(
                    label: 'បិទ',
                    icon: Icons.close_rounded,
                    color: const Color(0xFF94A3B8),
                    onPressed: () => _actClose(letter),
                  );
              }
            }).toList(),
      ),
    );
  }

  // ─── Parent feedback card (child letters only) ────────────────────────────

  Widget _buildParentFeedbackCard(
    CorrespondenceLetter letter,
    Map<String, String> lang,
  ) {
    return _SectionCard(
      title: 'មតិយោបល់ទៅអង្គភាពម្ដាយ',
      child: SizedBox(
        width: double.infinity,
        child: _ActionButton(
          label: 'ផ្ញើមតិទៅអង្គភាពម្ដាយ',
          icon: Icons.reply_outlined,
          color: const Color(0xFF0B6B58),
          onPressed: () => _actFeedbackParent(letter),
          fullWidth: true,
        ),
      ),
    );
  }

  // ─── Distributions list ───────────────────────────────────────────────────

  Widget _buildDistributionsCard(
    CorrespondenceLetter letter,
    Map<String, String> lang,
  ) {
    final dists = letter.distributions!;
    return _SectionCard(
      title: 'ការចែកចាយ (${dists.length})',
      child: Column(
        children: dists.map((dist) => _buildDistributionRow(dist)).toList(),
      ),
    );
  }

  Widget _buildDistributionRow(CorrespondenceLetterDistribution dist) {
    final canAck = _canAcknowledge(dist);
    final canFb = _canFeedback(dist);
    final statusColor = _distStatusColor(dist.status);

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color:
                      dist.isTo
                          ? const Color(0xFF1D4F91).withAlpha(26)
                          : const Color(0xFF94A3B8).withAlpha(26),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  dist.isTo ? 'TO' : 'CC',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    color:
                        dist.isTo
                            ? const Color(0xFF1D4F91)
                            : const Color(0xFF64748B),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  dist.getTarget(),
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: statusColor.withAlpha(26),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  _distStatusLabel(dist.status),
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    color: statusColor,
                  ),
                ),
              ),
            ],
          ),
          if (dist.feedbackNote != null) ...[
            const SizedBox(height: 6),
            Text(
              dist.feedbackNote!,
              style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
            ),
          ],
          if (canAck || canFb) ...[
            const SizedBox(height: 8),
            Row(
              children: [
                if (canAck)
                  _SmallActionButton(
                    label: 'ទទួលស្គាល់',
                    icon: Icons.check_rounded,
                    color: const Color(0xFF0B6B58),
                    onPressed: () => _actAcknowledge(dist),
                  ),
                if (canFb) ...[
                  if (canAck) const SizedBox(width: 8),
                  _SmallActionButton(
                    label: 'ផ្ញើមតិ',
                    icon: Icons.reply_rounded,
                    color: const Color(0xFF1D4F91),
                    onPressed: () => _actFeedback(dist),
                  ),
                ],
              ],
            ),
          ],
        ],
      ),
    );
  }

  // ─── Action history ────────────────────────────────────────────────────────

  Widget _buildActionHistoryCard(
    CorrespondenceLetter letter,
    Map<String, String> lang,
  ) {
    final actions = letter.actions!;
    return _SectionCard(
      title: 'ប្រវត្តិសកម្មភាព',
      child: Column(
        children:
            actions.reversed.map((action) {
              return Container(
                margin: const EdgeInsets.only(bottom: 10),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: const Color(0xFF0B6B58).withAlpha(26),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.history_rounded,
                        size: 16,
                        color: Color(0xFF0B6B58),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _actionTypeLabel(action.actionType),
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF10211B),
                            ),
                          ),
                          if (action.actedByName != null)
                            Text(
                              action.actedByName!,
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xFF64748B),
                              ),
                            ),
                          if (action.note != null && action.note!.isNotEmpty)
                            Container(
                              margin: const EdgeInsets.only(top: 4),
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF1F5F9),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                action.note!,
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: Color(0xFF475569),
                                ),
                              ),
                            ),
                          if (action.createdAt != null)
                            Text(
                              _formatDateTime(action.createdAt),
                              style: const TextStyle(
                                fontSize: 11,
                                color: Color(0xFF94A3B8),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
              );
            }).toList(),
      ),
    );
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────

  String _formatDate(DateTime? dt) {
    if (dt == null) return '-';
    return '${dt.day.toString().padLeft(2, '0')}/'
        '${dt.month.toString().padLeft(2, '0')}/'
        '${dt.year}';
  }

  String _formatDateTime(DateTime? dt) {
    if (dt == null) return '';
    return '${_formatDate(dt)} '
        '${dt.hour.toString().padLeft(2, '0')}:'
        '${dt.minute.toString().padLeft(2, '0')}';
  }

  String _distStatusLabel(String status) {
    switch (status) {
      case 'pending_ack':
        return 'រង់ចាំ';
      case 'acknowledged':
        return 'ទទួលស្គាល់';
      case 'feedback_sent':
        return 'ផ្ញើមតិ';
      case 'closed':
        return 'បិទ';
      default:
        return status;
    }
  }

  Color _distStatusColor(String status) {
    switch (status) {
      case 'pending_ack':
        return const Color(0xFFD79C2E);
      case 'acknowledged':
        return const Color(0xFF1D4F91);
      case 'feedback_sent':
        return const Color(0xFF0B6B58);
      case 'closed':
        return const Color(0xFF94A3B8);
      default:
        return const Color(0xFF94A3B8);
    }
  }

  String _actionTypeLabel(String type) {
    switch (type) {
      case 'created':
        return 'បង្កើតលិខិត';
      case 'delegate':
        return 'ប្រគល់';
      case 'office_comment':
        return 'ចំណាំការិយាល័យ';
      case 'office_comment_related':
        return 'ចំណាំការិយាល័យ (ពាក់ព័ន្ធ)';
      case 'deputy_review':
        return 'ពិនិត្យអនុប្រធាន';
      case 'director_approved':
        return 'អនុម័ត';
      case 'director_rejected':
        return 'មិនអនុម័ត';
      case 'distribute':
        return 'ចែកចាយ';
      case 'acknowledge':
        return 'ទទួលស្គាល់';
      case 'feedback':
        return 'ផ្ញើមតិ';
      case 'feedback_to_parent':
        return 'ផ្ញើមតិទៅម្ដាយ';
      case 'closed':
        return 'បិទ';
      default:
        return type;
    }
  }

  BoxDecoration _cardDecoration({Color? border}) => BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(12),
    border: Border.all(color: border ?? const Color(0xFFE2E8F0)),
    boxShadow: const [
      BoxShadow(color: Color(0x0A0F172A), blurRadius: 8, offset: Offset(0, 2)),
    ],
  );
}

// ─── Enums ──────────────────────────────────────────────────────────────────

enum _CorrAction {
  delegate,
  officeComment,
  deputyReview,
  directorDecision,
  distribute,
  close,
}

// ─── Form result models ──────────────────────────────────────────────────────

class _DelegateFormResult {
  _DelegateFormResult({
    required this.assignedDeptId,
    required this.officeUserId,
    required this.deputyUserId,
    required this.directorUserId,
    this.note = '',
  }) : relatedUserIds = const [];

  final int assignedDeptId;
  final int officeUserId;
  final int deputyUserId;
  final int directorUserId;
  final List<int> relatedUserIds;
  final String note;
}

class _DistributeFormResult {
  _DistributeFormResult({
    this.toDeptIds = const [],
    this.ccDeptIds = const [],
    this.toUserIds = const [],
    this.ccUserIds = const [],
    this.note = '',
  });

  final List<int> toDeptIds;
  final List<int> ccDeptIds;
  final List<int> toUserIds;
  final List<int> ccUserIds;
  final String note;
}

class _DecisionFormResult {
  _DecisionFormResult({required this.decision, this.note = ''});

  final String decision; // 'approved' | 'rejected'
  final String note;
}

// ─── Reusable UI components ─────────────────────────────────────────────────

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A0F172A),
            blurRadius: 8,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: Color(0xFF10211B),
            ),
          ),
          const SizedBox(height: 10),
          child,
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
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
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 16, color: const Color(0xFF94A3B8)),
          const SizedBox(width: 8),
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: valueColor ?? const Color(0xFF1E293B),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StepTile extends StatelessWidget {
  const _StepTile({
    required this.label,
    required this.isDone,
    required this.isCurrent,
    required this.isLast,
  });

  final String label;
  final bool isDone;
  final bool isCurrent;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final color =
        isDone
            ? const Color(0xFF0B6B58)
            : isCurrent
            ? const Color(0xFF1D4F91)
            : const Color(0xFFCBD5E1);

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 24,
            child: Column(
              children: [
                Container(
                  width: 16,
                  height: 16,
                  decoration: BoxDecoration(
                    color: color,
                    shape: BoxShape.circle,
                  ),
                  child:
                      isDone
                          ? const Icon(
                            Icons.check_rounded,
                            size: 10,
                            color: Colors.white,
                          )
                          : null,
                ),
                if (!isLast)
                  Expanded(
                    child: Container(
                      width: 2,
                      color:
                          isDone
                              ? const Color(0xFF0B6B58)
                              : const Color(0xFFE2E8F0),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: isCurrent ? FontWeight.w700 : FontWeight.w500,
                  color: color,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _TypeBadge extends StatelessWidget {
  const _TypeBadge({required this.isIncoming});

  final bool isIncoming;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color:
            isIncoming
                ? const Color(0xFF1D4F91).withAlpha(26)
                : const Color(0xFF0B6B58).withAlpha(26),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        isIncoming ? 'លិខិតចូល' : 'លិខិតចេញ',
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: isIncoming ? const Color(0xFF1D4F91) : const Color(0xFF0B6B58),
        ),
      ),
    );
  }
}

class _PriorityBadge extends StatelessWidget {
  const _PriorityBadge({required this.priority});

  final String priority;

  @override
  Widget build(BuildContext context) {
    final isUrgent = priority == 'urgent';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color:
            isUrgent
                ? const Color(0xFFEF4444).withAlpha(26)
                : const Color(0xFF6B21A8).withAlpha(26),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        isUrgent ? 'បន្ទាន់' : 'សម្ងាត់',
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: isUrgent ? const Color(0xFFEF4444) : const Color(0xFF6B21A8),
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    Color color;
    String label;
    switch (status) {
      case 'pending':
        color = const Color(0xFFD79C2E);
        label = 'ចាប់ផ្ដើម';
      case 'in_progress':
        color = const Color(0xFF1D4F91);
        label = 'ដំណើរការ';
      case 'completed':
        color = const Color(0xFF0B6B58);
        label = 'បានបញ្ចប់';
      case 'archived':
        color = const Color(0xFF94A3B8);
        label = 'ចងក្រង';
      default:
        color = const Color(0xFF94A3B8);
        label = status;
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withAlpha(26),
        borderRadius: BorderRadius.circular(6),
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
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.label,
    required this.icon,
    required this.color,
    required this.onPressed,
    this.fullWidth = false,
  });

  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback onPressed;
  final bool fullWidth;

  @override
  Widget build(BuildContext context) {
    final button = ElevatedButton.icon(
      onPressed: onPressed,
      icon: Icon(icon, size: 16),
      label: Text(label),
      style: ElevatedButton.styleFrom(
        backgroundColor: color,
        foregroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
      ),
    );
    return fullWidth ? SizedBox(width: double.infinity, child: button) : button;
  }
}

class _SmallActionButton extends StatelessWidget {
  const _SmallActionButton({
    required this.label,
    required this.icon,
    required this.color,
    required this.onPressed,
  });

  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: onPressed,
      icon: Icon(icon, size: 14),
      label: Text(label),
      style: OutlinedButton.styleFrom(
        foregroundColor: color,
        side: BorderSide(color: color),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        textStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
      ),
    );
  }
}

// ─── Delegate bottom sheet ────────────────────────────────────────────────────

class _DelegateSheet extends StatefulWidget {
  const _DelegateSheet({required this.service, required this.orgUnits});

  final CorrespondenceService service;
  final List<CorrespondenceLookupOption> orgUnits;

  @override
  State<_DelegateSheet> createState() => _DelegateSheetState();
}

class _DelegateSheetState extends State<_DelegateSheet> {
  CorrespondenceLookupOption? _dept;
  CorrespondenceLookupOption? _officeUser;
  CorrespondenceLookupOption? _deputyUser;
  CorrespondenceLookupOption? _directorUser;
  final _noteController = TextEditingController();

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  Future<void> _pickDept() async {
    final result = await showModalBottomSheet<CorrespondenceLookupOption>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder:
          (ctx) => _OrgUnitSelectSheet(
            options: widget.orgUnits,
            title: 'ជ្រើសរើសអង្គភាព',
          ),
    );
    if (result != null && mounted) setState(() => _dept = result);
  }

  Future<void> _pickUser(
    String title,
    ValueSetter<CorrespondenceLookupOption> onPicked,
  ) async {
    final result = await showModalBottomSheet<CorrespondenceLookupOption>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder:
          (ctx) => _UserSearchSingleSelectSheet(
            title: title,
            service: widget.service,
          ),
    );
    if (result != null && mounted) setState(() => onPicked(result));
  }

  bool get _isValid =>
      _dept != null &&
      _officeUser != null &&
      _deputyUser != null &&
      _directorUser != null;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'ប្រគល់លិខិត',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 16),
              // Department
              _SheetFieldTile(
                label: 'អង្គភាពដំណើរការ *',
                value: _dept?.text ?? 'ជ្រើសរើស...',
                icon: Icons.account_tree_outlined,
                onTap: _pickDept,
              ),
              const SizedBox(height: 10),
              // Office comment user (step 3)
              _SheetFieldTile(
                label: 'ជំហានទី 3 – ចំណាំការិយាល័យ *',
                value: _officeUser?.text ?? 'ជ្រើសរើស...',
                icon: Icons.person_outline,
                onTap:
                    () => _pickUser(
                      'ជំហានទី 3 – ចំណាំការិយាល័យ',
                      (u) => _officeUser = u,
                    ),
              ),
              const SizedBox(height: 10),
              // Deputy review user (step 4)
              _SheetFieldTile(
                label: 'ជំហានទី 4 – ពិនិត្យអនុប្រធាន *',
                value: _deputyUser?.text ?? 'ជ្រើសរើស...',
                icon: Icons.person_outline,
                onTap:
                    () => _pickUser(
                      'ជំហានទី 4 – ពិនិត្យអនុប្រធាន',
                      (u) => _deputyUser = u,
                    ),
              ),
              const SizedBox(height: 10),
              // Director user (step 5)
              _SheetFieldTile(
                label: 'ជំហានទី 5 – សម្រេចប្រធាន *',
                value: _directorUser?.text ?? 'ជ្រើសរើស...',
                icon: Icons.person_outline,
                onTap:
                    () => _pickUser(
                      'ជំហានទី 5 – សម្រេចប្រធាន',
                      (u) => _directorUser = u,
                    ),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _noteController,
                decoration: const InputDecoration(
                  labelText: 'ចំណាំ (ស្រេចចិត្ត)',
                  border: OutlineInputBorder(),
                ),
                maxLines: 2,
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed:
                      _isValid
                          ? () => Navigator.pop(
                            context,
                            _DelegateFormResult(
                              assignedDeptId: _dept!.id,
                              officeUserId: _officeUser!.id,
                              deputyUserId: _deputyUser!.id,
                              directorUserId: _directorUser!.id,
                              note: _noteController.text.trim(),
                            ),
                          )
                          : null,
                  child: const Text('ប្រគល់'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Distribute bottom sheet ──────────────────────────────────────────────────

class _DistributeSheet extends StatefulWidget {
  const _DistributeSheet({
    required this.service,
    required this.orgUnits,
    required this.isIncoming,
  });

  final CorrespondenceService service;
  final List<CorrespondenceLookupOption> orgUnits;
  final bool isIncoming;

  @override
  State<_DistributeSheet> createState() => _DistributeSheetState();
}

class _DistributeSheetState extends State<_DistributeSheet> {
  List<CorrespondenceLookupOption> _toDepts = const [];
  List<CorrespondenceLookupOption> _ccDepts = const [];
  List<CorrespondenceLookupOption> _toUsers = const [];
  List<CorrespondenceLookupOption> _ccUsers = const [];
  final _noteController = TextEditingController();

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  Future<void> _pickDepts(
    String title,
    List<CorrespondenceLookupOption> current,
    ValueSetter<List<CorrespondenceLookupOption>> onSelected,
  ) async {
    final result = await showModalBottomSheet<List<CorrespondenceLookupOption>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder:
          (ctx) => _OrgUnitMultiSelectSheet(
            options: widget.orgUnits,
            title: title,
            current: current,
          ),
    );
    if (result != null && mounted) setState(() => onSelected(result));
  }

  Future<void> _pickUsers(
    String title,
    List<CorrespondenceLookupOption> current,
    ValueSetter<List<CorrespondenceLookupOption>> onSelected,
  ) async {
    final result = await showModalBottomSheet<List<CorrespondenceLookupOption>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder:
          (ctx) => _UserSearchMultiSelectSheet(
            title: title,
            service: widget.service,
            current: current,
          ),
    );
    if (result != null && mounted) setState(() => onSelected(result));
  }

  bool get _isValid =>
      _toDepts.isNotEmpty ||
      _ccDepts.isNotEmpty ||
      _toUsers.isNotEmpty ||
      _ccUsers.isNotEmpty;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'ចែកចាយ',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 16),
              _SheetFieldTile(
                label: 'អង្គភាព TO',
                value:
                    _toDepts.isEmpty
                        ? 'ជ្រើសរើស...'
                        : '${_toDepts.length} អង្គភាព',
                icon: Icons.business_outlined,
                onTap:
                    () => _pickDepts('TO – អង្គភាព', _toDepts, (v) {
                      _toDepts = v;
                    }),
              ),
              if (_toDepts.isNotEmpty) _ChipRow(items: _toDepts),
              const SizedBox(height: 10),
              _SheetFieldTile(
                label: 'អង្គភាព CC',
                value:
                    _ccDepts.isEmpty
                        ? 'ជ្រើសរើស...'
                        : '${_ccDepts.length} អង្គភាព',
                icon: Icons.business_center_outlined,
                onTap:
                    () => _pickDepts('CC – អង្គភាព', _ccDepts, (v) {
                      _ccDepts = v;
                    }),
              ),
              if (_ccDepts.isNotEmpty) _ChipRow(items: _ccDepts),
              const SizedBox(height: 10),
              _SheetFieldTile(
                label: 'បុគ្គល TO',
                value:
                    _toUsers.isEmpty
                        ? 'ជ្រើសរើស...'
                        : '${_toUsers.length} នាក់',
                icon: Icons.person_outline,
                onTap:
                    () => _pickUsers('TO – បុគ្គល', _toUsers, (v) {
                      _toUsers = v;
                    }),
              ),
              if (_toUsers.isNotEmpty) _ChipRow(items: _toUsers),
              const SizedBox(height: 10),
              _SheetFieldTile(
                label: 'បុគ្គល CC',
                value:
                    _ccUsers.isEmpty
                        ? 'ជ្រើសរើស...'
                        : '${_ccUsers.length} នាក់',
                icon: Icons.person_search_outlined,
                onTap:
                    () => _pickUsers('CC – បុគ្គល', _ccUsers, (v) {
                      _ccUsers = v;
                    }),
              ),
              if (_ccUsers.isNotEmpty) _ChipRow(items: _ccUsers),
              const SizedBox(height: 10),
              TextField(
                controller: _noteController,
                decoration: const InputDecoration(
                  labelText: 'ចំណាំ (ស្រេចចិត្ត)',
                  border: OutlineInputBorder(),
                ),
                maxLines: 2,
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed:
                      _isValid
                          ? () => Navigator.pop(
                            context,
                            _DistributeFormResult(
                              toDeptIds: _toDepts.map((e) => e.id).toList(),
                              ccDeptIds: _ccDepts.map((e) => e.id).toList(),
                              toUserIds: _toUsers.map((e) => e.id).toList(),
                              ccUserIds: _ccUsers.map((e) => e.id).toList(),
                              note: _noteController.text.trim(),
                            ),
                          )
                          : null,
                  child: const Text('ចែកចាយ'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Director decision dialog ─────────────────────────────────────────────────

class _DirectorDecisionDialog extends StatefulWidget {
  const _DirectorDecisionDialog();

  @override
  State<_DirectorDecisionDialog> createState() =>
      _DirectorDecisionDialogState();
}

class _DirectorDecisionDialogState extends State<_DirectorDecisionDialog> {
  String _decision = 'approved';
  final _noteController = TextEditingController();

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final needsNote = _decision == 'rejected';
    return AlertDialog(
      title: const Text('ការសម្រេចរបស់ប្រធាន'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Expanded(
                child: RadioListTile<String>(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('អនុម័ត'),
                  value: 'approved',
                  groupValue: _decision,
                  onChanged: (v) => setState(() => _decision = v!),
                  activeColor: const Color(0xFF0B6B58),
                ),
              ),
              Expanded(
                child: RadioListTile<String>(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('មិនអនុម័ត'),
                  value: 'rejected',
                  groupValue: _decision,
                  onChanged: (v) => setState(() => _decision = v!),
                  activeColor: const Color(0xFFDC2626),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _noteController,
            decoration: InputDecoration(
              labelText: needsNote ? 'មូលហេតុ *' : 'ចំណាំ (ស្រេចចិត្ត)',
              border: const OutlineInputBorder(),
            ),
            maxLines: 3,
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('បោះបង់'),
        ),
        ElevatedButton(
          onPressed: () {
            final note = _noteController.text.trim();
            if (needsNote && note.isEmpty) return;
            Navigator.pop(
              context,
              _DecisionFormResult(decision: _decision, note: note),
            );
          },
          style: ElevatedButton.styleFrom(
            backgroundColor:
                _decision == 'approved'
                    ? const Color(0xFF0B6B58)
                    : const Color(0xFFDC2626),
            foregroundColor: Colors.white,
          ),
          child: const Text('យល់ព្រម'),
        ),
      ],
    );
  }
}

// ─── Note dialog ──────────────────────────────────────────────────────────────

class _NoteDialog extends StatefulWidget {
  const _NoteDialog({required this.title, this.required = false});

  final String title;
  final bool required;

  @override
  State<_NoteDialog> createState() => _NoteDialogState();
}

class _NoteDialogState extends State<_NoteDialog> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title),
      content: TextField(
        controller: _controller,
        decoration: InputDecoration(
          labelText: widget.required ? 'ចំណាំ *' : 'ចំណាំ (ស្រេចចិត្ត)',
          border: const OutlineInputBorder(),
        ),
        maxLines: 4,
        autofocus: true,
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('បោះបង់'),
        ),
        ElevatedButton(
          onPressed: () {
            final note = _controller.text.trim();
            if (widget.required && note.isEmpty) return;
            Navigator.pop(context, note.isEmpty ? null : note);
          },
          child: const Text('យល់ព្រម'),
        ),
      ],
    );
  }
}

// ─── Org unit single select sheet ─────────────────────────────────────────────

class _OrgUnitSelectSheet extends StatefulWidget {
  const _OrgUnitSelectSheet({required this.options, required this.title});

  final List<CorrespondenceLookupOption> options;
  final String title;

  @override
  State<_OrgUnitSelectSheet> createState() => _OrgUnitSelectSheetState();
}

class _OrgUnitSelectSheetState extends State<_OrgUnitSelectSheet> {
  final _searchController = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final filtered =
        _query.isEmpty
            ? widget.options
            : widget.options.where((item) {
              final q = _query.toLowerCase();
              return item.text.toLowerCase().contains(q) ||
                  (item.subtitle?.toLowerCase().contains(q) ?? false);
            }).toList();

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              widget.title,
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _searchController,
              decoration: const InputDecoration(
                hintText: 'ស្វែងរក...',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.search),
                isDense: true,
              ),
              onChanged: (v) => setState(() => _query = v),
            ),
            const SizedBox(height: 8),
            Flexible(
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: filtered.length,
                itemBuilder: (ctx, i) {
                  final item = filtered[i];
                  return ListTile(
                    dense: true,
                    leading: const Icon(
                      Icons.radio_button_off,
                      color: Color(0xFF94A3B8),
                    ),
                    title: Text(
                      item.text,
                      style: const TextStyle(fontSize: 13),
                    ),
                    subtitle:
                        item.subtitle != null
                            ? Text(
                              item.subtitle!,
                              style: const TextStyle(fontSize: 11),
                            )
                            : null,
                    onTap: () => Navigator.pop(context, item),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Org unit multi-select sheet ──────────────────────────────────────────────

class _OrgUnitMultiSelectSheet extends StatefulWidget {
  const _OrgUnitMultiSelectSheet({
    required this.options,
    required this.title,
    required this.current,
  });

  final List<CorrespondenceLookupOption> options;
  final String title;
  final List<CorrespondenceLookupOption> current;

  @override
  State<_OrgUnitMultiSelectSheet> createState() =>
      _OrgUnitMultiSelectSheetState();
}

class _OrgUnitMultiSelectSheetState extends State<_OrgUnitMultiSelectSheet> {
  final _searchController = TextEditingController();
  String _query = '';
  late List<CorrespondenceLookupOption> _selected;

  @override
  void initState() {
    super.initState();
    _selected = List.from(widget.current);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final filtered =
        _query.isEmpty
            ? widget.options
            : widget.options.where((item) {
              final q = _query.toLowerCase();
              return item.text.toLowerCase().contains(q) ||
                  (item.subtitle?.toLowerCase().contains(q) ?? false);
            }).toList();

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    widget.title,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 15,
                    ),
                  ),
                ),
                TextButton(
                  onPressed: () => Navigator.pop(context, _selected),
                  child: Text('ជ្រើស (${_selected.length})'),
                ),
              ],
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _searchController,
              decoration: const InputDecoration(
                hintText: 'ស្វែងរក...',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.search),
                isDense: true,
              ),
              onChanged: (v) => setState(() => _query = v),
            ),
            const SizedBox(height: 8),
            Flexible(
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: filtered.length,
                itemBuilder: (ctx, i) {
                  final item = filtered[i];
                  final checked = _selected.any((s) => s.id == item.id);
                  return CheckboxListTile(
                    dense: true,
                    value: checked,
                    title: Text(
                      item.text,
                      style: const TextStyle(fontSize: 13),
                    ),
                    subtitle:
                        item.subtitle != null
                            ? Text(
                              item.subtitle!,
                              style: const TextStyle(fontSize: 11),
                            )
                            : null,
                    activeColor: const Color(0xFF0B6B58),
                    onChanged: (v) {
                      setState(() {
                        if (v == true) {
                          _selected = [..._selected, item];
                        } else {
                          _selected =
                              _selected.where((s) => s.id != item.id).toList();
                        }
                      });
                    },
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── User search single-select sheet ─────────────────────────────────────────

class _UserSearchSingleSelectSheet extends StatefulWidget {
  const _UserSearchSingleSelectSheet({
    required this.title,
    required this.service,
  });

  final String title;
  final CorrespondenceService service;

  @override
  State<_UserSearchSingleSelectSheet> createState() =>
      _UserSearchSingleSelectSheetState();
}

class _UserSearchSingleSelectSheetState
    extends State<_UserSearchSingleSelectSheet> {
  final _searchController = TextEditingController();
  List<CorrespondenceLookupOption> _results = const [];
  bool _loading = false;
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _onChanged(String q) {
    _debounce?.cancel();
    if (q.trim().isEmpty) {
      setState(() => _results = const []);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 400), () async {
      if (!mounted) return;
      setState(() => _loading = true);
      try {
        final rows = await widget.service.searchUserOptions(q.trim());
        if (mounted) setState(() => _results = rows);
      } catch (_) {
      } finally {
        if (mounted) setState(() => _loading = false);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              widget.title,
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _searchController,
              autofocus: true,
              decoration: InputDecoration(
                hintText: 'ស្វែងរកឈ្មោះ...',
                border: const OutlineInputBorder(),
                prefixIcon: const Icon(Icons.search),
                suffixIcon:
                    _loading
                        ? const Padding(
                          padding: EdgeInsets.all(12),
                          child: SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                        )
                        : null,
                isDense: true,
              ),
              onChanged: _onChanged,
            ),
            const SizedBox(height: 8),
            Flexible(
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: _results.length,
                itemBuilder: (ctx, i) {
                  final item = _results[i];
                  return ListTile(
                    dense: true,
                    leading: const CircleAvatar(
                      radius: 14,
                      child: Icon(Icons.person, size: 16),
                    ),
                    title: Text(
                      item.text,
                      style: const TextStyle(fontSize: 13),
                    ),
                    onTap: () => Navigator.pop(context, item),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── User search multi-select sheet ──────────────────────────────────────────

class _UserSearchMultiSelectSheet extends StatefulWidget {
  const _UserSearchMultiSelectSheet({
    required this.title,
    required this.service,
    required this.current,
  });

  final String title;
  final CorrespondenceService service;
  final List<CorrespondenceLookupOption> current;

  @override
  State<_UserSearchMultiSelectSheet> createState() =>
      _UserSearchMultiSelectSheetState();
}

class _UserSearchMultiSelectSheetState
    extends State<_UserSearchMultiSelectSheet> {
  final _searchController = TextEditingController();
  List<CorrespondenceLookupOption> _results = const [];
  late List<CorrespondenceLookupOption> _selected;
  bool _loading = false;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _selected = List.from(widget.current);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _onChanged(String q) {
    _debounce?.cancel();
    if (q.trim().isEmpty) {
      setState(() => _results = const []);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 400), () async {
      if (!mounted) return;
      setState(() => _loading = true);
      try {
        final rows = await widget.service.searchUserOptions(q.trim());
        if (mounted) setState(() => _results = rows);
      } catch (_) {
      } finally {
        if (mounted) setState(() => _loading = false);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    widget.title,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 15,
                    ),
                  ),
                ),
                TextButton(
                  onPressed: () => Navigator.pop(context, _selected),
                  child: Text('ជ្រើស (${_selected.length})'),
                ),
              ],
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _searchController,
              autofocus: true,
              decoration: InputDecoration(
                hintText: 'ស្វែងរកឈ្មោះ...',
                border: const OutlineInputBorder(),
                prefixIcon: const Icon(Icons.search),
                suffixIcon:
                    _loading
                        ? const Padding(
                          padding: EdgeInsets.all(12),
                          child: SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                        )
                        : null,
                isDense: true,
              ),
              onChanged: _onChanged,
            ),
            const SizedBox(height: 8),
            Flexible(
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: _results.length,
                itemBuilder: (ctx, i) {
                  final item = _results[i];
                  final checked = _selected.any((s) => s.id == item.id);
                  return CheckboxListTile(
                    dense: true,
                    value: checked,
                    title: Text(
                      item.text,
                      style: const TextStyle(fontSize: 13),
                    ),
                    activeColor: const Color(0xFF0B6B58),
                    onChanged: (v) {
                      setState(() {
                        if (v == true && !checked) {
                          _selected = [..._selected, item];
                        } else if (v == false) {
                          _selected =
                              _selected.where((s) => s.id != item.id).toList();
                        }
                      });
                    },
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Sheet helper widgets ─────────────────────────────────────────────────────

class _SheetFieldTile extends StatelessWidget {
  const _SheetFieldTile({
    required this.label,
    required this.value,
    required this.icon,
    required this.onTap,
  });

  final String label;
  final String value;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          border: const OutlineInputBorder(),
          prefixIcon: Icon(icon, size: 18),
          suffixIcon: const Icon(Icons.expand_more_rounded),
          isDense: true,
        ),
        child: Text(value, style: const TextStyle(fontSize: 13)),
      ),
    );
  }
}

class _ChipRow extends StatelessWidget {
  const _ChipRow({required this.items});

  final List<CorrespondenceLookupOption> items;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Wrap(
        spacing: 6,
        runSpacing: 4,
        children:
            items
                .map(
                  (item) => Chip(
                    label: Text(
                      item.text,
                      style: const TextStyle(fontSize: 11),
                    ),
                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    visualDensity: VisualDensity.compact,
                  ),
                )
                .toList(),
      ),
    );
  }
}
