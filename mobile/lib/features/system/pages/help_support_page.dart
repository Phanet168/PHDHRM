import 'dart:io' show Platform;

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../auth/models/auth_user.dart';
import '../support_contact.dart';
import 'system_widgets.dart';

/// One frequently asked question, with the area it belongs to.
class _Faq {
  const _Faq(this.icon, this.color, this.question, this.answer);

  final IconData icon;
  final Color color;
  final String question;
  final String answer;
}

const _attendance = Color(0xFF2563EB);
const _leave = Color(0xFF10B981);
const _mission = Color(0xFF8B5CF6);
const _account = Color(0xFFF59E0B);

const _faqs = <_Faq>[
  _Faq(
    Icons.qr_code_scanner_rounded,
    _attendance,
    'តើធ្វើដូចម្តេចដើម្បីស្កេនវត្តមាន?',
    'ចុចប៊ូតុង «ស្កេន» នៅកណ្ដាលរបារខាងក្រោម រួចដាក់កូដ QR របស់អង្គភាពក្នុងស៊ុម។ '
        'ប្រសិនបើគ្មាន QR អាចចុះឈ្មោះតាម GPS បាន ពេលអ្នកនៅក្នុងតំបន់អង្គភាព។',
  ),
  _Faq(
    Icons.cloud_off_rounded,
    _attendance,
    'ពេលគ្មានអ៊ីនធឺណិត តើស្កេនវត្តមានបានទេ?',
    'បាន។ ការស្កេនត្រូវបានរក្សាទុកក្នុងទូរសព្ទ ហើយបញ្ជូនទៅម៉ាស៊ីនមេដោយស្វ័យប្រវត្តិ '
        'ពេលមានអ៊ីនធឺណិតវិញ (ក្នុងរយៈពេល ២៤ ម៉ោង)។ ផ្ទាំងដើមបង្ហាញចំនួនស្កេនដែលកំពុងរង់ចាំ។',
  ),
  _Faq(
    Icons.timelapse_rounded,
    _attendance,
    'ហេតុអ្វីថ្ងៃនេះបង្ហាញ «កំពុងបន្ត»?',
    'ថ្ងៃនេះមិនទាន់ចប់ម៉ោងធ្វើការទេ។ ស្ថានភាពចុងក្រោយ (ទាន់ពេល យឺត ឬអវត្តមាន) '
        'នឹងកំណត់បន្ទាប់ពីចប់ម៉ោងធ្វើការ។',
  ),
  _Faq(
    Icons.watch_later_rounded,
    _attendance,
    'មកយឺត និងចេញមុន គិតយ៉ាងដូចម្តេច?',
    'ប្រៀបធៀបម៉ោងស្កេនជាមួយកាលវិភាគវេនរបស់អង្គភាព (រួមទាំងនាទីអនុគ្រោះ)។ '
        'ថ្ងៃច្បាប់ បេសកកម្ម ថ្ងៃបុណ្យ និងថ្ងៃឈប់សម្រាក មិនគិតយឺត ឬអវត្តមានទេ។',
  ),
  _Faq(
    Icons.event_available_rounded,
    _leave,
    'តើខ្ញុំអាចសុំច្បាប់បានយ៉ាងដូចម្តេច?',
    'ចូលទៅ «ការសុំច្បាប់» › «ដាក់សំណើច្បាប់ថ្មី» ជ្រើសប្រភេទច្បាប់ កាលបរិច្ឆេទ មូលហេតុ '
        'រួចដាក់ស្នើ។ អ្នកអនុម័តនឹងពិនិត្យតាមលំដាប់ ហើយអ្នកអាចតាមដាននៅ «ព័ត៌មានសំណើ»។',
  ),
  _Faq(
    Icons.event_busy_rounded,
    _leave,
    'តើបោះបង់សំណើច្បាប់ដោយរបៀបណា?',
    'បើកសំណើដែលកំពុងរង់ចាំ ហើយចុច «បោះបង់សំណើ» នៅខាងក្រោម។ '
        'សំណើដែលបានអនុម័តរួច សូមទាក់ទងអ្នកអនុម័ត ឬផ្នែកបុគ្គលិក។',
  ),
  _Faq(
    Icons.luggage_rounded,
    _mission,
    'តើស្នើសុំបេសកកម្មដោយរបៀបណា?',
    'ចូលទៅផ្ទាំង «ការងារ» › «ស្នើសុំបេសកកម្ម» បំពេញគោលដៅ កាលបរិច្ឆេទ និងសមាជិក។ '
        'សំណើឆ្លងកាត់ប្រធានការិយាល័យ និងប្រធានមន្ទីរ ទើបចេញលិខិតបញ្ជាបេសកកម្ម។',
  ),
  _Faq(
    Icons.assignment_turned_in_rounded,
    _mission,
    'ពេលណាដាក់របាយការណ៍បេសកកម្ម?',
    'ក្រោយចាប់ផ្តើមបេសកកម្ម បើកបេសកកម្មនោះ ហើយចុច «ដាក់របាយការណ៍» '
        'ដើម្បីបំពេញសកម្មភាព លទ្ធផល និងអនុសាសន៍។',
  ),
  _Faq(
    Icons.password_rounded,
    _account,
    'តើធ្វើដូចម្តេចបើភ្លេចពាក្យសម្ងាត់?',
    'សូមទូរសព្ទ ឬផ្ញើអ៊ីមែលទៅក្រុមជំនួយខាងលើ ដើម្បីកំណត់ពាក្យសម្ងាត់ឡើងវិញ។ '
        'ពេលចូលបានហើយ អាចប្ដូរពាក្យសម្ងាត់នៅ «ការកំណត់» › «ប្ដូរពាក្យសម្ងាត់»។',
  ),
  _Faq(
    Icons.palette_rounded,
    _account,
    'តើប្ដូរពណ៌ ឬទំហំអក្សរបានទេ?',
    'បាន។ ចូលទៅ «ការកំណត់» › «រូបរាង» ដើម្បីជ្រើសពណ៌កម្មវិធី និងទំហំអក្សរ។',
  ),
];

