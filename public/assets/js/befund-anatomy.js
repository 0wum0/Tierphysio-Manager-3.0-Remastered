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

    const ROOT = document.getElementById('befund-anatomy');
    if (!ROOT) return; // nicht auf dieser Seite

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
    const SILHOUETTES = {
        dog: `
            <g fill="#c7cdd4" stroke="#6b7280" stroke-width="1.5">
                <ellipse cx="220" cy="170" rx="130" ry="55"/>
                <ellipse cx="95" cy="140" rx="45" ry="40"/>
                <rect x="70" y="115" width="30" height="12" rx="4"/>
                <rect x="62" y="100" width="8" height="22" rx="2"/>
                <rect x="82" y="100" width="8" height="22" rx="2"/>
                <path d="M140 220 L140 260 L150 260 L155 225 Z"/>
                <path d="M200 225 L200 265 L212 265 L215 228 Z"/>
                <path d="M270 225 L270 265 L282 265 L283 228 Z"/>
                <path d="M320 222 L318 262 L330 262 L333 225 Z"/>
                <path d="M345 145 Q370 125 365 170" fill="none"/>
            </g>
        `,
        cat: `
            <g fill="#c7cdd4" stroke="#6b7280" stroke-width="1.5">
                <ellipse cx="230" cy="175" rx="115" ry="48"/>
                <circle cx="115" cy="155" r="36"/>
                <path d="M95 125 L105 105 L112 130 Z"/>
                <path d="M125 125 L132 105 L140 130 Z"/>
                <path d="M155 225 L155 262 L163 262 L168 228 Z"/>
                <path d="M210 228 L210 265 L220 265 L223 230 Z"/>
                <path d="M275 228 L275 265 L285 265 L287 230 Z"/>
                <path d="M320 225 L320 263 L330 263 L333 228 Z"/>
                <path d="M345 150 Q390 130 380 180" fill="none"/>
            </g>
        `,
        horse: `
            <g fill="#c7cdd4" stroke="#6b7280" stroke-width="1.5">
                <ellipse cx="240" cy="155" rx="145" ry="55"/>
                <ellipse cx="95" cy="115" rx="28" ry="48"/>
                <rect x="90" y="70" width="8" height="22" rx="2"/>
                <rect x="103" y="70" width="8" height="22" rx="2"/>
                <rect x="130" y="210" width="12" height="75" rx="3"/>
                <rect x="180" y="210" width="12" height="75" rx="3"/>
                <rect x="295" y="210" width="12" height="75" rx="3"/>
                <rect x="345" y="210" width="12" height="75" rx="3"/>
                <path d="M380 130 Q410 150 395 200" fill="none"/>
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
        stage.innerHTML = '';

        // Silhouette
        const silSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        silSvg.setAttribute('class', 'anatomy-silhouette');
        silSvg.setAttribute('viewBox', '0 0 500 300');
        silSvg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
        try {
            silSvg.innerHTML = SILHOUETTES[state.species] || SILHOUETTES.dog;
        } catch (e) {
            console.warn('[Befund Anatomy] Silhouette-Fehler, Fallback aktiv:', e);
            const fb = document.createElement('div');
            fb.className = 'anatomy-fallback';
            fb.innerHTML = '<strong>Silhouette nicht verfügbar</strong><br>Klick-Markierungen funktionieren trotzdem.';
            stage.appendChild(fb);
        }
        stage.appendChild(silSvg);

        // Overlay (interaktiv)
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
})();
