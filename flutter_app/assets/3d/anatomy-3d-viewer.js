/**
 * TheraPano — 3D Anatomie-Schmerzanalyse Viewer
 * Three.js r160 ESM, keine externe CDN-Abhängigkeit zur Laufzeit.
 *
 * Einstieg: window.Anatomy3D.init(containerId, patientId, animalType, csrfToken)
 *
 * Architektur:
 *  - GLB-Modell laden via GLTFLoader
 *  - Mesh-Inspektion: falls Named Meshes brauchbar → direkt nutzen
 *  - Fallback: Hotspot-Zonen als unsichtbare BoxGeometry über dem Modell
 *  - Raycasting auf Hotspots + sichtbares Modell
 *  - Material-Cloning für Highlight (Originale werden NICHT mutiert)
 *  - Schmerzformular als Overlay-Panel
 *  - AJAX POST/GET gegen /api/patienten/{id}/schmerzpunkte
 *  - Vollbild via Fullscreen API
 */

import * as THREE from 'three';
import { OrbitControls } from './vendor/three/OrbitControls.js';
import { GLTFLoader }    from './vendor/three/GLTFLoader.js';

/* ═══════════════════════════════════════════════════════
   MUSCLE GROUP DEFINITIONS
   Manuelle Hotspot-Zonen in normalisiertem Modell-Raum.
   position: Schwerpunkt der Region [x, y, z]
   size:     halbe Box-Ausdehnung   [w, h, d]
   (Koordinaten gelten nach Auto-Zentrierung/-Skalierung
    des geladenen GLB auf eine 2-Einheiten-Boundingbox)
═══════════════════════════════════════════════════════ */

