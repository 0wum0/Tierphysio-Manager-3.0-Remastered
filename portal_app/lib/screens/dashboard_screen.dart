import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import '../services/portal_api_service.dart';
import 'pet_detail_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});
  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Map<String, dynamic>? _data;
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
      final auth = context.read<PortalAuthService>();
      final api  = PortalApiService(token: auth.token);
      final data = await api.getDashboard();
      if (mounted) setState(() { _data = data; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<PortalAuthService>();
    final cs   = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Hallo, ${auth.name.split(' ').first}!'),
          Text('Ihr Besitzerportal', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w400, color: cs.onSurfaceVariant)),
        ]),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _buildError()
              : RefreshIndicator(onRefresh: _load, child: _buildContent()),
    );
  }

  Widget _buildError() => Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
    const Icon(Icons.wifi_off_rounded, size: 48, color: AppTheme.danger),
    const SizedBox(height: 16),
    Text('Laden fehlgeschlagen', style: Theme.of(context).textTheme.titleMedium),
    const SizedBox(height: 8),
    Text(_error!, textAlign: TextAlign.center, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
    const SizedBox(height: 24),
    FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut versuchen')),
  ])));

  Widget _buildContent() {
    final pets          = List<dynamic>.from(_data?['pets'] ?? []);
    final appointments  = List<dynamic>.from(_data?['upcoming_appointments'] ?? []);
    final openInvoices  = _data?['open_invoices'] as int? ?? 0;
    final unreadMessages = _data?['unread_messages'] as int? ?? 0;

    return ListView(padding: const EdgeInsets.only(bottom: 24), children: [
      // Stats row
      Padding(padding: const EdgeInsets.fromLTRB(16, 16, 16, 8), child: Row(children: [
        _StatCard(icon: Icons.pets_rounded, color: AppTheme.primary,    label: 'Tiere',       value: pets.length.toString()),
        const SizedBox(width: 12),
        _StatCard(icon: Icons.receipt_long_rounded, color: AppTheme.warning, label: 'Offene Rechnungen', value: openInvoices.toString()),
        const SizedBox(width: 12),
        _StatCard(icon: Icons.chat_rounded, color: AppTheme.secondary,  label: 'Nachrichten', value: unreadMessages.toString(),
          highlight: unreadMessages > 0),
      ])),

      // My pets
      if (pets.isNotEmpty) ...[
        _SectionHeader(title: 'Meine Tiere'),
        SizedBox(height: 120, child: ListView.builder(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: pets.length,
          itemBuilder: (_, i) => _PetCard(pet: pets[i]),
        )),
      ],

      // Upcoming appointments
      _SectionHeader(title: 'Nächste Termine', action: appointments.isEmpty ? null : 'Alle'),
      if (appointments.isEmpty)
        Padding(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8), child:
          Text('Keine bevorstehenden Termine.', style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)))
      else
        ...appointments.take(3).map((a) => _AppointmentTile(appt: a)),
    ]);
  }
}

class _SectionHeader extends StatelessWidget {
  final String title;
  final String? action;
  const _SectionHeader({required this.title, this.action});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
    child: Row(children: [
      Text(title, style: Theme.of(context).textTheme.titleMedium),
      const Spacer(),
      if (action != null) TextButton(onPressed: () {}, child: Text(action!)),
    ]),
  );
}

class _StatCard extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String label;
  final String value;
  final bool highlight;
  const _StatCard({required this.icon, required this.color, required this.label, required this.value, this.highlight = false});
  @override
  Widget build(BuildContext context) => Expanded(child: Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: highlight ? color.withValues(alpha: 0.12) : Theme.of(context).cardTheme.color,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: highlight ? color.withValues(alpha: 0.4) : color.withValues(alpha: 0.15)),
    ),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(icon, color: color, size: 22),
      const SizedBox(height: 8),
      Text(value, style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: color)),
      Text(label, style: TextStyle(fontSize: 11, color: Theme.of(context).colorScheme.onSurfaceVariant), maxLines: 1, overflow: TextOverflow.ellipsis),
    ]),
  ));
}

class _PetCard extends StatelessWidget {
  final Map<String, dynamic> pet;
  const _PetCard({required this.pet});
  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PetDetailScreen(petId: pet['id'] as int))),
    child: Container(
      width: 100, margin: const EdgeInsets.only(right: 12),
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.primary.withValues(alpha: 0.15)),
      ),
      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        CircleAvatar(radius: 30, backgroundColor: AppTheme.primary.withValues(alpha: 0.1),
          backgroundImage: pet['photo_url'] != null ? NetworkImage(pet['photo_url'] as String) : null,
          child: pet['photo_url'] == null ? const Icon(Icons.pets_rounded, color: AppTheme.primary, size: 28) : null),
        const SizedBox(height: 8),
        Padding(padding: const EdgeInsets.symmetric(horizontal: 6), child: Text(
          pet['name'] as String? ?? '–',
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
          textAlign: TextAlign.center, maxLines: 1, overflow: TextOverflow.ellipsis)),
        Text(pet['species'] as String? ?? '', style: TextStyle(fontSize: 11, color: Theme.of(context).colorScheme.onSurfaceVariant)),
      ]),
    ),
  );
}

class _AppointmentTile extends StatelessWidget {
  final Map<String, dynamic> appt;
  const _AppointmentTile({required this.appt});
  @override
  Widget build(BuildContext context) {
    final cs   = Theme.of(context).colorScheme;
    final raw  = appt['start_at'] as String? ?? appt['start'] as String? ?? '';
    DateTime? dt;
    try { dt = DateTime.parse(raw).toLocal(); } catch (_) {}
    final dateStr = dt != null ? DateFormat('dd.MM.yyyy', 'de').format(dt) : raw;
    final timeStr = dt != null ? DateFormat('HH:mm', 'de').format(dt) : '';

    return Card(child: ListTile(
      leading: Container(width: 44, height: 44, decoration: BoxDecoration(
        color: AppTheme.tertiary.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
        child: const Icon(Icons.calendar_month_rounded, color: AppTheme.tertiary, size: 22)),
      title: Text(appt['title'] as String? ?? appt['type'] as String? ?? 'Termin',
        style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: Text('$dateStr${timeStr.isNotEmpty ? ' · $timeStr' : ''}',
        style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13)),
      trailing: appt['patient_name'] != null
          ? Text(appt['patient_name'] as String, style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant))
          : null,
    ));
  }
}
