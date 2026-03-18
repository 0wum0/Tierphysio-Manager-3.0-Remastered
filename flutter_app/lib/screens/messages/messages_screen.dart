import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';
import '../../widgets/search_bar_widget.dart';

class MessagesScreen extends StatefulWidget {
  const MessagesScreen({super.key});

  @override
  State<MessagesScreen> createState() => _MessagesScreenState();
}

class _MessagesScreenState extends State<MessagesScreen> {
  final _api = ApiService();
  List<dynamic> _threads = [];
  List<dynamic> _filtered = [];
  bool _loading = true;
  String? _error;
  String _search = '';

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.messageThreads();
      setState(() {
        _threads  = data;
        _filtered = _applySearch(data, _search);
        _loading  = false;
      });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  List<dynamic> _applySearch(List<dynamic> list, String q) {
    if (q.isEmpty) return list;
    final lq = q.toLowerCase();
    return list.where((t) =>
      (t['subject']    as String? ?? '').toLowerCase().contains(lq) ||
      (t['owner_name'] as String? ?? '').toLowerCase().contains(lq) ||
      (t['last_body']  as String? ?? '').toLowerCase().contains(lq),
    ).toList();
  }

  void _onSearch(String q) {
    setState(() {
      _search   = q;
      _filtered = _applySearch(_threads, q);
    });
  }

  String _timeAgo(String? raw) {
    if (raw == null || raw.isEmpty) return '';
    try {
      final dt   = DateTime.parse(raw).toLocal();
      final diff = DateTime.now().difference(dt);
      if (diff.inMinutes < 1)  return 'gerade eben';
      if (diff.inHours < 1)    return 'vor ${diff.inMinutes} Min.';
      if (diff.inHours < 24)   return 'vor ${diff.inHours} Std.';
      if (diff.inDays < 7)     return 'vor ${diff.inDays} Tagen';
      return DateFormat('dd.MM.yy').format(dt);
    } catch (_) { return ''; }
  }

