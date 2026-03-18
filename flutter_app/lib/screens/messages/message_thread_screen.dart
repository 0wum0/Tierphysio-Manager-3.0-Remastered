import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class MessageThreadScreen extends StatefulWidget {
  final int threadId;
  final Map<String, dynamic>? prefill;
  const MessageThreadScreen({super.key, required this.threadId, this.prefill});

  @override
  State<MessageThreadScreen> createState() => _MessageThreadScreenState();
}

class _MessageThreadScreenState extends State<MessageThreadScreen> {
  final _api       = ApiService();
  final _replyCtrl = TextEditingController();
  final _scrollCtrl= ScrollController();
  final _focusNode = FocusNode();

  Map<String, dynamic>? _thread;
  List<dynamic> _messages = [];
  bool _loading  = true;
  bool _sending  = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    // Use prefill data immediately so header shows while loading
    if (widget.prefill != null) {
      _thread = widget.prefill;
    }
    _load();
  }

  @override
  void dispose() {
    _replyCtrl.dispose();
    _scrollCtrl.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.messageThread(widget.threadId);
      final msgs = List<dynamic>.from(data['messages'] as List? ?? []);
      setState(() {
        _thread   = data;
        _messages = msgs;
        _loading  = false;
      });
      _scrollToBottom();
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(
          _scrollCtrl.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _send() async {
    final body = _replyCtrl.text.trim();
    if (body.isEmpty) return;
    setState(() => _sending = true);
    try {
      final msg = await _api.messageReply(widget.threadId, body);
      _replyCtrl.clear();
      setState(() {
        _messages.add(msg);
        _sending = false;
      });
      _scrollToBottom();
    } catch (e) {
      setState(() => _sending = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(e.toString())));
      }
    }
  }

  Future<void> _toggleStatus() async {
    final current = _thread?['status'] as String? ?? 'open';
    final next    = current == 'open' ? 'closed' : 'open';
    try {
      await _api.messageSetStatus(widget.threadId, next);
      setState(() => _thread?['status'] = next);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(next == 'closed'
              ? 'Konversation geschlossen'
              : 'Konversation wieder geöffnet'),
        ));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _delete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Konversation löschen?'),
        content: const Text('Diese Aktion kann nicht rückgängig gemacht werden.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Abbrechen')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppTheme.danger),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Löschen'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await _api.messageDelete(widget.threadId);
      if (mounted) context.pop();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  String _formatTime(String? raw) {
    if (raw == null || raw.isEmpty) return '';
    try {
      final dt = DateTime.parse(raw).toLocal();
      final now = DateTime.now();
      if (dt.year == now.year && dt.month == now.month && dt.day == now.day) {
        return DateFormat('HH:mm').format(dt);
      }
      return DateFormat('dd.MM. HH:mm').format(dt);
    } catch (_) { return ''; }
  }

  @override
  Widget build(BuildContext context) {
    final subject   = _thread?['subject'] as String? ?? 'Nachricht';
    final ownerName = _thread?['owner_name'] as String? ?? '';
    final isClosed  = (_thread?['status'] as String?) == 'closed';
    final cs        = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(subject,
              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
              overflow: TextOverflow.ellipsis),
          if (ownerName.isNotEmpty)
            Text(ownerName,
                style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant)),
        ]),
        actions: [
          IconButton(
            icon: Icon(isClosed
                ? Icons.lock_open_rounded
                : Icons.lock_outline_rounded),
            tooltip: isClosed ? 'Öffnen' : 'Schließen',
            onPressed: _toggleStatus,
          ),
          PopupMenuButton<String>(
            onSelected: (v) { if (v == 'delete') _delete(); },
            itemBuilder: (_) => [
              const PopupMenuItem(
                value: 'delete',
                child: Row(children: [
                  Icon(Icons.delete_rounded, color: Colors.red, size: 18),
                  SizedBox(width: 8),
                  Text('Konversation löschen',
                      style: TextStyle(color: Colors.red)),
                ]),
              ),
            ],
          ),
        ],
      ),
      body: _loading && _messages.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _messages.isEmpty
              ? Center(child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(_error!),
                    const SizedBox(height: 12),
                    FilledButton(onPressed: _load, child: const Text('Erneut')),
                  ],
                ))
              : Column(children: [
                  // Closed banner
                  if (isClosed)
                    Container(
                      width: double.infinity,
                      color: Colors.grey.withValues(alpha: 0.08),
                      padding: const EdgeInsets.symmetric(
                          horizontal: 16, vertical: 8),
                      child: Row(children: [
                        const Icon(Icons.lock_outline_rounded,
                            size: 14, color: Colors.grey),
                        const SizedBox(width: 6),
                        const Text('Diese Konversation ist geschlossen.',
                            style: TextStyle(
                                color: Colors.grey, fontSize: 12)),
                        const Spacer(),
                        TextButton(
                          onPressed: _toggleStatus,
                          style: TextButton.styleFrom(
                              padding: EdgeInsets.zero,
                              minimumSize: Size.zero,
                              tapTargetSize: MaterialTapTargetSize.shrinkWrap),
                          child: const Text('Öffnen',
                              style: TextStyle(fontSize: 12)),
                        ),
                      ]),
                    ),

                  // Messages
                  Expanded(
                    child: RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        controller: _scrollCtrl,
                        padding: const EdgeInsets.symmetric(
                            horizontal: 12, vertical: 12),
                        physics: const AlwaysScrollableScrollPhysics(),
                        itemCount: _messages.length,
                        itemBuilder: (ctx, i) =>
                            _buildBubble(_messages[i] as Map<String, dynamic>),
                      ),
                    ),
                  ),

                  // Reply input
                  _buildReplyBar(isClosed),
                ]),
    );
  }

  Widget _buildBubble(Map<String, dynamic> msg) {
    final isAdmin    = msg['sender_type'] == 'admin';
    final senderName = msg['sender_name'] as String? ?? '';
    final body       = msg['body'] as String? ?? '';
    final time       = _formatTime(msg['created_at'] as String?);
    final cs         = Theme.of(context).colorScheme;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment:
            isAdmin ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isAdmin) ...[
            CircleAvatar(
              radius: 16,
              backgroundColor: AppTheme.secondary.withValues(alpha: 0.15),
              child: Text(
                senderName.isNotEmpty ? senderName[0].toUpperCase() : '?',
                style: const TextStyle(
                    color: AppTheme.secondary,
                    fontSize: 12,
                    fontWeight: FontWeight.w700),
              ),
            ),
            const SizedBox(width: 8),
          ],
          Flexible(
            child: Container(
              constraints: BoxConstraints(
                  maxWidth: MediaQuery.of(context).size.width * 0.72),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: isAdmin
                    ? AppTheme.primary
                    : (Theme.of(context).brightness == Brightness.dark
                        ? const Color(0xFF2A2D3A)
                        : Colors.white),
                borderRadius: BorderRadius.only(
                  topLeft:     const Radius.circular(18),
                  topRight:    const Radius.circular(18),
                  bottomLeft:  Radius.circular(isAdmin ? 18 : 4),
                  bottomRight: Radius.circular(isAdmin ? 4 : 18),
                ),
                boxShadow: [
                  BoxShadow(
                      color: Colors.black.withValues(alpha: 0.06),
                      blurRadius: 8,
                      offset: const Offset(0, 2)),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (!isAdmin)
                    Text(senderName,
                        style: TextStyle(
                            color: AppTheme.secondary,
                            fontSize: 11,
                            fontWeight: FontWeight.w700)),
                  if (!isAdmin) const SizedBox(height: 2),
                  Text(body,
                      style: TextStyle(
                          color: isAdmin
                              ? Colors.white
                              : cs.onSurface,
                          fontSize: 14,
                          height: 1.4)),
                  const SizedBox(height: 4),
                  Text(time,
                      style: TextStyle(
                          color: isAdmin
                              ? Colors.white.withValues(alpha: 0.6)
                              : Colors.grey.shade400,
                          fontSize: 10)),
                ],
              ),
            ),
          ),
          if (isAdmin) const SizedBox(width: 8),
        ],
      ),
    );
  }

  Widget _buildReplyBar(bool isClosed) {
    return Container(
      padding: EdgeInsets.only(
        left: 12, right: 8, top: 8,
        bottom: MediaQuery.of(context).viewInsets.bottom + 8,
      ),
      decoration: BoxDecoration(
        color: Theme.of(context).brightness == Brightness.dark
            ? const Color(0xFF1A1D27)
            : Colors.white,
        border: Border(
            top: BorderSide(color: Theme.of(context).dividerColor)),
      ),
      child: Row(children: [
        Expanded(
          child: TextField(
            controller: _replyCtrl,
            focusNode: _focusNode,
            enabled: !isClosed,
            maxLines: null,
            textInputAction: TextInputAction.newline,
            decoration: InputDecoration(
              hintText: isClosed
                  ? 'Konversation ist geschlossen'
                  : 'Antwort schreiben…',
              border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(24),
                  borderSide: BorderSide.none),
              filled: true,
              fillColor: Theme.of(context).brightness == Brightness.dark
                  ? Colors.white.withValues(alpha: 0.06)
                  : Colors.grey.shade100,
              contentPadding: const EdgeInsets.symmetric(
                  horizontal: 16, vertical: 10),
            ),
          ),
        ),
        const SizedBox(width: 8),
        AnimatedBuilder(
          animation: _replyCtrl,
          builder: (_, __) {
            final hasText = _replyCtrl.text.trim().isNotEmpty;
            return IconButton.filled(
              onPressed: (hasText && !_sending && !isClosed) ? _send : null,
              icon: _sending
                  ? const SizedBox(
                      width: 18, height: 18,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.send_rounded, size: 20),
              style: IconButton.styleFrom(
                backgroundColor: (hasText && !isClosed)
                    ? AppTheme.primary
                    : Colors.grey.shade300,
                foregroundColor: Colors.white,
              ),
            );
          },
        ),
      ]),
    );
  }
}
