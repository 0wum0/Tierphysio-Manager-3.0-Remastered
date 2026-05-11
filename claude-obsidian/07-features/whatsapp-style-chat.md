# WhatsApp-Style Chat

## Status
**Vollständig implementiert** — inkl. Dateianhänge, Lightbox, Polling, Formatierungsmenü

## Beschreibung
Chat-System zwischen Praxis-Admin und Tierhaltern im Besitzerportal.
UI nach WhatsApp-Vorbild: Bubbles, Timestamps, Häkchen (gesendet/gelesen), Dateianhänge.

## Relevante Dateien im Repo
- `plugins/owner-portal/MessagingAdminController.php` — Admin-CRUD, Upload, Download, Poll
- `plugins/owner-portal/MessagingOwnerController.php` — Owner-CRUD, Upload, Download, Poll
- `plugins/owner-portal/MessagingRepository.php` — DB-Operationen (Threads, Messages, Attachments)
- `plugins/owner-portal/templates/admin_message_thread.twig` — Admin-Chat-View
- `plugins/owner-portal/templates/owner_message_thread.twig` — Owner-Chat-View
- `storage/themes/smart-tierphysio/layout.twig` — Admin-Drawer-Chat (Inline im Layout)
- `plugins/owner-portal/ServiceProvider.php` — Routen-Registrierung
- `plugins/owner-portal/migrations/010_message_attachments.sql` — attachment_path/name/size Columns

## Routen
| Route | Controller | Auth |
|-------|-----------|------|
| `GET /portal-admin/nachrichten/{id}` | `MessagingAdminController::thread` | `['auth']` |
| `POST /api/portal-admin/nachrichten/{id}/antworten` | `MessagingAdminController::reply` | `['auth']` |
| `POST /api/portal-admin/nachrichten/{id}/anhang` | `MessagingAdminController::upload` | `['auth']` |
| `GET /api/portal-admin/nachrichten/{id}/anhang/{msgId}` | `MessagingAdminController::download` | `['auth']` |
| `GET /api/portal-admin/nachrichten/{id}/poll` | `MessagingAdminController::poll` | `['auth']` |
| `GET /portal/nachrichten/{id}` | `MessagingOwnerController::thread` | Owner-Session |
| `POST /api/portal/nachrichten/{id}/anhang` | `MessagingOwnerController::upload` | Owner-Session |
| `GET /api/portal/nachrichten/{id}/anhang/{msgId}` | `MessagingOwnerController::download` | Owner-Session |
| `GET /api/portal/nachrichten/{id}/poll` | `MessagingOwnerController::poll` | Owner-Session |

## Datenfluss
```
Browser Upload → POST /api/.../anhang → MIME-Validierung → tenant_storage_path('portal-attachments/{threadId}/')
                                                          → DB: attachment_path, attachment_name, attachment_size
Browser Image → GET /api/.../anhang/{msgId} → getMessageById() → tenant_storage_path(attachment_path) → readfile()
```

## Features
- **WhatsApp-Bubbles**: Admin rechts (blau), Owner links (weiß)
- **Read-Ticks**: Einfach (gesendet) / Doppelt blau (gelesen) via `read_at`-Timestamp
- **Polling**: alle 4s, `visibilitychange`-aware
- **Dateianhänge**: Bilder als Inline-Vorschau, Dokumente als Download-Karte
- **Lightbox**: Bild-Vollansicht ohne neuen Tab (eigener `wa-lightbox`-Overlay)
- **Rechtsklick-Formatierungsmenü**: Fett, Kursiv, Durchgestrichen, Code
- **Admin-Drawer**: Inline-Chat im Layout ohne Seitenwechsel (eigene Lightbox: `drawer-lightbox`)

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten (Storage-Pfad via `tenant_storage_path()`).
- Image-MIME-Typen müssen in `ALLOWED_MIME` beider Controller stehen.

## Bekannte Einschränkungen
- Kein Video-Support als Inline-Preview
- Kein server-seitiges Image-Resize
- Polling ignoriert eigene Nachrichten (harmlos, da sofort via `appendMsg()` gerendert)

## TODOs
- [ ] Video-Attachments (mp4) mit `<video>`-Tag
- [ ] Thumbnail-Generierung für hochgeladene Bilder

## Verlinkungen
- [[07-features/chat-media-system]]
- [[05-portal/owner-portal]]
- [[02-api/mobile-api]]
- [[11-decisions/decision-log]]
