import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../services/api_service.dart';
import '../../widgets/search_bar_widget.dart';
import '../../widgets/paw_avatar.dart';
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

  static const _avatarColors = [
    Color(0xFF5B8AF0), Color(0xFF8B5CF6), Color(0xFF06B6D4),
    Color(0xFF10B981), Color(0xFFF59E0B), Color(0xFFEF4444),
  ];

  Color _avatarColor(String name) =>
      _avatarColors[name.isNotEmpty ? name.codeUnitAt(0) % _avatarColors.length : 0];

  String _speciesEmoji(String s) {
    final l = s.toLowerCase();
    if (l.contains('hund') || l.contains('dog'))  return '🐕';
    if (l.contains('katze') || l.contains('cat')) return '🐈';
    if (l.contains('pferd') || l.contains('horse')) return '🐴';
    if (l.contains('vogel') || l.contains('bird')) return '🦜';
    return '🐾';
  }

  @override
  Widget build(BuildContext context) {
    final p       = patient;
    final name    = p['name']    as String? ?? '';
    final species = p['species'] as String? ?? '';
    final breed   = p['breed']   as String? ?? '';
    final owner   = p['owner_name'] as String? ?? '';
    final status  = p['status'] as String? ?? 'active';
    final sub     = [species, breed].where((s) => s.isNotEmpty).join(' · ');
    final isDark  = Theme.of(context).brightness == Brightness.dark;
    final color   = _avatarColor(name);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: Material(
        color: isDark ? const Color(0xFF1A1D27) : Colors.white,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
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
              /* Avatar */
              Stack(children: [
                p['photo_url'] != null && (p['photo_url'] as String).isNotEmpty
                    ? PawAvatar(
                        photoPath: p['photo_url'] as String,
                        species: species, name: name, radius: 26)
                    : Container(
                        width: 52, height: 52,
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: [color, color.withValues(alpha: 0.7)],
                            begin: Alignment.topLeft, end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Center(
                          child: Text(
                            species.isNotEmpty ? _speciesEmoji(species)
                                : (name.isNotEmpty ? name[0].toUpperCase() : '?'),
                            style: const TextStyle(fontSize: 22),
                          ),
                        ),
                      ),
                /* Status dot */
                Positioned(
                  right: 0, bottom: 0,
                  child: Container(
                    width: 12, height: 12,
                    decoration: BoxDecoration(
                      color: status == 'active'   ? const Color(0xFF10B981)
                           : status == 'inactive' ? const Color(0xFF6B7280)
                           : const Color(0xFFF59E0B),
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: isDark ? const Color(0xFF1A1D27) : Colors.white,
                        width: 2,
                      ),
                    ),
                  ),
                ),
              ]),
              const SizedBox(width: 14),
              /* Info */
              Expanded(child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(name,
                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, height: 1.2),
                    overflow: TextOverflow.ellipsis),
                  if (sub.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(sub,
                      style: TextStyle(fontSize: 12,
                        color: Theme.of(context).colorScheme.onSurfaceVariant),
                      overflow: TextOverflow.ellipsis),
                  ],
                  if (owner.isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Row(children: [
                      Icon(Icons.person_outline_rounded, size: 11,
                        color: Theme.of(context).colorScheme.onSurfaceVariant),
                      const SizedBox(width: 3),
                      Expanded(child: Text(owner,
                        style: TextStyle(fontSize: 11,
                          color: Theme.of(context).colorScheme.onSurfaceVariant),
                        overflow: TextOverflow.ellipsis)),
                    ]),
                  ],
                ],
              )),
              /* Arrow */
              Icon(Icons.chevron_right_rounded, size: 20,
                color: Theme.of(context).colorScheme.onSurfaceVariant
                    .withValues(alpha: 0.4)),
            ]),
          ),
        ),
      ),
    );
  }
}
