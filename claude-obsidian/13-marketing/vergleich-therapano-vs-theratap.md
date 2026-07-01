# TheraPano vs. TheraTap.de — Vollständiger Vergleich

**Stand:** 2026-07-01, nach vollständigem Code-Audit (nicht nur gegen alte Doku-Annahmen).
TheraTap-Angaben stammen aus deren öffentlicher Website (Stand der letzten Abfrage).

## Fazit vorweg
TheraTap ist eine solide, fokussierte Einzelplatz-Software mit Stärken bei Abrechnung nach
GOT und mobiler Logistik. TheraPano ist strukturell breiter: eine Multi-Tenant-SaaS-Plattform
mit eigenem Besitzerportal, nativer App, vollwertigem Hundeschul-Geschäftsmodell und einem
in dieser Form am Markt nicht auffindbaren 3D-Schmerzanalyse-System.

---

## TheraPano — Stärken, die TheraTap laut eigener Website NICHT bietet

| Bereich | TheraPano | TheraTap (lt. Website) |
|---|---|---|
| **Befund-Visualisierung** | Echtes 3D-Modell (Hund/Katze/Pferd), frei rotierbar, 27–34 klickbare anatomisch benannte Muskelregionen, NRS + 10 Schmerzarten je Region | „Visuelle Befundung am Tiermodell" mit Freihand-Zeichnen — kein Hinweis auf 3D, Rotation oder benannte Muskelregionen |
| **Besitzerportal** | Vollständiges Portal mit WhatsApp-Style-Chat (Lesehäkchen, Medien, Lightbox) | Nur „Online-Kundenansicht", kein erkennbarer Chat |
| **Rechnungsdesign** | Logo-Upload, freie Farbwahl, Schriftart, individuelle Bilder je Dokumenttyp, Freitexte | Nicht erwähnt |
| **Steuerexport** | DATEV-Buchungsstapel, SKR03-Kontenrahmen, Kassenbuch, ZIP mit allen Rechnungen+Belegen+SHA-256-Manifest, Rechnungen UND Ausgaben kombiniert | Nicht erwähnt (nur Lexware-Office-Integration) |
| **Ausgaben-OCR** | Beleg fotografieren → Daten automatisch erkannt | Nicht erwähnt |
| **Mahnwesen** | Vollständiges mehrstufiges Verfahren (Erinnerung → 1./2./letzte Mahnung), automatische Stufenzählung, Gebühren, PDF pro Stufe, pro Tenant | Nicht erwähnt |
| **Native App** | Windows + Android, mit Offline-SQLite-Cache (14 Tage) + Auto-Sync | Nur Responsive-Web, keine native App |
| **Hundeschul-Modul** | Vollständiges Kursgeschäft: Kurse, Pakete, Anwesenheit, Trainerverwaltung, Leads, Reports, eigene Rechnungen, Online-Buchung | Zielgruppe erwähnt, aber kein dediziertes Kurssystem sichtbar |
| **E-Mail-Client** | Echter IMAP/SMTP-Client in der App (Mailbox-Plugin) | Nicht erwähnt |
| **Serienmails & Feiertags-Mailing** | Ja, inkl. automatischer Berechnung beweglicher Feiertage | Nicht erwähnt |
| **Self-Service-Onboarding** | Einladungslink (sofort) ODER öffentliches Formular MIT Freigabe-Workflow — beide Varianten parallel | Nicht erwähnt |
| **Custom-Themes** | ZIP-Upload für komplettes eigenes Layout pro Tenant | Nicht erwähnt |
| **Daten-Migration** | SQL-Dump-Import-Assistent für Wechsel von Konkurrenzsoftware | Nicht erwähnt |
| **Feedback-/Support-System** | Eingebaut, direkt aus der App | Nicht erwähnt |
| **Terminologie-Anpassung** | Automatisches Wechseln zwischen Praxis- und Hundeschul-Sprache | Nicht erwähnt |
| **SaaS-Architektur** | Multi-Tenant mit eigenem Betreiber-Admin, Plan-/Lizenzsystem | Einzelplatz-Software, kein Betreiber-Layer erkennbar |
| **Echte KI-Funktionen** (seit 2026-07-01) | Grok/Gemini-Integration: KI-Zusammenfassung im Therapiefortschritt, KI-Entwurf für Tierarztberichte, KI-Trainingsempfehlungen | Nicht erwähnt (außer Anamnese-Sprachtranskription, siehe unten) |

