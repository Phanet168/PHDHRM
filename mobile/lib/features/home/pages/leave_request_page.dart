import 'package:flutter/material.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_design_system.dart';
import '../../auth/models/auth_user.dart';
import '../models/leave_request_models.dart';
import '../services/home_leave_service.dart';
import 'home/home_profile_widgets.dart';
import 'home/home_theme.dart';
import 'home/leave_balance_widgets.dart';
import 'leave_detail_page.dart';
import 'leave_form_page.dart';
import 'leave_history_page.dart';
import 'leave_review_page.dart';

// ignore_for_file: lines_longer_than_80_chars

Color _dynamicPrimary() =>
    AppDesignSystem.colorForWeekday(DateTime.now().weekday);

String _km(int value) =>
    value
        .toString()
        .split('')
        .map((c) => int.tryParse(c) == null ? c : '០១២៣៤៥៦៧៨៩'[int.parse(c)])
        .join();

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

DateTime? _parseDate(String value) {
  final text = value.trim();
  if (text.isEmpty) return null;
  return DateTime.tryParse(text) ??
      DateTime.tryParse(text.replaceFirst(' ', 'T'));
}

/// "២០ ឧសភា → ២២ ឧសភា ២០២៦" (the year once, when both ends share it).
String _khmerRange(String start, String end) {
  final from = _parseDate(start);
  final to = _parseDate(end);
  if (from == null || to == null) {
    return '${start.trim().isEmpty ? '-' : start.trim()} → ${end.trim().isEmpty ? '-' : end.trim()}';
  }
  String day(DateTime d) => '${_km(d.day)} ${_months[d.month - 1]}';
  if (from.year == to.year && from.month == to.month && from.day == to.day) {
    return '${day(from)} ${_km(from.year)}';
  }
  return from.year == to.year
      ? '${day(from)} → ${day(to)} ${_km(to.year)}'
      : '${day(from)} ${_km(from.year)} → ${day(to)} ${_km(to.year)}';
}

/// "ការសុំច្បាប់": this year's requests at a glance, a new-request button,
/// each leave type's remaining balance, and the latest requests (tap for
/// details); reviewers also get a shortcut to the approval queue.
class LeaveRequestPage extends StatefulWidget {
  LeaveRequestPage({
    super.key,
    required this.user,
    required this.language,
    HomeLeaveService? leaveService,
  }) : leaveService = leaveService ?? HomeLeaveService();

  final AuthUser user;
  final Map<String, String> language;
  final HomeLeaveService leaveService;

  @override
  State<LeaveRequestPage> createState() => _LeaveRequestPageState();
}

