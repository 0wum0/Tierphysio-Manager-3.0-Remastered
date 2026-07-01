# TheraPano — Vollständige Stärken-Dokumentation

**Stand:** 2026-07-01 · Quelle: Vollaudit gegen echten Code (nicht gegen Marketing-Annahmen).
Zielgruppen: Tiertherapeuten, Tierheilpraktiker, Tiertrainer, Hundeschulen.

Jeder Punkt hier ist im Code verifiziert (Controller/Service/Migration benannt), keine Behauptung
ohne Beleg. Details je Feature stehen in `claude-obsidian/07-features/*.md`.

---

## 1. Klinische Dokumentation & Befund

- **2D-Anatomiemodell mit NRS-Schmerzskala**: interaktive Silhouette (Hund/Katze/Pferd), Freihand-Zeichnen, Markierungen, Schmerzskala 0–10, PDF-Export der Marker/Zeichnungen als strukturierte Zusammenfassung.
- **3D-Schmerzanalyse (Alleinstellungsmerkmal)**: echtes 3D-Modell direkt in der Patientenakte, frei rotierbar/zoombar, mit 27 klickbaren Muskelregionen beim Hund, 24 bei der Katze, 34 beim Pferd — jede anatomisch korrekt benannt (z. B. „M. longissimus dorsi"). Klick auf eine Region öffnet ein Schmerzformular: NRS 0–10, zehn Schmerzarten (Druckschmerz, Bewegungsschmerz, Ruheschmerz, Verspannung, Verhärtung, Triggerpunkt, Schwellung, Wärme, Schonhaltung, Unklar), Notizfeld. Alles wird gespeichert und bei jedem Öffnen wieder angezeigt.
- **Fortschrittssystem (TherapyCare Progress)**: sechs klinische Verlaufsmetriken (Gangbild, Beweglichkeit, Schmerzreaktion, Muskelspannung, Belastbarkeit, Allgemeinzustand), grafisch dargestellt über die Zeit.
- **Patienten-Timeline**: chronologische Verlaufsansicht mit gemischten Eintragstypen (Behandlung, Notiz), Medien-Anhängen inkl. Video, automatischer Benachrichtigung des Tierbesitzers im Portal bei neuem Behandlungseintrag.
- **Video-Feedback**: Vorher/Nachher-Videoaufnahmen direkt am Patienten hochladen und im Verlauf anzeigen.
- **Tierarztbericht-Editor**: Rich-Text-Editor (Quill) mit medizinischen Schnellvorlagen (Anschreiben, Befundbericht, Rücküberweisung, Verlaufsbericht, Hundetraining-Bericht) UND vollautomatisch generierte Auto-Berichte aus Patientendaten/Timeline — beides parallel nutzbar, PDF-Export.

## 2. Finanzen & Rechtssicherheit

- **Individuelles Rechnungsdesign**: eigenes Firmenlogo, freie Farbwahl (Sidebar-/Akzentfarbe u. a.), Schriftart und -größe, eigene Bilder je Dokumenttyp (Rechnung, Quittung, Barzahlung, Erinnerung, Mahnung), frei editierbare Intro-/Schluss-/Fußzeilentexte, konfigurierbarer Rechnungsnummernkreis, Wasserzeichen (z. B. „ENTWURF").
- **Steuerberater-fertiger Export**: DATEV-Buchungsstapel (Format 7/EXTF v510) direkt importierbar in DATEV Unternehmen Online, Lexware oder BuchhaltungsButler; SKR03-Kontenrahmen hinterlegt; Kassenbuch für Barzahlungen; ZIP-Komplettpaket mit allen Rechnungs-PDFs, allen Ausgabenbelegen, CSV-Journalen und SHA-256-Manifest zur Prüfung durch den Steuerberater. **Rechnungen und Ausgaben werden gemeinsam exportiert.**
- **Ausgabenverwaltung mit OCR**: Beleg fotografieren — Datum, Betrag, Steuersatz und Lieferant werden automatisch erkannt und vorausgefüllt, Kategorisierung nach Standard-Kategorien (Miete, Fortbildung, Marketing, Fahrtkosten u. a.).
- **GoBD-konformes Änderungsprotokoll**: Rechnungen werden nach Finalisierung unveränderlich, Stornierungen laufen über Gegenbuchungen statt Löschung, jede Änderung wird protokolliert.
- **Mehrstufiges Mahnwesen**: vollständiges deutsches Mahnverfahren — Zahlungserinnerung → 1. Mahnung → 2. Mahnung → letzte Mahnung, automatische Stufenzählung, konfigurierbare Mahngebühr, eigenes PDF pro Stufe im Praxis-Design, Überfälligkeits-Übersicht als Dashboard-Warnsystem. **Jede Praxis (jeder Tenant) kann dieses Mahnwesen eigenständig für ihre Tierhalter-Rechnungen nutzen** — es ist kein reines Interna für unsere eigene Abo-Abrechnung, sondern voll im Praxisalltag einsetzbar.
- **Cron ohne Server-Cronjob**: Hintergrundprozesse (Geburtstagsmails, Erinnerungen, Kalender-Sync) laufen über ein pixelbasiertes Trigger-System — funktioniert auch auf einfachem Shared-Hosting ohne Cron-Zugriff.

## 3. Kommunikation & Kundenbindung

- **Eigenes Besitzerportal** mit **WhatsApp-Style-Chat**: Sprechblasen, Lesehäkchen, Bild-/Video-Anhänge mit Lightbox, Rechtsklick-Formatierungsmenü, automatische Bildoptimierung.
- **Eingebauter E-Mail-Client (Mailbox-Plugin)**: Posteingang per IMAP/POP3 lesen, E-Mails per SMTP verfassen — direkt in der Praxis-App, kein externes Mail-Programm nötig.
- **Serienmails & Feiertags-Mailing**: Massen-E-Mails an gefilterte Besitzerlisten sowie automatisches Feiertags-Mailing inkl. beweglicher Feiertage (Ostern etc.).
- **Self-Service-Onboarding für Tierbesitzer**: entweder per Einladungslink mit sofortiger automatischer Anlage, oder über ein öffentliches Multi-Step-Anmeldeformular mit Admin-Freigabe-Workflow — je nach gewünschtem Kontrollgrad.
- **Versionierte Einwilligungsformulare (DSGVO)**: für Kursteilnahme und weitere Einverständnisse.
- **Öffentliche Terminanfrage/Online-Buchung**: ohne Login, mit Honeypot- und Rate-Limiting-Schutz gegen Spam.
- **Client-seitige Medienkompression**: Video-/Bilddateien werden bereits im Browser vor dem Upload komprimiert (ffmpeg.wasm), das spart Ladezeit und Speicherplatz.
- **Eingebautes Feedback-/Support-System**: Nutzer können direkt aus der App Fehler melden oder Fragen stellen, landet automatisch im Betreiber-Ticketsystem.

## 4. Hundeschulen & Tiertraining — eigenständiges Geschäftsmodell

- **Kurssystem**: Kurse, Kurskategorien, Kurspakete/Mehrfachkarten mit automatischer Guthaben-Verwaltung, Kurs-Enrollment.
- **Anwesenheitsverwaltung**: Matrix pro Kurstag (anwesend/entschuldigt/verspätet/vorzeitig gegangen/nicht erschienen) mit Notizfeld.
- **Trainerverwaltung**: Profile mit Bio, Spezialisierung, Avatar, eigenständigem Verfügbarkeitssystem nach Wochentag/Uhrzeit.
- **Interessentenverwaltung (Leads)** und **öffentliche Online-Buchung** für Kursanfragen mit automatischer Konvertierung zu Leads.
- **Business-Reports**: Kursauslastung, Anwesenheitsquote, Umsatz aus Enrollments/Paketverkäufen, Lead-Konversionsrate.
- **Eigenständiges Hundeschul-Rechnungsmodul**, automatisch aus Kurs-Enrollments und Paketverkäufen erzeugt.
- **Eigenständiges Kalender-Plugin** mit Warteliste-Ansicht und Stats-Dashboard.
- **Dynamische Terminologie**: Die App spricht automatisch die passende Sprache — „Patient" vs. „Hund", „Behandlung" vs. „Training", „Tierhalter" vs. „Halter" — je nachdem, ob eine Praxis oder eine Hundeschule eingeloggt ist, sowohl im Web als auch in der Flutter-App.

## 5. Plattform & Technik

- **Multi-Tenant-SaaS-Architektur**: echte Datenbank-Isolation pro Kunde (Präfix-basiert), eigenes Betreiber-Admin für Tenants, Pläne, Lizenzen, Abrechnung.
- **Custom-Themes per ZIP-Upload**: Praxen können ihr eigenes Layout/Design hochladen, ohne dass wir Code deployen müssen.
- **Native Flutter-App** für Android und Windows mit Offline-Modus (lokaler SQLite-Cache, 14 Tage, automatischer Sync bei Wiederverbindung).
- **Daten-Migrationsassistent**: Neue Kunden können ihre Daten aus einer anderen Software per SQL-Dump-Import übernehmen (automatische Tabellen-Prefixierung, Duplikat-Toleranz, automatische Admin-Einrichtung) — der Wechsel zu TheraPano beginnt nicht bei null.
- **Persönliches UI-Layout & Notification-Center**: jeder Nutzer kann sein Dashboard anpassen, ein zentrales Benachrichtigungs-Center bündelt Systemhinweise (z. B. Patienten-Geburtstage).

---

## Wichtig für die Vertriebs-/Video-Nutzung
Alle Punkte oben sind **verifiziert implementiert**, keine Wunschliste. Wo Einschränkungen bestehen
(z. B. Mahnwesen ist nutzergesteuert statt vollautomatisch, native Apps aktuell nur Windows/Android),
ist das hier bewusst nicht verschwiegen — für Marketing-Texte bitte trotzdem die ehrliche Einschränkung
im Hinterkopf behalten, um keine falschen Erwartungen zu wecken.
