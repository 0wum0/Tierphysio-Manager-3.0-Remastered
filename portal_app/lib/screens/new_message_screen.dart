import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import '../services/portal_api_service.dart';

class NewMessageScreen extends StatefulWidget {
  const NewMessageScreen({super.key});
  @override
  State<NewMessageScreen> createState() => _NewMessageScreenState();
}

class _NewMessageScreenState extends State<NewMessageScreen> {
  final _subjectCtrl = TextEditingController();
  final _bodyCtrl    = TextEditingController();
  bool _sending = false;
  String? _error;

  @override
  void dispose() { _subjectCtrl.dispose(); _bodyCtrl.dispose(); super.dispose(); }

  Future<void> _send() async {
    final subject = _subjectCtrl.text.trim();
    final body    = _bodyCtrl.text.trim();
    if (subject.isEmpty || body.isEmpty) {
      setState(() => _error = 'Bitte Betreff und Nachricht eingeben.');
      return;
    }
    setState(() { _sending = true; _error = null; });
    try {
      final auth = context.read<PortalAuthService>();
      await PortalApiService(token: auth.token).newThread(subject, body);
      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _sending = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Neue Nachricht'),
        actions: [
          TextButton(
            onPressed: _sending ? null : _send,
            child: _sending
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.5))
                : const Text('Senden', style: TextStyle(fontWeight: FontWeight.w700)),
          ),
        ],
      ),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(children: [
          TextField(
            controller: _subjectCtrl,
            textInputAction: TextInputAction.next,
            decoration: const InputDecoration(labelText: 'Betreff'),
          ),
          const SizedBox(height: 16),
          Expanded(child: TextField(
            controller: _bodyCtrl,
            maxLines: null,
            expands: true,
            textAlignVertical: TextAlignVertical.top,
            decoration: const InputDecoration(
              labelText: 'Nachricht',
              alignLabelWithHint: true,
            ),
          )),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppTheme.danger.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppTheme.danger.withValues(alpha: 0.3)),
              ),
              child: Text(_error!, style: const TextStyle(color: AppTheme.danger)),
            ),
          ],
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: _sending ? null : _send,
            icon: const Icon(Icons.send_rounded),
            label: const Text('Nachricht senden'),
          ),
        ]),
      ),
    );
  }
}
