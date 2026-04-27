import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:therapano/core/theme.dart';

void main() {
  testWidgets('renders themed app shell smoke test',
      (WidgetTester tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        home: const Scaffold(
          body: Text('TheraPano'),
        ),
      ),
    );

    expect(find.text('TheraPano'), findsOneWidget);
  });
}
