# Öffentliche Legal-Seiten (therapano.de)

## Zweck
Öffentlich erreichbare Rechtsseiten ohne Login-Pflicht.
Erforderlich für:
- Google OAuth / Trust & Safety Prüfung
- DSGVO-Pflichtseiten
- Registrierungsformular (Zustimmung)

## URLs

| URL | Methode | Controller |
|---|---|---|
| `https://therapano.de/impressum` | GET | `LegalController::impressum()` |
| `https://therapano.de/datenschutz` | GET | `LegalController::datenschutz()` |
| `https://therapano.de/legal/datenschutz` | GET | `LegalController::view()` |
| `https://therapano.de/legal/agb` | GET | `LegalController::view()` |
| `https://therapano.de/legal/av-vertrag` | GET | `LegalController::view()` |
| `https://therapano.de/legal/{slug}` | GET | `LegalController::view()` |

## Routing-Architektur

```
therapano.de  →  .htaccess  →  saas-platform/public/index.php
                                → Application::registerRoutes()
                                → platform.php geladen (ZUERST)
                                → web.php geladen (danach)
```

### platform.php (Primärquelle für legal routes)
```php
$router->get('/legal/{slug}', [LegalController::class, 'view']);
$router->get('/impressum',    [LegalController::class, 'impressum']);
$router->get('/datenschutz',  [LegalController::class, 'datenschutz']);
```

Router matcht erste passende Route → platform.php-Einträge greifen.

## Controller: LegalController

`saas-platform/app/Controllers/LegalController.php`

- **Kein `requireAuth()`** → öffentlich ohne Login
- Injiziert: `LegalRepository` + `SettingsRepository` (auto-wired via Container)
- `view(array $params)`: liest `legal_documents` per Slug, zeigt Placeholder wenn nicht gefunden
- `impressum()`: liest **Company Settings** aus `saas_settings` → rendert `legal/impressum.twig`
- `datenschutz()`: liest `legal_documents` Slug `datenschutz` → rendert `legal/view.twig`
- **try/catch** um alle DB-Zugriffe → kein 500 wenn Tabelle nicht existiert

### Fallback-Verhalten
Wenn Slug nicht in `legal_documents` vorhanden:
- Kein HTTP 404
- Placeholder-Doc mit Titel aus `$titleMap`
- Content: „Dieses Dokument wird in Kürze verfügbar sein."

## Templates

### legal/impressum.twig (NEU)
`saas-platform/templates/legal/impressum.twig`

- Extends `layouts/public.twig`
- Datenquelle: `company.*` aus Settings (kein `legal_documents`)
- Sektionen: Anbieter, Kontakt, Steuerliche Angaben, Haftungsausschluss
- Placeholder-Notice wenn keine Firmendaten gepflegt
- Vollständig responsive, dark-theme, SEO-freundlich

### legal/view.twig (generisch)
`saas-platform/templates/legal/view.twig`

- Extends `layouts/public.twig`
- Zeigt `doc.title`, `doc.version`, `doc.updated_at|date`, `doc.content|nl2br`
- Footer-Links zu Registrierung und zurück

## Layout: public.twig

Footer enthält alle Legal-Links:
```
Impressum | Datenschutz | AGB | AVV
```

`/impressum` → `LegalController::impressum()`
`/legal/datenschutz` → `LegalController::view()`

## DB: legal_documents

Tabelle in `saas-platform/migrations/001_initial_schema.sql`

| slug | title | Seed-Status |
|---|---|---|
| `datenschutz` | Datenschutzerklärung | ✅ seit 001 |
| `agb` | AGB | ✅ seit 001 |
| `av-vertrag` | AVV | ✅ seit 001 |
| `impressum` | Impressum | ✅ seit Migration 053 |

Admin-Interface: `https://therapano.de/admin/legal`

## Footer-Links Übersicht

### landing.twig (Landingpage)
```
/impressum  →  /datenschutz
```

### public.twig (Registration, Legal-Seiten)
```
/impressum  →  /legal/datenschutz  →  /legal/agb  →  /legal/av-vertrag
```

### auth/tenant-login.twig
```
/impressum  →  /datenschutz
```

## Wichtige Regeln
- Neue Legal-Slugs immer als `INSERT IGNORE` in Migration hinzufügen
- Keine `requireAuth()` in `LegalController::view/impressum/datenschutz`
- Placeholder statt 404 — nie Google einen 404 zurückliefern
