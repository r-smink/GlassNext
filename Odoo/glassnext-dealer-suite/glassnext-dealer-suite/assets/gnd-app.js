"use strict";
(function(){
const ROOT=document.getElementById("gnd-app");
if(!ROOT)return;

const CFG=window.GND_CONFIG||{},O=CFG.options||{};
const $=s=>ROOT.querySelector(s),$$=s=>[...ROOT.querySelectorAll(s)];
const EPS=.0001;
const num=v=>Number(String(v??"").trim().replace(",","."));
const fmtN=(n,d=2)=>new Intl.NumberFormat("nl-NL",{minimumFractionDigits:d,maximumFractionDigits:d}).format(Number(n)||0);
const fmtMoney=n=>new Intl.NumberFormat("nl-NL",{style:"currency",currency:"EUR"}).format(Number(n)||0);
const esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]));
const colorFor=id=>{let h=0;for(const c of String(id))h=(h*31+c.charCodeAt(0))>>>0;return `hsl(${h%360} 70% 78%)`};

const SET={
  rollW:num(O.gn_roll_width)||1520, rollL:num(O.gn_roll_length)||60000,
  margin:Math.max(0,num(O.gn_margin_side)||0), kerf:Math.max(0,num(O.gn_kerf)||0),
  quality:parseInt(O.gn_quality,10)||140, rotateDefault:String(O.gn_rotate_default??"1")==="0"?"0":"1",
  matPrice:num(O.gn_material_price)||0, cutPrice:num(O.gn_cut_price)||0,
  matDisc:num(O.gn_material_discount)||0, mountDisc:num(O.gn_mount_discount)||0, vat:num(O.gn_vat_rate)||0,
  mountBasis:O.gn_mount_area_basis==="gross"?"gross":"net",
  mountRates:{easy:num(O.gn_mount_rate_easy)||35,average:num(O.gn_mount_rate_average)||45,complex:num(O.gn_mount_rate_complex)||60,very:num(O.gn_mount_rate_very)||75},
  mountClass:O.gn_mount_class||"average",
  otherCosts:Array.isArray(O.gn_other_costs)?O.gn_other_costs:(O.gn_other_costs&&typeof O.gn_other_costs==="object"?Object.values(O.gn_other_costs):[])
};
const CLASS_TEXT={easy:"Eenvoudig",average:"Gemiddeld",complex:"Complex",very:"Zeer complex"};

let lastPlan=null,lastCalc=null,importFile=null,busy=false,submitted=false;

/* ---------------- Kleine helpers ---------------- */
let toastTimer=null;
function toast(msg,ms=2200){const t=$("#gndToast");t.textContent=msg;t.classList.remove("gnd-hidden");clearTimeout(toastTimer);toastTimer=setTimeout(()=>t.classList.add("gnd-hidden"),ms)}
function setStatus(el,cls,text,html=false){el.className="gnd-status"+(cls?" gnd-"+cls:"");if(html)el.innerHTML=text;else el.textContent=text}
const store={
  key:"gnd_draft_"+(CFG.user?.id||0),
  get(){try{return JSON.parse(localStorage.getItem(this.key)||"null")}catch(e){return null}},
  set(v){try{localStorage.setItem(this.key,JSON.stringify(v))}catch(e){}},
  clear(){try{localStorage.removeItem(this.key)}catch(e){}}
};

/* ---------------- Ruitmaten ---------------- */
const paneBody=$("#gndPaneTable tbody");
function addPaneRow(p={},opts={}){
  const tr=document.createElement("tr");
  const rot=String(p.rot??SET.rotateDefault);
  tr.innerHTML=`<td class="c-id"><span class="gnd-cl">Kenmerk</span><input class="pid" value="${esc(p.id??"")}" placeholder="R${paneBody.children.length+1}" aria-label="Kenmerk"></td>
    <td class="c-w"><span class="gnd-cl">Breedte cm</span><input class="pw" inputmode="decimal" value="${esc(p.w??"")}" aria-label="Breedte cm"></td>
    <td class="c-h"><span class="gnd-cl">Hoogte cm</span><input class="ph" inputmode="decimal" value="${esc(p.h??"")}" aria-label="Hoogte cm"></td>
    <td class="c-n"><span class="gnd-cl">Aantal</span><input class="pn" inputmode="numeric" value="${esc(p.n??1)}" aria-label="Aantal"></td>
    <td class="c-rot"><span class="gnd-cl">Rotatie</span><select class="prot" aria-label="Rotatie"><option value="1"${rot==="1"?" selected":""}>Ja</option><option value="0"${rot==="0"?" selected":""}>Nee</option></select></td>
    <td class="c-room"><span class="gnd-cl">Ruimte / verdieping</span><input class="proom" list="gndRoomList" value="${esc(p.room??"")}" aria-label="Ruimte / verdieping"></td>
    <td class="c-del"><button type="button" class="gnd-btn gnd-btn-danger gnd-x remove" aria-label="Verwijder ruit">✕</button></td>`;
  tr.querySelector(".remove").onclick=()=>{
    const id=tr.querySelector(".pid").value||"ruit";
    if((tr.querySelector(".pw").value||tr.querySelector(".ph").value)&&!confirm(`${id} verwijderen?`))return;
    tr.remove();invalidate();saveDraft();
  };
  paneBody.appendChild(tr);
  if(opts.flash){tr.classList.add("gnd-new");setTimeout(()=>tr.classList.remove("gnd-new"),1300)}
  return tr;
}
function readPanes(){
  return [...paneBody.querySelectorAll("tr")].map(tr=>({
    id:tr.querySelector(".pid").value.trim(),w:tr.querySelector(".pw").value.trim(),h:tr.querySelector(".ph").value.trim(),
    n:tr.querySelector(".pn").value.trim(),rot:tr.querySelector(".prot").value,room:tr.querySelector(".proom").value.trim()
  })).filter(p=>p.id||p.w||p.h||p.room);
}
function paneTotals(){
  const panes=readPanes();let count=0,area=0;
  panes.forEach(p=>{const n=parseInt(p.n,10)||1,w=num(p.w),h=num(p.h);count+=n;if(w>0&&h>0)area+=n*w*h/1e4});
  return{lines:panes.length,count,area};
}
function updatePaneSummary(){
  const t=paneTotals();
  $("#gndPaneSummary").textContent=t.lines?`${t.lines} ${t.lines===1?"regel":"regels"} · ${t.count} ${t.count===1?"ruit":"ruiten"} · ${fmtN(t.area)} m² netto glas`:"";
  $("#gndPaneCount").textContent=t.count;
  $("#gndPaneEmpty").classList.toggle("gnd-hidden",paneBody.children.length>0);
  const rooms=[...new Set(readPanes().map(p=>p.room).filter(Boolean))];
  $("#gndRoomList").innerHTML=rooms.map(r=>`<option value="${esc(r)}">`).join("");
  $("#gndQId").placeholder=nextId();
}
function nextId(){
  let max=0;readPanes().forEach(p=>{const m=/^R(\d+)$/i.exec(p.id);if(m)max=Math.max(max,+m[1])});
  return "R"+Math.max(max+1,readPanes().length+1);
}
function invalidate(){
  lastPlan=null;lastCalc=null;
  $("#gndPlanArea").classList.add("gnd-hidden");$("#gndCalcCard").classList.add("gnd-hidden");
  $("#gndPlanMetrics").innerHTML="";$("#gndProgress").style.width="0%";
  setStatus($("#gndPlanStatus"),"","Het snijplan wordt berekend zodra je deze stap opent.");
  updatePaneSummary();
}
paneBody.addEventListener("input",invalidate);paneBody.addEventListener("change",invalidate);
$("#gndAddPane").onclick=()=>{const tr=addPaneRow({},{flash:true});updatePaneSummary();tr.querySelector(".pw").focus()};

