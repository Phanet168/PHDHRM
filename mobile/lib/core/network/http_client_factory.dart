import 'package:http/http.dart' as http;

import 'http_client_factory_stub.dart'
    if (dart.library.io) 'http_client_factory_io.dart';

/// The app's HTTP client: fails fast when a server address is unreachable,
/// while still letting a reachable-but-busy server finish answering.
http.Client createHttpClient() => buildHttpClient();
