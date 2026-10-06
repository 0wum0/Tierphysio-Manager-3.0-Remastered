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
const server = http.createServer((req,res) => {
    const url = new URL(req.url,'http://localhost');
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
            assert.equal(await page.evaluate(()=>Anatomy3D._instance.hotspots.length),225);
            assert(await page.evaluate(()=>{
                const v=Anatomy3D._instance;
                return v.hotspots.every(h=>v._surfaces.entries.has(h.def.id))
                    && v._surfaces.entries.size===225
                    && v._modelMeshes.filter(m=>m.userData.muscleId).length===250;
            }));
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
            assert(await page.evaluate(()=>Anatomy3D._instance._modelMeshes.every(m=>m.visible && m.material.emissive.getHex()===0)));
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
            assert.deepEqual(errors,[]);
            await page.close();
        }
        const context=await browser.newContext({viewport:{width:390,height:844},deviceScaleFactor:3,isMobile:true,hasTouch:true});
        const page=await context.newPage();await page.goto(origin+'/test');await ready(page);
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
        console.log(JSON.stringify({passed:true,models:results,checks:'225 exact muscle structures; surface raycast; NRS preview/cancel/zero/save/reload/delete; regional surface colors; mobile muscle form; zone checkbox off/on; all regions reachable; constant mobile point size; desktop both sides; mobile taps; CSP; web+Flutter bundle; save/reload/delete and errors; species races; resize; disposal'},null,2));
    } finally {await browser.close();server.close();}
})().catch(e=>{console.error(e);server.close();process.exitCode=1});
