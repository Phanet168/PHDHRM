import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../auth/models/auth_user.dart';
import '../models/leave_request_models.dart';
import '../services/home_leave_service.dart';

class LeaveReviewPage extends StatefulWidget {
  const LeaveReviewPage({
    super.key,
    required this.user,
    required this.language,
    required this.leaveService,
  });

  final AuthUser user;
  final Map<String, String> language;
  final HomeLeaveService leaveService;

  @override
  State<LeaveReviewPage> createState() => _LeaveReviewPageState();
}

class _LeaveReviewPageState extends State<LeaveReviewPage> {
  List<LeaveRequestItem> _requests = <LeaveRequestItem>[];
  bool _loading = true;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _loadPendingReviews();
  }

  Future<void> _loadPendingReviews() async {
    setState(() {
      _loading = true;
    });

    try {
      final requests = await widget.leaveService.fetchPendingReviews(widget.user);
      if (!mounted) {
        return;
      }

      setState(() {
        _requests = requests;
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

  Future<void> _submitReview(LeaveRequestItem item, String action) async {
    final noteController = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: Text(
            action == 'approve'
                ? _tr('approve_leave', 'Approve leave')
                : _tr('rejected', 'Reject leave'),
          ),
          content: TextField(
            controller: noteController,
            minLines: 2,
            maxLines: 4,
            decoration: InputDecoration(
              labelText: _tr('note', 'Note'),
              border: const OutlineInputBorder(),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: Text(_tr('cancel', 'Cancel')),
            ),
            ElevatedButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: Text(
                action == 'approve'
                    ? _tr('approve_leave', 'Approve')
                    : _tr('rejected', 'Reject'),
              ),
            ),
          ],
        );
      },
    );

    if (confirmed != true) {
      noteController.dispose();
      return;
    }

    setState(() {
      _submitting = true;
    });

    try {
      await widget.leaveService.reviewRequest(
        user: widget.user,
        requestId: item.id,
        action: action,
        note: noteController.text,
      );
      if (mounted) {
        _showMessage(
          action == 'approve'
              ? _tr('approve_leave', 'Approved successfully')
              : _tr('rejected', 'Rejected successfully'),
        );
      }
      await _loadPendingReviews();
    } catch (error) {
      if (mounted) {
        _showMessage(error.toString(), isError: true);
      }
    } finally {
      noteController.dispose();
      if (mounted) {
        setState(() {
          _submitting = false;
        });
      }
    }
  }

  String _tr(String key, String fallback) {
    return widget.language[key] ?? fallback;
  }

  void _showMessage(String message, {bool isError = false}) {
    final normalized = extractApiErrorMessage(message);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(normalized),
        backgroundColor: isError ? const Color(0xFFD34B5F) : null,
      ),
    );
  }

  Future<void> _openAttachment(String url) async {
    final uri = Uri.tryParse(url.trim());
    if (uri == null) {
      _showMessage(_tr('attachment', 'Invalid attachment link'), isError: true);
      return;
    }

    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched && mounted) {
      _showMessage(_tr('attachment', 'Unable to open attachment'), isError: true);
    }
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
    return Scaffold(
      appBar: AppBar(
        title: Text(_tr('approve_leave', 'Review Leave Requests')),
        actions: [
          IconButton(
            onPressed: _loading ? null : _loadPendingReviews,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadPendingReviews,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_requests.isEmpty)
                    Card(
                      child: ListTile(
                        title: Text(_tr('no_data_found', 'No pending requests')),
                      ),
                    )
                  else
                    for (final item in _requests)
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
                                      item.employeeName?.isNotEmpty == true
                                          ? item.employeeName!
                                          : '-',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 8,
                                      vertical: 4,
                                    ),
                                    decoration: BoxDecoration(
                                      color: _statusColor(item.status)
                                          .withValues(alpha: 0.12),
                                      borderRadius: BorderRadius.circular(999),
                                    ),
                                    child: Text(
                                      item.displayStatus(widget.language),
                                      style: TextStyle(
                                        color: _statusColor(item.status),
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 6),
                              if (item.employeeNo?.isNotEmpty == true)
                                Text('ID: ${item.employeeNo}'),
                              Text(
                                item.leaveTypeKm.trim().isNotEmpty
                                    ? item.leaveTypeKm
                                    : item.leaveType,
                              ),
                              Text('${item.startDate} -> ${item.endDate}'),
                              Text('${_tr('day_leave', 'Days')}: ${item.requestedDays}'),
                              if (item.reason.trim().isNotEmpty) ...[
                                const SizedBox(height: 6),
                                Text(item.reason),
                              ],
                              if (item.attachmentUrl?.trim().isNotEmpty == true) ...[
                                const SizedBox(height: 6),
                                Align(
                                  alignment: Alignment.centerLeft,
                                  child: TextButton.icon(
                                    onPressed: _submitting
                                        ? null
                                        : () => _openAttachment(item.attachmentUrl!),
                                    icon: const Icon(Icons.attach_file_outlined),
                                    label: Text(_tr('attachment', 'Open attachment')),
                                  ),
                                ),
                              ],
                              const SizedBox(height: 8),
                              Row(
                                children: [
                                  Expanded(
                                    child: OutlinedButton.icon(
                                      onPressed: _submitting
                                          ? null
                                          : () => _submitReview(item, 'reject'),
                                      icon: const Icon(Icons.close),
                                      label: Text(_tr('rejected', 'Reject')),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: ElevatedButton.icon(
                                      onPressed: _submitting
                                          ? null
                                          : () => _submitReview(item, 'approve'),
                                      icon: const Icon(Icons.check),
                                      label: Text(_tr('approve_leave', 'Approve')),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                ],
              ),
            ),
    );
  }
}
