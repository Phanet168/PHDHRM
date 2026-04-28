import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;

import '../../../core/config/app_routes.dart';
import '../../../core/config/api_config.dart';
import '../../../core/device/device_metadata_service.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/api_service.dart';
import '../../../core/storage/token_storage_service.dart';
import '../../../core/storage/user_session_storage_service.dart';
import '../../../core/storage/machine_number_storage_service.dart';
import '../../auth/models/device_access_request_result.dart';
import '../../auth/services/device_access_request_service.dart';

class SystemSettingsPage extends StatefulWidget {
  const SystemSettingsPage({super.key});

  @override
  State<SystemSettingsPage> createState() => _SystemSettingsPageState();
}

class _SystemSettingsPageState extends State<SystemSettingsPage> {
  final TextEditingController _serverController = TextEditingController();
  final DeviceAccessRequestService _deviceAccessRequestService =
      DeviceAccessRequestService();
  final DeviceMetadataService _deviceMetadataService = DeviceMetadataService();
  final TokenStorageService _tokenStorageService = TokenStorageService();
  final UserSessionStorageService _userSessionStorageService =
      UserSessionStorageService();

  late final Future<String> _machineNumberFuture;
  late final Future<Map<String, dynamic>> _deviceInfoFuture;

  bool _isTestingConnection = false;
  bool _isSavingServer = false;
  String? _connectionMessage;
  Color _connectionColor = const Color(0xFF5D6D65);

  @override
  void initState() {
    super.initState();
    _serverController.text = ApiConfig.configuredBaseUrls.join('\n');
    _machineNumberFuture = MachineNumberStorageService().getMachineNumber();
    _deviceInfoFuture = _deviceMetadataService.collect();
  }

  @override
  void dispose() {
    _serverController.dispose();
    super.dispose();
  }

  List<String> _resolveCandidateBaseUrls() {
    final custom = ApiConfig.normalizeConfiguredBaseUrls(
      _serverController.text,
    );
    if (custom.isNotEmpty) {
      return custom;
    }

    return ApiConfig.baseUrls;
  }

