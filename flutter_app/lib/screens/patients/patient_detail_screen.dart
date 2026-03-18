import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../widgets/species_icon.dart';

class PatientDetailScreen extends StatefulWidget {
  final int id;
  const PatientDetailScreen({super.key, required this.id});

  @override
  State<PatientDetailScreen> createState() => _PatientDetailScreenState();
}

class _PatientDetailScreenState extends State<PatientDetailScreen> {
  final _api = ApiService();
  Map<String, dynamic>? _patient;
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
      final data = await _api.patientShow(widget.id);
      setState(() { _patient = data; _loading = false; });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_patient?['name'] as String? ?? 'Patient'),
        actions: [
          if (_patient != null)
            IconButton(
              icon: const Icon(Icons.edit),
              onPressed: () => context.push('/patienten/${widget.id}/edit').then((_) => _load()),
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [Text(_error!), const SizedBox(height: 12), FilledButton(onPressed: _load, child: const Text('Erneut'))],
                ))
              : _buildBody(),
    );
  }

  Widget _buildBody() {
    final p  = _patient!;
    final cs = Theme.of(context).colorScheme;
    final timeline = List<dynamic>.from(p['timeline'] as List? ?? []);

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
                      radius: 32,
                      backgroundColor: cs.primaryContainer,
                      child: SpeciesIcon(species: p['species'] as String? ?? '', size: 32),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(p['name'] as String? ?? '', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                          Text('${p['species'] ?? ''} · ${p['breed'] ?? ''}'.replaceAll(RegExp(r' · $|^ · '), ''),
                              style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: cs.onSurfaceVariant)),
                          if (p['owner_first_name'] != null)
                            Text('${p['owner_first_name']} ${p['owner_last_name']}',
                                style: Theme.of(context).textTheme.bodySmall),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            _InfoSection(title: 'Patientendaten', items: {
              'Geschlecht':   _gender(p['gender'] as String? ?? ''),
              'Geburtsdatum': _date(p['birth_date'] as String?),
              'Gewicht':      p['weight'] != null ? '${p['weight']} kg' : '—',
              'Chip-Nr.':     p['chip_number'] as String? ?? '—',
              'Farbe':        p['color'] as String? ?? '—',
              'Status':       _statusLabel(p['status'] as String? ?? ''),
            }),
            if (p['owner_email'] != null || p['owner_phone'] != null)
              _InfoSection(title: 'Besitzer', items: {
                'Name':  '${p['owner_first_name'] ?? ''} ${p['owner_last_name'] ?? ''}'.trim(),
                'E-Mail': p['owner_email'] as String? ?? '—',
                'Telefon': p['owner_phone'] as String? ?? '—',
              }),
            if ((p['notes'] as String? ?? '').isNotEmpty)
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
                        Text(p['notes'] as String),
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
                  Text('Akte', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
                  TextButton.icon(
                    icon: const Icon(Icons.add, size: 18),
                    label: const Text('Eintrag'),
                    onPressed: () => _showAddTimelineDialog(),
                  ),
                ],
              ),
            ),
            if (timeline.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 16),
                child: Text('Noch keine Einträge.', style: TextStyle(color: Colors.grey)),
              )
            else
              ...timeline.map((e) => _TimelineEntry(entry: e as Map<String, dynamic>)),
          ],
        ),
      ),
    );
  }

  String _gender(String g) => switch (g) {
    'männlich'   => '♂ Männlich',
    'weiblich'   => '♀ Weiblich',
    'kastriert'  => '⚲ Kastriert',
    'sterilisiert'=> '⚲ Sterilisiert',
    _            => 'Unbekannt',
  };

  String _date(String? d) {
    if (d == null || d.isEmpty) return '—';
    try {
      final dt = DateTime.parse(d);
      final age = DateTime.now().difference(dt).inDays ~/ 365;
      return '${DateFormat('dd.MM.yyyy').format(dt)} ($age Jahre)';
    } catch (_) { return d; }
  }

  String _statusLabel(String s) => switch (s) {
    'active'   => 'Aktiv',
    'inactive' => 'Inaktiv',
    'deceased' => 'Verstorben',
    _          => s,
  };

  Future<void> _showAddTimelineDialog() async {
    final titleCtrl = TextEditingController();
    final contentCtrl = TextEditingController();
    String type = 'note';

    await showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Neuer Akte-Eintrag'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                initialValue: type,
                decoration: const InputDecoration(labelText: 'Typ'),
                items: const [
                  DropdownMenuItem(value: 'note', child: Text('Notiz')),
                  DropdownMenuItem(value: 'treatment', child: Text('Behandlung')),
                  DropdownMenuItem(value: 'other', child: Text('Sonstiges')),
                ],
                onChanged: (v) => type = v!,
              ),
              const SizedBox(height: 12),
              TextField(controller: titleCtrl, decoration: const InputDecoration(labelText: 'Titel *')),
              const SizedBox(height: 12),
              TextField(controller: contentCtrl, decoration: const InputDecoration(labelText: 'Inhalt'), maxLines: 3),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Abbrechen')),
          FilledButton(
            onPressed: () async {
              if (titleCtrl.text.isEmpty) return;
              Navigator.pop(ctx);
              try {
                await _api.patientTimelineCreate(widget.id, {
                  'type': type,
                  'title': titleCtrl.text,
                  'content': contentCtrl.text,
                  'entry_date': DateTime.now().toIso8601String().substring(0, 10),
                });
                _load();
              } catch (e) {
                if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
              }
            },
            child: const Text('Speichern'),
          ),
        ],
      ),
    );
  }
}

class _InfoSection extends StatelessWidget {
  final String title;
  final Map<String, String> items;
  const _InfoSection({required this.title, required this.items});

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

class _TimelineEntry extends StatelessWidget {
  final Map<String, dynamic> entry;
  const _TimelineEntry({required this.entry});

  @override
  Widget build(BuildContext context) {
    final icon = switch (entry['type'] as String? ?? '') {
      'treatment' => Icons.medical_services_outlined,
      'photo'     => Icons.photo_outlined,
      'document'  => Icons.description_outlined,
      _           => Icons.note_outlined,
    };
    final color = switch (entry['type'] as String? ?? '') {
      'treatment' => Colors.blue,
      'photo'     => Colors.purple,
      'document'  => Colors.orange,
      _           => Colors.green,
    };
    String dateStr = '';
    try {
      final d = DateTime.parse(entry['entry_date'] as String? ?? '');
      dateStr = DateFormat('dd.MM.yyyy').format(d);
    } catch (_) {}

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: color.withAlpha(38),
          child: Icon(icon, color: color, size: 20),
        ),
        title: Text(entry['title'] as String? ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if ((entry['content'] as String? ?? '').isNotEmpty)
              Text(entry['content'] as String, maxLines: 2, overflow: TextOverflow.ellipsis),
            Text('$dateStr · ${entry['user_name'] ?? ''}', style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
        isThreeLine: true,
      ),
    );
  }
}
