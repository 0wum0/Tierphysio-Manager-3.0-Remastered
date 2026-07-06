import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import '../services/portal_api_service.dart';
import '../widgets/auth_image.dart';
import 'media_viewer_screen.dart';

class PetDetailScreen extends StatefulWidget {
  final int petId;
  const PetDetailScreen({super.key, required this.petId});
  @override
  State<PetDetailScreen> createState() => _PetDetailScreenState();
}

class _PetDetailScreenState extends State<PetDetailScreen>
    with SingleTickerProviderStateMixin {
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;
  late final TabController _tabs;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 4, vsync: this);
    _load();
  }

  @override
  void dispose() { _tabs.dispose(); super.dispose(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final auth = context.read<PortalAuthService>();
      final data = await PortalApiService(token: auth.token).getPetDetail(widget.petId);
      if (mounted) setState(() { _data = data; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final name = _data?['name'] as String? ?? 'Tierprofil';
    return Scaffold(
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _buildError()
              : _buildBody(name),
    );
  }

  Widget _buildError() => Scaffold(
    appBar: AppBar(title: const Text('Tierprofil')),
    body: Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
      const Icon(Icons.error_outline_rounded, size: 48, color: AppTheme.danger),
      const SizedBox(height: 12),
      Text(_error!, textAlign: TextAlign.center),
      const SizedBox(height: 24),
      FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut')),
    ]))),
  );

  Widget _buildBody(String name) {
    final pet        = _data!;
    final photoUrl   = pet['photo_url'] as String?;
    final timeline   = List<dynamic>.from(pet['timeline'] ?? []);
    final exercises  = List<dynamic>.from(pet['exercises'] ?? []);
    final homework   = List<dynamic>.from(pet['homework_plans'] ?? []);
    final befunde    = List<dynamic>.from(pet['befunde'] ?? []);
    final cs         = Theme.of(context).colorScheme;

    return NestedScrollView(
      headerSliverBuilder: (context, _) => [
        SliverAppBar(
          expandedHeight: 240,
          pinned: true,
          flexibleSpace: FlexibleSpaceBar(
            title: Text(name, style: const TextStyle(fontWeight: FontWeight.w700)),
            background: photoUrl != null
                ? AuthImage(url: photoUrl, fit: BoxFit.cover)
                : Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter, end: Alignment.bottomCenter,
                        colors: [AppTheme.primary.withValues(alpha: 0.6), AppTheme.primary.withValues(alpha: 0.2)],
                      ),
                    ),
                    child: Center(child: Icon(Icons.pets_rounded, size: 80, color: Colors.white.withValues(alpha: 0.7))),
                  ),
          ),
          actions: [
            IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load),
          ],
        ),
        SliverPersistentHeader(
          pinned: true,
          delegate: _TabBarDelegate(
            TabBar(
              controller: _tabs,
              labelColor: AppTheme.primary,
              indicatorColor: AppTheme.primary,
              unselectedLabelColor: cs.onSurfaceVariant,
              labelStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 12),
              tabs: [
                Tab(text: 'Profil', icon: const Icon(Icons.info_outline_rounded, size: 18)),
                Tab(text: 'Verlauf (${timeline.length})', icon: const Icon(Icons.history_rounded, size: 18)),
                Tab(text: 'Übungen (${exercises.length})', icon: const Icon(Icons.fitness_center_rounded, size: 18)),
                Tab(text: 'Hausaufgaben (${homework.length})', icon: const Icon(Icons.assignment_rounded, size: 18)),
              ],
            ),
            Theme.of(context).colorScheme.surface,
          ),
        ),
      ],
      body: TabBarView(
        controller: _tabs,
        children: [
          _ProfileTab(pet: pet, befunde: befunde),
          _TimelineTab(timeline: timeline, petId: widget.petId),
          _ExercisesTab(exercises: exercises),
          _HomeworkTab(plans: homework, petId: widget.petId),
        ],
      ),
    );
  }
}

// ── Tab: Profil ───────────────────────────────────────────────────────────────

class _ProfileTab extends StatelessWidget {
  final Map<String, dynamic> pet;
  final List<dynamic> befunde;
  const _ProfileTab({required this.pet, required this.befunde});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;

