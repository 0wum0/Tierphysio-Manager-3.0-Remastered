import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import '../services/portal_api_service.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  Map<String, dynamic>? _profile;
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final auth = context.read<PortalAuthService>();
      final data = await PortalApiService(token: auth.token).getProfile();
      if (mounted) setState(() { _profile = data; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mein Profil')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : RefreshIndicator(onRefresh: _load, child: _buildBody()),
    );
  }

  Widget _buildBody() {
    final p  = _profile ?? {};
    final cs = Theme.of(context).colorScheme;

    return ListView(padding: const EdgeInsets.all(16), children: [
      // Avatar
      Center(child: Column(children: [
        const SizedBox(height: 16),
        CircleAvatar(radius: 48, backgroundColor: AppTheme.primary.withValues(alpha: 0.12),
          child: const Icon(Icons.person_rounded, color: AppTheme.primary, size: 48)),
        const SizedBox(height: 12),
        Text(p['name'] as String? ?? '–', style: Theme.of(context).textTheme.headlineSmall),
        Text(p['email'] as String? ?? '–', style: TextStyle(color: cs.onSurfaceVariant)),
        const SizedBox(height: 24),
      ])),

      // Profile details
      Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(children: [
        _InfoRow(Icons.phone_rounded, 'Telefon', p['phone'] as String? ?? '–'),
        _InfoRow(Icons.home_rounded, 'Adresse', [p['address'], p['city']].where((v) => v != null && v.toString().isNotEmpty).join(', ')),
        _InfoRow(Icons.calendar_month_rounded, 'Mitglied seit', p['created_at'] as String? ?? '–'),
      ]))),

      const SizedBox(height: 24),

      // Change password
      Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Passwort ändern', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 16),
        _ChangePasswordForm(),
      ]))),
    ]);
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  const _InfoRow(this.icon, this.label, this.value);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: Row(children: [
      Icon(icon, size: 18, color: AppTheme.primary),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: TextStyle(fontSize: 11, color: Theme.of(context).colorScheme.onSurfaceVariant)),
        Text(value.isNotEmpty ? value : '–', style: const TextStyle(fontWeight: FontWeight.w500)),
      ])),
    ]),
  );
}

class _ChangePasswordForm extends StatefulWidget {
  @override
  State<_ChangePasswordForm> createState() => _ChangePasswordFormState();
}

class _ChangePasswordFormState extends State<_ChangePasswordForm> {
  final _currentCtrl  = TextEditingController();
  final _newCtrl      = TextEditingController();
  final _confirmCtrl  = TextEditingController();
  bool _saving = false;
  String? _error;
  String? _success;

  @override
  void dispose() { _currentCtrl.dispose(); _newCtrl.dispose(); _confirmCtrl.dispose(); super.dispose(); }

  Future<void> _save() async {
    if (_newCtrl.text != _confirmCtrl.text) {
      setState(() => _error = 'Die neuen Passwörter stimmen nicht überein.');
      return;
    }
    if (_newCtrl.text.length < 8) {
      setState(() => _error = 'Passwort muss mindestens 8 Zeichen lang sein.');
      return;
    }
    setState(() { _saving = true; _error = null; _success = null; });
    try {
      final auth = context.read<PortalAuthService>();
      await PortalApiService(token: auth.token).changePassword(_currentCtrl.text, _newCtrl.text, _confirmCtrl.text);
      if (mounted) {
        _currentCtrl.clear(); _newCtrl.clear(); _confirmCtrl.clear();
        setState(() { _success = 'Passwort erfolgreich geändert.'; _saving = false; });
      }
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _saving = false; });
    }
  }

  @override
  Widget build(BuildContext context) => Column(children: [
    _PwField(ctrl: _currentCtrl, label: 'Aktuelles Passwort'),
    const SizedBox(height: 12),
    _PwField(ctrl: _newCtrl, label: 'Neues Passwort'),
    const SizedBox(height: 12),
    _PwField(ctrl: _confirmCtrl, label: 'Neues Passwort bestätigen'),
    if (_error != null) ...[
      const SizedBox(height: 12),
      Container(padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AppTheme.danger.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppTheme.danger.withValues(alpha: 0.3))),
        child: Text(_error!, style: const TextStyle(color: AppTheme.danger))),
    ],
    if (_success != null) ...[
      const SizedBox(height: 12),
      Container(padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AppTheme.success.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppTheme.success.withValues(alpha: 0.3))),
        child: Text(_success!, style: const TextStyle(color: AppTheme.success))),
    ],
    const SizedBox(height: 16),
    FilledButton(
      onPressed: _saving ? null : _save,
      child: _saving
          ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
          : const Text('Passwort speichern'),
    ),
  ]);
}

class _PwField extends StatefulWidget {
  final TextEditingController ctrl;
  final String label;
  const _PwField({required this.ctrl, required this.label});
  @override
  State<_PwField> createState() => _PwFieldState();
}

class _PwFieldState extends State<_PwField> {
  bool _obscure = true;
  @override
  Widget build(BuildContext context) => TextFormField(
    controller: widget.ctrl,
    obscureText: _obscure,
    decoration: InputDecoration(
      labelText: widget.label,
      prefixIcon: const Icon(Icons.lock_outline_rounded),
      suffixIcon: IconButton(
        icon: Icon(_obscure ? Icons.visibility_off_outlined : Icons.visibility_outlined),
        onPressed: () => setState(() => _obscure = !_obscure),
      ),
    ),
  );
}
