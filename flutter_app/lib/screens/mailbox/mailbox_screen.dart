import 'package:flutter/material.dart';
import '../../services/api_service.dart';

class MailboxScreen extends StatefulWidget {
  const MailboxScreen({super.key});

  @override
  State<MailboxScreen> createState() => _MailboxScreenState();
}

class _MailboxScreenState extends State<MailboxScreen> {
  final ApiService _api = ApiService();
  Map<String, dynamic> _status = {};
  Map<String, dynamic> _messages = {};
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
        _api.mailboxStatus(),
        _api.mailboxList(),
      ]);
      setState(() {
        _status = results[0];
        _messages = results[1];
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
        title: const Text('Mailbox'),
        actions: [
          IconButton(
            icon: const Icon(Icons.send),
            onPressed: _compose,
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadData,
              child: Column(
                children: [
                  Card(
                    margin: const EdgeInsets.all(16),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Status: ${_status['connected'] == true ? 'Verbunden' : 'Getrennt'}'),
                          Text('Ungelesen: ${_status['unread'] as int? ?? 0}'),
                        ],
                      ),
                    ),
                  ),
                  Expanded(
                    child: ListView.builder(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      itemCount: _messages['messages'] != null ? (_messages['messages'] as List).length : 0,
                      itemBuilder: (context, index) {
                        final msg = (_messages['messages'] as List)[index] as Map<String, dynamic>;
                        return Card(
                          margin: const EdgeInsets.only(bottom: 8),
                          child: ListTile(
                            title: Text(msg['subject'] as String? ?? ''),
                            subtitle: Text('${msg['from'] as String? ?? ''} - ${msg['date'] as String? ?? ''}'),
                            onTap: () => _openMessage(msg['uid'] as String),
                            trailing: IconButton(
                              icon: const Icon(Icons.delete, color: Colors.red),
                              onPressed: () => _deleteMessage(msg['uid'] as String),
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Future<void> _compose() async {
    final toController = TextEditingController();
    final subjectController = TextEditingController();
    final bodyController = TextEditingController();
    
    if (!mounted) return;
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('E-Mail senden'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: toController,
              decoration: const InputDecoration(hintText: 'An'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: subjectController,
              decoration: const InputDecoration(hintText: 'Betreff'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: bodyController,
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
              await _api.mailboxSend({
                'to': toController.text,
                'subject': subjectController.text,
                'body': bodyController.text,
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

  Future<void> _openMessage(String uid) async {
    final msg = await _api.mailboxShow(uid);
    
    if (!mounted) return;
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(msg['subject'] as String? ?? ''),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('Von: ${msg['from'] as String? ?? ''}'),
              const SizedBox(height: 8),
              Text(msg['body'] as String? ?? ''),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Schließen'),
          ),
        ],
      ),
    );
  }

  Future<void> _deleteMessage(String uid) async {
    await _api.mailboxDelete(uid);
    _loadData();
  }
}
