import 'dart:io';

Future<List<String>> loadPrivateIpv4Prefixes() async {
  final results = <String>{};

  try {
    final interfaces = await NetworkInterface.list(
      type: InternetAddressType.IPv4,
      includeLoopback: false,
    );

    for (final interface in interfaces) {
      for (final address in interface.addresses) {
        final host = address.address.trim();
        final parts = host.split('.');
        if (parts.length != 4) {
          continue;
        }

        final first = int.tryParse(parts[0]) ?? -1;
        final second = int.tryParse(parts[1]) ?? -1;
        if (!_isPrivateIpv4(first, second)) {
          continue;
        }

        results.add('${parts[0]}.${parts[1]}.${parts[2]}');
      }
    }
  } catch (_) {
    return const <String>[];
  }

  return results.toList(growable: false);
}

bool _isPrivateIpv4(int first, int second) {
  if (first == 10) {
    return true;
  }
  if (first == 172 && second >= 16 && second <= 31) {
    return true;
  }
  if (first == 192 && second == 168) {
    return true;
  }
  return false;
}
