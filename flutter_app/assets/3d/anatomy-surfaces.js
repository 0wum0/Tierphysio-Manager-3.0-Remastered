import * as THREE from './vendor/three/three.module.min.js';

// Exact models carry explicit muscle IDs. Legacy models have only landmarks:
// their nearest-landmark vertex regions are an approximation, never segmentation.
export class PainSurfaces {
    constructor(meshes, definitions, exact, leftSign = 1) {
        this.exact = exact;
        this.entries = new Map();
        this.buffers = [];
        const originals = new Set();
        const defs = new Map(definitions.map(d => [d.id, d]));
        for (const mesh of meshes) {
            if (exact) {
                const id = mesh.userData.muscleId;
                if (!defs.has(id)) continue;
                originals.add(mesh.material);
                mesh.material = mesh.material.clone();
                mesh.userData.neutralColor = mesh.material.color.clone();
                if (!this.entries.has(id)) this.entries.set(id, []);
                this.entries.get(id).push(mesh);
                continue;
            }
            const geometry = mesh.geometry;
            const position = geometry.getAttribute('position');
            const assignments = new Uint16Array(position.count);
            const sides = new Uint8Array(position.count);
            const point = new THREE.Vector3();
            for (let i = 0; i < position.count; i++) {
                point.fromBufferAttribute(position, i).applyMatrix4(mesh.matrixWorld);
                let nearest = 0, distance = Infinity;
                definitions.forEach((def, index) => {
                    const p = def.pos;
                    const d = (point.x-p[0])**2 + (point.y-p[1])**2 + (point.z-p[2])**2;
                    if (d < distance) { nearest = index; distance = d; }
                });
                assignments[i] = nearest;
                sides[i] = point.x * leftSign >= 0 ? 0 : 1;
            }
            const colors = new THREE.BufferAttribute(new Float32Array(position.count*3), 3);
            const weights = new THREE.BufferAttribute(new Float32Array(position.count), 1);
            geometry.setAttribute('painColor', colors);
            geometry.setAttribute('painWeight', weights);
            mesh.material.onBeforeCompile = shader => {
                shader.vertexShader = 'attribute vec3 painColor; attribute float painWeight; varying vec3 vPainColor; varying float vPainWeight;\n' + shader.vertexShader;
                shader.vertexShader = shader.vertexShader.replace('#include <begin_vertex>', '#include <begin_vertex>\nvPainColor = painColor; vPainWeight = painWeight;');
                shader.fragmentShader = 'varying vec3 vPainColor; varying float vPainWeight;\n' + shader.fragmentShader;
                shader.fragmentShader = shader.fragmentShader.replace('#include <opaque_fragment>', 'outgoingLight = mix(outgoingLight, vPainColor * 1.15, vPainWeight * 0.85);\n#include <opaque_fragment>');
            };
            mesh.material.customProgramCacheKey = () => 'anatomy-regional-pain-v1';
            mesh.material.needsUpdate = true;
            this.buffers.push({mesh, definitions, assignments, sides, colors, weights});
        }
        originals.forEach(material => { if (!meshes.some(m => m.material === material)) material.dispose(); });
    }
    paint(getLevel, colorForLevel) {
        this.entries.forEach((meshes, id) => {
            const level = getLevel(id);
            meshes.forEach(mesh => {
                const material = mesh.material;
                material.color.copy(mesh.userData.neutralColor);
                material.emissive.setHex(0);
                material.emissiveIntensity = 0;
                if (level != null) {
                    material.color.set(colorForLevel(level));
                    material.emissive.set(colorForLevel(level));
                    material.emissiveIntensity = 0.16;
                }
            });
        });
        this.buffers.forEach(({definitions, assignments, sides, colors, weights}) => {
            const palette = definitions.map(d => ['left','right'].map(side => {
                const level = getLevel(d.id, side);
                return level == null ? null : new THREE.Color(colorForLevel(level));
            }));
            for (let i = 0; i < assignments.length; i++) {
                const color = palette[assignments[i]][sides[i]];
                weights.setX(i, color ? 1 : 0);
                if (color) colors.setXYZ(i, color.r, color.g, color.b);
            }
            colors.needsUpdate = true; weights.needsUpdate = true;
        });
    }
}
