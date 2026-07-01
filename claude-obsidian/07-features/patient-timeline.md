# Patienten-Timeline

## Beschreibung
Zentrale, chronologische Verlaufsansicht pro Patient mit gemischten Eintragstypen und Medien —
bisher nicht als eigenes Feature dokumentiert.

## Status
**implemented** (verifiziert 2026-07-01, Tiefenaudit)

## Relevante Dateien im Repo
- `app/Controllers/PatientController.php` (Zeile 479–655)

## Funktionsumfang
- Multi-Type-Timeline-Einträge (Behandlung, Notiz, u.a.)
- Anhang-Upload direkt am Timeline-Eintrag, inkl. Video-Kompression via `MediaOptimizerService`
- Automatischer Portal-Mail-Versand an den Tierbesitzer bei neuem Behandlungs-Eintrag
- JSON-Endpoints für AJAX-Nachladen ohne Seitenwechsel

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Timeline-Update/-Delete prüft, ob der Eintrag wirklich zum Patienten der Route gehört (bereits als Fix dokumentiert in [[00-start/open-items]]).

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[07-features/video-feedback]]
- [[07-features/media-compressor]]
