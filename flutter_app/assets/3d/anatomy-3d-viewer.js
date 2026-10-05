/**
 * TheraPano — 3D Anatomie-Schmerzanalyse Viewer
 * Three.js r160 ESM, keine externe CDN-Abhängigkeit zur Laufzeit.
 *
 * Einstieg: window.Anatomy3D.init(containerId, patientId, animalType, csrfToken)
 *
 * Architektur:
 *  - GLB-Modell laden via GLTFLoader
 *  - Kalibrierte Muskelregionen je Tierart, stabile IDs für vorhandene Befunde
 *  - Punktgenaue Maus-/Touchauswahl mit Verdeckungsprüfung
 *  - Meshopt-komprimierte Offline-Modelle und bedarfsgesteuertes Rendering
 *  - Schmerzformular als Overlay-Panel
 *  - AJAX POST/GET gegen /api/patienten/{id}/schmerzpunkte
 *  - Vollbild via Fullscreen API
 */

import * as THREE from 'three';
import { OrbitControls } from './vendor/three/OrbitControls.js';
import { GLTFLoader }    from './vendor/three/GLTFLoader.js';

import { MeshoptDecoder } from './vendor/three/MeshoptDecoder.js';
import { MUSCLE_GROUPS } from './anatomy-landmarks.js?v=20261005';

// Only compressed model bytes are retained; GPU resources belong to one viewer.
const MODEL_BYTES = new Map();
const SIDE_LABELS = {left:'Links', right:'Rechts', midline:'Mittig', bilateral:'Beidseitig'};
function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/* NRS → Farbe */
const NRS_COLOR = ['#22c55e','#65a30d','#a3e635','#facc15','#fb923c','#f97316','#ef4444','#dc2626','#b91c1c','#991b1b','#7f1d1d'];

function painColor(level) { return NRS_COLOR[Math.min(10, Math.max(0, level))]; }

/* ═══════════════════════════════════════════════════════
   VIEWER CLASS
═══════════════════════════════════════════════════════ */
class Anatomy3DViewer {
    constructor(container, patientId, animalType, csrfToken) {
        this.container   = container;
        this.patientId   = patientId;
        this.animalType  = MUSCLE_GROUPS[animalType] ? animalType : 'dog';
        this.csrfToken   = csrfToken;

        /* App-Kontext (Offline-Bundle + Mobile-API via Bearer-Token) */
        const _cfg       = (typeof window !== 'undefined' && window.__A3D_CONFIG__) ? window.__A3D_CONFIG__ : {};
        this.apiBase     = (_cfg.apiBase || '').replace(/\/$/, '');
        this.token       = _cfg.token || csrfToken || '';

        /* Three.js state */
        this.scene       = null;
        this.camera      = null;
        this.renderer    = null;
        this.controls    = null;
        this.loader      = new GLTFLoader().setMeshoptDecoder(MeshoptDecoder);
        this.pointer     = new THREE.Vector2(-9999, -9999);

        /* Model state */
        this.modelGroup  = null;     /* loaded GLB root */
        this.hotspots    = [];       /* { mesh, def } */
        this.painData    = {};       /* key → {painLevel, painType, notes, id} */

        /* UI state */
        this.selectedKey = null;
        this.hoveredMesh = null;
        this.debugMode   = true;
        this._disposed = false;
        this._loadVersion = 0;
        this._dataVersion = 0;
        this._dirty = true;
        this._hoverDirty = false;
        this._animId     = null;

        this._buildUI();
        this._initThree();
        this._loadModel(this.animalType);
        this._loadPainData();
    }

