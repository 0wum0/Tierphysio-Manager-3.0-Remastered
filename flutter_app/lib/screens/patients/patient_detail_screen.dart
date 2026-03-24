import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_html/flutter_html.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:file_picker/file_picker.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';
import '../../widgets/html_editor.dart';
import '../../widgets/paw_avatar.dart';
import '../../widgets/shimmer_list.dart';
import '../../widgets/media_viewer.dart';

class PatientDetailScreen extends StatefulWidget {
  final int id;
  const PatientDetailScreen({super.key, required this.id});

  @override
  State<PatientDetailScreen> createState() => _PatientDetailScreenState();
}

class _PatientDetailScreenState extends State<PatientDetailScreen>
    with SingleTickerProviderStateMixin {
  final _api = ApiService();
  Map<String, dynamic>? _patient;
  bool _loading = true;
  bool _uploading = false;
  String? _error;
  late TabController _tabCtrl;

  // Exercises (Übungen)
  List<Map<String, dynamic>> _exercises = [];
  bool _loadingExercises = false;
  bool _exercisesLoaded  = false;

  // Homework (Hausaufgaben)
  List<Map<String, dynamic>> _homework = [];
  bool _loadingHomework = false;
  bool _homeworkLoaded  = false;

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 4, vsync: this);
    _tabCtrl.addListener(_onTabChanged);
    _load();
  }

  void _onTabChanged() {
    if (_tabCtrl.indexIsChanging) return;
    if (_tabCtrl.index == 1 && !_exercisesLoaded) _loadExercises();
    if (_tabCtrl.index == 2 && !_homeworkLoaded)  _loadHomework();
  }

  Future<void> _loadExercises() async {
    setState(() => _loadingExercises = true);
    try {
      final list = await _api.exercisesList(widget.id);
      setState(() {
        _exercises = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _exercisesLoaded  = true;
        _loadingExercises = false;
      });
    } catch (_) { setState(() => _loadingExercises = false); }
  }

  Future<void> _loadHomework() async {
    setState(() => _loadingHomework = true);
    try {
      final list = await _api.patientHomeworkList(widget.id);
      setState(() {
        _homework = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _homeworkLoaded  = true;
        _loadingHomework = false;
      });
    } catch (_) { setState(() => _loadingHomework = false); }
  }

  @override
  void dispose() { _tabCtrl.removeListener(_onTabChanged); _tabCtrl.dispose(); super.dispose(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.patientShow(widget.id);
      setState(() { _patient = data; _loading = false; });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  Future<void> _uploadProfilePhoto() async {
    final result = await FilePicker.platform.pickFiles(type: FileType.image, allowMultiple: false);
    if (result == null || result.files.single.path == null) return;
    setState(() => _uploading = true);
    try {
      await _api.patientPhotoUpload(widget.id, File(result.files.single.path!));
      _load();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Foto aktualisiert ✓'), backgroundColor: Colors.green));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = _patient;
    return Scaffold(
      body: _loading
          ? _buildLoadingSkeleton()
          : _error != null
              ? _buildError()
              : _buildContent(p!),
      floatingActionButton: p == null ? null : FloatingActionButton.extended(
        onPressed: _uploading ? null : _showAddBottomSheet,
        icon: _uploading
            ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
            : const Icon(Icons.add_rounded),
        label: Text(_uploading ? 'Hochladen…' : 'Eintrag hinzufügen'),
      ),
    );
  }

  Widget _buildLoadingSkeleton() {
    return CustomScrollView(slivers: [
      SliverAppBar(expandedHeight: 160, pinned: true,
        flexibleSpace: FlexibleSpaceBar(
          background: Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(colors: [AppTheme.primary, AppTheme.secondary]),
            ),
          ),
        ),
      ),
      SliverPadding(
        padding: const EdgeInsets.all(16),
        sliver: SliverList(delegate: SliverChildListDelegate([
          ShimmerBox(width: double.infinity, height: 90, radius: 16),
          const SizedBox(height: 12),
          ShimmerBox(width: double.infinity, height: 140, radius: 16),
          const SizedBox(height: 12),
          ShimmerBox(width: double.infinity, height: 200, radius: 16),
        ])),
      ),
    ]);
  }

  Widget _buildError() {
    return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      Icon(Icons.error_outline_rounded, size: 56, color: AppTheme.danger),
      const SizedBox(height: 12),
      Text(_error!, textAlign: TextAlign.center),
      const SizedBox(height: 16),
      FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut')),
    ]));
  }

  Widget _buildContent(Map<String, dynamic> p) {
    final species = p['species'] as String? ?? '';
    final timeline = List<Map<String, dynamic>>.from(
        (p['timeline'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));

    final statusColor = switch (p['status'] as String? ?? '') {
      'active'   => AppTheme.success,
      'inactive' => Colors.grey,
      'deceased' => AppTheme.danger,
      _          => AppTheme.primary,
    };

    return RefreshIndicator(
      onRefresh: _load,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          // ── Gradient SliverAppBar ──
          SliverAppBar(
            expandedHeight: 190,
            pinned: true,
            backgroundColor: statusColor,
            foregroundColor: Colors.white,
            actions: [
              IconButton(
                icon: const Icon(Icons.receipt_long_rounded),
                tooltip: 'Neue Rechnung',
                onPressed: () => context.push(
                  '/rechnungen/neu',
                  extra: {'patientId': widget.id, 'patientName': p['name'], 'ownerId': p['owner_id'], 'ownerName': '${p['owner_first_name'] ?? ''} ${p['owner_last_name'] ?? ''}'.trim()},
                ),
              ),
              IconButton(
                icon: const Icon(Icons.event_rounded),
                tooltip: 'Neuer Termin',
                onPressed: () => _showNewAppointmentSheet(p),
              ),
              IconButton(
                icon: const Icon(Icons.edit_rounded),
                tooltip: 'Bearbeiten',
                onPressed: () => context.push('/patienten/${widget.id}/edit').then((_) => _load()),
              ),
            ],
            flexibleSpace: FlexibleSpaceBar(
              collapseMode: CollapseMode.pin,
              background: Stack(children: [
                Container(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft, end: Alignment.bottomRight,
                      colors: [statusColor, Color.lerp(statusColor, AppTheme.secondary, 0.5)!],
                    ),
                  ),
                ),
                Positioned(right: -20, top: -20, child: Container(
                  width: 140, height: 140,
                  decoration: BoxDecoration(shape: BoxShape.circle,
                    color: Colors.white.withValues(alpha: 0.07)),
                )),
                Positioned(right: 60, bottom: 48, child: Container(
                  width: 80, height: 80,
                  decoration: BoxDecoration(shape: BoxShape.circle,
                    color: Colors.white.withValues(alpha: 0.05)),
                )),
                // Content sits between status bar + back button (top ~56) and TabBar (bottom 48)
                Positioned.fill(
                  bottom: 48,
                  child: SafeArea(
                    bottom: false,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 52, 100, 0),
                      child: Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
                        GestureDetector(
                          onTap: _uploading ? null : _uploadProfilePhoto,
                          child: Stack(
                            children: [
                              Hero(
                                tag: 'patient_${widget.id}',
                                child: PawAvatar(
                                  photoPath: p['photo_url'] as String?,
                                  species: species,
                                  name: p['name'] as String?,
                                  radius: 30,
                                ),
                              ),
                              Positioned(
                                bottom: 0, right: 0,
                                child: Container(
                                  width: 22, height: 22,
                                  decoration: const BoxDecoration(
                                    color: Colors.white,
                                    shape: BoxShape.circle,
                                  ),
                                  child: _uploading
                                      ? const Padding(
                                          padding: EdgeInsets.all(3),
                                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black54))
                                      : const Icon(Icons.camera_alt_rounded, size: 14, color: Colors.black87),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(p['name'] as String? ?? '',
                              style: const TextStyle(color: Colors.white, fontSize: 20,
                                  fontWeight: FontWeight.w800, letterSpacing: -0.5),
                              overflow: TextOverflow.ellipsis),
                            const SizedBox(height: 2),
                            Text(
                              [species, p['breed'] as String? ?? ''].where((s) => s.isNotEmpty).join(' · '),
                              style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 12),
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 4),
                            Row(children: [
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(alpha: 0.2),
                                  borderRadius: BorderRadius.circular(20),
                                ),
                                child: Text(
                                  _statusLabel(p['status'] as String? ?? ''),
                                  style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700),
                                ),
                              ),
                              if (p['owner_first_name'] != null) ...[
                                const SizedBox(width: 6),
                                Icon(Icons.person_rounded, size: 11, color: Colors.white.withValues(alpha: 0.8)),
                                const SizedBox(width: 2),
                                Flexible(child: Text(
                                  '${p['owner_first_name']} ${p['owner_last_name'] ?? ''}'.trim(),
                                  style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 11),
                                  overflow: TextOverflow.ellipsis,
                                )),
                              ],
                            ]),
                          ],
                        )),
                      ]),
                    ),
                  ),
                ),
              ]),
            ),
            bottom: TabBar(
              controller: _tabCtrl,
              labelColor: Colors.white,
              unselectedLabelColor: Colors.white.withValues(alpha: 0.6),
              indicatorColor: Colors.white,
              indicatorWeight: 3,
              dividerColor: Colors.transparent,
              tabs: const [
                Tab(text: 'Akte',         icon: Icon(Icons.folder_open_rounded,     size: 16)),
                Tab(text: 'Übungen',      icon: Icon(Icons.fitness_center_rounded,   size: 16)),
                Tab(text: 'Hausaufgaben', icon: Icon(Icons.assignment_rounded,       size: 16)),
                Tab(text: 'Daten',        icon: Icon(Icons.info_outline_rounded,     size: 16)),
              ],
            ),
          ),

          // ── Tab content as sliver ──
          SliverFillRemaining(
            child: TabBarView(
              controller: _tabCtrl,
              children: [
                _buildTimeline(timeline),
                _buildExercisesTab(),
                _buildHomeworkTab(),
                _buildInfo(p),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ── Timeline Tab ──────────────────────────────────────────

  Widget _buildTimeline(List<Map<String, dynamic>> timeline) {
    if (timeline.isEmpty) {
      return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.folder_open_rounded, size: 64, color: Colors.grey.shade300),
        const SizedBox(height: 12),
        Text('Noch keine Einträge', style: TextStyle(color: Colors.grey.shade500, fontSize: 16)),
        const SizedBox(height: 8),
        Text('Tippe auf + um einen Eintrag hinzuzufügen', style: TextStyle(color: Colors.grey.shade400, fontSize: 13)),
      ]));
    }
    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
      itemCount: timeline.length,
      itemBuilder: (ctx, i) => _TimelineCard(
        entry: timeline[i],
        isFirst: i == 0,
        isLast: i == timeline.length - 1,
      ),
    );
  }

  // ── Exercises Tab ─────────────────────────────────────────

  Widget _buildExercisesTab() {
    if (_loadingExercises) return const Center(child: CircularProgressIndicator());
    return RefreshIndicator(
      onRefresh: () async { _exercisesLoaded = false; await _loadExercises(); },
      child: Stack(
        children: [
          _exercises.isEmpty
              ? ListView(padding: const EdgeInsets.all(32), children: [
                  Center(child: Column(children: [
                    const SizedBox(height: 40),
                    Icon(Icons.fitness_center_rounded, size: 72, color: Colors.grey.shade300),
                    const SizedBox(height: 16),
                    Text('Keine Übungen', style: TextStyle(color: Colors.grey.shade500, fontSize: 16)),
                    const SizedBox(height: 8),
                    FilledButton.icon(
                      onPressed: _showAddExerciseSheet,
                      icon: const Icon(Icons.add_rounded),
                      label: const Text('Übung hinzufügen'),
                    ),
                  ])),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
                  itemCount: _exercises.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (_, i) => _ExerciseCard(
                    exercise: _exercises[i],
                    onDelete: () => _deleteExercise(_exercises[i]),
                  ),
                ),
          if (_exercises.isNotEmpty)
            Positioned(
              bottom: 16, right: 16,
              child: FloatingActionButton.small(
                heroTag: 'add_exercise',
                onPressed: _showAddExerciseSheet,
                child: const Icon(Icons.add_rounded),
              ),
            ),
        ],
      ),
    );
  }

  void _showAddExerciseSheet() {
    final titleCtrl = TextEditingController();
    final descCtrl  = TextEditingController();
    final videoCtrl = TextEditingController();
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, ss) => Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16),
                decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
              Text('Übung hinzufügen',
                style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(height: 16),
              TextField(controller: titleCtrl, decoration: const InputDecoration(
                labelText: 'Titel *', prefixIcon: Icon(Icons.fitness_center_rounded))),
              const SizedBox(height: 12),
              TextField(controller: descCtrl, maxLines: 3, decoration: const InputDecoration(
                labelText: 'Beschreibung', prefixIcon: Icon(Icons.notes_rounded))),
              const SizedBox(height: 12),
              TextField(controller: videoCtrl, decoration: const InputDecoration(
                labelText: 'Video-URL (optional)', prefixIcon: Icon(Icons.videocam_rounded))),
              const SizedBox(height: 20),
              SizedBox(width: double.infinity, child: FilledButton.icon(
                icon: const Icon(Icons.save_rounded),
                label: const Text('Speichern'),
                onPressed: () async {
                  if (titleCtrl.text.trim().isEmpty) return;
                  Navigator.pop(ctx);
                  try {
                    await _api.exerciseCreate(widget.id, {
                      'title':       titleCtrl.text.trim(),
                      'description': descCtrl.text.trim(),
                      'video_url':   videoCtrl.text.trim(),
                    });
                    _exercisesLoaded = false;
                    await _loadExercises();
                    if (mounted) ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('✓ Übung hinzugefügt'), backgroundColor: Colors.green));
                  } catch (e) {
                    if (mounted) ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text(e.toString()), backgroundColor: Colors.red));
                  }
                },
              )),
            ]),
          ),
        ),
      ),
    );
  }

  Future<void> _deleteExercise(Map<String, dynamic> ex) async {
    final id = int.tryParse(ex['id'].toString());
    if (id == null) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (dlgCtx) => AlertDialog(
        title: const Text('Übung löschen'),
        content: Text('"${ex['title'] ?? ex['name'] ?? ''}" wirklich löschen?'),
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
      await _api.exerciseDelete(id);
      _exercisesLoaded = false;
      await _loadExercises();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Übung gelöscht'), backgroundColor: Colors.orange));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: Colors.red));
    }
  }

  // ── Homework Tab ───────────────────────────────────────────

  Widget _buildHomeworkTab() {
    if (_loadingHomework) return const Center(child: CircularProgressIndicator());
    return RefreshIndicator(
      onRefresh: () async { _homeworkLoaded = false; await _loadHomework(); },
      child: _homework.isEmpty
          ? ListView(padding: const EdgeInsets.all(32), children: [
              Center(child: Column(children: [
                const SizedBox(height: 40),
                Icon(Icons.assignment_outlined, size: 72, color: Colors.grey.shade300),
                const SizedBox(height: 16),
                Text('Keine Hausaufgaben', style: TextStyle(color: Colors.grey.shade500, fontSize: 16)),
                const SizedBox(height: 8),
                Text('Hausaufgaben werden über das Portal Admin zugewiesen.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.grey.shade400, fontSize: 12)),
              ])),
            ])
          : ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
              itemCount: _homework.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (_, i) => _HomeworkCard(homework: _homework[i]),
            ),
    );
  }

  // ── Info Tab ──────────────────────────────────────────────

  Widget _buildInfo(Map<String, dynamic> p) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
      child: Column(children: [
        _ModernInfoCard(title: 'Patientendaten', icon: Icons.pets_rounded, color: AppTheme.primary, items: {
          'Geschlecht':   _gender(p['gender'] as String? ?? ''),
          'Geburtsdatum': _date(p['birth_date'] as String?),
          'Gewicht':      p['weight'] != null ? '${p['weight']} kg' : '—',
          'Chip-Nr.':     p['chip_number'] as String? ?? '—',
          'Farbe':        p['color'] as String? ?? '—',
        }),
        const SizedBox(height: 12),
        if (p['owner_email'] != null || p['owner_phone'] != null)
          _ModernInfoCard(title: 'Besitzer', icon: Icons.person_rounded, color: AppTheme.secondary, items: {
            'Name':    '${p['owner_first_name'] ?? ''} ${p['owner_last_name'] ?? ''}'.trim(),
            'E-Mail':  p['owner_email'] as String? ?? '—',
            'Telefon': p['owner_phone'] as String? ?? '—',
          }),
        if ((p['notes'] as String? ?? '').isNotEmpty) ...[
          const SizedBox(height: 12),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Theme.of(context).cardTheme.color,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppTheme.warning.withValues(alpha: 0.2)),
            ),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Icon(Icons.notes_rounded, color: AppTheme.warning, size: 18),
                const SizedBox(width: 8),
                Text('Notizen', style: TextStyle(fontWeight: FontWeight.w700, color: AppTheme.warning)),
              ]),
              const SizedBox(height: 10),
              Text(p['notes'] as String, style: Theme.of(context).textTheme.bodyMedium),
            ]),
          ),
        ],
        // Next appointment card
        const SizedBox(height: 12),
        _buildNextAppointmentCard(p),
        // Invoice stats if present
        if (p['invoice_stats'] != null) ...[
          const SizedBox(height: 12),
          _ModernInfoCard(title: 'Rechnungsstatistik', icon: Icons.receipt_long_rounded, color: AppTheme.tertiary, items: {
            'Offen':   '${p['invoice_stats']['open_count'] ?? p['invoice_stats']['open'] ?? 0}',
            'Bezahlt': '${p['invoice_stats']['paid_count'] ?? p['invoice_stats']['paid'] ?? 0}',
          }),
        ],
      ]),
    );
  }

  // ── Next Appointment Card ─────────────────────────────────

  Widget _buildNextAppointmentCard(Map<String, dynamic> p) {
    final upcoming = p['upcoming_appointments'] as List? ?? [];
    final Map<String, dynamic>? next = upcoming.isNotEmpty
        ? Map<String, dynamic>.from(upcoming.first as Map)
        : null;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final accent = AppTheme.tertiary;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1A1D27) : Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: accent.withValues(alpha: isDark ? 0.25 : 0.18)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Icon(Icons.event_rounded, color: accent, size: 18),
          const SizedBox(width: 8),
          Text('Nächster Termin',
            style: TextStyle(fontWeight: FontWeight.w700, color: accent, fontSize: 14)),
          const Spacer(),
          GestureDetector(
            onTap: () => context.go('/kalender'),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: accent.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text('Kalender',
                style: TextStyle(color: accent, fontSize: 11, fontWeight: FontWeight.w600)),
            ),
          ),
        ]),
        const SizedBox(height: 10),
        if (next == null)
          Row(children: [
            Icon(Icons.event_busy_rounded, size: 16,
              color: Theme.of(context).colorScheme.onSurfaceVariant.withValues(alpha: 0.5)),
            const SizedBox(width: 8),
            Text('Kein Termin geplant',
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
                fontSize: 13)),
          ])
        else
          _buildNextAptDetail(next, accent),
      ]),
    );
  }

  Widget _buildNextAptDetail(Map<String, dynamic> apt, Color accent) {
    DateTime? start;
    try { start = DateTime.parse(apt['start_at'] as String? ?? ''); } catch (_) {}
    final isToday = start != null &&
        start.year == DateTime.now().year &&
        start.month == DateTime.now().month &&
        start.day == DateTime.now().day;
    final dateStr = start != null
        ? DateFormat('EEEE, d. MMMM yyyy', 'de_DE').format(start)
        : '—';
    final timeStr = start != null ? DateFormat('HH:mm').format(start) : '';
    final treatName = apt['treatment_type_name'] as String? ?? '';
    final title     = apt['title'] as String? ?? '';

    Color aptColor = accent;
    final colorHex = apt['treatment_color'] as String? ?? apt['color'] as String?;
    if (colorHex != null && colorHex.isNotEmpty) {
      try { aptColor = Color(int.parse('FF${colorHex.replaceAll('#', '')}', radix: 16)); }
      catch (_) {}
    }
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(
        width: 48, height: 48,
        decoration: BoxDecoration(
          color: aptColor.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: aptColor.withValues(alpha: 0.3)),
        ),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          if (start != null) ...[
            Text(DateFormat('d', 'de_DE').format(start),
              style: TextStyle(fontWeight: FontWeight.w800, color: aptColor, fontSize: 16, height: 1.0)),
            Text(DateFormat('MMM', 'de_DE').format(start),
              style: TextStyle(fontSize: 9, color: aptColor, fontWeight: FontWeight.w600)),
          ] else
            Icon(Icons.event_rounded, color: aptColor, size: 20),
        ]),
      ),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text(title.isNotEmpty ? title : (treatName.isNotEmpty ? treatName : 'Termin'),
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5),
            maxLines: 1, overflow: TextOverflow.ellipsis)),
          if (isToday)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
              decoration: BoxDecoration(
                color: aptColor, borderRadius: BorderRadius.circular(8)),
              child: const Text('Heute',
                style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w700)),
            ),
        ]),
        const SizedBox(height: 3),
        Text(dateStr, style: TextStyle(
          fontSize: 12, color: Theme.of(context).colorScheme.onSurfaceVariant)),
        if (timeStr.isNotEmpty) ...[
          const SizedBox(height: 2),
          Row(children: [
            Icon(Icons.schedule_rounded, size: 12, color: aptColor),
            const SizedBox(width: 4),
            Text(timeStr + ' Uhr',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: aptColor)),
            if (treatName.isNotEmpty) ...[
              const SizedBox(width: 8),
              Expanded(child: Text('· $treatName',
                style: TextStyle(fontSize: 11,
                  color: Theme.of(context).colorScheme.onSurfaceVariant),
                maxLines: 1, overflow: TextOverflow.ellipsis)),
            ],
          ]),
        ],
      ])),
    ]);
  }

  // ── Helpers ───────────────────────────────────────────────

  String _gender(String g) => switch (g) {
    'männlich'    => '♂ Männlich',
    'weiblich'    => '♀ Weiblich',
    'kastriert'   => '⚲ Kastriert',
    'sterilisiert'=> '⚲ Sterilisiert',
    _             => 'Unbekannt',
  };

  String _date(String? d) {
    if (d == null || d.isEmpty) return '—';
    try {
      final dt = DateTime.parse(d);
      final age = DateTime.now().difference(dt).inDays ~/ 365;
      return '${DateFormat('dd.MM.yyyy').format(dt)} ($age J.)';
    } catch (_) { return d; }
  }

  String _statusLabel(String s) => switch (s) {
    'active'   => 'Aktiv',
    'inactive' => 'Inaktiv',
    'deceased' => 'Verstorben',
    _          => s,
  };

  // ── New Appointment from patient ─────────────────────────

  void _showNewAppointmentSheet(Map<String, dynamic> p) {
    final titleCtrl = TextEditingController(text: p['name'] as String? ?? '');
    final notesCtrl = TextEditingController();
    DateTime selDate = DateTime.now().add(const Duration(days: 1));
    TimeOfDay selTime = const TimeOfDay(hour: 10, minute: 0);

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSS) => Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16),
                decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
              Text('Neuer Termin', style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(height: 16),
              TextField(controller: titleCtrl, decoration: InputDecoration(
                labelText: 'Titel *',
                prefixIcon: const Icon(Icons.pets_rounded),
                hintText: p['name'] as String? ?? '',
              )),
              const SizedBox(height: 12),
              Row(children: [
                Expanded(child: InkWell(
                  borderRadius: BorderRadius.circular(8),
                  onTap: () async {
                    final d = await showDatePicker(context: ctx,
                      initialDate: selDate, firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 365)));
                    if (d != null) setSS(() => selDate = d);
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
                    if (t != null) setSS(() => selTime = t);
                  },
                  child: InputDecorator(
                    decoration: const InputDecoration(labelText: 'Uhrzeit', prefixIcon: Icon(Icons.access_time_rounded)),
                    child: Text('${selTime.hour.toString().padLeft(2,'0')}:${selTime.minute.toString().padLeft(2,'0')}'),
                  ),
                )),
              ]),
              const SizedBox(height: 12),
              TextField(controller: notesCtrl, decoration: const InputDecoration(
                labelText: 'Notizen', prefixIcon: Icon(Icons.notes_rounded))),
              const SizedBox(height: 20),
              SizedBox(width: double.infinity, child: FilledButton.icon(
                icon: const Icon(Icons.save_rounded),
                label: const Text('Termin erstellen'),
                onPressed: () async {
                  if (titleCtrl.text.trim().isEmpty) return;
                  Navigator.pop(ctx);
                  final start = DateTime(selDate.year, selDate.month, selDate.day, selTime.hour, selTime.minute);
                  final end   = start.add(const Duration(hours: 1));
                  try {
                    await _api.appointmentCreate({
                      'title':      titleCtrl.text.trim(),
                      'patient_id': widget.id,
                      'owner_id':   p['owner_id'],
                      'start':      start.toIso8601String(),
                      'end':        end.toIso8601String(),
                      'notes':      notesCtrl.text.trim(),
                    });
                    if (mounted) ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Termin erstellt ✓'), backgroundColor: Colors.green));
                  } catch (e) {
                    if (mounted) ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text(e.toString()), backgroundColor: AppTheme.danger));
                  }
                },
              )),
            ]),
          ),
        ),
      ),
    );
  }

  // ── Bottom Sheet: Add entry ───────────────────────────────

  void _showAddBottomSheet() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => _AddEntrySheet(
        onTextEntry: (type, title, content, treatmentTypeId) async {
          Navigator.pop(ctx);
          try {
            await _api.patientTimelineCreate(widget.id, {
              'type':    type,
              'title':   title,
              'content': content,
              'entry_date': DateTime.now().toIso8601String().substring(0, 10),
              if (treatmentTypeId != null) 'treatment_type_id': treatmentTypeId,
            });
            _load();
          } catch (e) {
            if (mounted) ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(e.toString()), backgroundColor: AppTheme.danger));
          }
        },
        onFileEntry: (type) async {
          Navigator.pop(ctx);
          await _pickAndUpload(type);
        },
      ),
    );
  }

  Future<void> _pickAndUpload(String type) async {
    FileType ft;
    List<String>? exts;
    if (type == 'video') {
      ft = FileType.video;
    } else if (type == 'photo') {
      ft = FileType.image;
    } else {
      ft = FileType.custom;
      exts = ['pdf', 'doc', 'docx', 'txt'];
    }

    final result = await FilePicker.platform.pickFiles(type: ft, allowedExtensions: exts);
    if (result == null || result.files.single.path == null) return;

    final file = File(result.files.single.path!);
    final name = result.files.single.name;
    setState(() => _uploading = true);
    try {
      await _api.patientTimelineUpload(
        widget.id, file,
        title: name,
        type: type,
      );
      _load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppTheme.danger));
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }
}

