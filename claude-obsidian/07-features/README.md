# Features Hub

## Beschreibung
Zentrale Sammlung fachlicher Module inkl. Live-Stand, Risiken und offenen Punkten.

## Zweck
Feature-Wissen entkoppeln, damit Änderungen nicht an implizitem Wissen hängen.

## Relevante Dateien im Repo
- `plugins/`
- `templates/`
- `flutter_app/lib/screens/`

## Datenfluss
Feature auswählen → Detaildatei öffnen → verlinkte Quell-Dateien prüfen.

## Wichtige Regeln
- Pro neues Feature eigene Datei in `07-features/`.
- Status explizit markieren: `implemented`, `partial`, `planned`, `unknown`.

## Risiken
- Fehlende Statusangabe führt zu falschen Implementierungsannahmen.

## Statusmatrix

**Zuletzt aktualisiert:** 2026-07-01 — Vollaudit gegen echten Code (nicht nur gegen alte Doku-Stände).

| Feature | Status | Offene Punkte |
|---|---|---|
| [[07-features/homework-plugin]] | implemented | Keine bekannten funktionalen TODOs im Brain |
| [[07-features/whatsapp-style-chat]] | implemented | Video-Preview, Lightbox, Bildoptimierung/Resize |
| [[07-features/chat-media-system]] | implemented | Bilder, Dokumente und Videos im Chat |
| [[07-features/google-calendar-sync]] | active | UI-Anzeige Matching, Recurring-Expansion und Europe/Berlin-Normalisierung umgesetzt |
| [[07-features/3d-befundmodell-schmerzskala]] | implemented basis | Read-only-NRS in Show-Views, PDF-Zusammenfassung fuer Marker/Zeichnungen |
| [[07-features/veterinary-anatomy-engine]] | planned/blocked by assets | Layer-Engine/3D-Assets noch nicht umgesetzt |
| [[07-features/hundeschulen-support]] | implemented | Dashboard-Paketverkauf per AJAX/JSON und befuelltem Paketkatalog |
| [[07-features/kurs-system-hundeschulen]] | implemented | Öffentliche Endkunden-Online-Buchung ohne Login pruefen |
| [[07-features/terminbuchung]] | implemented | Soll-/Ist- und E2E-Doku offen |
| [[07-features/smart-erinnerungen]] | implemented | Soll-/Ist- und E2E-Doku offen |
| [[07-features/fortschrittssystem]] | implemented via TherapyCarePro | E2E-Doku offen |
| [[07-features/video-feedback]] | **partial** | Upload/Vorher-Nachher ja, kein Annotations-/Kommentar-Modul |
| [[07-features/finanz-autopilot]] | **partial** | Nur manuelle Mahnungen, keine automatische Fristen-Eskalation |
| [[07-features/marketing-automation]] | **not_found** | Nur Dashboard-Reporting, kein Kampagnen-/Automations-Backend |
| [[07-features/zahlung-im-portal]] | **partial** | Nur Rechnungsliste/PDF, keine Online-Zahlfunktion fuer Tierbesitzer |
| [[07-features/praxis-vs-hundeschule]] | implemented | Terminology-Switching in `terminology.dart` bestaetigt |
| [[07-features/therapycare-ai]] | **not_found (AI)** | Basissystem ohne KI implementiert; "AI" ist Branding ohne Backend |
| [[07-features/trainingcare-ai]] | **implemented (Trainingsplan-System), AI: not_found** | Trainingsplaene real umgesetzt, KI-Anteil existiert nicht |
| [[07-features/gamification]] | **partial** | Nur Score-Badges/Fortschrittsbalken, keine Punkte/Streaks/Achievements |

## TODOs
- Detailseiten mit generischem TODO "Fachlichen Soll-/Ist-Vergleich ergänzen" schrittweise konkretisieren.
- Open Items in [[00-start/open-items]] nach jeder Umsetzung synchron halten.

## Verlinkungen
- [[07-features/whatsapp-style-chat]]
- [[07-features/fortschrittssystem]]
- [[07-features/therapycare-ai]]
- [[07-features/trainingcare-ai]]
- [[07-features/smart-erinnerungen]]
- [[07-features/video-feedback]]
- [[07-features/terminbuchung]]
- [[07-features/zahlung-im-portal]]
- [[07-features/gamification]]
- [[07-features/marketing-automation]]
- [[07-features/finanz-autopilot]]
- [[07-features/kurs-system-hundeschulen]]
- [[07-features/praxis-vs-hundeschule]]
