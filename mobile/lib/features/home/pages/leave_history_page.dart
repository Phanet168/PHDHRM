import 'package:flutter/material.dart';

import '../../auth/models/auth_user.dart';
import '../models/leave_request_models.dart';
import '../services/home_leave_service.dart';
import 'leave_detail_page.dart';

// ─────────────────────────────────────────────────────────────────────────────
//  Leave History Screen  –  ប្រវត្តិច្បាប់
// ─────────────────────────────────────────────────────────────────────────────

class LeaveHistoryPage extends StatefulWidget {
  const LeaveHistoryPage({
    super.key,
    required this.user,
    required this.language,
    required this.leaveService,
    required this.types,
  });

  final AuthUser user;
  final Map<String, String> language;
  final HomeLeaveService leaveService;
  final List<LeaveTypeOption> types;

  @override
  State<LeaveHistoryPage> createState() => _LeaveHistoryPageState();
}

class _LeaveHistoryPageState extends State<LeaveHistoryPage> {
  List<LeaveRequestItem> _all = <LeaveRequestItem>[];
  bool _loading = true;

  // Filters
  int _filterYear = DateTime.now().year;
  int? _filterMonth; // null = all months
  String _filterStatus = 'all';
  int? _filterTypeId;

  static const List<int> _years = <int>[2022, 2023, 2024, 2025, 2026, 2027];
  static const List<String> _statusOptions = <String>[
    'all',
    'pending',
    'approved',
    'rejected',
    'cancelled',
  ];

  static const List<String> _kmMonths = <String>[
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
      final requests = await widget.leaveService.fetchRequests(widget.user);
      if (!mounted) return;
      setState(() => _all = requests);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.toString()),
            backgroundColor: const Color(0xFFEF4444),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  // ── Filtering ──────────────────────────────────────────────────────────────

  List<LeaveRequestItem> get _filtered {
    return _all.where((req) {
      // Year filter
      final start = _parseDate(req.startDate);
      if (start != null && start.year != _filterYear) return false;

      // Month filter
      if (_filterMonth != null &&
          start != null &&
          start.month != _filterMonth) {
        return false;
      }

      // Status filter
      if (_filterStatus != 'all' &&
          req.status.trim().toLowerCase() != _filterStatus) {
        return false;
      }

      // Type filter
      if (_filterTypeId != null) {
        if (req.leaveTypeId != _filterTypeId) {
          return false;
        }
      }

      return true;
    }).toList();
  }

  DateTime? _parseDate(String s) {
    if (s.isEmpty) return null;
    try {
      final parts = s.split('-');
      if (parts.length >= 3) {
        return DateTime(
          int.parse(parts[0]),
          int.parse(parts[1]),
          int.parse(parts[2]),
        );
      }
    } catch (_) {}
    return null;
  }

  String _monthLabel(int month) => _kmMonths[month - 1];

  String _statusLabel(String status) {
    switch (status) {
      case 'pending':
        return _tr('pending', 'រង់ចាំ');
      case 'approved':
        return _tr('approved', 'អនុម័ត');
      case 'rejected':
        return _tr('rejected', 'បដិសេធ');
      case 'cancelled':
        return _tr('cancelled', 'បោះបង់');
      default:
        return 'ទាំងអស់';
    }
  }

