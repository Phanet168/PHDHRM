import Flutter
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    if let controller = window?.rootViewController as? FlutterViewController {
      // Monotonic uptime + boot identity, as on Android (MainActivity.kt). Offline
      // attendance anchors the server's clock to these, so changing the phone's
      // date/time cannot fake a scan time.
      let clock = FlutterMethodChannel(name: "phdhrm/clock", binaryMessenger: controller.binaryMessenger)
      clock.setMethodCallHandler { call, result in
        if call.method == "uptime" {
          result(AppDelegate.uptime())
        } else {
          result(FlutterMethodNotImplemented)
        }
      }
    }
    GeneratedPluginRegistrant.register(with: self)
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  /// Milliseconds since boot (CLOCK_MONOTONIC keeps counting while the phone sleeps)
  /// and the boot time in seconds, which changes after a restart.
  private static func uptime() -> [String: Int64] {
    var now = timespec()
    clock_gettime(CLOCK_MONOTONIC, &now)
    let elapsed = Int64(now.tv_sec) * 1000 + Int64(now.tv_nsec) / 1_000_000

    var boot = timeval()
    var size = MemoryLayout<timeval>.stride
    var mib: [Int32] = [CTL_KERN, KERN_BOOTTIME]
    let found = sysctl(&mib, 2, &boot, &size, nil, 0) == 0

    return ["elapsed": elapsed, "boot": found ? Int64(boot.tv_sec) : -1]
  }
}
