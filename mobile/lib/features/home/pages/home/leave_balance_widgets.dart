import 'package:flutter/material.dart';

import '../../models/leave_request_models.dart';

/// Style preset (tint/accent) for a leave-type balance card, matched to a
/// [LeaveBalanceItem] by keyword. Shared between the Leave screen's balance
/// grid and the Dashboard's leave-balance cards so both read identically.
class LeaveBalanceDef {
  const LeaveBalanceDef({
    required this.tint,
    required this.accent,
    required this.keywords,
    this.icon = Icons.event_note_rounded,
  });

  final Color tint;
  final Color accent;
  final List<String> keywords;
  final IconData icon;
}

/// A leave type's balance, resolved to display-ready values (title already
/// localized, used/pending combined) plus its card colors.
class LeaveBalanceDisplay {
  const LeaveBalanceDisplay({
    required this.title,
    required this.used,
    required this.total,
    required this.remaining,
    required this.percent,
    required this.tint,
    required this.accent,
    this.icon = Icons.event_note_rounded,
  });

  final String title;
  final int used;
  final int total;
  final int remaining;
  final double percent;
  final Color tint;
  final Color accent;
  final IconData icon;
}

// Style presets for recognisable leave types (matched by keyword); any
// other type returned by the backend still gets a card, styled from
// _fallbackPalette below, so no leave type is ever dropped from a grid.
const List<LeaveBalanceDef> _balanceDefs = <LeaveBalanceDef>[
  LeaveBalanceDef(
    tint: Color(0xFFEFF6FF),
    accent: Color(0xFF3B82F6),
    keywords: <String>['annual', 'year', 'ប្រចាំឆ្នាំ'],
    icon: Icons.beach_access_rounded,
  ),
  LeaveBalanceDef(
    tint: Color(0xFFF0FDF4),
    accent: Color(0xFF10B981),
    keywords: <String>['short', 'casual', 'special', 'រយៈពេលខ្លី'],
    icon: Icons.timelapse_rounded,
  ),
  LeaveBalanceDef(
    tint: Color(0xFFFAF5FF),
    accent: Color(0xFF8B5CF6),
    keywords: <String>['maternity', 'mater', 'លំហែ'],
    icon: Icons.child_friendly_rounded,
  ),
  LeaveBalanceDef(
    tint: Color(0xFFFFFBEB),
    accent: Color(0xFFF59E0B),
    keywords: <String>['sick', 'medical', 'ព្យាបាល', 'ជំងឺ'],
    icon: Icons.medical_services_rounded,
  ),
];

const List<LeaveBalanceDef> _fallbackPalette = <LeaveBalanceDef>[
  LeaveBalanceDef(
    tint: Color(0xFFEEF2FF),
    accent: Color(0xFF6366F1),
    keywords: <String>[],
  ),
  LeaveBalanceDef(
    tint: Color(0xFFFDF2F8),
    accent: Color(0xFFEC4899),
    keywords: <String>[],
  ),
  LeaveBalanceDef(
    tint: Color(0xFFF0F9FF),
    accent: Color(0xFF0EA5E9),
    keywords: <String>[],
  ),
  LeaveBalanceDef(
    tint: Color(0xFFF7FEE7),
    accent: Color(0xFF65A30D),
    keywords: <String>[],
  ),
];

/// The icon of the leave type named by [names] (English and/or Khmer name),
/// matched with the same keywords as the balance cards.
IconData leaveTypeIcon(Iterable<String> names) {
  final text = names.map((name) => name.toLowerCase()).join(' ');
  for (final def in _balanceDefs) {
    if (def.keywords.any(text.contains)) return def.icon;
  }
  return Icons.event_note_rounded;
}

