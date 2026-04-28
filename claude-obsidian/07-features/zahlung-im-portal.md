# Zahlung im Portal

## Beschreibung
Feature-Dokumentation für Zahlung im Portal.

## Zweck
Gemeinsames Verständnis für Implementierung, Grenzen und nächste Schritte.

## Relevante Dateien im Repo
- `saas-platform/templates/register/payment_success.twig`
- `saas-platform/app/Services/PaymentService.php`
- `plugins/owner-portal/templates/owner_invoices.twig`

## Datenfluss
Client/Web/Portal → Route/Controller/Plugin → Repository/Service → UI/Response.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Status: **partial/verify**.

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
