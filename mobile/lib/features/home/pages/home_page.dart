import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../../core/config/app_routes.dart';
import '../../../core/config/api_config.dart';
import '../../../core/localization/laravel_language_service.dart';
import '../../../core/network/api_exception.dart';
import '../../auth/controllers/auth_controller.dart';
import '../../correspondence/pages/correspondence_page.dart';
import '../../system/pages/help_support_page.dart';
import '../models/attendance_day_record.dart';
import '../models/home_notification_item.dart';
import '../models/leave_request_models.dart';
import '../models/mission_summary.dart';
import 'mission_detail_page.dart';
import 'attendance_history_page.dart';
import 'attendance_statistics_page.dart';
import '../../system/pages/settings_home_page.dart';
import '../../system/pages/logout_confirm_dialog.dart';
import 'leave_history_page.dart';
import 'leave_review_page.dart';
import 'leave_request_page.dart';
import 'attendance_scan_page.dart';
import '../services/home_attendance_service.dart';
import '../services/home_leave_service.dart';
import '../services/home_mission_service.dart';
import '../services/home_notification_service.dart';
import '../services/home_profile_service.dart';
import '../../auth/models/auth_user.dart';
import 'home/home_menu.dart';
import 'home/home_nav_widgets.dart';
import 'home/home_common_widgets.dart';
import 'home/home_dashboard_widgets.dart';
import 'home/leave_balance_widgets.dart';
import 'home/home_notice_widgets.dart';
import 'home/home_attendance_widgets.dart';
import 'home/home_mission_widgets.dart';
import 'home/home_profile_widgets.dart';
import 'home/home_theme.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key, required this.authController});

  final AuthController authController;

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  late final HomeAttendanceService _attendanceService;
  late final HomeMissionService _missionService;
  late final HomeNotificationService _notificationService;
  late final HomeProfileService _profileService;
  late final HomeLeaveService _leaveService;
  late final Future<Map<String, String>> _languageFuture;
  Future<LeaveSummary>? _dashboardLeaveSummaryFuture;
  Future<List<AttendanceDayRecord>>? _dashboardAttendanceFuture;
  Future<List<MissionSummary>>? _missionsFuture;
  Future<HomeNotificationPageData>? _notificationsFuture;
  Future<AuthUser>? _profileFuture;
  Future<int>? _pendingLeaveCountFuture;
  Future<PackageInfo>? _packageInfoFuture;
  bool _isMarkingAllNotifications = false;

  /// Set by [CorrespondencePage] via `onRefreshReady` so the shared shell
  /// AppBar's refresh action can trigger that page's own list reload — it
  /// no longer renders its own topbar/refresh button.
  Future<void> Function()? _correspondenceRefresh;

  HomeMenuItem _selectedMenu = HomeMenuItem.dashboard;

  bool get _isOnDashboard => _selectedMenu == HomeMenuItem.dashboard;

  double _contentBottomPadding(BuildContext context, {double base = 24}) {
    final inset = MediaQuery.of(context).padding.bottom;
    return base + (inset < 12 ? 12 : inset);
  }

  @override
  void initState() {
    super.initState();
    _attendanceService = HomeAttendanceService();
    _missionService = HomeMissionService();
    _notificationService = HomeNotificationService();
    _profileService = HomeProfileService();
    _leaveService = HomeLeaveService();
    _languageFuture = LaravelLanguageService.instance.load();
    _dashboardLeaveSummaryFuture = _loadDashboardLeaveSummary();
    // Fetch fresh profile data from backend
    _profileFuture = _profileService.fetchProfile();
    // Keep dashboard startup lightweight to avoid UI stalls on slower devices.
    _dashboardAttendanceFuture = _loadAttendance(
      fromDate: DateTime.now().subtract(const Duration(days: 7)),
      toDate: DateTime.now(),
    );
    _missionsFuture = null;
    _notificationsFuture = null;
    _pendingLeaveCountFuture = _loadPendingLeaveCount();
  }

  /// Pending leave count for the "សំណើរង់ចាំ" summary card and the drawer's
  /// "ពិនិត្យច្បាប់" badge: reviewers see requests awaiting their review;
  /// everyone else sees their own still-pending requests.
  Future<int> _loadPendingLeaveCount() async {
    final user = widget.authController.currentUser;
    if (user == null) {
      return 0;
    }

    try {
      if (user.canReviewLeaveRequests) {
        final pendingReviews = await _leaveService.fetchPendingReviews(user);
        return pendingReviews.length;
      }

      final ownRequests = await _leaveService.fetchRequests(user);
      return ownRequests
          .where((request) => request.status.trim().toLowerCase() == 'pending')
          .length;
    } catch (_) {
      return 0;
    }
  }

  String _tr(Map<String, String> language, String key, String fallback) {
    final value = language[key]?.trim();
    if (value == null || value.isEmpty) {
      return fallback;
    }

    return value;
  }

  String _menuTitle(HomeMenuItem item, Map<String, String> language) {
    final entry = homeDrawerEntries.firstWhere((e) => e.menuItem == item);
    return _tr(language, entry.titleKey, entry.titleFallback);
  }

  int? _bottomNavIndex() {
    switch (_selectedMenu) {
      case HomeMenuItem.dashboard:
        return 0;
      case HomeMenuItem.attendance:
        return 1;
      case HomeMenuItem.mission:
        return 3;
      case HomeMenuItem.profile:
        return 4;
      default:
        return null;
    }
  }

  Future<void> _onBottomNavTap(int index, Map<String, String> language) async {
    switch (index) {
      case 0:
        _switchToMenu(HomeMenuItem.dashboard);
        return;
      case 1:
        await _openAttendanceHistory(language);
        return;
      case 2:
        await _openAttendanceScanner(language);
        return;
      case 3:
        _switchToMenu(HomeMenuItem.mission);
        return;
      case 4:
        _switchToMenu(HomeMenuItem.profile);
        return;
    }
  }

  /// Each leave type's balance is fetched and shown on its own — leave days
  /// from different types are never summed into one combined figure, since
  /// each type draws from its own, unrelated entitlement.
  Future<LeaveSummary> _loadDashboardLeaveSummary() async {
    final user = widget.authController.currentUser;
    if (user == null) {
      return const LeaveSummary(totalRemaining: 0, types: <LeaveBalanceItem>[]);
    }

    return _leaveService.fetchSummary(user);
  }

  Future<List<AttendanceDayRecord>> _loadAttendance({
    DateTime? fromDate,
    DateTime? toDate,
  }) {
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
        .fetchAttendanceHistory(user, fromDate: fromDate, toDate: toDate)
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

  String _apiRoot() {
    final uri = Uri.parse(ApiConfig.baseUrl);
    var path = uri.path;

    if (path.endsWith('/')) {
      path = path.substring(0, path.length - 1);
    }

    if (path.endsWith('/api')) {
      path = path.substring(0, path.length - 4);
    }

    if (path.isEmpty) {
      return _apiOrigin();
    }

    return '${_apiOrigin()}$path';
  }

  String? _resolveProfileImageUrl(String? rawValue) {
    final value = rawValue?.trim();
    if (value == null || value.isEmpty) {
      return null;
    }

    final origin = _apiOrigin();
    final apiRoot = _apiRoot();

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
      if (value.startsWith('/storage/')) {
        return '$apiRoot$value';
      }
      return '$origin$value';
    }

    if (value.startsWith('storage/')) {
      return '$apiRoot/$value';
    }

    return '$apiRoot/storage/$value';
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

  void _switchToMenu(HomeMenuItem item) {
    if (_selectedMenu == item) {
      return;
    }

    setState(() {
      _selectedMenu = item;
      if (item == HomeMenuItem.mission && _missionsFuture == null) {
        _missionsFuture = _loadMissions();
      }
      if (item == HomeMenuItem.notice && _notificationsFuture == null) {
        _notificationsFuture = _loadNotifications();
      }
      if (item == HomeMenuItem.profile) {
        // Re-create future when entering profile so failed attempts can recover.
        _profileFuture = _profileService.fetchProfile();
      }
    });
  }

  void _returnToDashboard() {
    _switchToMenu(HomeMenuItem.dashboard);
  }

  Widget _buildTopNotificationAction(Map<String, String> language) {
    final notificationsFuture = _notificationsFuture ??= _loadNotifications();

    return FutureBuilder<HomeNotificationPageData>(
      future: notificationsFuture,
      builder: (context, snapshot) {
        final unreadCount = snapshot.data?.unreadCount ?? 0;

        return HomeNotificationBellAction(
          unreadCount: unreadCount,
          onPressed: () => _switchToMenu(HomeMenuItem.notice),
          tooltip: _tr(language, 'all_notifications', 'ការជូនដំណឹង'),
        );
      },
    );
  }

  void _openAdditionalService(
    HomeMenuItem item,
    Map<String, String> language, {
    String? message,
  }) {
    _switchToMenu(item);

    if (message != null && message.trim().isNotEmpty) {
      _showServiceMessage(message);
    }
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

    final result = await Navigator.of(context).push<HomeMenuItem>(
      MaterialPageRoute<HomeMenuItem>(
        builder:
            (_) => AttendanceHistoryPage(
              user: user,
              attendanceService: _attendanceService,
              language: language,
            ),
      ),
    );

    if (result != null && mounted) {
      _switchToMenu(result);
    }
  }

  Future<void> _openAttendanceStatistics(Map<String, String> language) async {
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

    final result = await Navigator.of(context).push<HomeMenuItem>(
      MaterialPageRoute<HomeMenuItem>(
        builder:
            (_) => AttendanceStatisticsPage(
              user: user,
              attendanceService: _attendanceService,
              language: language,
            ),
      ),
    );

    if (result != null && mounted) {
      _switchToMenu(result);
    }
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
        _dashboardAttendanceFuture = _loadAttendance(
          fromDate: DateTime.now().subtract(const Duration(days: 7)),
          toDate: DateTime.now(),
        );
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

    // Shorthand to build a HomeProfileRow
    HomeProfileRow r(String label, String? value) =>
        HomeProfileRow(label: label, value: value ?? '');

    // Build profile picture URL
    final picUrl = _resolveProfileImageUrl(user.profilePic as String?);

    Widget avatar;
    if (picUrl != null && picUrl.isNotEmpty) {
      avatar = CircleAvatar(
        radius: 44,
        backgroundColor: const Color(0xFFE7EFEB),
        backgroundImage: NetworkImage(picUrl),
        onBackgroundImageError: (_, __) {},
      );
    } else {
      avatar = CircleAvatar(
        radius: 44,
        backgroundColor: homeAccentColor(),
        child: Text(
          (user.name as String).isNotEmpty
              ? (user.name as String)[0].toUpperCase()
              : 'U',
          style: const TextStyle(
            fontSize: 32,
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

    final profileCards = <Widget>[
      ProfileHeroCard(
        avatar: avatar,
        name: user.name as String,
        position: positionText,
        department: departmentText,
        role: user.role as String?,
        chips: [
          ProfileHighlightChip(
            icon: Icons.badge_outlined,
            label: 'អត្តលេខ',
            value:
                (user.employeeCode as String?)?.isNotEmpty == true
                    ? user.employeeCode as String
                    : ((user.cardNo as String?)?.isNotEmpty == true
                        ? user.cardNo as String
                        : '${user.employeeId}'),
          ),
          ProfileHighlightChip(
            icon: Icons.account_tree_outlined,
            label: 'កាំប្រាក់',
            value: payLevelText,
          ),
          ProfileHighlightChip(
            icon: Icons.calendar_month_outlined,
            label: 'ថ្ងៃចូលបម្រើ',
            value: serviceDateText,
          ),
          ProfileHighlightChip(
            icon: Icons.call_outlined,
            label: 'ទំនាក់ទំនង',
            value: contactText,
          ),
        ],
        badges: [
          if ((user.employeeCode as String?)?.isNotEmpty == true ||
              (user.cardNo as String?)?.isNotEmpty == true)
            HomeInfoBadge(
              icon: Icons.credit_card_outlined,
              text:
                  (user.employeeCode as String?)?.isNotEmpty == true
                      ? user.employeeCode as String
                      : user.cardNo as String?,
            ),
          if ((user.phone as String?)?.isNotEmpty == true)
            HomeInfoBadge(
              icon: Icons.phone_outlined,
              text: user.phone as String,
            ),
          if ((user.email as String).isNotEmpty)
            HomeInfoBadge(
              icon: Icons.email_outlined,
              text: user.email as String,
            ),
        ],
      ),
      ProfileSection(
        icon: Icons.person_outline,
        title: 'ព័ត៌មានផ្ទាល់ខ្លួន',
        subtitle: 'ព័ត៌មានបុគ្គល',
        initiallyExpanded: true,
        rows: [
          r(_tr(language, 'gender', 'ភេទ'), user.gender as String?),
          r('ថ្ងៃខែឆ្នាំកំណើត', _formatDateDisplay(user.dateOfBirth)),
          r('ស្ថានភាពអាពាហ៍ពិពាហ៍', user.maritalStatus as String?),
          r('សញ្ជាតិ', user.nationality as String?),
          r('សាសនា', user.religion as String?),
          r('ជនជាតិ/ក្រុម', user.ethnicGroup as String?),
        ],
        subsections: [
          ProfileSubsection(
            label: 'ទំនាក់ទំនង',
            rows: [
              r(_tr(language, 'phone', 'ទូរស័ព្ទ'), user.phone as String?),
              r('ទូរស័ព្ទបន្ត', user.alternatePhone as String?),
              r(_tr(language, 'email', 'អ៊ីមែល'), user.email as String),
            ],
          ),
          ProfileSubsection(
            label: 'អាសយដ្ឋាន',
            rows: [
              r('បច្ចុប្បន្ន', user.presentAddress as String?),
              r('អចិន្ត្រៃយ៍', user.permanentAddress as String?),
            ],
          ),
        ],
      ),
      ProfileSection(
        icon: Icons.card_giftcard_outlined,
        title: 'អត្តសញ្ញាណ និងឯកសារ',
        subtitle: 'អត្តសញ្ញាណ និងឯកសារផ្លូវការ',
        rows: [
          r('លេខអត្តសញ្ញាណប័ណ្ណ', user.nationalId as String?),
          r('លេខឯកសារ', user.legalDocumentNumber as String?),
          r('ប្រភេទឯកសារ', user.legalDocumentType as String?),
        ],
      ),
      ProfileSection(
        icon: Icons.business,
        title: 'ព័ត៌មានអង្គភាព និងការងារ',
        subtitle: 'ព័ត៌មានការងារ',
        subsections: [
          ProfileSubsection(
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
          ProfileSubsection(
            label: 'តួនាទី និងអង្គភាព',
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
          ProfileSubsection(
            label: 'កាលបរិច្ឆេទ',
            rows: [
              r('ថ្ងៃចូលបម្រើ', _formatDateDisplay(user.serviceStartDate)),
              r('ថ្ងៃជួលចូល', _formatDateDisplay(user.hireDate)),
              r('ថ្ងៃចូលធ្វើការ', _formatDateDisplay(user.joiningDate)),
              r(
                'ចាប់ផ្ដើមកិច្ចសន្យា',
                _formatDateDisplay(user.contractStartDate),
              ),
              r('ផុតកំណត់កិច្ចសន្យា', _formatDateDisplay(user.contractEndDate)),
            ],
          ),
          ProfileSubsection(
            label: 'ស្ថានភាព',
            rows: [
              r('ស្ថានភាពការងារ', user.workStatusName as String?),
              r('ស្ថានភាពពេញសិទ្ធ', fullRightText),
              r('ថ្ងៃពេញសិទ្ធ', _formatDateDisplay(user.fullRightDate)),
            ],
          ),
        ],
      ),
    ];

    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFFF3F8F6), Color(0xFFFAFCFB)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: ListView.separated(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(14, 16, 14, 24),
        itemCount: profileCards.length,
        separatorBuilder: (_, __) => const SizedBox(height: 14),
        itemBuilder: (context, index) => profileCards[index],
      ),
    );
  }

  Future<void> _refresh() async {
    if (_selectedMenu == HomeMenuItem.mission) {
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

    if (_selectedMenu == HomeMenuItem.notice) {
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

    if (_selectedMenu == HomeMenuItem.profile) {
      setState(() {
        _profileFuture = _profileService.fetchProfile(forceRefresh: true);
      });

      try {
        await _profileFuture;
      } catch (_) {
        // FutureBuilder renders fallback profile data when profile fetch fails.
      }
      return;
    }

    if (_selectedMenu == HomeMenuItem.correspondence) {
      final refresh = _correspondenceRefresh;
      if (refresh != null) {
        await refresh();
      }
      return;
    }

    setState(() {
      _dashboardLeaveSummaryFuture = _loadDashboardLeaveSummary();
      _dashboardAttendanceFuture = _loadAttendance(
        fromDate: DateTime.now().subtract(const Duration(days: 7)),
        toDate: DateTime.now(),
      );
    });

    try {
      await _dashboardAttendanceFuture;
    } catch (_) {
      // FutureBuilder renders error state for failed dashboard requests.
    }
  }

  Future<void> _markNotificationAsRead(HomeNotificationItem item) async {
    if (!item.isUnread || item.id.trim().isEmpty) {
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

  Future<void> _openNotificationItem(HomeNotificationItem item) async {
    if (item.isUnread) {
      await _markNotificationAsRead(item);
      if (!mounted) {
        return;
      }
    }

    await _navigateNotificationTarget(item);
  }

  HomeMenuItem _resolveNotificationTarget(HomeNotificationItem item) {
    final source = item.source.trim().toLowerCase();
    final link = (item.link ?? '').trim().toLowerCase();

    if (source == 'leave_workflow' || link.contains('/hr/leaves')) {
      return HomeMenuItem.leave;
    }

    if (source == 'attendance_workflow' ||
        link.contains('/attendance-adjustments') ||
        link.contains('/attendance')) {
      return HomeMenuItem.attendance;
    }

    if (source == 'correspondence_workflow' ||
        link.contains('/correspondence')) {
      return HomeMenuItem.correspondence;
    }

    return HomeMenuItem.notice;
  }

  Future<void> _navigateNotificationTarget(HomeNotificationItem item) async {
    final user = widget.authController.currentUser;
    if (user == null) {
      _switchToMenu(_resolveNotificationTarget(item));
      return;
    }

    final language = await _languageFuture;
    if (!mounted) {
      return;
    }
    final source = item.source.trim().toLowerCase();
    final audience = item.audienceLabel.trim().toLowerCase();

    if (source == 'leave_workflow') {
      final isReviewerAudience =
          audience.contains('អ្នកអនុម័ត') ||
          audience.contains('អ្នកពិនិត្យ') ||
          audience.contains('approver') ||
          audience.contains('reviewer');

      if (isReviewerAudience) {
        await Navigator.of(context).push<void>(
          MaterialPageRoute<void>(
            builder:
                (_) => LeaveReviewPage(
                  user: user,
                  language: language,
                  leaveService: _leaveService,
                ),
          ),
        );
        return;
      }

      await Navigator.of(context).push<void>(
        MaterialPageRoute<void>(
          builder:
              (_) => LeaveHistoryPage(
                user: user,
                language: language,
                leaveService: _leaveService,
                types: const [],
              ),
        ),
      );
      return;
    }

    final target = _resolveNotificationTarget(item);
    if (target == HomeMenuItem.attendance) {
      await _openAttendanceHistory(language);
      return;
    }

    _switchToMenu(target);
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

  Future<void> _onMenuTap(HomeMenuItem item) async {
    Navigator.of(context).pop();

    if (item == HomeMenuItem.logout) {
      final confirmed = await showLogoutConfirmDialog(context);
      if (confirmed == true && mounted) {
        await widget.authController.logout();
      }
      return;
    }

    if (item == HomeMenuItem.attendance) {
      final language = await _languageFuture;
      if (!mounted) return;
      await _openAttendanceHistory(language);
      return;
    }

    _switchToMenu(item);
  }

  Future<void> _openSystemSettingsFromDrawer() async {
    Navigator.of(context).pop();
    await Navigator.of(context).pushNamed(AppRoutes.systemSettings);
  }

  Future<void> _openSettingsHome() async {
    Navigator.of(context).pop();
    final result = await Navigator.of(context).push<HomeMenuItem>(
      MaterialPageRoute<HomeMenuItem>(
        builder: (_) => SettingsHomePage(authController: widget.authController),
      ),
    );
    if (result != null && mounted) {
      _switchToMenu(result);
    }
  }

  String _userInitial(dynamic user) {
    final name = user?.name?.toString().trim() ?? '';
    if (name.isEmpty) {
      return 'U';
    }

    return name.substring(0, 1).toUpperCase();
  }

  /// Monday..Saturday of the week containing today — the Figma day-selector's
  /// fixed 6-day work week.
  List<DateTime> _currentWeekDates() {
    final now = DateTime.now();
    final monday = DateTime(now.year, now.month, now.day - (now.weekday - 1));
    return List<DateTime>.generate(
      6,
      (i) => DateTime(monday.year, monday.month, monday.day + i),
    );
  }

  AttendanceDayRecord? _recordForDate(
    List<AttendanceDayRecord> records,
    DateTime date,
  ) {
    final key = _formatDateKey(date);
    for (final record in records) {
      if (record.date.startsWith(key)) {
        return record;
      }
    }
    return null;
  }

  /// "4.5ម៉ / 8ម៉" — worked hours (one decimal, trimmed) over the assumed
  /// 8-hour expected shift also used by [_todayHoursPercent].
  String _workedHoursShort(AttendanceDayRecord? record) {
    final raw = record?.totalHours.trim();
    if (raw == null || raw.isEmpty || raw == '-') {
      return '0ម៉ / 8ម៉';
    }

    final parts = raw.split(':');
    final hours = int.tryParse(parts.elementAt(0)) ?? 0;
    final minutes = parts.length > 1 ? int.tryParse(parts[1]) ?? 0 : 0;
    final decimal = hours + minutes / 60;
    final text =
        decimal == decimal.roundToDouble()
            ? decimal.toStringAsFixed(0)
            : decimal.toStringAsFixed(1);
    return '$textម៉ / 8ម៉';
  }

  /// Count of the loaded week's records that need the employee's attention
  /// (an HR-flagged exception, or any non-clean status) — drives the
  /// drawer's "វត្តមាន" badge.
  int _attendanceAlertCount(List<AttendanceDayRecord> records) {
    const alertStatuses = {
      'late',
      'early_leave',
      'late_and_early_leave',
      'incomplete',
      'partial',
      'unpaired_punch',
    };
    return records.where((record) {
      if (record.hasException == true) return true;
      final status = record.attendanceStatus?.trim().toLowerCase();
      return status != null && alertStatuses.contains(status);
    }).length;
  }

  String _liveWorkStatusLabel(AttendanceDayRecord? todayRecord) {
    if (todayRecord == null || todayRecord.timeIn.trim() == '-') {
      return 'មិនទាន់ចូលធ្វើការ';
    }
    if (todayRecord.timeOut.trim() == '-') {
      return 'កំពុងធ្វើការ';
    }
    return 'បញ្ចប់ការងារថ្ងៃនេះ';
  }

  Widget _buildDrawer(AuthUser? user, Map<String, String> language) {
    final positionText =
        (user?.positionKm?.trim().isNotEmpty == true)
            ? user!.positionKm!.trim()
            : ((user?.position?.trim().isNotEmpty == true)
                ? user!.position!.trim()
                : '-');
    final organizationText =
        (user?.departmentName?.trim().isNotEmpty == true)
            ? user!.departmentName!.trim()
            : _tr(language, 'organization', 'អង្គភាព');
    final canReviewLeave = user?.canReviewLeaveRequests == true;

    return FutureBuilder<int>(
      future: _pendingLeaveCountFuture ??= _loadPendingLeaveCount(),
      builder: (context, pendingSnapshot) {
        final pendingLeaveCount = pendingSnapshot.data ?? 0;

        return FutureBuilder<HomeNotificationPageData>(
          future: _notificationsFuture ??= _loadNotifications(),
          builder: (context, noticeSnapshot) {
            final unreadNoticeCount = noticeSnapshot.data?.unreadCount ?? 0;

            return FutureBuilder<List<AttendanceDayRecord>>(
              future: _dashboardAttendanceFuture,
              builder: (context, attendanceSnapshot) {
                final attendanceAlertCount = _attendanceAlertCount(
                  attendanceSnapshot.data ?? const <AttendanceDayRecord>[],
                );

                return Drawer(
                  backgroundColor: const Color(0xFFF7F9F8),
                  child: SafeArea(
                    child: Column(
                      children: [
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              colors: [
                                dashboardHeaderStart,
                                dashboardHeaderEnd,
                              ],
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                            ),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.center,
                                children: [
                                  CircleAvatar(
                                    radius: 28,
                                    backgroundColor: Colors.white.withAlpha(28),
                                    backgroundImage:
                                        _resolveProfileImageUrl(
                                                  user?.profilePic,
                                                ) !=
                                                null
                                            ? NetworkImage(
                                              _resolveProfileImageUrl(
                                                user?.profilePic,
                                              )!,
                                            )
                                            : null,
                                    onBackgroundImageError:
                                        _resolveProfileImageUrl(
                                                  user?.profilePic,
                                                ) !=
                                                null
                                            ? (_, __) {}
                                            : null,
                                    child:
                                        _resolveProfileImageUrl(
                                                  user?.profilePic,
                                                ) ==
                                                null
                                            ? Text(
                                              _userInitial(user),
                                              style: const TextStyle(
                                                color: Colors.white,
                                                fontWeight: FontWeight.w800,
                                                fontSize: 18,
                                              ),
                                            )
                                            : null,
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Text(
                                          user?.name ?? 'User',
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(
                                            color: Colors.white,
                                            fontWeight: FontWeight.w700,
                                            fontSize: 16,
                                          ),
                                        ),
                                        const SizedBox(height: 3),
                                        Text(
                                          positionText,
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: TextStyle(
                                            color: Colors.white.withAlpha(217),
                                            fontSize: 11,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 14),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 6,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.white.withAlpha(28),
                                  borderRadius: BorderRadius.circular(999),
                                  border: Border.all(
                                    color: Colors.white.withAlpha(52),
                                  ),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(
                                      Icons.apartment_rounded,
                                      size: 16,
                                      color: Colors.white,
                                    ),
                                    const SizedBox(width: 8),
                                    Flexible(
                                      child: Text(
                                        organizationText,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontSize: 11,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        Expanded(
                          child: ListView(
                            padding: const EdgeInsets.all(12),
                            children: [
                              DrawerSectionCard(
                                icon: Icons.person_outline,
                                title: _tr(language, 'drawer_account', 'គណនី'),
                                rows: [
                                  DrawerActionRow(
                                    icon: Icons.person_outline,
                                    label: _tr(
                                      language,
                                      'my_profile',
                                      'ប្រវត្តិរូប',
                                    ),
                                    onTap:
                                        () => _onMenuTap(HomeMenuItem.profile),
                                  ),
                                  DrawerActionRow(
                                    icon: Icons.qr_code_scanner_rounded,
                                    label: _tr(language, 'qr_scan', 'ស្កេន QR'),
                                    onTap: () {
                                      Navigator.of(context).pop();
                                      _openAttendanceScanner(language);
                                    },
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              DrawerSectionCard(
                                icon: Icons.grid_view_rounded,
                                title: _tr(
                                  language,
                                  'drawer_management',
                                  'ការគ្រប់គ្រង',
                                ),
                                rows: [
                                  DrawerActionRow(
                                    icon: Icons.calendar_month_outlined,
                                    label: _tr(
                                      language,
                                      'attendance',
                                      'វត្តមាន',
                                    ),
                                    badgeCount: attendanceAlertCount,
                                    onTap:
                                        () =>
                                            _onMenuTap(HomeMenuItem.attendance),
                                  ),
                                  DrawerActionRow(
                                    icon: Icons.description_outlined,
                                    label: _tr(
                                      language,
                                      'leave_requests_drawer',
                                      'សំណើរច្បាប់',
                                    ),
                                    badgeCount: pendingLeaveCount,
                                    onTap:
                                        () => _onMenuTap(
                                          canReviewLeave
                                              ? HomeMenuItem.leaveReview
                                              : HomeMenuItem.leave,
                                        ),
                                  ),
                                  DrawerActionRow(
                                    icon: Icons.work_outline,
                                    label: _tr(language, 'mission', 'បេសកកម្ម'),
                                    onTap:
                                        () => _onMenuTap(HomeMenuItem.mission),
                                  ),
                                  DrawerActionRow(
                                    icon: Icons.mail_outlined,
                                    label: _tr(
                                      language,
                                      'correspondence',
                                      'លិខិតរដ្ឋបាល',
                                    ),
                                    onTap:
                                        () => _onMenuTap(
                                          HomeMenuItem.correspondence,
                                        ),
                                  ),
                                  DrawerActionRow(
                                    icon: Icons.account_balance_wallet_outlined,
                                    label: _tr(
                                      language,
                                      'salary_details',
                                      'ព័ត៌មានប្រាក់ខែ',
                                    ),
                                    onTap:
                                        () => _onMenuTap(HomeMenuItem.salary),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              DrawerSectionCard(
                                icon: Icons.settings_outlined,
                                title: _tr(language, 'system', 'ប្រព័ន្ធ'),
                                rows: [
                                  DrawerActionRow(
                                    icon: Icons.notifications_none_rounded,
                                    label: _tr(
                                      language,
                                      'notice_list',
                                      'ការជូនដំណឹង',
                                    ),
                                    badgeCount: unreadNoticeCount,
                                    onTap:
                                        () => _onMenuTap(HomeMenuItem.notice),
                                  ),
                                  DrawerActionRow(
                                    icon: Icons.tune_rounded,
                                    label: _tr(
                                      language,
                                      'settings',
                                      'ការកំណត់',
                                    ),
                                    onTap: _openSettingsHome,
                                  ),
                                  DrawerActionRow(
                                    icon: Icons.phonelink_setup_outlined,
                                    label: _tr(language, 'devices', 'ឧបករណ៍'),
                                    onTap: _openSystemSettingsFromDrawer,
                                  ),
                                ],
                              ),
                              const SizedBox(height: 20),
                              DrawerLogoutRow(
                                label: _tr(
                                  language,
                                  'logout',
                                  'ចាកចេញពីប្រព័ន្ធ',
                                ),
                                onTap: () => _onMenuTap(HomeMenuItem.logout),
                              ),
                              const SizedBox(height: 12),
                              Center(
                                child: FutureBuilder<PackageInfo>(
                                  future:
                                      _packageInfoFuture ??=
                                          PackageInfo.fromPlatform(),
                                  builder: (context, packageSnapshot) {
                                    final version =
                                        packageSnapshot.data?.version;
                                    return Text(
                                      version == null
                                          ? 'PHD HRM'
                                          : 'PHD HRM v$version',
                                      style: const TextStyle(
                                        fontSize: 11,
                                        color: Color(0xFF718078),
                                      ),
                                    );
                                  },
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            );
          },
        );
      },
    );
  }

  Widget _buildDashboard(
    dynamic user,
    Map<String, String> language,
    ThemeData theme,
  ) {
    final attendanceFuture =
        _dashboardAttendanceFuture ??= _loadAttendance(
          fromDate: DateTime.now().subtract(const Duration(days: 7)),
          toDate: DateTime.now(),
        );

    return FutureBuilder<HomeNotificationPageData>(
      future: _notificationsFuture ??= _loadNotifications(),
      builder: (context, noticeSnapshot) {
        final unreadNoticeCount = noticeSnapshot.data?.unreadCount ?? 0;

        return RefreshIndicator(
          onRefresh: _refresh,
          child: FutureBuilder<List<AttendanceDayRecord>>(
            future: attendanceFuture,
            builder: (context, attendanceSnapshot) {
              if (attendanceSnapshot.connectionState ==
                  ConnectionState.waiting) {
                return const Center(child: CircularProgressIndicator());
              }

              final records =
                  attendanceSnapshot.data ?? const <AttendanceDayRecord>[];
              final todayRecord = _findTodayRecord(records);
              final hasActiveSession =
                  todayRecord != null &&
                  todayRecord.timeIn.trim() != '-' &&
                  todayRecord.timeOut.trim() == '-';
              final todayKey = _formatDateKey(DateTime.now());
              final weekDays =
                  _currentWeekDates()
                      .map(
                        (date) => DashboardWeekDay(
                          date: date,
                          label: khmerWeekdayShort(date),
                          percent:
                              _todayHoursPercent(
                                _recordForDate(records, date),
                              ) *
                              100,
                          isToday: _formatDateKey(date) == todayKey,
                        ),
                      )
                      .toList();
              final workStatusName =
                  (user?.workStatusName?.trim().isNotEmpty == true)
                      ? user!.workStatusName!.trim() as String
                      : '-';

              return ListView(
                padding: EdgeInsets.zero,
                children: [
                  DashboardProfileHeader(
                    appName: 'PHD HRM',
                    organization: (user?.departmentName ?? '-').toString(),
                    nameAndPosition:
                        '@${user?.name ?? 'User'} | ${(user?.positionKm ?? user?.position) ?? '-'}',
                    initial: _userInitial(user),
                    unreadNotifications: unreadNoticeCount,
                    onMenuTap: () => Scaffold.of(context).openDrawer(),
                    onNotificationsTap:
                        () => _switchToMenu(HomeMenuItem.notice),
                    onAvatarTap: () => _switchToMenu(HomeMenuItem.profile),
                    onLanguageTap: _openSettingsHome,
                    onHelpTap:
                        () => Navigator.of(context).push<void>(
                          MaterialPageRoute<void>(
                            builder: (_) => const HelpSupportPage(),
                          ),
                        ),
                    statusPrimaryLabel: workStatusName,
                    statusSecondaryLabel: _liveWorkStatusLabel(todayRecord),
                    profileImageUrl: _resolveProfileImageUrl(
                      user?.profilePic?.toString(),
                    ),
                  ),
                  DashboardWeekStrip(days: weekDays),
                  Container(
                    width: double.infinity,
                    color: const Color(0xFFF7F9F8),
                    padding: const EdgeInsets.fromLTRB(12, 10, 12, 4),
                    child: DashboardActionsGrid(
                      actions: [
                        DashboardActionItem(
                          icon: Icons.qr_code_scanner_rounded,
                          label: _tr(
                            language,
                            'qr_scan_attendance',
                            'ស្កេន QR កូដវត្តមាន',
                          ),
                          color: const Color(0xFF6C5CE7),
                          badgeLabel: hasActiveSession ? 'ONLINE' : null,
                          onTap: () => _openAttendanceScanner(language),
                        ),
                        DashboardActionItem(
                          icon: Icons.event_note_outlined,
                          label: _tr(
                            language,
                            'submit_leave_request',
                            'ដាក់សំណើសុំច្បាប់',
                          ),
                          color: dashboardHeaderStart,
                          onTap:
                              () => _openAdditionalService(
                                HomeMenuItem.leave,
                                language,
                                message: _tr(
                                  language,
                                  'service_redirect_leave',
                                  'សូមដាក់សំណើច្បាប់ រួចរង់ចាំការអនុម័ត។',
                                ),
                              ),
                        ),
                        DashboardActionItem(
                          icon: Icons.description_outlined,
                          label: _tr(
                            language,
                            'correspondence',
                            'លិខិតផ្លូវការ',
                          ),
                          color: const Color(0xFFF39C12),
                          onTap:
                              () => _openAdditionalService(
                                HomeMenuItem.correspondence,
                                language,
                              ),
                        ),
                        DashboardActionItem(
                          icon: Icons.badge_outlined,
                          label: _tr(language, 'staff_info', 'ព័ត៌មានមន្ត្រី'),
                          color: const Color(0xFF00838F),
                          onTap: () => _switchToMenu(HomeMenuItem.profile),
                        ),
                        DashboardActionItem(
                          icon: Icons.flag_outlined,
                          label: _tr(
                            language,
                            'my_missions',
                            'បេសកកម្មរបស់ខ្ញុំ',
                          ),
                          color: const Color(0xFF5C6BC0),
                          onTap:
                              () => _openAdditionalService(
                                HomeMenuItem.mission,
                                language,
                              ),
                        ),
                      ],
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                    child: GestureDetector(
                      onTap: () => _openAttendanceStatistics(language),
                      child: DashboardWorkStatusCard(
                        title: _tr(
                          language,
                          'today_status',
                          'ស្ថានភាពការធ្វើការថ្ងៃនេះ',
                        ),
                        sessions: todayRecord?.sessions ?? const [],
                        emptyLabel: _tr(
                          language,
                          'no_shift_today',
                          'មិនទាន់មានវេនបង្ហាញ',
                        ),
                        provisionalNote:
                            todayRecord?.isProvisional == true
                                ? _tr(
                                  language,
                                  'attendance_provisional',
                                  'វត្តមានថ្ងៃនេះកំពុងកត់ត្រា មិនទាន់ជាលទ្ធផលចុងក្រោយ។',
                                )
                                : null,
                        progressLabel: _tr(
                          language,
                          'work_progress',
                          'វឌ្ឍភាពការងារ',
                        ),
                        progressValue: _workedHoursShort(todayRecord),
                        percent: _todayHoursPercent(todayRecord),
                        checkInLabel: _tr(language, 'check_in', 'ចូល'),
                        checkOutLabel: _tr(language, 'check_out', 'ចេញ'),
                        waitingLabel: _tr(language, 'waiting', 'រង់ចាំ'),
                        onTimeLabel: _tr(language, 'on_time', 'ទាន់ពេល'),
                        lateLabel: _tr(language, 'late', 'មកយឺត'),
                        earlyLeaveLabel: _tr(
                          language,
                          'early_leave',
                          'ចេញមុនម៉ោង',
                        ),
                      ),
                    ),
                  ),
                  FutureBuilder<LeaveSummary>(
                    future:
                        _dashboardLeaveSummaryFuture ??=
                            _loadDashboardLeaveSummary(),
                    builder: (context, leaveSnapshot) {
                      final types =
                          leaveSnapshot.data?.types ??
                          const <LeaveBalanceItem>[];
                      if (types.isEmpty) {
                        return const SizedBox.shrink();
                      }

                      final displays = buildLeaveBalanceDisplays(
                        types,
                        language,
                      );

                      return Padding(
                        padding: EdgeInsets.fromLTRB(
                          16,
                          12,
                          16,
                          _contentBottomPadding(context),
                        ),
                        child: Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            boxShadow: const [
                              BoxShadow(
                                color: Color(0x14173529),
                                blurRadius: 12,
                                offset: Offset(0, 4),
                              ),
                            ],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Icon(
                                    Icons.calendar_month_outlined,
                                    size: 18,
                                    color: dashboardHeaderEnd,
                                  ),
                                  const SizedBox(width: 8),
                                  Text(
                                    _tr(
                                      language,
                                      'leave_balance',
                                      'សមតុល្យច្បាប់',
                                    ),
                                    style: TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w800,
                                      color: dashboardHeaderEnd,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 14),
                              for (var i = 0; i < displays.length; i++) ...[
                                GestureDetector(
                                  onTap:
                                      () => _openAdditionalService(
                                        HomeMenuItem.leave,
                                        language,
                                      ),
                                  child: LeaveBalanceProgressRow(
                                    item: displays[i],
                                  ),
                                ),
                                if (i != displays.length - 1)
                                  const SizedBox(height: 14),
                              ],
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ],
              );
            },
          ),
        );
      },
    );
  }

  /// Progress through today's expected working hours (0.0-1.0), driving
  /// the Dashboard attendance ring. Based on an assumed 8-hour shift since
  /// the backend doesn't provide a per-employee expected-hours figure.
  double _todayHoursPercent(AttendanceDayRecord? record) {
    final raw = record?.totalHours.trim();
    if (raw == null || raw.isEmpty || raw == '-') {
      return 0;
    }

    final parts = raw.split(':');
    final hours = int.tryParse(parts.elementAt(0)) ?? 0;
    final minutes = parts.length > 1 ? int.tryParse(parts[1]) ?? 0 : 0;
    const expectedHours = 8;
    return ((hours + minutes / 60) / expectedHours).clamp(0.0, 1.0);
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
                HomeErrorStateCard(
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
                HomeSectionCard(
                  title: _tr(language, 'mission', 'បេសកកម្ម'),
                  description: _tr(
                    language,
                    'no_missions',
                    'មិនទាន់មានបេសកកម្ម',
                  ),
                ),
              ],
            );
          }

          return ListView(
            padding: listPadding,
            children: [
              for (final mission in missions) ...[
                InkWell(
                  borderRadius: BorderRadius.circular(24),
                  onTap: () async {
                    await Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder:
                            (_) => MissionDetailPage(missionId: mission.id),
                      ),
                    );
                    if (mounted) await _refresh();
                  },
                  child: MissionRecordCard(
                    title: mission.title.isEmpty ? '-' : mission.title,
                    destination:
                        mission.destination.isEmpty ? '-' : mission.destination,
                    dateRange:
                        '${_formatDateDisplay(mission.startDate)} - ${_formatDateDisplay(mission.endDate)}',
                    status: mission.status,
                    employeeCount: mission.employeeCount,
                    language: language,
                  ),
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
    final notificationsFuture = _notificationsFuture ??= _loadNotifications();
    final listPadding = EdgeInsets.fromLTRB(
      16,
      12,
      16,
      _contentBottomPadding(context),
    );

    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<HomeNotificationPageData>(
        future: notificationsFuture,
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
                HomeErrorStateCard(
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
                HomeSectionCard(
                  title: _tr(language, 'notice_list', 'ជូនដំណឹង'),
                  description: _tr(
                    language,
                    'no_notice_to_show',
                    'មិនទាន់មានជូនដំណឹង',
                  ),
                ),
              ],
            );
          }

          return ListView(
            padding: listPadding,
            children: [
              AttendanceSectionHeader(
                title: _tr(language, 'notice_list', 'ជូនដំណឹង'),
                subtitle:
                    noticeData.unreadCount > 0
                        ? '${_tr(language, 'unread', 'មិនទាន់អាន')}: ${noticeData.unreadCount}'
                        : _tr(language, 'latest_records', 'ព័ត៌មានថ្មីៗ'),
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
                      _tr(language, 'mark_all_read', 'សម្គាល់ថាបានអានទាំងអស់'),
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 12),
              for (final notice in notices) ...[
                NoticeFeedCard(
                  title: notice.title,
                  description: notice.description,
                  meta: notice.meta,
                  typeLabel: notice.typeLabel,
                  dateLabel: notice.dateLabel,
                  audienceLabel: notice.audienceLabel,
                  contextLabel: notice.contextLabel,
                  stepName: notice.stepName,
                  source: notice.source,
                  unread: notice.isUnread,
                  onTap: () => _openNotificationItem(notice),
                  onMarkRead:
                      notice.isUnread
                          ? () => _markNotificationAsRead(notice)
                          : null,
                  actionLabel: _tr(language, 'mark_as_read', 'សម្គាល់ថាបានអាន'),
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
      case HomeMenuItem.dashboard:
        return _buildDashboard(user, language, theme);
      case HomeMenuItem.attendance:
        // Reached only when a notification resolves to attendance while no
        // user session is active; a real session is always routed to
        // AttendanceHistoryPage via _openAttendanceHistory instead.
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            HomeSectionCard(
              title: _menuTitle(_selectedMenu, language),
              description: _tr(
                language,
                'wrong_info_alert',
                'មិនមានព័ត៌មានអ្នកប្រើប្រាស់',
              ),
            ),
          ],
        );
      case HomeMenuItem.mission:
        return _buildMissions(language);
      case HomeMenuItem.profile:
        // Fetch fresh profile data from backend
        return FutureBuilder<AuthUser>(
          future: _profileFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const ProfileLoadingSkeleton();
            }
            if (snapshot.hasError) {
              // Fallback to cached user data if fetch fails
              return _buildProfileSection(user, language, theme);
            }
            final profile = snapshot.data ?? user;
            return _buildProfileSection(profile, language, theme);
          },
        );
      case HomeMenuItem.leave:
        if (user == null) {
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              HomeSectionCard(
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
      case HomeMenuItem.leaveReview:
        if (user == null) {
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              HomeSectionCard(
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

        return LeaveReviewPage(
          user: user,
          language: language,
          leaveService: _leaveService,
        );
      case HomeMenuItem.salary:
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            HomeSectionCard(
              title: _menuTitle(_selectedMenu, language),
              description: 'ផ្នែកនេះត្រៀមសម្រាប់ភ្ជាប់ API ខាង Laravel បន្ត។',
            ),
          ],
        );
      case HomeMenuItem.correspondence:
        return CorrespondencePage(
          authController: widget.authController,
          onRefreshReady: (refresh) => _correspondenceRefresh = refresh,
        );
      case HomeMenuItem.notice:
        return _buildNoticeCenter(language);
      case HomeMenuItem.logout:
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

        return AnnotatedRegion<SystemUiOverlayStyle>(
          value: SystemUiOverlayStyle.light,
          child: PopScope(
            canPop: _isOnDashboard,
            onPopInvokedWithResult: (didPop, result) {
              if (!didPop && !_isOnDashboard) {
                _returnToDashboard();
              }
            },
            child: Scaffold(
              // The Dashboard tab renders its own full-bleed hero header
              // (DashboardHeroHeader) instead of a standard AppBar.
              appBar:
                  _isOnDashboard
                      ? null
                      : AppBar(
                        backgroundColor: homeAccentColor(),
                        foregroundColor: Colors.white,
                        systemOverlayStyle: SystemUiOverlayStyle.light,
                        automaticallyImplyLeading: false,
                        leading: IconButton(
                          onPressed: _returnToDashboard,
                          icon: const Icon(Icons.arrow_back_rounded),
                          tooltip: _tr(language, 'back', 'ត្រឡប់ក្រោយ'),
                        ),
                        title: Text(
                          _menuTitle(_selectedMenu, language),
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            color: Colors.white,
                          ),
                        ),
                        actions: [
                          _buildTopNotificationAction(language),
                          const SizedBox(width: 4),
                          if (_selectedMenu == HomeMenuItem.attendance ||
                              _selectedMenu == HomeMenuItem.mission ||
                              _selectedMenu == HomeMenuItem.notice ||
                              _selectedMenu == HomeMenuItem.correspondence)
                            IconButton(
                              onPressed: _refresh,
                              icon: const Icon(Icons.refresh),
                              tooltip: _tr(
                                language,
                                'refresh',
                                'ធ្វើបច្ចុប្បន្នភាព',
                              ),
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
                      ),
              drawer: _buildDrawer(user, language),
              body: _buildBody(user, language, theme),
              bottomNavigationBar: HomeBottomNavigation(
                currentIndex: _bottomNavIndex(),
                onTap: (index) => _onBottomNavTap(index, language),
              ),
            ),
          ),
        );
      },
    );
  }
}