/** @type {Record<string, MuscleGroupDef[]>} */
const MUSCLE_GROUPS = {

  /* ── HUND ─────────────────────────────────────────────────────────────────────
   *  GLB-Bounds: X:±0.360(Seite), Y:±0.840(Höhe), Z:±1.000(Länge)
   *  GEMESSEN: Kopf=RECHTS im Bild → Kopf=-Z, Schwanz=+Z (Kamera auf +Z schaut nach -Z)
   *  X-Asymmetrie: +X:0.184, -X:-0.089 → sichtbare Seite (links im Bild) ist +X
   *  Rücken Y≈+0.20..+0.28, Bauch Y≈-0.15, Pfoten Y≈-0.70
   * ──────────────────────────────────────────────────────────────────────────── */
  dog: [
    { id:'dog_head',             label:'Kopfmuskulatur',              anatomical:'Musculi capitis',              region:'head',      side:'midline', pos:[ 0.00, 0.12,-0.78], size:[0.18,0.16,0.14] },
    { id:'dog_jaw',              label:'Kaumuskulatur',               anatomical:'M. masseter / temporalis',     region:'head',      side:'midline', pos:[ 0.00,-0.10,-0.76], size:[0.15,0.12,0.12] },
    { id:'dog_neck',             label:'Nackenmuskulatur',            anatomical:'Mm. nuchae',                   region:'neck',      side:'midline', pos:[ 0.00, 0.22,-0.58], size:[0.14,0.10,0.14] },
    { id:'dog_neck_ventral',     label:'Halsmuskulatur ventral',      anatomical:'Mm. colli ventrales',          region:'neck',      side:'midline', pos:[ 0.00,-0.10,-0.55], size:[0.13,0.10,0.14] },
    { id:'dog_shoulder_l',       label:'Schultermuskulatur links',    anatomical:'M. deltoideus / infraspinatus',region:'shoulder',  side:'left',   pos:[ 0.18, 0.16,-0.42], size:[0.10,0.14,0.12] },
    { id:'dog_shoulder_r',       label:'Schultermuskulatur rechts',   anatomical:'M. deltoideus / infraspinatus',region:'shoulder',  side:'right',  pos:[-0.18, 0.16,-0.42], size:[0.10,0.14,0.12] },
    { id:'dog_chest',            label:'Brustmuskulatur',             anatomical:'M. pectoralis',                region:'chest',     side:'midline', pos:[ 0.00,-0.18,-0.42], size:[0.20,0.12,0.14] },
    { id:'dog_thoracic',         label:'Rückenmuskulatur (BWS)',      anatomical:'M. longissimus dorsi',         region:'back',      side:'midline', pos:[ 0.00, 0.24,-0.05], size:[0.12,0.10,0.30] },
    { id:'dog_lumbar',           label:'Lendenmuskulatur',            anatomical:'M. iliopsoas / multifidus',    region:'lumbar',    side:'midline', pos:[ 0.00, 0.22, 0.28], size:[0.12,0.10,0.22] },
    { id:'dog_belly',            label:'Bauchmuskulatur',             anatomical:'M. rectus abdominis',          region:'abdomen',   side:'midline', pos:[ 0.00,-0.16,-0.02], size:[0.16,0.10,0.38] },
    { id:'dog_hip_l',            label:'Hüftmuskulatur links',        anatomical:'M. gluteus medius',            region:'hip',       side:'left',   pos:[ 0.18, 0.20, 0.44], size:[0.10,0.14,0.12] },
    { id:'dog_hip_r',            label:'Hüftmuskulatur rechts',       anatomical:'M. gluteus medius',            region:'hip',       side:'right',  pos:[-0.18, 0.20, 0.44], size:[0.10,0.14,0.12] },
    { id:'dog_glute_l',          label:'Glutealmuskulatur links',     anatomical:'M. gluteus superficialis',     region:'gluteal',   side:'left',   pos:[ 0.18, 0.08, 0.56], size:[0.10,0.14,0.12] },
    { id:'dog_glute_r',          label:'Glutealmuskulatur rechts',    anatomical:'M. gluteus superficialis',     region:'gluteal',   side:'right',  pos:[-0.18, 0.08, 0.56], size:[0.10,0.14,0.12] },
    { id:'dog_fore_l',           label:'Vorderbeinmuskulatur links',  anatomical:'M. triceps brachii',           region:'forelimb',  side:'left',   pos:[ 0.20,-0.28,-0.40], size:[0.08,0.22,0.09] },
    { id:'dog_fore_r',           label:'Vorderbeinmuskulatur rechts', anatomical:'M. triceps brachii',           region:'forelimb',  side:'right',  pos:[-0.20,-0.28,-0.40], size:[0.08,0.22,0.09] },
    { id:'dog_hind_l',           label:'Hinterbeinmuskulatur links',  anatomical:'M. biceps femoris',            region:'hindlimb',  side:'left',   pos:[ 0.18,-0.26, 0.55], size:[0.08,0.24,0.10] },
    { id:'dog_hind_r',           label:'Hinterbeinmuskulatur rechts', anatomical:'M. biceps femoris',            region:'hindlimb',  side:'right',  pos:[-0.18,-0.26, 0.55], size:[0.08,0.24,0.10] },
    { id:'dog_carpus_l',         label:'Karpalgelenk links',          anatomical:'Regio carpalis',               region:'carpus',    side:'left',   pos:[ 0.20,-0.52,-0.38], size:[0.07,0.07,0.07] },
    { id:'dog_carpus_r',         label:'Karpalgelenk rechts',         anatomical:'Regio carpalis',               region:'carpus',    side:'right',  pos:[-0.20,-0.52,-0.38], size:[0.07,0.07,0.07] },
    { id:'dog_tarsus_l',         label:'Sprunggelenk links',          anatomical:'Regio tarsi',                  region:'tarsus',    side:'left',   pos:[ 0.18,-0.50, 0.66], size:[0.07,0.07,0.07] },
    { id:'dog_tarsus_r',         label:'Sprunggelenk rechts',         anatomical:'Regio tarsi',                  region:'tarsus',    side:'right',  pos:[-0.18,-0.50, 0.66], size:[0.07,0.07,0.07] },
    { id:'dog_paw_fl',           label:'Pfote vorne links',           anatomical:'Regio manus',                  region:'paw',       side:'left',   pos:[ 0.20,-0.70,-0.38], size:[0.06,0.05,0.07] },
    { id:'dog_paw_fr',           label:'Pfote vorne rechts',          anatomical:'Regio manus',                  region:'paw',       side:'right',  pos:[-0.20,-0.70,-0.38], size:[0.06,0.05,0.07] },
    { id:'dog_paw_hl',           label:'Pfote hinten links',          anatomical:'Regio pedis',                  region:'paw',       side:'left',   pos:[ 0.18,-0.70, 0.74], size:[0.06,0.05,0.07] },
    { id:'dog_paw_hr',           label:'Pfote hinten rechts',         anatomical:'Regio pedis',                  region:'paw',       side:'right',  pos:[-0.18,-0.70, 0.74], size:[0.06,0.05,0.07] },
    { id:'dog_tail',             label:'Schwanzbasis',                anatomical:'Regio caudalis',               region:'tail',      side:'midline', pos:[ 0.00, 0.12, 0.86], size:[0.10,0.09,0.10] },
  ],

  /* ── KATZE ─────────────────────────────────────────────────── */
  /* Bounds: X:±0.261(Seite), Y:±0.742(Höhe), Z:±1.000(Länge)
   * GEMESSEN: Kopf=RECHTS im Bild → Kopf=-Z, Schwanz=+Z
   * X symmetrisch: +X:0.027, -X:-0.027 → sichtbare Seite (links im Bild) = +X
   * Rücken Y≈+0.18..+0.30, Bauch Y≈-0.10, Pfoten Y≈-0.60 */
  cat: [
    { id:'cat_head',             label:'Kopfmuskulatur',              anatomical:'Musculi capitis',              region:'head',      side:'midline', pos:[ 0.00, 0.18,-0.80], size:[0.14,0.14,0.12] },
    { id:'cat_jaw',              label:'Kaumuskulatur',               anatomical:'M. masseter / temporalis',     region:'head',      side:'midline', pos:[ 0.00,-0.04,-0.78], size:[0.12,0.11,0.09] },
    { id:'cat_neck',             label:'Nackenmuskulatur',            anatomical:'Mm. nuchae',                   region:'neck',      side:'midline', pos:[ 0.00, 0.24,-0.60], size:[0.12,0.10,0.12] },
    { id:'cat_neck_ventral',     label:'Halsmuskulatur ventral',      anatomical:'Mm. colli ventrales',          region:'neck',      side:'midline', pos:[ 0.00,-0.06,-0.57], size:[0.11,0.10,0.12] },
    { id:'cat_shoulder_l',       label:'Schultermuskulatur links',    anatomical:'M. deltoideus',                region:'shoulder',  side:'left',   pos:[ 0.16, 0.18,-0.44], size:[0.09,0.13,0.11] },
    { id:'cat_shoulder_r',       label:'Schultermuskulatur rechts',   anatomical:'M. deltoideus',                region:'shoulder',  side:'right',  pos:[-0.16, 0.18,-0.44], size:[0.09,0.13,0.11] },
    { id:'cat_chest',            label:'Brustmuskulatur',             anatomical:'M. pectoralis',                region:'chest',     side:'midline', pos:[ 0.00,-0.14,-0.44], size:[0.18,0.11,0.13] },
    { id:'cat_thoracic',         label:'Rückenmuskulatur (BWS)',      anatomical:'M. longissimus dorsi',         region:'back',      side:'midline', pos:[ 0.00, 0.26,-0.10], size:[0.11,0.09,0.28] },
    { id:'cat_lumbar',           label:'Lendenmuskulatur',            anatomical:'M. iliopsoas',                 region:'lumbar',    side:'midline', pos:[ 0.00, 0.24, 0.20], size:[0.11,0.09,0.20] },
    { id:'cat_belly',            label:'Bauchmuskulatur',             anatomical:'M. rectus abdominis',          region:'abdomen',   side:'midline', pos:[ 0.00,-0.10, 0.00], size:[0.14,0.09,0.35] },
    { id:'cat_hip_l',            label:'Hüftmuskulatur links',        anatomical:'M. gluteus medius',            region:'hip',       side:'left',   pos:[ 0.16, 0.20, 0.42], size:[0.09,0.13,0.11] },
    { id:'cat_hip_r',            label:'Hüftmuskulatur rechts',       anatomical:'M. gluteus medius',            region:'hip',       side:'right',  pos:[-0.16, 0.20, 0.42], size:[0.09,0.13,0.11] },
    { id:'cat_glute_l',          label:'Glutealmuskulatur links',     anatomical:'M. gluteus superficialis',     region:'gluteal',   side:'left',   pos:[ 0.16, 0.10, 0.52], size:[0.09,0.13,0.11] },
    { id:'cat_glute_r',          label:'Glutealmuskulatur rechts',    anatomical:'M. gluteus superficialis',     region:'gluteal',   side:'right',  pos:[-0.16, 0.10, 0.52], size:[0.09,0.13,0.11] },
    { id:'cat_fore_l',           label:'Vorderbeinmuskulatur links',  anatomical:'M. triceps brachii',           region:'forelimb',  side:'left',   pos:[ 0.18,-0.20,-0.42], size:[0.07,0.21,0.08] },
    { id:'cat_fore_r',           label:'Vorderbeinmuskulatur rechts', anatomical:'M. triceps brachii',           region:'forelimb',  side:'right',  pos:[-0.18,-0.20,-0.42], size:[0.07,0.21,0.08] },
    { id:'cat_hind_l',           label:'Hinterbeinmuskulatur links',  anatomical:'M. biceps femoris',            region:'hindlimb',  side:'left',   pos:[ 0.16,-0.16, 0.52], size:[0.07,0.23,0.09] },
    { id:'cat_hind_r',           label:'Hinterbeinmuskulatur rechts', anatomical:'M. biceps femoris',            region:'hindlimb',  side:'right',  pos:[-0.16,-0.16, 0.52], size:[0.07,0.23,0.09] },
    { id:'cat_paw_fl',           label:'Pfote vorne links',           anatomical:'Regio manus',                  region:'paw',       side:'left',   pos:[ 0.18,-0.60,-0.40], size:[0.06,0.05,0.06] },
    { id:'cat_paw_fr',           label:'Pfote vorne rechts',          anatomical:'Regio manus',                  region:'paw',       side:'right',  pos:[-0.18,-0.60,-0.40], size:[0.06,0.05,0.06] },
    { id:'cat_paw_hl',           label:'Pfote hinten links',          anatomical:'Regio pedis',                  region:'paw',       side:'left',   pos:[ 0.16,-0.60, 0.62], size:[0.06,0.05,0.06] },
    { id:'cat_paw_hr',           label:'Pfote hinten rechts',         anatomical:'Regio pedis',                  region:'paw',       side:'right',  pos:[-0.16,-0.60, 0.62], size:[0.06,0.05,0.06] },
    { id:'cat_tail_base',        label:'Schwanzbasis',                anatomical:'Regio caudalis',               region:'tail',      side:'midline', pos:[ 0.00, 0.16, 0.80], size:[0.09,0.08,0.09] },
    { id:'cat_tail',             label:'Schwanzmuskulatur',           anatomical:'Mm. caudales',                 region:'tail',      side:'midline', pos:[ 0.00, 0.18, 0.92], size:[0.07,0.06,0.08] },
  ],

  /* ── PFERD ──────────────────────────────────────────────────── */
  /* Bounds: X:±0.319(Seite), Y:±0.912(Höhe), Z:±1.000(Länge)
   * GEMESSEN: Kopf=RECHTS im Bild → Kopf=-Z, Schwanz=+Z
   * X-Asymmetrie: +X:0.197, -X:-0.264 → sichtbare Seite (links im Bild) = -X (breiter)
   * Rücken Y≈+0.30..+0.45, Bauch Y≈-0.10, Hufe Y≈-0.82 */
  horse: [
    { id:'horse_head',           label:'Kopfmuskulatur',              anatomical:'Musculi capitis',              region:'head',      side:'midline', pos:[ 0.00, 0.06,-0.86], size:[0.14,0.18,0.12] },
    { id:'horse_jaw',            label:'Kaumuskulatur',               anatomical:'M. masseter',                  region:'head',      side:'midline', pos:[ 0.00,-0.06,-0.82], size:[0.12,0.12,0.10] },
    { id:'horse_neck',           label:'Halsmuskulatur',              anatomical:'Mm. colli',                    region:'neck',      side:'midline', pos:[ 0.00, 0.16,-0.64], size:[0.14,0.12,0.16] },
    { id:'horse_neck_dorsal',    label:'Nackenmuskulatur',            anatomical:'Lig. nuchae / Mm. nuchae',     region:'neck',      side:'midline', pos:[ 0.00, 0.34,-0.58], size:[0.12,0.10,0.14] },
    { id:'horse_shoulder_l',     label:'Schultermuskulatur links',    anatomical:'M. deltoideus / infraspinatus',region:'shoulder',  side:'left',   pos:[-0.22, 0.22,-0.44], size:[0.10,0.14,0.14] },
    { id:'horse_shoulder_r',     label:'Schultermuskulatur rechts',   anatomical:'M. deltoideus / infraspinatus',region:'shoulder',  side:'right',  pos:[ 0.22, 0.22,-0.44], size:[0.10,0.14,0.14] },
    { id:'horse_chest',          label:'Brustmuskulatur',             anatomical:'M. pectoralis profundus',      region:'chest',     side:'midline', pos:[ 0.00,-0.14,-0.50], size:[0.22,0.14,0.16] },
    { id:'horse_withers',        label:'Widerristregion',             anatomical:'Processus spinosus T3-T9',     region:'withers',   side:'midline', pos:[ 0.00, 0.46,-0.30], size:[0.14,0.10,0.14] },
    { id:'horse_thoracic',       label:'Rückenmuskulatur (Sattellage)',anatomical:'M. longissimus dorsi',        region:'back',      side:'midline', pos:[ 0.00, 0.42, 0.00], size:[0.14,0.10,0.28] },
    { id:'horse_lumbar',         label:'Lendenmuskulatur',            anatomical:'M. iliopsoas / multifidus',    region:'lumbar',    side:'midline', pos:[ 0.00, 0.38, 0.26], size:[0.14,0.10,0.18] },
    { id:'horse_belly',          label:'Bauchmuskulatur',             anatomical:'M. obliquus abdominis',        region:'abdomen',   side:'midline', pos:[ 0.00,-0.16, 0.00], size:[0.18,0.14,0.40] },
    { id:'horse_hip_l',          label:'Hüftmuskulatur links',        anatomical:'M. tensor fasciae latae',      region:'hip',       side:'left',   pos:[-0.20, 0.32, 0.42], size:[0.10,0.14,0.14] },
    { id:'horse_hip_r',          label:'Hüftmuskulatur rechts',       anatomical:'M. tensor fasciae latae',      region:'hip',       side:'right',  pos:[ 0.20, 0.32, 0.42], size:[0.10,0.14,0.14] },
    { id:'horse_glute_l',        label:'Glutealmuskulatur links',     anatomical:'M. gluteus medius',            region:'gluteal',   side:'left',   pos:[-0.22, 0.20, 0.52], size:[0.10,0.14,0.12] },
    { id:'horse_glute_r',        label:'Glutealmuskulatur rechts',    anatomical:'M. gluteus medius',            region:'gluteal',   side:'right',  pos:[ 0.22, 0.20, 0.52], size:[0.10,0.14,0.12] },
    { id:'horse_thigh_l',        label:'Oberschenkelmuskulatur links', anatomical:'M. biceps femoris',           region:'hindlimb',  side:'left',   pos:[-0.24,-0.12, 0.52], size:[0.09,0.20,0.10] },
    { id:'horse_thigh_r',        label:'Oberschenkelmuskulatur rechts',anatomical:'M. biceps femoris',          region:'hindlimb',  side:'right',  pos:[ 0.24,-0.12, 0.52], size:[0.09,0.20,0.10] },
    { id:'horse_fore_l',         label:'Vorderbeinmuskulatur links',  anatomical:'M. triceps brachii',           region:'forelimb',  side:'left',   pos:[-0.26,-0.24,-0.46], size:[0.08,0.24,0.08] },
    { id:'horse_fore_r',         label:'Vorderbeinmuskulatur rechts', anatomical:'M. triceps brachii',           region:'forelimb',  side:'right',  pos:[ 0.26,-0.24,-0.46], size:[0.08,0.24,0.08] },
    { id:'horse_hind_l',         label:'Hinterbeinmuskulatur links',  anatomical:'M. gastrocnemius',             region:'hindlimb',  side:'left',   pos:[-0.26,-0.28, 0.60], size:[0.08,0.24,0.08] },
    { id:'horse_hind_r',         label:'Hinterbeinmuskulatur rechts', anatomical:'M. gastrocnemius',             region:'hindlimb',  side:'right',  pos:[ 0.26,-0.28, 0.60], size:[0.08,0.24,0.08] },
    { id:'horse_carpus_l',       label:'Karpalgelenkregion links',    anatomical:'Regio carpalis',               region:'carpus',    side:'left',   pos:[-0.28,-0.56,-0.44], size:[0.07,0.07,0.07] },
    { id:'horse_carpus_r',       label:'Karpalgelenkregion rechts',   anatomical:'Regio carpalis',               region:'carpus',    side:'right',  pos:[ 0.28,-0.56,-0.44], size:[0.07,0.07,0.07] },
    { id:'horse_tarsus_l',       label:'Sprunggelenkregion links',    anatomical:'Regio tarsi',                  region:'tarsus',    side:'left',   pos:[-0.28,-0.56, 0.64], size:[0.07,0.07,0.07] },
    { id:'horse_tarsus_r',       label:'Sprunggelenkregion rechts',   anatomical:'Regio tarsi',                  region:'tarsus',    side:'right',  pos:[ 0.28,-0.56, 0.64], size:[0.07,0.07,0.07] },
    { id:'horse_fetlock_fl',     label:'Fesselregion vorne links',    anatomical:'Regio metacarpalis distalis',  region:'fetlock',   side:'left',   pos:[-0.28,-0.70,-0.44], size:[0.05,0.06,0.05] },
    { id:'horse_fetlock_fr',     label:'Fesselregion vorne rechts',   anatomical:'Regio metacarpalis distalis',  region:'fetlock',   side:'right',  pos:[ 0.28,-0.70,-0.44], size:[0.05,0.06,0.05] },
    { id:'horse_fetlock_hl',     label:'Fesselregion hinten links',   anatomical:'Regio metatarsalis distalis',  region:'fetlock',   side:'left',   pos:[-0.28,-0.70, 0.70], size:[0.05,0.06,0.05] },
    { id:'horse_fetlock_hr',     label:'Fesselregion hinten rechts',  anatomical:'Regio metatarsalis distalis',  region:'fetlock',   side:'right',  pos:[ 0.28,-0.70, 0.70], size:[0.05,0.06,0.05] },
    { id:'horse_hoof_fl',        label:'Hufregion vorne links',       anatomical:'Regio ungulae',               region:'hoof',      side:'left',   pos:[-0.28,-0.82,-0.44], size:[0.05,0.05,0.05] },
    { id:'horse_hoof_fr',        label:'Hufregion vorne rechts',      anatomical:'Regio ungulae',               region:'hoof',      side:'right',  pos:[ 0.28,-0.82,-0.44], size:[0.05,0.05,0.05] },
    { id:'horse_hoof_hl',        label:'Hufregion hinten links',      anatomical:'Regio ungulae',               region:'hoof',      side:'left',   pos:[-0.28,-0.82, 0.76], size:[0.05,0.05,0.05] },
    { id:'horse_hoof_hr',        label:'Hufregion hinten rechts',     anatomical:'Regio ungulae',               region:'hoof',      side:'right',  pos:[ 0.28,-0.82, 0.76], size:[0.05,0.05,0.05] },
    { id:'horse_tail',           label:'Schweifansatz',               anatomical:'Regio caudae',                region:'tail',      side:'midline', pos:[ 0.00, 0.22, 0.88], size:[0.10,0.10,0.10] },
  ],
};

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
        this.animalType  = animalType || 'dog';
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
        this.loader      = new GLTFLoader();
        this.raycaster   = new THREE.Raycaster();
        this.pointer     = new THREE.Vector2(-9999, -9999);

        /* Model state */
        this.modelGroup  = null;     /* loaded GLB root */
        this.hotspots    = [];       /* { mesh, def } */
        this.origMats    = new Map();/* mesh → original material */
        this.painData    = {};       /* key → {painLevel, painType, notes, id} */

        /* UI state */
        this.selectedKey = null;
        this.hoveredMesh = null;
        this.debugMode   = false;
        this._animId     = null;

        this._buildUI();
        this._initThree();
        this._loadModel(this.animalType);
        this._loadPainData();
    }

    /* ── Build HTML skeleton ──────────────────────────────── */
    _buildUI() {
        this.container.style.cssText = 'position:relative;width:100%;height:100%;background:#0a0f1a;border-radius:12px;overflow:hidden;';
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
            <button id="a3d-debug-btn"  title="Zonen anzeigen"        style="padding:4px 8px;border-radius:6px;border:none;cursor:pointer;font-size:.72rem;background:rgba(255,255,255,.08);color:#e2e8f0;">🔲 Zonen</button>
            <button id="a3d-fs-btn"     title="Vollbild"              style="padding:4px 8px;border-radius:6px;border:none;cursor:pointer;font-size:.72rem;background:rgba(255,255,255,.08);color:#e2e8f0;">⛶ Vollbild</button>
          </div>

          <!-- Loading overlay -->
          <div id="a3d-loading" style="
            position:absolute;inset:0;display:flex;flex-direction:column;
            align-items:center;justify-content:center;z-index:30;
            background:rgba(10,15,26,.9);">
            <div style="width:36px;height:36px;border:3px solid rgba(255,255,255,.15);border-top-color:#4f7cff;border-radius:50%;animation:a3d-spin .8s linear infinite;"></div>
            <div id="a3d-load-text" style="margin-top:12px;font-size:.8rem;color:#94a3b8;">Lade Modell…</div>
          </div>

          <!-- Hover tooltip -->
          <div id="a3d-tooltip" style="
            position:absolute;pointer-events:none;z-index:25;
            background:rgba(10,15,26,.92);border:1px solid rgba(255,255,255,.12);
            border-radius:7px;padding:5px 9px;font-size:.72rem;color:#e2e8f0;
            display:none;white-space:nowrap;"></div>

          <!-- Pain marker legend -->
          <div id="a3d-legend" style="
            position:absolute;bottom:10px;left:10px;z-index:20;
            background:rgba(10,15,26,.82);backdrop-filter:blur(6px);
            border:1px solid rgba(255,255,255,.1);border-radius:8px;
            padding:7px 10px;font-size:.68rem;color:#94a3b8;">
            <div style="font-weight:700;margin-bottom:4px;color:#e2e8f0;">Schmerzskala</div>
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
                    style="flex:1;accent-color:#4f7cff;">
                  <span id="a3d-pain-val" style="font-size:.9rem;font-weight:700;color:#e2e8f0;min-width:20px;text-align:center;">0</span>
                </div>
                <div id="a3d-pain-bar" style="height:6px;border-radius:4px;background:#22c55e;margin-top:4px;transition:background .2s;"></div>
              </div>
              <div>
                <label style="font-size:.7rem;color:#64748b;display:block;margin-bottom:4px;">Seite</label>
                <select id="a3d-side-sel" class="form-select form-select-sm">
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
                placeholder="Freitext…" style="font-size:.78rem;resize:none;"></textarea>
            </div>

            <div style="display:flex;gap:8px;">
              <button id="a3d-save-btn"   class="btn btn-primary btn-sm" style="flex:1;">Speichern</button>
              <button id="a3d-remove-btn" class="btn btn-outline-danger btn-sm">Entfernen</button>
              <button id="a3d-cancel-btn" class="btn btn-secondary btn-sm">Abbrechen</button>
            </div>
          </div>

          <style>
            @keyframes a3d-spin { to { transform:rotate(360deg); } }
            .a3d-species-btn { background:rgba(255,255,255,.06); color:#94a3b8; }
            .a3d-species-btn.active { background:#4f7cff; color:#fff; }
            .a3d-pt-btn.active { background:rgba(79,124,255,.25); border-color:#4f7cff; color:#e2e8f0; }
          </style>
        `;

        this._bindUI();
    }

    _bindUI() {
        const c = this.container;

        /* Species buttons */
        c.querySelectorAll('.a3d-species-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const sp = btn.dataset.sp;
                this._switchAnimal(sp);
            });
        });
        this._updateSpeciesBtn();

        c.querySelector('#a3d-reset-btn').addEventListener('click', () => this._resetCamera());
        c.querySelector('#a3d-debug-btn').addEventListener('click', () => this._toggleDebug());
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
        this.renderer.setPixelRatio(window.devicePixelRatio);
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.shadowMap.enabled = true;

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
        key.castShadow = true;
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

        /* Events */
        canvas.addEventListener('pointermove', e => this._onPointerMove(e));
        canvas.addEventListener('pointerdown', e => this._onClick(e));
        window.addEventListener('resize', () => this._resize());

        this._resize();
        this._animate();
    }

    _resize() {
        const w = this.container.clientWidth;
        const h = this.container.clientHeight || 400;
        this.renderer.setSize(w, h, false);
        this.camera.aspect = w / h;
        this.camera.updateProjectionMatrix();
    }

    _animate() {
        this._animId = requestAnimationFrame(() => this._animate());
        this.controls.update();
        this._updateHover();
        this.renderer.render(this.scene, this.camera);
    }

    destroy() {
        if (this._animId) cancelAnimationFrame(this._animId);
        this.renderer.dispose();
    }

    /* ── Model Loading ────────────────────────────────────── */
    _loadModel(species) {
        const paths = {
            dog:   './models/Hund.glb',
            cat:   './models/katze.glb',
            horse: './models/Pferd.glb',
        };
        const path = paths[species] || paths.dog;

        this._showLoading(`Lade ${species === 'dog' ? 'Hund' : species === 'cat' ? 'Katze' : 'Pferd'}…`);

        /* Remove old model + hotspots */
        if (this.modelGroup) {
            this.scene.remove(this.modelGroup);
            this.modelGroup = null;
        }
        this.hotspots.forEach(h => {
            if (h.mesh.parent)   h.mesh.parent.remove(h.mesh);
            if (h.marker?.parent) h.marker.parent.remove(h.marker);
        });
        this.hotspots = [];
        this._modelBox = null;

        this.loader.load(
            path,
            gltf => this._onModelLoaded(gltf, species),
            xhr  => {
                if (xhr.total > 0) {
                    const pct = Math.round(xhr.loaded / xhr.total * 100);
                    this._showLoading(`Lade… ${pct}%`);
                }
            },
            err  => {
                console.error('[Anatomy3D] GLB load error:', err);
                this._showLoading('Fehler beim Laden des Modells.');
            }
        );
    }

    _onModelLoaded(gltf, species) {
        const model = gltf.scene;

        /* ── Inspect meshes */
        const meshNames = [];
        model.traverse(obj => {
            if (obj.isMesh) meshNames.push(obj.name || '[unnamed]');
        });
        console.log(`[Anatomy3D] ${species} — ${meshNames.length} Meshes:`, meshNames);

        /* ── Auto-scale + center */
        const box = new THREE.Box3().setFromObject(model);
        const size = new THREE.Vector3();
        box.getSize(size);
        const maxDim = Math.max(size.x, size.y, size.z);
        const scale  = 2.0 / maxDim;
        model.scale.setScalar(scale);

        /* Recompute box after scale, then center */
        model.updateMatrixWorld(true);
        const box2 = new THREE.Box3().setFromObject(model);
        const center = new THREE.Vector3();
        box2.getCenter(center);
        model.position.sub(center);

        /* Recompute final bounding box in world space after centering */
        model.updateMatrixWorld(true);
        const finalBox = new THREE.Box3().setFromObject(model);
        const finalSize = new THREE.Vector3();
        finalBox.getSize(finalSize);
        const finalMin = finalBox.min.clone();

        /* Store final dimensions for hotspot placement */
        this._modelBox = { box: finalBox, size: finalSize, min: finalMin };

        const finalCenter = new THREE.Vector3();
        finalBox.getCenter(finalCenter);
        console.log(`[Anatomy3D] ${species} final bounds:`,
            'min', finalBox.min.x.toFixed(3), finalBox.min.y.toFixed(3), finalBox.min.z.toFixed(3),
            'max', finalBox.max.x.toFixed(3), finalBox.max.y.toFixed(3), finalBox.max.z.toFixed(3),
            'size', finalSize.x.toFixed(3), finalSize.y.toFixed(3), finalSize.z.toFixed(3)
        );
        console.log(`[Anatomy3D] ${species} model.position after centering:`,
            model.position.x.toFixed(4), model.position.y.toFixed(4), model.position.z.toFixed(4),
            '| finalCenter:', finalCenter.x.toFixed(4), finalCenter.y.toFixed(4), finalCenter.z.toFixed(4)
        );

        /* ── Debug: log vertex extremes per axis to determine head/tail/floor direction */
        {
            let maxZ=-Infinity,minZ=Infinity,maxX=-Infinity,minX=Infinity,minY=Infinity;
            const tmp = new THREE.Vector3();
            model.updateMatrixWorld(true);
            model.traverse(obj => {
                if (!obj.isMesh) return;
                const pos = obj.geometry?.attributes?.position;
                if (!pos) return;
                for (let i = 0; i < Math.min(pos.count, 3000); i++) {
                    tmp.fromBufferAttribute(pos, i).applyMatrix4(obj.matrixWorld);
                    if (tmp.z > maxZ) maxZ = tmp.z;
                    if (tmp.z < minZ) minZ = tmp.z;
                    if (tmp.x > maxX) maxX = tmp.x;
                    if (tmp.x < minX) minX = tmp.x;
                    if (tmp.y < minY) minY = tmp.y;
                }
            });
            console.log(`[Anatomy3D] ${species} axis extremes — +Z:${maxZ.toFixed(3)} -Z:${minZ.toFixed(3)} +X:${maxX.toFixed(3)} -X:${minX.toFixed(3)} minY(floor):${minY.toFixed(3)}`);
        }

        /* Preserve original GLB materials — do NOT override textures */
        model.traverse(obj => {
            if (obj.isMesh) {
                obj.castShadow    = true;
                obj.receiveShadow = true;
            }
        });

        this.modelGroup = model;
        this.scene.add(model);

        /* ── Build hotspot markers in world space (pos[] are world coords
         *    matching the final auto-scaled + centered bounding box). */
        this._buildHotspots(species);

        /* ── Apply existing pain data */
        this._applyPainToHotspots();

        this._hideLoading();
        this._updateSpeciesBtn();
    }

    _buildHotspots(species) {
        const groups = MUSCLE_GROUPS[species] || [];
        const mb     = this._modelBox;
        if (!mb) return;

        /* Collect the real model meshes so hotspots can be projected onto the
         * actual surface. The def.pos[] values are only approximate anchors in
         * normalized model space; the true surface depends on each GLB's real
         * proportions, so we snap every point onto the mesh at runtime. This
         * prevents markers from floating in the air or sinking into the body. */
        const modelMeshes = [];
        this.modelGroup.traverse(o => { if (o.isMesh) modelMeshes.push(o); });

        groups.forEach(def => {
            /* Surface-projected world position for this muscle group */
            const snapped = this._projectToSurface(def, mb, modelMeshes);

            /* Invisible raycasting box — matches the zone size */
            const geo  = new THREE.BoxGeometry(
                def.size[0] * 2,
                def.size[1] * 2,
                def.size[2] * 2
            );
            const mat  = new THREE.MeshBasicMaterial({
                color: 0x4f7cff,
                transparent: true,
                opacity: 0,
                depthWrite: false,
                depthTest: false,
            });
            const mesh = new THREE.Mesh(geo, mat);
            mesh.position.copy(snapped);
            mesh.renderOrder = 999;
            mesh.visible = false; /* raycasting only — never rendered */
            mesh.userData.hotspot = def;
            this.scene.add(mesh);

            /* Visible marker sphere — small dot on model surface */
            const markerGeo = new THREE.SphereGeometry(0.022, 8, 8);
            const markerMat = new THREE.MeshBasicMaterial({
                color: 0x4f7cff,
                transparent: true,
                opacity: 0,
                depthWrite: false,
            });
            const marker = new THREE.Mesh(markerGeo, markerMat);
            marker.position.copy(snapped);
            marker.renderOrder = 1000;
            this.scene.add(marker);

            this.hotspots.push({ mesh, marker, def, pos: snapped });
        });
    }

    /* ── Snap a muscle-group anchor onto the real model surface ──────────────
     * The anchor (def.pos) is projected radially outward from the body's
     * longitudinal axis (the spine, running along Z at x=0, y=vertical center).
     * A ray is cast from far outside back toward that axis through the anchor;
     * the first surface hit is the outer skin facing that direction. The marker
     * is then placed just above the surface so it is always visible on the body
     * regardless of the individual GLB's proportions. Falls back to the raw
     * anchor if the ray misses the mesh entirely. */
    _projectToSurface(def, mb, modelMeshes) {
        const anchor = new THREE.Vector3(def.pos[0], def.pos[1], def.pos[2]);
        if (!modelMeshes || !modelMeshes.length) return anchor;

        const centerY = mb.box.min.y + mb.size.y / 2;
        const axis    = new THREE.Vector3(0, centerY, def.pos[2]);

        /* Outward direction: from the spine axis toward the anchor */
        const outward = anchor.clone().sub(axis);
        if (outward.lengthSq() < 1e-6) outward.set(0, 1, 0); /* dead-on axis → up */
        outward.normalize();

        /* Cast from outside the bounding sphere back toward the axis */
        const reach = mb.size.length();
        const start = axis.clone().add(outward.clone().multiplyScalar(reach));
        const ray   = new THREE.Raycaster(start, outward.clone().negate(), 0, reach * 2.2);

        const hits = ray.intersectObjects(modelMeshes, true);
        if (hits.length) {
            /* Lift marker slightly off the surface so it renders on top */
            return hits[0].point.clone().add(outward.clone().multiplyScalar(0.015));
        }
        return anchor;
    }

    /* ── Raycasting / Hover ───────────────────────────────── */
    _onPointerMove(e) {
        const rect = this.renderer.domElement.getBoundingClientRect();
        this.pointer.x =  ((e.clientX - rect.left)  / rect.width)  * 2 - 1;
        this.pointer.y = -((e.clientY - rect.top)   / rect.height) * 2 + 1;
        this._moveTooltip(e.clientX, e.clientY);
    }

    _updateHover() {
        if (!this.hotspots.length) return;
        this.raycaster.setFromCamera(this.pointer, this.camera);

        /* Three.js skips invisible objects — temporarily enable for raycasting */
        const targets = this.hotspots.map(h => h.mesh);
        targets.forEach(m => { m.visible = true; });
        const hits = this.raycaster.intersectObjects(targets, false);
        targets.forEach(m => { m.visible = false; });

        const hitMesh = hits.length ? hits[0].object : null;

        if (hitMesh !== this.hoveredMesh) {
            /* Restore previous hover — hide invisible raycast box, update marker */
            if (this.hoveredMesh) {
                const prevEntry = this.hotspots.find(h => h.mesh === this.hoveredMesh);
                if (prevEntry) {
                    const key     = `${prevEntry.def.id}::${prevEntry.def.side}`;
                    const hasPain = this.painData[key]?.painLevel > 0;
                    /* marker: show if pain, else hide (unless debug) */
                    prevEntry.marker.material.opacity  = hasPain ? 0.9 : (this.debugMode ? 0.4 : 0);
                    prevEntry.marker.material.color.set(
                        hasPain ? painColor(this.painData[key].painLevel) : 0x4f7cff
                    );
                    prevEntry.marker.scale.setScalar(1);
                }
            }
            this.hoveredMesh = hitMesh;
            if (hitMesh) {
                const entry = this.hotspots.find(h => h.mesh === hitMesh);
                if (entry) {
                    /* Scale up marker and make visible as hover indicator */
                    entry.marker.material.color.setHex(0xfbbf24);
                    entry.marker.material.opacity = 0.95;
                    entry.marker.scale.setScalar(1.8);
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
        if (!this.hoveredMesh) return;

        const def = this.hoveredMesh.userData.hotspot;
        this._openForm(def);
    }

    /* ── Pain form ────────────────────────────────────────── */
    _openForm(def) {
        this.selectedKey = `${def.id}::${def.side}`;
        const existing   = this.painData[this.selectedKey] || {};

        const c = this.container;
        c.querySelector('#a3d-form-title').textContent = def.label;
        c.querySelector('#a3d-form-sub').textContent   =
            `${def.anatomical} · ${def.region} · ${def.side}`;

        const lvl = existing.painLevel ?? 0;
        const slider = c.querySelector('#a3d-pain-slider');
        slider.value = lvl;
        c.querySelector('#a3d-pain-val').textContent = lvl;
        c.querySelector('#a3d-pain-bar').style.background = painColor(lvl);

        c.querySelectorAll('.a3d-pt-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.pt === existing.painType);
        });

        c.querySelector('#a3d-side-sel').value = def.side;
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
    }

    _previewPain(key, level) {
        const entry = this.hotspots.find(h => `${h.def.id}::${h.def.side}` === key);
        if (!entry) return;
        const mat = entry.marker.material;
        if (level === 0) {
            mat.color.setHex(0x4f7cff);
            mat.opacity = this.debugMode ? 0.45 : 0;
            entry.marker.scale.setScalar(1);
        } else {
            mat.color.set(painColor(level));
            mat.opacity = 0.9;
            entry.marker.scale.setScalar(1.4);
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
                    animal_type:        this.animalType,
                    muscle_group_id:    def.id,
                    muscle_group_label: def.label,
                    region:             def.region,
                    side,
                    pain_level:         lvl,
                    pain_type:          painType,
                    notes,
                }),
            });
            const json = await res.json();

            if (json.success) {
                this.painData[this.selectedKey] = { painLevel: lvl, painType, notes, id: json.id };
                this._applyPainToHotspots();
                this._renderList();
                this._closeForm();
            }
        } catch (err) {
            console.error('[Anatomy3D] save error:', err);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Speichern';
        }
    }

    async _removePain() {
        if (!this.selectedKey) return;
        const entry = this.painData[this.selectedKey];
        if (!entry?.id) { this._closeForm(); return; }

        try {
            await fetch(`${this.apiBase}/api/mobile/patients/${this.patientId}/schmerzpunkte/${entry.id}/loeschen`, {
                method: 'POST',
                headers: {
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Authorization':    `Bearer ${this.token}`,
                },
            });
            delete this.painData[this.selectedKey];
            this._applyPainToHotspots();
            this._renderList();
            this._closeForm();
        } catch (err) {
            console.error('[Anatomy3D] remove error:', err);
        }
    }

    /* ── Load existing pain data from API ─────────────────── */
    async _loadPainData() {
        try {
            const res  = await fetch(`${this.apiBase}/api/mobile/patients/${this.patientId}/schmerzpunkte?animal_type=${this.animalType}`, {
                headers: {
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Authorization':    `Bearer ${this.token}`,
                },
            });
            const json = await res.json();
            if (!json.success) return;

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
        this.hotspots.forEach(({ mesh, marker, def }) => {
            const key   = `${def.id}::${def.side}`;
            const entry = this.painData[key];
            mesh.visible = false; /* raycasting box never visible */

            if (entry && entry.painLevel > 0) {
                marker.material.color.set(painColor(entry.painLevel));
                marker.material.opacity = 0.9;
                marker.scale.setScalar(1.2);
            } else {
                marker.material.color.setHex(0x4f7cff);
                marker.material.opacity = this.debugMode ? 0.45 : 0;
                marker.scale.setScalar(1);
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
            return `<div class="a3d-list-item" data-key="${key}"
                style="display:flex;align-items:center;gap:6px;padding:5px 6px;border-radius:6px;
                cursor:pointer;margin-bottom:3px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);">
                <div style="width:10px;height:10px;border-radius:50%;background:${col};flex-shrink:0;"></div>
                <div style="min-width:0;">
                  <div style="font-weight:600;color:#e2e8f0;font-size:.72rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${def?.label || key}</div>
                  <div style="color:#64748b;font-size:.65rem;">NRS ${v.painLevel} · ${v.painType || '–'}</div>
                </div>
              </div>`;
        }).join('');

        body.querySelectorAll('.a3d-list-item').forEach(el => {
            el.addEventListener('click', () => {
                const def = this._defFromKey(el.dataset.key);
                if (def) {
                    this._focusHotspot(def);
                    this._openForm(def);
                }
            });
        });
    }

    _focusHotspot(def) {
        /* Prefer the surface-projected position of the built hotspot; fall back
         * to the raw anchor if the hotspot was not built (e.g. model reloading). */
        const entry = this.hotspots.find(h => h.def.id === def.id && h.def.side === def.side);
        const pos  = entry?.pos ? entry.pos.clone() : new THREE.Vector3(...def.pos);
        const dist = 1.2;
        const dir  = this.camera.position.clone().sub(pos).normalize().multiplyScalar(dist);
        this.camera.position.copy(pos.clone().add(dir));
        this.controls.target.copy(pos);
        this.controls.update();
    }

    /* ── Tooltip ─────────────────────────────────────────── */
    _showTooltip(def) {
        const tt  = this.container.querySelector('#a3d-tooltip');
        const key = `${def.id}::${def.side}`;
        const p   = this.painData[key];
        tt.innerHTML = `<strong style="color:#e2e8f0;">${def.label}</strong>` +
            (p?.painLevel > 0 ? `<br><span style="color:${painColor(p.painLevel)}">▲ NRS ${p.painLevel}</span>` : '');
        tt.style.display = 'block';
    }
    _hideTooltip() {
        this.container.querySelector('#a3d-tooltip').style.display = 'none';
    }
    _moveTooltip(cx, cy) {
        const rect = this.container.getBoundingClientRect();
        const tt   = this.container.querySelector('#a3d-tooltip');
        tt.style.left = `${cx - rect.left + 14}px`;
        tt.style.top  = `${cy - rect.top  - 10}px`;
    }

    /* ── Helpers ─────────────────────────────────────────── */
    _defFromKey(key) {
        const groups = MUSCLE_GROUPS[this.animalType] || [];
        const [id, side] = key.split('::');
        return groups.find(d => d.id === id && d.side === side) || null;
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
        if (species === this.animalType) return;
        this.animalType = species;
        this._loadModel(species);
        this._loadPainData();
    }

    _resetCamera() {
        this.camera.position.set(0, 0.5, 3.2);
        this.controls.target.set(0, 0, 0);
        this.controls.update();
    }

    _toggleDebug() {
        this.debugMode = !this.debugMode;
        this.container.querySelector('#a3d-debug-btn').style.background =
            this.debugMode ? 'rgba(79,124,255,.35)' : 'rgba(255,255,255,.08)';
        this._applyPainToHotspots();
    }

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
