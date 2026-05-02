import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../../core/network/api_exception.dart';
import '../../auth/models/auth_user.dart';
import '../models/leave_request_models.dart';
import '../services/home_leave_service.dart';

// ─────────────────────────────────────────────────────────────────────────────
//  Leave Request Form  –  ស្នើសុំច្បាប់ថ្មី
// ─────────────────────────────────────────────────────────────────────────────

class LeaveFormPage extends StatefulWidget {
  const LeaveFormPage({
    super.key,
    required this.user,
    required this.language,
    required this.leaveService,
    required this.types,
    required this.handoverEmployees,
    required this.summary,
  });

  final AuthUser user;
  final Map<String, String> language;
  final HomeLeaveService leaveService;
  final List<LeaveTypeOption> types;
  final List<HandoverEmployeeOption> handoverEmployees;
  final LeaveSummary summary;

  @override
  State<LeaveFormPage> createState() => _LeaveFormPageState();
}

class _LeaveFormPageState extends State<LeaveFormPage> {
  final _formKey = GlobalKey<FormState>();
  final _reasonController = TextEditingController();
  final _noteController = TextEditingController();

  int? _selectedTypeId;
  int? _selectedHandoverEmployeeId;
  DateTime? _startDate;
  DateTime? _endDate;
  PlatformFile? _attachment;
  bool _submitting = false;

  // ── Helpers ────────────────────────────────────────────────────────────────

  String _tr(String key, String fallback) {
    final v = widget.language[key]?.trim();
    return (v == null || v.isEmpty) ? fallback : v;
  }

  LeaveBalanceItem? get _selectedBalance {
    final id = _selectedTypeId;
    if (id == null) return null;
    try {
      return widget.summary.types.firstWhere((b) => b.leaveTypeId == id);
    } catch (_) {
      return null;
    }
  }

  LeaveTypeOption? get _selectedType {
    final id = _selectedTypeId;
    if (id == null) return null;
    try {
      return widget.types.firstWhere((t) => t.id == id);
    } catch (_) {
      return null;
    }
  }

  HandoverEmployeeOption? get _selectedHandoverEmployee {
    final id = _selectedHandoverEmployeeId;
    if (id == null) return null;
    try {
      return widget.handoverEmployees.firstWhere((employee) => employee.id == id);
    } catch (_) {
      return null;
    }
  }

  int get _dayCount {
    if (_startDate == null || _endDate == null) return 0;
    if (_endDate!.isBefore(_startDate!)) return 0;
    return _endDate!.difference(_startDate!).inDays + 1;
  }

  bool get _showsBalanceWarning {
    final bal = _selectedBalance;
    if (bal == null) return false;
    return _dayCount > bal.remaining;
  }

  bool get _showsPerRequestWarning {
    final type = _selectedType;
    if (type == null) return false;
    if (type.maxPerRequest <= 0) return false;
    return _dayCount > type.maxPerRequest;
  }

  String _fmt(DateTime? d) {
    if (d == null) return '--/--/----'.replaceAll('/', '-');
    return '${d.day.toString().padLeft(2, '0')}-${d.month.toString().padLeft(2, '0')}-${d.year}';
  }

  // ── Date pickers ───────────────────────────────────────────────────────────

