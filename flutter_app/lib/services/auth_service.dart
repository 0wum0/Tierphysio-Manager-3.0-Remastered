import 'dart:async';
import 'dart:developer' as dev;
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';

enum LoginError {
  none,
  network,
  timeout,
  invalidCredentials,
  serverError,
  unknown,
}

class LoginResult {
  final bool success;
  final LoginError error;
  final String? message;

  const LoginResult.ok()
      : success = true,
        error = LoginError.none,
        message = null;

  const LoginResult.fail(this.error, [this.message]) : success = false;
}

class AuthService extends ChangeNotifier {
  static const _userNameKey  = 'user_name';
  static const _userEmailKey = 'user_email';
  static const _userRoleKey  = 'user_role';
  static const _userIdKey    = 'user_id';
  static const _practiceTypeKey = 'practice_type';

  SharedPreferences? _prefs;

  bool _loggedIn = false;
  Map<String, dynamic> _user = {};
  String _practiceType = 'therapeut';
  bool _initialized = false;

  bool get isLoggedIn  => _loggedIn;
  bool get initialized => _initialized;
  Map<String, dynamic> get user => _user;
  String get practiceType => _practiceType;
  bool get isTrainer => _practiceType == 'trainer';
  String get userName  => _user['name']  as String? ?? '';
  String get userEmail => _user['email'] as String? ?? '';
  String get userRole  => _user['role']  as String? ?? '';
  bool   get isAdmin   => userRole == 'admin';

  Future<void> init() async {
    _prefs = await SharedPreferences.getInstance();
    await ApiService.init();
    final token = await ApiService.getToken();
    if (token != null) {
      final name  = _prefs?.getString(_userNameKey)  ?? '';
      final email = _prefs?.getString(_userEmailKey) ?? '';
      final role  = _prefs?.getString(_userRoleKey)  ?? '';
      final id    = _prefs?.getString(_userIdKey)    ?? '';
      _practiceType = _prefs?.getString(_practiceTypeKey) ?? 'therapeut';
      _user = {'name': name, 'email': email, 'role': role, 'id': id};
      _loggedIn = true;
      await _refreshPracticeType();
    }
    _initialized = true;
    notifyListeners();
  }

  /// Returns a [LoginResult] with a precise error type.
  Future<LoginResult> loginWithResult(String email, String password) async {
    dev.log('[Auth] Login attempt → ${ApiService.baseUrl}/api/mobile/login',
        name: 'AuthService');
    try {
      final api  = ApiService();
      final data = await api.login(
        email.trim(),
        password,
        Platform.isAndroid ? 'TheraPano Android' : 'TheraPano Windows',
      );

      dev.log('[Auth] Login response keys: ${data.keys.toList()}',
          name: 'AuthService');

      // Guard: server must return a token
      final token = data['token'] as String?;
      if (token == null || token.isEmpty) {
        dev.log('[Auth] No token in response! Full response: $data',
            name: 'AuthService');
        return const LoginResult.fail(
          LoginError.invalidCredentials,
          'Anmeldung fehlgeschlagen: Server hat kein Token zurückgegeben. '
          'Bitte Zugangsdaten prüfen.',
        );
      }

      final user = data['user'] as Map<String, dynamic>? ?? {};

      await ApiService.saveToken(token);
      await _prefs?.setString(_userNameKey,  user['name']  as String? ?? '');
      await _prefs?.setString(_userEmailKey, user['email'] as String? ?? '');
      await _prefs?.setString(_userRoleKey,  user['role']  as String? ?? '');
      await _prefs?.setString(_userIdKey,    user['id']?.toString() ?? '');

      _user = user;
      _loggedIn = true;
      await _refreshPracticeType();
      notifyListeners();
      dev.log('[Auth] Login successful for ${user['email']}', name: 'AuthService');
      return const LoginResult.ok();

    } on SocketException catch (e) {
      dev.log('[Auth] SocketException: ${e.message}', name: 'AuthService');
      return LoginResult.fail(
        LoginError.network,
        'Netzwerkfehler: Server app.therapano.de nicht erreichbar. '
        'Bitte Internetverbindung prüfen.',
      );
    } on TimeoutException {
      dev.log('[Auth] Request timed out after 30s', name: 'AuthService');
      return const LoginResult.fail(
        LoginError.timeout,
        'Zeitüberschreitung: Server antwortet nicht. '
        'Bitte später erneut versuchen.',
      );
    } on ApiException catch (e) {
      dev.log('[Auth] ApiException ${e.statusCode}: ${e.message}',
          name: 'AuthService');

      // 401, 403, 422 = wrong credentials or access denied by server
      if (e.statusCode == 401 || e.statusCode == 403 || e.statusCode == 422) {
        final backendMsg = e.message.trim();
        // Only show backend message if it's meaningful (not generic HTML text)
        final hasUsefulMsg = backendMsg.isNotEmpty &&
            !backendMsg.contains('403') &&
            !backendMsg.contains('Zugang verweigert') &&
            !backendMsg.contains('<!') &&
            backendMsg.length < 200;
        return LoginResult.fail(
          LoginError.invalidCredentials,
          hasUsefulMsg
              ? backendMsg
              : 'E-Mail oder Passwort ist falsch. Bitte erneut versuchen.',
        );
      }
      return LoginResult.fail(
        LoginError.serverError,
        'Serverfehler (${e.statusCode}): ${e.message}',
      );
    } catch (e, st) {
      dev.log('[Auth] Unexpected error: $e', name: 'AuthService', stackTrace: st);
      final msg = e.toString();
      if (msg.contains('HandshakeException') ||
          msg.toLowerCase().contains('tls') ||
          msg.contains('certificate')) {
        return LoginResult.fail(
          LoginError.network,
          'SSL-Fehler: Sichere Verbindung konnte nicht aufgebaut werden.',
        );
      }
      return LoginResult.fail(LoginError.unknown,
          'Unbekannter Fehler: $msg');
    }
  }

  /// Legacy bool-returning wrapper, still used in some places.
  Future<bool> login(String email, String password, String _serverUrl) async {
    final result = await loginWithResult(email, password);
    return result.success;
  }

  Future<void> logout() async {
    try {
      final api = ApiService();
      await api.logout();
    } catch (_) {}
    await ApiService.clearToken();
    await _prefs?.clear();
    _user = {};
    _practiceType = 'therapeut';
    _loggedIn = false;
    notifyListeners();
  }

  Future<void> _refreshPracticeType() async {
    try {
      final api = ApiService();
      final settings = await api.settings();
      final practiceType = (settings['practice_type'] as String? ?? 'therapeut').trim();
      _practiceType = practiceType.isEmpty ? 'therapeut' : practiceType;
      await _prefs?.setString(_practiceTypeKey, _practiceType);
    } catch (_) {
      _practiceType = _prefs?.getString(_practiceTypeKey) ?? _practiceType;
    }
  }
}
