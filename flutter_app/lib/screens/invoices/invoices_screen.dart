import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';
import '../../widgets/search_bar_widget.dart';
import '../../widgets/animated_stat_card.dart';

class InvoicesScreen extends StatefulWidget {
  const InvoicesScreen({super.key});

  @override
  State<InvoicesScreen> createState() => _InvoicesScreenState();
}

class _InvoicesScreenState extends State<InvoicesScreen>
    with SingleTickerProviderStateMixin {
  final _api    = ApiService();
  List<dynamic> _items = [];
  bool  _loading = true;
  String? _error;
  String _search = '';
  String _status = '';
  int _page = 1;
  bool _hasNext = false;
  late TabController _tabCtrl;

  static const _statusFilters = [
    ('', 'Alle'),
    ('open', 'Offen'),
    ('paid', 'Bezahlt'),
    ('overdue', 'Überfällig'),
    ('draft', 'Entwurf'),
  ];

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 2, vsync: this);
    _load();
  }

  @override
  void dispose() { _tabCtrl.dispose(); super.dispose(); }

  Future<void> _load({bool reset = true}) async {
    if (reset) { _page = 1; _items = []; }
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.invoices(page: _page, status: _status, search: _search, perPage: 200);
      final items = List<dynamic>.from(data['items'] as List? ?? []);
      setState(() {
        _items   = reset ? items : [..._items, ...items];
        _hasNext = data['has_next'] as bool? ?? false;
        _loading = false;
      });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  static double _toDouble(dynamic v) =>
      v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0.0;

  String _eur(dynamic v) =>
      NumberFormat.currency(locale: 'de_DE', symbol: '€').format(_toDouble(v));

  // ── Stats from loaded items ──

  double get _totalRevenue =>
      _items.where((i) => i['status'] == 'paid').fold(0.0, (s, i) => s + _toDouble(i['total_gross']));
  double get _totalOpen =>
      _items.where((i) => i['status'] == 'open').fold(0.0, (s, i) => s + _toDouble(i['total_gross']));
  double get _totalOverdue =>
      _items.where((i) => i['status'] == 'overdue').fold(0.0, (s, i) => s + _toDouble(i['total_gross']));
  int get _countPaid    => _items.where((i) => i['status'] == 'paid').length;
  int get _countOpen    => _items.where((i) => i['status'] == 'open').length;
  int get _countOverdue => _items.where((i) => i['status'] == 'overdue').length;
  int get _countDraft   => _items.where((i) => i['status'] == 'draft').length;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Rechnungen'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load),
          IconButton(icon: const Icon(Icons.add_rounded),
              onPressed: () => context.push('/rechnungen/neu').then((_) => _load())),
        ],
        bottom: TabBar(
          controller: _tabCtrl,
          tabs: const [
            Tab(icon: Icon(Icons.list_alt_rounded, size: 18), text: 'Liste'),
            Tab(icon: Icon(Icons.bar_chart_rounded, size: 18), text: 'Analyse'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabCtrl,
        children: [
          _buildListTab(),
          _buildAnalyticsTab(),
        ],
      ),
    );
  }

  // ═══════════════════════════════════════ LIST TAB ═══════════════════════

  Widget _buildListTab() {
    return Column(children: [
      AppSearchBar(onSearch: (q) { _search = q; _load(); }, hint: 'Rechnung suchen…'),
      SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
        child: Row(children: _statusFilters.map((f) => Padding(
          padding: const EdgeInsets.only(right: 6),
          child: FilterChip(
            label: Text(f.$2),
            selected: _status == f.$1,
            onSelected: (_) { _status = f.$1; _load(); },
          ),
        )).toList()),
      ),
      Expanded(child: _buildList()),
    ]);
  }

  Widget _buildList() {
    if (_loading && _items.isEmpty) return const Center(child: CircularProgressIndicator());
    if (_error != null && _items.isEmpty) return Center(child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [Text(_error!), const SizedBox(height: 12), FilledButton(onPressed: _load, child: const Text('Erneut'))],
    ));
    if (_items.isEmpty) return const Center(child: Text('Keine Rechnungen gefunden.'));

    return RefreshIndicator(
      onRefresh: () => _load(),
      child: ListView.builder(
        itemCount: _items.length + (_hasNext ? 1 : 0),
        padding: const EdgeInsets.only(bottom: 16),
        itemBuilder: (ctx, i) {
          if (i == _items.length) return Padding(
            padding: const EdgeInsets.all(16),
            child: FilledButton.tonal(
                onPressed: () { _page++; _load(reset: false); },
                child: const Text('Mehr laden')),
          );
          final inv    = _items[i] as Map<String, dynamic>;
          final status = inv['status'] as String? ?? '';
          final isDark = Theme.of(context).brightness == Brightness.dark;
          final statusColor = _resolveStatusColor(status);
          return Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Material(
              color: isDark ? const Color(0xFF1A1D27) : Colors.white,
              borderRadius: BorderRadius.circular(16),
              child: InkWell(
                borderRadius: BorderRadius.circular(16),
                onTap: () => context.push('/rechnungen/${inv['id']}').then((_) => _load()),
                child: Container(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: isDark
                          ? Colors.white.withValues(alpha: 0.06)
                          : Colors.black.withValues(alpha: 0.06),
                    ),
                  ),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  child: Row(children: [
                    Container(
                      width: 44, height: 44,
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [statusColor, statusColor.withValues(alpha: 0.65)],
                          begin: Alignment.topLeft, end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(13),
                        boxShadow: [
                          BoxShadow(
                            color: statusColor.withValues(alpha: isDark ? 0.22 : 0.28),
                            blurRadius: 8, offset: const Offset(0, 3)),
                        ],
                      ),
                      child: Icon(_resolveStatusIcon(status), color: Colors.white, size: 20),
                    ),
                    const SizedBox(width: 14),
                    Expanded(child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(inv['invoice_number'] as String? ?? '',
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, height: 1.2)),
                        const SizedBox(height: 3),
                        Text(
                          '${inv['owner_name'] ?? ''}${inv['patient_name'] != null ? " · ${inv['patient_name']}" : ""}',
                          style: TextStyle(fontSize: 12,
                            color: Theme.of(context).colorScheme.onSurfaceVariant),
                          overflow: TextOverflow.ellipsis),
                      ],
                    )),
                    const SizedBox(width: 10),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(_eur(inv['total_gross']),
                          style: TextStyle(
                            fontWeight: FontWeight.w800, fontSize: 15,
                            color: isDark ? Colors.white : const Color(0xFF0F172A))),
                        const SizedBox(height: 4),
                        _StatusBadge(status: status),
                      ],
                    ),
                  ]),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  static Color _resolveStatusColor(String s) => switch (s) {
    'paid'    => AppTheme.success,
    'open'    => AppTheme.primary,
    'overdue' => AppTheme.danger,
    'draft'   => Colors.grey,
    _         => AppTheme.primary,
  };

  static IconData _resolveStatusIcon(String s) => switch (s) {
    'paid'    => Icons.check_circle_rounded,
    'open'    => Icons.pending_rounded,
    'overdue' => Icons.warning_rounded,
    'draft'   => Icons.edit_note_rounded,
    _         => Icons.receipt_long_rounded,
  };

  // ═══════════════════════════════════════ ANALYTICS TAB ═══════════════════

  Widget _buildAnalyticsTab() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_items.isEmpty) return const Center(child: Text('Keine Daten vorhanden.'));

    return RefreshIndicator(
      onRefresh: () => _load(),
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // ── Stat cards ──
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisSpacing: 10, mainAxisSpacing: 10,
            childAspectRatio: 1.6,
            children: [
              AnimatedStatCard(label: 'Bezahlt', value: _eur(_totalRevenue),
                  icon: Icons.check_circle_rounded, color: AppTheme.success,
                  sub: '$_countPaid Rechnungen'),
              AnimatedStatCard(label: 'Offen', value: _eur(_totalOpen),
                  icon: Icons.pending_rounded, color: AppTheme.primary,
                  sub: '$_countOpen Rechnungen'),
              AnimatedStatCard(label: 'Überfällig', value: _eur(_totalOverdue),
                  icon: Icons.warning_rounded, color: AppTheme.danger,
                  sub: '$_countOverdue Rechnungen'),
              AnimatedStatCard(label: 'Entwürfe', value: '$_countDraft',
                  icon: Icons.edit_note_rounded, color: Colors.grey,
                  sub: 'unveröffentlicht'),
            ],
          ),
          const SizedBox(height: 20),

          // ── Donut chart: status distribution ──
          _ChartCard(
            title: 'Status-Verteilung',
            subtitle: 'Anteil nach Betrag',
            child: _buildDonutChart(),
          ),
          const SizedBox(height: 16),

          // ── Bar chart: monthly revenue ──
          _ChartCard(
            title: 'Monatsumsatz',
            subtitle: 'Bezahlte Rechnungen nach Monat',
            child: _buildMonthlyBarChart(),
          ),
          const SizedBox(height: 16),

          // ── Top owners by revenue ──
          _ChartCard(
            title: 'Top Tierhalter',
            subtitle: 'Nach Gesamtumsatz',
            child: _buildTopOwnersChart(),
          ),
        ]),
      ),
    );
  }

  Widget _buildDonutChart() {
    final data = [
      if (_totalRevenue > 0)  PieChartSectionData(value: _totalRevenue,  color: AppTheme.success, title: '', radius: 48),
      if (_totalOpen > 0)     PieChartSectionData(value: _totalOpen,     color: AppTheme.primary, title: '', radius: 48),
      if (_totalOverdue > 0)  PieChartSectionData(value: _totalOverdue,  color: AppTheme.danger,  title: '', radius: 48),
      if (_countDraft > 0)    PieChartSectionData(value: _countDraft * 1.0, color: Colors.grey,   title: '', radius: 48),
    ];
    if (data.isEmpty) return const Center(child: Text('Keine Daten'));

    return Row(children: [
      Expanded(
        child: PieChart(PieChartData(
          sections: data,
          centerSpaceRadius: 44,
          sectionsSpace: 2,
        )),
      ),
      const SizedBox(width: 16),
      Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
        _Legend(color: AppTheme.success, label: 'Bezahlt',    value: _eur(_totalRevenue)),
        const SizedBox(height: 8),
        _Legend(color: AppTheme.primary, label: 'Offen',      value: _eur(_totalOpen)),
        const SizedBox(height: 8),
        _Legend(color: AppTheme.danger,  label: 'Überfällig', value: _eur(_totalOverdue)),
        if (_countDraft > 0) ...[
          const SizedBox(height: 8),
          _Legend(color: Colors.grey,    label: 'Entwurf',    value: '$_countDraft Stk.'),
        ],
      ]),
    ]);
  }

  Widget _buildMonthlyBarChart() {
    // Group paid invoices by month
    final Map<String, double> monthly = {};
    for (final inv in _items) {
      if (inv['status'] != 'paid') continue;
      final date = inv['issue_date'] as String? ?? '';
      if (date.length < 7) continue;
      final key = date.substring(0, 7); // YYYY-MM
      monthly[key] = (monthly[key] ?? 0) + _toDouble(inv['total_gross']);
    }
    if (monthly.isEmpty) return const Center(child: Text('Keine bezahlten Rechnungen'));

    final sorted = monthly.entries.toList()..sort((a, b) => a.key.compareTo(b.key));
    final last8 = sorted.length > 8 ? sorted.sublist(sorted.length - 8) : sorted;
    final maxY = last8.map((e) => e.value).reduce((a, b) => a > b ? a : b);

    return BarChart(BarChartData(
      maxY: maxY * 1.25,
      gridData: const FlGridData(show: true, drawVerticalLine: false),
      borderData: FlBorderData(show: false),
      titlesData: FlTitlesData(
        bottomTitles: AxisTitles(sideTitles: SideTitles(
          showTitles: true, reservedSize: 26,
          getTitlesWidget: (v, _) {
            final idx = v.toInt();
            if (idx < 0 || idx >= last8.length) return const SizedBox();
            final parts = last8[idx].key.split('-');
            return Text(parts.length >= 2 ? '${parts[1]}/${parts[0].substring(2)}' : '',
                style: const TextStyle(fontSize: 10));
          },
        )),
        leftTitles: AxisTitles(sideTitles: SideTitles(
          showTitles: true, reservedSize: 50,
          getTitlesWidget: (v, _) => Text(
            NumberFormat.compactCurrency(locale: 'de_DE', symbol: '€').format(v),
            style: const TextStyle(fontSize: 9)),
        )),
        rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
      ),
      barGroups: last8.asMap().entries.map((e) => BarChartGroupData(
        x: e.key,
        barRods: [BarChartRodData(
          toY: e.value.value,
          gradient: LinearGradient(
            begin: Alignment.bottomCenter, end: Alignment.topCenter,
            colors: [AppTheme.primary.withValues(alpha: 0.7), AppTheme.primary],
          ),
          width: 18, borderRadius: const BorderRadius.vertical(top: Radius.circular(6)),
        )],
      )).toList(),
    ));
  }

  Widget _buildTopOwnersChart() {
    final Map<String, double> byOwner = {};
    for (final inv in _items) {
      if (inv['status'] != 'paid') continue;
      final owner = inv['owner_name'] as String? ?? 'Unbekannt';
      byOwner[owner] = (byOwner[owner] ?? 0) + _toDouble(inv['total_gross']);
    }
    if (byOwner.isEmpty) return const Center(child: Text('Keine Daten'));

    final sorted = byOwner.entries.toList()..sort((a, b) => b.value.compareTo(a.value));
    final top5 = sorted.length > 5 ? sorted.sublist(0, 5) : sorted;
    final maxVal = top5.first.value;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: top5.asMap().entries.map((entry) {
        final frac = maxVal > 0 ? entry.value.value / maxVal : 0.0;
        final colors = [AppTheme.primary, AppTheme.secondary, AppTheme.tertiary, AppTheme.success, AppTheme.warning];
        final c = colors[entry.key % colors.length];
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Row(children: [
            SizedBox(width: 110,
              child: Text(entry.value.key, overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 12))),
            const SizedBox(width: 8),
            Expanded(child: ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: frac, minHeight: 16,
                backgroundColor: c.withValues(alpha: 0.1),
                valueColor: AlwaysStoppedAnimation(c),
              ),
            )),
            const SizedBox(width: 8),
            Text(_eur(entry.value.value), style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: c)),
          ]),
        );
      }).toList(),
    );
  }
}

