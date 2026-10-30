class ApiException implements Exception {
  ApiException({required this.message, this.statusCode, this.body});

  final String message;
  final int? statusCode;

  /// The decoded JSON body of a non-2xx response, when there was one — lets a
  /// caller read structured rejection details (e.g. scan distance) on a 422.
  final Map<String, dynamic>? body;

  @override
  String toString() =>
      'ApiException(statusCode: $statusCode, message: $message)';
}

class UnauthorizedException extends ApiException {
  UnauthorizedException({super.message = 'Unauthorized'})
    : super(statusCode: 401);
}

class NetworkException extends ApiException {
  NetworkException({super.message = defaultMessage});

  static const String defaultMessage =
      'Cannot connect to server. Please check Server/IP and network.';
}

bool isNetworkErrorMessage(String message) {
  final normalized = message.toLowerCase();
  return normalized.contains('connection timeout') ||
      normalized.contains('timeout') ||
      normalized.contains('failed host lookup') ||
      normalized.contains('connection refused') ||
      normalized.contains('connection closed') ||
      normalized.contains('closed before full header') ||
      normalized.contains('connection reset') ||
      normalized.contains('network is unreachable') ||
      normalized.contains('network unreachable') ||
      normalized.contains('no route to host') ||
      normalized.contains('software caused connection abort') ||
      normalized.contains('httpexception') ||
      normalized.contains('clientexception') ||
      normalized.contains('socketexception') ||
      normalized.contains('xmlhttprequest') ||
      normalized.contains('unexpected non-json response') ||
      normalized.contains('api base url') ||
      normalized.contains('tried:');
}

final RegExp _khmerScript = RegExp('[ក-៿]');

const String _networkKm =
    'មិនអាចភ្ជាប់ទៅម៉ាស៊ីនមេបានទេ។ សូមពិនិត្យអ៊ីនធឺណិត ឬអាសយដ្ឋានម៉ាស៊ីនមេ។';

/// The Khmer message for "the server could not be reached" — screens that
/// offer the server settings look for it.
const String networkErrorKm = _networkKm;

/// The sign-in device gate (registration / approval), keyed by the server's
/// `code`, in Khmer. The login screen offers a device request for these.
const Map<String, String> deviceGateMessagesKm = <String, String>{
  'device_not_registered':
      'ទូរសព្ទនេះមិនទាន់បានចុះឈ្មោះ។ សូមផ្ញើសំណើអនុញ្ញាតឧបករណ៍។',
  'device_pending': 'ទូរសព្ទនេះកំពុងរង់ចាំការអនុម័តពីអ្នកគ្រប់គ្រង។',
  'device_blocked': 'ទូរសព្ទនេះត្រូវបានបិទ។ សូមទាក់ទងអ្នកគ្រប់គ្រង។',
  'device_rejected':
      'សំណើអនុញ្ញាតទូរសព្ទនេះត្រូវបានបដិសេធ។ សូមទាក់ទងអ្នកគ្រប់គ្រង។',
  'device_imei_mismatch':
      'លេខ IMEI មិនត្រូវនឹងទូរសព្ទដែលបានចុះឈ្មោះទេ។ សូមទាក់ទងអ្នកគ្រប់គ្រង។',
};

/// The server's English wording for each [deviceGateMessagesKm] code, for
/// responses that arrive without a code.
const Map<String, String> _deviceGateWording = <String, String>{
  'device is not registered': 'device_not_registered',
  'waiting for administrator approval': 'device_pending',
  'device has been blocked': 'device_blocked',
  'registration was rejected': 'device_rejected',
  'hardware mismatch': 'device_imei_mismatch',
};

