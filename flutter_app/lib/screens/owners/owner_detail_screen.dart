import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import '../../widgets/species_icon.dart';

class OwnerDetailScreen extends StatefulWidget {
  final int id;
  const OwnerDetailScreen({super.key, required this.id});

  @override
  State<OwnerDetailScreen> createState() => _OwnerDetailScreenState();
}

class _OwnerDetailScreenState extends State<OwnerDetailScreen> {
  final _api = ApiService();
  Map<String, dynamic>? _owner;
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.ownerShow(widget.id);
      setState(() { _owner = data; _loading = false; });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: _owner != null
            ? Text('${_owner!['last_name']}, ${_owner!['first_name']}')
            : const Text('Tierhalter'),
        actions: [
          if (_owner != null)
            IconButton(icon: const Icon(Icons.edit), onPressed: () => context.push('/tierhalter/${widget.id}/edit').then((_) => _load())),
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
    final o  = _owner!;
    final cs = Theme.of(context).colorScheme;
    final patients = List<dynamic>.from(o['patients'] as List? ?? []);

    return RefreshIndicator(
      onRefresh: _load,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.only(bottom: 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Card(
              margin: const EdgeInsets.all(16),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 30,
                      backgroundColor: cs.primaryContainer,
                      child: Text(
                        ((o['last_name'] as String? ?? '').isNotEmpty ? o['last_name'] as String : '?')[0].toUpperCase(),
                        style: TextStyle(fontSize: 22, color: cs.onPrimaryContainer, fontWeight: FontWeight.bold),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('${o['first_name']} ${o['last_name']}',
                              style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                          if ((o['email'] as String? ?? '').isNotEmpty)
                            InkWell(
                              onTap: () => launchUrl(Uri.parse('mailto:${o['email']}')),
                              child: Text(o['email'] as String, style: TextStyle(color: cs.primary)),
                            ),
                          if ((o['phone'] as String? ?? '').isNotEmpty)
                            InkWell(
                              onTap: () => launchUrl(Uri.parse('tel:${o['phone']}')),
                              child: Text(o['phone'] as String, style: TextStyle(color: cs.primary)),
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            if ((o['address'] as String? ?? '').isNotEmpty || (o['city'] as String? ?? '').isNotEmpty)
              _InfoCard(title: 'Adresse', items: {
                'Straße': o['address'] as String? ?? '—',
                'PLZ / Ort': '${o['zip'] ?? ''} ${o['city'] ?? ''}'.trim(),
              }),
            if ((o['notes'] as String? ?? '').isNotEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Notizen', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
                        const SizedBox(height: 6),
                        Text(o['notes'] as String),
                      ],
                    ),
                  ),
                ),
              ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Tiere (${patients.length})', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
                  TextButton.icon(
                    icon: const Icon(Icons.add, size: 18),
                    label: const Text('Neues Tier'),
                    onPressed: () => context.push('/patienten/neu').then((_) => _load()),
                  ),
                ],
              ),
            ),
            if (patients.isEmpty)
              const Padding(padding: EdgeInsets.symmetric(horizontal: 16), child: Text('Noch keine Tiere.', style: TextStyle(color: Colors.grey)))
            else
              ...patients.map((p) {
                final pat = p as Map<String, dynamic>;
                return ListTile(
                  leading: CircleAvatar(
                    backgroundColor: cs.secondaryContainer,
                    child: SpeciesIcon(species: pat['species'] as String? ?? ''),
                  ),
                  title: Text(pat['name'] as String? ?? ''),
                  subtitle: Text('${pat['species'] ?? ''} · ${pat['breed'] ?? ''}'.replaceAll(RegExp(r' · $|^ · '), '')),
                  onTap: () => context.push('/patienten/${pat['id']}'),
                );
              }),
          ],
        ),
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  final String title;
  final Map<String, String> items;
  const _InfoCard({required this.title, required this.items});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
              const SizedBox(height: 10),
              ...items.entries.map((e) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: Row(
                  children: [
                    SizedBox(width: 110, child: Text(e.key, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant))),
                    Expanded(child: Text(e.value.isEmpty ? '—' : e.value)),
                  ],
                ),
              )),
            ],
          ),
        ),
      ),
    );
  }
}
