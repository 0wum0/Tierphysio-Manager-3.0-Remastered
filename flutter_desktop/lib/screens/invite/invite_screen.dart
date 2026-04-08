import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class InviteScreen extends StatefulWidget {
  const InviteScreen({super.key});

  @override
  State<InviteScreen> createState() => _InviteScreenState();
}

class _InviteScreenState extends State<InviteScreen> {
  final _api = ApiService();
  List<dynamic> _items = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.inviteList();
      setState(() { _items = (data['items'] as List? ?? []); _loading = false; });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  Future<void> _sendInvite() async {
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => const _InviteDialog(),
    );
    if (result == null || !mounted) return;
    try {
      final resp = await _api.inviteSend(result);
      if (!mounted) return;
      final link     = resp['invite_url'] as String? ?? resp['invite_link'] as String? ?? '';
      final waUrl    = resp['whatsapp_url'] as String? ?? '';
      final phone    = result['phone'] as String? ?? '';
      if (link.isNotEmpty) {
        await showDialog(
          context: context,
          builder: (_) => _InviteLinkDialog(link: link, phone: phone, whatsappUrl: waUrl),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: const Text('✓ Einladung erstellt'),
          backgroundColor: Colors.green.shade700,
          behavior: SnackBarBehavior.floating,
        ));
      }
      _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _whatsapp(int id, String phone) async {
    try {
      final resp = await _api.inviteWhatsapp(id);
      final link = resp['whatsapp_url'] as String? ?? resp['url'] as String? ?? '';
      if (link.isNotEmpty) {
        final uri = Uri.tryParse(link);
        if (uri != null && await canLaunchUrl(uri)) {
          await launchUrl(uri, mode: LaunchMode.externalApplication);
        }
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _editInvite(Map<String, dynamic> item) async {
    final id = int.tryParse(item['id'].toString());
    if (id == null) return;
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _EditInviteDialog(
        phone: item['phone'] as String? ?? '',
        note:  item['note']  as String? ?? '',
      ),
    );
    if (result == null || !mounted) return;
    try {
      await _api.inviteUpdate(id, result);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Einladung aktualisiert'),
        behavior: SnackBarBehavior.floating,
      ));
      _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _revoke(Map<String, dynamic> item) async {
    final id = int.tryParse(item['id'].toString());
    if (id == null) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Einladung widerrufen'),
        content: const Text('Diese Einladung wirklich widerrufen?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Widerrufen'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    try {
      await _api.inviteRevoke(id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: const Text('Einladung widerrufen'),
        behavior: SnackBarBehavior.floating,
      ));
      _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Einladungen'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _sendInvite,
        icon: const Icon(Icons.person_add_rounded),
        label: const Text('Einladen'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(Icons.error_outline_rounded, size: 48, color: cs.error),
                  const SizedBox(height: 8),
                  Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  FilledButton(onPressed: _load, child: const Text('Erneut versuchen')),
                ]))
              : _items.isEmpty
                  ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.mail_outline_rounded, size: 64, color: cs.outlineVariant),
                      const SizedBox(height: 12),
                      Text('Keine Einladungen', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 16)),
                      const SizedBox(height: 8),
                      Text('Tippe auf „Einladen" um einen Besitzer einzuladen',
                        style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13),
                        textAlign: TextAlign.center),
                    ]))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.fromLTRB(12, 12, 12, 90),
                        itemCount: _items.length,
                        itemBuilder: (_, i) => _InviteCard(
                          item: Map<String, dynamic>.from(_items[i] as Map),
                          onWhatsApp: () => _whatsapp(
                            int.tryParse(_items[i]['id'].toString()) ?? 0,
                            _items[i]['phone'] as String? ?? _items[i]['owner_phone'] as String? ?? '',
                          ),
                          onEdit:    () => _editInvite(Map<String, dynamic>.from(_items[i] as Map)),
                          onRevoke:  () => _revoke(Map<String, dynamic>.from(_items[i] as Map)),
                        ),
                      ),
                    ),
    );
  }
}

