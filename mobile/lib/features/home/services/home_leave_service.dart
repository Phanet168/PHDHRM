import '../../../core/network/api_exception.dart';
import '../../../core/network/api_service.dart';
import '../../auth/models/auth_user.dart';
import '../models/leave_request_models.dart';

class HomeLeaveService {
  HomeLeaveService({ApiService? apiService})
    : _apiService = apiService ?? ApiService();

  final ApiService _apiService;

  Future<List<LeaveTypeOption>> fetchTypes(AuthUser user) async {
    _ensureSession(user);

    final response = _responseMap(await _apiService.get('/v1/leave-types'));
    final status = (response['status'] ?? '').toString().toLowerCase();
    if (status != 'ok') {
      return <LeaveTypeOption>[];
    }

    final payload = response['data'];
    if (payload is! List) {
      return <LeaveTypeOption>[];
    }

    final rows = <LeaveTypeOption>[];
    for (final item in payload) {
      if (item is Map<String, dynamic>) {
        rows.add(LeaveTypeOption.fromMap(item));
      } else if (item is Map) {
        rows.add(
          LeaveTypeOption.fromMap(
            item.map((key, value) => MapEntry(key.toString(), value)),
          ),
        );
      }
    }

    return rows;
  }

  Future<LeaveSummary> fetchSummary(AuthUser user) async {
    _ensureSession(user);

    final response = _responseMap(await _apiService.get('/v1/leave-requests/summary'));
    final status = (response['status'] ?? '').toString().toLowerCase();
    if (status != 'ok') {
      return const LeaveSummary(totalRemaining: 0, types: <LeaveBalanceItem>[]);
    }

    final payload = response['data'];
    if (payload is! Map<String, dynamic>) {
      return const LeaveSummary(totalRemaining: 0, types: <LeaveBalanceItem>[]);
    }

    return LeaveSummary.fromMap(payload);
  }

  Future<List<LeaveRequestItem>> fetchRequests(AuthUser user) async {
    _ensureSession(user);

    final response = _responseMap(await _apiService.get('/v1/leave-requests'));
    final status = (response['status'] ?? '').toString().toLowerCase();
    if (status != 'ok') {
      return <LeaveRequestItem>[];
    }

    final payload = response['data'];
    List<dynamic> rows = <dynamic>[];

    if (payload is List<dynamic>) {
      rows = payload;
    } else if (payload is Map<String, dynamic> && payload['data'] is List) {
      rows = (payload['data'] as List).cast<dynamic>();
    }

    final requests = <LeaveRequestItem>[];
    for (final row in rows) {
      if (row is Map<String, dynamic>) {
        requests.add(LeaveRequestItem.fromMap(row));
      } else if (row is Map) {
        requests.add(
          LeaveRequestItem.fromMap(
            row.map((key, value) => MapEntry(key.toString(), value)),
          ),
        );
      }
    }

    return requests;
  }

  Future<void> submitRequest({
    required AuthUser user,
    required int leaveTypeId,
    required DateTime startDate,
    required DateTime endDate,
    required String reason,
  }) async {
    _ensureSession(user);

    await _apiService.post(
      '/v1/leave-requests',
      body: <String, dynamic>{
        'leave_type_id': leaveTypeId,
        'start_date': _formatDateOnly(startDate),
        'end_date': _formatDateOnly(endDate),
        'reason': reason.trim(),
      },
    );
  }

  Future<void> cancelRequest({required AuthUser user, required int requestId}) async {
    _ensureSession(user);
    await _apiService.post('/v1/leave-requests/$requestId/cancel');
  }

  void _ensureSession(AuthUser user) {
    if (user.userId <= 0) {
      throw ApiException(message: 'Invalid user session');
    }
  }

  Map<String, dynamic> _responseMap(Map<String, dynamic> raw) {
    final response = raw['response'];
    if (response is Map<String, dynamic>) {
      return response;
    }

    throw ApiException(message: 'Invalid leave API response format');
  }

  String _formatDateOnly(DateTime date) {
    final y = date.year.toString().padLeft(4, '0');
    final m = date.month.toString().padLeft(2, '0');
    final d = date.day.toString().padLeft(2, '0');
    return '$y-$m-$d';
  }
}
