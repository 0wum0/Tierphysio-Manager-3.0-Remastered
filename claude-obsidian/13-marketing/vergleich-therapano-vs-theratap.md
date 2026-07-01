# TheraPano vs. TheraTap.de — Vollständiger Vergleich

**Stand:** 2026-07-01, zweite Fassung nach Tiefenrecherche.
Erste Fassung basierte auf **einem** WebFetch-Aufruf auf die TheraTap-Startseite — das war zu
oberflächlich (kleine Zusammenfassungsmodelle kürzen Inhalte). Diese Fassung basiert auf **14
tatsächlich abgerufenen Unterseiten**: alle 12 `/funktionen/*`-Detailseiten, `/preise/` und
`/mein-tier/`. Trotzdem gilt: das ist Website-Recherche, keine Code-Prüfung von TheraTap (dazu
haben wir keinen Zugriff) — TheraTap-Angaben sind Marketing-Aussagen der Anbieter-Seite, nicht
verifizierter Code wie bei TheraPano.

## Fazit vorweg (korrigiert)
TheraTap ist deutlich tiefer ausgebaut als die erste Analyse zeigte — insbesondere bei
GOT-Abrechnung, Tourenplanung (echte Google-Routes-API), Praxis-Analytics (Heatmaps,
Geo-Mapping) und einer eigenen KI-Seite (Sprache-zu-Formular, automatische Rechnungserstellung
aus Terminen). TheraPano bleibt trotzdem strukturell breiter aufgestellt (SaaS-Plattform,
eigenes Besitzerportal mit Chat, echtes 3D statt 2D-Anatomie) — aber der Abstand ist in einigen
Bereichen kleiner als zuerst dargestellt, und in anderen (Analytics, Routenplanung,
GOT-Tiefe) liegt TheraTap klar vorn.

---

## Wichtigste Korrektur: TheraTap hat eine eigene KI-Funktionsseite

`/funktionen/ki-funktionen/` listet vier KI-Funktionen (Sprachfunktion als kostenpflichtiges
Add-on **€7,90/Monat**, nicht in allen Tarifen automatisch enthalten):

1. **Sprache-zu-Struktur bei Befunden**: gesprochene Befunde werden automatisch kategorisiert
   einsortiert (z. B. Bewegungsanalyse, Palpation, Verhalten)
2. **Sprache-zu-Formular**: Verträge, Futterpläne, Aufklärungsbögen per Sprache oder Audio-Upload
   ausfüllen
3. **Automatische Rechnungserstellung**: KI durchsucht Termine/Behandlungen/Notizen seit der
   letzten Rechnung und leitet Rechnungspositionen automatisch ab
4. **KI-Zusammenfassung** von Anamnese/Befunden zu präzisem Text

**Einordnung:** Das ist eine andere KI-Anwendung als unsere (Grok/Gemini-Integration seit
2026-07-01, siehe [[07-features/ai-integration]]): TheraTap fokussiert auf **Spracheingabe →
strukturierte Felder** und **automatische Rechnungsableitung aus Terminen**. TheraPano fokussiert
auf **Zusammenfassung/Empfehlung aus bestehenden Verlaufsdaten** (Therapiefortschritt,
Tierarztbericht-Entwurf, Trainingsempfehlung). Beides sind legitime, unterschiedliche
Anwendungsfälle — kein 1:1-Gewinner. TheraTaps automatische KI-Rechnungserstellung aus Terminen
haben wir nicht.

---

## TheraPano — Stärken, die TheraTap laut Website NICHT bietet

