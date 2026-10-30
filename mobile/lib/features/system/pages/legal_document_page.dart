import 'package:flutter/material.dart';

import '../support_contact.dart';
import 'system_widgets.dart';

/// One numbered section of a legal document: a heading, paragraphs and
/// bullet points.
class LegalSection {
  const LegalSection(this.title, this.paragraphs, {this.bullets = const []});

  final String title;
  final List<String> paragraphs;
  final List<String> bullets;
}

/// A legal document of the app (privacy policy, terms of use).
class LegalDocument {
  const LegalDocument({
    required this.title,
    required this.icon,
    required this.summary,
    required this.sections,
  });

  final String title;
  final IconData icon;
  final String summary;
  final List<LegalSection> sections;

  static const updated = '៣០ តុលា ២០២៦';

  static const privacy = LegalDocument(
    title: 'គោលការណ៍ឯកជនភាព',
    icon: Icons.privacy_tip_rounded,
    summary:
        'កម្មវិធី PHD HRM ប្រើព័ត៌មានរបស់អ្នក សម្រាប់តែការងាររដ្ឋបាលរបស់មន្ទីរសុខាភិបាលខេត្តស្ទឹងត្រែងប៉ុណ្ណោះ។',
    sections: [
      LegalSection(
        'ព័ត៌មានដែលយើងប្រើ',
        ['ដើម្បីដំណើរការ កម្មវិធីប្រើព័ត៌មានដូចខាងក្រោម៖'],
        bullets: [
          'ព័ត៌មានគណនី និងមន្ត្រី៖ ឈ្មោះ អ៊ីមែល អត្តលេខ មុខតំណែង និងអង្គភាព។',
          'កំណត់ត្រាវត្តមាន៖ ម៉ោងស្កេនចូល/ចេញ និងវិធីស្កេន (QR ឬ GPS)។',
          'ទីតាំង GPS៖ យកតែនៅពេលអ្នកស្កេនវត្តមាន ដើម្បីផ្ទៀងផ្ទាត់ថាអ្នកនៅក្នុងតំបន់អង្គភាព។',
          'ព័ត៌មានឧបករណ៍៖ លេខសម្គាល់ឧបករណ៍ ដើម្បីដឹងស្ថានភាពឧបករណ៍ដែលកំពុងប្រើកម្មវិធី។',
          'សំណើ និងឯកសារ៖ សំណើច្បាប់ បេសកកម្ម របាយការណ៍ និងឯកសារភ្ជាប់ដែលអ្នកដាក់។',
        ],
      ),
      LegalSection('កាមេរ៉ា និងទីតាំង', [
        'កាមេរ៉ាប្រើសម្រាប់អានកូដ QR តែប៉ុណ្ណោះ — កម្មវិធីមិនថត ឬរក្សាទុករូបភាពទេ។',
        'ទីតាំងមិនត្រូវបានតាមដានជាប្រចាំ ឬនៅផ្ទៃខាងក្រោយទេ គឺយកតែពេលស្កេនវត្តមានប៉ុណ្ណោះ។',
      ]),
      LegalSection('គោលបំណងនៃការប្រើប្រាស់', [
        'ព័ត៌មានត្រូវបានប្រើដើម្បីគ្រប់គ្រងវត្តមាន ច្បាប់ បេសកកម្ម លិខិតផ្លូវការ '
            'និងការជូនដំណឹង ស្របតាមបទប្បញ្ញត្តិរបស់មន្ទីរ។',
      ]),
      LegalSection(
        'ការរក្សាទុក',
        ['ទិន្នន័យសំខាន់ត្រូវបានរក្សាទុកនៅម៉ាស៊ីនមេរបស់មន្ទីរ។ នៅលើទូរសព្ទ៖'],
        bullets: [
          'សោចូលប្រើ (token) ត្រូវបានរក្សាក្នុងកន្លែងផ្ទុកសុវត្ថិភាពរបស់ទូរសព្ទ។',
          'ការស្កេនក្រៅបណ្ដាញ និងទិន្នន័យចុងក្រោយ ត្រូវបានរក្សាទុកបណ្ដោះអាសន្ន រហូតដល់បញ្ជូនទៅម៉ាស៊ីនមេ។',
          'ការចាកចេញពីគណនី នឹងលុបសោចូលប្រើ និងព័ត៌មានគណនីចេញពីទូរសព្ទ។',
        ],
      ),
      LegalSection('អ្នកដែលអាចមើលព័ត៌មាន', [
        'មានតែអ្នក និងអ្នកដែលមានតួនាទីពាក់ព័ន្ធក្នុងមន្ទីរ (ប្រធានអង្គភាព អ្នកអនុម័ត '
            'និងផ្នែកបុគ្គលិក) ទើបអាចមើលព័ត៌មានរបស់អ្នក តាមសិទ្ធិដែលបានកំណត់។ '
            'យើងមិនលក់ ឬចែករំលែកព័ត៌មានរបស់អ្នកទៅភាគីទីបីទេ។',
      ]),
      LegalSection('សិទ្ធិរបស់អ្នក', [
        'អ្នកអាចមើលកំណត់ត្រារបស់អ្នកក្នុងកម្មវិធី និងស្នើកែតម្រូវព័ត៌មានដែលមិនត្រឹមត្រូវ '
            'តាមរយៈផ្នែកបុគ្គលិក ឬក្រុមជំនួយ។',
      ]),
    ],
  );

