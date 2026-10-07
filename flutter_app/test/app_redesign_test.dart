import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:therapano/core/navigation.dart';
import 'package:therapano/core/terminology.dart';
import 'package:therapano/core/theme.dart';
import 'package:therapano/main.dart';
import 'package:therapano/screens/login_screen.dart';
import 'package:therapano/services/auth_service.dart';
import 'package:therapano/services/offline_service.dart';
import 'package:therapano/services/owner_portal_auth_service.dart';
import 'package:therapano/services/theme_service.dart';
import 'package:therapano/widgets/module_sheet.dart';
import 'package:therapano/widgets/search_bar_widget.dart';

Future<void> loadTestFonts() async {
  final inter = FontLoader('Inter');
  for (final weight in [400, 500, 600, 700, 800]) {
    inter.addFont(rootBundle.load('assets/fonts/Inter-$weight.ttf'));
  }
  await inter.load();
  await (FontLoader(
    'MaterialIcons',
  )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
}

Widget host(
  Widget child, {
  bool dark = false,
  double scale = 1,
  AuthService? auth,
}) => ChangeNotifierProvider<AuthService>.value(
  value: auth ?? AuthService(),
  child: MaterialApp(
    debugShowCheckedModeBanner: false,
    theme: dark ? AppTheme.dark() : AppTheme.light(),
    localizationsDelegates: GlobalMaterialLocalizations.delegates,
    supportedLocales: const [Locale('de', 'DE')],
    locale: const Locale('de', 'DE'),
    builder: (context, child) => MediaQuery(
      data: MediaQuery.of(context)
          .copyWith(textScaler: TextScaler.linear(scale)),
      child: child!,
    ),
    home: child,
  ),
);

void viewport(WidgetTester tester, double width, [double height = 844]) {
  tester.view.devicePixelRatio = 1;
  tester.view.physicalSize = Size(width, height);
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Future<void> capture(WidgetTester tester, GlobalKey key, String name) async {
  final directory = Platform.environment['THERAPANO_SCREENSHOT_DIR'];
  if (directory == null) return;
  await tester.runAsync(() async {
    final boundary =
        key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 2);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    await Directory(directory).create(recursive: true);
    await File('$directory/$name.png')
        .writeAsBytes(bytes!.buffer.asUint8List());
    image.dispose();
  });
}

class ControlledAuth extends AuthService {
  int calls = 0;
  String? email;
  final response = Completer<LoginResult>();
  @override
  Future<LoginResult> loginWithResult(String email, String password) {
    calls++;
    this.email = email;
    return response.future;
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(loadTestFonts);
  setUp(() => SharedPreferences.setMockInitialValues({}));

  for (final config in [
    (320.0, 640.0, false, 1.0),
    (390.0, 844.0, false, 1.0),
    (390.0, 844.0, true, 1.0),
    (320.0, 640.0, false, 1.8),
    (1100.0, 820.0, false, 1.0),
  ]) {
    testWidgets('Login fits $config and validates empty fields', (
      tester,
    ) async {
      viewport(tester, config.$1, config.$2);
      final key = GlobalKey();
      await tester.pumpWidget(
        host(
          RepaintBoundary(key: key, child: const LoginScreen()),
          dark: config.$3,
          scale: config.$4,
        ),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await capture(
        tester,
        key,
        'login-${config.$1}-${config.$3}-${config.$4}',
      );
      await tester.ensureVisible(find.text('Anmelden'));
      await tester.tap(find.text('Anmelden'));
      await tester.pumpAndSettle();
      expect(find.text('Bitte E-Mail-Adresse eingeben.'), findsOneWidget);
      expect(find.text('Bitte Passwort eingeben.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('Login guards duplicate submits and recovers from rejection', (
    tester,
  ) async {
    viewport(tester, 390);
    final auth = ControlledAuth();
    await tester.pumpWidget(host(const LoginScreen(), auth: auth));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byType(TextFormField).first,
      ' praxis@example.org ',
    );
    await tester.enterText(find.byType(TextFormField).last, 'test-password');
    await tester.ensureVisible(find.byTooltip('Passwort anzeigen'));
    await tester.tap(find.byTooltip('Passwort anzeigen'));
    await tester.pump();
    expect(
      tester.widget<EditableText>(find.byType(EditableText).last).obscureText,
      isFalse,
    );
    final submit = tester
        .widget<FilledButton>(find.byType(FilledButton).first)
        .onPressed!;
    submit();
    submit();
    await tester.pump();
    expect(auth.calls, 1);
    expect(auth.email, 'praxis@example.org');
    auth.response.complete(
      const LoginResult.fail(
        LoginError.invalidCredentials,
        'Zugangsdaten prüfen.',
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Zugangsdaten prüfen.'), findsOneWidget);
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton).first).onPressed,
      isNotNull,
    );
    expect(tester.takeException(), isNull);
  });

  for (final trainer in [false, true]) {
    testWidgets('All modules remain searchable, trainer=$trainer', (
      tester,
    ) async {
      viewport(tester, 320, 720);
      String? selected;
      await tester.pumpWidget(
        host(
          Scaffold(
            body: ModuleSheet(
              sections: buildNavSections(
                term: Terminology(isTrainer: trainer),
                isTrainer: trainer,
              ),
              currentRoute: '/dashboard',
              onSelect: (item) => selected = item.route,
              onLogout: () {},
            ),
          ),
          scale: 1.5,
        ),
      );
      await tester.pumpAndSettle();
      final entries = trainer
          ? [
              ('Steuerexport', '/steuerexport'),
              ('Trainingspläne', '/trainingsplaene'),
              ('Portal Admin', '/portal-admin'),
            ]
          : [
              ('Steuerexport', '/steuerexport'),
              ('Therapy Care', '/tcp'),
              ('Befundbögen', '/befunde'),
            ];
      for (final entry in entries) {
        await tester.enterText(find.byType(TextField), entry.$1);
        await tester.pumpAndSettle();
        final tile = find.widgetWithText(ListTile, entry.$1);
        expect(tile, findsOneWidget);
        await tester.tap(tile);
        expect(selected, entry.$2);
        expect(tester.takeException(), isNull);
      }
      await tester.enterText(find.byType(TextField), 'zzzz');
      await tester.pumpAndSettle();
      expect(find.text('Kein passendes Modul gefunden.'), findsOneWidget);
    });
  }

  testWidgets(
    'Search debounces, accepts one letter, clears and cancels on dispose',
    (tester) async {
      final values = <String>[];
      await tester.pumpWidget(
        host(Scaffold(body: AppSearchBar(onSearch: values.add))),
      );
      await tester.enterText(find.byType(TextField), 'Bel');
      await tester.pump(const Duration(milliseconds: 150));
      await tester.enterText(find.byType(TextField), 'Bella');
      await tester.pump(const Duration(milliseconds: 300));
      expect(values, ['Bella']);
      await tester.enterText(find.byType(TextField), 'B');
      await tester.pump(const Duration(milliseconds: 300));
      expect(values, ['Bella', 'B']);
      await tester.tap(find.byTooltip('Suche leeren'));
      expect(values.last, '');
      await tester.enterText(find.byType(TextField), 'pending');
      await tester.pumpWidget(const SizedBox());
      await tester.pump(const Duration(seconds: 1));
      expect(values, ['Bella', 'B', '']);
    },
  );

  testWidgets('Theme changes preserve router, location and form input', (
    tester,
  ) async {
    viewport(tester, 390);
    final theme = ThemeService();
    await theme.init();
    await tester.pumpWidget(
      TheraPanoApp(
        themeService: theme,
        offlineService: OfflineService(),
        portalAuth: OwnerPortalAuthService(),
      ),
    );
    await tester.pumpAndSettle();
    final router =
        tester.widget<MaterialApp>(find.byType(MaterialApp)).routerConfig!
            as GoRouter;
    router.go('/login?from=patient');
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byType(TextFormField).first,
      'praxis@example.org',
    );
    await theme.setMode(ThemeMode.dark);
    await tester.pumpAndSettle();
    expect(
      tester.widget<MaterialApp>(find.byType(MaterialApp)).routerConfig,
      same(router),
    );
    expect(
      router.routeInformationProvider.value.uri.toString(),
      '/login?from=patient',
    );
    expect(find.text('praxis@example.org'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
}
