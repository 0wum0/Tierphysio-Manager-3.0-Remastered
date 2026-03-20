import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class DunningsScreen extends StatefulWidget {
  const DunningsScreen({super.key});
  @override
  State<DunningsScreen> createState() => _DunningsScreenState();
}

class _DunningsScreenState extends State<DunningsScreen>
    with SingleTickerProviderStateMixin {
  final _api = ApiService();
  late TabController _tabs;

  List<Map<String, dynamic>> _dunnings = [];
  List<Map<String, dynamic>> _overdue  = [];
  bool _loadingD = true, _loadingO = true;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _loadDunnings();
    _loadOverdue();
  }

  @override
  void dispose() { _tabs.dispose(); super.dispose(); }

  Future<void> _loadDunnings() async {
    setState(() => _loadingD = true);
    try {
      final data = await _api.dunningsList();
      setState(() { _dunnings = data.map((e) => Map<String, dynamic>.from(e as Map)).toList(); _loadingD = false; });
    } catch (_) { setState(() => _loadingD = false); }
  }

  Future<void> _loadOverdue() async {
    setState(() => _loadingO = true);
    try {
      final data = await _api.overdueAlerts();
      setState(() { _overdue = data.map((e) => Map<String, dynamic>.from(e as Map)).toList(); _loadingO = false; });
    } catch (_) { setState(() => _loadingO = false); }
  }

  String _fmt(String? d) {
    if (d == null) return '—';
    try { return DateFormat('dd.MM.yyyy', 'de_DE').format(DateTime.parse(d)); } catch (_) { return d; }
  }

  String _money(dynamic v) {
    final n = double.tryParse(v?.toString() ?? '0') ?? 0;
    return NumberFormat.currency(locale: 'de_DE', symbol: '€').format(n);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Mahnungen & Überfällig'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: () { _loadDunnings(); _loadOverdue(); }),
        ],
        bottom: TabBar(
          controller: _tabs,
          tabs: [
            Tab(text: 'Mahnungen', icon: Badge(
              isLabelVisible: _dunnings.isNotEmpty,
              label: Text('${_dunnings.length}'),
              backgroundColor: AppTheme.danger,
              child: const Icon(Icons.warning_amber_rounded, size: 16),
            )),
            Tab(text: 'Überfällig', icon: Badge(
              isLabelVisible: _overdue.isNotEmpty,
              label: Text('${_overdue.length}'),
              backgroundColor: AppTheme.warning,
              child: const Icon(Icons.schedule_rounded, size: 16),
            )),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: [
          _buildDunnings(),
          _buildOverdue(),
        ],
      ),
    );
  }

  Widget _buildDunnings() {
    if (_loadingD) return const Center(child: CircularProgressIndicator());
    if (_dunnings.isEmpty) return _empty('Keine Mahnungen vorhanden', Icons.check_circle_outline_rounded, AppTheme.success);
    return RefreshIndicator(
      onRefresh: _loadDunnings,
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: _dunnings.length,
        separatorBuilder: (_, __) => const SizedBox(height: 10),
        itemBuilder: (ctx, i) {
          final d = _dunnings[i];
          final level = d['level'] as int? ?? 1;
          final color = level >= 3 ? AppTheme.danger : level == 2 ? AppTheme.warning : AppTheme.tertiary;
          return Container(
            decoration: BoxDecoration(
              color: Theme.of(context).cardTheme.color,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: color.withValues(alpha: 0.3)),
            ),
            child: ListTile(
              contentPadding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
              leading: Container(
                width: 42, height: 42,
                decoration: BoxDecoration(color: color.withValues(alpha: 0.12), shape: BoxShape.circle),
                child: Center(child: Text('M$level', style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 13))),
              ),
              title: Text(d['invoice_number'] as String? ?? '—',
                style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                if (d['patient_name'] != null)
                  Text(d['patient_name'] as String, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                Text('Gesendet: ${_fmt(d['sent_at'] as String?)}',
                  style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
              ]),
              trailing: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Text(_money(d['amount']), style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 14)),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
                  child: Text('Stufe $level', style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w600)),
                ),
              ]),
              onTap: d['invoice_id'] != null
                  ? () => context.push('/rechnungen/${d['invoice_id']}')
                  : null,
            ),
          );
        },
      ),
    );
  }

  Widget _buildOverdue() {
    if (_loadingO) return const Center(child: CircularProgressIndicator());
    if (_overdue.isEmpty) return _empty('Keine überfälligen Rechnungen', Icons.check_circle_outline_rounded, AppTheme.success);
    return RefreshIndicator(
      onRefresh: _loadOverdue,
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: _overdue.length,
        separatorBuilder: (_, __) => const SizedBox(height: 10),
        itemBuilder: (ctx, i) {
          final inv = _overdue[i];
          final overdueDays = inv['overdue_days'] as int? ?? 0;
          final color = overdueDays > 60 ? AppTheme.danger : overdueDays > 30 ? AppTheme.warning : AppTheme.tertiary;
          return Container(
            decoration: BoxDecoration(
              color: Theme.of(context).cardTheme.color,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: color.withValues(alpha: 0.3)),
            ),
            child: ListTile(
              contentPadding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
              leading: Container(
                width: 42, height: 42,
                decoration: BoxDecoration(color: color.withValues(alpha: 0.12), shape: BoxShape.circle),
                child: Icon(Icons.schedule_rounded, color: color, size: 22),
              ),
              title: Text(inv['invoice_number'] as String? ?? '—',
                style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                if (inv['patient_name'] != null)
                  Text(inv['patient_name'] as String, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                Row(children: [
                  Icon(Icons.calendar_today_rounded, size: 11, color: Colors.grey.shade500),
                  const SizedBox(width: 4),
                  Text('Fällig: ${_fmt(inv['due_date'] as String?)}',
                    style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
                ]),
              ]),
              trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                Text(_money(inv['total']), style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 14)),
                Text('$overdueDays Tage', style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
              ]),
              onTap: () => context.push('/rechnungen/${inv['id']}'),
            ),
          );
        },
      ),
    );
  }

  Widget _empty(String msg, IconData icon, Color color) => Center(child: Column(
    mainAxisAlignment: MainAxisAlignment.center,
    children: [
      Icon(icon, size: 72, color: color.withValues(alpha: 0.4)),
      const SizedBox(height: 16),
      Text(msg, style: TextStyle(color: Colors.grey.shade500, fontSize: 16)),
    ],
  ));
}
