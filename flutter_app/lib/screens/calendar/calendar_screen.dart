import 'package:flutter/material.dart';
import 'package:table_calendar/table_calendar.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';

class CalendarScreen extends StatefulWidget {
  const CalendarScreen({super.key});

  @override
  State<CalendarScreen> createState() => _CalendarScreenState();
}

class _CalendarScreenState extends State<CalendarScreen> {
  final _api = ApiService();
  DateTime _focusedDay  = DateTime.now();
  DateTime _selectedDay = DateTime.now();
  Map<DateTime, List<dynamic>> _events = {};
  List<dynamic> _selectedEvents = [];
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _loadMonth(_focusedDay);
  }

  DateTime _normalizeDate(DateTime d) => DateTime(d.year, d.month, d.day);

  Future<void> _loadMonth(DateTime month) async {
    setState(() => _loading = true);
    final start = DateTime(month.year, month.month, 1);
    final end   = DateTime(month.year, month.month + 1, 0);
    try {
      final apts = await _api.appointments(
        start: start.toIso8601String().substring(0, 10),
        end:   end.toIso8601String().substring(0, 10),
      );
      final map = <DateTime, List<dynamic>>{};
      for (final a in apts) {
        try {
          final d = _normalizeDate(DateTime.parse(a['start_at'] as String));
          map[d] = [...(map[d] ?? []), a];
        } catch (_) {}
      }
      setState(() {
        _events = map;
        _selectedEvents = map[_normalizeDate(_selectedDay)] ?? [];
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
    }
  }

  List<dynamic> _getEventsForDay(DateTime day) =>
      _events[_normalizeDate(day)] ?? [];

  void _onDaySelected(DateTime selected, DateTime focused) {
    setState(() {
      _selectedDay    = selected;
      _focusedDay     = focused;
      _selectedEvents = _getEventsForDay(selected);
    });
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Kalender'),
        actions: [
          if (_loading) const Padding(
            padding: EdgeInsets.only(right: 16),
            child: Center(child: SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))),
          ),
          IconButton(icon: const Icon(Icons.add), onPressed: () => _showNewAppointmentDialog()),
        ],
      ),
      body: Column(
        children: [
          TableCalendar<dynamic>(
            firstDay: DateTime(2020),
            lastDay: DateTime(2030),
            focusedDay: _focusedDay,
            selectedDayPredicate: (d) => isSameDay(_selectedDay, d),
            eventLoader: _getEventsForDay,
            calendarFormat: CalendarFormat.month,
            startingDayOfWeek: StartingDayOfWeek.monday,
            headerStyle: const HeaderStyle(
              formatButtonVisible: false,
              titleCentered: true,
            ),
            calendarStyle: CalendarStyle(
              markerDecoration: BoxDecoration(color: cs.primary, shape: BoxShape.circle),
              selectedDecoration: BoxDecoration(color: cs.primary, shape: BoxShape.circle),
              todayDecoration: BoxDecoration(color: cs.primaryContainer, shape: BoxShape.circle),
              todayTextStyle: TextStyle(color: cs.onPrimaryContainer),
            ),
            onDaySelected: _onDaySelected,
            onPageChanged: (focused) {
              _focusedDay = focused;
              _loadMonth(focused);
            },
            locale: 'de_DE',
          ),
          const Divider(height: 1),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                Text(
                  DateFormat('EEEE, d. MMMM yyyy', 'de_DE').format(_selectedDay),
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
                ),
                const Spacer(),
                Text('${_selectedEvents.length} Termin(e)', style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
          Expanded(
            child: _selectedEvents.isEmpty
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.calendar_today, size: 48, color: cs.outlineVariant),
                        const SizedBox(height: 8),
                        Text('Keine Termine', style: TextStyle(color: cs.onSurfaceVariant)),
                        const SizedBox(height: 12),
                        FilledButton.tonal(
                          onPressed: () => _showNewAppointmentDialog(),
                          child: const Text('Termin erstellen'),
                        ),
                      ],
                    ),
                  )
                : ListView.builder(
                    padding: const EdgeInsets.only(bottom: 24),
                    itemCount: _selectedEvents.length,
                    itemBuilder: (ctx, i) => _AppointmentTile(
                      apt: _selectedEvents[i] as Map<String, dynamic>,
                      onStatusChanged: () => _loadMonth(_focusedDay),
                      onDeleted: () => _loadMonth(_focusedDay),
                      api: _api,
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  Future<void> _showNewAppointmentDialog() async {
    final titleCtrl = TextEditingController();
    DateTime start = _selectedDay.copyWith(hour: 9, minute: 0);
    DateTime end   = _selectedDay.copyWith(hour: 10, minute: 0);

    List<dynamic> owners   = [];
    List<dynamic> patients = [];
    int? ownerId;
    int? patientId;

    try {
      final [o, p] = await Future.wait([_api.owners(perPage: 100), _api.patients(perPage: 200)]);
      owners   = List<dynamic>.from((o as Map)['items'] as List? ?? []);
      patients = List<dynamic>.from((p as Map)['items'] as List? ?? []);
    } catch (_) {}

    if (!mounted) return;

    await showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setS) => AlertDialog(
          title: const Text('Neuer Termin'),
          contentPadding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: titleCtrl,
                  decoration: const InputDecoration(labelText: 'Titel *', isDense: true),
                  autofocus: true,
                ),
                const SizedBox(height: 10),
                Row(children: [
                  Expanded(child: InkWell(
                    onTap: () async {
                      final d = await showDatePicker(context: ctx, initialDate: start, firstDate: DateTime(2020), lastDate: DateTime(2030));
                      final t = await showTimePicker(context: ctx, initialTime: TimeOfDay.fromDateTime(start));
                      if (d != null && t != null) setS(() => start = DateTime(d.year, d.month, d.day, t.hour, t.minute));
                    },
                    child: InputDecorator(
                      decoration: const InputDecoration(labelText: 'Start', isDense: true),
                      child: Text(_fmtDt(start), style: const TextStyle(fontSize: 13)),
                    ),
                  )),
                  const SizedBox(width: 8),
                  Expanded(child: InkWell(
                    onTap: () async {
                      final d = await showDatePicker(context: ctx, initialDate: end, firstDate: DateTime(2020), lastDate: DateTime(2030));
                      final t = await showTimePicker(context: ctx, initialTime: TimeOfDay.fromDateTime(end));
                      if (d != null && t != null) setS(() => end = DateTime(d.year, d.month, d.day, t.hour, t.minute));
                    },
                    child: InputDecorator(
                      decoration: const InputDecoration(labelText: 'Ende', isDense: true),
                      child: Text(_fmtDt(end), style: const TextStyle(fontSize: 13)),
                    ),
                  )),
                ]),
                const SizedBox(height: 10),
                if (owners.isNotEmpty) DropdownButtonFormField<int>(
                  value: ownerId,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Tierhalter', isDense: true),
                  items: [
                    const DropdownMenuItem(value: null, child: Text('— kein Tierhalter —')),
                    ...owners.map((o) => DropdownMenuItem(
                      value: int.tryParse(o['id'].toString()),
                      child: Text('${o['last_name']}, ${o['first_name']}', overflow: TextOverflow.ellipsis),
                    )),
                  ],
                  onChanged: (v) => setS(() {
                    ownerId   = v;
                    patientId = null;
                  }),
                ),
                const SizedBox(height: 10),
                if (patients.isNotEmpty) DropdownButtonFormField<int>(
                  value: patientId,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Patient', isDense: true),
                  items: [
                    const DropdownMenuItem(value: null, child: Text('— kein Patient —')),
                    ...patients
                        .where((p) => ownerId == null || p['owner_id']?.toString() == ownerId.toString())
                        .map((p) => DropdownMenuItem(
                          value: int.tryParse(p['id'].toString()),
                          child: Text('${p['name']} (${p['species']})', overflow: TextOverflow.ellipsis),
                        )),
                  ],
                  onChanged: (v) => setS(() => patientId = v),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Abbrechen')),
            FilledButton(
              onPressed: () async {
                if (titleCtrl.text.isEmpty) return;
                Navigator.pop(ctx);
                try {
                  await _api.appointmentCreate({
                    'title':      titleCtrl.text,
                    'start_at':   start.toIso8601String().substring(0, 16).replaceAll('T', ' '),
                    'end_at':     end.toIso8601String().substring(0, 16).replaceAll('T', ' '),
                    if (ownerId   != null) 'owner_id':   ownerId,
                    if (patientId != null) 'patient_id': patientId,
                    'status': 'scheduled',
                  });
                  _loadMonth(_focusedDay);
                } catch (e) {
                  if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
                }
              },
              child: const Text('Erstellen'),
            ),
          ],
        ),
      ),
    );
  }

  String _fmtDt(DateTime d) => DateFormat('dd.MM. HH:mm').format(d);
}

