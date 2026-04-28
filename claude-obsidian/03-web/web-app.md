# Web App (Praxis)

## Beschreibung
Server-rendered Praxisoberfläche (Twig) mit klinischem und dogschool-spezifischem UI.

## Zweck
Dokumentiert UI-Referenzverhalten, das Flutter fachlich spiegeln muss.

## Relevante Dateien im Repo
- `templates/layouts/base.twig`
- `templates/dashboard/*`
- `templates/settings/index.twig`
- `templates/dogschool/*`
- `app/Controllers/*Controller.php`

## Datenfluss
Browser → Web-Routen → Controller → Repositories → Twig-Template.

## Wichtige Regeln
- Web ist fachliche Referenz für mobile Flows.
- CSRF in POST-Formularen.
- Feature-Flags steuern sichtbare Menüs/Funktionen.

## Risiken
- UI-Änderungen ohne API-Angleich erzeugen Drift zu Flutter.

## TODOs
- Kritische Screens als Prozesskarten dokumentieren (Patient, Rechnung, Termine).

## Verlinkungen
- [[04-flutter/flutter-app]]
- [[07-features/terminbuchung]]
- [[07-features/kurs-system-hundeschulen]]
