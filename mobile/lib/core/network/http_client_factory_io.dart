import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:http/io_client.dart';

import '../config/api_config.dart';

/// A dead address fails within [ApiConfig.connectionTimeout] (instead of
/// eating the whole request budget), so the next address is tried quickly.
http.Client buildHttpClient() =>
    IOClient(HttpClient()..connectionTimeout = ApiConfig.connectionTimeout);
