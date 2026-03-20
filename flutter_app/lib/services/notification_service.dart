import 'dart:convert';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:workmanager/workmanager.dart';
import 'api_service.dart';

// ── Top-level callback required by workmanager ─────────────────────────────
@pragma('vm:entry-point')
void callbackDispatcher() {
  Workmanager().executeTask((task, inputData) async {
    try {
      await NotificationService._runBackgroundCheck(inputData ?? {});
    } catch (_) {}
    return Future.value(true);
  });
}

// ── Notification IDs ────────────────────────────────────────────────────────
class _NID {
  static const newOwner       = 1000;
  static const birthday       = 2000;
  static const appointment    = 3000; // + appointment.id
}

// ── NotificationService ─────────────────────────────────────────────────────
class NotificationService {
  static final _plugin = FlutterLocalNotificationsPlugin();
  static bool _initialized = false;

  static const _taskName     = 'therapano_poll';
  static const _prefKeyToken = 'notif_last_owner_id';
  static const _prefKeyBirth = 'notif_birthday_sent';   // comma-sep "MM-DD" values
  static const _prefKeyApts  = 'notif_apt_sent';        // comma-sep apt IDs

  // ── Init (call once from main) ──────────────────────────────────────────
  static Future<void> init() async {
    if (_initialized) return;
    _initialized = true;

    const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const initSettings = InitializationSettings(android: androidSettings);
    await _plugin.initialize(initSettings,
        onDidReceiveNotificationResponse: _onTap);

    await Workmanager().initialize(callbackDispatcher, isInDebugMode: false);
    await Workmanager().registerPeriodicTask(
      _taskName,
      _taskName,
      frequency: const Duration(minutes: 15),
      constraints: Constraints(networkType: NetworkType.connected),
      existingWorkPolicy: ExistingWorkPolicy.keep,
    );
  }

  // ── Request permission (Android 13+) ───────────────────────────────────
  static Future<void> requestPermission() async {
    final android = _plugin.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    await android?.requestNotificationsPermission();
  }

  // ── Called when user taps a notification ──────────────────────────────
  static void _onTap(NotificationResponse r) {}

  // ── Background check (runs in isolate via workmanager) ─────────────────
  static Future<void> _runBackgroundCheck(Map<String, dynamic> input) async {
    await ApiService.init();
    final token = await ApiService.getToken();
    if (token == null) return;

    final api   = ApiService();
    final prefs = await SharedPreferences.getInstance();

    try {
      final d = await api.dashboard();
      await _checkNewOwners(d, prefs);
      await _checkBirthdays(d, prefs);
      await _checkUpcomingAppointments(api, prefs);
    } catch (_) {}
  }

  // ── Foreground check (call when app opens / from shell) ────────────────
  static Future<void> checkNow() async {
    final prefs = await SharedPreferences.getInstance();
    final api   = ApiService();
    try {
      final d = await api.dashboard();
      await _checkNewOwners(d, prefs);
      await _checkBirthdays(d, prefs);
      await _checkUpcomingAppointments(api, prefs);
    } catch (_) {}
  }

  // ── New owner registrations ────────────────────────────────────────────
  static Future<void> _checkNewOwners(
      Map<String, dynamic> d, SharedPreferences prefs) async {
    final newIntakes = (d['new_intakes'] as num?)?.toInt() ?? 0;
    final lastSent   = prefs.getInt(_prefKeyToken) ?? 0;
    if (newIntakes > lastSent) {
      final diff = newIntakes - lastSent;
      await _show(
        id:      _NID.newOwner,
        title:   'Neue Anmeldung${diff == 1 ? '' : 'en'}',
        body:    '$diff neue${diff == 1 ? 'r Besitzer hat' : ' Besitzer haben'} sich registriert.',
        channel: _ownerChannel,
      );
      await prefs.setInt(_prefKeyToken, newIntakes);
    }
  }