---

## TheraTap — Stärken, die TheraPano (aktuell) NICHT hat

Ehrlichkeit gehört dazu — diese Punkte sind bei TheraTap laut Website vorhanden und bei uns
aktuell nicht verifiziert bzw. nicht vorhanden:

| Bereich | TheraTap | TheraPano-Status |
|---|---|---|
| **GOT-Abrechnung** | Automatische Gebührenordnung für Tierärzte inkl. Notdienst-Faktoren (2×/3×/4×) | Nicht vorhanden — relevant primär für approbierte Tierärzte, weniger für unsere Kernzielgruppe (Therapeuten/Heilpraktiker/Trainer) |
| **Tourenplanung mit Routenoptimierung** | Ja, für mobile Therapeuten | Nicht vorhanden |
| **Vollständiger Offline-Modus** | Wirbt mit „vollständiger Funktionalität ohne Internet" | TheraPano hat einen SQLite-Cache mit 14-Tage-Sync in der Flutter-App — nicht 1:1 vergleichbar, evtl. weniger umfassend als TheraTaps Anspruch |
| **SMS-Erinnerungen** | Ja | TheraPano erinnert per E-Mail/Portal, keine SMS verifiziert |
| **Lexware-Office-Live-Integration** | Direkte Anbindung | TheraPano exportiert DATEV-/CSV-Dateien, die in Lexware & Co. importiert werden können — aber keine Live-API-Anbindung |
| **Stempelkarten (Kundenbindungssystem)** | Ja | Nicht vorhanden (Kurspakete/Mehrfachkarten decken das nur für Hundeschulen ab) |
| **KI-Sprachtranskription für Anamnese** | Beworben | TheraPano hat keine Sprachtranskription, aber seit 2026-07-01 echte KI (Grok/Gemini) für Fortschritts-Zusammenfassungen, Tierarztbericht-Entwürfe und Trainingsempfehlungen (siehe [[07-features/ai-integration]]) — andere Anwendungsfälle, kein 1:1-Vergleich |

---

## Einordnung für Vertrieb/Video

- Die stärksten, komplett abgesicherten Verkaufsargumente sind: **3D-Schmerzanalyse**,
  **Rechnungsdesign**, **Steuerberater-Export**, **Mahnwesen**, **Besitzerportal/Chat**,
  **native App**, **Hundeschul-Vollmodul**, **Daten-Migrationsassistent**.
- Bei **GOT-Abrechnung**, **Routenoptimierung** und **Lexware-Live-Integration** liegt TheraTap
  aktuell vorn — falls diese Punkte im Vertrieb häufig nachgefragt werden, empfiehlt sich eine
  Aufnahme in die Produkt-Roadmap (siehe `claude-obsidian/12-roadmap/roadmap.md`).
- Bei **KI-Funktionen** sollte weder TheraPano noch der Vergleich mit TheraTap mit KI-Versprechen
  werben, die (noch) nicht eingelöst werden — das betrifft aktuell primär die eigene Namensgebung
  „TherapyCare/TrainingCare AI".

## Quellen
- TheraPano: vollständiger Code-Audit 2026-07-01 (drei Runden Sub-Agent-Verifikation), siehe
  `claude-obsidian/07-features/*.md` und `claude-obsidian/00-start/open-items.md`.
- TheraTap: öffentliche Website theratap.de (Feature-, Preis- und Zielgruppenangaben).
