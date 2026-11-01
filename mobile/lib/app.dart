import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'core/config/app_routes.dart';
import 'core/network/api_service.dart';
import 'core/network/connection_status.dart';
import 'core/theme/app_design_system.dart';
import 'core/theme/appearance_settings.dart';
import 'features/auth/controllers/auth_controller.dart';
import 'features/auth/pages/login_page.dart';
import 'features/home/pages/home_page.dart';
import 'features/system/pages/system_settings_page.dart';
import 'shared/widgets/app_loading_screen.dart';
import 'shared/widgets/connection_banner.dart';

class StaffMobileApp extends StatefulWidget {
  const StaffMobileApp({super.key, this.authController});

  final AuthController? authController;

  @override
  State<StaffMobileApp> createState() => _StaffMobileAppState();
}

class _StaffMobileAppState extends State<StaffMobileApp> {
  late final AuthController _authController;
  late final bool _ownsAuthController;

  @override
  void initState() {
    super.initState();
    _ownsAuthController = widget.authController == null;
    _authController = widget.authController ?? AuthController();

    AppearanceSettings.instance.addListener(_redrawEverything);

    // The online/offline indicators: a light ping keeps them current.
    final pingApi = ApiService();
    ConnectionStatus.instance.startWatching(
      () => pingApi.get('/v1/ping', requiresAuth: false),
    );
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => ConnectionStatus.instance.check(),
    );

    if (_ownsAuthController) {
      _authController.restoreSession();
    }
  }

  @override
  void dispose() {
    AppearanceSettings.instance.removeListener(_redrawEverything);
    ConnectionStatus.instance.stopWatching();
    if (_ownsAuthController) {
      _authController.dispose();
    }
    super.dispose();
  }

  /// Screens read the accent while building, so a new accent or text size
  /// must rebuild every screen on the stack, not only the visible one.
  void _redrawEverything() {
    if (!mounted) return;
    void rebuild(Element element) {
      element.markNeedsBuild();
      element.visitChildren(rebuild);
    }

    (context as Element).visitChildren(rebuild);
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: Listenable.merge([
        _authController,
        AppearanceSettings.instance,
      ]),
      builder: (context, _) {
        final weekdayColor = AppDesignSystem.colorForWeekday(
          DateTime.now().weekday,
        );
        final textScale = AppearanceSettings.instance.textScale;
        return MaterialApp(
          title: 'PHDHRMS',
          debugShowCheckedModeBanner: false,
          // The app is Khmer only, Flutter's own text included.
          locale: const Locale('km'),
          supportedLocales: const [Locale('km')],
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          // Every screen sits under the online/offline bar.
          builder:
              (context, child) => ConnectionBanner(
                child:
                    textScale == 1.0
                        ? child!
                        : MediaQuery(
                          data: MediaQuery.of(context).copyWith(
                            textScaler: ScaledTextScaler(
                              MediaQuery.textScalerOf(context),
                              textScale,
                            ),
                          ),
                          child: child!,
                        ),
              ),
          theme: AppDesignSystem.lightTheme(seedColor: weekdayColor),
          onGenerateRoute: (settings) {
            switch (settings.name) {
              case AppRoutes.login:
                return MaterialPageRoute<void>(
                  builder: (_) => LoginPage(authController: _authController),
                  settings: settings,
                );
              case AppRoutes.home:
                return MaterialPageRoute<void>(
                  builder: (_) => HomePage(authController: _authController),
                  settings: settings,
                );
              case AppRoutes.systemSettings:
                return MaterialPageRoute<void>(
                  builder: (_) => const SystemSettingsPage(),
                  settings: settings,
                );
              default:
                return MaterialPageRoute<void>(
                  builder:
                      (_) => _AppLaunchView(authController: _authController),
                  settings: settings,
                );
            }
          },
        );
      },
    );
  }
}

class _AppLaunchView extends StatelessWidget {
  const _AppLaunchView({required this.authController});

  final AuthController authController;

  @override
  Widget build(BuildContext context) {
    switch (authController.status) {
      case AuthStatus.initializing:
        return const AppLoadingScreen(message: 'កំពុងពិនិត្យ session...');
      case AuthStatus.authenticated:
        return HomePage(authController: authController);
      case AuthStatus.unauthenticated:
        return LoginPage(authController: authController);
    }
  }
}
