import 'package:flutter/material.dart';
import '../core/theme.dart';

class HomeworkDetailScreen extends StatelessWidget {
  final Map<String, dynamic> plan;
  const HomeworkDetailScreen({super.key, required this.plan});

  @override
  Widget build(BuildContext context) {
    final name      = plan['title'] as String? ?? plan['name'] as String? ?? 'Übungsplan';
    final exercises = List<dynamic>.from(plan['exercises'] ?? []);
    final cs        = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: Text(name)),
      body: ListView(padding: const EdgeInsets.only(bottom: 32), children: [
        Padding(padding: const EdgeInsets.all(20), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if ((plan['description'] as String? ?? '').isNotEmpty) ...[
            Text(plan['description'] as String, style: TextStyle(color: cs.onSurfaceVariant)),
            const SizedBox(height: 12),
          ],
          if (plan['patient_name'] != null) Row(children: [
            const Icon(Icons.pets_rounded, size: 16, color: AppTheme.primary),
            const SizedBox(width: 6),
            Text('Für: ${plan['patient_name']}', style: TextStyle(color: cs.onSurfaceVariant)),
          ]),
        ])),

        if (exercises.isEmpty)
          Padding(padding: const EdgeInsets.all(24), child: Text('Keine Übungen vorhanden.',
              style: TextStyle(color: cs.onSurfaceVariant)))
        else ...[
          Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 8), child:
            Text('Übungen (${exercises.length})', style: Theme.of(context).textTheme.titleMedium)),
          ...exercises.asMap().entries.map((e) => _ExerciseCard(index: e.key + 1, exercise: e.value as Map<String, dynamic>)),
        ],
      ]),
    );
  }
}

class _ExerciseCard extends StatefulWidget {
  final int index;
  final Map<String, dynamic> exercise;
  const _ExerciseCard({required this.index, required this.exercise});
  @override
  State<_ExerciseCard> createState() => _ExerciseCardState();
}

class _ExerciseCardState extends State<_ExerciseCard> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final ex = widget.exercise;
    final cs = Theme.of(context).colorScheme;
    final hasDesc  = (ex['description'] as String? ?? '').isNotEmpty;
    final hasVideo = ex['video_url'] != null && (ex['video_url'] as String).isNotEmpty;

    return Card(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      ListTile(
        leading: CircleAvatar(radius: 18,
          backgroundColor: AppTheme.success.withValues(alpha: 0.12),
          child: Text('${widget.index}', style: const TextStyle(color: AppTheme.success, fontWeight: FontWeight.w800))),
        title: Text(ex['name'] as String? ?? '–', style: const TextStyle(fontWeight: FontWeight.w700)),
        subtitle: ex['repetitions'] != null
            ? Text('${ex['repetitions']}× Wiederholungen${ex['sets'] != null ? ' · ${ex['sets']} Sätze' : ''}',
                style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13))
            : null,
        trailing: (hasDesc || hasVideo) ? IconButton(
          icon: Icon(_expanded ? Icons.expand_less_rounded : Icons.expand_more_rounded),
          onPressed: () => setState(() => _expanded = !_expanded),
        ) : null,
      ),
      if (_expanded && hasDesc) Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        child: Text(ex['description'] as String, style: TextStyle(color: cs.onSurfaceVariant)),
      ),
      if (_expanded && hasVideo) Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        child: Row(children: [
          const Icon(Icons.play_circle_outline_rounded, color: AppTheme.tertiary, size: 18),
          const SizedBox(width: 6),
          const Text('Video verfügbar', style: TextStyle(color: AppTheme.tertiary, fontWeight: FontWeight.w600)),
        ]),
      ),
    ]));
  }
}
