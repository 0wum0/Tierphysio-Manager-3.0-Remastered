import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import '../services/portal_api_service.dart';
import 'homework_detail_screen.dart';

class HomeworkScreen extends StatefulWidget {
  const HomeworkScreen({super.key});
  @override
  State<HomeworkScreen> createState() => _HomeworkScreenState();
}

class _HomeworkScreenState extends State<HomeworkScreen> {
  List<dynamic> _items = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final auth = context.read<PortalAuthService>();
      final data = await PortalApiService(token: auth.token).get('/api/mobile/portal/hausaufgaben');
      final raw  = data is List ? data : (data['plans'] ?? []);
      if (mounted) setState(() { _items = List<dynamic>.from(raw); _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Hausaufgaben')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _buildError()
              : RefreshIndicator(onRefresh: _load, child: _items.isEmpty
                  ? _buildEmpty()
                  : ListView.builder(
                      padding: const EdgeInsets.only(bottom: 24),
                      itemCount: _items.length,
                      itemBuilder: (_, i) => _HomeworkTile(plan: _items[i] as Map<String, dynamic>),
                    )),
    );
  }

  Widget _buildEmpty() => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(Icons.assignment_rounded, size: 64, color: AppTheme.success.withValues(alpha: 0.3)),
    const SizedBox(height: 16),
    const Text('Keine Hausaufgaben', style: TextStyle(fontWeight: FontWeight.w600)),
    const SizedBox(height: 8),
    Text('Übungspläne werden hier angezeigt.', style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
  ]));

  Widget _buildError() => Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
    const Icon(Icons.wifi_off_rounded, size: 48, color: AppTheme.danger),
    const SizedBox(height: 12),
    Text(_error!, textAlign: TextAlign.center),
    const SizedBox(height: 24),
    FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut')),
  ])));
}

class _HomeworkTile extends StatelessWidget {
  final Map<String, dynamic> plan;
  const _HomeworkTile({required this.plan});

  @override
  Widget build(BuildContext context) {
    final cs       = Theme.of(context).colorScheme;
    final exercises = List<dynamic>.from(plan['exercises'] ?? []);
    return Card(child: ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      leading: Container(width: 44, height: 44,
        decoration: BoxDecoration(color: AppTheme.success.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
        child: const Icon(Icons.assignment_rounded, color: AppTheme.success, size: 22)),
      title: Text(plan['name'] as String? ?? 'Übungsplan', style: const TextStyle(fontWeight: FontWeight.w700)),
      subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (plan['patient_name'] != null) ...[
          const SizedBox(height: 2),
          Text('Für: ${plan['patient_name']}', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13)),
        ],
        if (exercises.isNotEmpty) ...[
          const SizedBox(height: 2),
          Text('${exercises.length} Übung${exercises.length == 1 ? '' : 'en'}', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12)),
        ],
      ]),
      trailing: const Icon(Icons.chevron_right_rounded),
      onTap: () => Navigator.push(context, MaterialPageRoute(
        builder: (_) => HomeworkDetailScreen(planId: plan['id'] as int, planName: plan['name'] as String? ?? 'Übungsplan'))),
    ));
  }
}
