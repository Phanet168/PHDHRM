import 'package:flutter/material.dart';

/// Figma "ជំនួយ & គាំទ្រ" — static FAQ + support contact info page.
class HelpSupportPage extends StatefulWidget {
  const HelpSupportPage({super.key});

  @override
  State<HelpSupportPage> createState() => _HelpSupportPageState();
}

class _HelpSupportPageState extends State<HelpSupportPage> {
  static const _faqs = <(String, String)>[
    (
      'តើធ្វើដូចម្តេចដើម្បីស្កេនវត្តមាន?',
      'ចុចប៊ូតុង "ស្កេន" នៅជើងទំព័រ ឬកាត "ស្កេនកូដ QR" នៅផ្ទាំងគ្រប់គ្រង រួចដាក់កូដ QR របស់អង្គភាពក្នុងស៊ុមដើម្បីកត់ត្រាវត្តមាន។',
    ),
    (
      'តើខ្ញុំអាចសុំច្បាប់បានយ៉ាងដូចម្តេច?',
      'ចូលទៅផ្នែក "ការសុំច្បាប់" ជ្រើសរើសប្រភេទច្បាប់ បំពេញព័ត៌មាន រួចដាក់ស្នើសំណើ។ អ្នកគ្រប់គ្រងនឹងពិនិត្យ និងអនុម័តតាមលំដាប់។',
    ),
    (
      'តើធ្វើដូចម្តេចបើភ្លេចពាក្យសម្ងាត់?',
      'សូមទាក់ទងផ្នែកជំនួយខាងក្រោម ដើម្បីស្នើសុំកំណត់ពាក្យសម្ងាត់ឡើងវិញ។',
    ),
  ];

  final Set<int> _expanded = <int>{};

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F7FA),
      body: SafeArea(
        child: Column(
          children: [
            Container(
              height: 72,
              padding: const EdgeInsets.fromLTRB(16, 20, 16, 12),
              color: Colors.white,
              child: Row(
                children: [
                  InkWell(
                    onTap: () => Navigator.of(context).maybePop(),
                    borderRadius: BorderRadius.circular(11),
                    child: const Padding(
                      padding: EdgeInsets.all(4),
                      child: Icon(
                        Icons.arrow_back_ios_new_rounded,
                        size: 18,
                        color: Color(0xFF17201E),
                      ),
                    ),
                  ),
                  const Expanded(
                    child: Text(
                      'ជំនួយ & គាំទ្រ',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF17201E),
                      ),
                    ),
                  ),
                  const SizedBox(width: 22),
                ],
              ),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  const Text(
                    'សំណួរញឹកញាប់',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF17201E),
                    ),
                  ),
                  const SizedBox(height: 12),
                  for (var i = 0; i < _faqs.length; i++) ...[
                    _FaqTile(
                      question: _faqs[i].$1,
                      answer: _faqs[i].$2,
                      expanded: _expanded.contains(i),
                      onTap: () {
                        setState(() {
                          if (!_expanded.add(i)) {
                            _expanded.remove(i);
                          }
                        });
                      },
                    ),
                    const SizedBox(height: 12),
                  ],
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'ទំនាក់ទំនងគាំទ្រ',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF17201E),
                          ),
                        ),
                        const SizedBox(height: 12),
                        const Row(
                          children: [
                            Icon(
                              Icons.call_outlined,
                              size: 16,
                              color: Color(0xFF17201E),
                            ),
                            SizedBox(width: 8),
                            Text(
                              '០៧៤ ២១០ ២៤៤',
                              style: TextStyle(
                                fontSize: 13,
                                color: Color(0xFF17201E),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        const Row(
                          children: [
                            Icon(
                              Icons.mail_outline,
                              size: 16,
                              color: Color(0xFF17201E),
                            ),
                            SizedBox(width: 8),
                            Flexible(
                              child: Text(
                                'support@stungtreng-phd.gov.kh',
                                style: TextStyle(
                                  fontSize: 13,
                                  color: Color(0xFF17201E),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    height: 50,
                    child: OutlinedButton(
                      onPressed: () {
                        ScaffoldMessenger.of(context)
                          ..hideCurrentSnackBar()
                          ..showSnackBar(
                            const SnackBar(
                              content: Text(
                                'សូមទាក់ទងតាមលេខ ឬអ៊ីមែលខាងលើដើម្បីរាយការណ៍បញ្ហា',
                              ),
                            ),
                          );
                      },
                      style: OutlinedButton.styleFrom(
                        side: const BorderSide(color: Color(0xFF0B6B58)),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text(
                        'រាយការណ៍បញ្ហា',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF0B6B58),
                        ),
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

class _FaqTile extends StatelessWidget {
  const _FaqTile({
    required this.question,
    required this.answer,
    required this.expanded,
    required this.onTap,
  });

  final String question;
  final String answer;
  final bool expanded;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            border: Border.all(color: const Color(0xFFE2E8E6)),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      question,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF17201E),
                      ),
                    ),
                  ),
                  Icon(
                    expanded
                        ? Icons.keyboard_arrow_up_rounded
                        : Icons.keyboard_arrow_down_rounded,
                    size: 20,
                    color: const Color(0xFF0B6B58),
                  ),
                ],
              ),
              if (expanded) ...[
                const SizedBox(height: 10),
                Text(
                  answer,
                  style: const TextStyle(
                    fontSize: 12,
                    height: 1.5,
                    color: Color(0xFF66736F),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