    String? formatDate(String? raw) {
      if (raw == null || raw.isEmpty) return null;
      try { return DateFormat('dd.MM.yyyy').format(DateTime.parse(raw)); }
      catch (_) { return raw; }
    }

    return ListView(padding: const EdgeInsets.fromLTRB(16, 16, 16, 32), children: [
      // Basic info card
      _Card(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _SectionTitle('Stammdaten'),
        _Row('Tierart', pet['species'] as String? ?? '–'),
        _Row('Rasse', pet['breed'] as String? ?? '–'),
        _Row('Geschlecht', pet['gender'] as String? ?? '–'),
        _Row('Geburtsdatum', formatDate(pet['birth_date']) ?? '–'),
        if (pet['weight'] != null) _Row('Gewicht', '${pet['weight']} kg'),
        if ((pet['color'] as String? ?? '').isNotEmpty) _Row('Farbe', pet['color'] as String),
        if ((pet['chip_number'] as String? ?? '').isNotEmpty) _Row('Chip-Nr.', pet['chip_number'] as String),
      ])),

      if ((pet['notes'] as String? ?? '').isNotEmpty) ...[
        const SizedBox(height: 12),
        _Card(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          _SectionTitle('Notizen'),
          const SizedBox(height: 8),
          Text(pet['notes'] as String, style: TextStyle(color: cs.onSurfaceVariant, height: 1.5)),
        ])),
      ],

      if (befunde.isNotEmpty) ...[
        const SizedBox(height: 12),
        _Card(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          _SectionTitle('Befundbögen'),
          const SizedBox(height: 4),
          ...befunde.map((b) => _BefundRow(befund: b as Map<String, dynamic>)),
        ])),
      ],
    ]);
  }
}

class _BefundRow extends StatelessWidget {
  final Map<String, dynamic> befund;
  const _BefundRow({required this.befund});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Container(width: 36, height: 36,
        decoration: BoxDecoration(color: AppTheme.tertiary.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)),
        child: const Icon(Icons.description_rounded, color: AppTheme.tertiary, size: 18)),
      title: Text(befund['title'] as String? ?? 'Befundbogen',
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
      subtitle: Text(befund['datum'] as String? ?? '', style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 12)),
      trailing: IconButton(
        icon: const Icon(Icons.picture_as_pdf_rounded, color: AppTheme.danger),
        onPressed: () async {
          final url = befund['pdf_url'] as String?;
          if (url != null) {
            final uri = Uri.tryParse(url);
            if (uri != null) await launchUrl(uri, mode: LaunchMode.externalApplication);
          }
        },
      ),
    );
  }
}

// ── Tab: Verlauf ──────────────────────────────────────────────────────────────

class _TimelineTab extends StatelessWidget {
  final List<dynamic> timeline;
  final int petId;
  const _TimelineTab({required this.timeline, required this.petId});

  @override
  Widget build(BuildContext context) {
    if (timeline.isEmpty) {
      return _EmptyState(icon: Icons.history_rounded, label: 'Kein Verlauf vorhanden.');
    }
    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
      itemCount: timeline.length,
      itemBuilder: (_, i) => _TimelineCard(entry: timeline[i] as Map<String, dynamic>),
    );
  }
}

class _TimelineCard extends StatelessWidget {
  final Map<String, dynamic> entry;
  const _TimelineCard({required this.entry});

  static const _typeLabels = {
    'treatment': ('Behandlung', AppTheme.primary, Icons.medical_services_rounded),
    'note':      ('Notiz',      AppTheme.secondary, Icons.note_rounded),
    'photo':     ('Foto',       AppTheme.success, Icons.photo_rounded),
    'document':  ('Dokument',   AppTheme.tertiary, Icons.description_rounded),
    'payment':   ('Zahlung',    AppTheme.warning, Icons.payment_rounded),
    'other':     ('Sonstiges',  Colors.grey, Icons.circle_outlined),
  };

