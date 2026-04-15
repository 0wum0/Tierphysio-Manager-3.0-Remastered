import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../core/terminology.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../core/theme.dart';
import '../../widgets/search_bar_widget.dart';
import '../../widgets/shimmer_list.dart';

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
    final t = Terminology(isTrainer: context.watch<AuthService>().isTrainer);
    return Scaffold(
      appBar: AppBar(
        title: Text(t.ownerPlural),
        actions: [
          IconButton(
            icon: const Icon(Icons.person_add_rounded),
            onPressed: () => context.push('/tierhalter/neu').then((_) => _load()),
          ),
        ],
      ),
      body: Column(children: [
        AppSearchBar(onSearch: (q) { _search = q; _load(); }, hint: '${t.ownerSingular} suchen…'),
        Expanded(child: _buildList()),
      ]),
    );
  }

  Widget _buildList() {
    final t = Terminology(isTrainer: context.watch<AuthService>().isTrainer);
    if (_loading && _items.isEmpty) return const ShimmerList();
    if (_error != null && _items.isEmpty) {
      return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.error_outline_rounded, size: 48, color: AppTheme.danger),
        const SizedBox(height: 12),
        Text(_error!, textAlign: TextAlign.center),
        const SizedBox(height: 16),
        FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut')),
      ]));
    }
    if (_items.isEmpty) {
      return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.people_outline_rounded, size: 64, color: Colors.grey.shade300),
        const SizedBox(height: 12),
        Text(t.ownersFoundEmpty(), style: Theme.of(context).textTheme.bodyLarge),
      ]));
    }

    final isTablet = MediaQuery.of(context).size.width >= 600;
    return RefreshIndicator(
      onRefresh: () => _load(),
      child: isTablet ? _buildGrid() : _buildListView(),
    );
  }

  Widget _buildListView() {
    return ListView.builder(
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemCount: _items.length + (_hasNext ? 1 : 0),
      itemBuilder: (ctx, i) {
        if (i == _items.length) return Padding(
          padding: const EdgeInsets.all(16),
          child: FilledButton.tonal(
            onPressed: () { _page++; _load(reset: false); },
            child: const Text('Mehr laden'),
          ),
        );
        return _OwnerTile(
          owner: _items[i] as Map<String, dynamic>,
          onTap: () => context.push('/tierhalter/${_items[i]['id']}').then((_) => _load()),
        );
      },
    );
  }

  Widget _buildGrid() {
    return GridView.builder(
      padding: const EdgeInsets.all(16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2, mainAxisSpacing: 12, crossAxisSpacing: 12, childAspectRatio: 2.6,
      ),
      itemCount: _items.length + (_hasNext ? 1 : 0),
      itemBuilder: (ctx, i) {
        if (i == _items.length) return FilledButton.tonal(
          onPressed: () { _page++; _load(reset: false); },
          child: const Text('Mehr laden'),
        );
        return _OwnerTile(
          owner: _items[i] as Map<String, dynamic>,
          onTap: () => context.push('/tierhalter/${_items[i]['id']}').then((_) => _load()),
        );
      },
    );
  }
}

// ── Owner avatar colour from name hash ───────────────────────────────────────

Color _avatarColor(String name) {
  final colors = [
    AppTheme.primary, AppTheme.secondary, AppTheme.tertiary,
    AppTheme.success, AppTheme.warning,
    const Color(0xFFEC4899), const Color(0xFF14B8A6),
  ];
  if (name.isEmpty) return AppTheme.primary;
  return colors[name.codeUnitAt(0) % colors.length];
}

// ── Owner Tile ────────────────────────────────────────────────────────────────

class _OwnerTile extends StatelessWidget {
  final Map<String, dynamic> owner;
  final VoidCallback onTap;
  const _OwnerTile({required this.owner, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final o        = owner;
    final last     = o['last_name']  as String? ?? '';
    final first    = o['first_name'] as String? ?? '';
    final fullName = '$last${last.isNotEmpty && first.isNotEmpty ? ', ' : ''}$first';
    final initials = '${last.isNotEmpty ? last[0] : ''}${first.isNotEmpty ? first[0] : ''}'.toUpperCase();
    final color    = _avatarColor(last);
    final email    = o['email'] as String? ?? '';
    final phone    = o['phone'] as String? ?? '';
    final pCount   = o['patients_count'] as int? ?? (o['patients_count'] is String ? int.tryParse(o['patients_count'] as String) ?? 0 : 0);
    final sub      = [email, phone].where((s) => s.isNotEmpty).join(' · ');

    final isDark = Theme.of(context).brightness == Brightness.dark;
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
              /* Gradient avatar circle */
              Container(
                width: 50, height: 50,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [color, Color.lerp(color, AppTheme.secondary, 0.45)!],
                    begin: Alignment.topLeft, end: Alignment.bottomRight,
                  ),
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: color.withValues(alpha: isDark ? 0.25 : 0.30),
                      blurRadius: 10, offset: const Offset(0, 4),
                    ),
                  ],
                ),
                alignment: Alignment.center,
                child: Text(initials.isEmpty ? '?' : initials,
                  style: const TextStyle(
                    color: Colors.white, fontWeight: FontWeight.w800, fontSize: 17)),
              ),
              const SizedBox(width: 14),
              /* Info */
              Expanded(child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(fullName,
                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, height: 1.2),
                    overflow: TextOverflow.ellipsis),
                  if (sub.isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Text(sub,
                      style: TextStyle(fontSize: 12,
                        color: Theme.of(context).colorScheme.onSurfaceVariant),
                      overflow: TextOverflow.ellipsis, maxLines: 1),
                  ],
                ],
              )),
              /* Patient-count badge */
              if (pCount > 0) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: isDark ? 0.18 : 0.10),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: color.withValues(alpha: 0.20)),
                  ),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    Icon(Icons.pets_rounded, size: 11, color: color),
                    const SizedBox(width: 4),
                    Text('$pCount',
                      style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 12)),
                  ]),
                ),
              ],
              const SizedBox(width: 6),
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
