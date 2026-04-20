import 'package:flutter/material.dart';

import '../../auth/models/auth_user.dart';
import '../models/leave_request_models.dart';
import '../services/home_leave_service.dart';

class LeaveRequestPage extends StatefulWidget {
  LeaveRequestPage({
    super.key,
    required this.user,
    required this.language,
    HomeLeaveService? leaveService,
  }) : leaveService = leaveService ?? HomeLeaveService();

  final AuthUser user;
  final Map<String, String> language;
  final HomeLeaveService leaveService;

  @override
  State<LeaveRequestPage> createState() => _LeaveRequestPageState();
}

class _LeaveRequestPageState extends State<LeaveRequestPage> {
  late final TextEditingController _reasonController;

  List<LeaveTypeOption> _types = <LeaveTypeOption>[];
  List<LeaveRequestItem> _requests = <LeaveRequestItem>[];
  LeaveSummary _summary = const LeaveSummary(
    totalRemaining: 0,
    types: <LeaveBalanceItem>[],
  );

  int? _selectedTypeId;
  DateTime? _startDate;
  DateTime? _endDate;
  bool _loading = true;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _reasonController = TextEditingController();
    _loadAll();
  }

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  Future<void> _loadAll() async {
    setState(() {
      _loading = true;
    });

    try {
      final results = await Future.wait<dynamic>([
        widget.leaveService.fetchTypes(widget.user),
        widget.leaveService.fetchSummary(widget.user),
        widget.leaveService.fetchRequests(widget.user),
      ]);

      if (!mounted) {
        return;
      }

      final types = results[0] as List<LeaveTypeOption>;
      final summary = results[1] as LeaveSummary;
      final requests = results[2] as List<LeaveRequestItem>;

      setState(() {
        _types = types;
        _summary = summary;
        _requests = requests;
        _selectedTypeId ??= types.isNotEmpty ? types.first.id : null;
      });
    } catch (error) {
      if (mounted) {
        _showMessage(error.toString(), isError: true);
      }
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
        });
      }
    }
  }

  Future<void> _pickStartDate() async {
    final now = DateTime.now();
    final initial = _startDate ?? now;
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 2),
    );

    if (picked == null) {
      return;
    }

    setState(() {
      _startDate = DateTime(picked.year, picked.month, picked.day);
      if (_endDate != null && _endDate!.isBefore(_startDate!)) {
        _endDate = _startDate;
      }
    });
  }

  Future<void> _pickEndDate() async {
    final now = DateTime.now();
    final initial = _endDate ?? _startDate ?? now;
    final minDate = _startDate ?? DateTime(now.year - 1);

    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: minDate,
      lastDate: DateTime(now.year + 2),
    );

    if (picked == null) {
      return;
    }

    setState(() {
      _endDate = DateTime(picked.year, picked.month, picked.day);
    });
  }

  Future<void> _submitRequest() async {
    final typeId = _selectedTypeId;
    if (typeId == null) {
      _showMessage(_tr('leave_type', 'សូមជ្រើសប្រភេទច្បាប់'), isError: true);
      return;
    }

    if (_startDate == null || _endDate == null) {
      _showMessage(_tr('from_date', 'សូមជ្រើសកាលបរិច្ឆេទ'), isError: true);
      return;
    }

    final reason = _reasonController.text.trim();
    if (reason.isEmpty) {
      _showMessage(_tr('leave_reason', 'សូមបញ្ចូលហេតុផល'), isError: true);
      return;
    }

    setState(() {
      _submitting = true;
    });

    try {
      await widget.leaveService.submitRequest(
        user: widget.user,
        leaveTypeId: typeId,
        startDate: _startDate!,
        endDate: _endDate!,
        reason: reason,
      );

      if (!mounted) {
        return;
      }

      _reasonController.clear();
      setState(() {
        _startDate = null;
        _endDate = null;
      });

      _showMessage(_tr('leave_request_success', 'សំណើច្បាប់ត្រូវបានបញ្ជូនរួចរាល់'));
      await _loadAll();
    } catch (error) {
      if (mounted) {
        _showMessage(error.toString(), isError: true);
      }
    } finally {
      if (mounted) {
        setState(() {
          _submitting = false;
        });
      }
    }
  }

  Future<void> _cancelRequest(LeaveRequestItem request) async {
    setState(() {
      _submitting = true;
    });

    try {
      await widget.leaveService.cancelRequest(
        user: widget.user,
        requestId: request.id,
      );
      if (mounted) {
        _showMessage(_tr('cancel', 'បោះបង់សំណើបានជោគជ័យ'));
      }
      await _loadAll();
    } catch (error) {
      if (mounted) {
        _showMessage(error.toString(), isError: true);
      }
    } finally {
      if (mounted) {
        setState(() {
          _submitting = false;
        });
      }
    }
  }

  String _formatDate(DateTime? value) {
    if (value == null) {
      return '-';
    }
    final y = value.year.toString().padLeft(4, '0');
    final m = value.month.toString().padLeft(2, '0');
    final d = value.day.toString().padLeft(2, '0');
    return '$y-$m-$d';
  }

  String _tr(String key, String fallback) {
    return widget.language[key] ?? fallback;
  }

  void _showMessage(String message, {bool isError = false}) {
    final normalized = message
        .replaceAll('ApiException(statusCode: null, message: ', '')
        .replaceAll(')', '');

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(normalized),
        backgroundColor: isError ? const Color(0xFFD34B5F) : null,
      ),
    );
  }

  Color _statusColor(String status) {
    switch (status.trim().toLowerCase()) {
      case 'approved':
        return const Color(0xFF2E7D32);
      case 'rejected':
      case 'cancelled':
        return const Color(0xFFC62828);
      default:
        return const Color(0xFF0277BD);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    return RefreshIndicator(
      onRefresh: _loadAll,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _tr('leave_remaining', 'ច្បាប់នៅសល់'),
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${_summary.totalRemaining}',
                    style: Theme.of(context).textTheme.headlineMedium,
                  ),
                  const SizedBox(height: 12),
                  for (final item in _summary.types)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Text(
                        '${item.displayName(widget.language)}: ${item.remaining}/${item.entitlement}',
                      ),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _tr('request_for_leave', 'ស្នើសុំច្បាប់'),
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    initialValue: _selectedTypeId,
                    items: _types
                        .map(
                          (item) => DropdownMenuItem<int>(
                            value: item.id,
                            child: Text(item.displayName(widget.language)),
                          ),
                        )
                        .toList(),
                    decoration: InputDecoration(
                      labelText: _tr('leave_type', 'ប្រភេទច្បាប់'),
                      border: const OutlineInputBorder(),
                    ),
                    onChanged: _submitting
                        ? null
                        : (value) {
                            setState(() {
                              _selectedTypeId = value;
                            });
                          },
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _submitting ? null : _pickStartDate,
                          icon: const Icon(Icons.calendar_today_outlined),
                          label: Text(
                            '${_tr('from_date', 'From')}: ${_formatDate(_startDate)}',
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _submitting ? null : _pickEndDate,
                          icon: const Icon(Icons.event_outlined),
                          label: Text(
                            '${_tr('to_date', 'To')}: ${_formatDate(_endDate)}',
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _reasonController,
                    minLines: 2,
                    maxLines: 4,
                    decoration: InputDecoration(
                      labelText: _tr('leave_reason', 'ហេតុផល'),
                      border: const OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: _submitting ? null : _submitRequest,
                      icon: const Icon(Icons.send_outlined),
                      label: Text(_tr('submit', 'ដាក់ស្នើ')),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            _tr('leave_his_status', 'ប្រវត្តិសំណើ'),
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          if (_requests.isEmpty)
            Card(
              child: ListTile(
                title: Text(_tr('no_data_found', 'មិនមានទិន្នន័យ')),
              ),
            )
          else
            for (final request in _requests)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              request.leaveTypeKm.trim().isNotEmpty
                                  ? request.leaveTypeKm
                                  : request.leaveType,
                              style: const TextStyle(fontWeight: FontWeight.w700),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: _statusColor(
                                request.status,
                              ).withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              request.displayStatus(widget.language),
                              style: TextStyle(
                                color: _statusColor(request.status),
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text('${request.startDate} -> ${request.endDate}'),
                      Text('${_tr('day_leave', 'ចំនួនថ្ងៃ')}: ${request.requestedDays}'),
                      if (request.reason.trim().isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(request.reason),
                      ],
                      if (request.canCancel) ...[
                        const SizedBox(height: 8),
                        Align(
                          alignment: Alignment.centerRight,
                          child: TextButton.icon(
                            onPressed: _submitting
                                ? null
                                : () {
                                    _cancelRequest(request);
                                  },
                            icon: const Icon(Icons.cancel_outlined),
                            label: Text(_tr('cancel', 'បោះបង់')),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
        ],
      ),
    );
  }
}
