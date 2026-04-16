# AGENTS.md – Coding-Standards für AI-Agenten

Dieses Dokument definiert verbindliche Regeln für alle AI-Agenten (Claude Code, Windsurf, Codeium etc.)
die an diesem Repository arbeiten.

---

## Projekt-Identität

| Eigenschaft | Wert |
|---|---|
| Name | TheraPano / Tierphysio Manager 3.0 |
| Typ | Multi-Tenant SaaS für Tierphysio-Praxen |
| Stack | PHP 8.3, Twig 3, Bootstrap 5.3, Vanilla JS, Dart/Flutter |
| Namespaces | `App\` (Praxis-App), `Saas\` (SaaS-Platform), `Plugins\` (Plugins) |
| PHP-Mindest-Version | 8.3 |

---

## Die drei Haupt-Apps im Repo

### 1. Praxis-App (`/app/` + `/templates/` + `/public/`)
- Die Software die Tierphysio-Praxen täglich nutzen
- Multi-Tenant: Jede Praxis hat eigene Tabellen mit Prefix `t_{id}_`
- Authentifizierung: Session-basiert, eigene User-Tabelle pro Tenant
- Prefix-Handling: `$db->setPrefix()` und `$db->prefix('table')`

### 2. SaaS-Plattform (`/saas-platform/`)
- Admin-Panel für den Betreiber (therapano.de/admin)
- Verwaltet Tenants, Abonnements, Lizenz-Tokens, Rechnungen
- Hat KEINEN `prefix()`-Aufruf in Database.php → Prefix immer als String-Parameter
- Namespace: `Saas\`

### 3. Flutter-App (`/flutter_app/`)
- Dart/Flutter Desktop (Windows) + Mobile (Android)
- Kommuniziert mit der Praxis-App via REST-API (`/api/` Endpoints)
- State: Provider, Navigation: go_router

---

## Absolute Verbote (never do)

1. **Nie** Dateien aus `/vendor/` bearbeiten
2. **Nie** `/dist/` bearbeiten (SmartAdmin Referenz – read-only)
3. **Nie** `$db->prefix()` in `/saas-platform/` aufrufen (Methode existiert dort nicht)
4. **Nie** `declare(strict_types=1)` weglassen
5. **Nie** rohen SQL in Controllern – immer Repository oder `$db->query()`
6. **Nie** Cron-Tokens hardcoded hinterlegen – immer aus `settings`-Tabelle lesen
7. **Nie** direkt auf `$_GET`/`$_POST` zugreifen außer in Controllern
8. **Nie** `var_dump()` oder `echo` für Debugging in committed Code
9. **Nie** auf `main` pushen ohne PR
10. **Nie** Abo-/Subscription-Logik (`SubscriptionRepository`, `subRepo`) ohne expliziten Auftrag ändern

---

## PHP-Standards

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class BeispielController extends Controller
{
    public function __construct(
        // Constructor-Promotion für alle Dependencies
        private readonly Database $db,
    ) {}

    public function index(array $params = []): void
    {
        $this->requireAuth();

        // Immer Repository nutzen, nie direktes $db->query() in Controllern
        $data = $this->someRepo->findAll();

        $this->render('template/index.twig', [
            'items'      => $data,
            'page_title' => 'Titel',
        ]);
    }
}
```

### PHP Formatierung
- 4 Spaces Einrückung, keine Tabs
- K&R-Style: öffnende `{` auf gleicher Zeile wie `function`/`class`/`if`
- Typdeklarationen überall: Parameter, Rückgabe, Properties
- `readonly` für injizierte Dependencies in Services
- Array-Syntax: `[]` nicht `array()`
- String-Interpolation: `"text {$var}"` oder Konkatenation, kein `sprintf` für einfache Fälle

### Namens-Konventionen PHP
| Typ | Convention | Beispiel |
|---|---|---|
| Klassen | PascalCase | `TenantHealthService` |
| Methoden | camelCase | `findByTenant()` |
| Properties | camelCase | `$tablePrefix` |
| Konstanten | SCREAMING_SNAKE | `DEFAULT_TIMEOUT` |
| DB-Tabellen | snake_case | `patient_treatments` |
| Template-Vars | snake_case | `page_title`, `all_subs` |

---

## Twig-Standards

```twig
{# Immer extends und block #}
{% extends 'layouts/base.twig' %}
{% set active_nav = 'patients' %}

{% block content %}
{# Einrückung: 4 Spaces #}
<div class="card mb-3">
    <div class="card-header px-4 py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">{{ title }}</span>
        <a href="/path" class="btn btn-sm btn-outline-primary">Aktion</a>
    </div>
    <div class="card-body px-4 py-3">
        {# Variablen-Ausgabe mit Default #}
        {{ item.name ?? '–' }}
        
        {# CSRF in jedem POST-Formular #}
        <form method="POST" action="/route">
            {{ csrf_field()|raw }}
            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>
    </div>
</div>
{% endblock %}
```

### Design-Referenz nutzen
- Vor neuen UI-Komponenten: `/dist/` durchsuchen nach passendem HTML-Muster
- `/dist/ui-cards.html` → Card-Layouts
- `/dist/ui-modal.html` → Modale
- `/dist/forms-*.html` → Formulare
- `/dist/index.html` → Dashboard-Widgets