  Future<void> _pickStart() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _startDate ?? now,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 2),
      builder: _datePickerTheme,
    );
    if (picked == null) return;
    setState(() {
      _startDate = picked;
      if (_endDate != null && _endDate!.isBefore(picked)) _endDate = picked;
    });
  }

  Future<void> _pickEnd() async {
    final now = DateTime.now();
    final min = _startDate ?? DateTime(now.year - 1);
    final picked = await showDatePicker(
      context: context,
      initialDate: _endDate ?? min,
      firstDate: min,
      lastDate: DateTime(now.year + 2),
      builder: _datePickerTheme,
    );
    if (picked == null) return;
    setState(() => _endDate = picked);
  }

  Widget _datePickerTheme(BuildContext ctx, Widget? child) {
    return Theme(
      data: Theme.of(ctx).copyWith(
        colorScheme: const ColorScheme.light(
          primary: Color(0xFF0B6B58),
          onPrimary: Colors.white,
        ),
      ),
      child: child!,
    );
  }

  Future<void> _openHandoverPicker() async {
    if (_submitting) return;

    final selected = await showModalBottomSheet<int>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return _HandoverEmployeePickerSheet(
          title: _tr('handover_employee', 'អ្នកជំនួស'),
          searchHint: _tr(
            'search_handover_employee',
            'ស្វែងរកឈ្មោះ ឬ លេខបុគ្គលិក',
          ),
          employees: widget.handoverEmployees,
          selectedId: _selectedHandoverEmployeeId,
        );
      },
    );

    if (!mounted || selected == null) return;
    setState(() => _selectedHandoverEmployeeId = selected);
  }

  // ── Attachment ─────────────────────────────────────────────────────────────

  Future<void> _pickAttachment() async {
    final result = await FilePicker.platform.pickFiles(
      withData: false,
      type: FileType.custom,
      allowedExtensions: const <String>[
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'txt',
        'rtf',
        'jpeg',
        'jpg',
        'png',
        'gif',
        'svg',
      ],
    );
    if (result == null || result.files.isEmpty) return;
    final file = result.files.first;
    const maxAttachmentBytes = 50 * 1024 * 1024;
    if (file.size > maxAttachmentBytes) {
      _showMsg('ឯកសារធំពេក (អតិបរមា 50MB)', isError: true);
      return;
    }
    setState(() => _attachment = file);
  }

  // ── Submit ─────────────────────────────────────────────────────────────────

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    final typeId = _selectedTypeId;
    if (typeId == null) {
      _showMsg(_tr('leave_type', 'សូមជ្រើសប្រភេទច្បាប់'), isError: true);
      return;
    }
    if (_startDate == null || _endDate == null) {
      _showMsg('សូមជ្រើសថ្ងៃ', isError: true);
      return;
    }
    if ((_selectedHandoverEmployeeId ?? 0) <= 0) {
      _showMsg(
        _tr('handover_employee', 'សូមជ្រើសរើសអ្នកជំនួស'),
        isError: true,
      );
      return;
    }
    final reason = _reasonController.text.trim();
    final note = _noteController.text.trim();
    if (reason.isEmpty) {
      _showMsg(_tr('leave_reason', 'សូមបញ្ចូលមូលហេតុ'), isError: true);
      return;
    }
    if (_dayCount <= 0) {
      _showMsg('កាលបរិច្ឆេទសុំច្បាប់មិនត្រឹមត្រូវ', isError: true);
      return;
    }
    if ((_selectedType?.requiresAttachment ?? false) && _attachment == null) {
      _showMsg('ប្រភេទច្បាប់នេះតម្រូវឱ្យភ្ជាប់ឯកសារ', isError: true);
      return;
    }

    setState(() => _submitting = true);
    try {
      final message = await widget.leaveService.submitRequest(
        user: widget.user,
        leaveTypeId: typeId,
        handoverEmployeeId: _selectedHandoverEmployeeId!,
        startDate: _startDate!,
        endDate: _endDate!,
        reason: reason,
        note: note.isEmpty ? null : note,
        attachmentPath: _attachment?.path,
        attachmentBytes: _attachment?.bytes,
        attachmentName: _attachment?.name,
      );
      if (!mounted) return;
      _showMsg(message);
      Navigator.of(context).pop(true);
      return;
    } catch (e) {
      if (mounted) _showMsg(extractApiErrorMessage(e), isError: true);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _showMsg(String msg, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg),
        backgroundColor:
            isError ? const Color(0xFFEF4444) : const Color(0xFF10B981),
      ),
    );
  }

  // ── Build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
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
          'ស្នើសុំច្បាប់ថ្មី',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
        ),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Container(height: 1, color: const Color(0xFFF0F0F0)),
        ),
      ),
      body:
          widget.types.isEmpty
              ? _buildNoTypes()
              : Form(
                key: _formKey,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 120),
                  children: <Widget>[
                    // Leave type selector
                    _SectionCard(
                      title: 'ប្រភេទច្បាប់',
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          _DropdownField<int>(
                            hint: 'ជ្រើសប្រភេទច្បាប់',
                            value: _selectedTypeId,
                            items:
                                widget.types
                                    .map(
                                      (t) => DropdownMenuItem<int>(
                                        value: t.id,
                                        child: Text(
                                          t.displayName(widget.language),
                                        ),
                                      ),
                                    )
                                    .toList(),
                            onChanged:
                                _submitting
                                    ? null
                                    : (v) =>
                                        setState(() => _selectedTypeId = v),
                          ),
                          if (_selectedBalance != null) ...<Widget>[
                            const SizedBox(height: 10),
                            _BalancePill(balance: _selectedBalance!),
                          ],
                          if (_selectedType != null &&
                              (_selectedType!.requiresAttachment ||
                                  _selectedType!.maxPerRequest >
                                      0)) ...<Widget>[
                            const SizedBox(height: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 8,
                              ),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFFFBEB),
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(
                                  color: const Color(0xFFFDE68A),
                                ),
                              ),
                              child: Text(
                                [
                                  if (_selectedType!.requiresAttachment)
                                    'ត្រូវភ្ជាប់ឯកសារ',
                                  if (_selectedType!.maxPerRequest > 0)
                                    'អតិបរមា ${_selectedType!.maxPerRequest} ថ្ងៃ/សំណើ',
                                ].join('  •  '),
                                style: const TextStyle(
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w600,
                                  color: Color(0xFF92400E),
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),

                    _SectionCard(
                      title: _tr('handover_employee', 'អ្នកជំនួស'),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          _SearchablePickerField(
                            label: _tr(
                              'select_handover_employee',
                              'ជ្រើសរើសអ្នកជំនួស',
                            ),
                            value: _selectedHandoverEmployee?.displayLabel(),
                            hint: _tr(
                              'search_handover_employee',
                              'ស្វែងរកឈ្មោះ ឬ លេខបុគ្គលិក',
                            ),
                            icon: Icons.manage_search_rounded,
                            onTap: _openHandoverPicker,
                          ),
                          const SizedBox(height: 8),
                          Text(
                            _tr(
                              'handover_employee_hint',
                              'សូមជ្រើសរើសអ្នកជំនួស ដើម្បីទទួលការងារជំនួសក្នុងអំឡុងពេលឈប់សម្រាក។',
                            ),
                            style: const TextStyle(
                              fontSize: 12,
                              color: Color(0xFF64748B),
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Date range
                    _SectionCard(
                      title: 'រយៈពេលច្បាប់',
                      child: Column(
                        children: <Widget>[
                          Row(
                            children: <Widget>[
                              Expanded(
                                child: _DatePickerTile(
                                  label: 'ថ្ងៃចាប់ផ្តើម',
                                  value: _fmt(_startDate),
                                  hasValue: _startDate != null,
                                  onTap: _submitting ? null : _pickStart,
                                ),
                              ),
                              Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                ),
                                child: Icon(
                                  Icons.arrow_forward_rounded,
                                  size: 18,
                                  color: Colors.grey[400],
                                ),
                              ),
                              Expanded(
                                child: _DatePickerTile(
                                  label: 'ថ្ងៃបញ្ចប់',
                                  value: _fmt(_endDate),
                                  hasValue: _endDate != null,
                                  onTap: _submitting ? null : _pickEnd,
                                ),
                              ),
                            ],
                          ),
                          if (_startDate != null &&
                              _endDate != null) ...<Widget>[
                            const SizedBox(height: 10),
                            _DayCountBanner(
                              days: _dayCount,
                              exceeds: _showsBalanceWarning,
                              remaining: _selectedBalance?.remaining,
                              perRequestLimit: _selectedType?.maxPerRequest,
                              exceedsPerRequestLimit: _showsPerRequestWarning,
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Reason
                    _SectionCard(
                      title: 'មូលហេតុ',
                      child: TextFormField(
                        controller: _reasonController,
                        enabled: !_submitting,
                        minLines: 3,
                        maxLines: 5,
                        validator:
                            (v) =>
                                (v == null || v.trim().isEmpty)
                                    ? 'សូមបញ្ចូលមូលហេតុ'
                                    : null,
                        decoration: _inputDeco(
                          hint: 'បញ្ចូលមូលហេតុ / ការពន្យល់ ...',
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Attachment
                    _SectionCard(
                      title:
                          (_selectedType?.requiresAttachment ?? false)
                              ? 'ឯកសារភ្ជាប់ *'
                              : 'ឯកសារភ្ជាប់ (ស្រេចចិត្ត)',
                      child: _AttachmentPicker(
                        file: _attachment,
                        disabled: _submitting,
                        onPick: _pickAttachment,
                        onClear: () => setState(() => _attachment = null),
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Note
                    _SectionCard(
                      title: 'កំណត់សម្គាល់ (ឯកលក្ខណ៍)',
                      child: TextFormField(
                        controller: _noteController,
                        enabled: !_submitting,
                        minLines: 2,
                        maxLines: 4,
                        decoration: _inputDeco(hint: 'កំណត់ចំណាំបន្ថែម ...'),
                      ),
                    ),
                  ],
                ),
              ),

      // ── Action bar ──────────────────────────────────────────────────────
      bottomNavigationBar:
          widget.types.isEmpty
              ? null
              : Container(
                padding: EdgeInsets.fromLTRB(
                  16,
                  12,
                  16,
                  12 + MediaQuery.of(context).padding.bottom,
                ),
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
                child: Row(
                  children: <Widget>[
                    Expanded(
                      child: SizedBox(
                        height: 50,
                        child: ElevatedButton.icon(
                          onPressed: _submitting ? null : _submit,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF0B6B58),
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(13),
                            ),
                            elevation: 0,
                          ),
                          icon:
                              _submitting
                                  ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: Colors.white,
                                    ),
                                  )
                                  : const Icon(Icons.send_rounded, size: 18),
                          label: Text(
                            _submitting ? 'កំពុងដាក់...' : 'ដាក់សំណើ',
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
    );
  }

  Widget _buildNoTypes() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(Icons.event_busy_outlined, size: 56, color: Colors.grey[300]),
          const SizedBox(height: 12),
          Text('មិនមានប្រភេទច្បាប់', style: TextStyle(color: Colors.grey[500])),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _reasonController.dispose();
    _noteController.dispose();
    super.dispose();
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Sub-widgets
// ─────────────────────────────────────────────────────────────────────────────

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.child});

  final String title;
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
          Text(
            title,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: Color(0xFF64748B),
              letterSpacing: 0.3,
            ),
          ),
          const SizedBox(height: 10),
          child,
        ],
      ),
    );
  }
}

class _DropdownField<T> extends StatelessWidget {
  const _DropdownField({
    required this.hint,
    required this.value,
    required this.items,
    required this.onChanged,
  });

  final String hint;
  final T? value;
  final List<DropdownMenuItem<T>> items;
  final ValueChanged<T?>? onChanged;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<T>(
      initialValue: value,
      items: items,
      onChanged: onChanged,
      decoration: InputDecoration(
        hintText: hint,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 14,
          vertical: 12,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: Color(0xFF0B6B58), width: 1.5),
        ),
        filled: true,
        fillColor: const Color(0xFFF8FAFC),
      ),
    );
  }
}

