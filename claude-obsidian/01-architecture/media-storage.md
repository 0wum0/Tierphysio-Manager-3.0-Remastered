# Media Storage — Architektur & Pfad-Regeln

## Beschreibung
Zentrale Dokumentation aller Medien-Pfade, Upload-Endpunkte, Serve-Controller und Fallback-Logik.

## Tenant-Pfad-Schema

Alle Mediendateien werden unter dem Tenant-Speicherpfad abgelegt:
```
STORAGE_PATH/tenants/{tenant_slug}/{subPath}
```
Zugriff via `tenant_storage_path(string $subPath)` — Hilfsfunktion in `app/helpers.php`.

## Upload-Pfade nach Medientyp

| Medientyp | Upload-Pfad | Controller-Methode |
|---|---|---|
| Patientenfoto (Profilbild) | `patients/{id}/` | `PatientController::uploadPhoto()` |
| Patientenfoto (Intake/Einladung) | `intake/` | `IntakeController` / `InviteController` |
| Patientendokument | `patients/{id}/docs/` | `PatientController::uploadDocument()` |
| Timeline-Anhang | `patients/{id}/timeline/` | `PatientController::addTimelineEntry()` |
| Chat-Anhang (Portal) | `portal-attachments/{threadId}/` | `MessagingAdminController` / `MessagingOwnerController` |
| TherapyCare-Medien | `therapy-care/{id}/` | `TherapyCareController` |
| Befund-Anatomie | `befunde/{id}/` | `BefundbogenController` |
| Ausgaben-Belege | `expenses/{id}/` | `ExpenseController` |

## Serve-Routes & Controller

| Route | Controller::Methode | Kandidaten-Reihenfolge |
|---|---|---|
| `GET /patienten/{id}/foto/{file}` | `PatientController::servePhoto()` | `patients/{id}/`, `patients/`, `intake/` (invite_/intake_ zuerst) |
| `GET /patienten/{id}/dokumente/{file}` | `PatientController::downloadDocument()` | `patients/{id}/docs/` (primär), `patients/{id}/timeline/` (legacy), `patients/{id}/` |
| `GET /api/mobile/patients/{id}/foto/{file}` | `MobileApiController::mediaServePhoto()` | — |
| `GET /api/mobile/patients/{id}/media/{file}` | `MobileApiController::mediaServeFile()` | — |

## Fallback-Verhalten bei fehlenden Dateien

### Server-seitig (PHP Controller)
- **`servePhoto()`**: Datei fehlt → `servePawPlaceholder()` → HTTP 200 + SVG-Tatzen-Placeholder
- **`downloadDocument()`**: Bilddatei fehlt → `servePawPlaceholder()` → HTTP 200 + SVG
- **`downloadDocument()`**: Nicht-Bild fehlt → HTTP 404 (korrekt für Downloads)
- Alle Fehler werden per `error_log()` geloggt

### Client-seitig (JS)
Globaler `MutationObserver`-basierter Image-Fallback in:
- `public/assets/js/app.js` (App-Layout)
- `plugins/owner-portal/templates/portal_layout.twig` (Portal-Layout)

```javascript
// Paw-Placeholder für jedes <img> das bricht (inkl. dynamisch geladenem Content)
img.onerror → img.src = '/assets/img/placeholder-paw.svg'
```

## Paw-Placeholder SVG
`public/assets/img/placeholder-paw.svg`
- TheraPano Dark-Theme Design: `#1e293b` Hintergrund, `#334155` Pads, `#a78bfa` Akzent
- Responsive 200×200 ViewBox, `role="img"`
- Inline SVG-Fallback in `servePawPlaceholder()` wenn Datei fehlt

## Fehler-Logging
Fehlende Mediendateien werden geloggt mit:
- `[PatientController] Missing photo: patients/{id}/{file}`
- `[PatientController] Missing document: patients/{id}/docs/{file}`

Logs in `storage/logs/error.log` (oder PHP-Error-Log).

## Wichtige Regeln

1. **Niemals** `timeline/` als primären Pfad für `uploadDocument()` nutzen — Uploads gehen in `docs/`
2. **Legacy-Fallback** in `downloadDocument()` deckt alte `timeline/`-Uploads ab (nicht entfernen!)
3. **Tenant-Isolation**: `tenant_storage_path()` MUSS genutzt werden, niemals absolute Pfade
4. **Kein path-Traversal**: Immer `basename()` auf user-supplied Dateinamen
5. **CSP**: `img-src 'self' data: blob:` erlaubt SVG-Inline + Blob URLs für Medienvorschauen

## Verlinkungen
- [[01-architecture/tenant-system]]
- [[10-bugs/known-bugs]] — Foto/Dokument 404 Bugs dokumentiert
