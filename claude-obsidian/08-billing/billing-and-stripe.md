# Billing & Stripe

## Beschreibung
Abrechnung im SaaS-Admin und Zahlungsflüsse (Stripe/PayPal Callback-Routen).

## Zweck
Zahlungsrelevante Änderungen sicher und ohne Breaking Side-Effects dokumentieren.

## Relevante Dateien im Repo
- `saas-platform/app/Services/PaymentService.php`
- `saas-platform/app/Controllers/PaymentSettingsController.php`
- `saas-platform/app/Routes/web.php`
- `templates/partials/_billing_notice.twig`
- `migrations/011_feedback_and_payment.sql`

## Datenfluss
Checkout/Webhook/Return → `PaymentService` → Tenant-/Subscription-Zustand → Anzeigen/Notices.

## Wichtige Regeln
- Webhook-Handling nicht brechen.
- Subscription-Logik nur mit explizitem Auftrag ändern.

## Risiken
- Falsche Statuswechsel erzeugen Zugriffs- oder Umsatzfehler.

## TODOs
- Retry- und Idempotency-Strategie dokumentieren.

## Verlinkungen
- [[06-saas/saas-platform-overview]]
- [[11-decisions/decision-log]]
