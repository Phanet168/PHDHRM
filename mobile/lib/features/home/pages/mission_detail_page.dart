import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_design_system.dart';
import '../models/mission_detail.dart';
import '../services/home_mission_service.dart';
import 'home/home_theme.dart';
import 'mission_report_form_page.dart';
import '../../../core/network/api_exception.dart';

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

  Future<void> _report(MissionDetail detail) async {
    // A lingering snackbar would follow onto the form and cover its button.
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    final updated = await Navigator.of(context).push<MissionDetail>(
      MaterialPageRoute(
        builder:
            (_) => MissionReportFormPage(detail: detail, service: _service),
      ),
    );
    if (updated != null && mounted) {
      setState(() {
        _future = Future.value(updated);
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('បានដាក់ស្នើរបាយការណ៍ដោយជោគជ័យ។')),
      );
    }
  }

  /// One submitted report, by section (៤ / ៥ / ៦) like the printed form.
  Widget _reportCard(MissionReportEntry report, MissionDetail detail) {
    final accent = homeAccentColor();
    Widget section(String title, Widget body) => Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.bold,
              color: accent,
            ),
          ),
          const SizedBox(height: 3),
          body,
        ],
      ),
    );
    const bodyStyle = TextStyle(fontSize: 12, height: 1.55);
    final at = report.submittedAt;

    return _card(
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  report.isMine
                      ? 'របាយការណ៍របស់ខ្ញុំ'
                      : (report.employeeName.isEmpty
                          ? 'របាយការណ៍'
                          : report.employeeName),
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              if (at != null)
                Text(
                  _date(at.toIso8601String()),
                  style: const TextStyle(
                    fontSize: 11,
                    color: AppDesignSystem.textSecondary,
                  ),
                ),
            ],
          ),
          if (!report.isStructured)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(report.summary, style: bodyStyle),
            )
          else ...[
            if (report.activities.isNotEmpty)
              section(
                '៤- ទីកន្លែងនិងសកម្មភាពការងារ',
                Text(report.activities, style: bodyStyle),
              ),
            if (report.results.isNotEmpty)
              section(
                '៥- លទ្ធផលការងារ',
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    for (final line in report.results)
                      Text('• $line', style: bodyStyle),
                  ],
                ),
              ),
            if (report.recommendations.isNotEmpty)
              section(
                '៦- សំណូមពរ និងសេចក្ដីសន្និដ្ឋាន',
                Text(report.recommendations, style: bodyStyle),
              ),
          ],
          if (report.isMine && detail.canViewReport) ...[
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _busy ? null : () => _openLetter(MissionLetter.report),
              style: OutlinedButton.styleFrom(
                foregroundColor: accent,
                side: BorderSide(color: accent),
                minimumSize: const Size(0, 40),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              icon: const Icon(Icons.print_rounded, size: 18),
              label: const Text('បោះពុម្ពរបាយការណ៍'),
            ),
          ],
        ],
      ),
    );
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

  Future<void> _openLetter(MissionLetter letter) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final uri = await _service.letterUrl(widget.missionId, letter);
      if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
        throw Exception('មិនអាចបើកលិខិតបាន។');
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

  static String _km(int value) =>
      value.toString().split('').map((c) => '០១២៣៤៥៦៧៨៩'[int.parse(c)]).join();

  Widget _sectionTitle(String text) => Padding(
    padding: const EdgeInsets.only(top: 14, bottom: 14),
    child: Text(
      text,
      style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
    ),
  );

  Widget _fact(String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // A fixed share (not Flexible) so every value starts in one column.
        Expanded(
          flex: 2,
          child: Text(
            label,
            style: const TextStyle(
              fontSize: 12,
              color: AppDesignSystem.textSecondary,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          flex: 3,
          child: Text(
            value.isEmpty ? '—' : value,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );

  /// The official order (លិខិតបញ្ជាបេសកកម្ម) as the officer will read it,
  /// with links to the printable order and their own travel-visa sheet.
  Widget _orderLetter(MissionDetail detail) {
    final accent = homeAccentColor();
    final order = detail.order;
    final issuedTone = order.issued ? accent : AppDesignSystem.warning;
    // The section heading already says "លិខិតបញ្ជាបេសកកម្ម"; the card leads
    // with the letter's own number and date instead.
    final heading =
        order.numberLabel.isNotEmpty
            ? 'លេខ ${order.numberLabel}'
            : 'មិនទាន់មានលេខលិខិត';
    final subtitle =
        order.issuedOn.isNotEmpty ? 'ចុះ ${_date(order.issuedOn)}' : '';

    return _card(
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  gradient: LinearGradient(
                    colors: [dashboardHeaderStart, dashboardHeaderEnd],
                  ),
                ),
                child: const Icon(
                  Icons.assignment_rounded,
                  color: Colors.white,
                  size: 22,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      heading,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    if (subtitle.isNotEmpty)
                      Text(
                        subtitle,
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
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: issuedTone.withAlpha(22),
                    borderRadius: BorderRadius.circular(99),
                  ),
                  child: Text(
                    order.issued ? 'បានចេញលិខិត' : 'មិនទាន់ចេញ',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: issuedTone,
                    ),
                  ),
                ),
              ),
            ],
          ),
          if (order.references.isNotEmpty) ...[
            const SizedBox(height: 12),
            const Text(
              'យោង',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
            ),
            for (final reference in order.references)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  '• $reference',
                  style: const TextStyle(
                    fontSize: 12,
                    height: 1.5,
                    color: AppDesignSystem.textSecondary,
                  ),
                ),
              ),
          ],
          if (detail.officers.isNotEmpty) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: accent.withAlpha(12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                children: [
                  for (var i = 0; i < detail.officers.length; i++)
                    Padding(
                      padding: EdgeInsets.only(top: i == 0 ? 0 : 8),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${_km(i + 1)}.',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: accent,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  [
                                    detail.officers[i].honorific,
                                    detail.officers[i].name,
                                  ].where((s) => s.isNotEmpty).join(' '),
                                  style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                if (detail.officers[i].position.isNotEmpty)
                                  Text(
                                    detail.officers[i].position,
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
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerRight,
                    child: Text(
                      'សរុប ${_km(detail.officers.length)} នាក់',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: accent,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 6),
          _fact('មូលហេតុ', detail.purpose),
          _fact('ចេញដំណើរ', _date(detail.mission.startDate)),
          _fact('ផុតកំណត់', _date(detail.mission.endDate)),
          _fact('មធ្យោបាយធ្វើដំណើរ', order.transport),
          _fact('ឧបត្ថម្ភថវិកា', order.funding),
          if (order.signatoryTitle.isNotEmpty)
            _fact('អ្នកចុះហត្ថលេខា', order.signatoryTitle),
          const SizedBox(height: 10),
          if (detail.canViewOrder || detail.canViewTravelVisa)
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (detail.canViewOrder)
                  FilledButton.icon(
                    onPressed:
                        _busy ? null : () => _openLetter(MissionLetter.order),
                    style: FilledButton.styleFrom(
                      backgroundColor: accent,
                      minimumSize: const Size(0, 42),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    icon: const Icon(Icons.description_rounded, size: 18),
                    label: const Text('មើលលិខិតបញ្ជា'),
                  ),
                if (detail.canViewTravelVisa)
                  OutlinedButton.icon(
                    onPressed:
                        _busy
                            ? null
                            : () => _openLetter(MissionLetter.travelVisa),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: accent,
                      side: BorderSide(color: accent),
                      minimumSize: const Size(0, 42),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    icon: const Icon(Icons.route_rounded, size: 18),
                    label: const Text('លិខិតចុះទិដ្ឋការរបស់ខ្ញុំ'),
                  ),
              ],
            )
          else
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: AppDesignSystem.warning.withAlpha(18),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.info_outline_rounded,
                    size: 16,
                    color: AppDesignSystem.warning,
                  ),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'អាចមើល និងទាញយកលិខិតបញ្ជា និងលិខិតចុះទិដ្ឋការ បន្ទាប់ពីលិខិតត្រូវបានចេញ។',
                      style: TextStyle(fontSize: 11, height: 1.5),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  /// Request → approval → order → report steps, newest last.
  Widget _timeline(List<MissionTimelineEntry> entries) {
    final accent = homeAccentColor();
    String stamp(DateTime? at) =>
        at == null
            ? ''
            : '${_date(at.toIso8601String())} ${at.hour.toString().padLeft(2, '0')}:${at.minute.toString().padLeft(2, '0')}';

    return _card(
      Column(
        children: [
          for (var i = 0; i < entries.length; i++)
            IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  SizedBox(
                    width: 18,
                    child: Column(
                      children: [
                        Container(
                          width: 12,
                          height: 12,
                          margin: const EdgeInsets.only(top: 3),
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color:
                                i == entries.length - 1
                                    ? accent
                                    : accent.withAlpha(90),
                          ),
                        ),
                        if (i != entries.length - 1)
                          Expanded(
                            child: Container(
                              width: 2,
                              color: accent.withAlpha(40),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Padding(
                      padding: EdgeInsets.only(
                        bottom: i == entries.length - 1 ? 0 : 14,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            entries[i].label,
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Text(
                            [
                              entries[i].actor,
                              stamp(entries[i].at),
                            ].where((s) => s.isNotEmpty).join(' · '),
                            style: const TextStyle(
                              fontSize: 11,
                              color: AppDesignSystem.textSecondary,
                            ),
                          ),
                          if (entries[i].note.isNotEmpty)
                            Text(
                              entries[i].note,
                              style: const TextStyle(fontSize: 11, height: 1.5),
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
      padding: 14,
    );
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
                        detail.lifecycleLabel.isNotEmpty
                            ? detail.lifecycleLabel
                            : missionStatusLabel(mission.status),
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
        _sectionTitle('លិខិតបញ្ជាបេសកកម្ម'),
        _orderLetter(detail),
        _sectionTitle('សមាជិកក្រុម'),
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
                onPressed:
                    !_busy && detail.canReport ? () => _report(detail) : null,
                style: FilledButton.styleFrom(
                  backgroundColor: accent,
                  minimumSize: const Size(0, 46),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: Text(
                  detail.myReport == null ? 'រាយការណ៍' : 'កែរបាយការណ៍',
                ),
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
              child: _reportCard(report, detail),
            ),
        ],
        if (detail.timeline.isNotEmpty) ...[
          const SizedBox(height: 6),
          _sectionTitle('ដំណើរការបេសកកម្ម'),
          _timeline(detail.timeline),
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
                  Text(extractApiErrorMessage(snapshot.error!)),
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
