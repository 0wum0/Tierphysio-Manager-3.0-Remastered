import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class HomeworkScreen extends StatefulWidget {
  const HomeworkScreen({super.key});
  @override
  State<HomeworkScreen> createState() => _HomeworkScreenState();
}

class _HomeworkScreenState extends State<HomeworkScreen> {
  final _api = ApiService();
  List<dynamic> _plans = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final plans = await _api.homeworkPlanList();
      setState(() { _plans = plans; _loading = false; });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  String _fmt(String? d) {
    if (d == null) return '—';
    try { return DateFormat('dd.MM.yyyy', 'de_DE').format(DateTime.parse(d)); }
    catch (_) { return d; }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Hausaufgaben'),
        actions: [
          IconButton(
            icon: const Icon(Icons.add_rounded),
            tooltip: 'Neuer Hausaufgabenplan',
            onPressed: () => _showCreateDialog(context),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _buildError()
              : _plans.isEmpty
                  ? _buildEmpty(cs)
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: _plans.length,
                        itemBuilder: (_, i) => _PlanCard(
                          plan: Map<String, dynamic>.from(_plans[i] as Map),
                          fmt: _fmt,
                          onTap: () => context.push(
                            '/portal-admin/hausaufgabenplan/${_plans[i]['id']}',
                          ),
                        ),
                      ),
                    ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showCreateDialog(context),
        icon: const Icon(Icons.add_rounded),
        label: const Text('Neuer Plan'),
        backgroundColor: AppTheme.primary,
        foregroundColor: Colors.white,
      ),
    );
  }

  Widget _buildError() => Center(
    child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.error_outline_rounded, size: 48, color: AppTheme.danger),
        const SizedBox(height: 12),
        Text(_error!, textAlign: TextAlign.center),
        const SizedBox(height: 16),
        FilledButton.icon(
          onPressed: _load,
          icon: const Icon(Icons.refresh_rounded),
          label: const Text('Erneut versuchen'),
        ),
      ],
    ),
  );

  Widget _buildEmpty(ColorScheme cs) => Center(
    child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.assignment_outlined, size: 64,
            color: cs.onSurface.withValues(alpha: 0.2)),
        const SizedBox(height: 16),
        Text('Noch keine Hausaufgabenpläne',
            style: TextStyle(fontSize: 17, fontWeight: FontWeight.w600,
                color: cs.onSurface.withValues(alpha: 0.5))),
        const SizedBox(height: 8),
        Text('Erstelle den ersten Plan für einen Patienten.',
            style: TextStyle(color: cs.onSurface.withValues(alpha: 0.4))),
      ],
    ),
  );

  Future<void> _showCreateDialog(BuildContext context) async {
    // Navigate to portal admin where plans are created
    context.push('/portal-admin');
  }
}

class _PlanCard extends StatelessWidget {
  final Map<String, dynamic> plan;
  final String Function(String?) fmt;
  final VoidCallback onTap;

  const _PlanCard({required this.plan, required this.fmt, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final cs      = Theme.of(context).colorScheme;
    final isDark  = cs.brightness == Brightness.dark;
    final name    = plan['patient_name']  as String? ?? '—';
    final date    = fmt(plan['plan_date'] as String?);
    final status  = plan['status']        as String? ?? 'active';
    final tasks   = (plan['exercises'] as List?)?.length ?? 0;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: isDark ? const Color(0xFF1A1D27) : Colors.white,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: isDark
                    ? Colors.white.withValues(alpha: 0.07)
                    : Colors.black.withValues(alpha: 0.06),
              ),
            ),
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              Container(
                width: 48, height: 48,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [AppTheme.primary, AppTheme.secondary],
                    begin: Alignment.topLeft, end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [
                    BoxShadow(
                      color: AppTheme.primary.withValues(alpha: isDark ? 0.25 : 0.30),
                      blurRadius: 10, offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: const Icon(Icons.assignment_rounded,
                    color: Colors.white, size: 24),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name,
                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, height: 1.2)),
                    const SizedBox(height: 3),
                    Row(children: [
                      Icon(Icons.calendar_today_rounded, size: 11,
                        color: cs.onSurface.withValues(alpha: 0.45)),
                      const SizedBox(width: 4),
                      Text('Plan vom $date',
                        style: TextStyle(fontSize: 12,
                          color: cs.onSurface.withValues(alpha: 0.55))),
                    ]),
                    if (tasks > 0) ...[
                      const SizedBox(height: 3),
                      Row(children: [
                        Icon(Icons.checklist_rounded, size: 11,
                          color: AppTheme.success.withValues(alpha: 0.7)),
                        const SizedBox(width: 4),
                        Text('$tasks Aufgabe${tasks == 1 ? '' : 'n'}',
                          style: TextStyle(fontSize: 11,
                            color: cs.onSurface.withValues(alpha: 0.45))),
                      ]),
                    ],
                  ],
                ),
              ),
              _StatusChip(status: status),
              const SizedBox(width: 8),
              Icon(Icons.chevron_right_rounded, size: 20,
                color: cs.onSurface.withValues(alpha: 0.3)),
            ]),
          ),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String status;
  const _StatusChip({required this.status});

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'active'   => ('Aktiv',    AppTheme.success),
      'archived' => ('Archiv',   AppTheme.warning),
      _          => ('Unbekannt', AppTheme.warning),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(label,
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: color)),
    );
  }
}
