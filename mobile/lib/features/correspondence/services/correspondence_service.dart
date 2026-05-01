import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;

import '../../../core/config/api_config.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/api_service.dart';
import '../../../core/storage/token_storage_service.dart';
import '../models/correspondence_models.dart';

class CorrespondenceService {
  CorrespondenceService({ApiService? apiService})
    : _apiService = apiService ?? ApiService();

  static const Duration _listCacheTtl = Duration(seconds: 45);
  static final Map<String, _CacheEntry<CorrespondenceListResponse>>
  _incomingCache = <String, _CacheEntry<CorrespondenceListResponse>>{};
  static final Map<String, _CacheEntry<CorrespondenceListResponse>>
  _outgoingCache = <String, _CacheEntry<CorrespondenceListResponse>>{};
  static final Map<String, _CacheEntry<Map<String, dynamic>>> _dashboardCache =
      <String, _CacheEntry<Map<String, dynamic>>>{};

  final ApiService _apiService;
  final TokenStorageService _tokenStorageService = TokenStorageService();

  /// ទាក់ទងលិខិតចូល
  Future<CorrespondenceListResponse> fetchIncomingLetters({
    int page = 1,
    int perPage = 20,
    String period = 'all',
    bool forceRefresh = false,
    String? status,
    String? sortBy,
    DateTime? startDate,
    DateTime? endDate,
  }) async {
    final queryParams = <String, dynamic>{'page': page, 'per_page': perPage};
    _applyPeriodFilter(
      queryParams,
      period: period,
      startDate: startDate,
      endDate: endDate,
    );
    if (status != null && status.isNotEmpty) {
      queryParams['status'] = status;
    }
    if (sortBy != null && sortBy.isNotEmpty) {
      queryParams['sort_by'] = sortBy;
    }
    if (startDate != null) {
      queryParams['start_date'] = _formatDateOnly(startDate);
    }
    if (endDate != null) {
      queryParams['end_date'] = _formatDateOnly(endDate);
    }

    final cacheKey = _buildCacheKey('incoming', queryParams);
    if (!forceRefresh) {
      final cached = _readCache(_incomingCache, cacheKey);
      if (cached != null) {
        return cached;
      }
    }

    final raw = await _apiService.get(
      '/v1/correspondence/incoming',
      queryParameters: queryParams,
    );
    final response = CorrespondenceListResponse.fromJson(raw);
    _writeCache(_incomingCache, cacheKey, response);
    return response;
  }

  /// ទាក់ទងលិខិតចេញ
  Future<CorrespondenceListResponse> fetchOutgoingLetters({
    int page = 1,
    int perPage = 20,
    String period = 'all',
    bool forceRefresh = false,
    String? status,
    String? sortBy,
    DateTime? startDate,
    DateTime? endDate,
  }) async {
    final queryParams = <String, dynamic>{'page': page, 'per_page': perPage};
    _applyPeriodFilter(
      queryParams,
      period: period,
      startDate: startDate,
      endDate: endDate,
    );
    if (status != null && status.isNotEmpty) {
      queryParams['status'] = status;
    }
    if (sortBy != null && sortBy.isNotEmpty) {
      queryParams['sort_by'] = sortBy;
    }
    if (startDate != null) {
      queryParams['start_date'] = _formatDateOnly(startDate);
    }
    if (endDate != null) {
      queryParams['end_date'] = _formatDateOnly(endDate);
    }

    final cacheKey = _buildCacheKey('outgoing', queryParams);
    if (!forceRefresh) {
      final cached = _readCache(_outgoingCache, cacheKey);
      if (cached != null) {
        return cached;
      }
    }

    final raw = await _apiService.get(
      '/v1/correspondence/outgoing',
      queryParameters: queryParams,
    );
    final response = CorrespondenceListResponse.fromJson(raw);
    _writeCache(_outgoingCache, cacheKey, response);
    return response;
  }

  /// ទាក់ទងលម្អិតលិខិត
  Future<CorrespondenceLetter> fetchLetterDetail(int letterId) async {
    final raw = await _apiService.get('/v1/correspondence/$letterId');
    final response = _asResponse(raw);
    final data = response['data'];
    final payload = data is Map<String, dynamic> ? data : <String, dynamic>{};
    return CorrespondenceLetter.fromJson(payload);
  }

