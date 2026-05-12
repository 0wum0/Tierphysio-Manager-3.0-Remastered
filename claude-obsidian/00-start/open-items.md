# Open Items Audit

**Zuletzt aktualisiert:** 2026-05-12  
**Quelle:** Abgleich `claude-obsidian/**` gegen Repo-Dateien per `rg`, punktueller Code-Review und Fix-Durchlauf.

## Zweck
Zentrale Sicht auf offene, nicht abgearbeitete Punkte aus dem Brain. Diese Datei ersetzt nicht die
Detailseiten, sondern priorisiert sie fuer die naechste Umsetzung.

## P0 - Funktionale Bugs

Keine offenen P0-Bugs aus diesem Audit.

## P1 - Produkt-/UX-Luecken

Keine offenen P1-Punkte aus diesem Audit. Die bisherigen P1-Punkte wurden am 2026-05-12 umgesetzt:

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
