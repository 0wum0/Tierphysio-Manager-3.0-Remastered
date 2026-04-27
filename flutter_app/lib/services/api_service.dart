import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  static const _tokenKey = 'api_token';
  static SharedPreferences? _prefs;

  // Feste Domain für alle Flutter-Clients (Windows + Android).
  static final String _baseUrl = 'https://app.therapano.de';

  static Future<void> init() async {
    _prefs = await SharedPreferences.getInstance();
  }

  @Deprecated('Die API-Domain ist fest auf https://app.therapano.de gesetzt.')
  static Future<void> setBaseUrl(String url) async {
    // Legacy no-op: Domainwechsel wird bewusst nicht mehr unterstützt.
  }

  static String get baseUrl => _baseUrl;

  /// Build an absolute media URL from a relative path returned by the backend.
  /// Handles paths like /patient-photos/5/abc.jpg, /patient-timeline/5/abc.mp4,
  /// /patients/intake_abc.jpg, and already-absolute https:// URLs.
  static String mediaUrl(String relativePath) {
    if (relativePath.startsWith('http://') ||
        relativePath.startsWith('https://')) {
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
        // Required by many Laravel installations to accept JSON responses
        // and avoid being treated as a browser (which causes HTML redirect/403).
        'X-Requested-With': 'XMLHttpRequest',
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

  static const _timeout = Duration(seconds: 30);

  /* ── Generic HTTP ── */

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) async {
    final h = await _authHeaders();
    final res = await http.get(_uri(path, query), headers: h).timeout(_timeout);
    return _parse(res);
  }

  Future<dynamic> post(String path, Map<String, dynamic> body) async {
    final h = await _authHeaders();
    final res = await http
        .post(_uri(path), headers: h, body: jsonEncode(body))
        .timeout(_timeout);
    return _parse(res);
  }

  Future<dynamic> postPublic(String path, Map<String, dynamic> body) async {
    final res = await http
        .post(_uri(path), headers: _headers(), body: jsonEncode(body))
        .timeout(_timeout);
    return _parse(res);
  }

  dynamic _parse(http.Response res) {
    final body = utf8.decode(res.bodyBytes);

    // Try JSON first
    dynamic data;
    bool isJson = false;
    try {
      data = jsonDecode(body);
      isJson = true;
    } catch (_) {
      // Not JSON – server returned HTML or plain text.
      // Still honour the HTTP status code so callers get the right error type.
    }

    if (res.statusCode >= 400) {
      String msg;
      if (isJson && data is Map) {
        final errorCode = (data['error'] ?? '').toString();
        final feature = (data['feature'] ?? '').toString();
        if (errorCode == 'feature_disabled') {
          msg =
              'Diese Funktion ist in deinem aktuellen Tarif nicht verfuegbar.';
          throw FeatureDisabledException(msg, res.statusCode, feature);
        }
        // Prefer backend's own error/message field
        msg = (data['message'] ?? data['error'] ?? 'Fehler ${res.statusCode}')
            .toString();
      } else if (!isJson && body.isNotEmpty) {
        // Strip HTML tags and truncate so the message is readable
        final stripped = body
            .replaceAll(RegExp(r'<[^>]*>'), ' ')
            .replaceAll(RegExp(r'\s+'), ' ')
            .trim();
        msg =
            stripped.length > 120 ? '${stripped.substring(0, 120)}…' : stripped;
        // If the stripped text is still HTML-garbage, fall back to a clean message
        if (msg.isEmpty || msg.startsWith('<!') || msg.startsWith('<?')) {
          msg = 'Server ${res.statusCode}: Zugang verweigert.';
        }
      } else {
        msg = 'Fehler ${res.statusCode}';
      }
      throw ApiException(msg, res.statusCode);
    }

    if (!isJson) {
      // 2xx but not JSON – unexpected, surface a clean error
      throw ApiException(
        'Server-Fehler ${res.statusCode}: Ungültige Antwort vom Server.',
        res.statusCode,
      );
    }

    return data;
  }

  /* ── Auth ── */

  Future<Map<String, dynamic>> login(
      String email, String password, String device) async {
    final data = await postPublic('/login', {
      'email': email,
      'password': password,
      'device_name': device,
    });
    return Map<String, dynamic>.from(data);
  }

  Future<void> logout() async {
    try {
      await post('/logout', {});
    } catch (_) {}
    await clearToken();
  }

  Future<Map<String, dynamic>> me() async =>
      Map<String, dynamic>.from(await get('/me'));

  /* ── Dashboard ── */

  Future<Map<String, dynamic>> dashboard() async =>
      Map<String, dynamic>.from(await get('/dashboard'));

  /* ── Patients ── */

  Future<Map<String, dynamic>> patients(
          {int page = 1, int perPage = 20, String search = ''}) async =>
      Map<String, dynamic>.from(await get('/patients', query: {
        'page': page,
        'per_page': perPage,
        if (search.isNotEmpty) 'search': search,
      }));

  Future<Map<String, dynamic>> patientShow(int id) async =>
      Map<String, dynamic>.from(await get('/patients/$id'));

  Future<Map<String, dynamic>> patientCreate(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/patients', data));

  Future<Map<String, dynamic>> patientUpdate(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/patients/$id', data));

  Future<List<dynamic>> patientTimeline(int id) async =>
      List<dynamic>.from(await get('/patients/$id/timeline'));

  Future<Map<String, dynamic>> patientTimelineCreate(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/patients/$id/timeline', data));

  Future<Map<String, dynamic>> patientTimelineCreateMultipart(
    int patientId, {
    String? title,
    String? content,
    String type = 'note',
    int? treatmentTypeId,
    String? statusBadge,
    List<File> files = const [],
  }) async {
    final token = await getToken();
    final req =
        http.MultipartRequest('POST', _uri('/patients/$patientId/timeline'))
          ..headers.addAll(
              {'Authorization': 'Bearer $token', 'Accept': 'application/json'})
          ..fields['type'] = type
          ..fields['entry_date'] =
              DateTime.now().toIso8601String().substring(0, 10);

    if (title != null) req.fields['title'] = title;
    if (content != null) req.fields['content'] = content;
    if (treatmentTypeId != null)
      req.fields['treatment_type_id'] = treatmentTypeId.toString();
    if (statusBadge != null) req.fields['status_badge'] = statusBadge;

    for (final file in files) {
      req.files.add(await http.MultipartFile.fromPath('files[]', file.path));
    }

    final res = await http.Response.fromStream(await req.send());
    return Map<String, dynamic>.from(_parse(res) as Map);
  }

  Future<Map<String, dynamic>> patientTimelineUpload(
    int patientId,
    File file, {
    required String title,
    required String type,
    String content = '',
  }) async {
    final token = await getToken();
    final uri = _uri('/patients/$patientId/timeline/upload');
    final req = http.MultipartRequest('POST', uri)
      ..headers.addAll(
          {'Authorization': 'Bearer $token', 'Accept': 'application/json'})
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

  Future<Map<String, dynamic>> owners(
          {int page = 1, int perPage = 20, String search = ''}) async =>
      Map<String, dynamic>.from(await get('/owners', query: {
        'page': page,
        'per_page': perPage,
        if (search.isNotEmpty) 'search': search,
      }));

  Future<Map<String, dynamic>> ownerShow(int id) async =>
      Map<String, dynamic>.from(await get('/owners/$id'));

  Future<Map<String, dynamic>> ownerCreate(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/owners', data));

  Future<Map<String, dynamic>> ownerUpdate(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/owners/$id', data));

  /* ── Invoices ── */

  Future<Map<String, dynamic>> invoices(
          {int page = 1,
          int perPage = 20,
          String status = '',
          String search = ''}) async =>
      Map<String, dynamic>.from(await get('/invoices', query: {
        'page': page,
        'per_page': perPage,
        if (status.isNotEmpty) 'status': status,
        if (search.isNotEmpty) 'search': search,
      }));

  Future<Map<String, dynamic>> invoiceShow(int id) async =>
      Map<String, dynamic>.from(await get('/invoices/$id'));

  Future<Map<String, dynamic>> invoiceCreate(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/invoices', data));

  /* ── Appointments ── */

  Future<List<dynamic>> appointments({String? start, String? end}) async =>
      List<dynamic>.from(await get('/appointments', query: {
        if (start != null) 'start': start,
        if (end != null) 'end': end,
      }));

  Future<Map<String, dynamic>> appointmentCreate(
          Map<String, dynamic> data) async =>
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

  Future<void> invoiceUpdateStatus(int id, String status,
          {String? reason, String? paidAt}) async =>
      await post('/invoices/$id/status', {
        'status': status,
        if (reason != null) 'cancellation_reason': reason,
        if (paidAt != null) 'paid_at': paidAt,
      });

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
      ..headers.addAll(
          {'Authorization': 'Bearer $token', 'Accept': 'application/json'})
      ..files.add(await http.MultipartFile.fromPath('photo', photo.path));
    final res = await http.Response.fromStream(await req.send());
    return Map<String, dynamic>.from(_parse(res) as Map);
  }

  Future<void> patientTimelineUpdate(
          int patientId, int entryId, Map<String, dynamic> data) async =>
      await post('/patients/$patientId/timeline/$entryId/update', data);

  /* ── Owners extended ── */

  Future<void> ownerDelete(int id) async =>
      await post('/owners/$id/loeschen', {});

  Future<List<dynamic>> ownerInvoices(int id) async =>
      List<dynamic>.from(await get('/owners/$id/rechnungen'));

  Future<List<dynamic>> ownerPatients(int id) async =>
      List<dynamic>.from(await get('/owners/$id/patienten'));

  /* ── Invoices extended ── */

  Future<Map<String, dynamic>> invoiceUpdate(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/invoices/$id/update', data));

  Future<void> invoiceDelete(int id) async =>
      await post('/invoices/$id/loeschen', {});

  Future<Map<String, dynamic>> invoicePdfUrl(int id) async =>
      Map<String, dynamic>.from(await get('/invoices/$id/pdf'));

  Future<Map<String, dynamic>> invoiceStats() async =>
      Map<String, dynamic>.from(await get('/invoices/stats'));

  Future<Map<String, dynamic>> invoiceSendEmail(int id) async =>
      Map<String, dynamic>.from(await post('/invoices/$id/senden', {}));

  Future<Map<String, dynamic>> reminderSendEmail(
          int invoiceId, int reminderId) async =>
      Map<String, dynamic>.from(await post(
          '/invoices/$invoiceId/erinnerungen/$reminderId/senden', {}));

  Future<Map<String, dynamic>> dunningSendEmail(
          int invoiceId, int dunningId) async =>
      Map<String, dynamic>.from(
          await post('/invoices/$invoiceId/mahnungen/$dunningId/senden', {}));

  /* ── Reminders ── */

  Future<List<dynamic>> remindersList() async =>
      List<dynamic>.from(await get('/erinnerungen'));

  Future<List<dynamic>> remindersForInvoice(int invoiceId) async =>
      List<dynamic>.from(await get('/invoices/$invoiceId/erinnerungen'));

  Future<Map<String, dynamic>> reminderCreate(
          int invoiceId, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/invoices/$invoiceId/erinnerungen', data));

  Future<void> reminderDelete(int invoiceId, int reminderId) async =>
      await post('/invoices/$invoiceId/erinnerungen/$reminderId/loeschen', {});

  Future<List<dynamic>> overdueAlerts() async =>
      List<dynamic>.from(await get('/ueberfaellig'));

  /* ── Dunnings ── */

  Future<List<dynamic>> dunningsList() async =>
      List<dynamic>.from(await get('/mahnungen'));

  Future<List<dynamic>> dunningsForInvoice(int invoiceId) async =>
      List<dynamic>.from(await get('/invoices/$invoiceId/mahnungen'));

  Future<Map<String, dynamic>> dunningCreate(
          int invoiceId, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/invoices/$invoiceId/mahnungen', data));

  Future<void> dunningDelete(int invoiceId, int dunningId) async =>
      await post('/invoices/$invoiceId/mahnungen/$dunningId/loeschen', {});

  /* ── Google Calendar Sync ── */
  // Endpoints corrected to match API docs: /google-kalender/*

  Future<Map<String, dynamic>> googleSyncStatus() async =>
      Map<String, dynamic>.from(await get('/google-kalender/status'));

  Future<Map<String, dynamic>> googleSyncPull() async =>
      Map<String, dynamic>.from(await post('/google-kalender/sync', {}));

  Future<Map<String, dynamic>> googleSyncPush() async =>
      Map<String, dynamic>.from(await post('/google-kalender/sync', {}));

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

  Future<Map<String, dynamic>> waitlistSchedule(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/warteliste/$id/einplanen', data));

  /* ── Treatment types CRUD ── */

  Future<List<dynamic>> treatmentTypes() async =>
      List<dynamic>.from(await get('/treatment-types'));

  Future<Map<String, dynamic>> treatmentTypeShow(int id) async =>
      Map<String, dynamic>.from(await get('/behandlungsarten/$id'));

  Future<Map<String, dynamic>> treatmentTypeCreate(
          Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/behandlungsarten', data));

  Future<Map<String, dynamic>> treatmentTypeUpdate(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/behandlungsarten/$id/update', data));

  Future<void> treatmentTypeDelete(int id) async =>
      await post('/behandlungsarten/$id/loeschen', {});

  /* ── Profile ── */

  Future<Map<String, dynamic>> profileGet() async =>
      Map<String, dynamic>.from(await get('/profil'));

  Future<Map<String, dynamic>> profileUpdate(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/profil', data));

  Future<void> profileChangePassword(
          {required String current,
          required String newPw,
          required String confirm}) async =>
      await post('/profil/passwort', {
        'current_password': current,
        'new_password': newPw,
        'new_password_confirmation': confirm
      });

  /* ── Search & Notifications ── */

  Future<List<dynamic>> globalSearch(String q) async {
    final raw = await get('/search', query: {'q': q});
    if (raw is List) return List<dynamic>.from(raw);
    if (raw is Map) {
      final grouped = Map<String, dynamic>.from(raw);
      final out = <Map<String, dynamic>>[];

      void addType(String type, List<dynamic> entries) {
        for (final entry in entries) {
          final row = Map<String, dynamic>.from(entry as Map);
          final id = row['id'];
          if (id == null) continue;
          final title = (row['name'] ?? row['title'] ?? '').toString();
          String subtitle = '';
          if (type == 'patient') {
            final species = (row['species'] ?? '').toString();
            subtitle =
                species.isNotEmpty ? species : (row['status'] ?? '').toString();
          } else if (type == 'owner') {
            subtitle = (row['email'] ?? row['phone'] ?? '').toString();
          } else if (type == 'invoice') {
            subtitle =
                '${row['status'] ?? ''} · ${row['issue_date'] ?? ''}'.trim();
          } else if (type == 'appointment') {
            subtitle = (row['start_at'] ?? '').toString();
          }
          out.add({
            ...row,
            'id': id is int ? id : int.tryParse(id.toString()),
            'type': type,
            'title': title.isEmpty ? '—' : title,
            'subtitle': subtitle,
          });
        }
      }

      addType('patient',
          List<dynamic>.from(grouped['patients'] as List? ?? const []));
      addType(
          'owner', List<dynamic>.from(grouped['owners'] as List? ?? const []));
      addType('invoice',
          List<dynamic>.from(grouped['invoices'] as List? ?? const []));
      addType('appointment',
          List<dynamic>.from(grouped['appointments'] as List? ?? const []));
      return out;
    }
    return <dynamic>[];
  }

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

  Future<Map<String, dynamic>> intakeInbox(
          {int page = 1, int perPage = 50}) async =>
      Map<String, dynamic>.from(await get('/anmeldung', query: {
        'page': page,
        'per_page': perPage,
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

  Future<Map<String, dynamic>> inviteList(
          {int page = 1, int perPage = 50}) async =>
      Map<String, dynamic>.from(await get('/einladungen', query: {
        'page': page,
        'per_page': perPage,
      }));

  Future<Map<String, dynamic>> inviteSend(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/einladungen', data));

  Future<void> inviteRevoke(int id) async =>
      await post('/einladungen/$id/widerrufen', {});

  Future<Map<String, dynamic>> inviteUpdate(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/einladungen/$id/bearbeiten', data));

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

  /* ── Therapy Care Pro (TCP) ── */

  Future<Map<String, dynamic>> tcpProgress(int patientId) async =>
      Map<String, dynamic>.from(await get('/tcp/$patientId/progress'));

  Future<Map<String, dynamic>> tcpSave(
          int patientId, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/tcp/$patientId/save', data));

  Future<List<dynamic>> tcpNatural(int patientId) async =>
      List<dynamic>.from(await get('/tcp/$patientId/natural'));

  Future<List<dynamic>> tcpReports(int patientId) async =>
      List<dynamic>.from(await get('/tcp/$patientId/reports'));

  /* ── Portal Messaging (Patient Context) ── */

  Future<List<dynamic>> portalThreadsByPatient(int patientId) async =>
      List<dynamic>.from(await get('/patients/$patientId/portal-threads'));

  /* ── Invoices extended ── */

  Future<Map<String, dynamic>> invoiceStorno(int id, String reason) async =>
      Map<String, dynamic>.from(
          await post('/rechnungen/$id/storno', {'reason': reason}));

  Future<void> portalUserDelete(int id) async =>
      await post('/portal-admin/benutzer/$id/loeschen', {});

  Future<Map<String, dynamic>> portalOwnerOverview(int ownerId) async =>
      Map<String, dynamic>.from(
          await get('/portal-admin/besitzer/$ownerId/uebersicht'));

  /* ── Patient Homework (Hausaufgaben per Patient) ── */

  Future<List<dynamic>> patientHomeworkList(int patientId) async =>
      List<dynamic>.from(
          await get('/patients/$patientId/hausaufgaben') as List);

  Future<Map<String, dynamic>> patientHomeworkShow(int id) async =>
      Map<String, dynamic>.from(await get('/hausaufgaben/$id'));

  /* ── Exercises (per patient) ── */

  Future<List<dynamic>> exercisesList(int patientId) async =>
      List<dynamic>.from(
          await get('/portal-admin/patienten/$patientId/uebungen'));

  Future<Map<String, dynamic>> exerciseCreate(
          int patientId, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/portal-admin/patienten/$patientId/uebungen', data));

  Future<Map<String, dynamic>> exerciseShow(int id) async =>
      Map<String, dynamic>.from(await get('/portal-admin/uebungen/$id'));

  Future<Map<String, dynamic>> exerciseUpdate(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/portal-admin/uebungen/$id/update', data));

  Future<void> exerciseDelete(int id) async =>
      await post('/portal-admin/uebungen/$id/loeschen', {});

  /* ── Homework Plans ── */

  Future<List<dynamic>> homeworkPlanList() async =>
      List<dynamic>.from(await get('/portal-admin/hausaufgabenplaene'));

  Future<Map<String, dynamic>> homeworkPlanCreate(
          Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/portal-admin/hausaufgabenplaene', data));

  Future<Map<String, dynamic>> homeworkPlanShow(int id) async =>
      Map<String, dynamic>.from(
          await get('/portal-admin/hausaufgabenplaene/$id'));

  Future<Map<String, dynamic>> homeworkPlanUpdate(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/portal-admin/hausaufgabenplaene/$id/update', data));

  Future<void> homeworkPlanDelete(int id) async =>
      await post('/portal-admin/hausaufgabenplaene/$id/loeschen', {});

  Future<Map<String, dynamic>> homeworkPlanPdfUrl(int id) async =>
      Map<String, dynamic>.from(
          await get('/portal-admin/hausaufgabenplaene/$id/pdf'));

  Future<Map<String, dynamic>> homeworkPlanSend(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/portal-admin/hausaufgabenplaene/$id/senden', data));

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

  /* ── Therapy Care Pro (tcp) ── */

  // Progress tracking
  Future<List<dynamic>> tcpProgressCategories() async =>
      List<dynamic>.from(await get('/tcp/fortschritt/kategorien'));

  Future<List<dynamic>> tcpProgressList(int patientId) async =>
      List<dynamic>.from(await get('/tcp/patienten/$patientId/fortschritt'));

  Future<Map<String, dynamic>> tcpProgressStore(
          int patientId, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/tcp/patienten/$patientId/fortschritt', data));

  Future<void> tcpProgressDelete(int entryId) async =>
      await post('/tcp/fortschritt/$entryId/loeschen', {});

  // Exercise feedback
  Future<List<dynamic>> tcpFeedbackList(int patientId) async {
    try {
      final raw = await get('/tcp/patienten/$patientId/feedback');
      if (raw is Map) {
        return List<dynamic>.from(raw['feedback'] as List? ?? const []);
      }
      return List<dynamic>.from(raw as List? ?? const []);
    } on FeatureDisabledException {
      return <dynamic>[];
    }
  }

  Future<List<dynamic>> tcpFeedbackProblematic() async {
    try {
      return List<dynamic>.from(await get('/tcp/feedback/problematisch'));
    } on FeatureDisabledException {
      return <dynamic>[];
    }
  }

  // Therapy reports
  Future<List<dynamic>> tcpReportList(int patientId) async =>
      List<dynamic>.from(await get('/tcp/patienten/$patientId/berichte'));

  Future<Map<String, dynamic>> tcpReportCreate(
          int patientId, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/tcp/patienten/$patientId/berichte', data));

  Future<Map<String, dynamic>> tcpReportShow(int id) async =>
      Map<String, dynamic>.from(await get('/tcp/berichte/$id'));

  Future<String> tcpReportPdfUrl(int id) async {
    final data = await get('/tcp/berichte/$id/pdf');
    return (data as Map)['pdf_url'] as String? ?? '';
  }

  Future<void> tcpReportDelete(int id) async =>
      await post('/tcp/berichte/$id/loeschen', {});

  // Exercise library
  Future<List<dynamic>> tcpLibraryList() async =>
      List<dynamic>.from(await get('/tcp/bibliothek'));

  Future<Map<String, dynamic>> tcpLibraryCreate(
          Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/tcp/bibliothek', data));

  Future<Map<String, dynamic>> tcpLibraryShow(int id) async =>
      Map<String, dynamic>.from(await get('/tcp/bibliothek/$id'));

  Future<Map<String, dynamic>> tcpLibraryUpdate(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/tcp/bibliothek/$id/update', data));

  Future<void> tcpLibraryDelete(int id) async =>
      await post('/tcp/bibliothek/$id/loeschen', {});

  // Natural therapy
  Future<List<dynamic>> tcpNaturalList(int patientId) async =>
      List<dynamic>.from(await get('/tcp/patienten/$patientId/naturheilkunde'));

  Future<Map<String, dynamic>> tcpNaturalCreate(
          int patientId, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/tcp/patienten/$patientId/naturheilkunde', data));

  Future<Map<String, dynamic>> tcpNaturalUpdate(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/tcp/naturheilkunde/$id/update', data));

  Future<void> tcpNaturalDelete(int id) async =>
      await post('/tcp/naturheilkunde/$id/loeschen', {});

  // Reminder queue (TCP)
  Future<List<dynamic>> tcpReminderTemplates() async =>
      List<dynamic>.from(await get('/tcp/erinnerungen/vorlagen'));

  Future<List<dynamic>> tcpReminderQueue(int patientId) async =>
      List<dynamic>.from(await get('/tcp/patienten/$patientId/erinnerungen'));

  Future<Map<String, dynamic>> tcpReminderQueueStore(
          int patientId, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/tcp/patienten/$patientId/erinnerungen', data));

  /* ── Tax Export Pro (Steuerexport) ── */

  Future<Map<String, dynamic>> taxExportList() async =>
      Map<String, dynamic>.from(await get('/steuerexport'));

  Future<Map<String, dynamic>> taxExportUrls() async =>
      Map<String, dynamic>.from(await get('/steuerexport/export-url'));

  Future<Map<String, dynamic>> taxExportAuditLog() async =>
      Map<String, dynamic>.from(await get('/steuerexport/audit-log'));

  Future<Map<String, dynamic>> taxExportFinalize(int id) async =>
      Map<String, dynamic>.from(
          await post('/steuerexport/$id/finalisieren', {}));

  Future<Map<String, dynamic>> taxExportCancel(int id) async =>
      Map<String, dynamic>.from(await post('/steuerexport/$id/stornieren', {}));

  /* ── Mailbox (IMAP/SMTP) ── */

  Future<Map<String, dynamic>> mailboxStatus() async =>
      Map<String, dynamic>.from(await get('/mailbox/status'));

  Future<Map<String, dynamic>> mailboxList() async =>
      Map<String, dynamic>.from(await get('/mailbox/nachrichten'));

  Future<Map<String, dynamic>> mailboxShow(String uid) async =>
      Map<String, dynamic>.from(await get('/mailbox/nachrichten/$uid'));

  Future<Map<String, dynamic>> mailboxSend(Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/mailbox/senden', data));

  Future<void> mailboxDelete(String uid) async =>
      await post('/mailbox/nachrichten/$uid/loeschen', {});

  /* ── Owner Portal (Besitzerportal) ── */

  // Auth
  Future<Map<String, dynamic>> portalLogin(
          String email, String password) async =>
      Map<String, dynamic>.from(await postPublic('/portal/login', {
        'email': email,
        'password': password,
      }));

  Future<void> portalLogout() async => await post('/portal/logout', {});

  Future<Map<String, dynamic>> portalSetPassword(
          String token, String password) async =>
      Map<String, dynamic>.from(
          await postPublic('/portal/passwort-setzen/$token', {
        'password': password,
        'password_confirmation': password,
      }));

  // Dashboard
  Future<Map<String, dynamic>> ownerPortalDashboard() async =>
      Map<String, dynamic>.from(await get('/portal/dashboard'));

  // Meine Tiere
  Future<List<dynamic>> ownerPortalPetList() async =>
      List<dynamic>.from(await get('/portal/tiere'));

  Future<Map<String, dynamic>> ownerPortalPetDetail(int id) async =>
      Map<String, dynamic>.from(await get('/portal/tiere/$id'));

  Future<Map<String, dynamic>> ownerPortalPetEdit(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/portal/tiere/$id/bearbeiten', data));

  // Rechnungen
  Future<List<dynamic>> ownerPortalInvoices() async =>
      List<dynamic>.from(await get('/portal/rechnungen'));

  Future<String> ownerPortalInvoicePdfUrl(int id) async {
    final data = await get('/portal/rechnungen/$id/pdf-url');
    return (data as Map)['pdf_url'] as String? ?? '';
  }

  // Termine
  Future<List<dynamic>> ownerPortalAppointments() async =>
      List<dynamic>.from(await get('/portal/termine'));

  // Nachrichten
  Future<int> ownerPortalUnread() async {
    final data = await get('/portal/nachrichten/ungelesen');
    return (data as Map)['unread'] as int? ?? 0;
  }

  Future<List<dynamic>> ownerPortalThreadList() async =>
      List<dynamic>.from(await get('/portal/nachrichten'));

  Future<Map<String, dynamic>> ownerPortalNewThread(
          Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(await post('/portal/nachrichten/neu', data));

  Future<Map<String, dynamic>> ownerPortalThreadShow(int id) async =>
      Map<String, dynamic>.from(await get('/portal/nachrichten/$id'));

  Future<Map<String, dynamic>> ownerPortalReply(
          int id, Map<String, dynamic> data) async =>
      Map<String, dynamic>.from(
          await post('/portal/nachrichten/$id/antworten', data));

  // Befundbögen
  Future<List<dynamic>> ownerPortalBefunde() async =>
      List<dynamic>.from(await get('/portal/befunde'));

  Future<String> ownerPortalBefundPdfUrl(int id) async {
    final data = await get('/portal/befunde/$id/pdf-url');
    return (data as Map)['pdf_url'] as String? ?? '';
  }

  // Profil
  Future<Map<String, dynamic>> ownerPortalProfile() async =>
      Map<String, dynamic>.from(await get('/portal/profil'));

  Future<Map<String, dynamic>> ownerPortalChangePassword(
          String currentPassword, String newPassword) async =>
      Map<String, dynamic>.from(await post('/portal/profil/passwort', {
        'current_password': currentPassword,
        'new_password': newPassword,
        'new_password_confirmation': newPassword,
      }));

  /// Submit feedback to the SaaS platform (not the tenant backend).
  /// Uses a separate base URL pointing to app.therapano.de.
  static Future<bool> submitFeedback({
    required String message,
    required String category,
    int? rating,
    String platform = 'android',
    String? appVersion,
    String? email,
  }) async {
    try {
      final token = await getToken();
      final saasUrl = 'https://app.therapano.de/api/feedback';
      final headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        if (token != null) 'Authorization': 'Bearer $token',
      };
      final body = json.encode({
        'message': message,
        'category': category,
        if (rating != null) 'rating': rating,
        'platform': platform,
        if (appVersion != null && appVersion.isNotEmpty)
          'app_version': appVersion,
        if (email != null && email.isNotEmpty) 'email': email,
      });
      final response = await http
          .post(Uri.parse(saasUrl), headers: headers, body: body)
          .timeout(const Duration(seconds: 15));
      if (response.statusCode < 200 || response.statusCode >= 300) {
        return false;
      }
      final decoded = jsonDecode(utf8.decode(response.bodyBytes));
      return decoded is Map ? decoded['success'] == true : true;
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

class FeatureDisabledException extends ApiException {
  final String feature;
  FeatureDisabledException(super.message, super.statusCode, this.feature);
}
