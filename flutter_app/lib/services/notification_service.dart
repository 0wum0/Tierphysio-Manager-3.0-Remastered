import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:timezone/timezone.dart' as tz;
import 'package:timezone/data/latest_all.dart' as tz;
import 'api_service.dart';

// ── Notification IDs ─────────────────────────────────────────────────────────
class _NID {
  static const intake     = 1000;
  static const birthday   = 2000;
  static const appointment = 3000;
  static const message    = 4000;
  static const invite     = 5000;
  static const feedback   = 6000;
}

// ── Navigation callback (set from main.dart/shell) ──────────────────────────
typedef NotifTapCallback = void Function(String route);

// ── NotificationService ──────────────────────────────────────────────────────
class NotificationService {
  static final _plugin      = FlutterLocalNotificationsPlugin();
  static bool  _initialized = false;

  /// Called when user taps a notification — navigate to given route
  static NotifTapCallback? onTap;

  // ── Pref keys ─────────────────────────────────────────────────────────────
  static const _kIntakeCount   = 'notif_intake_count';
  static const _kBirthSent     = 'notif_birthday_sent';
  static const _kAptSent       = 'notif_apt_sent';
  static const _kMsgUnread     = 'notif_msg_unread';
  static const _kInviteCount   = 'notif_invite_count';
  static const _kFeedbackCount = 'notif_feedback_count';

  // ── Channels ──────────────────────────────────────────────────────────────
  static final _chIntake = AndroidNotificationChannel(
    'therapano_intakes',
    'Neue Anmeldungen',
    description: 'Benachrichtigungen bei neuen Tierhalter-Anmeldungen',
    importance: Importance.max,
    playSound: true,
    enableVibration: true,
    vibrationPattern: Int64List.fromList([0, 250, 100, 250]),
  );

  static const _chBirthday = AndroidNotificationChannel(
    'therapano_birthdays',
    'Geburtstage',
    description: 'Geburtstags-Erinnerungen für Tiere',
    importance: Importance.high,
    playSound: true,
    enableVibration: true,
  );

  static final _chAppointment = AndroidNotificationChannel(
    'therapano_appointments',
    'Termine',
    description: 'Erinnerungen für bevorstehende Termine',
    importance: Importance.max,
    playSound: true,
    enableVibration: true,
    vibrationPattern: Int64List.fromList([0, 200, 100, 200]),
  );

  static final _chMessage = AndroidNotificationChannel(
    'therapano_messages',
    'Neue Nachrichten',
    description: 'Benachrichtigungen bei neuen Portal-Nachrichten',
    importance: Importance.max,
    playSound: true,
    enableVibration: true,
    vibrationPattern: Int64List.fromList([0, 150, 80, 150]),
  );

  static const _chInvite = AndroidNotificationChannel(
    'therapano_invites',
    'Einladungen',
    description: 'Benachrichtigungen bei neuen Einladungen',
    importance: Importance.high,
    playSound: true,
    enableVibration: true,
  );

  static const _chFeedback = AndroidNotificationChannel(
    'therapano_feedback',
    'Portal-Feedback',
    description: 'Feedback vom Besitzerportal',
    importance: Importance.high,
    playSound: true,
    enableVibration: true,
  );

  // ── Init ──────────────────────────────────────────────────────────────────
  static Future<void> init() async {
    if (_initialized) return;
    _initialized = true;

    tz.initializeTimeZones();
    try { tz.setLocalLocation(tz.getLocation('Europe/Berlin')); } catch (_) {}

    const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const initSettings    = InitializationSettings(android: androidSettings);

    await _plugin.initialize(
      initSettings,
      onDidReceiveNotificationResponse:         _onNotifTap,
      onDidReceiveBackgroundNotificationResponse: _onNotifTap,
    );

    final android = _plugin.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    for (final ch in [_chIntake, _chBirthday, _chAppointment, _chMessage, _chInvite, _chFeedback]) {
      await android?.createNotificationChannel(ch);
    }
  }

  // ── Permission ────────────────────────────────────────────────────────────
  static Future<void> requestPermission() async {
    final android = _plugin.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    await android?.requestNotificationsPermission();
  }

  // ── Tap handler ───────────────────────────────────────────────────────────
  @pragma('vm:entry-point')
  static void _onNotifTap(NotificationResponse r) {
    final payload = r.payload ?? '';
    if (payload.isNotEmpty && onTap != null) {
      onTap!(payload);
    }
  }

