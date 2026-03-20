import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class InvoiceFormScreen extends StatefulWidget {
  /// Pre-fill data passed via GoRouter extra (Map with optional keys:
  /// patientId, patientName, ownerId, ownerName)
  final Map<String, dynamic>? prefill;
  const InvoiceFormScreen({super.key, this.prefill});

  @override
  State<InvoiceFormScreen> createState() => _InvoiceFormScreenState();
}

class _InvoiceFormScreenState extends State<InvoiceFormScreen> {
  final _api     = ApiService();
  final _formKey = GlobalKey<FormState>();
  bool _loading  = false;
  bool _dataReady = false;

  int?    _ownerId;
  String? _ownerLabel;
  int?    _patientId;
  String? _patientLabel;

  String _issueDate = DateTime.now().toIso8601String().substring(0, 10);
  String _dueDate   = DateTime.now().add(const Duration(days: 14)).toIso8601String().substring(0, 10);
  String _status    = 'open';
  String _payMethod = 'rechnung';
  final _notesCtrl  = TextEditingController();

  List<dynamic> _allOwners        = [];
  List<dynamic> _allPatients      = [];
  List<dynamic> _treatmentTypes   = [];
  List<_Position> _positions = [_Position()];

  // Live-search controllers
  final _ownerSearchCtrl   = TextEditingController();
  final _patientSearchCtrl = TextEditingController();

  List<dynamic> get _filteredOwners => _allOwners;

