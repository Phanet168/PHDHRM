import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:staff_mobile_app/app.dart';
import 'package:staff_mobile_app/features/auth/controllers/auth_controller.dart';

void main() {
  testWidgets('shows login screen when there is no stored session', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      StaffMobileApp(authController: _FakeAuthController()),
    );
    await tester.pump();

    expect(find.text('PHD HRM'), findsOneWidget);
    expect(find.byType(TextFormField), findsNWidgets(2));
  });
}

class _FakeAuthController extends AuthController {
  @override
  AuthStatus get status => AuthStatus.unauthenticated;
}
