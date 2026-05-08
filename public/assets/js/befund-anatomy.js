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

    // ── Veterinary Anatomy Engine — Phase 1 (SVG Layer System) ────
    // viewBox: 0 0 500 300 — Tiere im lateralen Profil, Blickrichtung links.
    // body  = kontinuierliche Außenkontur (eine path-Definition)
    // regions = klickbare anatomische Zonen mit data-region + bilateral-Flag
    const SIL_FILL    = '#c9d4de';  // helle Hautfarbe
    const SIL_STROKE  = '#2c3e50';  // dunkle Kontur
    const SIL_SW      = '2.2';
    const MUS_FILL    = '#9fb1c2';  // Muskel-Layer Grundfarbe
    const MUS_STROKE  = '#5b738a';

    const ANATOMY = {
        // ── Hund (lateral, blickt nach links) ────────────────────
        dog: {
            body: 'M28,148 Q22,144 26,140 L70,134 Q82,130 88,122 Q66,108 70,96 Q84,86 102,94 L108,96 Q112,76 124,72 L130,76 Q134,96 132,112 Q150,102 180,94 Q240,86 305,94 Q360,100 400,112 Q422,96 448,76 Q466,74 462,94 Q456,116 428,134 Q418,152 412,172 L414,224 Q416,248 430,260 L430,266 Q412,270 388,266 L388,250 Q386,216 376,196 Q352,194 280,196 Q230,212 208,212 Q196,218 192,242 L192,260 Q198,266 178,266 Q160,266 156,260 L156,242 Q154,216 150,196 Q144,176 138,166 Q124,160 96,160 Q72,158 48,154 Q34,152 28,148 Z',
            regions: [
                { id: 'head',     label: 'Kopf',          bilateral: false, d: 'M28,148 Q22,144 26,140 L70,134 Q82,130 88,122 Q66,108 70,96 Q84,86 102,94 L108,96 Q112,76 124,72 L130,76 Q134,96 132,112 Q120,118 108,124 Q92,138 80,150 Q60,156 40,154 Q32,152 28,148 Z' },
                { id: 'cervical', label: 'HWS / Hals',    bilateral: false, d: 'M132,112 Q150,102 180,94 Q186,118 178,140 Q150,150 132,148 Q124,140 132,112 Z' },
                { id: 'shoulder', label: 'Schulter',      bilateral: true,  d: 'M178,140 Q186,118 196,108 Q220,108 230,128 Q228,156 210,170 Q188,170 178,140 Z' },
                { id: 'thoracic', label: 'BWS',           bilateral: false, d: 'M196,108 Q240,96 290,98 L290,150 Q260,154 230,148 Q220,128 196,108 Z' },
                { id: 'lumbar',   label: 'LWS',           bilateral: false, d: 'M290,98 Q330,98 370,104 L370,156 Q330,160 290,150 Z' },
                { id: 'croup',    label: 'Kruppe / ISG',  bilateral: false, d: 'M370,104 Q400,112 412,128 Q416,154 400,170 Q386,172 370,156 Z' },
                { id: 'tail',     label: 'Rute',          bilateral: false, d: 'M412,128 Q422,96 448,76 Q466,74 462,94 Q456,116 432,128 Q418,134 412,128 Z' },
                { id: 'biceps_femoris', label: 'Biceps femoris', bilateral: true, d: 'M376,170 Q400,170 412,172 L414,224 Q400,224 386,216 Q380,194 376,170 Z' },
                { id: 'hock',     label: 'Sprunggelenk',  bilateral: true,  d: 'M388,224 L414,224 L414,260 Q412,266 388,266 L388,250 Z' },
                { id: 'thorax_wall', label: 'Brustkorb',  bilateral: false, d: 'M210,170 Q260,180 290,180 Q282,202 240,206 Q210,206 196,196 Q200,184 210,170 Z' },
                { id: 'triceps',  label: 'Triceps brachii', bilateral: true, d: 'M150,170 Q180,170 196,196 Q188,212 168,214 Q146,212 138,196 Q142,180 150,170 Z' },
                { id: 'forearm',  label: 'Unterarm',      bilateral: true,  d: 'M150,196 Q170,200 168,214 L156,260 Q150,266 156,242 Q154,216 150,196 Z' },
                { id: 'carpus',   label: 'Karpalgelenk',  bilateral: true,  d: 'M156,242 L192,242 L192,260 Q198,266 178,266 Q160,266 156,260 Z' },
            ],
        },
        // ── Katze (lateral, blickt nach links) ───────────────────
        cat: {
            body: 'M22,156 Q18,152 22,148 L58,142 Q70,140 76,134 Q60,124 60,108 Q56,90 70,84 L82,118 L92,118 L96,82 Q104,70 116,76 Q124,90 122,118 Q130,116 152,108 Q210,98 270,108 Q320,116 360,124 Q380,108 412,80 Q444,52 462,72 Q470,92 442,118 Q412,144 388,152 L388,212 Q392,234 408,254 Q406,260 384,260 L384,242 Q380,210 372,194 Q344,196 280,200 Q230,212 206,212 Q196,218 192,238 L192,256 Q198,262 178,262 Q160,262 156,256 L156,238 Q154,210 148,194 Q134,180 100,168 Q60,164 36,160 Q26,158 22,156 Z',
            regions: [
                { id: 'head',     label: 'Kopf',          bilateral: false, d: 'M22,156 Q18,152 22,148 L58,142 Q70,140 76,134 Q60,124 60,108 Q56,90 70,84 L82,118 L92,118 L96,82 Q104,70 116,76 Q124,90 122,118 Q108,128 90,136 Q62,154 30,158 Q24,158 22,156 Z' },
                { id: 'cervical', label: 'HWS / Hals',    bilateral: false, d: 'M122,118 Q140,114 160,112 Q170,134 158,148 Q138,150 122,144 Q120,130 122,118 Z' },
                { id: 'shoulder', label: 'Schulter',      bilateral: true,  d: 'M158,148 Q170,134 188,128 Q208,128 218,148 Q216,170 198,180 Q176,178 158,148 Z' },
                { id: 'thoracic', label: 'BWS',           bilateral: false, d: 'M188,128 Q220,118 270,120 L270,164 Q230,168 208,160 Q200,142 188,128 Z' },
                { id: 'lumbar',   label: 'LWS',           bilateral: false, d: 'M270,120 Q310,120 350,128 L350,168 Q310,172 270,164 Z' },
                { id: 'croup',    label: 'Kruppe / ISG',  bilateral: false, d: 'M350,128 Q372,140 388,152 Q394,178 376,190 Q360,188 350,168 Z' },
                { id: 'tail',     label: 'Rute',          bilateral: false, d: 'M388,152 Q412,144 442,118 Q470,92 462,72 Q444,52 412,80 Q392,108 388,140 Q386,148 388,152 Z' },
                { id: 'biceps_femoris', label: 'Biceps femoris', bilateral: true, d: 'M360,188 Q380,190 388,212 L388,254 Q374,254 366,238 Q358,214 360,188 Z' },
                { id: 'hock',     label: 'Sprunggelenk',  bilateral: true,  d: 'M366,238 L388,254 L384,260 Q368,260 366,238 Z' },
                { id: 'thorax_wall', label: 'Brustkorb',  bilateral: false, d: 'M198,180 Q240,190 270,190 Q262,210 224,212 Q198,212 188,200 Q192,188 198,180 Z' },
                { id: 'triceps',  label: 'Triceps brachii', bilateral: true, d: 'M148,180 Q176,178 198,196 Q190,212 170,214 Q146,212 138,198 Q140,186 148,180 Z' },
                { id: 'forearm',  label: 'Unterarm',      bilateral: true,  d: 'M148,196 Q168,200 170,214 L156,256 Q148,256 148,238 Q150,216 148,196 Z' },
                { id: 'carpus',   label: 'Karpalgelenk',  bilateral: true,  d: 'M156,238 L192,238 L192,256 Q198,262 178,262 Q160,262 156,256 Z' },
            ],
        },
        // ── Pferd (lateral, blickt nach links) ───────────────────
        horse: {
            body: 'M30,86 Q24,80 32,74 L72,62 Q86,58 90,46 Q88,28 102,26 Q116,28 120,52 L122,72 Q124,90 118,110 Q124,128 122,148 L114,158 Q98,160 80,158 Q70,170 70,184 Q90,196 130,196 Q200,196 280,196 Q360,196 420,196 Q444,180 466,150 Q480,128 472,110 Q448,114 434,128 L434,170 Q436,196 446,222 Q450,252 458,272 Q446,278 422,272 L420,256 Q412,228 396,210 Q368,212 320,212 Q260,212 200,212 Q170,212 156,200 Q150,222 144,250 Q146,266 154,272 Q142,278 122,272 L120,250 Q118,220 116,196 Q110,180 96,170 Q72,166 50,162 Q36,158 30,148 Z',
            regions: [
                { id: 'head',     label: 'Kopf',          bilateral: false, d: 'M30,86 Q24,80 32,74 L72,62 Q86,58 90,46 Q88,28 102,26 Q116,28 120,52 L122,72 Q124,90 118,110 Q102,118 78,118 Q50,114 36,108 Q28,98 30,86 Z' },
                { id: 'cervical', label: 'HWS / Hals',    bilateral: false, d: 'M118,110 Q140,116 168,128 Q172,154 152,168 Q128,170 114,158 Q116,128 118,110 Z' },
                { id: 'shoulder', label: 'Schulter',      bilateral: true,  d: 'M152,168 Q168,154 188,156 Q210,160 218,184 Q210,200 184,202 Q160,200 152,168 Z' },
                { id: 'withers',  label: 'Widerrist',     bilateral: false, d: 'M168,128 Q200,118 240,118 L240,156 Q210,162 188,156 Q172,146 168,128 Z' },
                { id: 'thoracic', label: 'BWS / Sattellage', bilateral: false, d: 'M240,118 Q300,116 360,122 L360,160 Q300,168 240,156 Z' },
                { id: 'lumbar',   label: 'LWS',           bilateral: false, d: 'M360,122 Q400,128 432,134 L432,168 Q400,172 360,160 Z' },
                { id: 'croup',    label: 'Kruppe / ISG',  bilateral: false, d: 'M432,134 Q448,114 472,110 Q480,128 466,150 Q448,170 432,168 Z' },
                { id: 'tail',     label: 'Schweifrübe',   bilateral: false, d: 'M432,168 Q448,180 446,222 Q444,252 458,272 Q446,278 422,272 L420,256 Q418,224 432,168 Z' },
                { id: 'biceps_femoris', label: 'Hinterhand-Muskulatur', bilateral: true, d: 'M396,210 Q420,212 432,210 L432,248 Q420,256 396,260 Q386,232 396,210 Z' },
                { id: 'gaskin',   label: 'Unterschenkel', bilateral: true, d: 'M396,260 Q412,228 420,256 L422,272 Q400,278 396,260 Z' },
                { id: 'hock',     label: 'Sprunggelenk',  bilateral: true,  d: 'M396,260 L420,272 Q400,278 396,260 Z' },
                { id: 'thorax_wall', label: 'Brustkorb',  bilateral: false, d: 'M184,202 Q260,210 360,210 Q340,224 280,228 Q220,228 188,224 Q176,214 184,202 Z' },
                { id: 'triceps',  label: 'Triceps / Oberarm', bilateral: true, d: 'M150,178 Q178,180 188,202 Q180,222 158,222 Q138,218 134,200 Q138,184 150,178 Z' },
                { id: 'forearm',  label: 'Unterarm',      bilateral: true,  d: 'M150,200 Q172,210 158,222 L150,250 Q142,254 144,228 Q146,212 150,200 Z' },
                { id: 'carpus',   label: 'Karpalgelenk',  bilateral: true,  d: 'M144,250 Q158,256 156,272 Q142,278 122,272 L120,256 Q132,250 144,250 Z' },
            ],
        },
    };

    // Kompatibilitäts-Wrapper — alte SILHOUETTES-API liefert vollständige Layer-Komposition
    const SILHOUETTES = new Proxy({}, {
        get(_, species) {
            const data = ANATOMY[species] || ANATOMY.dog;
            const regionPaths = data.regions.map(r =>
                `<path d="${r.d}" class="anatomy-region" data-region="${r.id}" data-label="${escapeAttr(r.label)}" data-bilateral="${r.bilateral ? '1' : '0'}" fill="${MUS_FILL}" stroke="${MUS_STROKE}" stroke-width="0.8"/>`
            ).join('');
            return `
                <g class="layer-contour" fill="${SIL_FILL}" stroke="${SIL_STROKE}" stroke-width="${SIL_SW}" stroke-linejoin="round" stroke-linecap="round">
                    <path d="${data.body}"/>
                </g>
                <g class="layer-muscles" stroke-linejoin="round" stroke-linecap="round" pointer-events="all">
                    ${regionPaths}
                </g>
            `;
        },
    });

    function escapeAttr(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
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
            if (state.tool !== 'marker') return;
            const { x, y } = coords(ev);
            const region = detectRegion(stage, x, y);
            const place = (side) => {
                state.markers.push({
                    id:      'm_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
                    x: round(x), y: round(y),
                    color:   state.color,
                    note:    '',
                    region:  region ? region.id    : null,
                    label:   region ? region.label : null,
                    side:    side || null,
                    createdAt: new Date().toISOString(),
                });
                renderOverlay();
                renderMarkerList();
                syncHidden();
            };
            if (region && region.bilateral) {
                showSidePicker(stage, ev.clientX, ev.clientY, region, (side) => place(side));
            } else {
                place(null);
            }
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
        state.markers.forEach(m => {
            const row = document.createElement('div');
            row.className = 'marker-row';
            const sideLabel = m.side === 'left' ? 'L' : (m.side === 'right' ? 'R' : '');
            const regionLabel = m.label || m.region || '';
            const meta = regionLabel
                ? `<span class="marker-region">${escapeHtml(regionLabel)}${sideLabel ? ' · ' + sideLabel : ''}</span>`
                : '';
            row.innerHTML = `
                <span class="marker-dot" style="background:${m.color}"></span>
                <span class="marker-text">${meta}<span class="marker-note">${escapeHtml(m.note || '(ohne Notiz)')}</span></span>
                <button type="button" class="marker-remove" title="Entfernen">×</button>
            `;
            row.querySelector('.marker-remove').addEventListener('click', () => {
                state.markers = state.markers.filter(x => x.id !== m.id);
                renderOverlay(); renderMarkerList(); syncHidden();
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
