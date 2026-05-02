import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../../core/network/api_exception.dart';
import '../models/correspondence_models.dart';
import '../services/correspondence_service.dart';

class CorrespondenceCreatePage extends StatefulWidget {
  const CorrespondenceCreatePage({
    super.key,
    required this.service,
    required this.languageFuture,
    this.canCreateIncoming = true,
    this.canCreateOutgoing = true,
  });

  final CorrespondenceService service;
  final Future<Map<String, String>> languageFuture;
  final bool canCreateIncoming;
  final bool canCreateOutgoing;

  @override
  State<CorrespondenceCreatePage> createState() =>
      _CorrespondenceCreatePageState();
}

class _CorrespondenceCreatePageState extends State<CorrespondenceCreatePage> {
  final _formKey = GlobalKey<FormState>();
  final _subjectController = TextEditingController();
  final _letterNoController = TextEditingController();
  final _fromOrgController = TextEditingController();
  final _toOrgController = TextEditingController();
  final _summaryController = TextEditingController();

  late final Future<List<CorrespondenceLookupOption>> _orgUnitsFuture;
  List<CorrespondenceLookupOption> _orgUnits = const [];

  String _letterType = 'incoming';
  String _priority = 'normal';
  DateTime? _letterDate;
  DateTime? _receivedDate;
  DateTime? _sentDate;
  DateTime? _dueDate;
  CorrespondenceLookupOption? _originDepartment;
  List<CorrespondenceLookupOption> _toDepartments = const [];
  List<CorrespondenceLookupOption> _ccDepartments = const [];
  List<CorrespondenceLookupOption> _toUsers = const [];
  List<CorrespondenceLookupOption> _ccUsers = const [];
  List<PlatformFile> _attachments = const [];
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    if (!widget.canCreateIncoming && widget.canCreateOutgoing) {
      _letterType = 'outgoing';
    }
    _orgUnitsFuture = widget.service.fetchOrgUnits().then((value) {
      _orgUnits = value;
      return value;
    });
  }

  @override
  void dispose() {
    _subjectController.dispose();
    _letterNoController.dispose();
    _fromOrgController.dispose();
    _toOrgController.dispose();
    _summaryController.dispose();
    super.dispose();
  }

  String _tr(Map<String, String> language, String key, String fallback) {
    final value = language[key]?.trim();
    return value == null || value.isEmpty ? fallback : value;
  }

  String _formatDate(DateTime? value) {
    if (value == null) {
      return '--/--/----'.replaceAll('/', '-');
    }
    final day = value.day.toString().padLeft(2, '0');
    final month = value.month.toString().padLeft(2, '0');
    return '$day-$month-${value.year}';
  }

  Future<void> _pickDate(
    DateTime? current,
    ValueSetter<DateTime> onPicked,
  ) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: current ?? now,
      firstDate: DateTime(now.year - 2),
      lastDate: DateTime(now.year + 3),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: Color(0xFF0B6B58),
              onPrimary: Colors.white,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked == null || !mounted) {
      return;
    }

    setState(() => onPicked(picked));
  }

  Future<void> _pickAttachments() async {
    final result = await FilePicker.platform.pickFiles(
      allowMultiple: true,
      withData: true,
    );
    if (result == null || result.files.isEmpty || !mounted) {
      return;
    }

    setState(() {
      _attachments = [..._attachments, ...result.files];
    });
  }

  void _removeAttachment(PlatformFile file) {
    setState(() {
      _attachments = _attachments.where((item) => item != file).toList();
    });
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor:
            isError ? const Color(0xFFDC2626) : const Color(0xFF0B6B58),
      ),
    );
  }

  void _onLetterTypeChanged(String value) {
    setState(() {
      _letterType = value;
      if (value == 'incoming') {
        _sentDate = null;
        _toDepartments = const [];
        _ccDepartments = const [];
        _toUsers = const [];
        _ccUsers = const [];
      } else {
        _receivedDate = null;
        _dueDate = null;
        _fromOrgController.clear();
        _toOrgController.clear();
      }
    });
  }

  Future<void> _selectOriginDepartment(Map<String, String> language) async {
    if (_orgUnits.isEmpty) {
      _showMessage(
        _tr(language, 'loading', 'មិនទាន់មានបញ្ជីអង្គភាព'),
        isError: true,
      );
      return;
    }

    final selected = await showModalBottomSheet<CorrespondenceLookupOption>(
      context: context,
      isScrollControlled: true,
      builder:
          (context) => _LookupSingleSelectSheet(
            title: 'ជ្រើសរើសអង្គភាពដើម',
            options: _orgUnits,
            initialSelection: _originDepartment,
          ),
    );

    if (selected != null && mounted) {
      setState(() => _originDepartment = selected);
    }
  }

  Future<void> _selectDepartments({
    required String title,
    required List<CorrespondenceLookupOption> current,
    required ValueSetter<List<CorrespondenceLookupOption>> onSelected,
  }) async {
    if (_orgUnits.isEmpty) {
      return;
    }

    final selected =
        await showModalBottomSheet<List<CorrespondenceLookupOption>>(
          context: context,
          isScrollControlled: true,
          builder:
              (context) => _LookupMultiSelectSheet(
                title: title,
                options: _orgUnits,
                initialSelection: current,
              ),
        );

    if (selected != null && mounted) {
      setState(() => onSelected(selected));
    }
  }

  Future<void> _selectUsers({
    required String title,
    required List<CorrespondenceLookupOption> current,
    required ValueSetter<List<CorrespondenceLookupOption>> onSelected,
  }) async {
    final selected =
        await showModalBottomSheet<List<CorrespondenceLookupOption>>(
          context: context,
          isScrollControlled: true,
          builder:
              (context) => _UserLookupMultiSelectSheet(
                title: title,
                service: widget.service,
                initialSelection: current,
              ),
        );

    if (selected != null && mounted) {
      setState(() => onSelected(selected));
    }
  }

  Future<void> _submit(
    Map<String, String> language, {
    required String sendAction,
  }) async {
    if (_letterType == 'incoming' && !widget.canCreateIncoming) {
      _showMessage('អ្នកមិនមានសិទ្ធិបង្កើតលិខិតចូល', isError: true);
      return;
    }
    if (_letterType == 'outgoing' && !widget.canCreateOutgoing) {
      _showMessage('អ្នកមិនមានសិទ្ធិបង្កើតលិខិតចេញ', isError: true);
      return;
    }

    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    if (_letterType == 'outgoing' &&
        sendAction == 'send' &&
        _toDepartments.isEmpty &&
        _ccDepartments.isEmpty &&
        _toUsers.isEmpty &&
        _ccUsers.isEmpty) {
      _showMessage('សូមជ្រើសរើសអ្នកទទួល To/CC យ៉ាងហោចណាស់មួយ', isError: true);
      return;
    }

    setState(() => _submitting = true);

    try {
      await widget.service.createLetter(
        CorrespondenceCreateRequest(
          letterType: _letterType,
          subject: _subjectController.text.trim(),
          letterNo:
              _letterNoController.text.trim().isEmpty
                  ? null
                  : _letterNoController.text.trim(),
          priority: _priority,
          fromOrg:
              _fromOrgController.text.trim().isEmpty
                  ? null
                  : _fromOrgController.text.trim(),
          toOrg:
              _toOrgController.text.trim().isEmpty
                  ? null
                  : _toOrgController.text.trim(),
          letterDate: _letterDate,
          receivedDate: _letterType == 'incoming' ? _receivedDate : null,
          sentDate: _letterType == 'outgoing' ? _sentDate : null,
          dueDate: _letterType == 'incoming' ? _dueDate : null,
          summary:
              _summaryController.text.trim().isEmpty
                  ? null
                  : _summaryController.text.trim(),
          originDepartmentId: _originDepartment?.id,
          toDepartmentIds: _toDepartments.map((item) => item.id).toList(),
          ccDepartmentIds: _ccDepartments.map((item) => item.id).toList(),
          toUserIds: _toUsers.map((item) => item.id).toList(),
          ccUserIds: _ccUsers.map((item) => item.id).toList(),
          sendAction: sendAction,
        ),
        attachments:
            _attachments
                .map(
                  (file) => CorrespondenceAttachmentInput(
                    name: file.name,
                    path: file.path,
                    bytes: file.bytes?.toList(),
                  ),
                )
                .toList(),
      );

      if (!mounted) {
        return;
      }

      _showMessage(_tr(language, 'data_save', 'បានរក្សាទុកដោយជោគជ័យ'));
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) {
        return;
      }
      _showMessage(extractApiErrorMessage(error), isError: true);
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, String>>(
      future: widget.languageFuture,
      builder: (context, languageSnapshot) {
        final language = languageSnapshot.data ?? const <String, String>{};

        return FutureBuilder<List<CorrespondenceLookupOption>>(
          future: _orgUnitsFuture,
          builder: (context, orgUnitSnapshot) {
            final orgUnits = orgUnitSnapshot.data ?? _orgUnits;
            if (orgUnits.isNotEmpty && _orgUnits.isEmpty) {
              _orgUnits = orgUnits;
            }

            return Scaffold(
              appBar: AppBar(
                title: Text(_tr(language, 'new_letter', 'បង្កើតលិខិតថ្មី')),
              ),
              body: Form(
                key: _formKey,
                child: ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    _FormSectionCard(
                      title: _tr(language, 'correspondence', 'លិខិតរដ្ឋបាល'),
                      child: Column(
                        children: [
                          DropdownButtonFormField<String>(
                            initialValue: _letterType,
                            items: [
                              if (widget.canCreateIncoming)
                                const DropdownMenuItem(
                                  value: 'incoming',
                                  child: Text('លិខិតចូល'),
                                ),
                              if (widget.canCreateOutgoing)
                                const DropdownMenuItem(
                                  value: 'outgoing',
                                  child: Text('លិខិតចេញ'),
                                ),
                            ],
                            decoration: InputDecoration(
                              labelText: _tr(
                                language,
                                'letter_type',
                                'ប្រភេទលិខិត',
                              ),
                              border: const OutlineInputBorder(),
                            ),
                            onChanged:
                                (!widget.canCreateIncoming &&
                                        !widget.canCreateOutgoing)
                                    ? null
                                    : (value) {
                                      if (value != null) {
                                        _onLetterTypeChanged(value);
                                      }
                                    },
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _subjectController,
                            decoration: InputDecoration(
                              labelText: _tr(language, 'subject', 'ប្រធានបទ'),
                              border: const OutlineInputBorder(),
                            ),
                            validator: (value) {
                              if (value == null || value.trim().isEmpty) {
                                return 'សូមបញ្ចូលប្រធានបទ';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _letterNoController,
                            decoration: const InputDecoration(
                              labelText: 'លេខលិខិត',
                              border: OutlineInputBorder(),
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            readOnly: true,
                            decoration: InputDecoration(
                              labelText: 'លេខចុះបញ្ជី',
                              hintText: 'បង្កើតស្វ័យប្រវត្តពេលរក្សាទុក',
                              border: const OutlineInputBorder(),
                              filled: true,
                              fillColor: const Color(0xFFF8FAFC),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    _FormSectionCard(
                      title: 'ព័ត៌មានបន្ថែម',
                      child: Column(
                        children: [
                          DropdownButtonFormField<String>(
                            initialValue: _priority,
                            decoration: const InputDecoration(
                              labelText: 'អាទិភាព',
                              border: OutlineInputBorder(),
                            ),
                            items: const [
                              DropdownMenuItem(
                                value: 'normal',
                                child: Text('ធម្មតា'),
                              ),
                              DropdownMenuItem(
                                value: 'urgent',
                                child: Text('បន្ទាន់'),
                              ),
                              DropdownMenuItem(
                                value: 'confidential',
                                child: Text('សម្ងាត់'),
                              ),
                            ],
                            onChanged: (value) {
                              if (value != null) {
                                setState(() => _priority = value);
                              }
                            },
                          ),
                          if (_letterType == 'incoming') ...[
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _fromOrgController,
                              decoration: const InputDecoration(
                                labelText: 'អង្គភាពចេញលិខិត',
                                border: OutlineInputBorder(),
                              ),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _toOrgController,
                              decoration: const InputDecoration(
                                labelText: 'អង្គភាពទទួល',
                                border: OutlineInputBorder(),
                              ),
                            ),
                            const SizedBox(height: 12),
                            _SelectionFieldTile(
                              label: 'អង្គភាពដើម',
                              value:
                                  _originDepartment?.text ??
                                  'ជ្រើសរើសអង្គភាពដើម',
                              onTap: () => _selectOriginDepartment(language),
                            ),
                          ],
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _summaryController,
                            minLines: 3,
                            maxLines: 5,
                            decoration: const InputDecoration(
                              labelText: 'ខ្លឹមសារសង្ខេប',
                              border: OutlineInputBorder(),
                              alignLabelWithHint: true,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    _FormSectionCard(
                      title: 'កាលបរិច្ឆេទ',
                      child: Column(
                        children: [
                          _DateFieldTile(
                            label: 'ថ្ងៃលិខិត',
                            value: _formatDate(_letterDate),
                            onTap:
                                () => _pickDate(
                                  _letterDate,
                                  (value) => _letterDate = value,
                                ),
                          ),
                          if (_letterType == 'incoming')
                            _DateFieldTile(
                              label: 'ថ្ងៃទទួលលិខិត',
                              value: _formatDate(_receivedDate),
                              onTap:
                                  () => _pickDate(
                                    _receivedDate,
                                    (value) => _receivedDate = value,
                                  ),
                            ),
                          if (_letterType == 'outgoing')
                            _DateFieldTile(
                              label: 'ថ្ងៃផ្ញើចេញ',
                              value: _formatDate(_sentDate),
                              onTap:
                                  () => _pickDate(
                                    _sentDate,
                                    (value) => _sentDate = value,
                                  ),
                            ),
                          if (_letterType == 'incoming')
                            _DateFieldTile(
                              label: 'ថ្ងៃកំណត់',
                              value: _formatDate(_dueDate),
                              onTap:
                                  () => _pickDate(
                                    _dueDate,
                                    (value) => _dueDate = value,
                                  ),
                            ),
                        ],
                      ),
                    ),
                    if (_letterType == 'outgoing') ...[
                      const SizedBox(height: 12),
                      _FormSectionCard(
                        title: 'អ្នកទទួល',
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _SelectionFieldTile(
                              label: 'ទៅអង្គភាព (To)',
                              value:
                                  _toDepartments.isEmpty
                                      ? 'ជ្រើសរើសអង្គភាព'
                                      : '${_toDepartments.length} អង្គភាព',
                              onTap:
                                  () => _selectDepartments(
                                    title: 'ជ្រើសរើសអង្គភាព To',
                                    current: _toDepartments,
                                    onSelected:
                                        (value) => _toDepartments = value,
                                  ),
                            ),
                            _SelectedChipWrap(
                              items: _toDepartments,
                              onRemove:
                                  (item) => setState(
                                    () =>
                                        _toDepartments =
                                            _toDepartments
                                                .where(
                                                  (value) =>
                                                      value.id != item.id,
                                                )
                                                .toList(),
                                  ),
                            ),
                            const SizedBox(height: 10),
                            _SelectionFieldTile(
                              label: 'ជូនចម្លងអង្គភាព (CC)',
                              value:
                                  _ccDepartments.isEmpty
                                      ? 'ជ្រើសរើសអង្គភាព'
                                      : '${_ccDepartments.length} អង្គភាព',
                              onTap:
                                  () => _selectDepartments(
                                    title: 'ជ្រើសរើសអង្គភាព CC',
                                    current: _ccDepartments,
                                    onSelected:
                                        (value) => _ccDepartments = value,
                                  ),
                            ),
                            _SelectedChipWrap(
                              items: _ccDepartments,
                              onRemove:
                                  (item) => setState(
                                    () =>
                                        _ccDepartments =
                                            _ccDepartments
                                                .where(
                                                  (value) =>
                                                      value.id != item.id,
                                                )
                                                .toList(),
                                  ),
                            ),
                            const SizedBox(height: 10),
                            _SelectionFieldTile(
                              label: 'ទៅបុគ្គល (To)',
                              value:
                                  _toUsers.isEmpty
                                      ? 'ស្វែងរកអ្នកប្រើ'
                                      : '${_toUsers.length} នាក់',
                              onTap:
                                  () => _selectUsers(
                                    title: 'ជ្រើសរើសអ្នកទទួល To',
                                    current: _toUsers,
                                    onSelected: (value) => _toUsers = value,
                                  ),
                            ),
                            _SelectedChipWrap(
                              items: _toUsers,
                              onRemove:
                                  (item) => setState(
                                    () =>
                                        _toUsers =
                                            _toUsers
                                                .where(
                                                  (value) =>
                                                      value.id != item.id,
                                                )
                                                .toList(),
                                  ),
                            ),
                            const SizedBox(height: 10),
                            _SelectionFieldTile(
                              label: 'ជូនចម្លងបុគ្គល (CC)',
                              value:
                                  _ccUsers.isEmpty
                                      ? 'ស្វែងរកអ្នកប្រើ'
                                      : '${_ccUsers.length} នាក់',
                              onTap:
                                  () => _selectUsers(
                                    title: 'ជ្រើសរើសអ្នកទទួល CC',
                                    current: _ccUsers,
                                    onSelected: (value) => _ccUsers = value,
                                  ),
                            ),
                            _SelectedChipWrap(
                              items: _ccUsers,
                              onRemove:
                                  (item) => setState(
                                    () =>
                                        _ccUsers =
                                            _ccUsers
                                                .where(
                                                  (value) =>
                                                      value.id != item.id,
                                                )
                                                .toList(),
                                  ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: 12),
                    _FormSectionCard(
                      title: 'ឯកសារភ្ជាប់',
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          OutlinedButton.icon(
                            onPressed: _pickAttachments,
                            icon: const Icon(Icons.attach_file_outlined),
                            label: const Text('បន្ថែមឯកសារ'),
                          ),
                          const SizedBox(height: 10),
                          if (_attachments.isEmpty)
                            const Text(
                              'មិនទាន់មានឯកសារភ្ជាប់',
                              style: TextStyle(color: Color(0xFF64748B)),
                            )
                          else
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children:
                                  _attachments
                                      .map(
                                        (file) => Chip(
                                          label: Text(file.name),
                                          onDeleted:
                                              () => _removeAttachment(file),
                                        ),
                                      )
                                      .toList(),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Text(
                        _letterType == 'outgoing'
                            ? 'លិខិតចេញអាចរក្សាទុកជា draft ឬរក្សាទុក និងផ្ញើទៅអ្នកទទួល To/CC បាន។'
                            : 'លិខិតចូលនឹងត្រូវរក្សាទុកជាមូលដ្ឋានសិន ហើយ workflow បន្តអនុវត្តនៅជំហានបន្ទាប់។',
                        style: const TextStyle(
                          fontSize: 13,
                          color: Color(0xFF475569),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (_letterType == 'outgoing')
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed:
                                  _submitting
                                      ? null
                                      : () => _submit(
                                        language,
                                        sendAction: 'draft',
                                      ),
                              icon: const Icon(Icons.save_outlined),
                              label: const Text('រក្សាទុក Draft'),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: ElevatedButton.icon(
                              onPressed:
                                  _submitting
                                      ? null
                                      : () =>
                                          _submit(language, sendAction: 'send'),
                              icon:
                                  _submitting
                                      ? const SizedBox(
                                        height: 18,
                                        width: 18,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                        ),
                                      )
                                      : const Icon(Icons.send_outlined),
                              label: Text(
                                _submitting
                                    ? 'កំពុងដំណើរការ...'
                                    : 'រក្សាទុក និងផ្ញើ',
                              ),
                            ),
                          ),
                        ],
                      )
                    else
                      SizedBox(
                        height: 52,
                        child: ElevatedButton.icon(
                          onPressed:
                              _submitting
                                  ? null
                                  : () =>
                                      _submit(language, sendAction: 'draft'),
                          icon:
                              _submitting
                                  ? const SizedBox(
                                    height: 18,
                                    width: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                  : const Icon(Icons.save_outlined),
                          label: Text(
                            _submitting ? 'កំពុងរក្សាទុក...' : 'រក្សាទុក',
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _FormSectionCard extends StatelessWidget {
  const _FormSectionCard({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0F0F172A),
            blurRadius: 12,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _DateFieldTile extends StatelessWidget {
  const _DateFieldTile({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: label,
            border: const OutlineInputBorder(),
            suffixIcon: const Icon(Icons.calendar_month_outlined),
          ),
          child: Text(value),
        ),
      ),
    );
  }
}

class _SelectionFieldTile extends StatelessWidget {
  const _SelectionFieldTile({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: label,
            border: const OutlineInputBorder(),
            suffixIcon: const Icon(Icons.expand_more_rounded),
          ),
          child: Text(value),
        ),
      ),
    );
  }
}

class _SelectedChipWrap extends StatelessWidget {
  const _SelectedChipWrap({required this.items, required this.onRemove});

  final List<CorrespondenceLookupOption> items;
  final ValueSetter<CorrespondenceLookupOption> onRemove;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const SizedBox.shrink();
    }

    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children:
          items
              .map(
                (item) => InputChip(
                  label: Text(item.text),
                  onDeleted: () => onRemove(item),
                ),
              )
              .toList(),
    );
  }
}

class _LookupSingleSelectSheet extends StatefulWidget {
  const _LookupSingleSelectSheet({
    required this.title,
    required this.options,
    this.initialSelection,
  });

  final String title;
  final List<CorrespondenceLookupOption> options;
  final CorrespondenceLookupOption? initialSelection;

  @override
  State<_LookupSingleSelectSheet> createState() =>
      _LookupSingleSelectSheetState();
}

class _LookupSingleSelectSheetState extends State<_LookupSingleSelectSheet> {
  final _searchController = TextEditingController();
  Timer? _searchDebounce;
  String _query = '';
  static const int _maxRenderedItems = 80;

  void _onQueryChanged(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 180), () {
      if (!mounted) {
        return;
      }
      setState(() => _query = value.trim());
    });
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final filtered =
        widget.options
            .where((item) {
              if (_query.trim().isEmpty) {
                return true;
              }
              final query = _query.toLowerCase();
              return item.text.toLowerCase().contains(query) ||
                  (item.subtitle?.toLowerCase().contains(query) ?? false);
            })
            .take(_maxRenderedItems)
            .toList();

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              widget.title,
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _searchController,
              decoration: const InputDecoration(
                hintText: 'ស្វែងរក',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.search),
              ),
              onChanged: _onQueryChanged,
            ),
            const SizedBox(height: 12),
            Flexible(
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: filtered.length,
                itemBuilder: (context, index) {
                  final item = filtered[index];
                  final selected = widget.initialSelection?.id == item.id;
                  return ListTile(
                    leading: Icon(
                      selected
                          ? Icons.radio_button_checked
                          : Icons.radio_button_off,
                      color:
                          selected
                              ? const Color(0xFF0B6B58)
                              : const Color(0xFF94A3B8),
                    ),
                    title: Text(item.text),
                    subtitle:
                        item.subtitle == null ? null : Text(item.subtitle!),
                    onTap: () => Navigator.of(context).pop(item),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LookupMultiSelectSheet extends StatefulWidget {
  const _LookupMultiSelectSheet({
    required this.title,
    required this.options,
    required this.initialSelection,
  });

  final String title;
  final List<CorrespondenceLookupOption> options;
  final List<CorrespondenceLookupOption> initialSelection;

  @override
  State<_LookupMultiSelectSheet> createState() =>
      _LookupMultiSelectSheetState();
}

class _LookupMultiSelectSheetState extends State<_LookupMultiSelectSheet> {
  final _searchController = TextEditingController();
  Timer? _searchDebounce;
  late List<CorrespondenceLookupOption> _selected;
  String _query = '';
  static const int _maxRenderedItems = 80;

  void _onQueryChanged(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 180), () {
      if (!mounted) {
        return;
      }
      setState(() => _query = value.trim());
    });
  }

  @override
  void initState() {
    super.initState();
    _selected = [...widget.initialSelection];
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final filtered =
        widget.options
            .where((item) {
              if (_query.trim().isEmpty) {
                return true;
              }
              final query = _query.toLowerCase();
              return item.text.toLowerCase().contains(query) ||
                  (item.subtitle?.toLowerCase().contains(query) ?? false);
            })
            .take(_maxRenderedItems)
            .toList();

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              widget.title,
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _searchController,
              decoration: const InputDecoration(
                hintText: 'ស្វែងរក',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.search),
              ),
              onChanged: _onQueryChanged,
            ),
            const SizedBox(height: 12),
            Flexible(
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: filtered.length,
                itemBuilder: (context, index) {
                  final item = filtered[index];
                  final checked = _selected.any((value) => value.id == item.id);
                  return CheckboxListTile(
                    value: checked,
                    title: Text(item.text),
                    subtitle:
                        item.subtitle == null ? null : Text(item.subtitle!),
                    onChanged: (value) {
                      setState(() {
                        if (value == true) {
                          _selected = [
                            ..._selected.where((row) => row.id != item.id),
                            item,
                          ];
                        } else {
                          _selected =
                              _selected
                                  .where((row) => row.id != item.id)
                                  .toList();
                        }
                      });
                    },
                  );
                },
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => Navigator.of(context).pop(_selected),
                child: const Text('រួចរាល់'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _UserLookupMultiSelectSheet extends StatefulWidget {
  const _UserLookupMultiSelectSheet({
    required this.title,
    required this.service,
    required this.initialSelection,
  });

  final String title;
  final CorrespondenceService service;
  final List<CorrespondenceLookupOption> initialSelection;

  @override
  State<_UserLookupMultiSelectSheet> createState() =>
      _UserLookupMultiSelectSheetState();
}

class _UserLookupMultiSelectSheetState
    extends State<_UserLookupMultiSelectSheet> {
  final _searchController = TextEditingController();
  late List<CorrespondenceLookupOption> _selected;
  List<CorrespondenceLookupOption> _results = const [];
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _selected = [...widget.initialSelection];
    _results = [...widget.initialSelection];
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _search() async {
    setState(() => _loading = true);
    try {
      final results = await widget.service.searchUserOptions(
        _searchController.text,
      );
      if (!mounted) {
        return;
      }
      setState(() => _results = results);
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              widget.title,
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _searchController,
                    decoration: const InputDecoration(
                      hintText: 'ស្វែងរកអ្នកប្រើ',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.search),
                    ),
                    onSubmitted: (_) => _search(),
                  ),
                ),
                const SizedBox(width: 8),
                ElevatedButton(
                  onPressed: _loading ? null : _search,
                  child: const Text('ស្វែងរក'),
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (_loading)
              const Padding(
                padding: EdgeInsets.all(16),
                child: CircularProgressIndicator(),
              )
            else
              Flexible(
                child: ListView.builder(
                  shrinkWrap: true,
                  itemCount: _results.length,
                  itemBuilder: (context, index) {
                    final item = _results[index];
                    final checked = _selected.any(
                      (value) => value.id == item.id,
                    );
                    return CheckboxListTile(
                      value: checked,
                      title: Text(item.text),
                      subtitle:
                          item.subtitle == null ? null : Text(item.subtitle!),
                      onChanged: (value) {
                        setState(() {
                          if (value == true) {
                            _selected = [
                              ..._selected.where((row) => row.id != item.id),
                              item,
                            ];
                          } else {
                            _selected =
                                _selected
                                    .where((row) => row.id != item.id)
                                    .toList();
                          }
                        });
                      },
                    );
                  },
                ),
              ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => Navigator.of(context).pop(_selected),
                child: const Text('រួចរាល់'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
