import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class PortalUserDetailScreen extends StatefulWidget {
  final int id;
  const PortalUserDetailScreen({super.key, required this.id});
  @override
  State<PortalUserDetailScreen> createState() => _PortalUserDetailScreenState();
}

class _PortalUserDetailScreenState extends State<PortalUserDetailScreen> {
  final _api = ApiService();
  Map<String, dynamic>? _user;
  Map<String, dynamic>? _overview;
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final user = await _api.portalUserShow(widget.id);
      setState(() { _user = user; });
      // Load owner overview if owner_id available
      final ownerId = user['owner_id'] as int?;
      if (ownerId != null) {
        try {
          final overview = await _api.portalOwnerOverview(ownerId);
          setState(() { _overview = overview; });
        } catch (_) {}
      }
      setState(() => _loading = false);
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  String _fmt(String? d) {
    if (d == null) return '—';
    try { return DateFormat('dd.MM.yyyy HH:mm', 'de_DE').format(DateTime.parse(d)); } catch (_) { return d; }
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
    final user = _user;
    final active = user?['active'] as bool? ?? user?['status'] == 'active';
    return Scaffold(
      appBar: AppBar(
        title: Text(user?['name'] as String? ?? user?['email'] as String? ?? 'Portal-Benutzer'),
        actions: [
          if (user != null)
            PopupMenuButton<String>(
              onSelected: (v) async {
                try {
                  switch (v) {
                    case 'resend':
                      await _api.portalResendInvite(widget.id);
                      _showSnack('Einladung erneut gesendet ✓');
                    case 'toggle':
                      if (active) {
                        await _api.portalDeactivate(widget.id);
                        _showSnack('Deaktiviert ✓');
                      } else {
                        await _api.portalActivate(widget.id);
                        _showSnack('Aktiviert ✓');
                      }
                      _load();
                    case 'delete':
                      final ok = await showDialog<bool>(context: context, builder: (_) => AlertDialog(
                        title: const Text('Benutzer löschen'),
                        content: const Text('Portal-Benutzer wirklich dauerhaft löschen?'),
                        actions: [
                          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
                          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Löschen'),
                            style: FilledButton.styleFrom(backgroundColor: AppTheme.danger)),
                        ],
                      ));
                      if (ok == true) {
                        await _api.portalUserDelete(widget.id);
                        if (mounted) context.pop();
                      }
                  }
                } catch (e) { _showSnack(e.toString(), error: true); }
              },
              itemBuilder: (_) => [
                const PopupMenuItem(value: 'resend', child: Row(children: [
                  Icon(Icons.send_rounded, size: 18), SizedBox(width: 8), Text('Einladung erneut senden'),
                ])),
                PopupMenuItem(value: 'toggle', child: Row(children: [
                  Icon(active ? Icons.block_rounded : Icons.check_circle_rounded,
                    size: 18, color: active ? AppTheme.warning : AppTheme.success),
                  const SizedBox(width: 8),
                  Text(active ? 'Deaktivieren' : 'Aktivieren'),
                ])),
                PopupMenuItem(value: 'delete', child: Row(children: [
                  Icon(Icons.delete_outline_rounded, size: 18, color: AppTheme.danger),
                  const SizedBox(width: 8),
                  Text('Löschen', style: TextStyle(color: AppTheme.danger)),
                ])),
              ],
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(Icons.error_outline_rounded, size: 56, color: AppTheme.danger),
                  const SizedBox(height: 12),
                  Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut')),
                ]))
              : _buildBody(),
    );
  }

  Widget _buildBody() {
    final user = _user!;
    final active  = user['active'] as bool? ?? user['status'] == 'active';
    final pending = user['status'] == 'pending' || user['status'] == 'invited';
    final statusColor = active ? AppTheme.success : pending ? AppTheme.warning : Colors.grey;
    final statusLabel = active ? 'Aktiv' : pending ? 'Einladung ausstehend' : 'Inaktiv';
    final overview    = _overview;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(children: [
        // User card
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: [
              statusColor, Color.lerp(statusColor, AppTheme.primary, 0.4)!,
            ]),
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(children: [
            CircleAvatar(
              radius: 36,
              backgroundColor: Colors.white.withValues(alpha: 0.2),
              child: Text(
                (user['name'] as String? ?? user['email'] as String? ?? '?').substring(0, 1).toUpperCase(),
                style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: Colors.white),
              ),
            ),
            const SizedBox(height: 12),
            Text(user['name'] as String? ?? '—',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18)),
            Text(user['email'] as String? ?? '—',
              style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 13)),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(statusLabel,
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
            ),
          ]),
        ),

        const SizedBox(height: 16),

        // Info card
        _infoCard('Kontodetails', Icons.info_rounded, AppTheme.primary, [
          if (user['owner_name'] != null) _infoRow(Icons.person_rounded, 'Besitzer', user['owner_name'] as String),
          _infoRow(Icons.login_rounded, 'Letzter Login', _fmt(user['last_login'] as String?)),
          _infoRow(Icons.calendar_today_rounded, 'Registriert', _fmt(user['created_at'] as String?)),
          if (user['invited_at'] != null) _infoRow(Icons.send_rounded, 'Eingeladen', _fmt(user['invited_at'] as String?)),
        ]),

        if (overview != null) ...[
          const SizedBox(height: 16),
          _buildOwnerOverview(overview),
        ],
      ]),
    );
  }

  Widget _infoCard(String title, IconData icon, Color color, List<Widget> rows) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Theme.of(context).dividerColor),
      ),
      child: Column(children: [
        Padding(padding: const EdgeInsets.fromLTRB(16, 14, 16, 8), child: Row(children: [
          Container(padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
            child: Icon(icon, color: color, size: 16)),
          const SizedBox(width: 10),
          Text(title, style: TextStyle(fontWeight: FontWeight.w700, color: color, fontSize: 14)),
        ])),
        const Divider(height: 1),
        Padding(padding: const EdgeInsets.all(16), child: Column(children: rows)),
      ]),
    );
  }

  Widget _infoRow(IconData icon, String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Row(children: [
      Icon(icon, size: 14, color: Colors.grey.shade400),
      const SizedBox(width: 8),
      SizedBox(width: 110, child: Text(label,
        style: TextStyle(fontSize: 12, color: Colors.grey.shade500))),
      Expanded(child: Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13))),
    ]),
  );

  Widget _buildOwnerOverview(Map<String, dynamic> overview) {
    final pets  = List<dynamic>.from(overview['pets'] as List? ?? []);
    final plans = List<dynamic>.from(overview['homework_plans'] as List? ?? []);

    return _infoCard('Besitzer-Übersicht', Icons.home_rounded, AppTheme.secondary, [
      if (pets.isNotEmpty) ...[
        Text('Tiere (${pets.length})',
          style: TextStyle(fontWeight: FontWeight.w700, color: AppTheme.secondary, fontSize: 12)),
        const SizedBox(height: 8),
        ...pets.map((pet) {
          final p = Map<String, dynamic>.from(pet as Map);
          return ListTile(
            dense: true,
            contentPadding: EdgeInsets.zero,
            leading: CircleAvatar(
              radius: 16, backgroundColor: AppTheme.primary.withValues(alpha: 0.1),
              child: Icon(Icons.pets_rounded, color: AppTheme.primary, size: 16),
            ),
            title: Text(p['name'] as String? ?? '—', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
            subtitle: Text(p['species'] as String? ?? '', style: const TextStyle(fontSize: 11)),
            trailing: TextButton(
              onPressed: () => context.push('/patienten/${p['id']}'),
              child: const Text('Akte'),
            ),
          );
        }),
        if (pets.isNotEmpty && plans.isNotEmpty) const Divider(),
      ],
      if (plans.isNotEmpty) ...[
        Text('Hausaufgabenpläne (${plans.length})',
          style: TextStyle(fontWeight: FontWeight.w700, color: AppTheme.secondary, fontSize: 12)),
        const SizedBox(height: 8),
        ...plans.take(3).map((plan) {
          final pl = Map<String, dynamic>.from(plan as Map);
          final sent = pl['sent_at'] != null;
          return ListTile(
            dense: true,
            contentPadding: EdgeInsets.zero,
            leading: Icon(Icons.assignment_rounded,
              color: sent ? AppTheme.success : AppTheme.warning, size: 20),
            title: Text(pl['title'] as String? ?? '—', style: const TextStyle(fontSize: 13)),
            subtitle: Text(sent ? 'Gesendet' : 'Ausstehend',
              style: TextStyle(fontSize: 10, color: sent ? AppTheme.success : AppTheme.warning)),
          );
        }),
      ],
    ]);
  }
}