class _SearchablePickerField extends StatelessWidget {
  const _SearchablePickerField({
    required this.label,
    required this.hint,
    required this.onTap,
    this.value,
    this.icon = Icons.search_rounded,
  });

  final String label;
  final String hint;
  final String? value;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final hasValue = value != null && value!.trim().isNotEmpty;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Ink(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Row(
          children: <Widget>[
            Icon(icon, size: 20, color: const Color(0xFF0B6B58)),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    label,
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF64748B),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    hasValue ? value!.trim() : hint,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: hasValue ? FontWeight.w600 : FontWeight.w400,
                      color: hasValue
                          ? const Color(0xFF0F172A)
                          : const Color(0xFF94A3B8),
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            const Icon(
              Icons.keyboard_arrow_down_rounded,
              color: Color(0xFF64748B),
            ),
          ],
        ),
      ),
    );
  }
}

class _HandoverEmployeePickerSheet extends StatefulWidget {
  const _HandoverEmployeePickerSheet({
    required this.title,
    required this.searchHint,
    required this.employees,
    required this.selectedId,
  });

  final String title;
  final String searchHint;
  final List<HandoverEmployeeOption> employees;
  final int? selectedId;

  @override
  State<_HandoverEmployeePickerSheet> createState() =>
      _HandoverEmployeePickerSheetState();
}

