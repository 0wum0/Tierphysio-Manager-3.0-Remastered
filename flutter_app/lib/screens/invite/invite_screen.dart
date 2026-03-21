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
      final items = await _api.inviteList();
      setState(() { _items = items; _loading = false; });
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
      final link = resp['invite_link'] as String? ?? '';
      if (link.isNotEmpty) {
        await showDialog(
          context: context,
          builder: (_) => _InviteLinkDialog(link: link, phone: result['phone'] as String? ?? ''),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: const Text('✓ Einladung gesendet'),
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
        await _openWhatsApp(link, phone);
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _openWhatsApp(String waUrl, String phone) async {
    final cleanPhone = phone.replaceAll(RegExp(r'[^\d+]'), '');
    final message = Uri.encodeComponent(waUrl);

    // 1. WhatsApp Business (com.whatsapp.w4b) via Android intent scheme
    final businessIntentUri = Uri.parse(
      'intent://send?phone=$cleanPhone&text=$message'
      '#Intent;scheme=whatsapp;package=com.whatsapp.w4b;end',
    );
    // 2. Regular WhatsApp (com.whatsapp) via Android intent scheme
    final waIntentUri = Uri.parse(
      'intent://send?phone=$cleanPhone&text=$message'
      '#Intent;scheme=whatsapp;package=com.whatsapp;end',
    );
    // 3. Generic whatsapp:// deep-link
    final waDeepLink = Uri.parse('whatsapp://send?phone=$cleanPhone&text=$message');
    // 4. Web fallback
    final webUri = Uri.parse('https://wa.me/$cleanPhone?text=$message');

    if (await canLaunchUrl(businessIntentUri)) {
      await launchUrl(businessIntentUri, mode: LaunchMode.externalApplication);
    } else if (await canLaunchUrl(waIntentUri)) {
      await launchUrl(waIntentUri, mode: LaunchMode.externalApplication);
    } else if (await canLaunchUrl(waDeepLink)) {
      await launchUrl(waDeepLink, mode: LaunchMode.externalApplication);
    } else {
      await launchUrl(webUri, mode: LaunchMode.externalApplication);
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
                          onRevoke: () => _revoke(Map<String, dynamic>.from(_items[i] as Map)),
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
  final VoidCallback onRevoke;

  const _InviteCard({required this.item, required this.onWhatsApp, required this.onRevoke});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final ownerName = item['owner_name'] as String?
        ?? '${item['first_name'] ?? ''} ${item['last_name'] ?? ''}'.trim();
    final email = item['email'] as String? ?? '';
    final phone = item['phone'] as String? ?? item['owner_phone'] as String? ?? '';
    final status = item['status'] as String? ?? 'pending';
    final expiresAt = item['expires_at'] as String? ?? item['invite_expires'] as String? ?? '';
    String expiresStr = '';
    if (expiresAt.isNotEmpty) {
      try {
        final dt = DateTime.parse(expiresAt);
        expiresStr = DateFormat('dd.MM.yyyy', 'de_DE').format(dt);
        if (dt.isBefore(DateTime.now())) expiresStr = 'Abgelaufen ($expiresStr)';
      } catch (_) {}
    }
    final isActive = status == 'active' || status == 'accepted';
    final isPending = status == 'pending' || status == 'invited';

    Color statusColor = isPending ? AppTheme.warning : isActive ? Colors.green.shade700 : cs.onSurfaceVariant;
    String statusLabel = isPending ? 'Ausstehend' : isActive ? 'Aktiv' : status;

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
              Text(ownerName.isNotEmpty ? ownerName : '—',
                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
              if (email.isNotEmpty)
                Text(email, style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant)),
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
          if (isPending && phone.isNotEmpty) ...[
            const SizedBox(height: 10),
            Row(children: [
              Expanded(child: OutlinedButton.icon(
                onPressed: onWhatsApp,
                icon: const Icon(Icons.chat_rounded, size: 16),
                label: const Text('WhatsApp senden'),
                style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF25D366)),
              )),
              const SizedBox(width: 10),
              OutlinedButton(
                onPressed: onRevoke,
                style: OutlinedButton.styleFrom(foregroundColor: Colors.red, side: const BorderSide(color: Colors.red)),
                child: const Text('Widerrufen'),
              ),
            ]),
          ] else if (isPending) ...[
            const SizedBox(height: 10),
            Align(
              alignment: Alignment.centerRight,
              child: OutlinedButton(
                onPressed: onRevoke,
                style: OutlinedButton.styleFrom(foregroundColor: Colors.red, side: const BorderSide(color: Colors.red)),
                child: const Text('Widerrufen'),
              ),
            ),
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
  int? _ownerId;
  List<dynamic> _owners = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadOwners();
  }

  Future<void> _loadOwners() async {
    try {
      final data = await _api.owners(perPage: 500);
      setState(() {
        _owners = (data['items'] as List? ?? []);
        _loading = false;
      });
    } catch (_) {
      setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Besitzer einladen'),
      content: SizedBox(
        width: 340,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : Column(mainAxisSize: MainAxisSize.min, children: [
                DropdownButtonFormField<int>(
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Tierhalter *', isDense: true, border: OutlineInputBorder()),
                  items: [
                    const DropdownMenuItem(value: null, child: Text('— auswählen —')),
                    ..._owners.map((o) => DropdownMenuItem<int>(
                      value: int.tryParse(o['id'].toString()),
                      child: Text('${o['last_name']}, ${o['first_name']}', overflow: TextOverflow.ellipsis),
                    )),
                  ],
                  onChanged: (v) {
                    setState(() => _ownerId = v);
                    if (v != null) {
                      final owner = _owners.firstWhere(
                        (o) => int.tryParse(o['id'].toString()) == v, orElse: () => {});
                      _emailCtrl.text = owner['email'] as String? ?? '';
                      _phoneCtrl.text = owner['phone'] as String? ?? '';
                    }
                  },
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _emailCtrl,
                  decoration: const InputDecoration(labelText: 'E-Mail *', isDense: true, border: OutlineInputBorder()),
                  keyboardType: TextInputType.emailAddress,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _phoneCtrl,
                  decoration: const InputDecoration(labelText: 'Telefon (für WhatsApp)', isDense: true, border: OutlineInputBorder()),
                  keyboardType: TextInputType.phone,
                ),
              ]),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Abbrechen')),
        FilledButton(
          onPressed: _ownerId == null || _emailCtrl.text.trim().isEmpty
              ? null
              : () => Navigator.pop(context, {
                  'owner_id': _ownerId,
                  'email': _emailCtrl.text.trim(),
                  'phone': _phoneCtrl.text.trim(),
                }),
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
  const _InviteLinkDialog({required this.link, required this.phone});

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
        if (phone.isNotEmpty)
          FilledButton.icon(
            onPressed: () async {
              Navigator.pop(context);
              final cleanPhone = phone.replaceAll(RegExp(r'[^\d+]'), '');
              final msg = Uri.encodeComponent('Hier ist dein Einladungslink zum Portal: $link');
              final businessUri = Uri.parse(
                'intent://send?phone=$cleanPhone&text=$msg'
                '#Intent;scheme=whatsapp;package=com.whatsapp.w4b;end',
              );
              final waUri = Uri.parse(
                'intent://send?phone=$cleanPhone&text=$msg'
                '#Intent;scheme=whatsapp;package=com.whatsapp;end',
              );
              final webUri = Uri.parse('https://wa.me/$cleanPhone?text=$msg');
              if (await canLaunchUrl(businessUri)) {
                await launchUrl(businessUri, mode: LaunchMode.externalApplication);
              } else if (await canLaunchUrl(waUri)) {
                await launchUrl(waUri, mode: LaunchMode.externalApplication);
              } else {
                await launchUrl(webUri, mode: LaunchMode.externalApplication);
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
