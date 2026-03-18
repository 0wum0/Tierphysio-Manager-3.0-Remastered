import 'package:go_router/go_router.dart';
import '../services/auth_service.dart';
import '../screens/login_screen.dart';
import '../screens/shell_screen.dart';
import '../screens/dashboard_screen.dart';
import '../screens/patients/patients_screen.dart';
import '../screens/patients/patient_detail_screen.dart';
import '../screens/patients/patient_form_screen.dart';
import '../screens/owners/owners_screen.dart';
import '../screens/owners/owner_detail_screen.dart';
import '../screens/owners/owner_form_screen.dart';
import '../screens/invoices/invoices_screen.dart';
import '../screens/invoices/invoice_detail_screen.dart';
import '../screens/invoices/invoice_form_screen.dart';
import '../screens/calendar/calendar_screen.dart';
import '../screens/messages/messages_screen.dart';
import '../screens/messages/message_thread_screen.dart';

class AppRouter {
  final AuthService authService;
  AppRouter(this.authService);

  late final router = GoRouter(
    refreshListenable: authService,
    redirect: (context, state) {
      final loggedIn = authService.isLoggedIn;
      final onLogin  = state.matchedLocation == '/login';
      if (!loggedIn && !onLogin) return '/login';
      if (loggedIn  && onLogin)  return '/dashboard';
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      ShellRoute(
        builder: (context, state, child) => ShellScreen(child: child),
        routes: [
          GoRoute(path: '/dashboard', builder: (_, __) => const DashboardScreen()),

          GoRoute(
            path: '/patienten',
            builder: (_, __) => const PatientsScreen(),
            routes: [
              GoRoute(path: 'neu',      builder: (_, __) => const PatientFormScreen()),
              GoRoute(path: ':id',      builder: (_, s)  => PatientDetailScreen(id: int.parse(s.pathParameters['id']!))),
              GoRoute(path: ':id/edit', builder: (_, s)  => PatientFormScreen(patientId: int.parse(s.pathParameters['id']!))),
            ],
          ),

          GoRoute(
            path: '/tierhalter',
            builder: (_, __) => const OwnersScreen(),
            routes: [
              GoRoute(path: 'neu',      builder: (_, __) => const OwnerFormScreen()),
              GoRoute(path: ':id',      builder: (_, s)  => OwnerDetailScreen(id: int.parse(s.pathParameters['id']!))),
              GoRoute(path: ':id/edit', builder: (_, s)  => OwnerFormScreen(ownerId: int.parse(s.pathParameters['id']!))),
            ],
          ),

          GoRoute(
            path: '/rechnungen',
            builder: (_, __) => const InvoicesScreen(),
            routes: [
              GoRoute(
                path: 'neu',
                builder: (_, s) => InvoiceFormScreen(
                  prefill: s.extra is Map<String, dynamic> ? s.extra as Map<String, dynamic> : null,
                ),
              ),
              GoRoute(path: ':id', builder: (_, s) => InvoiceDetailScreen(id: int.parse(s.pathParameters['id']!))),
            ],
          ),

          GoRoute(path: '/kalender', builder: (_, __) => const CalendarScreen()),

          GoRoute(
            path: '/nachrichten',
            builder: (_, __) => const MessagesScreen(),
            routes: [
              GoRoute(
                path: ':id',
                builder: (_, s) => MessageThreadScreen(
                  threadId: int.parse(s.pathParameters['id']!),
                  prefill: s.extra is Map<String, dynamic> ? s.extra as Map<String, dynamic> : null,
                ),
              ),
            ],
          ),
        ],
      ),
    ],
    initialLocation: '/dashboard',
  );
}
