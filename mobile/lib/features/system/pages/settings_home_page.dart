import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../../core/theme/app_design_system.dart';
import '../../auth/controllers/auth_controller.dart';
import '../../home/pages/home/home_menu.dart';
import 'about_app_page.dart';
import 'change_password_page.dart';
import 'help_support_page.dart';
import 'logout_confirm_dialog.dart';

/// Figma "ការកំណត់ / Before" — the Settings hub: profile row, account
/// (password/notifications), display (language/appearance), help (FAQ/about)
/// and logout. Rows without a real destination yet show a "coming soon"
/// notice rather than pretending to work — matches the same pattern already
/// used for unfinished drawer items elsewhere in this app.
class SettingsHomePage extends StatefulWidget {
  const SettingsHomePage({super.key, required this.authController});

  final AuthController authController;

  @override
  State<SettingsHomePage> createState() => _SettingsHomePageState();
}

class _SettingsHomePageState extends State<SettingsHomePage> {
  late final Future<String> _versionFuture;

  @override
  void initState() {
    super.initState();
    _versionFuture = PackageInfo.fromPlatform().then((info) => info.version);
  }

  void _comingSoon(String label) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text('$label កំពុងអភិវឌ្ឍ')));
  }

  Future<void> _confirmLogout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (confirmed == true && mounted) {
      await widget.authController.logout();
    }
  }

  String _userInitial() {
    final name = widget.authController.currentUser?.name.trim() ?? '';
    return name.isEmpty ? 'U' : name.substring(0, 1).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.authController.currentUser;
    final name = (user?.name.trim().isNotEmpty ?? false) ? user!.name : '-';
    final role =
        (user?.positionKm?.trim().isNotEmpty ?? false)
            ? user!.positionKm!
            : (user?.role?.trim().isNotEmpty ?? false)
            ? user!.role!
            : '-';
    final accent = AppDesignSystem.colorForWeekday(DateTime.now().weekday);

    return Scaffold(
      backgroundColor: const Color(0xFFF5F7FA),
      body: SafeArea(
        child: Column(
          children: [
            Container(
              height: 72,
              padding: const EdgeInsets.fromLTRB(16, 20, 16, 12),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(bottom: BorderSide(color: Color(0xFFE2E8E6))),
              ),
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
                      'ការកំណត់',
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
                  Material(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    child: InkWell(
                      onTap:
                          () => Navigator.of(context).pop(HomeMenuItem.profile),
                      borderRadius: BorderRadius.circular(16),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Row(
                          children: [
                            CircleAvatar(
                              radius: 26,
                              backgroundColor: accent.withValues(alpha: 0.15),
                              child: Text(
                                _userInitial(),
                                style: TextStyle(
                                  color: accent,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 20,
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    name,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      fontSize: 16,
                                      fontWeight: FontWeight.bold,
                                      color: Color(0xFF17201E),
                                    ),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    role,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      fontSize: 11,
                                      color: Color(0xFF66736F),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const Icon(
                              Icons.chevron_right_rounded,
                              color: Color(0xFF66736F),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  _SettingsSection(
                    title: 'គណនី',
                    rows: [
                      _SettingsRow(
                        icon: Icons.lock_outline,
                        label: 'ប្ដូរពាក្យសម្ងាត់',
                        trailing: const _ChevronTrailing(),
                        onTap:
                            () => Navigator.of(context).push<void>(
                              MaterialPageRoute<void>(
                                builder:
                                    (_) => ChangePasswordPage(
                                      authController: widget.authController,
                                    ),
                              ),
                            ),
                      ),
                      _SettingsRow(
                        icon: Icons.notifications_active_outlined,
                        label: 'ការជូនដំណឹង',
                        trailing: Switch(
                          value: true,
                          activeThumbColor: AppDesignSystem.primary,
                          onChanged: (_) => _comingSoon('ការកំណត់ជូនដំណឹង'),
                        ),
                        onTap: () => _comingSoon('ការកំណត់ជូនដំណឹង'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  _SettingsSection(
                    title: 'ការបង្ហាញ',
                    rows: [
                      _SettingsRow(
                        icon: Icons.language_outlined,
                        label: 'ភាសា',
                        trailing: const _ValueTrailing('ខ្មែរ'),
                        onTap: () => _comingSoon('ការប្ដូរភាសា'),
                      ),
                      _SettingsRow(
                        icon: Icons.palette_outlined,
                        label: 'រូបរាង',
                        trailing: const _ValueTrailing('ពន្លឺ'),
                        onTap: () => _comingSoon('ការប្ដូររូបរាង'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  _SettingsSection(
                    title: 'ជំនួយ',
                    rows: [
                      _SettingsRow(
                        icon: Icons.help_outline,
                        label: 'ជំនួយ & គាំទ្រ',
                        trailing: const _ChevronTrailing(),
                        onTap:
                            () => Navigator.of(context).push<void>(
                              MaterialPageRoute<void>(
                                builder: (_) => const HelpSupportPage(),
                              ),
                            ),
                      ),
                      _SettingsRow(
                        icon: Icons.info_outline,
                        label: 'អំពីកម្មវិធី',
                        trailing: const _ChevronTrailing(),
                        onTap:
                            () => Navigator.of(context).push<void>(
                              MaterialPageRoute<void>(
                                builder: (_) => const AboutAppPage(),
                              ),
                            ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  FutureBuilder<String>(
                    future: _versionFuture,
                    builder: (context, snapshot) {
                      final version = snapshot.data ?? '...';
                      return Text(
                        'PHD HRM • កំណែ $version',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 10,
                          color: Color(0xFF66736F),
                        ),
                      );
                    },
                  ),
                  const SizedBox(height: 8),
                  Material(
                    color: Colors.transparent,
                    child: InkWell(
                      onTap: _confirmLogout,
                      borderRadius: BorderRadius.circular(12),
                      child: const Padding(
                        padding: EdgeInsets.symmetric(vertical: 12),
                        child: Column(
                          children: [
                            Icon(
                              Icons.logout_rounded,
                              size: 24,
                              color: Color(0xFFC83B3B),
                            ),
                            SizedBox(height: 8),
                            Text(
                              'ចាកចេញ',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFFC83B3B),
                              ),
                            ),
                          ],
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

class _SettingsSection extends StatelessWidget {
  const _SettingsSection({required this.title, required this.rows});

  final String title;
  final List<_SettingsRow> rows;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.bold,
            color: Color(0xFF66736F),
          ),
        ),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(
            children: [
              for (var i = 0; i < rows.length; i++)
                Container(
                  decoration:
                      i == 0
                          ? null
                          : const BoxDecoration(
                            border: Border(
                              top: BorderSide(color: Color(0xFFE2E8E6)),
                            ),
                          ),
                  child: rows[i],
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _SettingsRow extends StatelessWidget {
  const _SettingsRow({
    required this.icon,
    required this.label,
    required this.trailing,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final Widget trailing;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Icon(icon, size: 22, color: const Color(0xFF17201E)),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  style: const TextStyle(
                    fontSize: 13,
                    color: Color(0xFF17201E),
                  ),
                ),
              ),
              trailing,
            ],
          ),
        ),
      ),
    );
  }
}

class _ChevronTrailing extends StatelessWidget {
  const _ChevronTrailing();

  @override
  Widget build(BuildContext context) {
    return const Icon(
      Icons.chevron_right_rounded,
      size: 18,
      color: Color(0xFF66736F),
    );
  }
}

class _ValueTrailing extends StatelessWidget {
  const _ValueTrailing(this.value);

  final String value;

  @override
  Widget build(BuildContext context) {
    return Text(
      value,
      style: const TextStyle(fontSize: 11, color: Color(0xFF66736F)),
    );
  }
}
