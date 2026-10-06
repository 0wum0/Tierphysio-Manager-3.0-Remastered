import { DOG_BONES } from './anatomy-dog-bones.js?v=20261006-atlas';

const SOURCE = {label:'MusculoskeletalDog · Modellquelle (MIT)', url:'https://github.com/vittorione94/MusculoskeletalDog/tree/2e87897e78cc99ecfcab930869a5b68c0fb1605a'};
const HISTOLOGY = {label:'Ahmed et al., 2019 · Faszienschichten bei Hund und Pferd', url:'https://pubmed.ncbi.nlm.nih.gov/31402460/'};
const PELVIS = {label:'University of Minnesota · Becken und Oberschenkel', url:'https://vanat.ahc.umn.edu/carnLabs/Lab05/Lab05.html'};
const FORELIMB = {label:'University of Minnesota · Distale Vordergliedmaße', url:'https://open.lib.umn.edu/dogcatanatomylabguide/chapter/part-4-distal-thoracic-limb/'};
const HINDLIMB = {label:'University of Minnesota · Distale Hintergliedmaße', url:'https://pressbooks.umn.edu/dogcatanatomylabguide/chapter/part-3-distal-pelvic-limb/'};
const SIDE = {left:'Links', right:'Rechts', midline:'Mittig', bilateral:'Beidseitig', whole:'Gesamter Körper'};
const KIND = {muscle:'Muskeln', fascia:'Faszien / Aponeurosen', tendon:'Sehnen', skeleton:'Skelett', skin:'Haut', fur:'Fell'};
const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const normalized = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('de').replace(/[_.,]/g,' ');

// Documentation entries have no selectable 3D geometry and cannot receive findings.
// This is an explicitly limited regional checklist, not an exhaustive fascial atlas.
const FASCIA_REFERENCE = [
    ['superficialis','Fascia superficialis','Oberflächliche Faszie','whole','Unter der Haut; beim Hund regional mehrschichtig. Im Modell fehlt eine eigene Gewebefläche.',HISTOLOGY],
    ['profunda','Fascia profunda','Tiefe Faszie','whole','Tiefe bindegewebige Schichten im Verhältnis zur Muskulatur. Ihre regionale Ausdehnung ist hier nicht vollständig modelliert.',HISTOLOGY],
    ['epimysium','Epimysium','Muskelhülle','whole','Bindegewebe an der Muskeloberfläche. Die vorhandenen Muskelmeshes besitzen keine separat segmentierten Epimysiumflächen.',HISTOLOGY],
    ['gluteal_superficial','Oberflächliche Glutealfaszie','Becken / Gesäß','bilateral','Regionale Faszie der Glutealregion; separate Oberfläche fehlt.',PELVIS],
    ['gluteal_deep','Tiefe Glutealfaszie','Becken / Gesäß','bilateral','Tiefe Faszie der Glutealregion; separate Oberfläche fehlt.',PELVIS],
    ['lata','Fascia lata','Oberschenkelfaszie','bilateral','Faszie am Oberschenkel. Die Quelle enthält zwei uneindeutig benannte Flächen nahe dem M. vastus lateralis; sie belegen keine vollständige Fascia lata.',PELVIS],
    ['cruris','Fascia cruris','Unterschenkelfaszie','bilateral','Tiefe Faszienhülle um die Unterschenkelmuskulatur. Keine zugeordnete 3D-Fläche vorhanden.',HINDLIMB],
    ['antebrachii','Fascia antebrachii','Unterarmfaszie','bilateral','Tiefe Faszienhülle um die Unterarmmuskulatur. Keine zugeordnete 3D-Fläche vorhanden.',FORELIMB],
].map(([id,anatomical,label,side,description,source]) => ({id:'reference_fascia_'+id,anatomical,label,side,kind:'fascia',status:'missing',description,sources:[source]}));

