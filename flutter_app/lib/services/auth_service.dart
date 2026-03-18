import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'api_service.dart';

class AuthService extends ChangeNotifier {
  static const _storage = FlutterSecureStorage();
  static const _userNameKey  = 'user_name';
  static const _userEmailKey = 'user_email';
  static const _userRoleKey  = 'user_role';
  static const _userIdKey    = 'user_id';

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
    await ApiService.init();
    final token = await ApiService.getToken();
    if (token != null) {
      final name  = await _storage.read(key: _userNameKey)  ?? '';
      final email = await _storage.read(key: _userEmailKey) ?? '';
      final role  = await _storage.read(key: _userRoleKey)  ?? '';
      final id    = await _storage.read(key: _userIdKey)    ?? '';
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
      await _storage.write(key: _userNameKey,  value: user['name']  as String? ?? '');
      await _storage.write(key: _userEmailKey, value: user['email'] as String? ?? '');
      await _storage.write(key: _userRoleKey,  value: user['role']  as String? ?? '');
      await _storage.write(key: _userIdKey,    value: user['id'].toString());

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
    await _storage.deleteAll();
    _user = {};
    _loggedIn = false;
    notifyListeners();
  }
}
