import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../models/befundbogen.dart';
import '../../services/api_service.dart';

class BefundDetailScreen extends StatefulWidget {
  final int id;
  const BefundDetailScreen({super.key, required this.id});

  @override
  State<BefundDetailScreen> createState() => _BefundDetailScreenState();
}

class _BefundDetailScreenState extends State<BefundDetailScreen> {
  final _api = ApiService();
  Befundbogen? _item;
  bool _loading = true;
  String _error = '';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = ''; });
    try {
      final data = await _api.befundeShow(widget.id);
      setState(() {
        _item = Befundbogen.fromJson(data);
        _loading = false;
      });
    } catch (e) {
      setState(() { _loading = false; _error = e.toString(); });
    }
  }

  Future<void> _openPdf() async {
    try {
      final url = await _api.befundePdfUrl(widget.id);
      if (url.isNotEmpty) {
        await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('PDF-Fehler: $e')),
        );
      }
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

    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Befundbogen')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_error.isNotEmpty || _item == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Befundbogen')),
        body: Center(child: Text(_error.isNotEmpty ? _error : 'Nicht gefunden',
          style: const TextStyle(color: Colors.red))),
      );
    }

    final b = _item!;
    final statusColor = _statusColor(b.status);

    return Scaffold(
      appBar: AppBar(
        title: Text(b.number),
        actions: [
          IconButton(
            icon: const Icon(Icons.picture_as_pdf_outlined),
            tooltip: 'Als PDF öffnen',
            onPressed: _openPdf,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Status + meta card
            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Expanded(child: Text(b.patientName ?? '—',
                      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700))),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: statusColor.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: statusColor.withOpacity(0.3)),
                      ),
                      child: Text(b.statusLabel,
                        style: TextStyle(color: statusColor, fontWeight: FontWeight.w600, fontSize: 12)),
                    ),
                  ]),
                  if (b.patientSpecies != null) ...[
                    const SizedBox(height: 2),
                    Text(b.patientSpecies!, style: theme.textTheme.bodySmall),
                  ],
                  const Divider(height: 20),
                  _metaRow(context, Icons.calendar_today, 'Datum', b.formattedDatum),
                  if (b.naechsterTermin != null && b.naechsterTermin!.isNotEmpty)
                    _metaRow(context, Icons.event_available, 'Nächster Termin', _formatDate(b.naechsterTermin!)),
                  if (b.erstellerName != null)
                    _metaRow(context, Icons.person_outline, 'Behandler', b.erstellerName!),
                  if (b.pdfSentAt != null)
                    _metaRow(context, Icons.mark_email_read_outlined, 'Versendet',
                      '${_formatDate(b.pdfSentAt!)}${b.pdfSentTo != null ? ' · ${b.pdfSentTo}' : ''}'),
                ]),
              ),
            ),
            const SizedBox(height: 12),

            if (b.felder.isNotEmpty) ...[
              _sectionCard(context, 'Anamnese & Vorgeschichte', {
                'hauptbeschwerde': 'Hauptbeschwerde',
                'seit_wann': 'Seit wann / Verlauf',
                'vorerkrankungen': 'Vorerkrankungen / OPs',
                'medikamente': 'Medikamente',
                'allergien': 'Allergien',
                'ernaehrung': 'Ernährung',
                'bewegung': 'Bewegung',
                'haltung': 'Haltung',
                'bisherige_therapien': 'Bisherige Therapien',
              }, b.felder),
              _sectionCard(context, 'Allgemeinbefund', {
                'allgemeinbefinden': 'Allgemeinbefinden',
                'ernaehrungszustand': 'Ernährungszustand',
                'temperament': 'Temperament',
                'koerpertemperatur': 'Körpertemperatur (°C)',
                'koerperhaltung': 'Körperhaltung',
                'gangbild': 'Gangbild',
                'lahmheitsgrad': 'Lahmheitsgrad (0–5)',
              }, b.felder),
              _sectionCard(context, 'Bewegungsapparat & Muskulatur', {
                'betroffene_regionen': 'Betroffene Regionen',
                'muskeltonus': 'Muskeltonus',
                'triggerpunkte': 'Triggerpunkte',
                'schmerz_nrs': 'Schmerzskala NRS',
                'gelenke_befund': 'Gelenkbefund',
                'neurologischer_status': 'Neurologischer Status',
              }, b.felder),
              _sectionCard(context, 'Naturheilkundliche Diagnostik', {
                'konstitutionstyp': 'Konstitutionstyp',
                'energetischer_eindruck': 'Energetischer Eindruck',
                'bachblueten_emotionen': 'Bach-Blüten Emotionen',
                'bachblueten_auswahl': 'Bach-Blüten Mischung',
                'bachblueten_dosierung': 'Dosierung',
                'homoeopathie_mittel': 'Homöopathisches Mittel',
                'homoeopathie_potenz': 'Potenz',
                'homoeopathie_dauer': 'Wiederholung / Dauer',
                'phytotherapie': 'Phytotherapie',
                'schuesslersalze': 'Schüssler Salze',
                'weitere_naturheilmittel': 'Weitere Mittel',
              }, b.felder),
              _sectionCard(context, 'Physiotherapeutische Maßnahmen', {
                'pt_methoden': 'Angewandte Methoden',
                'therapieziele': 'Therapieziele',
                'hausaufgaben': 'Hausaufgaben',
                'therapiefrequenz': 'Frequenz',
                'therapiedauer': 'Dauer',
                'kontrolltermin': 'Kontrolltermin',
              }, b.felder),
              _sectionCard(context, 'Verlauf & Notizen', {
                'verlauf_notizen': 'Verlaufsnotizen',
              }, b.felder),
            ] else
              Center(
                child: Padding(
                  padding: const EdgeInsets.all(32),
                  child: Column(children: [
                    Icon(Icons.description_outlined, size: 48,
                      color: theme.colorScheme.outlineVariant),
                    const SizedBox(height: 8),
                    const Text('Keine Felder ausgefüllt'),
                  ]),
                ),
              ),

            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: _openPdf,
              icon: const Icon(Icons.picture_as_pdf_outlined),
              label: const Text('Als PDF öffnen'),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _metaRow(BuildContext context, IconData icon, String label, String value) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, size: 16, color: theme.colorScheme.outline),
        const SizedBox(width: 8),
        Text('$label: ', style: theme.textTheme.bodySmall?.copyWith(
          color: theme.colorScheme.outline, fontWeight: FontWeight.w600)),
        Expanded(child: Text(value, style: theme.textTheme.bodySmall)),
      ]),
    );
  }

  Widget _sectionCard(BuildContext context, String title,
      Map<String, String> fields, Map<String, dynamic> felder) {
    final visible = fields.entries
        .where((e) => felder.containsKey(e.key) && _fieldValue(felder[e.key]).isNotEmpty)
        .toList();
    if (visible.isEmpty) return const SizedBox.shrink();

    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Card(
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 12),
            ...visible.map((e) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                SizedBox(
                  width: 140,
                  child: Text(e.value,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.outline,
                      fontWeight: FontWeight.w600,
                    )),
                ),
                Expanded(child: Text(_fieldValue(felder[e.key]),
                  style: theme.textTheme.bodySmall)),
              ]),
            )),
          ]),
        ),
      ),
    );
  }

  String _fieldValue(dynamic v) {
    if (v == null) return '';
    if (v is List) return v.join(', ');
    return v.toString();
  }

  String _formatDate(String s) {
    try {
      final d = DateTime.parse(s);
      return '${d.day.toString().padLeft(2, '0')}.${d.month.toString().padLeft(2, '0')}.${d.year}';
    } catch (_) {
      return s;
    }
  }
}
