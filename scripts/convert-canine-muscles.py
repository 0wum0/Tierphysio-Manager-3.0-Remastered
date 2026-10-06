#!/usr/bin/env python3
"""Convert MIT-licensed MusculoskeletalDog SKN bind-pose surfaces to static GLB.
Usage: python scripts/convert-canine-muscles.py /path/to/MusculoskeletalDog
Source: https://github.com/vittorione94/MusculoskeletalDog (see model license).
No simulated poses, inferred boundaries or cross-species warping are used.
"""
import json, math, re, struct, sys
from pathlib import Path
root = Path(__file__).resolve().parents[1]
source = Path(sys.argv[1]) / 'musculoskeletal_dog/assets/skins'
blob = bytearray(); views=[]; accessors=[]; meshes=[]; nodes=[]; groups={}
exclude = ('Eye', 'Ligament', 'Nose', 'Skutulum', 'Aponeurosis', 'Fascia', 'tendon', 'fascia_lata')
fixes = {'capri':'carpi', 'tranversus':'transversus', 'transverses':'transversus', 'sternohyroideus':'sternohyoideus', 'tricepsbrachii':'triceps_brachii', 'tensor_f.antebrachii':'tensor_fasciae_antebrachii', 'tensor_f.latae':'tensor_fasciae_latae', 'thoracis_m_pectoralis':'pectoralis', 'thoracis_serratus_ventr':'serratus_ventralis_thoracis', 'dorsi_scaleni_dors':'scalenus_dorsalis', 'latissimus_dors':'latissimus_dorsi', 'intercostals_ext':'intercostales_externi', 'intercostals_int':'intercostales_interni'}
words = {'lat':'lateralis','med':'medialis','ext':'externus','int':'internus','ventr':'ventralis','sup':'superioris','long':'longum','lateral':'laterale','medial':'mediale','accessory':'accessorium'}
def add(data, component, kind, count, bounds=None):
    while len(blob)%4: blob.append(0)
    off=len(blob); blob.extend(data); vi=len(views); views.append({'buffer':0,'byteOffset':off,'byteLength':len(data)})
    a={'bufferView':vi,'componentType':component,'count':count,'type':kind}
    if bounds: a.update(min=bounds[0],max=bounds[1])
    accessors.append(a); return len(accessors)-1
for p in sorted(source.glob('m_*.skn')):
    name=p.stem; raw=p.read_bytes(); nv,nt,nf,nb=struct.unpack_from('<4i',raw)
    floats=struct.unpack_from('<'+'f'*(nv*3),raw,16)
    verts=[(floats[i+1],floats[i+2],floats[i]) for i in range(0,len(floats),3)]
    faces=struct.unpack_from('<'+'I'*(nf*3),raw,16+12*nv+8*nt)
    assert max(faces)<nv
    normal=[[0.,0.,0.] for _ in verts]
    for i in range(0,len(faces),3):
        a,b,c=[verts[faces[i+j]] for j in range(3)]; u=[b[k]-a[k] for k in range(3)]; v=[c[k]-a[k] for k in range(3)]
        n=[u[1]*v[2]-u[2]*v[1],u[2]*v[0]-u[0]*v[2],u[0]*v[1]-u[1]*v[0]]
        for j in range(3):
            for k in range(3): normal[faces[i+j]][k]+=n[k]
    normals=[]
    for n in normal:
        length=math.sqrt(sum(x*x for x in n)) or 1; normals.extend(x/length for x in n)
    bounds=([min(v[k] for v in verts) for k in range(3)],[max(v[k] for v in verts) for k in range(3)])
    position=add(struct.pack('<'+'f'*(nv*3),*(x for v in verts for x in v)),5126,'VEC3',nv,bounds)
    norm=add(struct.pack('<'+'f'*len(normals),*normals),5126,'VEC3',nv)
    indices=add(struct.pack('<'+'I'*len(faces),*faces),5125,'SCALAR',len(faces))
    is_muscle=not any(x in name for x in exclude)
    canonical=re.sub(r'\(\d+\)|\.\d+$','',name).replace('.L','_L')
    canonical=re.sub(r'([LR])\d+$',r'\1',canonical)
    ident='dog_mesh_'+re.sub('[^a-z0-9_]', '_',canonical.lower())
    extras={'sourceName':name}
    if is_muscle:
        extras['muscleId']=ident
        g=groups.setdefault(ident,{'name':canonical,'vertices':[]}); g['vertices'].extend(verts)
    meshes.append({'name':name,'primitives':[{'attributes':{'POSITION':position,'NORMAL':norm},'indices':indices,'material':0 if is_muscle else 1}]})
    nodes.append({'name':name,'mesh':len(meshes)-1,'extras':extras})