class _LeaveRequestPageState extends State<LeaveRequestPage> {
  List<LeaveTypeOption> _types = <LeaveTypeOption>[];
  List<HandoverEmployeeOption> _handoverEmployees = <HandoverEmployeeOption>[];
  List<LeaveRequestItem> _requests = <LeaveRequestItem>[];
  LeaveSummary _summary = const LeaveSummary(
    totalRemaining: 0,
    types: <LeaveBalanceItem>[],
  );
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadAll();
  }

  String _tr(String key, String fallback) {
    final v = widget.language[key]?.trim();
    return (v == null || v.isEmpty) ? fallback : v;
  }

  Future<void> _loadAll() async {
    setState(() => _loading = true);
    try {
      final results = await Future.wait<dynamic>([
        widget.leaveService.fetchTypes(widget.user),
        widget.leaveService.fetchHandoverEmployees(widget.user),
        widget.leaveService.fetchSummary(widget.user),
        widget.leaveService.fetchRequests(widget.user),
      ]);
      if (!mounted) return;
      setState(() {
        _types = results[0] as List<LeaveTypeOption>;
        _handoverEmployees = results[1] as List<HandoverEmployeeOption>;
        _summary = results[2] as LeaveSummary;
        _requests = results[3] as List<LeaveRequestItem>;
      });
    } catch (e) {
      if (mounted) _showError(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _showError(Object error) {
    final msg = extractApiErrorMessage(error);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(msg), backgroundColor: const Color(0xFFEF4444)),
    );
  }

  Future<void> _openForm() async {
    final submitted = await Navigator.push<bool>(
      context,
      MaterialPageRoute<bool>(
        builder:
            (_) => LeaveFormPage(
              user: widget.user,
              language: widget.language,
              leaveService: widget.leaveService,
              types: _types,
              handoverEmployees: _handoverEmployees,
              summary: _summary,
            ),
      ),
    );
    if (submitted == true && mounted) await _loadAll();
  }

  Future<void> _openHistory() async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder:
            (_) => LeaveHistoryPage(
              user: widget.user,
              language: widget.language,
              leaveService: widget.leaveService,
              types: _types,
            ),
      ),
    );
  }

  Future<void> _openReview() async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder:
            (_) => LeaveReviewPage(
              user: widget.user,
              language: widget.language,
              leaveService: widget.leaveService,
            ),
      ),
    );
    if (mounted) await _loadAll();
  }

  Future<void> _openDetail(LeaveRequestItem request) async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder:
            (_) => LeaveDetailPage(
              request: request,
              language: widget.language,
              leaveService: widget.leaveService,
              user: widget.user,
              onCancelled: _loadAll,
            ),
      ),
    );
  }

  Widget _sectionHeader(IconData icon, String title, {Widget? trailing}) {
    final accent = _dynamicPrimary();
    return Padding(
      padding: const EdgeInsets.fromLTRB(2, 22, 2, 12),
      child: Row(
        children: <Widget>[
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
              color: accent.withAlpha(24),
              borderRadius: BorderRadius.circular(9),
            ),
            child: Icon(icon, size: 17, color: accent),
          ),
          const SizedBox(width: 10),
          Expanded(
            flex: 3,
            child: Text(
              title,
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: Color(0xFF0F172A),
              ),
            ),
          ),
          // Shrinks rather than pushing the row past the screen edge.
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
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _requests.isEmpty && _summary.types.isEmpty) {
      return Center(child: CircularProgressIndicator(color: _dynamicPrimary()));
    }

    final year = DateTime.now().year;
    final thisYear =
        _requests.where((r) => _parseDate(r.startDate)?.year == year).toList();
    String status(LeaveRequestItem r) => r.status.trim().toLowerCase();
    final pending = thisYear.where((r) => status(r) == 'pending').length;
    final approved = thisYear.where((r) => status(r) == 'approved').toList();
    final daysTaken = approved.fold<int>(
      0,
      (sum, r) => sum + (r.approvedDays > 0 ? r.approvedDays : r.requestedDays),
    );
    final balances = buildLeaveBalanceDisplays(_summary.types, widget.language);
    final recent = _requests.take(5).toList();
    var entrance = 0;

    return RefreshIndicator(
      color: _dynamicPrimary(),
      onRefresh: _loadAll,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 40),
        children: <Widget>[
          StaggeredEntrance(
            index: entrance++,
            child: _LeaveHero(
              year: year,
              pending: pending,
              approved: approved.length,
              daysTaken: daysTaken,
              newRequestLabel: _tr('request_new_leave', 'ដាក់សំណើច្បាប់ថ្មី'),
              onNewRequest: _openForm,
            ),
          ),
          if (widget.user.canReviewLeaveRequests) ...<Widget>[
            const SizedBox(height: 14),
            StaggeredEntrance(
              index: entrance++,
              child: _ReviewShortcut(onTap: _openReview),
            ),
          ],
          if (balances.isNotEmpty) ...<Widget>[
            _sectionHeader(
              Icons.donut_large_rounded,
              'សមតុល្យច្បាប់ ឆ្នាំ${_km(year)}',
            ),
            StaggeredEntrance(
              index: entrance++,
              child: _BalanceGrid(balances: balances),
            ),
          ],
          _sectionHeader(
            Icons.history_rounded,
            _tr('recent_requests', 'សំណើថ្មីៗ'),
            trailing: TextButton(
              onPressed: _openHistory,
              style: TextButton.styleFrom(
                foregroundColor: _dynamicPrimary(),
                backgroundColor: _dynamicPrimary().withAlpha(18),
                shape: const StadiumBorder(),
                padding: const EdgeInsets.symmetric(horizontal: 14),
              ),
              child: const Text(
                'មើលទាំងអស់ ›',
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12),
              ),
            ),
          ),
          if (recent.isEmpty)
            StaggeredEntrance(
              index: entrance++,
              child: _EmptyState(
                label: _tr('no_data_found', 'មិនទាន់មានសំណើច្បាប់'),
                onNewRequest: _openForm,
              ),
            )
          else
            for (final req in recent)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: StaggeredEntrance(
                  index: entrance++,
                  child: _LeaveRequestCard(
                    request: req,
                    language: widget.language,
                    onTap: () => _openDetail(req),
                  ),
                ),
              ),
        ],
      ),
    );
  }
}

