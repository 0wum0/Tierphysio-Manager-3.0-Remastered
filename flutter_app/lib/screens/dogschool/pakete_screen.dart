import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';
import 'dogschool_common.dart';

class PaketeScreen extends StatefulWidget {
  const PaketeScreen({super.key});
  @override
  State<PaketeScreen> createState() => _PaketeScreenState();
}

class _PaketeScreenState extends State<PaketeScreen>
    with SingleTickerProviderStateMixin {
  final _api = ApiService();
  late final TabController _tabs;

  List<Map<String, dynamic>> _catalog = [];
  List<Map<String, dynamic>> _balances = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _load();
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final cat = await _api.dogschoolPackages();
      final bal = await _api.dogschoolPackageBalances();
      setState(() {
        _catalog = (cat['items'] as List? ?? [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
        _balances = (bal['items'] as List? ?? [])
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

  Future<void> _createPackage() async {
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _PackageFormSheet(),
    );
    if (ok == true) _load();
  }

  Future<void> _sellPackage() async {
    if (_catalog.isEmpty) {
      showDsSnack(context, 'Bitte zuerst ein Paket im Katalog anlegen.',
          error: true);
      return;
    }
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _SellPackageSheet(catalog: _catalog),
    );
    if (ok == true) _load();
  }

  Future<void> _redeem(Map<String, dynamic> balance) async {
    try {
      await _api.dogschoolPackageRedeem(asInt(balance['id']), 1);
      if (mounted) showDsSnack(context, 'Einheit eingelöst ✓');
      _load();
    } catch (e) {
      if (mounted) showDsSnack(context, e.toString(), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pakete'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: _load),
        ],
        bottom: TabBar(
          controller: _tabs,
          tabs: const [
            Tab(text: 'Verkauft'),
            Tab(text: 'Katalog'),
          ],
        ),
      ),
      floatingActionButton: AnimatedBuilder(
        animation: _tabs,
        builder: (_, __) => _tabs.index == 0
            ? FloatingActionButton.extended(
                onPressed: _sellPackage,
                icon: const Icon(Icons.sell_rounded),
                label: const Text('Paket verkaufen'),
              )
            : FloatingActionButton.extended(
                onPressed: _createPackage,
                icon: const Icon(Icons.add_rounded),
                label: const Text('Neues Paket'),
              ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: [
          _buildBalances(),
          _buildCatalog(),
        ],
      ),
    );
  }

  Widget _buildBalances() {
    final cs = Theme.of(context).colorScheme;
    return AsyncBody(
      loading: _loading,
      error: _error,
      empty: _balances.isEmpty,
      emptyTitle: 'Keine verkauften Pakete',
      emptyHint: 'Verkaufe ein Paket, um Guthaben für einen Halter anzulegen.',
      emptyIcon: Icons.confirmation_number_outlined,
      onRetry: _load,
      child: RefreshIndicator(
        onRefresh: _load,
        child: ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
          itemCount: _balances.length,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (_, i) {
            final b = _balances[i];
            final remaining = asInt(b['units_remaining']);
            final total = asInt(b['units_total']);
            return DsCard(
              child: Row(children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: statusColor(asStr(b['status'])).withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  alignment: Alignment.center,
                  child: Text('$remaining',
                      style: TextStyle(
                          fontWeight: FontWeight.w900,
                          color: statusColor(asStr(b['status'])))),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(asStr(b['package_name']),
                          style: const TextStyle(fontWeight: FontWeight.w700)),
                      Text(
                        '${asStr(b['owner_first_name'])} ${asStr(b['owner_last_name'])}'.trim(),
                        style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant),
                      ),
                      Text('$remaining von $total übrig',
                          style: TextStyle(
                              fontSize: 12, color: cs.onSurfaceVariant)),
                    ],
                  ),
                ),
                if (remaining > 0 && asStr(b['status']) == 'active')
                  IconButton(
                    tooltip: 'Einheit einlösen',
                    icon: const Icon(Icons.remove_circle_outline_rounded,
                        color: AppTheme.primary),
                    onPressed: () => _redeem(b),
                  )
                else
                  StatusChip(
                      label: asStr(b['status']),
                      color: statusColor(asStr(b['status']))),
              ]),
            );
          },
        ),
      ),
    );
  }

  Widget _buildCatalog() {
    final cs = Theme.of(context).colorScheme;
    return AsyncBody(
      loading: _loading,
      error: _error,
      empty: _catalog.isEmpty,
      emptyTitle: 'Kein Paket im Katalog',
      emptyHint: 'Lege ein Paket an (z.B. „10er Karte Gruppe").',
      emptyIcon: Icons.style_outlined,
      onRetry: _load,
      child: RefreshIndicator(
        onRefresh: _load,
        child: ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
          itemCount: _catalog.length,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (_, i) {
            final p = _catalog[i];
            return DsCard(
              child: Row(children: [
                const Icon(Icons.confirmation_number_rounded,
                    color: AppTheme.tertiary),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(asStr(p['name']),
                          style: const TextStyle(fontWeight: FontWeight.w700)),
                      Text(
                        '${asInt(p['total_units'])} Einheiten · ${euroFromCents(p['price_cents'])}',
                        style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant),
                      ),
                    ],
                  ),
                ),
                if (asInt(p['is_active']) == 1)
                  const StatusChip(label: 'aktiv', color: AppTheme.success)
                else
                  const StatusChip(label: 'inaktiv', color: AppTheme.danger),
              ]),
            );
          },
        ),
      ),
    );
  }
}

