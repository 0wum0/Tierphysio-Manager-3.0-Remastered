import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';

// ── Notification IDs (für In-App-Badges) ─────────────────────────────────────
//
// Desktop: kein flutter_local_notifications.
// Stattdessen werden Zähler in SharedPreferences gehalten und über die
// Shell-Sidebar als Badge-Icons angezeigt.
// ─────────────────────────────────────────────────────────────────────────────

typedef NotifTapCallback = void Function(String route);

class NotificationService {
  static bool _initialized = false;

  /// Wird gesetzt, wenn der Nutzer auf ein In-App-Badge tippt.
  static NotifTapCallback? onTap;

  // ── Pref keys ─────────────────────────────────────────────────────────────
  static const _kIntakeCount   = 'notif_intake_count';
  static const _kMsgUnread     = 'notif_msg_unread';
  static const _kInviteCount   = 'notif_invite_count';
  static const _kFeedbackCount = 'notif_feedback_count';

  // ── Init (no-op auf Desktop) ──────────────────────────────────────────────
  static Future<void> init() async {
    if (_initialized) return;
    _initialized = true;
    // Desktop: keine System-Notifications nötig – In-App-Badges reichen.
  }

  /// Keine Berechtigungsanfrage nötig auf Desktop.
  static Future<void> requestPermission() async {}

  // ── Hauptcheck (alle 5 Minuten aus der Shell aufgerufen) ──────────────────
  static Future<void> checkNow(
      Map<String, dynamic> dashboardData, ApiService api) async {
    final prefs = await SharedPreferences.getInstance();
    await _checkIntakes(dashboardData, prefs);
    await _checkMessages(api, prefs);
    await _checkInvites(api, prefs);
    await _checkFeedback(api, prefs);
  }

  // ── 1. Neue Anmeldungen ───────────────────────────────────────────────────
  static Future<void> _checkIntakes(
      Map<String, dynamic> d, SharedPreferences prefs) async {
    final current = (d['new_intakes'] as num?)?.toInt() ?? 0;
    await prefs.setInt(_kIntakeCount, current);
  }

  // ── 2. Nachrichten ────────────────────────────────────────────────────────
  static Future<void> _checkMessages(
      ApiService api, SharedPreferences prefs) async {
    try {
      final count = await api.messageUnread();
      await prefs.setInt(_kMsgUnread, count);
    } catch (_) {}
  }

  // ── 3. Einladungen ────────────────────────────────────────────────────────
  static Future<void> _checkInvites(
      ApiService api, SharedPreferences prefs) async {
    try {
      final data    = await api.get('/einladungen/benachrichtigungen');
      final current = (data as Map)['pending'] as int? ?? 0;
      await prefs.setInt(_kInviteCount, current);
    } catch (_) {}
  }

  // ── 4. Portal-Feedback ────────────────────────────────────────────────────
  static Future<void> _checkFeedback(
      ApiService api, SharedPreferences prefs) async {
    try {
      final data    = await api.get('/portal/feedback/neu');
      final current = (data as Map)['count'] as int? ?? 0;
      await prefs.setInt(_kFeedbackCount, current);
    } catch (_) {}
  }
}
