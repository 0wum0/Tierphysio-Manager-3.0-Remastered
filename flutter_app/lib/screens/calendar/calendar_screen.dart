import 'package:flutter/material.dart';
import 'package:table_calendar/table_calendar.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class CalendarScreen extends StatefulWidget {
  const CalendarScreen({super.key});

  @override
  State<CalendarScreen> createState() => _CalendarScreenState();
}

class _CalendarScreenState extends State<CalendarScreen> {
  final _api = ApiService();

  DateTime _focusedDay  = DateTime.now();
  DateTime _selectedDay = DateTime.now();
  Map<DateTime, List<Map<String, dynamic>>> _events = {};
  List<Map<String, dynamic>> _selectedEvents = [];
  bool _loading = false;

  // Cached reference data — loaded once
  List<Map<String, dynamic>> _owners          = [];
  List<Map<String, dynamic>> _patients        = [];
  List<Map<String, dynamic>> _treatmentTypes  = [];
  bool _refDataLoaded = false;

  @override
  void initState() {
    super.initState();
    _loadAll();
  }

  Future<void> _loadAll() async {
    await Future.wait([_loadMonth(_focusedDay), _loadRefData()]);
  }

  /// Load owners, patients, treatment types once — reuse in all dialogs.
  Future<void> _loadRefData() async {
    if (_refDataLoaded) return;
    try {
      final results = await Future.wait([
        _api.owners(perPage: 500),
        _api.patients(perPage: 500),
        _api.treatmentTypes(),
      ]);
      if (!mounted) return;
      setState(() {
        _owners         = ((results[0] as Map)['items'] as List? ?? []).cast<Map<String, dynamic>>();
        _patients       = ((results[1] as Map)['items'] as List? ?? []).cast<Map<String, dynamic>>();
        _treatmentTypes = (results[2] as List).cast<Map<String, dynamic>>();
        _refDataLoaded  = true;
      });
    } catch (_) {}
  }

  DateTime _norm(DateTime d) => DateTime(d.year, d.month, d.day);

