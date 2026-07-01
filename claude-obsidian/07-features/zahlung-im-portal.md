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
- Status: **partial** — Rechnungsliste/PDF-Download ja, Online-Zahlfunktion im Owner-Portal nein.

## Audit-Befund (2026-07-01, gegen echten Code verifiziert)
`owner_invoices.twig` zeigt Tierbesitzern nur eine Rechnungsliste mit PDF-Download-Link — **kein**
Zahlungsbutton, kein Stripe-Checkout, keine Zahlungsweiterleitung im Owner-Portal-Plugin
(`plugins/owner-portal/` enthält keinen `payment`/`checkout`/`stripe`-Verweis). `PaymentService.php`
in `saas-platform/` implementiert echte Stripe-/PayPal-Zahlungen, ist aber ausschließlich für
**SaaS-Tenant-Abos** zuständig (Praxis zahlt Abo an TheraPano), nicht für Tierbesitzer-Zahlungen
an die Praxis. "Zahlung im Portal" für Endkunden existiert also nicht.

## Risiken
- Teilimplementierungen können zu falschen Erwartungen führen.

## TODOs
- Online-Zahlfunktion für Tierbesitzer im Owner-Portal bauen (z.B. Stripe-Checkout pro offener
  Rechnung), falls das Produkt-Ziel ist — aktuell nur Anzeige/Download.

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