  @override
  Widget build(BuildContext context) {
    final type   = entry['type'] as String? ?? 'other';
    final title  = entry['title'] as String? ?? type;
    final cs     = Theme.of(context).colorScheme;
    final (label, color, icon) = _typeLabels[type] ?? _typeLabels['other']!;
    final media  = List<dynamic>.from(entry['media'] ?? []);

    String? dateStr;
    try {
      final raw = entry['entry_date'] as String? ?? entry['created_at'] as String? ?? '';
      if (raw.isNotEmpty) {
        dateStr = DateFormat('dd.MM.yyyy · HH:mm', 'de').format(DateTime.parse(raw).toLocal());
      }
    } catch (_) {}

    return Card(margin: const EdgeInsets.only(bottom: 10), child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ListTile(
          contentPadding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
          leading: Container(width: 40, height: 40,
            decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
            child: Icon(icon, color: color, size: 20)),
          title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
          subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Container(
                margin: const EdgeInsets.only(top: 4),
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(4)),
                child: Text(label, style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w700)),
              ),
              if (dateStr != null) ...[
                const SizedBox(width: 8),
                Text(dateStr, style: TextStyle(color: cs.onSurfaceVariant, fontSize: 11)),
              ],
            ]),
            if (entry['user_name'] != null) ...[
              const SizedBox(height: 2),
              Text('von ${entry['user_name']}', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 11)),
            ],
          ]),
        ),
        if ((entry['content'] as String? ?? '').isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: Text(entry['content'] as String, style: TextStyle(color: cs.onSurfaceVariant, height: 1.5, fontSize: 13)),
          ),
        if (media.isNotEmpty) _MediaStrip(media: media),
        if (media.isNotEmpty) const SizedBox(height: 8),
      ],
    ));
  }
}

class _MediaStrip extends StatelessWidget {
  final List<dynamic> media;
  const _MediaStrip({required this.media});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 100,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: media.length,
        itemBuilder: (_, i) {
          final m    = media[i] as Map<String, dynamic>;
          final kind = m['kind'] as String? ?? 'file';
          final url  = m['url'] as String? ?? '';
          final name = m['filename'] as String? ?? 'Datei';

          return GestureDetector(
            onTap: () => Navigator.push(context, MaterialPageRoute(
              builder: (_) => MediaViewerScreen(url: url, title: name, kind: kind))),
            child: Container(
              width: 100, margin: const EdgeInsets.only(right: 8),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.surfaceContainerHighest,
                borderRadius: BorderRadius.circular(10),
              ),
              clipBehavior: Clip.hardEdge,
              child: kind == 'image'
                  ? AuthImage(url: url, fit: BoxFit.cover)
                  : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(
                        kind == 'document' ? Icons.description_rounded : Icons.insert_drive_file_rounded,
                        size: 32, color: AppTheme.tertiary),
                      const SizedBox(height: 4),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        child: Text(name, style: const TextStyle(fontSize: 10), maxLines: 2,
                          overflow: TextOverflow.ellipsis, textAlign: TextAlign.center),
                      ),
                    ]),
            ),
          );
        },
      ),
    );
  }
}

// ── Tab: Übungen ──────────────────────────────────────────────────────────────

class _ExercisesTab extends StatelessWidget {
  final List<dynamic> exercises;
  const _ExercisesTab({required this.exercises});

  @override
  Widget build(BuildContext context) {
    if (exercises.isEmpty) {
      return _EmptyState(icon: Icons.fitness_center_rounded, label: 'Keine Übungen zugewiesen.');
    }
    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
      itemCount: exercises.length,
      itemBuilder: (_, i) => _ExerciseCard(ex: exercises[i] as Map<String, dynamic>, index: i + 1),
    );
  }
}

class _ExerciseCard extends StatefulWidget {
  final Map<String, dynamic> ex;
  final int index;
  const _ExerciseCard({required this.ex, required this.index});
  @override
  State<_ExerciseCard> createState() => _ExerciseCardState();
}

