import 'package:flutter/material.dart';

import '../../../../core/theme/app_design_system.dart';
import '../../../auth/models/auth_user.dart';

enum HomeMenuItem {
  dashboard,
  attendance,
  leave,
  leaveReview,
  mission,
  salary,
  notice,
  correspondence,
  profile,
  logout,
}

/// Which live count (if any) a drawer row's badge shows. The count itself is
/// resolved at render time from data `HomePage` has already fetched — kept
/// out of [HomeDrawerEntry] so the entry list can stay `const`.
enum HomeDrawerBadgeKind { none, pendingLeaveReviews, unreadNotices }

bool _alwaysVisible(AuthUser user) => true;

bool _canReviewLeave(AuthUser user) => user.canReviewLeaveRequests;

/// One row in the Home drawer's main navigation list: an icon (shown in a
/// colored badge), a translation key/fallback for its title, the tab it
/// switches to, who can see it, and which live count (if any) its badge
/// shows.
///
/// Adding a new module to the drawer means adding one entry here (plus a
/// body case in `HomePage._buildBody`) instead of hand-writing another
/// `DrawerMenuTile` block and a matching switch case in `_menuTitle`.
class HomeDrawerEntry {
  const HomeDrawerEntry({
    required this.icon,
    required this.menuItem,
    required this.titleKey,
    required this.titleFallback,
    required this.badgeTint,
    required this.badgeIconColor,
    this.visibleTo = _alwaysVisible,
    this.badgeKind = HomeDrawerBadgeKind.none,
  });

  final IconData icon;
  final HomeMenuItem menuItem;
  final String titleKey;
  final String titleFallback;

  /// Background tint of this row's icon badge.
  final Color badgeTint;

  /// Icon color inside this row's badge.
  final Color badgeIconColor;

  /// Whether the current user should see this row at all.
  final bool Function(AuthUser user) visibleTo;

  /// Which live count this row's notification badge (if any) reflects.
  final HomeDrawerBadgeKind badgeKind;
}

const List<HomeDrawerEntry> homeDrawerEntries = [
  HomeDrawerEntry(
    icon: Icons.dashboard_outlined,
    menuItem: HomeMenuItem.dashboard,
    titleKey: 'dashboard',
    titleFallback: 'ផ្ទាំងគ្រប់គ្រង',
    badgeTint: Color(0xFFE9F4F1),
    badgeIconColor: AppDesignSystem.primary,
  ),
  HomeDrawerEntry(
    icon: Icons.access_time_outlined,
    menuItem: HomeMenuItem.attendance,
    titleKey: 'attendance_history',
    titleFallback: 'ប្រវត្តិវត្តមាន',
    badgeTint: Color(0xFFEAF1FF),
    badgeIconColor: Color(0xFF5D79C8),
  ),
  HomeDrawerEntry(
    icon: Icons.event_note_outlined,
    menuItem: HomeMenuItem.leave,
    titleKey: 'leave_type',
    titleFallback: 'ការសុំច្បាប់',
    badgeTint: Color(0xFFFFF4E5),
    badgeIconColor: Color(0xFFD79C2E),
  ),
  HomeDrawerEntry(
    icon: Icons.fact_check_outlined,
    menuItem: HomeMenuItem.leaveReview,
    titleKey: 'leave_review',
    titleFallback: 'ពិនិត្យច្បាប់',
    badgeTint: Color(0xFFFFF4E5),
    badgeIconColor: Color(0xFFD79C2E),
    visibleTo: _canReviewLeave,
    badgeKind: HomeDrawerBadgeKind.pendingLeaveReviews,
  ),
  HomeDrawerEntry(
    icon: Icons.work_outline,
    menuItem: HomeMenuItem.mission,
    titleKey: 'mission',
    titleFallback: 'បេសកកម្ម',
    badgeTint: Color(0xFFF1EEFF),
    badgeIconColor: Color(0xFF7C5CBF),
  ),
  HomeDrawerEntry(
    icon: Icons.account_balance_wallet_outlined,
    menuItem: HomeMenuItem.salary,
    titleKey: 'salary_details',
    titleFallback: 'ព័ត៌មានប្រាក់ខែ',
    badgeTint: Color(0xFFE6F7F2),
    badgeIconColor: Color(0xFF1D8A6E),
  ),
  HomeDrawerEntry(
    icon: Icons.campaign_outlined,
    menuItem: HomeMenuItem.notice,
    titleKey: 'notice_list',
    titleFallback: 'ជូនដំណឹង',
    badgeTint: Color(0xFFFFECEC),
    badgeIconColor: Color(0xFFD34B5F),
    badgeKind: HomeDrawerBadgeKind.unreadNotices,
  ),
  HomeDrawerEntry(
    icon: Icons.mail_outlined,
    menuItem: HomeMenuItem.correspondence,
    titleKey: 'correspondence',
    titleFallback: 'លិខិតរដ្ឋបាល',
    badgeTint: Color(0xFFEAF1FF),
    badgeIconColor: Color(0xFF1D4F91),
  ),
  HomeDrawerEntry(
    icon: Icons.person_outline,
    menuItem: HomeMenuItem.profile,
    titleKey: 'my_profile',
    titleFallback: 'ព័ត៌មានផ្ទាល់ខ្លួន',
    badgeTint: Color(0xFFF1F4F6),
    badgeIconColor: Color(0xFF4B5563),
  ),
  HomeDrawerEntry(
    icon: Icons.logout,
    menuItem: HomeMenuItem.logout,
    titleKey: 'logout',
    titleFallback: 'ចាកចេញ',
    badgeTint: Color(0xFFFFECEC),
    badgeIconColor: Color(0xFFD34B5F),
  ),
];

/// Badge colors for the Settings row, which lives outside [homeDrawerEntries]
/// because it opens a route instead of switching a [HomeMenuItem] tab.
const Color homeSettingsBadgeTint = Color(0xFFF1F4F6);
const Color homeSettingsBadgeIconColor = Color(0xFF4B5563);
