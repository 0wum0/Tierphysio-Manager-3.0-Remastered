import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';
import 'dogschool_common.dart';

class KurseScreen extends StatefulWidget {
  const KurseScreen({super.key});
  @override
  State<KurseScreen> createState() => _KurseScreenState();
}

class _KurseScreenState extends State<KurseScreen> {
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
      final data = await _api.dogschoolCourses(status: _statusFilter, search: _search);
      final items = (data['items'] as List? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      setState(() {
        _items = items;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _openCreate() async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _CourseFormSheet(),
    );
    if (created == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Kurse'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _openCreate,
        icon: const Icon(Icons.add_rounded),
        label: const Text('Neuer Kurs'),
      ),
      body: Column(
        children: [
          _FilterBar(
            selected: _statusFilter,
            onSelected: (s) {
              setState(() => _statusFilter = s);
              _load();
            },
            onSearch: (q) {
              _search = q;
              _load();
            },
          ),
          Expanded(
            child: AsyncBody(
              loading: _loading,
              error: _error,
              empty: _items.isEmpty,
              emptyTitle: 'Keine Kurse',
              emptyHint: 'Tippe auf „Neuer Kurs", um einen Kurs anzulegen.',
              emptyIcon: Icons.school_outlined,
              onRetry: _load,
              child: RefreshIndicator(
                onRefresh: _load,
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
                  itemCount: _items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (ctx, i) => _CourseCard(
                    course: _items[i],
                    onTap: () async {
                      await Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => CourseDetailScreen(
                              courseId: asInt(_items[i]['id'])),
                        ),
                      );
                      _load();
                    },
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterBar extends StatelessWidget {
  final String selected;
  final ValueChanged<String> onSelected;
  final ValueChanged<String> onSearch;
  const _FilterBar({
    required this.selected,
    required this.onSelected,
    required this.onSearch,
  });

  @override
  Widget build(BuildContext context) {
    const options = {
      '': 'Alle',
      'active': 'Aktiv',
      'draft': 'Entwurf',
      'full': 'Ausgebucht',
      'completed': 'Abgeschlossen',
    };
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: Column(
        children: [
          TextField(
            decoration: InputDecoration(
              hintText: 'Kurs suchen…',
              prefixIcon: const Icon(Icons.search_rounded),
              isDense: true,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            onSubmitted: onSearch,
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 36,
            child: ListView(
              scrollDirection: Axis.horizontal,
              children: [
                for (final e in options.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(e.value),
                      selected: selected == e.key,
                      onSelected: (_) => onSelected(e.key),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CourseCard extends StatelessWidget {
  final Map<String, dynamic> course;
  final VoidCallback onTap;
  const _CourseCard({required this.course, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final status = asStr(course['status']);
    final enrolled = asInt(course['enrolled_count']);
    final max = asInt(course['max_participants']);
    final cs = Theme.of(context).colorScheme;
    return DsCard(
      onTap: onTap,
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: AppTheme.tertiary.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            alignment: Alignment.center,
            child: const Icon(Icons.school_rounded, color: AppTheme.tertiary),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(asStr(course['name']),
                    style: const TextStyle(
                        fontWeight: FontWeight.w700, fontSize: 14)),
                const SizedBox(height: 3),
                Wrap(
                  spacing: 6,
                  runSpacing: 4,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    StatusChip(
                        label: courseStatusLabel(status),
                        color: statusColor(status)),
                    Text(courseTypeLabel(asStr(course['type'])),
                        style: TextStyle(
                            fontSize: 12, color: cs.onSurfaceVariant)),
                    Row(mainAxisSize: MainAxisSize.min, children: [
                      Icon(Icons.group_rounded,
                          size: 13, color: cs.onSurfaceVariant),
                      const SizedBox(width: 3),
                      Text('$enrolled/$max',
                          style: TextStyle(
                              fontSize: 12, color: cs.onSurfaceVariant)),
                    ]),
                  ],
                ),
              ],
            ),
          ),
          Icon(Icons.chevron_right_rounded, color: cs.onSurfaceVariant),
        ],
      ),
    );
  }
}

/* ═══════════════════════ Course create/edit sheet ═══════════════════════ */

class _CourseFormSheet extends StatefulWidget {
  final Map<String, dynamic>? existing;
  const _CourseFormSheet({this.existing});
  @override
  State<_CourseFormSheet> createState() => _CourseFormSheetState();
}

class _CourseFormSheetState extends State<_CourseFormSheet> {
  final _api = ApiService();
  late final TextEditingController _name;
  late final TextEditingController _location;
  late final TextEditingController _price;
  late final TextEditingController _maxParticipants;
  late final TextEditingController _numSessions;
  final _promptCtrl = TextEditingController();
  String _type = 'group';
  String _status = 'draft';
  bool _saving = false;
  bool _aiMode = false;
  bool _aiLoading = false;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _name = TextEditingController(text: asStr(e?['name']));
    _location = TextEditingController(text: asStr(e?['location']));
    _price = TextEditingController(
        text: e != null ? (asInt(e['price_cents']) / 100).toStringAsFixed(2) : '');
    _maxParticipants =
        TextEditingController(text: e != null ? '${asInt(e['max_participants'])}' : '8');
    _numSessions =
        TextEditingController(text: e != null ? '${asInt(e['num_sessions'])}' : '1');
    _type = asStr(e?['type']).isEmpty ? 'group' : asStr(e?['type']);
    _status = asStr(e?['status']).isEmpty ? 'draft' : asStr(e?['status']);
  }

  @override
  void dispose() {
    _name.dispose();
    _location.dispose();
    _price.dispose();
    _maxParticipants.dispose();
    _numSessions.dispose();
    _promptCtrl.dispose();
    super.dispose();
  }

  Future<void> _aiGenerate() async {
    if (_promptCtrl.text.trim().isEmpty) return;
    setState(() => _aiLoading = true);
    try {
      final result = await _api.aiGenerate('kurs', _promptCtrl.text.trim());
      setState(() {
        _aiLoading = false;
        _aiMode = false;
        _name.text = result['name']?.toString() ?? '';
        _location.text = result['location']?.toString() ?? '';
        _type = result['type']?.toString() ?? 'group';
        _maxParticipants.text = result['max_participants']?.toString() ?? '8';
        _numSessions.text = result['num_sessions']?.toString() ?? '1';
        if (result['price_cents'] != null) {
          final cents = int.tryParse(result['price_cents'].toString()) ?? 0;
          _price.text = (cents / 100).toStringAsFixed(2);
        }
      });
    } catch (e) {
      setState(() => _aiLoading = false);
      if (mounted) showDsSnack(context, e.toString(), error: true);
    }
  }

  Future<void> _save() async {
    if (_name.text.trim().isEmpty) {
      showDsSnack(context, 'Bitte einen Kursnamen eingeben.', error: true);
      return;
    }
    setState(() => _saving = true);
    final priceCents =
        ((double.tryParse(_price.text.replaceAll(',', '.')) ?? 0) * 100).round();
    final payload = {
      'name': _name.text.trim(),
      'type': _type,
      'status': _status,
      'location': _location.text.trim(),
      'price_cents': priceCents,
      'max_participants': int.tryParse(_maxParticipants.text) ?? 8,
      'num_sessions': int.tryParse(_numSessions.text) ?? 1,
    };
    try {
      if (widget.existing != null) {
        await _api.dogschoolCourseUpdate(asInt(widget.existing!['id']), payload);
      } else {
        await _api.dogschoolCourseCreate(payload);
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
            Row(children: [
              Expanded(
                child: Text(
                    widget.existing != null ? 'Kurs bearbeiten' : 'Neuer Kurs',
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.w700)),
              ),
              if (widget.existing == null)
                TextButton.icon(
                  icon: Icon(
                      _aiMode ? Icons.edit_rounded : Icons.auto_awesome_rounded,
                      size: 16),
                  label: Text(_aiMode ? 'Manuell' : 'Mit KI'),
                  onPressed: () => setState(() => _aiMode = !_aiMode),
                ),
            ]),
            const SizedBox(height: 16),
            if (_aiMode) ...[
              TextField(
                controller: _promptCtrl,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'KI-Prompt *',
                  hintText: 'z.B. "Welpenkurs, 6 Einheiten, max. 8 Teilnehmer, 89€"',
                  prefixIcon: Icon(Icons.auto_awesome_rounded),
                ),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  icon: _aiLoading
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.auto_awesome_rounded),
                  label: Text(_aiLoading ? 'KI generiert…' : 'Mit KI ausfüllen'),
                  onPressed: _aiLoading ? null : _aiGenerate,
                ),
              ),
            ] else ...[
              TextField(
                controller: _name,
                decoration: const InputDecoration(
                    labelText: 'Kursname *',
                    prefixIcon: Icon(Icons.title_rounded)),
              ),
              const SizedBox(height: 10),
              DropdownButtonFormField<String>(
                initialValue: _type,
                isExpanded: true,
                decoration: const InputDecoration(
                    labelText: 'Kurstyp',
                    prefixIcon: Icon(Icons.category_rounded)),
                items: [
                  for (final k in courseTypeKeys)
                    DropdownMenuItem(value: k, child: Text(courseTypeLabel(k))),
                ],
                onChanged: (v) => setState(() => _type = v ?? 'group'),
              ),
              const SizedBox(height: 10),
              DropdownButtonFormField<String>(
                initialValue: _status,
                isExpanded: true,
                decoration: const InputDecoration(
                    labelText: 'Status',
                    prefixIcon: Icon(Icons.flag_rounded)),
                items: [
                  for (final k in [
                    'draft',
                    'active',
                    'full',
                    'paused',
                    'completed',
                    'cancelled'
                  ])
                    DropdownMenuItem(value: k, child: Text(courseStatusLabel(k))),
                ],
                onChanged: (v) => setState(() => _status = v ?? 'draft'),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _location,
                decoration: const InputDecoration(
                    labelText: 'Ort',
                    prefixIcon: Icon(Icons.place_rounded)),
              ),
              const SizedBox(height: 10),
              Row(children: [
                Expanded(
                  child: TextField(
                    controller: _maxParticipants,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                        labelText: 'Max. Teilnehmer',
                        prefixIcon: Icon(Icons.group_rounded)),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    controller: _numSessions,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                        labelText: 'Einheiten',
                        prefixIcon: Icon(Icons.repeat_rounded)),
                  ),
                ),
              ]),
              const SizedBox(height: 10),
              TextField(
                controller: _price,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(
                    labelText: 'Preis (€)',
                    prefixIcon: Icon(Icons.euro_rounded)),
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
          ],
        ),
      ),
    );
  }
}

/* ═══════════════════════ Course detail ═══════════════════════ */

class CourseDetailScreen extends StatefulWidget {
  final int courseId;
  const CourseDetailScreen({super.key, required this.courseId});
  @override
  State<CourseDetailScreen> createState() => _CourseDetailScreenState();
}

class _CourseDetailScreenState extends State<CourseDetailScreen> {
  final _api = ApiService();
  Map<String, dynamic>? _course;
  bool _loading = true;
  String? _error;

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
      final data = await _api.dogschoolCourse(widget.courseId);
      setState(() {
        _course = data;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _edit() async {
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _CourseFormSheet(existing: _course),
    );
    if (ok == true) _load();
  }

  Future<void> _enroll() async {
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _EnrollSheet(courseId: widget.courseId),
    );
    if (ok == true) _load();
  }

  Future<void> _setEnrollmentStatus(int enrollmentId, String status) async {
    try {
      await _api.dogschoolEnrollmentStatus(enrollmentId, status);
      _load();
    } catch (e) {
      if (mounted) showDsSnack(context, e.toString(), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final course = _course;
    final enrollments = (course?['enrollments'] as List? ?? [])
        .map((e) => Map<String, dynamic>.from(e as Map))
        .toList();
    final sessions = (course?['sessions'] as List? ?? [])
        .map((e) => Map<String, dynamic>.from(e as Map))
        .toList();
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: Text(course != null ? asStr(course['name']) : 'Kurs'),
        actions: [
          if (course != null)
            IconButton(icon: const Icon(Icons.edit_rounded), onPressed: _edit),
        ],
      ),
      floatingActionButton: course == null
          ? null
          : FloatingActionButton.extended(
              onPressed: _enroll,
              icon: const Icon(Icons.person_add_alt_1_rounded),
              label: const Text('Einschreiben'),
            ),
      body: AsyncBody(
        loading: _loading,
        error: _error,
        empty: false,
        emptyTitle: '',
        emptyHint: '',
        emptyIcon: Icons.school_outlined,
        onRetry: _load,
        child: course == null
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
                                label: courseStatusLabel(asStr(course['status'])),
                                color: statusColor(asStr(course['status']))),
                            const SizedBox(width: 8),
                            Text(courseTypeLabel(asStr(course['type'])),
                                style: TextStyle(color: cs.onSurfaceVariant)),
                          ]),
                          const SizedBox(height: 10),
                          _infoRow(Icons.place_rounded, 'Ort',
                              asStr(course['location']).isEmpty ? '—' : asStr(course['location'])),
                          _infoRow(Icons.euro_rounded, 'Preis',
                              euroFromCents(course['price_cents'])),
                          _infoRow(Icons.group_rounded, 'Teilnehmer',
                              '${enrollments.where((e) => asStr(e['status']) == 'active').length}/${asInt(course['max_participants'])}'),
                          _infoRow(Icons.repeat_rounded, 'Einheiten',
                              '${asInt(course['num_sessions'])}'),
                          if (asStr(course['trainer_name']).isNotEmpty)
                            _infoRow(Icons.badge_rounded, 'Trainer',
                                asStr(course['trainer_name'])),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    Text('Teilnehmer (${enrollments.length})',
                        style: Theme.of(context)
                            .textTheme
                            .titleMedium
                            ?.copyWith(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 8),
                    if (enrollments.isEmpty)
                      Text('Noch keine Einschreibungen.',
                          style: TextStyle(color: cs.onSurfaceVariant))
                    else
                      ...enrollments.map((e) => Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: DsCard(
                              child: Row(children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(asStr(e['patient_name']),
                                          style: const TextStyle(
                                              fontWeight: FontWeight.w700)),
                                      Text(
                                        '${asStr(e['owner_first_name'])} ${asStr(e['owner_last_name'])}'.trim(),
                                        style: TextStyle(
                                            fontSize: 12,
                                            color: cs.onSurfaceVariant),
                                      ),
                                    ],
                                  ),
                                ),
                                StatusChip(
                                    label: asStr(e['status']),
                                    color: statusColor(asStr(e['status']))),
                                PopupMenuButton<String>(
                                  onSelected: (s) =>
                                      _setEnrollmentStatus(asInt(e['id']), s),
                                  itemBuilder: (_) => const [
                                    PopupMenuItem(value: 'active', child: Text('Aktiv')),
                                    PopupMenuItem(value: 'completed', child: Text('Abgeschlossen')),
                                    PopupMenuItem(value: 'cancelled', child: Text('Storniert')),
                                    PopupMenuItem(value: 'no_show', child: Text('Nicht erschienen')),
                                  ],
                                ),
                              ]),
                            ),
                          )),
                    const SizedBox(height: 20),
                    Text('Termine (${sessions.length})',
                        style: Theme.of(context)
                            .textTheme
                            .titleMedium
                            ?.copyWith(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 8),
                    if (sessions.isEmpty)
                      Text('Noch keine Termine angelegt.',
                          style: TextStyle(color: cs.onSurfaceVariant))
                    else
                      ...sessions.map((s) => Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: DsCard(
                              child: Row(children: [
                                Icon(Icons.event_rounded,
                                    color: statusColor(asStr(s['status']))),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                          'Einheit ${asInt(s['session_number'])} · ${asStr(s['session_date'])}',
                                          style: const TextStyle(
                                              fontWeight: FontWeight.w600)),
                                      if (asStr(s['topic']).isNotEmpty)
                                        Text(asStr(s['topic']),
                                            style: TextStyle(
                                                fontSize: 12,
                                                color: cs.onSurfaceVariant)),
                                    ],
                                  ),
                                ),
                                StatusChip(
                                    label: asStr(s['status']),
                                    color: statusColor(asStr(s['status']))),
                              ]),
                            ),
                          )),
                  ],
                ),
              ),
      ),
    );
  }

  Widget _infoRow(IconData icon, String label, String value) {
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(children: [
        Icon(icon, size: 16, color: cs.onSurfaceVariant),
        const SizedBox(width: 8),
        Text('$label: ',
            style: TextStyle(fontSize: 13, color: cs.onSurfaceVariant)),
        Expanded(
          child: Text(value,
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
        ),
      ]),
    );
  }
}