class _ExerciseCardState extends State<_ExerciseCard> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final ex     = widget.ex;
    final cs     = Theme.of(context).colorScheme;
    final title  = ex['title'] as String? ?? '–';
    final desc   = ex['description'] as String? ?? '';
    final video  = ex['video_url'] as String?;
    final imgUrl = ex['image'] as String?;
    final hasMore = desc.isNotEmpty || video != null || imgUrl != null;

    return Card(margin: const EdgeInsets.only(bottom: 10), child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ListTile(
          leading: CircleAvatar(radius: 18,
            backgroundColor: AppTheme.success.withValues(alpha: 0.12),
            child: Text('${widget.index}', style: const TextStyle(color: AppTheme.success, fontWeight: FontWeight.w800, fontSize: 13))),
          title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
          trailing: hasMore ? IconButton(
            icon: Icon(_expanded ? Icons.expand_less_rounded : Icons.expand_more_rounded),
            onPressed: () => setState(() => _expanded = !_expanded),
          ) : null,
        ),
        if (_expanded) ...[
          if (imgUrl != null)
            ClipRRect(
              borderRadius: const BorderRadius.vertical(bottom: Radius.circular(12)),
              child: AuthImage(url: imgUrl, height: 180, fit: BoxFit.cover, width: double.infinity),
            ),
          if (desc.isNotEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
              child: Text(desc, style: TextStyle(color: cs.onSurfaceVariant, height: 1.5, fontSize: 13)),
            ),
          if (video != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
              child: FilledButton.tonalIcon(
                onPressed: () async {
                  final uri = Uri.tryParse(video);
                  if (uri != null) await launchUrl(uri, mode: LaunchMode.externalApplication);
                },
                icon: const Icon(Icons.play_circle_outline_rounded),
                label: const Text('Video ansehen'),
              ),
            ),
        ],
      ],
    ));
  }
}

// ── Tab: Hausaufgaben ─────────────────────────────────────────────────────────

class _HomeworkTab extends StatefulWidget {
  final List<dynamic> plans;
  final int petId;
  const _HomeworkTab({required this.plans, required this.petId});
  @override
  State<_HomeworkTab> createState() => _HomeworkTabState();
}

class _HomeworkTabState extends State<_HomeworkTab> {
  late List<Map<String, dynamic>> _plans;

  @override
  void initState() {
    super.initState();
    _plans = widget.plans.map((p) => Map<String, dynamic>.from(p as Map)).toList();
  }

  @override
  Widget build(BuildContext context) {
    if (_plans.isEmpty) {
      return _EmptyState(icon: Icons.assignment_rounded, label: 'Keine Hausaufgaben vorhanden.');
    }
    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
      itemCount: _plans.length,
      itemBuilder: (_, i) => _HomeworkPlanCard(
        plan: _plans[i],
        onCheckChanged: (planId, taskId, checked) => _toggle(planId, taskId, checked),
      ),
    );
  }

  Future<void> _toggle(int planId, int taskId, bool checked) async {
    try {
      final auth = context.read<PortalAuthService>();
      await PortalApiService(token: auth.token).toggleHomeworkTask(planId, taskId, checked: checked);
      // Update local state
      setState(() {
        for (final plan in _plans) {
          if ((int.tryParse(plan['id'].toString()) ?? 0) == planId) {
            final checks = Map<String, dynamic>.from(plan['checks'] as Map? ?? {});
            checks[taskId.toString()] = checked;
            plan['checks'] = checks;
          }
        }
      });
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Fehler: $e'), behavior: SnackBarBehavior.floating));
    }
  }
}

class _HomeworkPlanCard extends StatefulWidget {
  final Map<String, dynamic> plan;
  final void Function(int planId, int taskId, bool checked) onCheckChanged;
  const _HomeworkPlanCard({required this.plan, required this.onCheckChanged});
  @override
  State<_HomeworkPlanCard> createState() => _HomeworkPlanCardState();
}

class _HomeworkPlanCardState extends State<_HomeworkPlanCard> {
  bool _expanded = true;

