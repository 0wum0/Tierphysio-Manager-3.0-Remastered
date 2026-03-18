import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';

class InvoiceFormScreen extends StatefulWidget {
  const InvoiceFormScreen({super.key});

  @override
  State<InvoiceFormScreen> createState() => _InvoiceFormScreenState();
}

class _InvoiceFormScreenState extends State<InvoiceFormScreen> {
  final _api     = ApiService();
  final _formKey = GlobalKey<FormState>();
  bool _loading  = false;

  int?   _ownerId;
  int?   _patientId;
  String _issueDate = DateTime.now().toIso8601String().substring(0, 10);
  String _dueDate   = DateTime.now().add(const Duration(days: 14)).toIso8601String().substring(0, 10);
  String _status    = 'open';
  String _payMethod = 'rechnung';
  final _notesCtrl  = TextEditingController();

  List<dynamic> _owners   = [];
  List<dynamic> _patients = [];
  List<dynamic> _filteredPatients = [];
  List<_Position> _positions = [_Position()];

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    try {
      final [o, p] = await Future.wait([
        _api.owners(perPage: 100),
        _api.patients(perPage: 200),
      ]);
      setState(() {
        _owners   = List<dynamic>.from((o as Map)['items'] as List? ?? []);
        _patients = List<dynamic>.from((p as Map)['items'] as List? ?? []);
        _filteredPatients = _patients;
      });
    } catch (_) {}
  }

  void _onOwnerChanged(int? id) {
    setState(() {
      _ownerId  = id;
      _patientId = null;
      _filteredPatients = id == null
          ? _patients
          : _patients.where((p) => p['owner_id']?.toString() == id.toString()).toList();
    });
  }

  double get _totalGross => _positions.fold(0.0, (s, p) => s + p.total);

  String _eur(double v) => NumberFormat.currency(locale: 'de_DE', symbol: '€').format(v);

  Future<void> _pickDate(bool isIssue) async {
    final initial = DateTime.tryParse(isIssue ? _issueDate : _dueDate) ?? DateTime.now();
    final d = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: DateTime(2030),
    );
    if (d == null) return;
    setState(() {
      final s = d.toIso8601String().substring(0, 10);
      if (isIssue) _issueDate = s; else _dueDate = s;
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_ownerId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Bitte Tierhalter auswählen')));
      return;
    }
    if (_positions.every((p) => p.description.isEmpty)) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Mindestens eine Position eingeben')));
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
      if (mounted) {
        setState(() => _loading = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  @override
  void dispose() { _notesCtrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Neue Rechnung'),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: FilledButton(
              onPressed: _loading ? null : _submit,
              child: _loading
                  ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Speichern'),
            ),
          ),
        ],
      ),
      body: Form(
        key: _formKey,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _SectionTitle('Grunddaten'),
              DropdownButtonFormField<int>(
                initialValue: _ownerId,
                decoration: const InputDecoration(labelText: 'Tierhalter *', prefixIcon: Icon(Icons.person_outlined)),
                items: [
                  const DropdownMenuItem(value: null, child: Text('— Tierhalter wählen —')),
                  ..._owners.map((o) => DropdownMenuItem(
                    value: int.tryParse(o['id'].toString()),
                    child: Text('${o['last_name']}, ${o['first_name']}'),
                  )),
                ],
                onChanged: _onOwnerChanged,
                validator: (v) => v == null ? 'Tierhalter wählen' : null,
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<int>(
                initialValue: _patientId,
                decoration: const InputDecoration(labelText: 'Patient', prefixIcon: Icon(Icons.pets)),
                items: [
                  const DropdownMenuItem(value: null, child: Text('— kein Patient —')),
                  ..._filteredPatients.map((p) => DropdownMenuItem(
                    value: int.tryParse(p['id'].toString()),
                    child: Text('${p['name']} (${p['species']})'),
                  )),
                ],
                onChanged: (v) => setState(() => _patientId = v),
              ),
              const SizedBox(height: 14),
              Row(children: [
                Expanded(child: InkWell(
                  onTap: () => _pickDate(true),
                  child: InputDecorator(
                    decoration: const InputDecoration(labelText: 'Rechnungsdatum', prefixIcon: Icon(Icons.calendar_today)),
                    child: Text(_fmtDate(_issueDate)),
                  ),
                )),
                const SizedBox(width: 12),
                Expanded(child: InkWell(
                  onTap: () => _pickDate(false),
                  child: InputDecorator(
                    decoration: const InputDecoration(labelText: 'Fällig am', prefixIcon: Icon(Icons.event)),
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
                  ],
                  onChanged: (v) => setState(() => _payMethod = v!),
                )),
              ]),
              const SizedBox(height: 14),
              TextFormField(
                controller: _notesCtrl,
                decoration: const InputDecoration(labelText: 'Notizen', prefixIcon: Icon(Icons.notes)),
                maxLines: 2,
              ),
              const SizedBox(height: 20),
              _SectionTitle('Positionen'),
              ..._positions.asMap().entries.map((e) => _PositionRow(
                key: ValueKey(e.key),
                position: e.value,
                index: e.key,
                canRemove: _positions.length > 1,
                onRemove: () => setState(() => _positions.removeAt(e.key)),
                onChanged: () => setState(() {}),
              )),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => setState(() => _positions.add(_Position())),
                icon: const Icon(Icons.add),
                label: const Text('Position hinzufügen'),
              ),
              const Divider(height: 28),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Text('Gesamt: ', style: Theme.of(context).textTheme.titleMedium),
                  Text(_eur(_totalGross), style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold, color: Theme.of(context).colorScheme.primary,
                  )),
                ],
              ),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }

  String _fmtDate(String d) {
    try { return DateFormat('dd.MM.yyyy').format(DateTime.parse(d)); } catch (_) { return d; }
  }
}