// ── Invite Card ──────────────────────────────────────────────────────────────

class _InviteCard extends StatelessWidget {
  final Map<String, dynamic> item;
  final VoidCallback onWhatsApp;
  final VoidCallback onEdit;
  final VoidCallback onRevoke;

  const _InviteCard({required this.item, required this.onWhatsApp, required this.onEdit, required this.onRevoke});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final email = item['email'] as String? ?? '';
    final phone = item['phone'] as String? ?? '';
    final note  = item['note']  as String? ?? '';
    final status = item['status'] as String? ?? 'pending';
    final expiresAt = item['expires_at'] as String? ?? '';
    String expiresStr = '';
    bool isExpired = false;
    if (expiresAt.isNotEmpty) {
      try {
        final dt = DateTime.parse(expiresAt);
        expiresStr = DateFormat('dd.MM.yyyy', 'de_DE').format(dt);
        isExpired = dt.isBefore(DateTime.now());
        if (isExpired) expiresStr = 'Abgelaufen ($expiresStr)';
      } catch (_) {}
    }
    final isPending = (status == 'offen' || status == 'pending') && !isExpired;
    final isRevoked = status == 'abgelaufen' || status == 'revoked';
    final isUsed    = status == 'angenommen' || status == 'used';

    Color statusColor = isPending ? AppTheme.warning
        : isUsed ? Colors.green.shade700
        : cs.onSurfaceVariant;
    String statusLabel = isPending ? 'Ausstehend'
        : isUsed ? 'Angenommen'
        : isRevoked ? 'Widerrufen'
        : isExpired ? 'Abgelaufen'
        : status;

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(
              width: 44, height: 44,
              decoration: BoxDecoration(
                color: AppTheme.secondary.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(Icons.person_rounded, color: AppTheme.secondary, size: 24),
            ),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(email.isNotEmpty ? email : phone.isNotEmpty ? phone : '—',
                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
              if (phone.isNotEmpty && email.isNotEmpty)
                Text(phone, style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant)),
              if (note.isNotEmpty)
                Text(note, style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant), maxLines: 1, overflow: TextOverflow.ellipsis),
            ])),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: statusColor.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(statusLabel, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: statusColor)),
            ),
          ]),
          if (expiresStr.isNotEmpty) ...[
            const SizedBox(height: 6),
            Row(children: [
              Icon(Icons.access_time_rounded, size: 13, color: cs.onSurfaceVariant),
              const SizedBox(width: 4),
              Text('Ablaufdatum: $expiresStr', style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant)),
            ]),
          ],
          if (isPending) ...[
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 6, children: [
              if (phone.isNotEmpty)
                OutlinedButton.icon(
                  onPressed: onWhatsApp,
                  icon: const Icon(Icons.chat_rounded, size: 16),
                  label: const Text('WhatsApp'),
                  style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF25D366)),
                ),
              OutlinedButton.icon(
                onPressed: onEdit,
                icon: const Icon(Icons.edit_rounded, size: 16),
                label: const Text('Bearbeiten'),
              ),
              OutlinedButton.icon(
                onPressed: onRevoke,
                icon: const Icon(Icons.block_rounded, size: 16),
                label: const Text('Widerrufen'),
                style: OutlinedButton.styleFrom(foregroundColor: Colors.red, side: const BorderSide(color: Colors.red)),
              ),
            ]),
          ],
        ]),
      ),
    );
  }
}

// ── New Invite Dialog ─────────────────────────────────────────────────────────

class _InviteDialog extends StatefulWidget {
  const _InviteDialog();

  @override
  State<_InviteDialog> createState() => _InviteDialogState();
}

