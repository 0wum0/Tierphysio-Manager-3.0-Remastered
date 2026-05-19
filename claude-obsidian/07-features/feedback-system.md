# Feedback & Support System

> Status: **Implementiert** (Mai 2026)

Das Feedback-System ist gleichzeitig das Support-System für Praxisinhaber, Therapeuten und Trainer.

## Überblick

- **Floating Action Button (FAB)** rechts unten auf allen Praxis-Seiten (in `base.twig`)
- Öffnet ein Bootstrap-5-Modal mit Formular
- Speichert direkt in der zentralen SaaS-Tabelle `feedback`
- SaaS-Admin kann Status setzen, interne Notiz hinterlassen, Anhänge öffnen

## UI-Integration (Praxis-App)

### FAB-Positionierungsregel
```css
#feedback-fab {
    position: fixed;
    right: 1.25rem;
    bottom: 1.25rem;
    z-index: 1040;
}
/* Seiten mit festem unterem Bereich: body[data-chat-bar="1"] → bottom: 5rem */
```

- **Standard**: `bottom: 1.25rem`
- **Mit Chat-/Send-Bar**: `<body data-chat-bar="1">` → FAB rutscht auf `bottom: 5rem`
- Alle Seiten über `layouts/base.twig` — nur für eingeloggte Nutzer sichtbar (`{% if current_user %}`)

## Formularfelder

| Feld | Pflicht | Beschreibung |
|---|---|---|
| type | Ja | bug / feature / support / question / praise / other |
| subject | Nein | max. 255 Zeichen |
| message | Ja | Freitext |
| priority | Nein | low / normal / high / critical |
| attachment | Nein | Bild (JPEG, PNG, WebP, GIF) oder PDF, max. 5 MB |

### Automatisch erfasst
- `current_url` — aktuelle Seite (window.location.pathname)
- `user_id`, `user_name` — aus Session
- `tenant_id`, `tenant_name` — via SaaS-DB-Lookup (aus tenant_table_prefix)
- `user_agent` — Browser HTTP-Header
- `platform` — immer `'web'`
- Timestamp — MySQL `DEFAULT CURRENT_TIMESTAMP`

## Architektur

### Praxis-App → SaaS-DB

```
POST /feedback/submit
  → app/Controllers/FeedbackController.php::submit()
  → PDO-Verbindung zur SaaS-DB (saas_db.* Config)
  → INSERT INTO feedback (...)
```

- `FeedbackController` liegt unter `app/Controllers/FeedbackController.php`
- Route: `POST /feedback/submit` mit `['auth']`-Middleware
- CSRF via `_csrf_token` POST-Parameter (Session::validateCsrfToken)
- Anhänge landen unter `storage/feedback/feedback_<random>.{ext}`

### SaaS-Admin

- Übersicht: `GET /admin/feedback`
- Detail: `GET /admin/feedback/{id}`
- Status-Update: `POST /admin/feedback/{id}/status`
- Löschen: `POST /admin/feedback/{id}/delete`
- Alle gelesen: `POST /admin/feedback/mark-all-read`

## DB-Schema (SaaS `feedback`-Tabelle)

Migration `011_feedback_and_payment.sql` — Grundstruktur  
Migration `069_feedback_support_fields.sql` — Erweiterung

Neue Felder (v069):
- `subject` VARCHAR(255)
- `type` ENUM(bug, feature, support, question, praise, other)
- `status` ENUM(open, in_progress, resolved, closed) DEFAULT 'open'
- `priority` ENUM(low, normal, high, critical) DEFAULT 'normal'
- `current_url` VARCHAR(500)
- `user_id` INT UNSIGNED
- `user_name` VARCHAR(200)
- `user_agent` TEXT
- `attachment_path` VARCHAR(500)
- `admin_note` TEXT
- `resolved_at` DATETIME

## Sicherheit

- Nur eingeloggte Praxis-Nutzer (`['auth']` Middleware)
- CSRF-Token-Prüfung im Controller
- XSS: `htmlspecialchars + strip_tags` auf allen Inputs
- Anhänge: MIME-Type-Validierung via `finfo`, nur Bilder + PDF
- Kein Tenant-Cross-Leak: Feedback wird pro Tenant gespeichert

## Bekannte TODOs

- [ ] E-Mail-Benachrichtigung an Betreiber bei neuem Feedback
- [ ] Filterschaltflächen im Admin für `status=open`, `type=support`, `priority=high`
- [ ] Anhang-Preview im SaaS-Admin (derzeit nur externer Link)
