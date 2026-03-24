import 'dart:io';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import '../../services/api_service.dart';

class PatientFormScreen extends StatefulWidget {
  final int? patientId;
  const PatientFormScreen({super.key, this.patientId});

  @override
  State<PatientFormScreen> createState() => _PatientFormScreenState();
}

class _PatientFormScreenState extends State<PatientFormScreen> {
  final _api     = ApiService();
  final _formKey = GlobalKey<FormState>();
  bool _loading  = false;
  bool _loadingData = false;

  final _nameCtrl    = TextEditingController();
  final _speciesCtrl = TextEditingController();
  final _breedCtrl   = TextEditingController();
  final _chipCtrl    = TextEditingController();
  final _colorCtrl   = TextEditingController();
  final _weightCtrl  = TextEditingController();
  final _notesCtrl   = TextEditingController();

  String _gender    = 'unbekannt';
  String _status    = 'active';
  String? _birthDate;
  int? _ownerId;

  List<dynamic> _owners = [];
  File? _pickedPhoto;
  String? _existingPhotoUrl;

  bool get _isEdit => widget.patientId != null;

  @override
  void initState() {
    super.initState();
    _loadOwners();
    if (_isEdit) _loadPatient();
  }

  Future<void> _loadOwners() async {
    try {
      final data = await _api.owners(perPage: 100);
      setState(() => _owners = List<dynamic>.from(data['items'] as List? ?? []));
    } catch (_) {}
  }

