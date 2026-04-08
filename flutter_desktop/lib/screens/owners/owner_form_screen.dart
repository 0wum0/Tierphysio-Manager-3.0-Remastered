import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../services/api_service.dart';

class OwnerFormScreen extends StatefulWidget {
  final int? ownerId;
  const OwnerFormScreen({super.key, this.ownerId});

  @override
  State<OwnerFormScreen> createState() => _OwnerFormScreenState();
}

class _OwnerFormScreenState extends State<OwnerFormScreen> {
  final _api     = ApiService();
  final _formKey = GlobalKey<FormState>();
  bool _loading  = false;
  bool _loadingData = false;

  final _firstNameCtrl = TextEditingController();
  final _lastNameCtrl  = TextEditingController();
  final _emailCtrl     = TextEditingController();
  final _phoneCtrl     = TextEditingController();
  final _addressCtrl   = TextEditingController();
  final _cityCtrl      = TextEditingController();
  final _zipCtrl       = TextEditingController();
  final _notesCtrl     = TextEditingController();

  bool get _isEdit => ownerId != null;
  int? get ownerId => widget.ownerId;

  @override
  void initState() {
    super.initState();
    if (_isEdit) _loadOwner();
  }

  Future<void> _loadOwner() async {
    setState(() => _loadingData = true);
    try {
      final o = await _api.ownerShow(ownerId!);
      _firstNameCtrl.text = o['first_name'] as String? ?? '';
      _lastNameCtrl.text  = o['last_name']  as String? ?? '';
      _emailCtrl.text     = o['email']      as String? ?? '';
      _phoneCtrl.text     = o['phone']      as String? ?? '';
      _addressCtrl.text   = o['address']    as String? ?? '';
      _cityCtrl.text      = o['city']       as String? ?? '';
      _zipCtrl.text       = o['zip']        as String? ?? '';
      _notesCtrl.text     = o['notes']      as String? ?? '';
      setState(() => _loadingData = false);
    } catch (e) {
      setState(() => _loadingData = false);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  void dispose() {
    _firstNameCtrl.dispose(); _lastNameCtrl.dispose(); _emailCtrl.dispose();
    _phoneCtrl.dispose(); _addressCtrl.dispose(); _cityCtrl.dispose();
    _zipCtrl.dispose(); _notesCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _loading = true);
    final data = {
      'first_name': _firstNameCtrl.text.trim(),
      'last_name':  _lastNameCtrl.text.trim(),
      'email':      _emailCtrl.text.trim(),
      'phone':      _phoneCtrl.text.trim(),
      'address':    _addressCtrl.text.trim(),
      'city':       _cityCtrl.text.trim(),
      'zip':        _zipCtrl.text.trim(),
      'notes':      _notesCtrl.text.trim(),
    };
    try {
      if (_isEdit) {
        await _api.ownerUpdate(ownerId!, data);
      } else {
        await _api.ownerCreate(data);
      }
      if (mounted) context.pop();
    } catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_isEdit ? 'Tierhalter bearbeiten' : 'Neuer Tierhalter')),
      body: _loadingData
          ? const Center(child: CircularProgressIndicator())
          : Form(
              key: _formKey,
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    Row(children: [
                      Expanded(child: TextFormField(
                        controller: _firstNameCtrl,
                        decoration: const InputDecoration(labelText: 'Vorname'),
                      )),
                      const SizedBox(width: 12),
                      Expanded(child: TextFormField(
                        controller: _lastNameCtrl,
                        decoration: const InputDecoration(labelText: 'Nachname *'),
                        validator: (v) => (v == null || v.isEmpty) ? 'Nachname eingeben' : null,
                      )),
                    ]),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _emailCtrl,
                      decoration: const InputDecoration(labelText: 'E-Mail', prefixIcon: Icon(Icons.email_outlined)),
                      keyboardType: TextInputType.emailAddress,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _phoneCtrl,
                      decoration: const InputDecoration(labelText: 'Telefon', prefixIcon: Icon(Icons.phone_outlined)),
                      keyboardType: TextInputType.phone,
                    ),
                    const Divider(height: 28),
                    TextFormField(
                      controller: _addressCtrl,
                      decoration: const InputDecoration(labelText: 'Straße & Hausnummer', prefixIcon: Icon(Icons.home_outlined)),
                    ),
                    const SizedBox(height: 14),
                    Row(children: [
                      SizedBox(width: 100, child: TextFormField(
                        controller: _zipCtrl,
                        decoration: const InputDecoration(labelText: 'PLZ'),
                        keyboardType: TextInputType.number,
                      )),
                      const SizedBox(width: 12),
                      Expanded(child: TextFormField(
                        controller: _cityCtrl,
                        decoration: const InputDecoration(labelText: 'Stadt'),
                      )),
                    ]),
                    const Divider(height: 28),
                    TextFormField(
                      controller: _notesCtrl,
                      decoration: const InputDecoration(labelText: 'Notizen', prefixIcon: Icon(Icons.notes)),
                      maxLines: 3,
                    ),
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: _loading ? null : _submit,
                      child: _loading
                          ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : Text(_isEdit ? 'Speichern' : 'Tierhalter erstellen'),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
