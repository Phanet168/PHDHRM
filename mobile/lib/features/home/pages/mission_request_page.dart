import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/theme/app_design_system.dart';
import '../models/mission_detail.dart';
import '../models/mission_request.dart';
import '../services/home_mission_service.dart';
import 'home/home_theme.dart';
import '../../../core/network/api_exception.dart';

/// "ស្នើសុំបេសកកម្ម": the employee's own mission request. The requester is
/// always on the team and may add colleagues of their unit; the request then
/// goes to the office head and the director, as on the web form. Pops with
/// the created [MissionDetail].
class MissionRequestPage extends StatefulWidget {
  const MissionRequestPage({super.key, this.service});

  final HomeMissionService? service;

  @override
  State<MissionRequestPage> createState() => _MissionRequestPageState();
}

class _MissionRequestPageState extends State<MissionRequestPage> {
  late final HomeMissionService _service =
      widget.service ?? HomeMissionService();
  late Future<MissionRequestOptions> _options = _service.fetchRequestOptions();

  final _form = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _destination = TextEditingController();
  final _purpose = TextEditingController();
  final _transportDescription = TextEditingController();
  final _sponsor = TextEditingController();
  DateTime? _start;
  DateTime? _end;
  int? _missionTypeId;
  int? _transportTypeId;
  int? _fundingSourceId;
  final List<MissionColleague> _colleagues = [];
  bool _saving = false;
  bool _datesTouched = false;
  String? _error;

  @override
  void dispose() {
    for (final c in [
      _title,
      _destination,
      _purpose,
      _transportDescription,
      _sponsor,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  static String _km(int value) =>
      value.toString().split('').map((c) => '០១២៣៤៥៦៧៨៩'[int.parse(c)]).join();

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

  String _date(DateTime d) =>
      '${_km(d.day)} ${_months[d.month - 1]} ${_km(d.year)}';

  String? get _dateError {
    if (!_datesTouched) return null;
    if (_start == null || _end == null) {
      return 'សូមជ្រើសរើសថ្ងៃចេញដំណើរ និងថ្ងៃត្រឡប់';
    }
    if (_end!.isBefore(_start!)) {
      return 'ថ្ងៃត្រឡប់ត្រូវនៅក្រោយ ឬស្មើថ្ងៃចេញដំណើរ';
    }
    return null;
  }

  Future<void> _pickDate({required bool start}) async {
    final now = DateTime.now();
    final initial = (start ? _start : _end) ?? _start ?? now;
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 2),
      helpText: start ? 'ថ្ងៃចេញដំណើរ' : 'ថ្ងៃត្រឡប់មកវិញ',
    );
    if (picked == null || !mounted) return;
    setState(() {
      if (start) {
        _start = picked;
        if (_end != null && _end!.isBefore(picked)) _end = picked;
      } else {
        _end = picked;
      }
    });
  }

  Future<void> _addColleague(MissionRequestOptions options) async {
    final picked = await showModalBottomSheet<MissionColleague>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder:
          (_) => _ColleagueSearchSheet(
            service: _service,
            exclude: {options.requester.id, ..._colleagues.map((c) => c.id)},
          ),
    );
    if (picked != null && mounted) setState(() => _colleagues.add(picked));
  }