  Future<void> _showNewThreadSheet() async {
    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _NewThreadSheet(api: _api),
    );
    if (result == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    final totalUnread = _threads.fold<int>(
        0, (s, t) => s + ((t['unread_count'] as int?) ?? 0));

    return Scaffold(
      appBar: AppBar(
        title: Row(children: [
          const Text('Nachrichten'),
          if (totalUnread > 0) ...[
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
              decoration: BoxDecoration(
                  color: AppTheme.danger,
                  borderRadius: BorderRadius.circular(20)),
              child: Text('$totalUnread',
                  style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w700)),
            ),
          ],
        ]),
        actions: [
          IconButton(
              icon: const Icon(Icons.refresh_rounded), onPressed: _load),
          IconButton(
              icon: const Icon(Icons.edit_rounded),
              tooltip: 'Neue Nachricht',
              onPressed: _showNewThreadSheet),
        ],
      ),
      body: Column(children: [
        AppSearchBar(onSearch: _onSearch, hint: 'Konversation suchen…'),
        Expanded(child: _buildBody()),
      ]),
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return Center(child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const Icon(Icons.error_outline_rounded, size: 48, color: Colors.grey),
        const SizedBox(height: 8),
        Text(_error!, textAlign: TextAlign.center),
        const SizedBox(height: 12),
        FilledButton(onPressed: _load, child: const Text('Erneut')),
      ],
    ));
    if (_filtered.isEmpty) return Center(child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.mark_chat_unread_rounded,
            size: 56, color: AppTheme.primary.withValues(alpha: 0.3)),
        const SizedBox(height: 12),
        Text(_search.isEmpty
            ? 'Noch keine Nachrichten'
            : 'Keine Ergebnisse für „$_search"',
            style: const TextStyle(color: Colors.grey)),
        if (_search.isEmpty) ...[
          const SizedBox(height: 16),
          FilledButton.icon(
            icon: const Icon(Icons.edit_rounded, size: 16),
            label: const Text('Erste Nachricht schreiben'),
            onPressed: _showNewThreadSheet,
          ),
        ],
      ],
    ));

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        itemCount: _filtered.length,
        separatorBuilder: (_, __) => const Divider(height: 1, indent: 72),
        itemBuilder: (ctx, i) {
          final t       = _filtered[i] as Map<String, dynamic>;
          final unread  = (t['unread_count'] as int?) ?? 0;
          final isClosed= t['status'] == 'closed';
          final ownerName = t['owner_name'] as String? ?? '?';
          final initial   = ownerName.isNotEmpty ? ownerName[0].toUpperCase() : '?';
          final colors    = [AppTheme.primary, AppTheme.secondary, AppTheme.tertiary,
            AppTheme.success, AppTheme.warning];
          final avatarColor = colors[(ownerName.codeUnitAt(0)) % colors.length];

          return ListTile(
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
            leading: Stack(clipBehavior: Clip.none, children: [
              CircleAvatar(
                radius: 24,
                backgroundColor: avatarColor.withValues(alpha: 0.15),
                child: Text(initial,
                    style: TextStyle(
                        color: avatarColor,
                        fontWeight: FontWeight.w700,
                        fontSize: 16)),
              ),
              if (unread > 0)
                Positioned(
                  right: -2, top: -2,
                  child: Container(
                    width: 18, height: 18,
                    decoration: BoxDecoration(
                        color: AppTheme.danger,
                        shape: BoxShape.circle,
                        border: Border.all(color: Colors.white, width: 1.5)),
                    child: Center(
                      child: Text('$unread',
                          style: const TextStyle(
                              color: Colors.white,
                              fontSize: 9,
                              fontWeight: FontWeight.w800)),
                    ),
                  ),
                ),
            ]),
            title: Row(children: [
              Expanded(
                child: Text(
                  t['subject'] as String? ?? '',
                  style: TextStyle(
                      fontWeight: unread > 0
                          ? FontWeight.w700
                          : FontWeight.w500,
                      fontSize: 14),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(width: 8),
              if (isClosed)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                      color: Colors.grey.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(6)),
                  child: const Text('Geschlossen',
                      style: TextStyle(
                          fontSize: 9,
                          color: Colors.grey,
                          fontWeight: FontWeight.w600)),
                ),
            ]),
            subtitle: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 2),
                Row(children: [
                  Icon(Icons.person_outline_rounded,
                      size: 12, color: Colors.grey.shade500),
                  const SizedBox(width: 3),
                  Text(ownerName,
                      style: TextStyle(
                          fontSize: 12, color: Colors.grey.shade600)),
                  const SizedBox(width: 8),
                  Text(_timeAgo(t['last_message_at'] as String?),
                      style: TextStyle(
                          fontSize: 11, color: Colors.grey.shade400)),
                ]),
                if ((t['last_body'] as String? ?? '').isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    t['last_body'] as String,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        fontSize: 12,
                        color: unread > 0
                            ? Theme.of(context).colorScheme.onSurface
                            : Colors.grey.shade500,
                        fontWeight: unread > 0
                            ? FontWeight.w600
                            : FontWeight.normal),
                  ),
                ],
              ],
            ),
            onTap: () => context
                .push('/nachrichten/${t['id']}', extra: t)
                .then((_) => _load()),
          );
        },
      ),
    );
  }
}

// ── New Thread Bottom Sheet ───────────────────────────────────────────────────

class _NewThreadSheet extends StatefulWidget {
  final ApiService api;
  const _NewThreadSheet({required this.api});

  @override
  State<_NewThreadSheet> createState() => _NewThreadSheetState();
}

class _NewThreadSheetState extends State<_NewThreadSheet> {
  final _formKey = GlobalKey<FormState>();
  final _subjectCtrl = TextEditingController();
  final _bodyCtrl    = TextEditingController();
  final _ownerCtrl   = TextEditingController();

  List<dynamic> _owners = [];
  Map<String, dynamic>? _selectedOwner;
  bool _saving = false;
  bool _loadingOwners = true;

  @override
  void initState() {
    super.initState();
    _loadOwners();
  }

  Future<void> _loadOwners() async {
    try {
      final data = await widget.api.owners(perPage: 200);
      setState(() {
        _owners = List<dynamic>.from(data['items'] as List? ?? []);
        _loadingOwners = false;
      });
    } catch (_) {
      setState(() => _loadingOwners = false);
    }
  }