    /* ── Build HTML skeleton ──────────────────────────────── */
    _buildUI() {
        this.container.style.cssText = 'position:relative;width:100%;height:100%;background:#0a0f1a;border-radius:12px;overflow:hidden;-webkit-tap-highlight-color:transparent;';
        this.container.innerHTML = `
          <canvas id="a3d-canvas" style="display:block;width:100%;height:100%;touch-action:none;"></canvas>

          <!-- Toolbar -->
          <div id="a3d-toolbar" style="
            position:absolute;top:10px;left:50%;transform:translateX(-50%);
            display:flex;gap:6px;z-index:20;background:rgba(10,15,26,.85);
            backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.1);
            border-radius:10px;padding:5px 8px;align-items:center;flex-wrap:wrap;">
            <button class="a3d-species-btn" data-sp="dog"   style="padding:4px 10px;border-radius:6px;border:none;cursor:pointer;font-size:.72rem;font-weight:600;">🐕 Hund</button>
            <button class="a3d-species-btn" data-sp="cat"   style="padding:4px 10px;border-radius:6px;border:none;cursor:pointer;font-size:.72rem;font-weight:600;">🐈 Katze</button>
            <button class="a3d-species-btn" data-sp="horse" style="padding:4px 10px;border-radius:6px;border:none;cursor:pointer;font-size:.72rem;font-weight:600;">🐎 Pferd</button>
            <div style="width:1px;height:18px;background:rgba(255,255,255,.15);margin:0 2px;"></div>
            <button id="a3d-reset-btn"  title="Ansicht zurücksetzen"  style="padding:4px 8px;border-radius:6px;border:none;cursor:pointer;font-size:.72rem;background:rgba(255,255,255,.08);color:#e2e8f0;">↺ Reset</button>
            <label id="a3d-debug-btn" title="Muskelpunkte auf der sichtbaren Körperseite anzeigen" style="display:flex;align-items:center;gap:5px;padding:4px 8px;border-radius:6px;cursor:pointer;font-size:.72rem;color:#e2e8f0;">
              <input id="a3d-zones-visible" type="checkbox" checked style="margin:0;accent-color:#4f7cff;"> Zonen
            </label>
            <button id="a3d-fs-btn"     title="Vollbild"              style="padding:4px 8px;border-radius:6px;border:none;cursor:pointer;font-size:.72rem;background:rgba(255,255,255,.08);color:#e2e8f0;">⛶ Vollbild</button>
            <select id="a3d-region-select" aria-label="Alle Muskelregionen" disabled style="flex-basis:100%;width:100%;min-width:0;padding:6px;border:1px solid #475569;border-radius:6px;background:#1e293b;color:#e2e8f0;font-size:.72rem;">
              <option value="">Muskelregionen werden geladen…</option>
            </select>
            <span style="flex-basis:100%;font-size:.65rem;color:#94a3b8;">Andere Körperseite: Modell drehen oder Region auswählen.</span>
          </div>

          <!-- Loading overlay -->
          <div id="a3d-loading" style="
            position:absolute;inset:0;display:flex;flex-direction:column;
            align-items:center;justify-content:center;z-index:30;
            background:rgba(10,15,26,.9);">
            <div style="width:36px;height:36px;border:3px solid rgba(255,255,255,.15);border-top-color:#4f7cff;border-radius:50%;animation:a3d-spin .8s linear infinite;"></div>
            <button id="a3d-retry-btn" type="button" hidden style="padding:8px;margin:10px;">Erneut laden</button>
            <div id="a3d-load-text" style="margin-top:12px;font-size:.8rem;color:#94a3b8;">Lade Modell…</div>
          </div>

          <!-- Hover tooltip -->
          <div id="a3d-tooltip" style="
            position:absolute;pointer-events:none;z-index:25;
            background:rgba(10,15,26,.92);border:1px solid rgba(255,255,255,.12);
            border-radius:7px;padding:5px 9px;font-size:.72rem;color:#e2e8f0;
            display:none;max-width:calc(100% - 30px);white-space:normal;"></div>

          <!-- Pain marker legend -->
          <div id="a3d-legend" style="
            position:absolute;bottom:10px;left:10px;z-index:20;
            background:rgba(10,15,26,.82);backdrop-filter:blur(6px);
            border:1px solid rgba(255,255,255,.1);border-radius:8px;
            padding:7px 10px;font-size:.68rem;color:#94a3b8;">
            <div style="font-weight:700;margin-bottom:4px;color:#e2e8f0;">Muskelregionen · Schmerzskala</div>
            <div style="display:flex;gap:2px;align-items:center;">
              ${NRS_COLOR.map((c,i)=>`<div title="${i}" style="width:16px;height:8px;background:${c};border-radius:2px;"></div>`).join('')}
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:2px;">
              <span>0 – kein</span><span>10 – extrem</span>
            </div>
          </div>

          <!-- Pain points list -->
          <div id="a3d-list" style="
            position:absolute;top:54px;right:10px;bottom:10px;width:200px;
            background:rgba(10,15,26,.85);backdrop-filter:blur(8px);
            border:1px solid rgba(255,255,255,.1);border-radius:10px;
            overflow-y:auto;z-index:20;padding:8px;display:none;font-size:.72rem;">
            <div style="font-weight:700;color:#e2e8f0;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center;">
              <span>Schmerzpunkte</span>
              <button id="a3d-list-close" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:.9rem;">✕</button>
            </div>
            <div id="a3d-list-body"></div>
          </div>
          <button id="a3d-list-btn" style="
            position:absolute;top:54px;right:10px;z-index:20;
            background:rgba(10,15,26,.85);border:1px solid rgba(255,255,255,.1);
            border-radius:8px;padding:5px 9px;font-size:.72rem;color:#e2e8f0;
            cursor:pointer;">📋 Liste</button>

          <!-- Pain form panel -->
          <div id="a3d-form" style="
            position:absolute;bottom:0;left:0;right:0;z-index:40;
            background:rgba(15,21,37,.97);backdrop-filter:blur(12px);
            border-top:1px solid rgba(255,255,255,.12);padding:14px 16px;
            display:none;max-height:65%;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
              <div>
                <div id="a3d-form-title" style="font-weight:700;font-size:.85rem;color:#e2e8f0;"></div>
                <div id="a3d-form-sub"   style="font-size:.7rem;color:#64748b;margin-top:2px;"></div>
              </div>
              <button id="a3d-form-close" style="background:none;border:none;color:#64748b;cursor:pointer;font-size:1rem;padding:2px 6px;">✕</button>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
              <div>
                <label style="font-size:.7rem;color:#64748b;display:block;margin-bottom:4px;">Schmerzstärke (0–10)</label>
                <div style="display:flex;align-items:center;gap:8px;">
                  <input type="range" id="a3d-pain-slider" min="0" max="10" value="0"
                    style="flex:1;min-width:0;width:100%;accent-color:#4f7cff;">
                  <span id="a3d-pain-val" style="font-size:.9rem;font-weight:700;color:#e2e8f0;min-width:20px;text-align:center;">0</span>
                </div>
                <div id="a3d-pain-bar" style="height:6px;border-radius:4px;background:#22c55e;margin-top:4px;transition:background .2s;"></div>
              </div>
              <div>
                <label style="font-size:.7rem;color:#64748b;display:block;margin-bottom:4px;">Seite</label>
                <select id="a3d-side-sel" class="form-select form-select-sm" style="max-width:100%;">
                  <option value="midline">Mittig</option>
                  <option value="left">Links</option>
                  <option value="right">Rechts</option>
                  <option value="bilateral">Beidseitig</option>
                </select>
              </div>
            </div>

            <div style="margin-bottom:10px;">
              <label style="font-size:.7rem;color:#64748b;display:block;margin-bottom:4px;">Schmerzart</label>
              <div id="a3d-pain-types" style="display:flex;flex-wrap:wrap;gap:5px;">
                ${['Druckschmerz','Bewegungsschmerz','Ruheschmerz','Verspannung','Verhärtung','Triggerpunkt','Schwellung','Wärme','Schonhaltung','Unklar'].map(t=>`
                  <button type="button" class="a3d-pt-btn" data-pt="${t}"
                    style="padding:3px 8px;border-radius:20px;border:1px solid rgba(255,255,255,.15);
                    background:transparent;color:#94a3b8;font-size:.68rem;cursor:pointer;">${t}</button>
                `).join('')}
              </div>
            </div>

            <div style="margin-bottom:12px;">
              <label style="font-size:.7rem;color:#64748b;display:block;margin-bottom:4px;">Notiz</label>
              <textarea id="a3d-notes" rows="2" class="form-control form-control-sm"
                placeholder="Freitext…" style="font-size:.78rem;resize:none;box-sizing:border-box;width:100%;"></textarea>
            </div>

            <div id="a3d-form-error" role="alert" style="color:#fca5a5;margin-bottom:8px;"></div>
            <div style="display:flex;gap:8px;">
              <button id="a3d-save-btn"   class="btn btn-primary btn-sm" style="flex:1;">Speichern</button>
              <button id="a3d-remove-btn" class="btn btn-outline-danger btn-sm">Entfernen</button>
              <button id="a3d-cancel-btn" class="btn btn-secondary btn-sm">Abbrechen</button>
            </div>
          </div>

          <style>
            @keyframes a3d-spin { to { transform:rotate(360deg); } }
            #a3d-toolbar { max-width:calc(100% - 36px); width:max-content; justify-content:center; }
            #a3d-form select, #a3d-form textarea { background:#1e293b; color:#e2e8f0; border:1px solid #475569; border-radius:6px; padding:6px; }
            #a3d-form .btn { border:1px solid #475569; border-radius:6px; padding:7px 10px; background:#1e293b; color:#e2e8f0; cursor:pointer; }
            #a3d-form #a3d-save-btn { background:#2563eb; border-color:#2563eb; }
            #a3d-form .btn:disabled { opacity:.6; cursor:wait; }
            .a3d-species-btn { background:rgba(255,255,255,.06); color:#94a3b8; }
            .a3d-species-btn.active { background:#4f7cff; color:#fff; }
            .a3d-pt-btn.active { background:rgba(79,124,255,.25); border-color:#4f7cff; color:#e2e8f0; }
          </style>
        `;

        this._bindUI();
    }

