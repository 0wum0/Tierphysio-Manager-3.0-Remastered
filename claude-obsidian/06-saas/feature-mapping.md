# Feature-Label Mapping – SaaS Landingpage

> Stand: Mai 2026  
> Implementiert in: `saas-platform/app/Services/FeatureLabelService.php`

## Architektur

Das Mapping wird zentral in `FeatureLabelService` gepflegt.  
Der Service stellt drei Twig-Filter bereit (registriert in `View.php`):

| Filter | Verwendung | Gibt zurück |
|---|---|---|
| `f\|feature_label` | `{{ f\|feature_label }}` | Deutschen Anzeigenamen |
| `f\|feature_icon` | `<i class="{{ f\|feature_icon }}">` | Bootstrap-Icon-Klasse |
| `f\|feature_group` | Gruppierung in Admin-UI | Kategorie-Name |

**WICHTIG**: Nur die Anzeigenamen ändern – nie die internen Keys.  
Die Keys entsprechen exakt `saas_feature_flags.feature_key` und `plans.features` (JSON).

---

## Feature-Gruppen

### Verwaltung
| Key | Anzeige |
|---|---|
| `patients` | Patienten- & Hundeverwaltung |
| `owners` | Besitzerverwaltung |
| `appointments` | Terminkalender |
| `calendar` | Kalender |
| `staff` | Mitarbeiterverwaltung |
| `waitlist` | Warteliste |
| `uploads` | Datei-Uploads |
| `templates` | Dokumentvorlagen |

### Kommunikation
| Key | Anzeige |
|---|---|
| `notifications` | Benachrichtigungen |
| `reminders` | Erinnerungen |
| `homework` | Hausaufgaben |
| `bulk_mail` | Serienmails |
| `patient_invite` | Besitzer-Einladungen |
| `patient_portal` | Besitzerportal |
| `patient_intake` | Digitale Aufnahmeformulare |

### Finanzen
| Key | Anzeige |
|---|---|
| `invoices` | Rechnungen |
| `tax_export` | Steuerexport |
| `expenses` | Ausgabenverwaltung |
| `dunning` | Mahnwesen |
| `analytics` | Statistiken & Analysen |
| `exports` | Datenexporte |
| `dogschool_invoicing` | Rechnungsverwaltung — Route: `/hundeschule/rechnungen` |
| `dogschool_datev_export` | DATEV Export — Route: `/hundeschule/steuerexport` (NICHT `/steuerexport`! Das ist `tax_export` vom tax-export-pro Plugin) |

### Therapie & Training
| Key | Anzeige |
|---|---|
| `befunde` | Befundsystem |
| `vet_report` | Tierarztberichte |
| `therapy_care` | TherapyCare System (TCP) |
| `dogschool_training_plans` | Trainingspläne |
| `dogschool_exercises` | Übungen & Aufgaben |
| `dogschool_homework` | Trainings-Hausaufgaben |
| `dogschool_progress` | Trainingsfortschritt |
| `dogschool_progress_tracking` | Fortschrittsverfolgung |
| `dogschool_reports` | Trainingsberichte |

### Portal & App
| Key | Anzeige |
|---|---|
| `mobile_api` | Mobile App Unterstützung |
| `google_calendar_sync` | Google Kalender Synchronisation |

### KI & Automatisierung
| Key | Anzeige |
|---|---|
| `ki_assistance` | KI-Unterstützung |

### Hundeschule
| Key | Anzeige |
|---|---|
| `dogschool_dashboard` | Hundeschul-Dashboard |
| `dogschool_courses` | Kursverwaltung |
| `dogschool_group_training` | Gruppentraining |
| `dogschool_attendance` | Teilnehmerverwaltung |
| `dogschool_waitlist` | Warteliste für Kurse |
| `dogschool_categories` | Kurskategorien |
| `dogschool_media` | Medienverwaltung |
| `dogschool_templates` | Kurs-Vorlagen |
| `dogschool_events` | Veranstaltungen |
| `dogschool_leads` | Interessentenverwaltung |
| `dogschool_consents` | Einverständniserklärungen |
| `dogschool_online_booking` | Online-Terminbuchung |
| `dogschool_packages` | Kurspakete & Mehrfachkarten |
| `dogschool_trainers` | Trainerprofile |
| `dogschool_trainer_management` | Trainerverwaltung |

---

## Erweiterung

Neue Feature-Keys in `FeatureLabelService::MAP` eintragen:
```php
'neuer_key' => ['Deutscher Name', 'bi-icon-name', 'Gruppe'],
```

Unbekannte Keys werden automatisch durch `humanize()` lesbar formatiert (snake_case → Title Case).

---

## Geänderte Dateien

| Datei | Änderung |
|---|---|
| `saas-platform/app/Services/FeatureLabelService.php` | Neu – zentrales Mapping |
| `saas-platform/app/Core/View.php` | `feature_label`, `feature_icon`, `feature_group` Filter |
| `saas-platform/templates/landing/index.twig` | Pricing-Section: `f\|feature_label`, `f\|feature_icon`, Plan-Badges |
| `saas-platform/templates/register/plans.twig` | Vollständig überarbeitet mit Badges & Mapping |
