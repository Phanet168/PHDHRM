import 'package:flutter/material.dart';

import '../../../core/localization/laravel_language_service.dart';
import '../../auth/controllers/auth_controller.dart';
import '../models/attendance_day_record.dart';
import '../models/dashboard_summary.dart';
import '../services/home_attendance_service.dart';
import '../services/home_dashboard_service.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key, required this.authController});

  final AuthController authController;

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  late final HomeDashboardService _dashboardService;
  late final HomeAttendanceService _attendanceService;
  late final Future<Map<String, String>> _languageFuture;
  Future<DashboardSummary>? _summaryFuture;
  Future<List<AttendanceDayRecord>>? _attendanceFuture;
  _HomeMenuItem _selectedMenu = _HomeMenuItem.dashboard;

  @override
  void initState() {
    super.initState();
    _dashboardService = HomeDashboardService();
    _attendanceService = HomeAttendanceService();
    _languageFuture = LaravelLanguageService.instance.load();
    _summaryFuture = _loadSummary();
    _attendanceFuture = _loadAttendance();
  }

  String _tr(Map<String, String> language, String key, String fallback) {
    final value = language[key]?.trim();
    if (value == null || value.isEmpty) {
      return fallback;
    }

    return value;
  }

  String _menuTitle(_HomeMenuItem item, Map<String, String> language) {
    switch (item) {
      case _HomeMenuItem.dashboard:
        return _tr(language, 'dashboard', 'Dashboard');
      case _HomeMenuItem.attendance:
        return _tr(language, 'attendance_list', 'Attendance');
      case _HomeMenuItem.leave:
        return _tr(language, 'leave_type', 'Leave');
      case _HomeMenuItem.salary:
        return _tr(language, 'salary_details', 'Salary');
      case _HomeMenuItem.notice:
        return _tr(language, 'notice_list', 'Notice');
      case _HomeMenuItem.profile:
        return _tr(language, 'my_profile', 'Profile');
      case _HomeMenuItem.logout:
        return _tr(language, 'logout', 'Logout');
    }
  }

  Future<DashboardSummary> _loadSummary() {
    final user = widget.authController.currentUser;
    if (user == null) {
      throw Exception('User session មិនមាន');
    }

    return _dashboardService.fetchSummary(user);
  }

  Future<List<AttendanceDayRecord>> _loadAttendance() {
    final user = widget.authController.currentUser;
    if (user == null) {
      throw Exception('User session មិនមាន');
    }

    return _attendanceService.fetchAttendanceHistory(user);
  }

  Widget _buildProfileSection(
    dynamic user,
    Map<String, String> language,
    ThemeData theme,
  ) {
    if (user == null) {
      return const Center(child: Text('មិនមានទិន្នន័យអ្នកប្រើប្រាស់'));
    }

    // Shorthand to build a _ProfileRow
    _ProfileRow r(String label, String? value) =>
        _ProfileRow(label: label, value: value ?? '');

    // Build profile picture URL
    String? picUrl = user.profilePic as String?;
    if (picUrl != null && picUrl.isNotEmpty && !picUrl.startsWith('http')) {
      picUrl = 'http://192.168.1.15:8000/$picUrl';
    }

    Widget avatar;
    if (picUrl != null && picUrl.isNotEmpty) {
      avatar = CircleAvatar(
        radius: 52,
        backgroundColor: const Color(0xFFE7EFEB),
        backgroundImage: NetworkImage(picUrl),
        onBackgroundImageError: (_, __) {},
      );
    } else {
      avatar = CircleAvatar(
        radius: 52,
        backgroundColor: const Color(0xFF188754),
        child: Text(
          (user.name as String).isNotEmpty
              ? (user.name as String)[0].toUpperCase()
              : 'U',
          style: const TextStyle(
            fontSize: 40,
            color: Colors.white,
            fontWeight: FontWeight.bold,
          ),
        ),
      );
    }

    final bool? fro = user.isFullRightOfficer as bool?;
    final String? fullRightText =
        fro == null ? null : (fro ? 'ពេញសិទ្ធ' : 'មិនទាន់ពេញសិទ្ធ');

    return ListView(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 24),
      children: [
        // ─── Header card ────────────────────────────────────────────
        Card(
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
            side: const BorderSide(color: Color(0xFFE7EFEB)),
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 28, horizontal: 16),
            child: Column(
              children: [
                avatar,
                const SizedBox(height: 14),
                Text(
                  user.name as String,
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: const Color(0xFF0B5D4B),
                  ),
                  textAlign: TextAlign.center,
                ),
                if ((user.position as String?)?.isNotEmpty == true) ...[
                  const SizedBox(height: 4),
                  Text(
                    user.position as String,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: const Color(0xFF188754),
                      fontWeight: FontWeight.w600,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
                if ((user.departmentName as String?)?.isNotEmpty == true) ...[
                  const SizedBox(height: 2),
                  Text(
                    user.departmentName as String,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: Colors.grey[600],
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
                if ((user.role as String?) != null) ...[
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0xFF188754).withAlpha(26),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      user.role as String,
                      style: const TextStyle(
                        color: Color(0xFF188754),
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                Wrap(
                  alignment: WrapAlignment.center,
                  spacing: 8,
                  runSpacing: 6,
                  children: [
                    if ((user.employeeCode as String?)?.isNotEmpty == true ||
                        (user.cardNo as String?)?.isNotEmpty == true)
                      _InfoBadge(
                        icon: Icons.badge_outlined,
                        text:
                            (user.employeeCode as String?)?.isNotEmpty == true
                                ? (user.employeeCode as String)
                                : (user.cardNo as String?),
                      ),
                    if ((user.phone as String?)?.isNotEmpty == true)
                      _InfoBadge(
                        icon: Icons.phone_outlined,
                        text: user.phone as String,
                      ),
                    if ((user.email as String).isNotEmpty)
                      _InfoBadge(
                        icon: Icons.email_outlined,
                        text: user.email as String,
                      ),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),

        // ─── ព័ត៌មានផ្ទាល់ខ្លួន ──────────────────────────────────────
        _ProfileSection(
          icon: Icons.person_outline,
          title: 'ព័ត៌មានផ្ទាល់ខ្លួន',
          subtitle: 'Personal Information',
          rows: [
            r(_tr(language, 'gender', 'ភេទ'), user.gender as String?),
            r('ថ្ងៃខែឆ្នាំកំណើត', user.dateOfBirth as String?),
            r('ស្ថានភាពអាពាហ៍ពិពាហ៍', user.maritalStatus as String?),
            r('សញ្ជាតិ', user.nationality as String?),
            r('សាសនា', user.religion as String?),
            r('ជនជាតិ/ក្រុម', user.ethnicGroup as String?),
          ],
          subsections: [
            _ProfileSubsection(
              label: 'ការទាក់ទងមាន',
              rows: [
                r(_tr(language, 'phone', 'ទូរស័ព្ទ'), user.phone as String?),
                r('ទូរស័ព្ទបន្ត', user.alternatePhone as String?),
                r(_tr(language, 'email', 'អ៊ីមែល'), user.email as String),
              ],
            ),
            _ProfileSubsection(
              label: 'អាសយដ្ឋាន',
              rows: [
                r('បច្ចុប្បន្ន', user.presentAddress as String?),
                r('អចិន្ត្រៃយ៍', user.permanentAddress as String?),
              ],
            ),
          ],
        ),
        const SizedBox(height: 12),

        // ─── អត្តសញ្ញាណ ───────────────────────────────────────────────
        _ProfileSection(
          icon: Icons.card_giftcard_outlined,
          title: 'អត្តសញ្ញាណ និងឯកសារ',
          subtitle: 'Identity & Documents',
          rows: [
            r('លេខអត្តសញ្ញាណប័ណ្ណ', user.nationalId as String?),
            r('លេខឯកសារ', user.legalDocumentNumber as String?),
            r('ប្រភេទឯកសារ', user.legalDocumentType as String?),
          ],
        ),
        const SizedBox(height: 12),

        // ─── ព័ត៌មានអង្គភាព ──────────────────────────────────────────
        _ProfileSection(
          icon: Icons.business,
          title: 'ព័ត៌មានអង្គភាព និងការងារ',
          subtitle: 'Work Information',
          subsections: [
            _ProfileSubsection(
              label: 'ឯកលក្ខណ៍របស់មន្ត្រី',
              rows: [
                r('លេខសម្គាល់មន្ត្រី', user.employeeId.toString()),
                r('Employee Code', user.employeeCode as String?),
                r('Card No', user.cardNo as String?),
              ],
            ),
            _ProfileSubsection(
              label: 'ដាក់ឡើងលើ',
              rows: [
                r('នាយកដ្ឋាន', user.departmentName as String?),
                r('តួនាទី', user.position as String?),
                r('ជំនាញ', user.skillName as String?),
                r('កាំប្រាក់', user.employeeGrade as String?),
              ],
            ),
            _ProfileSubsection(
              label: 'កាលបរិច្ឆេដ',
              rows: [
                r('ថ្ងៃចូលបម្រើ', user.serviceStartDate as String?),
                r('ថ្ងៃជួលចូល', user.hireDate as String?),
                r('ថ្ងៃចូលធ្វើការ', user.joiningDate as String?),
                r('ចាប់ផ្ដើមកិច្ចសន្យា', user.contractStartDate as String?),
                r('ផុតកំណត់កិច្ចសន្យា', user.contractEndDate as String?),
              ],
            ),
            _ProfileSubsection(
              label: 'ស្ថានភាព',
              rows: [
                r('ស្ថានភាពការងារ', user.workStatusName as String?),
                r('ស្ថានភាពពេញសិទ្ធ', fullRightText),
                r('ថ្ងៃពេញសិទ្ធ', user.fullRightDate as String?),
              ],
            ),
          ],
        ),
      ],
    );
  }

  Future<void> _refresh() async {
    setState(() {
      _summaryFuture = _loadSummary();
      _attendanceFuture = _loadAttendance();
    });

    if (_selectedMenu == _HomeMenuItem.attendance) {
      await _attendanceFuture;
      return;
    }

    await _summaryFuture;
  }

  void _onMenuTap(_HomeMenuItem item) {
    Navigator.of(context).pop();

    if (item == _HomeMenuItem.logout) {
      widget.authController.logout();
      return;
    }

    setState(() {
      _selectedMenu = item;
    });
  }

  @override
  Widget build(BuildContext context) {
    final authController = widget.authController;
    final user = authController.currentUser;
    final theme = Theme.of(context);

    return FutureBuilder<Map<String, String>>(
      future: _languageFuture,
      builder: (context, snapshot) {
        final language = snapshot.data ?? const <String, String>{};

        return Scaffold(
          appBar: AppBar(
            backgroundColor: const Color(0xFF188754),
            foregroundColor: Colors.white,
            elevation: 0,
            title: Text(_menuTitle(_selectedMenu, language)),
            actions: [
              IconButton(
                onPressed:
                    authController.isSubmitting
                        ? null
                        : () async {
                          await authController.logout();
                        },
                icon: const Icon(Icons.logout),
                tooltip: _tr(language, 'logout', 'Logout'),
              ),
            ],
          ),
          drawer: Drawer(
            backgroundColor: const Color(0xFFF8FBF9),
            child: SafeArea(
              child: Column(
                children: [
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.fromLTRB(16, 18, 16, 14),
                    decoration: const BoxDecoration(
                      gradient: LinearGradient(
                        colors: [Color(0xFF1C8E5B), Color(0xFF188754)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white70),
                          ),
                          clipBehavior: Clip.antiAlias,
                          child: Image.asset(
                            'assets/images/laravel_logo.png',
                            fit: BoxFit.cover,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                user?.name ?? 'User',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                user?.email ?? '-',
                                style: const TextStyle(
                                  color: Color(0xFFE5F5EC),
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Divider(height: 1),
                  _DrawerMenuTile(
                    icon: Icons.dashboard_outlined,
                    title: _menuTitle(_HomeMenuItem.dashboard, language),
                    selected: _selectedMenu == _HomeMenuItem.dashboard,
                    onTap: () => _onMenuTap(_HomeMenuItem.dashboard),
                  ),
                  _DrawerMenuTile(
                    icon: Icons.access_time_outlined,
                    title: _menuTitle(_HomeMenuItem.attendance, language),
                    selected: _selectedMenu == _HomeMenuItem.attendance,
                    onTap: () => _onMenuTap(_HomeMenuItem.attendance),
                  ),
                  _DrawerMenuTile(
                    icon: Icons.event_note_outlined,
                    title: _menuTitle(_HomeMenuItem.leave, language),
                    selected: _selectedMenu == _HomeMenuItem.leave,
                    onTap: () => _onMenuTap(_HomeMenuItem.leave),
                  ),
                  _DrawerMenuTile(
                    icon: Icons.account_balance_wallet_outlined,
                    title: _menuTitle(_HomeMenuItem.salary, language),
                    selected: _selectedMenu == _HomeMenuItem.salary,
                    onTap: () => _onMenuTap(_HomeMenuItem.salary),
                  ),
                  _DrawerMenuTile(
                    icon: Icons.campaign_outlined,
                    title: _menuTitle(_HomeMenuItem.notice, language),
                    selected: _selectedMenu == _HomeMenuItem.notice,
                    onTap: () => _onMenuTap(_HomeMenuItem.notice),
                  ),
                  _DrawerMenuTile(
                    icon: Icons.person_outline,
                    title: _menuTitle(_HomeMenuItem.profile, language),
                    selected: _selectedMenu == _HomeMenuItem.profile,
                    onTap: () => _onMenuTap(_HomeMenuItem.profile),
                  ),
                  const Spacer(),
                  const Divider(height: 1),
                  _DrawerMenuTile(
                    icon: Icons.logout,
                    title: _menuTitle(_HomeMenuItem.logout, language),
                    selected: false,
                    onTap: () => _onMenuTap(_HomeMenuItem.logout),
                  ),
                ],
              ),
            ),
          ),
          body:
              _selectedMenu == _HomeMenuItem.dashboard
                  ? RefreshIndicator(
                    onRefresh: _refresh,
                    child: ListView(
                      padding: const EdgeInsets.all(15),
                      children: [
                        Card(
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(6),
                            side: const BorderSide(color: Color(0xFFE7EFEB)),
                          ),
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${_tr(language, 'welcome_msg', 'សូមស្វាគមន៍')} ${user?.name ?? ''}',
                                  style: theme.textTheme.titleLarge?.copyWith(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  '${_tr(language, 'email', 'Email')}: ${user?.email ?? '-'}',
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  '${_tr(language, 'employee_id', 'Employee ID')}: ${user?.employeeId ?? '-'}',
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'Department: ${user?.departmentName ?? '-'}',
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 14),
                        FutureBuilder<DashboardSummary>(
                          future: _summaryFuture,
                          builder: (context, snapshot) {
                            if (snapshot.connectionState ==
                                ConnectionState.waiting) {
                              return const Padding(
                                padding: EdgeInsets.symmetric(vertical: 32),
                                child: Center(
                                  child: CircularProgressIndicator(),
                                ),
                              );
                            }

                            if (snapshot.hasError) {
                              return Card(
                                child: Padding(
                                  padding: const EdgeInsets.all(16),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      const Text(
                                        'មិនអាចទាញ dashboard data បាន',
                                      ),
                                      const SizedBox(height: 8),
                                      Text('${snapshot.error}'),
                                      const SizedBox(height: 12),
                                      FilledButton(
                                        onPressed: _refresh,
                                        child: const Text('សាកម្តងទៀត'),
                                      ),
                                    ],
                                  ),
                                ),
                              );
                            }

                            final summary = snapshot.data;
                            if (summary == null) {
                              return const SizedBox.shrink();
                            }

                            return Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Wrap(
                                  spacing: 12,
                                  runSpacing: 12,
                                  children: [
                                    _MetricCard(
                                      title: 'Total Hours',
                                      value: summary.totalHours,
                                    ),
                                    _MetricCard(
                                      title: _tr(
                                        language,
                                        'leave_remaining',
                                        'Leave Remaining',
                                      ),
                                      value: summary.remainingLeave,
                                    ),
                                    _MetricCard(
                                      title: _tr(
                                        language,
                                        'loan_amount',
                                        'Loan Amount',
                                      ),
                                      value: summary.loanAmount,
                                    ),
                                    _MetricCard(
                                      title: _tr(
                                        language,
                                        'salary_details',
                                        'Salary Records',
                                      ),
                                      value: summary.salaryCount.toString(),
                                    ),
                                    _MetricCard(
                                      title: _tr(
                                        language,
                                        'notice_list',
                                        'Notice Count',
                                      ),
                                      value: summary.noticeCount.toString(),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 16),
                                Card(
                                  elevation: 0,
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(6),
                                    side: const BorderSide(
                                      color: Color(0xFFE7EFEB),
                                    ),
                                  ),
                                  child: Padding(
                                    padding: const EdgeInsets.all(16),
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          _tr(
                                            language,
                                            'notice_list',
                                            'Recent Notices',
                                          ),
                                        ),
                                        const SizedBox(height: 8),
                                        if (summary.notices.isEmpty)
                                          Text(
                                            _tr(
                                              language,
                                              'no_notice_to_show',
                                              'មិនទាន់មាន notice',
                                            ),
                                          )
                                        else
                                          ...summary.notices.map(
                                            (notice) => Padding(
                                              padding: const EdgeInsets.only(
                                                bottom: 6,
                                              ),
                                              child: Text('- $notice'),
                                            ),
                                          ),
                                      ],
                                    ),
                                  ),
                                ),
                              ],
                            );
                          },
                        ),
                      ],
                    ),
                  )
                  : _selectedMenu == _HomeMenuItem.attendance
                  ? RefreshIndicator(
                    onRefresh: _refresh,
                    child: FutureBuilder<List<AttendanceDayRecord>>(
                      future: _attendanceFuture,
                      builder: (context, snapshot) {
                        if (snapshot.connectionState ==
                            ConnectionState.waiting) {
                          return ListView(
                            padding: const EdgeInsets.symmetric(vertical: 120),
                            children: [
                              Center(child: CircularProgressIndicator()),
                            ],
                          );
                        }

                        if (snapshot.hasError) {
                          return ListView(
                            padding: const EdgeInsets.all(15),
                            children: [
                              Card(
                                elevation: 0,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(6),
                                  side: const BorderSide(
                                    color: Color(0xFFE7EFEB),
                                  ),
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.all(16),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        _tr(
                                          language,
                                          'attendance_list',
                                          'Attendance',
                                        ),
                                      ),
                                      const SizedBox(height: 8),
                                      Text('${snapshot.error}'),
                                      const SizedBox(height: 12),
                                      FilledButton(
                                        onPressed: _refresh,
                                        child: Text(
                                          _tr(
                                            language,
                                            'send_request',
                                            'សាកម្តងទៀត',
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ],
                          );
                        }

                        final records =
                            snapshot.data ?? const <AttendanceDayRecord>[];
                        if (records.isEmpty) {
                          return ListView(
                            padding: const EdgeInsets.all(15),
                            children: [
                              _SectionCard(
                                title: _menuTitle(_selectedMenu, language),
                                description: _tr(
                                  language,
                                  'no_record_found',
                                  'មិនទាន់មានទិន្នន័យវត្តមាន',
                                ),
                              ),
                            ],
                          );
                        }

                        return ListView.builder(
                          padding: const EdgeInsets.all(15),
                          itemCount: records.length,
                          itemBuilder: (context, index) {
                            final record = records[index];
                            return Card(
                              elevation: 0,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(6),
                                side: const BorderSide(
                                  color: Color(0xFFE7EFEB),
                                ),
                              ),
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      record.date,
                                      style: theme.textTheme.titleMedium
                                          ?.copyWith(
                                            fontWeight: FontWeight.w700,
                                          ),
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      '${_tr(language, 'in_time', 'In')}: ${record.timeIn}',
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      '${_tr(language, 'out_time', 'Out')}: ${record.timeOut}',
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      '${_tr(language, 'total_hours', 'Total Hours')}: ${record.totalHours}',
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      '${_tr(language, 'attendance_list', 'Punches')}: ${record.punchCount}',
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },
                        );
                      },
                    ),
                  )
                  : _selectedMenu == _HomeMenuItem.profile
                  ? _buildProfileSection(user, language, theme)
                  : ListView(
                    padding: const EdgeInsets.all(15),
                    children: [
                      _SectionCard(
                        title: _menuTitle(_selectedMenu, language),
                        description:
                            'ផ្នែកនេះត្រៀមសម្រាប់ភ្ជាប់ API ខាង Laravel បន្ត។',
                      ),
                    ],
                  ),
        );
      },
    );
  }
}

enum _HomeMenuItem {
  dashboard('Dashboard'),
  attendance('Attendance'),
  leave('Leave'),
  salary('Salary'),
  notice('Notice'),
  profile('Profile'),
  logout('Logout');

  const _HomeMenuItem(this.title);

  final String title;
}

class _DrawerMenuTile extends StatelessWidget {
  const _DrawerMenuTile({
    required this.icon,
    required this.title,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon, color: selected ? const Color(0xFF188754) : null),
      title: Text(title),
      selected: selected,
      selectedTileColor: const Color(0x1429A76C),
      selectedColor: const Color(0xFF188754),
      onTap: onTap,
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.description});

  final String title;
  final String description;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(6),
        side: const BorderSide(color: Color(0xFFE7EFEB)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            Text(description),
          ],
        ),
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.title, required this.value});

  final String title;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return SizedBox(
      width: 170,
      child: Card(
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(6),
          side: const BorderSide(color: Color(0xFFE7EFEB)),
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: const Color(0xFF60736A),
                ),
              ),
              const SizedBox(height: 6),
              Text(
                value,
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProfileRow {
  const _ProfileRow({required this.label, required this.value});

  final String label;
  final String value;
}

class _InfoBadge extends StatelessWidget {
  const _InfoBadge({required this.icon, required this.text});

  final IconData icon;
  final String? text;

  @override
  Widget build(BuildContext context) {
    if (text == null || text!.isEmpty) return const SizedBox.shrink();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: const Color(0xFFF0F7F4),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFD0E8DD)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: const Color(0xFF188754)),
          const SizedBox(width: 5),
          Text(
            text!,
            style: const TextStyle(fontSize: 12, color: Color(0xFF1A4A35)),
          ),
        ],
      ),
    );
  }
}

