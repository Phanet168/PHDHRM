import '../../../core/network/api_exception.dart';
import '../../../core/network/api_service.dart';
import '../../auth/models/auth_user.dart';
import '../models/mission_summary.dart';
import '../models/mission_detail.dart';
import '../models/mission_request.dart';

/// The printable mission letters the server can link to.
enum MissionLetter {
  order('order'),
  travelVisa('travel-visa'),
  report('report');

  const MissionLetter(this.path);
  final String path;
}

class HomeMissionService {
  HomeMissionService({ApiService? apiService})
    : _apiService = apiService ?? ApiService();

  final ApiService _apiService;

  Map<String, dynamic> _data(Map<String, dynamic> raw) {
    final response = raw['response'];
    if (response is! Map ||
        response['status'] != 'ok' ||
        response['data'] is! Map) {
      throw ApiException(message: 'Invalid mission response format');
    }
    return Map<String, dynamic>.from(response['data']);
  }

  Future<MissionRequestOptions> fetchRequestOptions() async =>
      MissionRequestOptions.fromMap(
        _data(await _apiService.get('/v1/missions/request-options')),
      );

  /// Colleagues that may join the requester's team, filtered by [query].
  Future<List<MissionColleague>> searchColleagues(String query) async {
    final raw = await _apiService.get(
      '/v1/missions/colleagues',
      queryParameters: {if (query.trim().isNotEmpty) 'q': query.trim()},
    );
    final response = raw['response'];
    if (response is! Map || response['data'] is! List) {
      throw ApiException(message: 'Invalid colleague list format');
    }
    return (response['data'] as List)
        .map((e) => MissionColleague.fromMap(Map<String, dynamic>.from(e)))
        .toList();
  }

  /// Submits the employee's own request; it starts at the office head.
  Future<MissionDetail> submitRequest(MissionRequestInput input) async =>
      MissionDetail.fromMap(
        _data(
          await _apiService.post('/v1/missions/request', body: input.toBody()),
        ),
      );

  Future<MissionDetail> fetchDetail(int id) async =>
      MissionDetail.fromMap(_data(await _apiService.get('/v1/missions/$id')));

  Future<MissionDetail> start(int id) async => MissionDetail.fromMap(
    _data(await _apiService.post('/v1/missions/$id/start')),
  );

  Future<MissionDetail> report(int id, MissionReportInput input) async =>
      MissionDetail.fromMap(
        _data(
          await _apiService.post(
            '/v1/missions/$id/report',
            body: input.toBody(),
          ),
        ),
      );

  Future<Uri> documentUrl(int id, int documentId) async => _signedUrl(
    await _apiService.get('/v1/missions/$id/documents/$documentId/signed-url'),
  );

  /// Short-lived link to a printable letter: [MissionLetter.order] or the
  /// caller's own [MissionLetter.travelVisa].
  Future<Uri> letterUrl(int id, MissionLetter letter) async => _signedUrl(
    await _apiService.get('/v1/missions/$id/letters/${letter.path}/signed-url'),
  );

  Uri _signedUrl(Map<String, dynamic> raw) {
    final data = _data(raw);
    final uri = Uri.tryParse(data['url']?.toString() ?? '');
    if (uri == null ||
        !uri.hasAuthority ||
        !['http', 'https'].contains(uri.scheme)) {
      throw ApiException(message: 'Invalid document URL');
    }
    return uri;
  }

  Future<List<MissionSummary>> fetchMissions(AuthUser user) async {
    if (user.userId <= 0) {
      throw ApiException(message: 'Invalid user session');
    }

    final byId = <int, MissionSummary>{};
    var page = 1;
    while (true) {
      final payload = _data(
        await _apiService.get(
          '/v1/missions',
          queryParameters: {'scope': 'mine', 'per_page': 100, 'page': page},
        ),
      );
      final rows = payload['data'];
      if (rows is! List) {
        throw ApiException(message: 'Invalid mission list format');
      }
      for (final row in rows) {
        if (row is! Map) {
          throw ApiException(message: 'Invalid mission item format');
        }
        final mission = MissionSummary.fromMap(Map<String, dynamic>.from(row));
        byId[mission.id] = mission;
      }
      final lastPage = int.tryParse('${payload['last_page']}') ?? 1;
      if (rows.isEmpty || page >= lastPage) break;
      page++;
    }
    final missions = byId.values.toList();
    missions.sort((a, b) => b.id.compareTo(a.id));
    return missions;
  }
}
