import 'dart:convert';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:timezone/timezone.dart' as tz;
import 'package:timezone/data/latest_all.dart' as tz;
import 'api_service.dart';

// ── Notification IDs ────────────────────────────────────────────────────────
class _NID {
  static const newOwner    = 1000;
  static const birthday    = 2000;
  static const appointment = 3000;
}

// ── NotificationService ─────────────────────────────────────────────────────
class NotificationService {
  static final _plugin      = FlutterLocalNotificationsPlugin();
  static bool  _initialized = false;

  static const _prefKeyOwnerCount = 'notif_owner_count';
  static const _prefKeyBirth      = 'notif_birthday_sent';
  static const _prefKeyApts       = 'notif_apt_sent';

  // ── Channels ────────────────────────────────────────────────────────────
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

  // ── Init ────────────────────────────────────────────────────────────────
  static Future<void> init() async {
    if (_initialized) return;
    _initialized = true;
    tz.initializeTimeZones();
    try { tz.setLocalLocation(tz.getLocation('Europe/Berlin')); } catch (_) {}

    const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const initSettings    = InitializationSettings(android: androidSettings);
    await _plugin.initialize(initSettings,
        onDidReceiveNotificationResponse: _onTap);

    final androidPlugin = _plugin.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    await androidPlugin?.createNotificationChannel(_ownerChannel);
    await androidPlugin?.createNotificationChannel(_birthdayChannel);
    await androidPlugin?.createNotificationChannel(_appointmentChannel);
  }

  // ── Request permission (Android 13+) ────────────────────────────────────
  static Future<void> requestPermission() async {
    final android = _plugin.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    await android?.requestNotificationsPermission();
  }

  static void _onTap(NotificationResponse r) {}

  // ── Main check — call from shell every N minutes ─────────────────────
  static Future<void> checkNow(
      Map<String, dynamic> dashboardData, ApiService api) async {
    final prefs = await SharedPreferences.getInstance();
    await _checkNewOwners(dashboardData, prefs);
    await _checkBirthdays(dashboardData, prefs);
    await _scheduleUpcomingAppointments(api, prefs);
  }

  // ── New owner registrations ──────────────────────────────────────────
  static Future<void> _checkNewOwners(
      Map<String, dynamic> d, SharedPreferences prefs) async {
    final current  = (d['new_intakes'] as num?)?.toInt() ?? 0;
    final lastSeen = prefs.getInt(_prefKeyOwnerCount) ?? current;
    if (current > lastSeen) {
      final diff = current - lastSeen;
      await _show(
        id:      _NID.newOwner,
        title:   'Neue Anmeldung${diff == 1 ? '' : 'en'}',
        body:    '$diff neue${diff == 1 ? 'r Besitzer hat' : ' Besitzer haben'} sich registriert.',
        channel: _ownerChannel,
      );
    }
    await prefs.setInt(_prefKeyOwnerCount, current);
  }

  // ── Animal birthdays today ───────────────────────────────────────────
  static Future<void> _checkBirthdays(
      Map<String, dynamic> d, SharedPreferences prefs) async {
    final birthdays = List<Map<String, dynamic>>.from(
        (d['birthdays_today'] as List? ?? [])
            .map((e) => Map<String, dynamic>.from(e as Map)));
    if (birthdays.isEmpty) return;

    final today    = _todayKey();
    final sentJson = prefs.getString(_prefKeyBirth) ?? '[]';
    final sent     = List<String>.from(jsonDecode(sentJson));

    for (final b in birthdays) {
      final key = '${today}_${b['id']}';
      if (sent.contains(key)) continue;
      final age  = (b['age']  as num?)?.toInt() ?? 0;
      final name = (b['name'] as String?) ?? 'Unbekannt';
      await _show(
        id:      _NID.birthday + ((b['id'] as int?) ?? 0),
        title:   'Geburtstag heute!',
        body:    '$name wird heute $age Jahr${age == 1 ? '' : 'e'} alt.',
        channel: _birthdayChannel,
      );
      sent.add(key);
    }
    await prefs.setString(_prefKeyBirth, jsonEncode(sent));
  }

  // ── Schedule 30-min reminders for upcoming appointments ──────────────
  static Future<void> _scheduleUpcomingAppointments(
      ApiService api, SharedPreferences prefs) async {
    final now   = DateTime.now();
    final today = now.toIso8601String().substring(0, 10);

    List apts;
    try {
      apts = await api.appointments(start: today, end: today);
    } catch (_) { return; }

    final sentJson = prefs.getString(_prefKeyApts) ?? '[]';
    final sent     = List<String>.from(jsonDecode(sentJson));

    for (final a in apts) {
      final apt = Map<String, dynamic>.from(a as Map);
      final id  = (apt['id'] as num?)?.toInt() ?? 0;
      if (id == 0) continue;

      DateTime startAt;
      try { startAt = DateTime.parse(apt['start_at'] as String); } catch (_) { continue; }

      final reminderTime = startAt.subtract(const Duration(minutes: 30));
      final key          = 'apt_${id}_${today}';
      if (sent.contains(key))          continue;
      if (reminderTime.isBefore(now))  continue; // already past

      final title   = (apt['title']        as String?) ?? 'Termin';
      final patient = (apt['patient_name'] as String?) ?? '';
      final body    = patient.isNotEmpty
          ? '$title mit $patient'
          : title;

      final tzReminder = tz.TZDateTime.from(reminderTime, tz.local);
      await _plugin.zonedSchedule(
        _NID.appointment + id,
        'Termin in 30 Minuten',
        body,
        tzReminder,
        NotificationDetails(
          android: AndroidNotificationDetails(
            _appointmentChannel.id,
            _appointmentChannel.name,
            channelDescription: _appointmentChannel.description,
            importance: Importance.high,
            priority: Priority.high,
            icon: '@mipmap/ic_launcher',
            styleInformation: BigTextStyleInformation(body),
          ),
        ),
        androidScheduleMode: AndroidScheduleMode.exactAllowWhileIdle,
        uiLocalNotificationDateInterpretation:
            UILocalNotificationDateInterpretation.absoluteTime,
      );
      sent.add(key);
    }
    await prefs.setString(_prefKeyApts, jsonEncode(sent));
  }

  // ── Helper ───────────────────────────────────────────────────────────
  static Future<void> _show({
    required int id,
    required String title,
    required String body,
    required AndroidNotificationChannel channel,
  }) async {
    await _plugin.show(
      id, title, body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          channel.id, channel.name,
          channelDescription: channel.description,
          importance: channel.importance,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
          styleInformation: BigTextStyleInformation(body),
        ),
      ),
    );
  }

  static String _todayKey() {
    final n = DateTime.now();
    return '${n.month.toString().padLeft(2, '0')}-${n.day.toString().padLeft(2, '0')}';
  }
}
