import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class WaitlistScreen extends StatefulWidget {
  const WaitlistScreen({super.key});
  @override
  State<WaitlistScreen> createState() => _WaitlistScreenState();
}

class _WaitlistScreenState extends State<WaitlistScreen> {
  final _api = ApiService();
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.waitlistList();
      setState(() { _items = data.map((e) => Map<String, dynamic>.from(e as Map)).toList(); _loading = false; });
    } catch (e) { setState(() { _error = e.toString(); _loading = false; }); }
  }

  Future<void> _delete(int id) async {
    final ok = await showDialog<bool>(context: context, builder: (_) => AlertDialog(
      title: const Text('Eintrag entfernen'),
      content: const Text('Soll dieser Wartelisten-Eintrag wirklich gelöscht werden?'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
        FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Löschen')),
      ],
    ));
    if (ok != true) return;
    try { await _api.waitlistDelete(id); _load(); }
    catch (e) { if (mounted) _showSnack(e.toString(), error: true); }
  }

  Future<void> _schedule(Map<String, dynamic> item) async {
    DateTime selDate = DateTime.now().add(const Duration(days: 1));
    TimeOfDay selTime = const TimeOfDay(hour: 10, minute: 0);
    await showModalBottomSheet(
      context: context, isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(builder: (ctx, ss) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom + 32),
        child: Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 0), child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
          Text('Termin einplanen', style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          Row(children: [
            Expanded(child: InkWell(
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
            )),
            const SizedBox(width: 12),
            Expanded(child: InkWell(
              borderRadius: BorderRadius.circular(8),
              onTap: () async {
                final t = await showTimePicker(context: ctx, initialTime: selTime);
                if (t != null) ss(() => selTime = t);
              },
              child: InputDecorator(
                decoration: const InputDecoration(labelText: 'Uhrzeit', prefixIcon: Icon(Icons.access_time_rounded)),
                child: Text('${selTime.hour.toString().padLeft(2,'0')}:${selTime.minute.toString().padLeft(2,'0')}'),
              ),
            )),
          ]),
          const SizedBox(height: 20),
          SizedBox(width: double.infinity, child: FilledButton.icon(
            icon: const Icon(Icons.event_available_rounded),
            label: const Text('Termin erstellen'),
            onPressed: () async {
              Navigator.pop(ctx);
              final start = DateTime(selDate.year, selDate.month, selDate.day, selTime.hour, selTime.minute);
              try {
                await _api.waitlistSchedule(item['id'] as int, {
                  'start': start.toIso8601String(),
                  'end': start.add(const Duration(hours: 1)).toIso8601String(),
                });
                _showSnack('Termin erstellt ✓');
                _load();
              } catch (e) { _showSnack(e.toString(), error: true); }
            },
          )),
        ])),
      )),
    );
  }

  void _showAddDialog() {
    final nameCtrl    = TextEditingController();
    final ownerCtrl   = TextEditingController();
    final phoneCtrl   = TextEditingController();
    final notesCtrl   = TextEditingController();
    showModalBottomSheet(
      context: context, isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom + 32),
        child: Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 0), child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
          Text('Zur Warteliste hinzufügen', style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          TextField(controller: nameCtrl,  decoration: const InputDecoration(labelText: 'Tiername *', prefixIcon: Icon(Icons.pets_rounded))),
          const SizedBox(height: 10),
          TextField(controller: ownerCtrl, decoration: const InputDecoration(labelText: 'Besitzername', prefixIcon: Icon(Icons.person_rounded))),
          const SizedBox(height: 10),
          TextField(controller: phoneCtrl, decoration: const InputDecoration(labelText: 'Telefon', prefixIcon: Icon(Icons.phone_rounded)), keyboardType: TextInputType.phone),
          const SizedBox(height: 10),
          TextField(controller: notesCtrl, decoration: const InputDecoration(labelText: 'Notizen', prefixIcon: Icon(Icons.notes_rounded)), maxLines: 2),
          const SizedBox(height: 20),
          SizedBox(width: double.infinity, child: FilledButton.icon(
            icon: const Icon(Icons.add_rounded),
            label: const Text('Hinzufügen'),
            onPressed: () async {
              if (nameCtrl.text.trim().isEmpty) return;
              Navigator.pop(ctx);
              try {
                await _api.waitlistAdd({
                  'patient_name': nameCtrl.text.trim(),
                  'owner_name':   ownerCtrl.text.trim(),
                  'phone':        phoneCtrl.text.trim(),
                  'notes':        notesCtrl.text.trim(),
                });
                _showSnack('Zur Warteliste hinzugefügt ✓');
                _load();
              } catch (e) { _showSnack(e.toString(), error: true); }
            },
          )),
        ])),
      ),
    );
  }

  void _showSnack(String msg, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      backgroundColor: error ? AppTheme.danger : AppTheme.success,
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Warteliste'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _showAddDialog,
        icon: const Icon(Icons.add_rounded),
        label: const Text('Hinzufügen'),
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
              : _items.isEmpty
                  ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.people_outline_rounded, size: 72, color: Colors.grey.shade300),
                      const SizedBox(height: 16),
                      Text('Keine Einträge', style: TextStyle(color: Colors.grey.shade500, fontSize: 16)),
                      const SizedBox(height: 8),
                      Text('Tippe auf + um jemanden zur Warteliste hinzuzufügen',
                        style: TextStyle(color: Colors.grey.shade400, fontSize: 13), textAlign: TextAlign.center),
                    ]))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
                        itemCount: _items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (ctx, i) {
                          final item = _items[i];
                          final position = i + 1;
                          String? dateStr;
                          try {
                            final d = DateTime.parse(item['created_at'] as String? ?? '');
                            dateStr = DateFormat('dd.MM.yy', 'de_DE').format(d);
                          } catch (_) {}
                          final isDark = Theme.of(context).brightness == Brightness.dark;
                          return Material(
                            color: isDark ? const Color(0xFF1A1D27) : Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            child: Container(
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(
                                  color: isDark
                                      ? Colors.white.withValues(alpha: 0.07)
                                      : Colors.black.withValues(alpha: 0.06),
                                ),
                              ),
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                              child: Row(children: [
                                /* Position badge */
                                Container(
                                  width: 44, height: 44,
                                  decoration: BoxDecoration(
                                    gradient: LinearGradient(
                                      colors: [AppTheme.warning, AppTheme.warning.withValues(alpha: 0.65)],
                                      begin: Alignment.topLeft, end: Alignment.bottomRight,
                                    ),
                                    shape: BoxShape.circle,
                                    boxShadow: [
                                      BoxShadow(
                                        color: AppTheme.warning.withValues(alpha: isDark ? 0.22 : 0.28),
                                        blurRadius: 8, offset: const Offset(0, 3)),
                                    ],
                                  ),
                                  alignment: Alignment.center,
                                  child: Text('$position',
                                    style: const TextStyle(
                                      color: Colors.white, fontWeight: FontWeight.w900, fontSize: 16)),
                                ),
                                const SizedBox(width: 14),
                                /* Info */
                                Expanded(child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(item['patient_name'] as String? ?? '—',
                                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, height: 1.2)),
                                    if ((item['owner_name'] as String? ?? '').isNotEmpty) ...[
                                      const SizedBox(height: 2),
                                      Row(children: [
                                        Icon(Icons.person_outline_rounded, size: 11,
                                          color: Theme.of(context).colorScheme.onSurfaceVariant.withValues(alpha: 0.6)),
                                        const SizedBox(width: 3),
                                        Text(item['owner_name'] as String,
                                          style: TextStyle(fontSize: 12,
                                            color: Theme.of(context).colorScheme.onSurfaceVariant)),
                                      ]),
                                    ],
                                    if ((item['phone'] as String? ?? '').isNotEmpty) ...[
                                      const SizedBox(height: 2),
                                      Row(children: [
                                        Icon(Icons.phone_outlined, size: 11,
                                          color: Theme.of(context).colorScheme.onSurfaceVariant.withValues(alpha: 0.5)),
                                        const SizedBox(width: 3),
                                        Text(item['phone'] as String,
                                          style: TextStyle(fontSize: 12,
                                            color: Theme.of(context).colorScheme.onSurfaceVariant.withValues(alpha: 0.7))),
                                      ]),
                                    ],
                                    if (dateStr != null) ...[
                                      const SizedBox(height: 2),
                                      Text('Seit $dateStr',
                                        style: TextStyle(fontSize: 11,
                                          color: Theme.of(context).colorScheme.onSurfaceVariant.withValues(alpha: 0.45))),
                                    ],
                                  ],
                                )),
                                /* Actions */
                                Row(mainAxisSize: MainAxisSize.min, children: [
                                  _ActionBtn(
                                    icon: Icons.event_available_rounded,
                                    color: AppTheme.success,
                                    tooltip: 'Einplanen',
                                    onTap: () => _schedule(item),
                                    isDark: isDark,
                                  ),
                                  const SizedBox(width: 4),
                                  _ActionBtn(
                                    icon: Icons.delete_outline_rounded,
                                    color: AppTheme.danger,
                                    tooltip: 'Entfernen',
                                    onTap: () => _delete(item['id'] as int),
                                    isDark: isDark,
                                  ),
                                ]),
                              ]),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}

class _ActionBtn extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String tooltip;
  final VoidCallback onTap;
  final bool isDark;

  const _ActionBtn({
    required this.icon,
    required this.color,
    required this.tooltip,
    required this.onTap,
    required this.isDark,
  });

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Container(
          width: 36, height: 36,
          decoration: BoxDecoration(
            color: color.withValues(alpha: isDark ? 0.15 : 0.10),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: color.withValues(alpha: 0.20)),
          ),
          child: Icon(icon, color: color, size: 18),
        ),
      ),
    );
  }
}
