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
          final isDark = Theme.of(context).brightness == Brightness.dark;
          return Material(
            color: isDark ? const Color(0xFF1A1D27) : Colors.white,
            borderRadius: BorderRadius.circular(16),
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: d['invoice_id'] != null ? () => context.push('/rechnungen/${d['invoice_id']}') : null,
              child: Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: color.withValues(alpha: isDark ? 0.28 : 0.20)),
                ),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                child: Row(children: [
                  Container(
                    width: 46, height: 46,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [color, color.withValues(alpha: 0.65)],
                        begin: Alignment.topLeft, end: Alignment.bottomRight,
                      ),
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: color.withValues(alpha: isDark ? 0.22 : 0.28),
                          blurRadius: 8, offset: const Offset(0, 3)),
                      ],
                    ),
                    alignment: Alignment.center,
                    child: Text('M$level',
                      style: const TextStyle(
                        color: Colors.white, fontWeight: FontWeight.w900, fontSize: 14)),
                  ),
                  const SizedBox(width: 14),
                  Expanded(child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(d['invoice_number'] as String? ?? '—',
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, height: 1.2)),
                      if (d['patient_name'] != null) ...[const SizedBox(height: 2),
                        Row(children: [
                          Icon(Icons.pets_rounded, size: 11,
                            color: Theme.of(context).colorScheme.onSurfaceVariant.withValues(alpha: 0.6)),
                          const SizedBox(width: 3),
                          Text(d['patient_name'] as String,
                            style: TextStyle(fontSize: 12,
                              color: Theme.of(context).colorScheme.onSurfaceVariant)),
                        ]),
                      ],
                      const SizedBox(height: 2),
                      Row(children: [
                        Icon(Icons.send_rounded, size: 11,
                          color: Theme.of(context).colorScheme.onSurfaceVariant.withValues(alpha: 0.5)),
                        const SizedBox(width: 3),
                        Text('Gesendet: ${_fmt(d['sent_at'] as String?)}',
                          style: TextStyle(fontSize: 11,
                            color: Theme.of(context).colorScheme.onSurfaceVariant.withValues(alpha: 0.6))),
                      ]),
                    ],
                  )),
                  const SizedBox(width: 10),
                  Column(crossAxisAlignment: CrossAxisAlignment.end, mainAxisAlignment: MainAxisAlignment.center, children: [
                    Text(_money(d['total_gross'] ?? d['amount']),
                      style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 15)),
                    const SizedBox(height: 4),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: color.withValues(alpha: isDark ? 0.18 : 0.10),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: color.withValues(alpha: 0.20))),
                      child: Text('Stufe $level',
                        style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w700)),
                    ),
                  ]),
                ]),
              ),
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
          final isDark2 = Theme.of(context).brightness == Brightness.dark;
          return Material(
            color: isDark2 ? const Color(0xFF1A1D27) : Colors.white,
            borderRadius: BorderRadius.circular(16),
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => context.push('/rechnungen/${inv['id']}'),
              child: Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: color.withValues(alpha: isDark2 ? 0.28 : 0.20)),
                ),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                child: Row(children: [
                  Container(
                    width: 46, height: 46,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [color, color.withValues(alpha: 0.65)],
                        begin: Alignment.topLeft, end: Alignment.bottomRight,
                      ),
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: color.withValues(alpha: isDark2 ? 0.22 : 0.28),
                          blurRadius: 8, offset: const Offset(0, 3)),
                      ],
                    ),
                    child: const Icon(Icons.schedule_rounded, color: Colors.white, size: 22),
                  ),
                  const SizedBox(width: 14),
                  Expanded(child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(inv['invoice_number'] as String? ?? '—',
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, height: 1.2)),
                      if (inv['patient_name'] != null) ...[const SizedBox(height: 2),
                        Row(children: [
                          Icon(Icons.pets_rounded, size: 11,
                            color: Theme.of(context).colorScheme.onSurfaceVariant.withValues(alpha: 0.6)),
                          const SizedBox(width: 3),
                          Text(inv['patient_name'] as String,
                            style: TextStyle(fontSize: 12,
                              color: Theme.of(context).colorScheme.onSurfaceVariant)),
                        ]),
                      ],
                      const SizedBox(height: 2),
                      Row(children: [
                        Icon(Icons.calendar_today_rounded, size: 11,
                          color: Theme.of(context).colorScheme.onSurfaceVariant.withValues(alpha: 0.5)),
                        const SizedBox(width: 3),
                        Text('Fällig: ${_fmt(inv['due_date'] as String?)}',
                          style: TextStyle(fontSize: 11,
                            color: Theme.of(context).colorScheme.onSurfaceVariant.withValues(alpha: 0.6))),
                      ]),
                    ],
                  )),
                  const SizedBox(width: 10),
                  Column(crossAxisAlignment: CrossAxisAlignment.end, mainAxisAlignment: MainAxisAlignment.center, children: [
                    Text(_money(inv['total_gross'] ?? inv['total']),
                      style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 15)),
                    const SizedBox(height: 4),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: color.withValues(alpha: isDark2 ? 0.18 : 0.10),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: color.withValues(alpha: 0.20))),
                      child: Text('$overdueDays Tage',
                        style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w700)),
                    ),
                  ]),
                ]),
              ),
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
