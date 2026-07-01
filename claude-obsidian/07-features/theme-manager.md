# Theme-Manager

## Beschreibung
Tenant-individuelle Custom-Themes per ZIP-Upload — CSS/Twig-Layout-Override ohne Code-Deploy.

## Status
**implemented** (verifiziert 2026-07-01, Vollaudit)

## Relevante Dateien im Repo
- `plugins/theme-manager/ThemeManager.php`
- `plugins/theme-manager/ThemeController.php`
- `bundled-themes/material-pro/` (mitgeliefertes Beispiel-Theme)

## Funktionsumfang
- ZIP-Upload für komplette Custom-Themes (CSS/Twig)
- Tenant-spezifische Aktivierung
- Mindestens ein mitgeliefertes Theme ("material-pro")

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten — Theme-Upload muss gegen Path-Traversal/Zip-Slip abgesichert sein.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- Sicherheitsreview des ZIP-Upload-Pfads dokumentieren (Zip-Slip-Schutz verifizieren).

## Verlinkungen
- [[03-web/web-app]]
