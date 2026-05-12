import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/terminology.dart';
import '../../core/theme.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../invoices/invoice_form_screen.dart';
import '../../widgets/search_bar_widget.dart';
import '../../widgets/paw_avatar.dart';
import '../../widgets/shimmer_list.dart';

class PatientsScreen extends StatefulWidget {
  const PatientsScreen({super.key});

  @override
  State<PatientsScreen> createState() => _PatientsScreenState();
}

class _PatientsScreenState extends State<PatientsScreen> {
  final _api = ApiService();
  List<dynamic> _items = [];
  bool _loading = true;
  String? _error;
  String _search = '';
  int _page = 1;
  bool _hasNext = false;
  List<Map<String, dynamic>> _treatmentTypes = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool reset = true}) async {
    if (reset) {
      _page = 1;
      _items = [];
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _api.patients(page: _page, search: _search);
      final items = List<dynamic>.from(data['items'] as List? ?? []);
      setState(() {
        _items = reset ? items : [..._items, ...items];
        _hasNext = data['has_next'] as bool? ?? false;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  void _onSearch(String q) {
    _search = q;
    _load();
  }

  Future<void> _loadMore() async {
    _page++;
    await _load(reset: false);
  }

  Future<void> _ensureTreatmentTypes() async {
    if (_treatmentTypes.isNotEmpty) return;
    try {
      final list = await _api.treatmentTypes();
      if (!mounted) return;
      setState(() {
        _treatmentTypes =
            list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
      });
    } catch (_) {}
  }

  int? _toInt(dynamic value) => int.tryParse(value?.toString() ?? '');

  String _ownerName(Map<String, dynamic> patient) => (patient['owner_name']
              as String? ??
          '${patient['owner_first_name'] ?? ''} ${patient['owner_last_name'] ?? ''}')
      .trim();

  String _ownerLastName(Map<String, dynamic> patient) {
    final direct = (patient['owner_last_name'] as String? ?? '').trim();
    if (direct.isNotEmpty) return direct;
    final owner = _ownerName(patient);
    if (owner.isEmpty) return '';
    final parts = owner.split(RegExp(r'\s+'));
    return parts.last;
  }

  Future<void> _callOwner(Map<String, dynamic> patient) async {
    final phone = (patient['owner_phone'] as String? ?? '').trim();
    if (phone.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Keine Telefonnummer hinterlegt.')),
      );
      return;
    }
    await launchUrl(Uri(scheme: 'tel', path: phone));
  }

  Future<void> _showInvoiceSheet(Map<String, dynamic> patient) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (_) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.94,
        minChildSize: 0.72,
        maxChildSize: 0.98,
        builder: (_, __) => ClipRRect(
          borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
          child: InvoiceFormScreen(
            prefill: {
              'patientId': patient['id'],
              'patientName': patient['name'],
              'ownerId': patient['owner_id'],
              'ownerName': _ownerName(patient),
            },
          ),
        ),
      ),
    );
  }

  Future<void> _showMessageSheet(Map<String, dynamic> patient) async {
    final ownerId = _toInt(patient['owner_id']);
    if (ownerId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Kein Tierhalter zugeordnet.')),
      );
      return;
    }
    final subjectCtrl = TextEditingController(
        text: 'Rückfrage zu ${patient['name'] ?? 'Patient'}');
    final bodyCtrl = TextEditingController();
    var saving = false;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setS) => Padding(
          padding: EdgeInsets.fromLTRB(
            20,
            10,
            20,
            MediaQuery.of(ctx).viewInsets.bottom + 24,
          ),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              width: 42,
              height: 4,
              decoration: BoxDecoration(
                color: Theme.of(ctx).colorScheme.outlineVariant,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 16),
            Text('Nachricht an ${_ownerName(patient)}',
                style: Theme.of(ctx)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 16),
            TextField(
              controller: subjectCtrl,
              decoration: const InputDecoration(
                labelText: 'Betreff',
                prefixIcon: Icon(Icons.subject_rounded),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: bodyCtrl,
              maxLines: 4,
              decoration: const InputDecoration(
                labelText: 'Nachricht',
                alignLabelWithHint: true,
                prefixIcon: Padding(
                  padding: EdgeInsets.only(bottom: 56),
                  child: Icon(Icons.message_rounded),
                ),
              ),
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: saving
                  ? null
                  : () async {
                      if (subjectCtrl.text.trim().isEmpty ||
                          bodyCtrl.text.trim().isEmpty) {
                        ScaffoldMessenger.of(ctx).showSnackBar(
                          const SnackBar(
                              content: Text(
                                  'Bitte Betreff und Nachricht ausfüllen.')),
                        );
                        return;
                      }
                      setS(() => saving = true);
                      try {
                        await _api.messageCreate(
                          ownerId: ownerId,
                          subject: subjectCtrl.text.trim(),
                          body: bodyCtrl.text.trim(),
                        );
                        if (ctx.mounted) Navigator.pop(ctx);
                        if (mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                                content: Text('Nachricht gesendet.')),
                          );
                        }
                      } catch (e) {
                        setS(() => saving = false);
                        if (ctx.mounted) {
                          ScaffoldMessenger.of(ctx).showSnackBar(
                              SnackBar(content: Text(e.toString())));
                        }
                      }
                    },
              icon: saving
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.send_rounded),
              label: Text(saving ? 'Senden...' : 'Senden'),
            ),
          ]),
        ),
      ),
    );
    subjectCtrl.dispose();
    bodyCtrl.dispose();
  }

  Future<void> _showAppointmentSheet(Map<String, dynamic> patient) async {
    await _ensureTreatmentTypes();
    final titleCtrl = TextEditingController();
    DateTime selDate = DateTime.now().add(const Duration(days: 1));
    TimeOfDay selTime = const TimeOfDay(hour: 10, minute: 0);
    int durationMinutes = 60;
    int? treatmentTypeId;
    var titleEdited = false;

    String buildTitle() {
      final treatment = treatmentTypeId == null
          ? ''
          : (_treatmentTypes
                  .where(
                      (t) => t['id']?.toString() == treatmentTypeId.toString())
                  .map((t) => t['name'] as String? ?? '')
                  .firstOrNull) ??
              '';
      return [
        _ownerLastName(patient),
        patient['name'] as String? ?? '',
        treatment
      ].where((part) => part.isNotEmpty).join(' - ');
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setS) => Padding(
          padding: EdgeInsets.fromLTRB(
            20,
            10,
            20,
            MediaQuery.of(ctx).viewInsets.bottom + 24,
          ),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              width: 42,
              height: 4,
              decoration: BoxDecoration(
                color: Theme.of(ctx).colorScheme.outlineVariant,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 16),
            Text('Termin für ${patient['name'] ?? ''}',
                style: Theme.of(ctx)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 16),
            TextField(
              controller: titleCtrl,
              decoration: InputDecoration(
                labelText: 'Titel *',
                prefixIcon: const Icon(Icons.title_rounded),
                hintText: buildTitle(),
              ),
              onChanged: (_) => titleEdited = true,
            ),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(
                child: InkWell(
                  onTap: () async {
                    final d = await showDatePicker(
                      context: ctx,
                      initialDate: selDate,
                      firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 365)),
                    );
                    if (d != null) setS(() => selDate = d);
                  },
                  child: InputDecorator(
                    decoration: const InputDecoration(
                      labelText: 'Datum',
                      prefixIcon: Icon(Icons.calendar_today_rounded),
                    ),
                    child: Text(DateFormat('dd.MM.yyyy').format(selDate)),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: InkWell(
                  onTap: () async {
                    final t = await showTimePicker(
                        context: ctx, initialTime: selTime);
                    if (t != null) setS(() => selTime = t);
                  },
                  child: InputDecorator(
                    decoration: const InputDecoration(
                      labelText: 'Uhrzeit',
                      prefixIcon: Icon(Icons.access_time_rounded),
                    ),
                    child: Text(
                        '${selTime.hour.toString().padLeft(2, '0')}:${selTime.minute.toString().padLeft(2, '0')}'),
                  ),
                ),
              ),
            ]),
            const SizedBox(height: 12),
            if (_treatmentTypes.isNotEmpty)
              DropdownButtonFormField<int>(
                initialValue: treatmentTypeId,
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'Behandlung / Trainingseinheit',
                  prefixIcon: Icon(Icons.category_rounded),
                ),
                items: [
                  const DropdownMenuItem<int>(
                    value: null,
                    child: Text('Keine Behandlungsart'),
                  ),
                  ..._treatmentTypes.map((t) => DropdownMenuItem<int>(
                        value: int.tryParse(t['id'].toString()),
                        child: Text(t['name'] as String? ?? '',
                            overflow: TextOverflow.ellipsis),
                      )),
                ],
                onChanged: (v) {
                  treatmentTypeId = v;
                  if (!titleEdited) setS(() => titleCtrl.text = buildTitle());
                },
              ),
            if (_treatmentTypes.isNotEmpty) const SizedBox(height: 12),
            DropdownButtonFormField<int>(
              initialValue: durationMinutes,
              decoration: const InputDecoration(
                labelText: 'Dauer',
                prefixIcon: Icon(Icons.timer_rounded),
              ),
              items: const [
                DropdownMenuItem(value: 30, child: Text('30 Minuten')),
                DropdownMenuItem(value: 45, child: Text('45 Minuten')),
                DropdownMenuItem(value: 60, child: Text('60 Minuten')),
                DropdownMenuItem(value: 90, child: Text('90 Minuten')),
              ],
              onChanged: (v) => setS(() => durationMinutes = v ?? 60),
            ),
            const SizedBox(height: 18),
            FilledButton.icon(
              icon: const Icon(Icons.save_rounded),
              label: const Text('Termin erstellen'),
              onPressed: () async {
                final title = titleCtrl.text.trim().isEmpty
                    ? buildTitle()
                    : titleCtrl.text.trim();
                if (title.isEmpty) return;
                final start = DateTime(selDate.year, selDate.month, selDate.day,
                    selTime.hour, selTime.minute);
                final end = start.add(Duration(minutes: durationMinutes));
                Navigator.pop(ctx);
                try {
                  await _api.appointmentCreate({
                    'title': title,
                    'patient_id': patient['id'],
                    'owner_id': patient['owner_id'],
                    if (treatmentTypeId != null)
                      'treatment_type_id': treatmentTypeId,
                    'start_at': start
                        .toIso8601String()
                        .substring(0, 16)
                        .replaceAll('T', ' '),
                    'end_at': end
                        .toIso8601String()
                        .substring(0, 16)
                        .replaceAll('T', ' '),
                    'status': 'scheduled',
                  });
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Termin erstellt.')),
                    );
                  }
                } catch (e) {
                  if (mounted) {
                    ScaffoldMessenger.of(context)
                        .showSnackBar(SnackBar(content: Text(e.toString())));
                  }
                }
              },
            ),
          ]),
        ),
      ),
    );
    titleCtrl.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = Terminology(isTrainer: context.watch<AuthService>().isTrainer);
    return Scaffold(
      appBar: AppBar(
        title: Text(t.patientPlural),
        actions: [
          IconButton(
            icon: const Icon(Icons.add_rounded),
            onPressed: () =>
                context.push('/patienten/neu').then((_) => _load()),
          ),
        ],
      ),
      body: Column(children: [
        AppSearchBar(onSearch: _onSearch, hint: '${t.patientSingular} suchen…'),
        Expanded(child: _buildList()),
      ]),
    );
  }

  Widget _buildList() {
    final t = Terminology(isTrainer: context.watch<AuthService>().isTrainer);
    if (_loading && _items.isEmpty) return const ShimmerList();
    if (_error != null && _items.isEmpty) {
      return Center(
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.error_outline_rounded, size: 48, color: Colors.red.shade300),
        const SizedBox(height: 12),
        Text(_error!, textAlign: TextAlign.center),
        const SizedBox(height: 16),
        FilledButton.icon(
            onPressed: _load,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Erneut versuchen')),
      ]));
    }
    if (_items.isEmpty)
      return Center(
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.pets_rounded, size: 56, color: Colors.grey.shade300),
        const SizedBox(height: 12),
        Text(t.patientsFoundEmpty(),
            style: Theme.of(context).textTheme.bodyLarge),
      ]));

    final isTablet = MediaQuery.of(context).size.width >= 600;

    return RefreshIndicator(
      onRefresh: () => _load(),
      child: isTablet ? _buildGrid() : _buildListView(),
    );
  }

  Widget _buildListView() {
    return ListView.builder(
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemCount: _items.length + (_hasNext ? 1 : 0),
      itemBuilder: (ctx, i) {
        if (i == _items.length) {
          return Padding(
            padding: const EdgeInsets.all(16),
            child: FilledButton.tonal(
                onPressed: _loadMore, child: const Text('Mehr laden')),
          );
        }
        return _PatientTile(
            patient: _items[i] as Map<String, dynamic>,
            onTap: () => context
                .push('/patienten/${_items[i]['id']}')
                .then((_) => _load()),
            onCall: () => _callOwner(_items[i] as Map<String, dynamic>),
            onMessage: () =>
                _showMessageSheet(_items[i] as Map<String, dynamic>),
            onAppointment: () =>
                _showAppointmentSheet(_items[i] as Map<String, dynamic>),
            onInvoice: () =>
                _showInvoiceSheet(_items[i] as Map<String, dynamic>));
      },
    );
  }

  Widget _buildGrid() {
    return GridView.builder(
      padding: const EdgeInsets.all(16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 1.95,
      ),
      itemCount: _items.length + (_hasNext ? 1 : 0),
      itemBuilder: (ctx, i) {
        if (i == _items.length) {
          return FilledButton.tonal(
              onPressed: _loadMore, child: const Text('Mehr laden'));
        }
        return _PatientTile(
            patient: _items[i] as Map<String, dynamic>,
            onTap: () => context
                .push('/patienten/${_items[i]['id']}')
                .then((_) => _load()),
            onCall: () => _callOwner(_items[i] as Map<String, dynamic>),
            onMessage: () =>
                _showMessageSheet(_items[i] as Map<String, dynamic>),
            onAppointment: () =>
                _showAppointmentSheet(_items[i] as Map<String, dynamic>),
            onInvoice: () =>
                _showInvoiceSheet(_items[i] as Map<String, dynamic>));
      },
    );
  }
}

