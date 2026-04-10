import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';

class OwnerPortalBefundeScreen extends StatefulWidget {
  const OwnerPortalBefundeScreen({super.key});

  @override
  State<OwnerPortalBefundeScreen> createState() => _OwnerPortalBefundeScreenState();
}

class _OwnerPortalBefundeScreenState extends State<OwnerPortalBefundeScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _befunde = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadBefunde();
  }

  Future<void> _loadBefunde() async {
    setState(() => _loading = true);
    try {
      final list = await _api.ownerPortalBefunde();
      setState(() {
        _befunde = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
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
        title: const Text('Befundbögen'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadBefunde,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _befunde.length,
                itemBuilder: (context, index) {
                  final befund = _befunde[index];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(befund['title'] as String? ?? ''),
                      subtitle: Text('${befund['patient_name'] as String? ?? ''} - ${befund['created_at'] as String? ?? ''}'),
                      trailing: IconButton(
                        icon: const Icon(Icons.picture_as_pdf),
                        onPressed: () => _openPdf(befund['id'] as int),
                      ),
                    ),
                  );
                },
              ),
            ),
    );
  }

  Future<void> _openPdf(int id) async {
    final url = await _api.ownerPortalBefundPdfUrl(id);
    if (url.isNotEmpty) {
      final uri = Uri.parse(url);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri);
      }
    }
  }
}
