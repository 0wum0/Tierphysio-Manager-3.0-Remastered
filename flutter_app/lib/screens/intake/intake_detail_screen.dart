import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class IntakeDetailScreen extends StatefulWidget {
  final int id;
  const IntakeDetailScreen({super.key, required this.id});

  @override
  State<IntakeDetailScreen> createState() => _IntakeDetailScreenState();
}

class _IntakeDetailScreenState extends State<IntakeDetailScreen> {
  final _api = ApiService();
  Map<String, dynamic>? _item;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final item = await _api.intakeShow(widget.id);
      setState(() { _item = item; _loading = false; });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  Future<void> _accept() async {
    setState(() => _saving = true);
    try {
      await _api.intakeAccept(widget.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: const Text('✓ Anmeldung bestätigt — Besitzer & Patient wurden angelegt'),
        backgroundColor: Colors.green.shade700,
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 4),
      ));
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _reject() async {
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
    setState(() => _saving = true);
    try {
      await _api.intakeReject(widget.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: const Text('Anmeldung abgelehnt'),
        backgroundColor: Colors.orange.shade700,
        behavior: SnackBarBehavior.floating,
      ));
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Anmeldung')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : _buildContent(cs),
    );
  }

  Widget _buildContent(ColorScheme cs) {
    final d = _item!;
    final ownerName = '${d['owner_first_name'] ?? ''} ${d['owner_last_name'] ?? ''}'.trim();
    final petName   = d['patient_name']    as String? ?? '';
    final species   = d['patient_species'] as String? ?? '';
    final breed     = d['patient_breed']   as String? ?? '';
    final email     = d['owner_email']     as String? ?? '';
    final phone     = d['owner_phone']     as String? ?? '';
    final address   = [d['owner_street'] ?? '', d['owner_zip'] ?? '', d['owner_city'] ?? ''].where((s) => s.toString().isNotEmpty).join(', ');
    final reason    = d['reason']          as String? ?? '';
    final notes     = d['notes']           as String? ?? '';
    final status    = d['status']          as String? ?? 'neu';
    final isPending = status == 'neu' || status == 'in_bearbeitung';

    String createdStr = '';
    final createdAt = d['created_at'] as String? ?? '';
    if (createdAt.isNotEmpty) {
      try { createdStr = DateFormat('dd.MM.yyyy HH:mm', 'de_DE').format(DateTime.parse(createdAt)); } catch (_) {}
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Status banner
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            color: isPending
                ? AppTheme.warning.withValues(alpha: 0.12)
                : status == 'uebernommen'
                    ? Colors.green.withValues(alpha: 0.10)
                    : Colors.red.withValues(alpha: 0.10),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(children: [
            Icon(
              isPending ? Icons.pending_rounded
                  : status == 'uebernommen' ? Icons.check_circle_rounded : Icons.cancel_rounded,
              color: isPending ? AppTheme.warning
                  : status == 'uebernommen' ? Colors.green.shade700 : Colors.red,
              size: 20,
            ),
            const SizedBox(width: 8),
            Text(
              isPending ? 'Warte auf Bestätigung'
                  : status == 'uebernommen' ? 'Übernommen' : 'Abgelehnt',
              style: TextStyle(
                fontWeight: FontWeight.w600,
                color: isPending ? AppTheme.warning
                    : status == 'uebernommen' ? Colors.green.shade700 : Colors.red,
              ),
            ),
            if (createdStr.isNotEmpty) ...[
              const Spacer(),
              Text(createdStr, style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant)),
            ],
          ]),
        ),

        const SizedBox(height: 20),
        _section('Tierhalter', Icons.person_rounded, AppTheme.secondary),
        _row('Name', ownerName.isNotEmpty ? ownerName : '—'),
        if (email.isNotEmpty) _row('E-Mail', email),
        if (phone.isNotEmpty) _row('Telefon', phone),
        if (address.isNotEmpty) _row('Adresse', address),

        if (reason.isNotEmpty) ...[
          const SizedBox(height: 16),
          _section('Anfrage / Grund', Icons.healing_rounded, AppTheme.primary),
          _row('Grund', reason),
          if ((d['appointment_wish'] as String? ?? '').isNotEmpty)
            _row('Terminwunsch', d['appointment_wish'] as String),
        ],

        if (petName.isNotEmpty) ...[
          const SizedBox(height: 16),
          _section('Tier', Icons.pets_rounded, AppTheme.primary),
          _row('Name', petName),
          if (species.isNotEmpty) _row('Tierart', species),
          if (breed.isNotEmpty)   _row('Rasse', breed),
          if ((d['patient_birth_date'] as String? ?? '').isNotEmpty)
            _row('Geburtsdatum', d['patient_birth_date'] as String),
          if ((d['patient_gender'] as String? ?? '').isNotEmpty)
            _row('Geschlecht', d['patient_gender'] as String),
          if ((d['patient_chip'] as String? ?? '').isNotEmpty)
            _row('Chip-Nr.', d['patient_chip'] as String),
          if ((d['patient_color'] as String? ?? '').isNotEmpty)
            _row('Farbe', d['patient_color'] as String),
        ],

        if (notes.isNotEmpty || reason.isNotEmpty) ...[
          const SizedBox(height: 16),
          _section('Nachricht / Notizen', Icons.notes_rounded, AppTheme.tertiary),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: cs.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(notes, style: TextStyle(color: cs.onSurface, height: 1.5)),
          ),
        ],

        if (isPending) ...[
          const SizedBox(height: 32),
          Row(children: [
            Expanded(child: OutlinedButton.icon(
              onPressed: _saving ? null : _reject,
              icon: const Icon(Icons.close_rounded),
              label: const Text('Ablehnen'),
              style: OutlinedButton.styleFrom(
                foregroundColor: Colors.red,
                side: const BorderSide(color: Colors.red),
                minimumSize: const Size(0, 50),
              ),
            )),
            const SizedBox(width: 12),
            Expanded(child: FilledButton.icon(
              onPressed: _saving ? null : _accept,
              icon: _saving
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.check_rounded),
              label: const Text('Bestätigen'),
              style: FilledButton.styleFrom(
                backgroundColor: Colors.green.shade700,
                minimumSize: const Size(0, 50),
              ),
            )),
          ]),
        ],

        const SizedBox(height: 24),
      ]),
    );
  }

  Widget _section(String title, IconData icon, Color color) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(children: [
        Icon(icon, size: 18, color: color),
        const SizedBox(width: 8),
        Text(title, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: color)),
      ]),
    );
  }

  Widget _row(String label, String value) {
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SizedBox(width: 110, child: Text(label, style: TextStyle(fontSize: 13, color: cs.onSurfaceVariant))),
        Expanded(child: Text(value, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500))),
      ]),
    );
  }
}
