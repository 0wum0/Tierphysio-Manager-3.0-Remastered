import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class PortalAdminScreen extends StatefulWidget {
  const PortalAdminScreen({super.key});
  @override
  State<PortalAdminScreen> createState() => _PortalAdminScreenState();
}

class _PortalAdminScreenState extends State<PortalAdminScreen>
    with SingleTickerProviderStateMixin {
  final _api = ApiService();
  late TabController _tabs;

  Map<String, dynamic>? _stats;
  List<Map<String, dynamic>> _users = [];
  List<Map<String, dynamic>> _plans = [];
  List<Map<String, dynamic>> _templates = [];

  bool _loadingUsers = true;
  bool _loadingPlans = true;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
    _loadAll();
  }

  @override
  void dispose() { _tabs.dispose(); super.dispose(); }

  Future<void> _loadAll() async {
    _loadUsers();
    _loadPlans();
  }

  Future<void> _loadUsers() async {
    setState(() => _loadingUsers = true);
    try {
      final results = await Future.wait([
        _api.portalStats().catchError((_) => <String, dynamic>{}),
        _api.portalUsersList(),
      ]);
      setState(() {
        _stats = results[0] as Map<String, dynamic>;
        _users = (results[1] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _loadingUsers = false;
      });
    } catch (_) { setState(() => _loadingUsers = false); }
  }

  Future<void> _loadPlans() async {
    setState(() => _loadingPlans = true);
    try {
      final results = await Future.wait([
        _api.homeworkPlanList(),
        _api.homeworkTemplates().catchError((_) => <dynamic>[]),
      ]);
      setState(() {
        _plans     = results[0].map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _templates = results[1].map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _loadingPlans = false;
      });
    } catch (_) { setState(() => _loadingPlans = false); }
  }

  String _fmt(String? d) {
    if (d == null) return '—';
    try { return DateFormat('dd.MM.yyyy', 'de_DE').format(DateTime.parse(d)); } catch (_) { return d; }
  }

  void _showSnack(String msg, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      backgroundColor: error ? AppTheme.danger : AppTheme.success,
    ));
  }

  // ── Portal User Actions ──────────────────────────────────────────────────

  Future<void> _inviteUser() async {
    final ownerIdCtrl = TextEditingController();
    final emailCtrl   = TextEditingController();
    await showModalBottomSheet(
      context: context, isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom + 32),
        child: Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 0), child: Column(mainAxisSize: MainAxisSize.min, children: [
          _handle(),
          Text('Portal-Benutzer einladen',
            style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          TextField(controller: emailCtrl,
            decoration: const InputDecoration(labelText: 'E-Mail *', prefixIcon: Icon(Icons.email_rounded)),
            keyboardType: TextInputType.emailAddress),
          const SizedBox(height: 10),
          TextField(controller: ownerIdCtrl,
            decoration: const InputDecoration(labelText: 'Besitzer-ID (optional)', prefixIcon: Icon(Icons.person_rounded)),
            keyboardType: TextInputType.number),
          const SizedBox(height: 20),
          SizedBox(width: double.infinity, child: FilledButton.icon(
            icon: const Icon(Icons.send_rounded),
            label: const Text('Einladung senden'),
            onPressed: () async {
              if (emailCtrl.text.trim().isEmpty) return;
              Navigator.pop(ctx);
              try {
                final res = await _api.portalInvite({
                  'email': emailCtrl.text.trim(),
                  if (ownerIdCtrl.text.trim().isNotEmpty)
                    'owner_id': int.tryParse(ownerIdCtrl.text.trim()),
                });
                _showSnack('Einladung gesendet ✓');
                if (res['whatsapp_url'] != null) {
                  final uri = Uri.parse(res['whatsapp_url'] as String);
                  if (await canLaunchUrl(uri)) await launchUrl(uri, mode: LaunchMode.externalApplication);
                }
                _loadUsers();
              } catch (e) { _showSnack(e.toString(), error: true); }
            },
          )),
        ])),
      ),
    );
  }

  Future<void> _userActions(Map<String, dynamic> user) async {
    final id     = user['id'] as int;
    final active = user['active'] as bool? ?? user['status'] == 'active';
    final choice = await showModalBottomSheet<String>(
      context: context,
      builder: (ctx) => SafeArea(child: Column(mainAxisSize: MainAxisSize.min, children: [
        _handle(),
        Text(user['email'] as String? ?? '—',
          style: Theme.of(ctx).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
        const SizedBox(height: 8),
        ListTile(leading: const Icon(Icons.person_search_rounded), title: const Text('Details anzeigen'),
          onTap: () => Navigator.pop(ctx, 'detail')),
        ListTile(leading: const Icon(Icons.send_rounded), title: const Text('Einladung erneut senden'),
          onTap: () => Navigator.pop(ctx, 'resend')),
        if (active)
          ListTile(leading: Icon(Icons.block_rounded, color: AppTheme.warning),
            title: Text('Deaktivieren', style: TextStyle(color: AppTheme.warning)),
            onTap: () => Navigator.pop(ctx, 'deactivate'))
        else
          ListTile(leading: Icon(Icons.check_circle_rounded, color: AppTheme.success),
            title: Text('Aktivieren', style: TextStyle(color: AppTheme.success)),
            onTap: () => Navigator.pop(ctx, 'activate')),
        ListTile(leading: Icon(Icons.delete_outline_rounded, color: AppTheme.danger),
          title: Text('Löschen', style: TextStyle(color: AppTheme.danger)),
          onTap: () => Navigator.pop(ctx, 'delete')),
        const SizedBox(height: 8),
      ])),
    );
    if (choice == null) return;
    try {
      switch (choice) {
        case 'detail':  context.push('/portal-admin/benutzer/$id'); return;
        case 'resend':  await _api.portalResendInvite(id); _showSnack('Einladung erneut gesendet ✓');
        case 'activate': await _api.portalActivate(id); _showSnack('Aktiviert ✓');
        case 'deactivate': await _api.portalDeactivate(id); _showSnack('Deaktiviert ✓');
        case 'delete':
          final ok = await _confirmDelete('Portal-Benutzer wirklich löschen?');
          if (ok) { await _api.portalUserDelete(id); _showSnack('Gelöscht ✓'); }
      }
      _loadUsers();
    } catch (e) { _showSnack(e.toString(), error: true); }
  }

  // ── Homework Plan Actions ────────────────────────────────────────────────

  void _showCreatePlan() {
    final titleCtrl = TextEditingController();
    final descCtrl  = TextEditingController();
    String? selectedTemplate;
    showModalBottomSheet(
      context: context, isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(builder: (ctx, ss) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom + 32),
        child: Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 0), child: Column(mainAxisSize: MainAxisSize.min, children: [
          _handle(),
          Text('Neuer Hausaufgabenplan',
            style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          TextField(controller: titleCtrl,
            decoration: const InputDecoration(labelText: 'Titel *', prefixIcon: Icon(Icons.assignment_rounded))),
          const SizedBox(height: 10),
          TextField(controller: descCtrl,
            decoration: const InputDecoration(labelText: 'Beschreibung', prefixIcon: Icon(Icons.notes_rounded)),
            maxLines: 2),
          if (_templates.isNotEmpty) ...[
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: selectedTemplate,
              decoration: const InputDecoration(labelText: 'Vorlage verwenden', prefixIcon: Icon(Icons.library_books_rounded)),
              items: [
                const DropdownMenuItem(value: null, child: Text('— Keine Vorlage —')),
                ..._templates.map((t) => DropdownMenuItem(
                  value: t['id'].toString(),
                  child: Text(t['name'] as String? ?? '—'),
                )),
              ],
              onChanged: (v) => ss(() => selectedTemplate = v),
            ),
          ],
          const SizedBox(height: 20),
          SizedBox(width: double.infinity, child: FilledButton.icon(
            icon: const Icon(Icons.add_rounded),
            label: const Text('Plan erstellen'),
            onPressed: () async {
              if (titleCtrl.text.trim().isEmpty) return;
              Navigator.pop(ctx);
              try {
                await _api.homeworkPlanCreate({
                  'title':       titleCtrl.text.trim(),
                  'description': descCtrl.text.trim(),
                  if (selectedTemplate != null) 'template_id': int.tryParse(selectedTemplate!),
                });
                _showSnack('Plan erstellt ✓');
                _loadPlans();
              } catch (e) { _showSnack(e.toString(), error: true); }
            },
          )),
        ])),
      )),
    );
  }

  Future<void> _planActions(Map<String, dynamic> plan) async {
    final id    = plan['id'] as int;
    final title = plan['title'] as String? ?? '—';
    final choice = await showModalBottomSheet<String>(
      context: context,
      builder: (ctx) => SafeArea(child: Column(mainAxisSize: MainAxisSize.min, children: [
        _handle(),
        Text(title, style: Theme.of(ctx).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
        const SizedBox(height: 8),
        ListTile(leading: const Icon(Icons.open_in_new_rounded), title: const Text('Details / Aufgaben'),
          onTap: () => Navigator.pop(ctx, 'detail')),
        ListTile(leading: Icon(Icons.picture_as_pdf_rounded, color: AppTheme.danger),
          title: const Text('PDF öffnen'),
          onTap: () => Navigator.pop(ctx, 'pdf')),
        ListTile(leading: Icon(Icons.send_rounded, color: AppTheme.primary),
          title: const Text('An Besitzer senden'),
          onTap: () => Navigator.pop(ctx, 'send')),
        ListTile(leading: Icon(Icons.delete_outline_rounded, color: AppTheme.danger),
          title: Text('Löschen', style: TextStyle(color: AppTheme.danger)),
          onTap: () => Navigator.pop(ctx, 'delete')),
        const SizedBox(height: 8),
      ])),
    );
    if (choice == null) return;
    switch (choice) {
      case 'detail': context.push('/portal-admin/hausaufgabenplan/$id'); return;
      case 'pdf':
        try {
          final data = await _api.homeworkPlanPdfUrl(id);
          final url  = data['url'] as String? ?? '';
          if (url.isEmpty) { _showSnack('Keine PDF-URL verfügbar.', error: true); return; }
          final fullUrl = url.startsWith('http') ? url : '${ApiService.baseUrl}$url';
          final uri = Uri.parse(fullUrl);
          if (await canLaunchUrl(uri)) await launchUrl(uri, mode: LaunchMode.externalApplication);
        } catch (e) { _showSnack(e.toString(), error: true); }
        return;
      case 'send': await _sendPlan(id); return;
      case 'delete':
        final ok = await _confirmDelete('Hausaufgabenplan wirklich löschen?');
        if (!ok) return;
        try { await _api.homeworkPlanDelete(id); _showSnack('Gelöscht ✓'); _loadPlans(); }
        catch (e) { _showSnack(e.toString(), error: true); }
    }
  }

  Future<void> _sendPlan(int planId) async {
    final ownerIdCtrl = TextEditingController();
    await showModalBottomSheet(
      context: context, isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom + 32),
        child: Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 0), child: Column(mainAxisSize: MainAxisSize.min, children: [
          _handle(),
          Text('Plan an Besitzer senden',
            style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          TextField(controller: ownerIdCtrl,
            decoration: const InputDecoration(labelText: 'Besitzer-ID *', prefixIcon: Icon(Icons.person_rounded)),
            keyboardType: TextInputType.number),
          const SizedBox(height: 20),
          SizedBox(width: double.infinity, child: FilledButton.icon(
            icon: const Icon(Icons.send_rounded),
            label: const Text('Senden'),
            onPressed: () async {
              if (ownerIdCtrl.text.trim().isEmpty) return;
              Navigator.pop(ctx);
              try {
                await _api.homeworkPlanSend(planId, {
                  'owner_id': int.parse(ownerIdCtrl.text.trim()),
                });
                _showSnack('Plan gesendet ✓');
              } catch (e) { _showSnack(e.toString(), error: true); }
            },
          )),
        ])),
      ),
    );
  }

  Future<bool> _confirmDelete(String msg) async {
    final ok = await showDialog<bool>(context: context, builder: (_) => AlertDialog(
      title: const Text('Löschen'),
      content: Text(msg),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
        FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Löschen'),
          style: FilledButton.styleFrom(backgroundColor: AppTheme.danger)),
      ],
    ));
    return ok == true;
  }

  Widget _handle() => Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16),
    decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)));

  // ── Build ────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Besitzer-Portal'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _loadAll),
        ],
        bottom: TabBar(
          controller: _tabs,
          tabs: [
            Tab(text: 'Benutzer', icon: Badge(
              isLabelVisible: _users.isNotEmpty,
              label: Text('${_users.length}'),
              backgroundColor: AppTheme.primary,
              child: const Icon(Icons.people_rounded, size: 16),
            )),
            const Tab(text: 'Übungen', icon: Icon(Icons.fitness_center_rounded, size: 16)),
            Tab(text: 'Pläne', icon: Badge(
              isLabelVisible: _plans.isNotEmpty,
              label: Text('${_plans.length}'),
              backgroundColor: AppTheme.secondary,
              child: const Icon(Icons.assignment_rounded, size: 16),
            )),
          ],
        ),
      ),
      floatingActionButton: _buildFab(),
      body: TabBarView(
        controller: _tabs,
        children: [
          _buildUsersTab(),
          _buildExercisesTab(),
          _buildPlansTab(),
        ],
      ),
    );
  }

  Widget? _buildFab() {
    return AnimatedBuilder(
      animation: _tabs,
      builder: (ctx, _) {
        if (_tabs.index == 0) {
          return FloatingActionButton.extended(
            onPressed: _inviteUser,
            icon: const Icon(Icons.person_add_rounded),
            label: const Text('Einladen'),
          );
        } else if (_tabs.index == 2) {
          return FloatingActionButton.extended(
            onPressed: _showCreatePlan,
            icon: const Icon(Icons.add_rounded),
            label: const Text('Neuer Plan'),
          );
        }
        return const SizedBox.shrink();
      },
    );
  }

  // ── Users Tab ────────────────────────────────────────────────────────────

  Widget _buildUsersTab() {
    if (_loadingUsers) return const Center(child: CircularProgressIndicator());
    final stats = _stats;
    return RefreshIndicator(
      onRefresh: _loadUsers,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
        children: [
          if (stats != null) _statsRow(stats),
          if (stats != null) const SizedBox(height: 16),
          if (_users.isEmpty)
            Center(child: Padding(
              padding: const EdgeInsets.only(top: 60),
              child: Column(children: [
                Icon(Icons.people_outline_rounded, size: 72, color: Colors.grey.shade300),
                const SizedBox(height: 16),
                Text('Keine Portal-Benutzer', style: TextStyle(color: Colors.grey.shade500, fontSize: 16)),
                const SizedBox(height: 8),
                Text('Tippe auf + um jemanden einzuladen',
                  style: TextStyle(color: Colors.grey.shade400, fontSize: 12)),
              ]),
            ))
          else ..._users.map((u) => _userCard(u)),
        ],
      ),
    );
  }

  Widget _statsRow(Map<String, dynamic> stats) {
    return Row(children: [
      _statChip('Gesamt', '${stats['total'] ?? 0}', AppTheme.primary, Icons.people_rounded),
      const SizedBox(width: 10),
      _statChip('Aktiv', '${stats['active'] ?? 0}', AppTheme.success, Icons.check_circle_rounded),
      const SizedBox(width: 10),
      _statChip('Ausstehend', '${stats['pending'] ?? 0}', AppTheme.warning, Icons.schedule_rounded),
    ]);
  }

  Widget _statChip(String label, String value, Color color, IconData icon) {
    return Expanded(child: Container(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Row(children: [
        Icon(icon, color: color, size: 18),
        const SizedBox(width: 8),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(value, style: TextStyle(fontWeight: FontWeight.w800, color: color, fontSize: 16)),
          Text(label, style: TextStyle(fontSize: 10, color: color.withValues(alpha: 0.7))),
        ]),
      ]),
    ));
  }

  Widget _userCard(Map<String, dynamic> u) {
    final active  = u['active'] as bool? ?? u['status'] == 'active';
    final pending = u['status'] == 'pending' || u['status'] == 'invited';
    final Color statusColor = active
        ? AppTheme.success
        : pending ? AppTheme.warning : Colors.grey;
    final String statusLabel = active ? 'Aktiv' : pending ? 'Ausstehend' : 'Inaktiv';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: statusColor.withValues(alpha: 0.25)),
      ),
      child: ListTile(
        contentPadding: const EdgeInsets.fromLTRB(16, 8, 8, 8),
        leading: CircleAvatar(
          backgroundColor: statusColor.withValues(alpha: 0.12),
          child: Text(
            (u['name'] as String? ?? u['email'] as String? ?? '?').substring(0, 1).toUpperCase(),
            style: TextStyle(color: statusColor, fontWeight: FontWeight.w800),
          ),
        ),
        title: Text(u['name'] as String? ?? u['email'] as String? ?? '—',
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
        subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if ((u['email'] as String? ?? '').isNotEmpty && u['name'] != null)
            Text(u['email'] as String, style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
          if (u['owner_name'] != null)
            Row(children: [
              Icon(Icons.person_rounded, size: 11, color: Colors.grey.shade400),
              const SizedBox(width: 3),
              Text(u['owner_name'] as String, style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
            ]),
          Text('Letzter Login: ${_fmt(u['last_login'] as String?)}',
            style: TextStyle(fontSize: 10, color: Colors.grey.shade400)),
        ]),
        trailing: Row(mainAxisSize: MainAxisSize.min, children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
            child: Text(statusLabel, style: TextStyle(color: statusColor, fontSize: 10, fontWeight: FontWeight.w700)),
          ),
          IconButton(icon: const Icon(Icons.more_vert_rounded, size: 20), onPressed: () => _userActions(u)),
        ]),
      ),
    );
  }

  // ── Exercises Tab ────────────────────────────────────────────────────────

  Widget _buildExercisesTab() {
    return Center(child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Container(
          width: 80, height: 80,
          decoration: BoxDecoration(
            color: AppTheme.secondary.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(Icons.fitness_center_rounded, size: 36, color: AppTheme.secondary),
        ),
        const SizedBox(height: 20),
        Text('Übungen', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
        const SizedBox(height: 8),
        Text(
          'Übungen werden pro Patient verwaltet. Öffne eine Patientenakte und wechsle zum Tab "Übungen".',
          textAlign: TextAlign.center,
          style: TextStyle(color: Colors.grey.shade500, height: 1.5),
        ),
        const SizedBox(height: 24),
        FilledButton.icon(
          icon: const Icon(Icons.pets_rounded),
          label: const Text('Zu Patienten'),
          onPressed: () => context.go('/patienten'),
        ),
      ]),
    ));
  }

  // ── Plans Tab ────────────────────────────────────────────────────────────

  Widget _buildPlansTab() {
    if (_loadingPlans) return const Center(child: CircularProgressIndicator());
    return RefreshIndicator(
      onRefresh: _loadPlans,
      child: _plans.isEmpty
          ? ListView(padding: const EdgeInsets.all(32), children: [
              Center(child: Column(children: [
                const SizedBox(height: 40),
                Icon(Icons.assignment_outlined, size: 72, color: Colors.grey.shade300),
                const SizedBox(height: 16),
                Text('Keine Hausaufgabenpläne', style: TextStyle(color: Colors.grey.shade500, fontSize: 16)),
                const SizedBox(height: 8),
                Text('Tippe auf + um einen neuen Plan zu erstellen',
                  style: TextStyle(color: Colors.grey.shade400, fontSize: 12)),
              ])),
            ])
          : ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
              itemCount: _plans.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (ctx, i) => _planCard(_plans[i]),
            ),
    );
  }

  Widget _planCard(Map<String, dynamic> plan) {
    final sent = plan['sent_at'] != null;
    final color = sent ? AppTheme.success : AppTheme.secondary;

    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: ListTile(
        contentPadding: const EdgeInsets.fromLTRB(16, 10, 8, 10),
        leading: Container(
          width: 42, height: 42,
          decoration: BoxDecoration(color: color.withValues(alpha: 0.1), shape: BoxShape.circle),
          child: Icon(Icons.assignment_rounded, color: color, size: 20),
        ),
        title: Text(plan['title'] as String? ?? '—',
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
        subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if ((plan['description'] as String? ?? '').isNotEmpty)
            Text(plan['description'] as String,
              style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
              maxLines: 1, overflow: TextOverflow.ellipsis),
          Row(children: [
            if (sent) ...[
              Icon(Icons.check_circle_rounded, size: 11, color: AppTheme.success),
              const SizedBox(width: 3),
              Text('Gesendet ${_fmt(plan['sent_at'] as String?)}',
                style: TextStyle(fontSize: 10, color: AppTheme.success)),
            ] else ...[
              Icon(Icons.schedule_rounded, size: 11, color: Colors.grey.shade400),
              const SizedBox(width: 3),
              Text('Noch nicht gesendet', style: TextStyle(fontSize: 10, color: Colors.grey.shade400)),
            ],
          ]),
          if (plan['task_count'] != null)
            Text('${plan['task_count']} Aufgaben', style: TextStyle(fontSize: 10, color: Colors.grey.shade500)),
        ]),
        trailing: Row(mainAxisSize: MainAxisSize.min, children: [
          IconButton(
            icon: Icon(Icons.picture_as_pdf_rounded, color: AppTheme.danger, size: 20),
            tooltip: 'PDF',
            onPressed: () async {
              try {
                final data = await _api.homeworkPlanPdfUrl(plan['id'] as int);
                final url  = data['url'] as String? ?? '';
                if (url.isEmpty) { _showSnack('Keine PDF verfügbar.', error: true); return; }
                final fullUrl = url.startsWith('http') ? url : '${ApiService.baseUrl}$url';
                final uri = Uri.parse(fullUrl);
                if (await canLaunchUrl(uri)) await launchUrl(uri, mode: LaunchMode.externalApplication);
              } catch (e) { _showSnack(e.toString(), error: true); }
            },
          ),
          IconButton(icon: const Icon(Icons.more_vert_rounded, size: 20), onPressed: () => _planActions(plan)),
        ]),
      ),
    );
  }
}