  // ── Build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final filtered = _filtered;

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
          'ប្រវត្តិច្បាប់',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
        ),
        actions: <Widget>[
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Refresh',
            onPressed: _loading ? null : _loadAll,
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Container(height: 1, color: const Color(0xFFF0F0F0)),
        ),
      ),
      body: Column(
        children: <Widget>[
          // ── Filter bar ─────────────────────────────────────────────────
          _FilterBar(
            filterYear: _filterYear,
            filterMonth: _filterMonth,
            filterStatus: _filterStatus,
            filterTypeId: _filterTypeId,
            years: _years,
            statusOptions: _statusOptions,
            types: widget.types,
            onYearChanged:
                (v) => setState(() => _filterYear = v ?? DateTime.now().year),
            onMonthChanged: (v) => setState(() => _filterMonth = v),
            onStatusChanged: (v) => setState(() => _filterStatus = v ?? 'all'),
            onTypeChanged: (v) => setState(() => _filterTypeId = v),
            monthLabel: _monthLabel,
            statusLabel: _statusLabel,
            language: widget.language,
          ),

          // ── Results count ──────────────────────────────────────────────
          if (!_loading)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
              child: Row(
                children: <Widget>[
                  Text(
                    'សរុប ${filtered.length} សំណើ',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF64748B),
                    ),
                  ),
                ],
              ),
            ),

          // ── List ───────────────────────────────────────────────────────
          Expanded(
            child:
                _loading
                    ? const Center(
                      child: CircularProgressIndicator(
                        color: Color(0xFF0B6B58),
                      ),
                    )
                    : filtered.isEmpty
                    ? _EmptyHistory(
                      label: _tr('no_data_found', 'មិនមានទិន្នន័យ'),
                    )
                    : RefreshIndicator(
                      color: const Color(0xFF0B6B58),
                      onRefresh: _loadAll,
                      child: ListView.builder(
                        padding: const EdgeInsets.fromLTRB(16, 10, 16, 32),
                        itemCount: filtered.length,
                        itemBuilder: (_, i) {
                          final req = filtered[i];
                          return Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: _HistoryCard(
                              request: req,
                              language: widget.language,
                              onTap: () => _openDetail(req),
                            ),
                          );
                        },
                      ),
                    ),
          ),
        ],
      ),
    );
  }

  Future<void> _openDetail(LeaveRequestItem req) async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder:
            (_) => LeaveDetailPage(
              request: req,
              language: widget.language,
              leaveService: widget.leaveService,
              user: widget.user,
              onCancelled: _loadAll,
            ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Filter bar
// ─────────────────────────────────────────────────────────────────────────────

class _FilterBar extends StatelessWidget {
  const _FilterBar({
    required this.filterYear,
    required this.filterMonth,
    required this.filterStatus,
    required this.filterTypeId,
    required this.years,
    required this.statusOptions,
    required this.types,
    required this.onYearChanged,
    required this.onMonthChanged,
    required this.onStatusChanged,
    required this.onTypeChanged,
    required this.monthLabel,
    required this.statusLabel,
    required this.language,
  });

  final int filterYear;
  final int? filterMonth;
  final String filterStatus;
  final int? filterTypeId;
  final List<int> years;
  final List<String> statusOptions;
  final List<LeaveTypeOption> types;
  final ValueChanged<int?> onYearChanged;
  final ValueChanged<int?> onMonthChanged;
  final ValueChanged<String?> onStatusChanged;
  final ValueChanged<int?> onTypeChanged;
  final String Function(int) monthLabel;
  final String Function(String) statusLabel;
  final Map<String, String> language;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: <Widget>[
            // Year
            _FilterChipDrop<int>(
              icon: Icons.calendar_month_outlined,
              value: filterYear,
              items:
                  years
                      .map(
                        (y) =>
                            DropdownMenuItem<int>(value: y, child: Text('$y')),
                      )
                      .toList(),
              onChanged: onYearChanged,
            ),
            const SizedBox(width: 8),

            // Month
            _FilterChipDrop<int>(
              icon: Icons.date_range_outlined,
              hint: 'ខែ',
              value: filterMonth,
              items: <DropdownMenuItem<int>>[
                const DropdownMenuItem<int>(
                  value: null,
                  child: Text('ខែទាំងអស់'),
                ),
                ...List<DropdownMenuItem<int>>.generate(
                  12,
                  (i) => DropdownMenuItem<int>(
                    value: i + 1,
                    child: Text(monthLabel(i + 1)),
                  ),
                ),
              ],
              onChanged: onMonthChanged,
            ),
            const SizedBox(width: 8),

            // Status
            _FilterChipDrop<String>(
              icon: Icons.tune_rounded,
              hint: 'ស្ថានភាព',
              value: filterStatus,
              items:
                  statusOptions
                      .map(
                        (s) => DropdownMenuItem<String>(
                          value: s,
                          child: Text(s == 'all' ? 'ទាំងអស់' : statusLabel(s)),
                        ),
                      )
                      .toList(),
              onChanged: onStatusChanged,
            ),

            if (types.isNotEmpty) ...<Widget>[
              const SizedBox(width: 8),
              // Leave type
              _FilterChipDrop<int>(
                icon: Icons.category_outlined,
                hint: 'ប្រភេទ',
                value: filterTypeId,
                items: <DropdownMenuItem<int>>[
                  const DropdownMenuItem<int>(
                    value: null,
                    child: Text('ប្រភេទទាំងអស់'),
                  ),
                  ...types.map(
                    (t) => DropdownMenuItem<int>(
                      value: t.id,
                      child: Text(t.displayName(language)),
                    ),
                  ),
                ],
                onChanged: onTypeChanged,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _FilterChipDrop<T> extends StatelessWidget {
  const _FilterChipDrop({
    required this.icon,
    required this.value,
    required this.items,
    required this.onChanged,
    this.hint,
  });

  final IconData icon;
  final T? value;
  final List<DropdownMenuItem<T>> items;
  final ValueChanged<T?> onChanged;
  final String? hint;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: DropdownButton<T>(
        value: value,
        hint:
            hint != null
                ? Row(
                  children: <Widget>[
                    Icon(icon, size: 14, color: const Color(0xFF64748B)),
                    const SizedBox(width: 4),
                    Text(
                      hint!,
                      style: const TextStyle(
                        fontSize: 12,
                        color: Color(0xFF64748B),
                      ),
                    ),
                  ],
                )
                : Row(
                  children: <Widget>[
                    Icon(icon, size: 14, color: const Color(0xFF0B6B58)),
                    const SizedBox(width: 4),
                  ],
                ),
        underline: const SizedBox.shrink(),
        items: items,
        onChanged: onChanged,
        isDense: true,
        style: const TextStyle(
          fontSize: 12,
          color: Color(0xFF0F172A),
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  History card
// ─────────────────────────────────────────────────────────────────────────────

class _HistoryCard extends StatelessWidget {
  const _HistoryCard({
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
            : request.leaveType;
    final si = _statusInfo(request.status);

    return GestureDetector(
      onTap: onTap,
      child: Container(
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
          children: <Widget>[
            // Status indicator strip
            Container(
              width: 4,
              height: 56,
              decoration: BoxDecoration(
                color: si.color,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          typeLabel,
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF0F172A),
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(width: 8),
                      _StatusBadge(label: si.label(language), color: si.color),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: <Widget>[
                      const Icon(
                        Icons.date_range_outlined,
                        size: 13,
                        color: Color(0xFF94A3B8),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '${request.startDate}  →  ${request.endDate}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF64748B),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 3),
                  Row(
                    children: <Widget>[
                      const Icon(
                        Icons.schedule_outlined,
                        size: 13,
                        color: Color(0xFF94A3B8),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '${request.requestedDays} ថ្ងៃ',
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF475569),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            const Icon(
              Icons.chevron_right_rounded,
              size: 18,
              color: Color(0xFFCBD5E1),
            ),
          ],
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Empty state
// ─────────────────────────────────────────────────────────────────────────────

class _EmptyHistory extends StatelessWidget {
  const _EmptyHistory({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(Icons.folder_open_outlined, size: 64, color: Colors.grey[300]),
          const SizedBox(height: 12),
          Text(label, style: TextStyle(color: Colors.grey[400], fontSize: 14)),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Shared helpers (re-declared to avoid cross-file private access)
// ─────────────────────────────────────────────────────────────────────────────

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
