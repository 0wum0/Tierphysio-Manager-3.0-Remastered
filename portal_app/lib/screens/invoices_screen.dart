import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import '../services/portal_api_service.dart';
import '../widgets/branded_loading.dart';

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
    final auth = context.read<PortalAuthService>();
    final url  = PortalApiService.invoicePdfUrl(id, auth.token ?? '');
    try {
      await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('PDF konnte nicht geöffnet werden: $e'), behavior: SnackBarBehavior.floating));
    }
  }

  Future<void> _showPaymentSheet(int id) async {
    final auth = context.read<PortalAuthService>();
    Map<String, dynamic>? info;
    bool loading = true;
    String? err;

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) {
          if (loading) {
            PortalApiService(token: auth.token).getInvoicePaymentInfo(id).then((data) {
              if (ctx.mounted) setSheetState(() { info = data; loading = false; });
            }).catchError((e) {
              if (ctx.mounted) setSheetState(() { err = e.toString(); loading = false; });
            });
            loading = false; // prevent re-trigger on rebuild
          }
          return _PaymentSheet(info: info, error: err);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Rechnungen')),
      body: _loading
          ? const BrandedLoading()
          : _error != null
              ? _buildError()
              : RefreshIndicator(onRefresh: _load, child: _items.isEmpty
                  ? _buildEmpty()
                  : ListView.builder(
                      padding: const EdgeInsets.only(bottom: 24),
                      itemCount: _items.length,
                      itemBuilder: (_, i) {
                        final inv    = _items[i] as Map<String, dynamic>;
                        final id     = int.tryParse(inv['id'].toString()) ?? 0;
                        final status = inv['status'] as String?;
                        final isOpen = status == 'open' || status == 'offen' ||
                                       status == 'overdue' || status == 'überfällig';
                        return _InvoiceTile(
                          invoice: inv,
                          onPdf: () => _openPdf(id),
                          onPay: isOpen ? () => _showPaymentSheet(id) : null,
                        );
                      },
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

// ── Payment info bottom sheet ─────────────────────────────────────────────────

class _PaymentSheet extends StatelessWidget {
  final Map<String, dynamic>? info;
  final String? error;
  const _PaymentSheet({this.info, this.error});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 20, 24, 32),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(width: 40, height: 40,
            decoration: BoxDecoration(color: AppTheme.success.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
            child: const Icon(Icons.account_balance_rounded, color: AppTheme.success, size: 20)),
          const SizedBox(width: 12),
          const Text('Jetzt bezahlen', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
        ]),
        const SizedBox(height: 4),
        Text('Überweisungsdaten für Ihre Banking-App', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13)),
        const SizedBox(height: 20),

        if (error != null) ...[
          Icon(Icons.error_outline_rounded, color: AppTheme.danger, size: 40),
          const SizedBox(height: 8),
          Text('Daten konnten nicht geladen werden', style: TextStyle(color: cs.onSurfaceVariant)),
        ] else if (info == null) ...[
          const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator())),
        ] else ...[
          _PayRow(label: 'Empfänger',        value: info!['recipient'] as String? ?? '–'),
          const Divider(height: 24),
          _PayRow(label: 'IBAN',             value: _formatIban(info!['iban'] as String? ?? '–')),
          const Divider(height: 24),
          if ((info!['bic'] as String? ?? '').isNotEmpty) ...[
            _PayRow(label: 'BIC',            value: info!['bic'] as String),
            const Divider(height: 24),
          ],
          _PayRow(label: 'Betrag',           value: '€ ${(info!['amount'] as num? ?? 0).toStringAsFixed(2)}'),
          const Divider(height: 24),
          _PayRow(label: 'Verwendungszweck', value: info!['reference'] as String? ?? '–'),
          const SizedBox(height: 24),
          SizedBox(width: double.infinity, child: FilledButton.icon(
            onPressed: () => _copyAll(context),
            icon: const Icon(Icons.copy_rounded),
            label: const Text('Alle Daten kopieren'),
            style: FilledButton.styleFrom(backgroundColor: AppTheme.success),
          )),
          const SizedBox(height: 8),
          SizedBox(width: double.infinity, child: OutlinedButton.icon(
            onPressed: () => Navigator.pop(context),
            icon: const Icon(Icons.close_rounded),
            label: const Text('Schließen'),
          )),
        ],
      ]),
    );
  }

  static String _formatIban(String raw) {
    final clean = raw.replaceAll(' ', '');
    final buf   = StringBuffer();
    for (var i = 0; i < clean.length; i++) {
      if (i > 0 && i % 4 == 0) buf.write(' ');
      buf.write(clean[i]);
    }
    return buf.toString();
  }

  void _copyAll(BuildContext context) {
    if (info == null) return;
    final text = [
      'Empfänger: ${info!['recipient'] ?? ''}',
      'IBAN: ${_formatIban(info!['iban'] as String? ?? '')}',
      if ((info!['bic'] as String? ?? '').isNotEmpty) 'BIC: ${info!['bic']}',
      'Betrag: € ${(info!['amount'] as num? ?? 0).toStringAsFixed(2)}',
      'Verwendungszweck: ${info!['reference'] ?? ''}',
    ].join('\n');
    Clipboard.setData(ClipboardData(text: text));
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Überweisungsdaten kopiert'), behavior: SnackBarBehavior.floating));
  }
}

