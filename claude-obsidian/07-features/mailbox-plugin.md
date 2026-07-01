# Mailbox-Plugin (IMAP/SMTP-Client)

## Beschreibung
Vollständiger E-Mail-Client direkt in der Praxis-App — kein reiner Benachrichtigungsversand, sondern echtes Mail-UI mit Posteingang.

## Status
**implemented** (verifiziert 2026-07-01, Vollaudit)

## Relevante Dateien im Repo
- `plugins/mailbox/MailboxController.php`
- `plugins/mailbox/MailboxService.php`

## Funktionsumfang
- Posteingang per IMAP/POP3 lesen
- E-Mails per SMTP verfassen und versenden
- Läuft innerhalb der Praxis-App, nicht nur als Cron-Versand

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- IMAP/SMTP-Zugangsdaten sind sensibel — niemals loggen oder in Fehlermeldungen ausgeben.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[07-features/bulk-mail]]