  // ── Main check (called from shell every 5 min) ────────────────────────────
  static Future<void> checkNow(
      Map<String, dynamic> dashboardData, ApiService api) async {
    final prefs = await SharedPreferences.getInstance();
    await _checkIntakes(dashboardData, prefs);
    await _checkBirthdays(dashboardData, prefs);
    await _checkMessages(api, prefs);
    await _checkInvites(api, prefs);
    await _checkFeedback(api, prefs);
    await _scheduleAppointments(api, prefs);
  }

  // ── 1. Neue Anmeldungen ───────────────────────────────────────────────────
  static Future<void> _checkIntakes(
      Map<String, dynamic> d, SharedPreferences prefs) async {
    final current  = (d['new_intakes'] as num?)?.toInt() ?? 0;
    final lastSeen = prefs.getInt(_kIntakeCount) ?? current;
    if (current > lastSeen) {
      final diff = current - lastSeen;
      await _show(
        id:      _NID.intake,
        title:   '$diff neue Anmeldung${diff == 1 ? '' : 'en'}',
        body:    diff == 1
            ? 'Ein neuer Tierhalter wartet auf Bestätigung.'
            : '$diff neue Tierhalter warten auf Bestätigung.',
        channel: _chIntake,
        payload: '/anmeldungen',
        actions: [
          const AndroidNotificationAction('open_intakes', 'Jetzt prüfen',
              showsUserInterface: true, cancelNotification: true),
        ],
      );
    }
    await prefs.setInt(_kIntakeCount, current);
  }

