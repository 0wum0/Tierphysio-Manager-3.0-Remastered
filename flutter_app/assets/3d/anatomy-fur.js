import * as THREE from './vendor/three/three.module.min.js';

// Cosmetic short coat on the existing outer surface. No anatomical tissue is
// inferred, displaced or added to the clinical structure catalog.
export function createShortCoat(root, count = 12000) {
    let skin;
    root.traverse(mesh => { if (mesh.isMesh && mesh.userData.anatomyLayer === 'skin') skin = mesh; });
    const map = skin?.material.map;
    if (!skin || !map?.image) return null;
    root.updateWorldMatrix(true,true);
    const transform = root.matrixWorld.clone().invert().multiply(skin.matrixWorld);
    const normalTransform = new THREE.Matrix3().getNormalMatrix(transform);
    const geometry = skin.geometry, positions = geometry.getAttribute('position'), normals = geometry.getAttribute('normal');
    const uv = geometry.getAttribute('uv'), index = geometry.index;
    if (!normals || !uv) return null;
    const source = document.createElement('canvas');
    source.width = map.image.width; source.height = map.image.height;
    const context = source.getContext('2d', {willReadFrequently:true});
    context.drawImage(map.image,0,0);
    const pixels = context.getImageData(0,0,source.width,source.height).data;
    const triangleCount = (index?.count || positions.count)/3;
    const cumulative = new Float64Array(triangleCount);
    const a=new THREE.Vector3(), b=new THREE.Vector3(), c=new THREE.Vector3(), edge=new THREE.Vector3();
    const vertex = i => index ? index.getX(i) : i;
    let area = 0;
    for (let i=0;i<triangleCount;i++) {
        a.fromBufferAttribute(positions,vertex(i*3)).applyMatrix4(transform);
        b.fromBufferAttribute(positions,vertex(i*3+1)).applyMatrix4(transform);
        c.fromBufferAttribute(positions,vertex(i*3+2)).applyMatrix4(transform);
        area += edge.subVectors(b,a).cross(c.sub(a)).length()/2;
        cumulative[i] = area;
    }
    if (!area) return null;
    let seed=71329;
    const random=()=>{seed=(Math.imul(seed,1664525)+1013904223)>>>0;return seed/4294967296;};
    const output=[], colors=[], normal=new THREE.Vector3(), point=new THREE.Vector3(), tangent=new THREE.Vector3();
    const color=new THREE.Color();
    for (let strand=0;strand<count;strand++) {
        const target=random()*area;
        let lo=0,hi=triangleCount-1;
        while(lo<hi){const mid=(lo+hi)>>>1;if(cumulative[mid]<target)lo=mid+1;else hi=mid;}
        const vertices=[vertex(lo*3),vertex(lo*3+1),vertex(lo*3+2)];
        const r=Math.sqrt(random()), t=random(), weights=[1-r,r*(1-t),r*t];
        point.set(0,0,0);normal.set(0,0,0);let u=0,v=0;
        vertices.forEach((id,k)=>{
            point.addScaledVector(a.fromBufferAttribute(positions,id).applyMatrix4(transform),weights[k]);
            normal.addScaledVector(b.fromBufferAttribute(normals,id).applyNormalMatrix(normalTransform),weights[k]);
            u+=uv.getX(id)*weights[k];v+=uv.getY(id)*weights[k];
        });
        normal.normalize();
        const x=Math.min(source.width-1,Math.max(0,Math.floor(u*source.width)));
        const y=Math.min(source.height-1,Math.max(0,Math.floor((map.flipY?1-v:v)*source.height)));
        const pixel=(y*source.width+x)*4;
        color.setRGB(pixels[pixel]/255,pixels[pixel+1]/255,pixels[pixel+2]/255,THREE.SRGBColorSpace);
        // Keep dark nose/eye regions clear. Coat length is a visual choice only.
        if(Math.max(color.r,color.g,color.b)<.075 || (pixels[pixel]>pixels[pixel+1]*2.3 && pixels[pixel+2]>pixels[pixel+1]*.8)) continue;
        color.multiplyScalar(.8);
        tangent.set(0,-.3,-1).addScaledVector(normal,-tangent.dot(normal)).normalize();
        const length=.0008+random()*.0012;
        a.copy(point).addScaledVector(normal,.0002);
        b.copy(a).addScaledVector(normal,length*.65).addScaledVector(tangent,length*.35);
        c.copy(a).addScaledVector(normal,length).addScaledVector(tangent,length*.8);
        for(const p of [a,b,b,c]) {output.push(p.x,p.y,p.z);colors.push(color.r,color.g,color.b);}
    }
    const coatGeometry=new THREE.BufferGeometry();
    coatGeometry.setAttribute('position',new THREE.Float32BufferAttribute(output,3));
    coatGeometry.setAttribute('color',new THREE.Float32BufferAttribute(colors,3));
    const coat=new THREE.LineSegments(coatGeometry,new THREE.LineBasicMaterial({vertexColors:true,transparent:true,opacity:.2,depthWrite:false}));
    coat.name='CosmeticShortCoat';coat.userData.anatomyLayer='fur';
    root.add(coat);
    return coat;
}
