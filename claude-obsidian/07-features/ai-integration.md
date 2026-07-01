# KI-Integration (Groq / Gemini)

## Beschreibung
Providerunabhängige KI-Textgenerierung, zentral im SaaS-Admin konfiguriert und an mehreren
sinnvollen Stellen in der Praxis-App genutzt. Ersetzt die vorherige reine Namensgebung
("TherapyCare AI", "TrainingCare AI" ohne echtes KI-Backend) durch echte Funktionalität.

## Status
**implemented** (2026-07-01, Provider am 2026-07-01 von xAI Grok auf **Groq** umgestellt —
siehe Korrektur unten)

## Korrektur (2026-07-01, gleicher Tag): Grok → Groq
Ursprünglich wurde xAI Grok (`api.x.ai`) als erster Provider verdrahtet. Auf Wunsch des
Produktverantwortlichen — Referenz war die bessere/kostenlose Anbindung auf einer anderen
Instanz — wurde auf **Groq** (`api.groq.com`, OpenAI-kompatible Chat-Completions-API, schnelles
LPU-Hosting offener Modelle, großzügiges kostenloses Tier) umgestellt. Die API-Form ist nahezu
identisch (Bearer-Token, `{model, messages, temperature}`-Payload), nur Endpoint und Modell-Namen
haben sich geändert. Gemini bleibt unverändert der zweite Provider.

## Architektur

### Zentraler Service
`app/Services/AiService.php` — providerunabhängige Klasse, unterstützt **Groq** und
**Google Gemini**. Design-Prinzip: wirft niemals eine Exception nach außen — jede Methode gibt
bei Fehlern/fehlender Konfiguration `null` zurück. KI-Ausfälle (Netzwerk, falscher Key,
Rate-Limit) dürfen die Praxissoftware nie blockieren oder crashen lassen.

- `isConfigured()` / `isProviderConfigured(string $provider)`
- `generateText(string $systemPrompt, string $userPrompt, ?string $provider = null): ?string`
  — nutzt den konfigurierten Standard-Provider, fällt automatisch auf den jeweils anderen
  Provider zurück, falls dieser nicht konfiguriert ist.
