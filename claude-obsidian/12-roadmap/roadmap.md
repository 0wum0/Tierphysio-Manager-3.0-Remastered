# Roadmap

## Beschreibung
Geplante Themenblöcke nach Architekturdomänen.

## Zweck
Orientierung, welche Features live sind vs. geplant.

## Relevante Dateien im Repo
- `templates/dogschool/*`
- `plugins/therapy-care-pro/*`
- `plugins/owner-portal/*`

## Datenfluss
Produktidee → Entscheidung → Implementierung → Brain-Update.

## Wichtige Regeln
- Roadmap-Einträge als `planned`, `in_progress`, `released`, `paused` markieren.

## Risiken
- Unklare Roadmap führt zu inkonsistenten Agent-Implementierungen.

## Priorisierte Roadmap

**Zuletzt aktualisiert:** 2026-05-12  
**Quelle:** [[00-start/open-items]] + Feature-Detailseiten.

| Prioritaet | Thema | Status | Referenz |
|---|---|---|---|
| P0 | Hundeschulen-Dashboard: Paket-Verkauf-Modal AJAX/JSON-Fix | released 2026-05-12 | [[10-bugs/known-bugs]] |
| P1 | Dashboard-Paketverkauf: Paketkatalog dynamisch befuellen | released 2026-05-12 | [[07-features/hundeschulen-support]] |
| P1 | Befund-Show: NRS als Read-only-Skala anzeigen | released 2026-05-12 | [[07-features/3d-befundmodell-schmerzskala]] |
| P1 | Befund-PDF: Anatomy-Zeichnungen/Marker exportieren | released 2026-05-12 | [[07-features/3d-befundmodell-schmerzskala]] |
| P1 | Google Kalender: Patient/Owner-Matching in Admin-UI sichtbar machen | released 2026-05-12 | [[07-features/google-calendar-sync]] |
| P1 | Chat-Medien: Video-Preview und Bild-Resize | released 2026-05-12 | [[07-features/whatsapp-style-chat]] |
| P2 | Google Kalender: Recurring Events und Timezone-Verhalten klaeren | verified/released 2026-05-12 | [[07-features/google-calendar-sync]] |
| P2 | Veterinary Anatomy Engine: Layer-Engine/Assets planen | blocked/planned | [[07-features/veterinary-anatomy-engine]] |
| P2 | Feature-Dokus: Soll-/Ist- und E2E-Flows konkretisieren | open | [[07-features/README]] |
| P2 | Finanz-Autopilot: Cron-Erinnerung an Mitarbeiter bei unbearbeiteten überfälligen Rechnungen (Mahnwesen selbst ist bereits vollstaendig, siehe Korrektur Teil 4) | open, optionale Ergaenzung | [[07-features/finanz-autopilot]] |
| P1 | Zahlung im Portal: Online-Zahlfunktion fuer Tierbesitzer im Owner-Portal bauen (aktuell nur Rechnungsliste) | open, aus Vollaudit 2026-07-01 | [[07-features/zahlung-im-portal]] |
| P1 | Video-Feedback: Annotations-/Kommentar-Modul ergaenzen oder Feature-Namen korrigieren | open, aus Vollaudit 2026-07-01 | [[07-features/video-feedback]] |
| P2 | TherapyCare AI / TrainingCare AI: echte KI-Funktionalitaet bauen oder Namen auf tatsaechlichen Funktionsumfang korrigieren | open, aus Vollaudit 2026-07-01 | [[07-features/therapycare-ai]] · [[07-features/trainingcare-ai]] |
| P2 | Marketing Automation: Kampagnen-/Automations-Backend bauen (aktuell nur Reporting-Dashboard) | open, aus Vollaudit 2026-07-01 | [[07-features/marketing-automation]] |
| P2 | Gamification: Punkte/Streaks/Achievements bauen oder Feature auf "Fortschritts-Badges" umbenennen | open, aus Vollaudit 2026-07-01 | [[07-features/gamification]] |
| P0 | Veterinary Anatomy Engine: Status-Korrektur "blocked" → "implemented", Marketing/Vertrieb informieren | released 2026-07-01 (Doku-Fix) | [[07-features/veterinary-anatomy-engine]] |
| P2 | Portal-Checkliste: Backend-Controller identifizieren und Funktionsumfang dokumentieren | open, aus Vollaudit 2026-07-01 | [[07-features/portal-checkliste]] |
| P2 | Weiterer Vollaudit-Sweep: restliche `plugins/*` und `app/Controllers/*` systematisch gegen 07-features abgleichen (bisher nur Stichprobe der auffaelligsten Luecken) | open | [[07-features/README]] |

## TODOs
- Nach jedem Fix Status in dieser Tabelle und in [[00-start/open-items]] synchronisieren.

## Verlinkungen
- [[07-features/README]]
- [[11-decisions/decision-log]]
- [[00-start/open-items]]
