import 'package:flutter/material.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/localization/laravel_language_service.dart';
import '../../../core/theme/app_design_system.dart';
import '../../auth/controllers/auth_controller.dart';
import 'correspondence_create_page.dart';
import 'correspondence_detail_page.dart';
import '../models/correspondence_models.dart';
import '../services/correspondence_service.dart';

Color _dynamicPrimary() =>
    AppDesignSystem.colorForWeekday(DateTime.now().weekday);

class CorrespondencePage extends StatefulWidget {
  const CorrespondencePage({
    super.key,
    required this.authController,
    this.onRefreshReady,
  });

  final AuthController authController;

  /// Hands the page's own list-reload function up to whatever screen embeds
  /// it — this page has no topbar of its own (see the home shell's shared
  /// AppBar), so its refresh action must be triggered from there instead.
  final ValueChanged<Future<void> Function()>? onRefreshReady;

  @override
  State<CorrespondencePage> createState() => _CorrespondencePageState();
}

class _CorrespondencePageState extends State<CorrespondencePage> {
  late final CorrespondenceService _service;
  late final Future<Map<String, String>> _languageFuture;

  Future<CorrespondenceListResponse>? _incomingFuture;
  Future<CorrespondenceListResponse>? _outgoingFuture;
  Future<Map<String, dynamic>>? _dashboardFuture;
  bool _canCreateIncoming = false;
  bool _canCreateOutgoing = false;
  String _period = 'all';
  DateTime? _startDate;
  DateTime? _endDate;

  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';

  int _selectedTab = 0; // 0 = incoming, 1 = outgoing, 2 = draft, 3 = dashboard

  @override
  void initState() {
    super.initState();
    _service = CorrespondenceService();
    _languageFuture = LaravelLanguageService.instance.load();
    widget.onRefreshReady?.call(_refresh);
    _incomingFuture = _loadIncoming();
    _outgoingFuture = _loadOutgoing();
    _dashboardFuture = _loadDashboard();
    _dashboardFuture!.then(_syncCreatePermissions).catchError((_) {});
    _searchController.addListener(() {
      setState(() => _searchQuery = _searchController.text.trim());
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  /// Draft outgoing letters — those still at the pre-dispatch step, never
  /// sent — filtered client-side from the same outgoing list the "ផ្ញើ" tab
  /// already loads, since the backend has no separate draft-only endpoint.
  List<CorrespondenceLetter> _draftsOf(List<CorrespondenceLetter> letters) {
    return letters
        .where((l) => l.currentStep == 'outgoing_draft')
        .toList(growable: false);
  }

  List<CorrespondenceLetter> _sentOf(List<CorrespondenceLetter> letters) {
    return letters
        .where((l) => l.currentStep != 'outgoing_draft')
        .toList(growable: false);
  }

  List<CorrespondenceLetter> _applySearch(List<CorrespondenceLetter> letters) {
    if (_searchQuery.isEmpty) {
      return letters;
    }
    final query = _searchQuery.toLowerCase();
    return letters
        .where(
          (l) =>
              l.subject.toLowerCase().contains(query) ||
              (l.letterNo ?? '').toLowerCase().contains(query) ||
              (l.registryNo ?? '').toLowerCase().contains(query),
        )
        .toList(growable: false);
  }

  Future<CorrespondenceListResponse> _loadIncoming({
    bool forceRefresh = false,
  }) {
    if (forceRefresh) {
      _incomingFuture = null;
    }
    return _incomingFuture ??= _service.fetchIncomingLetters(
      period: _period,
      forceRefresh: forceRefresh,
      startDate: _startDate,
      endDate: _endDate,
    );
  }

  Future<CorrespondenceListResponse> _loadOutgoing({
    bool forceRefresh = false,
  }) {
    if (forceRefresh) {
      _outgoingFuture = null;
    }
    return _outgoingFuture ??= _service.fetchOutgoingLetters(
      period: _period,
      forceRefresh: forceRefresh,
      startDate: _startDate,
      endDate: _endDate,
    );
  }

  Future<Map<String, dynamic>> _loadDashboard() {
    return _dashboardFuture ??= _service.fetchDashboard(
      period: _period,
      startDate: _startDate,
      endDate: _endDate,
    );
  }

  void _syncCreatePermissions(Map<String, dynamic> dashboard) {
    final permissions = dashboard['permissions'];
    if (permissions is! Map<String, dynamic>) {
      return;
    }

    final canIncoming = permissions['can_create_incoming'] == true;
    final canOutgoing = permissions['can_create_outgoing'] == true;

    if (!mounted) {
      _canCreateIncoming = canIncoming;
      _canCreateOutgoing = canOutgoing;
      return;
    }

    setState(() {
      _canCreateIncoming = canIncoming;
      _canCreateOutgoing = canOutgoing;
    });
  }

  Future<void> _refresh() async {
    setState(() {
      _incomingFuture = _loadIncoming(forceRefresh: true);
      _outgoingFuture = _loadOutgoing(forceRefresh: true);
      _dashboardFuture = _service.fetchDashboard(
        period: _period,
        forceRefresh: true,
        startDate: _startDate,
        endDate: _endDate,
      );
    });

    _dashboardFuture?.then(_syncCreatePermissions).catchError((_) {});

    try {
      if (_selectedTab == 0) {
        await _incomingFuture;
      } else if (_selectedTab == 1 || _selectedTab == 2) {
        await _outgoingFuture;
      } else {
        await _dashboardFuture;
      }
    } catch (_) {
      // Error handled by FutureBuilder
    }
  }

  String _tr(Map<String, String> language, String key, String fallback) {
    return language[key]?.trim() ?? fallback;
  }

  String _formatDate(DateTime? value) {
    if (value == null) {
      return '--/--/----'.replaceAll('/', '-');
    }
    final day = value.day.toString().padLeft(2, '0');
    final month = value.month.toString().padLeft(2, '0');
    return '$day-$month-${value.year}';
  }

  Future<DateTime?> _pickFilterDate(DateTime? current) async {
    final now = DateTime.now();
    return showDatePicker(
      context: context,
      initialDate: current ?? now,
      firstDate: DateTime(now.year - 5),
      lastDate: DateTime(now.year + 5),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: ColorScheme.light(
              primary: _dynamicPrimary(),
              onPrimary: Colors.white,
            ),
          ),
          child: child!,
        );
      },
    );
  }