class _EnrollSheet extends StatefulWidget {
  final int courseId;
  const _EnrollSheet({required this.courseId});
  @override
  State<_EnrollSheet> createState() => _EnrollSheetState();
}

class _EnrollSheetState extends State<_EnrollSheet> {
  final _api = ApiService();
  final _searchCtrl = TextEditingController();
  List<Map<String, dynamic>> _results = [];
  bool _searching = false;
  bool _saving = false;

  Future<void> _search(String q) async {
    if (q.trim().isEmpty) {
      setState(() => _results = []);
      return;
    }
    setState(() => _searching = true);
    try {
      final data = await _api.patients(search: q);
      final items = (data['items'] as List? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      setState(() {
        _results = items;
        _searching = false;
      });
    } catch (_) {
      setState(() => _searching = false);
    }
  }

  Future<void> _enroll(Map<String, dynamic> patient) async {
    final ownerId = asInt(patient['owner_id']);
    if (ownerId == 0) {
      showDsSnack(context, 'Dieser Hund hat keinen Halter hinterlegt.', error: true);
      return;
    }
    setState(() => _saving = true);
    try {
      await _api.dogschoolCourseEnroll(widget.courseId, {
        'patient_id': asInt(patient['id']),
        'owner_id': ownerId,
      });
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => _saving = false);
      if (mounted) showDsSnack(context, e.toString(), error: true);
    }
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom + 24),
      child: Padding(
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
            Text('Hund einschreiben',
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 16),
            TextField(
              controller: _searchCtrl,
              autofocus: true,
              decoration: const InputDecoration(
                labelText: 'Hund suchen…',
                prefixIcon: Icon(Icons.search_rounded),
              ),
              onChanged: _search,
            ),
            const SizedBox(height: 12),
            if (_searching)
              const Padding(
                padding: EdgeInsets.all(16),
                child: CircularProgressIndicator(),
              )
            else
              SizedBox(
                height: 260,
                child: ListView.separated(
                  itemCount: _results.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 6),
                  itemBuilder: (_, i) {
                    final p = _results[i];
                    return DsCard(
                      onTap: _saving ? null : () => _enroll(p),
                      child: Row(children: [
                        const Icon(Icons.pets_rounded, color: AppTheme.primary),
                        const SizedBox(width: 12),
                        Expanded(child: Text(asStr(p['name']))),
                        const Icon(Icons.add_circle_outline_rounded),
                      ]),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }
}
