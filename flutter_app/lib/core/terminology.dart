class Terminology {
  final bool isTrainer;
  const Terminology({required this.isTrainer});

  String get patientSingular => isTrainer ? 'Hund' : 'Patient';
  String get patientPlural => isTrainer ? 'Hunde' : 'Patienten';
  String get ownerSingular => isTrainer ? 'Halter' : 'Tierhalter';
  String get ownerPlural => isTrainer ? 'Halter' : 'Tierhalter';
  String get newPatient => isTrainer ? 'Neuer Hund' : 'Neuer Patient';
  String get newOwner => isTrainer ? 'Neuer Halter' : 'Neuer Tierhalter';
  String get patientRecord => isTrainer ? 'Hundeakte' : 'Patientenakte';

  String patientsFoundEmpty() => 'Keine $patientPlural gefunden';
  String ownersFoundEmpty() => 'Keine $ownerPlural gefunden';
  String searchHint() => '$patientPlural, $ownerPlural, Rechnungen…';
}