allverts=[v for g in groups.values() for v in g['vertices']]
# Include non-muscle surfaces in bounds, matching viewer Box3 normalization.
lo=[min(a['min'][k] for a in accessors if 'min' in a) for k in range(3)]
hi=[max(a['max'][k] for a in accessors if 'max' in a) for k in range(3)]
scale=2/max(hi[k]-lo[k] for k in range(3)); center=[(lo[k]+hi[k])/2 for k in range(3)]
defs=[]
for ident,g in groups.items():
    name=g['name'][2:]; side='left' if name.endswith('_L') else 'right' if name.endswith('_R') else 'midline'
    name=re.sub('_[LR]$','',name)
    for a,b in fixes.items(): name=name.replace(a,b)
    name=' '.join(words.get(w,w) for w in name.split('_'))
    name=re.sub(r'^triceps brachii (longum|laterale|mediale|accessorium)$',r'triceps brachii, caput \1',name)
    name=re.sub(r'^deltoideus (acromialis|scapularis)$',r'deltoideus, pars \1',name)
    anatomical=('Mm. ' if name.startswith(('intercostales','pectorales')) else 'M. ')+name
    if name=='diaphragma': anatomical='Diaphragma'
    mean=[sum(v[k] for v in g['vertices'])/len(g['vertices']) for k in range(3)]
    # Actual vertex nearest centroid; used only for focus, not for muscle boundaries.
    pos=min(g['vertices'],key=lambda v:sum((v[k]-mean[k])**2 for k in range(3)))
    defs.append({'id':ident,'anatomical':anatomical,'label':anatomical+' · '+{'left':'Links','right':'Rechts','midline':'Mittig'}[side], 'side':side,'region':'muscle','pos':[round((pos[k]-center[k])*scale,6) for k in range(3)],'sourceName':g['name']})
defs.sort(key=lambda d:d['label'])
asset={'asset':{'version':'2.0','generator':'TheraPano SKN bind-pose converter','copyright':'Copyright (c) 2025 Vittorio La Barbera. MIT License.'},'scene':0,'scenes':[{'nodes':list(range(len(nodes)))}],'nodes':nodes,'meshes':meshes,'materials':[{'pbrMetallicRoughness':{'baseColorFactor':[.48,.18,.14,1],'metallicFactor':0,'roughnessFactor':.7}},{'pbrMetallicRoughness':{'baseColorFactor':[.65,.62,.55,1],'metallicFactor':0,'roughnessFactor':.8}}], 'buffers':[{'byteLength':len(blob)}],'bufferViews':views,'accessors':accessors}
j=json.dumps(asset,separators=(',',':')).encode(); j+=b' '*((-len(j))%4); blob.extend(b'\0'*((-len(blob))%4))
out=root/'public/assets/3D/Hund-Muskeln.glb'; out.write_bytes(struct.pack('<III',0x46546c67,2,28+len(j)+len(blob))+struct.pack('<II',len(j),0x4e4f534a)+j+struct.pack('<II',len(blob),0x004e4942)+blob)
(root/'public/assets/js/anatomy-dog-muscles.js').write_text('// Source: MusculoskeletalDog, MIT; see assets/3D/licenses. IDs are independent of legacy regions.\nexport const DOG_MUSCLES = '+json.dumps(defs,ensure_ascii=False,indent=2)+';\n')
print(len(meshes),'surfaces;',len(defs),'selectable muscle structures;',out.stat().st_size,'bytes')
