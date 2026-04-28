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
- Status: **partial indicators (dashboard/scripts), verify backend automation**.

## Risiken
- Teilimplementierungen können zu falschen Erwartungen führen.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