class _SectionTitle extends StatelessWidget {
  final String text;
  const _SectionTitle(this.text);

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Text(text, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
  );
}

class _Position {
  String description = '';
  double quantity    = 1.0;
  double unitPrice   = 0.0;
  double taxRate     = 0.0;

  double get total => quantity * unitPrice;

  Map<String, dynamic> toMap() => {
    'description': description,
    'quantity':    quantity,
    'unit_price':  unitPrice,
    'tax_rate':    taxRate,
    'total':       total,
  };
}

class _PositionRow extends StatefulWidget {
  final _Position position;
  final int index;
  final bool canRemove;
  final VoidCallback onRemove;
  final VoidCallback onChanged;

  const _PositionRow({
    super.key,
    required this.position,
    required this.index,
    required this.canRemove,
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

  @override
  void initState() {
    super.initState();
    _descCtrl  = TextEditingController(text: widget.position.description);
    _qtyCtrl   = TextEditingController(text: widget.position.quantity.toString());
    _priceCtrl = TextEditingController(text: widget.position.unitPrice > 0 ? widget.position.unitPrice.toStringAsFixed(2) : '');
    _taxCtrl   = TextEditingController(text: widget.position.taxRate.toString());
  }

  @override
  void dispose() { _descCtrl.dispose(); _qtyCtrl.dispose(); _priceCtrl.dispose(); _taxCtrl.dispose(); super.dispose(); }

  String _eur(double v) => NumberFormat.currency(locale: 'de_DE', symbol: '€').format(v);

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            Row(
              children: [
                Text('Position ${widget.index + 1}', style: Theme.of(context).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.bold)),
                const Spacer(),
                if (widget.canRemove)
                  IconButton(icon: const Icon(Icons.delete_outline, size: 18), color: Colors.red, onPressed: widget.onRemove, padding: EdgeInsets.zero),
              ],
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: _descCtrl,
              decoration: const InputDecoration(labelText: 'Beschreibung *', isDense: true),
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
                decoration: const InputDecoration(labelText: 'Einzelpreis (€)', isDense: true),
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
            const SizedBox(height: 6),
            Align(
              alignment: Alignment.centerRight,
              child: Text('Gesamt: ${_eur(widget.position.total)}',
                  style: const TextStyle(fontWeight: FontWeight.bold)),
            ),
          ],
        ),
      ),
    );
  }
}
