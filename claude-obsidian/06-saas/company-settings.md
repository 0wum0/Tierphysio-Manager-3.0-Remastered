# Company Settings (SaaS-Platform)

## Überblick
Zentrale Firmendaten des Plattformbetreibers.
Gespeichert in `saas_settings` Tabelle (key/value), Gruppe: `company`.

## Admin-URL
`https://therapano.de/admin/settings?tab=company`

## Verfügbare Felder

| Key | Label | Verwendung |
|---|---|---|
| `company_name` | Firmenname | Impressum, Rechnungen, E-Mails |
| `company_owner` | Inhaber / Verantwortlicher | Impressum |
| `company_email` | E-Mail | Impressum, Mail-From-Fallback |
| `company_address` | Straße + Hausnummer | Impressum, Rechnungen |
| `company_zip` | PLZ | Impressum, Rechnungen |
| `company_city` | Stadt | Impressum, Rechnungen |
| `company_country` | Land | Impressum (Default: Deutschland) |
| `company_phone` | Telefon | Impressum |
| `company_website` | Website | Impressum |
| `tax_id` | Steuernummer | Impressum, Rechnungen |
| `vat_id` | USt-IdNr. | Impressum, Rechnungen |

## Zugriff (PHP)

### In Controllers (mit DI)
```php
// SettingsRepository per Konstruktor-Injection
$s = $this->settingsRepo->allFlat();
$companyName = $s['company_name'] ?? '';
```

### Direkt (ohne DI)
```php
$app = \Saas\Core\Application::getInstance();
$s = $app->getContainer()->get(\Saas\Repositories\SettingsRepository::class)->allFlat();
```

## Verwendung im Impressum

`LegalController::impressum()` liest alle Company-Keys via `settingsRepo->allFlat()`
und übergibt sie als `company`-Array an `legal/impressum.twig`.

```
Admin: /admin/settings?tab=company
         ↓ speichert in saas_settings
SettingsRepository::allFlat()
         ↓
LegalController::impressum()
         ↓ company.* Array
legal/impressum.twig
         ↓
https://therapano.de/impressum
```

## Verwendung in Rechnungen

`SaasInvoiceController` liest `company_name`, `company_address`, `company_city`,
`company_zip` direkt aus `saas_settings` für PDF-Generierung.

## Sicherheit
- Settings sind **read-only** im öffentlichen Impressum
- Kein Login für `/impressum`
- Nur ausgewählte Keys werden ans Template übergeben
- Kein `$_POST`-Zugriff oder Schreibzugriff im `view()`

## Controller-Allowed-Keys
`SettingsController::update()` erlaubt für Tab `company`:
```php
['company_name','company_owner','company_email','company_address',
 'company_zip','company_city','company_country','company_phone',
 'company_website','tax_id','vat_id']
```

## Tabellen-Schema
`saas_settings`: `key` (VARCHAR UNIQUE), `value` (TEXT), `group` (VARCHAR), `updated_at`
