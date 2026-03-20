import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'core/router.dart';
import 'core/theme.dart';
import 'services/auth_service.dart';
import 'services/api_service.dart';
import 'screens/splash_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
    DeviceOrientation.landscapeLeft,
    DeviceOrientation.landscapeRight,
  ]);
  runApp(const TheraPanoApp());
}

class TheraPanoApp extends StatefulWidget {
  const TheraPanoApp({super.key});

  @override
  State<TheraPanoApp> createState() => _TheraPanoAppState();
}

class _TheraPanoAppState extends State<TheraPanoApp> {
  final _authService = AuthService();

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: _authService),
        Provider(create: (_) => ApiService()),
      ],
      child: Builder(builder: (context) {
        final router = AppRouter(context.read<AuthService>()).router;
        return MaterialApp.router(
          title: 'TheraPano',
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light(),
          darkTheme: AppTheme.dark(),
          themeMode: ThemeMode.system,
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
        SplashScreen(
          authService: widget.authService,
          onComplete: () => setState(() => _splashDone = true),
        ),
    ]);
  }
}