  @override
  Widget build(BuildContext context) {
    final plan    = widget.plan;
    final tasks   = List<dynamic>.from(plan['tasks'] ?? []);
    final checks  = Map<String, dynamic>.from(plan['checks'] as Map? ?? {});
    final planId  = int.tryParse(plan['id'].toString()) ?? 0;
    final done    = tasks.where((t) => checks[t['id'].toString()] == true).length;
    final cs      = Theme.of(context).colorScheme;
    final progress = tasks.isEmpty ? 0.0 : done / tasks.length;

    return Card(margin: const EdgeInsets.only(bottom: 12), child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ListTile(
          leading: Container(width: 40, height: 40,
            decoration: BoxDecoration(color: AppTheme.success.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
            child: const Icon(Icons.assignment_rounded, color: AppTheme.success, size: 20)),
          title: Text(
            plan['plan_date'] != null ? 'Übungsplan vom ${plan['plan_date']}' : (plan['title'] as String? ?? 'Übungsplan'),
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
          subtitle: tasks.isNotEmpty ? Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              ClipRRect(borderRadius: BorderRadius.circular(4), child:
                LinearProgressIndicator(value: progress, color: AppTheme.success,
                  backgroundColor: AppTheme.success.withValues(alpha: 0.15), minHeight: 6)),
              const SizedBox(height: 4),
              Text('$done / ${tasks.length} Aufgaben erledigt',
                style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12)),
            ]),
          ) : null,
          trailing: IconButton(
            icon: Icon(_expanded ? Icons.expand_less_rounded : Icons.expand_more_rounded),
            onPressed: () => setState(() => _expanded = !_expanded),
          ),
        ),
        if (_expanded && (plan['general_notes'] as String? ?? '').isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Text(plan['general_notes'] as String, style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13)),
          ),
        if (_expanded && tasks.isNotEmpty) ...[
          const Divider(height: 1),
          ...tasks.map((t) {
            final taskId = int.tryParse(t['id'].toString()) ?? 0;
            final isChecked = checks[taskId.toString()] == true;
            return CheckboxListTile(
              value: isChecked,
              onChanged: (v) => widget.onCheckChanged(planId, taskId, v ?? false),
              title: Text(
                t['title'] as String? ?? '–',
                style: TextStyle(
                  fontWeight: FontWeight.w500,
                  decoration: isChecked ? TextDecoration.lineThrough : null,
                  color: isChecked ? cs.onSurfaceVariant : null,
                ),
              ),
              subtitle: (t['frequency'] as String? ?? '').isNotEmpty
                  ? Text(t['frequency'] as String, style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12))
                  : (t['duration'] as String? ?? '').isNotEmpty
                      ? Text(t['duration'] as String, style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12))
                      : null,
              activeColor: AppTheme.success,
              checkboxShape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 16),
            );
          }),
          const SizedBox(height: 8),
        ],
      ],
    ));
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

class _Card extends StatelessWidget {
  final Widget child;
  const _Card({required this.child});
  @override
  Widget build(BuildContext context) => Card(child: Padding(
    padding: const EdgeInsets.all(16), child: child));
}

class _SectionTitle extends StatelessWidget {
  final String text;
  const _SectionTitle(this.text);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Text(text, style: TextStyle(
      fontWeight: FontWeight.w700, fontSize: 13,
      color: Theme.of(context).colorScheme.onSurfaceVariant,
      letterSpacing: 0.5,
    )),
  );
}

class _Row extends StatelessWidget {
  final String label;
  final String value;
  const _Row(this.label, this.value);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      SizedBox(width: 120, child: Text(label,
        style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 13))),
      Expanded(child: Text(value, style: const TextStyle(fontWeight: FontWeight.w500, fontSize: 13))),
    ]),
  );
}

class _EmptyState extends StatelessWidget {
  final IconData icon;
  final String label;
  const _EmptyState({required this.icon, required this.label});
  @override
  Widget build(BuildContext context) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(icon, size: 56, color: Theme.of(context).colorScheme.onSurfaceVariant.withValues(alpha: 0.3)),
    const SizedBox(height: 16),
    Text(label, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
  ]));
}

class _TabBarDelegate extends SliverPersistentHeaderDelegate {
  final TabBar tabBar;
  final Color color;
  const _TabBarDelegate(this.tabBar, this.color);
  @override double get minExtent => tabBar.preferredSize.height;
  @override double get maxExtent => tabBar.preferredSize.height;
  @override bool shouldRebuild(covariant _TabBarDelegate old) => old.tabBar != tabBar;
  @override Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) =>
      Container(color: color, child: tabBar);
}