class _InviteDialogState extends State<_InviteDialog> {
  final _api = ApiService();
  final _emailCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _noteCtrl  = TextEditingController();
  final _searchCtrl = TextEditingController();
  String? _ownerDisplayName;
  List<dynamic> _owners = [];
  List<dynamic> _filtered = [];
  bool _loadingOwners = true;
  String _search = '';

  @override
  void initState() {
    super.initState();
    _loadOwners();
  }

  Future<void> _loadOwners() async {
    try {
      final data = await _api.owners(perPage: 500);
      final items = (data['items'] as List? ?? []);
      setState(() { _owners = items; _filtered = items; _loadingOwners = false; });
    } catch (_) {
      setState(() => _loadingOwners = false);
    }
  }

  String _ownerName(Map o) {
    final fn = o['first_name'] as String? ?? '';
    final ln = o['last_name']  as String? ?? '';
    final n  = o['name']       as String? ?? '';
    if (ln.isNotEmpty) return '$ln, $fn'.trim().replaceAll(RegExp(r',\s*$'), '');
    return n.isNotEmpty ? n : '—';
  }

  void _filterOwners(String q) {
    final lq = q.toLowerCase();
    setState(() {
      _search   = q;
      _filtered = q.isEmpty
          ? _owners
          : _owners.where((o) =>
              _ownerName(o as Map).toLowerCase().contains(lq) ||
              (o['email'] as String? ?? '').toLowerCase().contains(lq) ||
              (o['phone'] as String? ?? '').toLowerCase().contains(lq)
            ).toList();
    });
  }

  void _selectOwner(Map o) {
    setState(() {
      _ownerDisplayName = _ownerName(o);
      if ((o['email'] as String? ?? '').isNotEmpty) _emailCtrl.text = o['email'] as String;
      if ((o['phone'] as String? ?? '').isNotEmpty) _phoneCtrl.text = o['phone'] as String;
    });
    Navigator.pop(context);
  }

