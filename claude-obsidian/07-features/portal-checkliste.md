# Portal-Checkliste (Patient-Modal-Tab)

## Beschreibung
Eigener Tab `pd-panel-portal` im Patienten-Modal (`patient-modal-global.twig:657`) für die Portal-Freigabe-Checkliste pro Patient — bisher weder in [[07-features/zahlung-im-portal]] noch in der Owner-Portal-Doku erwähnt.

## Status
**implemented, Detailumfang zu klären** (verifiziert 2026-07-01, Vollaudit — nur Existenz bestätigt, Funktionsumfang nicht im Detail geprüft)

## Relevante Dateien im Repo
- `templates/partials/patient-modal-global.twig` (Tab `pd-panel-portal`)

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.

## TODOs
- Backend-Controller/Repository hinter dem Tab identifizieren und Funktionsumfang dokumentieren.
- Bezug zu [[05-portal/README]] und [[07-features/zahlung-im-portal]] klären.

## Verlinkungen
- [[05-portal/README]]
- [[07-features/zahlung-im-portal]]