/// Help & Support ("ជំនួយ & គាំទ្រ"): the support contact (call, e-mail,
/// copy), searchable answers to common questions, and a problem report that
/// opens an e-mail to support with the app version and device filled in.
class HelpSupportPage extends StatefulWidget {
  const HelpSupportPage({super.key, this.user});

  /// The signed-in officer, named in problem reports.
  final AuthUser? user;

  @override
  State<HelpSupportPage> createState() => _HelpSupportPageState();
}

class _HelpSupportPageState extends State<HelpSupportPage> {
  final Set<int> _expanded = <int>{};
  String _query = '';

  List<(int, _Faq)> get _visibleFaqs {
    final q = _query.trim();
    return [
      for (var i = 0; i < _faqs.length; i++)
        if (q.isEmpty ||
            _faqs[i].question.contains(q) ||
            _faqs[i].answer.contains(q))
          (i, _faqs[i]),
    ];
  }

  Future<void> _reportProblem() async {
    final report = await showModalBottomSheet<(String, String)>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _ReportSheet(),
    );
    if (report == null || !mounted) return;
    final (topic, details) = report;
    final body = await _reportBody(topic, details);
    if (!mounted) return;
    await SupportContact.sendEmail(
      context,
      subject: '[PHD HRM] រាយការណ៍បញ្ហា៖ $topic',
      body: body,
    );
  }

  Future<String> _reportBody(String topic, String details) async {
    var version = '';
    var device = '';
    try {
      final info = await PackageInfo.fromPlatform();
      version = '${info.version}+${info.buildNumber}';
    } catch (_) {}
    try {
      if (Platform.isAndroid) {
        final android = await DeviceInfoPlugin().androidInfo;
        device =
            '${android.manufacturer} ${android.model} '
            '(Android ${android.version.release})';
      } else if (Platform.isIOS) {
        final ios = await DeviceInfoPlugin().iosInfo;
        device = '${ios.utsname.machine} (iOS ${ios.systemVersion})';
      }
    } catch (_) {}
    final user = widget.user;
    return [
      'ប្រភេទបញ្ហា៖ $topic',
      '',
      'ការពិពណ៌នា៖',
      details,
      '',
      '—',
      if (user != null) 'អ្នកប្រើ៖ ${user.name} (${user.email})',
      if (user != null && user.employeeId > 0)
        'លេខសម្គាល់មន្ត្រី៖ ${user.employeeId}',
      if (version.isNotEmpty) 'កំណែកម្មវិធី៖ $version',
      if (device.isNotEmpty) 'ឧបករណ៍៖ $device',
    ].join('\n');
  }

  @override
  Widget build(BuildContext context) {
    final faqs = _visibleFaqs;
    return SystemPageScaffold(
      title: 'ជំនួយ & គាំទ្រ',
      children: [
        SystemHero(
          child: Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: Colors.white.withAlpha(45),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(
                  Icons.support_agent_rounded,
                  color: Colors.white,
                  size: 30,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'តើយើងអាចជួយអ្វីបាន?',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'ទាក់ទងក្រុមជំនួយ ឬស្វែងរកចម្លើយខាងក្រោម',
                      style: TextStyle(
                        color: Colors.white.withAlpha(225),
                        fontSize: 12.5,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        const _ContactCard(),
        const SizedBox(height: 20),
        const Padding(
          padding: EdgeInsets.only(left: 6, bottom: 10),
          child: Text(
            'សំណួរញឹកញាប់',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: Color(0xFF0F172A),
            ),
          ),
        ),
        TextField(
          onChanged: (value) => setState(() => _query = value),
          decoration: InputDecoration(
            hintText: 'ស្វែងរកសំណួរ…',
            prefixIcon: const Icon(Icons.search_rounded),
            filled: true,
            fillColor: Colors.white,
            contentPadding: const EdgeInsets.symmetric(vertical: 12),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide.none,
            ),
          ),
        ),
        const SizedBox(height: 12),
        if (faqs.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 24),
            child: Text(
              'រកមិនឃើញសំណួរដែលត្រូវគ្នា។ សូមទាក់ទងក្រុមជំនួយខាងលើ។',
              textAlign: TextAlign.center,
              style: TextStyle(color: Color(0xFF64748B)),
            ),
          )
        else
          for (final (index, faq) in faqs) ...[
            _FaqTile(
              faq: faq,
              expanded: _expanded.contains(index),
              onTap:
                  () => setState(() {
                    if (!_expanded.add(index)) _expanded.remove(index);
                  }),
            ),
            const SizedBox(height: 10),
          ],
        const SizedBox(height: 10),
        FilledButton.icon(
          onPressed: _reportProblem,
          style: FilledButton.styleFrom(
            backgroundColor: systemAccent(),
            minimumSize: const Size.fromHeight(54),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
            ),
          ),
          icon: const Icon(Icons.bug_report_rounded),
          label: const Text(
            'រាយការណ៍បញ្ហា',
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
          ),
        ),
      ],
    );
  }
}

