import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';

class TaxExportScreen extends StatefulWidget {
  const TaxExportScreen({super.key});

  @override
  State<TaxExportScreen> createState() => _TaxExportScreenState();
}

class _TaxExportScreenState extends State<TaxExportScreen> {
  final ApiService _api = ApiService();
  Map<String, dynamic> _exports = {};
  Map<String, dynamic> _urls = {};
  Map<String, dynamic> _auditLog = {};
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _loading = true);
    try {
      final results = await Future.wait([
        _api.taxExportList(),
        _api.taxExportUrls(),
        _api.taxExportAuditLog(),
      ]);
      setState(() {
        _exports = results[0];
        _urls = results[1];
        _auditLog = results[2];
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
        title: const Text('Steuerexport'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadData,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Export-URLs', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 12),
                          if (_urls['csv_url'] != null)
                            ListTile(
                              title: const Text('CSV Export'),
                              trailing: IconButton(
                                icon: const Icon(Icons.download),
                                onPressed: () => _openUrl(_urls['csv_url'] as String),
                              ),
                            ),
                          if (_urls['pdf_url'] != null)
                            ListTile(
                              title: const Text('PDF Export'),
                              trailing: IconButton(
                                icon: const Icon(Icons.download),
                                onPressed: () => _openUrl(_urls['pdf_url'] as String),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Exports', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 12),
                          if (_exports['exports'] != null)
                            ...(_exports['exports'] as List).map((e) {
                              final export = e as Map<String, dynamic>;
                              return ListTile(
                                title: Text(export['period'] as String? ?? 'Zeitraum'),
                                subtitle: Text(export['status'] as String? ?? ''),
                                trailing: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    if (export['status'] == 'draft')
                                      TextButton(
                                        onPressed: () => _finalize(export['id'] as int),
                                        child: const Text('Finalisieren'),
                                      ),
                                    if (export['status'] == 'finalized')
                                      IconButton(
                                        icon: const Icon(Icons.cancel, color: Colors.red),
                                        onPressed: () => _cancel(export['id'] as int),
                                      ),
                                  ],
                                ),
                              );
                            }).toList(),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Audit Log', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 12),
                          if (_auditLog['entries'] != null)
                            ...(_auditLog['entries'] as List).map((e) {
                              final entry = e as Map<String, dynamic>;
                              return ListTile(
                                title: Text(entry['action'] as String? ?? ''),
                                subtitle: Text(entry['timestamp'] as String? ?? ''),
                              );
                            }).toList(),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Future<void> _openUrl(String url) async {
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  Future<void> _finalize(int id) async {
    await _api.taxExportFinalize(id);
    _loadData();
  }

  Future<void> _cancel(int id) async {
    await _api.taxExportCancel(id);
    _loadData();
  }
}
