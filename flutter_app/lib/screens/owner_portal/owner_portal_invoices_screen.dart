import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class OwnerPortalInvoicesScreen extends StatefulWidget {
  const OwnerPortalInvoicesScreen({super.key});

  @override
  State<OwnerPortalInvoicesScreen> createState() => _OwnerPortalInvoicesScreenState();
}

class _OwnerPortalInvoicesScreenState extends State<OwnerPortalInvoicesScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _invoices = [];
  bool _loading = true;
  String? _error;

  static const _amber = Color(0xFFF59E0B);

  @override
  void initState() { super.initState(); _loadInvoices(); }

  Future<void> _loadInvoices() async {
    setState(() { _loading = true; _error = null; });
    try {
      final list = await _api.ownerPortalInvoices();
      setState(() { _invoices = list.map((e) => Map<String, dynamic>.from(e as Map)).toList(); _loading = false; });
    } catch (e) { setState(() { _loading = false; _error = e.toString(); }); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Rechnungen'),
        backgroundColor: _amber, foregroundColor: Colors.white),
      body: _loading
        ? Shimmer.fromColors(baseColor: Colors.grey.shade300, highlightColor: Colors.grey.shade100,
            child: ListView.builder(padding: const EdgeInsets.all(16), itemCount: 4,
              itemBuilder: (_, __) => Container(height: 95, margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18)))))
        : _error != null
            ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                const Icon(Icons.cloud_off_rounded, size: 48, color: Colors.grey),
                const SizedBox(height: 12), Text(_error!, textAlign: TextAlign.center),
                const SizedBox(height: 16),
                FilledButton.icon(onPressed: _loadInvoices,
                  icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut'),
                  style: FilledButton.styleFrom(backgroundColor: _amber))]))
            : RefreshIndicator(onRefresh: _loadInvoices, color: _amber,
                child: _invoices.isEmpty
                  ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      const Text('🧾', style: TextStyle(fontSize: 52)),
                      const SizedBox(height: 16),
                      const Text('Keine Rechnungen vorhanden',
                          style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                      const SizedBox(height: 8),
                      Text('Ihre Rechnungen erscheinen hier.',
                          style: TextStyle(fontSize: 13, color: Colors.grey.shade500))]))
                  : ListView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                      itemCount: _invoices.length,
                      itemBuilder: (context, i) {
                        final inv = _invoices[i];
                        final number = inv['invoice_number'] as String? ?? inv['rechnungsnummer'] as String? ?? inv['id']?.toString() ?? '';
                        final date   = inv['date'] as String? ?? inv['datum'] as String? ?? '';
                        final total  = inv['total'] as String? ?? inv['betrag'] as String? ?? inv['amount']?.toString() ?? '';
                        final status = inv['status'] as String? ?? '';
                        final isPaid = status == 'paid' || status == 'bezahlt';
                        final isOpen = status == 'open' || status == 'offen' || status == 'unpaid';
                        final statusColor = isPaid ? AppTheme.success : isOpen ? AppTheme.danger : AppTheme.warning;
                        final statusLabel = isPaid ? 'Bezahlt' : isOpen ? 'Offen' : status.isNotEmpty ? status : 'Ausstehend';

                        return TweenAnimationBuilder<double>(
                          tween: Tween(begin: 0, end: 1),
                          duration: Duration(milliseconds: 280 + i * 50),
                          curve: Curves.easeOutCubic,
                          builder: (_, v, child) => Opacity(opacity: v,
                            child: Transform.translate(offset: Offset(0, 16*(1-v)), child: child)),
                          child: Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: Container(
                              decoration: BoxDecoration(
                                color: Theme.of(context).colorScheme.brightness == Brightness.dark
                                    ? const Color(0xFF1A150A) : Colors.white,
                                borderRadius: BorderRadius.circular(18),
                                border: Border.all(color: _amber.withValues(alpha: 0.15)),
                              ),
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Row(children: [
                                  Container(width: 48, height: 48,
                                    decoration: BoxDecoration(color: _amber.withValues(alpha: 0.12),
                                      borderRadius: BorderRadius.circular(12)),
                                    child: const Icon(Icons.receipt_long_rounded, color: _amber, size: 22)),
                                  const SizedBox(width: 14),
                                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                    Text('Rechnung ${number.isNotEmpty ? "#$number" : ""}',
                                        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                                    if (date.isNotEmpty) Text(date, style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
                                    if (total.isNotEmpty) Text(total.contains('€') ? total : '$total €',
                                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: _amber)),
                                  ])),
                                  Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                                    Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                      decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.12),
                                        borderRadius: BorderRadius.circular(8),
                                        border: Border.all(color: statusColor.withValues(alpha: 0.3))),
                                      child: Text(statusLabel, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: statusColor))),
                                    const SizedBox(height: 4),
                                    GestureDetector(
                                      onTap: () => _openPdf(inv['id'] as int? ?? 0),
                                      child: Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                        decoration: BoxDecoration(color: Colors.grey.withValues(alpha: 0.1),
                                          borderRadius: BorderRadius.circular(6)),
                                        child: const Row(mainAxisSize: MainAxisSize.min, children: [
                                          Icon(Icons.picture_as_pdf_rounded, size: 14, color: Colors.grey),
                                          SizedBox(width: 4), Text('PDF', style: TextStyle(fontSize: 11, color: Colors.grey))])),
                                    ),
                                  ]),
                                ]),
                              ),
                            ),
                          ),
                        );
                      }),
              ),
    );
  }

  Future<void> _openPdf(int id) async {
    if (id == 0) return;
    final url = await _api.ownerPortalInvoicePdfUrl(id);
    if (url.isNotEmpty) {
      final uri = Uri.parse(url);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri);
      }
    }
  }
}