class _ProfileSubsection {
  const _ProfileSubsection({required this.label, required this.rows});
  final String label;
  final List<_ProfileRow> rows;
}

class _ProfileSection extends StatelessWidget {
  const _ProfileSection({
    required this.title,
    required this.subtitle,
    this.icon,
    this.rows,
    this.subsections,
  });

  final IconData? icon;
  final String title;
  final String subtitle;
  final List<_ProfileRow>? rows;
  final List<_ProfileSubsection>? subsections;

  @override
  Widget build(BuildContext context) {
    // Determine which rows to display
    List<_ProfileRow> mainRows = rows ?? [];
    final mainVisible = mainRows.where((r) => r.value.isNotEmpty).toList();
    final subsVisible =
        subsections
            ?.where((s) => s.rows.any((r) => r.value.isNotEmpty))
            .toList() ??
        [];

    if (mainVisible.isEmpty && subsVisible.isEmpty) {
      return const SizedBox.shrink();
    }

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
        side: const BorderSide(color: Color(0xFFE7EFEB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Section header with icon
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [Color(0xFFF0F7F4), Color(0xFFE8F5F1)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.only(
                topLeft: Radius.circular(8),
                topRight: Radius.circular(8),
              ),
            ),
            child: Row(
              children: [
                if (icon != null) ...[
                  Icon(icon, size: 20, color: const Color(0xFF188754)),
                  const SizedBox(width: 10),
                ],
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 14,
                          color: Color(0xFF0B5D4B),
                        ),
                      ),
                      Text(
                        subtitle,
                        style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: Color(0xFFE7EFEB)),
          // Main rows
          for (int i = 0; i < mainVisible.length; i++) ...[
            _buildRow(mainVisible[i]),
            if (i < mainVisible.length - 1)
              const Divider(height: 1, indent: 16, endIndent: 16),
          ],
          // Subsections
          if (subsVisible.isNotEmpty) ...[
            for (int sIdx = 0; sIdx < subsVisible.length; sIdx++) ...[
              _buildSubsection(subsVisible[sIdx]),
              if (sIdx < subsVisible.length - 1 || mainVisible.isNotEmpty)
                const Divider(height: 1, color: Color(0xFFE7EFEB)),
            ],
          ],
        ],
      ),
    );
  }

  Widget _buildRow(_ProfileRow row) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 140,
            child: Text(
              row.label,
              style: TextStyle(
                color: Colors.grey[600],
                fontSize: 13,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          Expanded(
            child: Text(
              row.value,
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: 13,
                color: Color(0xFF1A4A35),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSubsection(_ProfileSubsection sub) {
    final visible = sub.rows.where((r) => r.value.isNotEmpty).toList();
    if (visible.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Text(
            sub.label,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: Color(0xFF188754),
              letterSpacing: 0.3,
            ),
          ),
        ),
        for (int i = 0; i < visible.length; i++) ...[
          Padding(
            padding: const EdgeInsets.only(
              left: 32,
              right: 16,
              top: 8,
              bottom: 8,
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  width: 120,
                  child: Text(
                    visible[i].label,
                    style: TextStyle(color: Colors.grey[600], fontSize: 12),
                  ),
                ),
                Expanded(
                  child: Text(
                    visible[i].value,
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 12,
                      color: Color(0xFF1A4A35),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}
