import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class HomeworkScreen extends StatefulWidget {
  const HomeworkScreen({super.key});
  @override
  State<HomeworkScreen> createState() => _HomeworkScreenState();
}

class _HomeworkScreenState extends State<HomeworkScreen> {
  final _api = ApiService();
  List<dynamic> _plans = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final plans = await _api.homeworkPlanList();
      setState(() { _plans = plans; _loading = false; });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  String _fmt(String? d) {
    if (d == null) return '—';
    try { return DateFormat('dd.MM.yyyy', 'de_DE').format(DateTime.parse(d)); }
    catch (_) { return d; }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Hausaufgaben'),
        actions: [
          IconButton(
            icon: const Icon(Icons.add_rounded),
            tooltip: 'Neuer Hausaufgabenplan',
            onPressed: () => _showCreateDialog(context),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _buildError()
              : _plans.isEmpty
                  ? _buildEmpty(cs)
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: _plans.length,
                        itemBuilder: (_, i) => _PlanCard(
                          plan: Map<String, dynamic>.from(_plans[i] as Map),
                          fmt: _fmt,
                          onTap: () => context.push(
                            '/portal-admin/hausaufgabenplan/${_plans[i]['id']}',
                          ),
                        ),
                      ),
                    ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showCreateDialog(context),
        icon: const Icon(Icons.add_rounded),
        label: const Text('Neuer Plan'),
        backgroundColor: AppTheme.primary,
        foregroundColor: Colors.white,
      ),
    );
  }

  Widget _buildError() => Center(
    child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.error_outline_rounded, size: 48, color: AppTheme.danger),
        const SizedBox(height: 12),
        Text(_error!, textAlign: TextAlign.center),
        const SizedBox(height: 16),
        FilledButton.icon(
          onPressed: _load,
          icon: const Icon(Icons.refresh_rounded),
          label: const Text('Erneut versuchen'),
        ),
      ],
    ),
  );

  Widget _buildEmpty(ColorScheme cs) => Center(
    child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.assignment_outlined, size: 64,
            color: cs.onSurface.withValues(alpha: 0.2)),
        const SizedBox(height: 16),
        Text('Noch keine Hausaufgabenpläne',
            style: TextStyle(fontSize: 17, fontWeight: FontWeight.w600,
                color: cs.onSurface.withValues(alpha: 0.5))),
        const SizedBox(height: 8),
        Text('Erstelle den ersten Plan für einen Patienten.',
            style: TextStyle(color: cs.onSurface.withValues(alpha: 0.4))),
      ],
    ),
  );

  Future<void> _showCreateDialog(BuildContext ctx) async {
    await showModalBottomSheet(
      context: ctx,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _CreatePlanSheet(
        api: _api,
        onCreated: _load,
      ),
    );
  }
}

// ── Create Plan Bottom Sheet ─────────────────────────────────────────────────

class _CreatePlanSheet extends StatefulWidget {
  final ApiService api;
  final VoidCallback onCreated;
  const _CreatePlanSheet({required this.api, required this.onCreated});

  @override
  State<_CreatePlanSheet> createState() => _CreatePlanSheetState();
}

class _CreatePlanSheetState extends State<_CreatePlanSheet> {
  List<dynamic> _patients = [];
  bool _loadingPatients = true;
  int? _patientId;
  String _patientSearch = '';
  String? _planDate;
  bool _saving = false;
  final _searchCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadPatients();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadPatients() async {
    try {
      final data = await widget.api.patients(perPage: 500);
      setState(() {
        _patients = List<dynamic>.from(data['items'] as List? ?? []);
        _loadingPatients = false;
      });
    } catch (_) {
      setState(() => _loadingPatients = false);
    }
  }