class _AppointmentTile extends StatelessWidget {
  final Map<String, dynamic> apt;
  final VoidCallback onStatusChanged;
  final VoidCallback onDeleted;
  final ApiService api;

  const _AppointmentTile({
    required this.apt,
    required this.onStatusChanged,
    required this.onDeleted,
    required this.api,
  });

  String _fmtTime(String? s) {
    if (s == null) return '';
    try { return DateFormat('HH:mm').format(DateTime.parse(s)); } catch (_) { return s; }
  }

  Color _statusColor(String s) => switch (s) {
    'scheduled'  => Colors.blue,
    'confirmed'  => Colors.green,
    'completed'  => Colors.grey,
    'cancelled'  => Colors.red,
    'noshow'     => Colors.orange,
    _            => Colors.grey,
  };

  String _statusLabel(String s) => switch (s) {
    'scheduled'  => 'Geplant',
    'confirmed'  => 'Bestätigt',
    'completed'  => 'Abgeschlossen',
    'cancelled'  => 'Abgesagt',
    'noshow'     => 'Nicht erschienen',
    _            => s,
  };

  @override
  Widget build(BuildContext context) {
    final status = apt['status'] as String? ?? '';
    final color  = Color(int.tryParse('0xFF${(apt['color'] as String? ?? '#4f7cff').replaceAll('#', '')}') ?? 0xFF4F7CFF);

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: ListTile(
        leading: Container(width: 4, height: 40, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(2))),
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
        title: Text(apt['title'] as String? ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('${_fmtTime(apt['start_at'] as String?)} – ${_fmtTime(apt['end_at'] as String?)}'),
            if (apt['patient_name'] != null) Text('${apt['patient_name']}${apt['owner_name'] != null ? " · ${apt['owner_name']}" : ""}',
                style: const TextStyle(fontSize: 12)),
          ],
        ),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
          decoration: BoxDecoration(color: _statusColor(status).withOpacity(0.15), borderRadius: BorderRadius.circular(4)),
          child: Text(_statusLabel(status), style: TextStyle(fontSize: 10, color: _statusColor(status), fontWeight: FontWeight.w600)),
        ),
        onLongPress: () => _showOptions(context),
      ),
    );
  }

  void _showOptions(BuildContext context) {
    showModalBottomSheet(
      context: context,
      builder: (_) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.swap_horiz),
              title: const Text('Status ändern'),
              onTap: () { Navigator.pop(context); _changeStatus(context); },
            ),
            ListTile(
              leading: const Icon(Icons.delete_outline, color: Colors.red),
              title: const Text('Termin löschen', style: TextStyle(color: Colors.red)),
              onTap: () { Navigator.pop(context); _delete(context); },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _changeStatus(BuildContext context) async {
    final statuses = {
      'scheduled': 'Geplant', 'confirmed': 'Bestätigt',
      'completed': 'Abgeschlossen', 'cancelled': 'Abgesagt', 'noshow': 'Nicht erschienen',
    };
    final selected = await showDialog<String>(
      context: context,
      builder: (_) => SimpleDialog(
        title: const Text('Status ändern'),
        children: statuses.entries.map((e) => SimpleDialogOption(
          onPressed: () => Navigator.pop(context, e.key),
          child: Text(e.value),
        )).toList(),
      ),
    );
    if (selected == null) return;
    try {
      await api.appointmentUpdate(int.parse(apt['id'].toString()), {'status': selected});
      onStatusChanged();
    } catch (e) {
      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _delete(BuildContext context) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Termin löschen'),
        content: Text('Termin "${apt['title']}" wirklich löschen?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Löschen'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await api.appointmentDelete(int.parse(apt['id'].toString()));
      onDeleted();
    } catch (e) {
      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }
}
