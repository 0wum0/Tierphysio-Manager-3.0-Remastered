<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * DogschoolSeedService
 *
 * Pflegt Standard-Inhalte für Hundeschul-Tenants ein:
 *   - Übungen-Katalog (30+ Standard-Übungen)
 *   - Kursarten-Katalog (14 Kategorien mit Farben/Icons)
 *   - Einwilligungs-Vorlagen (Teilnahme, Foto/Video, Haftung, Impfschutz)
 *   - Trainingsplan-Vorlagen (Welpen-8-Wochen, Junghunde-Basis, Problemhunde)
 *
 * Alles idempotent:
 *   - Prüft auf slug-/name-Eindeutigkeit bevor insertet wird
 *   - Kann beliebig oft aufgerufen werden ohne Duplikate
 *   - Überschreibt KEINE bestehenden Daten mit is_system=0
 *   - System-Seeds (is_system=1) werden bei Updates aktualisiert
 */
class DogschoolSeedService
{
    /* Marker für seed-Version — bei Änderungen am Katalog erhöhen, damit
     * bestehende Tenants beim nächsten Zugriff neue Inhalte bekommen */
    private const SEED_VERSION = 2;

    private static array $seededPrefixes = [];

    public function __construct(
        private readonly Database $db,
        private readonly DogschoolSchemaService $schema,
    ) {}

    /**
     * Seed ausführen — idempotent, per-request gecacht.
     * Prüft zusätzlich ein `dogschool_seed_version`-Setting damit
     * bereits geseedete Tenants nicht bei jedem Request neu iterieren.
     */
    public function seed(): void
    {
        $prefix = $this->db->prefix('');
        if (isset(self::$seededPrefixes[$prefix])) {
            return;
        }

        /* Schema sicherstellen, falls Seed direkt aufgerufen wird */
        $this->schema->ensure();

        $storedVersion = $this->getStoredSeedVersion();
        if ($storedVersion >= self::SEED_VERSION) {
            self::$seededPrefixes[$prefix] = true;
            return;
        }

        try {
            $this->seedCourseCategories();
            $this->seedExercises();
            $this->seedConsents();
            $this->seedTrainingPlans();

            $this->setStoredSeedVersion(self::SEED_VERSION);
            self::$seededPrefixes[$prefix] = true;
        } catch (\Throwable $e) {
            error_log('[DogschoolSeedService] seed() failed: ' . $e->getMessage());
        }
    }

