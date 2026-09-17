import 'package:flutter/material.dart';

import '../../../../core/theme/app_design_system.dart';

/// The app's weekday-seeded brand color (see [AppDesignSystem.colorForWeekday]).
///
/// Widgets that have a [BuildContext] should prefer
/// `Theme.of(context).colorScheme.primary`, which [StaffMobileApp] seeds with
/// this same color -- this helper exists for the few call sites (plain
/// helper methods, not `build()`) that don't have a context handy.
Color homeAccentColor() =>
    AppDesignSystem.colorForWeekday(DateTime.now().weekday);

/// Brand gradient for the redesigned Dashboard header and its Navigation
/// Drawer (Figma "04 - Dashboard") — seeded by the same weekday accent as
/// [homeAccentColor] so both surfaces change color together with the rest
/// of the app, rather than staying a fixed green.
Color get dashboardHeaderStart => homeAccentColor();

/// Darker shade of [dashboardHeaderStart], used for the header's gradient
/// second stop and for on-white text/icon accents that need more contrast.
Color get dashboardHeaderEnd =>
    Color.lerp(homeAccentColor(), Colors.black, 0.25)!;

const _khmerWeekdays = <String>[
  'ចន្ទ',
  'អង្គារ',
  'ពុធ',
  'ព្រហស្បតិ៍',
  'សុក្រ',
  'សៅរ៍',
  'អាទិត្យ',
];

const _khmerMonths = <String>[
  'មករា',
  'កុម្ភៈ',
  'មីនា',
  'មេសា',
  'ឧសភា',
  'មិថុនា',
  'កក្កដា',
  'សីហា',
  'កញ្ញា',
  'តុលា',
  'វិច្ឆិកា',
  'ធ្នូ',
];

const _khmerDigits = <String>['០', '១', '២', '៣', '៤', '៥', '៦', '៧', '៨', '៩'];

String _toKhmerDigits(int value) =>
    value
        .toString()
        .split('')
        .map((c) => int.tryParse(c) == null ? c : _khmerDigits[int.parse(c)])
        .join();

/// "ថ្ងៃពុធ ១២ កញ្ញា ២០២៦" — weekday + Khmer-numeral day/year, used by the
/// Dashboard hero header's date pill.
String khmerHeaderDate(DateTime date) {
  final weekday = _khmerWeekdays[date.weekday - 1];
  final month = _khmerMonths[date.month - 1];
  return 'ថ្ងៃ$weekday ${_toKhmerDigits(date.day)} $month ${_toKhmerDigits(date.year)}';
}

/// Short Khmer weekday label ("ចន្ទ", "អង្គារ", ...) for the Dashboard's
/// week-day selector strip.
String khmerWeekdayShort(DateTime date) => _khmerWeekdays[date.weekday - 1];