### Farb-System (Dark-Theme)
```css
--bg-base:     #0f172a   /* Page-Background */
--bg-card:     #1e293b   /* Cards */
--bg-elevated: #334155   /* Elevated Cards, Borders */
--text-primary: #e2e8f0
--text-secondary: #94a3b8
--text-muted:   #64748b
--color-success: #6ee7b7
--color-warning: #fde68a
--color-danger:  #fca5a5
--color-info:    #60a5fa
--color-primary: #2563eb   /* SaaS-Plattform */
--color-accent:  #a78bfa
```

---

## JavaScript-Standards

```javascript
// IIFE für Isolation
(function() {
    'use strict';

    // Const für Unveränderliches, let für Zustand
    const btn = document.getElementById('my-btn');
    
    if (!btn) return; // Früh raus wenn Element fehlt

    btn.addEventListener('click', async () => {
        try {
            const res  = await fetch('/api/endpoint', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            // Verarbeitung...
        } catch (e) {
            console.error('[ModulName]', e);
        }
    });
})();
```

### AJAX POST mit CSRF
```javascript
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const res = await fetch('/admin/action', {
    method: 'POST',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({ _csrf: csrfToken, key: value }).toString(),
});
```

---

## Dart/Flutter-Standards

```dart
// Services: stateless, injizierbar
class PatientService {
  final String baseUrl;
  final http.Client client;

  const PatientService({required this.baseUrl, required this.client});

  Future<List<Patient>> fetchAll() async {
    final res = await client.get(Uri.parse('$baseUrl/api/patients'));
    if (res.statusCode != 200) throw Exception('Fehler ${res.statusCode}');
    final List<dynamic> json = jsonDecode(res.body);
    return json.map((e) => Patient.fromJson(e as Map<String, dynamic>)).toList();
  }
}

// Models: fromJson/toJson
class Patient {
  final int id;
  final String name;

  const Patient({required this.id, required this.name});

  factory Patient.fromJson(Map<String, dynamic> json) => Patient(
    id:   json['id'] as int,
    name: json['name'] as String,
  );
}
```

---

## Datenbank-Konventionen

### Praxis-App (prefixed tables)
```php
// Richtig: prefix() verwenden
$this->db->fetchAll("SELECT * FROM `{$this->t()}` WHERE status = ?", ['active']);
// this->t() = $db->prefix($this->table) in Repository-Base

// Direkt in Controller (Ausnahme):
$table = $this->db->prefix('settings');
$this->db->fetch("SELECT value FROM `{$table}` WHERE `key` = ?", ['key_name']);
```

### SaaS-Platform (prefix als Parameter)
```php
// Richtig: prefix als String-Parameter
class MyService {
    public function __construct(
        private readonly Database $db,
        private readonly string $prefix,
    ) {}

    public function getData(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}settings` WHERE `key` = ?",
            ['my_key']
        );
    }
}
```

### Safe DB Methods (für Hintergrundtasks)
```php
// Nicht-werfende Methoden für resiliente Hintergrundprozesse
$row    = $this->db->safeFetch($sql, $params);   // null statt Exception
$rows   = $this->db->safeFetchAll($sql, $params); // []   statt Exception
$value  = $this->db->safeFetchColumn($sql, $params); // null statt Exception
$count  = $this->db->safeExecute($sql, $params);  // false statt Exception
```

---

## Sicherheits-Checkliste

Vor jedem Commit prüfen:
- [ ] Alle User-Inputs über `$this->post()` / `$this->get()` mit Type-Cast
- [ ] SQL-Parameter immer als `?`-Bindings, nie string-interpoliert
- [ ] CSRF-Token in POST-Formularen: `{{ csrf_field()|raw }}`
- [ ] Datei-Uploads: Mime-Type validiert, nicht nur Extension
- [ ] Keine Passwörter / API-Keys im Code oder Templates
- [ ] Tenant-Isolation: Prefix korrekt gesetzt vor erstem DB-Zugriff
- [ ] XSS: Twig escapt automatisch, `|raw` nur für vertrauenswürdigen HTML

---

## Neue Features – Checkliste

```
[ ] Controller-Method mit requireAuth() beginnen
[ ] Route in web.php registrieren (GET+POST wenn nötig)
[ ] Twig-Template von layouts/base.twig erben
[ ] active_nav setzen für Sidebar-Highlighting
[ ] CSRF bei POST-Formularen
[ ] Bei neuen Tabellen: CREATE TABLE IF NOT EXISTS (self-healing)
[ ] Bei SaaS-Services: prefix als Constructor-Parameter, nicht global
[ ] Fehler-Handling: try/catch in Services, safe*-Methoden in Background-Tasks
```

---

## Kommunikation Praxis-App ↔ Flutter-App

- Endpunkte: `/api/` Prefix in `/app/Routes/api.php`
- Auth: Bearer-Token (gespeichert in SharedPreferences)
- Format: JSON, UTF-8
- Fehler-Response: `{"error": "Meldung", "code": 400}`
- Erfolg-Response: `{"data": {...}}` oder `{"items": [...]}`