  /// បង្កើតលិខិតថ្មី
  Future<CorrespondenceLetter> createLetter(
    CorrespondenceCreateRequest request, {
    List<CorrespondenceAttachmentInput> attachments = const [],
  }) async {
    if (attachments.isNotEmpty) {
      return _createLetterMultipart(request, attachments);
    }

    final raw = await _apiService.post(
      '/v1/correspondence/store',
      body: request.toJson(),
    );
    final response = _asResponse(raw);
    final data = response['data'];
    final payload = data is Map<String, dynamic> ? data : <String, dynamic>{};
    _clearListCaches();
    return CorrespondenceLetter.fromJson(payload);
  }

  Future<List<CorrespondenceLookupOption>> fetchOrgUnits() async {
    final raw = await _apiService.get('/v1/correspondence/org-units');
    final response = _asResponse(raw);
    final data = response['data'];
    if (data is! List<dynamic>) {
      return const <CorrespondenceLookupOption>[];
    }

    return data
        .whereType<Map<String, dynamic>>()
        .map(CorrespondenceLookupOption.fromJson)
        .toList();
  }

  /// ឲ្យលិខិតឋិតិវន្ត
  /// សម្រាប់លិខិតចូល: delegate -> office_comment -> deputy_review -> director_decision
  /// សម្រាប់លិខិតចេញ: ផ្ទាល់ dispatch
  Future<CorrespondenceLetter> progressWorkflow({
    required int letterId,
    required String
    action, // 'delegate', 'office_comment', 'deputy_review', etc.
    String? decision, // 'approved' or 'rejected' for director_decision
    String? note,
    int? assignedDepartmentId,
    List<int>? assignedDepartmentIds,
    int? officeUserId,
    List<int>? officeRelatedUserIds,
    int? deputyUserId,
    int? directorUserId,
  }) async {
    final payload = <String, dynamic>{'action': action};
    if (decision != null) {
      payload['decision'] = decision;
    }
    if (note != null && note.trim().isNotEmpty) {
      payload['note'] = note.trim();
    }
    if (assignedDepartmentId != null) {
      payload['assigned_department_id'] = assignedDepartmentId;
    }
    if (assignedDepartmentIds != null && assignedDepartmentIds.isNotEmpty) {
      payload['assigned_department_ids'] = assignedDepartmentIds;
    }
    if (officeUserId != null) {
      payload['office_comment_user_id'] = officeUserId;
    }
    if (officeRelatedUserIds != null && officeRelatedUserIds.isNotEmpty) {
      payload['office_comment_related_user_ids'] = officeRelatedUserIds;
    }
    if (deputyUserId != null) {
      payload['deputy_review_user_id'] = deputyUserId;
    }
    if (directorUserId != null) {
      payload['director_user_id'] = directorUserId;
    }

    final raw = await _apiService.post(
      '/v1/correspondence/$letterId/progress',
      body: payload,
    );
    final response = _asResponse(raw);
    final data = response['data'];
    final dataPayload =
        data is Map<String, dynamic> ? data : <String, dynamic>{};
    _clearListCaches();
    return CorrespondenceLetter.fromJson(dataPayload);
  }

  /// ចែកចាយលិខិតទៅនាយកដ្ឋាន/អ្នក
  /// សម្រាប់លិខិតចូលបន្ទាប់ពីអនុម័ត
  /// សម្រាប់លិខិតចេញ
  Future<CorrespondenceLetter> distributeLetters({
    required int letterId,
    List<int>? targetDepartmentIds,
    List<int>? toDepartmentIds,
    List<int>? ccDepartmentIds,
    int? targetUserId,
    List<int>? toUserIds,
    List<int>? ccUserIds,
    String? note,
  }) async {
    final payload = <String, dynamic>{};
    if (targetDepartmentIds != null && targetDepartmentIds.isNotEmpty) {
      payload['target_department_ids'] = targetDepartmentIds;
    }
    if (toDepartmentIds != null && toDepartmentIds.isNotEmpty) {
      payload['to_department_ids'] = toDepartmentIds;
    }
    if (ccDepartmentIds != null && ccDepartmentIds.isNotEmpty) {
      payload['cc_department_ids'] = ccDepartmentIds;
    }
    if (targetUserId != null && targetUserId > 0) {
      payload['target_user_id'] = targetUserId;
    }
    if (toUserIds != null && toUserIds.isNotEmpty) {
      payload['to_user_ids'] = toUserIds;
    }
    if (ccUserIds != null && ccUserIds.isNotEmpty) {
      payload['cc_user_ids'] = ccUserIds;
    }
    if (note != null && note.trim().isNotEmpty) {
      payload['note'] = note.trim();
    }

    final raw = await _apiService.post(
      '/v1/correspondence/$letterId/distribute',
      body: payload,
    );
    final response = _asResponse(raw);
    final data = response['data'];
    final dataPayload =
        data is Map<String, dynamic> ? data : <String, dynamic>{};
    _clearListCaches();
    return CorrespondenceLetter.fromJson(dataPayload);
  }