| Bereich | TheraPano | TheraTap (lt. Website) |
|---|---|---|
| **Befund-Visualisierung: 2D vs. 3D** | Echtes 3D-Modell (Hund/Katze/Pferd), frei **rotierbar**, 27–34 klickbare anatomisch benannte Muskelregionen, NRS-Schmerzskala + 10 Schmerzarten je Region | **Explizit 2D**, nicht rotierbar. Dafür mit „anatomischen Ebenen" (Pferd 10, Hund 8, Katze 3 Ebenen) und 4 Ansichten (links/rechts/vorne/hinten) — mehr Ebenen-Tiefe als unser aktuelles 2D-System, aber kein 3D und keine erwähnte Schmerzskala |
| **Hundeschul-Kurssystem** | Vollständiges Kursgeschäft: Kurse, Kurskategorien, **Gruppentraining**, Kurspakete, Anwesenheit, Trainerverwaltung, Leads, Reports, eigene Rechnungen | TheraTap bestätigt in der eigenen FAQ explizit: **nur 1:1-Training**, „Kursverwaltung und Gruppentraining werden nicht unterstützt" — klarer, jetzt doppelt verifizierter Vorteil |
| **Besitzerportal** | WhatsApp-Style-Chat (Lesehäkchen, Medien, Lightbox) direkt mit der Praxis | Kein Chat-Feature erwähnt; „Mein Tier" ist eine separate Consumer-App ohne Chat-Funktion zur Praxis |
| **Rechnungsdesign** | Logo-Upload, freie Farbwahl, Schriftart, individuelle Bilder je Dokumenttyp, Freitexte | Nicht erwähnt |
| **Mahnwesen** | Vollständiges mehrstufiges Verfahren (Erinnerung → 1./2./letzte Mahnung), automatische Stufenzählung, Gebühren, PDF pro Stufe, pro Tenant | Nur „Status-Tracking (offen/bezahlt/überfällig)" erwähnt, kein Mahnstufen-Workflow |
| **Native App** | Windows + Android, Offline-SQLite-Cache (14 Tage) + Auto-Sync | Web + mobile-optimiert, echter Offline-Modus für Befunderstellung ebenfalls vorhanden (siehe Korrektur unten) |
| **E-Mail-Client** | Echter IMAP/SMTP-Client in der App | Nicht erwähnt |
| **Serienmails & Feiertags-Mailing** | Ja, inkl. automatischer Berechnung beweglicher Feiertage | Nicht erwähnt |
| **Self-Service-Onboarding (2 Varianten)** | Einladungslink (sofort) ODER öffentliches Formular MIT Freigabe-Workflow | Erstkontaktformular ähnlich (E-Mail/SMS/WhatsApp, kein Login), aber nur eine Variante ohne Freigabe-Workflow |
| **Custom-Themes** | ZIP-Upload für komplettes eigenes Layout pro Tenant | Nur „Custom Client Portal Branding" im Praxis-Tarif, kein vollständiges Theme-Upload erwähnt |
| **Daten-Migration** | SQL-Dump-Import-Assistent für Wechsel von Konkurrenzsoftware | Nicht erwähnt |
| **SaaS-Architektur** | Multi-Tenant mit eigenem Betreiber-Admin, Plan-/Lizenzsystem | Einzelplatz-Software (Tarif-Modell), kein Betreiber-Layer erkennbar |
| **Steuerexport-Tiefe** | DATEV-Buchungsstapel, SKR03-Kontenrahmen, Kassenbuch, ZIP mit allen Rechnungen+Belegen+SHA-256-Manifest, Rechnungen UND Ausgaben kombiniert | Erwähnt DATEV/Lexoffice/XRechnung-3.0-Export, aber ohne die von uns genannte Tiefe (Kassenbuch, GoBD-Manifest, kombinierter Beleg-Export) — nicht abschließend vergleichbar, da TheraTap-Doku hierzu knapper ist |

---

## TheraTap — Stärken, die TheraPano NICHT hat (deutlich erweitert nach Tiefenrecherche)

