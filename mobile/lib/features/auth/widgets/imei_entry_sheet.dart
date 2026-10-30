import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/device/imei.dart';
import '../../../core/storage/machine_number_storage_service.dart';
import '../../../core/theme/app_design_system.dart';

/// Asks for this phone's IMEI (its machine number), saves it, and returns
/// it; null when dismissed. Android 10+ does not let an app read the IMEI,
/// so the sheet explains how to see it (*#06#) and checks what was typed.
Future<String?> showImeiEntrySheet(
  BuildContext context, {
  MachineNumberStorageService? storage,
  String? initial,
}) {
  return showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder:
        (_) => _ImeiEntrySheet(
          storage: storage ?? MachineNumberStorageService(),
          initial: initial,
        ),
  );
}

class _ImeiEntrySheet extends StatefulWidget {
  const _ImeiEntrySheet({required this.storage, this.initial});

  final MachineNumberStorageService storage;
  final String? initial;

  @override
  State<_ImeiEntrySheet> createState() => _ImeiEntrySheetState();
}

class _ImeiEntrySheetState extends State<_ImeiEntrySheet> {
  late final TextEditingController _controller = TextEditingController(
    text: widget.initial == null ? '' : normalizeImei(widget.initial!),
  );
  bool _saving = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  String get _digits => normalizeImei(_controller.text);

  bool get _iPhone => defaultTargetPlatform == TargetPlatform.iOS;

  String? get _error {
    final d = _digits;
    if (d.isEmpty) return null;
    if (d.length < 15) return 'IMEI មាន ១៥ ខ្ទង់ (បានបញ្ចូល ${_km(d.length)})';
    return isValidImei(d) ? null : 'លេខ IMEI មិនត្រឹមត្រូវ — សូមពិនិត្យម្ដងទៀត';
  }

  static String _km(int value) =>
      value.toString().split('').map((c) => '០១២៣៤៥៦៧៨៩'[int.parse(c)]).join();

  Future<void> _showImei() async {
    // The dialler shows the IMEI as soon as *#06# is typed.
    final opened = await launchUrl(
      Uri(scheme: 'tel', path: '*#06#'),
      mode: LaunchMode.externalApplication,
    ).catchError((_) => false);
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('សូមបើកកម្មវិធីហៅទូរសព្ទ ហើយចុច *#06#')),
      );
    }
  }

  Future<void> _save() async {
    if (!isValidImei(_digits)) return;
    setState(() => _saving = true);
    await widget.storage.saveImei(_digits);
    if (mounted) Navigator.of(context).pop(_digits);
  }

  @override
  Widget build(BuildContext context) {
    final accent = AppDesignSystem.colorForWeekday(DateTime.now().weekday);
    final valid = isValidImei(_digits);
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
        ),
        padding: EdgeInsets.fromLTRB(
          20,
          10,
          20,
          20 + MediaQuery.paddingOf(context).bottom,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      color: accent.withAlpha(26),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(
                      _iPhone
                          ? Icons.phone_iphone_rounded
                          : Icons.phone_android_rounded,
                      color: accent,
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Text(
                      'បញ្ចូលលេខ IMEI របស់ទូរសព្ទ',
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                'លេខ IMEI គឺជាលេខម៉ាស៊ីនដែលប្រព័ន្ធប្រើ ដើម្បីអនុញ្ញាតទូរសព្ទនេះ។ '
                '${_iPhone ? 'iPhone' : 'Android'} មិនអនុញ្ញាតឲ្យកម្មវិធីអាន IMEI '
                'ដោយស្វ័យប្រវត្តិទេ ដូច្នេះសូមបញ្ចូលវាម្ដងប៉ុណ្ណោះ។',
                style: const TextStyle(
                  fontSize: 13,
                  height: 1.6,
                  color: Color(0xFF475569),
                ),
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'របៀបមើល IMEI',
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      // iOS will not let an app dial *#06#, so iPhones use Settings.
                      _iPhone
                          ? '១. បើក ការកំណត់ (Settings) › ទូទៅ (General) › អំពី (About)\n'
                              '២. រំកិលចុះ រកមើល IMEI ហើយចុចឲ្យយូរលើលេខ ដើម្បីចម្លង\n'
                              '   (ឬវាយ *#06# ក្នុងកម្មវិធី Phone)\n'
                              '៣. ត្រឡប់មកវិញ ហើយបិទភ្ជាប់លេខ ១៥ ខ្ទង់ (បើមាន ២ ស៊ីម យក IMEI ទី ១)'
                          : '១. ចុចប៊ូតុង «បង្ហាញ IMEI» ខាងក្រោម (ឬវាយ *#06# ក្នុងកម្មវិធីហៅទូរសព្ទ)\n'
                              '២. ចម្លងលេខ IMEI (បើមាន ២ ស៊ីម យក IMEI ទី ១)\n'
                              '៣. ត្រឡប់មកវិញ ហើយបញ្ចូលលេខ ១៥ ខ្ទង់',
                      style: const TextStyle(
                        fontSize: 12.5,
                        height: 1.7,
                        color: Color(0xFF475569),
                      ),
                    ),
                  ],
                ),
              ),
              if (!_iPhone) ...[
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  onPressed: _showImei,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: accent,
                    side: BorderSide(color: accent.withAlpha(90)),
                    minimumSize: const Size.fromHeight(46),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  icon: const Icon(Icons.dialpad_rounded),
                  label: const Text('បង្ហាញ IMEI (*#06#)'),
                ),
              ],
              const SizedBox(height: 14),
              TextField(
                key: const ValueKey('imei-field'),
                controller: _controller,
                keyboardType: TextInputType.number,
                inputFormatters: [
                  FilteringTextInputFormatter.digitsOnly,
                  LengthLimitingTextInputFormatter(15),
                ],
                onChanged: (_) => setState(() {}),
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.5,
                ),
                decoration: InputDecoration(
                  labelText: 'លេខ IMEI (១៥ ខ្ទង់)',
                  hintText: '35xxxxxxxxxxxxx',
                  errorText: _error,
                  counterText: '',
                  suffixIcon:
                      valid
                          ? const Icon(
                            Icons.verified_rounded,
                            color: Color(0xFF16A34A),
                          )
                          : null,
                  filled: true,
                  fillColor: const Color(0xFFF8FAFC),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: valid && !_saving ? _save : null,
                style: FilledButton.styleFrom(
                  backgroundColor: accent,
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
                icon: const Icon(Icons.check_rounded),
                label: const Text(
                  'រក្សាទុក IMEI',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