  void _pickOwner() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        final filtered = ValueNotifier<List<dynamic>>(_owners);
        return DraggableScrollableSheet(
          expand: false,
          initialChildSize: 0.6,
          builder: (_, sc) => Column(children: [
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: TextField(
                autofocus: true,
                decoration: const InputDecoration(
                    hintText: 'Tierhalter suchen…',
                    prefixIcon: Icon(Icons.search_rounded)),
                onChanged: (q) {
                  final lq = q.toLowerCase();
                  filtered.value = _owners.where((o) =>
                    '${o['first_name']} ${o['last_name']}'.toLowerCase().contains(lq) ||
                    (o['email'] as String? ?? '').toLowerCase().contains(lq),
                  ).toList();
                },
              ),
            ),
            Expanded(child: ValueListenableBuilder<List<dynamic>>(
              valueListenable: filtered,
              builder: (_, list, __) => ListView.builder(
                controller: sc,
                itemCount: list.length,
                itemBuilder: (_, i) {
                  final o = list[i] as Map<String, dynamic>;
                  return ListTile(
                    leading: CircleAvatar(
                      backgroundColor: AppTheme.primary.withValues(alpha: 0.1),
                      child: Text(
                        (o['last_name'] as String? ?? '?')[0].toUpperCase(),
                        style: const TextStyle(
                            color: AppTheme.primary,
                            fontWeight: FontWeight.w700)),
                    ),
                    title: Text('${o['first_name']} ${o['last_name']}'),
                    subtitle: Text(o['email'] as String? ?? '',
                        style: const TextStyle(fontSize: 12)),
                    onTap: () {
                      Navigator.pop(ctx);
                      setState(() {
                        _selectedOwner = o;
                        _ownerCtrl.text =
                            '${o['first_name']} ${o['last_name']}';
                      });
                    },
                  );
                },
              ),
            )),
          ]),
        );
      },
    );
  }

  Future<void> _send() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedOwner == null) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Bitte einen Tierhalter auswählen.')));
      return;
    }
    setState(() => _saving = true);
    try {
      await widget.api.messageCreate(
        ownerId: (int.tryParse(_selectedOwner!['id']?.toString() ?? '') ?? 0),
        subject: _subjectCtrl.text.trim(),
        body: _bodyCtrl.text.trim(),
      );
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => _saving = false);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
          left: 16, right: 16, top: 8),
      child: Form(
        key: _formKey,
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 40, height: 4,
              decoration: BoxDecoration(
                  color: Colors.grey.withValues(alpha: 0.3),
                  borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 16),
          Text('Neue Nachricht', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          // Owner picker
          GestureDetector(
            onTap: _loadingOwners ? null : _pickOwner,
            child: AbsorbPointer(
              child: TextFormField(
                controller: _ownerCtrl,
                decoration: const InputDecoration(
                  labelText: 'Tierhalter',
                  prefixIcon: Icon(Icons.person_rounded),
                  suffixIcon: Icon(Icons.arrow_drop_down_rounded),
                ),
                validator: (v) => (v == null || v.isEmpty) ? 'Pflichtfeld' : null,
              ),
            ),
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _subjectCtrl,
            decoration: const InputDecoration(
                labelText: 'Betreff',
                prefixIcon: Icon(Icons.subject_rounded)),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Pflichtfeld' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _bodyCtrl,
            maxLines: 4,
            decoration: const InputDecoration(
                labelText: 'Nachricht',
                alignLabelWithHint: true,
                prefixIcon: Padding(
                    padding: EdgeInsets.only(bottom: 56),
                    child: Icon(Icons.message_rounded))),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Pflichtfeld' : null,
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            icon: _saving
                ? const SizedBox(width: 16, height: 16,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Icon(Icons.send_rounded, size: 18),
            label: Text(_saving ? 'Senden…' : 'Senden'),
            onPressed: _saving ? null : _send,
          ),
          const SizedBox(height: 16),
        ]),
      ),
    );
  }
}