function boneDefinition(entry) {
    const name = entry.sourceName;
    const side = /(?:^|_)L(?:_|\.|$)/.test(name) ? 'left' : /(?:^|_)R(?:_|\.|$)/.test(name) ? 'right' : 'midline';
    const base = name.replace(/_[LR](?=_|\.|$)/g,'');
    let anatomical, label;
    const exact = {
        Ribcage:['Thoraxskelet','Brustkorb · zusammengefasste Quellstruktur'],
        MergedSkull:['Cranium','Schädel · zusammengefasste Quellstruktur'], Jaw:['Mandibula','Unterkiefer'],
        Sacrum:['Os sacrum','Kreuzbein'], Pelvis:['Pelvis','Becken · zusammengefasste Quellstruktur'],
        Scapula:['Scapula','Schulterblatt'], humerus:['Humerus','Oberarmknochen'],
        Radius:['Radius','Speiche'], Ulna:['Ulna','Elle'], Femoris:['Femur','Oberschenkelknochen'],
        Patella:['Patella','Kniescheibe'], Tibia:['Tibia','Schienbein'], Fibula:['Fibula','Wadenbein'],
    };
    if (exact[base]) [anatomical,label] = exact[base];
    else if (/^C_\d+$/.test(base)) {anatomical = 'Vertebra cervicalis '+base.slice(2);label='Halswirbel';}
    else if (/^L_\d+$/.test(base)) {anatomical = 'Vertebra lumbalis '+base.slice(2);label='Lendenwirbel';}
    else if (/^Ca_\d+$/.test(base)) {anatomical = 'Vertebra caudalis '+base.slice(3);label='Schwanzwirbel';}
    else {
        // Retain ambiguous source numbering; do not invent individual digit/bone identities.
        anatomical = name;
        label = /^Phalan/.test(name) ? 'Zehenknochen · Quellbezeichnung'
            : /metacarp/i.test(name) ? 'Vordermittelfuß · Quellbezeichnung'
            : /Metatarsi/.test(name) ? 'Hintermittelfuß · Quellbezeichnung'
            : /Carpal/.test(name) ? 'Vorderfußwurzel · Quellbezeichnung'
            : /Tarsus|tarsal|Calcaneal/.test(name) ? 'Hinterfußwurzel · Quellbezeichnung'
            : /fabellae/.test(name) ? 'Sesambein · Quellbezeichnung' : 'Skelettfläche · Quellbezeichnung';
    }
    return {...entry,anatomical,label,side,kind:'skeleton',status:'model',sources:[SOURCE],
        description:'Einzeln darstellbare Oberfläche des Ausgangsmodells. Einige Teile enthalten mehrere Knochen; Quellnummern sind keine geprüfte anatomische Zehennummerierung. Diese Ansicht dient der Orientierung, ohne Knochenbefund-Erfassung.'};
}

export function dogAtlas(definitions) {
    return [
        ...definitions.map(def => ({...def,kind:def.kind || 'muscle',status:'model',sources:[SOURCE],
            description:def.kind === 'fascia' ? 'Benannte Gewebefläche aus dem Ausgangsmodell; keine vollständige Rekonstruktion aller regionalen Faszienschichten.'
                : 'Benannte Oberfläche aus dem Ausgangsmodell. Im Modell öffnen, freistellen und einen Schmerzbefund erfassen.'})),
        ...DOG_BONES.map(boneDefinition), ...FASCIA_REFERENCE,
        {id:'atlas_skin',anatomical:'Cutis',label:'Haut / Außenansicht',kind:'skin',side:'whole',status:'layer',layer:'skin',sources:[SOURCE],description:'Texturierte Körperoberfläche. Epidermis, Dermis, Unterhaut und Hautanhangsgebilde sind nicht einzeln segmentiert.'},
        {id:'atlas_fur',anatomical:'Kurzhaar-Fell',label:'Kosmetische Darstellung',kind:'fur',side:'whole',status:'layer',layer:'fur',sources:[SOURCE],description:'Prozedurale kurze Haare auf der Hautoberfläche; keine anatomische Darstellung von Haarfollikeln.'},
    ];
}

export function validateSkeletonAtlas(scene) {
    const expected = new Set(DOG_BONES.map(d => d.id)), found = new Set();
    scene.traverse(mesh => {
        if (!mesh.isMesh) return;
        const id = mesh.userData.atlasId;
        if (!expected.has(id) || found.has(id) || !mesh.geometry?.getAttribute('position')?.count) {
            throw new Error('Skelettatlas enthält eine ungültige Oberflächenzuordnung');
        }
        found.add(id);
    });
    if (found.size !== expected.size) throw new Error('Skelettatlas enthält nicht alle Quellstrukturen');
}

