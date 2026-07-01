# Open Items Audit

**Zuletzt aktualisiert:** 2026-07-01  
**Quelle:** Abgleich `claude-obsidian/**` gegen Repo-Dateien per `rg`, punktueller Code-Review und Fix-Durchlauf.

## Vollaudit 2026-07-01 — Doku-Korrekturen (Status war veraltet/zu optimistisch)

Anlass: `claude-obsidian/07-features/*.md` wies mehrere Features als "implemented"/"partial" aus,
obwohl der Code das nicht hergab (Auslöser: Wettbewerbsvergleich TheraPano vs. externem Anbieter,
bei dem auffiel, dass die Doku nicht mehr aktuell ist). Zwei Sub-Agenten haben je 5 Features gegen
den echten Code verifiziert. Ergebnis — Status korrigiert in den jeweiligen Detailseiten und in
[[07-features/README]]:

| Feature | Alter Status | Neuer Status |
|---|---|---|
| TherapyCare AI | partial/verify AI scope | **not_found (AI)** — Basissystem ohne KI ist implementiert |
| TrainingCare AI | planned/unknown | **implemented (Trainingsplan-System)**, AI-Anteil not_found |
| Gamification | unknown/planned | **partial** — nur Score-Badges, keine echte Gamification |
| Video Feedback | implemented, verify UX | **partial** — Upload/Anzeige ja, kein Feedback-/Annotations-Modul |
| Finanz-Autopilot | partial indicators | **partial** — nur manuelle Mahnungen, keine Cron-Eskalation |
| Marketing Automation | partial/verify | **not_found** — nur Reporting-Dashboard, kein Backend |
| Zahlung im Portal | partial/verify | **partial** — nur Rechnungsliste, keine Online-Zahlfunktion |
| Kurs-System Hundeschulen | implemented | implemented (bestätigt), Endkunden-Webshop-Buchung offen |
| Praxis vs. Hundeschule | implemented basis | **implemented** — Terminology-Switching bestätigt |

Details je Feature stehen in den jeweiligen `07-features/*.md`-Dateien unter "Audit-Befund (2026-07-01)".
Neue Roadmap-Punkte dazu in [[12-roadmap/roadmap]] (P1/P2).

## Vollaudit 2026-07-01 Teil 2 — Kritischer Fund + 11 komplett fehlende Feature-Docs

Der Produktverantwortliche wies darauf hin, dass die Doku "nicht alles" abbildet — konkretes
Beispiel: ein 3D-Schmerzanalyse-Tab im Patient-Modal mit echten 3D-Modellen (Hund/Katze/Pferd,
Three.js) war im Code voll produktiv, aber in [[07-features/veterinary-anatomy-engine]] als
"blockiert bis 3D-Modelle vorhanden" dokumentiert — **die Modelle existieren bereits** unter
`public/assets/3D/*.glb`. Das ist die gravierendste bisher gefundene Doku-Code-Abweichung
(P0-Korrektur, siehe Status-Hinweis oben in der Datei).

Ein zweiter, breiterer Code-Sweep (alle Controller, Plugins, große JS-Module, Patient-Modal-Tabs)
fand 11 weitere Features ganz ohne Doku-Entsprechung — jetzt neu angelegt:
[[07-features/gobd-audit-log]], [[07-features/tax-export-pro]], [[07-features/mailbox-plugin]],
[[07-features/bulk-mail]], [[07-features/theme-manager]], [[07-features/patient-invite]],
[[07-features/patient-intake]], [[07-features/consent-management]], [[07-features/online-booking]],
[[07-features/ui-settings-notifications]], [[07-features/media-compressor]],
[[07-features/portal-checkliste]] (Detailumfang bei letzterem noch offen).

**Wichtige Lehre:** Dieser zweite Sweep war noch keine vollständige Abdeckung von `app/Controllers/`
und `plugins/*` — nur eine gezielte Suche nach den auffälligsten Lücken. Ein systematischer
1:1-Abgleich aller Controller/Plugins gegen `07-features/*.md` steht noch aus (siehe Roadmap P2).

## Zweck
Zentrale Sicht auf offene, nicht abgearbeitete Punkte aus dem Brain. Diese Datei ersetzt nicht die
Detailseiten, sondern priorisiert sie fuer die naechste Umsetzung.

## P0 - Funktionale Bugs

| Bereich | Punkt | Hinweis |
|---|---|---|
| SaaS Migration v065 | `saas-platform/migrations/065_saas_billing_extended.sql` liegt im Tenant-Migrationspfad, enthält aber globale `saas_*` Tabellen. Vor Rollout prüfen und als v067/global Repair sauberziehen. |
| Cron Dispatcher | `CronController` nutzt `cron_dispatcher_log` unprefixed, während Tenant-Migrationen prefixed Tabellen erzeugen. Code + Repair-Migration v067 nötig. |
| Cron Logging | `CronAdminController` wird referenziert, Datei fehlt. Logging-Service oder Controller wiederherstellen. |
| Flutter Owner-Portal | Besitzerportal-Login/Navigation/Token-Speicherung ist getrennt vom Staff-Auth nicht stabil modelliert. Separaten Owner-Portal-Auth-State und Router-Zweig bauen. |

