import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import '../services/portal_api_service.dart';
import '../widgets/branded_loading.dart';

class AppointmentsScreen extends StatefulWidget {
  const AppointmentsScreen({super.key});
  @override
  State<AppointmentsScreen> createState() => _AppointmentsScreenState();
}

class _AppointmentsScreenState extends State<AppointmentsScreen> {
  List<dynamic> _items = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final auth = context.read<PortalAuthService>();
      final data = await PortalApiService(token: auth.token).get('/api/portal/mobile/termine');
      final raw  = data is List ? data : (data['appointments'] ?? []);
      if (mounted) setState(() { _items = List<dynamic>.from(raw); _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  Future<void> _cancel(int id) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Termin stornieren'),
        content: const Text('Möchten Sie diesen Termin wirklich stornieren?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            style: FilledButton.styleFrom(backgroundColor: AppTheme.danger),
            child: const Text('Stornieren'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    try {
      final auth = context.read<PortalAuthService>();
      await PortalApiService(token: auth.token).cancelAppointment(id);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Termin storniert.'), behavior: SnackBarBehavior.floating));
        _load();
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Fehler: $e'), behavior: SnackBarBehavior.floating));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Termine')),
      body: _loading
          ? const BrandedLoading()
          : _error != null
              ? _buildError()
              : RefreshIndicator(onRefresh: _load, child: _items.isEmpty
                  ? _buildEmpty()
                  : ListView.builder(
                      padding: const EdgeInsets.only(bottom: 24),
                      itemCount: _items.length,
                      itemBuilder: (_, i) => _ApptCard(
                        appt: _items[i] as Map<String, dynamic>,
                        onCancel: (_items[i]['can_cancel'] == true)
                            ? () => _cancel(int.tryParse(_items[i]['id'].toString()) ?? 0)
                            : null,
                      ),
                    )),
    );
  }

  Widget _buildEmpty() => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(Icons.event_available_rounded, size: 64, color: AppTheme.tertiary.withValues(alpha: 0.3)),
    const SizedBox(height: 16),
    const Text('Keine Termine', style: TextStyle(fontWeight: FontWeight.w600)),
    const SizedBox(height: 8),
    Text('Bevorstehende Termine werden hier angezeigt.', style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
  ]));

  Widget _buildError() => Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
    const Icon(Icons.wifi_off_rounded, size: 48, color: AppTheme.danger),
    const SizedBox(height: 12),
    Text(_error!, textAlign: TextAlign.center),
    const SizedBox(height: 24),
    FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut versuchen')),
  ])));
}

class _ApptCard extends StatelessWidget {
  final Map<String, dynamic> appt;
  final VoidCallback? onCancel;
  const _ApptCard({required this.appt, this.onCancel});

  static Color _statusColor(String? s) {
    switch (s) {
      case 'cancelled': case 'storniert': return AppTheme.danger;
      case 'completed': case 'abgeschlossen': return AppTheme.success;
      default: return AppTheme.tertiary;
    }
  }

  static String _statusLabel(String? s) {
    switch (s) {
      case 'cancelled': case 'storniert': return 'Storniert';
      case 'completed': case 'abgeschlossen': return 'Abgeschlossen';
      case 'confirmed': case 'bestätigt': return 'Bestätigt';
      default: return 'Geplant';
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs     = Theme.of(context).colorScheme;
    final raw    = appt['start_at'] as String? ?? appt['start'] as String? ?? '';
    DateTime? dt;
    try { dt = DateTime.parse(raw).toLocal(); } catch (_) {}

    final timeStr  = dt != null ? DateFormat('HH:mm', 'de').format(dt) : '';
    final duration = appt['duration_minutes'] as int?;
    final status   = appt['status'] as String?;
    final statusColor = _statusColor(status);
    final isCancelled = status == 'cancelled' || status == 'storniert';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(children: [
          // Date block
          if (dt != null) Container(
            width: 52, height: 58,
            decoration: BoxDecoration(
              color: isCancelled
                  ? AppTheme.danger.withValues(alpha: 0.08)
                  : AppTheme.tertiary.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              Text(DateFormat('dd', 'de').format(dt),
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800,
                  color: isCancelled ? AppTheme.danger : AppTheme.tertiary)),
              Text(DateFormat('MMM', 'de').format(dt).toUpperCase(),
                style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600,
                  color: isCancelled ? AppTheme.danger : AppTheme.tertiary)),
            ]),
          ) else const Icon(Icons.calendar_month_rounded, color: AppTheme.tertiary, size: 36),
          const SizedBox(width: 16),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(child: Text(
                appt['title'] as String? ?? appt['type'] as String? ?? 'Termin',
                style: TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 15,
                  color: isCancelled ? cs.onSurfaceVariant : null,
                  decoration: isCancelled ? TextDecoration.lineThrough : null,
                ),
              )),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(_statusLabel(status),
                  style: TextStyle(color: statusColor, fontSize: 10, fontWeight: FontWeight.w700)),
              ),
            ]),
            const SizedBox(height: 4),
            if (timeStr.isNotEmpty) Row(children: [
              const Icon(Icons.schedule_rounded, size: 13, color: AppTheme.tertiary),
              const SizedBox(width: 4),
              Text('$timeStr Uhr${duration != null ? ' · $duration Min' : ''}',
                  style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13)),
            ]),
            if (appt['patient_name'] != null) ...[
              const SizedBox(height: 2),
              Row(children: [
                const Icon(Icons.pets_rounded, size: 13, color: AppTheme.primary),
                const SizedBox(width: 4),
                Text(appt['patient_name'] as String, style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13)),
              ]),
            ],
            if (onCancel != null) ...[
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: onCancel,
                icon: const Icon(Icons.cancel_outlined, size: 16),
                label: const Text('Stornieren', style: TextStyle(fontSize: 13)),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppTheme.danger,
                  side: const BorderSide(color: AppTheme.danger),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
              ),
            ],
          ])),
        ]),
      ),
    );
  }
}
