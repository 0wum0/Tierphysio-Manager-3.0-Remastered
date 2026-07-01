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
- Status: **implemented (echte KI seit 2026-07-01)** — siehe [[07-features/ai-integration]].

## Update (2026-07-01, Teil 5): Echte KI-Funktionalität ergänzt
Der ursprüngliche Audit-Befund (unten, historisch) stellte fest, dass "AI" im Namen reines
Branding ohne technisches Gegenstück war. Das ist jetzt behoben: `TherapyCareController::aiProgressSummary()`
nutzt den neuen [[07-features/ai-integration|zentralen AiService]] (Grok/Gemini), um aus den
Fortschritts-Verlaufsdaten eine echte KI-generierte Zusammenfassung zu erstellen — sichtbar als
Karte "KI-Zusammenfassung" in der Fortschritts-Ansicht, gated durch Feature `ki_assistance`.

## Historischer Audit-Befund (2026-07-01, vor der KI-Ergänzung)
`TherapyCareReportService.php` und die Controller (`TherapyCareController.php`, `TherapyCarePortalController.php`)
enthielten **keinen einzigen LLM-/KI-Aufruf** — die PDF-Reports wurden rein über TCPDF aus
strukturierten Formulardaten generiert. Das nicht-KI-Basissystem (Fortschritts-Tracking, PDF-Reports)
war bereits vollständig implementiert, nur ohne echte KI-Komponente.

## Risiken
- Ohne konfigurierten Grok-/Gemini-Provider im SaaS-Admin bleibt der Button funktionslos
  (klare Fehlermeldung `ai_not_configured`, kein Crash).

## TODOs
- Siehe offene Punkte in [[07-features/ai-integration]] (Rate-Limiting, Caching, Audit-Log).

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
