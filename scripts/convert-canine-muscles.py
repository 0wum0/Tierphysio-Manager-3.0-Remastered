#!/usr/bin/env python3
"""Convert MIT-licensed MusculoskeletalDog SKN bind-pose surfaces to static GLB.
Usage: python scripts/convert-canine-muscles.py /path/to/MusculoskeletalDog
Source: https://github.com/vittorione94/MusculoskeletalDog (see model license).
No simulated poses, inferred boundaries or cross-species warping are used.
"""
import json, math, re, struct, sys
import xml.etree.ElementTree as ET
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
def surface(name, verts, faces, extras, material, uv=None):
    nv=len(verts)
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
    attributes={'POSITION':add(struct.pack('<'+'f'*(nv*3),*(x for v in verts for x in v)),5126,'VEC3',nv,bounds),
                'NORMAL':add(struct.pack('<'+'f'*len(normals),*normals),5126,'VEC3',nv)}
    if uv is not None:
        attributes['TEXCOORD_0']=add(struct.pack('<'+'f'*len(uv),*uv),5126,'VEC2',nv)
    indices=add(struct.pack('<'+'I'*len(faces),*faces),5125,'SCALAR',len(faces))
    meshes.append({'name':name,'primitives':[{'attributes':attributes,'indices':indices,'material':material}]})
    nodes.append({'name':name,'mesh':len(meshes)-1,'extras':extras})

def read_skin(path):
    raw=path.read_bytes(); nv,nt,nf,nb=struct.unpack_from('<4i',raw)
    floats=struct.unpack_from('<'+'f'*(nv*3),raw,16)
    verts=[(floats[i+1],floats[i+2],floats[i]) for i in range(0,len(floats),3)]
    uv=list(struct.unpack_from('<'+'f'*(nt*2),raw,16+12*nv))
    # This source atlas already matches glTF image row order; retain its UVs.
    faces=struct.unpack_from('<'+'I'*(nf*3),raw,16+12*nv+8*nt)
    assert max(faces)<nv
    off=16+12*nv+8*nt+12*nf; bindings={}
    for _ in range(nb):
        name=raw[off:off+40].split(b'\0')[0].decode(); off+=40
        pos=struct.unpack_from('<3f',raw,off); quat=struct.unpack_from('<4f',raw,off+12)
        count=struct.unpack_from('<i',raw,off+28)[0]; off+=32+8*count
        bindings[name]=(pos,quat)
    return verts,faces,uv,bindings

def fiber_uv(verts):
    # Visual texture only: principal-axis projection, NOT measured fibre paths.
    mean=[sum(v[k] for v in verts)/len(verts) for k in range(3)]
    centered=[[v[k]-mean[k] for k in range(3)] for v in verts]
    cov=[[sum(v[j]*v[k] for v in centered) for k in range(3)] for j in range(3)]
    longest=max(range(3),key=lambda k:cov[k][k]); axis=[float(k==longest) for k in range(3)]
    for _ in range(20):
        axis=[sum(row[k]*axis[k] for k in range(3)) for row in cov]
        length=math.sqrt(sum(x*x for x in axis)) or 1; axis=[x/length for x in axis]
    seed=min(range(3),key=lambda k:abs(axis[k])); cross=[float(k==seed)-axis[k]*axis[seed] for k in range(3)]
    length=math.sqrt(sum(x*x for x in cross)) or 1; cross=[x/length for x in cross]
    return [x for v in centered for x in (sum(v[k]*cross[k] for k in range(3))*100, sum(v[k]*axis[k] for k in range(3))*12)]

