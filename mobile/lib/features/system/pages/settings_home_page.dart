import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../../core/theme/appearance_settings.dart';
import '../../auth/controllers/auth_controller.dart';
import '../../home/pages/home/home_menu.dart';
import '../support_contact.dart';
import 'about_app_page.dart';
import 'appearance_page.dart';
import 'change_password_page.dart';
import 'help_support_page.dart';
import 'logout_confirm_dialog.dart';
import 'system_widgets.dart';

/// Settings ("ការកំណត់"): the signed-in officer, account (password,
/// notifications), appearance (accent colour, text size), help (support,
/// a direct call to the support contact, about) and logout. The app is
/// Khmer only, so there is no language choice.
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

  Future<void> _confirmLogout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (confirmed == true && mounted) {
      await widget.authController.logout();
    }
  }

  void _push(Widget page) => Navigator.of(
    context,
  ).push<void>(MaterialPageRoute<void>(builder: (_) => page));

  @override
  Widget build(BuildContext context) {
    final user = widget.authController.currentUser;
    final name = (user?.name.trim().isNotEmpty ?? false) ? user!.name : '-';
    final role =
        (user?.positionKm?.trim().isNotEmpty ?? false)
            ? user!.positionKm!
            : (user?.role?.trim().isNotEmpty ?? false)
            ? user!.role!
            : '';
    final appearance = AppearanceSettings.instance;

    return SystemPageScaffold(
      title: 'ការកំណត់',
      children: [
        SystemHero(
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => Navigator.of(context).pop(HomeMenuItem.profile),
              child: Row(
                children: [
                  Container(
                    width: 58,
                    height: 58,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: Colors.white.withAlpha(45),
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white.withAlpha(90)),
                    ),
                    child: Text(
                      name == '-' ? '?' : name.characters.first,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 17,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        if (role.isNotEmpty)
                          Text(
                            role,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: Colors.white.withAlpha(220),
                              fontSize: 12.5,
                            ),
                          ),
                        const SizedBox(height: 6),
                        Text(
                          'មើលព័ត៌មានផ្ទាល់ខ្លួន ›',
                          style: TextStyle(
                            color: Colors.white.withAlpha(235),
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 18),
        SettingsGroup(
          title: 'គណនី',
          children: [
            SettingsTile(
              icon: Icons.lock_rounded,
              color: const Color(0xFF2563EB),
              title: 'ប្ដូរពាក្យសម្ងាត់',
              subtitle: 'ការពារគណនីរបស់អ្នក',
              onTap:
                  () => _push(
                    ChangePasswordPage(authController: widget.authController),
                  ),
            ),
            SettingsTile(
              icon: Icons.notifications_active_rounded,
              color: const Color(0xFFF59E0B),
              title: 'ការជូនដំណឹង',
              subtitle: 'មើលជូនដំណឹងទាំងអស់របស់អ្នក',
              onTap: () => Navigator.of(context).pop(HomeMenuItem.notice),
            ),
          ],
        ),
        const SizedBox(height: 18),
        SettingsGroup(
          title: 'ការបង្ហាញ',
          children: [
            SettingsTile(
              icon: Icons.palette_rounded,
              color: const Color(0xFF8B5CF6),
              title: 'រូបរាង',
              subtitle:
                  '${appearance.accentChoice.label} · '
                  'អក្សរ${appearance.textSizeChoice.label}',
              onTap: () async {
                await Navigator.of(context).push<void>(
                  MaterialPageRoute<void>(
                    builder: (_) => const AppearancePage(),
                  ),
                );
                if (mounted) setState(() {});
              },
            ),
          ],
        ),
        const SizedBox(height: 18),
        SettingsGroup(
          title: 'ជំនួយ',
          children: [
            SettingsTile(
              icon: Icons.support_agent_rounded,
              color: const Color(0xFF10B981),
              title: 'ជំនួយ & គាំទ្រ',
              subtitle: 'សំណួរញឹកញាប់ និងរាយការណ៍បញ្ហា',
              onTap: () => _push(HelpSupportPage(user: user)),
            ),
            SettingsTile(
              icon: Icons.call_rounded,
              color: const Color(0xFF0EA5E9),
              title: 'ហៅទូរសព្ទជំនួយ',
              subtitle: SupportContact.name,
              value: SupportContact.phoneDisplay,
              trailing: const SizedBox(width: 4),
              onTap: () => SupportContact.call(context),
            ),
            SettingsTile(
              icon: Icons.info_rounded,
              color: const Color(0xFF64748B),
              title: 'អំពីកម្មវិធី',
              subtitle: 'កំណែ គោលការណ៍ឯកជនភាព លក្ខខណ្ឌ',
              onTap: () => _push(const AboutAppPage()),
            ),
          ],
        ),
        const SizedBox(height: 22),
        OutlinedButton.icon(
          onPressed: _confirmLogout,
          style: OutlinedButton.styleFrom(
            foregroundColor: const Color(0xFFDC2626),
            backgroundColor: Colors.white,
            side: const BorderSide(color: Color(0xFFFECACA)),
            minimumSize: const Size.fromHeight(52),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
            ),
          ),
          icon: const Icon(Icons.logout_rounded),
          label: const Text(
            'ចាកចេញ',
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
          ),
        ),
        const SizedBox(height: 14),
        FutureBuilder<String>(
          future: _versionFuture,
          builder:
              (context, snapshot) => Text(
                'PHD HRM · កំណែ ${snapshot.data ?? '...'}',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
              ),
        ),
      ],
    );
  }
}
