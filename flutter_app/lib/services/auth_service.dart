import 'dart:io';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';

enum LoginError {
  none,
  network,
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

  SharedPreferences? _prefs;

  bool _loggedIn = false;
  Map<String, dynamic> _user = {};
  bool _initialized = false;

  bool get isLoggedIn  => _loggedIn;
  bool get initialized => _initialized;
  Map<String, dynamic> get user => _user;
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
      _user = {'name': name, 'email': email, 'role': role, 'id': id};
      _loggedIn = true;
    }
    _initialized = true;
    notifyListeners();
  }

  /// Returns a [LoginResult] with a precise error type so the UI can show the
  /// right message (network vs. wrong credentials vs. server error).
  Future<LoginResult> loginWithResult(String email, String password) async {
    try {
      final api  = ApiService();
      final data = await api.login(email.trim(), password, 'TheraPano Windows');

      // Guard: server must return a token
      final token = data['token'] as String?;
      if (token == null || token.isEmpty) {
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
      notifyListeners();
      return const LoginResult.ok();
    } on SocketException catch (e) {
      return LoginResult.fail(
        LoginError.network,
        'Netzwerkfehler: Verbindung zu app.therapano.de nicht möglich. '
        'Bitte Internetverbindung prüfen. (${e.message})',
      );
    } on ApiException catch (e) {
      // 401, 403, 422 = wrong credentials or access denied
      if (e.statusCode == 401 || e.statusCode == 403 || e.statusCode == 422) {
        // Show the backend message if meaningful, otherwise generic text
        final backendMsg = e.message;
        final isGeneric = backendMsg.contains('403') ||
            backendMsg.contains('Zugang verweigert') ||
            backendMsg.isEmpty;
        return LoginResult.fail(
          LoginError.invalidCredentials,
          isGeneric
              ? 'E-Mail oder Passwort ist falsch. Bitte erneut versuchen.'
              : backendMsg,
        );
      }
      return LoginResult.fail(
        LoginError.serverError,
        'Serverfehler (${e.statusCode}): ${e.message}',
      );
    } catch (e) {
      // Covers HandshakeException, TlsException, timeout, etc.
      final msg = e.toString();
      if (msg.contains('HandshakeException') ||
          msg.contains('tls') ||
          msg.contains('certificate')) {
        return LoginResult.fail(
          LoginError.network,
          'SSL-Fehler: Verbindung konnte nicht gesichert werden.',
        );
      }
      return LoginResult.fail(LoginError.unknown, msg);
    }
  }

  /// Legacy bool-returning wrapper, still used in some places.
  Future<bool> login(String email, String password, String serverUrl) async {
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
    _loggedIn = false;
    notifyListeners();
  }
}