  /// ទទួលស្គាល់លិខិត (សម្រាប់អ្នកទទួល)
  Future<CorrespondenceLetterDistribution> acknowledgeDistribution(
    int distributionId,
  ) async {
    final raw = await _apiService.post(
      '/v1/correspondence/distribution/$distributionId/acknowledge',
    );
    final response = _asResponse(raw);
    final data = response['data'];
    final payload = data is Map<String, dynamic> ? data : <String, dynamic>{};
    _clearListCaches();
    return CorrespondenceLetterDistribution.fromJson(payload);
  }

  /// ផ្ញើមតិយោបល់ (សម្រាប់អ្នកទទួល)
  Future<CorrespondenceLetterDistribution> sendFeedback({
    required int distributionId,
    required String feedbackNote,
  }) async {
    final raw = await _apiService.post(
      '/v1/correspondence/distribution/$distributionId/feedback',
      body: {'feedback_note': feedbackNote.trim()},
    );
    final response = _asResponse(raw);
    final data = response['data'];
    final payload = data is Map<String, dynamic> ? data : <String, dynamic>{};
    _clearListCaches();
    return CorrespondenceLetterDistribution.fromJson(payload);
  }

  Future<String> fetchAttachmentSignedUrl({
    required int letterId,
    required int attachmentIndex,
    bool download = false,
  }) async {
    final raw = await _apiService.get(
      '/v1/correspondence/$letterId/attachments/$attachmentIndex/signed-url',
      queryParameters: download ? <String, dynamic>{'download': 1} : null,
    );
    final response = _asResponse(raw);
    final data = response['data'];
    if (data is Map<String, dynamic>) {
      final url = (data['url'] ?? '').toString().trim();
      if (url.isNotEmpty) {
        return url;
      }
    }

    throw ApiException(message: 'Attachment URL is unavailable.');
  }

  /// ផ្ញើមតិយោបល់ដល់ឪក្នុង (សម្រាប់លិខិតកូន)
  Future<void> sendFeedbackToParent({
    required int letterId,
    required String feedbackNote,
  }) async {
    final raw = await _apiService.post(
      '/v1/correspondence/$letterId/feedback-parent',
      body: {'feedback_note': feedbackNote.trim()},
    );
    _asResponse(raw);
    _clearListCaches();
  }

  /// ស្វាគមន៍អ្នក
  Future<List<Map<String, dynamic>>> searchUsers(String query) async {
    final raw = await _apiService.get(
      '/v1/correspondence/users/search',
      queryParameters: {'q': query.trim()},
    );
    final response = _asResponse(raw);
    final data = response['data'];
    if (data is List<dynamic>) {
      return data.whereType<Map<String, dynamic>>().toList();
    }
    return [];
  }

  Future<List<CorrespondenceLookupOption>> searchUserOptions(
    String query,
  ) async {
    final rows = await searchUsers(query);
    return rows.map(CorrespondenceLookupOption.fromJson).toList();
  }

  /// បិទលិខិត
  Future<CorrespondenceLetter> closeLetter(int letterId) async {
    final raw = await _apiService.post(
      '/v1/correspondence/$letterId/progress',
      body: {'action': 'close'},
    );
    final response = _asResponse(raw);
    final data = response['data'];
    final payload = data is Map<String, dynamic> ? data : <String, dynamic>{};
    _clearListCaches();
    return CorrespondenceLetter.fromJson(payload);
  }

  /// ដាក់ឆ្នូត PDF (TODO: implement PDF printing)
  // Note: ApiService does not have a getDocument method yet
  // This can be implemented when file download capability is added
  // Future<String> printLetterPdf(int letterId) async {
  //   final response = await _apiService.get(
  //     '/correspondence/$letterId/print',
  //   );
  //   return response['download_url'] ?? '';
  // }

  /// ទាក់ទងកំរិត (សម្រាប់រង្វាន់ឧក្រិដ្ឋ)
  Future<Map<String, dynamic>> fetchDashboard({
    String period = 'all',
    bool forceRefresh = false,
    DateTime? startDate,
    DateTime? endDate,
  }) async {
    final queryParams = <String, dynamic>{};
    _applyPeriodFilter(
      queryParams,
      period: period,
      startDate: startDate,
      endDate: endDate,
    );
    if (startDate != null) {
      queryParams['start_date'] = _formatDateOnly(startDate);
    }
    if (endDate != null) {
      queryParams['end_date'] = _formatDateOnly(endDate);
    }

    final cacheKey = _buildCacheKey('dashboard', queryParams);
    if (!forceRefresh) {
      final cached = _readCache(_dashboardCache, cacheKey);
      if (cached != null) {
        return Map<String, dynamic>.from(cached);
      }
    }

    final raw = await _apiService.get(
      '/v1/correspondence',
      queryParameters: queryParams.isEmpty ? null : queryParams,
    );
    final response = _asResponse(raw);
    final data = response['data'];
    final result = data is Map<String, dynamic> ? data : <String, dynamic>{};
    _writeCache(_dashboardCache, cacheKey, Map<String, dynamic>.from(result));
    return result;
  }

