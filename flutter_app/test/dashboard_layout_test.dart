import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:therapano/screens/dashboard_screen.dart';
import 'package:therapano/services/api_service.dart';
import 'package:therapano/services/auth_service.dart';

import 'app_redesign_test.dart' as helpers;

class DashboardAuth extends AuthService {
  @override
  String get userName => 'Mara Schneider';
}

class DashboardApi extends ApiService {
  final Future<Map<String, dynamic>>? pending;
  DashboardApi({this.pending});
  @override
  Future<Map<String, dynamic>> dashboard() async => pending != null
      ? await pending!
      : <String, dynamic>{
          'user_name': 'Mara Schneider',
          'patients_total': 128,
          'owners_total': 86,
          'today_apts': 1,
          'upcoming_apts': 12,
          'revenue_week': '1250.50',
          'revenue_month': 5480,
          'revenue_year': 48250,
          'revenue_total': 156780.50,
          'revenue_month_change': 12.5,
          'revenue_year_change': -3.2,
          'open_invoices': 4,
          'open_invoices_amount': 640,
          'overdue_invoices': 2,
          'overdue_invoices_amount': 120,
          'today_appointments': [
            {
              'id': 1,
              'patient_id': 1,
              'patient_name': 'Luna',
              'title': 'Physiotherapie',
              'owner_name': 'Lisa Weber',
              'start_at': '2026-10-07 10:00:00',
              'end_at': '2026-10-07 11:00:00',
              'status': 'confirmed',
            },
          ],
          'monthly_revenue': [
            for (var month = 5; month <= 10; month++)
              {
                'month': '2026-${month.toString().padLeft(2, '0')}',
                'revenue': 2500,
              },
          ],
        };
  @override
  Future<Map<String, dynamic>> analytics() async => {};
  @override
  Future<Map<String, dynamic>> notificationSummary() async => {};
  @override
  Future<List<dynamic>> waitlistList() async => [];
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() async {
    await helpers.loadTestFonts();
    await initializeDateFormatting('de_DE');
  });
  setUp(() => SharedPreferences.setMockInitialValues({}));
  for (final config in [
    (320.0, 1.0),
    (390.0, 1.0),
    (320.0, 1.8),
    (900.0, 1.0),
  ]) {
    testWidgets('Dashboard content fits $config', (tester) async {
      helpers.viewport(tester, config.$1);
      final key = GlobalKey();
      await tester.pumpWidget(
        helpers.host(
          RepaintBoundary(
            key: key,
            child: DashboardScreen(api: DashboardApi()),
          ),
          scale: config.$2,
          auth: DashboardAuth(),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('Mara'), findsWidgets);
      expect(find.text('128'), findsOneWidget);
      expect(find.textContaining('Luna'), findsWidgets);
      expect(tester.takeException(), isNull);
      await helpers.capture(tester, key, 'dashboard-${config.$1}-${config.$2}');
      await tester.drag(
        find.byType(SingleChildScrollView).first,
        const Offset(0, -1200),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
      await tester.pump(const Duration(seconds: 4));
    });
  }
  testWidgets('Dashboard ignores response after leaving screen', (
    tester,
  ) async {
    final pending = Completer<Map<String, dynamic>>();
    await tester.pumpWidget(
      helpers.host(DashboardScreen(api: DashboardApi(pending: pending.future))),
    );
    await tester.pump();
    await tester.pumpWidget(const SizedBox());
    pending.complete({});
    await tester.pump(const Duration(seconds: 4));
    expect(tester.takeException(), isNull);
  });
}