  // ── 2. Geburtstage ────────────────────────────────────────────────────────
  static Future<void> _checkBirthdays(
      Map<String, dynamic> d, SharedPreferences prefs) async {
    final birthdays = List<Map<String, dynamic>>.from(
        (d['birthdays_today'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
    if (birthdays.isEmpty) return;

    final today    = _todayKey();
    final sentJson = prefs.getString(_kBirthSent) ?? '[]';
    final sent     = List<String>.from(jsonDecode(sentJson));

    for (final b in birthdays) {
      final key  = '${today}_${b['id']}';
      if (sent.contains(key)) continue;
      final age  = (b['age']  as num?)?.toInt() ?? 0;
      final name = b['name']  as String? ?? 'Unbekannt';
      await _show(
        id:      _NID.birthday + ((b['id'] as num?)?.toInt() ?? 0),
        title:   '🎂 Geburtstag heute!',
        body:    '$name wird heute $age Jahr${age == 1 ? '' : 'e'} alt.',
        channel: _chBirthday,
        payload: '/patienten',
      );
      sent.add(key);
    }
    await prefs.setString(_kBirthSent, jsonEncode(sent));
  }

  // ── 3. Neue Nachrichten ───────────────────────────────────────────────────
  static Future<void> _checkMessages(
      ApiService api, SharedPreferences prefs) async {
    int current;
    try { current = await api.messageUnread(); } catch (_) { return; }
    final lastSeen = prefs.getInt(_kMsgUnread) ?? 0;
    if (current > lastSeen) {
      final diff = current - lastSeen;
      await _show(
        id:      _NID.message,
        title:   '$diff neue Nachricht${diff == 1 ? '' : 'en'}',
        body:    diff == 1
            ? 'Ein Besitzer hat eine neue Nachricht gesendet.'
            : '$diff neue Nachrichten von Besitzern.',
        channel: _chMessage,
        payload: '/nachrichten',
        actions: [
          const AndroidNotificationAction('open_messages', 'Öffnen',
              showsUserInterface: true, cancelNotification: true),
          const AndroidNotificationAction('mark_read', 'Als gelesen',
              cancelNotification: true),
        ],
      );
    }
    await prefs.setInt(_kMsgUnread, current);
  }

  // ── 4. Neue Einladungen ───────────────────────────────────────────────────
  static Future<void> _checkInvites(
      ApiService api, SharedPreferences prefs) async {
    int current;
    try {
      final data = await api.get('/einladungen/benachrichtigungen');
      current = (data as Map)['pending'] as int? ?? 0;
    } catch (_) { return; }
    final lastSeen = prefs.getInt(_kInviteCount) ?? current;
    if (current > lastSeen) {
      final diff = current - lastSeen;
      await _show(
        id:      _NID.invite,
        title:   '$diff neue Einladung${diff == 1 ? '' : 'en'}',
        body:    '$diff ausstehende Einladung${diff == 1 ? '' : 'en'} wartet${diff == 1 ? '' : 'en'} auf Bestätigung.',
        channel: _chInvite,
        payload: '/einladungen',
        actions: [
          const AndroidNotificationAction('open_invites', 'Anzeigen',
              showsUserInterface: true, cancelNotification: true),
        ],
      );
    }
    await prefs.setInt(_kInviteCount, current);
  }

  // ── 5. Portal-Feedback ────────────────────────────────────────────────────
  static Future<void> _checkFeedback(
      ApiService api, SharedPreferences prefs) async {
    int current;
    try {
      final data = await api.get('/portal/feedback/neu');
      current = (data as Map)['count'] as int? ?? 0;
    } catch (_) { return; }
    final lastSeen = prefs.getInt(_kFeedbackCount) ?? current;
    if (current > lastSeen) {
      final diff = current - lastSeen;
      await _show(
        id:      _NID.feedback,
        title:   'Neues Portal-Feedback',
        body:    '$diff neue${diff == 1 ? 's' : ''} Feedback${diff == 1 ? '' : 's'} von Besitzern eingegangen.',
        channel: _chFeedback,
        payload: '/portal-admin',
        actions: [
          const AndroidNotificationAction('open_feedback', 'Anzeigen',
              showsUserInterface: true, cancelNotification: true),
        ],
      );
    }
    await prefs.setInt(_kFeedbackCount, current);
  }

  // ── 6. Termin-Erinnerungen (30 min vorher) ────────────────────────────────
  static Future<void> _scheduleAppointments(
      ApiService api, SharedPreferences prefs) async {
    final now   = DateTime.now();
    final today = now.toIso8601String().substring(0, 10);

    List apts;
    try { apts = await api.appointments(start: today, end: today); }
    catch (_) { return; }

    final sentJson = prefs.getString(_kAptSent) ?? '[]';
    final sent     = List<String>.from(jsonDecode(sentJson));

    for (final a in apts) {
      final apt = Map<String, dynamic>.from(a as Map);
      final id  = (apt['id'] as num?)?.toInt() ?? 0;
      if (id == 0) continue;

      DateTime startAt;
      try { startAt = DateTime.parse(apt['start_at'] as String); }
      catch (_) { continue; }

      final reminderTime = startAt.subtract(const Duration(minutes: 30));
      final key          = 'apt_${id}_$today';
      if (sent.contains(key) || reminderTime.isBefore(now)) continue;

      final title   = apt['title']        as String? ?? 'Termin';
      final patient = apt['patient_name'] as String? ?? '';
      final body    = patient.isNotEmpty ? '$title mit $patient' : title;

      try {
        await _plugin.zonedSchedule(
          _NID.appointment + id,
          '📅 Termin in 30 Minuten',
          body,
          tz.TZDateTime.from(reminderTime, tz.local),
          NotificationDetails(
            android: AndroidNotificationDetails(
              _chAppointment.id, _chAppointment.name,
              channelDescription: _chAppointment.description,
              importance: Importance.max,
              priority: Priority.max,
              fullScreenIntent: true,
              icon: '@mipmap/ic_launcher',
              vibrationPattern: Int64List.fromList([0, 200, 100, 200]),
              enableVibration: true,
              playSound: true,
              styleInformation: BigTextStyleInformation(body),
              actions: [
                const AndroidNotificationAction('open_calendar', 'Kalender öffnen',
                    showsUserInterface: true, cancelNotification: true),
              ],
            ),
          ),
          androidScheduleMode: AndroidScheduleMode.exactAllowWhileIdle,
          uiLocalNotificationDateInterpretation:
              UILocalNotificationDateInterpretation.absoluteTime,
          payload: '/kalender',
        );
        sent.add(key);
      } catch (_) {}
    }
    await prefs.setString(_kAptSent, jsonEncode(sent));
  }

  // ── Helper: show immediate notification ───────────────────────────────────
  static Future<void> _show({
    required int    id,
    required String title,
    required String body,
    required AndroidNotificationChannel channel,
    String? payload,
    List<AndroidNotificationAction>? actions,
  }) async {
    await _plugin.show(
      id, title, body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          channel.id, channel.name,
          channelDescription: channel.description,
          importance: channel.importance,
          priority: Priority.max,
          icon: '@mipmap/ic_launcher',
          fullScreenIntent: channel.importance == Importance.max,
          enableVibration: channel.enableVibration,
          vibrationPattern: channel.vibrationPattern,
          playSound: channel.playSound,
          styleInformation: BigTextStyleInformation(
            body,
            htmlFormatBigText: false,
          ),
          actions: actions,
        ),
      ),
      payload: payload,
    );
  }

  static String _todayKey() {
    final n = DateTime.now();
    return '${n.year}-${n.month.toString().padLeft(2, '0')}-${n.day.toString().padLeft(2, '0')}';
  }
}
