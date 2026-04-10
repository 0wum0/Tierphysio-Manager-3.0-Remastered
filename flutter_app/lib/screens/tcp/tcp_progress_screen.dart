import 'package:flutter/material.dart';
import '../../services/api_service.dart';

class TcpProgressScreen extends StatefulWidget {
  final int patientId;
  const TcpProgressScreen({super.key, required this.patientId});

  @override
  State<TcpProgressScreen> createState() => _TcpProgressScreenState();
}

class _TcpProgressScreenState extends State<TcpProgressScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _categories = [];
  List<Map<String, dynamic>> _progress = [];
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
        _api.tcpProgressCategories().then((l) => l.map((e) => Map<String, dynamic>.from(e as Map)).toList()),
        _api.tcpProgressList(widget.patientId).then((l) => l.map((e) => Map<String, dynamic>.from(e as Map)).toList()),
      ]);
      setState(() {
        _categories = results[0];
        _progress = results[1];
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
        title: const Text('Fortschritt-Tracking'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadData,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _categories.length,
                itemBuilder: (context, index) {
                  final category = _categories[index];
                  final categoryProgress = _progress.where((p) => p['category_id'] == category['id']).toList();
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ExpansionTile(
                      title: Text(category['name'] as String? ?? 'Kategorie'),
                      subtitle: Text('${categoryProgress.length} Einträge'),
                      children: categoryProgress.map((p) => ListTile(
                        title: Text(p['notes'] as String? ?? '-'),
                        subtitle: Text(p['entry_date'] as String? ?? ''),
                        trailing: IconButton(
                          icon: const Icon(Icons.delete, color: Colors.red),
                          onPressed: () => _deleteProgress(p['id'] as int),
                        ),
                      )).toList(),
                    ),
                  );
                },
              ),
            ),
      floatingActionButton: FloatingActionButton(
        onPressed: _addProgress,
        child: const Icon(Icons.add),
      ),
    );
  }

  Future<void> _addProgress() async {
    // Show dialog to add progress entry
    final controller = TextEditingController();
    final categoryId = _categories.isNotEmpty ? _categories[0]['id'] : null;
    
    if (!mounted) return;
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Fortschritt hinzufügen'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(hintText: 'Notizen'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Abbrechen'),
          ),
          TextButton(
            onPressed: () async {
              await _api.tcpProgressStore(widget.patientId, {
                'category_id': categoryId,
                'notes': controller.text,
                'entry_date': DateTime.now().toIso8601String().substring(0, 10),
              });
              Navigator.pop(context);
              _loadData();
            },
            child: const Text('Speichern'),
          ),
        ],
      ),
    );
  }

  Future<void> _deleteProgress(int entryId) async {
    await _api.tcpProgressDelete(entryId);
    _loadData();
  }
}
