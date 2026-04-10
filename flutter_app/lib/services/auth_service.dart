import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';

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

  Future<bool> login(String email, String password, String serverUrl) async {
    try {
      await ApiService.setBaseUrl(serverUrl);
      final api  = ApiService();
      final data = await api.login(email.trim(), password, 'Flutter App');
      final token = data['token'] as String;
      final user  = data['user']  as Map<String, dynamic>;

      await ApiService.saveToken(token);
      await _prefs?.setString(_userNameKey,  user['name']  as String? ?? '');
      await _prefs?.setString(_userEmailKey, user['email'] as String? ?? '');
      await _prefs?.setString(_userRoleKey,  user['role']  as String? ?? '');
      await _prefs?.setString(_userIdKey,    user['id'].toString());

      _user = user;
      _loggedIn = true;
      notifyListeners();
      return true;
    } catch (_) {
      return false;
    }
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
