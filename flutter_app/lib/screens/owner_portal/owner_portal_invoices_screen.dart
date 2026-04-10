import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';

class OwnerPortalInvoicesScreen extends StatefulWidget {
  const OwnerPortalInvoicesScreen({super.key});

  @override
  State<OwnerPortalInvoicesScreen> createState() => _OwnerPortalInvoicesScreenState();
}

class _OwnerPortalInvoicesScreenState extends State<OwnerPortalInvoicesScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _invoices = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadInvoices();
  }

  Future<void> _loadInvoices() async {
    setState(() => _loading = true);
    try {
      final list = await _api.ownerPortalInvoices();
      setState(() {
        _invoices = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _loading = false;
      });
    } catch (_) {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Rechnungen'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadInvoices,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _invoices.length,
                itemBuilder: (context, index) {
                  final invoice = _invoices[index];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text('Rechnung #${invoice['invoice_number'] as String? ?? ''}'),
                      subtitle: Text('${invoice['total'] as String? ?? ''} € - ${invoice['status'] as String? ?? ''}'),
                      trailing: IconButton(
                        icon: const Icon(Icons.picture_as_pdf),
                        onPressed: () => _openPdf(invoice['id'] as int),
                      ),
                    ),
                  );
                },
              ),
            ),
    );
  }

  Future<void> _openPdf(int id) async {
    final url = await _api.ownerPortalInvoicePdfUrl(id);
    if (url.isNotEmpty) {
      final uri = Uri.parse(url);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri);
      }
    }
  }
}