  List<dynamic> get _filteredPatients => _ownerId == null
      ? _allPatients
      : _allPatients.where((p) => p['owner_id']?.toString() == _ownerId.toString()).toList();

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    try {
      final results = await Future.wait([
        _api.owners(perPage: 200),
        _api.patients(perPage: 500),
        _api.treatmentTypes().catchError((_) => <dynamic>[]),
      ]);
      final o  = results[0] as Map<String, dynamic>;
      final p  = results[1] as Map<String, dynamic>;
      final tt = results[2] as List<dynamic>;
      setState(() {
        _allOwners      = List<dynamic>.from(o['items'] as List? ?? []);
        _allPatients    = List<dynamic>.from(p['items'] as List? ?? []);
        _treatmentTypes = tt;
        _dataReady      = true;
      });
      // Apply pre-fill
      final pf = widget.prefill;
      if (pf != null) {
        setState(() {
          if (pf['ownerId'] != null) {
            _ownerId    = int.tryParse(pf['ownerId'].toString());
            _ownerLabel = pf['ownerName'] as String?;
          }
          if (pf['patientId'] != null) {
            _patientId    = int.tryParse(pf['patientId'].toString());
            _patientLabel = pf['patientName'] as String?;
          }
        });
      }
    } catch (_) { setState(() => _dataReady = true); }
  }

  double get _totalGross => _positions.fold(0.0, (s, p) => s + p.total);
  String _eur(double v) => NumberFormat.currency(locale: 'de_DE', symbol: '€').format(v);

  Future<void> _pickDate(bool isIssue) async {
    final initial = DateTime.tryParse(isIssue ? _issueDate : _dueDate) ?? DateTime.now();
    final d = await showDatePicker(context: context,
      initialDate: initial, firstDate: DateTime(2020), lastDate: DateTime(2030));
    if (d == null) return;
    setState(() {
      final s = d.toIso8601String().substring(0, 10);
      if (isIssue) _issueDate = s; else _dueDate = s;
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_ownerId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Bitte Tierhalter auswählen')));
      return;
    }
    if (_positions.every((p) => p.description.isEmpty)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Mindestens eine Position eingeben')));
      return;
    }
    setState(() => _loading = true);
    try {
      await _api.invoiceCreate({
        'owner_id':       _ownerId,
        'patient_id':     _patientId,
        'issue_date':     _issueDate,
        'due_date':       _dueDate,
        'status':         _status,
        'payment_method': _payMethod,
        'notes':          _notesCtrl.text.trim(),
        'positions':      _positions.where((p) => p.description.isNotEmpty).map((p) => p.toMap()).toList(),
      });
      if (mounted) context.pop();
    } catch (e) {
      if (mounted) { setState(() => _loading = false); ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()))); }
    }
  }

  @override
  void dispose() {
    _notesCtrl.dispose();
    _ownerSearchCtrl.dispose();
    _patientSearchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Neue Rechnung'),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: FilledButton.icon(
              onPressed: _loading ? null : _submit,
              icon: _loading
                  ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.save_rounded, size: 18),
              label: const Text('Speichern'),
            ),
          ),
        ],
      ),
      body: !_dataReady
          ? const Center(child: CircularProgressIndicator())
          : Form(
              key: _formKey,
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _SectionHeader(icon: Icons.person_rounded, title: 'Tierhalter & Patient', color: AppTheme.primary),
                    const SizedBox(height: 12),

                    // ── Owner live-search ──
                    _LiveSearchField(
                      label: 'Tierhalter *',
                      icon: Icons.person_outlined,
                      selectedLabel: _ownerLabel,
                      searchCtrl: _ownerSearchCtrl,
                      items: _filteredOwners,
                      itemLabel: (o) => '${o['last_name']}, ${o['first_name']}${(o['email'] as String? ?? '').isNotEmpty ? ' · ${o['email']}' : ''}',
                      onSelect: (o) {
                        setState(() {
                          _ownerId    = int.tryParse(o['id'].toString());
                          _ownerLabel = '${o['last_name']}, ${o['first_name']}';
                          // Reset patient selection
                          _patientId    = null;
                          _patientLabel = null;
                          _patientSearchCtrl.clear();
                          // Auto-select if this owner has exactly one patient
                          final ownerPatients = _allPatients
                              .where((p) => p['owner_id']?.toString() == _ownerId.toString())
                              .toList();
                          if (ownerPatients.length == 1) {
                            final only = ownerPatients.first;
                            _patientId    = int.tryParse(only['id'].toString());
                            _patientLabel = '${only['name']} (${only['species'] ?? ''})';
                          }
                        });
                      },
                      onClear: () => setState(() {
                        _ownerId = null; _ownerLabel = null;
                        _patientId = null; _patientLabel = null;
                        _patientSearchCtrl.clear();
                      }),
                      required: true,
                    ),
                    const SizedBox(height: 14),

                    // ── Patient live-search ──
                    _LiveSearchField(
                      label: 'Patient (optional)',
                      icon: Icons.pets_rounded,
                      selectedLabel: _patientLabel,
                      searchCtrl: _patientSearchCtrl,
                      items: _filteredPatients,
                      itemLabel: (p) => '${p['name']} (${p['species'] ?? ''})${_ownerId == null ? ' · ${_ownerNameFor(p['owner_id'])}' : ''}',
                      onSelect: (p) {
                        setState(() {
                          _patientId    = int.tryParse(p['id'].toString());
                          _patientLabel = '${p['name']} (${p['species'] ?? ''})';
                          // Auto-set owner from patient if not already set
                          if (_ownerId == null && p['owner_id'] != null) {
                            _ownerId = int.tryParse(p['owner_id'].toString());
                            final owner = _allOwners.firstWhere(
                              (o) => o['id'].toString() == p['owner_id'].toString(),
                              orElse: () => null);
                            if (owner != null) {
                              _ownerLabel = '${owner['last_name']}, ${owner['first_name']}';
                            }
                          }
                        });
                      },
                      onClear: () => setState(() { _patientId = null; _patientLabel = null; }),
                    ),
                    const SizedBox(height: 20),

                    _SectionHeader(icon: Icons.receipt_long_rounded, title: 'Rechnungsdetails', color: AppTheme.secondary),
                    const SizedBox(height: 12),

                    Row(children: [
                      Expanded(child: InkWell(
                        borderRadius: BorderRadius.circular(8),
                        onTap: () => _pickDate(true),
                        child: InputDecorator(
                          decoration: const InputDecoration(labelText: 'Rechnungsdatum', prefixIcon: Icon(Icons.calendar_today_rounded)),
                          child: Text(_fmtDate(_issueDate)),
                        ),
                      )),
                      const SizedBox(width: 12),
                      Expanded(child: InkWell(
                        borderRadius: BorderRadius.circular(8),
                        onTap: () => _pickDate(false),
                        child: InputDecorator(
                          decoration: const InputDecoration(labelText: 'Fällig am', prefixIcon: Icon(Icons.event_rounded)),
                          child: Text(_fmtDate(_dueDate)),
                        ),
                      )),
                    ]),
                    const SizedBox(height: 14),
                    Row(children: [
                      Expanded(child: DropdownButtonFormField<String>(
                        initialValue: _status,
                        decoration: const InputDecoration(labelText: 'Status'),
                        items: const [
                          DropdownMenuItem(value: 'draft', child: Text('Entwurf')),
                          DropdownMenuItem(value: 'open',  child: Text('Offen')),
                        ],
                        onChanged: (v) => setState(() => _status = v!),
                      )),
                      const SizedBox(width: 12),
                      Expanded(child: DropdownButtonFormField<String>(
                        initialValue: _payMethod,
                        decoration: const InputDecoration(labelText: 'Zahlungsart'),
                        items: const [
                          DropdownMenuItem(value: 'rechnung', child: Text('Rechnung')),
                          DropdownMenuItem(value: 'bar',      child: Text('Bar')),
                          DropdownMenuItem(value: 'karte',    child: Text('Karte')),
                          DropdownMenuItem(value: 'ueberweisung', child: Text('Überweisung')),
                        ],
                        onChanged: (v) => setState(() => _payMethod = v!),
                      )),
                    ]),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _notesCtrl,
                      decoration: const InputDecoration(labelText: 'Notizen', prefixIcon: Icon(Icons.notes_rounded)),
                      maxLines: 2,
                    ),
                    const SizedBox(height: 20),

                    _SectionHeader(icon: Icons.list_alt_rounded, title: 'Positionen', color: AppTheme.tertiary),
                    const SizedBox(height: 12),

                    ..._positions.asMap().entries.map((e) => _PositionRow(
                      key: ValueKey(e.key),
                      position: e.value,
                      index: e.key,
                      canRemove: _positions.length > 1,
                      treatmentTypes: _treatmentTypes,
                      onRemove: () => setState(() => _positions.removeAt(e.key)),
                      onChanged: () => setState(() {}),
                    )),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed: () => setState(() => _positions.add(_Position())),
                      icon: const Icon(Icons.add_rounded),
                      label: const Text('Position hinzufügen'),
                    ),
                    const Divider(height: 28),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppTheme.primary.withValues(alpha: 0.06),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppTheme.primary.withValues(alpha: 0.2)),
                      ),
                      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                        Text('Gesamtbetrag', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                        Text(_eur(_totalGross), style: TextStyle(
                          fontSize: 20, fontWeight: FontWeight.w800, color: AppTheme.primary, letterSpacing: -0.5,
                        )),
                      ]),
                    ),
                    const SizedBox(height: 32),
                  ],
                ),
              ),
            ),
    );
  }

  String _ownerNameFor(dynamic ownerId) {
    if (ownerId == null) return '';
    final o = _allOwners.firstWhere((o) => o['id'].toString() == ownerId.toString(), orElse: () => null);
    if (o == null) return '';
    return '${o['last_name']}, ${o['first_name']}';
  }

  String _fmtDate(String d) {
    try { return DateFormat('dd.MM.yyyy').format(DateTime.parse(d)); } catch (_) { return d; }
  }
}

