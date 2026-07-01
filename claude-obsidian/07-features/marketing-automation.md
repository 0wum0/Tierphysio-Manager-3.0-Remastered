# Marketing Automation

## Beschreibung
Feature-Dokumentation für Marketing Automation.

## Zweck
Gemeinsames Verständnis für Implementierung, Grenzen und nächste Schritte.

## Relevante Dateien im Repo
- `dist/dashboard-marketing.html (reference only)`
- `public/themes/smart-tierphysio/scripts/pages/marketingdashboard.js`
- `saas-platform/public/sa/scripts/pages/marketingdashboard.js`

## Datenfluss
Client/Web/Portal → Route/Controller/Plugin → Repository/Service → UI/Response.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Status: **not_found** — kein Backend, nur Dashboard-Visualisierung.

## Audit-Befund (2026-07-01, gegen echten Code verifiziert)
`marketingdashboard.js` rendert ausschließlich ApexCharts-Diagramme aus vorhandenen Daten
(Reporting-UI). Es existiert **kein** `MarketingController.php`, `CampaignController.php` oder
sonstige Backend-Logik für automatisierte E-Mail-Kampagnen, Serienmails oder Trigger-basierte
Nachrichten (`grep` nach "Campaign"/"sendEmailCampaign" in `app/` liefert keine Treffer). Das
Feature "Marketing Automation" existiert aktuell nicht — nur ein Reporting-Dashboard.

## Risiken
- Darf nicht als aktives Automatisierungs-Feature beworben werden.

## TODOs
- Entweder echte Marketing-Automation bauen (Trigger-Mails, Kampagnen, Serienmails) oder
  Feature-Seite als "Marketing-Dashboard (Reporting only)" umbenennen.

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
