import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../services/api_service.dart';
import '../../widgets/search_bar_widget.dart';
import '../../widgets/paw_avatar.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/shimmer_list.dart';

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
          IconButton(
            icon: const Icon(Icons.add_rounded),
            onPressed: () => context.push('/patienten/neu').then((_) => _load()),
          ),
        ],
      ),
      body: Column(children: [
        AppSearchBar(onSearch: _onSearch, hint: 'Patient suchen…'),
        Expanded(child: _buildList()),
      ]),
    );
  }

  Widget _buildList() {
    if (_loading && _items.isEmpty) return const ShimmerList();
    if (_error != null && _items.isEmpty) {
      return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.error_outline_rounded, size: 48, color: Colors.red.shade300),
        const SizedBox(height: 12),
        Text(_error!, textAlign: TextAlign.center),
        const SizedBox(height: 16),
        FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut versuchen')),
      ]));
    }
    if (_items.isEmpty) return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      Icon(Icons.pets_rounded, size: 56, color: Colors.grey.shade300),
      const SizedBox(height: 12),
      Text('Keine Patienten gefunden', style: Theme.of(context).textTheme.bodyLarge),
    ]));

    final isTablet = MediaQuery.of(context).size.width >= 600;

    return RefreshIndicator(
      onRefresh: () => _load(),
      child: isTablet
          ? _buildGrid()
          : _buildListView(),
    );
  }

  Widget _buildListView() {
    return ListView.builder(
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemCount: _items.length + (_hasNext ? 1 : 0),
      itemBuilder: (ctx, i) {
        if (i == _items.length) {
          return Padding(
            padding: const EdgeInsets.all(16),
            child: FilledButton.tonal(onPressed: _loadMore, child: const Text('Mehr laden')),
          );
        }
        return _PatientTile(patient: _items[i] as Map<String, dynamic>,
            onTap: () => context.push('/patienten/${_items[i]['id']}').then((_) => _load()));
      },
    );
  }

  Widget _buildGrid() {
    return GridView.builder(
      padding: const EdgeInsets.all(16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2, mainAxisSpacing: 12, crossAxisSpacing: 12, childAspectRatio: 2.8,
      ),
      itemCount: _items.length + (_hasNext ? 1 : 0),
      itemBuilder: (ctx, i) {
        if (i == _items.length) {
          return FilledButton.tonal(onPressed: _loadMore, child: const Text('Mehr laden'));
        }
        return _PatientTile(patient: _items[i] as Map<String, dynamic>,
            onTap: () => context.push('/patienten/${_items[i]['id']}').then((_) => _load()));
      },
    );
  }
}

class _PatientTile extends StatelessWidget {
  final Map<String, dynamic> patient;
  final VoidCallback onTap;
  const _PatientTile({required this.patient, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final p = patient;
    final species = p['species'] as String? ?? '';
    final breed   = p['breed']   as String? ?? '';
    final sub     = [species, breed].where((s) => s.isNotEmpty).join(' · ');

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(children: [
            PawAvatar(
              photoPath: p['photo_url'] as String?,
              species: species,
              name: p['name'] as String?,
              radius: 24,
            ),
            const SizedBox(width: 12),
            Expanded(child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(p['name'] as String? ?? '',
                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                    overflow: TextOverflow.ellipsis),
                if (sub.isNotEmpty) Text(sub,
                    style: Theme.of(context).textTheme.bodySmall,
                    overflow: TextOverflow.ellipsis),
              ],
            )),
            const SizedBox(width: 8),
            Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
              if (p['owner_name'] != null)
                Text(p['owner_name'] as String,
                    style: Theme.of(context).textTheme.bodySmall,
                    overflow: TextOverflow.ellipsis),
              const SizedBox(height: 4),
              StatusBadge(status: p['status'] as String? ?? 'active'),
            ]),
          ]),
        ),
      ),
    );
  }
}