  Future<void> _loadMonth(DateTime month) async {
    setState(() => _loading = true);
    final start = DateTime(month.year, month.month, 1);
    final end   = DateTime(month.year, month.month + 1, 0);
    try {
      final apts = await _api.appointments(
        start: start.toIso8601String().substring(0, 10),
        end:   end.toIso8601String().substring(0, 10),
      );
      final map = <DateTime, List<Map<String, dynamic>>>{};
      for (final a in apts) {
        try {
          final d = _norm(DateTime.parse(a['start_at'] as String));
          map[d] = [...(map[d] ?? []), Map<String, dynamic>.from(a as Map)];
        } catch (_) {}
      }
      if (!mounted) return;
      setState(() {
        _events         = map;
        _selectedEvents = map[_norm(_selectedDay)] ?? [];
        _loading        = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _onDaySelected(DateTime sel, DateTime focused) {
    setState(() {
      _selectedDay    = sel;
      _focusedDay     = focused;
      _selectedEvents = _events[_norm(sel)] ?? [];
    });
  }

  // ── Build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Kalender'),
        actions: [
          if (_loading)
            const Padding(
              padding: EdgeInsets.only(right: 16),
              child: Center(child: SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))),
            ),
          IconButton(
            icon: const Icon(Icons.sync_rounded),
            tooltip: 'Google Kalender Sync',
            onPressed: () => _showGoogleSyncInfo(context),
          ),
          IconButton(
            icon: const Icon(Icons.add_rounded),
            tooltip: 'Neuer Termin',
            onPressed: () => _openAppointmentDialog(null),
          ),
        ],
      ),
      body: Column(children: [
        RepaintBoundary(
          child: TableCalendar<Map<String, dynamic>>(
            firstDay: DateTime(2020),
            lastDay: DateTime(2030),
            focusedDay: _focusedDay,
            selectedDayPredicate: (d) => isSameDay(_selectedDay, d),
            eventLoader: (d) => _events[_norm(d)] ?? [],
            calendarFormat: CalendarFormat.month,
            startingDayOfWeek: StartingDayOfWeek.monday,
            headerStyle: const HeaderStyle(
              formatButtonVisible: false,
              titleCentered: true,
              leftChevronMargin: EdgeInsets.zero,
              rightChevronMargin: EdgeInsets.zero,
            ),
            calendarStyle: CalendarStyle(
              markerDecoration: BoxDecoration(color: cs.primary, shape: BoxShape.circle),
              markerSize: 5,
              markersMaxCount: 3,
              selectedDecoration: BoxDecoration(color: cs.primary, shape: BoxShape.circle),
              todayDecoration: BoxDecoration(
                color: cs.primaryContainer,
                shape: BoxShape.circle,
              ),
              todayTextStyle: TextStyle(color: cs.onPrimaryContainer, fontWeight: FontWeight.bold),
              weekendTextStyle: TextStyle(color: cs.error.withValues(alpha: 0.8)),
            ),
            onDaySelected: _onDaySelected,
            onPageChanged: (focused) {
              _focusedDay = focused;
              _loadMonth(focused);
            },
            locale: 'de_DE',
          ),
        ),
        const Divider(height: 1),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
          child: Row(children: [
            Text(
              DateFormat('EEEE, d. MMMM yyyy', 'de_DE').format(_selectedDay),
              style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
            ),
            const Spacer(),
            if (_selectedEvents.isNotEmpty)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: cs.primaryContainer,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  '${_selectedEvents.length}',
                  style: TextStyle(fontSize: 12, color: cs.onPrimaryContainer, fontWeight: FontWeight.bold),
                ),
              ),
          ]),
        ),
        Expanded(
          child: _selectedEvents.isEmpty
              ? _buildEmpty(cs)
              : ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 80),
                  itemCount: _selectedEvents.length,
                  itemBuilder: (_, i) => _AptCard(
                    apt: _selectedEvents[i],
                    onTap: () => _openAppointmentDialog(_selectedEvents[i]),
                    onDelete: () => _deleteApt(_selectedEvents[i]),
                  ),
                ),
        ),
      ]),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openAppointmentDialog(null),
        icon: const Icon(Icons.add_rounded),
        label: const Text('Termin'),
      ),
    );
  }

  Widget _buildEmpty(ColorScheme cs) {
    return Center(
      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.event_available_rounded, size: 52, color: cs.outlineVariant),
        const SizedBox(height: 10),
        Text('Keine Termine', style: TextStyle(color: cs.onSurfaceVariant)),
        const SizedBox(height: 16),
        FilledButton.tonal(
          onPressed: () => _openAppointmentDialog(null),
          child: const Text('Termin erstellen'),
        ),
      ]),
    );
  }

  // ── Appointment Dialog (create + edit) ───────────────────────────────────

  Future<void> _openAppointmentDialog(Map<String, dynamic>? existing) async {
    // Ensure ref data is loaded before opening
    if (!_refDataLoaded) {
      await _loadRefData();
      if (!mounted) return;
    }

    final isEdit   = existing != null;
    final aptId    = isEdit ? int.tryParse(existing['id'].toString()) : null;

    // Initial values from existing appointment or defaults
    final titleCtrl = TextEditingController(text: existing?['title'] as String? ?? '');
    var start = isEdit
        ? _parseDt(existing['start_at'] as String?)
        : _selectedDay.copyWith(hour: 9, minute: 0, second: 0, millisecond: 0);
    var end   = isEdit
        ? _parseDt(existing['end_at'] as String?)
        : _selectedDay.copyWith(hour: 10, minute: 0, second: 0, millisecond: 0);

    int? ownerId         = isEdit ? _toIntOrNull(existing['owner_id'])   : null;
    int? patientId       = isEdit ? _toIntOrNull(existing['patient_id']) : null;
    int? treatmentTypeId = isEdit ? _toIntOrNull(existing['treatment_type_id']) : null;
    String status        = (existing?['status'] as String?) ?? 'scheduled';

    // If existing, pre-select owner from patient if not set
    if (isEdit && ownerId == null && patientId != null) {
      final pat = _patients.where((p) => _toIntOrNull(p['id']) == patientId).firstOrNull;
      if (pat != null) ownerId = _toIntOrNull(pat['owner_id']);
    }

    bool titleEdited = isEdit && titleCtrl.text.isNotEmpty;

    void autoFillTitle(StateSetter setS, int? oId, int? pId, int? ttId) {
      if (titleEdited) return;
      final owner   = oId   != null ? _owners.where((o) => _toIntOrNull(o['id']) == oId).firstOrNull : null;
      final patient = pId   != null ? _patients.where((p) => _toIntOrNull(p['id']) == pId).firstOrNull : null;
      final tt      = ttId  != null ? _treatmentTypes.where((t) => _toIntOrNull(t['id']) == ttId).firstOrNull : null;

      final parts = <String>[
        if (owner != null) owner['last_name'] as String? ?? '',
        if (patient != null) patient['name'] as String? ?? '',
        if (tt != null) tt['name'] as String? ?? '',
      ].where((s) => s.isNotEmpty).toList();

      if (parts.isNotEmpty) {
        setS(() => titleCtrl.text = parts.join(' – '));
      }
    }

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setS) {
          final filteredPatients = _patients
              .where((p) => ownerId == null || _toIntOrNull(p['owner_id']) == ownerId)
              .toList();

          return Padding(
            padding: EdgeInsets.only(
              left: 20, right: 20, top: 8,
              bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
            ),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              // Handle
              Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16),
                decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),

              // Header
              Row(children: [
                Expanded(
                  child: Text(
                    isEdit ? 'Termin bearbeiten' : 'Neuer Termin',
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                  ),
                ),
                if (isEdit)
                  IconButton(
                    icon: const Icon(Icons.delete_outline_rounded, color: Colors.red),
                    tooltip: 'Löschen',
                    onPressed: () async {
                      Navigator.pop(ctx);
                      await _deleteApt(existing);
                    },
                  ),
              ]),

              const SizedBox(height: 12),

              // Title
              TextField(
                controller: titleCtrl,
                decoration: const InputDecoration(labelText: 'Titel *', isDense: true, border: OutlineInputBorder()),
                onChanged: (_) => titleEdited = true,
              ),
              const SizedBox(height: 10),

              // Date/time row
              Row(children: [
                Expanded(child: _DateTimePicker(
                  label: 'Start',
                  value: start,
                  onChanged: (dt) => setS(() {
                    start = dt;
                    if (end.isBefore(start)) end = start.add(const Duration(hours: 1));
                  }),
                )),
                const SizedBox(width: 8),
                Expanded(child: _DateTimePicker(
                  label: 'Ende',
                  value: end,
                  onChanged: (dt) => setS(() => end = dt),
                )),
              ]),
              const SizedBox(height: 10),

              // Tierhalter
              if (_owners.isNotEmpty) ...[
                DropdownButtonFormField<int>(
                  key: ValueKey('owner_$ownerId'),
                  initialValue: ownerId,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Tierhalter', isDense: true, border: OutlineInputBorder()),
                  items: [
                    const DropdownMenuItem(value: null, child: Text('— kein Tierhalter —')),
                    ..._owners.map((o) => DropdownMenuItem(
                      value: _toIntOrNull(o['id']),
                      child: Text('${o['last_name']}, ${o['first_name']}', overflow: TextOverflow.ellipsis),
                    )),
                  ],
                  onChanged: (v) => setS(() {
                    ownerId   = v;
                    patientId = null;
                    autoFillTitle(setS, v, null, treatmentTypeId);
                  }),
                ),
                const SizedBox(height: 10),
              ],

              // Tier — filtered by owner
              if (_patients.isNotEmpty) ...[
                DropdownButtonFormField<int>(
                  key: ValueKey('patient_${ownerId}_$patientId'),
                  initialValue: filteredPatients.any((p) => _toIntOrNull(p['id']) == patientId) ? patientId : null,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Tier', isDense: true, border: OutlineInputBorder()),
                  items: [
                    const DropdownMenuItem(value: null, child: Text('— kein Tier —')),
                    ...filteredPatients.map((p) => DropdownMenuItem(
                      value: _toIntOrNull(p['id']),
                      child: Text('${p['name']} (${p['species'] ?? ''})', overflow: TextOverflow.ellipsis),
                    )),
                  ],
                  onChanged: (v) => setS(() {
                    patientId = v;
                    // Auto-select owner from patient if not already set
                    if (v != null && ownerId == null) {
                      final pat = _patients.where((p) => _toIntOrNull(p['id']) == v).firstOrNull;
                      if (pat != null) ownerId = _toIntOrNull(pat['owner_id']);
                    }
                    autoFillTitle(setS, ownerId, v, treatmentTypeId);
                  }),
                ),
                const SizedBox(height: 10),
              ],

              // Behandlung
              if (_treatmentTypes.isNotEmpty) ...[
                DropdownButtonFormField<int>(
                  key: ValueKey('tt_$treatmentTypeId'),
                  initialValue: treatmentTypeId,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Behandlung', isDense: true, border: OutlineInputBorder()),
                  items: [
                    const DropdownMenuItem(value: null, child: Text('— keine Behandlung —')),
                    ..._treatmentTypes.map((t) => DropdownMenuItem(
                      value: _toIntOrNull(t['id']),
                      child: Row(children: [
                        if (t['color'] != null) Container(
                          width: 10, height: 10,
                          margin: const EdgeInsets.only(right: 8),
                          decoration: BoxDecoration(
                            color: _parseColor(t['color'] as String?),
                            shape: BoxShape.circle,
                          ),
                        ),
                        Expanded(child: Text(t['name'] as String? ?? '', overflow: TextOverflow.ellipsis)),
                      ]),
                    )),
                  ],
                  onChanged: (v) => setS(() {
                    treatmentTypeId = v;
                    autoFillTitle(setS, ownerId, patientId, v);
                  }),
                ),
                const SizedBox(height: 10),
              ],

              // Status (edit only)
              if (isEdit) ...[
                DropdownButtonFormField<String>(
                  initialValue: status,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Status', isDense: true, border: OutlineInputBorder()),
                  items: const [
                    DropdownMenuItem(value: 'scheduled',  child: Text('Geplant')),
                    DropdownMenuItem(value: 'confirmed',  child: Text('Bestätigt')),
                    DropdownMenuItem(value: 'completed',  child: Text('Abgeschlossen')),
                    DropdownMenuItem(value: 'cancelled',  child: Text('Abgesagt')),
                    DropdownMenuItem(value: 'noshow',     child: Text('Nicht erschienen')),
                  ],
                  onChanged: (v) => setS(() => status = v ?? status),
                ),
                const SizedBox(height: 10),
              ],

              // Actions
              Row(children: [
                Expanded(child: OutlinedButton(
                  onPressed: () => Navigator.pop(ctx),
                  child: const Text('Abbrechen'),
                )),
                const SizedBox(width: 12),
                Expanded(child: FilledButton(
                  onPressed: () async {
                    if (titleCtrl.text.trim().isEmpty) {
                      ScaffoldMessenger.of(ctx).showSnackBar(
                        const SnackBar(content: Text('Bitte einen Titel eingeben.')));
                      return;
                    }
                    Navigator.pop(ctx);
                    await _saveAppointment(
                      id: aptId,
                      title: titleCtrl.text.trim(),
                      start: start, end: end,
                      ownerId: ownerId,
                      patientId: patientId,
                      treatmentTypeId: treatmentTypeId,
                      status: status,
                    );
                  },
                  child: Text(isEdit ? 'Speichern' : 'Erstellen'),
                )),
              ]),
            ]),
          );
        },
      ),
    );
  }

  Future<void> _saveAppointment({
    int? id,
    required String title,
    required DateTime start,
    required DateTime end,
    int? ownerId, int? patientId, int? treatmentTypeId,
    required String status,
  }) async {
    // For create: omit null FK fields entirely (backend uses isset())
    // For update: send all FK fields including null so backend can clear them
    final Map<String, dynamic> payload = {
      'title':    title,
      'start_at': _fmtForApi(start),
      'end_at':   _fmtForApi(end),
      'status':   status,
    };

    if (id == null) {
      // CREATE — only include FKs when set
      if (ownerId         != null) payload['owner_id']          = ownerId;
      if (patientId       != null) payload['patient_id']        = patientId;
      if (treatmentTypeId != null) payload['treatment_type_id'] = treatmentTypeId;
    } else {
      // UPDATE — always send FKs (can be null to clear them)
      payload['owner_id']          = ownerId;
      payload['patient_id']        = patientId;
      payload['treatment_type_id'] = treatmentTypeId;
    }

    try {
      if (id == null) {
        await _api.appointmentCreate(payload);
        if (mounted) ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: const Text('✓ Termin erstellt'), backgroundColor: Colors.green.shade700,
            behavior: SnackBarBehavior.floating, duration: const Duration(seconds: 3)));
      } else {
        await _api.appointmentUpdate(id, payload);
        if (mounted) ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: const Text('✓ Termin gespeichert'), backgroundColor: Colors.green.shade700,
            behavior: SnackBarBehavior.floating, duration: const Duration(seconds: 3)));
      }
      _loadMonth(_focusedDay);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _deleteApt(Map<String, dynamic> apt) async {
    final id = int.tryParse(apt['id'].toString());
    if (id == null) return;

    final ok = await showDialog<bool>(
      context: context,
      builder: (dlgCtx) => AlertDialog(
        title: const Text('Termin löschen'),
        content: Text('„${apt['title']}" wirklich löschen?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dlgCtx, false), child: const Text('Abbrechen')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(dlgCtx, true),
            child: const Text('Löschen'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    try {
      await _api.appointmentDelete(id);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: const Row(children: [
          Icon(Icons.check_circle_rounded, color: Colors.white, size: 16),
          SizedBox(width: 8),
          Text('Termin gelöscht'),
        ]),
        backgroundColor: Colors.orange.shade700,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        margin: const EdgeInsets.all(12),
        duration: const Duration(seconds: 3),
      ));
      _loadMonth(_focusedDay);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Row(children: [
          const Icon(Icons.error_outline_rounded, color: Colors.white, size: 16),
          const SizedBox(width: 8),
          Expanded(child: Text(e.toString())),
        ]),
        backgroundColor: Colors.red,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        margin: const EdgeInsets.all(12),
      ));
    }
  }

  void _showGoogleSyncInfo(BuildContext context) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        icon: const Icon(Icons.sync_rounded, size: 36, color: Color(0xFF4285F4)),
        title: const Text('Google Kalender Sync'),
        content: const Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Der Google 2-Wege-Sync wird über das Web-Backend verwaltet.',
              style: TextStyle(fontSize: 14),
            ),
            SizedBox(height: 12),
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Icon(Icons.info_outline_rounded, size: 16, color: Colors.blue),
              SizedBox(width: 6),
              Expanded(child: Text(
                'Gehe im Browser zu Einstellungen → Kalender → Google Sync, um die Verbindung einzurichten oder zu prüfen.',
                style: TextStyle(fontSize: 12, color: Colors.grey),
              )),
            ]),
          ],
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

  static DateTime _parseDt(String? s) {
    if (s == null) return DateTime.now();
    try { return DateTime.parse(s); } catch (_) { return DateTime.now(); }
  }

  static int? _toIntOrNull(dynamic v) => v == null ? null : int.tryParse(v.toString());

  static String _fmtForApi(DateTime d) =>
      d.toIso8601String().substring(0, 16).replaceAll('T', ' ');

  static Color _parseColor(String? hex) {
    if (hex == null) return AppTheme.primary;
    try { return Color(int.parse('0xFF${hex.replaceAll('#', '')}'));} catch (_) { return AppTheme.primary; }
  }
}

// ── Appointment Card ──────────────────────────────────────────────────────────

class _AptCard extends StatelessWidget {
  final Map<String, dynamic> apt;
  final VoidCallback onTap;
  final VoidCallback onDelete;

  const _AptCard({required this.apt, required this.onTap, required this.onDelete});

  @override
  Widget build(BuildContext context) {
    final status = apt['status'] as String? ?? '';
    final colorHex = apt['treatment_type_color'] as String? ?? apt['color'] as String? ?? '#4f7cff';
    final color = Color(int.tryParse('0xFF${colorHex.replaceAll('#', '')}') ?? 0xFF4F7CFF);
    final cs = Theme.of(context).colorScheme;

    final startStr = _fmtTime(apt['start_at'] as String?);
    final endStr   = _fmtTime(apt['end_at'] as String?);
    final patient  = apt['patient_name'] as String?;
    final owner    = apt['owner_name']   as String?;
    final treatmentType = apt['treatment_type_name'] as String?;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      clipBehavior: Clip.hardEdge,
      child: InkWell(
        onTap: onTap,
        child: IntrinsicHeight(
          child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Container(width: 5, color: color),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
                child: Row(children: [
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(
                      apt['title'] as String? ?? '',
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
                    ),
                    const SizedBox(height: 3),
                    Row(children: [
                      Icon(Icons.access_time_rounded, size: 13, color: cs.onSurfaceVariant),
                      const SizedBox(width: 3),
                      Text('$startStr – $endStr', style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant)),
                    ]),
                    if (patient != null || owner != null) ...[
                      const SizedBox(height: 2),
                      Row(children: [
                        Icon(Icons.pets_rounded, size: 12, color: cs.onSurfaceVariant),
                        const SizedBox(width: 3),
                        Expanded(child: Text(
                          [if (patient != null) patient, if (owner != null) owner].join(' · '),
                          style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant),
                          overflow: TextOverflow.ellipsis,
                        )),
                      ]),
                    ],
                    if (treatmentType != null) ...[
                      const SizedBox(height: 2),
                      Text(treatmentType,
                        style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.w500)),
                    ],
                  ])),
                  Column(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    _StatusChip(status: status),
                    const SizedBox(height: 8),
                    InkWell(
                      onTap: onDelete,
                      borderRadius: BorderRadius.circular(20),
                      child: Padding(
                        padding: const EdgeInsets.all(4),
                        child: Icon(Icons.delete_outline_rounded, size: 18, color: cs.error.withValues(alpha: 0.7)),
                      ),
                    ),
                  ]),
                ]),
              ),
            ),
          ]),
        ),
      ),
    );
  }

  static String _fmtTime(String? s) {
    if (s == null) return '';
    try { return DateFormat('HH:mm').format(DateTime.parse(s)); } catch (_) { return s; }
  }
}

