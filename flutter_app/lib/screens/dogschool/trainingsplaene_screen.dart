import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';
import 'dogschool_common.dart';

class TrainingsplaeneScreen extends StatefulWidget {
  const TrainingsplaeneScreen({super.key});
  @override
  State<TrainingsplaeneScreen> createState() => _TrainingsplaeneScreenState();
}

class _TrainingsplaeneScreenState extends State<TrainingsplaeneScreen> {
  final _api = ApiService();
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;
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
      final data = await _api.dogschoolTrainingPlans(search: _search);
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
      builder: (_) => const _PlanFormSheet(),
    );
    if (ok == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Trainingspläne'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _create,
        icon: const Icon(Icons.add_rounded),
        label: const Text('Neuer Plan'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: TextField(
              decoration: InputDecoration(
                hintText: 'Trainingsplan suchen…',
                prefixIcon: const Icon(Icons.search_rounded),
                isDense: true,
                border:
                    OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              ),
              onSubmitted: (q) {
                _search = q;
                _load();
              },
            ),
          ),
          Expanded(
            child: AsyncBody(
              loading: _loading,
              error: _error,
              empty: _items.isEmpty,
              emptyTitle: 'Keine Trainingspläne',
              emptyHint: 'Lege einen Trainingsplan (Vorlage) an.',
              emptyIcon: Icons.fitness_center_outlined,
              onRetry: _load,
              child: RefreshIndicator(
                onRefresh: _load,
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
                  itemCount: _items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (_, i) {
                    final p = _items[i];
                    return DsCard(
                      onTap: () async {
                        await Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                TrainingPlanDetailScreen(planId: asInt(p['id'])),
                          ),
                        );
                        _load();
                      },
                      child: Row(children: [
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            color: AppTheme.tertiary.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          alignment: Alignment.center,
                          child: const Icon(Icons.fitness_center_rounded,
                              color: AppTheme.tertiary),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(asStr(p['name']),
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w700)),
                              const SizedBox(height: 2),
                              Text(
                                '${asInt(p['duration_weeks'])} Wochen · ${asInt(p['exercise_count'])} Übungen'
                                '${asStr(p['target_audience']).isNotEmpty ? ' · ${asStr(p['target_audience'])}' : ''}',
                                style: TextStyle(
                                    fontSize: 12, color: cs.onSurfaceVariant),
                              ),
                            ],
                          ),
                        ),
                        Icon(Icons.chevron_right_rounded,
                            color: cs.onSurfaceVariant),
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

/* ═══════════════════════ Plan detail ═══════════════════════ */

class TrainingPlanDetailScreen extends StatefulWidget {
  final int planId;
  const TrainingPlanDetailScreen({super.key, required this.planId});
  @override
  State<TrainingPlanDetailScreen> createState() =>
      _TrainingPlanDetailScreenState();
}

class _TrainingPlanDetailScreenState extends State<TrainingPlanDetailScreen> {
  final _api = ApiService();
  Map<String, dynamic>? _plan;
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
      final data = await _api.dogschoolTrainingPlan(widget.planId);
      setState(() {
        _plan = data;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _assign() async {
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _AssignPlanSheet(planId: widget.planId),
    );
    if (ok == true && mounted) {
      showDsSnack(context, 'Plan zugewiesen ✓');
    }
  }

  @override
  Widget build(BuildContext context) {
    final plan = _plan;
    final exercises = (plan?['exercises'] as List? ?? [])
        .map((e) => Map<String, dynamic>.from(e as Map))
        .toList();
    final cs = Theme.of(context).colorScheme;

    // Group exercises by week.
    final byWeek = <int, List<Map<String, dynamic>>>{};
    for (final ex in exercises) {
      byWeek.putIfAbsent(asInt(ex['week_number']), () => []).add(ex);
    }
    final weeks = byWeek.keys.toList()..sort();

    return Scaffold(
      appBar: AppBar(title: Text(plan != null ? asStr(plan['name']) : 'Plan')),
      floatingActionButton: plan == null
          ? null
          : FloatingActionButton.extended(
              onPressed: _assign,
              icon: const Icon(Icons.assignment_ind_rounded),
              label: const Text('Zuweisen'),
            ),
      body: AsyncBody(
        loading: _loading,
        error: _error,
        empty: false,
        emptyTitle: '',
        emptyHint: '',
        emptyIcon: Icons.fitness_center_outlined,
        onRetry: _load,
        child: plan == null
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
                          if (asStr(plan['description']).isNotEmpty) ...[
                            Text(asStr(plan['description'])),
                            const SizedBox(height: 8),
                          ],
                          Wrap(spacing: 8, runSpacing: 6, children: [
                            StatusChip(
                                label: '${asInt(plan['duration_weeks'])} Wochen',
                                color: AppTheme.primary),
                            StatusChip(
                                label:
                                    '${asInt(plan['sessions_per_week'])}×/Woche',
                                color: AppTheme.secondary),
                            StatusChip(
                                label: asStr(plan['difficulty']),
                                color: AppTheme.tertiary),
                          ]),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    Text('Übungen (${exercises.length})',
                        style: Theme.of(context)
                            .textTheme
                            .titleMedium
                            ?.copyWith(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 8),
                    if (exercises.isEmpty)
                      Text('Noch keine Übungen zugeordnet.',
                          style: TextStyle(color: cs.onSurfaceVariant))
                    else
                      for (final w in weeks) ...[
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          child: Text('Woche $w',
                              style: TextStyle(
                                  fontWeight: FontWeight.w700,
                                  color: cs.onSurfaceVariant)),
                        ),
                        ...byWeek[w]!.map((ex) => Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: DsCard(
                                child: Row(children: [
                                  const Icon(Icons.check_circle_outline_rounded,
                                      color: AppTheme.success, size: 20),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(asStr(ex['exercise_name']),
                                            style: const TextStyle(
                                                fontWeight: FontWeight.w600)),
                                        if (asStr(ex['category']).isNotEmpty)
                                          Text(asStr(ex['category']),
                                              style: TextStyle(
                                                  fontSize: 12,
                                                  color: cs.onSurfaceVariant)),
                                      ],
                                    ),
                                  ),
                                ]),
                              ),
                            )),
                      ],
                  ],
                ),
              ),
      ),
    );
  }
}