  Future<void> _submit(MissionRequestOptions options) async {
    setState(() {
      _datesTouched = true;
      _error = null;
    });
    final formOk = _form.currentState!.validate();
    if (!formOk || _dateError != null) return;
    setState(() => _saving = true);
    try {
      final detail = await _service.submitRequest(
        MissionRequestInput(
          title: _title.text,
          destination: _destination.text,
          startDate: _start!,
          endDate: _end!,
          purpose: _purpose.text,
          employeeIds: [options.requester.id, ..._colleagues.map((c) => c.id)],
          missionTypeId: _missionTypeId,
          transportTypeId: _transportTypeId,
          transportDescription: _transportDescription.text,
          fundingSourceId: _fundingSourceId,
          sponsorName: _sponsor.text,
        ),
      );
      if (mounted) Navigator.of(context).pop(detail);
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Widget _card({
    required String title,
    required IconData icon,
    required Widget child,
  }) {
    final accent = homeAccentColor();
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppDesignSystem.surface,
        borderRadius: BorderRadius.circular(AppDesignSystem.radiusCard),
        boxShadow: const [
          BoxShadow(
            color: Color(0x12173D2D),
            blurRadius: 14,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
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
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                  ),
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

  InputDecoration _input(String label, {String? hint}) => InputDecoration(
    labelText: label,
    hintText: hint,
    // maxLength still applies; the "0/255" counter is just noise here.
    counterText: '',
    isDense: true,
    filled: true,
    fillColor: AppDesignSystem.bg,
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppDesignSystem.border),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppDesignSystem.border),
    ),
  );

  Widget _dropdown(
    String label,
    List<MissionOption> options,
    int? value,
    ValueChanged<int?> onChanged,
  ) => DropdownButtonFormField<int>(
    initialValue: value,
    isExpanded: true,
    decoration: _input(label),
    items: [
      const DropdownMenuItem<int>(value: null, child: Text('— មិនជ្រើសរើស —')),
      for (final option in options)
        DropdownMenuItem<int>(
          value: option.id,
          child: Text(option.name, overflow: TextOverflow.ellipsis),
        ),
    ],
    onChanged: onChanged,
  );

  Widget _dateField(String label, DateTime? value, bool start) => InkWell(
    onTap: () => _pickDate(start: start),
    borderRadius: BorderRadius.circular(12),
    child: InputDecorator(
      decoration: _input(label).copyWith(
        suffixIcon: const Icon(Icons.calendar_month_rounded, size: 20),
      ),
      child: Text(
        value == null ? 'ជ្រើសរើស' : _date(value),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          fontSize: 13,
          color:
              value == null
                  ? AppDesignSystem.textSecondary
                  : AppDesignSystem.textPrimary,
        ),
      ),
    ),
  );

  String? _required(String? value) =>
      value == null || value.trim().isEmpty ? 'សូមបំពេញ' : null;

