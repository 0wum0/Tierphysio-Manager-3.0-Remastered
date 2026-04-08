import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:window_manager/window_manager.dart';
import 'core/router.dart';
import 'core/theme.dart';
import 'services/auth_service.dart';
import 'services/api_service.dart';
import 'services/notification_service.dart';
import 'services/theme_service.dart';
import 'screens/splash_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Desktop: Fenstergröße und -titel konfigurieren
  await windowManager.ensureInitialized();
  const windowOptions = WindowOptions(
    size: Size(1280, 800),
    minimumSize: Size(960, 600),
    center: true,
    title: 'TheraPano',
    titleBarStyle: TitleBarStyle.normal,
    backgroundColor: Colors.transparent,
  );
  await windowManager.waitUntilReadyToShow(windowOptions, () async {
    await windowManager.show();
    await windowManager.focus();
  });

  await ApiService.init();
  await NotificationService.init();
  final themeService = ThemeService();
  await themeService.init();

  runApp(TheraPanoApp(themeService: themeService));
}

class TheraPanoApp extends StatefulWidget {
  final ThemeService themeService;
  const TheraPanoApp({super.key, required this.themeService});

  @override
  State<TheraPanoApp> createState() => _TheraPanoAppState();
}

class _TheraPanoAppState extends State<TheraPanoApp> with WindowListener {
  final _authService = AuthService();

  @override
  void initState() {
    super.initState();
    windowManager.addListener(this);
  }

  @override
  void dispose() {
    windowManager.removeListener(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: _authService),
        ChangeNotifierProvider.value(value: widget.themeService),
        Provider(create: (_) => ApiService()),
      ],
      child: Builder(builder: (context) {
        final router = AppRouter(context.read<AuthService>()).router;
        final themeMode = context.watch<ThemeService>().mode;
        return MaterialApp.router(
          title: 'TheraPano',
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light(),
          darkTheme: AppTheme.dark(),
          themeMode: themeMode,
          routerConfig: router,
          builder: (context, child) {
            // Show splash on top until it signals completion.
            // This avoids a black frame from widget tree swap.
            return _SplashOverlay(
              authService: _authService,
              child: child ?? const SizedBox.shrink(),
            );
          },
          localizationsDelegates: const [
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          supportedLocales: const [Locale('de', 'DE')],
          locale: const Locale('de', 'DE'),
        );
      }),
    );
  }
}

// Renders the real app UNDER the splash overlay — no black frame ever.
class _SplashOverlay extends StatefulWidget {
  final AuthService authService;
  final Widget child;
  const _SplashOverlay({required this.authService, required this.child});

  @override
  State<_SplashOverlay> createState() => _SplashOverlayState();
}


class _SplashOverlayState extends State<_SplashOverlay> {
  bool _splashDone = false;

  @override
  Widget build(BuildContext context) {
    return Stack(children: [
      widget.child,
      if (!_splashDone)
        Material(
          type: MaterialType.transparency,
          child: SplashScreen(
            authService: widget.authService,
            onComplete: () => setState(() => _splashDone = true),
          ),
        ),
    ]);
  }
}
