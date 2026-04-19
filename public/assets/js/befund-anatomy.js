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
            <g fill="${SIL_FILL}" stroke="${SIL_STROKE}" stroke-width="${SIL_SW}" stroke-linejoin="round">
                <!-- Rumpf -->
                <ellipse cx="235" cy="168" rx="138" ry="58"/>
                <!-- Hals -->
                <path d="M138 140 Q125 115 130 100 Q148 92 162 118 Q158 136 155 155" stroke-width="1"/>
                <!-- Kopf -->
                <ellipse cx="92" cy="132" rx="46" ry="42"/>
                <!-- Schnauze -->
                <ellipse cx="57" cy="150" rx="22" ry="15"/>
                <!-- Ohr (hängend) -->
                <path d="M72 98 Q55 72 65 58 Q82 50 96 78 Q100 95 92 102"/>
                <!-- Auge -->
                <circle cx="78" cy="122" r="5" fill="${SIL_STROKE}" stroke="none"/>
                <!-- Nase -->
                <ellipse cx="43" cy="148" rx="7" ry="5" fill="${SIL_STROKE}" stroke="none"/>
                <!-- Vorderbein links -->
                <path d="M155 218 L148 272 Q152 276 156 272 L162 218"/>
                <!-- Vorderbein rechts -->
                <path d="M195 222 L189 272 Q193 276 197 272 L202 222"/>
                <!-- Hinterbein links -->
                <path d="M278 220 L272 272 Q276 276 280 272 L285 220"/>
                <!-- Hinterbein rechts -->
                <path d="M320 218 L315 272 Q319 276 323 272 L327 218"/>
                <!-- Rute (geschwungen) -->
                <path d="M368 142 Q398 108 408 128 Q415 148 400 162" fill="none" stroke-width="5" stroke-linecap="round"/>
            </g>
        `,
        // ── Katze (Seitenansicht) ────────────────────────────────
        cat: `
            <g fill="${SIL_FILL}" stroke="${SIL_STROKE}" stroke-width="${SIL_SW}" stroke-linejoin="round">
                <!-- Rumpf -->
                <ellipse cx="238" cy="172" rx="118" ry="50"/>
                <!-- Hals -->
                <path d="M135 148 Q122 125 130 110 Q146 102 158 122 Q155 138 152 158" stroke-width="1"/>
                <!-- Kopf -->
                <circle cx="95" cy="140" r="40"/>
                <!-- Schnauze -->
                <ellipse cx="66" cy="152" rx="18" ry="13"/>
                <!-- Ohr links (spitz) -->
                <polygon points="72,108 80,80 92,106"/>
                <!-- Ohr rechts (spitz) -->
                <polygon points="100,104 110,76 122,104"/>
                <!-- Auge -->
                <ellipse cx="82" cy="132" rx="6" ry="7" fill="${SIL_STROKE}" stroke="none"/>
                <!-- Nase -->
                <polygon points="60,147 66,142 72,147 66,153" fill="${SIL_STROKE}" stroke="none"/>
                <!-- Vorderbein links -->
                <path d="M160 218 L154 268 Q158 272 162 268 L167 218"/>
                <!-- Vorderbein rechts -->
                <path d="M198 222 L192 268 Q196 272 200 268 L205 222"/>
                <!-- Hinterbein links -->
                <path d="M286 220 L281 268 Q285 272 289 268 L294 220"/>
                <!-- Hinterbein rechts -->
                <path d="M322 218 L317 268 Q321 272 325 268 L330 218"/>
                <!-- Schwanz (lang, aufrecht) -->
                <path d="M354 182 Q420 168 435 135 Q440 108 418 98" fill="none" stroke-width="7" stroke-linecap="round"/>
            </g>
        `,
        // ── Pferd (Seitenansicht) ────────────────────────────────
        horse: `
            <g fill="${SIL_FILL}" stroke="${SIL_STROKE}" stroke-width="${SIL_SW}" stroke-linejoin="round">
                <!-- Rumpf -->
                <ellipse cx="248" cy="148" rx="148" ry="58"/>
                <!-- Kruppe (leichte Erhebung hinten) -->
                <ellipse cx="370" cy="138" rx="42" ry="35"/>
                <!-- Hals (lang) -->
                <path d="M118 120 Q105 88 112 65 Q128 52 148 68 Q155 88 152 118"/>
                <!-- Kopf -->
                <ellipse cx="88" cy="95" rx="28" ry="42"/>
                <!-- Maul/Schnauze -->
                <ellipse cx="72" cy="126" rx="18" ry="12"/>
                <!-- Nüstern -->
                <ellipse cx="64" cy="128" rx="5" ry="4" fill="${SIL_STROKE}" stroke="none"/>
                <!-- Auge -->
                <circle cx="88" cy="82" r="5" fill="${SIL_STROKE}" stroke="none"/>
                <!-- Ohr links -->
                <polygon points="78,62 84,44 92,62"/>
                <!-- Ohr rechts -->
                <polygon points="90,60 96,42 104,60"/>
                <!-- Mähne -->
                <path d="M112 68 Q118 80 122 98 Q128 110 132 120" fill="none" stroke-width="8" stroke-linecap="round"/>
                <!-- Vorderbein links -->
                <path d="M142 198 L134 268 Q138 274 144 268 L152 198"/>
                <!-- Vorderbein rechts -->
                <path d="M178 202 L170 268 Q174 274 180 268 L188 202"/>
                <!-- Hinterbein links -->
                <path d="M318 200 L312 268 Q316 274 322 268 L330 200"/>
                <!-- Hinterbein rechts -->
                <path d="M356 198 L350 268 Q354 274 360 268 L368 198"/>
                <!-- Schweif -->
                <path d="M392 145 Q428 128 438 155 Q445 175 430 188" fill="none" stroke-width="8" stroke-linecap="round"/>
            </g>
        `,
    };

    // ── State ──────────────────────────────────────────────────
    const hiddenSpecies  = ROOT.querySelector('input[name="anatomy_species"]');
    const hiddenMarkers  = ROOT.querySelector('input[name="anatomy_markers"]');
    const hiddenDrawings = ROOT.querySelector('input[name="anatomy_drawings"]');

    const state = {
        species:  safeRead(hiddenSpecies, 'dog'),
        markers:  safeParseJson(hiddenMarkers?.value, []),
        drawings: safeParseJson(hiddenDrawings?.value, []),
        tool:     'marker',           // 'marker' | 'draw' | 'erase'
        color:    COLORS[0].hex,
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

    // ── DOM-Build ──────────────────────────────────────────────
    try {
        renderToolbar();
        renderStage();
        renderLegend();
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

    let drawingPath = null;

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
})();
