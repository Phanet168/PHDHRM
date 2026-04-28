import '../../../core/network/api_exception.dart';
import '../../../core/network/api_service.dart';
import '../models/home_notification_item.dart';

class HomeNotificationService {
  HomeNotificationService({ApiService? apiService})
    : _apiService = apiService ?? ApiService();

  final ApiService _apiService;

  Future<HomeNotificationPageData> fetchNotifications({
    int page = 1,
    int perPage = 20,
  }) async {
    final raw = await _apiService.get(
      '/v1/notifications',
      queryParameters: <String, dynamic>{'page': page, 'per_page': perPage},
    );

    final response = _asResponse(raw);
    final data = response['data'];
    final payload = data is Map<String, dynamic> ? data : <String, dynamic>{};

    final rows = payload['data'] as List<dynamic>? ?? const <dynamic>[];
    final items = rows.whereType<Map<String, dynamic>>().map(_mapItem).toList();
    final unreadCount =
        (payload['unread_count'] as num?)?.toInt() ??
        items.where((item) => item.isUnread).length;

    return HomeNotificationPageData(items: items, unreadCount: unreadCount);
  }

  Future<void> markAsRead(int deliveryId) async {
    final raw = await _apiService.post('/v1/notifications/$deliveryId/read');
    _asResponse(raw);
  }

  Future<void> markAllAsRead() async {
    final raw = await _apiService.post('/v1/notifications/read-all');
    _asResponse(raw);
  }

  Map<String, dynamic> _asResponse(Map<String, dynamic> raw) {
    final response = raw['response'];
    if (response is! Map<String, dynamic>) {
      throw ApiException(message: 'Invalid notifications response format');
    }

    final status = (response['status'] ?? '').toString().toLowerCase();
    if (status != 'ok') {
      final message =
          (response['message'] ?? 'Unable to load notifications').toString();
      throw ApiException(message: message);
    }

    return response;
  }

  HomeNotificationItem _mapItem(Map<String, dynamic> row) {
    final title =
        (row['title'] ?? row['notice_type'] ?? 'Notification')
            .toString()
            .trim();
    final description =
        (row['description'] ?? row['body'] ?? '').toString().trim();
    final sentAtRaw = (row['sent_at'] ?? '').toString().trim();
    final noticeDateRaw = (row['notice_date'] ?? '').toString().trim();
    final noticeBy = (row['notice_by'] ?? '').toString().trim();

    return HomeNotificationItem(
      id: (row['id'] as num?)?.toInt() ?? 0,
      title: title.isEmpty ? 'Notification' : title,
      description: description.isEmpty ? '-' : description,
      meta: _buildMeta(noticeDateRaw, sentAtRaw, noticeBy),
      isUnread: row['is_unread'] == true || row['read_at'] == null,
      sentAt: sentAtRaw.isEmpty ? null : sentAtRaw,
      readAt: (row['read_at'] as String?)?.trim(),
    );
  }

  String _buildMeta(String noticeDateRaw, String sentAtRaw, String noticeBy) {
    final dateLabel = _formatDate(
      noticeDateRaw.isNotEmpty ? noticeDateRaw : sentAtRaw,
    );

    if (noticeBy.isNotEmpty) {
      return '$dateLabel - $noticeBy';
    }

    return dateLabel;
  }

  String _formatDate(String rawDate) {
    if (rawDate.trim().isEmpty) {
      return '-';
    }

    try {
      final date = DateTime.parse(rawDate).toLocal();
      final day = date.day.toString().padLeft(2, '0');
      final month = date.month.toString().padLeft(2, '0');
      final year = date.year.toString();
      final hour = date.hour.toString().padLeft(2, '0');
      final minute = date.minute.toString().padLeft(2, '0');

      return '$day/$month/$year $hour:$minute';
    } catch (_) {
      return rawDate;
    }
  }
}

