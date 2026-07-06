import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import '../services/portal_api_service.dart';

class PetDetailScreen extends StatefulWidget {
  final int petId;
  const PetDetailScreen({super.key, required this.petId});
  @override
  State<PetDetailScreen> createState() => _PetDetailScreenState();
}

class _PetDetailScreenState extends State<PetDetailScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final auth = context.read<PortalAuthService>();
      final data = await PortalApiService(token: auth.token).getPetDetail(widget.petId);
      if (mounted) setState(() { _data = data; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final pet = _data?['pet'] as Map<String, dynamic>? ?? _data ?? {};
    return Scaffold(
      appBar: AppBar(title: Text(pet['name'] as String? ?? 'Tierprofil')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
                  const Icon(Icons.error_outline_rounded, size: 48, color: AppTheme.danger),
                  const SizedBox(height: 12),
                  Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 24),
                  FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut')),
                ])))
              : RefreshIndicator(onRefresh: _load, child: _buildBody(pet)),
    );
  }

  Widget _buildBody(Map<String, dynamic> pet) {
    final cs        = Theme.of(context).colorScheme;
    final timeline  = List<dynamic>.from(_data?['timeline'] ?? []);
    final exercises = List<dynamic>.from(_data?['exercises'] ?? []);

    return ListView(padding: const EdgeInsets.only(bottom: 32), children: [
      // Header
      Container(
        padding: const EdgeInsets.all(24),
        child: Row(children: [
          CircleAvatar(
            radius: 48,
            backgroundColor: AppTheme.primary.withValues(alpha: 0.12),
            backgroundImage: pet['photo_url'] != null ? NetworkImage(pet['photo_url'] as String) : null,
            child: pet['photo_url'] == null ? const Icon(Icons.pets_rounded, color: AppTheme.primary, size: 48) : null,
          ),
          const SizedBox(width: 20),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(pet['name'] as String? ?? '–', style: Theme.of(context).textTheme.headlineMedium),
            const SizedBox(height: 4),
            Text('${pet['species'] ?? ''}${pet['breed'] != null ? ' · ${pet['breed']}' : ''}',
                style: TextStyle(color: cs.onSurfaceVariant)),
            if (pet['gender'] != null)
              Text(pet['gender'] as String, style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13)),
          ])),
        ]),
      ),

      // Details card
      Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(children: [
        _DetailRow('Geburtsdatum', pet['birth_date'] as String? ?? '–'),
        _DetailRow('Farbe / Abzeichen', pet['color'] as String? ?? '–'),
        _DetailRow('Chip-Nr.', pet['chip_number'] as String? ?? '–'),
        _DetailRow('Kastration', pet['neutered'] == true ? 'Ja' : 'Nein'),
        if (pet['weight_kg'] != null) _DetailRow('Gewicht', '${pet['weight_kg']} kg'),
        if (pet['allergies'] != null && (pet['allergies'] as String).isNotEmpty)
          _DetailRow('Allergien', pet['allergies'] as String),
        if (pet['notes'] != null && (pet['notes'] as String).isNotEmpty)
          _DetailRow('Notizen', pet['notes'] as String),
      ]))),

      // Exercises / Hausaufgaben
      if (exercises.isNotEmpty) ...[
        _SectionHeader('Hausaufgaben'),
        ...exercises.map((e) => Card(child: ListTile(
          leading: CircleAvatar(radius: 18,
            backgroundColor: AppTheme.success.withValues(alpha: 0.12),
            child: const Icon(Icons.assignment_rounded, color: AppTheme.success, size: 18)),
          title: Text(e['name'] as String? ?? '–', style: const TextStyle(fontWeight: FontWeight.w600)),
          subtitle: e['repetitions'] != null
              ? Text('${e['repetitions']}x Wiederholungen', style: TextStyle(color: cs.onSurfaceVariant))
              : null,
        ))),
      ],

      // Timeline
      if (timeline.isNotEmpty) ...[
        _SectionHeader('Verlauf'),
        ...timeline.map((t) => _TimelineTile(entry: t as Map<String, dynamic>)),
      ],
    ]);
  }
}

class _SectionHeader extends StatelessWidget {
  final String title;
  const _SectionHeader(this.title);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
    child: Text(title, style: Theme.of(context).textTheme.titleMedium),
  );
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;
  const _DetailRow(this.label, this.value);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(children: [
      SizedBox(width: 130, child: Text(label, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 13))),
      Expanded(child: Text(value, style: const TextStyle(fontWeight: FontWeight.w500))),
    ]),
  );
}

class _TimelineTile extends StatelessWidget {
  final Map<String, dynamic> entry;
  const _TimelineTile({required this.entry});
  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Card(child: ListTile(
      leading: Container(width: 40, height: 40,
        decoration: BoxDecoration(color: AppTheme.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
        child: const Icon(Icons.history_rounded, color: AppTheme.primary, size: 20)),
      title: Text(entry['title'] as String? ?? entry['type'] as String? ?? 'Eintrag',
          style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: Text(entry['date'] as String? ?? '', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12)),
    ));
  }
}
