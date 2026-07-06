import 'dart:developer' as dev;
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'portal_api_service.dart';

class PortalAuthService extends ChangeNotifier {
  static const _keyToken   = 'portal_token';
  static const _keyName    = 'portal_name';
  static const _keyEmail   = 'portal_email';
  static const _keyOwnerId = 'portal_owner_id';

  SharedPreferences? _prefs;
  bool _loggedIn    = false;
  bool _initialized = false;
  String _name    = '';
  String _email   = '';
  int    _ownerId = 0;

  bool   get isLoggedIn   => _loggedIn;
  bool   get initialized  => _initialized;
  String get name         => _name;
  String get email        => _email;
  int    get ownerId      => _ownerId;
  String? get token       => _prefs?.getString(_keyToken);

  Future<void> init() async {
    _prefs = await SharedPreferences.getInstance();
    final token = _prefs!.getString(_keyToken);
    if (token != null && token.isNotEmpty) {
      _name    = _prefs!.getString(_keyName)    ?? '';
      _email   = _prefs!.getString(_keyEmail)   ?? '';
      _ownerId = int.tryParse(_prefs!.getString(_keyOwnerId) ?? '') ?? 0;
      _loggedIn = true;
    }
    _initialized = true;
    notifyListeners();
  }

  Future<LoginResult> login(String email, String password) async {
    try {
      final api  = PortalApiService();
      final data = await api.postPublic('/api/portal/mobile-login', {
        'email': email.trim(),
        'password': password,
      });
      final token = data['token'] as String?;
      if (token == null || token.isEmpty) {
        return const LoginResult.fail('Anmeldung fehlgeschlagen: Kein Token erhalten.');
      }
      final ownerName  = data['name']     as String? ?? '';
      final ownerEmail = data['email']    as String? ?? '';
      final ownerId    = data['owner_id'] is int
          ? data['owner_id'] as int
          : int.tryParse(data['owner_id']?.toString() ?? '') ?? 0;

      await _prefs!.setString(_keyToken,   token);
      await _prefs!.setString(_keyName,    ownerName);
      await _prefs!.setString(_keyEmail,   ownerEmail);
      await _prefs!.setString(_keyOwnerId, ownerId.toString());

      _name    = ownerName;
      _email   = ownerEmail;
      _ownerId = ownerId;
      _loggedIn = true;
      notifyListeners();
      dev.log('[PortalAuth] Login OK: $ownerEmail', name: 'PortalAuthService');
      return const LoginResult.ok();
    } on PortalApiException catch (e) {
      final msg = e.message.trim();
      final useful = msg.isNotEmpty && !msg.contains('<!') && msg.length < 200;
      return LoginResult.fail(useful ? msg : 'E-Mail oder Passwort ist falsch.');
    } catch (e) {
      return LoginResult.fail('Verbindungsfehler: ${e.toString()}');
    }
  }

  Future<void> logout() async {
    try {
      final token = _prefs?.getString(_keyToken);
      if (token != null) {
        await PortalApiService(token: token).post('/api/portal/mobile-logout', {});
      }
    } catch (_) {}
    await _prefs?.remove(_keyToken);
    await _prefs?.remove(_keyName);
    await _prefs?.remove(_keyEmail);
    await _prefs?.remove(_keyOwnerId);
    _name    = '';
    _email   = '';
    _ownerId = 0;
    _loggedIn = false;
    notifyListeners();
  }
}

class LoginResult {
  final bool success;
  final String? message;
  const LoginResult.ok()           : success = true,  message = null;
  const LoginResult.fail(this.message) : success = false;
}
