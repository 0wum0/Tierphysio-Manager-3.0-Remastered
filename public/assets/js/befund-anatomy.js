/* ═══════════════════════════════════════════════════════════════
 *  Befund Anatomie — Interaktive Körperdarstellung
 *  Pure Vanilla JS, keine Abhängigkeiten.
 *  Self-Heal:
 *    - kaputte JSON-Daten → leer initialisieren
 *    - Fehlendes Target-DOM → Modul still aussetzen
 *    - Runtime-Fehler → console.error + UI-Fallback
 * ═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    // Doppel-Init-Schutz: falls Script versehentlich mehrfach geladen wird
    if (window.__befundAnatomyBooted) {
        return;
    }

    function boot() {
        const ROOT = document.getElementById('befund-anatomy');
        if (!ROOT) {
            // Nicht auf dieser Seite — einfach abbrechen
            return;
        }
        if (ROOT.dataset.booted === '1') {
            return; // schon initialisiert
        }
        ROOT.dataset.booted = '1';
        window.__befundAnatomyBooted = true;
        initAnatomy(ROOT);
    }

    // Warten bis DOM fertig ist (Script kann vor/nach DOMContentLoaded laden)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    function initAnatomy(ROOT) {

    // ── Config ─────────────────────────────────────────────────
    const COLORS = [
        { key: 'red',    hex: '#ef4444', label: 'Schmerz' },
        { key: 'yellow', hex: '#f59e0b', label: 'Leichte Verspannung' },
        { key: 'green',  hex: '#10b981', label: 'In Ordnung' },
        { key: 'blue',   hex: '#3b82f6', label: 'Bewegungseinschränkung' },
        { key: 'purple', hex: '#a855f7', label: 'Neurologisch' },
    ];

    const SPECIES = [
        { key: 'dog',   label: 'Hund'   },
        { key: 'cat',   label: 'Katze'  },
        { key: 'horse', label: 'Pferd'  },
    ];

    // ── Veterinary Anatomy Engine — Phase 2 (Realistic Lateral Silhouettes) ──
    // viewBox: 0 0 500 300 — Tiere im lateralen Profil, Blickrichtung links.
    // body    = realistische Außenkontur als geschlossener Cubic-Bezier-Pfad
    //           inkl. Kopf, Ohren, sichtbare Vorder-/Hinterläufe, Pfoten/Hufe, Rute.
    // regions = anatomische Muskelgruppen, klickbar; pro Klick wird ein NRS-Wert
    //           vergeben → Region wird mit NRS-Farbe eingefärbt.
    const SIL_FILL    = '#cfd9e1';
    const SIL_STROKE  = '#1f2a36';
    const SIL_SW      = '1.6';
    const MUS_FILL    = '#aab9c8';
    const MUS_STROKE  = '#3d5266';

    // NRS-Farbpalette 0..10 (grün → rot, Schmerzintensität)
    const NRS_COLORS = [
        '#22c55e', '#4ade80', '#a3e635', '#facc15', '#fb923c',
        '#f97316', '#ef4444', '#dc2626', '#b91c1c', '#991b1b', '#7f1d1d',
    ];

    const ANATOMY = {
        // ── Hund (lateral, mittelgroß, stehend, Kopf links) ───────
        dog: {
            body:
                'M30,138 C24,136 22,132 28,128 L40,124 ' +
                'C52,120 60,114 64,106 C64,96 66,90 72,84 ' +
                'C72,74 78,66 86,68 C90,58 98,58 100,68 ' +
                'C102,80 100,92 92,100 ' +
                'C106,104 124,108 140,112 ' +
                'C168,108 200,104 232,102 ' +
                'C266,100 302,100 334,104 ' +
                'C360,108 382,114 400,122 ' +
                'C412,116 422,98 434,82 ' +
                'C444,74 454,72 458,82 ' +
                'C460,96 454,116 442,126 ' +
                'C434,132 424,136 416,140 ' +
                'L416,200 L414,238 ' +
                'C414,252 416,262 414,266 ' +
                'C408,272 394,272 390,268 L388,254 L388,212 ' +
                'C386,202 382,198 376,196 ' +
                'C350,198 314,200 282,202 ' +
                'C252,204 224,206 202,208 ' +
                'C194,214 190,226 188,238 L188,256 ' +
                'C188,266 180,272 170,270 ' +
                'C160,268 156,260 156,252 L154,238 ' +
                'C154,222 148,202 140,190 ' +
                'C132,182 120,178 104,174 ' +
                'C84,170 64,164 46,158 ' +
                'C36,154 28,148 30,138 Z',
            regions: [
                { id: 'head',           label: 'Kopf',                bilateral: false, d: 'M30,138 C24,136 22,132 28,128 L40,124 C52,120 60,114 64,106 C64,96 66,90 72,84 C72,74 78,66 86,68 C90,58 98,58 100,68 C102,80 100,92 92,100 C84,114 70,128 50,138 C38,142 30,142 30,138 Z' },
                { id: 'cervical',       label: 'HWS / Hals',          bilateral: false, d: 'M92,100 C106,104 124,108 140,112 C150,128 144,148 132,150 C112,148 96,140 88,128 C86,118 88,108 92,100 Z' },
                { id: 'shoulder',       label: 'Schulter',            bilateral: true,  d: 'M140,112 C160,108 184,108 196,118 C204,138 198,162 182,176 C160,178 144,170 138,150 C134,134 136,120 140,112 Z' },
                { id: 'thoracic',       label: 'BWS / Brustwirbel',   bilateral: false, d: 'M196,118 C220,110 256,108 292,108 L292,180 C264,184 232,182 204,176 C198,156 196,134 196,118 Z' },
                { id: 'lumbar',         label: 'LWS / Lende',         bilateral: false, d: 'M292,108 C314,108 340,110 364,116 L364,184 C336,186 312,184 292,180 Z' },
                { id: 'croup',          label: 'Kruppe / ISG',        bilateral: false, d: 'M364,116 C384,120 400,124 414,138 C418,160 408,180 388,186 C376,186 366,184 364,184 Z' },
                { id: 'tail',           label: 'Rute',                bilateral: false, d: 'M414,138 C422,118 434,96 446,82 C454,72 460,76 458,86 C454,108 442,128 424,140 C418,142 414,142 414,138 Z' },
                { id: 'biceps_femoris', label: 'Biceps femoris',      bilateral: true,  d: 'M388,186 C400,188 410,190 416,200 L416,238 C406,242 394,238 388,212 C386,200 386,192 388,186 Z' },
                { id: 'gastroc_rear',   label: 'Wade hinten',         bilateral: true,  d: 'M388,212 C394,234 396,250 388,254 L388,266 C404,272 414,268 414,252 L414,238 C406,234 396,222 388,212 Z' },
                { id: 'thorax_wall',    label: 'Brustkorb / Rippen',  bilateral: false, d: 'M204,176 C236,184 274,186 298,184 C292,206 246,212 210,210 C200,210 196,200 198,188 C200,182 202,178 204,176 Z' },
                { id: 'triceps',        label: 'Triceps brachii',     bilateral: true,  d: 'M138,150 C162,160 180,170 184,184 C176,200 156,202 140,196 C128,188 122,170 124,156 C128,150 134,148 138,150 Z' },
                { id: 'forearm',        label: 'Unterarm',            bilateral: true,  d: 'M140,196 C158,200 174,206 174,222 L172,250 C166,258 152,258 152,238 C150,222 146,208 140,196 Z' },
                { id: 'carpus',         label: 'Karpalgelenk',        bilateral: true,  d: 'M152,238 L188,238 L188,256 C186,266 174,272 162,268 C156,266 152,260 152,252 Z' },
                { id: 'belly',          label: 'Bauch / Flanke',      bilateral: false, d: 'M210,210 C242,212 282,210 320,206 C346,204 364,200 376,200 C374,212 360,218 320,220 C272,222 224,222 200,218 C198,214 202,212 210,210 Z' },
            ],
        },
        // ── Katze (lateral, schlank, kompakter Körper, Kopf links) ─
        cat: {
            body:
                'M28,144 C24,142 22,138 28,134 L40,132 ' +
                'C50,128 58,124 60,116 ' +
                'C58,104 56,88 64,80 ' +
                'C62,66 72,58 78,68 ' +
                'C82,52 92,50 92,68 ' +
                'C92,80 88,92 82,100 ' +
                'C98,104 116,108 134,110 ' +
                'C164,106 198,102 232,102 ' +
                'C266,102 298,104 324,108 ' +
                'C350,112 372,118 388,128 ' +
                'C396,118 408,100 420,84 ' +
                'C432,72 444,68 448,80 ' +
                'C450,98 444,118 430,128 ' +
                'C420,134 410,138 404,142 ' +
                'L404,206 L402,242 ' +
                'C402,254 404,264 402,268 ' +
                'C396,272 384,272 380,268 L378,254 L378,212 ' +
                'C376,202 372,198 366,196 ' +
                'C344,198 308,200 276,202 ' +
                'C244,204 218,206 196,208 ' +
                'C188,214 184,226 184,238 L184,254 ' +
                'C184,266 176,272 168,270 ' +
                'C158,268 154,260 154,252 L154,238 ' +
                'C154,222 148,202 140,190 ' +
                'C128,182 110,178 92,174 ' +
                'C72,170 52,164 38,158 ' +
                'C30,154 26,148 28,144 Z',
            regions: [
                { id: 'head',           label: 'Kopf',                bilateral: false, d: 'M28,144 C24,142 22,138 28,134 L40,132 C50,128 58,124 60,116 C58,104 56,88 64,80 C62,66 72,58 78,68 C82,52 92,50 92,68 C92,80 88,92 82,100 C70,114 54,130 36,142 C30,144 28,146 28,144 Z' },
                { id: 'cervical',       label: 'HWS / Hals',          bilateral: false, d: 'M82,100 C100,104 120,108 138,110 C146,128 138,148 124,148 C108,146 92,138 84,128 C82,118 82,108 82,100 Z' },
                { id: 'shoulder',       label: 'Schulter',            bilateral: true,  d: 'M138,110 C158,108 180,108 192,118 C200,138 194,160 178,172 C158,174 142,166 138,148 C134,132 136,118 138,110 Z' },
                { id: 'thoracic',       label: 'BWS / Brustwirbel',   bilateral: false, d: 'M192,118 C218,110 252,108 286,108 L286,176 C258,180 226,178 200,172 C194,154 192,134 192,118 Z' },
                { id: 'lumbar',         label: 'LWS / Lende',         bilateral: false, d: 'M286,108 C310,108 336,110 358,116 L358,180 C330,182 308,180 286,176 Z' },
                { id: 'croup',          label: 'Kruppe / ISG',        bilateral: false, d: 'M358,116 C376,122 388,128 400,140 C402,162 392,180 376,184 C366,184 360,182 358,180 Z' },
                { id: 'tail',           label: 'Rute',                bilateral: false, d: 'M400,140 C408,118 420,98 432,84 C440,76 448,80 446,90 C442,108 432,124 414,138 C406,142 400,144 400,140 Z' },
                { id: 'biceps_femoris', label: 'Biceps femoris',      bilateral: true,  d: 'M376,184 C388,186 398,188 404,200 L404,242 C394,246 384,242 380,212 C378,200 376,192 376,184 Z' },
                { id: 'gastroc_rear',   label: 'Wade hinten',         bilateral: true,  d: 'M380,212 C386,232 388,248 380,252 L380,266 C396,272 404,268 404,252 L404,238 C396,234 388,222 380,212 Z' },
                { id: 'thorax_wall',    label: 'Brustkorb / Rippen',  bilateral: false, d: 'M200,172 C232,180 270,182 294,180 C288,202 244,210 208,208 C198,208 194,198 196,186 C198,178 200,174 200,172 Z' },
                { id: 'triceps',        label: 'Triceps brachii',     bilateral: true,  d: 'M138,148 C160,158 178,168 182,182 C174,198 154,200 138,194 C126,186 120,168 122,154 C128,148 134,146 138,148 Z' },
                { id: 'forearm',        label: 'Unterarm',            bilateral: true,  d: 'M138,194 C156,198 172,204 172,220 L170,248 C164,256 150,256 150,238 C148,220 144,206 138,194 Z' },
                { id: 'carpus',         label: 'Karpalgelenk',        bilateral: true,  d: 'M150,238 L184,238 L184,254 C182,266 170,272 158,268 C152,266 150,260 150,252 Z' },
                { id: 'belly',          label: 'Bauch / Flanke',      bilateral: false, d: 'M208,208 C240,210 280,208 318,204 C344,202 360,198 372,198 C370,210 356,216 318,218 C272,220 224,220 198,216 C196,212 200,210 208,208 Z' },
            ],
        },
        // ── Pferd (lateral, langgestreckt, lange Beine, Kopf links) ─
        horse: {
            body:
                'M30,98 C24,96 22,92 28,86 L42,80 ' +
                'C56,74 66,68 70,58 ' +
                'C70,46 76,38 84,40 ' +
                'C86,30 94,30 96,42 ' +
                'C98,52 92,62 86,68 ' +
                'C100,74 116,82 128,90 ' +
                'C134,96 138,106 142,114 ' +
                'C170,108 202,106 232,108 ' +
                'C264,110 296,112 326,116 ' +
                'C354,120 378,124 396,130 ' +
                'C404,114 416,94 428,80 ' +
                'C436,72 444,72 446,82 ' +
                'C452,104 458,134 458,164 ' +
                'C458,194 454,222 448,244 ' +
                'C444,258 438,266 432,272 ' +
                'C426,274 422,270 422,264 ' +
                'L422,250 ' +
                'C424,222 428,192 422,164 ' +
                'C418,146 412,134 406,128 ' +
                'L406,200 L406,240 ' +
                'C406,254 408,266 406,272 ' +
                'C400,276 388,276 384,270 L382,256 L382,212 ' +
                'C380,202 376,198 370,196 ' +
                'C346,198 314,200 282,202 ' +
                'C250,204 216,206 188,208 ' +
                'C176,212 168,222 164,234 L162,256 L160,272 ' +
                'C156,278 146,278 144,272 L142,260 L142,212 ' +
                'C140,202 136,196 130,192 ' +
                'C114,184 94,176 76,166 ' +
                'C62,158 50,150 40,142 ' +
                'C32,134 26,124 26,114 ' +
                'C26,108 28,102 30,98 Z',
            regions: [
                { id: 'head',           label: 'Kopf',                bilateral: false, d: 'M30,98 C24,96 22,92 28,86 L42,80 C56,74 66,68 70,58 C70,46 76,38 84,40 C86,30 94,30 96,42 C98,52 92,62 86,68 C100,74 116,82 128,90 C124,108 96,118 60,114 C42,110 32,104 30,98 Z' },
                { id: 'cervical',       label: 'HWS / Halswirbel',    bilateral: false, d: 'M128,90 C148,98 174,110 184,128 C188,148 174,168 154,170 C136,168 124,156 122,140 C122,118 124,102 128,90 Z' },
                { id: 'shoulder',       label: 'Schulter / Buggelenk', bilateral: true, d: 'M154,170 C176,166 198,168 212,184 C218,206 208,224 188,228 C166,228 152,216 148,194 C148,184 150,176 154,170 Z' },
                { id: 'withers',        label: 'Widerrist',           bilateral: false, d: 'M184,128 C212,118 246,118 268,124 L268,164 C240,170 212,168 188,160 C182,148 182,138 184,128 Z' },
                { id: 'thoracic',       label: 'BWS / Sattellage',    bilateral: false, d: 'M268,124 C306,124 344,128 372,132 L372,170 C342,174 306,172 268,164 Z' },
                { id: 'lumbar',         label: 'LWS / Lende',         bilateral: false, d: 'M372,132 C394,136 414,140 422,148 L422,182 C406,182 388,178 372,170 Z' },
                { id: 'croup',          label: 'Kruppe / ISG',        bilateral: false, d: 'M422,148 C436,138 446,124 450,116 C456,134 452,156 438,176 C432,182 426,184 422,182 Z' },
                { id: 'tail',           label: 'Schweifrübe',         bilateral: false, d: 'M438,176 C452,196 456,232 450,260 C444,272 436,278 428,274 L428,256 C432,232 438,204 438,176 Z' },
                { id: 'biceps_femoris', label: 'Hinterhand-Muskulatur', bilateral: true, d: 'M388,186 C404,190 416,194 422,210 L422,248 C414,254 396,256 384,232 C382,210 384,196 388,186 Z' },
                { id: 'gaskin',         label: 'Unterschenkel',       bilateral: true,  d: 'M384,232 C388,250 392,262 384,268 L384,272 C400,278 408,272 406,256 L406,240 C398,238 390,234 384,232 Z' },
                { id: 'thorax_wall',    label: 'Brustkorb / Rippen',  bilateral: false, d: 'M188,228 C234,230 290,230 340,226 C360,224 372,222 378,222 C374,236 350,242 290,242 C232,242 196,240 184,238 C182,232 184,230 188,228 Z' },
                { id: 'triceps',        label: 'Triceps / Oberarm',   bilateral: true,  d: 'M148,194 C172,200 192,210 198,226 C190,242 168,244 150,238 C138,228 130,210 132,196 C138,192 144,192 148,194 Z' },
                { id: 'forearm',        label: 'Unterarm',            bilateral: true,  d: 'M150,238 C168,242 184,248 184,264 L182,260 C176,272 162,272 162,256 C158,250 154,244 150,238 Z' },
                { id: 'carpus',         label: 'Karpalgelenk',        bilateral: true,  d: 'M142,260 L162,260 L162,272 C160,278 148,278 144,272 Z' },
                { id: 'belly',          label: 'Bauch / Flanke',      bilateral: false, d: 'M198,226 C236,228 280,226 322,222 C346,220 364,218 374,218 C372,228 360,234 322,236 C272,238 220,238 188,234 C186,230 192,228 198,226 Z' },
            ],
        },
    };

    // Kompatibilitäts-Wrapper — alte SILHOUETTES-API liefert vollständige Layer-Komposition
    const SILHOUETTES = new Proxy({}, {
        get(_, species) {
            const data = ANATOMY[species] || ANATOMY.dog;
            // Body als sanfte Schatten-Lage + scharfe Kontur für plastischeres Erscheinungsbild
            const bodyShadow = `<path d="${data.body}" fill="rgba(0,0,0,.08)" stroke="none" transform="translate(2.5 3.5)"/>`;
            const bodyMain   = `<path d="${data.body}" fill="${SIL_FILL}" stroke="${SIL_STROKE}" stroke-width="${SIL_SW}" stroke-linejoin="round" stroke-linecap="round"/>`;
            const regionPaths = data.regions.map(r =>
                `<path d="${r.d}" class="anatomy-region" data-region="${r.id}" data-label="${escapeAttr(r.label)}" data-bilateral="${r.bilateral ? '1' : '0'}" fill="${MUS_FILL}" fill-opacity=".0" stroke="${MUS_STROKE}" stroke-opacity=".35" stroke-width="0.6"/>`
            ).join('');
            return `
                <g class="layer-shadow">${bodyShadow}</g>
                <g class="layer-contour">${bodyMain}</g>
                <g class="layer-muscles" stroke-linejoin="round" stroke-linecap="round" pointer-events="all">
                    ${regionPaths}
                </g>
            `;
        },
    });

    function escapeAttr(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    // Liefert Region-Definition aus ANATOMY für ein bestimmtes Tier + Region-ID
    function findRegion(species, regionId) {
        const data = ANATOMY[species] || ANATOMY.dog;
        return (data.regions || []).find(r => r.id === regionId) || null;
    }

    // Eindeutiger Schlüssel für gespeicherte Region-Schmerz-Einträge (mit Seite, falls bilateral)
    function regionKey(regionId, side) {
        return side ? regionId + '_' + side : regionId;
    }

    // Findet bestehenden Region-Schmerz-Eintrag in state.markers
    function findRegionPainEntry(regionId, side) {
        const key = regionKey(regionId, side);
        return state.markers.find(m =>
            m && m.type === 'region' && regionKey(m.region, m.side) === key
        ) || null;
    }

    // ── State ──────────────────────────────────────────────────
    const hiddenSpecies  = ROOT.querySelector('input[name="anatomy_species"]');
    const hiddenMarkers  = ROOT.querySelector('input[name="anatomy_markers"]');
    const hiddenDrawings = ROOT.querySelector('input[name="anatomy_drawings"]');

    // Schmerzskala: liest bestehenden schmerz_nrs-Input aus dem Formular
    const nrsInput = document.querySelector('input[name="schmerz_nrs"]');

    const state = {
        species:  safeRead(hiddenSpecies, 'dog'),
        markers:  safeParseJson(hiddenMarkers?.value, []),
        drawings: safeParseJson(hiddenDrawings?.value, []),
        tool:     'marker',           // 'marker' | 'draw' | 'erase'
        color:    COLORS[0].hex,
        nrs:      (nrsInput && nrsInput.value !== '') ? parseInt(nrsInput.value, 10) : null,
    };

    function safeRead(el, fallback) {
        try { return (el && el.value) ? el.value : fallback; } catch { return fallback; }
    }

    function safeParseJson(raw, fallback) {
        if (raw === null || raw === undefined || raw === '') return fallback;
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : fallback;
        } catch (e) {
            console.warn('[Befund Anatomy] defekte JSON-Daten, initialisiere leer:', e);
            return fallback;
        }
    }

    let drawingPath = null; // must be before the try-block (renderStage→renderOverlay reads it)

    // ── DOM-Build ──────────────────────────────────────────────
    // Jeder Render-Schritt ist isoliert: ein Fehler in z.B. NRS soll
    // nicht die Silhouette verhindern. Echte Ursachen werden mit
    // step/name/message/stack protokolliert (Console → F12).
    const stepErrors = [];
    const safeRun = (name, fn) => {
        try { fn(); }
        catch (e) {
            stepErrors.push({ step: name, error: e });
            console.error('[Befund Anatomy] Schritt "' + name + '" fehlgeschlagen:',
                (e && e.name) || 'Error',
                (e && e.message) || e,
                '\nStack:\n' + ((e && e.stack) || '(kein stack)'));
        }
    };
    safeRun('renderToolbar',    renderToolbar);
    safeRun('renderStage',      renderStage);
    safeRun('paintRegions',     paintRegions);
    safeRun('renderLegend',     renderLegend);
    safeRun('renderNrsScale',   renderNrsScale);
    safeRun('renderMarkerList', renderMarkerList);
    safeRun('syncHidden',       syncHidden);

    // Warnhinweis nur wenn die Silhouette/Stage wirklich nicht gebaut werden konnte.
    // Andere Schritte (Legend/NRS/Marker) sind nicht kritisch.
    const stageOk = !!ROOT.querySelector('.anatomy-stage svg.anatomy-silhouette');
    if (!stageOk && stepErrors.length) {
        const warn = document.createElement('div');
        warn.className = 'alert alert-warning';
        warn.style.margin = '.5rem 0';
        warn.textContent = 'Die interaktive Anatomie konnte nicht geladen werden. Du kannst den Befund trotzdem normal bearbeiten.';
        const stage = ROOT.querySelector('.anatomy-stage');
        if (stage && stage.parentNode) {
            stage.parentNode.insertBefore(warn, stage);
            stage.style.display = 'none';
        } else {
            ROOT.prepend(warn);
        }
    }

    function renderToolbar() {
        const bar = ROOT.querySelector('.anatomy-toolbar');
        if (!bar) return;
        bar.innerHTML = '';

        // Tier-Wahl
        const speciesGroup = document.createElement('div');
        speciesGroup.className = 'anatomy-tool-group';
        SPECIES.forEach(sp => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'anatomy-species-btn' + (state.species === sp.key ? ' active' : '');
            btn.dataset.species = sp.key;
            btn.textContent = sp.label;
            btn.addEventListener('click', () => {
                state.species = sp.key;
                bar.querySelectorAll('.anatomy-species-btn').forEach(b => b.classList.toggle('active', b.dataset.species === sp.key));
                renderStage();
                syncHidden();
            });
            speciesGroup.appendChild(btn);
        });
        bar.appendChild(speciesGroup);

        // Werkzeug-Wahl
        const toolGroup = document.createElement('div');
        toolGroup.className = 'anatomy-tool-group';
        [
            { key: 'marker', label: '● Markieren' },
            { key: 'draw',   label: '✎ Zeichnen'  },
            { key: 'erase',  label: '⌫ Löschen'   },
        ].forEach(t => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'anatomy-tool-btn' + (state.tool === t.key ? ' active' : '');
            b.dataset.tool = t.key;
            b.textContent = t.label;
            b.addEventListener('click', () => {
                state.tool = t.key;
                toolGroup.querySelectorAll('.anatomy-tool-btn').forEach(x => x.classList.toggle('active', x.dataset.tool === t.key));
            });
            toolGroup.appendChild(b);
        });
        bar.appendChild(toolGroup);

        // Farben
        const colorGroup = document.createElement('div');
        colorGroup.className = 'anatomy-tool-group';
        COLORS.forEach(c => {
            const sw = document.createElement('button');
            sw.type = 'button';
            sw.className = 'anatomy-color-swatch' + (state.color === c.hex ? ' active' : '');
            sw.style.background = c.hex;
            sw.title = c.label;
            sw.addEventListener('click', () => {
                state.color = c.hex;
                colorGroup.querySelectorAll('.anatomy-color-swatch').forEach(s => s.classList.toggle('active', s.style.background === c.hex || s.style.backgroundColor === c.hex));
            });
            colorGroup.appendChild(sw);
        });
        bar.appendChild(colorGroup);

        // Clear
        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'btn btn-sm btn-outline-secondary ms-auto';
        clearBtn.textContent = 'Alles zurücksetzen';
        clearBtn.addEventListener('click', () => {
            if (!confirm('Alle Markierungen und Zeichnungen entfernen?')) return;
            state.markers = [];
            state.drawings = [];
            renderOverlay();
            renderMarkerList();
            paintRegions();
            syncHidden();
        });
        bar.appendChild(clearBtn);
    }

    function renderStage() {
        const stage = ROOT.querySelector('.anatomy-stage');
        if (!stage) return;

        // Server-seitig vorgerendertes SVG erhalten, falls Spezies übereinstimmt
        const existingSil = stage.querySelector('.anatomy-silhouette');
        const silMatches  = existingSil && existingSil.dataset.species === state.species;

        if (!silMatches) {
            // Alles neu aufbauen (Spezieswechsel oder kein server-seitiger Render)
            stage.innerHTML = '';

            const silSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            silSvg.setAttribute('class', 'anatomy-silhouette');
            silSvg.setAttribute('viewBox', '0 0 500 300');
            silSvg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
            silSvg.dataset.species = state.species;
            try {
                const svgSource = SILHOUETTES[state.species] || SILHOUETTES.dog;
                const parser    = new DOMParser();
                const doc       = parser.parseFromString(
                    '<svg xmlns="http://www.w3.org/2000/svg">' + svgSource + '</svg>',
                    'image/svg+xml'
                );
                const parsed = doc.documentElement;
                if (parsed && parsed.tagName !== 'parsererror' && !parsed.querySelector('parsererror')) {
                    Array.from(parsed.childNodes).forEach(n => silSvg.appendChild(document.importNode(n, true)));
                } else {
                    silSvg.innerHTML = svgSource;
                }
                stage.appendChild(silSvg);
            } catch (e) {
                console.warn('[Befund Anatomy] Silhouette-Fehler, Fallback aktiv:', e);
                const fb = document.createElement('div');
                fb.className = 'anatomy-fallback';
                fb.innerHTML = '<strong>Silhouette nicht verfügbar</strong><br>Klick-Markierungen funktionieren trotzdem.';
                stage.appendChild(fb);
            }
        } else {
            // Nur vorhandenes Overlay entfernen — Silhouette bleibt
            const oldOverlay = stage.querySelector('.anatomy-overlay');
            if (oldOverlay) oldOverlay.remove();
        }

        // Overlay (interaktiv) neu hinzufügen
        const overlay = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        overlay.setAttribute('class', 'anatomy-overlay');
        overlay.setAttribute('viewBox', '0 0 500 300');
        overlay.setAttribute('preserveAspectRatio', 'xMidYMid meet');
        stage.appendChild(overlay);

        attachStageEvents(stage, overlay);
        renderOverlay();
        // Falls bereits Region-Schmerz-Einträge existieren (Re-Render / Spezies-Wechsel),
        // müssen die Regionen neu eingefärbt werden.
        try { paintRegions(); } catch (e) { console.warn('[Anatomy] paintRegions:', e); }
    }

    function attachStageEvents(stage, overlay) {
        const coords = (ev) => {
            const rect = stage.getBoundingClientRect();
            const pt   = (ev.touches && ev.touches[0]) || ev;
            const x    = ((pt.clientX - rect.left) / rect.width)  * 500;
            const y    = ((pt.clientY - rect.top)  / rect.height) * 300;
            return { x, y };
        };

        overlay.addEventListener('click', (ev) => {
            if (state.tool === 'erase' || state.tool === 'draw') return;
            const { x, y } = coords(ev);
            const region = detectRegion(stage, x, y);

            // 1) Klick auf eine Muskelgruppe → NRS-Picker öffnen, Region einfärben
            if (region) {
                showRegionPainPicker(stage, ev.clientX, ev.clientY, region, (side, nrs) => {
                    setRegionPain(region, side, nrs);
                });
                return;
            }

            // 2) Klick außerhalb einer Region → freier Punkt-Marker (Legacy)
            state.markers.push({
                type:    'point',
                id:      'm_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
                x: round(x), y: round(y),
                color:   state.color,
                note:    '',
                region:  null,
                label:   null,
                side:    null,
                createdAt: new Date().toISOString(),
            });
            renderOverlay();
            renderMarkerList();
            paintRegions();
            syncHidden();
        });

        overlay.addEventListener('mousedown', (ev) => {
            if (state.tool !== 'draw') return;
            ev.preventDefault();
            const { x, y } = coords(ev);
            drawingPath = { color: state.color, points: [[round(x), round(y)]] };
        });
        overlay.addEventListener('mousemove', (ev) => {
            if (state.tool !== 'draw' || !drawingPath) return;
            const { x, y } = coords(ev);
            drawingPath.points.push([round(x), round(y)]);
            renderOverlay();
        });
        ['mouseup', 'mouseleave'].forEach(evt => overlay.addEventListener(evt, () => {
            if (drawingPath && drawingPath.points.length > 1) {
                state.drawings.push(drawingPath);
                syncHidden();
            }
            drawingPath = null;
            renderOverlay();
        }));

        // JS-Hover für Muskelregionen (CSS-:hover funktioniert wegen pointer-events:none nicht).
        let lastHoverPath = null;
        overlay.addEventListener('mousemove', (ev) => {
            if (state.tool === 'draw') return;
            const { x, y } = coords(ev);
            const sil = stage.querySelector('.anatomy-silhouette');
            if (!sil) return;
            try {
                const pt = sil.createSVGPoint();
                pt.x = x; pt.y = y;
                const paths = sil.querySelectorAll('path.anatomy-region');
                let hit = null;
                for (let i = paths.length - 1; i >= 0; i--) {
                    if (typeof paths[i].isPointInFill === 'function' && paths[i].isPointInFill(pt)) {
                        hit = paths[i];
                        break;
                    }
                }
                if (hit !== lastHoverPath) {
                    if (lastHoverPath) lastHoverPath.classList.remove('anatomy-region-hover');
                    if (hit) hit.classList.add('anatomy-region-hover');
                    lastHoverPath = hit;
                    overlay.style.cursor = hit ? 'pointer' : '';
                }
            } catch (_e) { /* still */ }
        });
        overlay.addEventListener('mouseleave', () => {
            if (lastHoverPath) lastHoverPath.classList.remove('anatomy-region-hover');
            lastHoverPath = null;
            overlay.style.cursor = '';
        });
    }

    function detectRegion(stage, vx, vy) {
        try {
            const sil = stage.querySelector('.anatomy-silhouette');
            if (!sil) return null;
            const pt = sil.createSVGPoint();
            pt.x = vx; pt.y = vy;
            const paths = sil.querySelectorAll('path.anatomy-region');
            // letzte (oberste) Region zuerst prüfen → präzisere Treffer
            for (let i = paths.length - 1; i >= 0; i--) {
                const p = paths[i];
                if (typeof p.isPointInFill === 'function' && p.isPointInFill(pt)) {
                    return {
                        id:        p.dataset.region,
                        label:     p.dataset.label || p.dataset.region,
                        bilateral: p.dataset.bilateral === '1',
                    };
                }
            }
        } catch (e) {
            console.warn('[Anatomy] detectRegion fehlgeschlagen:', e);
        }
        return null;
    }

    // Kombinierter Side- + NRS-Picker für Region-Schmerz-Eingabe.
    // Wird beim Klick auf eine Muskelgruppe geöffnet.
    function showRegionPainPicker(stage, clientX, clientY, region, onPick) {
        document.querySelectorAll('.anatomy-region-picker').forEach(el => el.remove());
        document.querySelectorAll('.anatomy-side-picker').forEach(el => el.remove());

        const stageRect = stage.getBoundingClientRect();
        const wrap = document.createElement('div');
        wrap.className = 'anatomy-region-picker';
        // Standardposition über dem Klick; Clamping unten
        wrap.style.left = (clientX - stageRect.left) + 'px';
        wrap.style.top  = (clientY - stageRect.top)  + 'px';

        let selectedSide = region.bilateral ? null : '';
        const existingMid = !region.bilateral ? findRegionPainEntry(region.id, null) : null;

        const buildHtml = () => {
            const sideHtml = region.bilateral
                ? '<div class="arp-row arp-sides">' +
                      '<button type="button" class="arp-side" data-side="left">Links</button>' +
                      '<button type="button" class="arp-side" data-side="right">Rechts</button>' +
                  '</div>'
                : '';
            const nrsButtons = NRS_COLORS.map((col, i) => {
                const ex = region.bilateral
                    ? findRegionPainEntry(region.id, selectedSide)
                    : existingMid;
                const active = ex && ex.nrs === i ? ' active' : '';
                return `<button type="button" class="arp-nrs${active}" data-nrs="${i}" style="--nrs:${col}">${i}</button>`;
            }).join('');
            return (
                '<div class="arp-title">' + escapeAttr(region.label) + '</div>' +
                sideHtml +
                '<div class="arp-row arp-nrs-row">' + nrsButtons + '</div>' +
                '<div class="arp-row arp-actions">' +
                    '<button type="button" class="arp-clear">Entfernen</button>' +
                    '<button type="button" class="arp-cancel">Abbrechen</button>' +
                '</div>'
            );
        };

        wrap.innerHTML = buildHtml();
        stage.appendChild(wrap);

        // Position innerhalb der Stage halten
        const wr = wrap.getBoundingClientRect();
        if (wr.right > stageRect.right - 4) {
            wrap.style.left = (stageRect.width - wr.width - 8) + 'px';
        }
        if (wr.left < stageRect.left + 4) {
            wrap.style.left = '8px';
        }
        if (wr.top < stageRect.top + 4) {
            wrap.style.top = '8px';
            wrap.style.transform = 'translate(-50%, 0)';
        }

        const close = () => {
            wrap.remove();
            document.removeEventListener('click', outside, true);
        };
        const outside = (ev) => {
            if (!wrap.contains(ev.target)) close();
        };

        const bind = () => {
            // Seiten-Buttons (bilateral)
            wrap.querySelectorAll('.arp-side').forEach(b => {
                if (selectedSide === b.dataset.side) b.classList.add('active');
                b.addEventListener('click', (ev) => {
                    ev.stopPropagation();
                    selectedSide = b.dataset.side;
                    wrap.innerHTML = buildHtml();
                    bind();
                });
            });
            // NRS-Buttons
            wrap.querySelectorAll('.arp-nrs').forEach(b => {
                b.addEventListener('click', (ev) => {
                    ev.stopPropagation();
                    if (region.bilateral && selectedSide == null) {
                        // Seite zwingend zuerst wählen
                        wrap.querySelectorAll('.arp-side').forEach(s => s.classList.add('arp-side-needed'));
                        return;
                    }
                    const nrs = parseInt(b.dataset.nrs, 10);
                    close();
                    onPick(selectedSide || null, nrs);
                });
            });
            // Eintrag entfernen
            wrap.querySelector('.arp-clear')?.addEventListener('click', (ev) => {
                ev.stopPropagation();
                if (region.bilateral && selectedSide == null) {
                    // Beide Seiten löschen
                    state.markers = state.markers.filter(m =>
                        !(m && m.type === 'region' && m.region === region.id)
                    );
                } else {
                    state.markers = state.markers.filter(m =>
                        !(m && m.type === 'region' && m.region === region.id && (m.side || null) === (selectedSide || null))
                    );
                }
                close();
                renderOverlay();
                renderMarkerList();
                paintRegions();
                syncHidden();
            });
            // Abbrechen
            wrap.querySelector('.arp-cancel')?.addEventListener('click', (ev) => {
                ev.stopPropagation();
                close();
            });
        };
        bind();

        setTimeout(() => document.addEventListener('click', outside, true), 0);
    }

    // Region-Schmerz-Eintrag setzen oder aktualisieren
    function setRegionPain(region, side, nrs) {
        const color = NRS_COLORS[Math.max(0, Math.min(10, nrs|0))];
        const key = regionKey(region.id, side);
        // Existierenden Eintrag entfernen
        state.markers = state.markers.filter(m =>
            !(m && m.type === 'region' && regionKey(m.region, m.side) === key)
        );
        state.markers.push({
            type:      'region',
            id:        'r_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
            region:    region.id,
            label:     region.label,
            side:      side || null,
            nrs:       nrs,
            color:     color,
            createdAt: new Date().toISOString(),
        });
        renderOverlay();
        renderMarkerList();
        paintRegions();
        syncHidden();
    }

    // Region-Pfade entsprechend der gespeicherten Schmerz-Einträge einfärben.
    // Mehrfache Einträge (left+right) → höchster NRS-Wert bestimmt die Farbe.
    function paintRegions() {
        const sil = ROOT.querySelector('.anatomy-silhouette');
        if (!sil) return;
        const paths = sil.querySelectorAll('path.anatomy-region');
        // Gruppiere Region-Schmerz-Einträge pro region.id
        const byRegion = {};
        (state.markers || []).forEach(m => {
            if (!m || m.type !== 'region' || !m.region) return;
            if (!byRegion[m.region] || (m.nrs|0) > (byRegion[m.region].nrs|0)) {
                byRegion[m.region] = m;
            }
        });
        paths.forEach(p => {
            const id = p.dataset.region;
            const entry = byRegion[id];
            if (entry) {
                p.setAttribute('fill', entry.color);
                p.setAttribute('fill-opacity', '0.65');
                p.setAttribute('stroke', entry.color);
                p.setAttribute('stroke-opacity', '0.9');
                p.setAttribute('stroke-width', '1.2');
                p.classList.add('anatomy-region-painted');
            } else {
                p.setAttribute('fill', MUS_FILL);
                p.setAttribute('fill-opacity', '0');
                p.setAttribute('stroke', MUS_STROKE);
                p.setAttribute('stroke-opacity', '0.35');
                p.setAttribute('stroke-width', '0.6');
                p.classList.remove('anatomy-region-painted');
            }
        });
    }

    function showSidePicker(stage, clientX, clientY, region, onPick) {
        document.querySelectorAll('.anatomy-side-picker').forEach(el => el.remove());
        const stageRect = stage.getBoundingClientRect();
        const wrap = document.createElement('div');
        wrap.className = 'anatomy-side-picker';
        wrap.style.left = (clientX - stageRect.left) + 'px';
        wrap.style.top  = (clientY - stageRect.top)  + 'px';
        wrap.innerHTML =
            '<div class="anatomy-side-picker-label">' + escapeAttr(region.label) + '</div>' +
            '<div class="anatomy-side-picker-buttons">' +
                '<button type="button" data-side="left">Links</button>' +
                '<button type="button" data-side="right">Rechts</button>' +
                '<button type="button" data-side="">Mittig</button>' +
            '</div>';
        stage.appendChild(wrap);
        const close = () => { wrap.remove(); document.removeEventListener('click', outside, true); };
        const outside = (ev) => { if (!wrap.contains(ev.target)) close(); };
        wrap.querySelectorAll('button').forEach(b => b.addEventListener('click', (ev) => {
            ev.stopPropagation();
            const side = b.dataset.side || null;
            close();
            onPick(side);
        }));
        setTimeout(() => document.addEventListener('click', outside, true), 0);
    }

    function renderOverlay() {
        const overlay = ROOT.querySelector('.anatomy-overlay');
        if (!overlay) return;
        overlay.innerHTML = '';

        // Zeichnungen
        (state.drawings || []).forEach(path => {
            if (!path || !Array.isArray(path.points) || path.points.length < 2) return;
            const d = 'M' + path.points.map(p => p.join(',')).join(' L');
            const el = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            el.setAttribute('d', d);
            el.setAttribute('fill', 'none');
            el.setAttribute('stroke', path.color || '#ef4444');
            el.setAttribute('stroke-width', '2.5');
            el.setAttribute('stroke-linecap', 'round');
            el.setAttribute('stroke-linejoin', 'round');
            overlay.appendChild(el);
        });

        // Aktiver Pfad
        if (drawingPath && drawingPath.points.length > 1) {
            const d = 'M' + drawingPath.points.map(p => p.join(',')).join(' L');
            const el = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            el.setAttribute('d', d);
            el.setAttribute('fill', 'none');
            el.setAttribute('stroke', drawingPath.color);
            el.setAttribute('stroke-width', '2.5');
            overlay.appendChild(el);
        }

        // Marker
        (state.markers || []).forEach(m => {
            if (!m || typeof m.x !== 'number' || typeof m.y !== 'number') return;
            const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            c.setAttribute('class', 'anatomy-marker');
            c.setAttribute('cx', String(m.x));
            c.setAttribute('cy', String(m.y));
            c.setAttribute('r',  '9');
            c.setAttribute('fill', m.color || '#ef4444');
            c.setAttribute('fill-opacity', '.75');
            c.setAttribute('stroke', '#fff');
            c.setAttribute('stroke-width', '2');
            c.dataset.id = m.id;
            c.addEventListener('click', (ev) => {
                ev.stopPropagation();
                if (state.tool === 'erase') {
                    state.markers = state.markers.filter(x => x.id !== m.id);
                    renderOverlay(); renderMarkerList(); syncHidden();
                } else {
                    const note = prompt('Notiz zu dieser Markierung (optional):', m.note || '');
                    if (note !== null) { m.note = note; renderMarkerList(); syncHidden(); }
                }
            });
            overlay.appendChild(c);
        });
    }

    function renderLegend() {
        const leg = ROOT.querySelector('.anatomy-legend');
        if (!leg) return;
        leg.innerHTML = COLORS.map(c =>
            `<span class="anatomy-legend-item"><span class="anatomy-legend-dot" style="background:${c.hex}"></span>${c.label}</span>`
        ).join('');
    }

    function renderMarkerList() {
        const list = ROOT.querySelector('.anatomy-marker-list');
        if (!list) return;
        if (!state.markers.length) {
            list.innerHTML = '<div class="text-muted small" style="padding:.5rem;">Noch keine Markierungen.</div>';
            return;
        }
        list.innerHTML = '';
        // Sortierung: Region-Schmerz-Einträge zuerst (nach NRS absteigend), dann Punkt-Marker
        const sorted = state.markers.slice().sort((a, b) => {
            const aReg = a && a.type === 'region' ? 1 : 0;
            const bReg = b && b.type === 'region' ? 1 : 0;
            if (aReg !== bReg) return bReg - aReg;
            if (aReg && bReg) return (b.nrs || 0) - (a.nrs || 0);
            return 0;
        });

        sorted.forEach(m => {
            const row = document.createElement('div');
            row.className = 'marker-row';
            const sideLabel = m.side === 'left' ? 'L' : (m.side === 'right' ? 'R' : '');
            const regionLabel = m.label || m.region || '';

            if (m.type === 'region') {
                // Region-Schmerz-Eintrag: Label · Seite · NRS
                const sideTxt = sideLabel ? ' · ' + sideLabel : '';
                row.innerHTML = `
                    <span class="marker-dot" style="background:${m.color}"></span>
                    <span class="marker-text">
                        <span class="marker-region">${escapeHtml(regionLabel)}${sideTxt}</span>
                        <span class="marker-note">NRS ${m.nrs ?? '–'} / 10</span>
                    </span>
                    <button type="button" class="marker-remove" title="Entfernen">×</button>
                `;
            } else {
                // Punkt-Marker
                const meta = regionLabel
                    ? `<span class="marker-region">${escapeHtml(regionLabel)}${sideLabel ? ' · ' + sideLabel : ''}</span>`
                    : '';
                row.innerHTML = `
                    <span class="marker-dot" style="background:${m.color}"></span>
                    <span class="marker-text">${meta}<span class="marker-note">${escapeHtml(m.note || '(ohne Notiz)')}</span></span>
                    <button type="button" class="marker-remove" title="Entfernen">×</button>
                `;
            }

            row.querySelector('.marker-remove').addEventListener('click', () => {
                state.markers = state.markers.filter(x => x.id !== m.id);
                renderOverlay(); renderMarkerList(); paintRegions(); syncHidden();
            });
            list.appendChild(row);
        });
    }

    function renderNrsScale() {
        const container = ROOT.querySelector('.anatomy-nrs-scale');
        if (!container) return;
        container.innerHTML = '';

        const NRS_COLORS = [
            '#22c55e','#4ade80','#a3e635',
            '#facc15','#fb923c',
            '#f97316','#ef4444',
            '#dc2626','#b91c1c','#991b1b','#7f1d1d',
        ];

        const wrap = document.createElement('div');
        wrap.className = 'anatomy-nrs-wrap';

        for (let i = 0; i <= 10; i++) {
            const btn = document.createElement('button');
            btn.type        = 'button';
            btn.textContent = String(i);
            btn.className   = 'anatomy-nrs-btn' + (state.nrs === i ? ' active' : '');
            btn.style.setProperty('--nrs-color', NRS_COLORS[i]);
            btn.title       = i === 0 ? 'Kein Schmerz' : i <= 3 ? 'Leicht' : i <= 6 ? 'Mäßig' : i <= 8 ? 'Stark' : 'Maximal';
            btn.addEventListener('click', () => {
                state.nrs = i;
                if (nrsInput) nrsInput.value = String(i);
                // Aktiv-Klasse aktualisieren ohne komplettes Re-Render
                wrap.querySelectorAll('.anatomy-nrs-btn').forEach((b, idx) => {
                    b.classList.toggle('active', idx === i);
                });
            });
            wrap.appendChild(btn);
        }

        const labels = document.createElement('div');
        labels.className = 'anatomy-nrs-labels';
        labels.innerHTML = '<span>0 – Kein Schmerz</span><span>5 – Mäßig</span><span>10 – Max</span>';

        container.appendChild(wrap);
        container.appendChild(labels);
    }

    function syncHidden() {
        try {
            if (hiddenSpecies)  hiddenSpecies.value  = state.species || '';
            if (hiddenMarkers)  hiddenMarkers.value  = JSON.stringify(state.markers  || []);
            if (hiddenDrawings) hiddenDrawings.value = JSON.stringify(state.drawings || []);
        } catch (e) {
            console.error('[Befund Anatomy] syncHidden fehlgeschlagen:', e);
        }
    }

    function round(n)       { return Math.round(n * 10) / 10; }
    function escapeHtml(s)  { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    // Physio-Bereiche Chip-Toggle
    document.querySelectorAll('.physio-bereich-chip').forEach(chip => {
        const cb = chip.querySelector('input[type="checkbox"]');
        if (!cb) return;
        chip.classList.toggle('checked', cb.checked);
        chip.addEventListener('click', (ev) => {
            if (ev.target !== cb) { cb.checked = !cb.checked; }
            chip.classList.toggle('checked', cb.checked);
        });
    });

    // Textbausteine einfügen
    document.addEventListener('click', (ev) => {
        const btn = ev.target.closest('[data-baustein-insert]');
        if (!btn) return;
        const target = document.querySelector(btn.dataset.bausteinTarget || '');
        const text   = btn.dataset.bausteinText || '';
        if (!target || !text) return;
        const sep = (target.value && !target.value.endsWith('\n')) ? '\n' : '';
        target.value = target.value + sep + text;
        target.focus();
    });

    // KI-Strukturieren
    const kiBtn = document.getElementById('befund-ki-strukturieren');
    if (kiBtn) {
        kiBtn.addEventListener('click', async () => {
            const notesField = document.querySelector('textarea[name="verlauf_notizen"]');
            const csrf       = document.querySelector('input[name="_csrf_token"]')?.value || '';
            const markerSum  = state.markers.map(m => m.note || 'ohne Notiz').join('; ');
            const bereiche   = Array.from(document.querySelectorAll('input[name="physio_bereiche[]"]:checked')).map(x => x.value).join(', ');
            try {
                kiBtn.disabled = true;
                kiBtn.textContent = 'Strukturiere…';
                const form = new URLSearchParams({
                    _csrf_token: csrf,
                    text:        notesField?.value || '',
                    markers:     markerSum,
                    bereiche:    bereiche,
                }).toString();
                const res = await fetch('/api/befund/ki/strukturieren', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body:    form,
                });
                const data = await res.json();
                if (data.summary && notesField) {
                    notesField.value = (notesField.value ? notesField.value + '\n\n' : '') + data.summary;
                }
            } catch (e) {
                console.error('[Befund KI]', e);
                alert('KI-Strukturierung momentan nicht verfügbar.');
            } finally {
                kiBtn.disabled = false;
                kiBtn.textContent = 'Befund strukturieren';
            }
        });
    }

    // Vorlagen anwenden
    const vorlageSelect = document.getElementById('befund-vorlage-select');
    if (vorlageSelect) {
        vorlageSelect.addEventListener('change', async () => {
            const id = vorlageSelect.value;
            if (!id) return;
            try {
                const res  = await fetch('/api/befund/vorlagen/' + encodeURIComponent(id));
                const data = await res.json();
                const felder = data.felder || {};
                if (!confirm('Vorlage "' + (data.name || '') + '" anwenden? Bestehende Felder werden überschrieben.')) {
                    vorlageSelect.value = '';
                    return;
                }
                Object.keys(felder).forEach(name => {
                    const el = document.querySelector(`[name="${name}"]`);
                    if (el) el.value = felder[name];
                });
            } catch (e) {
                console.error('[Befund Vorlagen]', e);
                alert('Vorlage konnte nicht geladen werden.');
            } finally {
                vorlageSelect.value = '';
            }
        });
    }

    } // end initAnatomy

    // Globaler Expose: ermöglicht Initialisierung im Patientenmodal
    // (ohne Seitenwechsel, dynamisch per createElement geladen)
    window.befundAnatomyInit = function (root) {
        if (!root) return;
        root.removeAttribute('data-booted'); // erlaubt Re-Init auf neuem Container
        initAnatomy(root);
    };
})();
