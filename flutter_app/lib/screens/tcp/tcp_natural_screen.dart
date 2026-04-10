import 'package:flutter/material.dart';
import '../../services/api_service.dart';

class TcpNaturalScreen extends StatefulWidget {
  final int patientId;
  const TcpNaturalScreen({super.key, required this.patientId});

  @override
  State<TcpNaturalScreen> createState() => _TcpNaturalScreenState();
}

class _TcpNaturalScreenState extends State<TcpNaturalScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _entries = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadEntries();
  }

  Future<void> _loadEntries() async {
    setState(() => _loading = true);
    try {
      final list = await _api.tcpNaturalList(widget.patientId);
      setState(() {
        _entries = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
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
        title: const Text('Naturheilkunde'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadEntries,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _entries.length,
                itemBuilder: (context, index) {
                  final entry = _entries[index];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(entry['therapy_type'] as String? ?? 'Behandlung'),
                      subtitle: Text(entry['notes'] as String? ?? ''),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          IconButton(
                            icon: const Icon(Icons.edit),
                            onPressed: () => _editEntry(entry['id'] as int, entry),
                          ),
                          IconButton(
                            icon: const Icon(Icons.delete, color: Colors.red),
                            onPressed: () => _deleteEntry(entry['id'] as int),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
      floatingActionButton: FloatingActionButton(
        onPressed: _addEntry,
        child: const Icon(Icons.add),
      ),
    );
  }

  Future<void> _addEntry() async {
    final typeController = TextEditingController();
    final notesController = TextEditingController();
    
    if (!mounted) return;
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Naturheilkunde hinzufügen'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: typeController,
              decoration: const InputDecoration(hintText: 'Therapieart'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: notesController,
              decoration: const InputDecoration(hintText: 'Notizen'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Abbrechen'),
          ),
          TextButton(
            onPressed: () async {
              await _api.tcpNaturalCreate(widget.patientId, {
                'therapy_type': typeController.text,
                'notes': notesController.text,
              });
              Navigator.pop(context);
              _loadEntries();
            },
            child: const Text('Speichern'),
          ),
        ],
      ),
    );
  }

  Future<void> _editEntry(int id, Map<String, dynamic> entry) async {
    final typeController = TextEditingController(text: entry['therapy_type'] as String? ?? '');
    final notesController = TextEditingController(text: entry['notes'] as String? ?? '');
    
    if (!mounted) return;
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Eintrag bearbeiten'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: typeController,
              decoration: const InputDecoration(hintText: 'Therapieart'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: notesController,
              decoration: const InputDecoration(hintText: 'Notizen'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Abbrechen'),
          ),
          TextButton(
            onPressed: () async {
              await _api.tcpNaturalUpdate(id, {
                'therapy_type': typeController.text,
                'notes': notesController.text,
              });
              Navigator.pop(context);
              _loadEntries();
            },
            child: const Text('Speichern'),
          ),
        ],
      ),
    );
  }

  Future<void> _deleteEntry(int id) async {
    await _api.tcpNaturalDelete(id);
    _loadEntries();
  }
}