## P1 - Produkt-/UX-Luecken

Neue P1-Punkte aus dem Sub-Agent-Audit 2026-05-12:

- Hundeschul-Strukturmigrationen liegen noch im Root-Legacy-Ordner `migrations/050...053`; zentralen SaaS-Repair-Rollout planen oder Runtime-Self-Healing explizit als Architekturentscheidung dokumentieren.
- `DogschoolSchemaService` nutzt `ADD COLUMN IF NOT EXISTS` fuer `tax_rate`; Repair-Migration ohne `IF NOT EXISTS` und Runtime-Code auf kompatibles Pattern umstellen.
- Paketverkauf/-einloesung fachlich abrunden: Rechnungsstatus/Button sichtbar machen und Einloesung gegen Halter/Hund/Kurs pruefen.
- Kurs-Terminbearbeitung klaeren: bestehende Sessions werden nach Kursdaten-Aenderung nicht automatisch nachgezogen.
- Lead-Mindestvalidierung ergaenzen: mindestens Name oder Kontakt plus Interesse/Hund.
- Mobile Hundeschule/TrainingCare: Capability-/Terminology-Vertrag fuer `practice_type`, Feature-Flags und Labels ergaenzen.

Bereits in diesem Durchlauf umgesetzt:

- Hundeschule: Paketverkauf-Route `/pakete/verkaufen` vor generischer `/pakete/{id}` Route verschoben.
- Hundeschule: Kursdetail/Dashboard reichen genutzte Feature-Flags fuer Rechnung/Warteliste/Anwesenheit durch.
- Hundeschule: Lead-/Paketformular enthalten keine verschachtelten Formulare mehr.
- Homework: fehlendes `declare(strict_types=1)` ergaenzt und Plan-Self-Healing nutzt prefixed Tabellen.
- Praxis-App: Timeline-Update/-Delete prueft, ob der Eintrag zum Patient der Route gehoert.
- Mobile API/Flutter: Passwort-Confirm-Felder, Rechnungs-Storno-URL, Offline-POST-Sync und `noshow`/`no_show` Status-Mapping repariert.

Die bisherigen P1-Punkte wurden am 2026-05-12 umgesetzt:

- Hundeschule: Dashboard-Paketverkauf antwortet bei AJAX mit JSON; Paketkatalog wird im Modal befuellt.
- Befund/Anatomie: `schmerz_nrs` wird in Admin-, Portal- und Praxis-Show-Views als Read-only-Skala angezeigt.
- Befund-PDF: Anatomy-Marker und Freihand-Zeichnungen werden als strukturierte Zusammenfassung exportiert.
- Google Kalender: Admin-Ansicht zeigt die letzten Import-Zuordnungen inkl. Patient/Halter/Appointment.
- Chat-Medien: MP4/WebM/MOV Upload + Inline-Preview in Admin, Portal und Drawer; Bilder werden serverseitig optimiert/resized, wenn noetig.

## P2 - Architektur/Doku/Verifikation

| Bereich | Punkt | Hinweis |
|---|---|---|
| Veterinary Anatomy Engine | Layer-Engine/3D-Fahrplan ist beschlossen, aber externe/professionelle Assets blockieren die Zielqualitaet. |
| Feature-Dokus | Viele Feature-Seiten enthalten noch generische TODOs: fachlicher Soll-/Ist-Vergleich und E2E-Flow. |
| Architektur-Dokus | Tenant-Discovery-Sequenz, Mobile-API-Fehlercodes, SaaS-Planmatrix, Cron-Dashboard-Ausbau sind noch Dokumentations-/Verifikationsaufgaben. |

## Erledigt / Kein offener Sprint-Blocker

- Sprint A: keine bekannten offenen Tasks.
- Google-Kalender Cron-/Tenant-Fixes: als `fixed` dokumentiert.
- Google-Kalender Recurring Events: `singleEvents=true` in `GoogleApiService::listEvents()` expandiert Serien in Einzeltermine.
- Google-Kalender Timezone: Push nutzt `Europe/Berlin`; Pull normalisiert importierte DateTime-Werte nach `Europe/Berlin`.
- Chat-Bildanhaenge, Video-Preview, Lightbox und serverseitige Bildoptimierung: implementiert.
- Hausaufgaben-Plugin: Default aktiv fuer Basic/Pro/Ultra dokumentiert.

## Verlinkungen
- [[00-start/sprint-status]]
- [[07-features/README]]
- [[10-bugs/known-bugs]]
- [[12-roadmap/roadmap]]
