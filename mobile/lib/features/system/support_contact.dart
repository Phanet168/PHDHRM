import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

/// Who to call for help with the app — shown on Help & Support, About and
/// Settings, so the number and address live in one place.
class SupportContact {
  const SupportContact._();

  static const name = 'លាង ផានិត្យ';
  static const role = 'ជំនួយបច្ចេកទេស PHD HRM';
  static const phone = '0972097888';
  static const phoneDisplay = '០៩៧ ២០៩ ៧៨៨៨';
  static const email = 'lphanet@gmail.com';

  static Uri get phoneUri => Uri(scheme: 'tel', path: phone);

  /// A mail draft to support; spaces stay spaces (not '+') in mail apps.
  static Uri emailUri({String subject = '', String body = ''}) => Uri.parse(
    'mailto:$email'
    '?subject=${Uri.encodeComponent(subject)}'
    '&body=${Uri.encodeComponent(body)}',
  );

  static Future<void> call(BuildContext context) =>
      _open(context, phoneUri, 'មិនអាចបើកកម្មវិធីហៅទូរសព្ទបានទេ');

  static Future<void> sendEmail(
    BuildContext context, {
    String subject = '',
    String body = '',
  }) => _open(
    context,
    emailUri(subject: subject, body: body),
    'មិនអាចបើកកម្មវិធីអ៊ីមែលបានទេ',
  );

  static Future<void> copy(
    BuildContext context,
    String value,
    String what,
  ) async {
    await Clipboard.setData(ClipboardData(text: value));
    if (!context.mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text('បានចម្លង$what')));
  }

  static Future<void> _open(
    BuildContext context,
    Uri uri,
    String failure,
  ) async {
    var opened = false;
    try {
      opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {}
    if (!opened && context.mounted) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text('$failure។')));
    }
  }
}
