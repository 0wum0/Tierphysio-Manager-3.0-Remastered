# Hundeschulen-/Trainer-Modale

> Vollständige Feature-Übersicht: [[hundeschulen-support]] (Memory)
> Dieser Artikel dokumentiert den Modal-Workflow für die Hundeschulen-Ansicht.

## Status: Implementiert (Mai 2026)

Alle wichtigen Erstellen-/Bearbeiten-Aktionen in der Hundeschulen-Ansicht laufen jetzt über Bootstrap-Modale.
Das Dashboard-Modal "Paket verkaufen" sendet per AJAX und erhaelt JSON von
`PackageController::sell()`. Der aktive Paketkatalog wird direkt im Modal befuellt.

---

## Umgesetzte Modale

### Dashboard (`/hundeschule`)
| Button | Modal-ID | Ziel-Route | Controller |
|---|---|---|---|
| + Neuer Kurs | `#ds-modal-new-course` | POST `/kurse` | `CourseController::store()` |
| + Interessent | `#ds-modal-new-lead` | POST `/interessenten` | `LeadController::store()` |
| Paket verkaufen | `#ds-modal-sell-package` | POST `/pakete/verkaufen` | `PackageController::sell()` |

### Kurse-Liste (`/kurse`)
| Button | Modal-ID | Ziel-Route |
|---|---|---|
| + Neuer Kurs | `#ci-modal-new-course` | POST `/kurse` |
| Neuen Kurs anlegen (Empty-State) | `#ci-modal-new-course` | POST `/kurse` |

### Kurs-Detail (`/kurse/{id}`)
| Button | Modal-ID | Ziel-Route |
|---|---|---|
| Bearbeiten | `#cs-modal-edit-course` | POST `/kurse/{id}` |

### Interessenten-Liste (`/interessenten`)
| Button | Modal-ID | Ziel-Route |
|---|---|---|
| + Neuer Interessent | `#li-modal-new-lead` | POST `/interessenten` |

### Pakete (`/pakete`)
| Button | Modal-ID | Ziel-Route |
|---|---|---|
| + Neues Paket | `#pi-modal-new-package` | POST `/pakete` |
| Paket verkaufen | `#modal-sell` | POST `/pakete/verkaufen` |

---

## Technische Details

### AJAX-Flow
1. Modal-Formular submittet via `fetch()` mit Header `X-Requested-With: XMLHttpRequest`
2. Controller erkennt XHR via `$this->isAjax()` und antwortet mit JSON
3. JSON-Response: `{success: true, id: N, redirect: "/pfad"}` oder `{success: false, error: "Meldung"}`
4. Bei Erfolg: Modal schließen, zu `data.redirect` navigieren oder Seite reloaden
5. Bei Fehler: Fehlermeldung im Modal anzeigen, Formular-Daten bleiben erhalten

### Controller-Änderungen
Alle folgenden Controller-Methoden haben einen `isAjax()`-Branch erhalten:
- `CourseController::store()` — JSON `{success, id, redirect}`
- `CourseController::update()` — JSON `{success, id, redirect}`
- `LeadController::store()` — JSON `{success, id, redirect}`
- `LeadController::update()` — JSON `{success, id, redirect}`
- `PackageController::store()` — JSON `{success, id, redirect}`
- `PackageController::update()` — JSON `{success, id, redirect}`

### Fallback
- Alle Buttons haben weiterhin `href`-Fallback-Links auf die normalen Seiten (`/kurse/neu`, `/interessenten/neu`, etc.)
- Vollständige Formulare (`form.twig`) bleiben unverändert erhalten
- Modal-Footer zeigt immer „Alle Felder anzeigen →" Link auf die vollständige Form-Seite

### CSRF
- Modal-Formulare beinhalten `<input type="hidden" name="_csrf_token" value="{{ csrf_token }}">`
- CSRF-Token wird via POST-Body mitgeschickt (validiert in `validateCsrf()`)

### Ladezustand
- Submit-Button: Spinner-Icon + Disabled während Fetch läuft
- Klassen `.ds-btn-text` / `.ds-btn-spin` (bzw. `ci-`/`cs-`/`li-`/`pi-` je Template)

---

## Geänderte Dateien

| Datei | Art der Änderung |
|---|---|
| `app/Controllers/CourseController.php` | `isAjax()` in `store()` + `update()` |
| `app/Controllers/LeadController.php` | `isAjax()` in `store()` + `update()` |
| `app/Controllers/PackageController.php` | `isAjax()` in `store()` + `update()` |
| `templates/dogschool/dashboard/index.twig` | 3 Modale + JS (Kurs, Lead, Paket) |
| `templates/dogschool/courses/index.twig` | Neuer-Kurs-Modal + JS |
| `templates/dogschool/courses/show.twig` | Kurs-Bearbeiten-Modal + JS |
| `templates/dogschool/leads/index.twig` | Neuer-Interessent-Modal + JS |
| `templates/dogschool/packages/index.twig` | Neues-Paket-Modal + JS |

---

## Offene TODOs
- Pakete-Bearbeiten: Link `/pakete/{id}/bearbeiten` führt noch auf eigene Seite (OK für komplexe Paket-Daten)
- Interessenten-Detail (`/interessenten/{id}`): Bearbeiten läuft noch als eigene Seite (OK)
- Keine bekannten funktionalen offenen Punkte im Modal-Flow.
