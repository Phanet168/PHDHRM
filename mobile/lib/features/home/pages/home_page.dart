import 'package:flutter/material.dart';

import '../../../core/config/app_routes.dart';
import '../../../core/config/api_config.dart';
import '../../../core/localization/laravel_language_service.dart';
import '../../../core/network/api_exception.dart';
import '../../auth/controllers/auth_controller.dart';
import '../../correspondence/pages/correspondence_page.dart';
import '../models/attendance_day_record.dart';
import '../models/dashboard_summary.dart';
import '../models/home_notification_item.dart';
import '../models/mission_summary.dart';
import 'attendance_history_page.dart';
import 'leave_request_page.dart';
import 'attendance_scan_page.dart';
import '../services/home_attendance_service.dart';
import '../services/home_dashboard_service.dart';
import '../services/home_mission_service.dart';
import '../services/home_notification_service.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key, required this.authController});

  final AuthController authController;

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  late final HomeDashboardService _dashboardService;
  late final HomeAttendanceService _attendanceService;
  late final HomeMissionService _missionService;
  late final HomeNotificationService _notificationService;
  late final Future<Map<String, String>> _languageFuture;
  Future<DashboardSummary>? _summaryFuture;
  Future<List<AttendanceDayRecord>>? _attendanceFuture;
  Future<List<MissionSummary>>? _missionsFuture;
  Future<HomeNotificationPageData>? _notificationsFuture;
  bool _isMarkingAllNotifications = false;
  _HomeMenuItem _selectedMenu = _HomeMenuItem.dashboard;

  bool get _isOnDashboard => _selectedMenu == _HomeMenuItem.dashboard;

  double _contentBottomPadding(BuildContext context, {double base = 24}) {
    final inset = MediaQuery.of(context).padding.bottom;
    return base + (inset < 12 ? 12 : inset);
  }

  @override
  void initState() {
    super.initState();
    _dashboardService = HomeDashboardService();
    _attendanceService = HomeAttendanceService();
    _missionService = HomeMissionService();
    _notificationService = HomeNotificationService();
    _languageFuture = LaravelLanguageService.instance.load();
    _summaryFuture = _loadSummary();
    // Preload attendance data so the dashboard can show today's status quickly.
    _attendanceFuture = _loadAttendance();
    _missionsFuture = null;
    _notificationsFuture = _loadNotifications();
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
        return 'វត្តមានការងារ';
      case _HomeMenuItem.attendance:
        return _tr(language, 'attendance_history', 'ប្រវត្តិវត្តមាន');
      case _HomeMenuItem.leave:
        return _tr(language, 'leave_type', 'ការសុំច្បាប់');
      case _HomeMenuItem.mission:
        return _tr(language, 'mission', 'បេសកកម្ម');
      case _HomeMenuItem.salary:
        return _tr(language, 'salary_details', 'ព័ត៌មានប្រាក់ខែ');
      case _HomeMenuItem.notice:
        return _tr(language, 'notice_list', 'ជូនដំណឹង');
      case _HomeMenuItem.correspondence:
        return _tr(language, 'correspondence', 'លិខិតរដ្ឋបាល');
      case _HomeMenuItem.profile:
        return _tr(language, 'my_profile', 'ព័ត៌មានផ្ទាល់ខ្លួន');
      case _HomeMenuItem.logout:
        return _tr(language, 'logout', 'ចាកចេញ');
    }
  }

  int? _bottomNavIndex() {
    switch (_selectedMenu) {
      case _HomeMenuItem.dashboard:
        return 0;
      case _HomeMenuItem.attendance:
        return 1;
      case _HomeMenuItem.notice:
        return 2;
      default:
        return null;
    }
  }

  Future<void> _onBottomNavTap(int index) async {
    switch (index) {
      case 0:
        setState(() {
          _selectedMenu = _HomeMenuItem.dashboard;
        });
        return;
      case 1:
        setState(() {
          _selectedMenu = _HomeMenuItem.attendance;
          _attendanceFuture ??= _loadAttendance();
        });
        return;
      case 2:
        setState(() {
          _selectedMenu = _HomeMenuItem.notice;
          _notificationsFuture ??= _loadNotifications();
        });
        return;
      case 3:
        await Navigator.of(context).pushNamed(AppRoutes.systemSettings);
        return;
    }
  }

  Future<DashboardSummary> _loadSummary({bool forceRefresh = false}) {
    final user = widget.authController.currentUser;
    if (user == null) {
      throw Exception('មិនមាន session អ្នកប្រើប្រាស់');
    }

    return _dashboardService
        .fetchSummary(user, forceRefresh: forceRefresh)
        .timeout(
          const Duration(seconds: 25),
          onTimeout: () {
            throw NetworkException();
          },
        );
  }

  Future<List<AttendanceDayRecord>> _loadAttendance() {
    final user = widget.authController.currentUser;
    if (user == null) {
      throw Exception('មិនមាន session អ្នកប្រើប្រាស់');
    }

    if (!user.hasEmployee) {
      return Future<List<AttendanceDayRecord>>.value(
        const <AttendanceDayRecord>[],
      );
    }

    return _attendanceService
        .fetchAttendanceHistory(user)
        .timeout(
          const Duration(seconds: 25),
          onTimeout: () {
            throw NetworkException();
          },
        );
  }

  Future<List<MissionSummary>> _loadMissions() {
    final user = widget.authController.currentUser;
    if (user == null) {
      throw Exception('មិនមាន session អ្នកប្រើប្រាស់');
    }

    return _missionService
        .fetchMissions(user)
        .timeout(
          const Duration(seconds: 25),
          onTimeout: () {
            throw NetworkException();
          },
        );
  }

  Future<HomeNotificationPageData> _loadNotifications() {
    return _notificationService.fetchNotifications().timeout(
      const Duration(seconds: 25),
      onTimeout: () {
        throw NetworkException();
      },
    );
  }

  String _attendanceStatusLabel(Map<String, String> language, String? status) {
    final normalized = status?.trim().toLowerCase();
    if (normalized == null || normalized.isEmpty) {
      return '-';
    }

    switch (normalized) {
      case 'on_time':
        return _tr(language, 'on_time', 'ទាន់ពេល');
      case 'late':
        return _tr(language, 'late', 'មកយឺត');
      case 'early_leave':
        return _tr(language, 'early_leave', 'ចេញមុនម៉ោង');
      case 'late_and_early_leave':
        return _tr(language, 'late_and_early_leave', 'មកយឺត និងចេញមុន');
      case 'incomplete':
        return _tr(language, 'incomplete', 'មិនពេញលេញ');
      default:
        return status!.replaceAll('_', ' ').trim();
    }
  }

  String _formatDateKey(DateTime date) {
    final year = date.year.toString().padLeft(4, '0');
    final month = date.month.toString().padLeft(2, '0');
    final day = date.day.toString().padLeft(2, '0');
    return '$year-$month-$day';
  }

  String _formatDateDisplay(String? dateString) {
    if (dateString == null || dateString.isEmpty) {
      return '-';
    }

    try {
      final date = DateTime.parse(dateString);
      final day = date.day.toString().padLeft(2, '0');
      final month = date.month.toString().padLeft(2, '0');
      final year = date.year;
      return '$day-$month-$year';
    } catch (_) {
      return dateString;
    }
  }

  String _apiOrigin() {
    final uri = Uri.parse(ApiConfig.baseUrl);
    final hasPort = uri.hasPort;
    final portPart = hasPort ? ':${uri.port}' : '';
    return '${uri.scheme}://${uri.host}$portPart';
  }

  String? _resolveProfileImageUrl(String? rawValue) {
    final value = rawValue?.trim();
    if (value == null || value.isEmpty) {
      return null;
    }

    final origin = _apiOrigin();

    if (value.startsWith('http://') || value.startsWith('https://')) {
      final uri = Uri.tryParse(value);
      if (uri == null) {
        return value;
      }

      final isLocalHost =
          uri.host == '127.0.0.1' ||
          uri.host == 'localhost' ||
          uri.host == '10.0.2.2';
      if (!isLocalHost) {
        return value;
      }

      final normalizedPath =
          uri.path.startsWith('/') ? uri.path : '/${uri.path}';
      return '$origin$normalizedPath';
    }

    if (value.startsWith('/')) {
      return '$origin$value';
    }

    if (value.startsWith('storage/')) {
      return '$origin/$value';
    }

    return '$origin/storage/$value';
  }

  AttendanceDayRecord? _findTodayRecord(List<AttendanceDayRecord> records) {
    final todayKey = _formatDateKey(DateTime.now());
    for (final record in records) {
      if (record.date.startsWith(todayKey)) {
        return record;
      }
    }
    return null;
  }

  void _showServiceMessage(String message) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
    );
  }

  void _switchToMenu(_HomeMenuItem item) {
    if (_selectedMenu == item) {
      return;
    }

    setState(() {
      _selectedMenu = item;
      if (item == _HomeMenuItem.attendance && _attendanceFuture == null) {
        _attendanceFuture = _loadAttendance();
      }
      if (item == _HomeMenuItem.mission && _missionsFuture == null) {
        _missionsFuture = _loadMissions();
      }
      if (item == _HomeMenuItem.notice && _notificationsFuture == null) {
        _notificationsFuture = _loadNotifications();
      }
    });
  }

  void _returnToDashboard() {
    _switchToMenu(_HomeMenuItem.dashboard);
  }

  void _openAdditionalService(
    _HomeMenuItem item,
    Map<String, String> language, {
    String? message,
  }) {
    _switchToMenu(item);

    if (message != null && message.trim().isNotEmpty) {
      _showServiceMessage(message);
    }
  }

  Widget _buildAdditionalServicesGrid(Map<String, String> language) {
    final screenWidth = MediaQuery.of(context).size.width;
    final crossAxisCount = screenWidth >= 760 ? 3 : 2;
    final childAspectRatio =
        crossAxisCount == 3 ? 1.45 : (screenWidth < 380 ? 1.18 : 1.32);

    return GridView.count(
      crossAxisCount: crossAxisCount,
      crossAxisSpacing: 10,
      mainAxisSpacing: 10,
      childAspectRatio: childAspectRatio,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      children: [
        _AdditionalServiceCard(
          icon: Icons.event_note_outlined,
          title: _tr(language, 'leave_type', 'សុំច្បាប់'),
          onTap:
              () => _openAdditionalService(
                _HomeMenuItem.leave,
                language,
                message: _tr(
                  language,
                  'service_redirect_leave',
                  'សូមដាក់សំណើច្បាប់ រួចរង់ចាំការអនុម័ត។',
                ),
              ),
        ),
        _AdditionalServiceCard(
          icon: Icons.work_outline,
          title: _tr(language, 'mission', 'បេសកកម្ម'),
          onTap: () => _openAdditionalService(_HomeMenuItem.mission, language),
        ),
        _AdditionalServiceCard(
          icon: Icons.calendar_month_outlined,
          title: _tr(language, 'attendance_history', 'ប្រវត្តិវត្តមាន'),
          onTap: () => _openAttendanceHistory(language),
        ),
        _AdditionalServiceCard(
          icon: Icons.fact_check_outlined,
          title: _tr(language, 'attendance_adjustment', 'កែវត្តមាន'),
          onTap: () {
            setState(() {
              _selectedMenu = _HomeMenuItem.attendance;
              _attendanceFuture ??= _loadAttendance();
            });

            _showServiceMessage(
              _tr(
                language,
                'service_adjustment_hint',
                'បើកប្រវត្តិវត្តមាន រួចជ្រើសថ្ងៃដើម្បីស្នើកែប្រែវត្តមាន។',
              ),
            );
          },
        ),
      ],
    );
  }

  Future<void> _openAttendanceHistory(Map<String, String> language) async {
    final user = widget.authController.currentUser;
    if (user == null) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _tr(language, 'wrong_info_alert', 'មិនមានព័ត៌មានអ្នកប្រើប្រាស់'),
          ),
          backgroundColor: const Color(0xFFD34B5F),
        ),
      );
      return;
    }

    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder:
            (_) => AttendanceHistoryPage(
              user: user,
              attendanceService: _attendanceService,
              language: language,
            ),
      ),
    );
  }

  Future<void> _openAttendanceScanner(Map<String, String> language) async {
    final user = widget.authController.currentUser;
    if (user == null) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _tr(language, 'wrong_info_alert', 'មិនមានព័ត៌មានអ្នកប្រើប្រាស់'),
          ),
          backgroundColor: const Color(0xFFD34B5F),
        ),
      );
      return;
    }

    if (!user.hasEmployee) {
      _showServiceMessage(
        'គណនីនេះមិនទាន់ភ្ជាប់ប្រវត្តិបុគ្គលិក ដូច្នេះមិនអាចស្កេនវត្តមានបានទេ។',
      );
      return;
    }

    final shouldRefresh = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder:
            (_) => AttendanceScanPage(
              user: user,
              attendanceService: _attendanceService,
              language: language,
            ),
      ),
    );

    if (shouldRefresh == true && mounted) {
      setState(() {
        _attendanceFuture = _loadAttendance();
      });
    }
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
    final picUrl = _resolveProfileImageUrl(user.profilePic as String?);

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
    final String positionText =
        ((user.positionKm ?? user.position) as String?)?.trim().isNotEmpty ==
                true
            ? ((user.positionKm ?? user.position) as String).trim()
            : '-';
    final String departmentText =
        (user.departmentName as String?)?.trim().isNotEmpty == true
            ? (user.departmentName as String).trim()
            : '-';
    final String payLevelText =
        ((user.employeeGradeKm ?? user.employeeGrade) as String?)
                    ?.trim()
                    .isNotEmpty ==
                true
            ? ((user.employeeGradeKm ?? user.employeeGrade) as String).trim()
            : '-';
    final String serviceDateText = _formatDateDisplay(user.serviceStartDate);
    final String contactText =
        (user.phone as String?)?.trim().isNotEmpty == true
            ? (user.phone as String).trim()
            : (user.email as String).trim();

    return ListView(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 24),
      children: [
        _ProfileHeroCard(
          avatar: avatar,
          name: user.name as String,
          position: positionText,
          department: departmentText,
          role: user.role as String?,
          chips: [
            _ProfileHighlightChip(
              icon: Icons.badge_outlined,
              label: 'អត្តលេខ',
              value:
                  (user.employeeCode as String?)?.isNotEmpty == true
                      ? user.employeeCode as String
                      : ((user.cardNo as String?)?.isNotEmpty == true
                          ? user.cardNo as String
                          : '${user.employeeId}'),
            ),
            _ProfileHighlightChip(
              icon: Icons.account_tree_outlined,
              label: 'កាំប្រាក់',
              value: payLevelText,
            ),
            _ProfileHighlightChip(
              icon: Icons.calendar_month_outlined,
              label: 'ថ្ងៃចូលបម្រើ',
              value: serviceDateText,
            ),
            _ProfileHighlightChip(
              icon: Icons.call_outlined,
              label: 'ទំនាក់ទំនង',
              value: contactText,
            ),
          ],
          badges: [
            if ((user.employeeCode as String?)?.isNotEmpty == true ||
                (user.cardNo as String?)?.isNotEmpty == true)
              _InfoBadge(
                icon: Icons.credit_card_outlined,
                text:
                    (user.employeeCode as String?)?.isNotEmpty == true
                        ? user.employeeCode as String
                        : user.cardNo as String?,
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
        const SizedBox(height: 12),

        // ─── ព័ត៌មានផ្ទាល់ខ្លួន ──────────────────────────────────────
        _ProfileSection(
          icon: Icons.person_outline,
          title: 'ព័ត៌មានផ្ទាល់ខ្លួន',
          subtitle: 'ព័ត៌មានបុគ្គល',
          rows: [
            r(_tr(language, 'gender', 'ភេទ'), user.gender as String?),
            r('ថ្ងៃខែឆ្នាំកំណើត', _formatDateDisplay(user.dateOfBirth)),
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
          subtitle: 'អត្តសញ្ញាណ និងឯកសារផ្លូវការ',
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
          subtitle: 'ព័ត៌មានការងារ',
          subsections: [
            _ProfileSubsection(
              label: 'ឯកលក្ខណ៍របស់មន្ត្រី',
              rows: [
                r(
                  'លេខសម្គាល់មន្ត្រី',
                  (user.employeeNo as String?)?.isNotEmpty == true
                      ? user.employeeNo as String
                      : ((user.employeeCode as String?)?.isNotEmpty == true
                          ? user.employeeCode as String
                          : user.employeeId.toString()),
                ),
                r('កូដបុគ្គលិក', user.employeeCode as String?),
                r('លេខកាត', user.cardNo as String?),
              ],
            ),
            _ProfileSubsection(
              label: 'ដាក់ឡើងលើ',
              rows: [
                r('នាយកដ្ឋាន', user.departmentName as String?),
                r('តួនាទី', (user.positionKm ?? user.position) as String?),
                r('ជំនាញ', user.skillName as String?),
                r(
                  'កាំប្រាក់',
                  (user.employeeGradeKm ?? user.employeeGrade) as String?,
                ),
              ],
            ),
            _ProfileSubsection(
              label: 'កាលបរិច្ឆេដ',
              rows: [
                r('ថ្ងៃចូលបម្រើ', _formatDateDisplay(user.serviceStartDate)),
                r('ថ្ងៃជួលចូល', _formatDateDisplay(user.hireDate)),
                r('ថ្ងៃចូលធ្វើការ', _formatDateDisplay(user.joiningDate)),
                r(
                  'ចាប់ផ្ដើមកិច្ចសន្យា',
                  _formatDateDisplay(user.contractStartDate),
                ),
                r(
                  'ផុតកំណត់កិច្ចសន្យា',
                  _formatDateDisplay(user.contractEndDate),
                ),
              ],
            ),
            _ProfileSubsection(
              label: 'ស្ថានភាព',
              rows: [
                r('ស្ថានភាពការងារ', user.workStatusName as String?),
                r('ស្ថានភាពពេញសិទ្ធ', fullRightText),
                r('ថ្ងៃពេញសិទ្ធ', _formatDateDisplay(user.fullRightDate)),
              ],
            ),
          ],
        ),
      ],
    );
  }

  Future<void> _refresh() async {
    if (_selectedMenu == _HomeMenuItem.attendance) {
      setState(() {
        _attendanceFuture = _loadAttendance();
      });

      try {
        await _attendanceFuture;
      } catch (_) {
        // FutureBuilder renders error state for failed attendance requests.
      }
      return;
    }

    if (_selectedMenu == _HomeMenuItem.mission) {
      setState(() {
        _missionsFuture = _loadMissions();
      });

      try {
        await _missionsFuture;
      } catch (_) {
        // FutureBuilder renders error state for failed mission requests.
      }
      return;
    }

    if (_selectedMenu == _HomeMenuItem.notice) {
      setState(() {
        _notificationsFuture = _loadNotifications();
      });

      try {
        await _notificationsFuture;
      } catch (_) {
        // FutureBuilder renders error state for failed notification requests.
      }
      return;
    }

    setState(() {
      _summaryFuture = _loadSummary(forceRefresh: true);
    });

    try {
      await _summaryFuture;
    } catch (_) {
      // FutureBuilder renders error state for failed dashboard requests.
    }
  }

  Future<void> _markNotificationAsRead(HomeNotificationItem item) async {
    if (!item.isUnread || item.id <= 0) {
      return;
    }

    try {
      await _notificationService.markAsRead(item.id);
      if (!mounted) {
        return;
      }
      setState(() {
        _notificationsFuture = _loadNotifications();
      });
    } catch (error) {
      _showServiceMessage(error.toString());
    }
  }

  Future<void> _markAllNotificationsAsRead() async {
    if (_isMarkingAllNotifications) {
      return;
    }

    setState(() {
      _isMarkingAllNotifications = true;
    });

    try {
      await _notificationService.markAllAsRead();
      if (!mounted) {
        return;
      }
      setState(() {
        _notificationsFuture = _loadNotifications();
      });
    } catch (error) {
      _showServiceMessage(error.toString());
    } finally {
      if (mounted) {
        setState(() {
          _isMarkingAllNotifications = false;
        });
      }
    }
  }

  void _onMenuTap(_HomeMenuItem item) {
    Navigator.of(context).pop();

    if (item == _HomeMenuItem.logout) {
      widget.authController.logout();
      return;
    }

    _switchToMenu(item);
  }

  String _userInitial(dynamic user) {
    final name = user?.name?.toString().trim() ?? '';
    if (name.isEmpty) {
      return 'U';
    }

    return name.substring(0, 1).toUpperCase();
  }

  Widget _buildDrawer(dynamic user, Map<String, String> language) {
    return Drawer(
      backgroundColor: Colors.white,
      child: SafeArea(
        child: Column(
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(18, 20, 18, 18),
              decoration: const BoxDecoration(
                color: Color(0xFF0B6B58),
                gradient: LinearGradient(
                  colors: [Color(0xFF0B6B58), Color(0xFF174C88)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 24,
                    backgroundColor: Colors.white,
                    child: Text(
                      _userInitial(user),
                      style: const TextStyle(
                        color: Color(0xFF0B6B58),
                        fontWeight: FontWeight.w800,
                        fontSize: 18,
                      ),
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
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 3),
                        Text(
                          user?.email ?? '-',
                          style: const TextStyle(
                            color: Color(0xFFE7F1F5),
                            fontSize: 12,
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
            const SizedBox(height: 8),
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
              icon: Icons.work_outline,
              title: _menuTitle(_HomeMenuItem.mission, language),
              selected: _selectedMenu == _HomeMenuItem.mission,
              onTap: () => _onMenuTap(_HomeMenuItem.mission),
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
              icon: Icons.mail_outlined,
              title: _menuTitle(_HomeMenuItem.correspondence, language),
              selected: _selectedMenu == _HomeMenuItem.correspondence,
              onTap: () => _onMenuTap(_HomeMenuItem.correspondence),
            ),
            _DrawerMenuTile(
              icon: Icons.person_outline,
              title: _menuTitle(_HomeMenuItem.profile, language),
              selected: _selectedMenu == _HomeMenuItem.profile,
              onTap: () => _onMenuTap(_HomeMenuItem.profile),
            ),
            const Spacer(),
            const Divider(height: 1, color: Color(0xFFE8EEF0)),
            _DrawerMenuTile(
              icon: Icons.logout,
              title: _menuTitle(_HomeMenuItem.logout, language),
              selected: false,
              onTap: () => _onMenuTap(_HomeMenuItem.logout),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }

  Widget _buildDashboard(
    dynamic user,
    Map<String, String> language,
    ThemeData theme,
  ) {
    final attendanceFuture = _attendanceFuture ?? _loadAttendance();
    final listPadding = EdgeInsets.fromLTRB(
      16,
      12,
      16,
      _contentBottomPadding(context),
    );

    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<DashboardSummary>(
        future: _summaryFuture,
        builder: (context, summarySnapshot) {
          if (summarySnapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }

          if (summarySnapshot.hasError) {
            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              children: [
                _ErrorStateCard(
                  title: 'មិនអាចទាញ dashboard data បាន',
                  message: '${summarySnapshot.error}',
                  onRetry: _refresh,
                ),
              ],
            );
          }

          final summary = summarySnapshot.data;
          if (summary == null) {
            return const SizedBox.shrink();
          }

          return FutureBuilder<List<AttendanceDayRecord>>(
            future: attendanceFuture,
            builder: (context, attendanceSnapshot) {
              final records =
                  attendanceSnapshot.data ?? const <AttendanceDayRecord>[];
              final recentRecords = records.take(2).toList();
              final todayRecord = _findTodayRecord(records);
              final statusToday = _attendanceStatusLabel(
                language,
                todayRecord?.attendanceStatus,
              );
              final employeeBadgeText =
                  (user?.employeeNo?.trim().isNotEmpty == true)
                      ? user!.employeeNo!.trim()
                      : (user?.employeeCode?.trim().isNotEmpty == true)
                      ? user!.employeeCode!.trim()
                      : (user?.cardNo?.trim().isNotEmpty == true)
                      ? user!.cardNo!.trim()
                      : '${user?.employeeId ?? '-'}';
              final positionText =
                  (user?.positionKm?.trim().isNotEmpty == true)
                      ? user!.positionKm!.trim()
                      : user?.position;
              final shiftToday =
                  (todayRecord == null ||
                          todayRecord.timeIn == '-' ||
                          todayRecord.timeOut == '-')
                      ? _tr(language, 'no_shift_today', 'មិនទាន់មានវេនបង្ហាញ')
                      : '${todayRecord.timeIn} - ${todayRecord.timeOut}';

              return ListView(
                padding: listPadding,
                children: [
                  _WelcomePanel(
                    greeting: _tr(language, 'welcome_msg', 'សូមស្វាគមន៍'),
                    name: user?.name ?? 'User',
                    email: user?.email ?? '-',
                    employeeId: employeeBadgeText,
                    department: user?.departmentName ?? '-',
                    position: positionText,
                    initial: _userInitial(user),
                  ),
                  const SizedBox(height: 12),
                  _DashboardTodayCard(
                    title: _tr(language, 'today_status', 'ស្ថានភាពថ្ងៃនេះ'),
                    shiftLabel: _tr(language, 'today_shift', 'វេនថ្ងៃនេះ'),
                    shiftValue: shiftToday,
                    statusLabel: _tr(language, 'status', 'ស្ថានភាព'),
                    statusValue: statusToday,
                    inTimeLabel: _tr(language, 'last_in', 'ម៉ោងចូលចុងក្រោយ'),
                    inTime: todayRecord?.timeIn ?? '-',
                    outTimeLabel: _tr(language, 'last_out', 'ម៉ោងចេញចុងក្រោយ'),
                    outTime: todayRecord?.timeOut ?? '-',
                    totalLabel: _tr(language, 'total_hours', 'ម៉ោងសរុប'),
                    totalHours: todayRecord?.totalHours ?? '-',
                    punchesLabel: _tr(language, 'punches', 'ចំនួនស្កេន'),
                    punchCount: '${todayRecord?.punchCount ?? 0}',
                    lateLabel: _tr(language, 'late', 'យឺត'),
                    lateMinutes: todayRecord?.lateMinutes,
                    earlyLeaveLabel: _tr(language, 'early_leave', 'ចេញមុន'),
                    earlyLeaveMinutes: todayRecord?.earlyLeaveMinutes,
                    buttonText: _tr(language, 'qr_scan', 'ស្កេន QR'),
                    onPressed: () => _openAttendanceScanner(language),
                  ),
                  const SizedBox(height: 12),
                  _DashboardQuickActions(
                    actions: [
                      _QuickActionItem(
                        icon: Icons.work_outline,
                        title: _tr(language, 'mission', 'បេសកកម្ម'),
                        tint: const Color(0xFFEAF1FF),
                        iconColor: const Color(0xFF5771B8),
                        onTap:
                            () => _openAdditionalService(
                              _HomeMenuItem.mission,
                              language,
                            ),
                      ),
                      _QuickActionItem(
                        icon: Icons.fact_check_outlined,
                        title: _tr(
                          language,
                          'attendance_adjustment',
                          'កែវត្តមាន',
                        ),
                        tint: const Color(0xFFEAF7EE),
                        iconColor: const Color(0xFF4F9C6F),
                        onTap: () {
                          setState(() {
                            _selectedMenu = _HomeMenuItem.attendance;
                            _attendanceFuture ??= _loadAttendance();
                          });
                          _showServiceMessage(
                            _tr(
                              language,
                              'service_adjustment_hint',
                              'បើកប្រវត្តិវត្តមាន រួចជ្រើសថ្ងៃដើម្បីស្នើកែប្រែវត្តមាន។',
                            ),
                          );
                        },
                      ),
                      _QuickActionItem(
                        icon: Icons.event_note_outlined,
                        title: _tr(language, 'leave_type', 'សុំច្បាប់'),
                        tint: const Color(0xFFFFF4E5),
                        iconColor: const Color(0xFFD79C2E),
                        onTap:
                            () => _openAdditionalService(
                              _HomeMenuItem.leave,
                              language,
                              message: _tr(
                                language,
                                'service_redirect_leave',
                                'Please submit a leave request and wait for approval.',
                              ),
                            ),
                      ),
                      _QuickActionItem(
                        icon: Icons.history_outlined,
                        title: _tr(
                          language,
                          'attendance_history',
                          'ប្រវត្តិវត្តមាន',
                        ),
                        tint: const Color(0xFFEAF1FF),
                        iconColor: const Color(0xFF5D79C8),
                        onTap: () => _openAttendanceHistory(language),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  _DashboardSectionRow(
                    title: _tr(language, 'attendance_history', 'ប្រវត្តិខ្លី'),
                    onPressed: () => _openAttendanceHistory(language),
                  ),
                  const SizedBox(height: 8),
                  if (recentRecords.isEmpty)
                    _SectionCard(
                      title: _tr(
                        language,
                        'attendance_history',
                        'ប្រវត្តិវត្តមាន',
                      ),
                      description: _tr(
                        language,
                        'no_record_found',
                        'មិនទាន់មានទិន្នន័យវត្តមាន',
                      ),
                    )
                  else
                    GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            crossAxisSpacing: 12,
                            mainAxisSpacing: 12,
                            mainAxisExtent: 148,
                          ),
                      itemCount: recentRecords.length,
                      itemBuilder: (context, index) {
                        final record = recentRecords[index];
                        return _CompactAttendanceRecordCard(
                          date: record.date,
                          inTime: record.timeIn,
                          outTime: record.timeOut,
                          status: _attendanceStatusLabel(
                            language,
                            record.attendanceStatus,
                          ),
                          statusCode: record.attendanceStatus,
                        );
                      },
                    ),
                  const SizedBox(height: 10),
                  Center(
                    child: OutlinedButton(
                      onPressed: () => _openAttendanceHistory(language),
                      child: Text(
                        _tr(
                          language,
                          'view_full_history',
                          'មើលប្រវត្តិទាំងអស់',
                        ),
                      ),
                    ),
                  ),
                ],
              );
            },
          );
        },
      ),
    );
  }

  Widget _buildAttendance(Map<String, String> language) {
    final listPadding = EdgeInsets.fromLTRB(
      16,
      12,
      16,
      _contentBottomPadding(context),
    );

    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<List<AttendanceDayRecord>>(
        future: _attendanceFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return ListView(
              padding: listPadding,
              children: [
                _AttendanceSectionHeader(
                  title: _tr(language, 'today_status', 'ស្ថានភាពថ្ងៃនេះ'),
                  subtitle: _tr(language, 'loading', 'កំពុងទាញទិន្នន័យ...'),
                ),
                const SizedBox(height: 12),
                const Center(child: CircularProgressIndicator()),
                const SizedBox(height: 20),
                _AttendanceSectionHeader(
                  title: _tr(language, 'scan_attendance', 'ស្កេនវត្តមាន'),
                  subtitle: _tr(
                    language,
                    'qr_attendance',
                    'បញ្ជាក់វត្តមានដោយ QR',
                  ),
                ),
                const SizedBox(height: 10),
                _AttendanceScanActionCard(
                  title: _tr(language, 'scan_now', 'ចុចស្កេនឥឡូវនេះ'),
                  description: _tr(
                    language,
                    'confirm_attendance',
                    'ស្កេន QR អង្គភាព ដើម្បីកត់វត្តមានជាមួយ GPS បច្ចុប្បន្ន។',
                  ),
                  buttonText: _tr(language, 'qr_scan', 'ស្កេន QR'),
                  onPressed: () => _openAttendanceScanner(language),
                ),
                const SizedBox(height: 24),
                const Center(child: CircularProgressIndicator()),
              ],
            );
          }

          if (snapshot.hasError) {
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _AttendanceSectionHeader(
                  title: _tr(language, 'today_status', 'ស្ថានភាពថ្ងៃនេះ'),
                  subtitle: _tr(language, 'attendance', 'វត្តមាន'),
                ),
                const SizedBox(height: 10),
                _ErrorStateCard(
                  title: _tr(language, 'attendance_history', 'ប្រវត្តិវត្តមាន'),
                  message: '${snapshot.error}',
                  onRetry: _refresh,
                ),
              ],
            );
          }

          final records = snapshot.data ?? const <AttendanceDayRecord>[];
          final recentRecords = records.take(7).toList();
          final todayRecord = _findTodayRecord(records);
          final statusToday = _attendanceStatusLabel(
            language,
            todayRecord?.attendanceStatus,
          );
          final shiftToday =
              (todayRecord == null ||
                      todayRecord.timeIn == '-' ||
                      todayRecord.timeOut == '-')
                  ? _tr(language, 'no_shift_today', 'មិនទាន់មានវេនបង្ហាញ')
                  : '${todayRecord.timeIn} - ${todayRecord.timeOut}';

          final historyTitle = _tr(
            language,
            'attendance_history',
            'ប្រវត្តិវត្តមាន',
          );

          if (records.isEmpty) {
            return ListView(
              padding: listPadding,
              children: [
                _AttendanceSectionHeader(
                  title: _tr(language, 'today_status', 'ស្ថានភាពថ្ងៃនេះ'),
                  subtitle: _tr(language, 'today', 'ថ្ងៃនេះ'),
                ),
                const SizedBox(height: 10),
                _TodayAttendanceStatusCard(
                  shiftLabel: _tr(language, 'today_shift', 'វេនថ្ងៃនេះ'),
                  shiftValue: shiftToday,
                  statusLabel: _tr(language, 'status', 'ស្ថានភាព'),
                  statusValue: statusToday,
                  inTimeLabel: _tr(language, 'last_in', 'ម៉ោងចូលចុងក្រោយ'),
                  inTime: '-',
                  outTimeLabel: _tr(language, 'last_out', 'ម៉ោងចេញចុងក្រោយ'),
                  outTime: '-',
                ),
                const SizedBox(height: 14),
                _AttendanceSectionHeader(
                  title: _tr(language, 'scan_attendance', 'ស្កេនវត្តមាន'),
                  subtitle: _tr(language, 'qr_attendance', 'ស្កេន QR'),
                ),
                const SizedBox(height: 10),
                _ProminentScanCard(
                  title: _tr(language, 'scan_now', 'ចុចស្កេនឥឡូវនេះ'),
                  subtitle: _tr(
                    language,
                    'confirm_attendance',
                    'ស្កេន QR អង្គភាព ដើម្បីកត់វត្តមានជាមួយ GPS បច្ចុប្បន្ន។',
                  ),
                  buttonText: _tr(language, 'qr_scan', 'ស្កេន QR'),
                  onPressed: () => _openAttendanceScanner(language),
                ),
                const SizedBox(height: 14),
                _AttendanceSectionHeader(
                  title: historyTitle,
                  subtitle: _tr(language, 'latest_records', 'កំណត់ត្រាថ្មីៗ'),
                ),
                const SizedBox(height: 10),
                _SectionCard(
                  title: historyTitle,
                  description: _tr(
                    language,
                    'no_record_found',
                    'មិនទាន់មានទិន្នន័យវត្តមាន',
                  ),
                ),
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  onPressed: () => _openAttendanceHistory(language),
                  icon: const Icon(Icons.calendar_view_month_outlined),
                  label: Text(
                    _tr(language, 'view_full_history', 'មើលប្រវត្តិតាមខែ'),
                  ),
                ),
                const SizedBox(height: 14),
                _AttendanceSectionHeader(
                  title: _tr(language, 'additional_services', 'សេវាកម្មបន្ថែម'),
                  subtitle: _tr(language, 'quick_access', 'ចូលប្រើរហ័ស'),
                ),
                const SizedBox(height: 10),
                _buildAdditionalServicesGrid(language),
              ],
            );
          }

          return ListView(
            padding: listPadding,
            children: [
              _AttendanceSectionHeader(
                title: _tr(language, 'today_status', 'ស្ថានភាពថ្ងៃនេះ'),
                subtitle: _tr(language, 'today', 'ថ្ងៃនេះ'),
              ),
              const SizedBox(height: 10),
              _TodayAttendanceStatusCard(
                shiftLabel: _tr(language, 'today_shift', 'វេនថ្ងៃនេះ'),
                shiftValue: shiftToday,
                statusLabel: _tr(language, 'status', 'ស្ថានភាព'),
                statusValue: statusToday,
                inTimeLabel: _tr(language, 'last_in', 'ម៉ោងចូលចុងក្រោយ'),
                inTime: todayRecord?.timeIn ?? '-',
                outTimeLabel: _tr(language, 'last_out', 'ម៉ោងចេញចុងក្រោយ'),
                outTime: todayRecord?.timeOut ?? '-',
              ),
              const SizedBox(height: 14),
              _AttendanceSectionHeader(
                title: _tr(language, 'scan_attendance', 'ស្កេនវត្តមាន'),
                subtitle: _tr(
                  language,
                  'confirm_attendance',
                  'ស្កេន QR ដើម្បីបញ្ជាក់វត្តមាន',
                ),
              ),
              const SizedBox(height: 10),
              _ProminentScanCard(
                title: _tr(language, 'scan_now', 'ចុចស្កេនឥឡូវនេះ'),
                subtitle: _tr(
                  language,
                  'confirm_attendance',
                  'ស្កេន QR អង្គភាព ដើម្បីកត់វត្តមានជាមួយ GPS បច្ចុប្បន្ន។',
                ),
                buttonText: _tr(language, 'qr_scan', 'ស្កេន QR'),
                onPressed: () => _openAttendanceScanner(language),
              ),
              const SizedBox(height: 14),
              _AttendanceSectionHeader(
                title: historyTitle,
                subtitle: _tr(
                  language,
                  'latest_7_days',
                  'កំណត់ត្រា 7 ថ្ងៃចុងក្រោយ',
                ),
              ),
              const SizedBox(height: 10),
              for (final record in recentRecords) ...[
                _AttendanceRecordCard(
                  date: record.date,
                  timeInLabel: _tr(language, 'in_time', 'ម៉ោងចូល'),
                  timeIn: record.timeIn,
                  timeOutLabel: _tr(language, 'out_time', 'ម៉ោងចេញ'),
                  timeOut: record.timeOut,
                  totalLabel: _tr(language, 'total_hours', 'ម៉ោងសរុប'),
                  totalHours: record.totalHours,
                  punchesLabel: _tr(language, 'punches', 'ចំនួនស្កេន'),
                  punchCount: record.punchCount.toString(),
                  statusLabel: _tr(language, 'status', 'ស្ថានភាព'),
                  statusValue: _attendanceStatusLabel(
                    language,
                    record.attendanceStatus,
                  ),
                  statusCode: record.attendanceStatus,
                  lateLabel: _tr(language, 'late', 'មកយឺត'),
                  lateMinutes: record.lateMinutes,
                  earlyLeaveLabel: _tr(language, 'early_leave', 'ចេញមុនម៉ោង'),
                  earlyLeaveMinutes: record.earlyLeaveMinutes,
                  hasException: record.hasException == true,
                ),
                const SizedBox(height: 12),
              ],
              OutlinedButton.icon(
                onPressed: () => _openAttendanceHistory(language),
                icon: const Icon(Icons.calendar_view_month_outlined),
                label: Text(
                  _tr(language, 'view_full_history', 'មើលប្រវត្តិតាមខែ'),
                ),
              ),
              const SizedBox(height: 2),
              _AttendanceSectionHeader(
                title: _tr(language, 'additional_services', 'សេវាកម្មបន្ថែម'),
                subtitle: _tr(language, 'quick_access', 'ចូលប្រើរហ័ស'),
              ),
              const SizedBox(height: 10),
              _buildAdditionalServicesGrid(language),
            ],
          );
        },
      ),
    );
  }

  Widget _buildMissions(Map<String, String> language) {
    final listPadding = EdgeInsets.fromLTRB(
      16,
      12,
      16,
      _contentBottomPadding(context),
    );

    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<List<MissionSummary>>(
        future: _missionsFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return ListView(
              padding: const EdgeInsets.all(16),
              children: const [
                SizedBox(height: 24),
                Center(child: CircularProgressIndicator()),
              ],
            );
          }

          if (snapshot.hasError) {
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _ErrorStateCard(
                  title: _tr(language, 'mission', 'បេសកកម្ម'),
                  message: '${snapshot.error}',
                  onRetry: _refresh,
                ),
              ],
            );
          }

          final missions = snapshot.data ?? const <MissionSummary>[];
          if (missions.isEmpty) {
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _SectionCard(
                  title: _tr(language, 'mission', 'បេសកកម្ម'),
                  description: _tr(
                    language,
                    'no_missions',
                    'មិនទាន់មានបេសកម្ម',
                  ),
                ),
              ],
            );
          }

          return ListView(
            padding: listPadding,
            children: [
              for (final mission in missions) ...[
                _MissionRecordCard(
                  title: mission.title.isEmpty ? '-' : mission.title,
                  destination:
                      mission.destination.isEmpty ? '-' : mission.destination,
                  dateRange: '${mission.startDate} - ${mission.endDate}',
                  status: mission.status,
                  employeeCount: mission.employeeCount,
                  language: language,
                ),
                const SizedBox(height: 12),
              ],
            ],
          );
        },
      ),
    );
  }

  Widget _buildNoticeCenter(Map<String, String> language) {
    final listPadding = EdgeInsets.fromLTRB(
      16,
      12,
      16,
      _contentBottomPadding(context),
    );

    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<HomeNotificationPageData>(
        future: _notificationsFuture ?? _loadNotifications(),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return ListView(
              padding: listPadding,
              children: const [
                SizedBox(height: 36),
                Center(child: CircularProgressIndicator()),
              ],
            );
          }

          if (snapshot.hasError) {
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _ErrorStateCard(
                  title: _tr(language, 'notice_list', 'ជូនដំណឹង'),
                  message: '${snapshot.error}',
                  onRetry: _refresh,
                ),
              ],
            );
          }

          final noticeData =
              snapshot.data ??
              const HomeNotificationPageData(
                items: <HomeNotificationItem>[],
                unreadCount: 0,
              );
          final notices = noticeData.items;

          if (notices.isEmpty) {
            return ListView(
              padding: listPadding,
              children: [
                _SectionCard(
                  title: _tr(language, 'notice_list', 'ជូនដំណឹង'),
                  description: _tr(
                    language,
                    'no_notice_to_show',
                    'No notifications yet',
                  ),
                ),
              ],
            );
          }

          return ListView(
            padding: listPadding,
            children: [
              _AttendanceSectionHeader(
                title: _tr(language, 'notice_list', 'ជូនដំណឹង'),
                subtitle:
                    noticeData.unreadCount > 0
                        ? '${_tr(language, 'unread', 'Unread')}: ${noticeData.unreadCount}'
                        : _tr(language, 'latest_records', 'Latest updates'),
              ),
              if (noticeData.unreadCount > 0) ...[
                const SizedBox(height: 4),
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton.icon(
                    onPressed:
                        _isMarkingAllNotifications
                            ? null
                            : _markAllNotificationsAsRead,
                    icon:
                        _isMarkingAllNotifications
                            ? const SizedBox(
                              width: 14,
                              height: 14,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                            : const Icon(Icons.done_all_rounded, size: 18),
                    label: Text(
                      _tr(language, 'mark_all_read', 'Mark all as read'),
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 12),
              for (final notice in notices) ...[
                _NoticeFeedCard(
                  title: notice.title,
                  description: notice.description,
                  meta: notice.meta,
                  unread: notice.isUnread,
                  onTap:
                      notice.isUnread
                          ? () => _markNotificationAsRead(notice)
                          : null,
                  onMarkRead:
                      notice.isUnread
                          ? () => _markNotificationAsRead(notice)
                          : null,
                  actionLabel: _tr(language, 'mark_as_read', 'Mark as read'),
                ),
                const SizedBox(height: 10),
              ],
            ],
          );
        },
      ),
    );
  }

  Widget _buildBody(
    dynamic user,
    Map<String, String> language,
    ThemeData theme,
  ) {
    switch (_selectedMenu) {
      case _HomeMenuItem.dashboard:
        return _buildDashboard(user, language, theme);
      case _HomeMenuItem.attendance:
        return _buildAttendance(language);
      case _HomeMenuItem.mission:
        return _buildMissions(language);
      case _HomeMenuItem.profile:
        return _buildProfileSection(user, language, theme);
      case _HomeMenuItem.leave:
        if (user == null) {
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _SectionCard(
                title: _menuTitle(_selectedMenu, language),
                description: _tr(
                  language,
                  'wrong_info_alert',
                  'មិនមានព័ត៌មានអ្នកប្រើប្រាស់',
                ),
              ),
            ],
          );
        }

        return LeaveRequestPage(user: user, language: language);
      case _HomeMenuItem.salary:
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _SectionCard(
              title: _menuTitle(_selectedMenu, language),
              description: 'ផ្នែកនេះត្រៀមសម្រាប់ភ្ជាប់ API ខាង Laravel បន្ត។',
            ),
          ],
        );
      case _HomeMenuItem.correspondence:
        return CorrespondencePage(authController: widget.authController);
      case _HomeMenuItem.notice:
        return _buildNoticeCenter(language);
      case _HomeMenuItem.logout:
        return const SizedBox.shrink();
    }
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

        return PopScope(
          canPop: _isOnDashboard,
          onPopInvokedWithResult: (didPop, result) {
            if (!didPop && !_isOnDashboard) {
              _returnToDashboard();
            }
          },
          child: Scaffold(
            appBar: AppBar(
              automaticallyImplyLeading: false,
              leading:
                  _isOnDashboard
                      ? null
                      : IconButton(
                        onPressed: _returnToDashboard,
                        icon: const Icon(Icons.arrow_back_rounded),
                        tooltip: _tr(language, 'back', 'ត្រឡប់ក្រោយ'),
                      ),
              title: Text(
                _menuTitle(_selectedMenu, language),
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              actions: [
                if (_selectedMenu == _HomeMenuItem.dashboard) ...[
                  _TopActionIcon(
                    icon: Icons.refresh_rounded,
                    onPressed: _refresh,
                    tooltip: _tr(language, 'refresh', 'ធ្វើបច្ចុប្បន្នភាព'),
                  ),
                  const SizedBox(width: 4),
                  Builder(
                    builder:
                        (context) => _TopActionIcon(
                          icon: Icons.menu_rounded,
                          onPressed: () => Scaffold.of(context).openDrawer(),
                          tooltip: _tr(language, 'menu', 'ម៉ឺនុយ'),
                        ),
                  ),
                  const SizedBox(width: 8),
                ] else ...[
                  if (_selectedMenu == _HomeMenuItem.attendance ||
                      _selectedMenu == _HomeMenuItem.mission ||
                      _selectedMenu == _HomeMenuItem.notice ||
                      _selectedMenu == _HomeMenuItem.correspondence)
                    IconButton(
                      onPressed: _refresh,
                      icon: const Icon(Icons.refresh),
                      tooltip: _tr(language, 'refresh', 'ធ្វើបច្ចុប្បន្នភាព'),
                    ),
                  IconButton(
                    onPressed:
                        authController.isSubmitting
                            ? null
                            : () async {
                              await authController.logout();
                            },
                    icon: const Icon(Icons.logout),
                    tooltip: _tr(language, 'logout', 'ចាកចេញ'),
                  ),
                ],
              ],
            ),
            drawer: _buildDrawer(user, language),
            body: _buildBody(user, language, theme),
            bottomNavigationBar: _HomeBottomNavigation(
              currentIndex: _bottomNavIndex(),
              onTap: _onBottomNavTap,
            ),
          ),
        );
      },
    );
  }
}