  Future<void> _saveServerConfig() async {
    final values = ApiConfig.normalizeConfiguredBaseUrls(
      _serverController.text,
    );
    if (values.isEmpty) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text('សូមបញ្ចូលអាសយដ្ឋានម៉ាស៊ីនមេឱ្យត្រឹមត្រូវ'),
          ),
        );
      return;
    }

    setState(() {
      _isSavingServer = true;
    });

    await ApiConfig.saveConfiguredBaseUrls(values);
    ApiService.resetRoutingState();
    await _tokenStorageService.clearToken();
    await _userSessionStorageService.clearUser();

    if (!mounted) {
      return;
    }

    setState(() {
      _isSavingServer = false;
      _serverController.text = values.join('\n');
    });

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(content: Text('បានរក្សាទុកម៉ាស៊ីនមេ: ${ApiConfig.baseUrl}')),
      );

    Navigator.of(
      context,
    ).pushNamedAndRemoveUntil(AppRoutes.login, (route) => false);
  }

  Future<void> _useDefaultServerConfig() async {
    await ApiConfig.clearConfiguredBaseUrls();
    ApiService.resetRoutingState();
    await _tokenStorageService.clearToken();
    await _userSessionStorageService.clearUser();
    if (!mounted) {
      return;
    }

    setState(() {
      _serverController.clear();
      _connectionMessage = 'បានត្រឡប់ទៅម៉ាស៊ីនមេលំនាំដើម';
      _connectionColor = const Color(0xFF0B6B58);
    });

    Navigator.of(
      context,
    ).pushNamedAndRemoveUntil(AppRoutes.login, (route) => false);
  }

  Future<void> _testServerConnection() async {
    final candidates = _resolveCandidateBaseUrls();
    if (candidates.isEmpty) {
      setState(() {
        _connectionMessage = 'មិនមានអាសយដ្ឋានម៉ាស៊ីនមេសម្រាប់តេស្តទេ';
        _connectionColor = const Color(0xFFB42318);
      });
      return;
    }

    setState(() {
      _isTestingConnection = true;
      _connectionMessage = 'កំពុងតេស្តភ្ជាប់ទៅម៉ាស៊ីនមេ...';
      _connectionColor = const Color(0xFF1D4F91);
    });

    String? failedReason;

    for (final base in candidates) {
      final uri = ApiConfig.buildUriForBase(base, '/auth/login');

      try {
        final response = await http
            .post(
              uri,
              headers: const <String, String>{
                'Accept': 'application/json',
                'Content-Type': 'application/json',
              },
              body: jsonEncode(<String, dynamic>{
                'email': 'probe@example.com',
                'password': 'invalid-password',
                'device_id': 'connection-probe',
              }),
            )
            .timeout(const Duration(seconds: 8));

        if (!mounted) {
          return;
        }

        final statusCode = response.statusCode;
        final reachable =
            (statusCode >= 200 && statusCode < 300) ||
            statusCode == 400 ||
            statusCode == 401 ||
            statusCode == 422 ||
            statusCode == 403;

        final isJson = _isJsonResponse(response);

        if (reachable) {
          if (!isJson) {
            failedReason = 'ទទួលបាន response មិនមែន JSON';
            continue;
          }

          final authHint =
              (statusCode == 401 || statusCode == 403)
                  ? ' - ត្រូវការសិទ្ធិចូលប្រើ'
                  : '';
          setState(() {
            _isTestingConnection = false;
            _connectionMessage =
                'ភ្ជាប់ជោគជ័យ: $base (HTTP $statusCode$authHint)';
            _connectionColor = const Color(0xFF0B6B58);
          });
          return;
        }

        failedReason = 'HTTP $statusCode';
      } on TimeoutException {
        failedReason = 'អស់ពេលក្នុងការភ្ជាប់';
      } catch (error) {
        failedReason = extractApiErrorMessage(error);
      }
    }

    if (!mounted) {
      return;
    }

    setState(() {
      _isTestingConnection = false;
      _connectionMessage =
          'ភ្ជាប់មិនបានទេ។ សូមពិនិត្យ URL/បណ្តាញ (${failedReason ?? 'កំហុសដែលមិនស្គាល់'})';
      _connectionColor = const Color(0xFFB42318);
    });
  }

  bool _isJsonResponse(http.Response response) {
    final contentType = response.headers['content-type']?.toLowerCase() ?? '';
    return contentType.contains('application/json') ||
        contentType.contains('+json');
  }

  Future<void> _openAccessRequestDialog(String machineNumber) async {
    final deviceInfo = await _deviceInfoFuture;
    if (!mounted) {
      return;
    }

    final requestFormKey = GlobalKey<FormState>();
    final nameController = TextEditingController();
    final emailController = TextEditingController();
    final passwordController = TextEditingController();
    final phoneController = TextEditingController();
    final reasonController = TextEditingController();
    bool isSubmitting = false;
    bool obscurePassword = true;

    final deviceSummary = _deviceMetadataService.summarize(deviceInfo);

    final result = await showDialog<DeviceAccessRequestResult>(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              backgroundColor: const Color(0xFFF8FAFC),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
              title: const Text('ស្នើសុំសិទ្ធិចូលប្រើប្រាស់'),
              content: SingleChildScrollView(
                child: Form(
                  key: requestFormKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      TextFormField(
                        controller: nameController,
                        decoration: const InputDecoration(labelText: 'ឈ្មោះ'),
                        validator: (value) {
                          if (value == null || value.trim().isEmpty) {
                            return 'សូមបញ្ចូលឈ្មោះ';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: emailController,
                        keyboardType: TextInputType.emailAddress,
                        decoration: const InputDecoration(labelText: 'អ៊ីមែល'),
                        validator: (value) {
                          final raw = (value ?? '').trim();
                          if (raw.isEmpty) {
                            return 'សូមបញ្ចូលអ៊ីមែល';
                          }
                          if (!raw.contains('@')) {
                            return 'អ៊ីមែលមិនត្រឹមត្រូវ';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: passwordController,
                        obscureText: obscurePassword,
                        decoration: InputDecoration(
                          labelText: 'ពាក្យសម្ងាត់',
                          suffixIcon: IconButton(
                            onPressed: () {
                              setDialogState(() {
                                obscurePassword = !obscurePassword;
                              });
                            },
                            icon: Icon(
                              obscurePassword
                                  ? Icons.visibility_off_outlined
                                  : Icons.visibility_outlined,
                            ),
                          ),
                        ),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'សូមបញ្ចូលពាក្យសម្ងាត់';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: phoneController,
                        keyboardType: TextInputType.phone,
                        decoration: const InputDecoration(
                          labelText: 'ទូរស័ព្ទ',
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
                          'លេខស្នើសុំឧបករណ៍: $machineNumber',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          'ឧបករណ៍: $deviceSummary',
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
                  child: const Text('បោះបង់'),
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
                                        email: emailController.text,
                                        password: passwordController.text,
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
                                  SnackBar(
                                    content: Text(
                                      extractApiErrorMessage(error),
                                    ),
                                  ),
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
                          : const Text('ផ្ញើសំណើ'),
                ),
              ],
            );
          },
        );
      },
    );

    nameController.dispose();
    emailController.dispose();
    passwordController.dispose();
    phoneController.dispose();
    reasonController.dispose();

    if (!mounted || result == null) {
      return;
    }

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text('${result.message} (លេខសំណើ: ${result.requestId})'),
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('ការកំណត់ប្រព័ន្ធ')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFDDE9E4)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'ការតភ្ជាប់ទៅម៉ាស៊ីនមេ',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'កំណត់ URL ម៉ាស៊ីនមេ និងអាចតេស្តការតភ្ជាប់បាន (វាយ IP ឬ Domain ក៏បាន)។',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: const Color(0xFF5D6D65),
                  ),
                ),
                const SizedBox(height: 12),
                SelectableText(
                  'អាសយដ្ឋានដែលកំពុងប្រើ: ${ApiConfig.baseUrl}',
                  style: theme.textTheme.bodySmall,
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: _serverController,
                  minLines: 3,
                  maxLines: 6,
                  decoration: const InputDecoration(
                    labelText: 'អាសយដ្ឋានម៉ាស៊ីនមេ',
                    hintText:
                        '192.168.1.9\n192.168.1.9/PHDHRM/backend\n10.0.2.2/PHDHRM/backend\n10.0.2.2:8000',
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    FilledButton.icon(
                      onPressed:
                          _isTestingConnection ? null : _testServerConnection,
                      icon: const Icon(Icons.wifi_tethering_outlined),
                      label: Text(
                        _isTestingConnection
                            ? 'កំពុងតេស្ត...'
                            : 'តេស្តការតភ្ជាប់',
                      ),
                    ),
                    FilledButton.tonalIcon(
                      onPressed: _isSavingServer ? null : _saveServerConfig,
                      icon: const Icon(Icons.save_outlined),
                      label: Text(
                        _isSavingServer ? 'កំពុងរក្សាទុក...' : 'រក្សាទុក',
                      ),
                    ),
                    TextButton(
                      onPressed: _useDefaultServerConfig,
                      child: const Text('ប្រើលំនាំដើម'),
                    ),
                  ],
                ),
                if (_connectionMessage != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    _connectionMessage!,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: _connectionColor,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 14),
          FutureBuilder<String>(
            future: _machineNumberFuture,
            builder: (context, machineSnapshot) {
              final machineNumber = machineSnapshot.data ?? '...';

              return Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFDDE9E4)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'ឧបករណ៍ និងសិទ្ធិចូលប្រើ',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      'លេខស្នើសុំឧបករណ៍',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: const Color(0xFF5D6D65),
                      ),
                    ),
                    const SizedBox(height: 4),
                    SelectableText(
                      machineNumber,
                      style: theme.textTheme.bodyLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF0B6B58),
                      ),
                    ),
                    const SizedBox(height: 10),
                    FutureBuilder<Map<String, dynamic>>(
                      future: _deviceInfoFuture,
                      builder: (context, snapshot) {
                        final info = snapshot.data;
                        final summary =
                            info == null
                                ? '...'
                                : _deviceMetadataService.summarize(info);

                        return Text(
                          'ឧបករណ៍: $summary',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: const Color(0xFF5D6D65),
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        TextButton.icon(
                          onPressed: () async {
                            final messenger = ScaffoldMessenger.of(context);
                            await Clipboard.setData(
                              ClipboardData(text: machineNumber),
                            );
                            if (!mounted) {
                              return;
                            }
                            messenger
                              ..hideCurrentSnackBar()
                              ..showSnackBar(
                                const SnackBar(
                                  content: Text('បានចម្លងលេខម៉ាស៊ីន'),
                                ),
                              );
                          },
                          icon: const Icon(Icons.copy_outlined, size: 18),
                          label: const Text('ចម្លងលេខម៉ាស៊ីន'),
                        ),
                        OutlinedButton.icon(
                          onPressed:
                              () => _openAccessRequestDialog(machineNumber),
                          icon: const Icon(Icons.send_outlined, size: 18),
                          label: const Text('ផ្ញើសំណើអនុញ្ញាត'),
                        ),
                      ],
                    ),
                  ],
                ),
              );
            },
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFDDE9E4)),
            ),
            child: Text(
              'ព័ត៌មាន: ទំព័រចូលប្រើ ត្រូវបានបង្រួមសម្រាប់ការបញ្ចូលអ៊ីមែល និងពាក្យសម្ងាត់ប៉ុណ្ណោះ។ ការកំណត់ផ្សេងៗ ត្រូវបានផ្លាស់មកទំព័រ ការកំណត់ប្រព័ន្ធ នេះ។',
              style: theme.textTheme.bodySmall?.copyWith(
                color: const Color(0xFF5D6D65),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