    _bindUI() {
        const c = this.container;

        c.querySelector('#a3d-retry-btn').addEventListener('click', () => this._loadModel(this.animalType));

        /* Species buttons */
        c.querySelectorAll('.a3d-species-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const sp = btn.dataset.sp;
                this._switchAnimal(sp);
            });
        });
        this._updateSpeciesBtn();

        c.querySelector('#a3d-reset-btn').addEventListener('click', () => this._resetCamera());
        c.querySelector('#a3d-zones-visible').addEventListener('change', e => this._setZonesVisible(e.target.checked));
        c.querySelector('#a3d-region-select').addEventListener('change', e => {
            const entry = this.hotspots.find(h => h.def.id === e.target.value);
            if (!entry) return;
            this._setZonesVisible(true);
            this._focusHotspot(entry.def);
            this._openForm(entry.def);
        });
        this._syncZonesControl();
        c.querySelector('#a3d-fs-btn').addEventListener('click', () => this._toggleFullscreen());

        c.querySelector('#a3d-form-close').addEventListener('click', () => this._closeForm());
        c.querySelector('#a3d-cancel-btn').addEventListener('click', () => this._closeForm());
        c.querySelector('#a3d-save-btn').addEventListener('click', () => this._savePain());
        c.querySelector('#a3d-remove-btn').addEventListener('click', () => this._removePain());

        const slider = c.querySelector('#a3d-pain-slider');
        slider.addEventListener('input', () => {
            const v = parseInt(slider.value);
            c.querySelector('#a3d-pain-val').textContent = v;
            c.querySelector('#a3d-pain-bar').style.background = painColor(v);
            if (this.selectedKey) this._previewPain(this.selectedKey, v);
        });

        c.querySelectorAll('.a3d-pt-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                c.querySelectorAll('.a3d-pt-btn').forEach(b => b.classList.remove('active'));
                btn.classList.toggle('active');
            });
        });

        c.querySelector('#a3d-list-btn').addEventListener('click', () => {
            c.querySelector('#a3d-list').style.display = 'block';
            c.querySelector('#a3d-list-btn').style.display = 'none';
        });
        c.querySelector('#a3d-list-close').addEventListener('click', () => {
            c.querySelector('#a3d-list').style.display = 'none';
            c.querySelector('#a3d-list-btn').style.display = 'block';
        });
    }

    /* ── Three.js init ────────────────────────────────────── */
    _initThree() {
        const canvas = this.container.querySelector('#a3d-canvas');

        this.renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.shadowMap.enabled = false;

        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x0a0f1a);
        this.scene.fog = new THREE.FogExp2(0x0a0f1a, 0.08);

        this.camera = new THREE.PerspectiveCamera(45, 1, 0.01, 100);
        this.camera.position.set(0, 0.5, 3.2);

        this.controls = new OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.06;
        this.controls.minDistance = 0.5;
        this.controls.maxDistance = 8;

        /* Lighting */
        const amb = new THREE.AmbientLight(0xffffff, 0.6);
        this.scene.add(amb);
        const key = new THREE.DirectionalLight(0xffffff, 1.4);
        key.position.set(2, 3, 2);
        key.castShadow = false;
        this.scene.add(key);
        const fill = new THREE.DirectionalLight(0x8888ff, 0.5);
        fill.position.set(-2, 1, -1);
        this.scene.add(fill);
        const rim = new THREE.DirectionalLight(0xffeedd, 0.4);
        rim.position.set(0, -1, -3);
        this.scene.add(rim);

        /* Ground grid */
        const grid = new THREE.GridHelper(6, 20, 0x1e293b, 0x1e293b);
        grid.position.y = -0.6;
        this.scene.add(grid);
        this.grid = grid;

        /* Events */
        canvas.addEventListener('pointermove', e => this._onPointerMove(e));
        canvas.addEventListener('pointerdown', e => {
            this._pointerDown = {x:e.clientX, y:e.clientY, id:e.pointerId};
        });
        canvas.addEventListener('pointerup', e => {
            const down = this._pointerDown;
            this._pointerDown = null;
            if (down && down.id === e.pointerId && Math.hypot(e.clientX-down.x, e.clientY-down.y) < 6) this._onClick(e);
        });
        canvas.addEventListener('pointercancel', () => { this._pointerDown = null; });
        canvas.addEventListener('pointerleave', () => {
            this.pointer.set(-9999, -9999);
            this._hoverDirty = true;
        });
        this.controls.addEventListener('change', () => { this._dirty = true; this._hoverDirty = true; });
        this._resizeObserver = new ResizeObserver(() => this._resize());
        this._resizeObserver.observe(this.container);
        this._resizeObserver.observe(this.container.querySelector('#a3d-toolbar'));

        this._resize();
        this._animate();
    }

    _resize() {
        const toolbarBottom = this.container.querySelector('#a3d-toolbar').offsetHeight + 20;
        this.container.querySelector('#a3d-list-btn').style.top = `${toolbarBottom}px`;
        this.container.querySelector('#a3d-list').style.top = `${toolbarBottom}px`;
        const w = Math.max(1, this.container.clientWidth);
        const h = this.container.clientHeight || 400;
        this.renderer.setSize(w, h, false);
        this.camera.aspect = w / h;
        this.camera.updateProjectionMatrix();
        this._dirty = true;
        if (this.modelGroup && this._lastAspect !== this.camera.aspect) this._resetCamera();
        this._lastAspect = this.camera.aspect;
    }

    _animate() {
        if (this._disposed) return;
        this._animId = requestAnimationFrame(() => this._animate());
        this.controls.update();
        if (this._hoverDirty) { this._hoverDirty = false; this._updateHover(); }
        if (this._dirty) { this._sizeMarkers(); this.renderer.render(this.scene, this.camera); this._dirty = false; }
    }

    _sizeMarkers() {
        // A constant CSS-pixel radius stays readable on a zoomed-out phone.
        this.camera.updateMatrixWorld(true);
        const pixelRadius = window.matchMedia('(pointer: coarse)').matches ? 6 : 5;
        const factor = 2 * Math.tan(THREE.MathUtils.degToRad(this.camera.fov)/2)
            / Math.max(1, this.renderer.domElement.clientHeight);
        this.hotspots.forEach(({marker, pos}) => {
            const depth = -pos.clone().applyMatrix4(this.camera.matrixWorldInverse).z;
            marker.scale.setScalar(Math.max(0.001, depth) * factor * pixelRadius / 0.012 * (marker.userData.emphasis || 1));
        });
    }

    _disposeObject(root) {
        const geometries = new Set(), materials = new Set(), textures = new Set();
        root.traverse(obj => {
            if (obj.geometry) geometries.add(obj.geometry);
            if (obj.material) (Array.isArray(obj.material) ? obj.material : [obj.material]).forEach(m => materials.add(m));
        });
        materials.forEach(m => Object.values(m).forEach(v => { if (v?.isTexture) textures.add(v); }));
        geometries.forEach(g => g.dispose());
        materials.forEach(m => m.dispose());
        textures.forEach(t => { t.dispose(); t.source?.data?.close?.(); });
    }

    _clearModel() {
        this.hoveredMesh = null;
        this._hideTooltip();
        if (this.modelGroup) {
            this.scene.remove(this.modelGroup);
            this._disposeObject(this.modelGroup);
            this.modelGroup = null;
        }
        this.hotspots.forEach(h => { this.scene.remove(h.marker); this._disposeObject(h.marker); });
        this.hotspots = [];
        const regions = this.container.querySelector('#a3d-region-select');
        regions.disabled = true;
        regions.replaceChildren(new Option('Muskelregionen werden geladen…', ''));
        this._modelMeshes = [];
        this._modelBox = null;
        this._dirty = true;
    }

    destroy() {
        this._disposed = true;
        ++this._loadVersion;
        ++this._dataVersion;
        this._fetchController?.abort();
        cancelAnimationFrame(this._animId);
        this._resizeObserver.disconnect();
        this.controls.dispose();
        this._clearModel();
        this._disposeObject(this.scene);
        this.renderer.dispose();
    }

    /* Model requests can finish out of order when the animal or patient changes. */
    async _loadModel(species) {
        const paths = {dog:'./models/Hund.glb?v=20261005',cat:'./models/katze.glb?v=20261005',horse:'./models/Pferd.glb?v=20261005'};
        const path = paths[species];
        if (!path) return;
        const version = ++this._loadVersion;
        this._fetchController?.abort();
        this._fetchController = new AbortController();
        this._closeForm();
        this._clearModel();
        this.container.querySelector('#a3d-retry-btn').hidden = true;
        this._showLoading('Lade ' + ({dog:'Hund',cat:'Katze',horse:'Pferd'}[species]) + '…');
        this._updateSpeciesBtn();
        let model = null;
        try {
            let bytes = MODEL_BYTES.get(path);
            if (!bytes) {
                const res = await fetch(path, {signal:this._fetchController.signal});
                if (!res.ok) throw new Error(`Modell: HTTP ${res.status}`);
                bytes = await res.arrayBuffer();
                MODEL_BYTES.set(path, bytes);
            }
            if (this._disposed || version !== this._loadVersion) return;
            this._showLoading('Bereite 3D-Ansicht vor…');
            // Yield once so the loading text can paint before mesh decoding.
            await new Promise(resolve => requestAnimationFrame(resolve));
            const gltf = await this.loader.parseAsync(bytes, path.slice(0, path.lastIndexOf('/')+1));
            model = gltf.scene;
            if (this._disposed || version !== this._loadVersion) { this._disposeObject(model); return; }
            this._onModelLoaded(gltf, species);
        } catch (err) {
            if (this._disposed || version !== this._loadVersion || err.name === 'AbortError') return;
            if (model && model !== this.modelGroup) this._disposeObject(model);
            this._clearModel();
            MODEL_BYTES.delete(path);
            console.error('[Anatomy3D] GLB load error:', err);
            this._showLoading('Modell konnte nicht geladen werden. Bitte erneut versuchen.');
            this.container.querySelector('#a3d-retry-btn').hidden = false;
        }
    }

    _onModelLoaded(gltf, species) {
        // Normalize in a parent group. Overwriting the GLB root transform breaks
        // quantized assets, which use node scale/translation to decode vertices.
        const model = new THREE.Group();
        model.add(gltf.scene);
        const box = new THREE.Box3().setFromObject(model);
        const size = box.getSize(new THREE.Vector3());
        model.scale.setScalar(2 / Math.max(size.x,size.y,size.z));
        model.updateMatrixWorld(true);
        model.position.sub(new THREE.Box3().setFromObject(model).getCenter(new THREE.Vector3()));
        model.updateMatrixWorld(true);
        const finalBox = new THREE.Box3().setFromObject(model);
        this._modelBox = {box:finalBox, size:finalBox.getSize(new THREE.Vector3())};
        this.modelGroup = model;
        this._modelMeshes = [];
        model.traverse(o => { if (o.isMesh) this._modelMeshes.push(o); });
        this.scene.add(model);
        this.grid.position.y = finalBox.min.y - 0.015;
        this._buildHotspots(species);
        this._applyPainToHotspots();
        this._resetCamera();
        this._hideLoading();
    }

    _buildHotspots(species) {
        // Calibrated coordinates are already on the surface. No runtime ray
        // projection can move a leg landmark onto the chest or throat.
        const groups = MUSCLE_GROUPS[species] || [];
        const select = this.container.querySelector('#a3d-region-select');
        select.replaceChildren(new Option(`Alle ${groups.length} Regionen auswählen…`, ''));
        groups.forEach(def => select.add(new Option(`${def.anatomical} – ${def.label}`, def.id)));
        select.disabled = false;
        for (const def of groups) {
            const marker = new THREE.Mesh(new THREE.SphereGeometry(0.012, 8, 6),
                new THREE.MeshBasicMaterial({color:0x4f7cff, transparent:true, opacity:0.95, depthWrite:false}));
            marker.position.fromArray(def.pos);
            this.scene.add(marker);
            const mesh = new THREE.Object3D();
            mesh.userData.hotspot = def;
            this.hotspots.push({mesh, marker, def, pos:marker.position.clone()});
        }
    }

    _pickHotspot() {
        if (!this.modelGroup) return null;
        this.scene.updateMatrixWorld(true);
        this.camera.updateMatrixWorld(true);
        const canvas = this.renderer.domElement;
        const radius = this._pointerType === 'touch' ? 18 : 12;
        const candidates = this.hotspots.filter(h => h.marker.material.opacity > 0).map(h => {
            const p = h.pos.clone().project(this.camera);
            const distance = Math.hypot((p.x-this.pointer.x)*canvas.clientWidth/2, (p.y-this.pointer.y)*canvas.clientHeight/2);
            return {h,p,distance};
        }).filter(c => c.p.z >= -1 && c.p.z <= 1 && c.distance <= radius)
          .sort((a,b) => a.distance-b.distance);
        for (const {h} of candidates) {
            const direction = h.pos.clone().sub(this.camera.position);
            const distance = direction.length();
            const ray = new THREE.Raycaster(this.camera.position, direction.normalize(), 0, distance-0.018);
            // Respect occlusion: a far-side marker must never win a front-side tap.
            if (!ray.intersectObjects(this._modelMeshes, false).length) return h.mesh;
        }
        return null;
    }

    /* ── Raycasting / Hover ───────────────────────────────── */
    _onPointerMove(e) {
        const rect = this.renderer.domElement.getBoundingClientRect();
        this.pointer.x =  ((e.clientX - rect.left)  / rect.width)  * 2 - 1;
        this.pointer.y = -((e.clientY - rect.top)   / rect.height) * 2 + 1;
        this._moveTooltip(e.clientX, e.clientY);
        this._pointerType = e.pointerType;
        this._hoverDirty = true;
    }

    _updateHover() {
        if (!this.hotspots.length) return;
        const hitMesh = this._pickHotspot();

        if (hitMesh !== this.hoveredMesh) {
            this._dirty = true;
            /* Restore the saved pain color after hover. */
            if (this.hoveredMesh) {
                const prevEntry = this.hotspots.find(h => h.mesh === this.hoveredMesh);
                if (prevEntry) {
                    const pain = this._painForDef(prevEntry.def);
                    const hasPain = pain?.painLevel > 0;
                    /* marker: show if pain, else hide (unless debug) */
                    prevEntry.marker.material.opacity  = hasPain ? 0.9 : (this.debugMode ? 0.95 : 0);
                    prevEntry.marker.material.color.set(
                        hasPain ? painColor(pain.painLevel) : 0x4f7cff
                    );
                    prevEntry.marker.userData.emphasis = 1;
                }
            }
            this.hoveredMesh = hitMesh;
            if (hitMesh) {
                const entry = this.hotspots.find(h => h.mesh === hitMesh);
                if (entry) {
                    /* Scale up marker and make visible as hover indicator */
                    entry.marker.material.color.setHex(0xfbbf24);
                    entry.marker.material.opacity = 0.95;
                    entry.marker.userData.emphasis = 1.8;
                    this._showTooltip(entry.def);
                }
                this.renderer.domElement.style.cursor = 'pointer';
            } else {
                this._hideTooltip();
                this.renderer.domElement.style.cursor = 'default';
            }
        }
    }

    _onClick(e) {
        if (e.button !== 0) return;
        this._onPointerMove(e);
        this._updateHover();
        if (!this.hoveredMesh) return;

        const def = this.hoveredMesh.userData.hotspot;
        this._openForm(def);
    }

    /* ── Pain form ────────────────────────────────────────── */
    _openForm(def, storedKey = null) {
        const savedKeys = Object.keys(this.painData).filter(key => key.split('::')[0] === def.id);
        this.selectedKey = storedKey || (savedKeys.length === 1 ? savedKeys[0] : `${def.id}::${def.side}`);
        const existing   = this.painData[this.selectedKey] || {};

        const c = this.container;
        c.querySelector('#a3d-region-select').value = def.id;
        c.querySelector('#a3d-form-error').textContent = '';
        c.querySelector('#a3d-form-title').textContent = def.label;
        c.querySelector('#a3d-form-sub').textContent   =
            `${def.anatomical} · ${SIDE_LABELS[this.selectedKey.split('::')[1]]} (aus Sicht des Tieres)`;

        const lvl = existing.painLevel ?? 0;
        const slider = c.querySelector('#a3d-pain-slider');
        slider.value = lvl;
        c.querySelector('#a3d-pain-val').textContent = lvl;
        c.querySelector('#a3d-pain-bar').style.background = painColor(lvl);

        c.querySelectorAll('.a3d-pt-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.pt === existing.painType);
        });

        const sideSelect = c.querySelector('#a3d-side-sel');
        const storedSide = this.selectedKey.split('::')[1];
        sideSelect.value = storedSide;
        sideSelect.disabled = Boolean(existing.id) || def.side !== 'midline';
        c.querySelector('#a3d-notes').value = existing.notes || '';

        c.querySelector('#a3d-remove-btn').style.display = existing.painLevel ? '' : 'none';
        c.querySelector('#a3d-form').style.display = 'block';
    }

    _closeForm() {
        this.container.querySelector('#a3d-form').style.display = 'none';
        /* Restore preview to actual pain state */
        if (this.selectedKey) {
            this._applyPainToHotspots();
        }
        this.selectedKey = null;
        this.container.querySelector('#a3d-region-select').value = '';
    }

    _previewPain(key, level) {
        this._dirty = true;
        const entry = this.hotspots.find(h => h.def.id === key.split('::')[0]);
        if (!entry) return;
        const mat = entry.marker.material;
        if (level === 0) {
            mat.color.setHex(0x4f7cff);
            mat.opacity = this.debugMode ? 0.95 : 0;
            entry.marker.userData.emphasis = 1;
        } else {
            mat.color.set(painColor(level));
            mat.opacity = 0.9;
            entry.marker.userData.emphasis = 1.4;
        }
    }

    async _savePain() {
        if (!this.selectedKey) return;
        const c   = this.container;
        const def = this._defFromKey(this.selectedKey);
        if (!def) return;

        const lvl      = parseInt(c.querySelector('#a3d-pain-slider').value);
        const ptBtn    = c.querySelector('.a3d-pt-btn.active');
        const painType = ptBtn ? ptBtn.dataset.pt : '';
        const notes    = c.querySelector('#a3d-notes').value.trim();
        const side     = c.querySelector('#a3d-side-sel').value;
        const species = this.animalType;
        const originalKey = this.selectedKey;
        const saveKey = `${def.id}::${side}`;
        const dataVersion = this._dataVersion;

        const btn = c.querySelector('#a3d-save-btn');
        btn.disabled = true;
        btn.textContent = 'Speichere…';

        try {
            const res  = await fetch(`${this.apiBase}/api/mobile/patients/${this.patientId}/schmerzpunkte`, {
                method: 'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Authorization':    `Bearer ${this.token}`,
                },
                body: JSON.stringify({
                    animal_type:        species,
                    muscle_group_id:    def.id,
                    muscle_group_label: `${def.label} – ${def.anatomical}`,
                    region:             def.region,
                    side,
                    pain_level:         lvl,
                    pain_type:          painType,
                    notes,
                }),
            });
            const json = await res.json();
            if (!res.ok || !json.success) throw new Error(json.error || 'Speichern fehlgeschlagen');
            if (this._disposed || dataVersion !== this._dataVersion) return;

            if (json.success) {
                this.painData[saveKey] = { painLevel: lvl, painType, notes, id: json.id };
                this._applyPainToHotspots();
                this._renderList();
                if (this.selectedKey === originalKey) this._closeForm();
            }
        } catch (err) {
            console.error('[Anatomy3D] save error:', err);
            if (!this._disposed && dataVersion === this._dataVersion) this.container.querySelector('#a3d-form-error').textContent = 'Speichern fehlgeschlagen. Bitte erneut versuchen.';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Speichern';
        }
    }

    async _removePain() {
        if (!this.selectedKey) return;
        const removeKey = this.selectedKey;
        const dataVersion = this._dataVersion;
        const entry = this.painData[removeKey];
        if (!entry?.id) { this._closeForm(); return; }

        try {
            const res = await fetch(`${this.apiBase}/api/mobile/patients/${this.patientId}/schmerzpunkte/${entry.id}/loeschen`, {
                method: 'POST',
                headers: {
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Authorization':    `Bearer ${this.token}`,
                },
            });
            const json = await res.json();
            if (!res.ok || !json.success) throw new Error(json.error || 'Löschen fehlgeschlagen');
            if (this._disposed || dataVersion !== this._dataVersion) return;
            delete this.painData[removeKey];
            this._applyPainToHotspots();
            this._renderList();
            if (this.selectedKey === removeKey) this._closeForm();
        } catch (err) {
            console.error('[Anatomy3D] remove error:', err);
            if (!this._disposed && dataVersion === this._dataVersion) this.container.querySelector('#a3d-form-error').textContent = 'Entfernen fehlgeschlagen. Bitte erneut versuchen.';
        }
    }

    /* ── Load existing pain data from API ─────────────────── */
    async _loadPainData() {
        const version = ++this._dataVersion;
        this.painData = {};
        this._renderList();
        try {
            const res  = await fetch(`${this.apiBase}/api/mobile/patients/${this.patientId}/schmerzpunkte?animal_type=${this.animalType}`, {
                headers: {
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Authorization':    `Bearer ${this.token}`,
                },
            });
            const json = await res.json();
            if (!json.success || this._disposed || version !== this._dataVersion) return;

            this.painData = {};
            (json.points || []).forEach(p => {
                const key = `${p.muscle_group_id}::${p.side}`;
                this.painData[key] = {
                    painLevel: p.pain_level,
                    painType:  p.pain_type,
                    notes:     p.notes,
                    id:        p.id,
                };
            });
            this._applyPainToHotspots();
            this._renderList();
        } catch (err) {
            console.error('[Anatomy3D] load pain data error:', err);
        }
    }

    /* ── Apply pain colors to hotspot markers ─────────────── */
    _applyPainToHotspots() {
        this._dirty = true;
        this.hotspots.forEach(({ mesh, marker, def }) => {
            const entry = this._painForDef(def);
            mesh.visible = false; /* identity object is not rendered */

            if (entry && entry.painLevel > 0) {
                marker.material.color.set(painColor(entry.painLevel));
                marker.material.opacity = 0.9;
                marker.userData.emphasis = 1.2;
            } else {
                marker.material.color.setHex(0x4f7cff);
                marker.material.opacity = this.debugMode ? 0.95 : 0;
                marker.userData.emphasis = 1;
            }
        });
    }

    /* ── Pain points list ─────────────────────────────────── */
    _renderList() {
        const body    = this.container.querySelector('#a3d-list-body');
        const entries = Object.entries(this.painData).filter(([,v]) => v.painLevel > 0);

        if (!entries.length) {
            body.innerHTML = '<div style="color:#64748b;font-size:.72rem;text-align:center;padding:10px;">Keine Schmerzpunkte</div>';
            return;
        }

        body.innerHTML = entries.map(([key, v]) => {
            const def = this._defFromKey(key);
            const col = painColor(v.painLevel);
            return `<div class="a3d-list-item" data-key="${escapeHTML(key)}"
                style="display:flex;align-items:center;gap:6px;padding:5px 6px;border-radius:6px;
                cursor:pointer;margin-bottom:3px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);">
                <div style="width:10px;height:10px;border-radius:50%;background:${col};flex-shrink:0;"></div>
                <div style="min-width:0;">
                  <div style="font-weight:600;color:#e2e8f0;font-size:.72rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHTML(def?.label || key)} · ${escapeHTML(SIDE_LABELS[key.split('::')[1]] || '')}</div>
                  <div style="color:#64748b;font-size:.65rem;">${escapeHTML(def?.anatomical || '')}<br>NRS ${escapeHTML(v.painLevel)} · ${escapeHTML(v.painType || '–')}</div>
                </div>
              </div>`;
        }).join('');

        body.querySelectorAll('.a3d-list-item').forEach(el => {
            el.addEventListener('click', () => {
                const def = this._defFromKey(el.dataset.key);
                if (def) {
                    this._focusHotspot(def);
                    this._openForm(def, el.dataset.key);
                }
            });
        });
    }

    _focusHotspot(def) {
        /* Focus the calibrated point, including when opened from the saved list. */
        const entry = this.hotspots.find(h => h.def.id === def.id && h.def.side === def.side);
        const pos  = entry?.pos ? entry.pos.clone() : new THREE.Vector3(...def.pos);
        const dist = Math.max(1.5, 1.1 / Math.max(0.4, this.camera.aspect));
        const left = this.animalType === 'dog' ? 1 : -1;
        const dir = def.side === 'left' || def.side === 'right'
            ? new THREE.Vector3(def.side === 'left' ? left : -left, 0.06, 0)
            : pos.clone();
        if (dir.lengthSq() < 0.01) dir.set(left, 0.2, 0);
        dir.normalize().multiplyScalar(dist);
        this.camera.position.copy(pos.clone().add(dir));
        this.controls.target.copy(pos);
        // Reset residual orbit damping before selecting the opposite body side.
        const damping = this.controls.enableDamping;
        this.controls.enableDamping = false;
        this.controls.update();
        this.controls.enableDamping = damping;
        this._dirty = true;
    }

    /* ── Tooltip ─────────────────────────────────────────── */
    _showTooltip(def) {
        const tt  = this.container.querySelector('#a3d-tooltip');
        const p = this._painForDef(def);
        tt.innerHTML = `<strong style="color:#e2e8f0;">${def.anatomical}</strong><br>${def.label}` +
            (p?.painLevel > 0 ? `<br><span style="color:${painColor(p.painLevel)}">▲ NRS ${p.painLevel}</span>` : '');
        tt.style.display = 'block';
    }
    _hideTooltip() {
        this.container.querySelector('#a3d-tooltip').style.display = 'none';
    }
    _moveTooltip(cx, cy) {
        const rect = this.container.getBoundingClientRect();
        const tt   = this.container.querySelector('#a3d-tooltip');
        tt.style.left = `${Math.max(4, Math.min(cx - rect.left + 14, rect.width - tt.offsetWidth - 4))}px`;
        tt.style.top  = `${cy - rect.top  - 10}px`;
    }

    /* ── Helpers ─────────────────────────────────────────── */
    _painForDef(def) {
        return Object.entries(this.painData)
            .filter(([key]) => key.split('::')[0] === def.id)
            .map(([,value]) => value)
            .sort((a,b) => b.painLevel-a.painLevel)[0];
    }

    _defFromKey(key) {
        const groups = MUSCLE_GROUPS[this.animalType] || [];
        const [id] = key.split('::');
        return groups.find(d => d.id === id) || null;
    }

    _showLoading(msg = 'Lade…') {
        const el = this.container.querySelector('#a3d-loading');
        this.container.querySelector('#a3d-load-text').textContent = msg;
        el.style.display = 'flex';
    }
    _hideLoading() {
        this.container.querySelector('#a3d-loading').style.display = 'none';
    }

    _updateSpeciesBtn() {
        this.container.querySelectorAll('.a3d-species-btn').forEach(b => {
            b.classList.toggle('active', b.dataset.sp === this.animalType);
        });
    }

    _switchAnimal(species) {
        if (!MUSCLE_GROUPS[species] || species === this.animalType) return;
        this.animalType = species;
        this._loadModel(species);
        this._loadPainData();
    }

    _resetCamera() {
        const sphere = this._modelBox?.box.getBoundingSphere(new THREE.Sphere());
        const radius = sphere?.radius || 1.3;
        const vfov = THREE.MathUtils.degToRad(this.camera.fov);
        const hfov = 2 * Math.atan(Math.tan(vfov/2)*this.camera.aspect);
        const distance = radius / Math.sin(Math.min(vfov,hfov)/2) * 1.08;
        const sign = this.animalType === 'dog' ? 1 : -1;
        this.camera.position.copy(new THREE.Vector3(sign*3,0.8,sign*1.6).normalize().multiplyScalar(distance));
        this.controls.maxDistance = Math.max(8,distance*2);
        this.controls.target.set(0,0,0);
        this.controls.update();
        this._dirty = true;
    }

    _syncZonesControl() {
        this.container.querySelector('#a3d-zones-visible').checked = this.debugMode;
        this.container.querySelector('#a3d-debug-btn').style.background =
            this.debugMode ? 'rgba(79,124,255,.35)' : 'rgba(255,255,255,.08)';
    }

    _setZonesVisible(visible) {
        this.debugMode = Boolean(visible);
        this.hoveredMesh = null;
        this._hideTooltip();
        this._syncZonesControl();
        this._applyPainToHotspots();
    }

    _toggleDebug() { this._setZonesVisible(!this.debugMode); }

    _toggleFullscreen() {
        if (!document.fullscreenElement) {
            this.container.requestFullscreen?.();
        } else {
            document.exitFullscreen?.();
        }
        /* Resize after fullscreen toggle */
        setTimeout(() => this._resize(), 200);
    }
}

/* ═══════════════════════════════════════════════════════
   PUBLIC API
═══════════════════════════════════════════════════════ */
window.Anatomy3D = {
    _instance: null,

    /**
     * @param {string} containerId  — DOM-ID des Container-Div
     * @param {number} patientId
     * @param {string} animalType   — 'dog'|'cat'|'horse'
     * @param {string} csrfToken
     */
    init(containerId, patientId, animalType, csrfToken) {
        const el = document.getElementById(containerId);
        if (!el) { console.error('[Anatomy3D] Container nicht gefunden:', containerId); return; }

        if (this._instance) {
            this._instance.destroy();
            this._instance = null;
        }

        this._instance = new Anatomy3DViewer(el, patientId, animalType, csrfToken);
    },

    switchAnimal(species) {
        this._instance?._switchAnimal(species);
    },
};
