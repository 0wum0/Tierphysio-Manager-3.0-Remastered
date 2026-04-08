class Befundbogen {
  final int id;
  final int patientId;
  final int? ownerId;
  final String status;
  final String datum;
  final String? naechsterTermin;
  final String? pdfSentAt;
  final String? pdfSentTo;
  final String? erstellerName;
  final String? patientName;
  final String? patientSpecies;
  final String? ownerName;
  final String createdAt;
  final Map<String, dynamic> felder;

  const Befundbogen({
    required this.id,
    required this.patientId,
    this.ownerId,
    required this.status,
    required this.datum,
    this.naechsterTermin,
    this.pdfSentAt,
    this.pdfSentTo,
    this.erstellerName,
    this.patientName,
    this.patientSpecies,
    this.ownerName,
    required this.createdAt,
    this.felder = const {},
  });

  factory Befundbogen.fromJson(Map<String, dynamic> j) => Befundbogen(
        id: int.parse(j['id'].toString()),
        patientId: int.parse(j['patient_id'].toString()),
        ownerId: j['owner_id'] != null ? int.tryParse(j['owner_id'].toString()) : null,
        status: j['status'] as String? ?? 'entwurf',
        datum: j['datum'] as String? ?? '',
        naechsterTermin: j['naechster_termin'] as String?,
        pdfSentAt: j['pdf_sent_at'] as String?,
        pdfSentTo: j['pdf_sent_to'] as String?,
        erstellerName: j['ersteller_name'] as String?,
        patientName: j['patient_name'] as String?,
        patientSpecies: j['patient_species'] as String?,
        ownerName: j['owner_name'] as String?,
        createdAt: j['created_at'] as String? ?? '',
        felder: j['felder'] is Map ? Map<String, dynamic>.from(j['felder'] as Map) : {},
      );

  String get statusLabel => switch (status) {
        'versendet' => 'Versendet',
        'abgeschlossen' => 'Abgeschlossen',
        _ => 'Entwurf',
      };

  String get formattedDatum {
    try {
      final d = DateTime.parse(datum);
      return '${d.day.toString().padLeft(2, '0')}.${d.month.toString().padLeft(2, '0')}.${d.year}';
    } catch (_) {
      return datum;
    }
  }

  String get number => 'BF-${id.toString().padLeft(4, '0')}';
}
