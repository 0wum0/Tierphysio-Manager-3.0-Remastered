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
Auslöser: Der 3D-Schmerzanalyse-Viewer wurde in der Doku fälschlich als "blockiert" gefuehrt, obwohl
vollständig implementiert — daraufhin wurde ein zweiter Sweep über den gesamten Code gefahren, der
11 weitere bisher komplett undokumentierte Features gefunden hat (siehe unten, neu hinzugefuegt).

| Feature | Status | Offene Punkte |
|---|---|---|
| [[07-features/homework-plugin]] | implemented | Keine bekannten funktionalen TODOs im Brain |
| [[07-features/whatsapp-style-chat]] | implemented | Video-Preview, Lightbox, Bildoptimierung/Resize |
| [[07-features/chat-media-system]] | implemented | Bilder, Dokumente und Videos im Chat |
| [[07-features/google-calendar-sync]] | active | UI-Anzeige Matching, Recurring-Expansion und Europe/Berlin-Normalisierung umgesetzt |
| [[07-features/3d-befundmodell-schmerzskala]] | implemented basis | Read-only-NRS in Show-Views, PDF-Zusammenfassung fuer Marker/Zeichnungen (2D-SVG-System) |
| [[07-features/veterinary-anatomy-engine]] | **implemented (Phase 2 / 3D bereits produktiv!)** | Doku bis 2026-07-01 fälschlich "blocked" — echter 3D-Viewer mit Hund/Katze/Pferd-GLB-Modellen existiert, siehe Status-Korrektur in der Datei |
| [[07-features/hundeschulen-support]] | implemented | Dashboard-Paketverkauf per AJAX/JSON und befuelltem Paketkatalog |
| [[07-features/kurs-system-hundeschulen]] | implemented | Öffentliche Endkunden-Online-Buchung ohne Login pruefen |
| [[07-features/terminbuchung]] | implemented | Soll-/Ist- und E2E-Doku offen |
| [[07-features/smart-erinnerungen]] | implemented | Soll-/Ist- und E2E-Doku offen |
| [[07-features/fortschrittssystem]] | implemented via TherapyCarePro | E2E-Doku offen |
| [[07-features/video-feedback]] | **partial** | Upload/Vorher-Nachher ja, kein Annotations-/Kommentar-Modul |
| [[07-features/finanz-autopilot]] | **implemented** (korrigiert Teil 4) | Vollwertiges mehrstufiges Mahnwesen (1./2./3. Mahnung, Gebühren, PDF, Überfälligkeits-Alerts) pro Tenant nutzbar — Eskalation bewusst nutzergesteuert statt cron-automatisch |
| [[07-features/marketing-automation]] | **not_found** | Nur Dashboard-Reporting, kein Kampagnen-/Automations-Backend |
| [[07-features/zahlung-im-portal]] | **partial** | Nur Rechnungsliste/PDF, keine Online-Zahlfunktion fuer Tierbesitzer |
| [[07-features/praxis-vs-hundeschule]] | implemented | Terminology-Switching in `terminology.dart` bestaetigt |
| [[07-features/therapycare-ai]] | **not_found (AI)** | Basissystem ohne KI implementiert; "AI" ist Branding ohne Backend |
| [[07-features/trainingcare-ai]] | **implemented (Trainingsplan-System), AI: not_found** | Trainingsplaene real umgesetzt, KI-Anteil existiert nicht |
| [[07-features/gamification]] | **partial** | Nur Score-Badges/Fortschrittsbalken, keine Punkte/Streaks/Achievements |

### Neu gefunden im Vollaudit 2026-07-01 (waren komplett undokumentiert)

| Feature | Status | Kurzbeschreibung |
|---|---|---|
| [[07-features/gobd-audit-log]] | implemented | Unveränderliches Rechnungs-Änderungsprotokoll (Steuerprüfungs-Nachweis) |
| [[07-features/tax-export-pro]] | implemented | DATEV/CSV/ZIP-Export inkl. Belegen, Kassenbuch, PDF-Steuerbericht, SKR03-Kontenrahmen — Rechnungen UND Ausgaben kombiniert |
| [[07-features/invoice-branding]] | implemented | Rechnungsdesign: Logo-Upload, Farben, Schriftart, individuelle Bilder pro Dokumenttyp |
| [[07-features/expense-management]] | implemented | Ausgabenerfassung mit OCR-Belegerkennung |
| [[07-features/mailbox-plugin]] | implemented | Vollwertiger IMAP/SMTP-Mail-Client in der App |
| [[07-features/bulk-mail]] | implemented | Serienmails + automatisches Feiertags-Mailing |
| [[07-features/theme-manager]] | implemented | Tenant-Custom-Themes per ZIP-Upload |
| [[07-features/patient-invite]] | implemented | Einladungslink → sofortige Patienten-Anlage ohne Freigabe |
| [[07-features/patient-intake]] | implemented | Öffentlicher Anmelde-Wizard MIT Admin-Freigabe-Workflow |
| [[07-features/consent-management]] | implemented | Versionierte Einwilligungsformulare (DSGVO) |
| [[07-features/online-booking]] | implemented | Öffentliche Terminanfrage mit Honeypot + Rate-Limiting |
| [[07-features/ui-settings-notifications]] | implemented | Persönliches UI-Layout + Notification-Center |
| [[07-features/media-compressor]] | implemented | Client-seitige Video-/Bildkompression via ffmpeg.wasm |
| [[07-features/portal-checkliste]] | implemented, Detail offen | Portal-Freigabe-Checkliste im Patient-Modal |
| [[07-features/cron-pixel-system]] | implemented | Pixel-getriggerte Background-Jobs ohne Server-Cron nötig |
| [[07-features/hundeschule-erweiterte-funktionen]] | implemented | Anwesenheit, Trainerverwaltung, Business-Reports, Hundeschul-Rechnungen, eigenständiger Kalender |
| [[07-features/patient-timeline]] | implemented | Zentrale Verlaufsansicht pro Patient mit Medien + Portal-Mail-Trigger |
| [[07-features/flutter-offline-mode]] | implemented | SQLite-Offline-Cache (14 Tage) mit Auto-Sync |
| [[07-features/data-migration-import]] | implemented | SQL-Dump-Import für Wechsel von Konkurrenzsoftware (Smart-/Raw-Modus) |
| [[06-saas/saas-admin-erweiterte-funktionen]] | implemented | Revenue-Analytics, 3-Ebenen-Feature-Gating, GoBD-SaaS-Rechnungen, Stripe+PayPal, Lizenz-API |

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
