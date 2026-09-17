import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app.dart';
import 'core/config/api_config.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Every screen in this app is built to fixed, portrait-shaped dimensions
  // (Figma-derived header heights, card layouts, etc.) — rotating to
  // landscape squeezes that fixed vertical chrome (topbar, bottom nav, FABs)
  // until it overlaps rather than reflowing, since nothing here is actually
  // built to be orientation-responsive.
  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);
  await ApiConfig.initialize();
  runApp(const StaffMobileApp());
}
