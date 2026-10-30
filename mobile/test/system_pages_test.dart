import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:staff_mobile_app/core/localization/laravel_language_service.dart';
import 'package:staff_mobile_app/core/network/api_exception.dart';
import 'package:staff_mobile_app/core/theme/app_design_system.dart';
import 'package:staff_mobile_app/core/theme/appearance_settings.dart';
import 'package:staff_mobile_app/features/auth/controllers/auth_controller.dart';
import 'package:staff_mobile_app/features/home/pages/home/home_menu.dart';
import 'package:staff_mobile_app/features/system/pages/about_app_page.dart';
import 'package:staff_mobile_app/features/system/pages/appearance_page.dart';
import 'package:staff_mobile_app/features/system/pages/help_support_page.dart';
import 'package:staff_mobile_app/features/system/pages/legal_document_page.dart';
import 'package:staff_mobile_app/features/system/pages/settings_home_page.dart';
import 'package:staff_mobile_app/features/system/support_contact.dart';

Future<void> _pump(
  WidgetTester tester,
  Widget page, {
  double textScale = 1,
  Size size = const Size(390, 2400),
}) async {
  tester.view.devicePixelRatio = 1;
  tester.view.physicalSize = size;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    MaterialApp(
      locale: const Locale('km'),
      supportedLocales: const [Locale('km')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      builder:
          (context, app) => MediaQuery(
            data: MediaQuery.of(
              context,
            ).copyWith(textScaler: TextScaler.linear(textScale)),
            child: app!,
          ),
      home: page,
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    PackageInfo.setMockInitialValues(
      appName: 'PHD HRM',
      packageName: 'phdhrm',
      version: '1.2.0',
      buildNumber: '7',
      buildSignature: '',
    );
    AppearanceSettings.instance.reset();
  });

  test('interface text is Khmer only, with no server language map', () async {
    final language = await LaravelLanguageService.instance.load();
    expect(language['email'], 'អ៊ីមែល');
    expect(language['gps_live'], 'ផ្ទាល់');
  });

  test('errors are told in Khmer', () {
    expect(
      extractApiErrorMessage(NetworkException()),
      contains('មិនអាចភ្ជាប់ទៅម៉ាស៊ីនមេ'),
    );
    expect(
      extractApiErrorMessage(
        ApiException(message: 'Forbidden', statusCode: 403),
      ),
      'អ្នកមិនមានសិទ្ធិធ្វើសកម្មភាពនេះទេ។',
    );
    expect(
      extractApiErrorMessage(const FormatException('Login failed')),
      'ចូលគណនីមិនបានជោគជ័យ។',
    );
    // The server's own Khmer message is kept as it is.
    expect(
      extractApiErrorMessage(
        ApiException(message: 'សំណើនេះត្រូវបានអនុម័តរួចហើយ។', statusCode: 422),
      ),
      'សំណើនេះត្រូវបានអនុម័តរួចហើយ។',
    );
    expect(
      extractApiErrorMessage(Exception('Connection timeout')),
      contains('មិនអាចភ្ជាប់'),
    );
  });

  testWidgets('Flutter\'s own labels are Khmer', (tester) async {
    late MaterialLocalizations labels;
    await _pump(
      tester,
      Builder(
        builder: (context) {
          labels = MaterialLocalizations.of(context);
          return const SizedBox();
        },
      ),
    );
    expect(labels.cancelButtonLabel, isNot('Cancel'));
    expect(RegExp('[ក-៿]').hasMatch(labels.cancelButtonLabel), isTrue);
  });

  testWidgets('settings has no language choice and leads everywhere', (
    tester,
  ) async {
    HomeMenuItem? popped;
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(390, 2400);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder:
              (context) => TextButton(
                onPressed: () async {
                  popped = await Navigator.of(context).push<HomeMenuItem>(
                    MaterialPageRoute(
                      builder:
                          (_) => SettingsHomePage(
                            authController: AuthController(),
                          ),
                    ),
                  );
                },
                child: const Text('open'),
              ),
        ),
      ),
    );
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.text('ភាសា'), findsNothing);
    expect(find.text('រូបរាង'), findsOneWidget);
    expect(find.text('ពណ៌តាមថ្ងៃ · អក្សរធម្មតា'), findsOneWidget);
    expect(find.text(SupportContact.phoneDisplay), findsOneWidget);
    expect(find.text('PHD HRM · កំណែ 1.2.0'), findsOneWidget);
    expect(find.textContaining('កំពុងអភិវឌ្ឍ'), findsNothing);

    await tester.tap(find.text('ការជូនដំណឹង'));
    await tester.pumpAndSettle();
    expect(popped, HomeMenuItem.notice);
  });

  testWidgets('appearance changes the accent and text size at once', (
    tester,
  ) async {
    final semantics = tester.ensureSemantics();
    await _pump(tester, const AppearancePage());
    await tester.tap(find.bySemanticsLabel('ខៀវ'));
    await tester.pumpAndSettle();
    expect(AppearanceSettings.instance.accent, const Color(0xFF1D4ED8));
    expect(
      AppDesignSystem.colorForWeekday(DateTime.monday),
      const Color(0xFF1D4ED8),
    );

    await tester.tap(find.bySemanticsLabel('អក្សរធំ'));
    await tester.pumpAndSettle();
    expect(AppearanceSettings.instance.textScale, 1.15);
    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getDouble('appearance.text_scale'), 1.15);

    await tester.tap(find.bySemanticsLabel('ពណ៌តាមថ្ងៃ'));
    await tester.pumpAndSettle();
    expect(AppearanceSettings.instance.accent, isNull);
    expect(
      AppDesignSystem.colorForWeekday(DateTime.friday),
      AppDesignSystem.traditionalWeekdayColor(DateTime.friday),
    );
    semantics.dispose();
  });

  testWidgets('help shows the support contact and finds answers', (
    tester,
  ) async {
    await _pump(tester, const HelpSupportPage());
    expect(find.text('លាង ផានិត្យ'), findsOneWidget);
    expect(find.text('០៩៧ ២០៩ ៧៨៨៨'), findsOneWidget);
    expect(find.text('lphanet@gmail.com'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'ក្រៅបណ្ដាញ');
    await tester.pumpAndSettle();
    expect(find.text('ពេលគ្មានអ៊ីនធឺណិត តើស្កេនវត្តមានបានទេ?'), findsNothing);
    await tester.enterText(find.byType(TextField), 'អ៊ីនធឺណិត');
    await tester.pumpAndSettle();
    expect(find.text('ពេលគ្មានអ៊ីនធឺណិត តើស្កេនវត្តមានបានទេ?'), findsOneWidget);
    expect(find.text('តើខ្ញុំអាចសុំច្បាប់បានយ៉ាងដូចម្តេច?'), findsNothing);

    // The report needs a description before it can be sent.
    await tester.tap(find.text('រាយការណ៍បញ្ហា'));
    await tester.pumpAndSettle();
    final send = find.widgetWithText(FilledButton, 'ផ្ញើតាមអ៊ីមែល');
    expect(tester.widget<FilledButton>(send).onPressed, isNull);
    await tester.enterText(
      find.byKey(const ValueKey('report-details')),
      'ស្កេនមិនចូល',
    );
    await tester.pumpAndSettle();
    expect(tester.widget<FilledButton>(send).onPressed, isNotNull);

    final mail =
        SupportContact.emailUri(subject: 'a b', body: 'ក ខ').toString();
    expect(mail, startsWith('mailto:lphanet@gmail.com?subject=a%20b&body='));
    expect(SupportContact.phoneUri.toString(), 'tel:0972097888');
  });

  testWidgets('about opens the privacy policy and terms, no licences', (
    tester,
  ) async {
    await _pump(tester, const AboutAppPage(), size: const Size(390, 3000));
    expect(find.text('កំណែ 1.2.0 (7)'), findsOneWidget);
    expect(find.text('លាង ផានិត្យ'), findsOneWidget);

    await tester.tap(find.text('គោលការណ៍ឯកជនភាព'));
    await tester.pumpAndSettle();
    expect(find.byType(LegalDocumentPage), findsOneWidget);
    expect(find.text('កាមេរ៉ា និងទីតាំង'), findsOneWidget);
    await tester.tap(find.byTooltip('ត្រឡប់ក្រោយ').last);
    await tester.pumpAndSettle();

    await tester.tap(find.text('លក្ខខណ្ឌប្រើប្រាស់'));
    await tester.pumpAndSettle();
    expect(find.text('ការស្កេនវត្តមាន'), findsOneWidget);
    await tester.tap(find.byTooltip('ត្រឡប់ក្រោយ').last);
    await tester.pumpAndSettle();

    expect(find.text('អាជ្ញាបណ្ណកម្មវិធី'), findsNothing);
  });

  testWidgets('system pages fit a narrow screen at 2x text', (tester) async {
    for (final page in <Widget>[
      const AppearancePage(),
      const HelpSupportPage(),
      const AboutAppPage(),
      const LegalDocumentPage(document: LegalDocument.privacy),
      SettingsHomePage(authController: AuthController()),
    ]) {
      await _pump(tester, page, textScale: 2, size: const Size(320, 6000));
      expect(tester.takeException(), isNull, reason: '${page.runtimeType}');
      await tester.pumpWidget(const SizedBox());
    }
  });
}
