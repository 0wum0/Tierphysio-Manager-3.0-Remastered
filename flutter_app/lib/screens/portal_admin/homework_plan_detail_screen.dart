import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class HomeworkPlanDetailScreen extends StatefulWidget {
  final int id;
  const HomeworkPlanDetailScreen({super.key, required this.id});
  @override
  State<HomeworkPlanDetailScreen> createState() => _HomeworkPlanDetailScreenState();
}

class _HomeworkPlanDetailScreenState extends State<HomeworkPlanDetailScreen> {
  final _api = ApiService();
  Map<String, dynamic>? _plan;
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final plan = await _api.homeworkPlanShow(widget.id);
      setState(() { _plan = plan; _loading = false; });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  String _fmt(String? d) {
    if (d == null) return '—';
    try { return DateFormat('dd.MM.yyyy', 'de_DE').format(DateTime.parse(d)); } catch (_) { return d; }
  }

  void _showSnack(String msg, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg), backgroundColor: error ? AppTheme.danger : AppTheme.success));
  }

  Future<void> _openPdf() async {
    try {
      final data = await _api.homeworkPlanPdfUrl(widget.id);
      final url  = data['url'] as String? ?? '';
      if (url.isEmpty) { _showSnack('Keine PDF verfügbar.', error: true); return; }
      final full = url.startsWith('http') ? url : '${ApiService.baseUrl}$url';
      final uri  = Uri.parse(full);
      if (await canLaunchUrl(uri)) await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (e) { _showSnack(e.toString(), error: true); }
  }

  Future<void> _sendPlan() async {
    final ownerIdCtrl = TextEditingController();
    await showModalBottomSheet(
      context: context, isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom + 32),
        child: Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 0), child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
          Text('Plan senden', style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          TextField(controller: ownerIdCtrl,
            decoration: const InputDecoration(labelText: 'Besitzer-ID *', prefixIcon: Icon(Icons.person_rounded)),
            keyboardType: TextInputType.number),
          const SizedBox(height: 20),
          SizedBox(width: double.infinity, child: FilledButton.icon(
            icon: const Icon(Icons.send_rounded),
            label: const Text('Senden'),
            onPressed: () async {
              if (ownerIdCtrl.text.trim().isEmpty) return;
              Navigator.pop(ctx);
              try {
                await _api.homeworkPlanSend(widget.id, {'owner_id': int.parse(ownerIdCtrl.text.trim())});
                _showSnack('Plan gesendet ✓');
                _load();
              } catch (e) { _showSnack(e.toString(), error: true); }
            },
          )),
        ])),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final plan = _plan;
    return Scaffold(
      appBar: AppBar(
        title: Text(plan?['title'] as String? ?? 'Hausaufgabenplan'),
        actions: [
          IconButton(
            icon: Icon(Icons.picture_as_pdf_rounded, color: AppTheme.danger),
            tooltip: 'PDF öffnen',
            onPressed: _openPdf,
          ),
          IconButton(
            icon: Icon(Icons.send_rounded, color: AppTheme.primary),
            tooltip: 'An Besitzer senden',
            onPressed: _sendPlan,
          ),
        ],
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
              : _buildBody(),
    );
  }

  Widget _buildBody() {
    final plan  = _plan!;
    final tasks = List<dynamic>.from(plan['tasks'] as List? ?? []);
    final sent  = plan['sent_at'] != null;
    final color = sent ? AppTheme.success : AppTheme.secondary;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
      child: Column(children: [
        // Header
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: [color, Color.lerp(color, AppTheme.primary, 0.35)!]),
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(child: Text(plan['title'] as String? ?? '—',
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18))),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(20)),
                child: Text(sent ? 'Gesendet' : 'Entwurf',
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 11)),
              ),
            ]),
            if ((plan['description'] as String? ?? '').isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(plan['description'] as String,
                style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 13, height: 1.4)),
            ],
            const SizedBox(height: 16),
            Row(children: [
              _headerStat(Icons.assignment_rounded, '${tasks.length} Aufgaben'),
              const SizedBox(width: 16),
              if (sent) _headerStat(Icons.send_rounded, 'Gesendet ${_fmt(plan['sent_at'] as String?)}')
              else _headerStat(Icons.edit_rounded, 'Erstellt ${_fmt(plan['created_at'] as String?)}'),
            ]),
          ]),
        ),

        const SizedBox(height: 16),

        // Action buttons
        Row(children: [
          Expanded(child: OutlinedButton.icon(
            icon: Icon(Icons.picture_as_pdf_rounded, color: AppTheme.danger),
            label: const Text('PDF öffnen'),
            style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger, side: BorderSide(color: AppTheme.danger)),
            onPressed: _openPdf,
          )),
          const SizedBox(width: 12),
          Expanded(child: FilledButton.icon(
            icon: const Icon(Icons.send_rounded),
            label: const Text('Senden'),
            onPressed: _sendPlan,
          )),
        ]),

        const SizedBox(height: 20),

        // Tasks
        Container(
          decoration: BoxDecoration(
            color: Theme.of(context).cardTheme.color,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: Theme.of(context).dividerColor),
          ),
          child: Column(children: [
            Padding(padding: const EdgeInsets.fromLTRB(16, 14, 16, 8), child: Row(children: [
              Icon(Icons.checklist_rounded, size: 18, color: AppTheme.primary),
              const SizedBox(width: 8),
              Expanded(child: Text('Aufgaben (${tasks.length})',
                style: TextStyle(fontWeight: FontWeight.w700, color: AppTheme.primary, fontSize: 14))),
            ])),
            const Divider(height: 1),
            if (tasks.isEmpty)
              Padding(
                padding: const EdgeInsets.all(24),
                child: Column(children: [
                  Icon(Icons.assignment_outlined, size: 48, color: Colors.grey.shade300),
                  const SizedBox(height: 12),
                  Text('Keine Aufgaben', style: TextStyle(color: Colors.grey.shade400, fontSize: 14)),
                  const SizedBox(height: 4),
                  Text('Aufgaben über die Web-Oberfläche hinzufügen',
                    style: TextStyle(color: Colors.grey.shade400, fontSize: 12), textAlign: TextAlign.center),
                ]),
              )
            else
              ...tasks.asMap().entries.map((e) {
                final i    = e.key;
                final task = Map<String, dynamic>.from(e.value as Map);
                return Column(children: [
                  if (i > 0) const Divider(height: 1),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Container(
                        width: 24, height: 24,
                        decoration: BoxDecoration(
                          color: AppTheme.primary.withValues(alpha: 0.1),
                          shape: BoxShape.circle,
                        ),
                        child: Center(child: Text('${i + 1}',
                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppTheme.primary))),
                      ),
                      const SizedBox(width: 12),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(task['title'] as String? ?? '—',
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                        if ((task['description'] as String? ?? '').isNotEmpty)
                          Text(task['description'] as String,
                            style: TextStyle(fontSize: 12, color: Colors.grey.shade600, height: 1.4)),
                        if (task['repetitions'] != null || task['sets'] != null)
                          Padding(padding: const EdgeInsets.only(top: 4), child: Wrap(spacing: 8, children: [
                            if (task['sets'] != null) _taskChip('${task['sets']}x Sätze', AppTheme.primary),
                            if (task['repetitions'] != null) _taskChip('${task['repetitions']}x Wdh.', AppTheme.secondary),
                            if (task['duration'] != null) _taskChip('${task['duration']} Sek.', AppTheme.tertiary),
                          ])),
                      ])),
                    ]),
                  ),
                ]);
              }),
          ]),
        ),
      ]),
    );
  }

  Widget _headerStat(IconData icon, String label) => Row(mainAxisSize: MainAxisSize.min, children: [
    Icon(icon, size: 13, color: Colors.white.withValues(alpha: 0.8)),
    const SizedBox(width: 4),
    Text(label, style: TextStyle(color: Colors.white.withValues(alpha: 0.9), fontSize: 12, fontWeight: FontWeight.w600)),
  ]);

  Widget _taskChip(String label, Color color) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
    decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
    child: Text(label, style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w600)),
  );
}