| Bereich | TheraTap | TheraPano-Status |
|---|---|---|
| **GOT-Abrechnung (sehr tief)** | Komplette GOT Teil A/B/C integriert, Volltextsuche über Leistungen, tierartgefiltert, 1-Klick-Multiplikator (1×/2×/3×), Notdienst-Modus mit 2×/3×/4× + konfigurierbarem Zuschlag (Standard 50€ netto), Kilometerpauschale oder Wegegeld-Konfiguration, **Preis-Snapshots bleiben nach GOT-Änderungen erhalten**, DATEV/Lexoffice/XRechnung-3.0-kompatibel | **Nicht vorhanden.** Relevant primär für approbierte Tierärzte — für unsere Kernzielgruppe (Therapeuten/Heilpraktiker/Trainer) rechtlich meist nicht anwendbar, aber ein echter Tiefen-Unterschied für die Tierarzt-Zielgruppe |
| **Tourenplanung (mit echter Routenoptimierung)** | Automatischer Import aller Tagestermine, **Google Routes Matrix API** für echte Fahrzeiten, KPIs (Hausbesuche/Gesamtstrecke/Fahrzeit) in der Kopfzeile, Ein-Klick-Öffnung der Route in Google Maps, Team-Filter pro Therapeut, automatische Fehlererkennung bei fehlenden Adressen | **Nicht vorhanden.** Kein Routing/Kartenintegration im Code gefunden |
| **Praxis-Analytics/Auswertung** | Umsatz, Zahlungsdisziplin, Terminauslastung, Kundenbindung, Trendvergleich zu Vorperioden, **Heatmap für Terminbuchungsdichte**, **geografische Kartendarstellung** des Einzugsgebiets, Team-Vergleichsmodus (Termine/Stunden/Neukunden/Umsatz je Therapeut) | Wir haben Dashboard-Reports (Umsatz, Fortschritt), aber keine Heatmaps, keine Geo-Kartendarstellung, keinen Team-Vergleichsmodus — hier liegt TheraTap klar vorn |
| **Online-Terminbuchung (mit Kalender-Sync + Widget)** | Öffentlicher Echtzeit-Buchungskalender, **Google-Kalender- UND Outlook-Sync**, einbettbares Website-Widget, QR-Code-Teilen, Multi-Therapeut/Raum/Geräte-Verwaltung im Praxis-Tarif, Selbst-Stornierung durch Kunden, automatische Erinnerungen (sofort + 24h vorher) | Wir haben Online-Buchung ([[07-features/online-booking]]) mit Honeypot/Rate-Limiting, aber ohne bestätigten Kalender-Sync oder einbettbares Widget — TheraTap ist hier ausgereifter |
| **Erstkontaktformular** | Verteilung per E-Mail/SMS/WhatsApp, kein Login nötig, automatische Kunden-/Tierprofil-Anlage | Vergleichbar zu unserem [[07-features/patient-intake]] / [[07-features/patient-invite]], aber TheraTap kombiniert Anamnese-Erfassung direkt im Formular |
| **Stempelkarten (universell)** | Beliebige Kartengrößen, eigene Icons/Farben, **automatische Stempelvergabe bei Terminabschluss** (nicht nur manuell), PDF-Export im Scheckkartenformat, Duplikat-Sicherung | Wir haben das nur für Hundeschul-Pakete (Kurspakete/Mehrfachkarten), nicht als universelles, automatisches Stempelsystem für jede Terminart |
| **Rechnungen: E-Rechnung + Multi-Land** | XRechnung-3.0-konforme E-Rechnungen für öffentliche Auftraggeber, QR-Code-Zahlung auf Rechnungen, **explizite Unterstützung für Deutschland, Schweiz, Österreich, Dänemark, Niederlande** mit länderspezifischen Anforderungen | TheraPano ist auf Deutschland/DATEV/GoBD fokussiert — keine E-Rechnung (XRechnung), kein Multi-Land-Support verifiziert |
| **"Mein Tier" — praxisübergreifende Consumer-App** | Eigenständige, kostenlose Tierhalter-App (unabhängig von einer einzelnen Praxis): digitales Gesundheitsbuch, Termine bei **beliebigen** TheraTap-Praxen buchen, automatische Übernahme von Befunden/Plänen jeder nutzenden Praxis, Premium-KI-Assistent „Malina" (2,99€/Monat) für Gesundheitsfragen 24/7 | TheraPano hat ein Besitzerportal **pro Praxis** (Tenant-gebunden), keine praxisübergreifende Consumer-App mit Netzwerkeffekt |
| **KI-Sprachfunktion für Diktat** | Sprache/Audio → strukturierte Formularfelder (Anamnese, Verträge, Futterpläne), als Add-on 7,90€/Monat | Nicht vorhanden — unsere KI (Grok/Gemini) fasst zusammen/empfiehlt, transkribiert aber keine Sprache |
| **Automatische KI-Rechnungserstellung** | KI leitet Rechnungspositionen automatisch aus Terminen/Behandlungen/Notizen seit letzter Rechnung ab | Nicht vorhanden |
| **Vollständiger Offline-Modus** | Befunderstellung ganz ohne Internet, automatische Sync bei Wiederverbindung — für alle Nutzer, nicht nur App | TheraPano: SQLite-Cache (14 Tage) nur in der Flutter-App, nicht in der Web-App — enger gefasst |
| **SMS-Erinnerungen** | Ja (Terminerinnerungen, Online-Buchung) | Nur E-Mail/Portal-Erinnerungen, kein SMS-Versand verifiziert |
| **Zwei-Faktor-Authentifizierung** | Dediziert dokumentiert (`/docs/team-verwaltung/zwei-faktor-authentifizierung/`) | Nicht verifiziert vorhanden |

