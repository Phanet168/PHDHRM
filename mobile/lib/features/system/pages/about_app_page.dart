import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

/// Figma "អំពីកម្មវិធី" — app identity, version, developer org, legal links.
class AboutAppPage extends StatefulWidget {
  const AboutAppPage({super.key});

  @override
  State<AboutAppPage> createState() => _AboutAppPageState();
}

class _AboutAppPageState extends State<AboutAppPage> {
  late final Future<PackageInfo> _packageInfoFuture;

  @override
  void initState() {
    super.initState();
    _packageInfoFuture = PackageInfo.fromPlatform();
  }

  void _comingSoon(String label) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text('$label កំពុងអភិវឌ្ឍ')));
  }

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
                      'អំពីកម្មវិធី',
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
                padding: const EdgeInsets.fromLTRB(20, 32, 20, 24),
                children: [
                  Center(
                    child: Container(
                      width: 86,
                      height: 86,
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: const Color(0xFF0B6B58),
                        borderRadius: BorderRadius.circular(22),
                      ),
                      child: Image.asset(
                        'assets/images/app_logo.png',
                        fit: BoxFit.contain,
                        errorBuilder:
                            (_, __, ___) => const Text(
                              'PHD',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                fontSize: 24,
                                fontWeight: FontWeight.bold,
                                color: Colors.white,
                              ),
                            ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  const Text(
                    'PHD HRM',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF17201E),
                    ),
                  ),
                  const SizedBox(height: 6),
                  FutureBuilder<PackageInfo>(
                    future: _packageInfoFuture,
                    builder: (context, snapshot) {
                      final version = snapshot.data?.version ?? '...';
                      return Text(
                        'Version $version',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF66736F),
                        ),
                      );
                    },
                  ),
                  const SizedBox(height: 18),
                  const Text(
                    'ប្រព័ន្ធគ្រប់គ្រងធនធានមនុស្ស សម្រាប់មន្ត្រីសុខាភិបាល ដើម្បីគ្រប់គ្រងវត្តមាន ច្បាប់ និងការងារប្រចាំថ្ងៃ។',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 13,
                      height: 1.5,
                      color: Color(0xFF66736F),
                    ),
                  ),
                  const SizedBox(height: 18),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: const Column(
                      children: [
                        Text(
                          'អភិវឌ្ឍ និងគ្រប់គ្រងដោយ',
                          style: TextStyle(
                            fontSize: 11,
                            color: Color(0xFF66736F),
                          ),
                        ),
                        SizedBox(height: 6),
                        Text(
                          'មន្ទីរសុខាភិបាលខេត្តស្ទឹងត្រែង',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF17201E),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  _LegalLinkRow(
                    label: 'គោលការណ៍ឯកជនភាព',
                    onTap: () => _comingSoon('គោលការណ៍ឯកជនភាព'),
                  ),
                  _LegalLinkRow(
                    label: 'លក្ខខណ្ឌប្រើប្រាស់',
                    onTap: () => _comingSoon('លក្ខខណ្ឌប្រើប្រាស់'),
                  ),
                  _LegalLinkRow(
                    label: 'អាជ្ញាបណ្ណកម្មវិធី',
                    onTap: () => _comingSoon('អាជ្ញាបណ្ណកម្មវិធី'),
                  ),
                  const SizedBox(height: 10),
                  const Center(
                    child: Text(
                      '© ២០២៦ មន្ទីរសុខាភិបាលខេត្តស្ទឹងត្រែង',
                      style: TextStyle(fontSize: 11, color: Color(0xFF66736F)),
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

class _LegalLinkRow extends StatelessWidget {
  const _LegalLinkRow({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              label,
              style: const TextStyle(fontSize: 13, color: Color(0xFF17201E)),
            ),
            const Icon(
              Icons.chevron_right_rounded,
              size: 18,
              color: Color(0xFF0B6B58),
            ),
          ],
        ),
      ),
    );
  }
}