// ignore: unused_element
class _SectionTitle extends StatelessWidget {
  final String text;
  const _SectionTitle(this.text);

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Text(text, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
  );
}

class _SectionHeader extends StatelessWidget {
  final IconData icon;
  final String title;
  final Color color;
  const _SectionHeader({required this.icon, required this.title, required this.color});

  @override
  Widget build(BuildContext context) {
    return Row(children: [
      Container(
        padding: const EdgeInsets.all(6),
        decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)),
        child: Icon(icon, color: color, size: 16),
      ),
      const SizedBox(width: 8),
      Text(title, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700, color: color)),
    ]);
  }
}

/// Tappable field that opens a bottom-sheet with a live search list
class _LiveSearchField extends StatelessWidget {
  final String label;
  final IconData icon;
  final String? selectedLabel;
  final TextEditingController searchCtrl;
  final List<dynamic> items;
  final String Function(dynamic) itemLabel;
  final void Function(dynamic) onSelect;
  final VoidCallback onClear;
  final bool required;

  const _LiveSearchField({
    required this.label,
    required this.icon,
    required this.selectedLabel,
    required this.searchCtrl,
    required this.items,
    required this.itemLabel,
    required this.onSelect,
    required this.onClear,
    this.required = false,
  });

  @override
  Widget build(BuildContext context) {
    final hasValue = selectedLabel != null && selectedLabel!.isNotEmpty;
    return GestureDetector(
      onTap: () => _openSheet(context),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: Icon(icon),
          suffixIcon: hasValue
              ? IconButton(icon: const Icon(Icons.close_rounded, size: 18), onPressed: onClear)
              : const Icon(Icons.search_rounded, size: 18),
          border: const OutlineInputBorder(),
          errorText: required && !hasValue ? null : null,
        ),
        child: Text(
          hasValue ? selectedLabel! : '— Tippen zum Suchen —',
          style: TextStyle(color: hasValue ? null : Colors.grey.shade500, fontSize: 14),
        ),
      ),
    );
  }

  void _openSheet(BuildContext context) {
    searchCtrl.clear();
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSS) {
          void onChange() => setSS(() {});
          searchCtrl.removeListener(onChange);
          searchCtrl.addListener(onChange);
          return DraggableScrollableSheet(
            initialChildSize: 0.7,
            minChildSize: 0.4,
            maxChildSize: 0.95,
            expand: false,
            builder: (_, sc) => Column(children: [
              Container(width: 40, height: 4, margin: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                child: TextField(
                  controller: searchCtrl,
                  autofocus: true,
                  decoration: InputDecoration(
                    hintText: 'Suchen…',
                    prefixIcon: const Icon(Icons.search_rounded),
                    suffixIcon: searchCtrl.text.isNotEmpty
                        ? IconButton(icon: const Icon(Icons.clear_rounded), onPressed: () { searchCtrl.clear(); setSS(() {}); })
                        : null,
                    isDense: true,
                  ),
                ),
              ),
              Expanded(
                child: items.isEmpty
                    ? Center(child: Text('Keine Treffer', style: TextStyle(color: Colors.grey.shade500)))
                    : ListView.builder(
                        controller: sc,
                        itemCount: items.length,
                        itemBuilder: (_, i) => ListTile(
                          title: Text(itemLabel(items[i]), style: const TextStyle(fontSize: 14)),
                          onTap: () {
                            onSelect(items[i]);
                            Navigator.pop(ctx);
                          },
                        ),
                      ),
              ),
            ]),
          );
        },
      ),
    );
  }
}

