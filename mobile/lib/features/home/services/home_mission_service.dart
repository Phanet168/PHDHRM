import '../../../core/network/api_exception.dart';
import '../../../core/network/api_service.dart';
import '../../auth/models/auth_user.dart';
import '../models/mission_summary.dart';

class HomeMissionService {
  HomeMissionService({ApiService? apiService})
    : _apiService = apiService ?? ApiService();

  final ApiService _apiService;

  Future<List<MissionSummary>> fetchMissions(AuthUser user) async {
    if (user.userId <= 0) {
      throw ApiException(message: 'Invalid user session');
    }

    final raw = await _apiService.get('/v1/missions');
    final response = raw['response'];
    if (response is! Map<String, dynamic>) {
      throw ApiException(message: 'Invalid mission response format');
    }

    final status = (response['status'] ?? '').toString().toLowerCase();
    if (status != 'ok') {
      return <MissionSummary>[];
    }

    final dynamic payload = response['data'];
    final List<dynamic> rows;
    if (payload is List<dynamic>) {
      rows = payload;
    } else if (payload is Map<String, dynamic> && payload['data'] is List) {
      rows = (payload['data'] as List).cast<dynamic>();
    } else {
      return <MissionSummary>[];
    }

    final missions = <MissionSummary>[];
    for (final row in rows) {
      if (row is Map<String, dynamic>) {
        missions.add(MissionSummary.fromMap(row));
      } else if (row is Map) {
        missions.add(
          MissionSummary.fromMap(
            row.map((key, value) => MapEntry(key.toString(), value)),
          ),
        );
      }
    }

    missions.sort((a, b) => b.id.compareTo(a.id));
    return missions;
  }
}
