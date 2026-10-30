import 'package:flutter/material.dart';

import '../../../core/theme/app_design_system.dart';
import '../../../core/theme/appearance_settings.dart';
import 'system_widgets.dart';

/// Settings › Appearance ("រូបរាង"): the app's accent colour (the colour of
/// the day, or one fixed colour) and its text size, with a live preview.
/// Choices apply at once to every screen and are kept on the phone.
class AppearancePage extends StatelessWidget {
  const AppearancePage({super.key});

  @override
  Widget build(BuildContext context) {
    final settings = AppearanceSettings.instance;
    return ListenableBuilder(
      listenable: settings,
      builder: (context, _) {
        final weekday = DateTime.now().weekday;
        return SystemPageScaffold(
          title: 'រូបរាង',
          children: [
            const _Preview(),
            const SizedBox(height: 18),
            _Group(
              title: 'ពណ៌កម្មវិធី',
              hint: '«ពណ៌តាមថ្ងៃ» ប្ដូរពណ៌កម្មវិធីរៀងរាល់ថ្ងៃ តាមពណ៌ប្រចាំថ្ងៃ',
              child: Wrap(
                spacing: 10,
                runSpacing: 14,
                children: [
                  for (final choice in AppearanceSettings.accents)
                    _ColorChoice(
                      label: choice.label,
                      color:
                          choice.color ??
                          AppDesignSystem.traditionalWeekdayColor(weekday),
                      rainbow: choice.color == null,
                      selected: identical(settings.accentChoice, choice),
                      onTap: () => settings.setAccent(choice.color),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _Group(
              title: 'ទំហំអក្សរ',
              hint: 'គុណនឹងទំហំអក្សរដែលបានកំណត់ក្នុងទូរសព្ទរបស់អ្នក',
              child: Row(
                children: [
                  for (final size in AppearanceSettings.textSizes) ...[
                    if (size != AppearanceSettings.textSizes.first)
                      const SizedBox(width: 8),
                    Expanded(
                      child: _SizeChoice(
                        choice: size,
                        selected: identical(settings.textSizeChoice, size),
                        onTap: () => settings.setTextScale(size.scale),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        );
      },
    );
  }
}

class _Preview extends StatelessWidget {
  const _Preview();

  @override
  Widget build(BuildContext context) {
    final accent = systemAccent();
    return SystemHero(
      color: accent,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: Colors.white.withAlpha(45),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.auto_awesome_rounded,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(
                  'មើលជាមុន',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            'ការសុំច្បាប់ · ប្រវត្តិវត្តមាន · បេសកកម្ម',
            style: TextStyle(color: Colors.white.withAlpha(230), fontSize: 13),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: FilledButton(
                  onPressed: () {},
                  style: FilledButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: accent,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: const Text('ប៊ូតុងគំរូ'),
                ),
              ),
              const SizedBox(width: 10),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: Colors.white.withAlpha(40),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: const Text(
                  'ទាន់ពេល',
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Group extends StatelessWidget {
  const _Group({required this.title, required this.hint, required this.child});

  final String title;
  final String hint;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A0F172A),
            blurRadius: 14,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            hint,
            style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
          ),
          const SizedBox(height: 14),
          child,
        ],
      ),
    );
  }
}

class _ColorChoice extends StatelessWidget {
  const _ColorChoice({
    required this.label,
    required this.color,
    required this.rainbow,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final Color color;
  final bool rainbow;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    // Monday … Sunday, closing the ring back on Monday.
    final week = [
      for (var d = DateTime.monday; d <= DateTime.sunday; d++)
        AppDesignSystem.traditionalWeekdayColor(d),
      AppDesignSystem.traditionalWeekdayColor(DateTime.monday),
    ];
    return Semantics(
      button: true,
      selected: selected,
      label: label,
      excludeSemantics: true,
      onTap: onTap,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: SizedBox(
          width: 86,
          child: Column(
            children: [
              AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                width: 50,
                height: 50,
                padding: const EdgeInsets.all(3),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: selected ? color : Colors.transparent,
                    width: 3,
                  ),
                ),
                child: Container(
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: rainbow ? null : color,
                    gradient: rainbow ? SweepGradient(colors: week) : null,
                  ),
                  child:
                      selected
                          ? const Icon(Icons.check_rounded, color: Colors.white)
                          : null,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                label,
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
                  color:
                      selected
                          ? const Color(0xFF0F172A)
                          : const Color(0xFF64748B),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SizeChoice extends StatelessWidget {
  const _SizeChoice({
    required this.choice,
    required this.selected,
    required this.onTap,
  });

  final TextSizeChoice choice;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = systemAccent();
    return Semantics(
      button: true,
      selected: selected,
      label: 'អក្សរ${choice.label}',
      excludeSemantics: true,
      onTap: onTap,
      child: Material(
        color: selected ? accent.withAlpha(24) : const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(14),
          child: Container(
            padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: selected ? accent : const Color(0xFFE2E8F0),
                width: selected ? 2 : 1,
              ),
            ),
            child: Column(
              children: [
                FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(
                    'ក',
                    style: TextStyle(
                      fontSize: 16 * choice.scale,
                      fontWeight: FontWeight.w800,
                      color: selected ? accent : const Color(0xFF334155),
                    ),
                  ),
                ),
                const SizedBox(height: 2),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(
                    choice.label,
                    style: TextStyle(
                      fontSize: 11.5,
                      fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
                      color: selected ? accent : const Color(0xFF64748B),
                    ),
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