/// English messages the app or Laravel can produce, said in Khmer.
const Map<String, String> _knownKm = <String, String>{
  'login failed': 'ចូលគណនីមិនបានជោគជ័យ។',
  'unauthorized': 'សម័យប្រើប្រាស់បានផុតកំណត់។ សូមចូលគណនីម្ដងទៀត។',
  'unauthenticated.': 'សម័យប្រើប្រាស់បានផុតកំណត់។ សូមចូលគណនីម្ដងទៀត។',
  'these credentials do not match our records.':
      'អ៊ីមែល ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវ។',
  'invalid user session': 'សូមចូលគណនីម្ដងទៀត។',
  'this account does not have an employee profile.':
      'គណនីនេះមិនទាន់ភ្ជាប់ជាមួយព័ត៌មានមន្ត្រីទេ។',
  'end date must be on or after start date.':
      'ថ្ងៃបញ្ចប់ត្រូវនៅថ្ងៃចាប់ផ្តើម ឬក្រោយនោះ។',
  'unable to submit leave request': 'មិនអាចដាក់សំណើច្បាប់បានទេ។',
  'unable to create correspondence': 'មិនអាចបង្កើតលិខិតបានទេ។',
  'attachment url is unavailable.': 'មិនអាចបើកឯកសារភ្ជាប់បានទេ។',
  'invalid credentials.': 'អ៊ីមែល ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវ។',
  'device request submitted. please wait for administrator approval.':
      'បានផ្ញើសំណើអនុញ្ញាតទូរសព្ទ។ សូមរង់ចាំការអនុម័តពីអ្នកគ្រប់គ្រង។',
  'this device is already approved.': 'ទូរសព្ទនេះត្រូវបានអនុម័តរួចហើយ។',
};

/// The Khmer message to show for a failure: Khmer text from the server is
/// kept as is; network trouble, the device gate (by [code] or wording),
/// known English messages and HTTP errors get a Khmer sentence; anything
/// else keeps its detail after a Khmer lead-in.
String khmerErrorMessage(String message, {int? statusCode, String? code}) {
  final text = message.trim();
  if (_khmerScript.hasMatch(text)) return text;
  final gate = deviceGateMessagesKm[code];
  if (gate != null) return gate;
  if (text.isEmpty) return 'មានបញ្ហាមិនរំពឹងទុក។ សូមព្យាយាមម្ដងទៀត។';
  if (text == NetworkException.defaultMessage || isNetworkErrorMessage(text)) {
    return _networkKm;
  }
  final lower = text.toLowerCase();
  for (final MapEntry(key: wording, value: gateCode)
      in _deviceGateWording.entries) {
    if (lower.contains(wording)) return deviceGateMessagesKm[gateCode]!;
  }
  final known = _knownKm[lower];
  if (known != null) return known;
  if (text.toLowerCase().startsWith('invalid ')) {
    return 'ទិន្នន័យពីម៉ាស៊ីនមេមិនត្រឹមត្រូវ។ សូមព្យាយាមម្ដងទៀត។';
  }
  switch (statusCode) {
    case 401:
      return _knownKm['unauthorized']!;
    case 403:
      return 'អ្នកមិនមានសិទ្ធិធ្វើសកម្មភាពនេះទេ។';
    case 404:
      return 'រកមិនឃើញទិន្នន័យដែលស្នើសុំទេ។';
    case 419:
    case 429:
      return 'សូមរង់ចាំបន្តិច រួចព្យាយាមម្ដងទៀត។';
  }
  if (statusCode != null && statusCode >= 500) {
    return 'ម៉ាស៊ីនមេមានបញ្ហា។ សូមព្យាយាមម្ដងទៀតនៅពេលក្រោយ។';
  }
  return 'មិនអាចដំណើរការបានទេ ($text)';
}

String extractApiErrorMessage(Object error) {
  if (error is ApiException) {
    final code = error.body?['code'];
    return khmerErrorMessage(
      error.message,
      statusCode: error.statusCode,
      code: code is String ? code : null,
    );
  }
  if (error is FormatException) {
    return khmerErrorMessage(error.message);
  }
  return khmerErrorMessage(_rawErrorMessage(error));
}

String _rawErrorMessage(Object error) {
  // "Exception: …" from a plain throw — the prefix is not for users.
  final raw = error.toString().trim().replaceFirst(
    RegExp(r'^\w*Exception:\s*'),
    '',
  );
  final match = RegExp(
    r'^ApiException\(statusCode: [^,]+, message: (.*)\)$',
  ).firstMatch(raw);
  final message = match?.group(1)?.trim();

  if (message != null && message.isNotEmpty) {
    final statusMatch = RegExp(
      r'^ApiException\(statusCode: (\d+|null),',
    ).firstMatch(raw);
    final statusText = statusMatch?.group(1)?.trim().toLowerCase();
    final hasHttpStatus = statusText != null && statusText != 'null';
    if (hasHttpStatus) {
      return message;
    }

    return isNetworkErrorMessage(message)
        ? NetworkException.defaultMessage
        : message;
  }

  return isNetworkErrorMessage(raw) ? NetworkException.defaultMessage : raw;
}