// ── Add Entry Bottom Sheet ────────────────────────────────────────────────────

class _AddEntrySheet extends StatefulWidget {
  final Future<void> Function(String type, String title, String content, int? treatmentTypeId) onTextEntry;
  final Future<void> Function(String type) onFileEntry;
  const _AddEntrySheet({required this.onTextEntry, required this.onFileEntry});

  @override
  State<_AddEntrySheet> createState() => _AddEntrySheetState();
}

class _AddEntrySheetState extends State<_AddEntrySheet> {
  final _api         = ApiService();
  final _titleCtrl   = TextEditingController();
  String _type       = 'note';
  String _htmlContent = '';
  bool   _showForm   = false;
  bool   _loadingTx  = false;

  List<Map<String, dynamic>> _treatmentTypes = [];
  int? _selectedTreatmentTypeId;

  @override
  void dispose() {
    _titleCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadTreatmentTypes() async {
    if (_treatmentTypes.isNotEmpty) return;
    setState(() => _loadingTx = true);
    try {
      final list = await _api.treatmentTypes();
      setState(() {
        _treatmentTypes = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _loadingTx = false;
      });
    } catch (_) {
      setState(() => _loadingTx = false);
    }
  }

  void _openEditor(BuildContext ctx) async {
    final result = await Navigator.push<String>(
      ctx,
      MaterialPageRoute(
        builder: (_) => HtmlEditorScreen(
          title: _typeLabel(_type),
          initialHtml: _htmlContent,
        ),
        fullscreenDialog: true,
      ),
    );
    if (result != null) setState(() => _htmlContent = result);
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: AnimatedSize(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOutCubic,
        child: _showForm ? _buildForm(context) : _buildOptions(),
      ),
    );
  }

