# Sprint A – Status

**Zuletzt aktualisiert:** 2026-04-28  
**Branch:** `claude/therapano-sprint-a-qaLiQ`  
**PR:** https://github.com/0wum0/Tierphysio-Manager-3.0-Remastered/pull/47

## Fertig

- ✅ **L1.1** Fortschritts-System (war bereits erledigt vor diesem Sprint)
- ✅ **L1.2** Smart Erinnerungen — `SmartReminderService`, Cron `/portal/cron/smart-erinnerungen`, Migration 008
- ✅ **L1.9** Besitzer Dashboard — „Letzte Aktivität"-Widget aus `portal_check_notifications`
- ✅ **L2.3** Early Tester / Founder System — `is_founder`, `founder_since`, Migration 003, Toggle in Tenant-Detail
- ✅ **L2.4** Tenant Übersicht — Trial-Ende, Billing-Datum, Founder-Badge, letzter Login in Tenant-Liste
- ✅ **L2.6** Audit Log — `/admin/audit-log`, `ActivityLogRepository`, Logging in TenantController
- ✅ **L2.8** Cronjob-Monitoring — `/admin/cron-monitoring`, Status-Cards, Logs-Tabelle
- ✅ **Bugfix** MySQL 1103 im Dispatcher — `?token=` → `&token=` in `PraxisCronController::runNow()`

## Offen

- Keine bekannten offenen Tasks aus Sprint A

## Kritische Stolpersteine

Siehe `claude-obsidian/bugs/dispatcher-mysql-1103.md`

---

# Brain-Vollaudit — 2026-07-01

**Branch:** `claude/therapano-theratap-comparison-qinkwt`

## Fertig

- ✅ Auslöser: Wettbewerbsvergleich TheraPano vs. theratap.de angefragt, User meldete "Obsidian ist nicht mehr aktuell"
- ✅ 10 Feature-Status in `07-features/*.md` gegen echten Code verifiziert und korrigiert (siehe `00-start/open-items.md` → "Vollaudit 2026-07-01")
- ✅ `07-features/README.md` Statusmatrix aktualisiert
- ✅ `12-roadmap/roadmap.md` um 6 neue P1/P2-Punkte aus dem Audit ergänzt

## Offen

- Weitere Feature-Seiten mit generischem TODO ("Fachlichen Soll-/Ist-Vergleich ergänzen") sind noch nicht vollständig auditiert
- Optionale Ergänzung: Cron-Erinnerung an Mitarbeiter bei unbearbeiteten überfälligen Rechnungen (siehe Roadmap P2)

## Nachtrag — 2026-07-01, Teil 4/5

- ✅ Nutzerhinweis "Mahnwesen ist auch pro Tenant nutzbar" verifiziert und `finanz-autopilot.md` korrigiert (war fälschlich als reine SaaS-interne Funktion unterbewertet)
- ✅ Landingpage (`saas-platform/templates/landing/index.twig`) mit vollständigem, verifiziertem Funktionsumfang überarbeitet: 3D-Schmerzanalyse, Rechnungsdesign, Steuerexport, Hundeschule, Besitzerportal neu ergänzt; App-Plattform-Angaben korrigiert (kein falsches macOS/iOS-Beta-Versprechen mehr)
- ✅ Drei Vertriebs-/Marketing-Dokumente erstellt: `13-marketing/staerken-dokumentation.md`, `13-marketing/video-skript.md`, `13-marketing/vergleich-therapano-vs-theratap.md`

## Nachtrag — 2026-07-01, Teil 5: Echte KI-Integration (Grok/Gemini)

**Branch:** `main` (direktes Commiten, siehe geänderte `git-pr-rules.md`)

