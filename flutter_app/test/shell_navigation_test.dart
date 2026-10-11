import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:therapano/core/theme.dart';
import 'package:therapano/screens/shell_screen.dart';
import 'package:therapano/services/auth_service.dart';
import 'package:therapano/services/theme_service.dart';

import 'app_redesign_test.dart' as helpers;

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(helpers.loadTestFonts);
  for (final width in [320.0, 760.0, 1200.0]) {
    testWidgets('Shell navigates without duplicated Navigator at $width', (
      tester,
    ) async {
      helpers.viewport(tester, width);
      final router = GoRouter(
        initialLocation: '/dashboard',
        routes: [
          ShellRoute(
            builder: (_, __, child) =>
                ShellScreen(backgroundTasks: false, child: child),
            routes: [
              for (final route in [
                '/dashboard',
                '/patienten',
                '/kalender',
                '/rechnungen',
              ])
                GoRoute(
                  path: route,
                  builder: (_, __) => Scaffold(body: Text(route)),
                ),
            ],
          ),
        ],
      );
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider(create: (_) => AuthService()),
            ChangeNotifierProvider(create: (_) => ThemeService()),
          ],
          child: MaterialApp.router(
            theme: AppTheme.light(),
            routerConfig: router,
            localizationsDelegates: GlobalMaterialLocalizations.delegates,
            supportedLocales: const [Locale('de', 'DE')],
            locale: const Locale('de', 'DE'),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      for (final route in [
        '/patienten',
        '/kalender',
        '/rechnungen',
        '/dashboard',
      ]) {
        router.go(route);
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 400));
        expect(tester.takeException(), isNull);
        expect(router.routeInformationProvider.value.uri.path, route);
      }
      await tester.pumpWidget(const SizedBox());
      router.dispose();
    });
  }
}
