import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class IntakeScreen extends StatefulWidget {
  const IntakeScreen({super.key});

  @override
  State<IntakeScreen> createState() => _IntakeScreenState();
}

class _IntakeScreenState extends State<IntakeScreen> {
  final _api = ApiService();
  List<dynamic> _items = [];
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
      final data = await _api.intakeInbox();
      setState(() { _items = (data['items'] as List? ?? []); _loading = false; });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  Future<void> _accept(Map<String, dynamic> item) async {
    final id = int.tryParse(item['id'].toString());
    if (id == null) return;
    try {
      await _api.intakeAccept(id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: const Text('✓ Anmeldung bestätigt'),
        backgroundColor: Colors.green.shade700,
        behavior: SnackBarBehavior.floating,
      ));
      _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _reject(Map<String, dynamic> item) async {
    final id = int.tryParse(item['id'].toString());
    if (id == null) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Anmeldung ablehnen'),
        content: const Text('Diese Anmeldung wirklich ablehnen?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Ablehnen'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    try {
      await _api.intakeReject(id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: const Text('Anmeldung abgelehnt'),
        backgroundColor: Colors.orange.shade700,
        behavior: SnackBarBehavior.floating,
      ));
      _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Neue Anmeldungen'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _load,
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(Icons.error_outline_rounded, size: 48, color: cs.error),
                  const SizedBox(height: 8),
                  Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  FilledButton(onPressed: _load, child: const Text('Erneut versuchen')),
                ]))
              : _items.isEmpty
                  ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.assignment_turned_in_rounded, size: 64, color: cs.outlineVariant),
                      const SizedBox(height: 12),
                      Text('Keine neuen Anmeldungen', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 16)),
                    ]))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: _items.length,
                        itemBuilder: (_, i) => _IntakeCard(
                          item: Map<String, dynamic>.from(_items[i] as Map),
                          onAccept: () => _accept(Map<String, dynamic>.from(_items[i] as Map)),
                          onReject: () => _reject(Map<String, dynamic>.from(_items[i] as Map)),
                          onTap: () => context.push('/anmeldungen/${_items[i]['id']}').then((_) => _load()),
                        ),
                      ),
                    ),
    );
  }
}

class _IntakeCard extends StatelessWidget {
  final Map<String, dynamic> item;
  final VoidCallback onAccept;
  final VoidCallback onReject;
  final VoidCallback onTap;

  const _IntakeCard({required this.item, required this.onAccept, required this.onReject, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final name = '${item['first_name'] ?? ''} ${item['last_name'] ?? ''}'.trim();
    final petName = item['pet_name'] as String? ?? item['animal_name'] as String? ?? '';
    final species = item['species'] as String? ?? item['pet_species'] as String? ?? '';
    final createdAt = item['created_at'] as String? ?? item['submitted_at'] as String? ?? '';
    String dateStr = '';
    if (createdAt.isNotEmpty) {
      try {
        dateStr = DateFormat('dd.MM.yyyy HH:mm', 'de_DE').format(DateTime.parse(createdAt));
      } catch (_) {}
    }
    final status = item['status'] as String? ?? 'pending';
    final isPending = status == 'pending' || status == 'new';

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Container(
                width: 44, height: 44,
                decoration: BoxDecoration(
                  color: AppTheme.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(Icons.assignment_ind_rounded, color: AppTheme.primary, size: 24),
              ),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(name.isNotEmpty ? name : 'Unbekannt',
                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                if (petName.isNotEmpty)
                  Text('$petName${species.isNotEmpty ? ' ($species)' : ''}',
                    style: TextStyle(fontSize: 13, color: cs.onSurfaceVariant)),
              ])),
              if (dateStr.isNotEmpty)
                Text(dateStr, style: TextStyle(fontSize: 11, color: cs.onSurfaceVariant)),
            ]),
            if (item['email'] != null || item['phone'] != null) ...[
              const SizedBox(height: 8),
              Wrap(spacing: 12, children: [
                if (item['email'] != null && (item['email'] as String).isNotEmpty)
                  Row(mainAxisSize: MainAxisSize.min, children: [
                    Icon(Icons.email_outlined, size: 14, color: cs.onSurfaceVariant),
                    const SizedBox(width: 4),
                    Text(item['email'] as String, style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant)),
                  ]),
                if (item['phone'] != null && (item['phone'] as String).isNotEmpty)
                  Row(mainAxisSize: MainAxisSize.min, children: [
                    Icon(Icons.phone_outlined, size: 14, color: cs.onSurfaceVariant),
                    const SizedBox(width: 4),
                    Text(item['phone'] as String, style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant)),
                  ]),
              ]),
            ],
            if (isPending) ...[
              const SizedBox(height: 12),
              Row(children: [
                Expanded(child: OutlinedButton.icon(
                  onPressed: onReject,
                  icon: const Icon(Icons.close_rounded, size: 16),
                  label: const Text('Ablehnen'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.red,
                    side: const BorderSide(color: Colors.red),
                  ),
                )),
                const SizedBox(width: 10),
                Expanded(child: FilledButton.icon(
                  onPressed: onAccept,
                  icon: const Icon(Icons.check_rounded, size: 16),
                  label: const Text('Bestätigen'),
                  style: FilledButton.styleFrom(backgroundColor: Colors.green.shade700),
                )),
              ]),
            ] else
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Chip(
                  label: Text(status == 'accepted' ? 'Bestätigt' : 'Abgelehnt',
                    style: const TextStyle(fontSize: 12)),
                  backgroundColor: status == 'accepted'
                      ? Colors.green.withValues(alpha: 0.12)
                      : Colors.red.withValues(alpha: 0.12),
                  side: BorderSide.none,
                ),
              ),
          ]),
        ),
      ),
    );
  }
}
