import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';
import 'core/theme.dart';
import 'services/portal_auth_service.dart';
import 'screens/splash_screen.dart';
import 'screens/login_screen.dart';
import 'screens/home_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);
  runApp(const PortalApp());
}

class PortalApp extends StatefulWidget {
  const PortalApp({super.key});
  @override
  State<PortalApp> createState() => _PortalAppState();
}

class _PortalAppState extends State<PortalApp> {
  final _auth = PortalAuthService();
  bool _splashDone = false;

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider.value(
      value: _auth,
      child: MaterialApp(
        title: 'TheraPano Portal',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        darkTheme: AppTheme.dark(),
        themeMode: ThemeMode.system,
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        supportedLocales: const [Locale('de', 'DE')],
        locale: const Locale('de', 'DE'),
        home: Builder(builder: (context) {
          return Stack(children: [
            _MainContent(auth: _auth),
            if (!_splashDone)
              Material(
                type: MaterialType.transparency,
                child: SplashScreen(
                  authService: _auth,
                  onComplete: () => setState(() => _splashDone = true),
                ),
              ),
          ]);
        }),
      ),
    );
  }
}

class _MainContent extends StatelessWidget {
  final PortalAuthService auth;
  const _MainContent({required this.auth});

  @override
  Widget build(BuildContext context) {
    final isLoggedIn = context.watch<PortalAuthService>().isLoggedIn;
    return isLoggedIn ? const HomeScreen() : const LoginScreen();
  }
}
