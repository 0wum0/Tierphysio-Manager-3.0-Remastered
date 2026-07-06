import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import '../services/portal_api_service.dart';
import 'message_thread_screen.dart';
import 'new_message_screen.dart';

class MessagesScreen extends StatefulWidget {
  const MessagesScreen({super.key});
  @override
  State<MessagesScreen> createState() => _MessagesScreenState();
}

class _MessagesScreenState extends State<MessagesScreen> {
  List<dynamic> _threads = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final auth = context.read<PortalAuthService>();
      final data = await PortalApiService(token: auth.token).get('/api/mobile/portal/nachrichten');
      final raw  = data is List ? data : (data['threads'] ?? []);
      if (mounted) setState(() { _threads = List<dynamic>.from(raw); _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Nachrichten'),
        actions: [
          IconButton(
            icon: const Icon(Icons.edit_rounded),
            tooltip: 'Neue Nachricht',
            onPressed: () async {
              await Navigator.push(context, MaterialPageRoute(builder: (_) => const NewMessageScreen()));
              _load();
            },
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _buildError()
              : RefreshIndicator(onRefresh: _load, child: _threads.isEmpty
                  ? _buildEmpty()
                  : ListView.separated(
                      padding: const EdgeInsets.only(bottom: 24),
                      itemCount: _threads.length,
                      separatorBuilder: (_, __) => const Divider(indent: 72, height: 1),
                      itemBuilder: (_, i) => _ThreadTile(
                        thread: _threads[i] as Map<String, dynamic>,
                        onTap: () async {
                          await Navigator.push(context, MaterialPageRoute(
                            builder: (_) => MessageThreadScreen(
                              threadId: _threads[i]['id'] as int,
                              subject: _threads[i]['subject'] as String? ?? 'Nachricht',
                            )));
                          _load();
                        },
                      ),
                    )),
    );
  }

  Widget _buildEmpty() => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(Icons.chat_bubble_outline_rounded, size: 64, color: AppTheme.secondary.withValues(alpha: 0.3)),
    const SizedBox(height: 16),
    const Text('Keine Nachrichten', style: TextStyle(fontWeight: FontWeight.w600)),
    const SizedBox(height: 8),
    Text('Ihre Konversationen erscheinen hier.', style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
    const SizedBox(height: 24),
    FilledButton.icon(
      onPressed: () async {
        await Navigator.push(context, MaterialPageRoute(builder: (_) => const NewMessageScreen()));
        _load();
      },
      icon: const Icon(Icons.edit_rounded),
      label: const Text('Erste Nachricht schreiben'),
    ),
  ]));

  Widget _buildError() => Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
    const Icon(Icons.wifi_off_rounded, size: 48, color: AppTheme.danger),
    const SizedBox(height: 12),
    Text(_error!, textAlign: TextAlign.center),
    const SizedBox(height: 24),
    FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut')),
  ])));
}

class _ThreadTile extends StatelessWidget {
  final Map<String, dynamic> thread;
  final VoidCallback onTap;
  const _ThreadTile({required this.thread, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final cs       = Theme.of(context).colorScheme;
    final unread   = (thread['unread_count'] as int? ?? 0) > 0;
    final lastMsg  = thread['last_message'] as String? ?? '';

    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      leading: CircleAvatar(
        radius: 22,
        backgroundColor: AppTheme.secondary.withValues(alpha: 0.12),
        child: Icon(Icons.support_agent_rounded, color: AppTheme.secondary, size: unread ? 24 : 20),
      ),
      title: Text(
        thread['subject'] as String? ?? 'Nachricht',
        style: TextStyle(fontWeight: unread ? FontWeight.w700 : FontWeight.w500, fontSize: 15),
      ),
      subtitle: lastMsg.isNotEmpty
          ? Text(lastMsg, maxLines: 1, overflow: TextOverflow.ellipsis,
              style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13,
                  fontWeight: unread ? FontWeight.w600 : FontWeight.w400))
          : null,
      trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
        Text(thread['updated_at'] as String? ?? '', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 11)),
        if (unread) ...[
          const SizedBox(height: 4),
          Container(width: 8, height: 8, decoration: const BoxDecoration(shape: BoxShape.circle, color: AppTheme.secondary)),
        ],
      ]),
      onTap: onTap,
    );
  }
}
