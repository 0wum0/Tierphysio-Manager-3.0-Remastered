<?php
/**
 * Therapano – Zentrale Google OAuth Konfiguration (SaaS-Betreiber)
 *
 * Diese Datei enthaelt die zentralen Google API Credentials fuer alle Tenants.
 * NICHT in die Versionskontrolle einchecken (.gitignore).
 *
 * Google Cloud Console: https://console.cloud.google.com/
 * 1. Projekt erstellen oder vorhandenes oeffnen
 * 2. "Google Calendar API" aktivieren
 * 3. OAuth 2.0 Client-ID erstellen (Typ: Webanwendung)
 * 4. Autorisierte Weiterleitungs-URI eintragen:
 *    https://app.therapano.de/google-kalender/callback
 * 5. Werte unten eintragen und diese Datei speichern
 */

return [
    'client_id'     => '',  // DEINE_CLIENT_ID.apps.googleusercontent.com
    'client_secret' => '',  // DEIN_CLIENT_SECRET
    'redirect_uri'  => 'https://app.therapano.de/google-kalender/callback',
    'cron_secret'   => '',  // Zufaelliger sicherer Token fuer den Cron-Endpunkt
];