  Widget _buildOptions() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(width: 40, height: 4, decoration: BoxDecoration(
          color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
        const SizedBox(height: 16),
        Text('Eintrag hinzufügen', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
        const SizedBox(height: 20),
        Row(children: [
          _OptionTile(icon: Icons.note_rounded, label: 'Notiz', color: AppTheme.success,
              onTap: () { setState(() { _type = 'note'; _showForm = true; }); }),
          const SizedBox(width: 10),
          _OptionTile(icon: Icons.medical_services_rounded, label: 'Behandlung', color: AppTheme.primary,
              onTap: () { setState(() { _type = 'treatment'; _showForm = true; }); _loadTreatmentTypes(); }),
        ]),
        const SizedBox(height: 10),
        Row(children: [
          _OptionTile(icon: Icons.photo_camera_rounded, label: 'Foto', color: AppTheme.secondary,
              onTap: () => widget.onFileEntry('photo')),
          const SizedBox(width: 10),
          _OptionTile(icon: Icons.videocam_rounded, label: 'Video', color: AppTheme.tertiary,
              onTap: () => widget.onFileEntry('video')),
        ]),
        const SizedBox(height: 10),
        Row(children: [
          _OptionTile(icon: Icons.attach_file_rounded, label: 'Dokument', color: AppTheme.warning,
              onTap: () => widget.onFileEntry('document')),
          const SizedBox(width: 10),
          _OptionTile(icon: Icons.text_snippet_rounded, label: 'Sonstiges', color: Colors.grey,
              onTap: () { setState(() { _type = 'other'; _showForm = true; }); }),
        ]),
      ]),
    );
  }

  Widget _buildForm(BuildContext context) {
    final hasContent = _htmlContent.trim().isNotEmpty;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(width: 40, height: 4, decoration: BoxDecoration(
          color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
        const SizedBox(height: 12),
        Row(children: [
          IconButton(
            icon: const Icon(Icons.arrow_back_rounded),
            onPressed: () => setState(() { _showForm = false; _htmlContent = ''; _selectedTreatmentTypeId = null; }),
          ),
          Expanded(child: Text(_typeLabel(_type),
            style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700))),
        ]),
        const SizedBox(height: 12),
        // Treatment type dropdown
        if (_type == 'treatment') ...[
          if (_loadingTx)
            const Padding(
              padding: EdgeInsets.only(bottom: 12),
              child: LinearProgressIndicator(),
            )
          else
            DropdownButtonFormField<int>(
              initialValue: _selectedTreatmentTypeId,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Behandlungsart',
                prefixIcon: Icon(Icons.category_rounded),
              ),
              hint: const Text('Behandlungsart wählen…'),
              items: _treatmentTypes.map((t) => DropdownMenuItem<int>(
                value: t['id'] as int,
                child: Row(children: [
                  Container(
                    width: 12, height: 12,
                    margin: const EdgeInsets.only(right: 8),
                    decoration: BoxDecoration(
                      color: _parseColor(t['color'] as String? ?? '#4f7cff'),
                      shape: BoxShape.circle,
                    ),
                  ),
                  Expanded(child: Text(t['name'] as String? ?? '', overflow: TextOverflow.ellipsis)),
                ]),
              )).toList(),
              onChanged: (v) => setState(() => _selectedTreatmentTypeId = v),
            ),
          const SizedBox(height: 12),
        ],
        // Title
        TextField(
          controller: _titleCtrl,
          decoration: const InputDecoration(
            labelText: 'Titel *',
            prefixIcon: Icon(Icons.title_rounded),
          ),
        ),
        const SizedBox(height: 12),
        // HTML content preview / open editor button
        GestureDetector(
          onTap: () => _openEditor(context),
          child: Container(
            width: double.infinity,
            constraints: const BoxConstraints(minHeight: 80, maxHeight: 160),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              border: Border.all(color: Theme.of(context).colorScheme.outline),
              borderRadius: BorderRadius.circular(12),
              color: Theme.of(context).colorScheme.surfaceContainerHighest,
            ),
            child: hasContent
                ? Html(
                    data: _htmlContent,
                    style: {
                      'body': Style(margin: Margins.zero, padding: HtmlPaddings.zero, fontSize: FontSize(13)),
                    },
                  )
                : Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Icon(Icons.edit_note_rounded, color: Theme.of(context).colorScheme.onSurfaceVariant),
                    const SizedBox(width: 8),
                    Text('Inhalt bearbeiten (Rich Text)…',
                      style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
                  ]),
          ),
        ),
        if (hasContent)
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              icon: const Icon(Icons.edit_rounded, size: 14),
              label: const Text('Bearbeiten'),
              onPressed: () => _openEditor(context),
            ),
          ),
        const SizedBox(height: 20),
        SizedBox(
          width: double.infinity,
          child: FilledButton.icon(
            icon: const Icon(Icons.save_rounded),
            label: const Text('Speichern'),
            onPressed: () {
              if (_titleCtrl.text.trim().isEmpty) return;
              widget.onTextEntry(
                _type,
                _titleCtrl.text.trim(),
                _htmlContent.trim(),
                _selectedTreatmentTypeId,
              );
            },
          ),
        ),
      ]),
    );
  }

  String _typeLabel(String t) => switch (t) {
    'treatment' => 'Behandlung',
    'other'     => 'Sonstiges',
    _           => 'Notiz',
  };

  Color _parseColor(String hex) {
    try {
      final h = hex.replaceAll('#', '');
      return Color(int.parse('FF$h', radix: 16));
    } catch (_) {
      return AppTheme.primary;
    }
  }
}

