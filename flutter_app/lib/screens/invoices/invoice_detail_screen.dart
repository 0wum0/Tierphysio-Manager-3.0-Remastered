import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class InvoiceDetailScreen extends StatefulWidget {
  final int id;
  const InvoiceDetailScreen({super.key, required this.id});

  @override
  State<InvoiceDetailScreen> createState() => _InvoiceDetailScreenState();
}

class _InvoiceDetailScreenState extends State<InvoiceDetailScreen>
    with SingleTickerProviderStateMixin {
  final _api = ApiService();
  late TabController _tabs;
  Map<String, dynamic>? _invoice;
  List<Map<String, dynamic>> _dunnings  = [];
  List<Map<String, dynamic>> _reminders = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
    _load();
  }

  @override
  void dispose() { _tabs.dispose(); super.dispose(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final results = await Future.wait([
        _api.invoiceShow(widget.id),
        _api.dunningsForInvoice(widget.id).then((v) => v).catchError((_) => <dynamic>[]),
        _api.remindersForInvoice(widget.id).then((v) => v).catchError((_) => <dynamic>[]),
      ]);
      setState(() {
        _invoice  = results[0] as Map<String, dynamic>;
        _dunnings  = (results[1] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _reminders = (results[2] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _loading  = false;
      });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  String _eur(dynamic v) {
    final d = v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0.0;
    return NumberFormat.currency(locale: 'de_DE', symbol: '€').format(d);
  }

  String _date(String? d) {
    if (d == null || d.isEmpty) return '—';
    try { return DateFormat('dd.MM.yyyy').format(DateTime.parse(d)); } catch (_) { return d; }
  }

  Future<void> _changeStatus() async {
    final current = _invoice!['status'] as String? ?? '';
    final options = <String, String>{
      'draft': 'Entwurf', 'open': 'Offen', 'paid': 'Bezahlt', 'overdue': 'Überfällig', 'cancelled': 'Storniert',
    };
    final selected = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Row(children: [
          Icon(Icons.swap_horiz_rounded, size: 20),
          SizedBox(width: 8),
          Text('Status ändern'),
        ]),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: options.entries.map((e) => RadioListTile<String>(
            value: e.key,
            groupValue: current,
            title: Text(e.value),
            onChanged: (v) => Navigator.pop(ctx, v),
            activeColor: AppTheme.primary,
            dense: true,
          )).toList(),
        ),
        actions: [TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Abbrechen'))],
      ),
    );
    if (selected == null || selected == current) return;
    
    String? reason;
    if (selected == 'cancelled') {
        final reasonCtrl = TextEditingController();
        final bool? confirmReason = await showDialog<bool>(
            context: context,
            builder: (ctx) => AlertDialog(
                title: const Text('Stornierungsgrund'),
                content: TextField(
                    controller: reasonCtrl,
                    decoration: const InputDecoration(labelText: 'Welcher Grund?', border: OutlineInputBorder()),
                    maxLines: 2,
                    autofocus: true,
                ),
                actions: [
                    TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Abbrechen')),
                    FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Speichern')),
                ]
            )
        );
        if (confirmReason != true) return;
        reason = reasonCtrl.text.trim();
    }

    try {
      setState(() => _loading = true);
      await _api.invoiceUpdateStatus(widget.id, selected, reason: reason);
      await _load();
      _showSnack('Status auf "${options[selected]}" geändert ✓');
    } catch (e) { _showSnack(e.toString(), error: true); }
  }

  Future<void> _openPdf() async {
    try {
      final data = await _api.invoicePdfUrl(widget.id);
      final url  = data['pdf_url'] as String? ?? '';
      if (url.isEmpty) { _showSnack('Keine PDF-URL verfügbar.', error: true); return; }
      final fullUrl = url.startsWith('http') ? url : '${ApiService.baseUrl}$url';
      final uri = Uri.parse(fullUrl);
      if (await canLaunchUrl(uri)) await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (e) { _showSnack(e.toString(), error: true); }
  }

  Future<void> _sharePdfWhatsApp() async {
    try {
      final data = await _api.invoicePdfUrl(widget.id);
      final url  = data['pdf_url'] as String? ?? '';
      if (url.isEmpty) { _showSnack('Keine PDF-URL verfügbar.', error: true); return; }
      final fullUrl = url.startsWith('http') ? url : '${ApiService.baseUrl}$url';
      final inv = _invoice!;
      final num = inv['invoice_number'] as String? ?? 'Rechnung';
      final msg = Uri.encodeComponent('Ihre Rechnung $num: $fullUrl');
      final waUri = Uri.parse('https://wa.me/?text=$msg');
      if (await canLaunchUrl(waUri)) {
        await launchUrl(waUri, mode: LaunchMode.externalApplication);
      } else {
        _showSnack('WhatsApp nicht verfügbar.', error: true);
      }
    } catch (e) { _showSnack(e.toString(), error: true); }
  }

  Future<void> _delete() async {
    final inv = _invoice;
    final num = inv?['invoice_number'] as String? ?? 'diese Rechnung';
    final ok = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(
      icon: Icon(Icons.delete_forever_rounded, color: AppTheme.danger, size: 32),
      title: const Text('Rechnung löschen?'),
      content: Text('Möchtest du $num wirklich dauerhaft löschen? Diese Aktion kann nicht rückgängig gemacht werden.'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Abbrechen')),
        FilledButton.icon(
          icon: const Icon(Icons.delete_forever_rounded, size: 16),
          label: const Text('Ja, löschen'),
          style: FilledButton.styleFrom(backgroundColor: AppTheme.danger),
          onPressed: () => Navigator.pop(ctx, true),
        ),
      ],
    ));
    if (ok != true) return;
    try {
      await _api.invoiceDelete(widget.id);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: const Row(children: [
            Icon(Icons.check_circle_rounded, color: Colors.white, size: 18),
            SizedBox(width: 8),
            Text('Rechnung gelöscht'),
          ]),
          backgroundColor: AppTheme.danger,
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 2),
        ));
        context.pop();
      }
    } catch (e) { _showSnack(e.toString(), error: true); }
  }

  Future<void> _sendEmail() async {
    _showSnack('E-Mail wird gesendet…');
    try {
      final result = await _api.invoiceSendEmail(widget.id);
      final email = result['email'] as String? ?? '';
      _showSnack('Rechnung per E-Mail gesendet an $email ✓');
      _load();
    } catch (e) { _showSnack(e.toString(), error: true); }
  }

  Future<void> _addDunning() async {
    final notesCtrl = TextEditingController();
    int level = (_dunnings.length) + 1;
    if (level > 3) level = 3;
    await showModalBottomSheet(
      context: context, isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom + 32),
        child: Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 0), child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
          Text('Mahnung erstellen (Stufe $level)',
            style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          TextField(controller: notesCtrl,
            decoration: const InputDecoration(labelText: 'Notizen', prefixIcon: Icon(Icons.notes_rounded)), maxLines: 2),
          const SizedBox(height: 20),
          SizedBox(width: double.infinity, child: FilledButton.icon(
            icon: const Icon(Icons.warning_amber_rounded),
            label: const Text('Mahnung erstellen'),
            onPressed: () async {
              Navigator.pop(ctx);
              try {
                await _api.dunningCreate(widget.id, {'level': level, 'notes': notesCtrl.text.trim()});
                _showSnack('Mahnung erstellt ✓');
                _load();
              } catch (e) { _showSnack(e.toString(), error: true); }
            },
          )),
        ])),
      ),
    );
  }

  Future<void> _addReminder() async {
    DateTime selDate = DateTime.now().add(const Duration(days: 7));
    final notesCtrl = TextEditingController();
    await showModalBottomSheet(
      context: context, isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(builder: (ctx, ss) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom + 32),
        child: Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 0), child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
          Text('Erinnerung erstellen', style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          InkWell(
            borderRadius: BorderRadius.circular(8),
            onTap: () async {
              final d = await showDatePicker(context: ctx, initialDate: selDate,
                firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 365)));
              if (d != null) ss(() => selDate = d);
            },
            child: InputDecorator(
              decoration: const InputDecoration(labelText: 'Datum', prefixIcon: Icon(Icons.calendar_today_rounded)),
              child: Text('${selDate.day.toString().padLeft(2,'0')}.${selDate.month.toString().padLeft(2,'0')}.${selDate.year}'),
            ),
          ),
          const SizedBox(height: 10),
          TextField(controller: notesCtrl,
            decoration: const InputDecoration(labelText: 'Notizen', prefixIcon: Icon(Icons.notes_rounded)), maxLines: 2),
          const SizedBox(height: 20),
          SizedBox(width: double.infinity, child: FilledButton.icon(
            icon: const Icon(Icons.alarm_add_rounded),
            label: const Text('Erinnerung erstellen'),
            onPressed: () async {
              Navigator.pop(ctx);
              try {
                await _api.reminderCreate(widget.id, {
                  'remind_at': selDate.toIso8601String().substring(0, 10),
                  'notes': notesCtrl.text.trim(),
                });
                _showSnack('Erinnerung erstellt ✓');
                _load();
              } catch (e) { _showSnack(e.toString(), error: true); }
            },
          )),
        ])),
      )),
    );
  }

  void _showSnack(String msg, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Row(children: [
        Icon(error ? Icons.error_outline_rounded : Icons.check_circle_rounded,
          color: Colors.white, size: 16),
        const SizedBox(width: 8),
        Expanded(child: Text(msg, style: const TextStyle(color: Colors.white))),
      ]),
      backgroundColor: error ? AppTheme.danger : AppTheme.success,
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      margin: const EdgeInsets.all(12),
    ));
  }

  @override
  Widget build(BuildContext context) {
    final inv = _invoice;
    return Scaffold(
      appBar: AppBar(
        title: Text(inv?['invoice_number'] as String? ?? 'Rechnung'),
        actions: [
          if (inv != null) ...[
            IconButton(icon: const Icon(Icons.picture_as_pdf_rounded), tooltip: 'PDF öffnen', onPressed: _openPdf),
            IconButton(icon: const Icon(Icons.swap_horiz_rounded), tooltip: 'Status ändern', onPressed: _changeStatus),
            PopupMenuButton<String>(
              onSelected: (v) {
                if (v == 'delete')    _delete();
                if (v == 'whatsapp') _sharePdfWhatsApp();
                if (v == 'email')    _sendEmail();
              },
              itemBuilder: (_) => [
                const PopupMenuItem(value: 'email', child: Row(children: [
                  Icon(Icons.email_rounded, color: Colors.blue, size: 18),
                  SizedBox(width: 8),
                  Text('Per E-Mail senden'),
                ])),
                const PopupMenuItem(value: 'whatsapp', child: Row(children: [
                  Icon(Icons.share_rounded, color: Color(0xFF25D366), size: 18),
                  SizedBox(width: 8),
                  Text('Per WhatsApp teilen'),
                ])),
                const PopupMenuDivider(),
                const PopupMenuItem(value: 'delete', child: Row(children: [
                  Icon(Icons.delete_outline_rounded, color: Colors.red, size: 18),
                  SizedBox(width: 8),
                  Text('Löschen', style: TextStyle(color: Colors.red)),
                ])),
              ],
            ),
          ],
        ],
        bottom: _loading || _error != null ? null : TabBar(
          controller: _tabs,
          tabs: [
            const Tab(text: 'Rechnung', icon: Icon(Icons.receipt_long_rounded, size: 16)),
            Tab(text: 'Mahnungen', icon: Badge(
              isLabelVisible: _dunnings.isNotEmpty,
              label: Text('${_dunnings.length}'),
              backgroundColor: AppTheme.danger,
              child: const Icon(Icons.warning_amber_rounded, size: 16),
            )),
            Tab(text: 'Erinnerungen', icon: Badge(
              isLabelVisible: _reminders.isNotEmpty,
              label: Text('${_reminders.length}'),
              backgroundColor: AppTheme.warning,
              child: const Icon(Icons.alarm_rounded, size: 16),
            )),
          ],
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(Icons.error_outline_rounded, size: 56, color: AppTheme.danger),
                  const SizedBox(height: 12),
                  Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut')),
                ]))
              : TabBarView(
                  controller: _tabs,
                  children: [
                    _buildDetails(),
                    _buildDunnings(),
                    _buildReminders(),
                  ],
                ),
    );
  }

  Widget _buildDetails() {
    final inv = _invoice!;
    final positions = List<dynamic>.from(inv['positions'] as List? ?? []);
    final cs = Theme.of(context).colorScheme;
    final statusColor = _statusColor(inv['status'] as String? ?? '');

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      child: Column(children: [
        // Header card
        Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: [statusColor, Color.lerp(statusColor, AppTheme.secondary, 0.4)!]),
            borderRadius: BorderRadius.circular(16),
          ),
          padding: const EdgeInsets.all(20),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(child: Text(inv['invoice_number'] as String? ?? '',
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18))),
              _StatusBadge(status: inv['status'] as String? ?? ''),
            ]),
            const SizedBox(height: 16),
            _headerRow(Icons.person_rounded, 'Tierhalter', inv['owner_name'] as String? ?? '—'),
            if (inv['patient_name'] != null)
              _headerRow(Icons.pets_rounded, 'Patient', inv['patient_name'] as String),
            _headerRow(Icons.calendar_today_rounded, 'Datum', _date(inv['issue_date'] as String?)),
            _headerRow(Icons.schedule_rounded, 'Fällig', _date(inv['due_date'] as String?)),
          ]),
        ),
        const SizedBox(height: 16),
        // Positions
        Container(
          decoration: BoxDecoration(
            color: Theme.of(context).cardTheme.color,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: Theme.of(context).dividerColor),
          ),
          child: Column(children: [
            Padding(padding: const EdgeInsets.fromLTRB(16, 14, 16, 6),
              child: Row(children: [
                Icon(Icons.list_rounded, size: 18, color: AppTheme.primary),
                const SizedBox(width: 8),
                Text('Positionen', style: TextStyle(fontWeight: FontWeight.w700, color: AppTheme.primary, fontSize: 14)),
              ])),
            const Divider(height: 1),
            ...positions.asMap().entries.map((entry) {
              final i = entry.key;
              final p = Map<String, dynamic>.from(entry.value as Map);
              return Column(children: [
                if (i > 0) const Divider(height: 1),
                Padding(padding: const EdgeInsets.all(14),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(p['description'] as String? ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
                      const SizedBox(height: 2),
                      Text('${p['quantity']} × ${_eur(p['unit_price'])}  ·  MwSt. ${p['tax_rate']}%',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(color: cs.onSurfaceVariant)),
                    ])),
                    Text(_eur(p['total']), style: const TextStyle(fontWeight: FontWeight.w700)),
                  ])),
              ]);
            }),
            const Divider(height: 1),
            Padding(padding: const EdgeInsets.all(16), child: Column(children: [
              _totRow('Netto',  _eur(inv['total_net']),  false),
              const SizedBox(height: 4),
              _totRow('MwSt.', _eur(inv['total_tax']),  false),
              const Divider(height: 16),
              _totRow('Gesamt', _eur(inv['total_gross']), true),
            ])),
          ]),
        ),
        if ((inv['notes'] as String? ?? '').isNotEmpty) ...[const SizedBox(height: 12),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Theme.of(context).cardTheme.color,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Theme.of(context).dividerColor),
            ),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Icon(Icons.notes_rounded, size: 16, color: AppTheme.warning),
                const SizedBox(width: 8),
                Text('Notizen', style: TextStyle(fontWeight: FontWeight.w700, color: AppTheme.warning)),
              ]),
              const SizedBox(height: 8),
              Text(inv['notes'] as String, style: Theme.of(context).textTheme.bodyMedium),
            ]),
          ),
        ],
        if (inv['status'] == 'cancelled' && (inv['cancellation_reason'] as String? ?? '').isNotEmpty) ...[const SizedBox(height: 12),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Theme.of(context).cardTheme.color,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppTheme.danger.withValues(alpha: 0.3)),
            ),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Row(children: [
                Icon(Icons.cancel_rounded, size: 16, color: AppTheme.danger),
                SizedBox(width: 8),
                Text('Stornierungsgrund', style: TextStyle(fontWeight: FontWeight.w700, color: AppTheme.danger)),
              ]),
              const SizedBox(height: 8),
              Text(inv['cancellation_reason'] as String, style: Theme.of(context).textTheme.bodyMedium),
            ]),
          ),
        ],
      ]),
    );
  }

  Widget _buildDunnings() {
    return Column(children: [
      Expanded(child: _dunnings.isEmpty
          ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              Icon(Icons.check_circle_outline_rounded, size: 72, color: AppTheme.success.withValues(alpha: 0.4)),
              const SizedBox(height: 16),
              Text('Keine Mahnungen', style: TextStyle(color: Colors.grey.shade500, fontSize: 16)),
            ]))
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: _dunnings.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (ctx, i) {
                final d = _dunnings[i];
                final level = d['level'] as int? ?? 1;
                final color = level >= 3 ? AppTheme.danger : level == 2 ? AppTheme.warning : AppTheme.tertiary;
                return Container(
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardTheme.color,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: color.withValues(alpha: 0.3)),
                  ),
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: color.withValues(alpha: 0.12),
                      child: Text('M$level', style: TextStyle(color: color, fontWeight: FontWeight.w800)),
                    ),
                    title: Text('Mahnung Stufe $level', style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: Text(_date(d['sent_at'] as String? ?? d['created_at'] as String?),
                      style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                    trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                      IconButton(
                        icon: const Icon(Icons.email_rounded, size: 20),
                        color: Colors.blue,
                        tooltip: 'Per E-Mail senden',
                        onPressed: () async {
                          _showSnack('Mahnung wird gesendet…');
                          try {
                            final r = await _api.dunningSendEmail(widget.id, d['id'] as int);
                            _showSnack('Mahnung gesendet an ${r['email'] ?? ''} ✓');
                            _load();
                          } catch (e) { _showSnack(e.toString(), error: true); }
                        },
                      ),
                      IconButton(
                        icon: Icon(Icons.delete_outline_rounded, color: AppTheme.danger),
                        tooltip: 'Löschen',
                        onPressed: () async {
                          final ok = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(
                            icon: Icon(Icons.delete_rounded, color: AppTheme.danger),
                            title: const Text('Mahnung löschen?'),
                            content: Text('Mahnung Stufe $level wirklich löschen?'),
                            actions: [
                              TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Abbrechen')),
                              FilledButton.icon(
                                icon: const Icon(Icons.delete_rounded, size: 16),
                                label: const Text('Löschen'),
                                style: FilledButton.styleFrom(backgroundColor: AppTheme.danger),
                                onPressed: () => Navigator.pop(ctx, true),
                              ),
                            ],
                          ));
                          if (ok != true) return;
                          try { await _api.dunningDelete(widget.id, d['id'] as int); _showSnack('Mahnung gelöscht ✓'); _load(); }
                          catch (e) { _showSnack(e.toString(), error: true); }
                        },
                      ),
                    ]),
                  ),
                );
              },
            )),
      Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
        child: SizedBox(width: double.infinity, child: FilledButton.icon(
          icon: const Icon(Icons.add_rounded),
          label: const Text('Mahnung erstellen'),
          onPressed: _addDunning,
        ))),
    ]);
  }

  Widget _buildReminders() {
    return Column(children: [
      Expanded(child: _reminders.isEmpty
          ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              Icon(Icons.alarm_off_rounded, size: 72, color: Colors.grey.shade300),
              const SizedBox(height: 16),
              Text('Keine Erinnerungen', style: TextStyle(color: Colors.grey.shade500, fontSize: 16)),
            ]))
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: _reminders.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (ctx, i) {
                final r = _reminders[i];
                final sent = r['sent'] as bool? ?? false;
                final color = sent ? AppTheme.success : AppTheme.warning;
                return Container(
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardTheme.color,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: color.withValues(alpha: 0.3)),
                  ),
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: color.withValues(alpha: 0.12),
                      child: Icon(sent ? Icons.alarm_on_rounded : Icons.alarm_rounded, color: color, size: 20),
                    ),
                    title: Text(_date(r['remind_at'] as String?), style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: (r['notes'] as String? ?? '').isNotEmpty
                        ? Text(r['notes'] as String, style: TextStyle(fontSize: 12, color: Colors.grey.shade600))
                        : null,
                    trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
                        child: Text(sent ? 'Gesendet' : 'Ausstehend',
                          style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w600)),
                      ),
                      IconButton(
                        icon: const Icon(Icons.email_rounded, size: 20),
                        color: Colors.blue,
                        tooltip: 'Per E-Mail senden',
                        onPressed: () async {
                          _showSnack('Erinnerung wird gesendet…');
                          try {
                            final res = await _api.reminderSendEmail(widget.id, r['id'] as int);
                            _showSnack('Erinnerung gesendet an ${res['email'] ?? ''} ✓');
                            _load();
                          } catch (e) { _showSnack(e.toString(), error: true); }
                        },
                      ),
                      IconButton(
                        icon: Icon(Icons.delete_outline_rounded, color: AppTheme.danger),
                        tooltip: 'Löschen',
                        onPressed: () async {
                          final ok = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(
                            icon: Icon(Icons.delete_rounded, color: AppTheme.danger),
                            title: const Text('Erinnerung löschen?'),
                            content: const Text('Diese Erinnerung wirklich löschen?'),
                            actions: [
                              TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Abbrechen')),
                              FilledButton.icon(
                                icon: const Icon(Icons.delete_rounded, size: 16),
                                label: const Text('Löschen'),
                                style: FilledButton.styleFrom(backgroundColor: AppTheme.danger),
                                onPressed: () => Navigator.pop(ctx, true),
                              ),
                            ],
                          ));
                          if (ok != true) return;
                          try { await _api.reminderDelete(widget.id, r['id'] as int); _showSnack('Erinnerung gelöscht ✓'); _load(); }
                          catch (e) { _showSnack(e.toString(), error: true); }
                        },
                      ),
                    ]),
                  ),
                );
              },
            )),
      Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
        child: SizedBox(width: double.infinity, child: FilledButton.icon(
          icon: const Icon(Icons.alarm_add_rounded),
          label: const Text('Erinnerung erstellen'),
          onPressed: _addReminder,
        ))),
    ]);
  }

  Widget _headerRow(IconData icon, String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Row(children: [
      Icon(icon, size: 14, color: Colors.white.withValues(alpha: 0.8)),
      const SizedBox(width: 6),
      Text('$label: ', style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 12)),
      Expanded(child: Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12),
        overflow: TextOverflow.ellipsis)),
    ]),
  );

  Widget _totRow(String label, String value, bool bold) => Row(
    mainAxisAlignment: MainAxisAlignment.spaceBetween,
    children: [
      Text(label, style: TextStyle(fontWeight: bold ? FontWeight.w700 : FontWeight.normal,
        fontSize: bold ? 15 : 13)),
      Text(value, style: TextStyle(fontWeight: bold ? FontWeight.w800 : FontWeight.normal,
        fontSize: bold ? 16 : 13,
        color: bold ? AppTheme.primary : null)),
    ],
  );

  Color _statusColor(String s) => switch (s) {
    'paid'      => AppTheme.success,
    'open'      => AppTheme.primary,
    'overdue'   => AppTheme.danger,
    'cancelled' => AppTheme.danger,
    'draft'     => Colors.grey,
    _           => AppTheme.primary,
  };
}

class _StatusBadge extends StatelessWidget {
  final String status;
  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'paid'      => ('Bezahlt',    Colors.green),
      'open'      => ('Offen',      Colors.blue),
      'overdue'   => ('Überfällig', Colors.red),
      'cancelled' => ('Storniert',  Colors.red),
      'draft'     => ('Entwurf',    Colors.grey),
      _           => (status,       Colors.grey),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(20)),
      child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 11)),
    );
  }
}