/* ═══════════════════════ Plan form ═══════════════════════ */

class _PlanFormSheet extends StatefulWidget {
  const _PlanFormSheet();
  @override
  State<_PlanFormSheet> createState() => _PlanFormSheetState();
}

class _PlanFormSheetState extends State<_PlanFormSheet> {
  final _api = ApiService();
  final _name = TextEditingController();
  final _audience = TextEditingController();
  final _weeks = TextEditingController(text: '8');
  final _perWeek = TextEditingController(text: '1');
  final _promptCtrl = TextEditingController();
  String _difficulty = 'easy';
  bool _saving = false;
  bool _aiMode = false;
  bool _aiLoading = false;

  @override
  void dispose() {
    _name.dispose();
    _audience.dispose();
    _weeks.dispose();
    _perWeek.dispose();
    _promptCtrl.dispose();
    super.dispose();
  }

  Future<void> _aiGenerate() async {
    if (_promptCtrl.text.trim().isEmpty) return;
    setState(() => _aiLoading = true);
    try {
      final result =
          await _api.aiGenerate('training_plan', _promptCtrl.text.trim());
      setState(() {
        _aiLoading = false;
        _aiMode = false;
        _name.text = result['name']?.toString() ?? '';
        _audience.text = result['target_audience']?.toString() ?? '';
        _weeks.text = result['duration_weeks']?.toString() ?? '8';
        _perWeek.text = result['sessions_per_week']?.toString() ?? '1';
        _difficulty = result['difficulty']?.toString() ?? 'easy';
      });
    } catch (e) {
      setState(() => _aiLoading = false);
      if (mounted) showDsSnack(context, e.toString(), error: true);
    }
  }

