import 'package:flutter/material.dart';

import 'core/config/app_routes.dart';
import 'features/auth/controllers/auth_controller.dart';
import 'features/auth/pages/login_page.dart';
import 'features/home/pages/home_page.dart';
import 'shared/widgets/app_loading_screen.dart';

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

    if (_ownsAuthController) {
      _authController.restoreSession();
    }
  }

  @override
  void dispose() {
    if (_ownsAuthController) {
      _authController.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _authController,
      builder: (context, _) {
        return MaterialApp(
          title: 'Staff Mobile App',
          debugShowCheckedModeBanner: false,
          theme: ThemeData(
            colorScheme: ColorScheme.fromSeed(
              seedColor: const Color(0xFF0B5D4B),
            ),
            scaffoldBackgroundColor: const Color(0xFFF4F7F5),
            useMaterial3: true,
          ),
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
