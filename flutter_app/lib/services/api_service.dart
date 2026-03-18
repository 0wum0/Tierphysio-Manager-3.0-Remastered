import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiService {
  static const _baseKey = 'api_base_url';
  static const _tokenKey = 'api_token';
  static const _storage = FlutterSecureStorage();

  static String _baseUrl = 'https://ew.makeit.uno';

  static Future<void> init() async {
    final saved = await _storage.read(key: _baseKey);
    if (saved != null && saved.isNotEmpty) _baseUrl = saved;
  }

  static Future<void> setBaseUrl(String url) async {
    _baseUrl = url.trimRight().replaceAll(RegExp(r'/$'), '');
    await _storage.write(key: _baseKey, value: _baseUrl);
  }

  static String get baseUrl => _baseUrl;

  static Future<void> saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  static Future<String?> getToken() => _storage.read(key: _tokenKey);

  static Future<void> clearToken() => _storage.delete(key: _tokenKey);

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

  /* ── Misc ── */

  Future<List<dynamic>> treatmentTypes() async =>
      List<dynamic>.from(await get('/treatment-types'));

  Future<Map<String, dynamic>> settings() async =>
      Map<String, dynamic>.from(await get('/settings'));
}

class ApiException implements Exception {
  final String message;
  final int statusCode;
  ApiException(this.message, this.statusCode);
  @override
  String toString() => message;
}
