import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  static const _baseKey = 'api_base_url';
  static const _tokenKey = 'api_token';
  static SharedPreferences? _prefs;

  static String _baseUrl = 'https://ew.makeit.uno';

  static Future<void> init() async {
    _prefs = await SharedPreferences.getInstance();
    final saved = _prefs?.getString(_baseKey);
    if (saved != null && saved.isNotEmpty) _baseUrl = saved;
  }

  static Future<void> setBaseUrl(String url) async {
    _baseUrl = url.trimRight().replaceAll(RegExp(r'/$'), '');
    await _prefs?.setString(_baseKey, _baseUrl);
  }

  static String get baseUrl => _baseUrl;

  /// Build an absolute media URL from a relative path returned by the backend.
  /// Handles paths like /patient-photos/5/abc.jpg, /patient-timeline/5/abc.mp4,
  /// /patients/intake_abc.jpg, and already-absolute https:// URLs.
  static String mediaUrl(String relativePath) {
    if (relativePath.startsWith('http://') || relativePath.startsWith('https://')) {
      return relativePath;
    }
    final base = _baseUrl.replaceAll(RegExp(r'/$'), '');
    final path = relativePath.startsWith('/') ? relativePath : '/$relativePath';
    return '$base$path';
  }

  static Future<void> saveToken(String token) async {
    await _prefs?.setString(_tokenKey, token);
  }

  static Future<String?> getToken() async {
    return _prefs?.getString(_tokenKey);
  }

  static Future<void> clearToken() async {
    await _prefs?.remove(_tokenKey);
  }

  Map<String, String> _headers([String? token]) => {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    if (token != null) 'Authorization': 'Bearer $token',
  };

  Future<Map<String, String>> _authHeaders() async {
    final token = await getToken();
    return _headers(token);
  }

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final q = query?.map((k, v) => MapEntry(k, v.toString()));
    return Uri.parse('$_baseUrl/api/mobile$path').replace(queryParameters: q);
  }

  /* ── Generic HTTP ── */

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) async {
    final h = await _authHeaders();
    final res = await http.get(_uri(path, query), headers: h);
    return _parse(res);
  }

  Future<dynamic> post(String path, Map<String, dynamic> body) async {
    final h = await _authHeaders();
    final res = await http.post(_uri(path), headers: h, body: jsonEncode(body));
    return _parse(res);
  }

  Future<dynamic> postPublic(String path, Map<String, dynamic> body) async {
    final res = await http.post(_uri(path), headers: _headers(), body: jsonEncode(body));
    return _parse(res);
  }

  dynamic _parse(http.Response res) {
    final body = utf8.decode(res.bodyBytes);
    dynamic data;
    try {
      data = jsonDecode(body);
    } catch (_) {
      throw ApiException(
        'Server-Fehler ${res.statusCode}: Ungültige Antwort vom Server.',
        res.statusCode,
      );
    }
    if (res.statusCode >= 400) {
      throw ApiException(
        data is Map ? (data['error'] ?? 'Fehler ${res.statusCode}') : 'Fehler ${res.statusCode}',
        res.statusCode,
      );
    }
    return data;
  }

  /* ── Auth ── */

  Future<Map<String, dynamic>> login(String email, String password, String device) async {
    final data = await postPublic('/login', {
      'email': email,
      'password': password,
      'device_name': device,
    });
    return Map<String, dynamic>.from(data);
  }

  Future<void> logout() async {
    try { await post('/logout', {}); } catch (_) {}
    await clearToken();
  }

  Future<Map<String, dynamic>> me() async =>
      Map<String, dynamic>.from(await get('/me'));

  /* ── Dashboard ── */

  Future<Map<String, dynamic>> dashboard() async =>
      Map<String, dynamic>.from(await get('/dashboard'));

  /* ── Patients ── */

  Future<Map<String, dynamic>> patients({int page = 1, int perPage = 20, String search = ''}) async =>
      Map<String, dynamic>.from(await get('/patients', query: {
        'page': page, 'per_page': perPage, if (search.isNotEmpty) 'search': search,
      }));

  Future<Map<String, dynamic>> patientShow(int id) async =>
      Map<String, dynamic>.from(await get('/patients/$id'));

  Future<Map<String, dynamic>> patientCreate(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/patients', data));

  Future<Map<String, dynamic>> patientUpdate(int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/patients/$id', data));

  Future<List<dynamic>> patientTimeline(int id) async =>
      List<dynamic>.from(await get('/patients/$id/timeline'));

  Future<Map<String, dynamic>> patientTimelineCreate(int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/patients/$id/timeline', data));

  Future<Map<String, dynamic>> patientTimelineUpload(int patientId, File file, {
    required String title,
    required String type,
    String content = '',
  }) async {
    final token = await getToken();
    final uri = _uri('/patients/$patientId/timeline/upload');
    final req = http.MultipartRequest('POST', uri)
      ..headers.addAll({'Authorization': 'Bearer $token', 'Accept': 'application/json'})
      ..fields['title'] = title
      ..fields['type'] = type
      ..fields['content'] = content
      ..fields['entry_date'] = DateTime.now().toIso8601String().substring(0, 10)
      ..files.add(await http.MultipartFile.fromPath('file', file.path));
    final streamed = await req.send();
    final res = await http.Response.fromStream(streamed);
    return Map<String, dynamic>.from(_parse(res) as Map);
  }

  Future<void> patientTimelineDelete(int patientId, int entryId) async =>
      await post('/patients/$patientId/timeline/$entryId/delete', {});

  /* ── Owners ── */

  Future<Map<String, dynamic>> owners({int page = 1, int perPage = 20, String search = ''}) async =>
      Map<String, dynamic>.from(await get('/owners', query: {
        'page': page, 'per_page': perPage, if (search.isNotEmpty) 'search': search,
      }));

  Future<Map<String, dynamic>> ownerShow(int id) async =>
      Map<String, dynamic>.from(await get('/owners/$id'));

  Future<Map<String, dynamic>> ownerCreate(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/owners', data));

  Future<Map<String, dynamic>> ownerUpdate(int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/owners/$id', data));

  /* ── Invoices ── */

  Future<Map<String, dynamic>> invoices({int page = 1, int perPage = 20, String status = '', String search = ''}) async =>
      Map<String, dynamic>.from(await get('/invoices', query: {
        'page': page, 'per_page': perPage,
        if (status.isNotEmpty) 'status': status,
        if (search.isNotEmpty) 'search': search,
      }));

  Future<Map<String, dynamic>> invoiceShow(int id) async =>
      Map<String, dynamic>.from(await get('/invoices/$id'));

  Future<Map<String, dynamic>> invoiceCreate(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/invoices', data));

  Future<void> invoiceUpdateStatus(int id, String status) async =>
      await post('/invoices/$id/status', {'status': status});

  /* ── Appointments ── */

  Future<List<dynamic>> appointments({String? start, String? end}) async =>
      List<dynamic>.from(await get('/appointments', query: {
        if (start != null) 'start': start,
        if (end   != null) 'end':   end,
      }));

  Future<Map<String, dynamic>> appointmentCreate(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/appointments', data));

  Future<void> appointmentUpdate(int id, Map<String, dynamic> data) async =>
      await post('/appointments/$id', data);

  Future<void> appointmentDelete(int id) async =>
      await post('/appointments/$id/loeschen', {});

  /* ── Messaging ── */

  Future<List<dynamic>> messageThreads() async =>
      List<dynamic>.from(await get('/nachrichten'));

  Future<int> messageUnread() async {
    final data = await get('/nachrichten/ungelesen');
    return (data as Map)['unread'] as int? ?? 0;
  }

  Future<Map<String, dynamic>> messageThread(int id) async =>
      Map<String, dynamic>.from(await get('/nachrichten/$id'));

  Future<Map<String, dynamic>> messageReply(int threadId, String body) async =>
      Map<String, dynamic>.from(
          await post('/nachrichten/$threadId/antworten', {'body': body}));

  Future<Map<String, dynamic>> messageCreate({
    required int ownerId,
    required String subject,
    required String body,
  }) async =>
      Map<String, dynamic>.from(await post('/nachrichten', {
        'owner_id': ownerId,
        'subject': subject,
        'body': body,
      }));

  Future<void> messageSetStatus(int threadId, String status) async =>
      await post('/nachrichten/$threadId/status', {'status': status});

  Future<void> messageDelete(int threadId) async =>
      await post('/nachrichten/$threadId/loeschen', {});

  /* ── Patients extended ── */

  Future<void> patientDelete(int id) async =>
      await post('/patients/$id/loeschen', {});

  Future<Map<String, dynamic>> patientPhotoUpload(int id, File photo) async {
    final token = await getToken();
    final req = http.MultipartRequest('POST', _uri('/patients/$id/foto'))
      ..headers.addAll({'Authorization': 'Bearer $token', 'Accept': 'application/json'})
      ..files.add(await http.MultipartFile.fromPath('photo', photo.path));
    final res = await http.Response.fromStream(await req.send());
    return Map<String, dynamic>.from(_parse(res) as Map);
  }

  Future<void> patientTimelineUpdate(int patientId, int entryId, Map<String, dynamic> data) async =>
      await post('/patients/$patientId/timeline/$entryId/update', data);

  /* ── Owners extended ── */

  Future<void> ownerDelete(int id) async =>
      await post('/owners/$id/loeschen', {});

  Future<List<dynamic>> ownerInvoices(int id) async =>
      List<dynamic>.from(await get('/owners/$id/rechnungen'));

  Future<List<dynamic>> ownerPatients(int id) async =>
      List<dynamic>.from(await get('/owners/$id/patienten'));

  /* ── Invoices extended ── */

  Future<Map<String, dynamic>> invoiceUpdate(int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/invoices/$id/update', data));

  Future<void> invoiceDelete(int id) async =>
      await post('/invoices/$id/loeschen', {});

  Future<Map<String, dynamic>> invoicePdfUrl(int id) async =>
      Map<String, dynamic>.from(await get('/invoices/$id/pdf'));

  Future<Map<String, dynamic>> invoiceStats() async =>
      Map<String, dynamic>.from(await get('/invoices/stats'));

  Future<Map<String, dynamic>> invoiceSendEmail(int id) async =>
      Map<String, dynamic>.from(await post('/invoices/$id/senden', {}));

  Future<Map<String, dynamic>> reminderSendEmail(int invoiceId, int reminderId) async =>
      Map<String, dynamic>.from(await post('/invoices/$invoiceId/erinnerungen/$reminderId/senden', {}));

  Future<Map<String, dynamic>> dunningSendEmail(int invoiceId, int dunningId) async =>
      Map<String, dynamic>.from(await post('/invoices/$invoiceId/mahnungen/$dunningId/senden', {}));

  /* ── Reminders ── */

  Future<List<dynamic>> remindersList() async =>
      List<dynamic>.from(await get('/erinnerungen'));

  Future<List<dynamic>> remindersForInvoice(int invoiceId) async =>
      List<dynamic>.from(await get('/invoices/$invoiceId/erinnerungen'));

  Future<Map<String, dynamic>> reminderCreate(int invoiceId, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/invoices/$invoiceId/erinnerungen', data));

  Future<void> reminderDelete(int invoiceId, int reminderId) async =>
      await post('/invoices/$invoiceId/erinnerungen/$reminderId/loeschen', {});

  Future<List<dynamic>> overdueAlerts() async =>
      List<dynamic>.from(await get('/ueberfaellig'));

  /* ── Dunnings ── */

  Future<List<dynamic>> dunningsList() async =>
      List<dynamic>.from(await get('/mahnungen'));

  Future<List<dynamic>> dunningsForInvoice(int invoiceId) async =>
      List<dynamic>.from(await get('/invoices/$invoiceId/mahnungen'));

  Future<Map<String, dynamic>> dunningCreate(int invoiceId, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/invoices/$invoiceId/mahnungen', data));

  Future<void> dunningDelete(int invoiceId, int dunningId) async =>
      await post('/invoices/$invoiceId/mahnungen/$dunningId/loeschen', {});

  /* ── Google Calendar Sync ── */

  Future<Map<String, dynamic>> googleSyncStatus() async =>
      Map<String, dynamic>.from(await get('/google-sync/status'));

  Future<Map<String, dynamic>> googleSyncPull() async =>
      Map<String, dynamic>.from(await post('/google-sync/pull', {}));

  Future<Map<String, dynamic>> googleSyncPush() async =>
      Map<String, dynamic>.from(await post('/google-sync/push', {}));

  /* ── Appointments extended ── */

  Future<List<dynamic>> appointmentsToday() async =>
      List<dynamic>.from(await get('/appointments/heute'));

  Future<Map<String, dynamic>> appointmentShow(int id) async =>
      Map<String, dynamic>.from(await get('/appointments/$id'));

  Future<void> appointmentStatusUpdate(int id, String status) async =>
      await post('/appointments/$id/status', {'status': status});

  /* ── Waitlist ── */

  Future<List<dynamic>> waitlistList() async =>
      List<dynamic>.from(await get('/warteliste'));

  Future<Map<String, dynamic>> waitlistAdd(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/warteliste', data));

  Future<void> waitlistDelete(int id) async =>
      await post('/warteliste/$id/loeschen', {});

  Future<Map<String, dynamic>> waitlistSchedule(int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/warteliste/$id/einplanen', data));

  /* ── Treatment types CRUD ── */

  Future<List<dynamic>> treatmentTypes() async =>
      List<dynamic>.from(await get('/treatment-types'));

  Future<Map<String, dynamic>> treatmentTypeShow(int id) async =>
      Map<String, dynamic>.from(await get('/behandlungsarten/$id'));

  Future<Map<String, dynamic>> treatmentTypeCreate(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/behandlungsarten', data));

  Future<Map<String, dynamic>> treatmentTypeUpdate(int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/behandlungsarten/$id/update', data));

  Future<void> treatmentTypeDelete(int id) async =>
      await post('/behandlungsarten/$id/loeschen', {});

  /* ── Profile ── */

  Future<Map<String, dynamic>> profileGet() async =>
      Map<String, dynamic>.from(await get('/profil'));

  Future<Map<String, dynamic>> profileUpdate(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/profil', data));

  Future<void> profileChangePassword({required String current, required String newPw, required String confirm}) async =>
      await post('/profil/passwort', {'current_password': current, 'new_password': newPw, 'new_password_confirmation': confirm});

  /* ── Search & Notifications ── */

  Future<List<dynamic>> globalSearch(String q) async =>
      List<dynamic>.from(await get('/search', query: {'q': q}));

  Future<Map<String, dynamic>> notificationSummary() async =>
      Map<String, dynamic>.from(await get('/notifications'));

  /* ── Analytics ── */

  Future<Map<String, dynamic>> analytics() async =>
      Map<String, dynamic>.from(await get('/analytics'));

  /* ── Settings ── */

  Future<Map<String, dynamic>> settings() async =>
      Map<String, dynamic>.from(await get('/settings'));

  Future<void> settingsUpdate(Map<String, dynamic> data) async =>
      await post('/settings', data);

  /* ── Users (admin) ── */

  Future<List<dynamic>> usersList() async =>
      List<dynamic>.from(await get('/benutzer'));

  Future<Map<String, dynamic>> userShow(int id) async =>
      Map<String, dynamic>.from(await get('/benutzer/$id'));

  Future<Map<String, dynamic>> userCreate(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/benutzer', data));

  Future<void> userDeactivate(int id) async =>
      await post('/benutzer/$id/deaktivieren', {});

  /* ── Intake (Anmeldungen) ── */

  Future<Map<String, dynamic>> intakeInbox({int page = 1, int perPage = 50}) async =>
      Map<String, dynamic>.from(await get('/anmeldung', query: {
        'page': page, 'per_page': perPage,
      }));

  Future<Map<String, dynamic>> intakeShow(int id) async =>
      Map<String, dynamic>.from(await get('/anmeldung/$id'));

  Future<Map<String, dynamic>> intakeAccept(int id) async =>
      Map<String, dynamic>.from(await post('/anmeldung/$id/annehmen', {}));

  Future<Map<String, dynamic>> intakeReject(int id, {String? reason}) async =>
      Map<String, dynamic>.from(await post('/anmeldung/$id/ablehnen', {
        if (reason != null) 'reason': reason,
      }));

  /* ── Invitations (Einladungen) ── */

  Future<Map<String, dynamic>> inviteList({int page = 1, int perPage = 50}) async =>
      Map<String, dynamic>.from(await get('/einladungen', query: {
        'page': page, 'per_page': perPage,
      }));

  Future<Map<String, dynamic>> inviteSend(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/einladungen', data));

  Future<void> inviteRevoke(int id) async =>
      await post('/einladungen/$id/widerrufen', {});

  Future<Map<String, dynamic>> inviteUpdate(int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/einladungen/$id/bearbeiten', data));

  Future<Map<String, dynamic>> inviteWhatsapp(int id) async =>
      Map<String, dynamic>.from(await get('/einladungen/$id/whatsapp'));

  /* ── Portal Admin ── */

  Future<Map<String, dynamic>> portalStats() async =>
      Map<String, dynamic>.from(await get('/portal-admin/stats'));

  Future<List<dynamic>> portalUsersList() async =>
      List<dynamic>.from(await get('/portal-admin/benutzer'));

  Future<Map<String, dynamic>> portalUserShow(int id) async =>
      Map<String, dynamic>.from(await get('/portal-admin/benutzer/$id'));

  Future<Map<String, dynamic>> portalInvite(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/portal-admin/einladen', data));

  Future<void> portalResendInvite(int id) async =>
      await post('/portal-admin/benutzer/$id/neu-einladen', {});

  Future<void> portalActivate(int id) async =>
      await post('/portal-admin/benutzer/$id/aktivieren', {});

  Future<void> portalDeactivate(int id) async =>
      await post('/portal-admin/benutzer/$id/deaktivieren', {});

  Future<void> portalUserDelete(int id) async =>
      await post('/portal-admin/benutzer/$id/loeschen', {});

  Future<Map<String, dynamic>> portalOwnerOverview(int ownerId) async =>
      Map<String, dynamic>.from(await get('/portal-admin/besitzer/$ownerId/uebersicht'));

  /* ── Patient Homework (Hausaufgaben per Patient) ── */

  Future<List<dynamic>> patientHomeworkList(int patientId) async =>
      List<dynamic>.from(await get('/patients/$patientId/hausaufgaben') as List);

  Future<Map<String, dynamic>> patientHomeworkShow(int id) async =>
      Map<String, dynamic>.from(await get('/hausaufgaben/$id'));

  /* ── Exercises (per patient) ── */

  Future<List<dynamic>> exercisesList(int patientId) async =>
      List<dynamic>.from(await get('/portal-admin/patienten/$patientId/uebungen'));

  Future<Map<String, dynamic>> exerciseCreate(int patientId, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/portal-admin/patienten/$patientId/uebungen', data));

  Future<Map<String, dynamic>> exerciseShow(int id) async =>
      Map<String, dynamic>.from(await get('/portal-admin/uebungen/$id'));

  Future<Map<String, dynamic>> exerciseUpdate(int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/portal-admin/uebungen/$id/update', data));

  Future<void> exerciseDelete(int id) async =>
      await post('/portal-admin/uebungen/$id/loeschen', {});

  /* ── Homework Plans ── */

  Future<List<dynamic>> homeworkPlanList() async =>
      List<dynamic>.from(await get('/portal-admin/hausaufgabenplaene'));

  Future<Map<String, dynamic>> homeworkPlanCreate(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/portal-admin/hausaufgabenplaene', data));

  Future<Map<String, dynamic>> homeworkPlanShow(int id) async =>
      Map<String, dynamic>.from(await get('/portal-admin/hausaufgabenplaene/$id'));

  Future<Map<String, dynamic>> homeworkPlanUpdate(int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/portal-admin/hausaufgabenplaene/$id/update', data));

  Future<void> homeworkPlanDelete(int id) async =>
      await post('/portal-admin/hausaufgabenplaene/$id/loeschen', {});

  Future<Map<String, dynamic>> homeworkPlanPdfUrl(int id) async =>
      Map<String, dynamic>.from(await get('/portal-admin/hausaufgabenplaene/$id/pdf'));

  Future<Map<String, dynamic>> homeworkPlanSend(int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/portal-admin/hausaufgabenplaene/$id/senden', data));

  Future<List<dynamic>> homeworkTemplates() async =>
      List<dynamic>.from(await get('/portal-admin/vorlagen'));

  /* ── Befundbögen (admin) ── */

  Future<Map<String, dynamic>> befundeList({
    int page = 1,
    int limit = 20,
    String search = '',
    String status = '',
  }) async =>
      Map<String, dynamic>.from(await get('/befunde', query: {
        'page': page,
        'limit': limit,
        if (search.isNotEmpty) 'search': search,
        if (status.isNotEmpty) 'status': status,
      }));

  Future<List<dynamic>> befundeByPatient(int patientId) async =>
      List<dynamic>.from(await get('/befunde/patient/$patientId') as List);

  Future<Map<String, dynamic>> befundeShow(int id) async =>
      Map<String, dynamic>.from(await get('/befunde/$id'));

  Future<String> befundePdfUrl(int id) async {
    final data = await get('/befunde/$id/pdf-url');
    return (data as Map)['pdf_url'] as String? ?? '';
  }

  /* ── Befundbögen (owner portal) ── */

  Future<Map<String, dynamic>> portalBefunde() async =>
      Map<String, dynamic>.from(await get('/portal/befunde'));

  Future<String> portalBefundPdfUrl(int id) async {
    final data = await get('/portal/befunde/$id/pdf-url');
    return (data as Map)['pdf_url'] as String? ?? '';
  }

  /// Submit feedback to the SaaS platform (not the tenant backend).
  /// Uses a separate base URL pointing to app.therapano.de.
  static Future<bool> submitFeedback({
    required String message,
    required String category,
    int? rating,
    String platform = 'android',
  }) async {
    try {
      final token = await getToken();
      final saasUrl = 'https://app.therapano.de/api/feedback';
      final headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      };
      final body = json.encode({
        'message':  message,
        'category': category,
        if (rating != null) 'rating': rating,
        'platform': platform,
      });
      final response = await http
          .post(Uri.parse(saasUrl), headers: headers, body: body)
          .timeout(const Duration(seconds: 15));
      return response.statusCode == 200;
    } catch (_) {
      return false;
    }
  }
}

class ApiException implements Exception {
  final String message;
  final int statusCode;
  ApiException(this.message, this.statusCode);
  @override
  String toString() => message;
}