export class AnatomyAtlas {
    constructor(container, onSelect) {
        this.container = container;
        this.onSelect = onSelect;
        const panel = document.createElement('section');
        panel.id = 'a3d-atlas'; panel.hidden = true;
        panel.setAttribute('role','dialog'); panel.setAttribute('aria-modal','true'); panel.setAttribute('aria-labelledby','a3d-atlas-title');
        panel.innerHTML = `
          <div class="a3d-atlas-header"><div><h2 id="a3d-atlas-title">Anatomie-Atlas · Hund</h2><p>Modellbestand und Faszienreferenzen</p></div><button type="button" id="a3d-atlas-close" aria-label="Atlas schließen">✕</button></div>
          <p class="a3d-atlas-note">Kein vollständiger Anatomie-Atlas: fehlende Faszien sind als „3D-Fläche fehlt“ markiert. Links und rechts gelten aus Sicht des Tieres.</p>
          <div class="a3d-atlas-filters">
            <input id="a3d-atlas-search" type="search" aria-label="Im Atlas suchen" placeholder="Name oder Quellbezeichnung suchen…">
            <select id="a3d-atlas-kind" aria-label="Atlas Gewebe"><option value="">Alle Gewebe</option>${Object.entries(KIND).map(([k,v])=>`<option value="${k}">${v}</option>`).join('')}</select>
            <select id="a3d-atlas-side" aria-label="Atlas Körperseite"><option value="">Alle Seiten</option>${Object.entries(SIDE).map(([k,v])=>`<option value="${k}">${v}</option>`).join('')}</select>
            <select id="a3d-atlas-status" aria-label="Atlas Verfügbarkeit"><option value="">Gesamter Katalog</option><option value="available">Im Modell vorhanden</option><option value="missing">3D-Fläche fehlt</option></select>
            <button type="button" id="a3d-atlas-reset">Filter löschen</button>
          </div>
          <p id="a3d-atlas-count" role="status"></p>
          <div class="a3d-atlas-columns"><div id="a3d-atlas-results" aria-label="Atlas Treffer"></div><div id="a3d-atlas-detail" tabindex="-1"></div></div>
          <style>
            #a3d-atlas:not([hidden]){display:flex;flex-direction:column;position:absolute;inset:8px;z-index:40;background:#101a2b;border:1px solid #475569;border-radius:12px;padding:16px;color:#e2e8f0;overflow:auto;font-size:14px;box-shadow:0 12px 45px #0009;box-sizing:border-box}
            #a3d-atlas [hidden]{display:none!important}#a3d-atlas h2{font-size:20px;margin:0}#a3d-atlas p{line-height:1.5;margin:6px 0 10px}
            .a3d-atlas-header{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}.a3d-atlas-header p,.a3d-atlas-note{color:#a8b7cc}
            .a3d-atlas-filters{display:flex;flex-wrap:wrap;gap:8px}.a3d-atlas-filters input{flex:1 1 240px;min-width:0}
            #a3d-atlas input,#a3d-atlas select,#a3d-atlas button{font:inherit;background:#1e293b;color:#e2e8f0;border:1px solid #475569;border-radius:7px;padding:9px;min-height:40px;box-sizing:border-box}
            #a3d-atlas button{cursor:pointer}#a3d-atlas button:disabled{opacity:.55;cursor:default}#a3d-atlas :focus-visible{outline:2px solid #93c5fd;outline-offset:2px}
            .a3d-atlas-columns{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;min-height:0;flex:1}.a3d-atlas-columns>div{overflow:auto;overflow-wrap:anywhere;min-width:0}
            #a3d-atlas-results button{display:block;text-align:left;width:100%;margin-bottom:6px}#a3d-atlas-results button[aria-pressed=true]{border-color:#60a5fa;background:#233d60}
            #a3d-atlas small{display:block;color:#a8b7cc;margin-top:4px}#a3d-atlas-detail{padding:12px;background:#162236;border-radius:9px;box-sizing:border-box}#a3d-atlas-detail h3{font-size:18px;margin:0 0 8px}#a3d-atlas a{color:#93c5fd}#a3d-atlas-open{background:#1d4ed8!important;margin:12px 0}
            @media(max-width:600px){#a3d-atlas:not([hidden]){inset:4px;padding:10px;font-size:13px}.a3d-atlas-columns{display:flex;flex-direction:column;overflow:auto}.a3d-atlas-columns>div{overflow:visible;flex-shrink:0}#a3d-atlas-results{max-height:32vh;overflow:auto}.a3d-atlas-filters select{flex:1;min-width:120px;max-width:100%}#a3d-atlas h2{font-size:18px}}
          </style>`;
        container.append(panel); this.panel = panel;
        this.q = selector => panel.querySelector(selector);
        this.q('#a3d-atlas-close').addEventListener('click',()=>this.close());
        this.q('#a3d-atlas-search').addEventListener('input',()=>this.render());
        panel.querySelectorAll('select').forEach(s=>s.addEventListener('change',()=>this.render()));
        this.q('#a3d-atlas-reset').addEventListener('click',()=>{panel.querySelectorAll('input,select').forEach(e=>e.value='');this.render();});
        this.q('#a3d-atlas-results').addEventListener('click',event=>{
            const button=event.target.closest('[data-atlas-id]');if(!button)return;
            this.selected=button.dataset.atlasId;this.renderDetail();
        });
        panel.addEventListener('keydown',event=>{
            if(event.key==='Escape'){event.preventDefault();event.stopPropagation();this.close();}
            if(event.key==='Tab'){
                const focusable=[...panel.querySelectorAll('button:not(:disabled),input,select,a[href]')].filter(e=>e.getClientRects().length);
                const first=focusable[0],last=focusable.at(-1);
                if(event.shiftKey && (document.activeElement===first || !focusable.includes(document.activeElement))){event.preventDefault();last?.focus();}
                else if(!event.shiftKey && document.activeElement===last){event.preventDefault();first?.focus();}
            }
        });
    }
    open(definitions) {
        this.entries=dogAtlas(definitions);this.panel.hidden=false;this.previousFocus=document.activeElement;
        // Keep keyboard and pointer interaction inside the dialog, including on mobile.
        this.inertSiblings=[...this.container.children].filter(e=>e!==this.panel && !e.inert);
        this.inertSiblings.forEach(e=>e.inert=true);
        this.render();this.q('#a3d-atlas-search').focus();
    }
    close() {
        this.panel.hidden=true;this.inertSiblings?.forEach(e=>e.inert=false);this.inertSiblings=[];
        if(this.previousFocus?.isConnected)this.previousFocus.focus();
    }
    render() {
        const terms=normalized(this.q('#a3d-atlas-search').value).split(/\s+/).filter(Boolean);
        const kind=this.q('#a3d-atlas-kind').value,side=this.q('#a3d-atlas-side').value,status=this.q('#a3d-atlas-status').value;
        this.matches=this.entries.filter(e=>(!kind||e.kind===kind)&&(!side||e.side===side)&&(!status||(status==='missing'?e.status==='missing':e.status!=='missing'))&&terms.every(t=>normalized(`${e.anatomical} ${e.label} ${e.sourceName || ''}`).includes(t)));
        const muscles=this.entries.filter(e=>e.kind==='muscle').length;
        const tissues=this.entries.filter(e=>['fascia','tendon'].includes(e.kind)&&e.status==='model').length;
        this.q('#a3d-atlas-count').textContent=`${this.matches.length} Treffer · ${muscles} Muskelstrukturen · ${tissues} Gewebeflächen · ${DOG_BONES.length} Skelettteile (keine Knochenzählung)`;
        this.q('#a3d-atlas-results').innerHTML=this.matches.length?this.matches.map(e=>`<button type="button" data-atlas-id="${esc(e.id)}"><strong>${esc(e.anatomical)}</strong><small>${esc(KIND[e.kind])} · ${esc(SIDE[e.side])} · ${e.status==='missing'?'3D-Fläche fehlt':'Im Modell vorhanden'}</small></button>`).join(''):'<p>Keine Treffer. Suchbegriff oder Filter ändern.</p>';
        if(!this.matches.some(e=>e.id===this.selected))this.selected=this.matches[0]?.id;
        this.renderDetail();
    }
    renderDetail() {
        const entry=this.matches.find(e=>e.id===this.selected),detail=this.q('#a3d-atlas-detail');
        this.panel.querySelectorAll('[data-atlas-id]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.atlasId===this.selected)));
        if(!entry){detail.textContent='Wähle eine Struktur aus dem Atlas.';return;}
        detail.innerHTML=`<h3>${esc(entry.anatomical)}</h3><p>${esc(entry.label)} · ${esc(SIDE[entry.side])}</p><p>${esc(entry.description)}</p>${entry.sourceName?`<small>Quelle: ${esc(entry.sourceName)}</small>`:''}<button type="button" id="a3d-atlas-open" ${entry.status==='missing'?'disabled':''}>${entry.status==='missing'?'Keine 3D-Fläche verfügbar':entry.kind==='skeleton'?'Skelettteil ansehen':entry.layer?'Schicht anzeigen':'Struktur und Schmerzbefund öffnen'}</button><p>Quellen</p>${entry.sources.map(s=>`<p><a href="${esc(s.url)}" target="_blank" rel="noopener noreferrer">${esc(s.label)}</a></p>`).join('')}`;
        this.q('#a3d-atlas-open').addEventListener('click',()=>{this.close();this.onSelect(entry);});
    }
}
