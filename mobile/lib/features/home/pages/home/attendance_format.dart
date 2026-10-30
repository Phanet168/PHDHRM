/// Khmer display helpers shared by the attendance History and Daily Detail
/// screens, so both read times, durations and dates the same way.
library;

const attendanceMonthNames = <String>[
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

const attendanceWeekdayNames = <String>[
  'ចន្ទ',
  'អង្គារ',
  'ពុធ',
  'ព្រហស្បតិ៍',
  'សុក្រ',
  'សៅរ៍',
  'អាទិត្យ',
];

/// Western digits → Khmer digits; everything else unchanged.
String kmDigits(Object value) =>
    value
        .toString()
        .split('')
        .map((c) => int.tryParse(c) == null ? c : '០១២៣៤៥៦៧៨៩'[int.parse(c)])
        .join();

/// "07:31:00" → "០៧:៣១"; '-' or empty → "--:--".
String kmClock(String value) {
  final text = value.trim();
  if (text.isEmpty || text == '-') return '--:--';
  final parts = text.split(':');
  if (parts.length < 2) return kmDigits(text);
  return kmDigits('${parts[0].padLeft(2, '0')}:${parts[1].padLeft(2, '0')}');
}

/// True when [value] holds a real clock time (not '-' or empty).
bool hasClock(String value) {
  final text = value.trim();
  return text.isNotEmpty && text != '-' && text.contains(':');
}

/// Minutes since midnight of a "HH:MM[:SS]" clock, or null.
int? clockMinutes(String value) {
  if (!hasClock(value)) return null;
  final parts = value.trim().split(':');
  final h = int.tryParse(parts[0]);
  final m = int.tryParse(parts[1]);
  return h == null || m == null ? null : h * 60 + m;
}

/// 450 → "៧ម៉ោង ៣០នាទី"; 30 → "៣០នាទី"; 0 → "០នាទី".
String kmDuration(int minutes) {
  final h = minutes ~/ 60;
  final m = minutes % 60;
  if (h == 0) return '${kmDigits(m)}នាទី';
  if (m == 0) return '${kmDigits(h)}ម៉ោង';
  return '${kmDigits(h)}ម៉ោង ${kmDigits(m)}នាទី';
}

/// "ថ្ងៃពុធ ២៩ តុលា ២០២៦".
String kmLongDate(DateTime date) =>
    'ថ្ងៃ${attendanceWeekdayNames[date.weekday - 1]} '
    '${kmDigits(date.day)} ${attendanceMonthNames[date.month - 1]} '
    '${kmDigits(date.year)}';