class _OptionTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  const _OptionTile({required this.icon, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Material(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 16),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Icon(icon, color: color, size: 28),
              const SizedBox(height: 6),
              Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w600, fontSize: 12)),
            ]),
          ),
        ),
      ),
    );
  }
}

// ── Timeline Card ─────────────────────────────────────────────────────────────

class _TimelineCard extends StatelessWidget {
  final Map<String, dynamic> entry;
  final bool isFirst, isLast;
  const _TimelineCard({required this.entry, required this.isFirst, required this.isLast});

  static const _typeConfig = {
    'treatment': (Icons.medical_services_rounded, AppTheme.primary),
    'photo':     (Icons.photo_rounded,             AppTheme.secondary),
    'video':     (Icons.videocam_rounded,           AppTheme.tertiary),
    'document':  (Icons.description_rounded,        AppTheme.warning),
    'note':      (Icons.note_rounded,               AppTheme.success),
  };

  @override
  Widget build(BuildContext context) {
    final type  = entry['type'] as String? ?? 'note';
    final cfg   = _typeConfig[type] ?? (Icons.note_rounded, AppTheme.success);
    final icon  = cfg.$1;
    final color = cfg.$2;

    String dateStr = '';
    try {
      final d = DateTime.parse(entry['entry_date'] as String? ?? '');
      dateStr = DateFormat('dd.MM.yy', 'de_DE').format(d);
    } catch (_) {}

    final fileUrl  = entry['file_url'] as String?;
    final hasFile  = fileUrl != null && fileUrl.isNotEmpty;
    final isMedia  = hasFile && (type == 'photo' || type == 'video' || type == 'document');
    final isPdf    = type == 'document';
    final fullUrl  = hasFile ? ApiService.mediaUrl(fileUrl) : null;

    return IntrinsicHeight(
      child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        // Timeline line
        SizedBox(width: 40, child: Column(children: [
          if (!isFirst) Container(width: 2, height: 12, color: color.withValues(alpha: 0.3)),
          Container(
            width: 36, height: 36,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              shape: BoxShape.circle,
              border: Border.all(color: color.withValues(alpha: 0.4), width: 2),
            ),
            child: Icon(icon, color: color, size: 17),
          ),
          if (!isLast) Expanded(child: Container(width: 2, color: color.withValues(alpha: 0.3))),
        ])),
        const SizedBox(width: 12),
        // Card
        Expanded(
          child: Container(
            margin: const EdgeInsets.only(bottom: 14),
            decoration: BoxDecoration(
              color: Theme.of(context).cardTheme.color,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: color.withValues(alpha: 0.18)),
            ),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
                child: Row(children: [
                  Expanded(child: Text(
                    entry['title'] as String? ?? '',
                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
                  )),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(dateStr, style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w600)),
                  ),
                ]),
              ),
              if ((entry['content'] as String? ?? '').isNotEmpty)
                Padding(
                  padding: const EdgeInsets.fromLTRB(14, 0, 14, 4),
                  child: Html(
                    data: entry['content'] as String,
                    style: {
                      'body': Style(
                        margin: Margins.zero,
                        padding: HtmlPaddings.zero,
                        fontSize: FontSize(12),
                        color: Theme.of(context).textTheme.bodySmall?.color,
                        maxLines: 5,
                        textOverflow: TextOverflow.ellipsis,
                      ),
                    },
                  ),
                ),
              // Media preview
              if (isMedia && fullUrl != null)
                Padding(
                  padding: const EdgeInsets.fromLTRB(14, 0, 14, 12),
                  child: MediaThumbnail(url: fullUrl, isVideo: type == 'video', isPdf: isPdf),
                ),
              if (entry['user_name'] != null)
                Padding(
                  padding: const EdgeInsets.fromLTRB(14, 0, 14, 10),
                  child: Row(children: [
                    Icon(Icons.person_rounded, size: 12, color: Theme.of(context).colorScheme.onSurfaceVariant),
                    const SizedBox(width: 4),
                    Text(entry['user_name'] as String,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: Theme.of(context).colorScheme.onSurfaceVariant)),
                  ]),
                ),
            ]),
          ),
        ),
      ]),
    );
  }
}