/* ═══════════════════════ Package catalog form ═══════════════════════ */

class _PackageFormSheet extends StatefulWidget {
  const _PackageFormSheet();
  @override
  State<_PackageFormSheet> createState() => _PackageFormSheetState();
}

class _PackageFormSheetState extends State<_PackageFormSheet> {
  final _api = ApiService();
  final _name = TextEditingController();
  final _units = TextEditingController(text: '10');
  final _price = TextEditingController();
  final _validDays = TextEditingController();
  String _type = 'multi';
  bool _saving = false;

  @override
  void dispose() {
    _name.dispose();
    _units.dispose();
    _price.dispose();
    _validDays.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_name.text.trim().isEmpty) {
      showDsSnack(context, 'Bitte einen Paketnamen eingeben.', error: true);
      return;
    }
    setState(() => _saving = true);
    final priceCents =
        ((double.tryParse(_price.text.replaceAll(',', '.')) ?? 0) * 100).round();
    try {
      await _api.dogschoolPackageCreate({
        'name': _name.text.trim(),
        'type': _type,
        'total_units': int.tryParse(_units.text) ?? 1,
        'price_cents': priceCents,
        if (_validDays.text.trim().isNotEmpty)
          'valid_days': int.tryParse(_validDays.text),
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
            Text('Neues Paket',
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 16),
            TextField(
              controller: _name,
              decoration: const InputDecoration(
                  labelText: 'Paketname *',
                  prefixIcon: Icon(Icons.title_rounded)),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: _type,
              isExpanded: true,
              decoration: const InputDecoration(
                  labelText: 'Typ', prefixIcon: Icon(Icons.category_rounded)),
              items: const [
                DropdownMenuItem(value: 'single', child: Text('Einzelstunde')),
                DropdownMenuItem(value: 'multi', child: Text('Mehrfachkarte')),
                DropdownMenuItem(value: 'subscription', child: Text('Abo')),
                DropdownMenuItem(value: 'unlimited', child: Text('Flatrate')),
              ],
              onChanged: (v) => setState(() => _type = v ?? 'multi'),
            ),
            const SizedBox(height: 10),
            Row(children: [
              Expanded(
                child: TextField(
                  controller: _units,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                      labelText: 'Einheiten',
                      prefixIcon: Icon(Icons.repeat_rounded)),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextField(
                  controller: _validDays,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                      labelText: 'Gültig (Tage)',
                      prefixIcon: Icon(Icons.timelapse_rounded)),
                ),
              ),
            ]),
            const SizedBox(height: 10),
            TextField(
              controller: _price,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(
                  labelText: 'Preis (€)', prefixIcon: Icon(Icons.euro_rounded)),
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
        ),
      ),
    );
  }
}

