import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class BehandlungsartenScreen extends StatefulWidget {
  const BehandlungsartenScreen({super.key});
  @override
  State<BehandlungsartenScreen> createState() => _BehandlungsartenScreenState();
}

class _BehandlungsartenScreenState extends State<BehandlungsartenScreen> {
  final _api = ApiService();
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await _api.treatmentTypes();
      setState(() { _items = data.map((e) => Map<String, dynamic>.from(e as Map)).toList(); _loading = false; });
    } catch (e) { setState(() => _loading = false); _showSnack(e.toString(), error: true); }
  }

  Future<void> _delete(int id) async {
    final ok = await showDialog<bool>(context: context, builder: (_) => AlertDialog(
      title: const Text('Behandlungsart löschen'),
      content: const Text('Diese Behandlungsart wirklich löschen?'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
        FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Löschen'),
          style: FilledButton.styleFrom(backgroundColor: AppTheme.danger)),
      ],
    ));
    if (ok != true) return;
    try { await _api.treatmentTypeDelete(id); _load(); }
    catch (e) { _showSnack(e.toString(), error: true); }
  }

  void _showForm({Map<String, dynamic>? existing}) {
    final nameCtrl  = TextEditingController(text: existing?['name'] as String? ?? '');
    final priceCtrl = TextEditingController(text: existing?['price']?.toString() ?? '');
    final descCtrl  = TextEditingController(text: existing?['description'] as String? ?? '');
    Color selectedColor = _parseColor(existing?['color'] as String? ?? '#4f7cff');
    final colors = [
      const Color(0xff4f7cff), const Color(0xff00c896), const Color(0xffff6b6b),
      const Color(0xffffa94d), const Color(0xff845ef7), const Color(0xff339af0),
      const Color(0xff51cf66), const Color(0xffff8787), const Color(0xfffe7b72),
      const Color(0xff74c0fc),
    ];

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(builder: (ctx, ss) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom + 32),
        child: Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 0), child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
          Text(existing == null ? 'Neue Behandlungsart' : 'Bearbeiten',
            style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Name *', prefixIcon: Icon(Icons.label_rounded))),
          const SizedBox(height: 10),
          TextField(controller: priceCtrl, decoration: const InputDecoration(labelText: 'Preis (€)', prefixIcon: Icon(Icons.euro_rounded)),
            keyboardType: const TextInputType.numberWithOptions(decimal: true)),
          const SizedBox(height: 10),
          TextField(controller: descCtrl, decoration: const InputDecoration(labelText: 'Beschreibung', prefixIcon: Icon(Icons.description_rounded)), maxLines: 2),
          const SizedBox(height: 14),
          Align(alignment: Alignment.centerLeft,
            child: Text('Farbe', style: TextStyle(fontSize: 12, color: Colors.grey.shade600, fontWeight: FontWeight.w500))),
          const SizedBox(height: 8),
          Wrap(spacing: 8, runSpacing: 8, children: colors.map((c) => GestureDetector(
            onTap: () => ss(() => selectedColor = c),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              width: 32, height: 32,
              decoration: BoxDecoration(
                color: c, shape: BoxShape.circle,
                border: Border.all(
                  color: selectedColor == c ? Colors.white : Colors.transparent, width: 3),
                boxShadow: selectedColor == c ? [BoxShadow(color: c.withValues(alpha: 0.5), blurRadius: 8)] : null,
              ),
              child: selectedColor == c ? const Icon(Icons.check_rounded, color: Colors.white, size: 16) : null,
            ),
          )).toList()),
          const SizedBox(height: 20),
          SizedBox(width: double.infinity, child: FilledButton.icon(
            icon: const Icon(Icons.save_rounded),
            label: Text(existing == null ? 'Erstellen' : 'Speichern'),
            onPressed: () async {
              if (nameCtrl.text.trim().isEmpty) return;
              Navigator.pop(ctx);
              final colorHex = '#${selectedColor.toARGB32().toRadixString(16).substring(2)}';
              final data = {
                'name':        nameCtrl.text.trim(),
                'description': descCtrl.text.trim(),
                'color':       colorHex,
                if (priceCtrl.text.trim().isNotEmpty) 'price': double.tryParse(priceCtrl.text.trim()) ?? 0,
              };
              try {
                if (existing == null) {
                  await _api.treatmentTypeCreate(data);
                } else {
                  await _api.treatmentTypeUpdate(existing['id'] as int, data);
                }
                _showSnack(existing == null ? 'Erstellt ✓' : 'Gespeichert ✓');
                _load();
              } catch (e) { _showSnack(e.toString(), error: true); }
            },
          )),
        ])),
      )),
    );
  }

  Color _parseColor(String hex) {
    try { return Color(int.parse('FF${hex.replaceAll('#', '')}', radix: 16)); }
    catch (_) { return AppTheme.primary; }
  }

  void _showSnack(String msg, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg), backgroundColor: error ? AppTheme.danger : AppTheme.success));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Behandlungsarten'),
        actions: [IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load)],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showForm(),
        icon: const Icon(Icons.add_rounded),
        label: const Text('Neue Art'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _items.isEmpty
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(Icons.category_outlined, size: 72, color: Colors.grey.shade300),
                  const SizedBox(height: 16),
                  Text('Keine Behandlungsarten', style: TextStyle(color: Colors.grey.shade500, fontSize: 16)),
                ]))
              : ReorderableListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
                  itemCount: _items.length,
                  onReorder: (oldIdx, newIdx) {
                    setState(() {
                      if (newIdx > oldIdx) newIdx--;
                      final item = _items.removeAt(oldIdx);
                      _items.insert(newIdx, item);
                    });
                  },
                  itemBuilder: (ctx, i) {
                    final item = _items[i];
                    final color = _parseColor(item['color'] as String? ?? '#4f7cff');
                    return Container(
                      key: ValueKey(item['id']),
                      margin: const EdgeInsets.only(bottom: 10),
                      decoration: BoxDecoration(
                        color: Theme.of(context).cardTheme.color,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: color.withValues(alpha: 0.3)),
                      ),
                      child: ListTile(
                        contentPadding: const EdgeInsets.fromLTRB(16, 8, 8, 8),
                        leading: Container(
                          width: 40, height: 40,
                          decoration: BoxDecoration(color: color.withValues(alpha: 0.15), shape: BoxShape.circle),
                          child: Center(child: Icon(Icons.medical_services_rounded, color: color, size: 20)),
                        ),
                        title: Text(item['name'] as String? ?? '—',
                          style: const TextStyle(fontWeight: FontWeight.w700)),
                        subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          if ((item['description'] as String? ?? '').isNotEmpty)
                            Text(item['description'] as String,
                              style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                              maxLines: 1, overflow: TextOverflow.ellipsis),
                          if (item['price'] != null)
                            Text('${item['price']} €', style: TextStyle(fontSize: 12, color: color, fontWeight: FontWeight.w600)),
                        ]),
                        trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                          IconButton(icon: const Icon(Icons.edit_rounded, size: 20), onPressed: () => _showForm(existing: item)),
                          IconButton(icon: Icon(Icons.delete_outline_rounded, size: 20, color: AppTheme.danger),
                            onPressed: () => _delete(item['id'] as int)),
                          const Icon(Icons.drag_handle_rounded, color: Colors.grey, size: 20),
                        ]),
                      ),
                    );
                  },
                ),
    );
  }
}
