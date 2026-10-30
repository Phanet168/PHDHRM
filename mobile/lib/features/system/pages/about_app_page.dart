import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../support_contact.dart';
import 'legal_document_page.dart';
import 'system_widgets.dart';

/// About the app ("អំពីកម្មវិធី"): identity and version, what it does, who
/// develops it and how to reach support, the privacy policy and the terms
/// of use.
class AboutAppPage extends StatefulWidget {
  const AboutAppPage({super.key});

  @override
  State<AboutAppPage> createState() => _AboutAppPageState();
}

class _AboutAppPageState extends State<AboutAppPage> {
  late final Future<PackageInfo> _packageInfoFuture;

  static const _features = <(IconData, String, Color)>[
    (Icons.qr_code_scanner_rounded, 'វត្តមាន QR / GPS', Color(0xFF2563EB)),
    (Icons.event_available_rounded, 'ការសុំច្បាប់', Color(0xFF10B981)),
    (Icons.luggage_rounded, 'បេសកកម្ម', Color(0xFF8B5CF6)),
    (Icons.description_rounded, 'លិខិតផ្លូវការ', Color(0xFFF59E0B)),
    (Icons.notifications_rounded, 'ជូនដំណឹង', Color(0xFFEC4899)),
    (Icons.cloud_off_rounded, 'ប្រើក្រៅបណ្ដាញ', Color(0xFF0EA5E9)),
  ];

  @override
  void initState() {
    super.initState();
    _packageInfoFuture = PackageInfo.fromPlatform();
  }

  void _openDocument(LegalDocument document) =>
      Navigator.of(context).push<void>(
        MaterialPageRoute<void>(
          builder: (_) => LegalDocumentPage(document: document),
        ),
      );

  @override
  Widget build(BuildContext context) {
    final accent = systemAccent();
    return SystemPageScaffold(
      title: 'អំពីកម្មវិធី',
      children: [
        SystemHero(
          child: Column(
            children: [
              const SizedBox(height: 6),
              Container(
                width: 84,
                height: 84,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withAlpha(30),
                      blurRadius: 16,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: Image.asset(
                  'assets/images/app_logo.png',
                  fit: BoxFit.contain,
                  errorBuilder:
                      (_, __, ___) => Center(
                        child: Text(
                          'PHD',
                          style: TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                            color: accent,
                          ),
                        ),
                      ),
                ),
              ),
              const SizedBox(height: 14),
              const Text(
                'PHD HRM',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 24,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                'ប្រព័ន្ធគ្រប់គ្រងធនធានមនុស្ស',
                style: TextStyle(
                  color: Colors.white.withAlpha(230),
                  fontSize: 13.5,
                ),
              ),
              const SizedBox(height: 12),
              FutureBuilder<PackageInfo>(
                future: _packageInfoFuture,
                builder: (context, snapshot) {
                  final info = snapshot.data;
                  return Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white.withAlpha(40),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      info == null
                          ? 'កំណែ ...'
                          : 'កំណែ ${info.version} (${info.buildNumber})',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  );
                },
              ),
              const SizedBox(height: 6),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'កម្មវិធីសម្រាប់មន្ត្រីសុខាភិបាល ដើម្បីគ្រប់គ្រងវត្តមាន ច្បាប់ '
                'បេសកកម្ម និងការងាររដ្ឋបាលប្រចាំថ្ងៃ។',
                style: TextStyle(
                  fontSize: 13.5,
                  height: 1.6,
                  color: Color(0xFF334155),
                ),
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final (icon, label, color) in _features)
                    Container(
                      padding: const EdgeInsets.fromLTRB(8, 6, 12, 6),
                      decoration: BoxDecoration(
                        color: color.withAlpha(20),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(icon, size: 16, color: color),
                          const SizedBox(width: 5),
                          Flexible(
                            child: Text(
                              label,
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                                color: color,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        SettingsGroup(
          title: 'អភិវឌ្ឍ និងគ្រប់គ្រង',
          children: [
            SettingsTile(
              icon: Icons.local_hospital_rounded,
              color: const Color(0xFF0B6B58),
              title: 'មន្ទីរសុខាភិបាលខេត្តស្ទឹងត្រែង',
              subtitle: 'ម្ចាស់ និងអ្នកគ្រប់គ្រងប្រព័ន្ធ',
              trailing: const SizedBox.shrink(),
              onTap: () {},
            ),
            SettingsTile(
              icon: Icons.support_agent_rounded,
              color: const Color(0xFF16A34A),
              title: SupportContact.name,
              subtitle:
                  '${SupportContact.role}\n'
                  '${SupportContact.phoneDisplay} · ${SupportContact.email}',
              trailing: const Icon(
                Icons.call_rounded,
                color: Color(0xFF16A34A),
              ),
              onTap: () => SupportContact.call(context),
            ),
          ],
        ),
        const SizedBox(height: 18),
        SettingsGroup(
          title: 'ឯកសារផ្លូវការ',
          children: [
            SettingsTile(
              icon: Icons.privacy_tip_rounded,
              color: const Color(0xFF2563EB),
              title: LegalDocument.privacy.title,
              subtitle: 'របៀបដែលយើងប្រើ និងការពារព័ត៌មាន',
              onTap: () => _openDocument(LegalDocument.privacy),
            ),
            SettingsTile(
              icon: Icons.gavel_rounded,
              color: const Color(0xFFF59E0B),
              title: LegalDocument.terms.title,
              subtitle: 'ច្បាប់សម្រាប់ការប្រើប្រាស់កម្មវិធី',
              onTap: () => _openDocument(LegalDocument.terms),
            ),
          ],
        ),
        const SizedBox(height: 18),
        const Center(
          child: Text(
            '© ២០២៦ មន្ទីរសុខាភិបាលខេត្តស្ទឹងត្រែង',
            style: TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
          ),
        ),
      ],
    );
  }
}
