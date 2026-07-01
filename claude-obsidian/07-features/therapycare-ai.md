# TherapyCare AI (Praxen)

## Beschreibung
Feature-Dokumentation für TherapyCare AI (Praxen).

## Zweck
Gemeinsames Verständnis für Implementierung, Grenzen und nächste Schritte.

## Relevante Dateien im Repo
- `plugins/therapy-care-pro/TherapyCareController.php`
- `plugins/therapy-care-pro/TherapyCarePortalController.php`
- `app/Routes/web.php (tcp API routes)`
- `flutter_app/lib/screens/tcp/tcp_screen.dart`

## Datenfluss
Client/Web/Portal → Route/Controller/Plugin → Repository/Service → UI/Response.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Status: **not_found (AI-Anteil)** — Basissystem ohne KI ist implementiert, siehe [[07-features/fortschrittssystem]].

## Audit-Befund (2026-07-01, gegen echten Code verifiziert)
`TherapyCareReportService.php` und die Controller (`TherapyCareController.php`, `TherapyCarePortalController.php`)
enthalten **keinen einzigen LLM-/KI-Aufruf** (kein OpenAI/Anthropic/GPT-Pattern im gesamten
`plugins/therapy-care-pro/`-Verzeichnis). Der Service generiert PDF-Reports rein über TCPDF aus
strukturierten Formulardaten. "AI" im Namen ist **Branding ohne technische KI-Funktionalität**.
Das nicht-KI-Basissystem (Fortschritts-Tracking, PDF-Reports) ist vollständig implementiert.

## Risiken
- Marketing/Vertrieb darf "TherapyCare AI" nicht als KI-Feature bewerben, solange kein KI-Backend existiert.

## TODOs
- Entweder echte KI-Funktionalität bauen (z.B. Trend-Analyse/Textgenerierung aus Progress-Daten)
  oder Feature-Namen intern/extern auf den tatsächlichen Funktionsumfang korrigieren.

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
