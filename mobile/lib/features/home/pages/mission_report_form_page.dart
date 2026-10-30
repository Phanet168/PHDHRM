import 'package:flutter/material.dart';

import '../../../core/theme/app_design_system.dart';
import '../models/mission_detail.dart';
import '../services/home_mission_service.dart';
import 'home/home_theme.dart';

/// "របាយការណ៍បេសកកម្ម" form, following the official template. Sections
/// ១–៣ (team, purpose, period) come from the issued order and are shown for
/// reference; the officer fills ៤ activities, ៥ results (one per line) and
/// ៦ recommendations. Pops with the refreshed [MissionDetail] once submitted.
class MissionReportFormPage extends StatefulWidget {
  const MissionReportFormPage({
    super.key,
    required this.detail,
    required this.service,
  });

  final MissionDetail detail;
  final HomeMissionService service;

  @override
  State<MissionReportFormPage> createState() => _MissionReportFormPageState();
}

class _MissionReportFormPageState extends State<MissionReportFormPage> {
  late final MissionReportEntry? _mine = widget.detail.myReport;

  // Reports from older app versions only have free text: start from it.
  late final _activities = TextEditingController(
    text:
        _mine == null
            ? ''
            : _mine.isStructured
            ? _mine.activities
            : _mine.summary,
  );
  late final List<TextEditingController> _results = [
    for (final line in _mine?.results ?? const <String>[])
      TextEditingController(text: line),
    if ((_mine?.results ?? const []).isEmpty) TextEditingController(),
  ];
  late final _recommendations = TextEditingController(
    text: _mine?.recommendations ?? '',
  );
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _activities.dispose();
    for (final controller in _results) {
      controller.dispose();
    }
    _recommendations.dispose();
    super.dispose();
  }

  static String _km(int value) =>
      value.toString().split('').map((c) => '០១២៣៤៥៦៧៨៩'[int.parse(c)]).join();

  static String _date(String value) {
    final date = DateTime.tryParse(value);
    if (date == null) return value;
    const months = [
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
    return '${_km(date.day)} ${months[date.month - 1]} ${_km(date.year)}';
  }

  void _removeResult(int index) {
    final removed = _results[index];
    setState(() => _results.removeAt(index));
    // Its text field unmounts this frame; dispose only after that.
    WidgetsBinding.instance.addPostFrameCallback((_) => removed.dispose());
  }

  Future<void> _submit() async {
    final input = MissionReportInput(
      activities: _activities.text,
      results: [for (final c in _results) c.text],
      recommendations: _recommendations.text,
    );
    if (input.activities.trim().isEmpty &&
        input.results.every((line) => line.trim().isEmpty)) {
      setState(
        () => _error = 'សូមបញ្ចូលលទ្ធផលការងារ ឬសកម្មភាពការងារ យ៉ាងហោចណាស់មួយ',
      );
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final detail = await widget.service.report(
        widget.detail.mission.id,
        input,
      );
      if (mounted) Navigator.of(context).pop(detail);
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Widget _card({required Widget child}) => Container(
    width: double.infinity,
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
    child: child,
  );

  Widget _heading(String number, String title, {String? subtitle}) {
    final accent = homeAccentColor();
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 26,
            height: 26,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: accent.withAlpha(24),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              number,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.bold,
                color: accent,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                if (subtitle != null && subtitle.isNotEmpty)
                  Text(
                    subtitle,
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppDesignSystem.textSecondary,
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  InputDecoration _input(String hint) => InputDecoration(
    hintText: hint,
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

  /// Sections ១–៣ and the order reference, as they will print.
  Widget _context(MissionDetail detail) {
    final order = detail.order;
    final reference = [
      if (order.numberLabel.isNotEmpty) 'លេខ ${order.numberLabel}',
      if (order.issuedOn.isNotEmpty) 'ចុះ ${_date(order.issuedOn)}',
    ].join(' ');
    Widget row(String number, String label, String value) => Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Text.rich(
        TextSpan(
          children: [
            TextSpan(
              text: '$number- $label៖ ',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            TextSpan(text: value.isEmpty ? '—' : value),
          ],
        ),
        style: const TextStyle(fontSize: 12, height: 1.55),
      ),
    );

    return _card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'យោងលិខិតបញ្ជាបេសកកម្ម ${reference.isEmpty ? '—' : reference}',
            style: const TextStyle(
              fontSize: 12,
              color: AppDesignSystem.textSecondary,
            ),
          ),
          row(
            '១',
            'ថ្នាក់ដឹកនាំ',
            detail.officers
                .map(
                  (o) => [
                    o.honorific,
                    o.name,
                  ].where((s) => s.isNotEmpty).join(' '),
                )
                .join(', '),
          ),
          row('២', 'គោលបំណង', detail.purpose),
          row(
            '៣',
            'ពេលវេលារយៈពេល',
            '${_km(detail.durationDays)} ថ្ងៃ '
                '(${_date(detail.mission.startDate)} — ${_date(detail.mission.endDate)})',
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final accent = homeAccentColor();
    final detail = widget.detail;

    return Scaffold(
      backgroundColor: AppDesignSystem.bg,
      appBar: AppBar(
        title: const Text(
          'របាយការណ៍បេសកកម្ម',
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
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  _context(detail),
                  const SizedBox(height: 14),
                  _card(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _heading(
                          '៤',
                          'ទីកន្លែងនិងសកម្មភាពការងារ',
                          subtitle: detail.mission.destination,
                        ),
                        TextField(
                          key: const ValueKey('report-activities'),
                          controller: _activities,
                          minLines: 3,
                          maxLines: 8,
                          maxLength: 20000,
                          decoration: _input('សកម្មភាពដែលបានអនុវត្ត...'),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  _card(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _heading('៥', 'លទ្ធផលការងារ'),
                        for (var i = 0; i < _results.length; i++)
                          Padding(
                            // Keyed by controller so removing a row never
                            // hands another row's text field the wrong state.
                            key: ObjectKey(_results[i]),
                            padding: const EdgeInsets.only(bottom: 8),
                            child: Row(
                              children: [
                                Text(
                                  '•',
                                  style: TextStyle(fontSize: 18, color: accent),
                                ),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: TextField(
                                    key: ValueKey('report-result-$i'),
                                    controller: _results[i],
                                    minLines: 1,
                                    maxLines: 4,
                                    textInputAction: TextInputAction.next,
                                    decoration: _input(
                                      'លទ្ធផលទី ${_km(i + 1)}',
                                    ),
                                  ),
                                ),
                                if (_results.length > 1)
                                  IconButton(
                                    tooltip: 'លុប',
                                    onPressed: () => _removeResult(i),
                                    icon: const Icon(
                                      Icons.remove_circle_outline_rounded,
                                      color: AppDesignSystem.textSecondary,
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        if (_results.length < 50)
                          TextButton.icon(
                            onPressed:
                                () => setState(
                                  () => _results.add(TextEditingController()),
                                ),
                            style: TextButton.styleFrom(
                              foregroundColor: accent,
                            ),
                            icon: const Icon(Icons.add_circle_outline_rounded),
                            label: const Text('បន្ថែមលទ្ធផល'),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  _card(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _heading('៦', 'សំណូមពរ និងសេចក្ដីសន្និដ្ឋាន'),
                        TextField(
                          key: const ValueKey('report-recommendations'),
                          controller: _recommendations,
                          minLines: 3,
                          maxLines: 8,
                          maxLength: 20000,
                          decoration: _input('សំណូមពរ និងសេចក្ដីសន្និដ្ឋាន...'),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
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
                      onPressed: _saving ? null : _submit,
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
                      label: Text(
                        _mine == null
                            ? 'ដាក់ស្នើរបាយការណ៍'
                            : 'រក្សាទុកការកែប្រែ',
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
}