/* ---------------- Snelinvoer ---------------- */
const Q={id:$("#gndQId"),w:$("#gndQW"),h:$("#gndQH"),n:$("#gndQN"),rot:$("#gndQRot"),room:$("#gndQRoom")};
Q.rot.value=SET.rotateDefault;
ROOT.querySelectorAll("[data-q-step]").forEach(b=>b.onclick=()=>{Q.n.value=Math.max(1,(parseInt(Q.n.value,10)||1)+Number(b.dataset.qStep))});
Q.w.addEventListener("keydown",e=>{if(e.key==="Enter"){e.preventDefault();Q.h.focus()}});
Q.id.addEventListener("keydown",e=>{if(e.key==="Enter"){e.preventDefault();Q.w.focus()}});
$("#gndQuick").addEventListener("submit",e=>{
  e.preventDefault();
  const st=$("#gndQuickStatus"),w=num(Q.w.value),h=num(Q.h.value),n=parseInt(Q.n.value,10);
  [Q.w,Q.h,Q.n].forEach(x=>x.classList.remove("gnd-invalid"));
  if(!(w>0)){Q.w.classList.add("gnd-invalid");Q.w.focus();return setStatus(st,"err","Vul een breedte in (cm).")}
  if(!(h>0)){Q.h.classList.add("gnd-invalid");Q.h.focus();return setStatus(st,"err","Vul een hoogte in (cm).")}
  if(!(n>=1)){Q.n.classList.add("gnd-invalid");Q.n.focus();return setStatus(st,"err","Aantal moet minimaal 1 zijn.")}
  if(w>1000||h>1000){if(!confirm(`${fmtN(w,1)} × ${fmtN(h,1)} cm is erg groot. Klopt het dat dit centimeters zijn?`))return}
  const id=Q.id.value.trim()||nextId();
  addPaneRow({id,w:+w.toFixed(2),h:+h.toFixed(2),n,rot:Q.rot.value,room:Q.room.value.trim()},{flash:true});
  invalidate();saveDraft();
  setStatus(st,"ok",`${id} toegevoegd: ${fmtN(w,1)} × ${fmtN(h,1)} cm${n>1?` (${n}×)`:""}.`);
  Q.id.value="";Q.w.value="";Q.h.value="";Q.n.value="1"; // ruimte en rotatie blijven staan voor de volgende ruit
  updatePaneSummary();Q.w.focus();
});

/* ---------------- Import (zelfde format als de lokale GlassNext Suite) ---------------- */
const PANE_HEADERS=["Kenmerk","Breedte cm","Hoogte cm","Aantal","Rotatie","Ruimte / verdieping"];
const TEMPLATE_ROWS=[["R1","88","68","4","Ja","Woonkamer"],["R2","140","200","2","Nee","Entree"]];
function parseCSV(text){
  text=String(text).replace(/^﻿/,"");
  const first=text.split(/\r?\n/).find(x=>x.trim())||"";
  const delimiter=[";",",","\t"].sort((a,b)=>first.split(b).length-first.split(a).length)[0];
  const rows=[],row=[];let field="",quoted=false;
  for(let i=0;i<text.length;i++){
    const c=text[i];
    if(c==='"'){if(quoted&&text[i+1]==='"'){field+='"';i++}else quoted=!quoted}
    else if(c===delimiter&&!quoted){row.push(field);field=""}
    else if((c==="\n"||c==="\r")&&!quoted){if(c==="\r"&&text[i+1]==="\n")i++;row.push(field);if(row.some(x=>String(x).trim()))rows.push(row.slice());row.length=0;field=""}
    else field+=c;
  }
  if(quoted)throw Error("Niet-afgesloten aanhalingsteken in CSV.");
  row.push(field);if(row.some(x=>String(x).trim()))rows.push(row);
  return rows;
}
const normHeader=v=>String(v??"").trim().toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g,"").replace(/[^a-z0-9]/g,"");
function parseRows(rows){
  // Zoek de kopregel in de eerste 10 rijen (sommige dealers zetten er een titel boven).
  const aliases=[["kenmerk","ruit","ruitnummer","id","nr","nummer"],["breedtecm","breedte","breedtemm"],["hoogtecm","hoogte","hoogtemm"],["aantal","stuks","st"],["rotatie","draaien"],["ruimteverdieping","ruimte","verdieping","omschrijving","locatie"]];
  let hIdx=-1,indexes=[];
  for(let r=0;r<Math.min(10,rows.length);r++){
    const norm=(rows[r]||[]).map(normHeader);
    const idx=aliases.map(g=>norm.findIndex(h=>g.includes(h)));
    if(idx[1]>=0&&idx[2]>=0){hIdx=r;indexes=idx;break}
  }
  if(hIdx<0)throw Error("Kopregel niet gevonden. Gebruik het sjabloon met de kolommen: "+PANE_HEADERS.join(", ")+".");
  const header=(rows[hIdx]||[]).map(normHeader);
  const mm=[1,2].map(j=>header[indexes[j]]?.endsWith("mm"));
  const parsed=[],errors=[];
  rows.slice(hIdx+1).forEach((cells,i)=>{
    if(!cells.some(v=>String(v??"").trim()))return;
    const get=j=>indexes[j]<0?"":String(cells[indexes[j]]??"").trim();
    const line=hIdx+i+2;
    let w=num(get(1)),h=num(get(2));
    if(mm[0])w=w/10;if(mm[1])h=h/10;
    const nRaw=get(3),n=nRaw===""?1:Number(nRaw.replace(",","."));
    const rawRot=normHeader(get(4));
    const rot=rawRot===""?SET.rotateDefault:(["ja","j","yes","y","true","1","x"].includes(rawRot)?"1":(["nee","n","no","false","0"].includes(rawRot)?"0":null));
    if(!Number.isFinite(w)||w<=0||!Number.isFinite(h)||h<=0||!Number.isInteger(n)||n<1||rot===null){errors.push(`Regel ${line}: controleer breedte, hoogte, aantal en rotatie.`);return}
    parsed.push({id:get(0)||`R${parsed.length+1}`,w:+w.toFixed(2),h:+h.toFixed(2),n,rot,room:get(5)});
  });
  if(errors.length)throw Error(errors.slice(0,8).join("\n")+(errors.length>8?`\n… en ${errors.length-8} andere fouten.`:""));
  if(!parsed.length)throw Error("Geen geldige ruitmaten gevonden.");
  return parsed;
}
async function importPaneFile(file){
  if(!file)return;
  const st=$("#gndImportStatus");setStatus(st,"",`Bestand controleren: ${file.name}`);
  try{
    if(file.size>5*1024*1024)throw Error("Bestand is groter dan 5 MB.");
    const ext=file.name.split(".").pop().toLowerCase();let rows;
    if(ext==="csv")rows=parseCSV(await file.text());
    else if(ext==="xlsx"){
      if(!window.XLSX)throw Error("Excel-import is niet beschikbaar (bibliotheek niet geladen). Gebruik CSV of herlaad de app.");
      const wb=XLSX.read(await file.arrayBuffer(),{type:"array"});
      rows=XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]],{header:1,defval:"",raw:false});
    }else throw Error("Kies een CSV- of XLSX-bestand.");
    const panes=parseRows(rows);
    const hasData=readPanes().some(p=>p.w||p.h);
    if(hasData&&!confirm(`${panes.length} ruitmaatregels gevonden in ${file.name}.\n\nDe huidige ruitmaten vervangen door deze import?`)){
      setStatus(st,"","Import geannuleerd. Bestaande ruitmaten zijn behouden.");return;
    }
    paneBody.innerHTML="";panes.forEach(p=>addPaneRow(p));importFile=file;invalidate();saveDraft();
    $("#gndImportBox").open=false;
    const total=panes.reduce((s,p)=>s+p.n,0);
    setStatus(st,"ok",`${panes.length} regels (${total} ruiten) geïmporteerd uit ${file.name}. Controleer de maten hieronder.`);
  }catch(err){setStatus(st,"err",`Import niet uitgevoerd: ${err.message}`)}
}
const drop=$("#gndDrop"),fileInput=$("#gndImportFile");
drop.onclick=()=>fileInput.click();
drop.onkeydown=e=>{if(e.key==="Enter"||e.key===" "){e.preventDefault();fileInput.click()}};
["dragenter","dragover"].forEach(t=>drop.addEventListener(t,e=>{e.preventDefault();drop.classList.add("over")}));
["dragleave","drop"].forEach(t=>drop.addEventListener(t,e=>{e.preventDefault();drop.classList.remove("over")}));
drop.addEventListener("drop",e=>importPaneFile(e.dataTransfer.files[0]));
fileInput.onchange=e=>{const f=e.target.files[0];e.target.value="";importPaneFile(f)};

