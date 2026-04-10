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
import '../screens/waitlist/waitlist_screen.dart';
import '../screens/dunnings/dunnings_screen.dart';
import '../screens/behandlungsarten/behandlungsarten_screen.dart';
import '../screens/profile/profile_screen.dart';
import '../screens/search/search_screen.dart';
import '../screens/portal_admin/portal_admin_screen.dart';
import '../screens/portal_admin/portal_user_detail_screen.dart';
import '../screens/portal_admin/homework_plan_detail_screen.dart';
import '../screens/intake/intake_screen.dart';
import '../screens/intake/intake_detail_screen.dart';
import '../screens/invite/invite_screen.dart';
import '../screens/homework/homework_screen.dart';
import '../screens/settings/settings_screen.dart';
import '../screens/befunde/befunde_screen.dart';
import '../screens/befunde/befund_detail_screen.dart';
import '../screens/tcp/tcp_screen.dart';
import '../screens/tcp/tcp_progress_screen.dart';
import '../screens/tcp/tcp_reports_screen.dart';
import '../screens/tcp/tcp_library_screen.dart';
import '../screens/tcp/tcp_natural_screen.dart';
import '../screens/tcp/tcp_reminders_screen.dart';
import '../screens/tcp/tcp_feedback_screen.dart';
import '../screens/tax_export/tax_export_screen.dart';
import '../screens/mailbox/mailbox_screen.dart';
import '../screens/owner_portal/owner_portal_login_screen.dart';
import '../screens/owner_portal/owner_portal_dashboard_screen.dart';
import '../screens/owner_portal/owner_portal_pets_screen.dart';
import '../screens/owner_portal/owner_portal_invoices_screen.dart';
import '../screens/owner_portal/owner_portal_appointments_screen.dart';
import '../screens/owner_portal/owner_portal_messages_screen.dart';
import '../screens/owner_portal/owner_portal_befunde_screen.dart';

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

          GoRoute(path: '/warteliste',       builder: (_, __) => const WaitlistScreen()),
          GoRoute(path: '/mahnungen',        builder: (_, __) => const DunningsScreen()),

          GoRoute(
            path: '/anmeldungen',
            builder: (_, __) => const IntakeScreen(),
            routes: [
              GoRoute(path: ':id', builder: (_, s) => IntakeDetailScreen(id: int.parse(s.pathParameters['id']!))),
            ],
          ),

          GoRoute(
            path: '/befunde',
            builder: (_, __) => const BefundeScreen(),
            routes: [
              GoRoute(path: ':id', builder: (_, s) => BefundDetailScreen(id: int.parse(s.pathParameters['id']!))),
            ],
          ),

          GoRoute(path: '/einladungen',    builder: (_, __) => const InviteScreen()),
          GoRoute(path: '/hausaufgaben',   builder: (_, __) => const HomeworkScreen()),
          GoRoute(path: '/behandlungsarten', builder: (_, __) => const BehandlungsartenScreen()),
          GoRoute(path: '/profil',           builder: (_, __) => const ProfileScreen()),
          GoRoute(path: '/einstellungen',    builder: (_, __) => const SettingsScreen()),
          GoRoute(path: '/suche',            builder: (_, __) => const SearchScreen()),
          GoRoute(path: '/tcp',              builder: (_, __) => const TcpScreen()),
          GoRoute(path: '/steuerexport',     builder: (_, __) => const TaxExportScreen()),
          GoRoute(path: '/mailbox',          builder: (_, __) => const MailboxScreen()),

          GoRoute(
            path: '/owner-portal/login',
            builder: (_, __) => const OwnerPortalLoginScreen(),
          ),
          GoRoute(
            path: '/owner-portal',
            builder: (_, __) => const OwnerPortalDashboardScreen(),
            routes: [
              GoRoute(path: 'dashboard', builder: (_, __) => const OwnerPortalDashboardScreen()),
              GoRoute(path: 'pets', builder: (_, __) => const OwnerPortalPetsScreen()),
              GoRoute(path: 'invoices', builder: (_, __) => const OwnerPortalInvoicesScreen()),
              GoRoute(path: 'appointments', builder: (_, __) => const OwnerPortalAppointmentsScreen()),
              GoRoute(path: 'messages', builder: (_, __) => const OwnerPortalMessagesScreen()),
              GoRoute(path: 'befunde', builder: (_, __) => const OwnerPortalBefundeScreen()),
            ],
          ),

          GoRoute(
            path: '/portal-admin',
            builder: (_, __) => const PortalAdminScreen(),
            routes: [
              GoRoute(
                path: 'benutzer/:id',
                builder: (_, s) => PortalUserDetailScreen(
                  id: int.parse(s.pathParameters['id']!),
                ),
              ),
              GoRoute(
                path: 'hausaufgabenplan/:id',
                builder: (_, s) => HomeworkPlanDetailScreen(
                  id: int.parse(s.pathParameters['id']!),
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
