# SaaS Billing System — Architektur & Implementierung

Stand: Mai 2026 — vollständig implementiert

## Architektur-Überblick

```
Stripe Webhook (invoice.payment_succeeded)
    ↓
PaymentService::onStripePaymentSucceeded()
    ↓
SaasInvoiceBillingService::createFromStripePayment()
    ↓ (idempotent via stripe_invoice_id UNIQUE + saas_webhook_events)
saas_invoices + saas_invoice_positions (erstellt)
```

## Beteiligte Dateien

| Datei | Aufgabe |
|---|---|
| `saas-platform/app/Services/SaasInvoiceBillingService.php` | Kern-Billing-Service |
| `saas-platform/app/Services/PaymentService.php` | Stripe-Webhook-Handler (ruft BillingService) |
| `saas-platform/app/Controllers/SaasInvoiceController.php` | Admin-UI + neue Aktionen |
| `saas-platform/app/Repositories/SaasInvoiceRepository.php` | DB-Zugriff |
| `saas-platform/migrations/065_saas_billing_extended.sql` | Neue Spalten + Tabellen |

## Datenbank-Schema (Migration 065)

### saas_invoices — neue Spalten
- `type` ENUM('invoice','credit_note','correction') DEFAULT 'invoice'
- `credit_note_for` INT — Verknüpfung zur Originalrechnung
- `storno_reason` VARCHAR(500) — Storno-Begründung
- `stripe_invoice_id` VARCHAR(128) UNIQUE — Idempotenz-Schlüssel
- `stripe_subscription_id` VARCHAR(128)
- `billing_period_start` DATE
- `billing_period_end` DATE
- `invoice_type_label` VARCHAR(64) — Anzeige-Label

### Neue Tabellen
- `saas_invoice_dunnings` — Mahnungen pro Rechnung (getrennt von Praxis!)
- `saas_webhook_events` — Webhook-Idempotenz-Log

## Stripe Webhook Flow

```
POST /payment/stripe/webhook
→ PaymentService::handleStripeWebhook()
→ invoice.payment_succeeded
  → onStripePaymentSucceeded()
    → UPDATE tenants SET status='active'
    → UPDATE subscriptions SET status='active', last_payment_at=NOW()
    → INSERT payments (ON DUPLICATE KEY UPDATE = idempotent)
    → SaasInvoiceBillingService::createFromStripePayment($stripeInvoice)
      → check saas_webhook_events (Duplikat → skip)
      → getNextInvoiceNumber('TP', 1000)
      → INSERT saas_invoices (stripe_invoice_id UNIQUE = Duplikat-Schutz)
      → INSERT saas_invoice_positions
      → markWebhookProcessed()
```

## Idempotenz-Regeln

1. **stripe_invoice_id UNIQUE** → INSERT wirft 1062 bei Duplikat → catch → return existing id
2. **saas_webhook_events** → Lookup vor INSERT, INSERT IGNORE nach INSERT
3. **payments** → `ON DUPLICATE KEY UPDATE paid_at = paid_at` (kein Fehler bei Doppelt)
4. Alle SaasInvoiceBillingService-Methoden sind try/catch-wrapped und loggen statt zu crashen

## Storno / Gutschrift (GoBD-konform)

- Keine Löschung von Rechnungen
- `createCreditNote(originalId, reason, overrideAmount)` → neue Rechnung mit `type='credit_note'`
- Original → Status `cancelled`
- Neue Gutschrift hat negative Beträge und eigene Rechnungsnummer (TPG-XXXX)
- Beide erscheinen im Steuerexport und DATEV

## Mahnwesen

- `runDunningCycle()` → läuft per Cron oder manuell
- 3 Stufen konfigurierbar via saas_settings:
  - Level 1 (7 Tage): Zahlungserinnerung, keine Gebühr
  - Level 2 (14 Tage): 1. Mahnung, 5 € Gebühr
  - Level 3 (21 Tage): Letzte Mahnung, 10 € Gebühr
- `saas_invoice_dunnings` Tabelle speichert alle Mahnungen
- E-Mail-Versand optional pro Mahnung

## DATEV Export

- Route: `GET /admin/invoices/datev-export?format=datev`
- Format: DATEV EXTF 510/21 Buchungsstapel
- Konto 8400 (Umsatzerlöse), Gegenkonto = Kundennummer
- Enthält alle Rechnungen UND Gutschriften im Zeitraum

## Self-Healing

- `reconcileMissingInvoices()` → prüft payments-Tabelle auf Zahlungen ohne Rechnung
- Erstellt fehlende Rechnungen retroaktiv mit Label "rekonstruiert"
- Manuell aufrufbar über Admin → Rechnungen → Verwaltung → Self-Healing

## Nummernkreise

- Rechnungen: `TP-XXXX` (konfigurierbar via saas_invoice_prefix)
- Gutschriften: `TPG-XXXX`
- Startpunkt: 1000 (konfigurierbar via saas_invoice_start_number)

## Multi-Tenant Sicherheit

- `saas_invoices` hat keine Tenant-Prefix — SaaS-eigene Tabellen
- Kein Zugriff von Praxis-App auf saas_invoices
- Praxis-Tenants sehen nur eigene Rechnungen (via Tenant-Portal-Scope)
- SaaS-Admin sieht alles

## Testphasen

- Trial-Rechnungen: `status='draft'`, `total_gross=0.00`, Label "Testphase"
- Nach Trial → Stripe löst invoice.payment_succeeded aus → echte Rechnung
- `createFromSubscriptionStart($tenant, $planName, $amount, isTrial=true)` erstellt Trial-Dokument