  T? _readCache<T>(Map<String, _CacheEntry<T>> cache, String key) {
    final entry = cache[key];
    if (entry == null) {
      return null;
    }

    if (DateTime.now().difference(entry.createdAt) > _listCacheTtl) {
      cache.remove(key);
      return null;
    }

    return entry.data;
  }

  void _writeCache<T>(Map<String, _CacheEntry<T>> cache, String key, T data) {
    cache[key] = _CacheEntry<T>(data: data, createdAt: DateTime.now());
  }

  String _buildCacheKey(String scope, Map<String, dynamic> queryParams) {
    final entries =
        queryParams.entries.toList()..sort((a, b) => a.key.compareTo(b.key));
    final query = entries.map((e) => '${e.key}=${e.value}').join('&');
    return '$scope|$query';
  }

  void _clearListCaches() {
    _incomingCache.clear();
    _outgoingCache.clear();
    _dashboardCache.clear();
  }

  void _applyPeriodFilter(
    Map<String, dynamic> queryParams, {
    required String period,
    DateTime? startDate,
    DateTime? endDate,
  }) {
    const allowedPeriods = <String>{
      'today',
      'yesterday',
      'this_week',
      'this_month',
      'all',
      'custom',
    };

    var normalized = period.trim().toLowerCase();
    if (!allowedPeriods.contains(normalized)) {
      normalized = (startDate != null || endDate != null) ? 'custom' : 'all';
    }

    if (normalized == 'custom' && startDate == null && endDate == null) {
      normalized = 'all';
    }

    queryParams['period'] = normalized;
  }

  String _formatDateOnly(DateTime date) {
    final year = date.year.toString().padLeft(4, '0');
    final month = date.month.toString().padLeft(2, '0');
    final day = date.day.toString().padLeft(2, '0');
    return '$year-$month-$day';
  }

  Map<String, dynamic> _asResponse(Map<String, dynamic> raw) {
    // Preferred backend shape:
    // { "response": { "status": "ok", "message": "...", "data": ... } }
    // Fallback shapes are tolerated to avoid false format errors.
    final nested = raw['response'];
    final response = nested is Map<String, dynamic> ? nested : raw;

    final status = (response['status'] ?? '').toString().toLowerCase();
    if (status == 'ok') {
      return response;
    }

    // Some APIs may return payload without `status` but still include usable data.
    if (response.containsKey('data') && status.isEmpty) {
      return response;
    }

    final message = _extractReadableMessage(response);
    throw ApiException(
      message:
          message ??
          'Invalid correspondence response format. Expected {response:{status,data}}.',
    );
  }

