#!/usr/bin/env python3
"""Export separate source skeleton surfaces in the muscle/skin bind pose.

Usage: python scripts/convert-canine-skeleton-atlas.py SOURCE OUTPUT.glb
Source: MusculoskeletalDog, MIT, revision documented in docs/anatomy-3d.md.
Parts are source surfaces, not a claim of a complete or clinically verified skeleton.
"""
import json
import math
from pathlib import Path
import re
import struct
import sys
import xml.etree.ElementTree as ET

source = Path(sys.argv[1]) / 'musculoskeletal_dog'
raw = (source / 'assets/skins/dog_skin.skn').read_bytes()
nv, nt, nf, nb = struct.unpack_from('<4i', raw)
offset = 16 + 12 * nv + 8 * nt + 12 * nf
bindings = {}
for _ in range(nb):
    name = raw[offset:offset+40].split(b'\0')[0].decode()
    offset += 40
    pos = struct.unpack_from('<3f', raw, offset)
    quat = struct.unpack_from('<4f', raw, offset+12)
    count = struct.unpack_from('<i', raw, offset+28)[0]
    offset += 32 + 8 * count
    assert all(abs(a-b) < 1e-6 for a, b in zip(quat, (1, 0, 0, 0)))
    bindings[name] = pos

xml = ET.parse(source / 'models/dog.xml').getroot()
assets = {m.get('name'): m for m in xml.findall('./asset/mesh')}
blob = bytearray()
views, accessors, meshes, nodes = [], [], [], []

def add(data, component, kind, count, bounds=None):
    while len(blob) % 4:
        blob.append(0)
    views.append({'buffer': 0, 'byteOffset': len(blob), 'byteLength': len(data)})
    blob.extend(data)
    accessor = {'bufferView': len(views)-1, 'componentType': component, 'count': count, 'type': kind}
    if bounds:
        accessor.update(min=bounds[0], max=bounds[1])
    accessors.append(accessor)
    return len(accessors)-1

for body in xml.findall('.//worldbody//body'):
    for geom in body.findall('geom'):
        name = geom.get('mesh')
        if not name or 'eye' in name.lower():
            continue
        asset = assets[name]
        assert not any(geom.get(k) for k in ('quat', 'euler', 'axisangle', 'xyaxes', 'zaxis'))
        assert not asset.get('scale')
        local = list(map(float, geom.get('pos', '0 0 0').split()))
        translation = [bindings[body.get('name')][k]+local[k] for k in range(3)]
        data = (source / 'models' / asset.get('file')).read_bytes()
        count = struct.unpack_from('<I', data, 80)[0]
        assert len(data) == 84 + 50 * count
        vertices, faces, lookup = [], [], {}
        for i in range(count):
            xyz = struct.unpack_from('<9f', data, 84+50*i+12)
            for j in range(0, 9, 3):
                v = tuple(xyz[j+k]+translation[k] for k in range(3))
                v = (v[1], v[2], v[0])
                if v not in lookup:
                    lookup[v] = len(vertices)
                    vertices.append(v)
                faces.append(lookup[v])
        normals = [[0., 0., 0.] for _ in vertices]
        for i in range(0, len(faces), 3):
            a, b, c = [vertices[faces[i+j]] for j in range(3)]
            u, v = [[p[k]-a[k] for k in range(3)] for p in (b, c)]
            n = (u[1]*v[2]-u[2]*v[1], u[2]*v[0]-u[0]*v[2], u[0]*v[1]-u[1]*v[0])
            for j in range(3):
                for k in range(3):
                    normals[faces[i+j]][k] += n[k]
        normals = [[x/(math.sqrt(sum(v*v for v in n)) or 1) for x in n] for n in normals]
        bounds = ([min(v[k] for v in vertices) for k in range(3)], [max(v[k] for v in vertices) for k in range(3)])
        attrs = {}
        for key, values in [('POSITION', vertices), ('NORMAL', normals)]:
            flat = [v for row in values for v in row]
            attrs[key] = add(struct.pack('<'+'f'*len(flat), *flat), 5126, 'VEC3', len(vertices), bounds if key == 'POSITION' else None)
        indices = add(struct.pack('<'+'I'*len(faces), *faces), 5125, 'SCALAR', len(faces))
        ident = 'dog_bone_' + re.sub('[^a-z0-9_]', '_', name.lower())
        assert all(n['extras']['atlasId'] != ident for n in nodes)
        nodes.append({'name': name, 'mesh': len(meshes), 'extras': {'atlasId': ident, 'sourceName': name, 'anatomyLayer': 'skeleton'}})
        meshes.append({'name': name, 'primitives': [{'attributes': attrs, 'indices': indices, 'material': 0}]})

asset = {'asset': {'version': '2.0', 'generator': 'TheraPano source skeleton atlas converter', 'copyright': 'Copyright (c) 2025 Vittorio La Barbera. MIT License.'},
         'scene': 0, 'scenes': [{'nodes': list(range(len(nodes)))}], 'nodes': nodes, 'meshes': meshes,
         'materials': [{'pbrMetallicRoughness': {'baseColorFactor': [.78, .72, .59, 1], 'metallicFactor': 0, 'roughnessFactor': .8}}],
         'buffers': [{'byteLength': len(blob)}], 'bufferViews': views, 'accessors': accessors}
encoded = json.dumps(asset, separators=(',', ':')).encode()
encoded += b' ' * (-len(encoded) % 4)
blob.extend(b'\0' * (-len(blob) % 4))
Path(sys.argv[2]).write_bytes(struct.pack('<III', 0x46546c67, 2, 28+len(encoded)+len(blob)) + struct.pack('<II', len(encoded), 0x4e4f534a) + encoded + struct.pack('<II', len(blob), 0x004e4942) + blob)
print(f'{len(nodes)} separate skeleton source surfaces exported')
if len(sys.argv) > 3:
    catalog = [{'id': n['extras']['atlasId'], 'sourceName': n['name']} for n in nodes]
    Path(sys.argv[3]).write_text('// Source surface identities, not a count of individual bones. See docs/anatomy-3d.md.\nexport const DOG_BONES = '+json.dumps(catalog, indent=2)+';\n')
