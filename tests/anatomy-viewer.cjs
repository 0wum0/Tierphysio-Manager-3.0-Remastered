/* Run with Node 20+ and Playwright installed. CHROMIUM_EXECUTABLE is optional. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const results = [];
const records = new Map();
let postBody, failSave = false, failDelete = false;
// Synthetic boxes exercise species routing only; these are not anatomical assets.
function muscleFixture(species) {
    const vertices = [-.25,-.25,-.25, .25,-.25,-.25, .25,.25,-.25, -.25,.25,-.25,
        -.25,-.25,.25, .25,-.25,.25, .25,.25,.25, -.25,.25,.25];
    const indices = [0,2,1,0,3,2,4,5,6,4,6,7,0,1,5,0,5,4,2,3,7,2,7,6,0,4,7,0,7,3,1,2,6,1,6,5];
    const binary=Buffer.alloc(vertices.length*4+indices.length*2);
    vertices.forEach((v,i)=>binary.writeFloatLE(v,i*4));
    indices.forEach((v,i)=>binary.writeUInt16LE(v,vertices.length*4+i*2));
    const json={asset:{version:'2.0'},scene:0,scenes:[{nodes:[0,1]}],
        nodes:['left','right'].map((side,i)=>({mesh:0,translation:[i===0?-.6:.6,0,0],extras:{muscleId:`${species}_mesh_test_${side}`}})),
        meshes:[{primitives:[{attributes:{POSITION:0},indices:1}]}],
        buffers:[{byteLength:binary.length}],bufferViews:[{buffer:0,byteOffset:0,byteLength:vertices.length*4},{buffer:0,byteOffset:vertices.length*4,byteLength:indices.length*2}],
        accessors:[{bufferView:0,componentType:5126,count:8,type:'VEC3',min:[-.25,-.25,-.25],max:[.25,.25,.25]},{bufferView:1,componentType:5123,count:indices.length,type:'SCALAR'}]};
    let text=Buffer.from(JSON.stringify(json));text=Buffer.concat([text,Buffer.alloc((4-text.length%4)%4,32)]);
    const header=Buffer.alloc(20);header.writeUInt32LE(0x46546c67,0);header.writeUInt32LE(2,4);header.writeUInt32LE(28+text.length+binary.length,8);header.writeUInt32LE(text.length,12);header.writeUInt32LE(0x4e4f534a,16);
    const chunk=Buffer.alloc(8);chunk.writeUInt32LE(binary.length,0);chunk.writeUInt32LE(0x004e4942,4);
    return Buffer.concat([header,text,chunk,binary]);
}
const server = http.createServer((req,res) => {
    const url = new URL(req.url,'http://localhost');
    const fixtureSpecies=url.pathname.match(/fixture-(cat|horse)\.glb$/)?.[1];
    if(fixtureSpecies){res.setHeader('Content-Type','model/gltf-binary');return res.end(muscleFixture(fixtureSpecies));}
    if (url.pathname.startsWith('/api/')) {
        let body='';req.on('data',b=>body+=b);req.on('end',()=>{
            res.setHeader('Content-Type','application/json');
            if(req.method==='GET') return res.end(JSON.stringify({success:true,points:[...records.values()].filter(p=>p.animal_type===url.searchParams.get('animal_type'))}));
            if(url.pathname.endsWith('/loeschen')) {
                if(failDelete){res.statusCode=500;return res.end('{"success":false}');}
                const id=Number(url.pathname.split('/').at(-2));for(const [key,v] of records) if(v.id===id) records.delete(key);
                return res.end('{"success":true}');
            }
            postBody=req.headers['content-type'].includes('json')?JSON.parse(body):Object.fromEntries(new URLSearchParams(body));
            if(failSave){res.statusCode=500;return res.end('{"success":false}');}
            const key=[postBody.animal_type,postBody.muscle_group_id,postBody.side].join('::');
            const id=records.get(key)?.id || records.size+1;
            records.set(key,{...postBody,id,pain_level:Number(postBody.pain_level)});
            res.end(JSON.stringify({success:true,id}));
        });return;
    }
    if(url.pathname==='/test' || url.pathname==='/bundle/test') {
        const mobile=url.searchParams.has('bundle');
        const prefix=mobile?'/bundle':'/assets/js';
        res.setHeader('Content-Type','text/html');
        // Same policy as public/.htaccess; catches decoder failures under CSP.
        const policy=fs.readFileSync(path.join(root,'public/.htaccess'),'utf8').match(/Header always set Content-Security-Policy "([^"]+)"/)[1];
        res.setHeader('Content-Security-Policy',policy);
        res.end(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>html,body{height:100%;margin:0;font-family:Arial}#viewer{height:100%;width:100%}</style><script type="importmap">{"imports":{"three":"${prefix}/vendor/three/three.module.min.js"}}</script></head><body><div id="viewer"></div><script>window.__A3D_CONFIG__={apiBase:location.origin,token:'test'};</script><script type="module">import '${prefix}/anatomy-3d-viewer.js';Anatomy3D.init('viewer',1,'dog','test');</script></body></html>`);return;
    }
    const file=url.pathname.startsWith('/bundle/')?path.join(root,'flutter_app/assets/3d',url.pathname.slice(8)):path.join(root,'public',url.pathname);
    if(!fs.existsSync(file)){res.statusCode=404;return res.end('not found');}
    res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.glb')?'model/gltf-binary':'text/plain');
    res.setHeader('Content-Length',fs.statSync(file).size);fs.createReadStream(file).pipe(res);
});
const ready=page=>page.waitForFunction(()=>Anatomy3D._instance.hotspots.length>0 && document.querySelector('#a3d-loading').style.display==='none');
const camera=async(page,x,z=0)=>{
    await page.evaluate(({x,z})=>{const v=Anatomy3D._instance;v.controls.enableDamping=false;v.camera.position.set(x,.1,z);v.controls.target.set(0,0,0);v.controls.update();v._dirty=true;},{x,z});
};
const position=async(page,id)=>page.evaluate(id=>{const v=Anatomy3D._instance;v.camera.updateMatrixWorld();const h=v.hotspots.find(h=>h.def.id===id);const p=h.pos.clone().project(v.camera);const r=v.renderer.domElement.getBoundingClientRect();return{x:r.left+(p.x+1)*r.width/2,y:r.top+(1-p.y)*r.height/2};},id);
(async()=>{
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    const origin=`http://127.0.0.1:${server.address().port}`;
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_EXECUTABLE || undefined,args:['--no-sandbox','--use-angle=swiftshader','--enable-unsafe-swiftshader']});
    try {
        for(const bundle of [false,true]) {
            const page=await browser.newPage({viewport:{width:1200,height:850}});
            const errors=[];page.on('pageerror',e=>{errors.push(e.message);console.error(e.message)});page.on('console',m=>{if(m.type()==='error')console.error(m.text())});page.on('requestfailed',r=>console.error(r.url(),r.failure()));
            await page.goto(origin+(bundle?'/bundle/test?bundle=1':'/test'));await ready(page);
            // New dog anatomy: exact surfaces, deep muscle isolation, independent sides and NRS 0.
            assert.equal(await page.evaluate(()=>Anatomy3D._instance.hotspots.length),229);
            assert(await page.evaluate(()=>{
                const v=Anatomy3D._instance;
                return v.hotspots.every(h=>v._surfaces.entries.has(h.def.id))
                    && v._surfaces.entries.size===229
                    && v._modelMeshes.filter(m=>m.userData.anatomyLayer==='muscle').length===250;
            }));
            // Supplementary layers share the primary normalization and load only on demand.
            await page.evaluate(()=>Anatomy3D._instance._loadLayer('skeleton'));
            assert(await page.evaluate(()=>{
                const v=Anatomy3D._instance;
                return v._modelMeshes.some(m=>m.userData.anatomyLayer==='skeleton' && m.visible)
                    && !v._modelMeshes.some(m=>m.userData.anatomyLayer==='skin')
                    && [...v._surfaces.entries.values()].flat().every(m=>m.material.bumpMap && m.material.color.getHexString()!=='8a929f');
            }));
            await page.locator('#a3d-layers summary').click();
            const transform = await page.evaluate(()=>Anatomy3D._instance.modelGroup.matrixWorld.toArray());
            await page.locator('[data-layer="skin"]').check();
            await page.waitForFunction(()=>Anatomy3D._instance._modelMeshes.some(m=>m.userData.anatomyLayer==='skin' && m.visible));
            assert.deepEqual(await page.evaluate(()=>Anatomy3D._instance.modelGroup.matrixWorld.toArray()), transform);
            assert(await page.evaluate(()=>Anatomy3D._instance._modelMeshes.filter(m=>m.visible).every(m=>m.userData.anatomyLayer==='skin')));
            if(process.env.ANATOMY_SCREENSHOTS && !bundle) await page.screenshot({path:path.join(process.env.ANATOMY_SCREENSHOTS,'dog-skin.png')});
            // A failed optional coat must preserve the loaded skin and allow retry.
            await page.evaluate(() => {
                window.originalGetImageData = CanvasRenderingContext2D.prototype.getImageData;
                CanvasRenderingContext2D.prototype.getImageData = () => { throw new Error('Texture read unavailable'); };
            });
            await page.locator('[data-layer="fur"]').click();
            await page.waitForFunction(() => !Anatomy3D._instance._layerState.fur);
            assert(await page.evaluate(() => {
                const v = Anatomy3D._instance;
                return v._layerState.skin && v._skinLayer?.parent === v.modelGroup
                    && v._modelMeshes.some(m => m.userData.anatomyLayer === 'skin' && m.visible);
            }));
            assert.match(await page.locator('#a3d-layer-status').textContent(), /Haut bleibt nutzbar/);
            await page.evaluate(() => {
                CanvasRenderingContext2D.prototype.getImageData = window.originalGetImageData;
                delete window.originalGetImageData;
            });
            await page.locator('[data-layer="fur"]').check();
            assert(await page.evaluate(()=>{
                const v=Anatomy3D._instance;
                return v._fur?.visible && v._fur.geometry.attributes.position.count>1000 && v._fur.geometry.attributes.position.count<=48000
                    && !v._modelMeshes.includes(v._fur);
            }));
            if(process.env.ANATOMY_SCREENSHOTS && !bundle) await page.screenshot({path:path.join(process.env.ANATOMY_SCREENSHOTS,'dog-coat.png')});
            await page.locator('[data-layer="skin"]').uncheck();
            assert(await page.evaluate(()=>!Anatomy3D._instance._fur.visible));
            for(const layer of ['muscle','fascia','tendon']) await page.locator(`[data-layer="${layer}"]`).uncheck();
            assert(await page.evaluate(()=>Anatomy3D._instance._modelMeshes.filter(m=>m.visible).every(m=>m.userData.anatomyLayer==='skeleton')));
            if(process.env.ANATOMY_SCREENSHOTS && !bundle) await page.screenshot({path:path.join(process.env.ANATOMY_SCREENSHOTS,'dog-skeleton.png')});
            for(const layer of ['muscle','fascia','tendon']) await page.locator(`[data-layer="${layer}"]`).check();
            await page.locator('#a3d-fascia-opacity').fill('0.35');
            assert(await page.evaluate(()=>Anatomy3D._instance._modelMeshes.filter(m=>m.userData.anatomyLayer==='fascia').every(m=>m.material.transparent && m.material.opacity===.35)));
            await page.locator('#a3d-fascia-opacity').fill('1');
            await page.locator('#a3d-layers summary').click();
            await page.locator('#a3d-search-panel summary').click();
            await page.locator('#a3d-structure-search').fill('BICEPS femoris');
            await page.selectOption('#a3d-filter-side','right');
            await page.selectOption('#a3d-filter-kind','tendon');
            assert.equal(await page.locator('#a3d-region-select option').count(),2);
            await page.selectOption('#a3d-region-select','dog_mesh_tissue_biceps_femoris_tendon_r');
            await page.locator('#a3d-pain-slider').fill('6');
            await page.locator('#a3d-save-btn').click();
            await page.waitForFunction(()=>Anatomy3D._instance.painData['dog_mesh_tissue_biceps_femoris_tendon_r::right']?.painLevel===6);
            assert.equal(postBody.region,'tendon');
            await page.selectOption('#a3d-region-select','dog_mesh_tissue_biceps_femoris_tendon_r');
            await page.locator('#a3d-remove-btn').click();
            await page.waitForFunction(()=>!Anatomy3D._instance.painData['dog_mesh_tissue_biceps_femoris_tendon_r::right']);
            await page.locator('#a3d-search-panel summary').click();
            await page.locator('#a3d-structure-search').fill('does-not-exist');
            assert(await page.locator('#a3d-region-select').isDisabled());
            await page.locator('#a3d-filter-reset').click();
            assert.equal(await page.locator('#a3d-region-select option').count(),230);
            await page.selectOption('#a3d-filter-kind','fascia');
            assert.equal(await page.locator('#a3d-region-select option').count(),3);
            await page.selectOption('#a3d-region-select','dog_mesh_tissue_thoracolumbar');
            await page.locator('#a3d-pain-slider').fill('4');
            assert(await page.evaluate(()=>Anatomy3D._instance._surfaces.entries.get('dog_mesh_tissue_thoracolumbar').every(m=>m.visible&&m.material.color.getHexString()==='fb923c')));
            await page.locator('#a3d-cancel-btn').click();
            await page.evaluate(()=>Anatomy3D._instance._resetStructureFilters());
            const muscleId='dog_mesh_m_biceps_brachii_l';
            await page.selectOption('#a3d-region-select', muscleId);
            assert(await page.locator('#a3d-isolate').isChecked());
            assert.equal(await page.evaluate(()=>{
                const v=Anatomy3D._instance;v.scene.updateMatrixWorld(true);v.camera.updateMatrixWorld(true);
                // Cast at visible triangles of the isolated muscle, independent of landmark dots.
                const mesh=v._modelMeshes.find(m=>m.visible);const pos=mesh.geometry.attributes.position;
                const point=v.controls.target.clone();
                for(let i=0;i<pos.count;i+=3){
                    point.fromBufferAttribute(pos,i).applyMatrix4(mesh.matrixWorld).project(v.camera);
                    v.pointer.set(point.x,point.y);const hit=v._pickHotspot();
                    if(hit) return hit.userData.hotspot.id;
                }
                return null;
            }),muscleId);
            await page.locator('#a3d-pain-slider').fill('8');
            assert(await page.evaluate(id=>{
                const v=Anatomy3D._instance;
                const selected=v._surfaces.entries.get(id);
                const opposite=v._surfaces.entries.get(id.replace(/_l$/, '_r'));
                return selected.length>0 && selected.every(m=>m.visible && m.material.color.getHexString()==='b91c1c' && m.material.emissiveIntensity>0)
                    && opposite.every(m=>!m.visible && m.material.emissive.getHex()===0);
            },muscleId));
            if(process.env.ANATOMY_SCREENSHOTS && !bundle) await page.screenshot({path:path.join(process.env.ANATOMY_SCREENSHOTS,'dog-muscle-isolated.png')});
            await page.locator('#a3d-cancel-btn').click();
            assert(await page.evaluate(()=>!Anatomy3D._instance._fur?.visible));
            assert(await page.evaluate(()=>Anatomy3D._instance._modelMeshes.every(m=>m.visible === (m.userData.anatomyLayer!=='skin') && m.material.emissive.getHex()===0)));
            await page.selectOption('#a3d-region-select',muscleId);
            await page.locator('#a3d-pain-slider').fill('8');await page.locator('#a3d-save-btn').click();
            await page.waitForFunction(id=>Anatomy3D._instance.painData[id+'::left']?.painLevel===8,muscleId);
            await page.evaluate(()=>Anatomy3D._instance._loadPainData());
            await page.waitForFunction(id=>Anatomy3D._instance.painData[id+'::left']?.painLevel===8,muscleId);
            assert(await page.evaluate(id=>Anatomy3D._instance._surfaces.entries.get(id).every(m=>m.material.color.getHexString()==='b91c1c'),muscleId));
            if(process.env.ANATOMY_SCREENSHOTS && !bundle) await page.screenshot({path:path.join(process.env.ANATOMY_SCREENSHOTS,'dog-muscles-desktop.png')});
            await page.selectOption('#a3d-region-select',muscleId);
            await page.locator('#a3d-pain-slider').fill('0');await page.locator('#a3d-save-btn').click();
            await page.waitForFunction(id=>Anatomy3D._instance.painData[id+'::left']?.painLevel===0,muscleId);
            assert(await page.evaluate(id=>Anatomy3D._instance._surfaces.entries.get(id).every(m=>m.material.color.getHexString()==='22c55e'),muscleId));
            await page.selectOption('#a3d-region-select',muscleId);await page.locator('#a3d-remove-btn').click();
            await page.waitForFunction(id=>!Anatomy3D._instance.painData[id+'::left'],muscleId);
            // Every structure, including the small/deep ones, fits between toolbar and form.
            assert(await page.evaluate(()=>{
                const v=Anatomy3D._instance, c=v.container;
                for (const h of v.hotspots) {
                    v._focusHotspot(h.def);v._openForm(h.def);v.camera.updateMatrixWorld(true);
                    const point=v.controls.target.clone().project(v.camera);
                    const rect=v.renderer.domElement.getBoundingClientRect();
                    const y=rect.top+(1-point.y)*rect.height/2;
                    if (y<=c.querySelector('#a3d-toolbar').getBoundingClientRect().bottom || y>=c.querySelector('#a3d-form').getBoundingClientRect().top) return false;
                }
                v._closeForm();return !v.camera.view?.enabled;
            }));
            // Legacy geometry and data remain independently reachable.
            await page.evaluate(()=>Anatomy3D._instance._setMuscleMode(false));await ready(page);
            for(const species of ['dog','cat','horse']) {
                await page.evaluate(species=>Anatomy3D.switchAnimal(species),species);await ready(page);
                const stats=await page.evaluate(()=>{const v=Anatomy3D._instance;let vertices=0;v.modelGroup.traverse(o=>{if(o.isMesh)vertices+=o.geometry.attributes.position.count});return{species:v.animalType,vertices,points:v.hotspots.length,size:v._modelBox.size.toArray()}});
                assert.equal(stats.species,species);assert(stats.vertices<200000);assert(stats.points>=40);assert(Math.abs(Math.max(...stats.size)-2)<.001);
                if(!bundle) results.push(stats);
                assert.equal(await page.locator('#a3d-zones-visible').isChecked(), true);
                assert.equal(await page.locator('#a3d-region-select option').count(), stats.points+1);
                await page.locator('#a3d-zones-visible').uncheck();
                assert(await page.evaluate(()=>!Anatomy3D._instance.debugMode && Anatomy3D._instance.hotspots.every(h=>h.marker.material.opacity===0)));
                await page.locator('#a3d-zones-visible').check();
                assert(await page.evaluate(()=>Anatomy3D._instance.debugMode && Anatomy3D._instance.hotspots.every(h=>h.marker.material.opacity>=.9)));
                await page.evaluate(()=>{
                    const v=Anatomy3D._instance;v._previewPain(v.hotspots[0].def.id+'::left',7);
                });
                assert(await page.evaluate(()=>Anatomy3D._instance._surfaces.buffers.every(b=>b.weights.array.some(w=>w===1))));
                await page.evaluate(()=>Anatomy3D._instance._applyPainToHotspots());
                // Every configured region is reachable even on the occluded side.
                const ids=await page.evaluate(()=>Anatomy3D._instance.hotspots.map(h=>h.def.id));
                for(const id of ids) {
                    await page.selectOption('#a3d-region-select',id);
                    assert.equal(await page.evaluate(()=>Anatomy3D._instance.selectedKey?.split('::')[0]),id);
                }
                await page.locator('#a3d-form-close').click();

                const sign=species==='dog'?1:-1;
                // Check actual picking on both sides, including the reported rear paw/throat regression.
                for(const [side,x] of [['l',sign*3.4],['r',-sign*3.4]]) {
                    await camera(page,x);
                    for(const suffix of ['shoulder_'+side,'fore_'+side,(species==='horse'?'hoof_h':'paw_h')+side]) {
                        const id=species+'_'+suffix;const p=await position(page,id);await page.mouse.click(p.x,p.y);
                        const selected=await page.evaluate(()=>Anatomy3D._instance.selectedKey);
                        assert.equal(selected?.split('::')[0],id,`pick ${id} (${JSON.stringify(p)})`);
                        assert((await page.locator('#a3d-form-sub').textContent()).includes('aus Sicht des Tieres'));
                        await page.locator('#a3d-form-close').click();
                    }
                }
                if(process.env.ANATOMY_SCREENSHOTS && !bundle) {
                    await camera(page,sign*3.4);
                    await page.screenshot({path:path.join(process.env.ANATOMY_SCREENSHOTS,`${species}-desktop.png`)});
                }
            }
            // A fresh tap has no hover state. Persisting a side-specific legacy region must round-trip.
            await page.evaluate(()=>{const v=Anatomy3D._instance;v._switchAnimal('dog');});await ready(page);
            await page.evaluate(()=>{const v=Anatomy3D._instance;v._openForm(v.hotspots.find(h=>h.def.id==='dog_thoracic').def);});
            await page.selectOption('#a3d-side-sel','left');await page.locator('#a3d-pain-slider').fill('6');
            await page.locator('[data-pt="Druckschmerz"]').click();await page.locator('#a3d-save-btn').click();
            await page.waitForFunction(()=>Anatomy3D._instance.painData['dog_thoracic::left']);
            assert.equal(postBody.side,'left');assert(postBody.muscle_group_label.includes('M. longissimus thoracis'));
            assert(await page.evaluate(()=>Anatomy3D._instance._surfaces.buffers.every(b=>{
                let colored=false;
                for(let i=0;i<b.sides.length;i++){
                    if(b.weights.getX(i)>0){colored=true;if(b.sides[i]!==0)return false;}
                }
                return colored;
            })));
            await page.evaluate(()=>Anatomy3D._instance._loadPainData());
            await page.waitForFunction(()=>Anatomy3D._instance.painData['dog_thoracic::left']);
            await page.evaluate(()=>{const v=Anatomy3D._instance;v._openForm(v.hotspots.find(h=>h.def.id==='dog_thoracic').def)});
            assert.equal(await page.locator('#a3d-pain-slider').inputValue(),'6');assert.equal(await page.locator('#a3d-side-sel').inputValue(),'left');
            failSave=true;await page.locator('#a3d-save-btn').click();await page.locator('#a3d-form-error').filter({hasText:'Speichern fehlgeschlagen'}).waitFor();failSave=false;
            failDelete=true;await page.locator('#a3d-remove-btn').click();await page.locator('#a3d-form-error').filter({hasText:'Entfernen fehlgeschlagen'}).waitFor();failDelete=false;
            assert(await page.evaluate(()=>Boolean(Anatomy3D._instance.painData['dog_thoracic::left'])));
            await page.locator('#a3d-remove-btn').click();await page.waitForFunction(()=>!Anatomy3D._instance.painData['dog_thoracic::left']);
            // Out-of-order species loads cannot replace the currently selected model.
            await page.evaluate(()=>{Anatomy3D.switchAnimal('cat');Anatomy3D.switchAnimal('horse');Anatomy3D.switchAnimal('dog')});await ready(page);
            assert(await page.evaluate(()=>Anatomy3D._instance.hotspots.every(h=>h.def.id.startsWith('dog_'))));
            // Registered cat/horse geometry follows the same picking/color/isolation path.
            const registryPath=(bundle?'/bundle':'/assets/js')+'/anatomy-models.js?v=20261006-usable';
            await page.evaluate(async registryPath=>{
                const {SEGMENTED_MODELS}=await import(registryPath);
                for(const species of ['cat','horse']) SEGMENTED_MODELS[species]={file:`fixture-${species}.glb`,leftSign:-1,
                    definitions:['left','right'].map((side,i)=>({id:`${species}_mesh_test_${side}`,label:`Testfläche ${side}`,anatomical:'Synthetische Testfläche',side,region:'test',pos:[i===0?-.705882:.705882,0,0]}))};
            },registryPath);
            await page.evaluate(()=>Anatomy3D._instance._setMuscleMode(true));await ready(page);
            for(const species of ['cat','horse']) {
                await page.evaluate(s=>Anatomy3D.switchAnimal(s),species);await ready(page);
                assert(await page.evaluate(()=>{const v=Anatomy3D._instance;return v._exactMuscles() && v._leftSign()===-1 && v.hotspots.length===2}));
                await page.selectOption('#a3d-region-select',`${species}_mesh_test_left`);
                await page.locator('#a3d-pain-slider').fill('5');
                assert(await page.evaluate(s=>{
                    const v=Anatomy3D._instance;
                    return v._surfaces.entries.get(s+'_mesh_test_left').every(m=>m.visible&&m.material.color.getHexString()==='f97316')
                        && v._surfaces.entries.get(s+'_mesh_test_right').every(m=>!m.visible&&m.material.emissive.getHex()===0);
                },species));
                await page.locator('#a3d-cancel-btn').click();
            }
            // A catalog from the wrong species must fail closed before any hotspot is shown.
            await page.evaluate(async registryPath=>{
                const {SEGMENTED_MODELS}=await import(registryPath);
                SEGMENTED_MODELS.cat.definitions[0].id='dog_mesh_wrong';
                Anatomy3D.switchAnimal('cat');
            },registryPath);
            await page.waitForFunction(()=>!document.querySelector('#a3d-retry-btn').hidden);
            assert(await page.evaluate(()=>Anatomy3D._instance.hotspots.length===0 && Anatomy3D._instance.modelGroup===null));
            assert.deepEqual(errors,[]);
            await page.close();
        }
        // Optional layer failures are recoverable; late parses cannot contaminate another species.
        const layerPage = await browser.newPage();
        let failSkin = true, releaseSkin;
        await layerPage.route('**/Hund-Haut.glb*', async route => {
            if (failSkin) return route.fulfill({status:503,body:'unavailable'});
            await new Promise(resolve => { releaseSkin = resolve; });
            try { await route.continue(); } catch { /* request may already have been aborted */ }
        });
        await layerPage.goto(origin+'/test');await ready(layerPage);
        await layerPage.evaluate(()=>Anatomy3D._instance._setLayer('skin',true));
        assert(await layerPage.evaluate(()=>!Anatomy3D._instance._layerState.skin && document.querySelector('#a3d-layer-status').textContent.includes('erneut')));
        await layerPage.evaluate(()=>Anatomy3D._instance._loadLayer('skeleton'));
        assert(await layerPage.evaluate(()=>!Anatomy3D._instance._layerState.skin && document.querySelector('#a3d-layer-status').textContent.includes('erneut')));
        failSkin = false;
        await layerPage.evaluate(()=>{Anatomy3D._instance._setLayer('skin',true);});
        await new Promise(resolve=>{
            const timer=setInterval(()=>{if(releaseSkin){clearInterval(timer);resolve();}},10);
        });
        await layerPage.evaluate(()=>Anatomy3D.switchAnimal('cat'));await ready(layerPage);
        releaseSkin();
        await layerPage.waitForTimeout(100);
        assert(await layerPage.evaluate(()=>Anatomy3D._instance.animalType==='cat' && !Anatomy3D._instance._modelMeshes.some(m=>m.userData.anatomyLayer)));
        await layerPage.close();
        const context=await browser.newContext({viewport:{width:390,height:844},deviceScaleFactor:3,isMobile:true,hasTouch:true});
        const page=await context.newPage();await page.goto(origin+'/test');await ready(page);
        await page.evaluate(()=>Anatomy3D._instance._loadLayer('skeleton'));
        await page.locator('#a3d-layers summary').tap();
        assert(await page.locator('[data-layer="skin"]').isVisible());
        assert(await page.evaluate(()=>document.querySelector('#a3d-layers').getBoundingClientRect().right<=innerWidth));
        await page.locator('#a3d-layers summary').tap();
        if(process.env.ANATOMY_SCREENSHOTS) await page.screenshot({path:path.join(process.env.ANATOMY_SCREENSHOTS,'dog-body-mobile.png')});
        await page.selectOption('#a3d-region-select','dog_mesh_m_biceps_brachii_l');
        await page.locator('#a3d-pain-slider').fill('7');
        if(process.env.ANATOMY_SCREENSHOTS) await page.screenshot({path:path.join(process.env.ANATOMY_SCREENSHOTS,'dog-muscles-mobile.png')});
        assert(await page.evaluate(()=>{
            const f=document.querySelector('#a3d-form').getBoundingClientRect();
            return f.left>=0 && f.right<=innerWidth && f.bottom<=innerHeight;
        }));
        await page.locator('#a3d-cancel-btn').click();
        await page.evaluate(()=>Anatomy3D._instance._setMuscleMode(false));await ready(page);
        for(const species of ['dog','cat','horse']) {
            await page.evaluate(s=>Anatomy3D.switchAnimal(s),species);await ready(page);
            assert(await page.evaluate(()=>{const v=Anatomy3D._instance;return v.renderer.getPixelRatio()<=1.5 && v.hotspots.every(h=>{const p=h.pos.clone().project(v.camera);return Math.abs(p.x)<1&&Math.abs(p.y)<1})}));
            const sign=species==='dog'?1:-1;
            await page.evaluate(sign=>{const v=Anatomy3D._instance;v.controls.enableDamping=false;const distance=v.camera.position.length();v.camera.position.set(sign*distance,.05,0);v.controls.target.set(0,0,0);v.controls.update()},sign);
            await page.locator('#a3d-zones-visible').uncheck();
            await page.locator('#a3d-zones-visible').check();
            assert(await page.evaluate(()=>{const v=Anatomy3D._instance;v._sizeMarkers();return v.hotspots.every(({marker,pos})=>{const depth=-pos.clone().applyMatrix4(v.camera.matrixWorldInverse).z;const radius=.012*marker.scale.x/depth/(2*Math.tan(v.camera.fov*Math.PI/360))*v.renderer.domElement.clientHeight;return radius>=5.9})}));
            const id=species+'_shoulder_l';const p=await position(page,id);await page.touchscreen.tap(p.x,p.y);
            assert.equal(await page.evaluate(()=>Anatomy3D._instance.selectedKey?.split('::')[0]),id);
            if(process.env.ANATOMY_SCREENSHOTS)await page.screenshot({path:path.join(process.env.ANATOMY_SCREENSHOTS,`${species}-mobile.png`)});
            await page.locator('#a3d-form-close').click();
        }
        // Container resize (modal/fullscreen) updates camera without a window resize event.
        await page.evaluate(()=>{Anatomy3D._instance.container.style.width='300px';Anatomy3D._instance.container.style.height='420px'});
        await page.waitForFunction(()=>Math.abs(Anatomy3D._instance.camera.aspect-300/420)<.001);
        // Closing while a model is parsing must leave no live renderer/model behind.
        await page.evaluate(()=>{const v=Anatomy3D._instance;v._switchAnimal('dog');v.destroy()});
        assert(await page.evaluate(()=>Anatomy3D._instance._disposed && Anatomy3D._instance.modelGroup===null));
        console.log(JSON.stringify({passed:true,models:results,checks:'225 exact muscle structures + 4 connective tissues; name/side/tissue search; cosmetic coat; focus of all 229 structures; lazy skin and skeleton; layer visibility and opacity; retry and layer/species races; surface raycast; NRS preview/cancel/zero/save/reload/delete; regional surface colors; mobile muscle form; zone checkbox off/on; all regions reachable; constant mobile point size; desktop both sides; mobile taps; CSP; web+Flutter bundle; save/reload/delete and errors; species races; resize; disposal'},null,2));
    } finally {await browser.close();server.close();}
})().catch(e=>{console.error(e);server.close();process.exitCode=1});
