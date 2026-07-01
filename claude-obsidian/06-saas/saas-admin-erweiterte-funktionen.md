# SaaS-Admin — Erweiterte Betreiber-Funktionen

## Beschreibung
Sammeldatei für sechs SaaS-Admin-Controller mit substantieller Business-Logik, die bisher nicht
dokumentiert waren. Betreffen den Betreiber (uns), nicht direkt die Praxis-Kunden — relevant für
interne Doku, nicht primär für den TheraTap-Wettbewerbsvergleich.

## Status
**implemented** (verifiziert 2026-07-01, Tiefenaudit)

## Revenue-Analytics
`saas-platform/app/Controllers/RevenueController.php` + `RevenueRepository` — MRR, ARR, ARPU,
Trial-Konversionsrate, Churn pro Monat (12-Monats-Trend), Revenue nach Plan, neue Tenants pro Monat.

## Feature-Gating (3-Ebenen)
`saas-platform/app/Controllers/FeaturesController.php` — (1) globale Kill-Switches je Feature,
(2) Plan-Feature-Matrix (JSON), (3) Per-Tenant-Override. Cache-Invalidierung sofort oder mit 5-Min-TTL.

## SaaS-Rechnungen (GoBD-konform, für Tenant-Abos)
`saas-platform/app/Controllers/SaasInvoiceController.php` — automatische Rechnungsnummern,
PDF-Generierung, Mahnwesen (3 Stufen mit Gebühren), Gutschrift/Storno, DATEV-/Steuer-Export,
Finalisierung mit Bearbeitungssperre.

## Zahlungsanbieter
`saas-platform/app/Controllers/PaymentSettingsController.php` — Stripe UND PayPal (inkl.
Sandbox-Mode), Verbindungstests für beide APIs.

## Zentrale Google-OAuth-Verwaltung
`saas-platform/app/Controllers/GoogleSettingsController.php` — eine zentrale Google-API-Konfiguration
für alle Tenants (Client-ID/Secret/Redirect-URI), Verbindungstest.

## Lizenz-API (Offline-Support)
`saas-platform/app/Controllers/LicenseApiController.php` — Token-Verify/Issue für Offline- bzw.
On-Premise-Betrieb, Features + Offline-Tage werden im Lizenz-Token mitgegeben.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Zahlungs-/OAuth-Credentials niemals loggen.

## TODOs
- Bei Bedarf einzelne Module auf eigene Detailseiten ausgliedern.

## Verlinkungen
- [[06-saas/plan-system]]
- [[06-saas/saas-platform-overview]]
- [[08-billing/billing-and-stripe]]
