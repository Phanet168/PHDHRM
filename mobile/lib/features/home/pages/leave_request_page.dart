import 'package:flutter/material.dart';

import '../../auth/models/auth_user.dart';
import '../models/leave_request_models.dart';
import '../services/home_leave_service.dart';
import 'leave_form_page.dart';
import 'leave_history_page.dart';
import 'leave_review_page.dart';

// ignore_for_file: lines_longer_than_80_chars

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
        widget.leaveService.fetchSummary(widget.user),
        widget.leaveService.fetchRequests(widget.user),
      ]);
      if (!mounted) return;
      setState(() {
        _types = results[0] as List<LeaveTypeOption>;
        _summary = results[1] as LeaveSummary;
        _requests = results[2] as List<LeaveRequestItem>;
      });
    } catch (e) {
      if (mounted) _showError(e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _showError(String message) {
    final msg = message
        .replaceAll('ApiException(statusCode: null, message: ', '')
        .replaceAll(')', '');
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

  // Prioritised balance display definitions
  static const List<_LeaveBalanceDef> _balanceDefs = <_LeaveBalanceDef>[
    _LeaveBalanceDef(
      title: 'ឈប់ប្រចាំឆ្នាំ',
      tint: Color(0xFFEFF6FF),
      accent: Color(0xFF3B82F6),
      icon: Icons.event_note_rounded,
      keywords: <String>['annual', 'year', 'ប្រចាំឆ្នាំ'],
    ),
    _LeaveBalanceDef(
      title: 'ឈប់រយៈពេលខ្លី',
      tint: Color(0xFFF0FDF4),
      accent: Color(0xFF10B981),
      icon: Icons.hourglass_top_rounded,
      keywords: <String>['short', 'casual', 'special', 'រយៈពេលខ្លី'],
    ),
    _LeaveBalanceDef(
      title: 'ឈប់លំហែមាតុភាព',
      tint: Color(0xFFFAF5FF),
      accent: Color(0xFF8B5CF6),
      icon: Icons.favorite_border_rounded,
      keywords: <String>['maternity', 'mater', 'លំហែ'],
    ),
    _LeaveBalanceDef(
      title: 'ឈប់ព្យាបាលជំងឺ',
      tint: Color(0xFFFFFBEB),
      accent: Color(0xFFF59E0B),
      icon: Icons.local_hospital_outlined,
      keywords: <String>['sick', 'medical', 'ព្យាបាល', 'ជំងឺ'],
    ),
  ];

  List<_LeaveBalanceDisplay> _buildBalances() {
    final source = _summary.types;
    final used = <int>{};

    LeaveBalanceItem? pick(List<String> keywords) {
      for (var i = 0; i < source.length; i++) {
        if (used.contains(i)) continue;
        final eng = source[i].leaveType.toLowerCase();
        final km = source[i].leaveTypeKm.toLowerCase();
        if (keywords.any((k) => eng.contains(k) || km.contains(k))) {
          used.add(i);
          return source[i];
        }
      }
      return null;
    }

    LeaveBalanceItem? fallback() {
      for (var i = 0; i < source.length; i++) {
        if (!used.contains(i)) {
          used.add(i);
          return source[i];
        }
      }
      return null;
    }

    return _balanceDefs.map((def) {
      final row = pick(def.keywords) ?? fallback();
      final ent = row?.entitlement ?? 0;
      final usedDays = row?.used ?? 0;
      final rem = row?.remaining ?? 0;
      // Use the API's Khmer name if available, else fall back to hardcoded title
      final displayTitle =
          (row != null)
              ? (row.leaveTypeKm.trim().isNotEmpty
                  ? row.leaveTypeKm
                  : (row.leaveType.trim().isNotEmpty
                      ? row.leaveType
                      : def.title))
              : def.title;
      return _LeaveBalanceDisplay(
        title: displayTitle,
        used: usedDays,
        total: ent,
        remaining: rem,
        percent: ent <= 0 ? 0.0 : (usedDays / ent).clamp(0.0, 1.0),
        tint: def.tint,
        accent: def.accent,
        icon: def.icon,
      );
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(
        child: CircularProgressIndicator(color: Color(0xFF0B6B58)),
      );
    }

    final balances = _buildBalances();
    final recent = _requests.take(5).toList();

    return RefreshIndicator(
      color: const Color(0xFF0B6B58),
      onRefresh: _loadAll,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
        children: <Widget>[
          _BalanceSummaryCard(
            totalRemaining: _summary.totalRemaining,
            balances: balances,
          ),
          const SizedBox(height: 16),
          _PrimaryButton(
            label: _tr('request_new_leave', 'ស្នើសុំច្បាប់ថ្មី'),
            icon: Icons.add_circle_outline_rounded,
            onPressed: _openForm,
          ),
          if (widget.user.canReviewLeaveRequests) ...<Widget>[
            const SizedBox(height: 10),
            _OutlineButton(
              label: _tr('approve_leave', 'ពិនិត្យ / អនុម័តសំណើ'),
              icon: Icons.assignment_turned_in_outlined,
              onPressed: _openReview,
            ),
          ],
          const SizedBox(height: 20),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: <Widget>[
              Text(
                _tr('recent_requests', 'សំណើថ្មីៗ'),
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: const Color(0xFF0F172A),
                ),
              ),
              GestureDetector(
                onTap: _openHistory,
                child: Text(
                  _tr('view_all', 'មើលទាំងអស់  ›'),
                  style: const TextStyle(
                    color: Color(0xFF0B6B58),
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (recent.isEmpty)
            _EmptyState(label: _tr('no_data_found', 'មិនមានសំណើ'))
          else
            ...recent.map(
              (req) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: _LeaveRequestCard(
                  request: req,
                  language: widget.language,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

// -- Balance summary card --

class _BalanceSummaryCard extends StatelessWidget {
  const _BalanceSummaryCard({
    required this.totalRemaining,
    required this.balances,
  });

  final int totalRemaining;
  final List<_LeaveBalanceDisplay> balances;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x0C000000),
            blurRadius: 20,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'សមតុល្យច្បាប់សរុប',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey[500],
                      ),
                    ),
                    const SizedBox(height: 2),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: <Widget>[
                        Text(
                          '\$totalRemaining',
                          style: const TextStyle(
                            fontSize: 32,
                            fontWeight: FontWeight.w900,
                            color: Color(0xFF0B6B58),
                            height: 1.1,
                          ),
                        ),
                        const SizedBox(width: 4),
                        const Padding(
                          padding: EdgeInsets.only(bottom: 4),
                          child: Text(
                            'ថ្ងៃ',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                              color: Color(0xFF64748B),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: const Color(0xFFECFDF5),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  '\${DateTime.now().year}',
                  style: const TextStyle(
                    color: Color(0xFF10B981),
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
              mainAxisExtent: 136,
            ),
            itemCount: balances.length,
            itemBuilder: (_, i) => _LeaveBalanceCard(item: balances[i]),
          ),
        ],
      ),
    );
  }
}

// -- Leave balance grid card --

class _LeaveBalanceCard extends StatelessWidget {
  const _LeaveBalanceCard({required this.item});

  final _LeaveBalanceDisplay item;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
      decoration: BoxDecoration(
        color: item.tint,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: item.accent.withValues(alpha: 0.25)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: item.accent.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(item.icon, size: 14, color: item.accent),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            item.title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: Color(0xFF0F172A),
              height: 1.3,
            ),
          ),
          const Spacer(),
          ClipRRect(
            borderRadius: BorderRadius.circular(3),
            child: LinearProgressIndicator(
              value: item.percent,
              minHeight: 4,
              backgroundColor: item.accent.withValues(alpha: 0.15),
              color: item.accent,
            ),
          ),
          const SizedBox(height: 6),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: <Widget>[
              Text(
                '\${item.used}/\${item.total}',
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: Colors.grey[600],
                ),
              ),
              Text(
                'នៅសល់ \${item.remaining}',
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: item.accent,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// -- Recent leave request card --

class _LeaveRequestCard extends StatelessWidget {
  const _LeaveRequestCard({required this.request, required this.language});

  final LeaveRequestItem request;
  final Map<String, String> language;

  @override
  Widget build(BuildContext context) {
    final typeLabel =
        request.leaveTypeKm.trim().isNotEmpty
            ? request.leaveTypeKm
            : request.leaveType;
    final statusInfo = _statusInfo(request.status);

    return Container(
      padding: const EdgeInsets.all(14),
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
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: statusInfo.color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              Icons.event_available_outlined,
              size: 20,
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
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF0F172A),
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '\${request.startDate}  →  \${request.endDate}',
                  style: const TextStyle(
                    fontSize: 12,
                    color: Color(0xFF64748B),
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '\${request.requestedDays} ថ្ងៃ',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF475569),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          _StatusBadge(
            label: statusInfo.label(language),
            color: statusInfo.color,
          ),
        ],
      ),
    );
  }
}

// -- Shared UI helpers --

class _PrimaryButton extends StatelessWidget {
  const _PrimaryButton({
    required this.label,
    required this.icon,
    required this.onPressed,
  });

  final String label;
  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: ElevatedButton.icon(
        onPressed: onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFF0B6B58),
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          elevation: 0,
        ),
        icon: Icon(icon, size: 20),
        label: Text(
          label,
          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
        ),
      ),
    );
  }
}

class _OutlineButton extends StatelessWidget {
  const _OutlineButton({
    required this.label,
    required this.icon,
    required this.onPressed,
  });

  final String label;
  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 46,
      child: OutlinedButton.icon(
        onPressed: onPressed,
        style: OutlinedButton.styleFrom(
          foregroundColor: const Color(0xFF0B6B58),
          side: const BorderSide(color: Color(0xFF0B6B58), width: 1.5),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
        icon: Icon(icon, size: 18),
        label: Text(
          label,
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 36),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: <Widget>[
          Icon(Icons.inbox_outlined, size: 48, color: Colors.grey[300]),
          const SizedBox(height: 8),
          Text(label, style: TextStyle(color: Colors.grey[400], fontSize: 14)),
        ],
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
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

// -- Private data models --

class _LeaveBalanceDef {
  const _LeaveBalanceDef({
    required this.title,
    required this.tint,
    required this.accent,
    required this.icon,
    required this.keywords,
  });

  final String title;
  final Color tint;
  final Color accent;
  final IconData icon;
  final List<String> keywords;
}

class _LeaveBalanceDisplay {
  const _LeaveBalanceDisplay({
    required this.title,
    required this.used,
    required this.total,
    required this.remaining,
    required this.percent,
    required this.tint,
    required this.accent,
    required this.icon,
  });

  final String title;
  final int used;
  final int total;
  final int remaining;
  final double percent;
  final Color tint;
  final Color accent;
  final IconData icon;
}