class _ChartCard extends StatelessWidget {
  final String title, subtitle;
  final Widget child;
  const _ChartCard({required this.title, required this.subtitle, required this.child});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1A1D27) : Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isDark
              ? Colors.white.withValues(alpha: 0.07)
              : Colors.black.withValues(alpha: 0.06),
        ),
        boxShadow: isDark ? null : [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 12, offset: const Offset(0, 4)),
        ],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(padding: const EdgeInsets.fromLTRB(16, 14, 16, 12), child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
            const SizedBox(height: 1),
            Text(subtitle, style: TextStyle(fontSize: 12,
              color: Theme.of(context).colorScheme.onSurfaceVariant)),
          ],
        )),
        Divider(height: 1,
          color: isDark
              ? Colors.white.withValues(alpha: 0.06)
              : Colors.black.withValues(alpha: 0.05)),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
          child: SizedBox(height: 180, child: child)),
      ]),
    );
  }
}

class _Legend extends StatelessWidget {
  final Color color;
  final String label, value;
  const _Legend({required this.color, required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Row(mainAxisSize: MainAxisSize.min, children: [
      Container(width: 10, height: 10, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
      const SizedBox(width: 6),
      Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: Theme.of(context).textTheme.bodySmall?.copyWith(fontSize: 11)),
        Text(value, style: TextStyle(fontWeight: FontWeight.w700, color: color, fontSize: 12)),
      ]),
    ]);
  }
}

class _StatusBadge extends StatelessWidget {
  final String status;
  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'paid'    => ('Bezahlt',   Colors.green),
      'open'    => ('Offen',     Colors.blue),
      'overdue' => ('Überfällig',Colors.red),
      'draft'   => ('Entwurf',   Colors.grey),
      _         => (status,      Colors.grey),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(4)),
      child: Text(label, style: TextStyle(fontSize: 10, color: color, fontWeight: FontWeight.w600)),
    );
  }
}
