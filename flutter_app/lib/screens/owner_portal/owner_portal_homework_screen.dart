import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shimmer/shimmer.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import '../../services/owner_portal_auth_service.dart';
import '../../core/theme.dart';

class OwnerPortalHomeworkScreen extends StatefulWidget {
  const OwnerPortalHomeworkScreen({super.key});

  @override
  State<OwnerPortalHomeworkScreen> createState() => _OwnerPortalHomeworkScreenState();
}

class _OwnerPortalHomeworkScreenState extends State<OwnerPortalHomeworkScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final list = await _api.ownerPortalHomework();
      setState(() {
        _items = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _loading = false;
      });
    } catch (e) {
      setState(() { _loading = false; _error = e.toString(); });
    }
  }

  @override
  Widget build(BuildContext context) {
    final isTrainer = context.read<OwnerPortalAuthService>().isTrainer;
    final title = isTrainer ? 'Trainingsaufgaben' : 'Hausaufgaben';
    const purple = AppTheme.secondary;

    return Scaffold(
      appBar: AppBar(title: Text(title),
        backgroundColor: purple, foregroundColor: Colors.white),
      body: _loading
          ? Shimmer.fromColors(baseColor: Colors.grey.shade300, highlightColor: Colors.grey.shade100,
              child: ListView.builder(padding: const EdgeInsets.all(16), itemCount: 4,
                itemBuilder: (_, __) => Container(height: 110, margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18)))))
          : _error != null
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  const Icon(Icons.cloud_off_rounded, size: 48, color: Colors.grey),
                  const SizedBox(height: 12), Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  FilledButton.icon(onPressed: _load,
                    icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut'),
                    style: FilledButton.styleFrom(backgroundColor: purple))]))
              : RefreshIndicator(onRefresh: _load, color: purple,
                  child: _items.isEmpty
                    ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        Text(isTrainer ? '🐕' : '📋', style: const TextStyle(fontSize: 52)),
                        const SizedBox(height: 16),
                        Text('Keine $title vorhanden',
                            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                        const SizedBox(height: 8),
                        Text('${isTrainer ? "Trainingsaufgaben" : "Hausaufgaben"} werden von der Praxis erstellt.',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 13, color: Colors.grey.shade500))]))
                    : ListView.builder(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                        itemCount: _items.length,
                        itemBuilder: (context, i) {
                          final item   = _items[i];
                          final name   = item['title'] as String? ?? item['name'] as String? ?? item['bezeichnung'] as String? ?? 'Aufgabe';
                          final desc   = item['description'] as String? ?? item['beschreibung'] as String? ?? '';
                          final pet    = item['patient_name'] as String? ?? item['tier'] as String? ?? '';
                          final date   = item['created_at'] as String? ?? item['datum'] as String? ?? '';
                          final pdfUrl = item['pdf_url'] as String? ?? '';
                          final videoUrl = item['video_url'] as String? ?? '';
                          final exercises = item['exercises'] as List? ?? item['uebungen'] as List? ?? [];

                          return TweenAnimationBuilder<double>(
                            tween: Tween(begin: 0, end: 1),
                            duration: Duration(milliseconds: 300 + i * 60),
                            curve: Curves.easeOutCubic,
                            builder: (_, v, child) => Opacity(opacity: v,
                              child: Transform.translate(offset: Offset(0, 16*(1-v)), child: child)),
                            child: Padding(
                              padding: const EdgeInsets.only(bottom: 14),
                              child: Container(
                                decoration: BoxDecoration(
                                  color: Theme.of(context).colorScheme.brightness == Brightness.dark
                                      ? const Color(0xFF150D2A) : Colors.white,
                                  borderRadius: BorderRadius.circular(20),
                                  border: Border.all(color: purple.withValues(alpha: 0.2)),
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.all(16),
                                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                    Row(children: [
                                      Container(width: 44, height: 44,
                                        decoration: BoxDecoration(color: purple.withValues(alpha: 0.12),
                                          borderRadius: BorderRadius.circular(12)),
                                        child: Icon(isTrainer ? Icons.fitness_center_rounded : Icons.assignment_rounded,
                                          color: purple, size: 22)),
                                      const SizedBox(width: 12),
                                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                        Text(name, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                                        if (pet.isNotEmpty) Text(pet, style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
                                        if (date.isNotEmpty) Text(date.length > 10 ? date.substring(0, 10) : date,
                                            style: TextStyle(fontSize: 11, color: Colors.grey.shade400)),
                                      ])),
                                    ]),
                                    if (desc.isNotEmpty) ...[
                                      const SizedBox(height: 10),
                                      Text(desc, style: TextStyle(fontSize: 13, color: Colors.grey.shade600, height: 1.4)),
                                    ],
                                    if (exercises.isNotEmpty) ...[
                                      const SizedBox(height: 12),
                                      Text('${exercises.length} ${isTrainer ? "Training(s)" : "Übung(en)"}',
                                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600,
                                            color: purple.withValues(alpha: 0.8))),
                                      const SizedBox(height: 6),
                                      ...exercises.take(3).map((ex) {
                                        final exName = (ex as Map)['name'] as String? ?? (ex)['title'] as String? ?? '';
                                        final reps   = ex['repetitions'] as String? ?? ex['wiederholungen'] as String? ?? '';
                                        return Padding(
                                          padding: const EdgeInsets.only(bottom: 4),
                                          child: Row(children: [
                                            Container(width: 5, height: 5, margin: const EdgeInsets.only(right: 8, top: 1),
                                              decoration: BoxDecoration(color: purple.withValues(alpha: 0.6), shape: BoxShape.circle)),
                                            Expanded(child: Text(
                                              '$exName${reps.isNotEmpty ? " · $reps" : ""}',
                                              style: TextStyle(fontSize: 12, color: Colors.grey.shade500))),
                                          ]),
                                        );
                                      }),
                                      if (exercises.length > 3) Padding(
                                        padding: const EdgeInsets.only(top: 2),
                                        child: Text('+ ${exercises.length - 3} weitere',
                                          style: TextStyle(fontSize: 11, color: purple.withValues(alpha: 0.6))),
                                      ),
                                    ],
                                    if (pdfUrl.isNotEmpty || videoUrl.isNotEmpty) ...[
                                      const SizedBox(height: 12),
                                      Row(children: [
                                        if (pdfUrl.isNotEmpty) _ActionChip(
                                          icon: Icons.picture_as_pdf_rounded, label: 'PDF',
                                          color: AppTheme.danger, onTap: () => _openUrl(pdfUrl)),
                                        if (pdfUrl.isNotEmpty && videoUrl.isNotEmpty) const SizedBox(width: 8),
                                        if (videoUrl.isNotEmpty) _ActionChip(
                                          icon: Icons.play_circle_rounded, label: 'Video',
                                          color: AppTheme.primary, onTap: () => _openUrl(videoUrl)),
                                      ]),
                                    ],
                                  ]),
                                ),
                              ),
                            ),
                          );
                        }),
              ),
    );
  }

  Future<void> _openUrl(String url) async {
    final uri = Uri.tryParse(url);
    if (uri != null && await canLaunchUrl(uri)) await launchUrl(uri);
  }
}

class _ActionChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  const _ActionChip({required this.icon, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: color.withValues(alpha: 0.3)),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, color: color, size: 14),
          const SizedBox(width: 5),
          Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: color)),
        ]),
      ),
    );
  }
}
