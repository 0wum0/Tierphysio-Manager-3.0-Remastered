import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import '../services/portal_api_service.dart';

class MessageThreadScreen extends StatefulWidget {
  final int threadId;
  final String subject;
  const MessageThreadScreen({super.key, required this.threadId, required this.subject});
  @override
  State<MessageThreadScreen> createState() => _MessageThreadScreenState();
}

class _MessageThreadScreenState extends State<MessageThreadScreen> {
  List<dynamic> _messages = [];
  bool _loading = true;
  bool _sending = false;
  String? _error;
  final _replyCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();

  @override
  void initState() { super.initState(); _load(); }
  @override
  void dispose() { _replyCtrl.dispose(); _scrollCtrl.dispose(); super.dispose(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final auth = context.read<PortalAuthService>();
      final data = await PortalApiService(token: auth.token).getThread(widget.threadId);
      if (mounted) {
        setState(() {
          _messages = List<dynamic>.from(data['messages'] ?? []);
          _loading  = false;
        });
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (_scrollCtrl.hasClients) _scrollCtrl.jumpTo(_scrollCtrl.position.maxScrollExtent);
        });
      }
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  Future<void> _send() async {
    final text = _replyCtrl.text.trim();
    if (text.isEmpty) return;
    setState(() => _sending = true);
    try {
      final auth = context.read<PortalAuthService>();
      await PortalApiService(token: auth.token).replyThread(widget.threadId, text);
      _replyCtrl.clear();
      await _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Fehler: $e')));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.subject, overflow: TextOverflow.ellipsis)),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : Column(children: [
                  Expanded(child: ListView.builder(
                    controller: _scrollCtrl,
                    padding: const EdgeInsets.all(16),
                    itemCount: _messages.length,
                    itemBuilder: (_, i) => _MessageBubble(msg: _messages[i] as Map<String, dynamic>),
                  )),
                  _buildReplyBar(),
                ]),
    );
  }

  Widget _buildReplyBar() {
    return Container(
      padding: EdgeInsets.only(left: 16, right: 8, top: 8, bottom: MediaQuery.of(context).viewInsets.bottom + 8),
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        border: Border(top: BorderSide(color: Theme.of(context).dividerTheme.color ?? Colors.transparent)),
      ),
      child: SafeArea(top: false, child: Row(children: [
        Expanded(child: TextField(
          controller: _replyCtrl,
          maxLines: null,
          textInputAction: TextInputAction.newline,
          decoration: const InputDecoration(hintText: 'Antwort schreiben…', border: InputBorder.none, filled: false),
        )),
        const SizedBox(width: 8),
        _sending
            ? const Padding(padding: EdgeInsets.all(12), child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2.5)))
            : IconButton.filled(
                icon: const Icon(Icons.send_rounded),
                onPressed: _send,
              ),
      ])),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  final Map<String, dynamic> msg;
  const _MessageBubble({required this.msg});

  @override
  Widget build(BuildContext context) {
    final isOwner = msg['sender_type'] == 'owner';
    final cs = Theme.of(context).colorScheme;

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        mainAxisAlignment: isOwner ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isOwner) ...[
            CircleAvatar(radius: 16,
              backgroundColor: AppTheme.primary.withValues(alpha: 0.12),
              child: const Icon(Icons.support_agent_rounded, color: AppTheme.primary, size: 16)),
            const SizedBox(width: 8),
          ],
          Flexible(child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: isOwner ? AppTheme.primary : (Theme.of(context).brightness == Brightness.dark ? const Color(0xFF252B3D) : const Color(0xFFEFF2FF)),
              borderRadius: BorderRadius.only(
                topLeft: const Radius.circular(18),
                topRight: const Radius.circular(18),
                bottomLeft: Radius.circular(isOwner ? 18 : 4),
                bottomRight: Radius.circular(isOwner ? 4 : 18),
              ),
            ),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              if (!isOwner && msg['sender_name'] != null)
                Padding(padding: const EdgeInsets.only(bottom: 4), child: Text(
                  msg['sender_name'] as String,
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppTheme.primary.withValues(alpha: 0.8)),
                )),
              Text(msg['body'] as String? ?? '',
                style: TextStyle(color: isOwner ? Colors.white : cs.onSurface)),
              const SizedBox(height: 4),
              Text(msg['created_at'] as String? ?? '',
                style: TextStyle(fontSize: 10,
                  color: isOwner ? Colors.white.withValues(alpha: 0.7) : cs.onSurfaceVariant)),
            ]),
          )),
          if (isOwner) const SizedBox(width: 4),
        ],
      ),
    );
  }
}