for p in sorted(source.glob('m_*.skn')):
    name=p.stem; verts,faces,_,_=read_skin(p)
    is_muscle=not any(x in name for x in exclude)
    canonical=re.sub(r'\(\d+\)|\.\d+$','',name).replace('.L','_L')
    canonical=re.sub(r'([LR])\d+$',r'\1',canonical)
    ident='dog_mesh_'+re.sub('[^a-z0-9_]', '_',canonical.lower())
    layer = 'muscle' if is_muscle else 'fascia' if any(x in name for x in ('Fascia','Aponeurosis','fascia_lata')) else 'tendon' if any(x in name for x in ('tendon','Ligament')) else 'context'
    extras={'sourceName':name, 'anatomyLayer':layer}
    if is_muscle:
        extras['muscleId']=ident
        g=groups.setdefault(ident,{'name':canonical,'vertices':[]}); g['vertices'].extend(verts)
    surface(name,verts,faces,extras,0 if is_muscle else 1,fiber_uv(verts))
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
def write_glb(filename, material_defs, texture=None):
    asset={'asset':{'version':'2.0','generator':'TheraPano SKN bind-pose converter','copyright':'Copyright (c) 2025 Vittorio La Barbera. MIT License.'},'scene':0,'scenes':[{'nodes':list(range(len(nodes)))}],'nodes':nodes,'meshes':meshes,'materials':material_defs, 'bufferViews':views,'accessors':accessors}
    if texture:
        while len(blob)%4: blob.append(0)
        data=texture.read_bytes(); view=len(views)
        views.append({'buffer':0,'byteOffset':len(blob),'byteLength':len(data)}); blob.extend(data)
        asset.update(images=[{'bufferView':view,'mimeType':'image/png'}],textures=[{'source':0}])
    asset['buffers']=[{'byteLength':len(blob)}]
    j=json.dumps(asset,separators=(',',':')).encode(); j+=b' '*((-len(j))%4); blob.extend(b'\0'*((-len(blob))%4))
    out=root/'public/assets/3D'/filename
    out.write_bytes(struct.pack('<III',0x46546c67,2,28+len(j)+len(blob))+struct.pack('<II',len(j),0x4e4f534a)+j+struct.pack('<II',len(blob),0x004e4942)+blob)
    print(filename, len(meshes),'surfaces;',out.stat().st_size,'bytes')

write_glb('Hund-Muskeln.glb',[{'pbrMetallicRoughness':{'baseColorFactor':[.48,.18,.14,1],'metallicFactor':0,'roughnessFactor':.7}},{'pbrMetallicRoughness':{'baseColorFactor':[.65,.62,.55,1],'metallicFactor':0,'roughnessFactor':.8}}])
(root/'public/assets/js/anatomy-dog-muscles.js').write_text('// Source: MusculoskeletalDog, MIT; see assets/3D/licenses. IDs are independent of legacy regions.\nexport const DOG_MUSCLES = '+json.dumps(defs,ensure_ascii=False,indent=2)+';\n')

# Optional skin retains the original UV atlas and texture.
blob=bytearray(); views=[]; accessors=[]; meshes=[]; nodes=[]
verts,faces,uv,bindings=read_skin(source/'dog_skin.skn')
surface('dog_skin',verts,faces,{'anatomyLayer':'skin'},0,uv)
write_glb('Hund-Haut.glb',[{'pbrMetallicRoughness':{'baseColorTexture':{'index':0},'metallicFactor':0,'roughnessFactor':.92}}],source.parent/'textures/skin_texture.png')

# Skeleton in the same bind pose as the skins, not the simulation's initial pose.
blob=bytearray(); views=[]; accessors=[]; meshes=[]; nodes=[]
xml=ET.parse(source.parents[1]/'models/dog.xml').getroot()
assets={m.get('name'):m for m in xml.findall('./asset/mesh')}
vertices=[]; triangles=[]; lookup={}; source_names=[]
for body in xml.findall('.//worldbody//body'):
    for geom in body.findall('geom'):
        if not geom.get('mesh') or 'eye' in geom.get('mesh').lower(): continue
        asset=assets[geom.get('mesh')]
        assert not any(geom.get(k) for k in ('quat','euler','axisangle','xyaxes','zaxis'))
        assert not asset.get('scale')
        pos,quat=bindings[body.get('name')]
        assert all(abs(a-b)<1e-6 for a,b in zip(quat,(1,0,0,0)))
        local=list(map(float,geom.get('pos','0 0 0').split()))
        offset=[pos[k]+local[k] for k in range(3)]
        raw=(source.parents[1]/'models'/asset.get('file')).read_bytes()
        count=struct.unpack_from('<I',raw,80)[0]; assert len(raw)==84+50*count
        source_names.append(geom.get('mesh'))
        for i in range(count):
            xyz=struct.unpack_from('<9f',raw,84+50*i+12)
            for j in range(0,9,3):
                v=tuple(xyz[j+k]+offset[k] for k in range(3)); v=(v[1],v[2],v[0])
                if v not in lookup: lookup[v]=len(vertices); vertices.append(v)
                triangles.append(lookup[v])
surface('Skeleton',vertices,triangles,{'anatomyLayer':'skeleton','sourceMeshes':source_names},0)
write_glb('Hund-Skelett.glb',[{'pbrMetallicRoughness':{'baseColorFactor':[.78,.72,.59,1],'metallicFactor':0,'roughnessFactor':.8}}])
