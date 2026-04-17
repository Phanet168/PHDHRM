import 'dart:io' show Platform;

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:package_info_plus/package_info_plus.dart';

class DeviceMetadataService {
  final DeviceInfoPlugin _deviceInfo = DeviceInfoPlugin();

  Future<Map<String, dynamic>> collect() async {
    final package = await PackageInfo.fromPlatform();

    final data = <String, dynamic>{
      'app_name': package.appName,
      'package_name': package.packageName,
      'app_version': package.version,
      'build_number': package.buildNumber,
    };

    if (kIsWeb) {
      final web = await _deviceInfo.webBrowserInfo;
      data.addAll(<String, dynamic>{
        'platform': 'web',
        'browser_name': web.browserName.name,
        'user_agent': web.userAgent,
        'vendor': web.vendor,
        'hardware_concurrency': web.hardwareConcurrency,
      });
      return data;
    }

    if (Platform.isAndroid) {
      final android = await _deviceInfo.androidInfo;
      data.addAll(<String, dynamic>{
        'platform': 'android',
        'brand': android.brand,
        'manufacturer': android.manufacturer,
        'model': android.model,
        'device': android.device,
        'product': android.product,
        'android_version': android.version.release,
        'android_sdk_int': android.version.sdkInt,
      });
      return data;
    }

    if (Platform.isIOS) {
      final ios = await _deviceInfo.iosInfo;
      data.addAll(<String, dynamic>{
        'platform': 'ios',
        'name': ios.name,
        'model': ios.model,
        'system_name': ios.systemName,
        'system_version': ios.systemVersion,
        'localized_model': ios.localizedModel,
      });
      return data;
    }

    if (Platform.isMacOS) {
      final mac = await _deviceInfo.macOsInfo;
      data.addAll(<String, dynamic>{
        'platform': 'macos',
        'model': mac.model,
        'os_release': mac.osRelease,
        'arch': mac.arch,
        'kernel_version': mac.kernelVersion,
      });
      return data;
    }

    if (Platform.isWindows) {
      final win = await _deviceInfo.windowsInfo;
      data.addAll(<String, dynamic>{
        'platform': 'windows',
        'computer_name': win.computerName,
        'major_version': win.majorVersion,
        'minor_version': win.minorVersion,
        'build_number': win.buildNumber,
      });
      return data;
    }

    if (Platform.isLinux) {
      final linux = await _deviceInfo.linuxInfo;
      data.addAll(<String, dynamic>{
        'platform': 'linux',
        'name': linux.name,
        'version': linux.version,
        'id': linux.id,
        'pretty_name': linux.prettyName,
      });
      return data;
    }

    data['platform'] = 'unknown';
    return data;
  }

  String summarize(Map<String, dynamic> info) {
    final platform = (info['platform'] ?? 'unknown').toString();
    final model =
        (info['model'] ?? info['device'] ?? info['name'] ?? '-').toString();
    final version =
        (info['android_version'] ??
                info['system_version'] ??
                info['os_release'] ??
                info['version'] ??
                '-')
            .toString();

    return '$platform | $model | $version';
  }
}
