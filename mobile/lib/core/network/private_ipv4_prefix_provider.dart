import 'private_ipv4_prefix_provider_stub.dart'
    if (dart.library.io) 'private_ipv4_prefix_provider_io.dart';

Future<List<String>> privateIpv4Prefixes() => loadPrivateIpv4Prefixes();
