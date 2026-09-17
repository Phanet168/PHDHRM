import '../../../core/network/api_exception.dart';
import '../../../core/network/api_service.dart';
import '../../auth/models/auth_user.dart';
import '../models/mission_summary.dart';
import '../models/mission_detail.dart';

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

  Future<MissionDetail> fetchDetail(int id) async =>
      MissionDetail.fromMap(_data(await _apiService.get('/v1/missions/$id')));

  Future<MissionDetail> start(int id) async => MissionDetail.fromMap(
    _data(await _apiService.post('/v1/missions/$id/start')),
  );

  Future<MissionDetail> report(int id, String summary) async =>
      MissionDetail.fromMap(
        _data(
          await _apiService.post(
            '/v1/missions/$id/report',
            body: {'summary': summary.trim()},
          ),
        ),
      );

  Future<Uri> documentUrl(int id, int documentId) async {
    final data = _data(
      await _apiService.get(
        '/v1/missions/$id/documents/$documentId/signed-url',
      ),
    );
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