  List<dynamic> get _filtered {
    if (_patientSearch.isEmpty) return _patients;
    final q = _patientSearch.toLowerCase();
    return _patients.where((p) =>
      (p['name'] as String? ?? '').toLowerCase().contains(q) ||
      (p['species'] as String? ?? '').toLowerCase().contains(q) ||
      (p['owner_name'] as String? ?? '').toLowerCase().contains(q)
    ).toList();
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: now,
      firstDate: DateTime(now.year - 2),
      lastDate: DateTime(now.year + 2),
      locale: const Locale('de', 'DE'),
    );
    if (picked != null) {
      setState(() => _planDate = DateFormat('yyyy-MM-dd').format(picked));
    }
  }

  Future<void> _save() async {
    if (_patientId == null) return;
    setState(() => _saving = true);
    try {
      await widget.api.homeworkPlanCreate({
        'patient_id': _patientId,
        'plan_date': _planDate ?? DateFormat('yyyy-MM-dd').format(DateTime.now()),
      });
      if (mounted) {
        Navigator.pop(context);
        widget.onCreated();
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: const Text('✓ Hausaufgabenplan erstellt'),
          backgroundColor: AppTheme.success,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ));
      }
    } catch (e) {
      setState(() => _saving = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(e.toString()),
          backgroundColor: AppTheme.danger,
          behavior: SnackBarBehavior.floating,
        ));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cs = Theme.of(context).colorScheme;
    final filtered = _filtered;
    final selectedPatient = _patientId != null
        ? _patients.firstWhere(
            (p) => int.tryParse(p['id'].toString()) == _patientId,
            orElse: () => null)
        : null;

    return DraggableScrollableSheet(
      initialChildSize: 0.85,
      minChildSize: 0.5,
      maxChildSize: 0.95,
      builder: (_, scrollCtrl) => Container(
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF12151F) : Colors.white,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(children: [
          /* Handle */
          Padding(
            padding: const EdgeInsets.only(top: 12, bottom: 4),
            child: Container(
              width: 40, height: 4,
              decoration: BoxDecoration(
                color: cs.onSurface.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          /* Header */
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 16, 12),
            child: Row(children: [
              Container(
                width: 40, height: 40,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [AppTheme.primary, AppTheme.secondary],
                    begin: Alignment.topLeft, end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.assignment_add, color: Colors.white, size: 20),
              ),
              const SizedBox(width: 12),
              const Expanded(child: Text('Neuer Hausaufgabenplan',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700))),
              IconButton(
                icon: const Icon(Icons.close_rounded),
                onPressed: () => Navigator.pop(context),
              ),
            ]),
          ),
          const Divider(height: 1),
          /* Body */
          Expanded(child: ListView(
            controller: scrollCtrl,
            padding: const EdgeInsets.all(20),
            children: [
              /* Plan date */
              Text('Plandatum', style: TextStyle(
                fontSize: 12, fontWeight: FontWeight.w600,
                color: cs.onSurface.withValues(alpha: 0.5),
                letterSpacing: 0.5)),
              const SizedBox(height: 8),
              InkWell(
                borderRadius: BorderRadius.circular(12),
                onTap: _pickDate,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
                  decoration: BoxDecoration(
                    color: isDark ? Colors.white.withValues(alpha: 0.05) : Colors.black.withValues(alpha: 0.04),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: cs.outline.withValues(alpha: 0.3)),
                  ),
                  child: Row(children: [
                    Icon(Icons.calendar_today_rounded, size: 18, color: AppTheme.primary),
                    const SizedBox(width: 10),
                    Text(
                      _planDate != null
                          ? DateFormat('dd.MM.yyyy').format(DateTime.parse(_planDate!))
                          : 'Heute (${DateFormat('dd.MM.yyyy').format(DateTime.now())})',
                      style: TextStyle(
                        fontSize: 14,
                        color: _planDate != null ? cs.onSurface : cs.onSurface.withValues(alpha: 0.5)),
                    ),
                    const Spacer(),
                    Icon(Icons.edit_calendar_rounded, size: 16, color: cs.onSurface.withValues(alpha: 0.3)),
                  ]),
                ),
              ),
              const SizedBox(height: 20),
              /* Patient selection */
              Text('Patient auswählen *', style: TextStyle(
                fontSize: 12, fontWeight: FontWeight.w600,
                color: cs.onSurface.withValues(alpha: 0.5),
                letterSpacing: 0.5)),
              const SizedBox(height: 8),
              /* Selected patient chip */
              if (selectedPatient != null) ...[
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                    color: AppTheme.primary.withValues(alpha: isDark ? 0.15 : 0.08),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppTheme.primary.withValues(alpha: 0.3)),
                  ),
                  child: Row(children: [
                    Icon(Icons.pets_rounded, size: 16, color: AppTheme.primary),
                    const SizedBox(width: 8),
                    Expanded(child: Text(
                      selectedPatient['name'] as String? ?? '—',
                      style: TextStyle(fontWeight: FontWeight.w600, color: AppTheme.primary),
                    )),
                    if ((selectedPatient['owner_name'] as String? ?? '').isNotEmpty)
                      Text(
                        selectedPatient['owner_name'] as String,
                        style: TextStyle(fontSize: 12, color: AppTheme.primary.withValues(alpha: 0.7)),
                      ),
                    const SizedBox(width: 8),
                    InkWell(
                      onTap: () => setState(() => _patientId = null),
                      child: Icon(Icons.close_rounded, size: 16, color: AppTheme.primary),
                    ),
                  ]),
                ),
                const SizedBox(height: 10),
              ],
              /* Search field */
              TextField(
                controller: _searchCtrl,
                decoration: InputDecoration(
                  hintText: 'Patient suchen...',
                  prefixIcon: const Icon(Icons.search_rounded, size: 20),
                  suffixIcon: _patientSearch.isNotEmpty
                      ? IconButton(
                          icon: const Icon(Icons.clear_rounded, size: 18),
                          onPressed: () {
                            _searchCtrl.clear();
                            setState(() => _patientSearch = '');
                          })
                      : null,
                  isDense: true,
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  filled: true,
                  fillColor: isDark ? Colors.white.withValues(alpha: 0.05) : Colors.black.withValues(alpha: 0.03),
                ),
                onChanged: (v) => setState(() => _patientSearch = v),
              ),
              const SizedBox(height: 8),
              /* Patient list */
              if (_loadingPatients)
                const Center(child: Padding(
                  padding: EdgeInsets.all(20),
                  child: CircularProgressIndicator(),
                ))
              else if (filtered.isEmpty)
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text('Keine Patienten gefunden',
                    style: TextStyle(color: cs.onSurface.withValues(alpha: 0.4)),
                    textAlign: TextAlign.center),
                )
              else
                ...filtered.map((p) {
                  final pid = int.tryParse(p['id'].toString());
                  final isSelected = pid == _patientId;
                  final pname = p['name'] as String? ?? '—';
                  final owner = p['owner_name'] as String? ?? '';
                  final species = p['species'] as String? ?? '';
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 6),
                    child: Material(
                      color: isSelected
                          ? AppTheme.primary.withValues(alpha: isDark ? 0.18 : 0.10)
                          : (isDark ? Colors.white.withValues(alpha: 0.04) : Colors.black.withValues(alpha: 0.02)),
                      borderRadius: BorderRadius.circular(12),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(12),
                        onTap: () => setState(() => _patientId = pid),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: isSelected
                                  ? AppTheme.primary.withValues(alpha: 0.4)
                                  : cs.outline.withValues(alpha: 0.15),
                            ),
                          ),
                          child: Row(children: [
                            Icon(Icons.pets_rounded, size: 16,
                              color: isSelected ? AppTheme.primary : cs.onSurface.withValues(alpha: 0.4)),
                            const SizedBox(width: 10),
                            Expanded(child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(pname, style: TextStyle(
                                  fontWeight: FontWeight.w600,
                                  color: isSelected ? AppTheme.primary : null,
                                )),
                                if (species.isNotEmpty || owner.isNotEmpty)
                                  Text([species, owner].where((s) => s.isNotEmpty).join(' · '),
                                    style: TextStyle(fontSize: 11,
                                      color: cs.onSurface.withValues(alpha: 0.5))),
                              ],
                            )),
                            if (isSelected)
                              Icon(Icons.check_circle_rounded, size: 18, color: AppTheme.primary),
                          ]),
                        ),
                      ),
                    ),
                  );
                }),
            ],
          )),
          /* Footer */
          Container(
            padding: EdgeInsets.fromLTRB(20, 12, 20, MediaQuery.of(context).viewInsets.bottom + 20),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF12151F) : Colors.white,
              border: Border(top: BorderSide(color: cs.outline.withValues(alpha: 0.12))),
            ),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: (_patientId == null || _saving) ? null : _save,
                icon: _saving
                    ? const SizedBox(width: 16, height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.check_rounded),
                label: Text(_saving ? 'Erstellt...' : 'Plan erstellen'),
                style: FilledButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
          ),
        ]),
      ),
    );
  }
}

