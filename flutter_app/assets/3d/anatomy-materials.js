import * as THREE from './vendor/three/three.module.min.js';

// Deterministic visual microstructure, not measured anatomical fibre directions.
// UVs follow each source mesh's principal axis (see the offline converter).
export function styleAnatomy(root) {
    const originals = new Set();
    let fibers;
    root.traverse(mesh => {
        if (!mesh.isMesh) return;
        const layer = mesh.userData.anatomyLayer;
        mesh.castShadow = mesh.receiveShadow = true;
        if (!layer || layer === 'skin') return;
        originals.add(mesh.material);
        const material = mesh.material.clone();
        mesh.material = material;
        material.metalness = 0;
        if (layer === 'muscle' || layer === 'fascia' || layer === 'tendon') {
            if (!fibers) {
                const size = 256, data = new Uint8Array(size*size*4);
                for (let y=0;y<size;y++) for (let x=0;x<size;x++) {
                    const phase = x + 1.4*Math.sin(y/43) + .6*Math.sin(y/11+x/37);
                    const value = Math.round(170 + 34*Math.sin(phase*1.57) + 15*Math.sin(phase*.51) + 5*Math.sin(x*13+y*17));
                    const i=(y*size+x)*4;
                    data[i]=data[i+1]=data[i+2]=value; data[i+3]=255;
                }
                fibers = new THREE.DataTexture(data,size,size);
                fibers.wrapS = fibers.wrapT = THREE.RepeatWrapping;
                fibers.magFilter = THREE.LinearFilter;
                fibers.minFilter = THREE.LinearMipmapLinearFilter;
                fibers.generateMipmaps = true;
                fibers.needsUpdate = true;
            }
            material.map = fibers;
            material.bumpMap = fibers;
            material.bumpScale = layer === 'muscle' ? .00065 : .00035;
            material.roughnessMap = fibers;
            material.color.set(layer === 'muscle' ? 0xb9665b : 0xe0d8bd);
            material.roughness = layer === 'muscle' ? .78 : .95;
            material.side = THREE.DoubleSide;
        } else if (layer === 'skeleton') {
            material.color.set(0xe2d7ba);
            material.roughness = .83;
        } else {
            const dark = /Eye|Nose/.test(mesh.userData.sourceName || '');
            material.color.set(dark ? 0x28201e : 0xd1b6a2);
            material.roughness = dark ? .28 : .8;
        }
        material.needsUpdate = true;
    });
    originals.forEach(material => material.dispose());
}