// -- Hero: this year at a glance + the main action --

class _LeaveHero extends StatelessWidget {
  const _LeaveHero({
    required this.year,
    required this.pending,
    required this.approved,
    required this.daysTaken,
    required this.newRequestLabel,
    required this.onNewRequest,
  });

  final int year;
  final int pending;
  final int approved;
  final int daysTaken;
  final String newRequestLabel;
  final VoidCallback onNewRequest;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(26),
      child: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[dashboardHeaderStart, dashboardHeaderEnd],
          ),
        ),
        child: Stack(
          children: <Widget>[
            // Soft decorative circles.
            Positioned(
              right: -40,
              top: -50,
              child: _Blob(size: 170, opacity: 0.10),
            ),
            Positioned(
              left: -30,
              bottom: -60,
              child: _Blob(size: 140, opacity: 0.07),
            ),
            Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Container(
                        width: 46,
                        height: 46,
                        decoration: BoxDecoration(
                          color: Colors.white.withAlpha(40),
                          borderRadius: BorderRadius.circular(15),
                        ),
                        child: const Icon(
                          Icons.event_available_rounded,
                          color: Colors.white,
                          size: 26,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            const Text(
                              'ការសុំច្បាប់របស់ខ្ញុំ',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 17,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            Text(
                              'ឆ្នាំ${_km(year)}',
                              style: const TextStyle(
                                color: Colors.white70,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    decoration: BoxDecoration(
                      color: Colors.white.withAlpha(30),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: Colors.white.withAlpha(45)),
                    ),
                    child: IntrinsicHeight(
                      child: Row(
                        children: <Widget>[
                          _HeroStat(
                            icon: Icons.hourglass_top_rounded,
                            value: pending,
                            label: 'រង់ចាំ',
                          ),
                          _divider(),
                          _HeroStat(
                            icon: Icons.verified_rounded,
                            value: approved,
                            label: 'បានអនុម័ត',
                          ),
                          _divider(),
                          _HeroStat(
                            icon: Icons.beach_access_rounded,
                            value: daysTaken,
                            label: 'ថ្ងៃបានឈប់',
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: onNewRequest,
                      style: FilledButton.styleFrom(
                        backgroundColor: Colors.white,
                        foregroundColor: dashboardHeaderEnd,
                        minimumSize: const Size(0, 50),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      // Label may wrap under a large system font.
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: <Widget>[
                          const Icon(Icons.add_circle_rounded, size: 22),
                          const SizedBox(width: 8),
                          Flexible(
                            child: Text(
                              newRequestLabel,
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        ],
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

  static Widget _divider() => Container(
    width: 1,
    margin: const EdgeInsets.symmetric(vertical: 4),
    color: Colors.white.withAlpha(50),
  );
}

class _HeroStat extends StatelessWidget {
  const _HeroStat({
    required this.icon,
    required this.value,
    required this.label,
  });

  final IconData icon;
  final int value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4),
        child: Column(
          children: <Widget>[
            Icon(icon, color: Colors.white70, size: 18),
            const SizedBox(height: 4),
            FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(
                _km(value),
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            Text(
              label,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white70, fontSize: 11),
            ),
          ],
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

// -- Reviewer shortcut --

class _ReviewShortcut extends StatelessWidget {
  const _ReviewShortcut({required this.onTap});

  final VoidCallback onTap;

  static const _amber = Color(0xFFE6943B);

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: _amber.withAlpha(70)),
          ),
          child: Row(
            children: <Widget>[
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: <Color>[Color(0xFFF2B05E), _amber],
                  ),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.fact_check_rounded,
                  color: Colors.white,
                  size: 24,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'ពិនិត្យ/អនុម័តសំណើ',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    Text(
                      'សំណើច្បាប់ដែលរង់ចាំការសម្រេចរបស់អ្នក',
                      style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: _amber),
            ],
          ),
        ),
      ),
    );
  }
}

// -- Balance grid: two tiles per row, each row as tall as its tallest tile --

class _BalanceGrid extends StatelessWidget {
  const _BalanceGrid({required this.balances});

  final List<LeaveBalanceDisplay> balances;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        for (var i = 0; i < balances.length; i += 2)
          Padding(
            padding: EdgeInsets.only(bottom: i + 2 < balances.length ? 10 : 0),
            child: IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Expanded(child: LeaveBalanceCard(item: balances[i])),
                  const SizedBox(width: 10),
                  Expanded(
                    child:
                        i + 1 < balances.length
                            ? LeaveBalanceCard(item: balances[i + 1])
                            : const SizedBox.shrink(),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

// -- Recent leave request card --

class _LeaveRequestCard extends StatelessWidget {
  const _LeaveRequestCard({
    required this.request,
    required this.language,
    required this.onTap,
  });

  final LeaveRequestItem request;
  final Map<String, String> language;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final typeLabel =
        request.leaveTypeKm.trim().isNotEmpty
            ? request.leaveTypeKm
            : request.leaveType.trim().isNotEmpty
            ? request.leaveType
            : 'ប្រភេទច្បាប់មិនបញ្ជាក់';
    final statusInfo = _statusInfo(request.status);

    // Shadow sits behind the opaque white card (a shadow on a transparent
    // box would tint the card grey); the ripple stays on the card.
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x0F000000),
            blurRadius: 12,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                // Status stripe.
                Container(
                  width: 5,
                  decoration: BoxDecoration(
                    color: statusInfo.color,
                    borderRadius: const BorderRadius.horizontal(
                      left: Radius.circular(18),
                    ),
                  ),
                ),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(12, 14, 10, 14),
                    child: Row(
                      children: <Widget>[
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            color: statusInfo.color.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Icon(
                            statusInfo.icon,
                            size: 22,
                            color: statusInfo.color,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                typeLabel,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF0F172A),
                                ),
                              ),
                              const SizedBox(height: 4),
                              Row(
                                children: <Widget>[
                                  const Icon(
                                    Icons.calendar_month_rounded,
                                    size: 14,
                                    color: Color(0xFF94A3B8),
                                  ),
                                  const SizedBox(width: 4),
                                  Expanded(
                                    child: Text(
                                      _khmerRange(
                                        request.startDate,
                                        request.endDate,
                                      ),
                                      style: const TextStyle(
                                        fontSize: 12,
                                        color: Color(0xFF64748B),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Wrap(
                                spacing: 6,
                                runSpacing: 6,
                                children: <Widget>[
                                  _Pill(
                                    label: '${_km(request.requestedDays)} ថ្ងៃ',
                                    color: const Color(0xFF475569),
                                  ),
                                  _Pill(
                                    label: statusInfo.label(language),
                                    color: statusInfo.color,
                                    icon: statusInfo.icon,
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        const Icon(
                          Icons.chevron_right_rounded,
                          color: Color(0xFFCBD5E1),
                        ),
                      ],
                    ),
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

// -- Shared UI helpers --

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color, this.icon});

  final String label;
  final Color color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          if (icon != null) ...<Widget>[
            Icon(icon, size: 12, color: color),
            const SizedBox(width: 4),
          ],
          Flexible(
            child: Text(
              label,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: color,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.label, required this.onNewRequest});

  final String label;
  final VoidCallback onNewRequest;

  @override
  Widget build(BuildContext context) {
    final accent = _dynamicPrimary();
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 28, horizontal: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        children: <Widget>[
          Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: accent.withAlpha(20),
            ),
            child: Icon(Icons.event_note_rounded, size: 36, color: accent),
          ),
          const SizedBox(height: 12),
          Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Color(0xFF64748B),
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 10),
          TextButton.icon(
            onPressed: onNewRequest,
            style: TextButton.styleFrom(foregroundColor: accent),
            icon: const Icon(Icons.add_rounded),
            label: const Text('ដាក់សំណើដំបូង'),
          ),
        ],
      ),
    );
  }
}

// -- Status helpers --

class _StatusInfo {
  const _StatusInfo(this._status);
  final String _status;

  Color get color {
    switch (_status.trim().toLowerCase()) {
      case 'approved':
        return const Color(0xFF10B981);
      case 'rejected':
        return const Color(0xFFEF4444);
      case 'cancelled':
        return const Color(0xFF9CA3AF);
      default:
        return const Color(0xFFF59E0B);
    }
  }

  IconData get icon {
    switch (_status.trim().toLowerCase()) {
      case 'approved':
        return Icons.check_circle_rounded;
      case 'rejected':
        return Icons.cancel_rounded;
      case 'cancelled':
        return Icons.block_rounded;
      default:
        return Icons.hourglass_top_rounded;
    }
  }

  String label(Map<String, String> language) {
    switch (_status.trim().toLowerCase()) {
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

_StatusInfo _statusInfo(String status) => _StatusInfo(status);