class _PlanCard extends StatelessWidget {
  final Map<String, dynamic> plan;
  final String Function(String?) fmt;
  final VoidCallback onTap;

  const _PlanCard({required this.plan, required this.fmt, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final cs      = Theme.of(context).colorScheme;
    final isDark  = cs.brightness == Brightness.dark;
    final name    = plan['patient_name']  as String? ?? '—';
    final date    = fmt(plan['plan_date'] as String?);
    final status  = plan['status']        as String? ?? 'active';
    final tasks   = (plan['exercises'] as List?)?.length ?? 0;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: isDark ? const Color(0xFF1A1D27) : Colors.white,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: onTap,
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
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              Container(
                width: 48, height: 48,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [AppTheme.primary, AppTheme.secondary],
                    begin: Alignment.topLeft, end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [
                    BoxShadow(
                      color: AppTheme.primary.withValues(alpha: isDark ? 0.25 : 0.30),
                      blurRadius: 10, offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: const Icon(Icons.assignment_rounded,
                    color: Colors.white, size: 24),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name,
                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, height: 1.2)),
                    const SizedBox(height: 3),
                    Row(children: [
                      Icon(Icons.calendar_today_rounded, size: 11,
                        color: cs.onSurface.withValues(alpha: 0.45)),
                      const SizedBox(width: 4),
                      Text('Plan vom $date',
                        style: TextStyle(fontSize: 12,
                          color: cs.onSurface.withValues(alpha: 0.55))),
                    ]),
                    if (tasks > 0) ...[
                      const SizedBox(height: 3),
                      Row(children: [
                        Icon(Icons.checklist_rounded, size: 11,
                          color: AppTheme.success.withValues(alpha: 0.7)),
                        const SizedBox(width: 4),
                        Text('$tasks Aufgabe${tasks == 1 ? '' : 'n'}',
                          style: TextStyle(fontSize: 11,
                            color: cs.onSurface.withValues(alpha: 0.45))),
                      ]),
                    ],
                  ],
                ),
              ),
              _StatusChip(status: status),
              const SizedBox(width: 8),
              Icon(Icons.chevron_right_rounded, size: 20,
                color: cs.onSurface.withValues(alpha: 0.3)),
            ]),
          ),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String status;
  const _StatusChip({required this.status});

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'active'   => ('Aktiv',    AppTheme.success),
      'archived' => ('Archiv',   AppTheme.warning),
      _          => ('Unbekannt', AppTheme.warning),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(label,
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: color)),
    );
  }
}
