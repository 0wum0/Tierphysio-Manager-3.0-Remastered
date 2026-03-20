import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class SearchScreen extends StatefulWidget {
  const SearchScreen({super.key});
  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  final _api  = ApiService();
  final _ctrl = TextEditingController();
  List<Map<String, dynamic>> _results = [];
  bool _loading = false;
  String _lastQ = '';

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  Future<void> _search(String q) async {
    if (q.trim().length < 2) { setState(() => _results = []); return; }
    if (q == _lastQ) return;
    _lastQ = q;
    setState(() => _loading = true);
    try {
      final data = await _api.globalSearch(q.trim());
      setState(() { _results = data.map((e) => Map<String, dynamic>.from(e as Map)).toList(); _loading = false; });
    } catch (_) { setState(() => _loading = false); }
  }

  IconData _iconFor(String type) => switch (type) {
    'patient' => Icons.pets_rounded,
    'owner'   => Icons.person_rounded,
    'invoice' => Icons.receipt_long_rounded,
    'appointment' => Icons.calendar_today_rounded,
    _ => Icons.search_rounded,
  };

  Color _colorFor(String type) => switch (type) {
    'patient' => AppTheme.primary,
    'owner'   => AppTheme.secondary,
    'invoice' => AppTheme.tertiary,
    'appointment' => AppTheme.warning,
    _ => Colors.grey,
  };

  String _labelFor(String type) => switch (type) {
    'patient' => 'Patient',
    'owner'   => 'Tierhalter',
    'invoice' => 'Rechnung',
    'appointment' => 'Termin',
    _ => type,
  };

  void _navigate(Map<String, dynamic> result) {
    final type = result['type'] as String? ?? '';
    final id   = result['id'] as int?;
    if (id == null) return;
    switch (type) {
      case 'patient':     context.push('/patienten/$id');
      case 'owner':       context.push('/tierhalter/$id');
      case 'invoice':     context.push('/rechnungen/$id');
      case 'appointment': context.push('/kalender');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: TextField(
          controller: _ctrl,
          autofocus: true,
          onChanged: _search,
          decoration: InputDecoration(
            hintText: 'Patienten, Tierhalter, Rechnungen…',
            border: InputBorder.none,
            suffixIcon: _ctrl.text.isNotEmpty
                ? IconButton(icon: const Icon(Icons.clear_rounded), onPressed: () { _ctrl.clear(); setState(() => _results = []); })
                : null,
          ),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _results.isEmpty && _lastQ.isNotEmpty
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(Icons.search_off_rounded, size: 64, color: Colors.grey.shade300),
                  const SizedBox(height: 12),
                  Text('Keine Ergebnisse für "$_lastQ"', style: TextStyle(color: Colors.grey.shade500)),
                ]))
              : _results.isEmpty
                  ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.search_rounded, size: 64, color: Colors.grey.shade300),
                      const SizedBox(height: 12),
                      Text('Mindestens 2 Zeichen eingeben', style: TextStyle(color: Colors.grey.shade500)),
                    ]))
                  : ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: _results.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (ctx, i) {
                        final r = _results[i];
                        final type  = r['type'] as String? ?? '';
                        final color = _colorFor(type);
                        return Material(
                          color: Theme.of(context).cardTheme.color,
                          borderRadius: BorderRadius.circular(12),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(12),
                            onTap: () => _navigate(r),
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Row(children: [
                                Container(
                                  width: 40, height: 40,
                                  decoration: BoxDecoration(color: color.withValues(alpha: 0.12), shape: BoxShape.circle),
                                  child: Icon(_iconFor(type), color: color, size: 20),
                                ),
                                const SizedBox(width: 12),
                                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                  Text(r['title'] as String? ?? '—',
                                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                                  if ((r['subtitle'] as String? ?? '').isNotEmpty)
                                    Text(r['subtitle'] as String,
                                      style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                                ])),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
                                  child: Text(_labelFor(type), style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w600)),
                                ),
                              ]),
                            ),
                          ),
                        );
                      },
                    ),
    );
  }
}
