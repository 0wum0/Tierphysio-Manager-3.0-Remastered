import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import '../services/portal_api_service.dart';

class InvoicesScreen extends StatefulWidget {
  const InvoicesScreen({super.key});
  @override
  State<InvoicesScreen> createState() => _InvoicesScreenState();
}

class _InvoicesScreenState extends State<InvoicesScreen> {
  List<dynamic> _items = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final auth = context.read<PortalAuthService>();
      final data = await PortalApiService(token: auth.token).get('/api/portal/mobile/rechnungen');
      final raw  = data is List ? data : (data['invoices'] ?? []);
      if (mounted) setState(() { _items = List<dynamic>.from(raw); _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  Future<void> _openPdf(int id) async {
    try {
      final auth = context.read<PortalAuthService>();
      final url  = await PortalApiService(token: auth.token).getInvoicePdfUrl(id);
      if (url.isNotEmpty && mounted) {
        await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Fehler: $e')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Rechnungen')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _buildError()
              : RefreshIndicator(onRefresh: _load, child: _items.isEmpty
                  ? _buildEmpty()
                  : ListView.builder(
                      padding: const EdgeInsets.only(bottom: 24),
                      itemCount: _items.length,
                      itemBuilder: (_, i) => _InvoiceTile(
        invoice: _items[i] as Map<String, dynamic>,
        onPdf: () => _openPdf(int.tryParse(_items[i]['id'].toString()) ?? 0),
      ),
                    )),
    );
  }

  Widget _buildEmpty() => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(Icons.receipt_long_rounded, size: 64, color: AppTheme.warning.withValues(alpha: 0.3)),
    const SizedBox(height: 16),
    const Text('Keine Rechnungen', style: TextStyle(fontWeight: FontWeight.w600)),
    const SizedBox(height: 8),
    Text('Ihre Rechnungen werden hier angezeigt.', style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
  ]));

  Widget _buildError() => Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
    const Icon(Icons.wifi_off_rounded, size: 48, color: AppTheme.danger),
    const SizedBox(height: 12),
    Text(_error!, textAlign: TextAlign.center),
    const SizedBox(height: 24),
    FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut')),
  ])));
}

class _InvoiceTile extends StatelessWidget {
  final Map<String, dynamic> invoice;
  final VoidCallback onPdf;
  const _InvoiceTile({required this.invoice, required this.onPdf});

  Color _statusColor(String? status) {
    switch (status) {
      case 'paid': return AppTheme.success;
      case 'overdue': return AppTheme.danger;
      case 'open': return AppTheme.warning;
      default: return AppTheme.tertiary;
    }
  }

  String _statusLabel(String? status) {
    switch (status) {
      case 'paid': return 'Bezahlt';
      case 'overdue': return 'Überfällig';
      case 'open': return 'Offen';
      default: return status ?? '–';
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs     = Theme.of(context).colorScheme;
    final status = invoice['status'] as String?;
    final color  = _statusColor(status);
    final rawTotal = invoice['total_eur'];
    final total = rawTotal is num
        ? rawTotal.toDouble()
        : double.tryParse(rawTotal?.toString() ?? '')
          ?? ((invoice['total_cents'] is int ? invoice['total_cents'] as int : int.tryParse(invoice['total_cents']?.toString() ?? '') ?? 0) / 100);
    final number = invoice['invoice_number'] as String? ?? invoice['number'] as String? ?? '–';

    return Card(child: ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      leading: Container(width: 44, height: 44,
        decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
        child: Icon(Icons.receipt_long_rounded, color: color, size: 22)),
      title: Text('Rechnung $number', style: const TextStyle(fontWeight: FontWeight.w700)),
      subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const SizedBox(height: 2),
        Row(children: [
          Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
            decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(6)),
            child: Text(_statusLabel(status), style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700))),
          const SizedBox(width: 8),
          Text(invoice['date'] as String? ?? '', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12)),
        ]),
      ]),
      trailing: Row(mainAxisSize: MainAxisSize.min, children: [
        Text('€ ${total.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
        const SizedBox(width: 4),
        IconButton(icon: const Icon(Icons.picture_as_pdf_rounded, color: AppTheme.danger), onPressed: onPdf, tooltip: 'PDF öffnen'),
      ]),
    ));
  }
}
