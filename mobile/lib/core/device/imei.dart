/// IMEI helpers: the phone's 15-digit hardware number is the app's machine
/// number. Android 10+ does not let an app read it, so the officer types it
/// in (dialling *#06# shows it) and these checks catch typing mistakes.
library;

/// Digits only: "35 209900-176148-1" → "352099001761481".
String normalizeImei(String value) => value.replaceAll(RegExp(r'\D'), '');

/// 15 digits whose last digit is the Luhn check digit of the first 14.
bool isValidImei(String value) {
  final digits = normalizeImei(value);
  if (digits.length != 15 || RegExp(r'^0+$').hasMatch(digits)) return false;
  var sum = 0;
  for (var i = 0; i < 15; i++) {
    var d = digits.codeUnitAt(i) - 48;
    // Every second digit from the left (positions 2, 4, …) is doubled.
    if (i.isOdd) {
      d *= 2;
      if (d > 9) d -= 9;
    }
    sum += d;
  }
  return sum % 10 == 0;
}

/// "352099001761481" → "35 209900 176148 1", the way phones print it.
String formatImei(String value) {
  final d = normalizeImei(value);
  if (d.length != 15) return value;
  return '${d.substring(0, 2)} ${d.substring(2, 8)} ${d.substring(8, 14)} ${d.substring(14)}';
}
