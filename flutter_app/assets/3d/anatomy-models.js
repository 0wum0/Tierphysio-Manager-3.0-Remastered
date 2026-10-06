import { DOG_MUSCLES } from './anatomy-dog-muscles.js?v=20261006';

// Enable a species only after its own licensed model and calibrated catalog exist.
// Cat and horse deliberately have no fabricated or cross-species geometry.
export const SEGMENTED_MODELS = {
    dog: {file:'Hund-Muskeln.glb?v=20261006', definitions:DOG_MUSCLES, leftSign:1},
};

export function validateMuscleModel(scene, species, definitions) {
    const ids = new Set();
    for (const def of definitions) {
        if (!def.id?.startsWith(`${species}_mesh_`) || ids.has(def.id)
            || !def.anatomical || !def.label || !['left','right','midline'].includes(def.side)
            || !Array.isArray(def.pos) || def.pos.length !== 3 || !def.pos.every(Number.isFinite)) {
            throw new Error(`Ungültiger Muskelkatalog für ${species}`);
        }
        ids.add(def.id);
    }
    if (!ids.size) throw new Error(`Leerer Muskelkatalog für ${species}`);
    const found = new Set();
    scene.traverse(mesh => {
        if (!mesh.isMesh || !mesh.userData.muscleId) return;
        const id = mesh.userData.muscleId;
        if (!ids.has(id) || !mesh.geometry?.getAttribute('position')?.count) {
            throw new Error(`Muskeloberfläche ohne gültige Zuordnung: ${id}`);
        }
        found.add(id);
    });
    for (const id of ids) {
        if (!found.has(id)) throw new Error(`Muskeloberfläche fehlt: ${id}`);
    }
}
