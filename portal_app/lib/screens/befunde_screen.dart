import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import '../services/portal_api_service.dart';

class BefundeScreen extends StatefulWidget {
  const BefundeScreen({super.key});
  @override
  State<BefundeScreen> createState() => _BefundeScreenState();
}

class _BefundeScreenState extends State<BefundeScreen> {
  List<dynamic> _items = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final auth = context.read<PortalAuthService>();
      final data = await PortalApiService(token: auth.token).get('/api/portal/mobile/befunde');
      final raw  = data is List ? data : (data['befunde'] ?? []);
      if (mounted) setState(() { _items = List<dynamic>.from(raw); _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  Future<void> _openPdf(int id) async {
    try {
      final auth = context.read<PortalAuthService>();
      final url  = await PortalApiService(token: auth.token).getBefundPdfUrl(id);
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
      appBar: AppBar(title: const Text('Befundbögen')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _buildError()
              : RefreshIndicator(onRefresh: _load, child: _items.isEmpty
                  ? _buildEmpty()
                  : ListView.builder(
                      padding: const EdgeInsets.only(bottom: 24),
                      itemCount: _items.length,
                      itemBuilder: (_, i) => _BefundTile(
                        befund: _items[i] as Map<String, dynamic>,
                        onPdf: () => _openPdf(_items[i]['id'] as int),
                      ),
                    )),
    );
  }

  Widget _buildEmpty() => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(Icons.description_rounded, size: 64, color: AppTheme.tertiary.withValues(alpha: 0.3)),
    const SizedBox(height: 16),
    const Text('Keine Befundbögen', style: TextStyle(fontWeight: FontWeight.w600)),
    const SizedBox(height: 8),
    Text('Ihre Befundbögen werden hier angezeigt.', style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
  ]));

  Widget _buildError() => Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
    const Icon(Icons.wifi_off_rounded, size: 48, color: AppTheme.danger),
    const SizedBox(height: 12),
    Text(_error!, textAlign: TextAlign.center),
    const SizedBox(height: 24),
    FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut')),
  ])));
}

class _BefundTile extends StatelessWidget {
  final Map<String, dynamic> befund;
  final VoidCallback onPdf;
  const _BefundTile({required this.befund, required this.onPdf});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Card(child: ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      leading: Container(width: 44, height: 44,
        decoration: BoxDecoration(color: AppTheme.tertiary.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
        child: const Icon(Icons.description_rounded, color: AppTheme.tertiary, size: 22)),
      title: Text(befund['title'] as String? ?? befund['patient_name'] as String? ?? 'Befundbogen',
          style: const TextStyle(fontWeight: FontWeight.w700)),
      subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (befund['patient_name'] != null) ...[
          const SizedBox(height: 2),
          Text(befund['patient_name'] as String, style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13)),
        ],
        if (befund['date'] != null) ...[
          const SizedBox(height: 2),
          Text(befund['date'] as String, style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12)),
        ],
      ]),
      trailing: IconButton(
        icon: const Icon(Icons.picture_as_pdf_rounded, color: AppTheme.danger),
        tooltip: 'PDF öffnen',
        onPressed: onPdf,
      ),
    ));
  }
}