  // ── Animal birthdays today ─────────────────────────────────────────────
  static Future<void> _checkBirthdays(
      Map<String, dynamic> d, SharedPreferences prefs) async {
    final birthdays = List<Map<String, dynamic>>.from(
        (d['birthdays_today'] as List? ?? [])
            .map((e) => Map<String, dynamic>.from(e as Map)));
    if (birthdays.isEmpty) return;

    final today     = _todayKey();
    final sentJson  = prefs.getString(_prefKeyBirth) ?? '[]';
    final sentList  = List<String>.from(jsonDecode(sentJson));

    for (final b in birthdays) {
      final key = '${today}_${b['id']}';
      if (sentList.contains(key)) continue;
      final age  = b['age']  as int?  ?? 0;
      final name = b['name'] as String? ?? 'Unbekannt';
      await _show(
        id:      _NID.birthday + ((b['id'] as int?) ?? 0),
        title:   '🎂 Geburtstag heute!',
        body:    '$name wird heute $age Jahr${age == 1 ? '' : 'e'} alt.',
        channel: _birthdayChannel,
      );
      sentList.add(key);
    }
    await prefs.setString(_prefKeyBirth, jsonEncode(sentList));
  }

  // ── Upcoming appointments (30 min warning) ────────────────────────────
  static Future<void> _checkUpcomingAppointments(
      ApiService api, SharedPreferences prefs) async {
    final now    = DateTime.now();
    final start  = now.toIso8601String().substring(0, 10);
    final end    = now.add(const Duration(hours: 2)).toIso8601String().substring(0, 10);

    final apts = await api.appointments(start: start, end: end);
    final sentJson = prefs.getString(_prefKeyApts) ?? '[]';
    final sentList = List<String>.from(jsonDecode(sentJson));

    for (final a in apts) {
      final apt = Map<String, dynamic>.from(a as Map);
      final id  = apt['id'] as int? ?? 0;
      if (id == 0) continue;

      DateTime? startAt;
      try { startAt = DateTime.parse(apt['start_at'] as String); } catch (_) { continue; }

      final diff = startAt.difference(now).inMinutes;
      if (diff < 0 || diff > 35) continue; // only 0-35 min window

      final key = 'apt_${id}_${startAt.toIso8601String().substring(0, 13)}';
      if (sentList.contains(key)) continue;

      final title   = apt['title']        as String? ?? 'Termin';
      final patient = apt['patient_name'] as String? ?? '';
      final body    = patient.isNotEmpty
          ? 'In $diff Minuten: $title mit $patient'
          : 'In $diff Minuten: $title';

      await _show(
        id:      _NID.appointment + id,
        title:   '⏰ Termin in $diff Minuten',
        body:    body,
        channel: _appointmentChannel,
      );
      sentList.add(key);
    }
    await prefs.setString(_prefKeyApts, jsonEncode(sentList));
  }

  // ── Helper: show notification ──────────────────────────────────────────
  static Future<void> _show({
    required int id,
    required String title,
    required String body,
    required AndroidNotificationChannel channel,
  }) async {
    final details = NotificationDetails(
      android: AndroidNotificationDetails(
        channel.id,
        channel.name,
        channelDescription: channel.description,
        importance: channel.importance,
        priority: Priority.high,
        icon: '@mipmap/ic_launcher',
        styleInformation: BigTextStyleInformation(body),
      ),
    );
    await _plugin.show(id, title, body, details);
  }

  // ── Channels ───────────────────────────────────────────────────────────
  static const _ownerChannel = AndroidNotificationChannel(
    'therapano_owners',
    'Neue Anmeldungen',
    description: 'Benachrichtigungen bei neuen Besitzer-Registrierungen',
    importance: Importance.high,
  );
  static const _birthdayChannel = AndroidNotificationChannel(
    'therapano_birthdays',
    'Geburtstage',
    description: 'Geburtstags-Erinnerungen für Tiere',
    importance: Importance.defaultImportance,
  );
  static const _appointmentChannel = AndroidNotificationChannel(
    'therapano_appointments',
    'Termine',
    description: 'Erinnerungen für bevorstehende Termine',
    importance: Importance.high,
  );

  static String _todayKey() {
    final n = DateTime.now();
    return '${n.month.toString().padLeft(2, '0')}-${n.day.toString().padLeft(2, '0')}';
  }
}