class _StatusChip extends StatelessWidget {
  final String status;
  const _StatusChip({required this.status});

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'scheduled'  => ('Geplant',         Colors.blue),
      'confirmed'  => ('Bestätigt',        Colors.green),
      'completed'  => ('Abgeschlossen',    Colors.grey),
      'cancelled'  => ('Abgesagt',         Colors.red),
      'noshow'     => ('Nicht erschienen', Colors.orange),
      _            => (status,             Colors.grey),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(label, style: TextStyle(fontSize: 10, color: color, fontWeight: FontWeight.w600)),
    );
  }
}

// ── DateTime Picker ───────────────────────────────────────────────────────────

class _DateTimePicker extends StatelessWidget {
  final String label;
  final DateTime value;
  final ValueChanged<DateTime> onChanged;

  const _DateTimePicker({required this.label, required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(4),
      onTap: () async {
        final d = await showDatePicker(
          context: context,
          initialDate: value,
          firstDate: DateTime(2020),
          lastDate: DateTime(2035),
        );
        if (d == null || !context.mounted) return;
        final t = await showTimePicker(context: context, initialTime: TimeOfDay.fromDateTime(value));
        if (t == null) return;
        onChanged(DateTime(d.year, d.month, d.day, t.hour, t.minute));
      },
      child: InputDecorator(
        decoration: InputDecoration(labelText: label, isDense: true, border: const OutlineInputBorder()),
        child: Text(
          DateFormat('dd.MM. HH:mm').format(value),
          style: const TextStyle(fontSize: 13),
        ),
      ),
    );
  }
}