function downloadBlob(blob,name){const a=document.createElement("a");a.href=URL.createObjectURL(blob);a.download=name;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(a.href),1000)}
$("#gndTemplateCsv").onclick=()=>{
  const csv="﻿"+[PANE_HEADERS,...TEMPLATE_ROWS].map(r=>r.map(x=>'"'+String(x).replace(/"/g,'""')+'"').join(";")).join("\r\n");
  downloadBlob(new Blob([csv],{type:"text/csv;charset=utf-8"}),"GlassNext_ruitmaten_sjabloon.csv");
};
$("#gndTemplateXlsx").onclick=()=>{
  if(!window.XLSX)return alert("Excel-bibliotheek niet geladen. Gebruik het CSV-sjabloon.");
  const ws=XLSX.utils.aoa_to_sheet([PANE_HEADERS,...TEMPLATE_ROWS.map(r=>[r[0],+r[1],+r[2],+r[3],r[4],r[5]])]);
  ws["!cols"]=PANE_HEADERS.map(()=>({wch:21}));
  const wb=XLSX.utils.book_new();XLSX.utils.book_append_sheet(wb,ws,"Ruitmaten");
  XLSX.writeFile(wb,"GlassNext_ruitmaten_sjabloon.xlsx");
};

/* ---------------- Snijplanner (identiek aan de GlassNext Suite) ---------------- */
function readItems(){
  const rollW=SET.rollW,margin=SET.margin,items=[],errors=[],splitNotices=[];
  const fits=(w,h,r)=>w<=rollW+EPS||(r&&h<=rollW+EPS);
  readPanes().forEach((p,row)=>{
    const id=p.id||`R${row+1}`,netW=num(p.w)*10,netH=num(p.h)*10,count=Math.max(1,parseInt(p.n||"1",10)||1),canRotate=p.rot==="1",room=p.room;
    if(!(netW>0)||!(netH>0)){errors.push(`${id}: vul een geldige breedte en hoogte in.`);return}
    const fullW=netW+2*margin,fullH=netH+2*margin;
    if(fits(fullW,fullH,canRotate)){
      for(let i=1;i<=count;i++)items.push({baseId:id,copy:i,label:count>1?`${id}-${i}`:id,room,netW,netH,w:fullW,h:fullH,canRotate,margin,split:false});
      return;
    }
    const alts=[
      {axis:"width",partNetW:netW/2,partNetH:netH,grossW:netW/2+margin,grossH:netH+2*margin,seamA:"rechts",seamB:"links"},
      {axis:"height",partNetW:netW,partNetH:netH/2,grossW:netW+2*margin,grossH:netH/2+margin,seamA:"onder",seamB:"boven"}
    ].filter(a=>fits(a.grossW,a.grossH,canRotate));
    if(!alts.length){errors.push(`${id}: past niet uit één stuk en ook niet als twee gelijke delen op de rol.`);return}
    alts.sort((a,b)=>(canRotate?Math.max(a.grossW,a.grossH):a.grossH)-(canRotate?Math.max(b.grossW,b.grossH):b.grossH));
    const s=alts[0];
    splitNotices.push(`${id}: ${count} ${count===1?"raam":"ramen"} verdeeld in Deel A en Deel B (naadzijde zonder snijrand).`);
    for(let i=1;i<=count;i++){
      const pl=count>1?`${id}-${i}`:id;
      ["A","B"].forEach(part=>items.push({baseId:id,copy:i,label:`${pl} - Deel ${part}`,room,netW:s.partNetW,netH:s.partNetH,w:s.grossW,h:s.grossH,canRotate,margin,split:true,splitPart:part,splitAxis:s.axis,seamSide:part==="A"?s.seamA:s.seamB}));
    }
  });
  if(!items.length&&!errors.length)errors.push("Voer minimaal één geldige ruit in.");
  return{items,errors,splitNotices};
}
function intersects(a,b,g){return !(a.x+a.w+g<=b.x+EPS||b.x+b.w+g<=a.x+EPS||a.y+a.h+g<=b.y+EPS||b.y+b.h+g<=a.y+EPS)}
function candidates(placed,rollW,g){
  const xs=new Set([0]),ys=new Set([0]);placed.forEach(p=>{xs.add(p.x+p.w+g);ys.add(p.y+p.h+g)});
  const arr=[];for(const y of ys)for(const x of xs)if(x<=rollW+EPS)arr.push({x,y});
  return arr.sort((a,b)=>a.y-b.y||a.x-b.x);
}
function lexLess(a,b){for(let i=0;i<a.length;i++){if(a[i]<b[i])return true;if(a[i]>b[i])return false}return false}
const frame=()=>new Promise(requestAnimationFrame);
async function placeSequence(seq,rollW,kerf){
  const placed=[];let maxY=0,work=0,lastYield=performance.now();
  for(const item of seq){
    let best=null;
    const oris=[{w:item.w,h:item.h,rotated:false}];
    if(item.canRotate&&Math.abs(item.w-item.h)>EPS)oris.push({w:item.h,h:item.w,rotated:true});
    const pts=candidates(placed,rollW,kerf);
    for(const o of oris){
      if(o.w>rollW+EPS)continue;
      for(const pt of pts){
        if(pt.x+o.w>rollW+EPS)continue;
        const t={x:pt.x,y:pt.y,w:o.w,h:o.h};
        if(placed.some(p=>intersects(t,p,kerf)))continue;
        const score=[Math.max(maxY,t.y+t.h),t.y,t.x,o.rotated?1:0];
        if(!best||lexLess(score,best.score))best={...t,rotated:o.rotated,item,score};
        if((++work&8191)===0&&performance.now()-lastYield>120){await frame();lastYield=performance.now()}
      }
    }
    if(!best)return null;
    placed.push(best);maxY=Math.max(maxY,best.y+best.h);
    if(performance.now()-lastYield>120){await frame();lastYield=performance.now()}
  }
  return{placed,totalLen:maxY};
}
function rng(seed){return function(){let t=seed+=0x6D2B79F5;t=Math.imul(t^t>>>15,t|1);t^=t+Math.imul(t^t>>>7,t|61);return((t^t>>>14)>>>0)/4294967296}}
function shuffle(a,r){const b=[...a];for(let i=b.length-1;i>0;i--){const j=Math.floor(r()*(i+1));[b[i],b[j]]=[b[j],b[i]]}return b}
function orders(items,n){
  const out=[],sorts=[
    (a,b)=>b.w*b.h-a.w*a.h,(a,b)=>Math.max(b.w,b.h)-Math.max(a.w,a.h)||b.w*b.h-a.w*a.h,
    (a,b)=>b.h-a.h||b.w-a.w,(a,b)=>b.w-a.w||b.h-a.h,
    (a,b)=>Math.min(b.w,b.h)-Math.min(a.w,a.h)||b.w*b.h-a.w*a.h,(a,b)=>(b.w+b.h)-(a.w+a.h)
  ];sorts.forEach(f=>out.push([...items].sort(f)));
  const r=rng(items.length*7919+Math.round(items.reduce((s,x)=>s+x.w+x.h,0)));
  while(out.length<n){let o=shuffle(items,r);const m=out.length%4;
    if(m===0)o.sort((a,b)=>(b.w*b.h-a.w*a.h)+(r()-.5)*Math.max(a.w*a.h,b.w*b.h)*.15);
    if(m===1)o.sort((a,b)=>(Math.max(b.w,b.h)-Math.max(a.w,a.h))+(r()-.5)*100);
    if(m===2)o.sort((a,b)=>(b.h-a.h)+(r()-.5)*100);out.push(o)}
  return out;
}
async function optimize(items){
  let best=null;const all=orders(items,SET.quality);
  for(let i=0;i<all.length;i++){
    $("#gndProgress").style.width=`${Math.round((i+1)/all.length*100)}%`;
    $("#gndPlanStatus").textContent=`Optimaliseren… proef ${i+1} van ${all.length}`;
    const res=await placeSequence(all[i],SET.rollW,SET.kerf);
    if(res&&(!best||res.totalLen<best.totalLen-EPS))best=res;
    if((i&7)===7)await frame();
  }
  return best;
}
function planStats(res){
  const net=res.placed.reduce((s,p)=>s+p.item.netW*p.item.netH,0),gross=res.placed.reduce((s,p)=>s+p.w*p.h,0),roll=SET.rollW*res.totalLen,waste=Math.max(0,roll-gross);
  return{netArea:net/1e6,grossArea:gross/1e6,rollArea:roll/1e6,wasteArea:waste/1e6,eff:roll?gross/roll*100:0,loss:roll?waste/roll*100:0,remaining:(SET.rollL-res.totalLen)/1000};
}
function drawPlan(res){
  const c=$("#gndCanvas"),ctx=c.getContext("2d"),rollW=SET.rollW;
  const scale=Math.min(1000/rollW,(28000-30)/Math.max(res.totalLen,1));
  c.width=Math.max(1,Math.ceil(rollW*scale));c.height=Math.max(200,Math.ceil(res.totalLen*scale)+30);
  ctx.fillStyle="#fff";ctx.fillRect(0,0,c.width,c.height);
  ctx.strokeStyle="#102535";ctx.lineWidth=2;ctx.strokeRect(1,1,rollW*scale-2,res.totalLen*scale-2);
  ctx.font="12px Segoe UI, Arial";ctx.textBaseline="top";
  res.placed.forEach(p=>{const x=p.x*scale,y=p.y*scale,w=p.w*scale,h=p.h*scale;ctx.fillStyle=colorFor(p.item.baseId);ctx.fillRect(x,y,w,h);ctx.strokeStyle="#344d60";ctx.lineWidth=1;ctx.strokeRect(x,y,w,h);ctx.fillStyle="#102535";if(w>42&&h>17)ctx.fillText(`${p.item.label}${p.rotated?" ↻":""}`,x+4,y+4);if(w>90&&h>35)ctx.fillText(`${Math.round(p.w)}×${Math.round(p.h)} mm`,x+4,y+20)});
}
async function calculatePlan(){
  if(busy)return false;
  const {items,errors,splitNotices}=readItems();
  const st=$("#gndPlanStatus");
  if(errors.length){setStatus(st,"err",errors.map(esc).join("<br>"),true);return false}
  busy=true;$("#gndCalculate").disabled=true;$("#gndSubmit").disabled=true;$("#gndNext").disabled=true;
  try{
    const res=await optimize(items);
    if(!res){setStatus(st,"err","Geen geldige indeling gevonden. Controleer de maten.");return false}
    const stats=planStats(res);
    lastPlan={...res,stats,rollW:SET.rollW};
    drawPlan(res);
    const warn=res.totalLen>SET.rollL+EPS?" Let op: de benodigde lengte is langer dan één rol.":"";
    setStatus(st,warn?"warn":"ok",`Snijplan gereed.${esc(warn)}`+(splitNotices.length?`<br><b>Tweedelige ruiten:</b> ${splitNotices.map(esc).join(" ")}`:""),true);
    $("#gndPlanMetrics").innerHTML=[
      ["Aantal stukken",res.placed.length],["Netto glas",fmtN(stats.netArea)+" m²"],["Rollengte",fmtN(res.totalLen/1000)+" m"],
      ["Rolverbruik",fmtN(stats.rollArea)+" m²"],["Snijverlies",`${fmtN(stats.wasteArea)} m² (${fmtN(stats.loss,1)}%)`],["Snijrendement",fmtN(stats.eff,1)+"%"]
    ].map(([l,v])=>`<div class="gnd-metric">${l}<strong>${v}</strong></div>`).join("");
    const rows=[...res.placed].sort((a,b)=>a.y-b.y||a.x-b.x).map((p,i)=>`<tr><td>${i+1}</td><td>${esc(p.item.label)}</td><td>${esc(p.item.room)}</td><td>${Math.round(p.item.netW)} × ${Math.round(p.item.netH)}</td><td>${Math.round(p.w)} × ${Math.round(p.h)}</td><td>${p.rotated?"Ja":"Nee"}</td></tr>`).join("");
    $("#gndPieces").innerHTML=`<table class="gnd-table"><thead><tr><th>#</th><th>Stuk</th><th>Ruimte</th><th>Netto mm</th><th>Bruto mm</th><th>Gedraaid</th></tr></thead><tbody>${rows}</tbody></table>`;
    $("#gndPlanArea").classList.remove("gnd-hidden");$("#gndCalcCard").classList.remove("gnd-hidden");
    calculatePrices();
    return true;
  }finally{busy=false;$("#gndCalculate").disabled=false;$("#gndSubmit").disabled=!navigator.onLine||submitted;$("#gndNext").disabled=false}
}
$("#gndCalculate").onclick=calculatePlan;

/* ---------------- Calculatie ---------------- */
function addCostRow(c={}){
  const row=document.createElement("div");row.className="gnd-cost-row";
  row.innerHTML=`<input class="oc-desc" placeholder="Omschrijving" value="${esc(c.description||"")}" aria-label="Omschrijving kostenpost"><input class="oc-amount" inputmode="decimal" placeholder="€ excl." value="${c.amount?esc(c.amount):""}" aria-label="Bedrag excl. btw"><button type="button" class="gnd-btn gnd-btn-danger gnd-x" aria-label="Verwijder kostenpost">✕</button>`;
  row.querySelector("button").onclick=()=>{row.remove();calculatePrices();saveDraft()};
  row.addEventListener("input",calculatePrices);
  $("#gndOtherCosts").appendChild(row);
}
function readOtherCosts(){
  return $$("#gndOtherCosts .gnd-cost-row").map(r=>({description:r.querySelector(".oc-desc").value.trim(),amount:Math.max(0,num(r.querySelector(".oc-amount").value)||0)})).filter(x=>x.description&&x.amount>0);
}
function rawOtherCosts(){return $$("#gndOtherCosts .gnd-cost-row").map(r=>({description:r.querySelector(".oc-desc").value,amount:r.querySelector(".oc-amount").value}))}
$("#gndAddCost").onclick=()=>{addCostRow();$$("#gndOtherCosts .oc-desc").pop()?.focus()};
function selfInstall(){return $("#gndInstallMode").value==="self"}
function calculatePrices(){
  $(".gnd-mount-only").classList.toggle("gnd-hidden",selfInstall());
  if(!lastPlan)return null;
  const s=lastPlan.stats,pieces=lastPlan.placed.length,cls=$("#gndMountClass").value,rate=SET.mountRates[cls]||0;
  const matGross=s.rollArea*SET.matPrice,matDisc=matGross*SET.matDisc/100,cut=pieces*SET.cutPrice;
  const mountArea=SET.mountBasis==="gross"?s.grossArea:s.netArea;
  const mountGross=selfInstall()?0:mountArea*rate,mountDisc=mountGross*SET.mountDisc/100;
  const oc=readOtherCosts(),other=oc.reduce((t,x)=>t+x.amount,0);
  const subtotal=matGross-matDisc+cut+mountGross-mountDisc+other,vat=subtotal*SET.vat/100,total=subtotal+vat;
  lastCalc={matGross,matDisc,cut,mountGross,mountDisc,other,subtotal,vat,total};
  const r=(a,b)=>`<tr><td>${a}</td><td>${b}</td></tr>`;
  $("#gndCalcRows").innerHTML=
    r(`GlassShield ${fmtN(s.rollArea)} m² à ${fmtMoney(SET.matPrice)}`,fmtMoney(matGross))+
    (matDisc?r(`Materiaalkorting ${fmtN(SET.matDisc,1)}%`,"- "+fmtMoney(matDisc)):"")+
    r(`Voorsnijden ${pieces} st. à ${fmtMoney(SET.cutPrice)}`,fmtMoney(cut))+
    (selfInstall()?"":r(`Montage ${CLASS_TEXT[cls]} ${fmtN(mountArea)} m² à ${fmtMoney(rate)}`,fmtMoney(mountGross))+(mountDisc?r(`Montagekorting ${fmtN(SET.mountDisc,1)}%`,"- "+fmtMoney(mountDisc)):"")+r("Voorrijkosten","volgens Odoo"))+
    oc.map(x=>r(esc(x.description),fmtMoney(x.amount))).join("")+
    `<tr class="gnd-total"><td>Totaal excl. btw</td><td>${fmtMoney(subtotal)}</td></tr>`+
    r(`${fmtN(SET.vat,1)}% btw`,fmtMoney(vat))+
    `<tr class="gnd-grand"><td>Totaal incl. btw</td><td>${fmtMoney(total)}</td></tr>`;
  return lastCalc;
}
$("#gndInstallMode").addEventListener("change",calculatePrices);
$("#gndMountClass").addEventListener("change",calculatePrices);

/* ---------------- Snijplan-PDF (overzicht, A4) ---------------- */
function planPdfDataUri(customer){
  if(!lastPlan||!window.jspdf?.jsPDF)return null;
  const {jsPDF}=window.jspdf,doc=new jsPDF({unit:"mm",format:"a4"}),r=lastPlan,left=15,top=28,drawW=180,drawH=245,scale=drawW/r.rollW,seg=drawH/scale,pages=Math.max(1,Math.ceil(r.totalLen/seg));
  for(let page=0;page<pages;page++){
    if(page)doc.addPage();
    const y0=page*seg,y1=Math.min(r.totalLen,(page+1)*seg);
    doc.setFontSize(14);doc.text(`GlassNext snijplan – ${customer}`,left,12);
    doc.setFontSize(9);doc.text(`Pagina ${page+1}/${pages} | segment ${fmtN(y0/1000)}–${fmtN(y1/1000)} m | rolbreedte ${r.rollW} mm`,left,19);
    doc.rect(left,top,drawW,(y1-y0)*scale);
    r.placed.forEach(p=>{const py0=Math.max(p.y,y0),py1=Math.min(p.y+p.h,y1);if(py1<=py0)return;const x=left+p.x*scale,y=top+(py0-y0)*scale,w=p.w*scale,h=(py1-py0)*scale;doc.setFillColor(225,235,245);doc.rect(x,y,w,h,"FD");
      if(p.y>=y0&&p.y<y1&&w>12&&h>5){doc.setFontSize(6.5);const lines=[`${p.item.label}${p.rotated?" R":""}`,`Netto ${fmtN(p.item.netW/10,1)} × ${fmtN(p.item.netH/10,1)} cm`,`Bruto ${fmtN(p.item.w/10,1)} × ${fmtN(p.item.h/10,1)} cm`];if(p.item.split)lines.push(`Naadzijde: ${p.item.seamSide} (geen snijrand)`);doc.text(lines,x+1.2,y+3.2,{maxWidth:Math.max(1,w-2.4),lineHeightFactor:1.05})}});
    doc.setFontSize(8);doc.text(`Rollengte ${fmtN(r.totalLen/1000)} m | roloppervlak ${fmtN(r.stats.rollArea)} m² | snijverlies ${fmtN(r.stats.wasteArea)} m² (${fmtN(r.stats.loss,1)}%)`,left,285);
  }
  return doc.output("datauristring");
}

/* ---------------- AJAX met automatische nonce-verversing ---------------- */
class SessionError extends Error{}
async function refreshNonce(){
  const fd=new FormData();fd.append("action","gnd_refresh_nonce");
  const r=await fetch(CFG.ajaxUrl,{method:"POST",body:fd,credentials:"same-origin"});
  const j=await r.json().catch(()=>null);
  if(j?.success&&j.data?.nonce){CFG.nonce=j.data.nonce;return true}
  throw new SessionError(j?.data?.message||"Je sessie is verlopen. Log opnieuw in.");
}
/** build(fd) vult de FormData; wordt bij een nieuwe nonce opnieuw aangeroepen. */
async function ajax(action,build,retried=false){
  const fd=new FormData();fd.append("action",action);fd.append("nonce",CFG.nonce);if(build)build(fd);
  const r=await fetch(CFG.ajaxUrl,{method:"POST",body:fd,credentials:"same-origin"});
  const text=await r.text();
  if(!retried&&(text.trim()==="-1"||text.trim()==="0"||r.status===403&&!/^\s*\{/.test(text))){
    await refreshNonce();return ajax(action,build,true);
  }
  let j;try{j=JSON.parse(text)}catch(e){throw Error(`Onverwacht antwoord van de server (HTTP ${r.status}).`)}
  return j;
}
function sessionExpired(err){
  if(!(err instanceof SessionError))return false;
  saveDraft();
  if(confirm(err.message+"\n\nJe invoer is op dit toestel bewaard. Nu opnieuw inloggen?"))location.href=CFG.appUrl||location.href;
  return true;
}

/* ---------------- Klant ---------------- */
const F=["CustomerName","ContactName","Email","Phone","Address","City","Reference","Description","Notes"];
function customer(){return Object.fromEntries(F.map(k=>[k[0].toLowerCase()+k.slice(1),$("#gnd"+k).value.trim()]))}
ROOT.addEventListener("input",e=>e.target.classList?.remove("gnd-invalid"));
function validateCustomer(){
  const c=customer();
  for(const id of ["gndCustomerName","gndEmail"])$("#"+id).classList.remove("gnd-invalid");
  if(!c.customerName)return{id:"gndCustomerName",msg:"Vul de klant / organisatie in."};
  if(c.email&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(c.email))return{id:"gndEmail",msg:"Vul een geldig e-mailadres in of laat het leeg."};
  return null;
}

/* ---------------- Stappen ---------------- */
let step=1;
const STEP_TITLES={1:"Klant en project",2:"Ruitmaten",3:"Snijplan en calculatie",4:"Controleren en versturen"};
async function setStep(n,{scroll=true}={}){
  n=Math.min(4,Math.max(1,n));
  // Vooruit springen mag alleen als de tussenliggende stappen kloppen.
  if(n>step){
    for(let s=step;s<n;s++){if(!(await canLeave(s))){n=s;break}}
  }
  step=n;ROOT.dataset.step=String(n);
  $$(".gnd-steps [data-goto]").forEach(b=>{const s=+b.dataset.goto;b.classList.toggle("is-active",s===n);b.classList.toggle("is-done",s<n)});
  $("#gndTopTitle").textContent=STEP_TITLES[n];
  $("#gndNext").textContent=n===3?"Controleren":"Volgende";
  if(scroll)scrollTop();
  if(n===3&&!lastPlan&&readPanes().length)calculatePlan();
  if(n===4)renderSummary();
  saveDraft();
}
async function canLeave(s){
  if(s===1){const e=validateCustomer();if(e){step=1;ROOT.dataset.step="1";const el=$("#"+e.id);el.classList.add("gnd-invalid");el.focus();toast(e.msg,3000);return false}}
  if(s===2){const {errors}=readItems();if(errors.length){setStatus($("#gndImportStatus"),"err",errors.join("\n"));step=2;ROOT.dataset.step="2";toast(errors[0],3500);return false}}
  if(s===3){if(!lastPlan&&!(await calculatePlan())){toast("Het snijplan kon niet worden berekend.",3000);return false}}
  return true;
}
function scrollTop(){
  if(CFG.mode==="app")window.scrollTo({top:0,behavior:"smooth"});
  else ROOT.scrollIntoView({behavior:"smooth",block:"start"});
}
$("#gndNext").onclick=()=>setStep(step+1);
$("#gndPrev").onclick=()=>setStep(step-1);
$$(".gnd-steps [data-goto]").forEach(b=>b.onclick=()=>setStep(+b.dataset.goto));

function renderSummary(){
  const c=customer(),t=paneTotals();
  if(lastPlan)calculatePrices();
  const row=(a,b,cls="")=>`<div class="gnd-sum-row ${cls}"><span>${a}</span><span>${b}</span></div>`;
  $("#gndSummary").innerHTML=
    row("Klant",esc(c.customerName||"–"))+
    (c.reference?row("Referentie",esc(c.reference)):"")+
    row("Aanbrengen",selfInstall()?"Zelf (alleen levering)":`Door GlassNext · ${CLASS_TEXT[$("#gndMountClass").value]||""}`)+
    row("Ruiten",`${t.count} st. · ${fmtN(t.area)} m² netto`)+
    (lastPlan?row("Rolverbruik",`${fmtN(lastPlan.stats.rollArea)} m² · ${lastPlan.placed.length} stukken`):"")+
    (lastCalc?row("Indicatief totaal excl. btw",fmtMoney(lastCalc.subtotal),"gnd-sum-total"):"");
}

/* ---------------- Versturen ---------------- */
async function submit(){
  const st=$("#gndSubmitStatus");setStatus(st,"","");
  if(submitted)return;
  if(!navigator.onLine){setStatus(st,"warn","Geen verbinding. Je invoer is bewaard; verstuur zodra je weer online bent.");return}
  const e=validateCustomer();if(e){await setStep(1);return}
  if(!lastPlan){setStatus(st,"","Snijplan wordt eerst berekend…");if(!(await calculatePlan())){setStatus(st,"err","Het snijplan kon niet worden berekend. Controleer de ruitmaten.");return}}
  calculatePrices();
  const btn=$("#gndSubmit");btn.disabled=true;const orig=btn.textContent;btn.textContent="Versturen…";
  setStatus(st,"","Offerte wordt opgeslagen en naar Odoo verstuurd…");
  const c=customer();
  const payload={
    customer:c,installMode:$("#gndInstallMode").value,mountClass:$("#gndMountClass").value,
    otherCosts:readOtherCosts(),panes:readPanes(),importFileName:importFile?.name||"",
    calc:lastCalc?{subtotal:lastCalc.subtotal,vat:lastCalc.vat,total:lastCalc.total,matGross:lastCalc.matGross,cut:lastCalc.cut,other:lastCalc.other}:{},
    plan:{rollW:lastPlan.rollW,totalLen:lastPlan.totalLen,stats:lastPlan.stats,placed:lastPlan.placed.map(p=>({
      label:p.item.label,baseId:p.item.baseId,copy:p.item.copy,room:p.item.room,x:p.x,y:p.y,w:p.w,h:p.h,
      netW:p.item.netW,netH:p.item.netH,rotated:p.rotated,margin:p.item.margin,seamSide:p.item.seamSide||""}))}
  };
  let pdf=null;try{pdf=planPdfDataUri(c.customerName)}catch(err){console.warn("PDF mislukt",err)}
  let ok=false;
  try{
    const resp=await ajax("gnd_submit_quote",fd=>{
      fd.append("project_json",JSON.stringify(payload));
      if(importFile)fd.append("import_file",importFile,importFile.name);
      if(pdf)fd.append("plan_pdf",pdf);
    });
    if(!resp.success)throw Error(resp.data?.message||"Onbekende fout.");
    ok=true;submitted=true;store.clear();
    const d=resp.data,o=d.odoo||{};
    if(o.success){
      setStatus(st,"",`<div class="gnd-result ok">Offerte <b>${esc(d.offerNumber)}</b> is aangemaakt${o.orderName?` in Odoo als <b>${esc(o.orderName)}</b>`:""}.`+(o.url?` <a href="${esc(o.url)}" target="_blank" rel="noopener">Openen in Odoo</a>`:"")+`</div>`,true);
    }else{
      setStatus(st,"",`<div class="gnd-result warn">Offerte <b>${esc(d.offerNumber)}</b> is opgeslagen, maar het doorzetten naar Odoo is mislukt: ${esc(o.error||"onbekende fout")}. Je kunt het opnieuw proberen bij <b>Offertes</b>.</div>`,true);
    }
    btn.classList.add("gnd-hidden");$("#gndDone").classList.remove("gnd-hidden");
    $(".gnd-actionbar").classList.add("gnd-hidden");
    listLoaded=false;
  }catch(err){
    if(!sessionExpired(err))setStatus(st,"err",`Versturen mislukt: ${err.message}`);
  }finally{if(!ok){btn.disabled=!navigator.onLine;btn.textContent=orig}}
}
$("#gndSubmit").onclick=submit;

function resetForm(){
  F.forEach(k=>$("#gnd"+k).value="");
  $("#gndInstallMode").value="professional";$("#gndMountClass").value=CLASS_TEXT[SET.mountClass]?SET.mountClass:"average";
  paneBody.innerHTML="";importFile=null;submitted=false;
  $("#gndOtherCosts").innerHTML="";initCosts();
  ["#gndImportStatus","#gndSubmitStatus","#gndQuickStatus"].forEach(s=>setStatus($(s),"",""));
  $("#gndSubmit").classList.remove("gnd-hidden");$("#gndSubmit").disabled=!navigator.onLine;$("#gndDone").classList.add("gnd-hidden");
  $(".gnd-actionbar").classList.remove("gnd-hidden");
  Q.id.value="";Q.w.value="";Q.h.value="";Q.n.value="1";Q.room.value="";Q.rot.value=SET.rotateDefault;
  invalidate();calculatePrices();store.clear();setStep(1);
}
$("#gndReset").onclick=()=>{if(confirm("Nieuwe offerte starten? De huidige invoer wordt gewist."))resetForm()};
$("#gndNewAfterSubmit").onclick=resetForm;

/* ---------------- Concept automatisch bewaren ---------------- */
let saveTimer=null;
function draftState(){
  return{v:1,savedAt:Date.now(),step,customer:customer(),installMode:$("#gndInstallMode").value,mountClass:$("#gndMountClass").value,
    panes:readPanes(),costs:rawOtherCosts(),quick:{room:Q.room.value,rot:Q.rot.value},importFileName:importFile?.name||""};
}
function saveDraft(){
  if(submitted)return;
  clearTimeout(saveTimer);
  saveTimer=setTimeout(()=>{
    const s=draftState();
    const empty=!s.panes.length&&!Object.values(s.customer).some(Boolean);
    if(empty)store.clear();else store.set(s);
  },300);
}
ROOT.addEventListener("input",e=>{if(e.target.closest("#gndQuick"))return;saveDraft()});
ROOT.addEventListener("change",e=>{if(e.target.closest("#gndQuick")&&e.target!==Q.room&&e.target!==Q.rot)return;saveDraft()});
function restoreDraft(){
  const d=store.get();
  if(!d||d.v!==1)return false;
  const c=d.customer||{};
  F.forEach(k=>{const key=k[0].toLowerCase()+k.slice(1);$("#gnd"+k).value=c[key]||""});
  if(d.installMode)$("#gndInstallMode").value=d.installMode;
  if(d.mountClass&&CLASS_TEXT[d.mountClass])$("#gndMountClass").value=d.mountClass;
  paneBody.innerHTML="";(d.panes||[]).forEach(p=>addPaneRow(p));
  $("#gndOtherCosts").innerHTML="";(d.costs||[]).forEach(x=>addCostRow({description:x.description,amount:x.amount}));
  if(d.quick){Q.room.value=d.quick.room||"";if(d.quick.rot)Q.rot.value=d.quick.rot}
  if(d.importFileName)setStatus($("#gndImportStatus"),"",`Ruitmaten uit ${d.importFileName} zijn hersteld (het bestand zelf wordt niet als bijlage meegestuurd).`);
  invalidate();
  const when=new Date(d.savedAt||Date.now());
  toast(`Concept van ${when.toLocaleDateString("nl-NL")} ${when.toLocaleTimeString("nl-NL",{hour:"2-digit",minute:"2-digit"})} hersteld`,3000);
  return Math.min(Math.max(1,d.step||1),2); // nooit direct naar snijplan/versturen
}

/* ---------------- Offertes ---------------- */
let listLoaded=false;
async function loadList(){
  const box=$("#gndList");
  box.innerHTML='<span class="gnd-note">Laden…</span>';
  try{
    const resp=await ajax("gnd_list_quotes");
    if(!resp.success)throw Error(resp.data?.message||"Laden mislukt");
    listLoaded=true;
    const {rows,isAdmin}=resp.data;
    if(!rows.length){box.innerHTML='<div class="gnd-empty">Nog geen offertes verstuurd.</div>';return}
    box.innerHTML=rows.map(r=>{
      const name=esc(r.odooName||"#"+r.odooId);
      const odoo=r.odooId&&!r.odooError?`<span class="gnd-badge ok">✓ In Odoo${r.odooName?" · "+name:""}</span>${r.odooUrl?`<a href="${esc(r.odooUrl)}" target="_blank" rel="noopener">Openen</a>`:""}`
        :r.odooId?`<span class="gnd-badge warn" title="${esc(r.odooError)}">⚠ ${name} onvolledig</span>`
        :r.odooError?`<span class="gnd-badge err" title="${esc(r.odooError)}">✗ Niet in Odoo</span><button type="button" class="gnd-btn gnd-btn-light gnd-small" data-retry="${esc(r.postId)}">Opnieuw versturen</button>`
        :`<span class="gnd-badge none">Wordt verwerkt</span>`;
      return `<article class="gnd-q">
        <div class="gnd-q-top"><span class="gnd-q-nr">${esc(r.offer)}</span><span class="gnd-q-date">${esc(r.date)}</span></div>
        <div class="gnd-q-cust">${esc(r.customer)}</div>
        <div class="gnd-q-meta">${[r.reference,isAdmin?r.dealer:"",`${r.pieces} stuks · ${fmtN(r.rollArea)} m² rol`].filter(Boolean).map(esc).join(" · ")}</div>
        <div class="gnd-q-odoo">${odoo}</div>
      </article>`}).join("");
  }catch(err){if(!sessionExpired(err))box.innerHTML=`<div class="gnd-empty gnd-err">${esc(err.message)}</div>`}
}
$("#gndList").addEventListener("click",async e=>{
  const b=e.target.closest("[data-retry]");if(!b)return;
  b.disabled=true;b.textContent="Bezig…";
  try{
    const resp=await ajax("gnd_retry_odoo",fd=>fd.append("post_id",b.dataset.retry));
    if(!resp?.success)alert(resp?.data?.message||"Opnieuw versturen mislukt.");
    else if(!resp.data.odoo.success)alert("Odoo gaf opnieuw een fout: "+resp.data.odoo.error);
    else toast("Offerte staat nu in Odoo");
  }catch(err){if(!sessionExpired(err))alert(err.message)}
  loadList();
});
$("#gndRefreshList").onclick=loadList;

/* ---------------- Tabs (met hash, zodat de terugknop van Android werkt) ---------------- */
const TAB_HASH={new:"",list:"#offertes",account:"#account"};
const TAB_TITLES={list:"Offertes",account:"Account"};
function setView(v,{push=true}={}){
  if(!TAB_HASH.hasOwnProperty(v))v="new";
  ROOT.dataset.view=v;
  $$("[data-view-pane]").forEach(p=>p.hidden=p.dataset.viewPane!==v);
  $$(".gnd-tab").forEach(t=>t.classList.toggle("is-active",t.dataset.tab===v));
  $("#gndTopTitle").textContent=v==="new"?STEP_TITLES[step]:TAB_TITLES[v];
  if(push&&CFG.mode==="app"){const h=TAB_HASH[v];if(location.hash!==h)history.pushState({gndView:v},"",h||location.pathname+location.search)}
  if(v==="list"&&!listLoaded)loadList();
  scrollTop();
}
ROOT.addEventListener("click",e=>{const t=e.target.closest("[data-tab]");if(t){e.preventDefault();setView(t.dataset.tab)}});
function viewFromHash(){return Object.keys(TAB_HASH).find(k=>TAB_HASH[k]&&TAB_HASH[k]===location.hash)||"new"}
window.addEventListener("popstate",()=>{if(CFG.mode==="app")setView(viewFromHash(),{push:false})});

/* ---------------- Online / offline ---------------- */
function updateOnline(){
  const on=navigator.onLine;
  $("#gndOffline").classList.toggle("gnd-hidden",on);
  if(!submitted&&!busy)$("#gndSubmit").disabled=!on;
}
window.addEventListener("online",()=>{updateOnline();toast("Weer online")});
window.addEventListener("offline",updateOnline);

/* ---------------- Installeren / service worker ---------------- */
const isStandalone=()=>window.matchMedia("(display-mode: standalone)").matches||navigator.standalone===true;
const isIos=/iphone|ipad|ipod/i.test(navigator.userAgent)||(navigator.platform==="MacIntel"&&navigator.maxTouchPoints>1);
let installEvt=null;
function updateInstall(){
  const done=isStandalone();
  $("#gndInstallDone").hidden=!done;
  $("#gndInstallAndroid").hidden=done||!installEvt;
  $("#gndInstallIos").hidden=done||!isIos;
  $("#gndInstallOther").hidden=done||!!installEvt||isIos;
}
window.addEventListener("beforeinstallprompt",e=>{e.preventDefault();installEvt=e;updateInstall()});
window.addEventListener("appinstalled",()=>{installEvt=null;updateInstall();toast("App geïnstalleerd")});
$("#gndInstallBtn").onclick=async()=>{if(!installEvt)return;installEvt.prompt();try{await installEvt.userChoice}catch(e){}installEvt=null;updateInstall()};
if(CFG.swUrl&&"serviceWorker" in navigator){
  navigator.serviceWorker.register(CFG.swUrl,{scope:CFG.scope}).catch(err=>console.warn("Service worker niet geregistreerd",err));
}
$("#gndLogout").addEventListener("click",async e=>{
  // Gecachte app-pagina van deze gebruiker opruimen; het concept blijft per gebruiker bewaard.
  e.preventDefault();const href=e.currentTarget.href;
  try{if(window.caches){const ks=await caches.keys();await Promise.all(ks.filter(k=>k.startsWith("gnd-")).map(k=>caches.delete(k)))}}catch(err){}
  location.href=href;
});

/* ---------------- Init ---------------- */
function initCosts(){SET.otherCosts.filter(x=>x&&(x.description||num(x.amount)>0)).forEach(x=>addCostRow({description:x.description,amount:num(x.amount)}))}
$("#gndMountClass").value=CLASS_TEXT[SET.mountClass]?SET.mountClass:"average";
const restoredStep=restoreDraft();
if(restoredStep===false)initCosts();
updatePaneSummary();calculatePrices();updateOnline();updateInstall();
step=restoredStep||1;ROOT.dataset.step=String(step);
setStep(step,{scroll:false});
if(CFG.mode==="app"){const v=viewFromHash();if(v!=="new")setView(v,{push:false})}
// Exporteer een paar functies voor de testharnas (niet nodig in productie).
window.GND_APP={setStep,setView,calculatePlan,readPanes,importPaneFile,draftState,get step(){return step},get lastPlan(){return lastPlan}};
})();
