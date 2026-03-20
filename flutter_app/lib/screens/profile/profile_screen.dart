import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../core/theme.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> with SingleTickerProviderStateMixin {
  final _api = ApiService();
  late TabController _tabs;
  Map<String, dynamic>? _profile;
  bool _loading = true;

  // Profile form
  final _nameCtrl  = TextEditingController();
  final _emailCtrl = TextEditingController();
  bool _saving = false;

  // Password form
  final _curPwCtrl  = TextEditingController();
  final _newPwCtrl  = TextEditingController();
  final _confPwCtrl = TextEditingController();
  bool _pwSaving = false;
  bool _showCur = false, _showNew = false, _showConf = false;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _load();
  }

  @override
  void dispose() {
    _tabs.dispose();
    _nameCtrl.dispose(); _emailCtrl.dispose();
    _curPwCtrl.dispose(); _newPwCtrl.dispose(); _confPwCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final p = await _api.profileGet();
      setState(() {
        _profile = p;
        _nameCtrl.text  = p['name']  as String? ?? '';
        _emailCtrl.text = p['email'] as String? ?? '';
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
      _showSnack(e.toString(), error: true);
    }
  }

  Future<void> _saveProfile() async {
    if (_nameCtrl.text.trim().isEmpty || _emailCtrl.text.trim().isEmpty) {
      _showSnack('Name und E-Mail sind Pflichtfelder.', error: true); return;
    }
    setState(() => _saving = true);
    try {
      await _api.profileUpdate({'name': _nameCtrl.text.trim(), 'email': _emailCtrl.text.trim()});
      _showSnack('Profil gespeichert ✓');
      _load();
    } catch (e) { _showSnack(e.toString(), error: true); }
    finally { setState(() => _saving = false); }
  }

  Future<void> _changePassword() async {
    if (_curPwCtrl.text.isEmpty || _newPwCtrl.text.isEmpty || _confPwCtrl.text.isEmpty) {
      _showSnack('Alle Passwortfelder ausfüllen.', error: true); return;
    }
    if (_newPwCtrl.text != _confPwCtrl.text) {
      _showSnack('Neue Passwörter stimmen nicht überein.', error: true); return;
    }
    if (_newPwCtrl.text.length < 8) {
      _showSnack('Passwort muss mindestens 8 Zeichen haben.', error: true); return;
    }
    setState(() => _pwSaving = true);
    try {
      await _api.profileChangePassword(
        current: _curPwCtrl.text,
        newPw: _newPwCtrl.text,
        confirm: _confPwCtrl.text,
      );
      _showSnack('Passwort geändert ✓');
      _curPwCtrl.clear(); _newPwCtrl.clear(); _confPwCtrl.clear();
    } catch (e) { _showSnack(e.toString(), error: true); }
    finally { setState(() => _pwSaving = false); }
  }

  void _showSnack(String msg, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      backgroundColor: error ? AppTheme.danger : AppTheme.success,
    ));
  }

  @override
  Widget build(BuildContext context) {
    final p = _profile;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Mein Profil'),
        bottom: TabBar(
          controller: _tabs,
          tabs: const [
            Tab(text: 'Profil', icon: Icon(Icons.person_rounded, size: 16)),
            Tab(text: 'Passwort', icon: Icon(Icons.lock_rounded, size: 16)),
          ],
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : TabBarView(controller: _tabs, children: [
              _buildProfileTab(p),
              _buildPasswordTab(),
            ]),
    );
  }

  Widget _buildProfileTab(Map<String, dynamic>? p) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(children: [
        // Avatar
        CircleAvatar(
          radius: 44,
          backgroundColor: AppTheme.primary.withValues(alpha: 0.12),
          child: Text(
            (_nameCtrl.text.isNotEmpty ? _nameCtrl.text[0].toUpperCase() : '?'),
            style: TextStyle(fontSize: 36, fontWeight: FontWeight.w800, color: AppTheme.primary),
          ),
        ),
        const SizedBox(height: 8),
        if (p != null) ...[
          Text(p['role'] as String? ?? '', style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.w600, fontSize: 12)),
          const SizedBox(height: 4),
          if (p['last_login'] != null)
            Text('Letzter Login: ${p['last_login']}', style: TextStyle(color: Colors.grey.shade500, fontSize: 11)),
        ],
        const SizedBox(height: 28),
        _card(child: Column(children: [
          _sectionHeader('Profilinfo', Icons.person_rounded, AppTheme.primary),
          const SizedBox(height: 16),
          TextField(
            controller: _nameCtrl,
            decoration: const InputDecoration(labelText: 'Name *', prefixIcon: Icon(Icons.badge_rounded)),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _emailCtrl,
            decoration: const InputDecoration(labelText: 'E-Mail *', prefixIcon: Icon(Icons.email_rounded)),
            keyboardType: TextInputType.emailAddress,
          ),
          const SizedBox(height: 20),
          SizedBox(width: double.infinity, child: FilledButton.icon(
            icon: _saving
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Icon(Icons.save_rounded),
            label: Text(_saving ? 'Speichern…' : 'Profil speichern'),
            onPressed: _saving ? null : _saveProfile,
          )),
        ])),
        const SizedBox(height: 16),
        _card(child: Column(children: [
          _sectionHeader('Abmelden', Icons.logout_rounded, AppTheme.danger),
          const SizedBox(height: 12),
          SizedBox(width: double.infinity, child: OutlinedButton.icon(
            icon: const Icon(Icons.logout_rounded),
            label: const Text('Abmelden'),
            style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger, side: BorderSide(color: AppTheme.danger)),
            onPressed: () async {
              final ok = await showDialog<bool>(context: context, builder: (_) => AlertDialog(
                title: const Text('Abmelden'),
                content: const Text('Wirklich abmelden?'),
                actions: [
                  TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
                  FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Abmelden')),
                ],
              ));
              if (ok == true && context.mounted) await context.read<AuthService>().logout();
            },
          )),
        ])),
      ]),
    );
  }

  Widget _buildPasswordTab() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: _card(child: Column(children: [
        _sectionHeader('Passwort ändern', Icons.lock_rounded, AppTheme.secondary),
        const SizedBox(height: 16),
        TextField(
          controller: _curPwCtrl,
          obscureText: !_showCur,
          decoration: InputDecoration(
            labelText: 'Aktuelles Passwort',
            prefixIcon: const Icon(Icons.lock_outline_rounded),
            suffixIcon: IconButton(icon: Icon(_showCur ? Icons.visibility_off_rounded : Icons.visibility_rounded),
              onPressed: () => setState(() => _showCur = !_showCur)),
          ),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _newPwCtrl,
          obscureText: !_showNew,
          decoration: InputDecoration(
            labelText: 'Neues Passwort',
            prefixIcon: const Icon(Icons.lock_rounded),
            suffixIcon: IconButton(icon: Icon(_showNew ? Icons.visibility_off_rounded : Icons.visibility_rounded),
              onPressed: () => setState(() => _showNew = !_showNew)),
          ),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _confPwCtrl,
          obscureText: !_showConf,
          decoration: InputDecoration(
            labelText: 'Passwort bestätigen',
            prefixIcon: const Icon(Icons.lock_reset_rounded),
            suffixIcon: IconButton(icon: Icon(_showConf ? Icons.visibility_off_rounded : Icons.visibility_rounded),
              onPressed: () => setState(() => _showConf = !_showConf)),
          ),
        ),
        const SizedBox(height: 20),
        SizedBox(width: double.infinity, child: FilledButton.icon(
          icon: _pwSaving
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
              : const Icon(Icons.key_rounded),
          label: Text(_pwSaving ? 'Ändern…' : 'Passwort ändern'),
          onPressed: _pwSaving ? null : _changePassword,
        )),
      ])),
    );
  }

  Widget _card({required Widget child}) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(20),
    decoration: BoxDecoration(
      color: Theme.of(context).cardTheme.color,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: Theme.of(context).dividerColor),
    ),
    child: child,
  );

  Widget _sectionHeader(String title, IconData icon, Color color) => Row(children: [
    Container(padding: const EdgeInsets.all(8), decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
      child: Icon(icon, color: color, size: 18)),
    const SizedBox(width: 10),
    Text(title, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: color)),
  ]);
}
