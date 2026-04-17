import 'package:flutter/material.dart';

import '../../../core/device/device_metadata_service.dart';
import '../../../core/localization/laravel_language_service.dart';
import '../../../core/storage/machine_number_storage_service.dart';
import '../models/device_access_request_result.dart';
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
  late final Future<Map<String, String>> _languageFuture;
  late final Future<String> _machineNumberFuture;
  late final Future<Map<String, dynamic>> _deviceInfoFuture;

  bool _obscurePassword = true;

  @override
  void initState() {
    super.initState();
    _languageFuture = LaravelLanguageService.instance.load(forceRefresh: true);
    _machineNumberFuture = MachineNumberStorageService().getMachineNumber();
    _deviceInfoFuture = _deviceMetadataService.collect();
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

  String _deviceRequestLabel(Map<String, String> language) {
    const keys = <String>[
      'divice_id_reques',
      'device_id_request',
      'device_request_id',
      'machine_no',
    ];

    for (final key in keys) {
      final value = language[key]?.trim();
      if (value != null && value.isNotEmpty) {
        return value;
      }
    }

    return 'លេខស្នើសុំឧបករណ៍';
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

  Future<void> _openAccessRequestForm(
    Map<String, String> language,
    String machineNumber,
  ) async {
    final deviceInfo = await _deviceInfoFuture;
    if (!mounted) {
      return;
    }
    final deviceSummary = _deviceMetadataService.summarize(deviceInfo);

    final requestFormKey = GlobalKey<FormState>();
    final nameController = TextEditingController();
    final requestEmailController = TextEditingController(
      text: _emailController.text,
    );
    final phoneController = TextEditingController();
    final reasonController = TextEditingController();
    bool isSubmitting = false;

    final result = await showDialog<DeviceAccessRequestResult>(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              title: Text(
                _tr(language, 'request_access', 'ស្នើសុំសិទ្ធិចូលប្រើប្រាស់'),
              ),
              content: SingleChildScrollView(
                child: Form(
                  key: requestFormKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      TextFormField(
                        controller: nameController,
                        decoration: InputDecoration(
                          labelText: _tr(language, 'name', 'ឈ្មោះ'),
                        ),
                        validator: (value) {
                          if (value == null || value.trim().isEmpty) {
                            return 'សូមបញ្ចូលឈ្មោះ';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: requestEmailController,
                        keyboardType: TextInputType.emailAddress,
                        decoration: InputDecoration(
                          labelText: _tr(language, 'email', 'Email'),
                        ),
                        validator: (value) {
                          final raw = (value ?? '').trim();
                          if (raw.isEmpty) {
                            return 'សូមបញ្ចូល Email';
                          }
                          if (!raw.contains('@')) {
                            return 'Email មិនត្រឹមត្រូវ';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: phoneController,
                        keyboardType: TextInputType.phone,
                        decoration: InputDecoration(
                          labelText: _tr(language, 'phone', 'ទូរស័ព្ទ'),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: reasonController,
                        minLines: 2,
                        maxLines: 4,
                        decoration: const InputDecoration(
                          labelText: 'មូលហេតុស្នើសុំ',
                        ),
                      ),
                      const SizedBox(height: 12),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          '${_deviceRequestLabel(language)}: $machineNumber',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          'Device: $deviceSummary',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed:
                      isSubmitting ? null : () => Navigator.of(context).pop(),
                  child: Text(_tr(language, 'cancel', 'បោះបង់')),
                ),
                FilledButton(
                  onPressed:
                      isSubmitting
                          ? null
                          : () async {
                            final form = requestFormKey.currentState;
                            if (form == null || !form.validate()) {
                              return;
                            }

                            setDialogState(() {
                              isSubmitting = true;
                            });

                            try {
                              final submitResult =
                                  await _deviceAccessRequestService
                                      .submitRequest(
                                        fullName: nameController.text,
                                        email: requestEmailController.text,
                                        phone: phoneController.text,
                                        machineNumber: machineNumber,
                                        deviceInfo: deviceInfo,
                                        deviceSummary: deviceSummary,
                                        reason: reasonController.text,
                                      );
                              if (!context.mounted) {
                                return;
                              }
                              Navigator.of(context).pop(submitResult);
                            } catch (error) {
                              if (!context.mounted) {
                                return;
                              }
                              ScaffoldMessenger.of(context)
                                ..hideCurrentSnackBar()
                                ..showSnackBar(
                                  SnackBar(content: Text('$error')),
                                );
                              setDialogState(() {
                                isSubmitting = false;
                              });
                            }
                          },
                  child:
                      isSubmitting
                          ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                          : Text(_tr(language, 'send_request', 'ផ្ញើសំណើ')),
                ),
              ],
            );
          },
        );
      },
    );

    nameController.dispose();
    requestEmailController.dispose();
    phoneController.dispose();
    reasonController.dispose();

    if (!mounted || result == null) {
      return;
    }

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text('${result.message} (Request ID: ${result.requestId})'),
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final screenWidth = MediaQuery.of(context).size.width;
    final panelWidth = screenWidth >= 500 ? 380.0 : 325.0;

    return FutureBuilder<Map<String, String>>(
      future: _languageFuture,
      builder: (context, snapshot) {
        final language = snapshot.data ?? const <String, String>{};

        return Scaffold(
          body: Stack(
            fit: StackFit.expand,
            children: [
              Image.asset('assets/images/login_bg.webp', fit: BoxFit.cover),
              Container(color: const Color(0xA6FFFFFF)),
              SafeArea(
                child: Center(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.all(24),
                    child: SizedBox(
                      width: panelWidth,
                      child: Card(
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(6),
                          side: const BorderSide(color: Color(0xFFE7EFEB)),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(30),
                          child: Form(
                            key: _formKey,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Center(
                                  child: Container(
                                    width: 90,
                                    height: 90,
                                    decoration: BoxDecoration(
                                      shape: BoxShape.circle,
                                      border: Border.all(
                                        color: Colors.black12,
                                        width: 1,
                                      ),
                                      boxShadow: const [
                                        BoxShadow(
                                          color: Color(0x14000000),
                                          blurRadius: 8,
                                          offset: Offset(0, 3),
                                        ),
                                      ],
                                    ),
                                    clipBehavior: Clip.antiAlias,
                                    child: Image.asset(
                                      'assets/images/laravel_logo.png',
                                      fit: BoxFit.cover,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  _tr(language, 'login', 'Login'),
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.headlineSmall
                                      ?.copyWith(
                                        fontWeight: FontWeight.w700,
                                        fontSize: 24,
                                      ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  _tr(language, 'welcome_msg', 'Welcome back'),
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    color: const Color(0xFF5D6D65),
                                    fontWeight: FontWeight.w600,
                                    fontSize: 14,
                                  ),
                                ),
                                const SizedBox(height: 24),
                                TextFormField(
                                  controller: _emailController,
                                  keyboardType: TextInputType.emailAddress,
                                  decoration: InputDecoration(
                                    labelText: _tr(language, 'email', 'Email'),
                                    border: const OutlineInputBorder(),
                                  ),
                                  validator: (value) {
                                    if (value == null || value.trim().isEmpty) {
                                      return _tr(
                                        language,
                                        'email_fild_can_not_empty',
                                        'សូមបញ្ចូល email',
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
                                    border: const OutlineInputBorder(),
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
                                        'សូមបញ្ចូល password',
                                      );
                                    }

                                    return null;
                                  },
                                  onFieldSubmitted: (_) => _submit(),
                                ),
                                const SizedBox(height: 12),
                                FutureBuilder<String>(
                                  future: _machineNumberFuture,
                                  builder: (context, machineSnapshot) {
                                    final machineNumber =
                                        machineSnapshot.data ?? '...';
                                    return Container(
                                      padding: const EdgeInsets.all(12),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFF6FAF8),
                                        borderRadius: BorderRadius.circular(8),
                                        border: Border.all(
                                          color: const Color(0xFFD6E6DD),
                                        ),
                                      ),
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            _deviceRequestLabel(language),
                                            style: theme.textTheme.bodySmall
                                                ?.copyWith(
                                                  fontWeight: FontWeight.w700,
                                                  color: const Color(
                                                    0xFF1A4A35,
                                                  ),
                                                ),
                                          ),
                                          const SizedBox(height: 4),
                                          SelectableText(
                                            machineNumber,
                                            style: theme.textTheme.bodyMedium
                                                ?.copyWith(
                                                  fontWeight: FontWeight.w600,
                                                  color: const Color(
                                                    0xFF0B5D4B,
                                                  ),
                                                ),
                                          ),
                                          const SizedBox(height: 6),
                                          Text(
                                            'ប្រសិនបើអ្នកមិនទាន់មានសិទ្ធិចូលប្រើ សូមផ្ញើលេខនេះទៅអ្នកគ្រប់គ្រងដើម្បីពិនិត្យ និងអនុញ្ញាត។',
                                            style: theme.textTheme.bodySmall
                                                ?.copyWith(
                                                  color: const Color(
                                                    0xFF5D6D65,
                                                  ),
                                                ),
                                          ),
                                          const SizedBox(height: 8),
                                          Align(
                                            alignment: Alignment.centerLeft,
                                            child: OutlinedButton.icon(
                                              onPressed:
                                                  () => _openAccessRequestForm(
                                                    language,
                                                    machineNumber,
                                                  ),
                                              icon: const Icon(
                                                Icons.send_outlined,
                                              ),
                                              label: Text(
                                                _tr(
                                                  language,
                                                  'send_request',
                                                  'ផ្ញើសំណើអនុញ្ញាត',
                                                ),
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                    );
                                  },
                                ),
                                const SizedBox(height: 24),
                                FilledButton(
                                  onPressed:
                                      widget.authController.isSubmitting
                                          ? null
                                          : _submit,
                                  style: FilledButton.styleFrom(
                                    minimumSize: const Size.fromHeight(46),
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
