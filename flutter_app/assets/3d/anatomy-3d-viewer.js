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
import { SEGMENTED_MODELS, validateMuscleModel } from './anatomy-models.js?v=20261006-atlas';
import { PainSurfaces } from './anatomy-surfaces.js?v=20261006-layers';
import { AnatomyAtlas, validateSkeletonAtlas } from './anatomy-atlas.js?v=20261006-atlas';
import { createShortCoat } from './anatomy-fur.js?v=20261006-usable';
import { styleAnatomy } from './anatomy-materials.js?v=20261006-layers';
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
        this.muscleMode = true;
        this._layerState = {muscle:true, fascia:true, tendon:true, skeleton:true, skin:false, fur:false};
        this._layerLoads = new Map();
        this._layerMessages = new Map();
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
            <select id="a3d-region-select" aria-label="Anatomische Struktur auswählen" disabled style="flex-basis:100%;width:100%;min-width:0;padding:6px;border:1px solid #475569;border-radius:6px;background:#1e293b;color:#e2e8f0;font-size:.72rem;">
              <option value="">Muskelregionen werden geladen…</option>
            </select>
            <details id="a3d-search-panel" style="flex-basis:100%;font-size:.72rem;color:#e2e8f0;">
              <summary style="cursor:pointer;padding:4px 0;">Suche &amp; Filter</summary>
              <div style="display:flex;flex-wrap:wrap;gap:6px;padding-top:6px;">
                <input id="a3d-structure-search" type="search" aria-label="Struktur suchen" placeholder="Name suchen, z. B. psoas oder Fascia" style="flex:1 1 180px;min-width:0;background:#1e293b;color:#e2e8f0;border:1px solid #475569;border-radius:6px;padding:6px;">
                <select id="a3d-filter-side" aria-label="Nach Körperseite filtern" style="background:#1e293b;color:#e2e8f0;border-radius:6px;padding:6px;"><option value="">Alle Seiten</option><option value="left">Links</option><option value="right">Rechts</option><option value="midline">Mittig</option></select>
                <select id="a3d-filter-kind" aria-label="Nach Gewebe filtern" style="background:#1e293b;color:#e2e8f0;border-radius:6px;padding:6px;"><option value="">Alle Gewebe</option><option value="muscle">Muskeln</option><option value="fascia">Faszien / Aponeurosen</option><option value="tendon">Sehnen</option></select>
                <button id="a3d-filter-reset" type="button" style="background:#1e293b;color:#e2e8f0;border:1px solid #475569;border-radius:6px;padding:6px;">Filter löschen</button>
              </div>
              <span id="a3d-search-count" role="status" style="display:block;padding-top:4px;color:#94a3b8;"></span>
            </details>
            <label id="a3d-muscle-mode-label" style="font-size:.72rem;color:#e2e8f0;"><input id="a3d-muscle-mode" type="checkbox" checked> Einzelstrukturen</label>
            <label id="a3d-isolate-label" style="font-size:.72rem;color:#e2e8f0;"><input id="a3d-isolate" type="checkbox"> Auswahl freistellen</label>
            <details id="a3d-layers" hidden style="flex-basis:100%;font-size:.72rem;color:#e2e8f0;">
              <summary style="cursor:pointer;padding:5px 0;">Gewebeschichten</summary>
              <div style="display:flex;gap:8px;flex-wrap:wrap;padding:6px 0;">
                <label><input type="checkbox" data-layer="skin"> Haut / Außenansicht</label>
                <label><input type="checkbox" data-layer="fur"> Kurzhaar-Fell</label>
                <label><input type="checkbox" data-layer="fascia" checked> Faszien (vorhandene)</label>
                <label><input type="checkbox" data-layer="muscle" checked> Muskeln</label>
                <label><input type="checkbox" data-layer="tendon" checked> Sehnen / Bänder</label>
                <label><input type="checkbox" data-layer="skeleton" checked> Skelett</label>
              </div>
              <label>Faszien-Deckkraft <input id="a3d-fascia-opacity" aria-label="Faszien-Deckkraft" type="range" min="0.15" max="1" step="0.05" value="1" style="vertical-align:middle;width:110px;"></label>
              <div id="a3d-layer-status" role="status" style="color:#cbd5e1;padding-top:4px;"></div>
              <div style="color:#94a3b8;font-size:.65rem;">Faszien nur teilweise in der Quelle enthalten. Hautansicht blendet tiefere Schichten aus.</div>
            </details>
            <button type="button" id="a3d-atlas-btn" hidden style="background:#1e3a5f;color:#dbeafe;border:1px solid #4778a8;border-radius:6px;padding:6px 12px;font-size:.75rem;">Anatomie-Atlas</button>
            <span id="a3d-surface-note" style="flex-basis:100%;font-size:.65rem;color:#94a3b8;"></span>
          </div>

          <section id="a3d-atlas-selection" hidden style="position:absolute;bottom:12px;left:12px;right:12px;z-index:22;padding:12px;border:1px solid #475569;border-radius:10px;background:#101a2bf2;color:#e2e8f0;font-size:.8rem;">
            <strong id="a3d-atlas-selection-title"></strong>
            <p style="margin:6px 0;">Skelettansicht zur Orientierung · keine Knochenbefund-Erfassung</p>
            <label><input type="checkbox" id="a3d-atlas-context"> Gesamtes Skelett anzeigen</label>
            <button type="button" id="a3d-atlas-back" style="margin-left:8px;padding:8px;border-radius:6px;background:#1e293b;color:#e2e8f0;border:1px solid #475569;">Zurück zur Schmerzanalyse</button>
          </section>

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

        this._atlas = new AnatomyAtlas(this.container, entry => this._selectAtlasEntry(entry));
        this._bindUI();
    }

    _bindUI() {
        const c = this.container;
        c.querySelector('#a3d-atlas-btn').addEventListener('click', () => this._atlas.open(this._activeGroups()));
        c.querySelector('#a3d-atlas-back').addEventListener('click', () => {this._clearAtlasSelection();this._resetCamera();});
        c.querySelector('#a3d-atlas-context').addEventListener('change', () => this._updateIsolation());

        c.querySelector('#a3d-muscle-mode').addEventListener('change', e => this._setMuscleMode(e.target.checked));
        c.querySelectorAll('[data-layer]').forEach(input => input.addEventListener('change', () => this._setLayer(input.dataset.layer, input.checked)));
        c.querySelector('#a3d-fascia-opacity').addEventListener('input', () => this._updateIsolation());
        c.querySelector('#a3d-isolate').addEventListener('change', () => {
            this._updateIsolation();
            if (this.selectedKey && c.querySelector('#a3d-isolate').checked) this._fitStructure(this._defFromKey(this.selectedKey));
            else this._resetCamera();
        });
        c.querySelector('#a3d-structure-search').addEventListener('input', () => this._filterStructures());
        c.querySelectorAll('#a3d-filter-side, #a3d-filter-kind').forEach(input => input.addEventListener('change', () => this._filterStructures()));
        c.querySelector('#a3d-filter-reset').addEventListener('click', () => this._resetStructureFilters());
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
        const amb = new THREE.HemisphereLight(0xeaf1ff, 0x554438, 1.15);
        this.scene.add(amb);
        const key = new THREE.DirectionalLight(0xfff0e2, 2.1);
        key.position.set(2, 3, 2);
        key.castShadow = false;
        this.scene.add(key);
        this._keyLight = key;
        key.shadow.mapSize.set(1024,1024);
        Object.assign(key.shadow.camera, {left:-1.5,right:1.5,top:1.5,bottom:-1.5,near:.1,far:8});
        key.shadow.normalBias = .006;
        key.shadow.bias = -.0001;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        const fill = new THREE.DirectionalLight(0xc5d9f0, 0.8);
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
        this._resizeObserver.observe(this.container.querySelector('#a3d-form'));
        this._resizeObserver.observe(this.container.querySelector('#a3d-atlas-selection'));

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
        if (this._atlasSelection) this._fitStructure(this._atlasSelection);
        else if (this.selectedKey && this._exactMuscles() && this.container.querySelector('#a3d-isolate').checked) {
            this._fitStructure(this._defFromKey(this.selectedKey));
        } else if (this.modelGroup && this._lastAspect !== this.camera.aspect) this._resetCamera();
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
        this._atlas.close();
        this._clearAtlasSelection();
        this._layerLoads.clear();
        this._layerMessages.clear();
        this._skinLayer = null;
        this._fur = null;
        if (this._ground) { this.scene.remove(this._ground); this._disposeObject(this._ground); this._ground = null; }
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
        this._surfaces = null;
        this._modelBox = null;
        this._viewBox = null;
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
        this._keyLight.shadow.dispose();
        this.renderer.dispose();
    }

    /* Model requests can finish out of order when the animal or patient changes. */
    async _loadModel(species) {
        const paths = {dog:'./models/Hund.glb?v=20261005',cat:'./models/katze.glb?v=20261005',horse:'./models/Pferd.glb?v=20261005'};
        const segmented = this.muscleMode ? SEGMENTED_MODELS[species] : null;
        if (segmented) paths[species] = './models/' + segmented.file;
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
            if (segmented) validateMuscleModel(gltf.scene, species, segmented.definitions);
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
        this.grid.visible = !this._exactMuscles();
        this.renderer.shadowMap.enabled = this._exactMuscles();
        this._keyLight.castShadow = this._exactMuscles();
        if (this._exactMuscles()) {
            styleAnatomy(model);
            this._ground = new THREE.Mesh(new THREE.PlaneGeometry(6,6), new THREE.ShadowMaterial({opacity:.3}));
            this._ground.rotation.x = -Math.PI/2;
            this._ground.position.y = finalBox.min.y - .015;
            this._ground.receiveShadow = true;
            this.scene.add(this._ground);
        }
        this._buildHotspots(species);
        this._surfaces = new PainSurfaces(this._modelMeshes, this._activeGroups(), this._exactMuscles(), this._leftSign());
        this.container.querySelector('#a3d-muscle-mode-label').hidden = !SEGMENTED_MODELS[species];
        this.container.querySelector('#a3d-muscle-mode').checked = this.muscleMode;
        this.container.querySelector('#a3d-isolate-label').hidden = !this._exactMuscles();
        this.container.querySelector('#a3d-debug-btn').style.display = this._exactMuscles() ? 'none' : 'flex';
        this.container.querySelector('#a3d-surface-note').textContent = this._exactMuscles()
            ? `${this._activeGroups().filter(d => !d.kind || d.kind === 'muscle').length} Muskelstrukturen · ${this._activeGroups().filter(d => d.kind && d.kind !== 'muscle').length} weitere Gewebestrukturen. Tiefe Strukturen über Suche und Freistellen öffnen. Unbefundet: Naturfarbe.`
            : 'Regionale Flächenfärbung (Näherung): keine exakten Einzelmuskelgrenzen. Unbefundet: Naturfarbe.';
        const layers = SEGMENTED_MODELS[species]?.layers;
        this.container.querySelector('#a3d-layers').hidden = !this._exactMuscles() || !layers;
        this.container.querySelector('#a3d-atlas-btn').hidden = !(this._exactMuscles() && species === 'dog' && layers?.skeletonAtlas);
        this.container.querySelector('#a3d-layer-status').textContent = '';
        this._syncLayerInputs();
        this._updateIsolation();
        this._renderList();
        this._applyPainToHotspots();
        this._resetCamera();
        this._hideLoading();
        if (this._exactMuscles() && layers) {
            for (const layer of Object.keys(layers)) if (this._layerState[layer]) this._loadLayer(layer);
        }
    }

    _buildHotspots(species) {
        // Calibrated coordinates are already on the surface. No runtime ray
        // projection can move a leg landmark onto the chest or throat.
        const groups = this._activeGroups();
        this._resetStructureFilters();
        this.container.querySelector('#a3d-filter-kind').hidden = !this._exactMuscles();
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

    _resetStructureFilters() {
        for (const id of ['a3d-structure-search','a3d-filter-side','a3d-filter-kind']) this.container.querySelector('#'+id).value = '';
        this._filterStructures();
    }

    _filterStructures() {
        const c = this.container, all = this._activeGroups();
        const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('de').replace(/[_.,]/g,' ');
        const terms = normalize(c.querySelector('#a3d-structure-search').value.trim()).split(/\s+/).filter(Boolean);
        const side = c.querySelector('#a3d-filter-side').value;
        const kind = this._exactMuscles() ? c.querySelector('#a3d-filter-kind').value : '';
        const matches = all.filter(def => (!side || def.side === side) && (!kind || (def.kind || 'muscle') === kind)
            && terms.every(term => normalize(`${def.label} ${def.anatomical} ${def.sourceName || ''}`).includes(term)));
        const select = c.querySelector('#a3d-region-select');
        select.replaceChildren(new Option(matches.length ? `${matches.length} Strukturen auswählen…` : 'Keine Treffer – Filter ändern', ''));
        for (const def of matches) select.add(new Option(this._exactMuscles() ? def.label : `${def.anatomical} – ${def.label}`, def.id));
        select.disabled = !matches.length;
        if (this.selectedKey && matches.some(d => d.id === this.selectedKey.split('::')[0])) select.value = this.selectedKey.split('::')[0];
        c.querySelector('#a3d-search-count').textContent = `${matches.length} von ${all.length} Strukturen · Seiten aus Sicht des Tieres`;
    }

    _pickHotspot() {
        if (!this.modelGroup) return null;
        this.scene.updateMatrixWorld(true);
        this.camera.updateMatrixWorld(true);
        if (this._exactMuscles()) {
            const ray = new THREE.Raycaster();
            ray.setFromCamera(this.pointer, this.camera);
            const hit = ray.intersectObjects(this._modelMeshes.filter(m => m.visible), false)[0];
            return hit ? this.hotspots.find(h => h.def.id === hit.object.userData.muscleId)?.mesh || null : null;
        }
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
        this._clearAtlasSelection();
        const savedKeys = Object.keys(this.painData).filter(key => key.split('::')[0] === def.id);
        this.selectedKey = storedKey || (savedKeys.length === 1 ? savedKeys[0] : `${def.id}::${def.side}`);
        const existing   = this.painData[this.selectedKey] || {};

        const c = this.container;
        if (![...c.querySelector('#a3d-region-select').options].some(o => o.value === def.id)) this._resetStructureFilters();
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
        sideSelect.disabled = this._exactMuscles() || Boolean(existing.id) || def.side !== 'midline';
        c.querySelector('#a3d-notes').value = existing.notes || '';

        c.querySelector('#a3d-remove-btn').style.display = existing.id ? '' : 'none';
        c.querySelector('#a3d-search-panel').open = false;
        c.querySelector('#a3d-layers').open = false;
        c.querySelector('#a3d-form').style.display = 'block';
        this._updateIsolation();
        if (this._exactMuscles() && c.querySelector('#a3d-isolate').checked) this._fitStructure(def);
    }

    _closeForm() {
        const wasIsolated = this._exactMuscles() && this.selectedKey && this.container.querySelector('#a3d-isolate').checked;
        this.container.querySelector('#a3d-form').style.display = 'none';
        /* Restore preview to actual pain state */
        if (this.selectedKey) {
            this._applyPainToHotspots();
        }
        this.selectedKey = null;
        this._updateIsolation();
        if (wasIsolated && this._modelBox) this._resetCamera();
        this.container.querySelector('#a3d-region-select').value = '';
    }

    _previewPain(key, level) {
        this._dirty = true;
        this._paintSurfaces(key, level);
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
        this._paintSurfaces();
        this.hotspots.forEach(({ mesh, marker, def }) => {
            const entry = this._painForDef(def);
            mesh.visible = false; /* identity object is not rendered */
            marker.visible = !this._exactMuscles();

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
        const entries = Object.entries(this.painData);

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
            el.addEventListener('click', async () => {
                const species = this.animalType;
                if (SEGMENTED_MODELS[species]) await this._setMuscleMode(el.dataset.key.startsWith(`${species}_mesh_`));
                if (this._disposed || this.animalType !== species || !this.hotspots.some(h => h.def.id === el.dataset.key.split('::')[0])) return;
                const def = this._defFromKey(el.dataset.key);
                if (def) {
                    this._focusHotspot(def);
                    this._openForm(def, el.dataset.key);
                }
            });
        });
    }

    _focusHotspot(def) {
        if (this._exactMuscles()) {
            this.container.querySelector('#a3d-isolate').checked = true;
            this._fitStructure(def);
            return;
        }
        /* Focus the calibrated point, including when opened from the saved list. */
        const entry = this.hotspots.find(h => h.def.id === def.id && h.def.side === def.side);
        const pos  = entry?.pos ? entry.pos.clone() : new THREE.Vector3(...def.pos);
        const dist = Math.max(1.5, 1.1 / Math.max(0.4, this.camera.aspect));
        const left = this._leftSign();
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

    _fitStructure(def) {
        if (!def || !this._exactMuscles()) return;
        this.scene.updateMatrixWorld(true);
        const box = new THREE.Box3();
        const meshes = this._atlasSelection?.id === def.id ? this._modelMeshes.filter(m => m.userData.atlasId === def.id) : this._surfaces?.entries.get(def.id) || [];
        for (const mesh of meshes) box.union(new THREE.Box3().setFromObject(mesh));
        if (box.isEmpty()) return;
        const sphere = box.getBoundingSphere(new THREE.Sphere());
        const rect = this.renderer.domElement.getBoundingClientRect();
        const toolbar = this.container.querySelector('#a3d-toolbar').getBoundingClientRect();
        const form = this.container.querySelector(this._atlasSelection ? '#a3d-atlas-selection' : '#a3d-form');
        const top = Math.max(12, toolbar.bottom-rect.top+12);
        const bottom = (this._atlasSelection || form.style.display === 'block') ? form.getBoundingClientRect().top-rect.top-12 : rect.height-20;
        const available = Math.max(80, bottom-top);
        const vfov = 2*Math.atan(Math.tan(THREE.MathUtils.degToRad(this.camera.fov)/2)*available/rect.height);
        const hfov = 2*Math.atan(Math.tan(THREE.MathUtils.degToRad(this.camera.fov)/2)*this.camera.aspect*.9);
        const distance = Math.max(.045, sphere.radius/Math.sin(Math.min(vfov,hfov)/2)*1.15);
        const sign = def.side === 'right' ? -this._leftSign() : this._leftSign();
        const direction = new THREE.Vector3(sign,.12,.08).normalize();
        this.camera.position.copy(sphere.center).addScaledVector(direction,distance);
        this.camera.near = .001;
        this.camera.setViewOffset(rect.width,rect.height,0,rect.height/2-(top+bottom)/2,rect.width,rect.height);
        this.controls.minDistance = .025;
        this.controls.maxDistance = Math.max(8,distance*2);
        this.controls.target.copy(sphere.center);
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

    _exactMuscles() { return Boolean(this.muscleMode && SEGMENTED_MODELS[this.animalType]); }

    _leftSign() { return this._exactMuscles() ? SEGMENTED_MODELS[this.animalType].leftSign : this.animalType === 'dog' ? 1 : -1; }

    _activeGroups() { return this._exactMuscles() ? SEGMENTED_MODELS[this.animalType].definitions : MUSCLE_GROUPS[this.animalType] || []; }

    async _setMuscleMode(enabled) {
        if (this.muscleMode === enabled) return;
        this._closeForm();
        this.muscleMode = enabled;
        if (SEGMENTED_MODELS[this.animalType]) await this._loadModel(this.animalType);
    }

    _paintSurfaces(previewKey = null, previewLevel = null) {
        const previewId = previewKey?.split('::')[0];
        const definitions = new Map(this._activeGroups().map(d => [d.id, d]));
        const previewSide = this.container.querySelector('#a3d-side-sel').value;
        this._surfaces?.paint((id, surfaceSide) => {
            const divided = surfaceSide && definitions.get(id)?.side === 'midline';
            const matches = side => !divided || side === 'midline' || side === 'bilateral' || side === surfaceSide;
            if (id === previewId && matches(previewSide)) return previewLevel;
            const values = Object.entries(this.painData)
                .filter(([key]) => key.split('::')[0] === id && matches(key.split('::')[1]))
                .map(([,entry]) => Number(entry.painLevel));
            return values.length ? Math.max(...values) : null;
        }, painColor);
    }

    _updateIsolation() {
        const atlas = this._atlasSelection;
        const id = this.selectedKey?.split('::')[0];
        const isolate = this._exactMuscles() && id && this.container.querySelector('#a3d-isolate').checked;
        const opacity = Number(this.container.querySelector('#a3d-fascia-opacity').value);
        const skinVisible = this._layerState.skin && this._modelMeshes?.some(m => m.userData.anatomyLayer === 'skin');
        if (this._fur) this._fur.visible = Boolean(skinVisible && this._layerState.fur && !isolate && !atlas);
        this._modelMeshes?.forEach(mesh => {
            const layer = mesh.userData.anatomyLayer;
            let visible = layer === 'context'
                ? this._layerState.muscle || this._layerState.skin
                : this._layerState[layer] !== false;
            // Keep the compact skeleton outside atlas inspection to limit draw calls.
            if (mesh.userData.atlasId) visible = false;
            if (skinVisible) visible = layer === 'skin';
            if (isolate) visible = mesh.userData.muscleId === id;
            if (atlas) visible = Boolean(mesh.userData.atlasId && (mesh.userData.atlasId === atlas.id || this.container.querySelector('#a3d-atlas-context').checked));
            if (mesh.userData.atlasId) {
                mesh.material.color.set(mesh.userData.atlasId === atlas?.id ? 0x60a5fa : 0xe2d7ba);
                mesh.material.emissive.setHex(mesh.userData.atlasId === atlas?.id ? 0x163552 : 0);
            }
            mesh.visible = !this._exactMuscles() || visible;
            if (layer === 'fascia') {
                mesh.material.opacity = opacity;
                const transparent = opacity < 1;
                if (mesh.material.transparent !== transparent) mesh.material.needsUpdate = true;
                mesh.material.transparent = transparent;
                mesh.material.depthWrite = !transparent;
                mesh.castShadow = !transparent;
            }
        });
        this._dirty = true;
    }

    _syncLayerInputs() {
        this.container.querySelectorAll('[data-layer]').forEach(input => { input.checked = this._layerState[input.dataset.layer]; });
    }

    async _setLayer(layer, enabled) {
        if (!(layer in this._layerState)) return;
        this._clearAtlasSelection();
        // Leaving an isolated selection restores the chosen full-body layers.
        this._closeForm();
        this._layerState[layer] = enabled;
        if (layer === 'fur' && enabled) this._layerState.skin = true;
        this._syncLayerInputs();
        this._updateIsolation();
        if (enabled) await this._loadLayer(layer === 'fur' ? 'skin' : layer);
        this._ensureFur();
        this._updateIsolation();
    }

    _clearAtlasSelection() {
        this._atlasRequest = (this._atlasRequest || 0) + 1;
        this._atlasSelection = null;
        this.container.querySelector('#a3d-atlas-selection').hidden = true;
        this._updateIsolation();
    }

    async _selectAtlasEntry(entry) {
        if (entry.status === 'missing' || this.animalType !== 'dog' || !this._exactMuscles()) return;
        this._clearAtlasSelection();
        this._closeForm();
        if (entry.layer) {
            await this._setLayer(entry.layer, true);
            this.container.querySelector('#a3d-layers').open = true;
            return;
        }
        if (entry.kind !== 'skeleton') {
            this.container.querySelector('#a3d-isolate').checked = true;
            this._openForm(entry);
            return;
        }
        const version = this._loadVersion, request = this._atlasRequest;
        this.container.querySelector('#a3d-layers').open = true;
        await this._loadLayer('skeletonAtlas');
        if (this._disposed || version !== this._loadVersion || request !== this._atlasRequest) return;
        if (!this._modelMeshes.some(m => m.userData.atlasId === entry.id)) {
            this.container.querySelector('#a3d-layers').open = true;
            return;
        }
        this._atlasSelection = entry;
        this.container.querySelector('#a3d-search-panel').open = false;
        this.container.querySelector('#a3d-layers').open = false;
        this.container.querySelector('#a3d-atlas-selection-title').textContent = `${entry.anatomical} · ${SIDE_LABELS[entry.side] || ''}`;
        this.container.querySelector('#a3d-atlas-selection').hidden = false;
        this.container.querySelector('#a3d-atlas-context').checked = false;
        this._updateIsolation();
        this._fitStructure(entry);
    }

    _setLayerStatus(layer, message) {
        // Concurrent layer loads must not erase another layer's retry message.
        if (message) this._layerMessages.set(layer, message);
        else this._layerMessages.delete(layer);
        this.container.querySelector('#a3d-layer-status').textContent = [...this._layerMessages.values()].join(' ');
    }

    _ensureFur() {
        if (!this._skinLayer || !this._layerState.fur || this._fur) return;
        try {
            this._fur = createShortCoat(this._skinLayer);
            if (!this._fur) throw new Error('No coat surface');
            this._setLayerStatus('fur', '');
        } catch (error) {
            // Optional cosmetics must never discard an otherwise usable skin layer.
            this._layerState.fur = false;
            this._syncLayerInputs();
            this._setLayerStatus('fur', 'Kurzhaar-Fell konnte nicht erstellt werden. Die Haut bleibt nutzbar. Zum Wiederholen erneut einschalten.');
        }
    }

    async _loadLayer(layer) {
        const file = this._exactMuscles() && SEGMENTED_MODELS[this.animalType]?.layers?.[layer];
        if (!file || !this.modelGroup) return;
        if (this._layerLoads.has(layer)) return this._layerLoads.get(layer);
        const version = this._loadVersion, parent = this.modelGroup;
        const path = './models/' + file;
        const label = layer === 'skin' ? 'Haut' : 'Skelett';
        const task = (async () => {
            let decoded;
            try {
                this._setLayerStatus(layer, `${label} wird geladen…`);
                let bytes = MODEL_BYTES.get(path);
                if (!bytes) {
                    const response = await fetch(path, {signal:this._fetchController.signal});
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    bytes = await response.arrayBuffer();
                    MODEL_BYTES.set(path, bytes);
                }
                if (this._disposed || version !== this._loadVersion) return;
                decoded = (await this.loader.parseAsync(bytes, path.slice(0,path.lastIndexOf('/')+1))).scene;
                if (this._disposed || version !== this._loadVersion) { this._disposeObject(decoded); return; }
                if (layer === 'skeletonAtlas') validateSkeletonAtlas(decoded);
                styleAnatomy(decoded);
                parent.add(decoded);
                if (layer === 'skin') {
                    this._skinLayer = decoded;
                }
                decoded.traverse(mesh => { if (mesh.isMesh) this._modelMeshes.push(mesh); });
                parent.updateMatrixWorld(true);
                this._viewBox = new THREE.Box3().setFromObject(parent);
                this._updateIsolation();
                if (this._atlasSelection) this._fitStructure(this._atlasSelection);
                else if (!this.selectedKey) this._resetCamera();
                this._setLayerStatus(layer, '');
                this._ensureFur();
                this._updateIsolation();
            } catch (error) {
                if (decoded) this._disposeObject(decoded);
                if (this._disposed || version !== this._loadVersion || error.name === 'AbortError') return;
                MODEL_BYTES.delete(path);
                this._layerLoads.delete(layer);
                this._layerState[layer] = false;
                this._syncLayerInputs();
                this._setLayerStatus(layer, `${label} konnte nicht geladen werden. Zum Wiederholen erneut einschalten.`);
            }
        })();
        this._layerLoads.set(layer, task);
        return task;
    }

    _defFromKey(key) {
        const groups = [...(MUSCLE_GROUPS[this.animalType] || []), ...(SEGMENTED_MODELS[this.animalType]?.definitions || [])];
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
        this.camera.clearViewOffset();
        this.camera.near = .01;
        this.camera.updateProjectionMatrix();
        this.controls.minDistance = .5;
        const sphere = (this._viewBox || this._modelBox?.box)?.getBoundingSphere(new THREE.Sphere());
        const radius = sphere?.radius || 1.3;
        const vfov = THREE.MathUtils.degToRad(this.camera.fov);
        const hfov = 2 * Math.atan(Math.tan(vfov/2)*this.camera.aspect);
        const distance = radius / Math.sin(Math.min(vfov,hfov)/2) * 1.08;
        const sign = this._leftSign();
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
