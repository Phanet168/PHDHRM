import '../../../core/network/api_exception.dart';
import '../../../core/network/api_service.dart';
import '../../auth/models/auth_user.dart';
import '../models/dashboard_summary.dart';

class HomeDashboardService {
  HomeDashboardService({ApiService? apiService})
    : _apiService = apiService ?? ApiService();

  final ApiService _apiService;
  static const Duration _cacheLifetime = Duration(seconds: 45);

  DashboardSummary? _cachedSummary;
  DateTime? _cachedAt;
  Future<DashboardSummary>? _inFlightSummary;

  Future<DashboardSummary> fetchSummary(
    AuthUser user, {
    bool forceRefresh = false,
  }) async {
    final employeeId = user.employeeId;
    if (employeeId <= 0) {
      return _emptySummary();
    }

    if (!forceRefresh && _cachedSummary != null && _cachedAt != null) {
      final age = DateTime.now().difference(_cachedAt!);
      if (age <= _cacheLifetime) {
        return _cachedSummary!;
      }
    }

    if (!forceRefresh && _inFlightSummary != null) {
      return _inFlightSummary!;
    }

    final future = _fetchSummaryInternal(employeeId);
    _inFlightSummary = future;
    try {
      return await future;
    } finally {
      if (identical(_inFlightSummary, future)) {
        _inFlightSummary = null;
      }
    }
  }

  Future<DashboardSummary> _fetchSummaryInternal(int employeeId) async {
    try {
      final results = await Future.wait<Map<String, dynamic>>([
        _safeGet(
          '/current_month_totalhours',
          queryParameters: <String, dynamic>{'employee_id': employeeId},
        ),
        _safeGet(
          '/leave_remaining',
          queryParameters: <String, dynamic>{'employee_id': employeeId},
        ),
        _safeGet(
          '/loan_amount',
          queryParameters: <String, dynamic>{'employee_id': employeeId},
        ),
        _safeGet(
          '/salary_info',
          queryParameters: <String, dynamic>{
            'employee_id': employeeId,
            'start': 0,
          },
        ),
        _safeGet('/noticeinfo', queryParameters: <String, dynamic>{'start': 0}),
      ]);

      final hours = _asResponse(results[0], allowErrorStatus: true);
      final leave = _asResponse(results[1], allowErrorStatus: true);
      final loan = _asResponse(results[2], allowErrorStatus: true);
      final salary = _asResponse(results[3], allowErrorStatus: true);
      final notice = _asResponse(results[4], allowErrorStatus: true);

      final salaryList =
          salary['salary_info'] as List<dynamic>? ?? const <dynamic>[];
      final noticeList =
          notice['historydata'] as List<dynamic>? ?? const <dynamic>[];

      final summary = DashboardSummary(
        totalHours: (hours['totalhours'] ?? '0').toString(),
        remainingLeave: (leave['total'] ?? '0').toString(),
        loanAmount: (loan['totalamount'] ?? '0').toString(),
        salaryCount: salaryList.length,
        noticeCount: (notice['length'] as num?)?.toInt() ?? noticeList.length,
        notices:
            noticeList.take(5).map((item) {
              if (item is Map<String, dynamic>) {
                final title =
                    (item['title'] ?? item['notice_title'] ?? '')
                        .toString()
                        .trim();
                if (title.isNotEmpty) {
                  return title;
                }
              }

              return 'Notice item';
            }).toList(),
      );

      _cachedSummary = summary;
      _cachedAt = DateTime.now();

      return summary;
    } catch (_) {
      // For heavy traffic scenarios, return last known-good summary when possible.
      if (_cachedSummary != null) {
        return _cachedSummary!;
      }

      rethrow;
    }
  }

  Future<Map<String, dynamic>> _safeGet(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) {
    return _apiService.get(
      path,
      queryParameters: queryParameters,
      requiresAuth: false,
      throwOnError: false,
    );
  }

  DashboardSummary _emptySummary() {
    return DashboardSummary(
      totalHours: '0',
      remainingLeave: '0',
      loanAmount: '0',
      salaryCount: 0,
      noticeCount: 0,
      notices: const <String>[],
    );
  }

  Map<String, dynamic> _asResponse(
    Map<String, dynamic> raw, {
    bool allowErrorStatus = false,
  }) {
    final response = raw['response'];
    if (response is! Map<String, dynamic>) {
      if (allowErrorStatus) {
        return <String, dynamic>{};
      }
      throw ApiException(message: 'Response format invalid');
    }

    final status = (response['status'] ?? '').toString().toLowerCase();
    if (status != 'ok') {
      if (allowErrorStatus) {
        return <String, dynamic>{};
      }

      final message = (response['message'] ?? 'Unable to load data').toString();
      throw ApiException(message: message);
    }

    return response;
  }
}
