# Owner Portal

## Beschreibung
Besitzerportal für Tierhalter inkl. Nachrichten, Befunde, Rechnungen, Termine, Hausaufgaben.
Web-Portal (session-basiert) + Flutter Mobile App (token-basiert, seit Commit 0133cb9).

## Auth-Architektur (Commit 0133cb9)

### Web-Portal (session-basiert)
- `POST /portal/login` → Session-Cookie → `owner_portal_user_id` in Session
- Controller-intern: `requireOwnerAuth()` prüft Session

### Flutter-App (token-basiert, NEU)
- `POST /api/portal/mobile-login` → JSON `{token, email, name, owner_id, practice_type, ...}`
- Token wird in `{PREFIX}owner_portal_users.mobile_token` gespeichert (90 Tage gültig)
- Alle `/api/portal/mobile/*` Endpoints: `Authorization: Bearer {token}`
- `OwnerAuthController::requireMobileAuth()` — sucht Token in allen `*_owner_portal_users` Tabellen
- `POST /api/portal/mobile-logout` — löscht Token aus DB

### Migration 068
- `mobile_token VARCHAR(64) NULL` und `mobile_token_expires DATETIME NULL` auf `owner_portal_users`

## Flutter Screens (alle rewritten, Commit 0133cb9)
- `owner_portal_login_screen.dart` — animated warm login
- `owner_portal_dashboard_screen.dart` — shimmer, stat cards, animated menu
- `owner_portal_pets_screen.dart` — hero cards, modal pet detail
- `owner_portal_appointments_screen.dart` — animated cards, status badges
- `owner_portal_invoices_screen.dart` — invoice cards, PDF download
- `owner_portal_messages_screen.dart` — thread list, FAB new thread
- `owner_portal_thread_screen.dart` — WhatsApp-style chat bubbles (NEU)
- `owner_portal_befunde_screen.dart` — befund cards, PDF button
- `owner_portal_homework_screen.dart` — exercise cards, video/PDF links (NEU)

## API Endpoints (Flutter → Backend)
| Endpoint | Methode | Beschreibung |
|---|---|---|
| `/api/portal/mobile-login` | POST | Login, gibt Token zurück |
| `/api/portal/mobile-logout` | POST | Token löschen |
| `/api/portal/mobile/dashboard` | GET | Stats: Tiere, Rechnungen, Termine, Nachrichten |
| `/api/portal/mobile/tiere` | GET | Tierliste des Besitzers |
| `/api/portal/mobile/tiere/{id}` | GET | Tierdetail |
| `/api/portal/mobile/termine` | GET | Terminliste |
| `/api/portal/mobile/rechnungen` | GET | Rechnungsliste |
| `/api/portal/mobile/rechnungen/{id}/pdf` | GET | PDF-URL |
| `/api/portal/mobile/befunde` | GET | Befundbögen |
| `/api/portal/mobile/befunde/{id}/pdf` | GET | PDF-URL |
| `/api/portal/mobile/hausaufgaben` | GET | Hausaufgabenpläne |
| `/api/portal/mobile/nachrichten` | GET | Thread-Liste |
| `/api/portal/mobile/nachrichten/ungelesen` | GET | Ungelesene Nachrichten count |
| `/api/portal/mobile/nachrichten/{id}` | GET | Thread + Nachrichten |
| `/api/portal/mobile/nachrichten/{id}/antworten` | POST | Antwort senden |
| `/api/portal/mobile/nachrichten/neu` | POST | Neuen Thread erstellen |
| `/api/portal/mobile/profil` | GET | Profilinfos |

## Relevante Dateien
- `plugins/owner-portal/OwnerAuthController.php` — mobileLogin/mobileLogout/requireMobileAuth
- `plugins/owner-portal/OwnerPortalMobileController.php` — alle API-Datenpunkte (NEU)
- `plugins/owner-portal/ServiceProvider.php` — Route-Registrierung
- `plugins/owner-portal/OwnerPortalRepository.php` — DB-Zugriff
- `flutter_app/lib/services/owner_portal_auth_service.dart` — Token-Persistenz, Login/Logout
- `flutter_app/lib/services/api_service.dart` — portalGet/portalPost/portalLogin*
- `flutter_app/lib/screens/owner_portal/*` — alle Portal-Screens
- `saas-platform/migrations/068_portal_mobile_token.sql`

## Wichtige Regeln
- Portal-Auth-System KOMPLETT getrennt von Praxis-App-Auth (kein Token-Sharing)
- Mobile-Token in `owner_portal_users.mobile_token` — NICHT in `api_tokens`
- `OwnerPortalAuthService` in main.dart als Provider registriert — nicht vergessen
- Portal-Domain: `app.therapano.de` (mobile API unter app.therapano.de/api/portal/*)
- Session-Auth (Web) und Token-Auth (Mobile) koexistieren — kein Umbau der Web-Auth

## TODOs
- Profil-Passwort-Änderung Backend-Endpoint implementieren
- Tierfoto-Upload via API ergänzen

## Verlinkungen
- [[07-features/whatsapp-style-chat]]
- [[07-features/terminbuchung]]
- [[02-api/mobile-api]]
