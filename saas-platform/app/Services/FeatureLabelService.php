<?php

declare(strict_types=1);

namespace Saas\Services;

/**
 * Zentrales Mapping von internen Feature-Keys zu deutschen Anzeigenamen.
 *
 * WICHTIG: Nur Anzeigenamen ändern – niemals die Keys selbst.
 * Die Keys sind identisch mit der Spalte `saas_feature_flags.feature_key`
 * und den Einträgen in `plans.features` (JSON-Array).
 */
final class FeatureLabelService
{
    /**
     * Key → [label, icon, group]
     * icon  = Bootstrap-Icon-Klasse (bi-*)
     * group = Kategorie für visuelle Gruppierung
     */
    private const MAP = [
        /* ─── Kernverwaltung ─────────────────────────────────── */
        'patients'                  => ['Patienten- & Hundeverwaltung',       'bi-heart-pulse',           'Verwaltung'],
        'owners'                    => ['Besitzerverwaltung',                  'bi-person-lines-fill',     'Verwaltung'],
        'appointments'              => ['Terminkalender',                      'bi-calendar-check',        'Verwaltung'],
        'invoices'                  => ['Rechnungen',                          'bi-receipt',               'Finanzen'],
        'calendar'                  => ['Kalender',                            'bi-calendar3',             'Verwaltung'],
        'staff'                     => ['Mitarbeiterverwaltung',               'bi-people',                'Verwaltung'],
        'waitlist'                  => ['Warteliste',                          'bi-list-ol',               'Verwaltung'],

        /* ─── Dokumentation & Befunde ────────────────────────── */
        'befunde'                   => ['Befundsystem',                        'bi-file-earmark-medical',  'Therapie & Training'],
        'uploads'                   => ['Datei-Uploads',                       'bi-cloud-upload',          'Verwaltung'],
        'exports'                   => ['Datenexporte',                        'bi-box-arrow-up',          'Finanzen'],
        'templates'                 => ['Dokumentvorlagen',                    'bi-file-earmark-text',     'Verwaltung'],
        'vet_report'                => ['Tierarztberichte',                    'bi-file-earmark-check',    'Therapie & Training'],
        'therapy_care'              => ['TherapyCare System (TCP)',            'bi-activity',              'Therapie & Training'],

        /* ─── Kommunikation ──────────────────────────────────── */
        'notifications'             => ['Benachrichtigungen',                  'bi-bell',                  'Kommunikation'],
        'reminders'                 => ['Erinnerungen',                        'bi-alarm',                 'Kommunikation'],
        'homework'                  => ['Hausaufgaben',                        'bi-pencil-square',         'Kommunikation'],
        'bulk_mail'                 => ['Serienmails',                         'bi-envelope-paper',        'Kommunikation'],
        'patient_invite'            => ['Besitzer-Einladungen',                'bi-person-plus',           'Kommunikation'],
        'patient_portal'            => ['Besitzerportal',                      'bi-person-badge',          'Portal & App'],
        'patient_intake'            => ['Digitale Aufnahmeformulare',          'bi-clipboard2-check',      'Kommunikation'],

        /* ─── Finanzen ───────────────────────────────────────── */
        'tax_export'                => ['Steuerexport',                        'bi-bank',                  'Finanzen'],
        'expenses'                  => ['Ausgabenverwaltung',                  'bi-wallet2',               'Finanzen'],
        'dunning'                   => ['Mahnwesen',                           'bi-exclamation-triangle',  'Finanzen'],
        'analytics'                 => ['Statistiken & Analysen',              'bi-bar-chart-line',        'Finanzen'],

        /* ─── Portal & App ───────────────────────────────────── */
        'mobile_api'                => ['Mobile App Unterstützung',            'bi-phone',                 'Portal & App'],
        'google_calendar_sync'      => ['Google Kalender Synchronisation',     'bi-google',                'Portal & App'],

        /* ─── KI & Automatisierung ───────────────────────────── */
        'ki_assistance'             => ['KI-Unterstützung',                    'bi-stars',                 'KI & Automatisierung'],

        /* ─── Hundeschule – Kern ─────────────────────────────── */
        'dogschool_dashboard'       => ['Hundeschul-Dashboard',                'bi-speedometer2',          'Hundeschule'],
        'dogschool_courses'         => ['Kursverwaltung',                      'bi-collection',            'Hundeschule'],
        'dogschool_group_training'  => ['Gruppentraining',                     'bi-people-fill',           'Hundeschule'],
        'dogschool_attendance'      => ['Teilnehmerverwaltung',                'bi-person-check',          'Hundeschule'],
        'dogschool_waitlist'        => ['Warteliste für Kurse',                'bi-list-stars',            'Hundeschule'],
        'dogschool_categories'      => ['Kurskategorien',                      'bi-tags',                  'Hundeschule'],
        'dogschool_media'           => ['Medienverwaltung',                    'bi-images',                'Hundeschule'],
        'dogschool_templates'       => ['Kurs-Vorlagen',                       'bi-layout-text-window',    'Hundeschule'],
        'dogschool_events'          => ['Veranstaltungen',                     'bi-calendar-event',        'Hundeschule'],

        /* ─── Hundeschule – Kommunikation ────────────────────── */
        'dogschool_leads'           => ['Interessentenverwaltung',             'bi-person-exclamation',    'Hundeschule'],
        'dogschool_consents'        => ['Einverständniserklärungen',           'bi-file-earmark-lock',     'Hundeschule'],
        'dogschool_online_booking'  => ['Online-Terminbuchung',                'bi-calendar-plus',         'Hundeschule'],

        /* ─── Hundeschule – Training ─────────────────────────── */
        'dogschool_training_plans'  => ['Trainingspläne',                      'bi-journal-bookmark',      'Therapie & Training'],
        'dogschool_exercises'       => ['Übungen & Aufgaben',                  'bi-clipboard-check',       'Therapie & Training'],
        'dogschool_homework'        => ['Trainings-Hausaufgaben',              'bi-pencil',                'Therapie & Training'],
        'dogschool_progress'        => ['Trainingsfortschritt',                'bi-graph-up',              'Therapie & Training'],
        'dogschool_progress_tracking' => ['Fortschrittsverfolgung',            'bi-activity',              'Therapie & Training'],

        /* ─── Hundeschule – Finanzen / Reports ───────────────── */
        'dogschool_invoicing'       => ['Rechnungsverwaltung',                 'bi-receipt-cutoff',        'Finanzen'],
        'dogschool_datev_export'    => ['DATEV Export',                        'bi-file-earmark-spreadsheet', 'Finanzen'],
        'dogschool_reports'         => ['Trainingsberichte',                   'bi-file-bar-graph',        'Therapie & Training'],

        /* ─── Hundeschule – Organisation ─────────────────────── */
        'dogschool_packages'        => ['Kurspakete & Mehrfachkarten',         'bi-box-seam',              'Hundeschule'],
        'dogschool_trainers'        => ['Trainerprofile',                      'bi-person-badge',          'Hundeschule'],
        'dogschool_trainer_management' => ['Trainerverwaltung',                'bi-people-fill',           'Hundeschule'],
    ];

    /** Gibt den deutschen Anzeigenamen zurück. Unbekannte Keys werden lesbar formatiert. */
    public static function label(string $key): string
    {
        return self::MAP[$key][0] ?? self::humanize($key);
    }

    /** Bootstrap-Icon-Klasse für einen Feature-Key. */
    public static function icon(string $key): string
    {
        return self::MAP[$key][1] ?? 'bi-check-circle';
    }

    /** Kategoriegruppe für visuelle Gruppierung. */
    public static function group(string $key): string
    {
        return self::MAP[$key][2] ?? 'Sonstiges';
    }

    /** Komplettes Mapping zurückgeben (z.B. für Admin-UIs). */
    public static function all(): array
    {
        return self::MAP;
    }

    /** Fallback: snake_case → "Snake Case" */
    private static function humanize(string $key): string
    {
        return ucwords(str_replace(['_', 'dogschool '], [' ', 'Hundeschule: '], $key));
    }
}
