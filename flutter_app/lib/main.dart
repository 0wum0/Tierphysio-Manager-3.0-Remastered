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
  runApp(const OmniPetApp());
}

class OmniPetApp extends StatefulWidget {
  const OmniPetApp({super.key});

  @override
  State<OmniPetApp> createState() => _OmniPetAppState();
}

class _OmniPetAppState extends State<OmniPetApp> {
  final _authService = AuthService();
  bool _splashDone = false;

  void _onSplashComplete() {
    setState(() => _splashDone = true);
  }

  @override
  Widget build(BuildContext context) {
    if (!_splashDone) {
      return MaterialApp(
        title: 'OmniPet',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.dark(),
        home: Scaffold(
          body: SplashScreen(
            authService: _authService,
            onComplete: _onSplashComplete,
          ),
        ),
      );
    }

    return MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: _authService),
        Provider(create: (_) => ApiService()),
      ],
      child: Builder(builder: (context) {
        final router = AppRouter(context.read<AuthService>()).router;
        return MaterialApp.router(
          title: 'OmniPet',
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light(),
          darkTheme: AppTheme.dark(),
          themeMode: ThemeMode.system,
          routerConfig: router,
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
