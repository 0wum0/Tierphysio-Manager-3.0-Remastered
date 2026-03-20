-- Migration 021: Update email templates from "wir/uns" to "ich/mein"
-- Only updates rows that still contain the old default text.

UPDATE settings
SET value = "Hallo {{owner_name}},\n\nich möchte dich freundlich daran erinnern, dass die Rechnung {{invoice_number}} vom {{issue_date}} über {{total_gross}} noch aussteht.\n\nBitte überweise den Betrag bis zum {{reminder_due_date}} auf mein Konto.\n\nFalls du die Zahlung bereits veranlasst hast, bitte ich dich, dieses Schreiben als gegenstandslos zu betrachten.\n\nLiebe Grüße\n{{company_name}}"
WHERE `key` = 'email_payment_reminder_body'
  AND value LIKE '%wir möchten%';

UPDATE settings
SET value = "Hallo {{owner_name}},\n\ntrotz meiner Zahlungserinnerung ist die Rechnung {{invoice_number}} vom {{issue_date}} über {{total_gross}} noch nicht beglichen worden.\n\nIch fordere dich hiermit auf, den ausstehenden Betrag zuzüglich einer Mahngebühr von {{fee}} bis zum {{dunning_due_date}} zu begleichen.\n\nGesamtbetrag: {{total_with_fee}}\n\nLiebe Grüße\n{{company_name}}"
WHERE `key` = 'email_dunning_body'
  AND value LIKE '%unserer Zahlungserinnerung%';