  static const terms = LegalDocument(
    title: 'លក្ខខណ្ឌប្រើប្រាស់',
    icon: Icons.gavel_rounded,
    summary: 'ការប្រើកម្មវិធី PHD HRM មានន័យថាអ្នកយល់ព្រមតាមលក្ខខណ្ឌខាងក្រោម។',
    sections: [
      LegalSection('អ្នកដែលអាចប្រើ', [
        'កម្មវិធីនេះសម្រាប់មន្ត្រីរបស់មន្ទីរសុខាភិបាលខេត្តស្ទឹងត្រែង ដែលមានគណនីផ្ដល់ដោយមន្ទីរប៉ុណ្ណោះ។',
      ]),
      LegalSection('គណនី និងពាក្យសម្ងាត់', [
        'សូមរក្សាពាក្យសម្ងាត់ជាការសម្ងាត់ និងកុំឲ្យអ្នកដទៃប្រើគណនីរបស់អ្នក។ '
            'អ្នកទទួលខុសត្រូវចំពោះសកម្មភាពទាំងអស់ក្រោមគណនីរបស់អ្នក។',
      ]),
      LegalSection(
        'ការស្កេនវត្តមាន',
        ['ដើម្បីឲ្យកំណត់ត្រាវត្តមានត្រឹមត្រូវ៖'],
        bullets: [
          'ស្កេនសម្រាប់ខ្លួនឯងតែប៉ុណ្ណោះ នៅទីកន្លែងធ្វើការពិតប្រាកដ។',
          'ហាមស្កេនជំនួសអ្នកដទៃ ក្លែងទីតាំង ឬកែម៉ោងទូរសព្ទ។',
          'ការស្កេនក្រៅបណ្ដាញ មានសុពលភាពក្នុងរយៈពេល ២៤ ម៉ោង ហើយត្រូវបានផ្ទៀងផ្ទាត់ម៉ោងដោយម៉ាស៊ីនមេ។',
          'ការបំពានអាចត្រូវទទួលវិធានការរដ្ឋបាល តាមបទប្បញ្ញត្តិជាធរមាន។',
        ],
      ),
      LegalSection('សំណើ និងឯកសារ', [
        'ព័ត៌មាន និងឯកសារភ្ជាប់ក្នុងសំណើច្បាប់ បេសកកម្ម និងរបាយការណ៍ ត្រូវតែពិត និងត្រឹមត្រូវ។',
      ]),
      LegalSection('ការផ្លាស់ប្ដូរ', [
        'មន្ទីរអាចកែលម្អកម្មវិធី និងលក្ខខណ្ឌទាំងនេះជាបន្តបន្ទាប់។ '
            'កំណែថ្មីនឹងបង្ហាញនៅទំព័រនេះ។',
      ]),
    ],
  );
}

/// Shows a [LegalDocument]: summary card, numbered sections, and who to
/// contact with questions.
class LegalDocumentPage extends StatelessWidget {
  const LegalDocumentPage({super.key, required this.document});

  final LegalDocument document;

  @override
  Widget build(BuildContext context) {
    final accent = systemAccent();
    return SystemPageScaffold(
      title: document.title,
      children: [
        SystemHero(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      color: Colors.white.withAlpha(45),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(document.icon, color: Colors.white),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      document.title,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                document.summary,
                style: TextStyle(
                  color: Colors.white.withAlpha(235),
                  fontSize: 13,
                  height: 1.55,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                'កែប្រែចុងក្រោយ៖ ${LegalDocument.updated}',
                style: TextStyle(
                  color: Colors.white.withAlpha(200),
                  fontSize: 11.5,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        for (var i = 0; i < document.sections.length; i++) ...[
          _SectionCard(
            number: i + 1,
            section: document.sections[i],
            accent: accent,
          ),
          const SizedBox(height: 10),
        ],
        const SizedBox(height: 4),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: accent.withAlpha(18),
            borderRadius: BorderRadius.circular(18),
          ),
          child: Row(
            children: [
              Icon(Icons.contact_support_rounded, color: accent),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  'មានសំណួរ? ទាក់ទង ${SupportContact.name} '
                  '${SupportContact.phoneDisplay} · ${SupportContact.email}',
                  style: const TextStyle(
                    fontSize: 12.5,
                    color: Color(0xFF334155),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.number,
    required this.section,
    required this.accent,
  });

  final int number;
  final LegalSection section;
  final Color accent;

  static String _km(int value) =>
      value.toString().split('').map((c) => '០១២៣៤៥៦៧៨៩'[int.parse(c)]).join();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 28,
                height: 28,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: accent.withAlpha(26),
                  shape: BoxShape.circle,
                ),
                child: Text(
                  _km(number),
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                    color: accent,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  section.title,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF0F172A),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          for (final paragraph in section.paragraphs)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                paragraph,
                style: const TextStyle(
                  fontSize: 13.5,
                  height: 1.65,
                  color: Color(0xFF334155),
                ),
              ),
            ),
          for (final bullet in section.bullets)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.only(top: 7, right: 8),
                    child: Container(
                      width: 6,
                      height: 6,
                      decoration: BoxDecoration(
                        color: accent,
                        shape: BoxShape.circle,
                      ),
                    ),
                  ),
                  Expanded(
                    child: Text(
                      bullet,
                      style: const TextStyle(
                        fontSize: 13.5,
                        height: 1.65,
                        color: Color(0xFF334155),
                      ),
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
