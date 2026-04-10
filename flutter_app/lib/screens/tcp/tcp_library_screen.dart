import 'package:flutter/material.dart';
import '../../services/api_service.dart';

class TcpLibraryScreen extends StatefulWidget {
  const TcpLibraryScreen({super.key});

  @override
  State<TcpLibraryScreen> createState() => _TcpLibraryScreenState();
}

class _TcpLibraryScreenState extends State<TcpLibraryScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _exercises = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadExercises();
  }

  Future<void> _loadExercises() async {
    setState(() => _loading = true);
    try {
      final list = await _api.tcpLibraryList();
      setState(() {
        _exercises = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
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
        title: const Text('Übungs-Bibliothek'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadExercises,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _exercises.length,
                itemBuilder: (context, index) {
                  final exercise = _exercises[index];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(exercise['title'] as String? ?? 'Übung'),
                      subtitle: Text(exercise['description'] as String? ?? ''),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          IconButton(
                            icon: const Icon(Icons.edit),
                            onPressed: () => _editExercise(exercise['id'] as int, exercise),
                          ),
                          IconButton(
                            icon: const Icon(Icons.delete, color: Colors.red),
                            onPressed: () => _deleteExercise(exercise['id'] as int),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
      floatingActionButton: FloatingActionButton(
        onPressed: _addExercise,
        child: const Icon(Icons.add),
      ),
    );
  }

  Future<void> _addExercise() async {
    final titleController = TextEditingController();
    final descController = TextEditingController();
    
    if (!mounted) return;
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Übung hinzufügen'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: titleController,
              decoration: const InputDecoration(hintText: 'Titel'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: descController,
              decoration: const InputDecoration(hintText: 'Beschreibung'),
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
              await _api.tcpLibraryCreate({
                'title': titleController.text,
                'description': descController.text,
                'sort_order': 0,
                'is_active': 1,
              });
              Navigator.pop(context);
              _loadExercises();
            },
            child: const Text('Speichern'),
          ),
        ],
      ),
    );
  }

  Future<void> _editExercise(int id, Map<String, dynamic> exercise) async {
    final titleController = TextEditingController(text: exercise['title'] as String? ?? '');
    final descController = TextEditingController(text: exercise['description'] as String? ?? '');
    
    if (!mounted) return;
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Übung bearbeiten'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: titleController,
              decoration: const InputDecoration(hintText: 'Titel'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: descController,
              decoration: const InputDecoration(hintText: 'Beschreibung'),
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
              await _api.tcpLibraryUpdate(id, {
                'title': titleController.text,
                'description': descController.text,
              });
              Navigator.pop(context);
              _loadExercises();
            },
            child: const Text('Speichern'),
          ),
        ],
      ),
    );
  }

  Future<void> _deleteExercise(int id) async {
    await _api.tcpLibraryDelete(id);
    _loadExercises();
  }
}
