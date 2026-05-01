import 'package:flutter/material.dart';

import '../../../core/config/app_routes.dart';
import '../../../core/device/device_metadata_service.dart';
import '../../../core/localization/laravel_language_service.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/storage/machine_number_storage_service.dart';
import '../controllers/auth_controller.dart';
import '../services/device_access_request_service.dart';

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
  final DeviceAccessRequestService _deviceAccessRequestService =
      DeviceAccessRequestService();
  final DeviceMetadataService _deviceMetadataService = DeviceMetadataService();
  final MachineNumberStorageService _machineNumberStorageService =
      MachineNumberStorageService();
  late final Future<Map<String, String>> _languageFuture;

  bool _obscurePassword = true;
  bool _isSubmittingDeviceRequest = false;
  String? _requestFeedbackMessage;
  bool _requestFeedbackIsError = false;

  @override
  void initState() {
    super.initState();
    _languageFuture = LaravelLanguageService.instance.load();
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

  String? get _errorText => widget.authController.errorMessage;

  bool get _hasInlineError =>
      _errorText != null && _errorText!.trim().isNotEmpty;

  bool get _showsDeviceActions =>
      _hasInlineError && _isDeviceApprovalIssue(_errorText!);

  bool get _showsServerActions =>
      _hasInlineError && _isServerConfigurationIssue(_errorText!);

  void _clearTransientFeedback() {
    if (!_hasInlineError && _requestFeedbackMessage == null) {
      return;
    }

    setState(() {
      _requestFeedbackMessage = null;
      _requestFeedbackIsError = false;
    });
  }

  Future<void> _submit() async {
    final formState = _formKey.currentState;
    if (formState == null || !formState.validate()) {
      return;
    }

    FocusScope.of(context).unfocus();
    _clearTransientFeedback();

    final success = await widget.authController.login(
      email: _emailController.text,
      password: _passwordController.text,
    );

    if (!mounted) {
      return;
    }

    if (!success && _hasInlineError) {
      setState(() {});
    }
  }

  bool _isDeviceApprovalIssue(String message) {
    final normalized = message.toLowerCase();
    return normalized.contains('device is not registered') ||
        normalized.contains('device_not_registered') ||
        normalized.contains('waiting for administrator approval') ||
        normalized.contains('device_pending') ||
        normalized.contains('device has been blocked') ||
        normalized.contains('device_blocked') ||
        normalized.contains('device registration was rejected') ||
        normalized.contains('device_rejected');
  }

  bool _isServerConfigurationIssue(String message) {
    final normalized = message.toLowerCase();
    return isNetworkErrorMessage(message) ||
        normalized.contains('cannot connect to server') ||
        normalized.contains('connection timeout') ||
        normalized.contains('timeout') ||
        normalized.contains('failed host lookup') ||
        normalized.contains('connection refused') ||
        normalized.contains('network is unreachable') ||
        normalized.contains('clientexception') ||
        normalized.contains('socketexception') ||
        normalized.contains('xmlhttprequest') ||
        normalized.contains('unexpected non-json response') ||
        normalized.contains('api base url') ||
        normalized.contains('tried:');
  }

  Future<void> _openServerConfiguration() async {
    if (!mounted) {
      return;
    }

    await Navigator.of(context).pushNamed(AppRoutes.systemSettings);
  }

  Future<void> _submitDeviceAccessRequest() async {
    final email = _emailController.text.trim();
    final password = _passwordController.text;
    if (email.isEmpty || password.isEmpty) {
      if (!mounted) {
        return;
      }

      setState(() {
        _requestFeedbackMessage = 'សូមបញ្ចូលអ៊ីមែល និងពាក្យសម្ងាត់មុនសិន';
        _requestFeedbackIsError = true;
      });
      return;
    }

    setState(() {
      _isSubmittingDeviceRequest = true;
      _requestFeedbackMessage = 'កំពុងផ្ញើសំណើសុំអនុម័តឧបករណ៍...';
      _requestFeedbackIsError = false;
    });

    try {
      final machineNumber =
          await _machineNumberStorageService.getMachineNumber();
      final deviceInfo = await _deviceMetadataService.collect();
      final deviceSummary = _deviceMetadataService.summarize(deviceInfo);

      final result = await _deviceAccessRequestService.submitRequest(
        fullName: email,
        email: email,
        password: password,
        machineNumber: machineNumber,
        deviceInfo: deviceInfo,
        deviceSummary: deviceSummary,
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _requestFeedbackMessage = result.message;
        _requestFeedbackIsError = false;
      });
    } catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _requestFeedbackMessage = extractApiErrorMessage(error);
        _requestFeedbackIsError = true;
      });
    } finally {
      if (mounted) {
        setState(() {
          _isSubmittingDeviceRequest = false;
        });
      }
    }
  }

  String _resolveInlineHeadline(Map<String, String> language) {
    if (_showsDeviceActions) {
      return _tr(
        language,
        'device_approval_required',
        'ឧបករណ៍នេះមិនទាន់អនុម័ត',
      );
    }
    if (_showsServerActions) {
      return _tr(
        language,
        'server_connection_issue',
        'មិនអាចភ្ជាប់ទៅម៉ាស៊ីនមេ',
      );
    }
    return _tr(language, 'login_failed', 'ចូលគណនីមិនបាន');
  }

  String _resolveInlineBody(Map<String, String> language) {
    final message = _errorText?.trim();
    if (message != null && message.isNotEmpty) {
      return message;
    }
    return _tr(language, 'please_try_again', 'សូមព្យាយាមម្តងទៀត');
  }

  Widget _buildInlineNotice(Map<String, String> language) {
    final hasRequestFeedback =
        _requestFeedbackMessage != null && _requestFeedbackMessage!.isNotEmpty;
    if (!_hasInlineError && !hasRequestFeedback) {
      return const SizedBox.shrink();
    }

    final showError = _hasInlineError;
    final backgroundColor =
        showError
            ? const Color(0xFFFFF4E8)
            : (_requestFeedbackIsError
                ? const Color(0xFFFFF4E8)
                : const Color(0xFFEAF7EF));
    final borderColor =
        showError
            ? const Color(0xFFF0B46A)
            : (_requestFeedbackIsError
                ? const Color(0xFFF0B46A)
                : const Color(0xFF7AC092));
    final iconColor =
        showError
            ? const Color(0xFF9A5B00)
            : (_requestFeedbackIsError
                ? const Color(0xFF9A5B00)
                : const Color(0xFF206B3C));
    final title =
        showError
            ? _resolveInlineHeadline(language)
            : (_requestFeedbackIsError
                ? _tr(language, 'request_failed', 'ផ្ញើសំណើមិនបាន')
                : _tr(language, 'request_sent', 'បានផ្ញើសំណើរួចហើយ'));
    final body =
        showError ? _resolveInlineBody(language) : _requestFeedbackMessage!;

    return Container(
      margin: const EdgeInsets.only(bottom: 18),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: borderColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                showError || _requestFeedbackIsError
                    ? Icons.info_outline
                    : Icons.check_circle_outline,
                color: iconColor,
                size: 20,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF1B2A25),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      body,
                      style: const TextStyle(
                        height: 1.35,
                        color: Color(0xFF42534D),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (_showsDeviceActions ||
              _showsServerActions ||
              !_requestFeedbackIsError)
            const SizedBox(height: 12),
          if (_showsDeviceActions)
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                FilledButton.icon(
                  onPressed:
                      _isSubmittingDeviceRequest
                          ? null
                          : _submitDeviceAccessRequest,
                  icon:
                      _isSubmittingDeviceRequest
                          ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                          : const Icon(Icons.verified_user_outlined),
                  label: Text(
                    _isSubmittingDeviceRequest
                        ? _tr(language, 'sending_request', 'កំពុងផ្ញើ...')
                        : _tr(language, 'submit_request', 'ផ្ញើសំណើអនុម័ត'),
                  ),
                ),
                OutlinedButton(
                  onPressed: _openServerConfiguration,
                  child: Text(_tr(language, 'open_settings', 'បើកការកំណត់')),
                ),
              ],
            ),
          if (_showsServerActions)
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                FilledButton(
                  onPressed: _openServerConfiguration,
                  child: Text(
                    _tr(language, 'configure_server', 'កំណត់ Server/IP'),
                  ),
                ),
                OutlinedButton(
                  onPressed:
                      widget.authController.isSubmitting ? null : _submit,
                  child: Text(_tr(language, 'retry', 'សាកម្ដងទៀត')),
                ),
              ],
            ),
          if (!showError && !_requestFeedbackIsError)
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton(
                onPressed: _openServerConfiguration,
                child: Text(
                  _tr(
                    language,
                    'view_machine_number',
                    'ពិនិត្យ Machine Number',
                  ),
                ),
              ),
            ),
        ],
      ),
    );
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
                    tooltip: 'ការកំណត់ប្រព័ន្ធ',
                    onPressed: _openServerConfiguration,
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
                                  _tr(language, 'login', 'ចូលប្រើ'),
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.headlineSmall
                                      ?.copyWith(
                                        fontWeight: FontWeight.w700,
                                        fontSize: 24,
                                        color: const Color(0xFF14211D),
                                      ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  _tr(
                                    language,
                                    'welcome_msg',
                                    'សូមស្វាគមន៍មកវិញ',
                                  ),
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    color: const Color(0xFF5C6B65),
                                    fontWeight: FontWeight.w600,
                                    fontSize: 14,
                                  ),
                                ),
                                const SizedBox(height: 26),
                                _buildInlineNotice(language),
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
                                        'សូមបញ្ចូលអ៊ីមែល',
                                      );
                                    }

                                    return null;
                                  },
                                  onChanged: (_) => _clearTransientFeedback(),
                                ),
                                const SizedBox(height: 16),
                                TextFormField(
                                  controller: _passwordController,
                                  obscureText: _obscurePassword,
                                  decoration: InputDecoration(
                                    labelText: _tr(
                                      language,
                                      'password',
                                      'ពាក្យសម្ងាត់',
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
                                        'សូមបញ្ចូលពាក្យសម្ងាត់',
                                      );
                                    }

                                    return null;
                                  },
                                  onChanged: (_) => _clearTransientFeedback(),
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
                                            _tr(language, 'sign_in', 'ចូលគណនី'),
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
