import 'package:flutter/material.dart';
import '../../services/api_service.dart';

class OwnerPortalThreadScreen extends StatefulWidget {
  final int threadId;
  const OwnerPortalThreadScreen({super.key, required this.threadId});

  @override
  State<OwnerPortalThreadScreen> createState() => _OwnerPortalThreadScreenState();
}

class _OwnerPortalThreadScreenState extends State<OwnerPortalThreadScreen> {
  final ApiService _api = ApiService();
  final _replyCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();

  Map<String, dynamic> _thread = {};
  List<Map<String, dynamic>> _messages = [];
  bool _loading = true;
  bool _sending = false;
  String? _error;

  static const _sky = Color(0xFF0EA5E9);

  @override
  void initState() { super.initState(); _loadThread(); }

  @override
  void dispose() { _replyCtrl.dispose(); _scrollCtrl.dispose(); super.dispose(); }

  Future<void> _loadThread() async {
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.ownerPortalThreadShow(widget.threadId);
      final msgs = data['messages'] as List? ?? data['nachrichten'] as List? ?? [];
      setState(() {
        _thread   = data;
        _messages = msgs.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _loading  = false;
      });
      _scrollToBottom();
    } catch (e) {
      setState(() { _loading = false; _error = e.toString(); });
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(_scrollCtrl.position.maxScrollExtent,
            duration: const Duration(milliseconds: 300), curve: Curves.easeOut);
      }
    });
  }

  Future<void> _send() async {
    final text = _replyCtrl.text.trim();
    if (text.isEmpty) return;
    setState(() => _sending = true);
    try {
      await _api.ownerPortalReply(widget.threadId, {'message': text});
      _replyCtrl.clear();
      await _loadThread();
    } catch (_) {}
    setState(() => _sending = false);
  }

  @override
  Widget build(BuildContext context) {
    final subject = _thread['subject'] as String? ?? _thread['betreff'] as String? ?? 'Nachricht';
    return Scaffold(
      appBar: AppBar(
        title: Text(subject, overflow: TextOverflow.ellipsis),
        backgroundColor: _sky, foregroundColor: Colors.white,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: _sky))
          : _error != null
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  const Icon(Icons.cloud_off_rounded, size: 48, color: Colors.grey),
                  const SizedBox(height: 12),
                  Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  FilledButton.icon(onPressed: _loadThread,
                    icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut'),
                    style: FilledButton.styleFrom(backgroundColor: _sky))]))
              : Column(children: [
                  Expanded(
                    child: ListView.builder(
                      controller: _scrollCtrl,
                      padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
                      itemCount: _messages.length,
                      itemBuilder: (_, i) => _MessageBubble(msg: _messages[i]),
                    ),
                  ),
                  _buildReplyBar(),
                ]),
    );
  }

  Widget _buildReplyBar() {
    return Container(
      padding: EdgeInsets.fromLTRB(12, 8, 12, MediaQuery.of(context).viewInsets.bottom + 12),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.brightness == Brightness.dark
            ? const Color(0xFF0D1B2A) : Colors.white,
        border: Border(top: BorderSide(
            color: Theme.of(context).colorScheme.brightness == Brightness.dark
                ? Colors.white12 : Colors.black.withValues(alpha: 0.07))),
      ),
      child: Row(children: [
        Expanded(
          child: TextField(
            controller: _replyCtrl,
            maxLines: null, textInputAction: TextInputAction.send,
            onSubmitted: (_) => _send(),
            decoration: InputDecoration(
              hintText: 'Antwort schreiben...',
              filled: true,
              fillColor: Theme.of(context).colorScheme.brightness == Brightness.dark
                  ? Colors.white.withValues(alpha: 0.06) : Colors.grey.shade100,
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(22), borderSide: BorderSide.none),
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            ),
          ),
        ),
        const SizedBox(width: 8),
        AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          child: _sending
              ? Container(width: 44, height: 44,
                  decoration: const BoxDecoration(color: _sky, shape: BoxShape.circle),
                  child: const Padding(padding: EdgeInsets.all(10),
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)))
              : GestureDetector(
                  onTap: _send,
                  child: Container(width: 44, height: 44,
                    decoration: const BoxDecoration(color: _sky, shape: BoxShape.circle),
                    child: const Icon(Icons.send_rounded, color: Colors.white, size: 20)),
                ),
        ),
      ]),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  final Map<String, dynamic> msg;
  const _MessageBubble({required this.msg});

  @override
  Widget build(BuildContext context) {
    final isDark    = Theme.of(context).colorScheme.brightness == Brightness.dark;
    final isOwner   = (msg['is_owner'] as bool?) ?? ((msg['sender_type'] as String?) == 'owner');
    final body      = msg['body'] as String? ?? msg['message'] as String? ?? msg['nachricht'] as String? ?? '';
    final sender    = msg['sender_name'] as String? ?? (isOwner ? 'Sie' : 'Praxis');
    final time      = msg['created_at'] as String? ?? msg['datum'] as String? ?? '';
    const _sky      = Color(0xFF0EA5E9);

    return Align(
      alignment: isOwner ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.78),
        margin: const EdgeInsets.only(bottom: 10),
        child: Column(
          crossAxisAlignment: isOwner ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            Text(sender, style: TextStyle(
              fontSize: 11, fontWeight: FontWeight.w600,
              color: isOwner ? _sky.withValues(alpha: 0.8) : Colors.grey.shade500)),
            const SizedBox(height: 3),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                gradient: isOwner ? const LinearGradient(
                    colors: [Color(0xFF0284C7), _sky],
                    begin: Alignment.topLeft, end: Alignment.bottomRight) : null,
                color: isOwner ? null : (isDark ? const Color(0xFF1E293B) : Colors.grey.shade100),
                borderRadius: BorderRadius.only(
                  topLeft:     const Radius.circular(18),
                  topRight:    const Radius.circular(18),
                  bottomLeft:  Radius.circular(isOwner ? 18 : 4),
                  bottomRight: Radius.circular(isOwner ? 4 : 18),
                ),
              ),
              child: Text(body, style: TextStyle(
                fontSize: 14, height: 1.4,
                color: isOwner ? Colors.white
                    : (isDark ? Colors.white.withValues(alpha: 0.9) : Colors.black87))),
            ),
            if (time.isNotEmpty) Padding(
              padding: const EdgeInsets.only(top: 3),
              child: Text(time.length > 10 ? time.substring(0, 16) : time,
                  style: TextStyle(fontSize: 10, color: Colors.grey.shade400)),
            ),
          ],
        ),
      ),
    );
  }
}
