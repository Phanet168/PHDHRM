import 'package:flutter/material.dart';

import '../../core/theme/app_design_system.dart';

/// Visual tone of an [InlineNoticeBanner]. Each tone maps to one set of
/// background/border/icon tints from [AppDesignSystem] so every banner in
/// the app (login errors, request feedback, form validation, …) looks the
/// same regardless of which screen renders it.
enum NoticeTone { info, success, warning, danger }

/// A titled inline banner with an icon, a body message, and optional action
/// buttons — used for login/device-request feedback and, in later cleanup
/// phases, for the error/empty states duplicated across other pages today.
class InlineNoticeBanner extends StatelessWidget {
  const InlineNoticeBanner({
    super.key,
    required this.tone,
    required this.title,
    required this.body,
    this.icon,
    this.actions = const [],
  });

  final NoticeTone tone;
  final String title;
  final String body;
  final IconData? icon;
  final List<Widget> actions;

  (Color bg, Color border, Color icon) _colors() {
    switch (tone) {
      case NoticeTone.success:
        return (
          AppDesignSystem.successBg,
          AppDesignSystem.successBorder,
          AppDesignSystem.successIcon,
        );
      case NoticeTone.warning:
        return (
          AppDesignSystem.warningBg,
          AppDesignSystem.warningBorder,
          AppDesignSystem.warningIcon,
        );
      case NoticeTone.danger:
        return (
          AppDesignSystem.dangerBg,
          AppDesignSystem.dangerBorder,
          AppDesignSystem.dangerIcon,
        );
      case NoticeTone.info:
        return (
          AppDesignSystem.warningBg,
          AppDesignSystem.warningBorder,
          AppDesignSystem.warningIcon,
        );
    }
  }

  IconData _defaultIcon() {
    return tone == NoticeTone.success
        ? Icons.check_circle_outline
        : Icons.info_outline;
  }

  @override
  Widget build(BuildContext context) {
    final (bg, border, iconColor) = _colors();

    return Container(
      margin: const EdgeInsets.only(bottom: AppDesignSystem.spacing * 2.25),
      padding: const EdgeInsets.all(AppDesignSystem.spacing * 1.75),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(AppDesignSystem.radiusInput),
        border: Border.all(color: border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon ?? _defaultIcon(), color: iconColor, size: 20),
              const SizedBox(width: AppDesignSystem.spacing * 1.25),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: AppDesignSystem.noticeTitle,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      body,
                      style: const TextStyle(
                        height: 1.35,
                        color: AppDesignSystem.noticeBody,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (actions.isNotEmpty) ...[
            const SizedBox(height: AppDesignSystem.spacing * 1.5),
            Wrap(spacing: 10, runSpacing: 10, children: actions),
          ],
        ],
      ),
    );
  }
}
