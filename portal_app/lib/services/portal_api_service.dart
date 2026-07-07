import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;

const _baseUrl = 'https://app.therapano.de';

class PortalApiException implements Exception {
  final int statusCode;
  final String message;
  const PortalApiException(this.statusCode, this.message);
  @override
  String toString() => 'PortalApiException($statusCode): $message';
}

class PortalApiService {
  final String? token;
  const PortalApiService({this.token});

  Map<String, String> get _headers => {
    HttpHeaders.contentTypeHeader: 'application/json',
    HttpHeaders.acceptHeader: 'application/json',
    if (token != null) HttpHeaders.authorizationHeader: 'Bearer $token',
  };

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final q = query?.map((k, v) => MapEntry(k, v.toString()));
    return Uri.parse('$_baseUrl$path').replace(queryParameters: q?.isEmpty ?? true ? null : q);
  }

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) async {
    final res = await http.get(_uri(path, query), headers: _headers)
        .timeout(const Duration(seconds: 30));
    return _parse(res);
  }

  Future<dynamic> post(String path, Map<String, dynamic> body) async {
    final res = await http.post(_uri(path), headers: _headers, body: jsonEncode(body))
        .timeout(const Duration(seconds: 30));
    return _parse(res);
  }

  Future<dynamic> postPublic(String path, Map<String, dynamic> body) async {
    final res = await http.post(
      _uri(path),
      headers: {
        HttpHeaders.contentTypeHeader: 'application/json',
        HttpHeaders.acceptHeader: 'application/json',
      },
      body: jsonEncode(body),
    ).timeout(const Duration(seconds: 30));
    return _parse(res);
  }

  dynamic _parse(http.Response res) {
    if (res.statusCode == 204) return {};
    final body = utf8.decode(res.bodyBytes);
    dynamic data;
    try { data = jsonDecode(body); } catch (_) { data = body; }
    if (res.statusCode >= 200 && res.statusCode < 300) return data;
    final msg = data is Map ? (data['message'] ?? data['error'] ?? body) : body;
    throw PortalApiException(res.statusCode, msg.toString());
  }

  // ── URL builders ──────────────────────────────────────────────────────────

  static String petPhotoUrl(int petId, String filename) =>
      '$_baseUrl/api/portal/mobile/tiere/$petId/foto/${Uri.encodeComponent(filename)}';

  static String petMediaUrl(int petId, String filename) =>
      '$_baseUrl/api/portal/mobile/tiere/$petId/media/${Uri.encodeComponent(filename)}';

  Map<String, String> get authHeaders =>
      token != null ? {'Authorization': 'Bearer $token'} : {};

  // ── Dashboard ─────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getDashboard() async =>
      Map<String, dynamic>.from(await get('/api/portal/mobile/dashboard'));

  // ── Tiere ─────────────────────────────────────────────────────────────────

  Future<List<dynamic>> getPets() async {
    final data = await get('/api/portal/mobile/tiere');
    return List<dynamic>.from(data is List ? data : (data['pets'] ?? []));
  }

  Future<Map<String, dynamic>> getPetDetail(int id) async =>
      Map<String, dynamic>.from(await get('/api/portal/mobile/tiere/$id'));

  // ── Termine ───────────────────────────────────────────────────────────────

  Future<List<dynamic>> getAppointments() async {
    final data = await get('/api/portal/mobile/termine');
    return List<dynamic>.from(data is List ? data : (data['appointments'] ?? []));
  }

  Future<void> cancelAppointment(int id) async =>
      await post('/api/portal/mobile/termine/$id/stornieren', {});

  // ── Rechnungen ────────────────────────────────────────────────────────────

  Future<List<dynamic>> getInvoices() async {
    final data = await get('/api/portal/mobile/rechnungen');
    return List<dynamic>.from(data is List ? data : (data['invoices'] ?? []));
  }

  Future<String> getInvoicePdfUrl(int id) async {
    final data = await get('/api/portal/mobile/rechnungen/$id/pdf');
    return data['url'] as String? ?? '';
  }

  // ── Nachrichten ───────────────────────────────────────────────────────────

  Future<List<dynamic>> getThreads() async {
    final data = await get('/api/portal/mobile/nachrichten');
    return List<dynamic>.from(data is List ? data : (data['threads'] ?? []));
  }

  Future<Map<String, dynamic>> getThread(int id) async =>
      Map<String, dynamic>.from(await get('/api/portal/mobile/nachrichten/$id'));

  Future<void> replyThread(int id, String body) async =>
      await post('/api/portal/mobile/nachrichten/$id/antworten', {'message': body});

  Future<void> newThread(String subject, String body) async =>
      await post('/api/portal/mobile/nachrichten/neu', {'subject': subject, 'message': body});

  // ── Befundbögen ───────────────────────────────────────────────────────────

  Future<List<dynamic>> getBefunde() async {
    final data = await get('/api/portal/mobile/befunde');
    return List<dynamic>.from(data is List ? data : (data['befunde'] ?? []));
  }

  Future<String> getBefundPdfUrl(int id) async {
    final data = await get('/api/portal/mobile/befunde/$id/pdf');
    return data['url'] as String? ?? '';
  }

  // ── Hausaufgaben ──────────────────────────────────────────────────────────

  Future<List<dynamic>> getHomework() async {
    final data = await get('/api/portal/mobile/hausaufgaben');
    return List<dynamic>.from(data is List ? data : (data['plans'] ?? []));
  }

  Future<void> toggleHomeworkTask(int planId, int taskId, {required bool checked}) async =>
      await post('/api/portal/mobile/hausaufgaben/$planId/aufgabe/$taskId/abhaken',
          {'checked': checked});

  // ── Profil ────────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getProfile() async =>
      Map<String, dynamic>.from(await get('/api/portal/mobile/profil'));

  Future<void> changePassword(String current, String newPw, String newPwConfirm) async =>
      await post('/api/portal/mobile/profil/passwort', {
        'current_password': current,
        'password': newPw,
        'confirm_password': newPwConfirm,
      });
}