  Future<void> _pickOwner() async {
    _searchCtrl.clear();
    _filterOwners('');
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, ss) {
          final isDark = Theme.of(ctx).brightness == Brightness.dark;
          final cs     = Theme.of(ctx).colorScheme;
          return Container(
            height: MediaQuery.of(ctx).size.height * 0.75,
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF12151F) : Colors.white,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
            ),
            child: Column(children: [
              Padding(
                padding: const EdgeInsets.only(top: 12, bottom: 4),
                child: Container(width: 40, height: 4,
                  decoration: BoxDecoration(
                    color: cs.onSurface.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(2))),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 16, 12),
                child: Row(children: [
                  const Expanded(child: Text('Tierhalter auswählen (optional)',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700))),
                  IconButton(icon: const Icon(Icons.close_rounded),
                    onPressed: () => Navigator.pop(ctx)),
                ]),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                child: TextField(
                  controller: _searchCtrl, autofocus: true,
                  decoration: InputDecoration(
                    hintText: 'Suchen...',
                    prefixIcon: const Icon(Icons.search_rounded, size: 20),
                    suffixIcon: _search.isNotEmpty
                        ? IconButton(icon: const Icon(Icons.clear_rounded, size: 18),
                            onPressed: () { _searchCtrl.clear(); ss(() => _filterOwners('')); })
                        : null,
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    filled: true,
                    fillColor: isDark ? Colors.white.withValues(alpha: 0.05) : Colors.black.withValues(alpha: 0.03),
                  ),
                  onChanged: (v) => ss(() => _filterOwners(v)),
                ),
              ),
              const Divider(height: 1),
              Expanded(child: _loadingOwners
                  ? const Center(child: CircularProgressIndicator())
                  : _filtered.isEmpty
                      ? Center(child: Text('Keine Tierhalter gefunden',
                          style: TextStyle(color: cs.onSurface.withValues(alpha: 0.4))))
                      : ListView.builder(
                          padding: const EdgeInsets.fromLTRB(12, 8, 12, 20),
                          itemCount: _filtered.length,
                          itemBuilder: (_, i) {
                            final o     = _filtered[i] as Map;
                            final dname = _ownerName(o);
                            final email = o['email'] as String? ?? '';
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 6),
                              child: InkWell(
                                borderRadius: BorderRadius.circular(12),
                                onTap: () => _selectOwner(o),
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                                  decoration: BoxDecoration(
                                    borderRadius: BorderRadius.circular(12),
                                    border: Border.all(color: cs.outline.withValues(alpha: 0.15)),
                                  ),
                                  child: Row(children: [
                                    Icon(Icons.person_outline_rounded, size: 16,
                                      color: cs.onSurface.withValues(alpha: 0.4)),
                                    const SizedBox(width: 10),
                                    Expanded(child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(dname, style: const TextStyle(fontWeight: FontWeight.w600)),
                                        if (email.isNotEmpty)
                                          Text(email, style: TextStyle(fontSize: 11,
                                            color: cs.onSurface.withValues(alpha: 0.5))),
                                      ],
                                    )),
                                    Icon(Icons.chevron_right_rounded, size: 18,
                                      color: cs.onSurface.withValues(alpha: 0.3)),
                                  ]),
                                ),
                              ),
                            );
                          },
                        )),
            ]),
          );
        },
      ),
    );
  }

  @override
  void dispose() {
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    _noteCtrl.dispose();
    _searchCtrl.dispose();
    super.dispose();
  }

  bool get _canSend {
    final e = _emailCtrl.text.trim();
    final p = _phoneCtrl.text.trim();
    return e.isNotEmpty || p.isNotEmpty;
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return AlertDialog(
      title: const Text('Einladung senden'),
      content: SizedBox(
        width: 360,
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          /* Optional: prefill from existing owner */
          OutlinedButton.icon(
            onPressed: _pickOwner,
            icon: const Icon(Icons.person_search_rounded, size: 18),
            label: Text(_ownerDisplayName != null
                ? 'Tierhalter: $_ownerDisplayName'
                : 'Aus Tierhaltern befüllen (optional)'),
            style: OutlinedButton.styleFrom(
              alignment: Alignment.centerLeft,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              textStyle: const TextStyle(fontSize: 13),
              foregroundColor: _ownerDisplayName != null
                  ? AppTheme.primary
                  : cs.onSurface.withValues(alpha: 0.6),
              side: BorderSide(
                color: _ownerDisplayName != null
                    ? AppTheme.primary.withValues(alpha: 0.4)
                    : cs.outline.withValues(alpha: 0.4)),
            ),
          ),
          const SizedBox(height: 4),
          Text('oder E-Mail/Telefon direkt eingeben:',
            style: TextStyle(fontSize: 11, color: cs.onSurface.withValues(alpha: 0.4))),
          const SizedBox(height: 10),
          TextField(
            controller: _emailCtrl,
            decoration: const InputDecoration(
              labelText: 'E-Mail',
              hintText: 'z.B. max@beispiel.de',
              isDense: true,
              prefixIcon: Icon(Icons.email_outlined, size: 18),
              border: OutlineInputBorder()),
            keyboardType: TextInputType.emailAddress,
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _phoneCtrl,
            decoration: const InputDecoration(
              labelText: 'Telefon (für WhatsApp)',
              hintText: 'z.B. +49 151 12345678',
              isDense: true,
              prefixIcon: Icon(Icons.phone_outlined, size: 18),
              border: OutlineInputBorder()),
            keyboardType: TextInputType.phone,
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _noteCtrl,
            decoration: const InputDecoration(
              labelText: 'Notiz (optional)',
              isDense: true,
              prefixIcon: Icon(Icons.notes_rounded, size: 18),
              border: OutlineInputBorder()),
          ),
          const SizedBox(height: 6),
          Text('Mindestens E-Mail oder Telefon erforderlich.',
            style: TextStyle(fontSize: 11, color: cs.onSurface.withValues(alpha: 0.4))),
        ]),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Abbrechen')),
        FilledButton(
          onPressed: _canSend
              ? () => Navigator.pop(context, {
                  'email': _emailCtrl.text.trim(),
                  'phone': _phoneCtrl.text.trim(),
                  'note':  _noteCtrl.text.trim(),
                })
              : null,
          child: const Text('Einladung senden'),
        ),
      ],
    );
  }
}

