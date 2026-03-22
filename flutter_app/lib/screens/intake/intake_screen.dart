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
    final name = '${item['owner_first_name'] ?? ''} ${item['owner_last_name'] ?? ''}'.trim();
    final petName = item['patient_name'] as String? ?? '';
    final species = item['patient_species'] as String? ?? '';
    final createdAt = item['created_at'] as String? ?? '';
    String dateStr = '';
    if (createdAt.isNotEmpty) {
      try {
        dateStr = DateFormat('dd.MM.yyyy HH:mm', 'de_DE').format(DateTime.parse(createdAt));
      } catch (_) {}
    }
    final status = item['status'] as String? ?? 'neu';
    final isPending = status == 'neu' || status == 'in_bearbeitung';

    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
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
                color: isPending
                    ? AppTheme.primary.withValues(alpha: isDark ? 0.22 : 0.16)
                    : (isDark
                        ? Colors.white.withValues(alpha: 0.06)
                        : Colors.black.withValues(alpha: 0.06)),
              ),
            ),
            padding: const EdgeInsets.all(14),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Container(
                  width: 46, height: 46,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [AppTheme.primary, AppTheme.secondary],
                      begin: Alignment.topLeft, end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(13),
                    boxShadow: [
                      BoxShadow(
                        color: AppTheme.primary.withValues(alpha: isDark ? 0.24 : 0.30),
                        blurRadius: 10, offset: const Offset(0, 4)),
                    ],
                  ),
                  child: const Icon(Icons.assignment_ind_rounded, color: Colors.white, size: 24),
                ),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(name.isNotEmpty ? name : 'Unbekannt',
                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, height: 1.2)),
                  if (petName.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text('$petName${species.isNotEmpty ? ' · $species' : ''}',
                      style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant)),
                  ],
                ])),
                if (dateStr.isNotEmpty)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: isDark
                          ? Colors.white.withValues(alpha: 0.06)
                          : Colors.black.withValues(alpha: 0.04),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(dateStr,
                      style: TextStyle(fontSize: 10, fontWeight: FontWeight.w500,
                        color: cs.onSurfaceVariant)),
                  ),
              ]),
              if (item['owner_email'] != null || item['owner_phone'] != null) ...[
                const SizedBox(height: 10),
                Wrap(spacing: 12, runSpacing: 4, children: [
                  if ((item['owner_email'] as String? ?? '').isNotEmpty)
                    Row(mainAxisSize: MainAxisSize.min, children: [
                      Icon(Icons.email_outlined, size: 13, color: cs.onSurfaceVariant.withValues(alpha: 0.65)),
                      const SizedBox(width: 4),
                      Text(item['owner_email'] as String,
                        style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant.withValues(alpha: 0.7))),
                    ]),
                  if ((item['owner_phone'] as String? ?? '').isNotEmpty)
                    Row(mainAxisSize: MainAxisSize.min, children: [
                      Icon(Icons.phone_outlined, size: 13, color: cs.onSurfaceVariant.withValues(alpha: 0.65)),
                      const SizedBox(width: 4),
                      Text(item['owner_phone'] as String,
                        style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant.withValues(alpha: 0.7))),
                    ]),
                ]),
              ],
              if (isPending) ...[
                const SizedBox(height: 12),
                Row(children: [
                  Expanded(child: OutlinedButton.icon(
                    onPressed: onReject,
                    icon: const Icon(Icons.close_rounded, size: 15),
                    label: const Text('Ablehnen'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppTheme.danger,
                      side: BorderSide(color: AppTheme.danger.withValues(alpha: 0.5)),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  )),
                  const SizedBox(width: 10),
                  Expanded(child: FilledButton.icon(
                    onPressed: onAccept,
                    icon: const Icon(Icons.check_rounded, size: 15),
                    label: const Text('Bestätigen'),
                    style: FilledButton.styleFrom(
                      backgroundColor: AppTheme.success,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  )),
                ]),
              ] else
                Padding(
                  padding: const EdgeInsets.only(top: 10),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                    decoration: BoxDecoration(
                      color: (status == 'uebernommen' ? AppTheme.success : AppTheme.danger)
                          .withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(
                        color: (status == 'uebernommen' ? AppTheme.success : AppTheme.danger)
                            .withValues(alpha: 0.22)),
                    ),
                    child: Text(
                      status == 'uebernommen' ? '✓ Übernommen' : '✗ Abgelehnt',
                      style: TextStyle(
                        fontSize: 12, fontWeight: FontWeight.w700,
                        color: status == 'uebernommen' ? AppTheme.success : AppTheme.danger)),
                  ),
                ),
            ]),
          ),
        ),
      ),
    );
  }
}
