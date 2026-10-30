import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// One accent the user can pick in Settings › Appearance; a null [color]
/// means the traditional colour of the day (Monday yellow, Tuesday purple…).
class AccentChoice {
  const AccentChoice(this.label, this.color);

  final String label;
  final Color? color;
}

/// One text size the user can pick; [scale] multiplies the phone's own
/// font size setting.
class TextSizeChoice {
  const TextSizeChoice(this.label, this.scale);

  final String label;
  final double scale;
}

/// The user's appearance preferences — accent colour and text size — kept
/// on the device. [StaffMobileApp] listens and redraws every screen when
/// they change.
class AppearanceSettings extends ChangeNotifier {
  AppearanceSettings._();

  static final AppearanceSettings instance = AppearanceSettings._();

  static const _accentKey = 'appearance.accent';
  static const _textScaleKey = 'appearance.text_scale';

  static const accents = <AccentChoice>[
    AccentChoice('ពណ៌តាមថ្ងៃ', null),
    AccentChoice('បៃតងសុខាភិបាល', Color(0xFF0B6B58)),
    AccentChoice('ខៀវ', Color(0xFF1D4ED8)),
    AccentChoice('ខៀវផ្ទៃមេឃ', Color(0xFF0E7490)),
    AccentChoice('ស្វាយ', Color(0xFF6D28D9)),
    AccentChoice('ក្រហមឈាមជ្រូក', Color(0xFFB4235A)),
    AccentChoice('ទឹកក្រូច', Color(0xFFC2410C)),
  ];

  static const textSizes = <TextSizeChoice>[
    TextSizeChoice('តូច', 0.9),
    TextSizeChoice('ធម្មតា', 1.0),
    TextSizeChoice('ធំ', 1.15),
    TextSizeChoice('ធំណាស់', 1.3),
  ];

  Color? _accent;
  double _textScale = 1.0;

  /// A fixed accent, or null for the colour of the day.
  Color? get accent => _accent;

  double get textScale => _textScale;

  AccentChoice get accentChoice => accents.firstWhere(
    (a) => a.color?.toARGB32() == _accent?.toARGB32(),
    orElse: () => accents.first,
  );

  TextSizeChoice get textSizeChoice => textSizes.firstWhere(
    (t) => t.scale == _textScale,
    orElse: () => textSizes[1],
  );

  Future<void> load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final argb = prefs.getInt(_accentKey);
      _accent = argb == null ? null : Color(argb);
      _textScale = prefs.getDouble(_textScaleKey) ?? 1.0;
    } catch (_) {
      // Defaults are fine when storage is unavailable.
    }
  }

  Future<void> setAccent(Color? color) async {
    if (color?.toARGB32() == _accent?.toARGB32()) return;
    _accent = color;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    if (color == null) {
      await prefs.remove(_accentKey);
    } else {
      await prefs.setInt(_accentKey, color.toARGB32());
    }
  }

  Future<void> setTextScale(double scale) async {
    if (scale == _textScale) return;
    _textScale = scale;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setDouble(_textScaleKey, scale);
  }

  /// Back to the defaults (tests).
  @visibleForTesting
  void reset() {
    _accent = null;
    _textScale = 1.0;
  }
}

/// The phone's text scaling, multiplied by the in-app text size.
class ScaledTextScaler extends TextScaler {
  const ScaledTextScaler(this.base, this.factor);

  final TextScaler base;
  final double factor;

  @override
  double scale(double fontSize) => base.scale(fontSize) * factor;

  @override
  // ignore: deprecated_member_use
  double get textScaleFactor => base.textScaleFactor * factor;

  @override
  bool operator ==(Object other) =>
      other is ScaledTextScaler && other.base == base && other.factor == factor;

  @override
  int get hashCode => Object.hash(base, factor);
}
