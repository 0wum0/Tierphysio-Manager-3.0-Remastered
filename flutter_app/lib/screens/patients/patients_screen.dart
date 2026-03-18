import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../services/api_service.dart';
import '../../widgets/search_bar_widget.dart';
import '../../widgets/species_icon.dart';

class PatientsScreen extends StatefulWidget {
  const PatientsScreen({super.key});

  @override
  State<PatientsScreen> createState() => _PatientsScreenState();
}

class _PatientsScreenState extends State<PatientsScreen> {
  final _api    = ApiService();
  List<dynamic> _items = [];
  bool  _loading = true;
  String? _error;
  String _search = '';
  int _page = 1;
  bool _hasNext = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool reset = true}) async {
    if (reset) { _page = 1; _items = []; }
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.patients(page: _page, search: _search);
      final items = List<dynamic>.from(data['items'] as List? ?? []);
      setState(() {
        _items    = reset ? items : [..._items, ...items];
        _hasNext  = data['has_next'] as bool? ?? false;
        _loading  = false;
      });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  void _onSearch(String q) {
    _search = q;
    _load();
  }

  Future<void> _loadMore() async {
    _page++;
    await _load(reset: false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Patienten'),
        actions: [
          IconButton(icon: const Icon(Icons.add), onPressed: () => context.push('/patienten/neu').then((_) => _load())),
        ],
      ),
      body: Column(
        children: [
          AppSearchBar(onSearch: _onSearch, hint: 'Patient suchen…'),
          Expanded(child: _buildList()),
        ],
      ),
    );
  }

  Widget _buildList() {
    if (_loading && _items.isEmpty) return const Center(child: CircularProgressIndicator());
    if (_error != null && _items.isEmpty) {
      return Center(child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(_error!),
          const SizedBox(height: 12),
          FilledButton(onPressed: _load, child: const Text('Erneut versuchen')),
        ],
      ));
    }
    if (_items.isEmpty) return const Center(child: Text('Keine Patienten gefunden.'));

    return RefreshIndicator(
      onRefresh: () => _load(),
      child: ListView.builder(
        itemCount: _items.length + (_hasNext ? 1 : 0),
        itemBuilder: (ctx, i) {
          if (i == _items.length) {
            return Padding(
              padding: const EdgeInsets.all(16),
              child: FilledButton.tonal(onPressed: _loadMore, child: const Text('Mehr laden')),
            );
          }
          final p = _items[i] as Map<String, dynamic>;
          return ListTile(
            leading: CircleAvatar(
              backgroundColor: Theme.of(context).colorScheme.primaryContainer,
              child: SpeciesIcon(species: p['species'] as String? ?? ''),
            ),
            title: Text(p['name'] as String? ?? ''),
            subtitle: Text('${p['species'] ?? ''} · ${p['breed'] ?? ''}'.trim().replaceAll(RegExp(r' · $'), '')),
            trailing: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                if (p['owner_name'] != null) Text(p['owner_name'] as String, style: Theme.of(context).textTheme.bodySmall),
                _StatusChip(status: p['status'] as String? ?? 'active'),
              ],
            ),
            onTap: () => context.push('/patienten/${p['id']}').then((_) => _load()),
          );
        },
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String status;
  const _StatusChip({required this.status});

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'active'   => Colors.green,
      'inactive' => Colors.grey,
      'deceased' => Colors.red,
      _          => Colors.grey,
    };
    final label = switch (status) {
      'active'   => 'Aktiv',
      'inactive' => 'Inaktiv',
      'deceased' => 'Verstorben',
      _          => status,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(4)),
      child: Text(label, style: TextStyle(fontSize: 10, color: color, fontWeight: FontWeight.w600)),
    );
  }
}
