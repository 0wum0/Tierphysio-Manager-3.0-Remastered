import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';

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
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.dashboard();
      setState(() { _data = data; _loading = false; });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  String _eur(dynamic v) => NumberFormat.currency(locale: 'de_DE', symbol: '€').format((v as num?)?.toDouble() ?? 0.0);

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: Text(_data?['company_name'] as String? ?? 'Dashboard'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _ErrorWidget(error: _error!, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.only(bottom: 24),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
                          child: Text('Übersicht', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                        ),
                        _StatsGrid(children: [
                          _StatCard(label: 'Patienten gesamt', value: '${_data?['patients_total'] ?? 0}', icon: Icons.pets, color: cs.primary),
                          _StatCard(label: 'Neue (30 Tage)', value: '${_data?['patients_new'] ?? 0}', icon: Icons.add_circle_outline, color: Colors.green),
                          _StatCard(label: 'Tierhalter', value: '${_data?['owners_total'] ?? 0}', icon: Icons.person, color: cs.secondary),
                          _StatCard(label: 'Termine heute', value: '${_data?['today_apts'] ?? 0}', icon: Icons.calendar_today, color: Colors.orange),
                        ]),
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
                          child: Text('Finanzen', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                        ),
                        _StatsGrid(children: [
                          _StatCard(label: 'Umsatz Monat', value: _eur(_data?['revenue_month']), icon: Icons.trending_up, color: Colors.green),
                          _StatCard(label: 'Umsatz Jahr', value: _eur(_data?['revenue_year']), icon: Icons.bar_chart, color: Colors.blue),
                          _StatCard(label: 'Offene Rechnungen', value: '${_data?['open_invoices'] ?? 0}', icon: Icons.receipt_long, color: Colors.orange, subtitle: _eur(_data?['open_amount'])),
                          _StatCard(label: 'Überfällig', value: '${_data?['overdue_invoices'] ?? 0}', icon: Icons.warning_amber, color: Colors.red, subtitle: _eur(_data?['overdue_amount'])),
                        ]),
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
                          child: Text('Termine', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                        ),
                        _StatsGrid(children: [
                          _StatCard(label: 'Heute', value: '${_data?['today_apts'] ?? 0}', icon: Icons.today, color: cs.primary),
                          _StatCard(label: 'Geplant', value: '${_data?['upcoming_apts'] ?? 0}', icon: Icons.event, color: Colors.indigo),
                        ]),
                      ],
                    ),
                  ),
                ),
    );
  }
}

class _StatsGrid extends StatelessWidget {
  final List<Widget> children;
  const _StatsGrid({required this.children});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      child: GridView.count(
        crossAxisCount: MediaQuery.of(context).size.width >= 600 ? 4 : 2,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        childAspectRatio: 1.5,
        children: children,
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final String? subtitle;

  const _StatCard({required this.label, required this.value, required this.icon, required this.color, this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                Icon(icon, color: color, size: 20),
                const SizedBox(width: 6),
                Expanded(child: Text(label, style: Theme.of(context).textTheme.bodySmall, overflow: TextOverflow.ellipsis)),
              ],
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(value, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold, color: color)),
                if (subtitle != null) Text(subtitle!, style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorWidget extends StatelessWidget {
  final String error;
  final VoidCallback onRetry;
  const _ErrorWidget({required this.error, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.red),
            const SizedBox(height: 12),
            Text(error, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: const Text('Erneut versuchen')),
          ],
        ),
      ),
    );
  }
}
