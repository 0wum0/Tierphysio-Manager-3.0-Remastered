# Chat-Medien-System (Dateianhänge im Besitzerportal-Chat)

## Status
**Vollständig implementiert** (Commits `363a935`, `871f072`, `21ae297`, `e253205`)

## Übersicht

Das Chat-System zwischen Praxis-Admin und Besitzern unterstützt Dateianhänge:
- **Bilder** (JPG, PNG, GIF, WebP): werden als Inline-Vorschau mit Lightbox angezeigt
- **Videos** (MP4, WebM, MOV): werden als Inline-Preview mit nativen Browser-Controls angezeigt
- **Dokumente** (PDF, Word, Excel, TXT, CSV): Download-Karten mit Icon, Dateiname, Größe
- **Max. Dateigröße**: 10 MB
- **Tenant-isoliert**: Dateien liegen in `storage/tenants/{prefix}/portal-attachments/{threadId}/`

---

## Upload-Flow

### Admin-seitig
1. `POST /api/portal-admin/nachrichten/{threadId}/anhang`
2. Controller: `MessagingAdminController::upload()` (`plugins/owner-portal/MessagingAdminController.php`)
3. Validates MIME gegen `ALLOWED_MIME` Konstante (inkl. Bilder seit Commit `363a935`)
4. Speichert als `bin2hex(random_bytes(8)).{ext}` in `tenant_storage_path('portal-attachments/{threadId}')`
5. DB: `attachment_path = 'portal-attachments/{threadId}/{safeName}'`, `attachment_name = original_filename`, `attachment_size = bytes`

### Besitzer-seitig (Portal)
1. `POST /api/portal/nachrichten/{threadId}/anhang`
2. Controller: `MessagingOwnerController::upload()` (`plugins/owner-portal/MessagingOwnerController.php`)
3. Gleiche Logik wie Admin-Upload
4. Besitzer-Auth via `requireOwnerAuth()` + Session-basierter Tenant-Kontext

---

## Download / Serving

### Admin-Endpoint
- `GET /api/portal-admin/nachrichten/{threadId}/anhang/{msgId}` → `MessagingAdminController::download()`
- Auth: `['auth']`-Middleware (Admin-Session erforderlich)
- Bilder → `Content-Disposition: inline` (Browser rendert direkt)
- Dokumente → `Content-Disposition: attachment` (Download)

### Besitzer-Endpoint
- `GET /api/portal/nachrichten/{threadId}/anhang/{msgId}` → `MessagingOwnerController::download()`
- Auth: `requireOwnerAuth()` + prüft `msg.owner_id === ownerId` (Tenant-Check)
- Gleiche Disposition-Logik wie Admin

---

## Rendering (Twig-Templates)

### Beim Laden der Seite (Server-Side)
Datei: `plugins/owner-portal/templates/admin_message_thread.twig` (Zeile 92-111)
Datei: `plugins/owner-portal/templates/owner_message_thread.twig` (Zeile 83-102)

```twig
{% if msg.attachment_name %}
{% set _ext = msg.attachment_name|split('.')|last|lower %}
{% set _isImg = _ext in ['jpg','jpeg','png','gif','webp'] %}
{% if _isImg %}
<a href="/api/portal-admin/nachrichten/{{ thread.id }}/anhang/{{ msg.id }}"
   class="wa-attach-image" data-lightbox="1" data-filename="{{ msg.attachment_name }}">
    <img src="..." alt="{{ msg.attachment_name }}" loading="lazy">
</a>
{% else %}
<a href="..." class="wa-attach" download>...</a>
{% endif %}
{% endif %}
```

### Nach AJAX-Send / Polling (Client-Side, JavaScript)
Funktion `buildBubble()` in beiden Templates:
- Prüft `d.attachment_name` und Extension
- Baut gleiche HTML-Struktur wie Twig
- URL: `/api/portal-admin/nachrichten/{threadId}/anhang/{d.id}`

---

## Lightbox

Beide Templates haben eine eigenständige Lightbox:
- Klick auf `.wa-attach-image[data-lightbox="1"]` → `openLightbox(src, filename)`
- Fullscreen-Overlay mit Zoom, Download-Button, ESC-Close
- Kein neuer Tab mehr (seit Commit `21ae297`)

Der Admin-Drawer (Layout-Twig) hat eine eigene Lightbox-Implementierung (`drawer-lightbox`).

---

## Datenbankstruktur

Tabelle: `{prefix}portal_messages`

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| `attachment_path` | VARCHAR(500) | relativer Pfad innerhalb tenant_storage |
| `attachment_name` | VARCHAR(255) | Original-Dateiname des Uploaders |
| `attachment_size` | INT UNSIGNED | Größe in Bytes |

Migration: `plugins/owner-portal/migrations/010_message_attachments.sql`
Läuft automatisch via `ServiceProvider::runMigrations()` bei jedem Request.

---

## Erlaubte MIME-Typen

```php
private const ALLOWED_MIME = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain', 'text/csv', 'application/csv',
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',  // seit Commit 363a935
];
```

---

## Bekannte Einschränkungen

1. **Polling überspringt eigene Nachrichten**: In `admin_message_thread.twig` (Zeile 420) werden nur `sender_type === 'owner'`-Nachrichten gerendert. Admin-eigene Nachrichten via Polling werden ignoriert — aber da sie via `appendMsg()` nach dem Send direkt gerendert werden, ist das kein sichtbares Problem.

2. **Video-Support**: MP4/WebM/MOV werden als Inline-Preview unterstützt.

3. **Bildoptimierung**: JPEG/PNG/WebP werden nach Upload via `MediaOptimizerService` serverseitig verkleinert, wenn Schwellwerte überschritten sind. Es gibt keine separate Thumbnail-Datei, weil die Chat-Vorschau direkt die optimierte Datei nutzt.

---

## Tenant-Sicherheit

- Upload: `tenant_storage_path()` nutzt DB-Prefix aus Session → Dateien landen im korrekten Tenant-Verzeichnis
- Download: `getMessageById()` joinet `portal_message_threads` und gibt `owner_id` zurück → Besitzer-Controller prüft `msg.owner_id === $ownerId`
- Kein Pfad-Traversal möglich: randomisierter Dateiname mit `bin2hex(random_bytes(8))`

---

## Verlinkungen
- [[05-portal/owner-portal]]
- [[10-bugs/known-bugs]]
