import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import 'dogschool_common.dart';

class InteressentenScreen extends StatefulWidget {
  const InteressentenScreen({super.key});
  @override
  State<InteressentenScreen> createState() => _InteressentenScreenState();
}

class _InteressentenScreenState extends State<InteressentenScreen> {
  final _api = ApiService();
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;
  String _statusFilter = '';
  String _search = '';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data =
          await _api.dogschoolLeads(status: _statusFilter, search: _search);
      setState(() {
        _items = (data['items'] as List? ?? [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _create() async {
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _LeadFormSheet(),
    );
    if (ok == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Interessenten'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _create,
        icon: const Icon(Icons.person_add_alt_1_rounded),
        label: const Text('Neuer Interessent'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: Column(
              children: [
                TextField(
                  decoration: InputDecoration(
                    hintText: 'Interessent suchen…',
                    prefixIcon: const Icon(Icons.search_rounded),
                    isDense: true,
                    border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12)),
                  ),
                  onSubmitted: (q) {
                    _search = q;
                    _load();
                  },
                ),
                const SizedBox(height: 10),
                SizedBox(
                  height: 36,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    children: [
                      for (final e in const {
                        '': 'Alle',
                        'new': 'Neu',
                        'contacted': 'Kontaktiert',
                        'trial_scheduled': 'Probe geplant',
                        'converted': 'Konvertiert',
                        'lost': 'Verloren',
                      }.entries)
                        Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: ChoiceChip(
                            label: Text(e.value),
                            selected: _statusFilter == e.key,
                            onSelected: (_) {
                              setState(() => _statusFilter = e.key);
                              _load();
                            },
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: AsyncBody(
              loading: _loading,
              error: _error,
              empty: _items.isEmpty,
              emptyTitle: 'Keine Interessenten',
              emptyHint: 'Erfasse einen Interessenten, um Anfragen zu verfolgen.',
              emptyIcon: Icons.person_search_outlined,
              onRetry: _load,
              child: RefreshIndicator(
                onRefresh: _load,
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
                  itemCount: _items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (_, i) {
                    final l = _items[i];
                    final cs = Theme.of(context).colorScheme;
                    return DsCard(
                      onTap: () async {
                        await Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                LeadDetailScreen(leadId: asInt(l['id'])),
                          ),
                        );
                        _load();
                      },
                      child: Row(children: [
                        CircleAvatar(
                          backgroundColor: statusColor(asStr(l['status']))
                              .withValues(alpha: 0.15),
                          child: Text(
                            (asStr(l['first_name']).isNotEmpty
                                    ? asStr(l['first_name'])[0]
                                    : '?')
                                .toUpperCase(),
                            style: TextStyle(
                                color: statusColor(asStr(l['status'])),
                                fontWeight: FontWeight.w800),
                          ),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${asStr(l['first_name'])} ${asStr(l['last_name'])}'.trim(),
                                style:
                                    const TextStyle(fontWeight: FontWeight.w700),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                [
                                  if (asStr(l['dog_name']).isNotEmpty)
                                    '🐕 ${asStr(l['dog_name'])}',
                                  if (asStr(l['interest']).isNotEmpty)
                                    asStr(l['interest']),
                                ].join(' · '),
                                style: TextStyle(
                                    fontSize: 12, color: cs.onSurfaceVariant),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                          ),
                        ),
                        StatusChip(
                            label: leadStatusLabel(asStr(l['status'])),
                            color: statusColor(asStr(l['status']))),
                      ]),
                    );
                  },
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/* ═══════════════════════ Lead detail ═══════════════════════ */

class LeadDetailScreen extends StatefulWidget {
  final int leadId;
  const LeadDetailScreen({super.key, required this.leadId});
  @override
  State<LeadDetailScreen> createState() => _LeadDetailScreenState();
}

class _LeadDetailScreenState extends State<LeadDetailScreen> {
  final _api = ApiService();
  Map<String, dynamic>? _lead;
  bool _loading = true;
  String? _error;

  static const _statusFlow = [
    'new',
    'contacted',
    'trial_scheduled',
    'trial_done',
    'converted',
    'lost',
    'archived',
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _api.dogschoolLead(widget.leadId);
      setState(() {
        _lead = data;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _setStatus(String status) async {
    try {
      await _api.dogschoolLeadUpdate(widget.leadId, {'status': status});
      _load();
    } catch (e) {
      if (mounted) showDsSnack(context, e.toString(), error: true);
    }
  }

  Future<void> _edit() async {
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _LeadFormSheet(existing: _lead),
    );
    if (ok == true) _load();
  }

  Future<void> _convert() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('In Kunde umwandeln'),
        content: const Text(
            'Es werden ein Halter und (falls Hundename vorhanden) ein Hund angelegt. Fortfahren?'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Abbrechen')),
          FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Umwandeln')),
        ],
      ),
    );
    if (confirm != true) return;
    try {
      await _api.dogschoolLeadConvert(widget.leadId);
      if (mounted) {
        showDsSnack(context, 'Interessent konvertiert ✓');
        _load();
      }
    } catch (e) {
      if (mounted) showDsSnack(context, e.toString(), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = _lead;
    final cs = Theme.of(context).colorScheme;
    final isConverted = asStr(l?['status']) == 'converted';
    return Scaffold(
      appBar: AppBar(
        title: Text(l != null
            ? '${asStr(l['first_name'])} ${asStr(l['last_name'])}'.trim()
            : 'Interessent'),
        actions: [
          if (l != null)
            IconButton(icon: const Icon(Icons.edit_rounded), onPressed: _edit),
        ],
      ),
      floatingActionButton: (l == null || isConverted)
          ? null
          : FloatingActionButton.extended(
              onPressed: _convert,
              icon: const Icon(Icons.how_to_reg_rounded),
              label: const Text('In Kunde umwandeln'),
            ),
      body: AsyncBody(
        loading: _loading,
        error: _error,
        empty: false,
        emptyTitle: '',
        emptyHint: '',
        emptyIcon: Icons.person_outline,
        onRetry: _load,
        child: l == null
            ? const SizedBox()
            : RefreshIndicator(
                onRefresh: _load,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
                  children: [
                    DsCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(children: [
                            StatusChip(
                                label: leadStatusLabel(asStr(l['status'])),
                                color: statusColor(asStr(l['status']))),
                            const Spacer(),
                            if (asStr(l['source']).isNotEmpty)
                              Text('Quelle: ${asStr(l['source'])}',
                                  style: TextStyle(
                                      fontSize: 12, color: cs.onSurfaceVariant)),
                          ]),
                          const SizedBox(height: 10),
                          _row(Icons.email_rounded, asStr(l['email'])),
                          _row(Icons.phone_rounded, asStr(l['phone'])),
                          _row(Icons.pets_rounded, [
                            asStr(l['dog_name']),
                            asStr(l['dog_breed']),
                            if (asInt(l['dog_age_months']) > 0)
                              '${asInt(l['dog_age_months'])} Mon.',
                          ].where((e) => e.isNotEmpty).join(' · ')),
                          _row(Icons.star_rounded, asStr(l['interest'])),
                          if (asStr(l['message']).isNotEmpty) ...[
                            const SizedBox(height: 8),
                            Text('Nachricht',
                                style: TextStyle(
                                    fontSize: 12, color: cs.onSurfaceVariant)),
                            Text(asStr(l['message'])),
                          ],
                          if (asStr(l['notes']).isNotEmpty) ...[
                            const SizedBox(height: 8),
                            Text('Notizen',
                                style: TextStyle(
                                    fontSize: 12, color: cs.onSurfaceVariant)),
                            Text(asStr(l['notes'])),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    Text('Status ändern',
                        style: Theme.of(context)
                            .textTheme
                            .titleMedium
                            ?.copyWith(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final s in _statusFlow)
                          if (s != 'converted')
                            ChoiceChip(
                              label: Text(leadStatusLabel(s)),
                              selected: asStr(l['status']) == s,
                              selectedColor:
                                  statusColor(s).withValues(alpha: 0.20),
                              onSelected:
                                  isConverted ? null : (_) => _setStatus(s),
                            ),
                      ],
                    ),
                  ],
                ),
              ),
      ),
    );
  }

  Widget _row(IconData icon, String value) {
    if (value.trim().isEmpty) return const SizedBox.shrink();
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(children: [
        Icon(icon, size: 16, color: cs.onSurfaceVariant),
        const SizedBox(width: 8),
        Expanded(child: Text(value, style: const TextStyle(fontSize: 13))),
      ]),
    );
  }
}

/* ═══════════════════════ Lead form ═══════════════════════ */

class _LeadFormSheet extends StatefulWidget {
  final Map<String, dynamic>? existing;
  const _LeadFormSheet({this.existing});
  @override
  State<_LeadFormSheet> createState() => _LeadFormSheetState();
}

class _LeadFormSheetState extends State<_LeadFormSheet> {
  final _api = ApiService();
  late final TextEditingController _firstName;
  late final TextEditingController _lastName;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  late final TextEditingController _dogName;
  late final TextEditingController _interest;
  late final TextEditingController _source;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _firstName = TextEditingController(text: asStr(e?['first_name']));
    _lastName = TextEditingController(text: asStr(e?['last_name']));
    _email = TextEditingController(text: asStr(e?['email']));
    _phone = TextEditingController(text: asStr(e?['phone']));
    _dogName = TextEditingController(text: asStr(e?['dog_name']));
    _interest = TextEditingController(text: asStr(e?['interest']));
    _source = TextEditingController(text: asStr(e?['source']));
  }

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _email.dispose();
    _phone.dispose();
    _dogName.dispose();
    _interest.dispose();
    _source.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_firstName.text.trim().isEmpty && _lastName.text.trim().isEmpty) {
      showDsSnack(context, 'Bitte mindestens einen Namen eingeben.',
          error: true);
      return;
    }
    setState(() => _saving = true);
    final payload = {
      'first_name': _firstName.text.trim(),
      'last_name': _lastName.text.trim(),
      'email': _email.text.trim(),
      'phone': _phone.text.trim(),
      'dog_name': _dogName.text.trim(),
      'interest': _interest.text.trim(),
      'source': _source.text.trim(),
    };
    try {
      if (widget.existing != null) {
        await _api.dogschoolLeadUpdate(asInt(widget.existing!['id']), payload);
      } else {
        await _api.dogschoolLeadCreate(payload);
      }
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => _saving = false);
      if (mounted) showDsSnack(context, e.toString(), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom + 24),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.only(bottom: 16),
              decoration: BoxDecoration(
                  color: Colors.grey.shade300,
                  borderRadius: BorderRadius.circular(2)),
            ),
            Text(
                widget.existing != null
                    ? 'Interessent bearbeiten'
                    : 'Neuer Interessent',
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 16),
            Row(children: [
              Expanded(
                child: TextField(
                  controller: _firstName,
                  decoration: const InputDecoration(
                      labelText: 'Vorname',
                      prefixIcon: Icon(Icons.person_rounded)),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextField(
                  controller: _lastName,
                  decoration:
                      const InputDecoration(labelText: 'Nachname'),
                ),
              ),
            ]),
            const SizedBox(height: 10),
            TextField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(
                  labelText: 'E-Mail', prefixIcon: Icon(Icons.email_rounded)),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                  labelText: 'Telefon', prefixIcon: Icon(Icons.phone_rounded)),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _dogName,
              decoration: const InputDecoration(
                  labelText: 'Hundename', prefixIcon: Icon(Icons.pets_rounded)),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _interest,
              decoration: const InputDecoration(
                  labelText: 'Interesse (z.B. Welpenkurs)',
                  prefixIcon: Icon(Icons.star_rounded)),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _source,
              decoration: const InputDecoration(
                  labelText: 'Quelle (z.B. Instagram)',
                  prefixIcon: Icon(Icons.campaign_rounded)),
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                icon: _saving
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.check_rounded),
                label: Text(_saving ? 'Speichern…' : 'Speichern'),
                onPressed: _saving ? null : _save,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