enum _HomeMenuItem {
  dashboard('ទំព័រដើម'),
  attendance('វត្តមាន'),
  leave('ច្បាប់'),
  mission('បេសកកម្ម'),
  salary('ប្រាក់ខែ'),
  notice('ជូនដំណឹង'),
  correspondence('លិខិតរដ្ឋបាល'),
  profile('ព័ត៌មានផ្ទាល់ខ្លួន'),
  logout('ចាកចេញ');

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
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
      child: Material(
        color: selected ? const Color(0xFFE9F4F1) : Colors.transparent,
        borderRadius: BorderRadius.circular(8),
        child: ListTile(
          minLeadingWidth: 22,
          leading: Icon(
            icon,
            color: selected ? const Color(0xFF0B6B58) : const Color(0xFF66746E),
          ),
          title: Text(
            title,
            style: TextStyle(
              color:
                  selected ? const Color(0xFF0B6B58) : const Color(0xFF24332E),
              fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          onTap: onTap,
        ),
      ),
    );
  }
}

class _HomeBottomNavigation extends StatelessWidget {
  const _HomeBottomNavigation({
    required this.currentIndex,
    required this.onTap,
  });

  final int? currentIndex;
  final Future<void> Function(int index) onTap;

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).padding.bottom;
    final items = <({IconData icon, String label})>[
      (icon: Icons.home_outlined, label: 'គេហទំព័រ'),
      (icon: Icons.calendar_today_outlined, label: 'ប្រតិទិន'),
      (icon: Icons.notifications_none_rounded, label: 'ជូនដំណឹង'),
      (icon: Icons.settings_outlined, label: 'ការកំណត់'),
    ];

    return SafeArea(
      top: false,
      child: Padding(
        padding: EdgeInsets.fromLTRB(16, 8, 16, bottomInset > 0 ? 8 : 16),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: const Color(0xFFE3EAF0)),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0D14211D),
                blurRadius: 20,
                offset: Offset(0, 8),
              ),
            ],
          ),
          child: Row(
            children: [
              for (var i = 0; i < items.length; i++) ...[
                Expanded(
                  child: _BottomNavItem(
                    icon: items[i].icon,
                    label: items[i].label,
                    active: currentIndex != null && i == currentIndex,
                    onTap: () => onTap(i),
                  ),
                ),
                if (i != items.length - 1) const SizedBox(width: 4),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _BottomNavItem extends StatelessWidget {
  const _BottomNavItem({
    required this.icon,
    required this.label,
    required this.active,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: active ? const Color(0xFFEAF6EF) : Colors.transparent,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                icon,
                size: 22,
                color:
                    active ? const Color(0xFF2E7D61) : const Color(0xFF758695),
              ),
              const SizedBox(height: 4),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color:
                      active
                          ? const Color(0xFF2E7D61)
                          : const Color(0xFF758695),
                  fontSize: 11.5,
                  fontWeight: active ? FontWeight.w800 : FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.description});

  final String title;
  final String description;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFE3E9E6)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0D14211D),
            blurRadius: 18,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: const Color(0xFFFFF6E1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(
                Icons.construction_outlined,
                color: Color(0xFFD48516),
              ),
            ),
            const SizedBox(height: 12),
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

class _TopActionIcon extends StatelessWidget {
  const _TopActionIcon({
    required this.icon,
    required this.onPressed,
    required this.tooltip,
  });

  final IconData icon;
  final VoidCallback onPressed;
  final String tooltip;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      onPressed: onPressed,
      tooltip: tooltip,
      style: IconButton.styleFrom(
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF53687A),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
      icon: Icon(icon),
    );
  }
}