### Fertig
- ✅ `app/Services/AiService.php` — providerunabhängiger KI-Service (xAI Grok + Google Gemini), niemals werfend, immer `null` bei Fehlern
- ✅ SaaS-Admin: `AiSettingsController` + Template + Route + Nav-Eintrag „KI-Integration (Grok/Gemini)" unter „System" — Muster 1:1 von `GoogleSettingsController` übernommen
- ✅ Config-Verteilung via `saas-platform/storage/config/ai.php` (identisches Muster wie `google.php`)
- ✅ Feature-Gate: bestehendes `ki_assistance`-Feature genutzt (kein neues Feature nötig)
- ✅ TherapyCare: `aiProgressSummary()` — KI-Zusammenfassung des Therapiefortschritts
- ✅ Tierarztbericht: `aiDraft()` — KI-Entwurf-Button im Quill-Editor (serverseitig HTML-escaped)
- ✅ Patienten-Timeline: `timelineAiInsight()` — KI-Zusammenfassung der letzten Einträge
- ✅ TrainingCare: `aiRecommendations()` — KI-Trainingsempfehlung aus Mastery-/Erfolgsquoten-Daten
- ✅ Alle 4 Integrationen additiv, ausfallsicher (kein Provider/Feature aktiv → Button ausgeblendet bzw. klare Fehlermeldung, nie ein Crash)
- ✅ `therapycare-ai.md` / `trainingcare-ai.md` korrigiert: "AI" ist jetzt echte KI, nicht mehr nur Branding
- ✅ Neue Feature-Seite `07-features/ai-integration.md` mit vollständiger Architektur-Doku
- ✅ Alle geänderten PHP-Dateien mit `php -l` geprüft, alle Twig-Dateien auf Tag-Balance geprüft

### Offen
- Rate-Limiting/Kostenkontrolle pro Tenant für KI-Aufrufe
- Caching von KI-Antworten
- Audit-Log für KI-Nutzung
- Weitere Integrationspunkte (Marketing-Automation, Feedback-Kategorisierung) sind denkbar, aber bewusst nicht in dieser Runde umgesetzt

## Nachtrag — 2026-07-01, Teil 6: TheraTap-Vergleich korrigiert (Tiefenrecherche)

User wies zu Recht darauf hin, dass die erste TheraTap-Analyse nur auf einem einzigen
WebFetch-Aufruf der Startseite beruhte. Nachrecherche mit 14 Unterseiten
(`13-marketing/vergleich-therapano-vs-theratap.md` aktualisiert) ergab u. a.:
- TheraTap hat eine eigene KI-Funktionsseite (Sprache-zu-Formular, automatische
  KI-Rechnungserstellung aus Terminen) — Add-on 7,90€/Monat, andere Anwendungsfälle als unsere
  Grok/Gemini-Integration
- TheraTap-Tiermodelle sind explizit **2D** (nicht 3D), aber mit mehr anatomischen Ebenen als
  unser aktuelles 2D-System — unser 3D-Vorteil bleibt bestehen
- TheraTap unterstützt laut eigener FAQ **nur 1:1-Training, kein Gruppentraining/Kurse** — unser
  Hundeschul-Kurssystem-Vorteil ist damit doppelt bestätigt
- Neu entdeckte TheraTap-Stärken, die wir nicht haben: tiefe GOT-Abrechnung, Tourenplanung mit
  Google Routes API, Praxis-Analytics mit Heatmap/Geo-Karte/Team-Vergleich, Kalender-Sync +
  einbettbares Buchungs-Widget, universelles Stempelkartensystem, E-Rechnung (XRechnung) +
  Multi-Land-Support, praxisübergreifende Consumer-App "Mein Tier"

## Nachtrag — 2026-07-01, Teil 7: Provider-Wechsel Grok → Groq

User-Referenz war eine andere Instanz ("inc.therapano.de") mit besserer, kostenloser
API-Anbindung — gemeint war **Groq** (api.groq.com, LPU-Hosting offener Modelle, kostenloses
Tier), nicht xAI Grok. Umgestellt:
- ✅ `app/Services/AiService.php`: Endpoint auf `api.groq.com/openai/v1/chat/completions`,
  Konfigurationsschlüssel `groq_api_key`/`groq_model` statt `grok_*`, neue kuratierte
  Modell-Konstante `AiService::GROQ_MODELS` (Llama 3.3 70B Versatile als Empfohlen, plus
  Llama 3.1 8B Instant, GPT-OSS 120B/20B, Gemma 2 9B, DeepSeek R1 Distill Llama 70B)
- ✅ SaaS-Admin (`AiSettingsController`): Radio-Button-Modellauswahl statt Freitext, serverseitig
  validiert gegen die kuratierte Liste; **wichtig:** Modell-Liste dort dupliziert, weil
  Praxis-App (`App\`) und SaaS-Platform (`Saas\`) getrennte Composer-Projekte ohne gemeinsames
  Autoloading sind (ursprünglicher `use App\Services\AiService;`-Import wäre zur Laufzeit
  fehlgeschlagen — vor dem Commit korrigiert)
- ✅ Routen, Nav-Text und alle Doku-Referenzen (`ai-integration.md`, Marketing-Dateien) von
  Grok auf Groq umbenannt
- Gemini bleibt unverändert als zweiter Provider
