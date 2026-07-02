import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';
import 'dogschool_common.dart';

class AnwesenheitScreen extends StatefulWidget {
  const AnwesenheitScreen({super.key});
  @override
  State<AnwesenheitScreen> createState() => _AnwesenheitScreenState();
}

class _AnwesenheitScreenState extends State<AnwesenheitScreen> {
  final _api = ApiService();
  List<Map<String, dynamic>> _sessions = [];
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
      final data = await _api.dogschoolSessions();
      final items = (data['items'] as List? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      setState(() {
        _sessions = items;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Anwesenheit'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load),
        ],
      ),
      body: AsyncBody(
        loading: _loading,
        error: _error,
        empty: _sessions.isEmpty,
        emptyTitle: 'Keine Termine',
        emptyHint: 'Es gibt aktuell keine Kurstermine für die Anwesenheit.',
        emptyIcon: Icons.checklist_rounded,
        onRetry: _load,
        child: RefreshIndicator(
          onRefresh: _load,
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
            itemCount: _sessions.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (_, i) {
              final s = _sessions[i];
              final marked = asInt(s['marked_count']);
              return DsCard(
                onTap: () async {
                  await Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) =>
                          AttendanceSessionScreen(sessionId: asInt(s['id'])),
                    ),
                  );
                  _load();
                },
                child: Row(children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: statusColor(asStr(s['status'])).withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    alignment: Alignment.center,
                    child: Icon(Icons.event_available_rounded,
                        color: statusColor(asStr(s['status']))),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(asStr(s['course_name']),
                            style: const TextStyle(
                                fontWeight: FontWeight.w700, fontSize: 14)),
                        const SizedBox(height: 3),
                        Text(
                          'Einheit ${asInt(s['session_number'])} · ${asStr(s['session_date'])}'
                          '${asStr(s['start_time']).isNotEmpty ? ' · ${asStr(s['start_time']).substring(0, 5)}' : ''}',
                          style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant),
                        ),
                      ],
                    ),
                  ),
                  if (marked > 0)
                    const StatusChip(label: 'erfasst', color: AppTheme.success)
                  else
                    Icon(Icons.chevron_right_rounded, color: cs.onSurfaceVariant),
                ]),
              );
            },
          ),
        ),
      ),
    );
  }
}

/* ═══════════════════════ Attendance matrix ═══════════════════════ */

class AttendanceSessionScreen extends StatefulWidget {
  final int sessionId;
  const AttendanceSessionScreen({super.key, required this.sessionId});
  @override
  State<AttendanceSessionScreen> createState() =>
      _AttendanceSessionScreenState();
}

class _AttendanceSessionScreenState extends State<AttendanceSessionScreen> {
  final _api = ApiService();
  Map<String, dynamic>? _session;
  List<Map<String, dynamic>> _rows = [];
  final Map<int, String> _status = {};
  bool _loading = true;
  String? _error;
  bool _saving = false;

  static const _options = ['present', 'absent', 'excused', 'late', 'no_show'];

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
      final data = await _api.dogschoolAttendanceMatrix(widget.sessionId);
      final rows = (data['rows'] as List? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      _status.clear();
      for (final r in rows) {
        _status[asInt(r['enrollment_id'])] =
            asStr(r['attendance_status']).isEmpty
                ? 'present'
                : asStr(r['attendance_status']);
      }
      setState(() {
        _session = data['session'] as Map<String, dynamic>?;
        _rows = rows;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final entries = _status.entries
        .map((e) => {'enrollment_id': e.key, 'status': e.value})
        .toList();
    try {
      await _api.dogschoolAttendanceSave(widget.sessionId, entries);
      if (mounted) {
        showDsSnack(context, 'Anwesenheit gespeichert ✓');
        Navigator.pop(context);
      }
    } catch (e) {
      setState(() => _saving = false);
      if (mounted) showDsSnack(context, e.toString(), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: Text(_session != null ? asStr(_session!['course_name']) : 'Anwesenheit'),
      ),
      floatingActionButton: _rows.isEmpty
          ? null
          : FloatingActionButton.extended(
              onPressed: _saving ? null : _save,
              icon: _saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.save_rounded),
              label: Text(_saving ? 'Speichern…' : 'Speichern'),
            ),
      body: AsyncBody(
        loading: _loading,
        error: _error,
        empty: _rows.isEmpty,
        emptyTitle: 'Keine Teilnehmer',
        emptyHint: 'Für diesen Termin sind keine aktiven Einschreibungen vorhanden.',
        emptyIcon: Icons.groups_outlined,
        onRetry: _load,
        child: ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
          itemCount: _rows.length,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (_, i) {
            final r = _rows[i];
            final eid = asInt(r['enrollment_id']);
            final current = _status[eid] ?? 'present';
            return DsCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(children: [
                    const Icon(Icons.pets_rounded, color: AppTheme.primary, size: 20),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(asStr(r['patient_name']),
                              style: const TextStyle(fontWeight: FontWeight.w700)),
                          Text(
                            '${asStr(r['owner_first_name'])} ${asStr(r['owner_last_name'])}'.trim(),
                            style:
                                TextStyle(fontSize: 12, color: cs.onSurfaceVariant),
                          ),
                        ],
                      ),
                    ),
                  ]),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 6,
                    children: [
                      for (final opt in _options)
                        ChoiceChip(
                          label: Text(attendanceLabel(opt),
                              style: const TextStyle(fontSize: 12)),
                          selected: current == opt,
                          selectedColor: statusColor(opt).withValues(alpha: 0.20),
                          onSelected: (_) => setState(() => _status[eid] = opt),
                        ),
                    ],
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}
