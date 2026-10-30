package com.example.staff_mobile_app

import android.os.Build
import android.os.SystemClock
import android.provider.Settings
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        // Monotonic uptime + boot counter. Offline attendance anchors the server's
        // clock to these, so changing the phone's date/time cannot fake a scan time.
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "phdhrm/clock")
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "uptime" -> result.success(
                        mapOf(
                            "elapsed" to SystemClock.elapsedRealtime(),
                            "boot" to if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                                Settings.Global.getInt(contentResolver, Settings.Global.BOOT_COUNT, -1)
                            } else {
                                -1
                            },
                        ),
                    )
                    else -> result.notImplemented()
                }
            }
        // This phone's own ID (Settings.Secure.ANDROID_ID). Android 10+ no longer lets an
        // app read the IMEI, so the IMEI is typed in by the officer; this is only the
        // fallback until then — unlike Build.ID it differs from phone to phone.
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "phdhrm/device")
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "androidId" -> result.success(
                        Settings.Secure.getString(contentResolver, Settings.Secure.ANDROID_ID),
                    )
                    else -> result.notImplemented()
                }
            }
    }
}
