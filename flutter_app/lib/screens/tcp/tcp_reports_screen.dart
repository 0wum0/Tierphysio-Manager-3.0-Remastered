import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';

class TcpReportsScreen extends StatefulWidget {
  final int? patientId;
  const TcpReportsScreen({super.key, this.patientId});

  @override
  State<TcpReportsScreen> createState() => _TcpReportsScreenState();
}

class _TcpReportsScreenState extends State<TcpReportsScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _reports = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadReports();
  }

  Future<void> _loadReports() async {
    setState(() => _loading = true);
    try {
      final list = widget.patientId != null
          ? await _api.tcpReportList(widget.patientId!)
          : [];
      setState(() {
        _reports = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
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
        title: const Text('Therapie-Berichte'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadReports,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _reports.length,
                itemBuilder: (context, index) {
                  final report = _reports[index];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(report['title'] as String? ?? 'Bericht'),
                      subtitle: Text(report['report_date'] as String? ?? ''),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          IconButton(
                            icon: const Icon(Icons.picture_as_pdf),
                            onPressed: () => _openPdf(report['id'] as int),
                          ),
                          IconButton(
                            icon: const Icon(Icons.delete, color: Colors.red),
                            onPressed: () => _deleteReport(report['id'] as int),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
      floatingActionButton: widget.patientId != null
          ? FloatingActionButton(
              onPressed: _createReport,
              child: const Icon(Icons.add),
            )
          : null,
    );
  }

  Future<void> _createReport() async {
    if (widget.patientId == null) return;
    final controller = TextEditingController();
    
    if (!mounted) return;
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Bericht erstellen'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(hintText: 'Titel'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Abbrechen'),
          ),
          TextButton(
            onPressed: () async {
              await _api.tcpReportCreate(widget.patientId!, {
                'title': controller.text,
                'report_date': DateTime.now().toIso8601String().substring(0, 10),
              });
              Navigator.pop(context);
              _loadReports();
            },
            child: const Text('Erstellen'),
          ),
        ],
      ),
    );
  }

  Future<void> _openPdf(int id) async {
    final url = await _api.tcpReportPdfUrl(id);
    if (url.isNotEmpty) {
      final uri = Uri.parse(url);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri);
      }
    }
  }

  Future<void> _deleteReport(int id) async {
    await _api.tcpReportDelete(id);
    _loadReports();
  }
}