  Future<void> _save() async {
    if (_name.text.trim().isEmpty) {
      showDsSnack(context, 'Bitte einen Namen eingeben.', error: true);
      return;
    }
    setState(() => _saving = true);
    try {
      await _api.dogschoolTrainingPlanCreate({
        'name': _name.text.trim(),
        'target_audience': _audience.text.trim(),
        'duration_weeks': int.tryParse(_weeks.text) ?? 8,
        'sessions_per_week': int.tryParse(_perWeek.text) ?? 1,
        'difficulty': _difficulty,
      });
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
            Row(
              children: [
                Expanded(
                  child: Text('Neuer Trainingsplan',
                      style: Theme.of(context)
                          .textTheme
                          .titleMedium
                          ?.copyWith(fontWeight: FontWeight.w700)),
                ),
                TextButton.icon(
                  icon: Icon(
                      _aiMode
                          ? Icons.edit_rounded
                          : Icons.auto_awesome_rounded,
                      size: 16),
                  label: Text(_aiMode ? 'Manuell' : 'Mit KI'),
                  onPressed: () => setState(() => _aiMode = !_aiMode),
                ),
              ],
            ),
            const SizedBox(height: 16),
            if (_aiMode) ...[
              TextField(
                controller: _promptCtrl,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'KI-Prompt *',
                  hintText: 'z.B. "8-Wochen-Plan für Welpen, Grundgehorsamkeit, 2x täglich"',
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
                    labelText: 'Name *',
                    prefixIcon: Icon(Icons.title_rounded)),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _audience,
                decoration: const InputDecoration(
                    labelText: 'Zielgruppe (z.B. Welpen)',
                    prefixIcon: Icon(Icons.groups_rounded)),
              ),
              const SizedBox(height: 10),
              Row(children: [
                Expanded(
                  child: TextField(
                    controller: _weeks,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                        labelText: 'Wochen',
                        prefixIcon: Icon(Icons.calendar_month_rounded)),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    controller: _perWeek,
                    keyboardType: TextInputType.number,
                    decoration:
                        const InputDecoration(labelText: 'Einheiten/Woche'),
                  ),
                ),
              ]),
              const SizedBox(height: 10),
              DropdownButtonFormField<String>(
                initialValue: _difficulty,
                isExpanded: true,
                decoration: const InputDecoration(
                    labelText: 'Schwierigkeit',
                    prefixIcon: Icon(Icons.trending_up_rounded)),
                items: const [
                  DropdownMenuItem(value: 'easy', child: Text('Leicht')),
                  DropdownMenuItem(value: 'medium', child: Text('Mittel')),
                  DropdownMenuItem(value: 'hard', child: Text('Schwer')),
                  DropdownMenuItem(value: 'expert', child: Text('Experte')),
                ],
                onChanged: (v) => setState(() => _difficulty = v ?? 'easy'),
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

/* ═══════════════════════ Assign plan sheet ═══════════════════════ */

class _AssignPlanSheet extends StatefulWidget {
  final int planId;
  const _AssignPlanSheet({required this.planId});
  @override
  State<_AssignPlanSheet> createState() => _AssignPlanSheetState();
}

class _AssignPlanSheetState extends State<_AssignPlanSheet> {
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
      setState(() {
        _results = (data['items'] as List? ?? [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
        _searching = false;
      });
    } catch (_) {
      setState(() => _searching = false);
    }
  }

  Future<void> _assign(Map<String, dynamic> patient) async {
    setState(() => _saving = true);
    try {
      await _api.dogschoolPlanAssign(widget.planId, {
        'patient_id': asInt(patient['id']),
        if (asInt(patient['owner_id']) > 0) 'owner_id': asInt(patient['owner_id']),
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
            Text('Plan einem Hund zuweisen',
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
                  prefixIcon: Icon(Icons.search_rounded)),
              onChanged: _search,
            ),
            const SizedBox(height: 12),
            if (_searching)
              const Padding(
                  padding: EdgeInsets.all(16),
                  child: CircularProgressIndicator())
            else
              SizedBox(
                height: 260,
                child: ListView.separated(
                  itemCount: _results.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 6),
                  itemBuilder: (_, i) {
                    final p = _results[i];
                    return DsCard(
                      onTap: _saving ? null : () => _assign(p),
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