// ── Invite Link Dialog ────────────────────────────────────────────────────────

class _InviteLinkDialog extends StatelessWidget {
  final String link;
  final String phone;
  final String whatsappUrl;
  const _InviteLinkDialog({required this.link, required this.phone, this.whatsappUrl = ''});

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Einladung erstellt'),
      content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Einladungslink:', style: TextStyle(fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surfaceContainerHighest,
            borderRadius: BorderRadius.circular(8),
          ),
          child: SelectableText(link, style: const TextStyle(fontSize: 12)),
        ),
      ]),
      actions: [
        TextButton.icon(
          onPressed: () {
            Clipboard.setData(ClipboardData(text: link));
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Link kopiert'), behavior: SnackBarBehavior.floating));
          },
          icon: const Icon(Icons.copy_rounded, size: 16),
          label: const Text('Kopieren'),
        ),
        if (whatsappUrl.isNotEmpty || phone.isNotEmpty)
          FilledButton.icon(
            onPressed: () async {
              Navigator.pop(context);
              final urlToOpen = whatsappUrl.isNotEmpty
                  ? whatsappUrl
                  : 'https://wa.me/${phone.replaceAll(RegExp(r'[^\d+]'), '')}?text=${Uri.encodeComponent('Einladungslink: $link')}';
              final uri = Uri.tryParse(urlToOpen);
              if (uri != null && await canLaunchUrl(uri)) {
                await launchUrl(uri, mode: LaunchMode.externalApplication);
              }
            },
            icon: const Icon(Icons.chat_rounded, size: 16),
            label: const Text('WhatsApp'),
            style: FilledButton.styleFrom(backgroundColor: const Color(0xFF25D366)),
          ),
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Schließen')),
      ],
    );
  }
}

// ── Edit Invite Dialog ────────────────────────────────────────────────────────

class _EditInviteDialog extends StatefulWidget {
  final String phone;
  final String note;
  const _EditInviteDialog({required this.phone, required this.note});

  @override
  State<_EditInviteDialog> createState() => _EditInviteDialogState();
}

class _EditInviteDialogState extends State<_EditInviteDialog> {
  late final TextEditingController _phoneCtrl;
  late final TextEditingController _noteCtrl;

  @override
  void initState() {
    super.initState();
    _phoneCtrl = TextEditingController(text: widget.phone);
    _noteCtrl  = TextEditingController(text: widget.note);
  }

  @override
  void dispose() {
    _phoneCtrl.dispose();
    _noteCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Einladung bearbeiten'),
      content: SizedBox(
        width: 360,
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(
            controller: _phoneCtrl,
            decoration: const InputDecoration(
              labelText: 'Telefon (für WhatsApp)',
              hintText: 'z.B. +49 151 12345678',
              isDense: true,
              prefixIcon: Icon(Icons.phone_outlined, size: 18),
              border: OutlineInputBorder(),
            ),
            keyboardType: TextInputType.phone,
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _noteCtrl,
            decoration: const InputDecoration(
              labelText: 'Notiz (optional)',
              isDense: true,
              prefixIcon: Icon(Icons.notes_rounded, size: 18),
              border: OutlineInputBorder(),
            ),
            maxLines: 3,
          ),
        ]),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Abbrechen')),
        FilledButton(
          onPressed: () => Navigator.pop(context, {
            'phone': _phoneCtrl.text.trim(),
            'note':  _noteCtrl.text.trim(),
          }),
          child: const Text('Speichern'),
        ),
      ],
    );
  }
}
