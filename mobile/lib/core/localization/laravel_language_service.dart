import '../network/api_service.dart';

class LaravelLanguageService {
  LaravelLanguageService._();

  static final LaravelLanguageService instance = LaravelLanguageService._();

  final ApiService _apiService = ApiService();
  Map<String, String>? _cache;
  Future<Map<String, String>>? _inFlight;
  DateTime? _lastFetchAttemptAt;
  static const Duration _refreshInterval = Duration(minutes: 10);
  static final RegExp _khmerRegex = RegExp(r'[\u1780-\u17FF]');

  static const Map<String, String> _khmerFallbacks = <String, String>{
    'login': 'ចូលប្រើ',
    'welcome_msg': 'សូមស្វាគមន៍មកវិញ',
    'sign_in': 'ចូលគណនី',
    'email': 'អ៊ីមែល',
    'password': 'ពាក្យសម្ងាត់',
    'email_fild_can_not_empty': 'សូមបញ្ចូលអ៊ីមែល',
    'email_pass_cannot_empt': 'សូមបញ្ចូលពាក្យសម្ងាត់',
    'logout': 'ចាកចេញ',
    'attendance_history': 'ប្រវត្តិវត្តមាន',
    'attendance': 'វត្តមាន',
    'attendance_adjustment': 'កែសម្រួលវត្តមាន',
    'leave_type': 'ការសុំច្បាប់',
    'leave_reason': 'មូលហេតុសុំច្បាប់',
    'request_new_leave': 'ដាក់សំណើច្បាប់ថ្មី',
    'approve_leave': 'ពិនិត្យ/អនុម័តសំណើ',
    'recent_requests': 'សំណើថ្មីៗ',
    'mission': 'បេសកកម្ម',
    'salary_details': 'ព័ត៌មានប្រាក់ខែ',
    'notice_list': 'ជូនដំណឹង',
    'my_profile': 'ព័ត៌មានផ្ទាល់ខ្លួន',
    'on_time': 'ទាន់ពេល',
    'late': 'មកយឺត',
    'early_leave': 'ចេញមុនម៉ោង',
    'late_and_early_leave': 'មកយឺត និងចេញមុន',
    'incomplete': 'មិនពេញលេញ',
    'service_redirect_leave': 'សូមដាក់សំណើច្បាប់ រួចរង់ចាំការអនុម័ត។',
    'service_adjustment_hint':
        'បើកប្រវត្តិវត្តមាន រួចជ្រើសថ្ងៃដើម្បីស្នើកែសម្រួល។',
    'wrong_info_alert': 'មិនមានព័ត៌មានអ្នកប្រើប្រាស់',
    'today_status': 'ស្ថានភាពថ្ងៃនេះ',
    'today_shift': 'វេនថ្ងៃនេះ',
    'status': 'ស្ថានភាព',
    'last_in': 'ម៉ោងចូលចុងក្រោយ',
    'last_out': 'ម៉ោងចេញចុងក្រោយ',
    'total_hours': 'ម៉ោងសរុប',
    'punches': 'ចំនួនស្កេន',
    'qr_scan': 'ស្កេនកូដ QR',
    'scan_attendance': 'ស្កេនវត្តមាន',
    'qr_attendance': 'បញ្ជាក់វត្តមានដោយ QR',
    'confirm_attendance': 'ស្កេន QR អង្គភាព ដើម្បីបញ្ជាក់វត្តមាន',
    'scan_now': 'ចុចស្កេនឥឡូវនេះ',
    'latest_records': 'ព័ត៌មានថ្មីៗ',
    'latest_7_days': 'កំណត់ត្រា ៧ ថ្ងៃចុងក្រោយ',
    'loading': 'កំពុងទាញទិន្នន័យ...',
    'today': 'ថ្ងៃនេះ',
    'view_full_history': 'មើលប្រវត្តិទាំងអស់',
    'view_all': 'មើលទាំងអស់',
    'quick_access': 'ចូលប្រើរហ័ស',
    'additional_services': 'សេវាកម្មបន្ថែម',
    'no_data_found': 'មិនមានទិន្នន័យ',
    'no_record_found': 'មិនមានកំណត់ត្រា',
    'no_shift_today': 'មិនទាន់មានវេនបង្ហាញ',
    'no_missions': 'មិនមានបេសកកម្ម',
    'no_notice_to_show': 'មិនទាន់មានជូនដំណឹង',
    'pending': 'រង់ចាំ',
    'approved': 'អនុម័ត',
    'rejected': 'បដិសេធ',
    'cancelled': 'បោះបង់',
    'day_leave': 'ចំនួនថ្ងៃសុំច្បាប់',
    'attachment': 'ឯកសារភ្ជាប់',
    'note': 'កំណត់សម្គាល់',
    'cancel': 'បោះបង់',
    'destination': 'គោលដៅ',
    'employee': 'បុគ្គលិក',
    'employees': 'បុគ្គលិក',
    'refresh': 'ធ្វើបច្ចុប្បន្នភាព',
    'menu': 'ម៉ឺនុយ',
    'unread': 'មិនទាន់អាន',
    'mark_all_read': 'សម្គាល់ថាបានអានទាំងអស់',
    'mark_as_read': 'សម្គាល់ថាបានអាន',
    // QR scan page keys
    'scan_again': 'ស្កេនម្ដងទៀត',
    'scan_success': 'ជោគជ័យ',
    'scan_failed': 'បរាជ័យ',
    'scan_result': 'លទ្ធផលស្កេន',
    'scan_time': 'ម៉ោងស្កេន',
    'scan_type': 'ប្រភេទស្កេន',
    'attendance_type': 'ប្រភេទវត្តមាន',
    'qr_detected': 'បានរកឃើញ QR កំពុងបញ្ជូនវត្តមាន...',
    'invalid_qr': 'ទិន្នន័យ QR មិនត្រឹមត្រូវ',
    'checking_attendance': 'កំពុងពិនិត្យទីតាំង និងរក្សាទុកវត្តមាន...',
    'location_service_disabled': 'សេវាទីតាំងត្រូវបានបិទ',
    'location_permission_denied': 'មិនបានអនុញ្ញាតប្រើទីតាំង',
    'location_permission_denied_permanent':
        'មិនបានអនុញ្ញាតប្រើទីតាំងជាអចិន្ត្រៃយ៍',
    'unexpected_error': 'កំហុសដែលមិននឹកស្មាន',
    'scan_qr_instruction':
        'ដាក់កូដ QR របស់អង្គភាពនៅក្នុងស៊ុម ហើយរង់ចាំការបញ្ជូនដោយស្វ័យប្រវត្តិ',
    'camera_access_failed': 'មិនអាចប្រើកាមេរ៉ា',
    'manual_token': 'បញ្ចូលដោយដៃ',
    'flashlight': 'ភ្លើងពិល',
    'manual_qr_token': 'បញ្ចូល QR Token ដោយដៃ',
    'manual_qr_hint': 'ចម្លង qr_token ឬអត្ថបទ QR មកបញ្ចូលទីនេះ',
    'submit': 'បញ្ជូន',
    'done': 'រួចរាល់',
    'time_in': 'ចូល',
    'time_out': 'ចេញ',
    'location': 'ទីតាំង',
    'distance': 'ចម្ងាយ',
    'allowed_range': 'ចម្ងាយអនុញ្ញាត',
  };