class _PatientTile extends StatelessWidget {
  final Map<String, dynamic> patient;
  final VoidCallback onTap;
  final VoidCallback onCall;
  final VoidCallback onMessage;
  final VoidCallback onAppointment;
  final VoidCallback onInvoice;
  const _PatientTile({
    required this.patient,
    required this.onTap,
    required this.onCall,
    required this.onMessage,
    required this.onAppointment,
    required this.onInvoice,
  });

  static const _avatarColors = [
    Color(0xFF5B8AF0),
    Color(0xFF8B5CF6),
    Color(0xFF06B6D4),
    Color(0xFF10B981),
    Color(0xFFF59E0B),
    Color(0xFFEF4444),
  ];

  Color _avatarColor(String name) => _avatarColors[
      name.isNotEmpty ? name.codeUnitAt(0) % _avatarColors.length : 0];

  String _speciesEmoji(String s) {
    final l = s.toLowerCase();
    if (l.contains('hund') || l.contains('dog')) return '🐕';
    if (l.contains('katze') || l.contains('cat')) return '🐈';
    if (l.contains('pferd') || l.contains('horse')) return '🐴';
    if (l.contains('vogel') || l.contains('bird')) return '🦜';
    return '🐾';
  }

  @override
  Widget build(BuildContext context) {
    final p = patient;
    final name = p['name'] as String? ?? '';
    final species = p['species'] as String? ?? '';
    final breed = p['breed'] as String? ?? '';
    final owner = p['owner_name'] as String? ?? '';
    final status = p['status'] as String? ?? 'active';
    final sub = [species, breed].where((s) => s.isNotEmpty).join(' · ');
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final color = _avatarColor(name);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: Material(
        color: isDark ? const Color(0xFF1A1D27) : Colors.white,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: isDark
                    ? Colors.white.withValues(alpha: 0.06)
                    : Colors.black.withValues(alpha: 0.06),
              ),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: Column(children: [
              Row(children: [
                /* Avatar */
                Stack(children: [
                  p['photo_url'] != null &&
                          (p['photo_url'] as String).isNotEmpty
                      ? PawAvatar(
                          photoPath: p['photo_url'] as String,
                          species: species,
                          name: name,
                          radius: 26)
                      : Container(
                          width: 52,
                          height: 52,
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              colors: [color, color.withValues(alpha: 0.7)],
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                            ),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Center(
                            child: Text(
                              species.isNotEmpty
                                  ? _speciesEmoji(species)
                                  : (name.isNotEmpty
                                      ? name[0].toUpperCase()
                                      : '?'),
                              style: const TextStyle(fontSize: 22),
                            ),
                          ),
                        ),
                  /* Status dot */
                  Positioned(
                    right: 0,
                    bottom: 0,
                    child: Container(
                      width: 12,
                      height: 12,
                      decoration: BoxDecoration(
                        color: status == 'active'
                            ? const Color(0xFF10B981)
                            : status == 'inactive'
                                ? const Color(0xFF6B7280)
                                : const Color(0xFFF59E0B),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color:
                              isDark ? const Color(0xFF1A1D27) : Colors.white,
                          width: 2,
                        ),
                      ),
                    ),
                  ),
                ]),
                const SizedBox(width: 14),
                /* Info */
                Expanded(
                    child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name,
                        style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 15,
                            height: 1.2),
                        overflow: TextOverflow.ellipsis),
                    if (sub.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(sub,
                          style: TextStyle(
                              fontSize: 12,
                              color: Theme.of(context)
                                  .colorScheme
                                  .onSurfaceVariant),
                          overflow: TextOverflow.ellipsis),
                    ],
                    if (owner.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Row(children: [
                        Icon(Icons.person_outline_rounded,
                            size: 11,
                            color:
                                Theme.of(context).colorScheme.onSurfaceVariant),
                        const SizedBox(width: 3),
                        Expanded(
                            child: Text(owner,
                                style: TextStyle(
                                    fontSize: 11,
                                    color: Theme.of(context)
                                        .colorScheme
                                        .onSurfaceVariant),
                                overflow: TextOverflow.ellipsis)),
                      ]),
                    ],
                  ],
                )),
                /* Arrow */
                Icon(Icons.chevron_right_rounded,
                    size: 20,
                    color: Theme.of(context)
                        .colorScheme
                        .onSurfaceVariant
                        .withValues(alpha: 0.4)),
              ]),
              const SizedBox(height: 12),
              Row(children: [
                _QuickAction(
                    Icons.call_rounded, 'Anrufen', AppTheme.warning, onCall),
                _QuickAction(Icons.chat_rounded, 'Nachricht', AppTheme.tertiary,
                    onMessage),
                _QuickAction(Icons.event_rounded, 'Termin', AppTheme.primary,
                    onAppointment),
                _QuickAction(Icons.receipt_long_rounded, 'Rechnung',
                    AppTheme.success, onInvoice),
              ]),
            ]),
          ),
        ),
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _QuickAction(this.icon, this.label, this.color, this.onTap);

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 2),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 7),
            child: Column(children: [
              Icon(icon, color: color, size: 18),
              const SizedBox(height: 3),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: color,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ]),
          ),
        ),
      ),
    );
  }
}
