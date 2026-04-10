import 'package:flutter/material.dart';
import '../../services/api_service.dart';

class OwnerPortalMessagesScreen extends StatefulWidget {
  const OwnerPortalMessagesScreen({super.key});

  @override
  State<OwnerPortalMessagesScreen> createState() => _OwnerPortalMessagesScreenState();
}

class _OwnerPortalMessagesScreenState extends State<OwnerPortalMessagesScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _threads = [];
  int _unread = 0;
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
        _api.ownerPortalThreadList().then((l) => l.map((e) => Map<String, dynamic>.from(e as Map)).toList()),
        _api.ownerPortalUnread(),
      ]);
      setState(() {
        _threads = results[0] as List<Map<String, dynamic>>;
        _unread = results[1] as int;
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
        title: const Text('Nachrichten'),
        actions: [
          Badge(
            label: Text('$_unread'),
            child: const Icon(Icons.mail),
          ),
          const SizedBox(width: 16),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadData,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _threads.length,
                itemBuilder: (context, index) {
                  final thread = _threads[index];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(thread['subject'] as String? ?? ''),
                      subtitle: Text(thread['last_message'] as String? ?? ''),
                      trailing: Text(thread['updated_at'] as String? ?? ''),
                      onTap: () => _openThread(thread['id'] as int),
                    ),
                  );
                },
              ),
            ),
      floatingActionButton: FloatingActionButton(
        onPressed: _newThread,
        child: const Icon(Icons.add),
      ),
    );
  }

  Future<void> _openThread(int id) async {
    Navigator.pushNamed(context, '/owner-portal/messages/$id');
  }

  Future<void> _newThread() async {
    final subjectController = TextEditingController();
    final messageController = TextEditingController();
    
    if (!mounted) return;
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Neue Nachricht'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: subjectController,
              decoration: const InputDecoration(hintText: 'Betreff'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: messageController,
              decoration: const InputDecoration(hintText: 'Nachricht'),
              maxLines: 5,
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
              await _api.ownerPortalNewThread({
                'subject': subjectController.text,
                'message': messageController.text,
              });
              Navigator.pop(context);
              _loadData();
            },
            child: const Text('Senden'),
          ),
        ],
      ),
    );
  }
}