// ── Exercise Card ─────────────────────────────────────────────────────────────

class _ExerciseCard extends StatelessWidget {
  final Map<String, dynamic> exercise;
  final VoidCallback? onDelete;
  const _ExerciseCard({required this.exercise, this.onDelete});

  @override
  Widget build(BuildContext context) {
    final sets  = exercise['sets'] as int? ?? exercise['repetitions_sets'] as int?;
    final reps  = exercise['repetitions'] as int? ?? exercise['reps'] as int?;
    final dur   = exercise['duration'] as int? ?? exercise['duration_seconds'] as int?;
    final notes = exercise['notes'] as String? ?? exercise['description'] as String? ?? '';
    final videoUrl = exercise['video_url'] as String?;

    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppTheme.secondary.withValues(alpha: 0.25)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(
              width: 36, height: 36,
              decoration: BoxDecoration(
                color: AppTheme.secondary.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(Icons.fitness_center_rounded, color: AppTheme.secondary, size: 18),
            ),
            const SizedBox(width: 10),
            Expanded(child: Text(
              exercise['name'] as String? ?? exercise['title'] as String? ?? '—',
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
            )),
            if (onDelete != null)
              IconButton(
                icon: Icon(Icons.delete_outline_rounded, size: 18, color: Colors.red.shade300),
                onPressed: onDelete,
                tooltip: 'Löschen',
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
              ),
          ]),
          if (notes.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(notes, style: TextStyle(fontSize: 12, color: Colors.grey.shade600, height: 1.4),
              maxLines: 3, overflow: TextOverflow.ellipsis),
          ],
          if (sets != null || reps != null || dur != null) ...[
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 4, children: [
              if (sets != null) _chip('${sets}× Sätze', AppTheme.secondary),
              if (reps != null) _chip('${reps}× Wdh.', AppTheme.primary),
              if (dur  != null) _chip('${dur} Sek.', AppTheme.tertiary),
            ]),
          ],
          if (videoUrl != null && videoUrl.isNotEmpty) ...[
            const SizedBox(height: 8),
            Row(children: [
              Icon(Icons.play_circle_outline_rounded, size: 14, color: AppTheme.tertiary),
              const SizedBox(width: 4),
              Text('Video verfügbar', style: TextStyle(fontSize: 11, color: AppTheme.tertiary, fontWeight: FontWeight.w600)),
            ]),
          ],
        ]),
      ),
    );
  }

  Widget _chip(String label, Color color) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
    decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(20)),
    child: Text(label, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
  );
}