class _HandoverEmployeePickerSheetState
    extends State<_HandoverEmployeePickerSheet> {
  final TextEditingController _searchController = TextEditingController();
  String _query = '';

  List<HandoverEmployeeOption> get _filteredEmployees {
    final query = _query.trim().toLowerCase();
    if (query.isEmpty) {
      return widget.employees;
    }

    return widget.employees.where((employee) {
      return employee.searchText.contains(query);
    }).toList();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final employees = _filteredEmployees;
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Container(
      height: MediaQuery.of(context).size.height * 0.82,
      padding: EdgeInsets.fromLTRB(16, 12, 16, 16 + bottomInset),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        children: <Widget>[
          Container(
            width: 44,
            height: 5,
            decoration: BoxDecoration(
              color: const Color(0xFFE2E8F0),
              borderRadius: BorderRadius.circular(999),
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  widget.title,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF0F172A),
                  ),
                ),
              ),
              IconButton(
                onPressed: () => Navigator.of(context).pop(),
                icon: const Icon(Icons.close_rounded),
              ),
            ],
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _searchController,
            autofocus: true,
            onChanged: (value) => setState(() => _query = value),
            decoration: InputDecoration(
              hintText: widget.searchHint,
              prefixIcon: const Icon(Icons.search_rounded),
              suffixIcon: _query.trim().isEmpty
                  ? null
                  : IconButton(
                      onPressed: () {
                        _searchController.clear();
                        setState(() => _query = '');
                      },
                      icon: const Icon(Icons.close_rounded),
                    ),
              filled: true,
              fillColor: const Color(0xFFF8FAFC),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: Color(0xFF0B6B58), width: 1.5),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Expanded(
            child: employees.isEmpty
                ? const Center(
                    child: Text(
                      'មិនមានឈ្មោះត្រូវនឹងការស្វែងរកទេ',
                      style: TextStyle(color: Color(0xFF64748B)),
                    ),
                  )
                : ListView.separated(
                    itemCount: employees.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 8),
                    itemBuilder: (context, index) {
                      final employee = employees[index];
                      final isSelected = employee.id == widget.selectedId;

                      return Material(
                        color: isSelected
                            ? const Color(0xFFE6F6F2)
                            : const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(14),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: () => Navigator.of(context).pop(employee.id),
                          child: Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                color: isSelected
                                    ? const Color(0xFF0B6B58)
                                    : const Color(0xFFE2E8F0),
                              ),
                            ),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Container(
                                  width: 38,
                                  height: 38,
                                  decoration: BoxDecoration(
                                    color: const Color(0xFF0B6B58).withValues(alpha: 0.10),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: const Icon(
                                    Icons.person_outline_rounded,
                                    color: Color(0xFF0B6B58),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: <Widget>[
                                      Text(
                                        employee.fullName.trim().isNotEmpty
                                            ? employee.fullName.trim()
                                            : employee.fullNameLatin.trim(),
                                        style: const TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w700,
                                          color: Color(0xFF0F172A),
                                        ),
                                      ),
                                      if (employee.fullNameLatin.trim().isNotEmpty &&
                                          employee.fullNameLatin.trim() != employee.fullName.trim()) ...<Widget>[
                                        const SizedBox(height: 2),
                                        Text(
                                          employee.fullNameLatin.trim(),
                                          style: const TextStyle(
                                            fontSize: 12,
                                            color: Color(0xFF64748B),
                                          ),
                                        ),
                                      ],
                                      if (employee.employeeNo.trim().isNotEmpty) ...<Widget>[
                                        const SizedBox(height: 4),
                                        Text(
                                          'លេខបុគ្គលិក៖ ${employee.employeeNo.trim()}',
                                          style: const TextStyle(
                                            fontSize: 12,
                                            color: Color(0xFF0B6B58),
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                ),
                                if (isSelected)
                                  const Padding(
                                    padding: EdgeInsets.only(left: 8),
                                    child: Icon(
                                      Icons.check_circle_rounded,
                                      color: Color(0xFF0B6B58),
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}

class _BalancePill extends StatelessWidget {
  const _BalancePill({required this.balance});

  final LeaveBalanceItem balance;

  @override
  Widget build(BuildContext context) {
    final rem = balance.remaining;
    final color = rem > 0 ? const Color(0xFF10B981) : const Color(0xFFEF4444);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Row(
        children: <Widget>[
          Icon(Icons.info_outline_rounded, size: 14, color: color),
          const SizedBox(width: 6),
          Text(
            'នៅសល់ $rem ថ្ងៃ  (ប្រើប្រាស់ ${balance.used}/${balance.entitlement})',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: color,
            ),
          ),
        ],
      ),
    );
  }
}

class _DatePickerTile extends StatelessWidget {
  const _DatePickerTile({
    required this.label,
    required this.value,
    required this.hasValue,
    required this.onTap,
  });

  final String label;
  final String value;
  final bool hasValue;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        decoration: BoxDecoration(
          color: const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color:
                hasValue
                    ? const Color(0xFF0B6B58).withValues(alpha: 0.5)
                    : const Color(0xFFE2E8F0),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              label,
              style: const TextStyle(
                fontSize: 10,
                color: Color(0xFF94A3B8),
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 3),
            Row(
              children: <Widget>[
                Icon(
                  Icons.calendar_today_outlined,
                  size: 13,
                  color:
                      hasValue
                          ? const Color(0xFF0B6B58)
                          : const Color(0xFF94A3B8),
                ),
                const SizedBox(width: 5),
                Expanded(
                  child: Text(
                    value,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color:
                          hasValue
                              ? const Color(0xFF0F172A)
                              : const Color(0xFFCBD5E1),
                    ),
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

class _DayCountBanner extends StatelessWidget {
  const _DayCountBanner({
    required this.days,
    required this.exceeds,
    required this.remaining,
    required this.perRequestLimit,
    required this.exceedsPerRequestLimit,
  });

  final int days;
  final bool exceeds;
  final int? remaining;
  final double? perRequestLimit;
  final bool exceedsPerRequestLimit;

  @override
  Widget build(BuildContext context) {
    final hasPolicyWarning = exceedsPerRequestLimit;
    final color =
        (exceeds || hasPolicyWarning)
            ? const Color(0xFFEF4444)
            : const Color(0xFF0B6B58);
    final bg = color.withValues(alpha: 0.07);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Row(
        children: <Widget>[
          Icon(
            (exceeds || hasPolicyWarning)
                ? Icons.warning_amber_rounded
                : Icons.check_circle_outline_rounded,
            size: 18,
            color: color,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'សរុប $days ថ្ងៃ',
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    color: color,
                    fontSize: 14,
                  ),
                ),
                if (exceeds && remaining != null)
                  Text(
                    'លើសសិទ្ធិដែលនៅសល់។ នៅសល់តែ $remaining ថ្ងៃ',
                    style: TextStyle(
                      fontSize: 11,
                      color: color,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                if (hasPolicyWarning && perRequestLimit != null)
                  Text(
                    'លើសកំណត់ក្នុងមួយសំណើ ($perRequestLimit ថ្ងៃ)',
                    style: TextStyle(
                      fontSize: 11,
                      color: color,
                      fontWeight: FontWeight.w600,
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

class _AttachmentPicker extends StatelessWidget {
  const _AttachmentPicker({
    required this.file,
    required this.disabled,
    required this.onPick,
    required this.onClear,
  });

  final PlatformFile? file;
  final bool disabled;
  final VoidCallback onPick;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    if (file != null) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: const Color(0xFFECFDF5),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: const Color(0xFF10B981).withValues(alpha: 0.3),
          ),
        ),
        child: Row(
          children: <Widget>[
            const Icon(
              Icons.attach_file_rounded,
              size: 18,
              color: Color(0xFF10B981),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                file!.name,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF0F172A),
                ),
                overflow: TextOverflow.ellipsis,
              ),
            ),
            GestureDetector(
              onTap: disabled ? null : onClear,
              child: const Icon(
                Icons.close_rounded,
                size: 18,
                color: Color(0xFF9CA3AF),
              ),
            ),
          ],
        ),
      );
    }

    return GestureDetector(
      onTap: disabled ? null : onPick,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: const Color(0xFFE2E8F0),
            style: BorderStyle.solid,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(Icons.upload_file_outlined, size: 20, color: Colors.grey[400]),
            const SizedBox(width: 8),
            Text(
              'ជ្រើសឯកសារ',
              style: TextStyle(
                color: Colors.grey[500],
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

InputDecoration _inputDeco({required String hint}) {
  return InputDecoration(
    hintText: hint,
    hintStyle: const TextStyle(color: Color(0xFFCBD5E1), fontSize: 13),
    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(10),
      borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(10),
      borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(10),
      borderSide: const BorderSide(color: Color(0xFF0B6B58), width: 1.5),
    ),
    filled: true,
    fillColor: const Color(0xFFF8FAFC),
  );
}