  Future<void> _openDateFilterSheet(Map<String, String> language) async {
    final selected = await showModalBottomSheet<_DateRangeSelection>(
      context: context,
      isScrollControlled: true,
      builder: (context) {
        return _DateRangeFilterSheet(
          language: language,
          initialPeriod: _period,
          initialStartDate: _startDate,
          initialEndDate: _endDate,
          formatDate: _formatDate,
          pickDate: _pickFilterDate,
        );
      },
    );

    if (selected == null || !mounted) {
      return;
    }

    setState(() {
      _period = selected.period;
      _startDate = selected.startDate;
      _endDate = selected.endDate;
      _incomingFuture = _service.fetchIncomingLetters(
        period: _period,
        startDate: _startDate,
        endDate: _endDate,
      );
      _outgoingFuture = _service.fetchOutgoingLetters(
        period: _period,
        startDate: _startDate,
        endDate: _endDate,
      );
      _dashboardFuture = _service.fetchDashboard(
        period: _period,
        startDate: _startDate,
        endDate: _endDate,
      );
    });

    _dashboardFuture?.then(_syncCreatePermissions).catchError((_) {});
  }

  Future<void> _openLetterDetail(CorrespondenceLetter letter) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder:
            (context) => CorrespondenceDetailPage(
              letterId: letter.id,
              service: _service,
              authController: widget.authController,
            ),
      ),
    );

    if (!mounted) {
      return;
    }

    await _refresh();
  }

  Future<void> _createNewLetter() async {
    if (!_canCreateIncoming && !_canCreateOutgoing) {
      _showPermissionMessage();
      return;
    }

    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder:
            (context) => CorrespondenceCreatePage(
              service: _service,
              languageFuture: _languageFuture,
              canCreateIncoming: _canCreateIncoming,
              canCreateOutgoing: _canCreateOutgoing,
            ),
      ),
    );

    if (created == true && mounted) {
      await _refresh();
    }
  }

  void _showPermissionMessage() {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('អ្នកមិនមានសិទ្ធិបង្កើតលិខិត'),
        backgroundColor: Color(0xFFDC2626),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, String>>(
      future: _languageFuture,
      builder: (context, snapshot) {
        final language = snapshot.data ?? const <String, String>{};
        final hasDateFilter =
            _startDate != null || _endDate != null || _period != 'all';

        final showCreateFab =
            _selectedTab != 3 && (_canCreateIncoming || _canCreateOutgoing);

        // Plain Container+Stack rather than a nested Scaffold: this page is
        // always embedded inside the home shell's own Scaffold (which
        // already owns the persistent bottom navigation bar), and a second
        // Scaffold's own FloatingActionButton geometry doesn't account for
        // that outer bar, letting the "+" button float on top of it.
        return Container(
          color: const Color(0xFFF7F9F8),
          child: Stack(
            children: [
              SafeArea(
                bottom: false,
                child: Column(
                  children: [
                    Container(
                      color: Colors.white,
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                      child: Column(
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Container(
                                  height: 46,
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 14,
                                  ),
                                  decoration: BoxDecoration(
                                    border: Border.all(
                                      color: const Color(0xFFE1E8E4),
                                    ),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Row(
                                    children: [
                                      const Icon(
                                        Icons.search_rounded,
                                        size: 20,
                                        color: Color(0xFF6D7973),
                                      ),
                                      const SizedBox(width: 10),
                                      Expanded(
                                        child: TextField(
                                          controller: _searchController,
                                          decoration: InputDecoration(
                                            isDense: true,
                                            isCollapsed: true,
                                            filled: false,
                                            contentPadding: EdgeInsets.zero,
                                            border: InputBorder.none,
                                            enabledBorder: InputBorder.none,
                                            focusedBorder: InputBorder.none,
                                            disabledBorder: InputBorder.none,
                                            hintText: _tr(
                                              language,
                                              'search_letter',
                                              'ស្វែងរកលិខិត...',
                                            ),
                                            hintStyle: const TextStyle(
                                              fontSize: 14,
                                              color: Color(0xFF6D7973),
                                            ),
                                          ),
                                          style: const TextStyle(
                                            fontSize: 14,
                                            color: Color(0xFF17231D),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              InkWell(
                                borderRadius: BorderRadius.circular(12),
                                onTap: () => _openDateFilterSheet(language),
                                child: Container(
                                  width: 46,
                                  height: 46,
                                  alignment: Alignment.center,
                                  decoration: BoxDecoration(
                                    color:
                                        hasDateFilter
                                            ? _dynamicPrimary().withAlpha(26)
                                            : const Color(0xFFF5F7F6),
                                    borderRadius: BorderRadius.circular(12),
                                    border: Border.all(
                                      color:
                                          hasDateFilter
                                              ? _dynamicPrimary()
                                              : const Color(0xFFE1E8E4),
                                    ),
                                  ),
                                  child: Icon(
                                    Icons.tune_rounded,
                                    size: 20,
                                    color:
                                        hasDateFilter
                                            ? _dynamicPrimary()
                                            : const Color(0xFF6D7973),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          Container(
                            padding: const EdgeInsets.all(4),
                            decoration: BoxDecoration(
                              color: const Color(0xFFE8EEEA),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Row(
                              children: [
                                Expanded(
                                  child: _TabButton(
                                    label: _tr(
                                      language,
                                      'incoming_letter',
                                      'ទទួល',
                                    ),
                                    isActive: _selectedTab == 0,
                                    onPressed:
                                        () => setState(() => _selectedTab = 0),
                                  ),
                                ),
                                Expanded(
                                  child: _TabButton(
                                    label: _tr(
                                      language,
                                      'outgoing_letter',
                                      'ផ្ញើ',
                                    ),
                                    isActive: _selectedTab == 1,
                                    onPressed:
                                        () => setState(() => _selectedTab = 1),
                                  ),
                                ),
                                Expanded(
                                  child: _TabButton(
                                    label: _tr(
                                      language,
                                      'draft_letter',
                                      'ព្រាង',
                                    ),
                                    isActive: _selectedTab == 2,
                                    onPressed:
                                        () => setState(() => _selectedTab = 2),
                                  ),
                                ),
                                Expanded(
                                  child: _TabButton(
                                    label: _tr(language, 'dashboard', 'សង្ខេប'),
                                    isActive: _selectedTab == 3,
                                    onPressed:
                                        () => setState(() => _selectedTab = 3),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Divider(height: 1, color: Color(0xFFE1E8E4)),
                    Expanded(child: _buildTabContent(language)),
                  ],
                ),
              ),
              if (showCreateFab)
                Positioned(
                  right: 16,
                  bottom: 16,
                  // A real FloatingActionButton here is unreliable — its tap
                  // sometimes falls through to the list card underneath once
                  // it's layered via Positioned/Stack instead of Scaffold's
                  // own floatingActionButton slot. GestureDetector+Container
                  // over a plain circle is the workaround already used
                  // elsewhere in this app for the same kind of flaky tap.
                  child: GestureDetector(
                    onTap: _createNewLetter,
                    child: Container(
                      width: 56,
                      height: 56,
                      decoration: BoxDecoration(
                        color: _dynamicPrimary(),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.25),
                            blurRadius: 8,
                            offset: const Offset(0, 3),
                          ),
                        ],
                      ),
                      child: const Icon(
                        Icons.add_rounded,
                        size: 28,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildTabContent(Map<String, String> language) {
    switch (_selectedTab) {
      case 0:
        return _buildIncomingTab(language);
      case 1:
        return _buildOutgoingTab(language);
      case 2:
        return _buildDraftTab(language);
      default:
        return _buildDashboardTab(language);
    }
  }

  Widget _buildIncomingTab(Map<String, String> language) {
    return FutureBuilder<CorrespondenceListResponse>(
      future: _incomingFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }

        if (snapshot.hasError) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.error_outline, size: 48, color: Colors.grey[400]),
                const SizedBox(height: 12),
                Text(
                  '${snapshot.error}',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.grey[600]),
                ),
              ],
            ),
          );
        }

        final response = snapshot.data;
        final letters = _applySearch(response?.letters ?? []);

        if (letters.isEmpty) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.mail_outline, size: 48, color: Colors.grey[400]),
                const SizedBox(height: 12),
                Text(
                  _tr(language, 'no_incoming_letter', 'មិនមានលិខិតចូលឡើយ'),
                  style: TextStyle(color: Colors.grey[600]),
                ),
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.separated(
            padding: const EdgeInsets.all(12),
            itemCount: letters.length,
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemBuilder: (context, index) {
              final letter = letters[index];
              return _CorrespondenceCard(
                letter: letter,
                language: language,
                onTap: () => _openLetterDetail(letter),
              );
            },
          ),
        );
      },
    );
  }

  Widget _buildOutgoingTab(Map<String, String> language) {
    return FutureBuilder<CorrespondenceListResponse>(
      future: _outgoingFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }

        if (snapshot.hasError) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.error_outline, size: 48, color: Colors.grey[400]),
                const SizedBox(height: 12),
                Text(
                  '${snapshot.error}',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.grey[600]),
                ),
              ],
            ),
          );
        }

        final response = snapshot.data;
        final letters = _applySearch(_sentOf(response?.letters ?? []));

        if (letters.isEmpty) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.mail_outline, size: 48, color: Colors.grey[400]),
                const SizedBox(height: 12),
                Text(
                  _tr(language, 'no_outgoing_letter', 'មិនមានលិខិតចេញឡើយ'),
                  style: TextStyle(color: Colors.grey[600]),
                ),
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.separated(
            padding: const EdgeInsets.all(12),
            itemCount: letters.length,
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemBuilder: (context, index) {
              final letter = letters[index];
              return _CorrespondenceCard(
                letter: letter,
                language: language,
                onTap: () => _openLetterDetail(letter),
              );
            },
          ),
        );
      },
    );
  }

  Widget _buildDraftTab(Map<String, String> language) {
    return FutureBuilder<CorrespondenceListResponse>(
      future: _outgoingFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }

        if (snapshot.hasError) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.error_outline, size: 48, color: Colors.grey[400]),
                const SizedBox(height: 12),
                Text(
                  '${snapshot.error}',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.grey[600]),
                ),
              ],
            ),
          );
        }

        final response = snapshot.data;
        final letters = _applySearch(_draftsOf(response?.letters ?? []));

        if (letters.isEmpty) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  Icons.edit_note_rounded,
                  size: 48,
                  color: Colors.grey[400],
                ),
                const SizedBox(height: 12),
                Text(
                  _tr(language, 'no_draft_letter', 'មិនទាន់មានលិខិតព្រាងទេ'),
                  style: TextStyle(color: Colors.grey[600]),
                ),
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.separated(
            padding: const EdgeInsets.all(12),
            itemCount: letters.length,
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemBuilder: (context, index) {
              final letter = letters[index];
              return _CorrespondenceCard(
                letter: letter,
                language: language,
                onTap: () => _openLetterDetail(letter),
              );
            },
          ),
        );
      },
    );
  }

  Widget _buildDashboardTab(Map<String, String> language) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _dashboardFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }

        if (snapshot.hasError) {
          return Center(child: Text('Error: ${snapshot.error}'));
        }

        final data = snapshot.data ?? <String, dynamic>{};
        final permissions =
            data['permissions'] is Map<String, dynamic>
                ? data['permissions'] as Map<String, dynamic>
                : const <String, dynamic>{};
        final levelLabel =
            (permissions['corr_level_label'] ?? '').toString().trim();
        final departmentName =
            (permissions['corr_department_name'] ?? '').toString().trim();
        final hasPermissionInfo =
            levelLabel.isNotEmpty || departmentName.isNotEmpty;

        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (hasPermissionInfo) ...[
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFECFDF5),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFA7F3D0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (levelLabel.isNotEmpty)
                        Text(
                          '${_tr(language, 'level', 'កម្រិត')}: $levelLabel',
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF065F46),
                          ),
                        ),
                      if (departmentName.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          '${_tr(language, 'department', 'អង្គភាព')}: $departmentName',
                          style: const TextStyle(
                            fontSize: 12,
                            color: Color(0xFF047857),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 12),
              ],
              // Summary cards
              _SummaryCard(
                title: _tr(language, 'incoming_total', 'លិខិតចូលសរុប'),
                count: (data['incoming_total'] as num?)?.toInt() ?? 0,
                color: const Color(0xFF1D4F91),
              ),
              const SizedBox(height: 12),
              _SummaryCard(
                title: _tr(language, 'outgoing_total', 'លិខិតចេញសរុប'),
                count: (data['outgoing_total'] as num?)?.toInt() ?? 0,
                color: _dynamicPrimary(),
              ),
              const SizedBox(height: 12),
              _SummaryCard(
                title: _tr(language, 'pending_ack', 'ស្ងាប់រង់ចាំព័ត៌មាន'),
                count: (data['pending_ack_count'] as num?)?.toInt() ?? 0,
                color: const Color(0xFFD79C2E),
              ),
              const SizedBox(height: 12),
              _SummaryCard(
                title: _tr(language, 'in_progress', 'កំពុងដំណើរការ'),
                count: (data['in_progress_count'] as num?)?.toInt() ?? 0,
                color: const Color(0xFF5D79C8),
              ),
              const SizedBox(height: 12),
              _SummaryCard(
                title: _tr(language, 'completed', 'បានបញ្ចប់'),
                count: (data['completed_count'] as num?)?.toInt() ?? 0,
                color: const Color(0xFF0F766E),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _TabButton extends StatelessWidget {
  const _TabButton({
    required this.label,
    required this.isActive,
    required this.onPressed,
  });

  final String label;
  final bool isActive;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: isActive ? Colors.white : Colors.transparent,
      borderRadius: BorderRadius.circular(8),
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 9),
          child: Text(
            label,
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 13,
              color: isActive ? _dynamicPrimary() : const Color(0xFF6D7973),
              fontWeight: isActive ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }
}

