class ApiException implements Exception {
  ApiException({required this.message, this.statusCode});

  final String message;
  final int? statusCode;

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

String extractApiErrorMessage(Object error) {
  if (error is ApiException) {
    if (error.statusCode != null && error.statusCode! >= 400) {
      return error.message;
    }

    return isNetworkErrorMessage(error.message)
        ? NetworkException.defaultMessage
        : error.message;
  }

  final raw = error.toString().trim();
  final match = RegExp(
    r'^ApiException\(statusCode: [^,]+, message: (.*)\)$',
  ).firstMatch(raw);
  final message = match?.group(1)?.trim();

  if (message != null && message.isNotEmpty) {
    final statusMatch = RegExp(r'^ApiException\(statusCode: (\d+|null),').firstMatch(raw);
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