/// Builds one [LeaveBalanceDisplay] per leave type — each type's balance is
/// its own figure (never summed across types, since different leave types
/// draw from different, unrelated entitlements).
List<LeaveBalanceDisplay> buildLeaveBalanceDisplays(
  List<LeaveBalanceItem> types,
  Map<String, String> language,
) {
  var fallbackIndex = 0;

  return types.map((row) {
    final eng = row.leaveType.toLowerCase();
    final km = row.leaveTypeKm.toLowerCase();
    final matched = _balanceDefs.where(
      (def) => def.keywords.any((k) => eng.contains(k) || km.contains(k)),
    );
    final style =
        matched.isNotEmpty
            ? matched.first
            : _fallbackPalette[fallbackIndex++ % _fallbackPalette.length];

    final ent = row.entitlement;
    // Display used+pending together so: used + remaining == entitlement always
    final displayUsed = row.used + row.pending;

    return LeaveBalanceDisplay(
      title: row.displayName(language),
      used: displayUsed,
      total: ent,
      remaining: row.remaining,
      percent: ent <= 0 ? 0.0 : (displayUsed / ent).clamp(0.0, 1.0),
      tint: style.tint,
      accent: style.accent,
      icon: style.icon,
    );
  }).toList();
}

/// One leave type's balance as a flat row (title + used/total, thin progress
/// bar underneath) — no card border, matching the Figma Dashboard's
/// "Leave Balance" list. Shares [LeaveBalanceDisplay] with [LeaveBalanceCard]
/// so both surfaces read the exact same figures.
class LeaveBalanceProgressRow extends StatelessWidget {
  const LeaveBalanceProgressRow({super.key, required this.item});

  final LeaveBalanceDisplay item;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
              child: Text(
                item.title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF17352B),
                ),
              ),
            ),
            Text(
              '${item.used}/${item.total}',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: item.accent,
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: item.percent,
            minHeight: 8,
            backgroundColor: item.accent.withValues(alpha: 0.12),
            valueColor: AlwaysStoppedAnimation<Color>(item.accent),
          ),
        ),
      ],
    );
  }
}

/// One leave type's balance tile on the Leave screen: an animated ring of the
/// days still available (number in the middle), the type's icon and name,
/// and "used / entitlement". Grows with its title, so long Khmer names and
/// large system fonts never overflow.
class LeaveBalanceCard extends StatelessWidget {
  const LeaveBalanceCard({super.key, required this.item});

  final LeaveBalanceDisplay item;

  static String _km(int value) =>
      value
          .toString()
          .split('')
          .map((c) => int.tryParse(c) == null ? c : '០១២៣៤៥៦៧៨៩'[int.parse(c)])
          .join();

  @override
  Widget build(BuildContext context) {
    final left = item.total <= 0 ? 0.0 : (1 - item.percent).clamp(0.0, 1.0);

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: item.accent.withValues(alpha: 0.18)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: item.accent.withValues(alpha: 0.10),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              SizedBox(
                width: 58,
                height: 58,
                child: TweenAnimationBuilder<double>(
                  tween: Tween<double>(begin: 0, end: left),
                  duration: const Duration(milliseconds: 900),
                  curve: Curves.easeOutCubic,
                  builder:
                      (context, value, child) => Stack(
                        fit: StackFit.expand,
                        children: <Widget>[
                          CircularProgressIndicator(
                            value: value,
                            strokeWidth: 6,
                            strokeCap: StrokeCap.round,
                            backgroundColor: item.accent.withValues(
                              alpha: 0.14,
                            ),
                            valueColor: AlwaysStoppedAnimation<Color>(
                              item.accent,
                            ),
                          ),
                          child!,
                        ],
                      ),
                  child: Center(
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Padding(
                        padding: const EdgeInsets.all(8),
                        child: Text(
                          _km(item.remaining),
                          style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w800,
                            color: item.accent,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              const Spacer(),
              Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  color: item.tint,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(item.icon, size: 18, color: item.accent),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            item.title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: Color(0xFF0F172A),
              height: 1.35,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'នៅសល់ ${_km(item.remaining)} ថ្ងៃ · ប្រើ ${_km(item.used)}/${_km(item.total)}',
            style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
          ),
        ],
      ),
    );
  }
}
