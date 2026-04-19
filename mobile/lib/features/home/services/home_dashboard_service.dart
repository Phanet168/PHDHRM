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

  Future<DashboardSummary> fetchSummary(
    AuthUser user, {
    bool forceRefresh = false,
  }) async {
    final employeeId = user.employeeId;
    if (employeeId <= 0) {
      throw ApiException(message: 'Employee ID មិនត្រឹមត្រូវ');
    }

    if (!forceRefresh && _cachedSummary != null && _cachedAt != null) {
      final age = DateTime.now().difference(_cachedAt!);
      if (age <= _cacheLifetime) {
        return _cachedSummary!;
      }
    }

    final results = await Future.wait<Map<String, dynamic>>([
      _apiService.get(
        '/current_month_totalhours',
        queryParameters: <String, dynamic>{'employee_id': employeeId},
        requiresAuth: false,
      ),
      _apiService.get(
        '/leave_remaining',
        queryParameters: <String, dynamic>{'employee_id': employeeId},
        requiresAuth: false,
      ),
      _apiService.get(
        '/loan_amount',
        queryParameters: <String, dynamic>{'employee_id': employeeId},
        requiresAuth: false,
      ),
      _apiService.get(
        '/salary_info',
        queryParameters: <String, dynamic>{
          'employee_id': employeeId,
          'start': 0,
        },
        requiresAuth: false,
      ),
      _apiService.get(
        '/noticeinfo',
        queryParameters: <String, dynamic>{'start': 0},
        requiresAuth: false,
      ),
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
  }

  Map<String, dynamic> _asResponse(
    Map<String, dynamic> raw, {
    bool allowErrorStatus = false,
  }) {
    final response = raw['response'];
    if (response is! Map<String, dynamic>) {
      throw ApiException(message: 'Response format មិនត្រឹមត្រូវ');
    }

    final status = (response['status'] ?? '').toString().toLowerCase();
    if (status != 'ok') {
      if (allowErrorStatus) {
        return <String, dynamic>{};
      }

      final message =
          (response['message'] ?? 'មិនអាចទាញទិន្នន័យបាន').toString();
      throw ApiException(message: message);
    }

    return response;
  }
}
