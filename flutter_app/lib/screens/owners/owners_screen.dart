import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../services/api_service.dart';
import '../../widgets/search_bar_widget.dart';

class OwnersScreen extends StatefulWidget {
  const OwnersScreen({super.key});

  @override
  State<OwnersScreen> createState() => _OwnersScreenState();
}

class _OwnersScreenState extends State<OwnersScreen> {
  final _api    = ApiService();
  List<dynamic> _items = [];
  bool  _loading = true;
  String? _error;
  String _search = '';
  int _page = 1;
  bool _hasNext = false;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load({bool reset = true}) async {
    if (reset) { _page = 1; _items = []; }
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.owners(page: _page, search: _search);
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Tierhalter'),
        actions: [IconButton(icon: const Icon(Icons.add), onPressed: () => context.push('/tierhalter/neu').then((_) => _load()))],
      ),
      body: Column(
        children: [
          AppSearchBar(onSearch: (q) { _search = q; _load(); }, hint: 'Tierhalter suchen…'),
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
    if (_items.isEmpty) return const Center(child: Text('Keine Tierhalter gefunden.'));

    return RefreshIndicator(
      onRefresh: () => _load(),
      child: ListView.builder(
        itemCount: _items.length + (_hasNext ? 1 : 0),
        itemBuilder: (ctx, i) {
          if (i == _items.length) return Padding(
            padding: const EdgeInsets.all(16),
            child: FilledButton.tonal(onPressed: () { _page++; _load(reset: false); }, child: const Text('Mehr laden')),
          );
          final o = _items[i] as Map<String, dynamic>;
          return ListTile(
            leading: CircleAvatar(child: Text(((o['last_name'] as String? ?? '').isNotEmpty ? o['last_name'] as String : '?')[0].toUpperCase())),
            title: Text('${o['last_name']}, ${o['first_name']}'),
            subtitle: Text([o['email'], o['phone']].where((v) => v != null && (v as String).isNotEmpty).join(' · ')),
            onTap: () => context.push('/tierhalter/${o['id']}').then((_) => _load()),
          );
        },
      ),
    );
  }
}