// ── Homework Card ─────────────────────────────────────────────────────────────

class _HomeworkCard extends StatelessWidget {
  final Map<String, dynamic> homework;
  const _HomeworkCard({required this.homework});

  String _fmt(String? d) {
    if (d == null) return '—';
    try { return DateFormat('dd.MM.yyyy', 'de_DE').format(DateTime.parse(d)); } catch (_) { return d; }
  }

  @override
  Widget build(BuildContext context) {
    final title    = homework['title'] as String? ?? homework['name'] as String? ?? '—';
    final desc     = homework['description'] as String? ?? homework['content'] as String? ?? '';
    final dueDate  = homework['due_date'] as String? ?? homework['deadline'] as String?;
    final done     = homework['completed'] == true || homework['done'] == true ||
                     homework['status'] == 'completed' || homework['status'] == 'done';
    final color    = done ? AppTheme.success : AppTheme.primary;

    // tasks / exercises sub-list
    final tasks = List<dynamic>.from(homework['tasks'] as List? ?? homework['exercises'] as List? ?? []);

    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
          child: Row(children: [
            Container(
              width: 36, height: 36,
              decoration: BoxDecoration(color: color.withValues(alpha: 0.1), shape: BoxShape.circle),
              child: Icon(done ? Icons.check_circle_rounded : Icons.assignment_rounded, color: color, size: 18),
            ),
            const SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
              if (dueDate != null)
                Text('Fällig: ${_fmt(dueDate)}',
                  style: TextStyle(fontSize: 11, color: done ? AppTheme.success : AppTheme.warning,
                    fontWeight: FontWeight.w600)),
            ])),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(20)),
              child: Text(done ? 'Erledigt' : 'Offen',
                style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w700)),
            ),
          ]),
        ),
        if (desc.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 8),
            child: Text(desc, style: TextStyle(fontSize: 12, color: Colors.grey.shade600, height: 1.4),
              maxLines: 3, overflow: TextOverflow.ellipsis),
          ),
        if (tasks.isNotEmpty) ...[
          const Divider(height: 1),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 8, 14, 12),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${tasks.length} Aufgaben',
                style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: color)),
              const SizedBox(height: 6),
              ...tasks.take(3).map((t) {
                final task = t as Map;
                return Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Row(children: [
                    Icon(Icons.radio_button_unchecked_rounded, size: 12, color: Colors.grey.shade400),
                    const SizedBox(width: 6),
                    Expanded(child: Text(task['title'] as String? ?? task['name'] as String? ?? '—',
                      style: const TextStyle(fontSize: 12))),
                  ]),
                );
              }),
              if (tasks.length > 3)
                Text('+ ${tasks.length - 3} weitere',
                  style: TextStyle(fontSize: 11, color: Colors.grey.shade400)),
            ]),
          ),
        ],
      ]),
    );
  }
}

// ── Modern Info Card ──────────────────────────────────────────────────────────

class _ModernInfoCard extends StatelessWidget {
  final String title;
  final IconData icon;
  final Color color;
  final Map<String, String> items;
  const _ModernInfoCard({required this.title, required this.icon, required this.color, required this.items});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withValues(alpha: 0.18)),
      ),
      child: Column(children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.08),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
          ),
          child: Row(children: [
            Icon(icon, color: color, size: 18),
            const SizedBox(width: 8),
            Text(title, style: TextStyle(fontWeight: FontWeight.w700, color: color, fontSize: 14)),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(children: items.entries.map((e) => Padding(
            padding: const EdgeInsets.symmetric(vertical: 5),
            child: Row(children: [
              SizedBox(width: 110, child: Text(e.key,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant, fontWeight: FontWeight.w500))),
              Expanded(child: Text(e.value.isEmpty ? '—' : e.value,
                style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13))),
            ]),
          )).toList()),
        ),
      ]),
    );
  }
}