class _Position {
  String description       = '';
  double quantity          = 1.0;
  double unitPrice         = 0.0;
  double taxRate           = 0.0;
  int?   treatmentTypeId;

  double get total => quantity * unitPrice;

  Map<String, dynamic> toMap() => {
    'description':        description,
    'quantity':           quantity,
    'unit_price':         unitPrice,
    'tax_rate':           taxRate,
    'total':              total,
    if (treatmentTypeId != null) 'treatment_type_id': treatmentTypeId,
  };
}

class _PositionRow extends StatefulWidget {
  final _Position position;
  final int index;
  final bool canRemove;
  final List<dynamic> treatmentTypes;
  final VoidCallback onRemove;
  final VoidCallback onChanged;

  const _PositionRow({
    super.key,
    required this.position,
    required this.index,
    required this.canRemove,
    required this.treatmentTypes,
    required this.onRemove,
    required this.onChanged,
  });

  @override
  State<_PositionRow> createState() => _PositionRowState();
}

class _PositionRowState extends State<_PositionRow> {
  late final TextEditingController _descCtrl;
  late final TextEditingController _qtyCtrl;
  late final TextEditingController _priceCtrl;
  late final TextEditingController _taxCtrl;
  int? _selectedTreatmentTypeId;

  @override
  void initState() {
    super.initState();
    _descCtrl  = TextEditingController(text: widget.position.description);
    _qtyCtrl   = TextEditingController(text: widget.position.quantity.toString());
    _priceCtrl = TextEditingController(text: widget.position.unitPrice > 0 ? widget.position.unitPrice.toStringAsFixed(2) : '');
    _taxCtrl   = TextEditingController(text: widget.position.taxRate > 0 ? widget.position.taxRate.toStringAsFixed(0) : '0');
    _selectedTreatmentTypeId = widget.position.treatmentTypeId;
  }

  @override
  void dispose() { _descCtrl.dispose(); _qtyCtrl.dispose(); _priceCtrl.dispose(); _taxCtrl.dispose(); super.dispose(); }

  String _eur(double v) => NumberFormat.currency(locale: 'de_DE', symbol: '€').format(v);

