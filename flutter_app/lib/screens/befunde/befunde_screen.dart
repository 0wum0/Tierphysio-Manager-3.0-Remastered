import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../models/befundbogen.dart';
import '../../services/api_service.dart';

class BefundeScreen extends StatefulWidget {
  const BefundeScreen({super.key});

  @override
  State<BefundeScreen> createState() => _BefundeScreenState();
}

class _BefundeScreenState extends State<BefundeScreen> {
  final _api = ApiService();
  final _searchCtrl = TextEditingController();

  List<Befundbogen> _items = [];
  bool _loading = true;
  String _error = '';
  String _statusFilter = '';
  int _page = 1;
  int _total = 0;
  static const _limit = 20;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load({bool reset = false}) async {
    if (reset) _page = 1;
    setState(() { _loading = true; _error = ''; });
    try {
      final data = await _api.befundeList(
        page: _page,
        limit: _limit,
        search: _searchCtrl.text.trim(),
        status: _statusFilter,
      );
      final raw = data['items'] as List? ?? [];
      setState(() {
        _items = raw.map((e) => Befundbogen.fromJson(Map<String, dynamic>.from(e as Map))).toList();
        _total = int.tryParse(data['total'].toString()) ?? 0;
        _loading = false;
      });
    } catch (e) {
      setState(() { _loading = false; _error = e.toString(); });
    }
  }

  Color _statusColor(String s) => switch (s) {
        'versendet' => Colors.green,
        'abgeschlossen' => Colors.blue,
        _ => Colors.grey,
      };

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Befundbögen'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => _load(reset: true),
          ),
        ],
      ),
      body: Column(
        children: [
          // Search + filter bar
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
            child: Row(children: [
              Expanded(
                child: TextField(
                  controller: _searchCtrl,
                  decoration: InputDecoration(
                    hintText: 'Patient / Besitzer suchen…',
                    prefixIcon: const Icon(Icons.search, size: 18),
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  onSubmitted: (_) => _load(reset: true),
                ),
              ),
              const SizedBox(width: 8),
              DropdownButton<String>(
                value: _statusFilter.isEmpty ? null : _statusFilter,
                hint: const Text('Status'),
                underline: const SizedBox(),
                items: const [
                  DropdownMenuItem(value: '', child: Text('Alle')),
                  DropdownMenuItem(value: 'entwurf', child: Text('Entwurf')),
                  DropdownMenuItem(value: 'abgeschlossen', child: Text('Abgeschlossen')),
                  DropdownMenuItem(value: 'versendet', child: Text('Versendet')),
                ],
                onChanged: (v) {
                  setState(() => _statusFilter = v ?? '');
                  _load(reset: true);
                },
              ),
            ]),
          ),

          if (_loading)
            const Expanded(child: Center(child: CircularProgressIndicator()))
          else if (_error.isNotEmpty)
            Expanded(child: Center(child: Text(_error, style: const TextStyle(color: Colors.red))))
          else if (_items.isEmpty)
            Expanded(child: Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.description_outlined, size: 56, color: theme.colorScheme.outlineVariant),
                const SizedBox(height: 12),
                const Text('Keine Befundbögen gefunden'),
              ]),
            ))
          else
            Expanded(
              child: RefreshIndicator(
                onRefresh: () => _load(reset: true),
                child: ListView.separated(
                  padding: const EdgeInsets.all(12),
                  itemCount: _items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (context, i) {
                    final b = _items[i];
                    final color = _statusColor(b.status);
                    return Card(
                      elevation: 0,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(12),
                        onTap: () => context.push('/befunde/${b.id}'),
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Row(children: [
                            Container(
                              width: 4,
                              height: 48,
                              decoration: BoxDecoration(
                                color: color,
                                borderRadius: BorderRadius.circular(2),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(children: [
                                  Text(b.number,
                                    style: theme.textTheme.labelSmall?.copyWith(
                                      fontFamily: 'monospace',
                                      color: theme.colorScheme.outline,
                                    )),
                                  const Spacer(),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: color.withOpacity(0.1),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(b.statusLabel,
                                      style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.w600)),
                                  ),
                                ]),
                                const SizedBox(height: 4),
                                if (b.patientName != null)
                                  Text(b.patientName!, style: theme.textTheme.titleSmall),
                                if (b.ownerName != null)
                                  Text(b.ownerName!, style: theme.textTheme.bodySmall),
                                const SizedBox(height: 4),
                                Text(b.formattedDatum,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: theme.colorScheme.outline,
                                  )),
                              ],
                            )),
                          ]),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ),

          // Pagination
          if (!_loading && _total > _limit)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                IconButton(
                  icon: const Icon(Icons.chevron_left),
                  onPressed: _page > 1 ? () { setState(() => _page--); _load(); } : null,
                ),
                Text('Seite $_page · ${_total} Einträge',
                  style: theme.textTheme.bodySmall),
                IconButton(
                  icon: const Icon(Icons.chevron_right),
                  onPressed: _page * _limit < _total ? () { setState(() => _page++); _load(); } : null,
                ),
              ]),
            ),
        ],
      ),
    );
  }
}