  Widget _content(MissionRequestOptions options) {
    final accent = homeAccentColor();
    final days =
        (_start != null && _end != null && !_end!.isBefore(_start!))
            ? _end!.difference(_start!).inDays + 1
            : null;

    return Form(
      key: _form,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _card(
            title: 'ព័ត៌មានបេសកកម្ម',
            icon: Icons.assignment_rounded,
            child: Column(
              children: [
                if (options.missionTypes.isNotEmpty) ...[
                  _dropdown(
                    'ប្រភេទបេសកកម្ម',
                    options.missionTypes,
                    _missionTypeId,
                    (v) => setState(() => _missionTypeId = v),
                  ),
                  const SizedBox(height: 12),
                ],
                TextFormField(
                  key: const ValueKey('request-title'),
                  controller: _title,
                  maxLength: 255,
                  decoration: _input(
                    'ចំណងជើង *',
                    hint: 'ឧ. ចូលរួមវគ្គបណ្ដុះបណ្ដាល...',
                  ),
                  validator: _required,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  key: const ValueKey('request-destination'),
                  controller: _destination,
                  maxLength: 255,
                  decoration: _input(
                    'ទីតាំងបំពេញបេសកកម្ម *',
                    hint: 'ឧ. សណ្ឋាគារ ខេមរា ខេត្តកំពត',
                  ),
                  validator: _required,
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(child: _dateField('ចេញដំណើរ *', _start, true)),
                    const SizedBox(width: 10),
                    Expanded(child: _dateField('ត្រឡប់មកវិញ *', _end, false)),
                  ],
                ),
                if (_dateError != null || days != null)
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Padding(
                      padding: const EdgeInsets.only(top: 6, left: 4),
                      child: Text(
                        _dateError ?? 'រយៈពេល ${_km(days!)} ថ្ងៃ',
                        style: TextStyle(
                          fontSize: 12,
                          color:
                              _dateError != null
                                  ? AppDesignSystem.danger
                                  : accent,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _purpose,
                  minLines: 3,
                  maxLines: 6,
                  maxLength: 10000,
                  decoration: _input(
                    'គោលបំណង',
                    hint: 'រៀបរាប់អំពីគោលបំណងនៃបេសកកម្ម',
                  ),
                ),
              ],
            ),
          ),
          _card(
            title: 'មន្ត្រីចូលរួម',
            icon: Icons.groups_rounded,
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                Chip(
                  avatar: Icon(Icons.person_rounded, size: 18, color: accent),
                  label: Text('${options.requester.name} (ខ្ញុំ)'),
                  backgroundColor: accent.withAlpha(20),
                  side: BorderSide.none,
                ),
                for (final colleague in _colleagues)
                  InputChip(
                    label: Text(colleague.name),
                    onDeleted:
                        () => setState(() => _colleagues.remove(colleague)),
                    deleteButtonTooltipMessage: 'ដកចេញ',
                  ),
                ActionChip(
                  avatar: Icon(
                    Icons.person_add_alt_1_rounded,
                    size: 18,
                    color: accent,
                  ),
                  label: const Text('បន្ថែមមន្ត្រី'),
                  onPressed: () => _addColleague(options),
                ),
              ],
            ),
          ),
          _card(
            title: 'ការធ្វើដំណើរ និងថវិកា',
            icon: Icons.directions_car_rounded,
            child: Column(
              children: [
                if (options.transportTypes.isNotEmpty) ...[
                  _dropdown(
                    'មធ្យោបាយធ្វើដំណើរ',
                    options.transportTypes,
                    _transportTypeId,
                    (v) => setState(() => _transportTypeId = v),
                  ),
                  const SizedBox(height: 12),
                ],
                TextFormField(
                  controller: _transportDescription,
                  maxLength: 255,
                  decoration: _input(
                    'ព័ត៌មានបន្ថែមពីមធ្យោបាយ',
                    hint: 'ឧ. ស្លាកលេខរថយន្ត',
                  ),
                ),
                const SizedBox(height: 12),
                if (options.fundingSources.isNotEmpty) ...[
                  _dropdown(
                    'ប្រភពថវិកា',
                    options.fundingSources,
                    _fundingSourceId,
                    (v) => setState(() => _fundingSourceId = v),
                  ),
                  const SizedBox(height: 12),
                ],
                TextFormField(
                  controller: _sponsor,
                  maxLength: 255,
                  decoration: _input('ឧបត្ថម្ភថវិកាដោយ', hint: 'ឧ. US-CDC'),
                ),
              ],
            ),
          ),
          _card(
            title: 'លំហូរអនុម័ត',
            icon: Icons.route_rounded,
            child: _ApprovalRoute(steps: options.approvalRoute),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final accent = homeAccentColor();
    return Scaffold(
      backgroundColor: AppDesignSystem.bg,
      appBar: AppBar(
        title: const Text(
          'ស្នើសុំបេសកកម្ម',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
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
      ),
      body: SafeArea(
        child: FutureBuilder<MissionRequestOptions>(
          future: _options,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return ListView(
                padding: const EdgeInsets.all(24),
                children: [
                  Text(extractApiErrorMessage(snapshot.error!)),
                  const SizedBox(height: 12),
                  OutlinedButton(
                    onPressed:
                        () => setState(
                          () => _options = _service.fetchRequestOptions(),
                        ),
                    child: const Text('ព្យាយាមម្ដងទៀត'),
                  ),
                ],
              );
            }
            final options = snapshot.requireData;
            return Column(
              children: [
                Expanded(child: _content(options)),
                Container(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
                  decoration: const BoxDecoration(
                    color: AppDesignSystem.surface,
                    boxShadow: [
                      BoxShadow(
                        color: Color(0x14173D2D),
                        blurRadius: 12,
                        offset: Offset(0, -3),
                      ),
                    ],
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (_error != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Text(
                            _error!,
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppDesignSystem.danger,
                            ),
                          ),
                        ),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton.icon(
                          onPressed: _saving ? null : () => _submit(options),
                          style: FilledButton.styleFrom(
                            backgroundColor: accent,
                            minimumSize: const Size(0, 48),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          icon:
                              _saving
                                  ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: Colors.white,
                                    ),
                                  )
                                  : const Icon(Icons.send_rounded, size: 18),
                          label: const Text('ដាក់ស្នើសំណើ'),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

/// Submit → each approval step (with who holds it) → order issued.
class _ApprovalRoute extends StatelessWidget {
  const _ApprovalRoute({required this.steps});

  final List<MissionApprovalStep> steps;

  @override
  Widget build(BuildContext context) {
    final accent = homeAccentColor();
    final rows = <(String, String)>[
      ('ដាក់សំណើ', 'ពីទូរសព្ទរបស់អ្នក'),
      for (final step in steps)
        (
          step.label,
          step.names.isEmpty
              ? 'មិនទាន់កំណត់អ្នកទទួលបន្ទុក'
              : step.names.join(', '),
        ),
      ('ចេញលិខិតបញ្ជាបេសកកម្ម', 'មន្ត្រីគ្រប់គ្រងលិខិត'),
    ];
    return Column(
      children: [
        for (var i = 0; i < rows.length; i++)
          Padding(
            padding: EdgeInsets.only(bottom: i == rows.length - 1 ? 0 : 10),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 24,
                  height: 24,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: accent,
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    '${i + 1}',
                    style: const TextStyle(
                      fontSize: 11,
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        rows[i].$1,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        rows[i].$2,
                        style: const TextStyle(
                          fontSize: 11,
                          color: AppDesignSystem.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

/// Bottom sheet: search colleagues of the requester's unit and pick one.
class _ColleagueSearchSheet extends StatefulWidget {
  const _ColleagueSearchSheet({required this.service, required this.exclude});

  final HomeMissionService service;
  final Set<int> exclude;

  @override
  State<_ColleagueSearchSheet> createState() => _ColleagueSearchSheetState();
}

class _ColleagueSearchSheetState extends State<_ColleagueSearchSheet> {
  late Future<List<MissionColleague>> _results = widget.service
      .searchColleagues('');
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  void _search(String query) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (mounted) {
        setState(() => _results = widget.service.searchColleagues(query));
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * 0.65,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: TextField(
                autofocus: true,
                onChanged: _search,
                decoration: InputDecoration(
                  hintText: 'ស្វែងរកឈ្មោះ ឬអត្តលេខ',
                  prefixIcon: const Icon(Icons.search_rounded),
                  isDense: true,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ),
            Expanded(
              child: FutureBuilder<List<MissionColleague>>(
                future: _results,
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  if (snapshot.hasError) {
                    return Center(
                      child: Text(extractApiErrorMessage(snapshot.error!)),
                    );
                  }
                  final rows =
                      (snapshot.data ?? const <MissionColleague>[])
                          .where((c) => !widget.exclude.contains(c.id))
                          .toList();
                  if (rows.isEmpty) {
                    return const Center(
                      child: Text('មិនមានមន្ត្រីត្រូវនឹងការស្វែងរក'),
                    );
                  }
                  return ListView.separated(
                    itemCount: rows.length,
                    separatorBuilder: (_, _) => const Divider(height: 1),
                    itemBuilder:
                        (context, i) => ListTile(
                          leading: CircleAvatar(
                            backgroundColor: homeAccentColor().withAlpha(24),
                            child: Text(
                              rows[i].name.isEmpty
                                  ? '?'
                                  : rows[i].name.characters.first,
                              style: TextStyle(
                                color: homeAccentColor(),
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                          title: Text(rows[i].name),
                          subtitle:
                              rows[i].position.isEmpty
                                  ? null
                                  : Text(rows[i].position),
                          onTap: () => Navigator.of(context).pop(rows[i]),
                        ),
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
