<?php
/**
 * Google Calendar Sync – Konfiguration
 *
 * Kopiere diese Datei nach: storage/config/google.php
 * und trage deine Google API Zugangsdaten ein.
 *
 * Alternativ kannst du die Werte als Umgebungsvariablen setzen:
 *   GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI, GOOGLE_SYNC_CRON_SECRET
 *
 * Google Cloud Console: https://console.cloud.google.com/
 * 1. Projekt erstellen
 * 2. "Google Calendar API" aktivieren
 * 3. OAuth 2.0 Client-ID erstellen (Typ: Webanwendung)
 * 4. Autorisierte Weiterleitungs-URI hinzufügen:
 *    https://DEINE-DOMAIN.de/google-kalender/callback
 */

return [
    'client_id'     => 'DEINE_CLIENT_ID.apps.googleusercontent.com',
    'client_secret' => 'DEIN_CLIENT_SECRET',
    'redirect_uri'  => 'https://DEINE-DOMAIN.de/google-kalender/callback',
    'cron_secret'   => 'EIN_ZUFAELLIGER_SICHERER_TOKEN_FUER_CRON',
];
