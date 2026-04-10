import 'package:flutter/material.dart';
import '../../services/api_service.dart';

class TcpRemindersScreen extends StatefulWidget {
  final int patientId;
  const TcpRemindersScreen({super.key, required this.patientId});

  @override
  State<TcpRemindersScreen> createState() => _TcpRemindersScreenState();
}

class _TcpRemindersScreenState extends State<TcpRemindersScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _templates = [];
  List<Map<String, dynamic>> _queue = [];
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
        _api.tcpReminderTemplates().then((l) => l.map((e) => Map<String, dynamic>.from(e as Map)).toList()),
        _api.tcpReminderQueue(widget.patientId).then((l) => l.map((e) => Map<String, dynamic>.from(e as Map)).toList()),
      ]);
      setState(() {
        _templates = results[0];
        _queue = results[1];
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
        title: const Text('Erinnerungs-Warteschlange'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadData,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  const Text('Vorlagen', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  ..._templates.map((t) => Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      title: Text(t['name'] as String? ?? 'Vorlage'),
                      subtitle: Text(t['description'] as String? ?? ''),
                    ),
                  )),
                  const SizedBox(height: 24),
                  const Text('Warteschlange', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  ..._queue.map((q) => Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      title: Text(q['template_name'] as String? ?? 'Erinnerung'),
                      subtitle: Text(q['scheduled_date'] as String? ?? ''),
                      trailing: IconButton(
                        icon: const Icon(Icons.delete, color: Colors.red),
                        onPressed: () => _deleteReminder(q['id'] as int),
                      ),
                    ),
                  )),
                ],
              ),
            ),
      floatingActionButton: FloatingActionButton(
        onPressed: _addReminder,
        child: const Icon(Icons.add),
      ),
    );
  }

  Future<void> _addReminder() async {
    if (_templates.isEmpty) return;
    final selectedTemplate = _templates[0];
    final dateController = TextEditingController();
    
    if (!mounted) return;
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Erinnerung hinzufügen'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(selectedTemplate['name'] as String? ?? 'Vorlage'),
            const SizedBox(height: 12),
            TextField(
              controller: dateController,
              decoration: const InputDecoration(hintText: 'Datum (YYYY-MM-DD)'),
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
              await _api.tcpReminderQueueStore(widget.patientId, {
                'template_id': selectedTemplate['id'],
                'scheduled_date': dateController.text,
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

  Future<void> _deleteReminder(int id) async {
    // Note: API doesn't have delete endpoint for queue items
    _loadData();
  }
}