class _ContactCard extends StatelessWidget {
  const _ContactCard();

  @override
  Widget build(BuildContext context) {
    final accent = systemAccent();
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0F0F172A),
            blurRadius: 18,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 26,
                backgroundColor: accent.withAlpha(30),
                child: Icon(Icons.headset_mic_rounded, color: accent),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'ទំនាក់ទំនងជំនួយ',
                      style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                    ),
                    Text(
                      SupportContact.name,
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    Text(
                      SupportContact.role,
                      style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _ContactLine(
            icon: Icons.call_rounded,
            color: const Color(0xFF16A34A),
            label: 'ទូរសព្ទ',
            value: SupportContact.phoneDisplay,
            onTap: () => SupportContact.call(context),
            onCopy:
                () => SupportContact.copy(
                  context,
                  SupportContact.phone,
                  'លេខទូរសព្ទ',
                ),
          ),
          const SizedBox(height: 8),
          _ContactLine(
            icon: Icons.mail_rounded,
            color: const Color(0xFF2563EB),
            label: 'អ៊ីមែល',
            value: SupportContact.email,
            onTap: () => SupportContact.sendEmail(context),
            onCopy:
                () => SupportContact.copy(
                  context,
                  SupportContact.email,
                  'អ៊ីមែល',
                ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: FilledButton.icon(
                  onPressed: () => SupportContact.call(context),
                  style: FilledButton.styleFrom(
                    backgroundColor: const Color(0xFF16A34A),
                    minimumSize: const Size(0, 48),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  icon: const Icon(Icons.call_rounded, size: 19),
                  label: const FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text('ហៅទូរសព្ទ'),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => SupportContact.sendEmail(context),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: const Color(0xFF2563EB),
                    side: const BorderSide(color: Color(0xFFBFDBFE)),
                    minimumSize: const Size(0, 48),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  icon: const Icon(Icons.mail_rounded, size: 19),
                  label: const FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text('ផ្ញើអ៊ីមែល'),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ContactLine extends StatelessWidget {
  const _ContactLine({
    required this.icon,
    required this.color,
    required this.label,
    required this.value,
    required this.onTap,
    required this.onCopy,
  });

  final IconData icon;
  final Color color;
  final String label;
  final String value;
  final VoidCallback onTap;
  final VoidCallback onCopy;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFFF8FAFC),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        onLongPress: onCopy,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 4, 8),
          child: Row(
            children: [
              Icon(icon, size: 20, color: color),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: const TextStyle(
                        fontSize: 11.5,
                        color: Color(0xFF94A3B8),
                      ),
                    ),
                    Text(
                      value,
                      style: const TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                  ],
                ),
              ),
              IconButton(
                onPressed: onCopy,
                tooltip: 'ចម្លង$label',
                icon: const Icon(
                  Icons.copy_rounded,
                  size: 18,
                  color: Color(0xFF94A3B8),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FaqTile extends StatelessWidget {
  const _FaqTile({
    required this.faq,
    required this.expanded,
    required this.onTap,
  });

  final _Faq faq;
  final bool expanded;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: AnimatedSize(
          duration: const Duration(milliseconds: 200),
          alignment: Alignment.topCenter,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 34,
                      height: 34,
                      decoration: BoxDecoration(
                        color: faq.color.withAlpha(26),
                        borderRadius: BorderRadius.circular(11),
                      ),
                      child: Icon(faq.icon, size: 18, color: faq.color),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        faq.question,
                        style: const TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF0F172A),
                        ),
                      ),
                    ),
                    AnimatedRotation(
                      turns: expanded ? 0.5 : 0,
                      duration: const Duration(milliseconds: 200),
                      child: Icon(
                        Icons.keyboard_arrow_down_rounded,
                        color: faq.color,
                      ),
                    ),
                  ],
                ),
                if (expanded) ...[
                  const SizedBox(height: 10),
                  Text(
                    faq.answer,
                    style: const TextStyle(
                      fontSize: 13,
                      height: 1.6,
                      color: Color(0xFF475569),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Asks what the problem is about and what happened; returns both.
class _ReportSheet extends StatefulWidget {
  const _ReportSheet();

  @override
  State<_ReportSheet> createState() => _ReportSheetState();
}

class _ReportSheetState extends State<_ReportSheet> {
  static const _topics = [
    'វត្តមាន / ស្កេន',
    'ការសុំច្បាប់',
    'បេសកកម្ម',
    'លិខិតផ្លូវការ',
    'គណនី / ចូលប្រើ',
    'ផ្សេងៗ',
  ];

  String _topic = _topics.first;
  final _details = TextEditingController();

  @override
  void dispose() {
    _details.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final accent = systemAccent();
    final ready = _details.text.trim().isNotEmpty;
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
        ),
        padding: EdgeInsets.fromLTRB(
          18,
          10,
          18,
          18 + MediaQuery.paddingOf(context).bottom,
        ),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              Center(
                child: Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              const Text(
                'រាយការណ៍បញ្ហា',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 2),
              const Text(
                'យើងនឹងបើកអ៊ីមែលទៅក្រុមជំនួយ ដោយភ្ជាប់កំណែកម្មវិធី និងឧបករណ៍ដោយស្វ័យប្រវត្តិ។',
                style: TextStyle(fontSize: 12.5, color: Color(0xFF64748B)),
              ),
              const SizedBox(height: 14),
              const Text(
                'បញ្ហាទាក់ទងនឹង',
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final topic in _topics)
                    ChoiceChip(
                      label: Text(topic),
                      selected: _topic == topic,
                      onSelected: (_) => setState(() => _topic = topic),
                      selectedColor: accent.withAlpha(36),
                      labelStyle: TextStyle(
                        fontWeight:
                            _topic == topic ? FontWeight.w800 : FontWeight.w500,
                        color:
                            _topic == topic ? accent : const Color(0xFF475569),
                      ),
                      showCheckmark: false,
                      shape: StadiumBorder(
                        side: BorderSide(
                          color:
                              _topic == topic
                                  ? accent
                                  : const Color(0xFFE2E8F0),
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 14),
              TextField(
                key: const ValueKey('report-details'),
                controller: _details,
                minLines: 4,
                maxLines: 8,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  hintText: 'តើមានបញ្ហាអ្វី? បានធ្វើអ្វីមុនពេលកើតបញ្ហា?',
                  filled: true,
                  fillColor: const Color(0xFFF8FAFC),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(16),
                    borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed:
                    ready
                        ? () => Navigator.of(
                          context,
                        ).pop((_topic, _details.text.trim()))
                        : null,
                style: FilledButton.styleFrom(
                  backgroundColor: accent,
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
                icon: const Icon(Icons.send_rounded),
                label: const Text(
                  'ផ្ញើតាមអ៊ីមែល',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