  Future<Map<String, String>> load({bool forceRefresh = false}) {
    if (forceRefresh || _cache == null) {
      _cache = Map<String, String>.from(_khmerFallbacks);
    }

    final shouldFetchRemote = forceRefresh || _isFetchStale();
    if (shouldFetchRemote) {
      _inFlight ??= _fetch();
    }

    return Future<Map<String, String>>.value(_cache);
  }

  bool _isFetchStale() {
    final lastAttempt = _lastFetchAttemptAt;
    if (lastAttempt == null) {
      return true;
    }

    return DateTime.now().difference(lastAttempt) >= _refreshInterval;
  }

  Future<Map<String, String>> _fetch() async {
    _lastFetchAttemptAt = DateTime.now();
    try {
      final raw = await _apiService.get(
        '/language',
        requiresAuth: false,
        throwOnError: false,
      );
      final response = raw['response'];
      if (response is Map) {
        final data = response.map(
          (key, value) => MapEntry(key.toString(), value?.toString() ?? ''),
        );
        final merged = _mergeWithKhmerFallbacks(data);
        _cache = merged;
        return merged;
      }
    } catch (_) {
      // Ignore network/parser issues and keep UI usable with fallback text.
    } finally {
      _inFlight = null;
    }

    _cache = Map<String, String>.from(_khmerFallbacks);
    return _cache!;
  }

  Map<String, String> _mergeWithKhmerFallbacks(Map<String, String> remote) {
    final merged = Map<String, String>.from(_khmerFallbacks);

    remote.forEach((key, value) {
      final trimmed = value.trim();
      if (trimmed.isEmpty) {
        return;
      }

      final hasKhmerFallback = _khmerFallbacks.containsKey(key);
      final isKhmerText = _khmerRegex.hasMatch(trimmed);
      if (hasKhmerFallback && !isKhmerText) {
        return;
      }

      merged[key] = trimmed;
    });

    return merged;
  }
}