---

## Preise (TheraTap, zur Einordnung)
Einsteiger / Profi / Praxis / Individuell — gestaffelt nach Tierakten-Limit (25 → unbegrenzt),
Nutzerzahl (1 → 2+) und Speicher (500 MB → 15 GB). GOT-Abrechnung und Tourenplanung sind erst
ab **Profi**-Tarif enthalten, Team-/Ressourcenplanung erst ab **Praxis**-Tarif (2+ Nutzer).
KI-Sprachfunktion ist ein Add-on (7,90€/Monat) in jedem Tarif buchbar. 12% Rabatt bei
Jahreszahlung, 7 Tage kostenlose Testphase ohne Kreditkarte.

---

## Einordnung für Vertrieb/Video (aktualisiert)

**Weiterhin robuste Verkaufsargumente:**
- **3D-Schmerzanalyse** (TheraTap ist explizit 2D)
- **Hundeschul-Kurssystem mit Gruppentraining** (TheraTap unterstützt laut eigener FAQ nur 1:1)
- **Besitzerportal mit Chat**, **native App**, **SaaS-Architektur**, **Rechnungsdesign**,
  **Mahnwesen**, **Daten-Migrationsassistent**

**Neu identifizierte Lücken — vor Vertriebsgesprächen einplanen:**
- Keine Routenplanung/Kartenintegration (TheraTap: Google Routes API)
- Keine Praxis-Analytics mit Heatmap/Geo-Karte/Team-Vergleich
- Keine GOT-Abrechnung (relevant nur für Tierarzt-Zielgruppe)
- Kein Kalender-Sync/einbettbares Widget bei der Online-Buchung
- Kein universelles, terminbasiertes Stempelkartensystem
- Keine E-Rechnung (XRechnung) / kein Multi-Land-Support

**KI-Vergleich:** Beide Seiten haben jetzt echte KI, aber für unterschiedliche Zwecke — TheraTap
für Diktat/Rechnungsautomatik, TheraPano für Zusammenfassung/Empfehlung. Kein Overselling in
beide Richtungen.

## Quellen & Methodik
- TheraPano: vollständiger Code-Audit 2026-07-01 (mehrere Runden Sub-Agent-Verifikation), siehe
  `claude-obsidian/07-features/*.md` und `claude-obsidian/00-start/open-items.md`.
- TheraTap: 14 abgerufene Seiten (Startseite + 12 `/funktionen/*`-Detailseiten + `/preise/` +
  `/mein-tier/` + `/fuer-reitschulen-hundeschulen/`), Stand 2026-07-01. Das ist Website-Recherche
  (Marketing-Aussagen des Anbieters), keine Code-Prüfung — im Gegensatz zu den TheraPano-Angaben,
  die gegen echten Code verifiziert sind. Nicht abgerufen wurden u. a. `/blog/`, vollständige
  `/docs/*`-Unterseiten, `/kooperation/` — dort könnten weitere Details stehen.