    /* ═══════════════════════ Kursarten ═══════════════════════ */
    private function seedCourseCategories(): void
    {
        $t = $this->db->prefix('dogschool_course_categories');
        $categories = [
            ['welpen',            'Welpenkurs',              '🐶', '#fbbf24', 60, 6, 15000, 'Sozialisierung & Basics für 8-16 Wochen'],
            ['junghunde',         'Junghundekurs',           '🐕', '#60a5fa', 60, 8, 18000, '4-12 Monate — Grundkommandos & Leinenführung'],
            ['alltag',            'Alltagstraining',         '🦮', '#22c55e', 60, 8, 18000, 'Stressfreier Alltag mit Hund'],
            ['rueckruf',          'Rückruftraining',         '📢', '#a78bfa', 60, 6, 18000, 'Zuverlässiger Rückruf in allen Situationen'],
            ['leinenfuehrigkeit', 'Leinenführigkeit',        '🔗', '#f97316', 60, 8, 18000, 'Entspanntes Gehen an lockerer Leine'],
            ['begegnung',         'Begegnungstraining',      '👥', '#ec4899', 90, 4, 25000, 'Ruhige Hundebegegnungen'],
            ['social_walk',       'Social Walk',             '🚶', '#14b8a6', 90, 10, 12000, 'Gemeinsamer Spaziergang in der Gruppe'],
            ['problem',           'Problemhundetraining',    '⚠️', '#ef4444', 60, 1, 45000, 'Einzeltraining bei Verhaltensauffälligkeiten'],
            ['agility',           'Agility / Fun-Sport',     '🏃', '#06b6d4', 60, 6, 18000, 'Parcours-Spaß und Fitness'],
            ['beschaeftigung',    'Beschäftigung / Tricks',  '🎾', '#8b5cf6', 45, 8, 15000, 'Nasenarbeit, Tricks, Denkaufgaben'],
            ['workshop',          'Workshop',                '🎓', '#0ea5e9', 180, 10, 35000, 'Themen-Workshop (z.B. Maulkorbtraining)'],
            ['seminar',           'Seminar / Vortrag',       '📚', '#d946ef', 120, 20, 20000, 'Theorie-Veranstaltung'],
            ['event',             'Event',                   '🎉', '#f43f5e', 180, 20, 0, 'Treffen, Feiern, Sondertermine'],
            ['group',             'Gruppentraining',         '👥', '#3b82f6', 60, 8, 18000, 'Allgemeines Gruppentraining'],
        ];
        $sort = 0;
        foreach ($categories as $c) {
            [$slug, $name, $icon, $color, $dur, $max, $price, $desc] = $c;
            $exists = (int)$this->db->safeFetchColumn(
                "SELECT COUNT(*) FROM `{$t}` WHERE slug = ?",
                [$slug]
            );
            if ($exists === 0) {
                $this->db->safeExecute(
                    "INSERT INTO `{$t}` (slug, name, description, icon, color,
                        default_duration_min, default_max_participants, default_price_cents,
                        default_tax_rate, sort_order, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 19.00, ?, 1)",
                    [$slug, $name, $desc, $icon, $color, $dur, $max, $price, $sort]
                );
            }
            $sort += 10;
        }
    }

    /* ═══════════════════════ Übungen-Katalog ═══════════════════════ */
    private function seedExercises(): void
    {
        $t = $this->db->prefix('dogschool_exercises');
        $exercises = [
            /* ── Basics / Grundkommandos ── */
            ['sitz',             'Sitz',                    'basics',    'easy',   5,  2, 'Hund sitzt auf Kommando.', 'Leckerli über die Nase hoch führen → Hund setzt sich. Sofort markern und belohnen.'],
            ['platz',            'Platz',                   'basics',    'easy',   5,  3, 'Hund legt sich ab.', 'Aus dem Sitz Leckerli zum Boden führen. Markern sobald die Ellbogen den Boden berühren.'],
            ['bleib',            'Bleib',                   'obedience', 'medium', 10, 4, 'Hund bleibt an seinem Platz.', 'Mit kurzen Distanzen/Dauern beginnen. Langsam steigern (3D-Regel: Dauer, Distanz, Ablenkung).'],
            ['komm',             'Komm / Hier',             'recall',    'medium', 10, 2, 'Hund kommt zuverlässig auf Ruf.', 'In reizarmer Umgebung beginnen. Immer positiv belohnen — nie bestrafen nach Rückruf.'],
            ['fuss',             'Bei Fuß',                 'obedience', 'medium', 15, 4, 'Hund läuft neben Halter ohne zu ziehen.', 'Richtungswechsel üben. Linkes Bein = Ankerpunkt.'],
            ['aus',              'Aus / Gib',               'basics',    'easy',   5,  3, 'Hund gibt Gegenstände ab.', 'Tausch-Prinzip: Leckerli gegen Objekt. Erst später Kommando einführen.'],
            ['nein',             'Abbruchsignal (Nein)',    'obedience', 'medium', 10, 3, 'Hund unterbricht sein Verhalten.', 'Kein Bestrafungswort — einfach Unterbrecher. Sofort alternatives Verhalten anbieten.'],
            ['steh',             'Steh',                    'obedience', 'medium', 10, 4, 'Hund bleibt im Stand stehen.', 'Aus dem Gehen stoppen, Leckerli vor die Nase halten. Wichtig für Tierarztbesuche.'],

            /* ── Leine ── */
            ['leine_locker',     'Lockere Leine',           'leash',     'medium', 15, 2, 'Entspanntes Gehen an durchhängender Leine.', 'Stehen bleiben sobald Leine sich spannt — weitergehen wenn sie lockert.'],
            ['richtungswechsel', 'Richtungswechsel',        'leash',     'medium', 10, 3, 'Hund folgt bei plötzlichem Umdrehen.', 'Ohne Worte umdrehen — Hund muss von selbst folgen. Belohnen wenn er aufschließt.'],

            /* ── Rückruf ── */
            ['rueckruf_basis',   'Rückruf Basis',           'recall',    'easy',   10, 2, 'Namen rufen, kommt zuverlässig in leerer Umgebung.', 'Hund anlocken mit extra-gutem Leckerli. Nie rufen wenn du nicht sicher bist dass er kommt.'],
            ['rueckruf_ablenk',  'Rückruf mit Ablenkung',   'recall',    'hard',   15, 5, 'Kommt trotz Ablenkung (Hunde, Wild).', 'Schleppleine für Sicherheit. Aufbau in 5 Schwierigkeitsstufen.'],
            ['notrueckruf',      'Notrückruf / Pfiff',      'recall',    'hard',   15, 5, 'Konditioniertes Sondersignal für Notfälle.', 'Pfeife oder spezielles Wort. NUR bei 100% Rückruf verwenden + Jackpot-Belohnung.'],

            /* ── Sozialverhalten ── */
            ['begegnung_hund',   'Hundebegegnung entspannt', 'social',   'hard',   20, 5, 'Ruhig an anderen Hunden vorbei.', 'Distanz variieren. Belohnung aufs Auge/Halter setzen. Gegenkonditionierung bei Unsicherheit.'],
            ['begegnung_mensch', 'Fremde Menschen',         'social',    'medium', 15, 4, 'Ruhig bei Annäherung fremder Personen.', 'Setz-Bleib üben. Keine Begrüßung aus Anspannung.'],
            ['boxentraining',    'Boxen-/Decke-Training',   'basics',    'easy',   15, 3, 'Freiwillig in Box/auf Decke gehen.', 'Capturing: Hund für selbstständiges Reingehen belohnen. Tür zunächst offen lassen.'],

            /* ── Tricks / Beschäftigung ── */
            ['pfote',            'Pfote geben',             'tricks',    'easy',   10, 2, 'Hebt Pfote auf Kommando.', 'Leckerli in geschlossener Faust unten halten — Hund probiert mit Pfote → markern.'],
            ['rolle',            'Rolle',                   'tricks',    'medium', 15, 4, 'Dreht sich um die Körperachse.', 'Aus dem Platz Leckerli über die Schulter führen. In Teilschritten aufbauen.'],
            ['winken',           'Winken',                  'tricks',    'medium', 10, 3, 'Hebt Pfote hoch und "winkt".', 'Aus "Pfote" aufbauen — Hand höher halten, Berührung auslassen.'],
            ['dreh',             'Drehung',                 'tricks',    'easy',   10, 2, 'Dreht sich um eigene Achse.', 'Mit Leckerli im Kreis führen. Links/rechts getrennt trainieren.'],
            ['verbeugung',       'Verbeugung',              'tricks',    'medium', 10, 3, 'Front runter, Hinterteil oben.', 'Leckerli zwischen Vorderbeinen nach hinten führen.'],

            /* ── Nasenarbeit ── */
            ['leckerli_suchen',  'Leckerlisuche',           'tricks',    'easy',   10, 2, 'Verstecktes Futter finden.', 'Start: 3 Leckerli sichtbar auf Boden. Steigerung: unter Decken, in Räumen.'],
            ['gegenstand_apport','Apportieren',             'tricks',    'medium', 15, 4, 'Gegenstand bringen.', 'Zweiball-Prinzip: Interesse hochhalten durch 2. Ball. Ball gegen Leckerli eintauschen.'],
            ['nasenspiel',       'Dummysuche',              'tricks',    'medium', 20, 4, 'Dummy in Wiese/Wald finden.', 'Leichte Verstecke zuerst. Wind beachten. Jagdhund-Erbe sinnvoll auslasten.'],

            /* ── Alltag ── */
            ['klingel',          'Klingel-Training',        'basics',    'medium', 10, 3, 'Ruhig bei Klingelgeräusch.', 'Klingel → Decke → Warten. Ritual etablieren damit Klingeln positiv konditioniert wird.'],
            ['auto',             'Autofahren',              'basics',    'medium', 15, 4, 'Entspannt im Auto.', 'Kurze Fahrten zu schönen Zielen. Transportbox oder Sicherheitsgeschirr.'],
            ['tierarzt',         'Tierarzt-Training',       'basics',    'medium', 15, 4, 'Entspannt bei Untersuchungen.', 'Medical Training: Pfote halten, Ohr anschauen, Temperatur messen simulieren.'],
            ['buergersteig',     'Bürgersteig-Warten',      'obedience', 'medium', 10, 3, 'Wartet am Bordstein.', 'Automatisches Sitzen an jeder Straßenecke → Sicherheit.'],
            ['maulkorb',         'Maulkorb-Training',       'basics',    'medium', 20, 4, 'Trägt Maulkorb freiwillig.', 'In Miniatur-Schritten aufbauen: Nase reinstecken → Lecker → Dauer aufbauen → Verschluss.'],

            /* ── Problemverhalten ── */
            ['territorialbell',  'Territorialbellen',       'problem',   'hard',   20, 5, 'Unterbricht übermäßiges Bellen.', 'Management + Alternativverhalten (Decke). Ursachen-Management essentiell.'],
            ['jagdersatz',       'Jagdersatztraining',      'problem',   'expert', 30, 5, 'Lenkt von Jagd-Trigger ab.', 'Umorientierung aufbauen. Dummy-Arbeit als Ersatzbefriedigung.'],
            ['leinenpobel',      'Leinenpöbeln',            'problem',   'hard',   20, 5, 'Entspannung bei Hundesichtung.', 'Schwellendistanz einhalten. Gegenkonditionierung mit Jackpot-Belohnung.'],
        ];

        foreach ($exercises as $e) {
            [$slug, $name, $cat, $diff, $dur, $minAge, $desc, $instr] = $e;
            $exists = (int)$this->db->safeFetchColumn(
                "SELECT COUNT(*) FROM `{$t}` WHERE slug = ?",
                [$slug]
            );
            if ($exists === 0) {
                $this->db->safeExecute(
                    "INSERT INTO `{$t}` (slug, name, category, description, instructions,
                        difficulty, duration_minutes, min_age_months, is_system, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)",
                    [$slug, $name, $cat, $desc, $instr, $diff, $dur, $minAge]
                );
            } else {
                /* System-Übung: Inhalte ggf. aktualisieren (nur Texte, keine Flags) */
                $this->db->safeExecute(
                    "UPDATE `{$t}` SET description = ?, instructions = ?
                      WHERE slug = ? AND is_system = 1",
                    [$desc, $instr, $slug]
                );
            }
        }
    }

    /* ═══════════════════════ Einwilligungs-Vorlagen ═══════════════════════ */
    private function seedConsents(): void
    {
        $t = $this->db->prefix('dogschool_consents');

        $templates = [
            [
                'name'        => 'Teilnahmebedingungen Hundetraining',
                'type'        => 'participation',
                'version'     => '1.0',
                'is_required' => 1,
                'content'     => "Mit der Anmeldung zu einem Kurs oder Einzeltraining erkenne ich folgende Teilnahmebedingungen an:\n\n" .
                                 "1. Mein Hund ist gesund, geimpft (mindestens Grundimmunisierung + jährliche Auffrischung gegen Staupe, Hepatitis, Parvovirose, Leptospirose und Tollwut) sowie entwurmt.\n" .
                                 "2. Ich bringe den aktuellen Impfpass zur ersten Einheit mit.\n" .
                                 "3. Hündinnen dürfen während der Läufigkeit nicht an Gruppentrainings teilnehmen.\n" .
                                 "4. Ich hafte für alle Schäden, die mein Hund verursacht. Eine gültige Hundehaftpflichtversicherung ist verpflichtend.\n" .
                                 "5. Ich folge den Anweisungen des Trainerteams während der Einheiten.\n" .
                                 "6. Bei Nichterscheinen ohne Abmeldung (mind. 24h vorher) wird die Einheit berechnet.\n" .
                                 "7. Stornierung eines Kursplatzes ist bis 14 Tage vor Kursbeginn kostenfrei möglich.",
            ],
            [
                'name'        => 'Einwilligung Foto-/Videoaufnahmen',
                'type'        => 'photo_video',
                'version'     => '1.0',
                'is_required' => 0,
                'content'     => "Ich willige ein, dass während der Trainingseinheiten Foto- und Videoaufnahmen von mir und meinem Hund gemacht werden dürfen. Diese dürfen verwendet werden für:\n\n" .
                                 "☐ Internes Trainings-Feedback (immer erlaubt wenn oben unterschrieben)\n" .
                                 "☐ Social Media der Hundeschule (Instagram, Facebook)\n" .
                                 "☐ Website der Hundeschule\n" .
                                 "☐ Werbematerial (Flyer, Broschüren)\n\n" .
                                 "Diese Einwilligung ist freiwillig und jederzeit widerrufbar (schriftlich per E-Mail).",
            ],
            [
                'name'        => 'Haftungsausschluss',
                'type'        => 'liability',
                'version'     => '1.0',
                'is_required' => 1,
                'content'     => "Die Teilnahme am Hundetraining erfolgt auf eigene Gefahr. Der Hundetrainer haftet nicht für:\n\n" .
                                 "• Schäden, die durch den eigenen oder andere Hunde verursacht werden.\n" .
                                 "• Verletzungen von Mensch oder Tier während der Trainingseinheiten, sofern nicht vorsätzlich oder grob fahrlässig verursacht.\n" .
                                 "• Verlust oder Beschädigung von Gegenständen auf dem Trainingsgelände.\n\n" .
                                 "Ich bestätige, dass mein Hund über eine gültige Hundehaftpflichtversicherung verfügt.",
            ],
            [
                'name'        => 'Datenschutzerklärung (DSGVO)',
                'type'        => 'data_protection',
                'version'     => '1.0',
                'is_required' => 1,
                'content'     => "Ich willige ein, dass meine personenbezogenen Daten (Name, Adresse, Telefon, E-Mail) sowie Daten meines Hundes (Name, Rasse, Alter, Impfstatus, Trainingsfortschritt) zum Zweck der Kursverwaltung, Terminkoordination und Kommunikation verarbeitet werden.\n\n" .
                                 "Die Daten werden gemäß Artikel 6 Abs. 1 lit. a und b DSGVO verarbeitet und nicht an Dritte weitergegeben, außer an gesetzlich vorgeschriebene Stellen (Steuerberater, Finanzamt).\n\n" .
                                 "Ich habe jederzeit das Recht auf:\n" .
                                 "• Auskunft (Art. 15 DSGVO)\n" .
                                 "• Berichtigung (Art. 16 DSGVO)\n" .
                                 "• Löschung (Art. 17 DSGVO)\n" .
                                 "• Widerspruch (Art. 21 DSGVO)",
            ],
            [
                'name'        => 'Gesundheits- und Impferklärung',
                'type'        => 'vaccination',
                'version'     => '1.0',
                'is_required' => 1,
                'content'     => "Ich erkläre hiermit:\n\n" .
                                 "• Mein Hund ist nach tierärztlichen Empfehlungen aktuell geimpft (Staupe, Hepatitis, Parvovirose, Leptospirose, Tollwut).\n" .
                                 "• Mein Hund ist frei von ansteckenden Krankheiten (Zwingerhusten, Giardien, Räude etc.).\n" .
                                 "• Mein Hund ist entwurmt (letzte Kur nicht älter als 3 Monate).\n" .
                                 "• Ich informiere die Hundeschule unverzüglich wenn mein Hund Anzeichen einer Krankheit zeigt.\n" .
                                 "• Ich lege den Impfpass auf Verlangen vor.",
            ],
        ];

        foreach ($templates as $tpl) {
            $exists = (int)$this->db->safeFetchColumn(
                "SELECT COUNT(*) FROM `{$t}` WHERE name = ?",
                [$tpl['name']]
            );
            if ($exists === 0) {
                $this->db->safeExecute(
                    "INSERT INTO `{$t}` (name, content, version, type, is_required, is_active)
                     VALUES (?, ?, ?, ?, ?, 1)",
                    [$tpl['name'], $tpl['content'], $tpl['version'], $tpl['type'], $tpl['is_required']]
                );
            }
        }
    }

    /* ═══════════════════════ Trainingsplan-Vorlagen ═══════════════════════ */
    private function seedTrainingPlans(): void
    {
        $plans = [
            [
                'name'              => 'Welpen-Grundkurs (8 Wochen)',
                'description'       => 'Systematischer Start für Welpen ab 10 Wochen. Fokus: Sozialisierung, Stubenreinheit, erste Signale.',
                'target_audience'   => 'welpen',
                'duration_weeks'    => 8,
                'sessions_per_week' => 1,
                'difficulty'        => 'easy',
                'curriculum'        => [
                    [1, 1, ['sitz', 'leckerli_suchen', 'boxentraining']],
                    [2, 1, ['sitz', 'platz', 'begegnung_mensch']],
                    [3, 1, ['platz', 'komm', 'leine_locker']],
                    [4, 1, ['bleib', 'komm', 'pfote']],
                    [5, 1, ['leine_locker', 'aus', 'nasenspiel']],
                    [6, 1, ['rueckruf_basis', 'begegnung_hund', 'dreh']],
                    [7, 1, ['sitz', 'platz', 'bleib', 'rueckruf_basis']],
                    [8, 1, ['rueckruf_basis', 'leine_locker', 'pfote', 'leckerli_suchen']],
                ],
            ],
            [
                'name'              => 'Junghunde-Basiskurs (10 Wochen)',
                'description'       => 'Für Hunde 4-12 Monate. Festigt Grundkommandos und führt Alltagstauglichkeit ein.',
                'target_audience'   => 'junghunde',
                'duration_weeks'    => 10,
                'sessions_per_week' => 1,
                'difficulty'        => 'medium',
                'curriculum'        => [
                    [1,  1, ['sitz', 'platz', 'komm']],
                    [2,  1, ['bleib', 'fuss']],
                    [3,  1, ['leine_locker', 'richtungswechsel']],
                    [4,  1, ['rueckruf_basis', 'aus']],
                    [5,  1, ['begegnung_hund', 'begegnung_mensch']],
                    [6,  1, ['fuss', 'buergersteig']],
                    [7,  1, ['rueckruf_ablenk', 'nein']],
                    [8,  1, ['steh', 'tierarzt']],
                    [9,  1, ['leine_locker', 'rueckruf_ablenk', 'begegnung_hund']],
                    [10, 1, ['bleib', 'rueckruf_ablenk', 'fuss', 'buergersteig']],
                ],
            ],
            [
                'name'              => 'Alltags-Perfekt (6 Wochen)',
                'description'       => 'Kompakter Kurs für erwachsene Hunde. Fokus: Alltagstauglichkeit, entspanntes Mitgehen.',
                'target_audience'   => 'adult',
                'duration_weeks'    => 6,
                'sessions_per_week' => 1,
                'difficulty'        => 'medium',
                'curriculum'        => [
                    [1, 1, ['leine_locker', 'buergersteig']],
                    [2, 1, ['begegnung_hund', 'begegnung_mensch']],
                    [3, 1, ['rueckruf_ablenk', 'klingel']],
                    [4, 1, ['auto', 'tierarzt']],
                    [5, 1, ['boxentraining', 'maulkorb']],
                    [6, 1, ['leine_locker', 'rueckruf_ablenk', 'begegnung_hund']],
                ],
            ],
            [
                'name'              => 'Rückruf-Intensiv (5 Wochen)',
                'description'       => 'Nur Rückruf. 5 Wochen zu einem sicheren Rückruf auch bei starken Ablenkungen.',
                'target_audience'   => 'adult',
                'duration_weeks'    => 5,
                'sessions_per_week' => 2,
                'difficulty'        => 'hard',
                'curriculum'        => [
                    [1, 1, ['rueckruf_basis']],
                    [1, 2, ['rueckruf_basis']],
                    [2, 1, ['rueckruf_ablenk']],
                    [2, 2, ['rueckruf_ablenk']],
                    [3, 1, ['rueckruf_ablenk']],
                    [3, 2, ['notrueckruf']],
                    [4, 1, ['rueckruf_ablenk', 'notrueckruf']],
                    [4, 2, ['rueckruf_ablenk', 'jagdersatz']],
                    [5, 1, ['notrueckruf', 'jagdersatz']],
                    [5, 2, ['rueckruf_ablenk', 'notrueckruf', 'jagdersatz']],
                ],
            ],
            [
                'name'              => 'Problemhunde-Basis (8 Wochen, einzeln)',
                'description'       => 'Einzeltraining für Hunde mit Verhaltensauffälligkeiten (Leinenpöbeln, Bellen, Jagd).',
                'target_audience'   => 'problem',
                'duration_weeks'    => 8,
                'sessions_per_week' => 1,
                'difficulty'        => 'expert',
                'curriculum'        => [
                    [1, 1, ['boxentraining', 'nein']],
                    [2, 1, ['leinenpobel']],
                    [3, 1, ['begegnung_hund']],
                    [4, 1, ['territorialbell', 'klingel']],
                    [5, 1, ['jagdersatz']],
                    [6, 1, ['leinenpobel', 'begegnung_hund']],
                    [7, 1, ['jagdersatz', 'notrueckruf']],
                    [8, 1, ['leinenpobel', 'begegnung_hund', 'notrueckruf']],
                ],
            ],
        ];

        $tPlans     = $this->db->prefix('dogschool_training_plans');
        $tPlanEx    = $this->db->prefix('dogschool_plan_exercises');
        $tExercises = $this->db->prefix('dogschool_exercises');

        foreach ($plans as $p) {
            $exists = (int)$this->db->safeFetchColumn(
                "SELECT id FROM `{$tPlans}` WHERE name = ? AND is_system = 1",
                [$p['name']]
            );
            if ($exists > 0) {
                continue; /* System-Plan bereits vorhanden */
            }

            $planId = (int)$this->db->insert(
                "INSERT INTO `{$tPlans}`
                    (name, description, target_audience, duration_weeks, sessions_per_week,
                     difficulty, is_template, is_system, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1, 1, 1)",
                [
                    $p['name'], $p['description'], $p['target_audience'],
                    $p['duration_weeks'], $p['sessions_per_week'], $p['difficulty'],
                ]
            );

            $sort = 0;
            foreach ($p['curriculum'] as $weekEntry) {
                [$week, $session, $exerciseSlugs] = $weekEntry;
                foreach ($exerciseSlugs as $slug) {
                    $exerciseId = (int)$this->db->safeFetchColumn(
                        "SELECT id FROM `{$tExercises}` WHERE slug = ?",
                        [$slug]
                    );
                    if ($exerciseId > 0) {
                        $this->db->safeExecute(
                            "INSERT INTO `{$tPlanEx}`
                                (plan_id, exercise_id, week_number, session_number, sort_order)
                             VALUES (?, ?, ?, ?, ?)",
                            [$planId, $exerciseId, $week, $session, $sort]
                        );
                        $sort++;
                    }
                }
            }
        }
    }

    /* ═══════════════════════ Seed-Version (settings) ═══════════════════════ */
    private function getStoredSeedVersion(): int
    {
        try {
            $v = $this->db->safeFetchColumn(
                "SELECT `value` FROM `{$this->db->prefix('settings')}` WHERE `key` = ?",
                ['dogschool_seed_version']
            );
            return (int)($v ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function setStoredSeedVersion(int $version): void
    {
        try {
            $this->db->safeExecute(
                "INSERT INTO `{$this->db->prefix('settings')}` (`key`, `value`)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                ['dogschool_seed_version', (string)$version]
            );
        } catch (\Throwable $e) {
            error_log('[DogschoolSeedService setStoredSeedVersion] ' . $e->getMessage());
        }
    }
}