/* ═══════════════════════ Sell package sheet ═══════════════════════ */

class _SellPackageSheet extends StatefulWidget {
  final List<Map<String, dynamic>> catalog;
  const _SellPackageSheet({required this.catalog});
  @override
  State<_SellPackageSheet> createState() => _SellPackageSheetState();
}

class _SellPackageSheetState extends State<_SellPackageSheet> {
  final _api = ApiService();
  final _ownerSearch = TextEditingController();
  int? _packageId;
  Map<String, dynamic>? _selectedOwner;
  List<Map<String, dynamic>> _ownerResults = [];
  bool _searching = false;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _packageId = asInt(widget.catalog.first['id']);
  }

  @override
  void dispose() {
    _ownerSearch.dispose();
    super.dispose();
  }

  Future<void> _searchOwners(String q) async {
    if (q.trim().isEmpty) {
      setState(() => _ownerResults = []);
      return;
    }
    setState(() => _searching = true);
    try {
      final data = await _api.owners(search: q, perPage: 15);
      setState(() {
        _ownerResults = (data['items'] as List? ?? [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
        _searching = false;
      });
    } catch (_) {
      setState(() => _searching = false);
    }
  }

  Future<void> _save() async {
    if (_packageId == null || _selectedOwner == null) {
      showDsSnack(context, 'Bitte Paket und Halter wählen.', error: true);
      return;
    }
    setState(() => _saving = true);
    try {
      await _api.dogschoolPackageSell({
        'package_id': _packageId,
        'owner_id': asInt(_selectedOwner!['id']),
      });
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => _saving = false);
      if (mounted) showDsSnack(context, e.toString(), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
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
            Text('Paket verkaufen',
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 16),
            DropdownButtonFormField<int>(
              initialValue: _packageId,
              isExpanded: true,
              decoration: const InputDecoration(
                  labelText: 'Paket',
                  prefixIcon: Icon(Icons.confirmation_number_rounded)),
              items: [
                for (final p in widget.catalog)
                  DropdownMenuItem(
                    value: asInt(p['id']),
                    child: Text(
                        '${asStr(p['name'])} (${euroFromCents(p['price_cents'])})',
                        overflow: TextOverflow.ellipsis),
                  ),
              ],
              onChanged: (v) => setState(() => _packageId = v),
            ),
            const SizedBox(height: 10),
            if (_selectedOwner != null)
              DsCard(
                child: Row(children: [
                  const Icon(Icons.person_rounded, color: AppTheme.secondary),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      '${asStr(_selectedOwner!['first_name'])} ${asStr(_selectedOwner!['last_name'])}'.trim(),
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                  ),
                  TextButton(
                    onPressed: () => setState(() => _selectedOwner = null),
                    child: const Text('Ändern'),
                  ),
                ]),
              )
            else ...[
              TextField(
                controller: _ownerSearch,
                decoration: const InputDecoration(
                    labelText: 'Halter suchen…',
                    prefixIcon: Icon(Icons.search_rounded)),
                onChanged: _searchOwners,
              ),
              const SizedBox(height: 8),
              if (_searching)
                const Padding(
                    padding: EdgeInsets.all(12),
                    child: CircularProgressIndicator())
              else
                SizedBox(
                  height: 180,
                  child: ListView.separated(
                    itemCount: _ownerResults.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 6),
                    itemBuilder: (_, i) {
                      final o = _ownerResults[i];
                      return DsCard(
                        onTap: () => setState(() => _selectedOwner = o),
                        child: Row(children: [
                          const Icon(Icons.person_outline_rounded),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              '${asStr(o['first_name'])} ${asStr(o['last_name'])}'.trim(),
                              style: TextStyle(color: cs.onSurface),
                            ),
                          ),
                        ]),
                      );
                    },
                  ),
                ),
            ],
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                icon: _saving
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.sell_rounded),
                label: Text(_saving ? 'Verkaufen…' : 'Verkaufen'),
                onPressed: _saving ? null : _save,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