  void _applyTreatmentType(int? id) {
    if (id == null) {
      setState(() => _selectedTreatmentTypeId = null);
      widget.position.treatmentTypeId = null;
      return;
    }
    final tt = widget.treatmentTypes.firstWhere(
      (t) => t['id'].toString() == id.toString(), orElse: () => null);
    if (tt == null) return;
    final price = double.tryParse(tt['price']?.toString().replaceAll(',', '.') ?? '') ?? 0.0;
    final name  = tt['name'] as String? ?? '';
    setState(() {
      _selectedTreatmentTypeId = id;
      widget.position.treatmentTypeId = id;
      widget.position.description = name;
      widget.position.unitPrice   = price;
      _descCtrl.text  = name;
      _priceCtrl.text = price > 0 ? price.toStringAsFixed(2) : '';
    });
    widget.onChanged();
  }

  @override
  Widget build(BuildContext context) {
    final hasTT = widget.treatmentTypes.isNotEmpty;
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            // Row header: position number + delete
            Row(children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: AppTheme.tertiary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text('Position ${widget.index + 1}',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: AppTheme.tertiary)),
              ),
              const Spacer(),
              if (widget.canRemove)
                IconButton(
                  icon: Icon(Icons.delete_outline_rounded, size: 18, color: AppTheme.danger),
                  onPressed: widget.onRemove, padding: EdgeInsets.zero),
            ]),

            // Behandlungsart dropdown (if treatment types are loaded)
            if (hasTT) ...[  
              const SizedBox(height: 10),
              InputDecorator(
                decoration: InputDecoration(
                  labelText: 'Behandlungsart',
                  prefixIcon: const Icon(Icons.category_rounded),
                  isDense: true,
                  suffixIcon: _selectedTreatmentTypeId != null
                      ? IconButton(
                          icon: const Icon(Icons.close_rounded, size: 16),
                          onPressed: () => _applyTreatmentType(null),
                          padding: EdgeInsets.zero,
                        )
                      : null,
                ),
                child: DropdownButton<int?>(
                  value: _selectedTreatmentTypeId,
                  isExpanded: true,
                  isDense: true,
                  underline: const SizedBox.shrink(),
                  hint: const Text('— Manuell eingeben —', style: TextStyle(fontSize: 13)),
                  items: [
                    const DropdownMenuItem<int?>(value: null, child: Text('— Manuell eingeben —', style: TextStyle(fontSize: 13))),
                    ...widget.treatmentTypes.map((t) {
                      final price = double.tryParse(t['price']?.toString().replaceAll(',', '.') ?? '') ?? 0.0;
                      final priceStr = price > 0 ? '  (${NumberFormat.currency(locale: 'de_DE', symbol: '€').format(price)})' : '';
                      return DropdownMenuItem<int?>(
                        value: int.tryParse(t['id'].toString()),
                        child: Text('${t['name']}$priceStr',
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 13)),
                      );
                    }),
                  ],
                  onChanged: (v) => _applyTreatmentType(v),
                ),
              ),
            ],

            const SizedBox(height: 8),
            TextFormField(
              controller: _descCtrl,
              decoration: const InputDecoration(
                labelText: 'Beschreibung *',
                isDense: true,
                prefixIcon: Icon(Icons.edit_rounded),
              ),
              onChanged: (v) { widget.position.description = v; widget.onChanged(); },
            ),
            const SizedBox(height: 8),
            Row(children: [
              Expanded(child: TextFormField(
                controller: _qtyCtrl,
                decoration: const InputDecoration(labelText: 'Anzahl', isDense: true),
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                onChanged: (v) { widget.position.quantity = double.tryParse(v) ?? 1; widget.onChanged(); },
              )),
              const SizedBox(width: 8),
              Expanded(child: TextFormField(
                controller: _priceCtrl,
                decoration: const InputDecoration(labelText: 'Preis (€)', isDense: true),
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                onChanged: (v) { widget.position.unitPrice = double.tryParse(v.replaceAll(',', '.')) ?? 0; widget.onChanged(); },
              )),
              const SizedBox(width: 8),
              Expanded(child: TextFormField(
                controller: _taxCtrl,
                decoration: const InputDecoration(labelText: 'MwSt. (%)', isDense: true),
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                onChanged: (v) { widget.position.taxRate = double.tryParse(v) ?? 0; widget.onChanged(); },
              )),
            ]),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: AppTheme.primary.withValues(alpha: 0.06),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text('Gesamt', style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                Text(_eur(widget.position.total),
                  style: TextStyle(fontWeight: FontWeight.w800, color: AppTheme.primary, fontSize: 14)),
              ]),
            ),
          ],
        ),
      ),
    );
  }
}
