import 'package:flutter/material.dart';

import '../../../core/config/app_routes.dart';
import '../../../core/localization/laravel_language_service.dart';
import '../controllers/auth_controller.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key, required this.authController});

  final AuthController authController;

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  late final Future<Map<String, String>> _languageFuture;

  bool _obscurePassword = true;

  @override
  void initState() {
    super.initState();
    _languageFuture = LaravelLanguageService.instance.load(forceRefresh: true);
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  String _tr(Map<String, String> language, String key, String fallback) {
    final value = language[key]?.trim();
    if (value == null || value.isEmpty) {
      return fallback;
    }

    return value;
  }

  Future<void> _submit() async {
    final formState = _formKey.currentState;
    if (formState == null || !formState.validate()) {
      return;
    }

    FocusScope.of(context).unfocus();

    final success = await widget.authController.login(
      email: _emailController.text,
      password: _passwordController.text,
    );

    if (!mounted) {
      return;
    }

    if (!success && widget.authController.errorMessage != null) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(content: Text(widget.authController.errorMessage!)),
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final screenWidth = MediaQuery.of(context).size.width;
    final panelWidth = screenWidth >= 600 ? 420.0 : double.infinity;

    return FutureBuilder<Map<String, String>>(
      future: _languageFuture,
      builder: (context, snapshot) {
        final language = snapshot.data ?? const <String, String>{};

        return Scaffold(
          body: Stack(
            fit: StackFit.expand,
            children: [
              Image.asset('assets/images/login_bg.webp', fit: BoxFit.cover),
              Container(color: const Color(0x8F0B1B16)),
              DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Colors.white.withAlpha(18),
                      const Color(0xFF0B1B16).withAlpha(118),
                    ],
                  ),
                ),
              ),
              Positioned(
                top: 8,
                right: 8,
                child: SafeArea(
                  child: IconButton(
                    tooltip: 'System Settings',
                    onPressed: () {
                      Navigator.of(context).pushNamed(AppRoutes.systemSettings);
                    },
                    icon: const Icon(
                      Icons.settings_outlined,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
              SafeArea(
                child: Center(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(22, 28, 22, 28),
                    child: SizedBox(
                      width: panelWidth,
                      child: Card(
                        color: Colors.white.withAlpha(246),
                        elevation: 12,
                        shadowColor: Colors.black.withAlpha(46),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                          side: const BorderSide(color: Color(0xFFE3E9E6)),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(26, 28, 26, 26),
                          child: Form(
                            key: _formKey,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Center(
                                  child: Container(
                                    width: 86,
                                    height: 86,
                                    decoration: BoxDecoration(
                                      shape: BoxShape.circle,
                                      border: Border.all(
                                        color: Colors.white,
                                        width: 4,
                                      ),
                                      boxShadow: const [
                                        BoxShadow(
                                          color: Color(0x240B1B16),
                                          blurRadius: 18,
                                          offset: Offset(0, 10),
                                        ),
                                      ],
                                    ),
                                    clipBehavior: Clip.antiAlias,
                                    child: Image.asset(
                                      'assets/images/laravel_logo.png',
                                      fit: BoxFit.contain,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 16),
                                Align(
                                  alignment: Alignment.center,
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 12,
                                      vertical: 6,
                                    ),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFFFF6E1),
                                      borderRadius: BorderRadius.circular(8),
                                      border: Border.all(
                                        color: const Color(0xFFF3D38A),
                                      ),
                                    ),
                                    child: const Text(
                                      'PHD HRM',
                                      style: TextStyle(
                                        color: Color(0xFF7A4E00),
                                        fontSize: 12,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  _tr(language, 'login', 'Login'),
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.headlineSmall?.copyWith(
                                    fontWeight: FontWeight.w700,
                                    fontSize: 24,
                                    color: const Color(0xFF14211D),
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  _tr(language, 'welcome_msg', 'Welcome back'),
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    color: const Color(0xFF5C6B65),
                                    fontWeight: FontWeight.w600,
                                    fontSize: 14,
                                  ),
                                ),
                                const SizedBox(height: 26),
                                TextFormField(
                                  controller: _emailController,
                                  keyboardType: TextInputType.emailAddress,
                                  decoration: InputDecoration(
                                    labelText: _tr(language, 'email', 'Email'),
                                    prefixIcon: const Icon(
                                      Icons.mail_outline,
                                      size: 20,
                                    ),
                                  ),
                                  validator: (value) {
                                    if (value == null || value.trim().isEmpty) {
                                      return _tr(
                                        language,
                                        'email_fild_can_not_empty',
                                        'Please enter email',
                                      );
                                    }

                                    return null;
                                  },
                                ),
                                const SizedBox(height: 16),
                                TextFormField(
                                  controller: _passwordController,
                                  obscureText: _obscurePassword,
                                  decoration: InputDecoration(
                                    labelText: _tr(
                                      language,
                                      'password',
                                      'Password',
                                    ),
                                    prefixIcon: const Icon(
                                      Icons.lock_outline,
                                      size: 20,
                                    ),
                                    suffixIcon: IconButton(
                                      onPressed: () {
                                        setState(() {
                                          _obscurePassword = !_obscurePassword;
                                        });
                                      },
                                      icon: Icon(
                                        _obscurePassword
                                            ? Icons.visibility_off_outlined
                                            : Icons.visibility_outlined,
                                      ),
                                    ),
                                  ),
                                  validator: (value) {
                                    if (value == null || value.isEmpty) {
                                      return _tr(
                                        language,
                                        'email_pass_cannot_empt',
                                        'Please enter password',
                                      );
                                    }

                                    return null;
                                  },
                                  onFieldSubmitted: (_) => _submit(),
                                ),
                                const SizedBox(height: 24),
                                FilledButton(
                                  onPressed:
                                      widget.authController.isSubmitting
                                          ? null
                                          : _submit,
                                  style: FilledButton.styleFrom(
                                    minimumSize: const Size.fromHeight(50),
                                    textStyle: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                  child:
                                      widget.authController.isSubmitting
                                          ? const SizedBox(
                                            width: 20,
                                            height: 20,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                            ),
                                          )
                                          : Text(
                                            _tr(language, 'sign_in', 'Sign In'),
                                          ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
