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

    // Extrem schematische SVG-Silhouetten (keine externen Assets nötig).
    // viewBox ist immer 0 0 500 300 — Marker-Koordinaten sind in diesem
    // viewBox-Koordinatensystem gespeichert.
    // Silhouetten-Farben: gut sichtbar auf hellem UND dunklem Hintergrund
    const SIL_FILL   = '#7d9bb5';  // mittleres Blau-Grau
    const SIL_STROKE = '#3d5a72';  // dunkles Blau-Grau für Kontur
    const SIL_SW     = '2.5';      // stroke-width

    const SILHOUETTES = {
        // ── Hund (Seitenansicht) ─────────────────────────────────
        dog: `
            <g fill="${SIL_FILL}" stroke="${SIL_STROKE}" stroke-width="${SIL_SW}" stroke-linejoin="round" stroke-linecap="round">
                <ellipse cx="280" cy="155" rx="150" ry="52"/>
                <ellipse cx="118" cy="140" rx="30" ry="28"/>
                <ellipse cx="73" cy="138" rx="48" ry="38"/>
                <path d="M36,152 C24,147 13,155 15,167 C17,178 30,182 45,178 C58,174 62,162 54,154 Z"/>
                <path d="M52,106 C38,110 30,128 34,148 C38,166 53,172 66,168 C79,163 83,148 78,130 C73,112 63,106 52,106 Z"/>
                <circle cx="62" cy="124" r="7" fill="${SIL_STROKE}" stroke="none"/>
                <ellipse cx="17" cy="165" rx="7" ry="6" fill="${SIL_STROKE}" stroke="none"/>
                <path d="M162,200 L157,274 Q165,280 172,274 L172,200 Z"/>
                <path d="M200,198 L195,272 Q203,278 210,272 L210,198 Z"/>
                <path d="M320,200 C313,218 314,238 322,252 C316,258 313,267 318,274 L334,274 C337,266 333,257 328,252 C334,237 333,217 330,200 Z"/>
                <path d="M356,198 C349,216 350,236 358,250 C352,256 349,265 354,272 L370,272 C373,264 369,255 364,250 C370,235 369,215 366,198 Z"/>
                <path d="M420,128 C445,104 458,88 452,70 C447,56 430,55 420,66" fill="none" stroke-width="9" stroke-linecap="round"/>
            </g>
        `,
        // ── Katze (Seitenansicht) ────────────────────────────────
        cat: `
            <g fill="${SIL_FILL}" stroke="${SIL_STROKE}" stroke-width="${SIL_SW}" stroke-linejoin="round" stroke-linecap="round">
                <ellipse cx="258" cy="152" rx="128" ry="48"/>
                <ellipse cx="118" cy="144" rx="28" ry="25"/>
                <circle cx="76" cy="138" r="44"/>
                <polygon points="50,98 64,40 86,97"/>
                <polygon points="86,95 104,37 122,95"/>
                <path d="M38,148 C26,143 15,152 17,163 C19,174 33,178 47,173 C60,168 63,155 55,149 Z"/>
                <ellipse cx="64" cy="124" rx="7" ry="9" fill="${SIL_STROKE}" stroke="none"/>
                <polygon points="50,144 58,136 66,144 58,152" fill="${SIL_STROKE}" stroke="none"/>
                <path d="M150,200 L146,270 Q154,276 161,270 L161,200 Z"/>
                <path d="M186,198 L182,268 Q190,274 197,268 L197,198 Z"/>
                <path d="M306,198 C300,216 302,236 309,250 C303,256 301,265 306,272 L321,272 C323,264 320,255 315,250 C321,235 319,215 316,198 Z"/>
                <path d="M338,196 C332,214 334,234 341,248 C335,254 333,263 338,270 L353,270 C355,262 352,253 347,248 C353,233 351,213 348,196 Z"/>
                <path d="M386,152 C415,130 442,110 448,82 C452,60 436,48 422,60 C410,70 412,90 420,104" fill="none" stroke-width="11" stroke-linecap="round"/>
            </g>
        `,
        // ── Pferd (Seitenansicht) ────────────────────────────────
        horse: `
            <g fill="${SIL_FILL}" stroke="${SIL_STROKE}" stroke-width="${SIL_SW}" stroke-linejoin="round" stroke-linecap="round">
                <ellipse cx="300" cy="144" rx="168" ry="62"/>
                <ellipse cx="422" cy="112" rx="52" ry="45"/>
                <path d="M155,100 C148,80 138,62 122,56 C106,50 88,60 86,80 C84,98 96,114 112,118 C130,122 150,112 158,102 Z"/>
                <path d="M86,80 C72,70 58,72 46,86 C34,100 36,122 50,136 C62,148 78,150 90,142 C104,132 106,110 96,94 Z"/>
                <path d="M50,136 C38,138 26,148 28,162 C30,174 44,178 58,172 C72,166 76,150 66,140 Z"/>
                <path d="M90,66 C86,48 94,30 104,30 C114,32 118,48 112,64 Z"/>
                <circle cx="66" cy="100" r="7" fill="${SIL_STROKE}" stroke="none"/>
                <ellipse cx="26" cy="160" rx="7" ry="5" fill="${SIL_STROKE}" stroke="none"/>
                <path d="M106,62 C114,80 118,100 118,118" fill="none" stroke-width="12" stroke-linecap="round"/>
                <path d="M168,194 L162,278 Q171,284 179,278 L179,194 Z"/>
                <path d="M207,192 L201,276 Q210,282 218,276 L218,192 Z"/>
                <path d="M362,194 C358,214 360,238 366,256 C360,262 357,271 362,278 L378,278 C380,270 376,261 372,256 C374,238 372,213 370,194 Z"/>
                <path d="M398,192 C394,212 396,236 402,254 C396,260 393,269 398,276 L414,276 C416,268 412,259 408,254 C410,236 408,211 406,192 Z"/>
                <path d="M452,130 C476,110 490,88 483,66 C476,50 458,50 447,64" fill="none" stroke-width="14" stroke-linecap="round"/>
                <path d="M452,130 C478,148 491,172 482,196 C476,210 460,214 448,204" fill="none" stroke-width="9" stroke-linecap="round"/>
            </g>
        `,
    };

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
    try {
        renderToolbar();
        renderStage();
        renderLegend();
        renderNrsScale();
        renderMarkerList();
        syncHidden();
    } catch (e) {
        console.error('[Befund Anatomy] Initialisierung fehlgeschlagen:', e);
        ROOT.innerHTML = '<div class="alert alert-warning">Die interaktive Anatomie konnte nicht geladen werden. Du kannst den Befund trotzdem normal bearbeiten.</div>';
        return;
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
            state.markers.push({
                id:      'm_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
                x: round(x), y: round(y),
                color:   state.color,
                note:    '',
                createdAt: new Date().toISOString(),
            });
            renderOverlay();
            renderMarkerList();
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
            row.innerHTML = `
                <span class="marker-dot" style="background:${m.color}"></span>
                <span>${escapeHtml(m.note || '(ohne Notiz)')}</span>
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
