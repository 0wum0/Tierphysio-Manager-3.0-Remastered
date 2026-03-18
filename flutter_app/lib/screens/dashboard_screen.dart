import 'package:flutter/material.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';
import '../core/theme.dart';
import '../widgets/shimmer_list.dart';
import '../widgets/animated_stat_card.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final _api = ApiService();
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.dashboard();
      setState(() { _data = data; _loading = false; });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  static double _toDouble(dynamic v) =>
      v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0.0;

  String _eur(dynamic v) =>
      NumberFormat.currency(locale: 'de_DE', symbol: '€').format(_toDouble(v));

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_data?['company_name'] as String? ?? 'Dashboard'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load),
        ],
      ),
      body: _loading
          ? _buildShimmer()
          : _error != null
              ? _ErrorView(error: _error!, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _buildContent(),
                ),
    );
  }

  Widget _buildShimmer() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(children: [
        Row(children: [
          Expanded(child: ShimmerBox(width: double.infinity, height: 100, radius: 16)),
          const SizedBox(width: 12),
          Expanded(child: ShimmerBox(width: double.infinity, height: 100, radius: 16)),
        ]),
        const SizedBox(height: 12),
        Row(children: [
          Expanded(child: ShimmerBox(width: double.infinity, height: 100, radius: 16)),
          const SizedBox(width: 12),
          Expanded(child: ShimmerBox(width: double.infinity, height: 100, radius: 16)),
        ]),
        const SizedBox(height: 20),
        ShimmerBox(width: double.infinity, height: 220, radius: 16),
        const SizedBox(height: 12),
        ShimmerBox(width: double.infinity, height: 180, radius: 16),
      ]),
    );
  }

  Widget _buildContent() {
    final w = MediaQuery.of(context).size.width;
    final isTablet = w >= 600;
    final d = _data!;

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.symmetric(horizontal: isTablet ? 24 : 16, vertical: 16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Greeting
        _greeting(d),
        const SizedBox(height: 20),

        // Stats row
        _buildStatsRow(isTablet, d),
        const SizedBox(height: 20),

        // Finance + Appointments row (tablet: side by side)
        if (isTablet)
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(child: _revenueChart(d)),
            const SizedBox(width: 16),
            Expanded(child: _appointmentDonut(d)),
          ])
        else ...[
          _revenueChart(d),
          const SizedBox(height: 16),
          _appointmentDonut(d),
        ],

        const SizedBox(height: 16),
        _invoiceStats(d),
        const SizedBox(height: 80),
      ]),
    );
  }

  Widget _greeting(Map<String, dynamic> d) {
    final hour = DateTime.now().hour;
    final greeting = hour < 12 ? 'Guten Morgen' : hour < 18 ? 'Guten Tag' : 'Guten Abend';
    return Row(children: [
      Expanded(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(greeting, style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          )),
          const SizedBox(height: 2),
          Text(
            d['user_name'] as String? ?? 'Willkommen',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w800, letterSpacing: -0.5,
            ),
          ),
        ]),
      ),
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: AppTheme.primary.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(Icons.calendar_today_rounded, size: 14, color: AppTheme.primary),
          const SizedBox(width: 6),
          Text(
            DateFormat('d. MMM', 'de_DE').format(DateTime.now()),
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.primary),
          ),
        ]),
      ),
    ]);
  }

  Widget _buildStatsRow(bool isTablet, Map<String, dynamic> d) {
    final cards = [
      AnimatedStatCard(label: 'Patienten', value: '${d['patients_total'] ?? 0}',
          icon: Icons.pets_rounded, color: AppTheme.primary,
          sub: '+${d['patients_new'] ?? 0} neu'),
      AnimatedStatCard(label: 'Tierhalter', value: '${d['owners_total'] ?? 0}',
          icon: Icons.person_rounded, color: AppTheme.secondary),
      AnimatedStatCard(label: 'Heute', value: '${d['today_apts'] ?? 0}',
          icon: Icons.today_rounded, color: AppTheme.tertiary,
          sub: 'Termine'),
      AnimatedStatCard(label: 'Ausstehend', value: '${d['upcoming_apts'] ?? 0}',
          icon: Icons.event_rounded, color: AppTheme.warning,
          sub: 'geplant'),
    ];
    return GridView.count(
      crossAxisCount: isTablet ? 4 : 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisSpacing: 12,
      mainAxisSpacing: 12,
      childAspectRatio: isTablet ? 1.4 : 1.5,
      padding: EdgeInsets.zero,
      children: cards,
    );
  }

  Widget _revenueChart(Map<String, dynamic> d) {
    final months = List<Map<String, dynamic>>.from(d['monthly_revenue'] as List? ?? []);
    final bars = months.isEmpty
        ? List.generate(6, (i) => BarChartGroupData(x: i, barRods: [
            BarChartRodData(toY: 0, width: 14, borderRadius: BorderRadius.circular(6)),
          ]))
        : months.asMap().entries.map((e) {
            final rev = _toDouble(e.value['revenue']);
            return BarChartGroupData(x: e.key, barRods: [
              BarChartRodData(
                toY: rev,
                width: 14,
                borderRadius: BorderRadius.circular(6),
                gradient: LinearGradient(
                  colors: [AppTheme.primary, AppTheme.secondary],
                  begin: Alignment.bottomCenter,
                  end: Alignment.topCenter,
                ),
              ),
            ]);
          }).toList();

    final maxY = months.isEmpty ? 1000.0
        : (months.map((m) => _toDouble(m['revenue'])).reduce((a, b) => a > b ? a : b) * 1.2).clamp(100.0, double.infinity);

    return _ChartCard(
      title: 'Umsatz (6 Monate)',
      subtitle: _eur(d['revenue_month']) + ' diesen Monat',
      child: BarChart(
        BarChartData(
          maxY: maxY,
          gridData: FlGridData(
            show: true,
            drawVerticalLine: false,
            getDrawingHorizontalLine: (_) => FlLine(
              color: Theme.of(context).dividerColor,
              strokeWidth: 0.5,
            ),
          ),
          borderData: FlBorderData(show: false),
          titlesData: FlTitlesData(
            leftTitles: AxisTitles(sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 46,
              getTitlesWidget: (v, _) => Text(
                NumberFormat.compactCurrency(locale: 'de', symbol: '€').format(v),
                style: const TextStyle(fontSize: 10),
              ),
            )),
            bottomTitles: AxisTitles(sideTitles: SideTitles(
              showTitles: true,
              getTitlesWidget: (v, _) {
                if (months.isEmpty) return const Text('');
                final idx = v.toInt();
                if (idx < 0 || idx >= months.length) return const Text('');
                final label = months[idx]['month'] as String? ?? '';
                return Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(label.length > 3 ? label.substring(0, 3) : label,
                      style: const TextStyle(fontSize: 10)),
                );
              },
            )),
            topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
            rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          ),
          barGroups: bars,
          barTouchData: BarTouchData(
            touchTooltipData: BarTouchTooltipData(
              getTooltipItem: (group, _, rod, __) => BarTooltipItem(
                _eur(rod.toY),
                const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _appointmentDonut(Map<String, dynamic> d) {
    final today    = _toDouble(d['today_apts']);
    final upcoming = _toDouble(d['upcoming_apts']);
    final total  = today + upcoming;

    final sections = total == 0
        ? [PieChartSectionData(value: 1, color: Colors.grey.shade200, radius: 40, title: '')]
        : [
            PieChartSectionData(value: today, color: AppTheme.primary, radius: 44,
                title: today > 0 ? today.toInt().toString() : '',
                titleStyle: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
            PieChartSectionData(value: upcoming, color: AppTheme.tertiary, radius: 40,
                title: upcoming > 0 ? upcoming.toInt().toString() : '',
                titleStyle: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
          ];

    return _ChartCard(
      title: 'Termine',
      subtitle: '${total.toInt()} gesamt',
      child: Row(children: [
        SizedBox(
          height: 140,
          width: 140,
          child: PieChart(PieChartData(
            sections: sections,
            centerSpaceRadius: 32,
            sectionsSpace: 3,
          )),
        ),
        const SizedBox(width: 20),
        Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.start, children: [
          _Legend(color: AppTheme.primary, label: 'Heute', value: today.toInt().toString()),
          const SizedBox(height: 10),
          _Legend(color: AppTheme.tertiary, label: 'Geplant', value: upcoming.toInt().toString()),
        ]),
      ]),
    );
  }

  Widget _invoiceStats(Map<String, dynamic> d) {
    return _ChartCard(
      title: 'Rechnungen',
      subtitle: _eur(d['revenue_year']) + ' Jahresumsatz',
      child: Row(children: [
        Expanded(child: _InvoiceStat(
          label: 'Offen', count: '${d['open_invoices'] ?? 0}',
          amount: _eur(d['open_amount']), color: AppTheme.primary,
        )),
        Container(width: 1, height: 60, color: Theme.of(context).dividerColor),
        Expanded(child: _InvoiceStat(
          label: 'Überfällig', count: '${d['overdue_invoices'] ?? 0}',
          amount: _eur(d['overdue_amount']), color: AppTheme.danger,
        )),
        Container(width: 1, height: 60, color: Theme.of(context).dividerColor),
        Expanded(child: _InvoiceStat(
          label: 'Jahresumsatz', count: '', amount: _eur(d['revenue_year']), color: AppTheme.success,
        )),
      ]),
    );
  }
}

// ── Sub-widgets ─────────────────────────────────────────────────────────────

class _ChartCard extends StatelessWidget {
  final String title, subtitle;
  final Widget child;
  const _ChartCard({required this.title, required this.subtitle, required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Theme.of(context).dividerColor),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(title, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
        Text(subtitle, style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        )),
        const SizedBox(height: 16),
        SizedBox(height: 160, child: child),
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
      const SizedBox(width: 8),
      Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: Theme.of(context).textTheme.bodySmall),
        Text(value, style: TextStyle(fontWeight: FontWeight.w700, color: color, fontSize: 16)),
      ]),
    ]);
  }
}

class _InvoiceStat extends StatelessWidget {
  final String label, count, amount;
  final Color color;
  const _InvoiceStat({required this.label, required this.count, required this.amount, required this.color});

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      if (count.isNotEmpty) Text(count, style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: color)),
      Text(amount, style: TextStyle(fontSize: count.isEmpty ? 14 : 11, fontWeight: FontWeight.w600, color: color)),
      const SizedBox(height: 2),
      Text(label, style: Theme.of(context).textTheme.bodySmall),
    ]);
  }
}

class _ErrorView extends StatelessWidget {
  final String error;
  final VoidCallback onRetry;
  const _ErrorView({required this.error, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: AppTheme.danger.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(Icons.error_outline_rounded, size: 40, color: AppTheme.danger),
        ),
        const SizedBox(height: 16),
        Text('Fehler', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        Text(error, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyMedium),
        const SizedBox(height: 24),
        FilledButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut versuchen')),
      ]),
    ));
  }
}
