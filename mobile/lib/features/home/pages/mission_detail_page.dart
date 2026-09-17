import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_design_system.dart';
import '../models/mission_detail.dart';
import '../services/home_mission_service.dart';
import 'home/home_theme.dart';

class MissionDetailPage extends StatefulWidget {
  const MissionDetailPage({super.key, required this.missionId, this.service});
  final int missionId;
  final HomeMissionService? service;

  @override
  State<MissionDetailPage> createState() => _MissionDetailPageState();
}

class _MissionDetailPageState extends State<MissionDetailPage> {
  late final HomeMissionService _service =
      widget.service ?? HomeMissionService();
  late Future<MissionDetail> _future = _service.fetchDetail(widget.missionId);
  bool _busy = false;

  Future<void> _refresh() async {
    final future = _service.fetchDetail(widget.missionId);
    setState(() {
      _future = future;
    });
    try {
      await future;
    } catch (_) {
      /* Displayed by FutureBuilder. */
    }
  }

  Future<void> _act(Future<MissionDetail> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final detail = await action();
      if (!mounted) return;
      setState(() {
        _future = Future.value(detail);
      });
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('បានរក្សាទុកដោយជោគជ័យ។')));
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _report() async {
    final summary = await showDialog<String>(
      context: context,
      builder: (_) => const _ReportDialog(),
    );
    if (summary != null && mounted) {
      await _act(() => _service.report(widget.missionId, summary));
    }
  }

  Future<void> _openDocument(MissionDocument document) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final uri = await _service.documentUrl(widget.missionId, document.id);
      if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
        throw Exception('មិនអាចបើកឯកសារបាន។');
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _asset(String name, double size, {Color? color}) => Image.asset(
    'assets/images/missions/$name.png',
    width: size,
    height: size,
    color: color,
  );

  Widget _card(Widget child, {double padding = 16}) => Container(
    width: double.infinity,
    padding: EdgeInsets.all(padding),
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

  Widget _avatar(
    MissionMember member, {
    Color? background,
    Color? foreground,
  }) => CircleAvatar(
    radius: 15,
    backgroundColor: background ?? homeAccentColor().withAlpha(20),
    child: Text(
      member.initial,
      style: TextStyle(
        fontSize: 11,
        fontWeight: FontWeight.bold,
        color: foreground ?? homeAccentColor(),
      ),
    ),
  );

  String _date(String value) {
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
    return '${date.day} ${months[date.month - 1]} ${date.year}';
  }

  Widget _content(MissionDetail detail) {
    final accent = homeAccentColor();
    final mission = detail.mission;
    final tone =
        ['rejected', 'cancelled'].contains(mission.status)
            ? AppDesignSystem.danger
            : mission.status == 'pending'
            ? AppDesignSystem.warning
            : accent;
    return ListView(
      padding: const EdgeInsets.all(16),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        _card(
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            spacing: 11,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      mission.title,
                      style: const TextStyle(
                        fontSize: 17,
                        height: 1.4,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Flexible(
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 5,
                      ),
                      decoration: BoxDecoration(
                        color: tone.withAlpha(20),
                        borderRadius: BorderRadius.circular(99),
                      ),
                      child: Text(
                        missionStatusLabel(mission.status),
                        style: TextStyle(
                          color: tone,
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _asset('map-pin', 16, color: accent),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      mission.destination,
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppDesignSystem.textSecondary,
                      ),
                    ),
                  ),
                ],
              ),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '${_date(mission.startDate)} — ${_date(mission.endDate)}',
                      style: const TextStyle(fontSize: 13),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    '${detail.durationDays} ថ្ងៃ',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.bold,
                      color: accent,
                    ),
                  ),
                ],
              ),
              const Divider(height: 1, color: AppDesignSystem.border),
              if (detail.assigner case final assigner?)
                Row(
                  children: [
                    _avatar(assigner),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'អ្នកចាត់តាំង',
                            style: TextStyle(
                              fontSize: 11,
                              color: AppDesignSystem.textSecondary,
                            ),
                          ),
                          Text(
                            assigner.name,
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              if (detail.members.isNotEmpty)
                SizedBox(
                  height: 32,
                  child: Stack(
                    children: [
                      for (
                        var i = 0;
                        i < detail.members.length.clamp(0, 4);
                        i++
                      )
                        Positioned(
                          left: i * 22,
                          child: Container(
                            decoration: const BoxDecoration(
                              shape: BoxShape.circle,
                              color: Colors.white,
                            ),
                            padding: const EdgeInsets.all(1),
                            child: _avatar(
                              detail.members[i],
                              background:
                                  [
                                    accent,
                                    const Color(0xFF5B9B7B),
                                    const Color(0xFF8D6EAA),
                                    const Color(0xFFE58C00),
                                  ][i],
                              foreground: Colors.white,
                            ),
                          ),
                        ),
                      if (detail.members.length > 4)
                        Positioned(
                          left: 88,
                          child: CircleAvatar(
                            radius: 15,
                            backgroundColor: AppDesignSystem.border,
                            child: Text(
                              '+${detail.members.length - 4}',
                              style: const TextStyle(fontSize: 11),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              if (detail.missionTypeLabel.isNotEmpty)
                Text(
                  detail.missionTypeLabel,
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppDesignSystem.textSecondary,
                  ),
                ),
              if (detail.orderNumber.isNotEmpty)
                Text(
                  'លិខិតបង្គាប់ការលេខ៖ ${detail.orderNumber}',
                  style: const TextStyle(fontSize: 12),
                ),
              if (detail.purpose.isNotEmpty)
                Text(
                  detail.purpose,
                  style: const TextStyle(
                    fontSize: 12,
                    height: 1.55,
                    color: AppDesignSystem.textSecondary,
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        const Text(
          'សមាជិកក្រុម',
          style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 14),
        _card(
          detail.members.isEmpty
              ? const Text('មិនមានសមាជិកក្រុម')
              : Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  for (final member in detail.members)
                    SizedBox(
                      width: 70,
                      child: Column(
                        children: [
                          _avatar(member),
                          const SizedBox(height: 5),
                          Text(
                            member.name,
                            textAlign: TextAlign.center,
                            style: const TextStyle(fontSize: 10),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
          padding: 12,
        ),
        const SizedBox(height: 14),
        const Text(
          'ឯកសារ',
          style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 14),
        _card(
          detail.documents.isEmpty
              ? const Text(
                'មិនទាន់មានឯកសារ',
                style: TextStyle(
                  color: AppDesignSystem.textSecondary,
                  fontSize: 12,
                ),
              )
              : Column(
                children: [
                  for (final document in detail.documents)
                    TextButton(
                      onPressed: _busy ? null : () => _openDocument(document),
                      style: TextButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        minimumSize: const Size(0, 44),
                      ),
                      child: Row(
                        children: [
                          _asset('file', 18, color: accent),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              document.name,
                              style: const TextStyle(
                                fontSize: 12,
                                color: AppDesignSystem.textPrimary,
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            document.sizeLabel,
                            style: const TextStyle(
                              fontSize: 10,
                              color: AppDesignSystem.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
          padding: 12,
        ),
        const SizedBox(height: 14),
        if (_busy) const LinearProgressIndicator(),
        Row(
          children: [
            Expanded(
              child: OutlinedButton(
                onPressed:
                    !_busy && detail.canStart
                        ? () => _act(() => _service.start(widget.missionId))
                        : null,
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(0, 46),
                  foregroundColor: accent,
                  side: BorderSide(
                    color: detail.canStart ? accent : AppDesignSystem.border,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text('ចាប់ផ្ដើម'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: FilledButton(
                onPressed: !_busy && detail.canReport ? _report : null,
                style: FilledButton.styleFrom(
                  backgroundColor: accent,
                  minimumSize: const Size(0, 46),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text('រាយការណ៍'),
              ),
            ),
          ],
        ),
        if (detail.reports.isNotEmpty) ...[
          const SizedBox(height: 20),
          const Text(
            'របាយការណ៍',
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
          ),
          for (final report in detail.reports)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: _card(Text(report)),
            ),
        ],
      ],
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppDesignSystem.bg,
    appBar: AppBar(
      title: const Text(
        'បេសកកម្ម',
        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
      ),
      foregroundColor: Colors.white,
      leading: IconButton(
        tooltip: 'ត្រឡប់ក្រោយ',
        onPressed: () => Navigator.of(context).pop(),
        icon: _asset('arrow-left', 22),
      ),
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
      child: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<MissionDetail>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(24),
                children: [
                  Text('${snapshot.error}'),
                  const SizedBox(height: 12),
                  OutlinedButton(
                    onPressed: _refresh,
                    child: const Text('ព្យាយាមម្ដងទៀត'),
                  ),
                ],
              );
            }
            return _content(snapshot.requireData);
          },
        ),
      ),
    ),
  );
}

class _ReportDialog extends StatefulWidget {
  const _ReportDialog();
  @override
  State<_ReportDialog> createState() => _ReportDialogState();
}

class _ReportDialogState extends State<_ReportDialog> {
  final _text = TextEditingController();
  final _form = GlobalKey<FormState>();
  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('របាយការណ៍បេសកកម្ម'),
    content: SingleChildScrollView(
      child: Form(
        key: _form,
        child: TextFormField(
          controller: _text,
          minLines: 4,
          maxLines: 8,
          maxLength: 20000,
          decoration: const InputDecoration(
            labelText: 'លទ្ធផល និងសកម្មភាពដែលបានអនុវត្ត',
          ),
          validator:
              (value) =>
                  value == null || value.trim().isEmpty
                      ? 'សូមបញ្ចូលរបាយការណ៍'
                      : null,
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('បោះបង់'),
      ),
      FilledButton(
        onPressed: () {
          if (_form.currentState!.validate()) {
            Navigator.pop(context, _text.text.trim());
          }
        },
        child: const Text('រក្សាទុក'),
      ),
    ],
  );
}
