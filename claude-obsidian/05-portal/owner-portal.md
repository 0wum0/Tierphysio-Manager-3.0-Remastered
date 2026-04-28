# Owner Portal

## Beschreibung
Besitzerportal-Funktionen für Tierhalter inkl. Nachrichten, Befunde, Rechnungen, Termine.

## Zweck
Portal-Architektur als gemeinsame Wissensbasis für Web-Plugin und Mobile Screens.

## Relevante Dateien im Repo
- `plugins/owner-portal/ServiceProvider.php`
- `plugins/owner-portal/OwnerPortalController.php`
- `plugins/owner-portal/OwnerPortalBookingController.php`
- `plugins/owner-portal/templates/*.twig`
- `flutter_app/lib/screens/owner_portal/*`

## Datenfluss
Owner-Auth/Login → Portal-Controller/Repository → Portal-Templates + Mobile Portal-Screens.

## Wichtige Regeln
- Portal-Domain bleibt `portal.therapano.de` (nicht tenant-spezifisch).
- Nachrichten-/Booking-Flows API-kompatibel halten.

## Risiken
- Vermischung mit interner Praxis-User-Auth.
- Inkonsistente Darstellung zwischen Portal-Web und Flutter Portal-Screens.

## TODOs
- Portal-Sessionmodell und Tokenstrategie nachziehen.

## Verlinkungen
- [[07-features/whatsapp-style-chat]]
- [[07-features/terminbuchung]]
- [[08-billing/billing-and-stripe]]
