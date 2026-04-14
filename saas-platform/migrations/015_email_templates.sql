-- Migration 009: Default E-Mail Templates
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('email_invoice_subject',  'Ihre Rechnung {{invoice_number}}'),
('email_invoice_body',     'Sehr geehrte/r {{owner_name}},\n\nanbei erhalten Sie Ihre Rechnung {{invoice_number}} vom {{issue_date}}.\n\nGesamtbetrag: {{total_gross}}\n\nBitte überweisen Sie den Betrag bis zum {{due_date}}.\n\nMit freundlichen Grüßen\n{{company_name}}'),
('email_receipt_subject',  'Ihre Quittung {{invoice_number}}'),
('email_receipt_body',     'Sehr geehrte/r {{owner_name}},\n\nvielen Dank für Ihre Zahlung. Anbei erhalten Sie Ihre Quittung für Rechnung {{invoice_number}} vom {{issue_date}}.\n\nBezahlter Betrag: {{total_gross}}\n\nMit freundlichen Grüßen\n{{company_name}}');