class _CorrespondenceCard extends StatelessWidget {
  const _CorrespondenceCard({
    required this.letter,
    required this.language,
    required this.onTap,
  });

  final CorrespondenceLetter letter;
  final Map<String, String> language;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final iconBackground =
        letter.isIncoming ? const Color(0xFFEAF2FC) : const Color(0xFFE8F4EE);
    final iconColor =
        letter.isIncoming ? const Color(0xFF1D4F91) : _dynamicPrimary();
    final counterpart =
        letter.isIncoming
            ? (letter.fromOrg?.trim().isNotEmpty == true
                ? '${_tr('from', 'ពី៖')} ${letter.fromOrg}'
                : null)
            : (letter.toOrg?.trim().isNotEmpty == true
                ? '${_tr('to', 'ទៅ៖')} ${letter.toOrg}'
                : null);
    final dateLabel = _formatShortDate(letter.letterDate ?? letter.createdAt);
    final hasAttachments = (letter.attachments ?? const []).isNotEmpty;

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border:
                letter.isUrgent
                    ? Border.all(color: const Color(0xFFEF4444).withAlpha(76))
                    : null,
            boxShadow: const [
              BoxShadow(
                color: Color(0x1417233B),
                blurRadius: 10,
                offset: Offset(0, 3),
              ),
            ],
          ),
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 40,
                height: 40,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: iconBackground,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  letter.isIncoming
                      ? Icons.move_to_inbox_outlined
                      : Icons.outbox_outlined,
                  size: 20,
                  color: iconColor,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            letter.letterNo?.trim().isNotEmpty == true
                                ? 'លេខ ${letter.letterNo}'
                                : letter.getLocalizedType(language),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: _dynamicPrimary(),
                            ),
                          ),
                        ),
                        if (letter.isUrgent)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 6,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEF4444).withAlpha(26),
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: const Text(
                              'បន្ទាន់',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFFEF4444),
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 5),
                    Text(
                      letter.subject,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF17231D),
                      ),
                    ),
                    if (counterpart != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        counterpart,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF6D7973),
                        ),
                      ),
                    ],
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Text(
                          letter.getLocalizedStatus(language),
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: _statusColor(letter.status),
                          ),
                        ),
                        if (letter.currentHandlerName != null) ...[
                          const Text(
                            '  ·  ',
                            style: TextStyle(color: Color(0xFF6D7973)),
                          ),
                          Expanded(
                            child: Text(
                              letter.currentHandlerName!,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 11,
                                color: Color(0xFF6D7973),
                              ),
                            ),
                          ),
                        ] else
                          const Spacer(),
                        if (dateLabel != null)
                          Text(
                            dateLabel,
                            style: const TextStyle(
                              fontSize: 11,
                              color: Color(0xFF6D7973),
                            ),
                          ),
                        if (hasAttachments) ...[
                          const SizedBox(width: 6),
                          const Icon(
                            Icons.attach_file_rounded,
                            size: 14,
                            color: Color(0xFF6D7973),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _tr(String key, String fallback) =>
      language[key]?.trim().isNotEmpty == true ? language[key]! : fallback;

  String? _formatShortDate(DateTime? value) {
    if (value == null) return null;
    const monthNames = [
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
    return '${value.day} ${monthNames[value.month - 1]} ${value.year}';
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'completed':
        return _dynamicPrimary();
      case 'in_progress':
        return const Color(0xFF1D4F91);
      case 'pending':
        return const Color(0xFFD79C2E);
      case 'archived':
        return Colors.grey;
      default:
        return Colors.grey[600]!;
    }
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({
    required this.title,
    required this.count,
    required this.color,
  });

  final String title;
  final int count;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: color.withAlpha(13),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withAlpha(51)),
      ),
      child: Row(
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: color.withAlpha(26),
              shape: BoxShape.circle,
            ),
            child: Center(
              child: Text(
                '$count',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  color: color,
                ),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              title,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: Color(0xFF10211B),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DateRangeSelection {
  const _DateRangeSelection({
    required this.period,
    this.startDate,
    this.endDate,
  });

  final String period;
  final DateTime? startDate;
  final DateTime? endDate;
}

class _DateRangeFilterSheet extends StatefulWidget {
  const _DateRangeFilterSheet({
    required this.language,
    required this.initialPeriod,
    required this.initialStartDate,
    required this.initialEndDate,
    required this.formatDate,
    required this.pickDate,
  });

  final Map<String, String> language;
  final String initialPeriod;
  final DateTime? initialStartDate;
  final DateTime? initialEndDate;
  final String Function(DateTime?) formatDate;
  final Future<DateTime?> Function(DateTime? current) pickDate;

  @override
  State<_DateRangeFilterSheet> createState() => _DateRangeFilterSheetState();
}

class _DateRangeFilterSheetState extends State<_DateRangeFilterSheet> {
  static const List<String> _periodOptions = <String>[
    'all',
    'today',
    'yesterday',
    'this_week',
    'this_month',
    'custom',
  ];

  late String _period;
  DateTime? _startDate;
  DateTime? _endDate;

  @override
  void initState() {
    super.initState();
    _period =
        _periodOptions.contains(widget.initialPeriod)
            ? widget.initialPeriod
            : 'all';
    _startDate = widget.initialStartDate;
    _endDate = widget.initialEndDate;
  }

  String _tr(String key, String fallback) {
    final value = widget.language[key]?.trim();
    return value == null || value.isEmpty ? fallback : value;
  }

  String _periodLabel(String value) {
    switch (value) {
      case 'today':
        return _tr('today', 'ថ្ងៃនេះ');
      case 'yesterday':
        return _tr('yesterday', 'ម្សិលមិញ');
      case 'this_week':
        return _tr('this_week', 'សប្ដាហ៍នេះ');
      case 'this_month':
        return _tr('this_month', 'ខែនេះ');
      case 'custom':
        return _tr('custom', 'កំណត់ដោយខ្លួនឯង');
      case 'all':
      default:
        return _tr('all_date', 'គ្រប់កាលបរិច្ឆេទ');
    }
  }

  Future<void> _chooseStartDate() async {
    final picked = await widget.pickDate(_startDate);
    if (picked == null || !mounted) {
      return;
    }

    setState(() {
      _startDate = picked;
      if (_endDate != null && picked.isAfter(_endDate!)) {
        _endDate = picked;
      }
    });
  }

  Future<void> _chooseEndDate() async {
    final picked = await widget.pickDate(_endDate ?? _startDate);
    if (picked == null || !mounted) {
      return;
    }

    setState(() {
      _endDate = picked;
      if (_startDate != null && picked.isBefore(_startDate!)) {
        _startDate = picked;
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
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              _tr('select_date', 'ជ្រើសរើសកាលបរិច្ឆេទ'),
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children:
                  _periodOptions.map((option) {
                    final selected = _period == option;
                    return ChoiceChip(
                      label: Text(_periodLabel(option)),
                      selected: selected,
                      onSelected: (_) {
                        setState(() {
                          _period = option;
                          if (_period != 'custom') {
                            _startDate = null;
                            _endDate = null;
                          }
                        });
                      },
                    );
                  }).toList(),
            ),
            if (_period == 'custom') ...[
              const SizedBox(height: 12),
              _DateFieldTile(
                label: _tr('from_date', 'ពីថ្ងៃ'),
                value: widget.formatDate(_startDate),
                onTap: _chooseStartDate,
              ),
              _DateFieldTile(
                label: _tr('to_date', 'ដល់ថ្ងៃ'),
                value: widget.formatDate(_endDate),
                onTap: _chooseEndDate,
              ),
            ],
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () {
                      setState(() {
                        _period = 'all';
                        _startDate = null;
                        _endDate = null;
                      });
                    },
                    child: Text(_tr('clear', 'សម្អាត')),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () {
                      var period = _period;
                      if (period == 'custom' &&
                          _startDate == null &&
                          _endDate == null) {
                        period = 'all';
                      }

                      Navigator.of(context).pop(
                        _DateRangeSelection(
                          period: period,
                          startDate: period == 'custom' ? _startDate : null,
                          endDate: period == 'custom' ? _endDate : null,
                        ),
                      );
                    },
                    child: Text(_tr('filter', 'ចម្រាញ់')),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CorrespondenceCreatePage extends StatefulWidget {
  const _CorrespondenceCreatePage({
    required this.service,
    required this.languageFuture,
  });

  final CorrespondenceService service;
  final Future<Map<String, String>> languageFuture;

  @override
  State<_CorrespondenceCreatePage> createState() =>
      _CorrespondenceCreatePageState();
}

class _CorrespondenceCreatePageState extends State<_CorrespondenceCreatePage> {
  final _formKey = GlobalKey<FormState>();
  final _subjectController = TextEditingController();
  final _letterNoController = TextEditingController();
  final _registryNoController = TextEditingController();
  final _fromOrgController = TextEditingController();
  final _toOrgController = TextEditingController();

  String _letterType = 'incoming';
  String _priority = 'normal';
  DateTime? _letterDate;
  DateTime? _receivedDate;
  DateTime? _sentDate;
  DateTime? _dueDate;
  bool _submitting = false;

  @override
  void dispose() {
    _subjectController.dispose();
    _letterNoController.dispose();
    _registryNoController.dispose();
    _fromOrgController.dispose();
    _toOrgController.dispose();
    super.dispose();
  }

  String _tr(Map<String, String> language, String key, String fallback) {
    final value = language[key]?.trim();
    return value == null || value.isEmpty ? fallback : value;
  }

  String _formatDate(DateTime? value) {
    if (value == null) {
      return '--/--/----'.replaceAll('/', '-');
    }
    final day = value.day.toString().padLeft(2, '0');
    final month = value.month.toString().padLeft(2, '0');
    return '$day-$month-${value.year}';
  }

  Future<void> _pickDate(
    DateTime? current,
    ValueSetter<DateTime> onPicked,
  ) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: current ?? now,
      firstDate: DateTime(now.year - 2),
      lastDate: DateTime(now.year + 3),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: ColorScheme.light(
              primary: _dynamicPrimary(),
              onPrimary: Colors.white,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked == null || !mounted) {
      return;
    }

    setState(() => onPicked(picked));
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? const Color(0xFFDC2626) : _dynamicPrimary(),
      ),
    );
  }

  Future<void> _submit(Map<String, String> language) async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    setState(() => _submitting = true);

    try {
      await widget.service.createLetter(
        CorrespondenceCreateRequest(
          letterType: _letterType,
          subject: _subjectController.text.trim(),
          letterNo:
              _letterNoController.text.trim().isEmpty
                  ? null
                  : _letterNoController.text.trim(),
          registryNo:
              _registryNoController.text.trim().isEmpty
                  ? null
                  : _registryNoController.text.trim(),
          priority: _priority,
          fromOrg:
              _fromOrgController.text.trim().isEmpty
                  ? null
                  : _fromOrgController.text.trim(),
          toOrg:
              _toOrgController.text.trim().isEmpty
                  ? null
                  : _toOrgController.text.trim(),
          letterDate: _letterDate,
          receivedDate: _letterType == 'incoming' ? _receivedDate : null,
          sentDate: _letterType == 'outgoing' ? _sentDate : null,
          dueDate: _dueDate,
          sendAction: 'draft',
        ),
      );

      if (!mounted) {
        return;
      }

      _showMessage(_tr(language, 'data_save', 'បានរក្សាទុកដោយជោគជ័យ'));
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) {
        return;
      }
      _showMessage(extractApiErrorMessage(error), isError: true);
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, String>>(
      future: widget.languageFuture,
      builder: (context, snapshot) {
        final language = snapshot.data ?? const <String, String>{};

        return Scaffold(
          appBar: AppBar(
            title: Text(_tr(language, 'new_letter', 'បង្កើតលិខិតថ្មី')),
          ),
          body: Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _FormSectionCard(
                  title: _tr(language, 'correspondence', 'លិខិតរដ្ឋបាល'),
                  child: Column(
                    children: [
                      DropdownButtonFormField<String>(
                        initialValue: _letterType,
                        decoration: InputDecoration(
                          labelText: _tr(
                            language,
                            'letter_type',
                            'ប្រភេទលិខិត',
                          ),
                          border: const OutlineInputBorder(),
                        ),
                        items: const [
                          DropdownMenuItem(
                            value: 'incoming',
                            child: Text('លិខិតចូល'),
                          ),
                          DropdownMenuItem(
                            value: 'outgoing',
                            child: Text('លិខិតចេញ'),
                          ),
                        ],
                        onChanged: (value) {
                          if (value == null) {
                            return;
                          }
                          setState(() => _letterType = value);
                        },
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _subjectController,
                        decoration: InputDecoration(
                          labelText: _tr(language, 'subject', 'ប្រធានបទ'),
                          border: const OutlineInputBorder(),
                        ),
                        validator: (value) {
                          if (value == null || value.trim().isEmpty) {
                            return _tr(
                              language,
                              'subject',
                              'សូមបញ្ចូលប្រធានបទ',
                            );
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _letterNoController,
                        decoration: const InputDecoration(
                          labelText: 'លេខលិខិត',
                          border: OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _registryNoController,
                        decoration: const InputDecoration(
                          labelText: 'លេខចុះបញ្ជី',
                          border: OutlineInputBorder(),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                _FormSectionCard(
                  title: _tr(language, 'status', 'ព័ត៌មានបន្ថែម'),
                  child: Column(
                    children: [
                      DropdownButtonFormField<String>(
                        initialValue: _priority,
                        decoration: const InputDecoration(
                          labelText: 'អាទិភាព',
                          border: OutlineInputBorder(),
                        ),
                        items: const [
                          DropdownMenuItem(
                            value: 'normal',
                            child: Text('ធម្មតា'),
                          ),
                          DropdownMenuItem(
                            value: 'urgent',
                            child: Text('បន្ទាន់'),
                          ),
                          DropdownMenuItem(
                            value: 'confidential',
                            child: Text('សម្ងាត់'),
                          ),
                        ],
                        onChanged: (value) {
                          if (value == null) {
                            return;
                          }
                          setState(() => _priority = value);
                        },
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _fromOrgController,
                        decoration: const InputDecoration(
                          labelText: 'មកពីអង្គភាព',
                          border: OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _toOrgController,
                        decoration: const InputDecoration(
                          labelText: 'ទៅអង្គភាព',
                          border: OutlineInputBorder(),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                _FormSectionCard(
                  title: _tr(language, 'date', 'កាលបរិច្ឆេទ'),
                  child: Column(
                    children: [
                      _DateFieldTile(
                        label: 'ថ្ងៃលិខិត',
                        value: _formatDate(_letterDate),
                        onTap:
                            () => _pickDate(
                              _letterDate,
                              (value) => _letterDate = value,
                            ),
                      ),
                      if (_letterType == 'incoming')
                        _DateFieldTile(
                          label: 'ថ្ងៃទទួល',
                          value: _formatDate(_receivedDate),
                          onTap:
                              () => _pickDate(
                                _receivedDate,
                                (value) => _receivedDate = value,
                              ),
                        ),
                      if (_letterType == 'outgoing')
                        _DateFieldTile(
                          label: 'ថ្ងៃផ្ញើ',
                          value: _formatDate(_sentDate),
                          onTap:
                              () => _pickDate(
                                _sentDate,
                                (value) => _sentDate = value,
                              ),
                        ),
                      _DateFieldTile(
                        label: 'ថ្ងៃផុតកំណត់',
                        value: _formatDate(_dueDate),
                        onTap:
                            () => _pickDate(
                              _dueDate,
                              (value) => _dueDate = value,
                            ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Text(
                    'ការបង្កើតពី mobile បច្ចុប្បន្នរក្សាទុកជាសេចក្តីព្រាង (draft) មុនសិន។ ការចែកចាយ និង workflow action នឹងបន្ថែមបន្ទាប់។',
                    style: const TextStyle(
                      fontSize: 13,
                      color: Color(0xFF475569),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                SizedBox(
                  height: 52,
                  child: ElevatedButton.icon(
                    onPressed: _submitting ? null : () => _submit(language),
                    icon:
                        _submitting
                            ? const SizedBox(
                              height: 18,
                              width: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                            : const Icon(Icons.save_outlined),
                    label: Text(
                      _submitting
                          ? _tr(language, 'loading', 'កំពុងរក្សាទុក...')
                          : _tr(language, 'save', 'រក្សាទុក'),
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _FormSectionCard extends StatelessWidget {
  const _FormSectionCard({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0F0F172A),
            blurRadius: 12,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _DateFieldTile extends StatelessWidget {
  const _DateFieldTile({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: label,
            border: const OutlineInputBorder(),
            suffixIcon: const Icon(Icons.calendar_month_outlined),
          ),
          child: Text(value),
        ),
      ),
    );
  }
}