class _DashboardSectionRow extends StatelessWidget {
  const _DashboardSectionRow({required this.title, required this.onPressed});

  final String title;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.w800,
              color: const Color(0xFF10211B),
            ),
          ),
        ),
        IconButton(
          onPressed: onPressed,
          icon: const Icon(Icons.chevron_right_rounded),
          color: const Color(0xFF6B7E8C),
        ),
      ],
    );
  }
}

class _QuickActionItem {
  const _QuickActionItem({
    required this.icon,
    required this.title,
    required this.tint,
    required this.iconColor,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final Color tint;
  final Color iconColor;
  final VoidCallback onTap;
}

class _DashboardQuickActions extends StatelessWidget {
  const _DashboardQuickActions({required this.actions});

  final List<_QuickActionItem> actions;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (var i = 0; i < actions.length; i++) ...[
          Expanded(child: _QuickActionShortcut(item: actions[i])),
          if (i != actions.length - 1) const SizedBox(width: 8),
        ],
      ],
    );
  }
}

class _QuickActionShortcut extends StatelessWidget {
  const _QuickActionShortcut({required this.item});

  final _QuickActionItem item;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: item.onTap,
        borderRadius: BorderRadius.circular(16),
        child: Ink(
          padding: const EdgeInsets.fromLTRB(8, 10, 8, 10),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0xFFE4EAEE)),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0A14211D),
                blurRadius: 10,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: item.tint,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(item.icon, color: item.iconColor, size: 19),
              ),
              const SizedBox(height: 8),
              Text(
                item.title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Color(0xFF425566),
                  fontSize: 11.5,
                  height: 1.35,
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

class _DashboardTodayCard extends StatelessWidget {
  const _DashboardTodayCard({
    required this.title,
    required this.shiftLabel,
    required this.shiftValue,
    required this.statusLabel,
    required this.statusValue,
    required this.inTimeLabel,
    required this.inTime,
    required this.outTimeLabel,
    required this.outTime,
    required this.totalLabel,
    required this.totalHours,
    required this.punchesLabel,
    required this.punchCount,
    required this.lateLabel,
    required this.lateMinutes,
    required this.earlyLeaveLabel,
    required this.earlyLeaveMinutes,
    required this.buttonText,
    required this.onPressed,
  });

  final String title;
  final String shiftLabel;
  final String shiftValue;
  final String statusLabel;
  final String statusValue;
  final String inTimeLabel;
  final String inTime;
  final String outTimeLabel;
  final String outTime;
  final String totalLabel;
  final String totalHours;
  final String punchesLabel;
  final String punchCount;
  final String lateLabel;
  final int? lateMinutes;
  final String earlyLeaveLabel;
  final int? earlyLeaveMinutes;
  final String buttonText;
  final VoidCallback onPressed;

  Color _statusTone() {
    final normalized = statusValue.trim().toLowerCase();
    if (normalized.contains('late') || normalized.contains('យឺត')) {
      return const Color(0xFFCC7A1B);
    }
    if (normalized.contains('absent') || normalized.contains('អវត្តមាន')) {
      return const Color(0xFFB91C1C);
    }
    return const Color(0xFF2E7D61);
  }

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusTone();

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE4EAEE)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A14211D),
            blurRadius: 16,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(
                  Icons.check_circle,
                  color: Color(0xFF2E7D61),
                  size: 22,
                ),
                const SizedBox(width: 8),
                Text(
                  '$title :',
                  style: const TextStyle(
                    color: Color(0xFF173B33),
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFFF1F8F5), Color(0xFFF7FBFA)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: _TodayPrimaryMetric(
                          label: shiftLabel,
                          value: inTime,
                          icon: Icons.calendar_today_outlined,
                          accent: const Color(0xFF2D7864),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _TodayPrimaryMetric(
                          label: outTimeLabel,
                          value: outTime,
                          icon: Icons.logout_rounded,
                          accent: const Color(0xFF7A8B98),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: _TodaySecondaryLine(
                          label: shiftLabel,
                          value: shiftValue,
                          valueColor: const Color(0xFF35576A),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _TodaySecondaryLine(
                          label: totalLabel,
                          value: totalHours,
                          valueColor: const Color(0xFF35576A),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Expanded(
                        child: _TodaySecondaryLine(
                          label: lateLabel,
                          value: '${lateMinutes ?? 0} នាទី',
                          valueColor: const Color(0xFFD16D61),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _TodaySecondaryLine(
                          label: earlyLeaveLabel,
                          value: '${earlyLeaveMinutes ?? 0} នាទី',
                          valueColor: const Color(0xFFD16D61),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: Text(
                    '$statusLabel: $statusValue',
                    style: TextStyle(
                      color: statusColor,
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Text(
                  '$punchesLabel: $punchCount',
                  style: const TextStyle(
                    color: Color(0xFF718392),
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: onPressed,
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFF5FA47B),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(18),
                  ),
                ),
                icon: const Icon(Icons.qr_code_scanner_rounded),
                label: Text(buttonText),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TodayPrimaryMetric extends StatelessWidget {
  const _TodayPrimaryMetric({
    required this.label,
    required this.value,
    required this.icon,
    required this.accent,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, size: 16, color: accent),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Color(0xFF49606A),
                  fontWeight: FontWeight.w700,
                  fontSize: 12,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Text(
          value,
          style: const TextStyle(
            color: Color(0xFF16362F),
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    );
  }
}

class _TodaySecondaryLine extends StatelessWidget {
  const _TodaySecondaryLine({
    required this.label,
    required this.value,
    required this.valueColor,
  });

  final String label;
  final String value;
  final Color valueColor;

  @override
  Widget build(BuildContext context) {
    return RichText(
      text: TextSpan(
        style: const TextStyle(
          fontFamilyFallback: ['Noto Sans Khmer', 'Public Sans'],
        ),
        children: [
          TextSpan(
            text: '$label: ',
            style: const TextStyle(
              color: Color(0xFF718392),
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
          TextSpan(
            text: value,
            style: TextStyle(
              color: valueColor,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
    );
  }
}

class _CompactAttendanceRecordCard extends StatelessWidget {
  const _CompactAttendanceRecordCard({
    required this.date,
    required this.inTime,
    required this.outTime,
    required this.status,
    required this.statusCode,
  });

  final String date;
  final String inTime;
  final String outTime;
  final String status;
  final String? statusCode;

  Color _badgeColor() {
    final normalized = statusCode?.trim().toLowerCase() ?? '';
    if (normalized == 'on_time' ||
        normalized == 'present' ||
        normalized == 'p') {
      return const Color(0xFF2E7D61);
    }
    if (normalized.contains('late') || normalized == 'l') {
      return const Color(0xFFCC7A1B);
    }
    if (normalized.contains('leave') || normalized == 'lv') {
      return const Color(0xFF7252B8);
    }
    return const Color(0xFF5D79C8);
  }

  @override
  Widget build(BuildContext context) {
    final badgeColor = _badgeColor();

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE4EAEE)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A14211D),
            blurRadius: 12,
            offset: Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            date,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Color(0xFF233744),
              fontSize: 13,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 10),
          _CompactHistoryLine(label: 'ចូល', value: inTime),
          const SizedBox(height: 4),
          _CompactHistoryLine(label: 'ចេញ', value: outTime),
          const Spacer(),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
            decoration: BoxDecoration(
              color: badgeColor.withAlpha(18),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Text(
              status,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: badgeColor,
                fontSize: 11.5,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CompactHistoryLine extends StatelessWidget {
  const _CompactHistoryLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Text(
          '$label: ',
          style: const TextStyle(
            color: Color(0xFF7A8B98),
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
        Expanded(
          child: Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Color(0xFF233744),
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    );
  }
}

class _WelcomePanel extends StatelessWidget {
  const _WelcomePanel({
    required this.greeting,
    required this.name,
    required this.email,
    required this.employeeId,
    required this.department,
    required this.position,
    required this.initial,
  });

  final String greeting;
  final String name;
  final String email;
  final String employeeId;
  final String department;
  final String? position;
  final String initial;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final subtitle =
        position?.trim().isNotEmpty == true ? position!.trim() : department;

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2EAE7)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A14211D),
            blurRadius: 14,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 20,
                  backgroundColor: const Color(0xFFDFF2E9),
                  child: Text(
                    initial,
                    style: const TextStyle(
                      color: Color(0xFF0B6B58),
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: const Color(0xFF10211B),
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle.isEmpty ? department : subtitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF5C7068),
                          fontWeight: FontWeight.w600,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE9F4F1),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    greeting,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Color(0xFF0B6B58),
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _SoftPill(
                  icon: Icons.badge_outlined,
                  label: employeeId,
                  backgroundColor: const Color(0xFFE9F4F1),
                ),
                _SoftPill(
                  icon: Icons.apartment_outlined,
                  label: department,
                  backgroundColor: const Color(0xFFEAF1FF),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _NoticeFeedCard extends StatelessWidget {
  const _NoticeFeedCard({
    required this.title,
    required this.description,
    required this.meta,
    required this.unread,
    this.onTap,
    this.onMarkRead,
    this.actionLabel,
  });

  final String title;
  final String description;
  final String meta;
  final bool unread;
  final VoidCallback? onTap;
  final VoidCallback? onMarkRead;
  final String? actionLabel;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: unread ? const Color(0xFFD6E6FF) : const Color(0xFFE2EAE7),
            ),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0A14211D),
                blurRadius: 10,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color:
                        unread
                            ? const Color(0xFFEAF1FF)
                            : const Color(0xFFF2F4F7),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(
                    unread
                        ? Icons.notifications_active_outlined
                        : Icons.notifications_none,
                    color:
                        unread
                            ? const Color(0xFF1D4F91)
                            : const Color(0xFF64748B),
                    size: 18,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              title,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Color(0xFF10211B),
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          if (unread)
                            Container(
                              width: 8,
                              height: 8,
                              decoration: const BoxDecoration(
                                color: Color(0xFF1D4F91),
                                shape: BoxShape.circle,
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        description,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF334155),
                          fontSize: 13,
                          height: 1.45,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      const SizedBox(height: 7),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Expanded(
                            child: Text(
                              meta,
                              style: const TextStyle(
                                color: Color(0xFF64748B),
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                          if (onMarkRead != null)
                            TextButton(
                              onPressed: onMarkRead,
                              style: TextButton.styleFrom(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                ),
                                minimumSize: const Size(0, 30),
                                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                              ),
                              child: Text(
                                (actionLabel ?? 'Mark as read').trim(),
                                style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _AttendanceScanActionCard extends StatelessWidget {
  const _AttendanceScanActionCard({
    required this.title,
    required this.description,
    required this.buttonText,
    required this.onPressed,
  });

  final String title;
  final String description;
  final String buttonText;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(8),
        gradient: const LinearGradient(
          colors: [Color(0xFFE8F4FF), Color(0xFFEAF8F2)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        border: Border.all(color: const Color(0xFFCDE0F0)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Icon(
                    Icons.qr_code_scanner_outlined,
                    color: Color(0xFF174C88),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    title,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                      color: const Color(0xFF14211D),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              description,
              style: const TextStyle(
                color: Color(0xFF2A3B36),
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: onPressed,
              icon: const Icon(Icons.qr_code),
              label: Text(buttonText),
            ),
          ],
        ),
      ),
    );
  }
}

class _AttendanceSectionHeader extends StatelessWidget {
  const _AttendanceSectionHeader({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w800,
            color: const Color(0xFF10211B),
          ),
        ),
        const SizedBox(height: 2),
        Text(
          subtitle,
          style: const TextStyle(
            color: Color(0xFF5C7068),
            fontSize: 13,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

class _TodayAttendanceStatusCard extends StatelessWidget {
  const _TodayAttendanceStatusCard({
    required this.shiftLabel,
    required this.shiftValue,
    required this.statusLabel,
    required this.statusValue,
    required this.inTimeLabel,
    required this.inTime,
    required this.outTimeLabel,
    required this.outTime,
  });

  final String shiftLabel;
  final String shiftValue;
  final String statusLabel;
  final String statusValue;
  final String inTimeLabel;
  final String inTime;
  final String outTimeLabel;
  final String outTime;

  Color _statusTone(String status) {
    final normalized = status.trim().toLowerCase();
    if (normalized.contains('on time') ||
        normalized.contains('មានវត្តមាន') ||
        normalized == 'on_time') {
      return const Color(0xFF0B6B58);
    }
    if (normalized.contains('late') || normalized.contains('យឺត')) {
      return const Color(0xFFA85C00);
    }
    if (normalized.contains('absent') || normalized.contains('អវត្តមាន')) {
      return const Color(0xFFD34B5F);
    }
    return const Color(0xFF1D4F91);
  }

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusTone(statusValue);

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE3ECE7)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A14211D),
            blurRadius: 14,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF3FF),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(
                    Icons.today_outlined,
                    color: Color(0xFF1D4F91),
                    size: 19,
                  ),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        shiftLabel,
                        style: const TextStyle(
                          color: Color(0xFF5C7068),
                          fontWeight: FontWeight.w600,
                          fontSize: 12,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        shiftValue,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF14211D),
                          fontSize: 15,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: statusColor.withAlpha(22),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    '$statusLabel: $statusValue',
                    style: TextStyle(
                      color: statusColor,
                      fontWeight: FontWeight.w800,
                      fontSize: 12,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _SoftPill(
                  icon: Icons.login,
                  label: '$inTimeLabel: $inTime',
                  backgroundColor: const Color(0xFFE9F4F1),
                ),
                _SoftPill(
                  icon: Icons.logout,
                  label: '$outTimeLabel: $outTime',
                  backgroundColor: const Color(0xFFFFF6E1),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ProminentScanCard extends StatelessWidget {
  const _ProminentScanCard({
    required this.title,
    required this.subtitle,
    required this.buttonText,
    required this.onPressed,
  });

  final String title;
  final String subtitle;
  final String buttonText;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: const LinearGradient(
          colors: [Color(0xFF0B6B58), Color(0xFF16508A)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x2414211D),
            blurRadius: 18,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            top: -30,
            right: -12,
            child: Container(
              width: 120,
              height: 120,
              decoration: BoxDecoration(
                color: Colors.white.withAlpha(20),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    Icon(Icons.qr_code_scanner_rounded, color: Colors.white),
                    SizedBox(width: 8),
                    Text(
                      'ស្កេន QR វត្តមាន',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                        fontSize: 16,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                    fontSize: 15,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: Color(0xFFE7F1F5),
                    fontWeight: FontWeight.w500,
                    height: 1.45,
                  ),
                ),
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: onPressed,
                    style: FilledButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: const Color(0xFF0B6B58),
                      elevation: 0,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    icon: const Icon(Icons.qr_code),
                    label: Text(
                      buttonText,
                      style: const TextStyle(fontWeight: FontWeight.w800),
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

class _AdditionalServiceCard extends StatelessWidget {
  const _AdditionalServiceCard({
    required this.icon,
    required this.title,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Ink(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0xFFE3ECE7)),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0814211D),
                blurRadius: 10,
                offset: Offset(0, 4),
              ),
            ],
          ),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Row(
                children: [
                  Container(
                    width: 34,
                    height: 34,
                    decoration: BoxDecoration(
                      color: const Color(0xFFE9F4F1),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(icon, color: const Color(0xFF0B6B58), size: 18),
                  ),
                  const Spacer(),
                  const Icon(
                    Icons.arrow_forward_ios_rounded,
                    size: 13,
                    color: Color(0xFF7A8D86),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 13,
                  height: 1.35,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF14211D),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _AttendanceRecordCard extends StatelessWidget {
  const _AttendanceRecordCard({
    required this.date,
    required this.timeInLabel,
    required this.timeIn,
    required this.timeOutLabel,
    required this.timeOut,
    required this.totalLabel,
    required this.totalHours,
    required this.punchesLabel,
    required this.punchCount,
    required this.statusLabel,
    required this.statusValue,
    this.statusCode,
    required this.lateLabel,
    required this.earlyLeaveLabel,
    this.lateMinutes,
    this.earlyLeaveMinutes,
    this.hasException = false,
  });

  final String date;
  final String timeInLabel;
  final String timeIn;
  final String timeOutLabel;
  final String timeOut;
  final String totalLabel;
  final String totalHours;
  final String punchesLabel;
  final String punchCount;
  final String statusLabel;
  final String statusValue;
  final String? statusCode;
  final String lateLabel;
  final String earlyLeaveLabel;
  final int? lateMinutes;
  final int? earlyLeaveMinutes;
  final bool hasException;

  String get _normalizedStatus {
    final source = (statusCode ?? statusValue).trim().toLowerCase();
    return source;
  }

  Color _statusBackgroundColor() {
    final normalized = _normalizedStatus;
    if (normalized == 'on_time' ||
        normalized == 'present' ||
        normalized == 'p') {
      return const Color(0xFFE9F4F1);
    }
    if (normalized == 'late' || normalized == 'l') {
      return const Color(0xFFFFF1E5);
    }
    if (normalized == 'early_leave') {
      return const Color(0xFFFFF5E9);
    }
    if (normalized == 'late_and_early_leave') {
      return const Color(0xFFFFECE5);
    }
    if (normalized == 'mission' || normalized == 'm') {
      return const Color(0xFFEFF3FF);
    }
    if (normalized == 'leave' || normalized == 'lv') {
      return const Color(0xFFEDE9FF);
    }
    if (normalized == 'holiday' || normalized == 'h') {
      return const Color(0xFFF2F4F7);
    }
    if (normalized == 'day_off' || normalized == 'off' || normalized == 'o') {
      return const Color(0xFFF3F4F6);
    }
    if (normalized == 'absent' ||
        normalized == 'a' ||
        normalized.contains('incomplete') ||
        hasException) {
      return const Color(0xFFFFEEF1);
    }

    return const Color(0xFFEFF3FF);
  }

  Color _statusTextColor() {
    final normalized = _normalizedStatus;
    if (normalized == 'on_time' ||
        normalized == 'present' ||
        normalized == 'p') {
      return const Color(0xFF0B6B58);
    }
    if (normalized == 'late' || normalized == 'l') {
      return const Color(0xFFA85C00);
    }
    if (normalized == 'early_leave') {
      return const Color(0xFF9A4D00);
    }
    if (normalized == 'late_and_early_leave') {
      return const Color(0xFF9A2F00);
    }
    if (normalized == 'mission' || normalized == 'm') {
      return const Color(0xFF1D4F91);
    }
    if (normalized == 'leave' || normalized == 'lv') {
      return const Color(0xFF5B2D82);
    }
    if (normalized == 'holiday' || normalized == 'h') {
      return const Color(0xFF3D495A);
    }
    if (normalized == 'day_off' || normalized == 'off' || normalized == 'o') {
      return const Color(0xFF4B5563);
    }
    if (normalized == 'absent' ||
        normalized == 'a' ||
        normalized.contains('incomplete') ||
        hasException) {
      return const Color(0xFFD34B5F);
    }

    return const Color(0xFF1D4F91);
  }

  bool get _hasLate => (lateMinutes ?? 0) > 0;
  bool get _hasEarlyLeave => (earlyLeaveMinutes ?? 0) > 0;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE3ECE7)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A14211D),
            blurRadius: 16,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE9F4F1),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(
                    Icons.access_time_outlined,
                    color: Color(0xFF0B6B58),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    date,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                      color: const Color(0xFF14211D),
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: _statusBackgroundColor(),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: Colors.white.withAlpha(150)),
                  ),
                  child: Text(
                    '$statusLabel: $statusValue',
                    style: TextStyle(
                      color: _statusTextColor(),
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _SoftPill(
                  icon: Icons.login,
                  label: '$timeInLabel: $timeIn',
                  backgroundColor: const Color(0xFFE9F4F1),
                ),
                _SoftPill(
                  icon: Icons.logout,
                  label: '$timeOutLabel: $timeOut',
                  backgroundColor: const Color(0xFFFFF6E1),
                ),
                _SoftPill(
                  icon: Icons.timer_outlined,
                  label: '$totalLabel: $totalHours',
                  backgroundColor: const Color(0xFFEFF3FF),
                ),
                _SoftPill(
                  icon: Icons.touch_app_outlined,
                  label: '$punchesLabel: $punchCount',
                  backgroundColor: const Color(0xFFFFEEF1),
                ),
                if (_hasLate)
                  _SoftPill(
                    icon: Icons.warning_amber_outlined,
                    label: '$lateLabel: $lateMinutes min',
                    backgroundColor: const Color(0xFFFFF1E5),
                  ),
                if (_hasEarlyLeave)
                  _SoftPill(
                    icon: Icons.outbox_outlined,
                    label: '$earlyLeaveLabel: $earlyLeaveMinutes min',
                    backgroundColor: const Color(0xFFFFEEF1),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _MissionRecordCard extends StatelessWidget {
  const _MissionRecordCard({
    required this.title,
    required this.destination,
    required this.dateRange,
    required this.status,
    required this.employeeCount,
    required this.language,
  });

  final String title;
  final String destination;
  final String dateRange;
  final String status;
  final int employeeCount;
  final Map<String, String> language;

  String _tr(String key, String fallback) {
    final value = language[key]?.trim();
    if (value == null || value.isEmpty) {
      return fallback;
    }

    return value;
  }

  Color _statusColor(String value) {
    final normalized = value.trim().toLowerCase();
    switch (normalized) {
      case 'approved':
        return const Color(0xFF0B6B58);
      case 'pending':
        return const Color(0xFFA85C00);
      case 'rejected':
      case 'cancelled':
        return const Color(0xFFD34B5F);
      default:
        return const Color(0xFF3D495A);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tone = _statusColor(status);
    final normalizedStatus = status.trim().isEmpty ? '-' : status.toUpperCase();

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE3ECE7)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A14211D),
            blurRadius: 16,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF3FF),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.work_outline,
                    color: Color(0xFF1D4F91),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    title,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF14211D),
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: tone.withAlpha(24),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    normalizedStatus,
                    style: TextStyle(
                      color: tone,
                      fontWeight: FontWeight.w800,
                      fontSize: 11,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _SoftPill(
                  icon: Icons.apartment_outlined,
                  label: '${_tr('destination', 'គោលដៅ')}: $destination',
                  backgroundColor: const Color(0xFFEFF3FF),
                ),
                _SoftPill(
                  icon: Icons.date_range_outlined,
                  label: dateRange,
                  backgroundColor: const Color(0xFFE9F4F1),
                ),
                _SoftPill(
                  icon: Icons.group_outlined,
                  label: '${_tr('employee', 'បុគ្គលិក')}: $employeeCount',
                  backgroundColor: const Color(0xFFFFF1E5),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorStateCard extends StatelessWidget {
  const _ErrorStateCard({
    required this.title,
    required this.message,
    required this.onRetry,
  });

  final String title;
  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFF0CED5)),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.error_outline, color: Color(0xFFD34B5F)),
          const SizedBox(height: 10),
          Text(
            title,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w900,
              color: const Color(0xFF14211D),
            ),
          ),
          const SizedBox(height: 8),
          Text(message, style: const TextStyle(color: Color(0xFF60736A))),
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: const Text('សាកម្តងទៀត'),
          ),
        ],
      ),
    );
  }
}

class _SoftPill extends StatelessWidget {
  const _SoftPill({
    required this.icon,
    required this.label,
    required this.backgroundColor,
  });

  final IconData icon;
  final String label;
  final Color backgroundColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 260),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: backgroundColor.withAlpha(232),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.white.withAlpha(92)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: const Color(0xFF0B6B58)),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              label.isEmpty ? '-' : label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Color(0xFF24332E),
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
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
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withAlpha(214),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFFD9E9E1)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: const Color(0xFF0B6B58)),
          const SizedBox(width: 6),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 180),
            child: Text(
              text!,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 12,
                color: Color(0xFF173C33),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfileHighlightChip {
  const _ProfileHighlightChip({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;
}

class _ProfileHeroCard extends StatelessWidget {
  const _ProfileHeroCard({
    required this.avatar,
    required this.name,
    required this.position,
    required this.department,
    required this.role,
    required this.chips,
    required this.badges,
  });

  final Widget avatar;
  final String name;
  final String position;
  final String department;
  final String? role;
  final List<_ProfileHighlightChip> chips;
  final List<Widget> badges;

  @override
  Widget build(BuildContext context) {
    final visibleBadges =
        badges.where((widget) => widget is! SizedBox).toList();

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        gradient: const LinearGradient(
          colors: [Color(0xFF0C6A58), Color(0xFF1C4A8D), Color(0xFF7AB8A3)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          stops: [0.0, 0.58, 1.0],
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x2610322A),
            blurRadius: 28,
            offset: Offset(0, 16),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            top: -38,
            right: -10,
            child: Container(
              width: 148,
              height: 148,
              decoration: BoxDecoration(
                color: Colors.white.withAlpha(24),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Positioned(
            bottom: -44,
            left: -20,
            child: Container(
              width: 164,
              height: 164,
              decoration: BoxDecoration(
                color: Colors.white.withAlpha(18),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(3),
                      decoration: BoxDecoration(
                        color: Colors.white.withAlpha(28),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: avatar,
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 5,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.white.withAlpha(34),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: const Text(
                              'ព័ត៌មានផ្ទាល់ខ្លួន',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                                letterSpacing: 0.4,
                              ),
                            ),
                          ),
                          const SizedBox(height: 10),
                          Text(
                            name,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 22,
                              fontWeight: FontWeight.w800,
                              height: 1.15,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            position,
                            style: const TextStyle(
                              color: Color(0xFFF4F8FA),
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            department,
                            style: const TextStyle(
                              color: Color(0xFFD9E7ED),
                              fontSize: 13,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                          if ((role ?? '').trim().isNotEmpty) ...[
                            const SizedBox(height: 10),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 6,
                              ),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFFF2C8),
                                borderRadius: BorderRadius.circular(999),
                              ),
                              child: Text(
                                role!.trim(),
                                style: const TextStyle(
                                  color: Color(0xFF5F4A00),
                                  fontSize: 12,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                LayoutBuilder(
                  builder: (context, constraints) {
                    final isCompact = constraints.maxWidth < 380;
                    final crossAxisCount = isCompact ? 1 : 2;
                    final cardHeight = isCompact ? 92.0 : 102.0;

                    return GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: chips.length,
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: crossAxisCount,
                        crossAxisSpacing: 10,
                        mainAxisSpacing: 10,
                        mainAxisExtent: cardHeight,
                      ),
                      itemBuilder: (context, index) {
                        final chip = chips[index];
                        return Container(
                          padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
                          decoration: BoxDecoration(
                            color: Colors.white.withAlpha(26),
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(
                              color: Colors.white.withAlpha(34),
                            ),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Container(
                                width: 30,
                                height: 30,
                                decoration: BoxDecoration(
                                  color: Colors.white.withAlpha(34),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Icon(
                                  chip.icon,
                                  size: 16,
                                  color: Colors.white,
                                ),
                              ),
                              Text(
                                chip.label,
                                style: const TextStyle(
                                  color: Color(0xFFD8E8EC),
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              Text(
                                chip.value,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w800,
                                  height: 1.2,
                                ),
                              ),
                            ],
                          ),
                        );
                      },
                    );
                  },
                ),
                if (visibleBadges.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  Wrap(spacing: 8, runSpacing: 8, children: visibleBadges),
                ],
              ],
            ),
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

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE3ECE7)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A14211D),
            blurRadius: 18,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  Color(0xFFF4FAF7),
                  Color(0xFFEDF7F2),
                  Color(0xFFF7FBFC),
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.only(
                topLeft: Radius.circular(24),
                topRight: Radius.circular(24),
              ),
            ),
            child: Row(
              children: [
                if (icon != null) ...[
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: const Color(0xFFDFF2E9),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(icon, size: 20, color: const Color(0xFF0B6B58)),
                  ),
                  const SizedBox(width: 12),
                ],
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                          color: Color(0xFF123E34),
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        style: TextStyle(
                          fontSize: 11,
                          color: Colors.grey[600],
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: Color(0xFFE8EFEC)),
          for (int i = 0; i < mainVisible.length; i++) ...[
            _buildRow(mainVisible[i]),
            if (i < mainVisible.length - 1)
              const Divider(height: 1, indent: 18, endIndent: 18),
          ],
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
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 132,
            child: Text(
              row.label,
              style: TextStyle(
                color: Colors.grey[600],
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          Expanded(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FBF9),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: const Color(0xFFE3ECE7)),
              ),
              child: Text(
                row.value,
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                  color: Color(0xFF163A31),
                  height: 1.3,
                ),
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
          padding: const EdgeInsets.fromLTRB(18, 16, 18, 10),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
            decoration: BoxDecoration(
              color: const Color(0xFFEAF6F0),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Text(
              sub.label,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w800,
                color: Color(0xFF0B6B58),
                letterSpacing: 0.2,
              ),
            ),
          ),
        ),
        for (int i = 0; i < visible.length; i++) ...[
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 0, 18, 10),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  width: 114,
                  child: Text(
                    visible[i].label,
                    style: TextStyle(
                      color: Colors.grey[600],
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 10,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FBF9),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: const Color(0xFFE3ECE7)),
                    ),
                    child: Text(
                      visible[i].value,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 12,
                        color: Color(0xFF163A31),
                        height: 1.3,
                      ),
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
