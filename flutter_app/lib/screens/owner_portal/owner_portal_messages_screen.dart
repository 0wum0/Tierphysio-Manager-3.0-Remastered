import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:shimmer/shimmer.dart';
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
  String? _error;

  static const _sky = Color(0xFF0EA5E9);

  @override
  void initState() { super.initState(); _loadData(); }

  Future<void> _loadData() async {
    setState(() { _loading = true; _error = null; });
    try {
      final results = await Future.wait([
        _api.ownerPortalThreadList().then((l) => l.map((e) => Map<String, dynamic>.from(e as Map)).toList()),
        _api.ownerPortalUnread(),
      ]);
      setState(() {
        _threads = results[0] as List<Map<String, dynamic>>;
        _unread  = results[1] as int;
        _loading = false;
      });
    } catch (e) { setState(() { _loading = false; _error = e.toString(); }); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Nachrichten'),
        backgroundColor: _sky, foregroundColor: Colors.white,
        actions: [
          if (_unread > 0)
            Padding(
              padding: const EdgeInsets.only(right: 16),
              child: Badge(label: Text('$_unread'),
                child: const Icon(Icons.mail_rounded, color: Colors.white)),
            ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _newThread,
        backgroundColor: _sky,
        icon: const Icon(Icons.edit_rounded, color: Colors.white),
        label: const Text('Neue Nachricht', style: TextStyle(color: Colors.white)),
      ),
      body: _loading
          ? Shimmer.fromColors(baseColor: Colors.grey.shade300, highlightColor: Colors.grey.shade100,
              child: ListView.builder(padding: const EdgeInsets.all(16), itemCount: 5,
                itemBuilder: (_, __) => Container(height: 78, margin: const EdgeInsets.only(bottom: 10),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)))))
          : _error != null
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  const Icon(Icons.cloud_off_rounded, size: 48, color: Colors.grey),
                  const SizedBox(height: 12), Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  FilledButton.icon(onPressed: _loadData, icon: const Icon(Icons.refresh_rounded),
                    label: const Text('Erneut'), style: FilledButton.styleFrom(backgroundColor: _sky))]))
              : RefreshIndicator(onRefresh: _loadData, color: _sky,
                  child: _threads.isEmpty
                    ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        const Text('💬', style: TextStyle(fontSize: 52)),
                        const SizedBox(height: 16),
                        const Text('Noch keine Nachrichten',
                            style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                        const SizedBox(height: 8),
                        Text('Starten Sie eine Konversation mit der Praxis.',
                            style: TextStyle(fontSize: 13, color: Colors.grey.shade500))]))
                    : ListView.builder(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
                        itemCount: _threads.length,
                        itemBuilder: (ctx, i) {
                          final t = _threads[i];
                          final subject = t['subject'] as String? ?? t['betreff'] as String? ?? 'Nachricht';
                          final preview = t['last_message'] as String? ?? t['preview'] as String? ?? '';
                          final time    = t['updated_at'] as String? ?? t['datum'] as String? ?? '';
                          final unread  = (t['unread_count'] as int? ?? 0) > 0;
                          final id      = t['id'] as int? ?? 0;
                          final isDark  = Theme.of(ctx).colorScheme.brightness == Brightness.dark;

                          return TweenAnimationBuilder<double>(
                            tween: Tween(begin: 0, end: 1),
                            duration: Duration(milliseconds: 280 + i * 45),
                            curve: Curves.easeOutCubic,
                            builder: (_, v, child) => Opacity(opacity: v,
                              child: Transform.translate(offset: Offset(0, 14*(1-v)), child: child)),
                            child: Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: Material(
                                color: isDark ? const Color(0xFF0D1B2A) : Colors.white,
                                borderRadius: BorderRadius.circular(16),
                                clipBehavior: Clip.antiAlias,
                                child: InkWell(
                                  onTap: () => context.go('/owner-portal/messages/$id'),
                                  splashColor: _sky.withValues(alpha: 0.08),
                                  child: Container(
                                    padding: const EdgeInsets.all(14),
                                    decoration: BoxDecoration(
                                      borderRadius: BorderRadius.circular(16),
                                      border: Border.all(color: unread
                                          ? _sky.withValues(alpha: 0.35)
                                          : (isDark ? Colors.white.withValues(alpha: 0.06) : Colors.black.withValues(alpha: 0.06)))),
                                    child: Row(children: [
                                      Container(width: 44, height: 44,
                                        decoration: BoxDecoration(
                                          color: unread ? _sky.withValues(alpha: 0.15) : Colors.grey.withValues(alpha: 0.1),
                                          shape: BoxShape.circle),
                                        child: Icon(Icons.chat_bubble_rounded,
                                          color: unread ? _sky : Colors.grey, size: 20)),
                                      const SizedBox(width: 12),
                                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                        Text(subject, style: TextStyle(fontSize: 14,
                                          fontWeight: unread ? FontWeight.w700 : FontWeight.w600,
                                          color: unread ? _sky : null),
                                          maxLines: 1, overflow: TextOverflow.ellipsis),
                                        if (preview.isNotEmpty) Text(preview,
                                          style: TextStyle(fontSize: 12, color: Colors.grey.shade500),
                                          maxLines: 1, overflow: TextOverflow.ellipsis),
                                      ])),
                                      const SizedBox(width: 8),
                                      Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                                        Text(time.length > 10 ? time.substring(0, 10) : time,
                                          style: TextStyle(fontSize: 10, color: Colors.grey.shade400)),
                                        if (unread) Container(width: 8, height: 8, margin: const EdgeInsets.only(top: 4),
                                          decoration: const BoxDecoration(color: _sky, shape: BoxShape.circle)),
                                      ]),
                                    ]),
                                  ),
                                ),
                              ),
                            ),
                          );
                        }),
              ),
    );
  }

  Future<void> _newThread() async {
    final subjectCtrl = TextEditingController();
    final msgCtrl     = TextEditingController();
    if (!mounted) return;
    showModalBottomSheet(
      context: context, isScrollControlled: true, backgroundColor: Colors.transparent,
      builder: (_) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
        child: Container(
          decoration: const BoxDecoration(
            color: Color(0xFF0D1B2A),
            borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
          padding: const EdgeInsets.fromLTRB(24, 12, 24, 24),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Center(child: Container(width: 40, height: 4,
              decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 16),
            const Text('Neue Nachricht', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: Colors.white)),
            const SizedBox(height: 16),
            TextField(controller: subjectCtrl,
              style: const TextStyle(color: Colors.white),
              decoration: InputDecoration(hintText: 'Betreff',
                hintStyle: TextStyle(color: Colors.white.withValues(alpha: 0.4)),
                filled: true, fillColor: Colors.white.withValues(alpha: 0.06),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none))),
            const SizedBox(height: 12),
            TextField(controller: msgCtrl, maxLines: 4,
              style: const TextStyle(color: Colors.white),
              decoration: InputDecoration(hintText: 'Ihre Nachricht...',
                hintStyle: TextStyle(color: Colors.white.withValues(alpha: 0.4)),
                filled: true, fillColor: Colors.white.withValues(alpha: 0.06),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none))),
            const SizedBox(height: 16),
            SizedBox(width: double.infinity, height: 48,
              child: FilledButton.icon(
                onPressed: () async {
                  Navigator.pop(context);
                  try {
                    await _api.ownerPortalNewThread({'subject': subjectCtrl.text, 'message': msgCtrl.text});
                    _loadData();
                  } catch (_) {}
                },
                style: FilledButton.styleFrom(backgroundColor: _sky, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                icon: const Icon(Icons.send_rounded), label: const Text('Senden'))),
          ]),
        ),
      ),
    );
  }
}
