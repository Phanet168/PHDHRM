import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:staff_mobile_app/core/device/imei.dart';
import 'package:staff_mobile_app/core/network/api_exception.dart';
import 'package:staff_mobile_app/core/storage/machine_number_storage_service.dart';
import 'package:staff_mobile_app/features/auth/models/login_request.dart';
import 'package:staff_mobile_app/features/auth/widgets/imei_entry_sheet.dart';

const _imei = '490154203237518';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    FlutterSecureStorage.setMockInitialValues({});
    SharedPreferences.setMockInitialValues({});
  });

  test('IMEI check digit catches typing mistakes', () {
    expect(isValidImei(_imei), isTrue);
    expect(isValidImei('49 015420 323751 8'), isTrue);
    expect(isValidImei('490154203237519'), isFalse); // last digit wrong
    expect(isValidImei('49015420323751'), isFalse); // 14 digits
    expect(isValidImei('ANDROID-BP2A.250605.031.A3'), isFalse);
    expect(formatImei(_imei), '49 015420 323751 8');
  });

  test('the machine number becomes the IMEI; the old number is kept '
      'for moving the registration', () async {
    FlutterSecureStorage.setMockInitialValues({
      'machine_number': 'ANDROID-BP2A.250605.031.A3',
    });
    final storage = MachineNumberStorageService();
    expect(await storage.getImei(), isNull);
    expect(await storage.getMachineNumber(), 'ANDROID-BP2A.250605.031.A3');

    await storage.saveImei('49 015420 323751 8');
    expect(await storage.getImei(), _imei);
    expect(await storage.getMachineNumber(), _imei);
    expect(
      await storage.getLegacyMachineNumber(),
      'ANDROID-BP2A.250605.031.A3',
    );
    expect(() => storage.saveImei('490154203237519'), throwsArgumentError);
  });

  test('a new phone without IMEI falls back to its own Android ID, '
      'not the shared build code', () async {
    const channel = MethodChannel('phdhrm/device-test');
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async => 'a1b2c3d4e5f60718');
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    addTearDown(() => debugDefaultTargetPlatformOverride = null);
    final storage = MachineNumberStorageService(deviceChannel: channel);
    expect(await storage.getMachineNumber(), 'ANDROID-A1B2C3D4E5F60718');
  });

  test('sign-in sends the IMEI and the previous number', () {
    final body =
        LoginRequest(
          email: 'a@b.c',
          password: 'x',
          tokenId: _imei,
          deviceName: _imei,
          imei: _imei,
          legacyDeviceId: 'ANDROID-BP2A.250605.031.A3',
        ).toBody();
    expect(body['device_id'], _imei);
    expect(body['imei'], _imei);
    expect(body['legacy_device_id'], 'ANDROID-BP2A.250605.031.A3');
  });

  test('device gate errors are told in Khmer', () {
    expect(
      extractApiErrorMessage(
        ApiException(
          message:
              'This device is not registered. Please contact your administrator.',
          statusCode: 403,
          body: const {'code': 'device_not_registered'},
        ),
      ),
      deviceGateMessagesKm['device_not_registered'],
    );
    expect(
      khmerErrorMessage('Your device is waiting for administrator approval.'),
      deviceGateMessagesKm['device_pending'],
    );
    expect(
      khmerErrorMessage('Invalid credentials.', statusCode: 401),
      'អ៊ីមែល ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវ។',
    );
  });

  testWidgets('the IMEI sheet fits a narrow screen at 2x text', (tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(320, 900);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      MaterialApp(
        builder:
            (context, app) => MediaQuery(
              data: MediaQuery.of(
                context,
              ).copyWith(textScaler: const TextScaler.linear(2)),
              child: app!,
            ),
        home: Builder(
          builder:
              (context) => TextButton(
                onPressed: () => showImeiEntrySheet(context),
                child: const Text('open'),
              ),
        ),
      ),
    );
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('on iPhone the sheet points to Settings, not *#06#', (
    tester,
  ) async {
    debugDefaultTargetPlatformOverride = TargetPlatform.iOS;
    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder:
              (context) => TextButton(
                onPressed: () => showImeiEntrySheet(context),
                child: const Text('open'),
              ),
        ),
      ),
    );
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(find.text('បង្ហាញ IMEI (*#06#)'), findsNothing);
    expect(find.textContaining('ការកំណត់ (Settings)'), findsOneWidget);
    debugDefaultTargetPlatformOverride = null;
  });

  testWidgets('the IMEI sheet only saves a valid IMEI', (tester) async {
    String? saved;
    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder:
              (context) => TextButton(
                onPressed:
                    () async => saved = await showImeiEntrySheet(context),
                child: const Text('open'),
              ),
        ),
      ),
    );
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    final save = find.widgetWithText(FilledButton, 'រក្សាទុក IMEI');
    await tester.enterText(
      find.byKey(const ValueKey('imei-field')),
      '490154203237519',
    );
    await tester.pump();
    expect(
      find.text('លេខ IMEI មិនត្រឹមត្រូវ — សូមពិនិត្យម្ដងទៀត'),
      findsOneWidget,
    );
    expect(tester.widget<FilledButton>(save).onPressed, isNull);

    await tester.enterText(find.byKey(const ValueKey('imei-field')), _imei);
    await tester.pump();
    expect(tester.widget<FilledButton>(save).onPressed, isNotNull);
    await tester.tap(save);
    await tester.pumpAndSettle();
    expect(saved, _imei);
    expect(await MachineNumberStorageService().getImei(), _imei);
  });
}