class _PayRow extends StatelessWidget {
  final String label;
  final String value;
  const _PayRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      SizedBox(width: 120, child: Text(label, style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13))),
      Expanded(child: Row(children: [
        Expanded(child: Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14))),
        IconButton(
          icon: Icon(Icons.copy_rounded, size: 16, color: cs.onSurfaceVariant),
          onPressed: () {
            Clipboard.setData(ClipboardData(text: value));
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('$label kopiert'), behavior: SnackBarBehavior.floating,
                duration: const Duration(seconds: 1)));
          },
          padding: EdgeInsets.zero,
          constraints: const BoxConstraints(),
          visualDensity: VisualDensity.compact,
          tooltip: 'Kopieren',
        ),
      ])),
    ]);
  }
}

// ── Invoice tile ──────────────────────────────────────────────────────────────

class _InvoiceTile extends StatelessWidget {
  final Map<String, dynamic> invoice;
  final VoidCallback onPdf;
  final VoidCallback? onPay;
  const _InvoiceTile({required this.invoice, required this.onPdf, this.onPay});

  Color _statusColor(String? status) {
    switch (status) {
      case 'paid': case 'bezahlt': return AppTheme.success;
      case 'overdue': case 'überfällig': return AppTheme.danger;
      case 'open': case 'offen': return AppTheme.warning;
      default: return AppTheme.tertiary;
    }
  }

  String _statusLabel(String? status) {
    switch (status) {
      case 'paid': case 'bezahlt': return 'Bezahlt';
      case 'overdue': case 'überfällig': return 'Überfällig';
      case 'open': case 'offen': return 'Offen';
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
          ?? ((invoice['total_cents'] is int ? invoice['total_cents'] as int
               : int.tryParse(invoice['total_cents']?.toString() ?? '') ?? 0) / 100);
    final number = invoice['invoice_number'] as String? ?? invoice['number'] as String? ?? '–';

    return Card(child: Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 12, 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(width: 44, height: 44,
            decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
            child: Icon(Icons.receipt_long_rounded, color: color, size: 22)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Rechnung $number', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
            const SizedBox(height: 4),
            Row(children: [
              Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(6)),
                child: Text(_statusLabel(status), style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700))),
              const SizedBox(width: 8),
              Text(invoice['date'] as String? ?? '', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12)),
            ]),
          ])),
          Text('€ ${total.toStringAsFixed(2)}',
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
        ]),
        const SizedBox(height: 10),
        Row(children: [
          OutlinedButton.icon(
            onPressed: onPdf,
            icon: const Icon(Icons.picture_as_pdf_rounded, size: 16),
            label: const Text('PDF öffnen'),
            style: OutlinedButton.styleFrom(
              foregroundColor: AppTheme.danger,
              side: BorderSide(color: AppTheme.danger.withValues(alpha: 0.6)),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              minimumSize: Size.zero,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
            ),
          ),
          if (onPay != null) ...[
            const SizedBox(width: 8),
            FilledButton.icon(
              onPressed: onPay,
              icon: const Icon(Icons.account_balance_rounded, size: 16),
              label: const Text('Jetzt bezahlen'),
              style: FilledButton.styleFrom(
                backgroundColor: AppTheme.success,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
              ),
            ),
          ],
        ]),
      ]),
    ));
  }
}
