import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../widgets/search_bar_widget.dart';

class InvoicesScreen extends StatefulWidget {
  const InvoicesScreen({super.key});

  @override
  State<InvoicesScreen> createState() => _InvoicesScreenState();
}

class _InvoicesScreenState extends State<InvoicesScreen> {
  final _api    = ApiService();
  List<dynamic> _items = [];
  bool  _loading = true;
  String? _error;
  String _search = '';
  String _status = '';
  int _page = 1;
  bool _hasNext = false;

  static const _statusFilters = [
    ('', 'Alle'),
    ('open', 'Offen'),
    ('paid', 'Bezahlt'),
    ('overdue', 'Überfällig'),
    ('draft', 'Entwurf'),
  ];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load({bool reset = true}) async {
    if (reset) { _page = 1; _items = []; }
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.invoices(page: _page, status: _status, search: _search);
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

  String _eur(dynamic v) => NumberFormat.currency(locale: 'de_DE', symbol: '€').format((v as num?)?.toDouble() ?? 0.0);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Rechnungen'),
        actions: [IconButton(icon: const Icon(Icons.add), onPressed: () => context.push('/rechnungen/neu').then((_) => _load()))],
      ),
      body: Column(
        children: [
          AppSearchBar(onSearch: (q) { _search = q; _load(); }, hint: 'Rechnung suchen…'),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            child: Row(
              children: _statusFilters.map((f) => Padding(
                padding: const EdgeInsets.only(right: 6),
                child: FilterChip(
                  label: Text(f.$2),
                  selected: _status == f.$1,
                  onSelected: (_) { _status = f.$1; _load(); },
                ),
              )).toList(),
            ),
          ),
          Expanded(child: _buildList()),
        ],
      ),
    );
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
        itemBuilder: (ctx, i) {
          if (i == _items.length) return Padding(
            padding: const EdgeInsets.all(16),
            child: FilledButton.tonal(onPressed: () { _page++; _load(reset: false); }, child: const Text('Mehr laden')),
          );
          final inv = _items[i] as Map<String, dynamic>;
          return ListTile(
            leading: _StatusIcon(status: inv['status'] as String? ?? ''),
            title: Text(inv['invoice_number'] as String? ?? ''),
            subtitle: Text('${inv['owner_name'] ?? ''}${inv['patient_name'] != null ? " · ${inv['patient_name']}" : ""}'),
            trailing: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(_eur(inv['total_gross']), style: const TextStyle(fontWeight: FontWeight.bold)),
                _StatusBadge(status: inv['status'] as String? ?? ''),
              ],
            ),
            onTap: () => context.push('/rechnungen/${inv['id']}').then((_) => _load()),
          );
        },
      ),
    );
  }
}

class _StatusIcon extends StatelessWidget {
  final String status;
  const _StatusIcon({required this.status});

  @override
  Widget build(BuildContext context) {
    final (icon, color) = switch (status) {
      'paid'    => (Icons.check_circle,   Colors.green),
      'open'    => (Icons.pending,        Colors.blue),
      'overdue' => (Icons.warning,        Colors.red),
      'draft'   => (Icons.edit_note,      Colors.grey),
      _         => (Icons.receipt,        Colors.grey),
    };
    return CircleAvatar(
      backgroundColor: color.withValues(alpha: 0.15),
      child: Icon(icon, color: color, size: 20),
    );
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
