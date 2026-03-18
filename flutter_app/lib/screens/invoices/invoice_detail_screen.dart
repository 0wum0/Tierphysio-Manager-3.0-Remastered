import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';

class InvoiceDetailScreen extends StatefulWidget {
  final int id;
  const InvoiceDetailScreen({super.key, required this.id});

  @override
  State<InvoiceDetailScreen> createState() => _InvoiceDetailScreenState();
}

class _InvoiceDetailScreenState extends State<InvoiceDetailScreen> {
  final _api = ApiService();
  Map<String, dynamic>? _invoice;
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.invoiceShow(widget.id);
      setState(() { _invoice = data; _loading = false; });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  String _eur(dynamic v) {
    final d = v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0.0;
    return NumberFormat.currency(locale: 'de_DE', symbol: '€').format(d);
  }
  String _date(String? d) {
    if (d == null || d.isEmpty) return '—';
    try { return DateFormat('dd.MM.yyyy').format(DateTime.parse(d)); } catch (_) { return d; }
  }

  Future<void> _changeStatus() async {
    final current = _invoice!['status'] as String? ?? '';
    final options = <String, String>{
      'draft':   'Entwurf',
      'open':    'Offen',
      'paid':    'Bezahlt',
      'overdue': 'Überfällig',
    };
    final selected = await showDialog<String>(
      context: context,
      builder: (_) => SimpleDialog(
        title: const Text('Status ändern'),
        children: options.entries.map((e) => SimpleDialogOption(
          onPressed: () => Navigator.pop(context, e.key),
          child: Row(children: [
            if (e.key == current) const Icon(Icons.check, size: 16),
            if (e.key != current) const SizedBox(width: 16),
            const SizedBox(width: 8),
            Text(e.value),
          ]),
        )).toList(),
      ),
    );
    if (selected == null || selected == current) return;
    try {
      await _api.invoiceUpdateStatus(widget.id, selected);
      _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_invoice?['invoice_number'] as String? ?? 'Rechnung'),
        actions: [
          if (_invoice != null)
            IconButton(icon: const Icon(Icons.swap_horiz), tooltip: 'Status ändern', onPressed: _changeStatus),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Text(_error!), const SizedBox(height: 12), FilledButton(onPressed: _load, child: const Text('Erneut')),
                ]))
              : _buildBody(),
    );
  }

  Widget _buildBody() {
    final inv = _invoice!;
    final positions = List<dynamic>.from(inv['positions'] as List? ?? []);
    final cs = Theme.of(context).colorScheme;

    return SingleChildScrollView(
      padding: const EdgeInsets.only(bottom: 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Card(
            margin: const EdgeInsets.all(16),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(inv['invoice_number'] as String? ?? '',
                          style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                      _StatusBadge(status: inv['status'] as String? ?? ''),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _Row('Tierhalter', inv['owner_name'] as String? ?? '—'),
                  if (inv['patient_name'] != null) _Row('Patient', inv['patient_name'] as String),
                  _Row('Rechnungsdatum', _date(inv['issue_date'] as String?)),
                  _Row('Fällig am', _date(inv['due_date'] as String?)),
                  if ((inv['notes'] as String? ?? '').isNotEmpty) _Row('Notizen', inv['notes'] as String),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Text('Positionen', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
          ),
          Card(
            margin: const EdgeInsets.symmetric(horizontal: 16),
            child: Column(
              children: [
                ...positions.asMap().entries.map((entry) {
                  final i = entry.key;
                  final p = entry.value as Map<String, dynamic>;
                  return Column(
                    children: [
                      if (i > 0) const Divider(height: 1),
                      Padding(
                        padding: const EdgeInsets.all(12),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(p['description'] as String? ?? '', style: const TextStyle(fontWeight: FontWeight.w500)),
                                  Text('${p['quantity']} × ${_eur(p['unit_price'])} · MwSt. ${p['tax_rate']}%',
                                      style: Theme.of(context).textTheme.bodySmall?.copyWith(color: cs.onSurfaceVariant)),
                                ],
                              ),
                            ),
                            Text(_eur(p['total']), style: const TextStyle(fontWeight: FontWeight.bold)),
                          ],
                        ),
                      ),
                    ],
                  );
                }),
                const Divider(height: 1),
                Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    children: [
                      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                        Text('Netto', style: Theme.of(context).textTheme.bodySmall),
                        Text(_eur(inv['total_net'])),
                      ]),
                      const SizedBox(height: 4),
                      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                        Text('MwSt.', style: Theme.of(context).textTheme.bodySmall),
                        Text(_eur(inv['total_tax'])),
                      ]),
                      const Divider(height: 12),
                      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                        Text('Gesamt', style: const TextStyle(fontWeight: FontWeight.bold)),
                        Text(_eur(inv['total_gross']), style: TextStyle(fontWeight: FontWeight.bold, color: cs.primary, fontSize: 16)),
                      ]),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _Row(String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3),
    child: Row(children: [
      SizedBox(width: 120, child: Text(label, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant))),
      Expanded(child: Text(value)),
    ]),
  );
}

class _StatusBadge extends StatelessWidget {
  final String status;
  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'paid'    => ('Bezahlt',    Colors.green),
      'open'    => ('Offen',      Colors.blue),
      'overdue' => ('Überfällig', Colors.red),
      'draft'   => ('Entwurf',    Colors.grey),
      _         => (status,       Colors.grey),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(6)),
      child: Text(label, style: TextStyle(color: color, fontWeight: FontWeight.bold)),
    );
  }
}