  Future<void> _loadPatient() async {
    setState(() => _loadingData = true);
    try {
      final p = await _api.patientShow(widget.patientId!);
      _nameCtrl.text    = p['name']        as String? ?? '';
      _speciesCtrl.text = p['species']     as String? ?? '';
      _breedCtrl.text   = p['breed']       as String? ?? '';
      _chipCtrl.text    = p['chip_number'] as String? ?? '';
      _colorCtrl.text   = p['color']       as String? ?? '';
      _notesCtrl.text   = p['notes']       as String? ?? '';
      if (p['weight'] != null) _weightCtrl.text = p['weight'].toString();
      _gender    = p['gender'] as String? ?? 'unbekannt';
      _status    = p['status'] as String? ?? 'active';
      _birthDate = p['birth_date'] as String?;
      _ownerId   = p['owner_id'] != null ? int.tryParse(p['owner_id'].toString()) : null;
      final photoUrl = p['photo_url'] as String?;
      if (photoUrl != null && photoUrl.isNotEmpty) {
        _existingPhotoUrl = ApiService.mediaUrl(photoUrl);
      }
      setState(() => _loadingData = false);
    } catch (e) {
      setState(() => _loadingData = false);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _pickPhoto() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt),
              title: const Text('Kamera'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Galerie'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
            if (_pickedPhoto != null || _existingPhotoUrl != null)
              ListTile(
                leading: const Icon(Icons.delete_outline, color: Colors.red),
                title: const Text('Foto entfernen', style: TextStyle(color: Colors.red)),
                onTap: () {
                  setState(() {
                    _pickedPhoto = null;
                    _existingPhotoUrl = null;
                  });
                  Navigator.pop(ctx);
                },
              ),
          ],
        ),
      ),
    );
    if (source == null) return;
    final picker = ImagePicker();
    final xfile = await picker.pickImage(source: source, imageQuality: 85, maxWidth: 1200);
    if (xfile != null) setState(() => _pickedPhoto = File(xfile.path));
  }

  @override
  void dispose() {
    _nameCtrl.dispose(); _speciesCtrl.dispose(); _breedCtrl.dispose();
    _chipCtrl.dispose(); _colorCtrl.dispose(); _weightCtrl.dispose(); _notesCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _loading = true);
    final data = <String, dynamic>{
      'name':        _nameCtrl.text.trim(),
      'species':     _speciesCtrl.text.trim(),
      'breed':       _breedCtrl.text.trim(),
      'gender':      _gender,
      'birth_date':  _birthDate ?? '',
      'chip_number': _chipCtrl.text.trim(),
      'color':       _colorCtrl.text.trim(),
      'notes':       _notesCtrl.text.trim(),
      'status':      _status,
      if (_ownerId != null) 'owner_id': _ownerId,
      if (_weightCtrl.text.isNotEmpty) 'weight': double.tryParse(_weightCtrl.text) ?? 0,
    };
    try {
      int patientId;
      if (_isEdit) {
        await _api.patientUpdate(widget.patientId!, data);
        patientId = widget.patientId!;
      } else {
        final created = await _api.patientCreate(data);
        patientId = created['id'] is int
            ? created['id'] as int
            : int.parse(created['id'].toString());
      }
      if (_pickedPhoto != null) {
        try {
          await _api.patientPhotoUpload(patientId, _pickedPhoto!);
        } catch (_) {}
      }
      if (mounted) context.pop();
    } catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  Widget _buildPhotoWidget(BuildContext context) {
    final token = ApiService.getToken();
    ImageProvider? imageProvider;
    if (_pickedPhoto != null) {
      imageProvider = FileImage(_pickedPhoto!);
    } else if (_existingPhotoUrl != null) {
      imageProvider = NetworkImage(_existingPhotoUrl!);
    }

    return Center(
      child: Stack(
        children: [
          GestureDetector(
            onTap: _pickPhoto,
            child: CircleAvatar(
              radius: 52,
              backgroundColor: Theme.of(context).colorScheme.surfaceContainerHighest,
              backgroundImage: imageProvider,
              child: imageProvider == null
                  ? Icon(Icons.pets, size: 48, color: Theme.of(context).colorScheme.onSurfaceVariant)
                  : null,
            ),
          ),
          Positioned(
            bottom: 0,
            right: 0,
            child: GestureDetector(
              onTap: _pickPhoto,
              child: CircleAvatar(
                radius: 16,
                backgroundColor: Theme.of(context).colorScheme.primary,
                child: const Icon(Icons.camera_alt, size: 16, color: Colors.white),
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_isEdit ? 'Patient bearbeiten' : 'Neuer Patient')),
      body: _loadingData
          ? const Center(child: CircularProgressIndicator())
          : Form(
              key: _formKey,
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    _buildPhotoWidget(context),
                    const SizedBox(height: 20),
                    TextFormField(
                      controller: _nameCtrl,
                      decoration: const InputDecoration(labelText: 'Name *', prefixIcon: Icon(Icons.pets)),
                      validator: (v) => (v == null || v.isEmpty) ? 'Name eingeben' : null,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _speciesCtrl,
                      decoration: const InputDecoration(labelText: 'Tierart *', prefixIcon: Icon(Icons.category_outlined)),
                      validator: (v) => (v == null || v.isEmpty) ? 'Tierart eingeben' : null,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _breedCtrl,
                      decoration: const InputDecoration(labelText: 'Rasse', prefixIcon: Icon(Icons.info_outlined)),
                    ),
                    const SizedBox(height: 14),
                    DropdownButtonFormField<String>(
                      initialValue: _gender,
                      decoration: const InputDecoration(labelText: 'Geschlecht'),
                      items: const [
                        DropdownMenuItem(value: 'männlich',    child: Text('Männlich')),
                        DropdownMenuItem(value: 'weiblich',    child: Text('Weiblich')),
                        DropdownMenuItem(value: 'kastriert',   child: Text('Kastriert')),
                        DropdownMenuItem(value: 'sterilisiert',child: Text('Sterilisiert')),
                        DropdownMenuItem(value: 'unbekannt',   child: Text('Unbekannt')),
                      ],
                      onChanged: (v) => setState(() => _gender = v!),
                    ),
                    const SizedBox(height: 14),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Geburtsdatum'),
                      subtitle: Text(_birthDate ?? 'Nicht angegeben'),
                      trailing: const Icon(Icons.calendar_today),
                      onTap: () async {
                        final d = await showDatePicker(
                          context: context,
                          initialDate: _birthDate != null ? DateTime.tryParse(_birthDate!) ?? DateTime.now() : DateTime.now(),
                          firstDate: DateTime(1990),
                          lastDate: DateTime.now(),
                        );
                        if (d != null) setState(() => _birthDate = d.toIso8601String().substring(0, 10));
                      },
                    ),
                    const Divider(),
                    TextFormField(
                      controller: _chipCtrl,
                      decoration: const InputDecoration(labelText: 'Chip-Nummer', prefixIcon: Icon(Icons.qr_code)),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _colorCtrl,
                      decoration: const InputDecoration(labelText: 'Farbe / Zeichnung', prefixIcon: Icon(Icons.palette_outlined)),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _weightCtrl,
                      decoration: const InputDecoration(labelText: 'Gewicht (kg)', prefixIcon: Icon(Icons.monitor_weight_outlined)),
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    ),
                    const SizedBox(height: 14),
                    if (_owners.isNotEmpty)
                      DropdownButtonFormField<int>(
                        initialValue: _ownerId,
                        decoration: const InputDecoration(labelText: 'Tierhalter', prefixIcon: Icon(Icons.person_outlined)),
                        items: [
                          const DropdownMenuItem(value: null, child: Text('— kein Tierhalter —')),
                          ..._owners.map((o) => DropdownMenuItem(
                            value: int.tryParse(o['id'].toString()),
                            child: Text('${o['last_name']}, ${o['first_name']}'),
                          )),
                        ],
                        onChanged: (v) => setState(() => _ownerId = v),
                      ),
                    const SizedBox(height: 14),
                    DropdownButtonFormField<String>(
                      initialValue: _status,
                      decoration: const InputDecoration(labelText: 'Status'),
                      items: const [
                        DropdownMenuItem(value: 'active',   child: Text('Aktiv')),
                        DropdownMenuItem(value: 'inactive', child: Text('Inaktiv')),
                        DropdownMenuItem(value: 'deceased', child: Text('Verstorben')),
                      ],
                      onChanged: (v) => setState(() => _status = v!),
                    ),
                    const SizedBox(height: 14),
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
                          : Text(_isEdit ? 'Speichern' : 'Patient erstellen'),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