- `AiService::GROQ_MODELS` — kuratierte Liste der in der SaaS-Admin-UI wählbaren Groq-Modelle
  (Llama 3.3 70B Versatile als „Empfohlen" markiert, plus Llama 3.1 8B Instant, GPT-OSS 120B/20B,
  Gemma 2 9B, DeepSeek R1 Distill Llama 70B). **Wichtig:** Diese Liste ist in
  `saas-platform/app/Controllers/AiSettingsController.php::GROQ_MODELS` dupliziert, weil
  Praxis-App (`App\`) und SaaS-Platform (`Saas\`) getrennte Composer-Projekte mit eigenem
  Autoloading sind — kein direkter Klassenzugriff über die Grenze möglich. Bei Änderungen an der
  Modell-Liste **beide Stellen synchron halten**.

### Konfigurationsverteilung (identisches Muster wie Google-Kalender-Integration)
- SaaS-Admin (`Saas\Controllers\AiSettingsController`) speichert Groq-/Gemini-API-Keys +
  Modellnamen + Standard-Provider in `saas_settings` (Key-Value-Tabelle).
- Beim Speichern wird zusätzlich `saas-platform/storage/config/ai.php` geschrieben.
- `AiService` liest diese Datei zur Laufzeit (`dirname(__DIR__, 2) . '/saas-platform/storage/config/ai.php'`).
- Kein neues Datenbankschema nötig — reines Zwei-Dateien-Muster wie bei `google.php`.

### SaaS-Admin-UI
- Route: `/admin/ai-settings` (Nav: „System" → „KI-Integration (Groq/Gemini)")
- Groq-Modell wird als Radio-Button-Auswahl (nicht Freitext) angeboten — Server validiert bei
  `update()`, dass nur ein Key aus `GROQ_MODELS` akzeptiert wird (verhindert ungültige Modell-Slugs
  durch manipulierte POST-Daten).
- `GET /admin/ai-settings/test-groq` / `GET /admin/ai-settings/test-gemini` — Verbindungstests
  analog zu `PaymentSettingsController::testStripe()`/`testPayPal()`.

### Feature-Gate
Alle KI-Buttons in der Praxis-App sind an das **bereits existierende** Feature
`ki_assistance` gekoppelt (`saas_feature_flags`, seit Migration 050, `required_plan = ultra`).
Templates prüfen `{% if features.ki_assistance ?? false %}` (Twig-Global aus
`FeatureGateService::all()`), Controller rufen zusätzlich `$this->requireFeature('ki_assistance')`
als Defense-in-Depth auf. Ist das Feature deaktiviert oder kein Provider konfiguriert, bleiben
die KI-Buttons in der UI komplett ausgeblendet — keine Auswirkung auf bestehende Funktionen.

## Konkrete Integrationspunkte

### 1. TherapyCare — Fortschritts-Zusammenfassung
- `plugins/therapy-care-pro/TherapyCareController.php::aiProgressSummary()`
- Route: `POST /api/tcp/patienten/{id}/fortschritt/ki-zusammenfassung`
- UI: neue Karte „KI-Zusammenfassung" in `progress_index.twig`, oberhalb der Eintragsliste.
- Prompt-Basis: letzte 40 Fortschritts-Einträge (Kategorie, Score, Notiz) → 2-3 Sätze
  Trendzusammenfassung, keine Diagnose/Behandlungsempfehlung.

### 2. Tierarztbericht — KI-Entwurf
- `plugins/vet-report/VetReportController.php::aiDraft()`
- Route: `POST /patienten/{id}/tierarztbericht/ki-entwurf`
- UI: neuer Button „KI-Entwurf" neben den medizinischen Schnellvorlagen im Quill-Editor-Modal
  (`templates/partials/patient-modal-global.twig`).
- Prompt-Basis: Timeline (Behandlungen/Notizen) + Anamnese-Notiz → strukturierter Berichtsentwurf
  (Anamnese/Verlauf/Befund/Empfehlung). KI-Output wird serverseitig in escaptes HTML (`<p>`-Tags)
  umgewandelt, bevor es ans Frontend geht — verhindert HTML-Injection durch Modell-Output.

### 3. Patienten-Timeline — KI-Zusammenfassung
- `app/Controllers/PatientController.php::timelineAiInsight()`
- Route: `POST /patienten/{id}/timeline/ki-zusammenfassung`
- UI: Karte oberhalb der Timeline-Liste im Patient-Modal.
- Prompt-Basis: letzte 20 Timeline-Einträge vom Typ `treatment`/`note`.

### 4. TrainingCare — Trainingsempfehlungen
- `app/Controllers/TrainingPlanController.php::aiRecommendations()`
- Route: `POST /trainingsplaene/zuweisung/{id}/ki-empfehlung`
- UI: Karte „KI-Trainingsempfehlung" auf der Zuweisungs-Detailseite (`assignment_show.twig`).
- Prompt-Basis: Mastery-Level + durchschnittliche Erfolgsquote je Übung.
- Feature-Gate: zusätzlich zu `ki_assistance` auch `dogschool_training_plans`.

## Ausfallsicherheit (wichtig für Bewertung)
- Kein Provider konfiguriert → Button bleibt sichtbar (Feature ist ja aktiv), Klick liefert
  `{ok:false, error:'ai_not_configured'}` mit klarer Nutzer-Meldung.
- API-Aufruf schlägt fehl (Netzwerk, Rate-Limit, falscher Key) → `{ok:false, error:'ai_unavailable'}`.
- Keine Daten für sinnvollen Prompt vorhanden → `{ok:false, error:'no_data'}`.
- In keinem Fall wird eine bestehende Funktion (PDF-Erzeugung, Timeline, Fortschritts-Tracking)
  durch die KI-Integration verändert oder blockiert — alle vier Punkte sind rein additiv.

## Bekannte Grenzen / TODOs
- Kein Rate-Limiting/Kostenkontrolle pro Tenant auf Praxis-Seite (nur Feature-Gate auf Ultra-Plan).
- Kein Caching von KI-Antworten — jeder Klick löst einen neuen API-Call aus.
- Kein Audit-Log für KI-Nutzung (wer hat wann welchen Prompt ausgelöst).
- Weitere sinnvolle Stellen (z.B. Marketing-Automation-Texte, Feedback-Kategorisierung) sind noch
  nicht angebunden — bewusst auf die vier wertvollsten, klar abgegrenzten Punkte beschränkt.

## Verlinkungen
- [[07-features/therapycare-ai]]
- [[07-features/trainingcare-ai]]
- [[06-saas/feature-mapping]]
- [[00-start/CRITICAL-RULES]]