  String? _extractReadableMessage(Map<String, dynamic> payload) {
    final directMessage = payload['message']?.toString().trim();
    if (directMessage != null && directMessage.isNotEmpty) {
      return directMessage;
    }

    final directError = payload['error']?.toString().trim();
    if (directError != null && directError.isNotEmpty) {
      return directError;
    }

    final errors = payload['errors'];
    if (errors is Map) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty) {
          final first = value.first?.toString().trim();
          if (first != null && first.isNotEmpty) {
            return first;
          }
        }
        final single = value?.toString().trim();
        if (single != null && single.isNotEmpty) {
          return single;
        }
      }
    }

    return null;
  }

  Future<CorrespondenceLetter> _createLetterMultipart(
    CorrespondenceCreateRequest request,
    List<CorrespondenceAttachmentInput> attachments,
  ) async {
    final token = await _tokenStorageService.readToken();
    ApiException? lastError;

    for (final base in ApiConfig.baseUrls) {
      final uri = ApiConfig.buildUriForBase(base, '/v1/correspondence/store');
      final multipart = http.MultipartRequest('POST', uri)
        ..headers['Accept'] = 'application/json';

      if (token != null && token.isNotEmpty) {
        multipart.headers['Authorization'] = 'Bearer $token';
      }

      _appendMultipartField(multipart, 'letter_type', request.letterType);
      _appendMultipartField(multipart, 'subject', request.subject);
      _appendMultipartField(multipart, 'letter_no', request.letterNo);
      _appendMultipartField(multipart, 'registry_no', request.registryNo);
      _appendMultipartField(multipart, 'priority', request.priority);
      _appendMultipartField(multipart, 'from_org', request.fromOrg);
      _appendMultipartField(multipart, 'to_org', request.toOrg);
      _appendMultipartField(
        multipart,
        'letter_date',
        _formatDate(request.letterDate),
      );
      _appendMultipartField(
        multipart,
        'received_date',
        _formatDate(request.receivedDate),
      );
      _appendMultipartField(
        multipart,
        'sent_date',
        _formatDate(request.sentDate),
      );
      _appendMultipartField(
        multipart,
        'due_date',
        _formatDate(request.dueDate),
      );
      _appendMultipartField(multipart, 'summary', request.summary);
      _appendMultipartField(
        multipart,
        'origin_department_id',
        request.originDepartmentId?.toString(),
      );
      _appendMultipartField(
        multipart,
        'send_action',
        request.sendAction ?? 'draft',
      );
      _appendMultipartList(
        multipart,
        'to_department_ids',
        request.toDepartmentIds,
      );
      _appendMultipartList(
        multipart,
        'cc_department_ids',
        request.ccDepartmentIds,
      );
      _appendMultipartList(multipart, 'to_user_ids', request.toUserIds);
      _appendMultipartList(multipart, 'cc_user_ids', request.ccUserIds);

      for (final attachment in attachments) {
        if (attachment.bytes != null && attachment.bytes!.isNotEmpty) {
          multipart.files.add(
            http.MultipartFile.fromBytes(
              'attachments[]',
              Uint8List.fromList(attachment.bytes!),
              filename: attachment.name,
            ),
          );
        } else if (attachment.path != null &&
            attachment.path!.trim().isNotEmpty) {
          multipart.files.add(
            await http.MultipartFile.fromPath(
              'attachments[]',
              attachment.path!.trim(),
              filename:
                  attachment.name.trim().isEmpty
                      ? null
                      : attachment.name.trim(),
            ),
          );
        }
      }

      try {
        final streamed = await multipart.send().timeout(
          ApiConfig.connectTimeout,
        );
        final response = await http.Response.fromStream(streamed);
        final decoded = _tryDecodeMap(response.body);

        if (response.statusCode >= 200 && response.statusCode < 300) {
          final payload = _asResponse(decoded)['data'];
          _clearListCaches();
          return CorrespondenceLetter.fromJson(
            payload is Map<String, dynamic> ? payload : <String, dynamic>{},
          );
        }

        lastError = ApiException(
          message: _extractMessage(decoded, fallback: 'Request failed'),
          statusCode: response.statusCode,
        );
      } catch (_) {
        lastError = ApiException(
          message: 'Connection timeout. Please try again.',
        );
      }
    }

    throw lastError ?? ApiException(message: 'Unable to create correspondence');
  }

  void _appendMultipartField(
    http.MultipartRequest request,
    String key,
    String? value,
  ) {
    final normalized = value?.trim();
    if (normalized == null || normalized.isEmpty) {
      return;
    }
    request.fields[key] = normalized;
  }

  void _appendMultipartList(
    http.MultipartRequest request,
    String key,
    List<int>? values,
  ) {
    if (values == null || values.isEmpty) {
      return;
    }

    for (var index = 0; index < values.length; index++) {
      request.fields['$key[$index]'] = values[index].toString();
    }
  }

  String? _formatDate(DateTime? value) {
    if (value == null) {
      return null;
    }

    return value.toIso8601String().split('T')[0];
  }

  Map<String, dynamic> _tryDecodeMap(String body) {
    if (body.isEmpty) {
      return <String, dynamic>{};
    }

    final trimmed = body.trimLeft();
    if (!trimmed.startsWith('{') && !trimmed.startsWith('[')) {
      return <String, dynamic>{};
    }

    try {
      final decoded = jsonDecode(trimmed);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
    } catch (_) {
      // Ignore parse errors.
    }

    return <String, dynamic>{};
  }

  String _extractMessage(
    Map<String, dynamic> body, {
    String fallback = 'Request failed',
  }) {
    final response = body['response'];
    if (response is Map<String, dynamic>) {
      final message = response['message']?.toString().trim();
      if (message != null && message.isNotEmpty) {
        return message;
      }
    }

    final message = body['message']?.toString().trim();
    if (message != null && message.isNotEmpty) {
      return message;
    }

    return fallback;
  }
}

class _CacheEntry<T> {
  _CacheEntry({required this.data, required this.createdAt});

  final T data;
  final DateTime createdAt;
}
