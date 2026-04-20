import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;

import '../../../core/config/api_config.dart';
import '../../../core/device/device_metadata_service.dart';
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

  late final Future<String> _machineNumberFuture;
  late final Future<Map<String, dynamic>> _deviceInfoFuture;

  bool _isTestingConnection = false;
  bool _isSavingServer = false;
  String? _connectionMessage;
  Color _connectionColor = const Color(0xFF5D6D65);

  @override
  void initState() {
    super.initState();
    _serverController.text =
        ApiConfig.hasStoredBaseUrls ? ApiConfig.baseUrls.join('\n') : '';
    _machineNumberFuture = MachineNumberStorageService().getMachineNumber();
    _deviceInfoFuture = _deviceMetadataService.collect();
  }

  @override
  void dispose() {
    _serverController.dispose();
    super.dispose();
  }

  List<String> _resolveCandidateBaseUrls() {
    final custom = ApiConfig.normalizeConfiguredBaseUrls(_serverController.text);
    if (custom.isNotEmpty) {
      return custom;
    }

    return ApiConfig.baseUrls;
  }

  Future<void> _saveServerConfig() async {
    final values = ApiConfig.normalizeConfiguredBaseUrls(_serverController.text);
    if (values.isEmpty) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(content: Text('សូមបញ្ចូល Server URL ឱ្យត្រឹមត្រូវ')),
        );
      return;
    }

    setState(() {
      _isSavingServer = true;
    });

    await ApiConfig.saveConfiguredBaseUrls(values);

    if (!mounted) {
      return;
    }

    setState(() {
      _isSavingServer = false;
      _serverController.text = ApiConfig.baseUrls.join('\n');
    });

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(content: Text('បានរក្សាទុក Server: ${ApiConfig.baseUrl}')),
      );
  }

  Future<void> _useDefaultServerConfig() async {
    await ApiConfig.clearConfiguredBaseUrls();
    if (!mounted) {
      return;
    }

    setState(() {
      _serverController.clear();
      _connectionMessage = 'បានត្រឡប់ទៅ Server លំនាំដើម';
      _connectionColor = const Color(0xFF0B6B58);
    });
  }

  Future<void> _testServerConnection() async {
    final candidates = _resolveCandidateBaseUrls();
    if (candidates.isEmpty) {
      setState(() {
        _connectionMessage = 'មិនមាន Server URL សម្រាប់តេស្តទេ';
        _connectionColor = const Color(0xFFB42318);
      });
      return;
    }

    setState(() {
      _isTestingConnection = true;
      _connectionMessage = 'កំពុងតេស្តភ្ជាប់ទៅ Server...';
      _connectionColor = const Color(0xFF1D4F91);
    });

    String? failedReason;

    for (final base in candidates) {
      final uri = ApiConfig.buildUriForBase(base, '/auth/profile');

      try {
        final response = await http
            .get(uri, headers: const <String, String>{'Accept': 'application/json'})
            .timeout(const Duration(seconds: 8));

        if (!mounted) {
          return;
        }

        if (response.statusCode < 500) {
          setState(() {
            _isTestingConnection = false;
            _connectionMessage =
                'ភ្ជាប់ជោគជ័យ: $base (HTTP ${response.statusCode})';
            _connectionColor = const Color(0xFF0B6B58);
          });
          return;
        }

        failedReason = 'HTTP ${response.statusCode}';
      } on TimeoutException {
        failedReason = 'Connection timeout';
      } catch (error) {
        failedReason = error.toString();
      }
    }

    if (!mounted) {
      return;
    }

    setState(() {
      _isTestingConnection = false;
      _connectionMessage =
          'ភ្ជាប់មិនបានទេ។ សូមពិនិត្យ URL/Network (${failedReason ?? 'Unknown error'})';
      _connectionColor = const Color(0xFFB42318);
    });
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
                        decoration: const InputDecoration(labelText: 'Email'),
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
                        controller: passwordController,
                        obscureText: obscurePassword,
                        decoration: InputDecoration(
                          labelText: 'Password',
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
                            return 'សូមបញ្ចូល Password';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: phoneController,
                        keyboardType: TextInputType.phone,
                        decoration: const InputDecoration(labelText: 'ទូរស័ព្ទ'),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: reasonController,
                        minLines: 2,
                        maxLines: 4,
                        decoration: const InputDecoration(labelText: 'មូលហេតុស្នើសុំ'),
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
                                  await _deviceAccessRequestService.submitRequest(
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
        SnackBar(content: Text('${result.message} (Request ID: ${result.requestId})')),
      );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('System Settings'),
      ),
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
                  'Connection to Server',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'កំណត់ URL Server និងអាចតេស្តការតភ្ជាប់បាន។',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: const Color(0xFF5D6D65),
                  ),
                ),
                const SizedBox(height: 12),
                SelectableText(
                  'Current: ${ApiConfig.baseUrl}',
                  style: theme.textTheme.bodySmall,
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: _serverController,
                  minLines: 3,
                  maxLines: 6,
                  decoration: const InputDecoration(
                    labelText: 'Server URL(s)',
                    hintText:
                        'phdhrm.local/PHDHRM/backend\nphdhrm.local:8000\n192.168.1.9/PHDHRM/backend',
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
                        _isTestingConnection ? 'កំពុងតេស្ត...' : 'Test Connection',
                      ),
                    ),
                    FilledButton.tonalIcon(
                      onPressed: _isSavingServer ? null : _saveServerConfig,
                      icon: const Icon(Icons.save_outlined),
                      label: Text(_isSavingServer ? 'កំពុងរក្សាទុក...' : 'Save'),
                    ),
                    TextButton(
                      onPressed: _useDefaultServerConfig,
                      child: const Text('Use Default'),
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
                      'Device and Access',
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
                          'Device: $summary',
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
                            await Clipboard.setData(
                              ClipboardData(text: machineNumber),
                            );
                            if (!mounted) {
                              return;
                            }
                            ScaffoldMessenger.of(context)
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
                          onPressed: () => _openAccessRequestDialog(machineNumber),
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
              'ព័ត៌មាន: Login Screen ត្រូវបានបង្រួមសម្រាប់ Username/Password/Login ប៉ុណ្ណោះ។ ការកំណត់ផ្សេងៗត្រូវបានផ្លាស់មកទំព័រ System Settings នេះ។',
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