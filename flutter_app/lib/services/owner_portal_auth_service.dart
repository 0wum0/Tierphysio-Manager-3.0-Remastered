import 'dart:async';
import 'dart:developer' as dev;
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';

/// Auth state for the Owner/Besitzer Portal.
/// Completely separate from [AuthService] (praxis app).
class OwnerPortalAuthService extends ChangeNotifier {
  static const _tokenKey   = 'portal_token';
  static const _nameKey    = 'portal_owner_name';
  static const _emailKey   = 'portal_owner_email';
  static const _ownerIdKey = 'portal_owner_id';
  static const _practiceTypeKey = 'portal_practice_type';

  SharedPreferences? _prefs;

  bool _loggedIn     = false;
  bool _initialized  = false;
  Map<String, dynamic> _owner = {};
  String _practiceType = 'therapeut';

  bool   get isLoggedIn    => _loggedIn;
  bool   get initialized   => _initialized;
  Map<String, dynamic> get owner => _owner;
  String get ownerName     => _owner['name']  as String? ?? '';
  String get ownerEmail    => _owner['email'] as String? ?? '';
  int    get ownerId       => int.tryParse(_owner['id']?.toString() ?? '') ?? 0;
  String get practiceType  => _practiceType;
  bool   get isTrainer     => _practiceType == 'trainer';

  /// Token key — accessible by [ApiService] for Bearer header in portal calls.
  static String get tokenPrefsKey => _tokenKey;

  Future<void> init() async {
    _prefs = await SharedPreferences.getInstance();
    final token = _prefs?.getString(_tokenKey);
    if (token != null && token.isNotEmpty) {
      _owner = {
        'name':  _prefs?.getString(_nameKey)    ?? '',
        'email': _prefs?.getString(_emailKey)   ?? '',
        'id':    _prefs?.getString(_ownerIdKey) ?? '',
      };
      _practiceType = _prefs?.getString(_practiceTypeKey) ?? 'therapeut';
      _loggedIn = true;
    }
    _initialized = true;
    notifyListeners();
  }

  Future<PortalLoginResult> loginWithResult(
      String email, String password) async {
    try {
      final api  = ApiService();
      final data = await api.portalLoginRaw(email.trim(), password);

      final token = data['token'] as String?;
      if (token == null || token.isEmpty) {
        return const PortalLoginResult.fail(
            'Anmeldung fehlgeschlagen: Kein Token erhalten.');
      }

      final practiceType = (data['practice_type'] as String? ?? 'therapeut').trim();
      final ownerName    = data['name']     as String? ?? '';
      final ownerEmail   = data['email']    as String? ?? '';
      final ownerId      = data['owner_id'] as int? ?? 0;
      final ownerMap     = {'name': ownerName, 'email': ownerEmail, 'id': ownerId.toString()};

      await _prefs?.setString(_tokenKey,        token);
      await _prefs?.setString(_nameKey,         ownerName);
      await _prefs?.setString(_emailKey,        ownerEmail);
      await _prefs?.setString(_ownerIdKey,      ownerId.toString());
      await _prefs?.setString(_practiceTypeKey, practiceType.isEmpty ? 'therapeut' : practiceType);

      _owner       = ownerMap;
      _practiceType = practiceType.isEmpty ? 'therapeut' : practiceType;
      _loggedIn    = true;
      notifyListeners();
      dev.log('[PortalAuth] Login OK for $ownerEmail', name: 'OwnerPortalAuthService');
      return const PortalLoginResult.ok();
    } on ApiException catch (e) {
      dev.log('[PortalAuth] ApiException ${e.statusCode}: ${e.message}', name: 'OwnerPortalAuthService');
      if (e.statusCode == 401 || e.statusCode == 403 || e.statusCode == 422) {
        final msg = e.message.trim();
        final useful = msg.isNotEmpty && !msg.contains('<!') && msg.length < 200;
        return PortalLoginResult.fail(
            useful ? msg : 'E-Mail oder Passwort ist falsch.');
      }
      return PortalLoginResult.fail('Serverfehler (${e.statusCode}): ${e.message}');
    } catch (e) {
      dev.log('[PortalAuth] Error: $e', name: 'OwnerPortalAuthService');
      return PortalLoginResult.fail('Verbindungsfehler: ${e.toString()}');
    }
  }

  Future<void> logout() async {
    try {
      await ApiService().portalLogoutRaw();
    } catch (_) {}
    await _prefs?.remove(_tokenKey);
    await _prefs?.remove(_nameKey);
    await _prefs?.remove(_emailKey);
    await _prefs?.remove(_ownerIdKey);
    await _prefs?.remove(_practiceTypeKey);
    _owner       = {};
    _practiceType = 'therapeut';
    _loggedIn    = false;
    notifyListeners();
  }

  /// Returns the stored portal token (synchronously from prefs cache).
  String? get token => _prefs?.getString(_tokenKey);
}

class PortalLoginResult {
  final bool success;
  final String? message;

  const PortalLoginResult.ok()  : success = true,  message = null;
  const PortalLoginResult.fail(this.message) : success = false;
}
