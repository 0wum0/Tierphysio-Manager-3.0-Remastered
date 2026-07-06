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
    try {
      data = jsonDecode(body);
    } catch (_) {
      data = body;
    }
    if (res.statusCode >= 200 && res.statusCode < 300) return data;
    final msg = data is Map ? (data['message'] ?? data['error'] ?? body) : body;
    throw PortalApiException(res.statusCode, msg.toString());
  }

  // ── Auth ──────────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getDashboard() async =>
      Map<String, dynamic>.from(await get('/api/mobile/portal/dashboard'));

  // ── Tiere ─────────────────────────────────────────────────────────────────

  Future<List<dynamic>> getPets() async =>
      List<dynamic>.from((await get('/api/mobile/portal/tiere'))['pets'] ?? await get('/api/mobile/portal/tiere'));

  Future<Map<String, dynamic>> getPetDetail(int id) async =>
      Map<String, dynamic>.from(await get('/api/mobile/portal/tiere/$id'));

  // ── Termine ───────────────────────────────────────────────────────────────

  Future<List<dynamic>> getAppointments() async =>
      List<dynamic>.from((await get('/api/mobile/portal/termine'))['appointments'] ??
          await get('/api/mobile/portal/termine'));

  // ── Rechnungen ────────────────────────────────────────────────────────────

  Future<List<dynamic>> getInvoices() async =>
      List<dynamic>.from((await get('/api/mobile/portal/rechnungen'))['invoices'] ??
          await get('/api/mobile/portal/rechnungen'));

  Future<String> getInvoicePdfUrl(int id) async {
    final data = await get('/api/mobile/portal/rechnungen/$id/pdf-url');
    return data['url'] as String? ?? '';
  }

  // ── Nachrichten ───────────────────────────────────────────────────────────

  Future<List<dynamic>> getThreads() async =>
      List<dynamic>.from((await get('/api/mobile/portal/nachrichten'))['threads'] ??
          await get('/api/mobile/portal/nachrichten'));

  Future<Map<String, dynamic>> getThread(int id) async =>
      Map<String, dynamic>.from(await get('/api/mobile/portal/nachrichten/$id'));

  Future<void> replyThread(int id, String body) async =>
      await post('/api/mobile/portal/nachrichten/$id/antworten', {'body': body});

  Future<void> newThread(String subject, String body) async =>
      await post('/api/mobile/portal/nachrichten/neu', {'subject': subject, 'body': body});

  // ── Befundbögen ───────────────────────────────────────────────────────────

  Future<List<dynamic>> getBefunde() async =>
      List<dynamic>.from((await get('/api/mobile/portal/befunde'))['befunde'] ??
          await get('/api/mobile/portal/befunde'));

  Future<String> getBefundPdfUrl(int id) async {
    final data = await get('/api/mobile/portal/befunde/$id/pdf-url');
    return data['url'] as String? ?? '';
  }

  // ── Hausaufgaben ──────────────────────────────────────────────────────────

  Future<List<dynamic>> getHomework() async =>
      List<dynamic>.from((await get('/api/mobile/portal/hausaufgaben'))['plans'] ??
          await get('/api/mobile/portal/hausaufgaben'));

  Future<Map<String, dynamic>> getHomeworkDetail(int id) async =>
      Map<String, dynamic>.from(await get('/api/mobile/portal/hausaufgaben/$id'));

  // ── Profil ────────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getProfile() async =>
      Map<String, dynamic>.from(await get('/api/mobile/portal/profil'));

  Future<void> changePassword(String current, String newPw, String newPwConfirm) async =>
      await post('/api/mobile/portal/profil/passwort', {
        'current_password': current,
        'password': newPw,
        'password_confirmation': newPwConfirm,
      });
}
