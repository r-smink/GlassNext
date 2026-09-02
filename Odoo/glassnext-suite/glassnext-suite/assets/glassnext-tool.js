"use strict";
if(document.getElementById("glassnext-app")){
const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
const EPS=.0001;
const fmtN=(n,d=2)=>new Intl.NumberFormat("nl-NL",{minimumFractionDigits:d,maximumFractionDigits:d}).format(Number(n)||0);
const fmtMoney=n=>new Intl.NumberFormat("nl-NL",{style:"currency",currency:"EUR"}).format(Number(n)||0);
const num=v=>Number(String(v??"").replace(",","."));
const esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]));
const safe=s=>String(s||"GlassNext").trim().replace(/[^a-z0-9_-]+/gi,"_").replace(/^_+|_+$/g,"")||"GlassNext";
const colorFor=id=>{let h=0;for(const c of id)h=(h*31+c.charCodeAt(0))>>>0;return `hsl(${h%360} 70% 78%)`};
let lastPlan=null,lastCalc=null,lastROI=null;
let flowMode=null;
const O=window.GN_CONFIG?.options||{};
const ajaxUrl=window.GN_CONFIG?.ajaxUrl||"";
const gnNonce=window.GN_CONFIG?.nonce||"";

function initConfig(){
  const map={
    rollW:"gn_roll_width",rollL:"gn_roll_length",marginSide:"gn_margin_side",kerf:"gn_kerf",
    quality:"gn_quality",rotateDefault:"gn_rotate_default",
    materialPrice:"gn_material_price",cutPrice:"gn_cut_price",materialDiscount:"gn_material_discount",
    mountDiscount:"gn_mount_discount",vatRate:"gn_vat_rate",mountClass:"gn_mount_class",
    mountSelectedPrice:"gn_mount_selected_price",mountAreaBasis:"gn_mount_area_basis",
    roiUBefore:"gn_roi_u_before",roiUAfter:"gn_roi_u_after",roiHDD:"gn_roi_hdd",
    roiBoilerEff:"gn_roi_boiler_eff",roiGasKwh:"gn_roi_gas_kwh",roiGasSaveM2:"gn_roi_gas_save_m2",
    roiCoolSaveM2:"gn_roi_cool_save_m2",roiBuildingFactor:"gn_roi_building_factor",
    roiEiaNet:"gn_roi_eia_net",roiGasPrice:"gn_roi_gas_price",roiElecPrice:"gn_roi_elec_price",
    roiCO2Price:"gn_roi_co2_price",roiCO2Gas:"gn_roi_co2_gas",roiCO2Elec:"gn_roi_co2_elec",roiYears:"gn_roi_years"
  };
  Object.entries(map).forEach(([elId,optKey])=>{
    const el=$("#"+elId); if(el&&O[optKey]!=null){
      if(el.tagName==="SELECT"){[...el.options].forEach(o=>{o.selected=o.value===String(O[optKey])})}
      else el.value=O[optKey];
    }
  });
  const q=$("#quality"); if(q&&O.gn_quality){[...q.options].forEach(o=>o.selected=o.value===String(O.gn_quality))}
  const rd=$("#rotateDefault"); if(rd&&O.gn_rotate_default){[...rd.options].forEach(o=>o.selected=o.value===String(O.gn_rotate_default))}
  const mc=$("#mountClass"); if(mc&&O.gn_mount_class){[...mc.options].forEach(o=>o.selected=o.value===String(O.gn_mount_class))}
  const mab=$("#mountAreaBasis"); if(mab&&O.gn_mount_area_basis){[...mab.options].forEach(o=>o.selected=o.value===String(O.gn_mount_area_basis))}
  const otherCosts=O.gn_other_costs; if(Array.isArray(otherCosts))setOtherCosts(otherCosts);
  const msp=$("#mountSelectedPrice");
  if(msp&&!num(msp.value)){
    const cls=$("#mountClass")?.value||O.gn_mount_class||"average";
    const rates={easy:O.gn_mount_rate_easy,average:O.gn_mount_rate_average,complex:O.gn_mount_rate_complex,very:O.gn_mount_rate_very};
    msp.value=num(rates[cls])||MOUNT_DEFAULT_RATES[cls]||45;
  }
}

const PAGES=["choice","planner","calc","roi","project","offer","workorder","exports"];
function visiblePages(){return PAGES.filter(p=>{const t=$(`.tab[data-page="${p}"]`);return t&&!t.classList.contains("hidden")})}
function activatePage(name){
  $$(".tab").forEach(b=>b.classList.toggle("active",b.dataset.page===name));
  $$(".page").forEach(p=>p.classList.toggle("active",p.id===`page-${name}`));
  if(name==="roi"){syncROIFromProject();calculateROI()}
  if(name==="offer")renderOffer();
  if(name==="workorder")renderWorkorder();
  if(name==="exports")renderExportSummary();
  renderNavButtons(name);
}
function renderNavButtons(current){
  const vis=visiblePages();
  const idx=vis.indexOf(current);
  const prev=$$("#page-"+current+" .nav-prev")[0];
  const next=$$("#page-"+current+" .nav-next")[0];
  if(prev){
    const prevPage=idx>0?vis[idx-1]:null;
    prev.disabled=!prevPage;
    if(prevPage)prev.dataset.target=prevPage;
  }
  if(next){
    const nextPage=idx>=0&&idx<vis.length-1?vis[idx+1]:null;
    next.disabled=!nextPage;
    if(nextPage)next.dataset.target=nextPage;
    if(current==="offer"&&next.id==="submitOffer"){
      const cb=$("#offerConsent");
      if(cb&&!cb.checked)next.disabled=true;
    }
  }
}
let installModeValue='professional';
function installMode(){return installModeValue}
function setInstallMode(mode){
  installModeValue=mode;
  const el=$('#installModeValue');if(el)el.value=mode;
  if(lastPlan){calculatePrices();if(lastROI)calculateROI()}
  if(typeof renderOffer==="function"&&$("#offerDoc").innerHTML)renderOffer();
}
let uploadedPhotos=[null,null,null];

function setFlowMode(mode){
  flowMode=mode;
  const plannerTab=$('.tab[data-page="planner"]');
  const calcTab=$('.tab[data-page="calc"]');
  const roiTab=$('.tab[data-page="roi"]');
  const projectTab=$('.tab[data-page="project"]');
  const offerTab=$('.tab[data-page="offer"]');
  const measureDates=$('#measureDates');
  const projectSubmit=$('#projectSubmit');
  const submitOfferBtn=$('#submitOffer');

  if(mode==="measure"){
    if(plannerTab)plannerTab.classList.add('hidden');
    if(calcTab)calcTab.classList.add('hidden');
    if(roiTab)roiTab.classList.add('hidden');
    if(projectTab)projectTab.classList.remove('hidden');
    if(offerTab)offerTab.classList.remove('hidden');
    if(measureDates)measureDates.classList.remove('hidden');
    if(projectSubmit){projectSubmit.textContent='Inmeten aanvragen';projectSubmit.id='submitMeasure';}
    $$('.self-flow-only').forEach(el=>el.classList.add('hidden'));
    activatePage('project');
  } else if(mode==="self"){
    if(plannerTab)plannerTab.classList.remove('hidden');
    if(calcTab)calcTab.classList.remove('hidden');
    if(roiTab)roiTab.classList.remove('hidden');
    if(projectTab)projectTab.classList.remove('hidden');
    if(offerTab)offerTab.classList.remove('hidden');
    if(measureDates)measureDates.classList.add('hidden');
    if(projectSubmit){projectSubmit.textContent='Volgende';projectSubmit.id='projectSubmit';}
    $$('.self-flow-only').forEach(el=>el.classList.add('hidden'));
    activatePage('planner');
  }
}

let pendingFlowMode=null;
const choiceSelfBtn=$('#choiceSelf');
const choiceMeasureBtn=$('#choiceMeasure');
if(choiceMeasureBtn)choiceMeasureBtn.onclick=()=>{
  pendingFlowMode='measure';
  choiceMeasureBtn.classList.add('selected');
  if(choiceSelfBtn)choiceSelfBtn.classList.remove('selected');
  showInstallChoice();
};
if(choiceSelfBtn)choiceSelfBtn.onclick=()=>{
  pendingFlowMode='self';
  choiceSelfBtn.classList.add('selected');
  if(choiceMeasureBtn)choiceMeasureBtn.classList.remove('selected');
  showInstallChoice();
};
function showInstallChoice(){
  const installChoice=$('#installChoice');
  const choiceContinue=$('#choiceContinue');
  if(installChoice)installChoice.classList.remove('hidden');
  if(choiceContinue)choiceContinue.classList.add('hidden');
  $$('.install-choice-btn').forEach(b=>b.classList.remove('selected'));
}
const choiceInstallProBtn=$('#choiceInstallPro');
if(choiceInstallProBtn)choiceInstallProBtn.onclick=()=>{
  setInstallChoice('professional');
};
const choiceInstallSelfBtn=$('#choiceInstallSelf');
if(choiceInstallSelfBtn)choiceInstallSelfBtn.onclick=()=>{
  setInstallChoice('self');
};
function setInstallChoice(mode){
  setInstallMode(mode);
  $$('.install-choice-btn').forEach(b=>b.classList.remove('selected'));
  const btn=mode==='professional'?$('#choiceInstallPro'):$('#choiceInstallSelf');
  if(btn)btn.classList.add('selected');
  const choiceContinue=$('#choiceContinue');
  if(choiceContinue)choiceContinue.classList.remove('hidden');
}
const choiceContinueBtn=$('#choiceContinueBtn');
if(choiceContinueBtn)choiceContinueBtn.onclick=()=>{
  if(pendingFlowMode)setFlowMode(pendingFlowMode);
};

function initTooltips(){
  const cols=['kenmerk','breedte','hoogte','aantal','rotatie','ruimte'];
  const tooltips={};
  cols.forEach(col=>{
    const textKey='gn_tooltip_'+col+'_text';
    const imgKey='gn_tooltip_'+col+'_image';
    tooltips[col]={text:O[textKey]||'',img:O[imgKey]||''};
  });
  $$('.tooltip-icon').forEach(icon=>{
    const col=icon.dataset.tooltipCol;
    const data=tooltips[col];
    if(!data||(!data.text&&!data.img))return;
    icon.addEventListener('mouseenter',e=>showTooltip(icon,data));
    icon.addEventListener('mouseleave',()=>hideTooltip());
    icon.addEventListener('click',e=>{e.preventDefault();toggleTooltip(icon,data)});
  });
}
let activeTooltip=null;
function showTooltip(anchor,data){
  hideTooltip();
  const pop=document.createElement('div');
  pop.className='tooltip-popover';
  pop.innerHTML=(data.img?`<img src="${esc(data.img)}" style="max-width:100%;border-radius:6px;margin-bottom:8px">`:"")+(data.text?`<p>${esc(data.text)}</p>`:"");
  document.body.appendChild(pop);
  const rect=anchor.getBoundingClientRect();
  pop.style.position='fixed';
  pop.style.top=(rect.bottom+8)+'px';
  pop.style.left=Math.min(rect.left,window.innerWidth-320)+'px';
  pop.style.zIndex='9999';
  activeTooltip=pop;
}
function toggleTooltip(anchor,data){
  if(activeTooltip)hideTooltip();
  else showTooltip(anchor,data);
}
function hideTooltip(){
  if(activeTooltip){activeTooltip.remove();activeTooltip=null;}
}
$$(".tab").forEach(b=>b.onclick=()=>{return false});
document.addEventListener("click",e=>{
  const btn=e.target.closest(".nav-prev, .nav-next");
  if(!btn||btn.disabled)return;
  if(btn.id==="submitOffer")return;
  if(btn.id==="plannerNext"&&flowMode==="self")return;
  const t=btn.dataset.target;
  if(t)activatePage(t);
});
const consentCb=$("#offerConsent");
if(consentCb){
  consentCb.addEventListener("change",()=>{
    const sb=$("#submitOffer");
    if(sb)sb.disabled=!consentCb.checked;
  });
}

const paneBody=$("#paneTable tbody");
function addPaneRow(p={}){
  const tr=document.createElement("tr");tr.className="pane-row";
  const i=paneBody.children.length+1;
  const rot=p.rot??$("#rotateDefault").value;
  tr.innerHTML=`<td colspan="7">
    <div class="pane-row-grid">
      <div class="pane-cell pane-kenmerk"><label class="pane-label">Kenmerk</label><input class="pid" placeholder="bijv. R1" value="${esc(p.id||``)}"></div>
      <div class="pane-cell pane-aantal"><label class="pane-label">Aantal</label><input class="pn" type="number" min="1" step="1" value="${p.n??1}"></div>
      <div class="pane-cell pane-breedte"><label class="pane-label">Breedte <span>cm</span></label><input class="pw" inputmode="decimal" placeholder="bijv. 88" value="${p.w??""}"></div>
      <div class="pane-cell pane-hoogte"><label class="pane-label">Hoogte <span>cm</span></label><input class="ph" inputmode="decimal" placeholder="bijv. 68" value="${p.h??""}"></div>
      <div class="pane-cell pane-rotatie"><label class="pane-label">Rotatie</label><select class="prot"><option value="1"${String(rot)==="1"?" selected":""}>Ja</option><option value="0"${String(rot)==="0"?" selected":""}>Nee</option></select></div>
      <div class="pane-cell pane-ruimte"><label class="pane-label">Ruimte</label><input class="proom" placeholder="bijv. Woonkamer" value="${esc(p.room||"")}"></div>
      <div class="pane-cell pane-delete"><button class="btn danger small remove">Verwijder</button></div>
    </div>
  </td>`;
  tr.querySelector(".remove").onclick=()=>tr.remove();
  paneBody.appendChild(tr);
}
$("#addPane").onclick=()=>addPaneRow();
$("#demoPanes").onclick=()=>{paneBody.innerHTML="";[
  {id:"R1",w:140,h:200,n:2,room:"Woonkamer"},
  {id:"R2",w:48,h:96,n:6,room:"Entree"},
  {id:"R3",w:88,h:68,n:4,room:"Slaapkamer"}
].forEach(addPaneRow)};
addPaneRow();

function readItems(){
  const margin=Math.max(0,num($("#marginSide").value)||0),items=[],errors=[];
  $$("#paneTable tbody tr").forEach((tr,row)=>{
    const id=tr.querySelector(".pid").value.trim()||`R${row+1}`;
    const netW=(num(tr.querySelector(".pw").value)||0)*10;
    const netH=(num(tr.querySelector(".ph").value)||0)*10;
    const count=Math.max(1,parseInt(tr.querySelector(".pn").value||"1",10));
    const canRotate=tr.querySelector(".prot").value==="1";
    const room=tr.querySelector(".proom").value.trim();
    if(netW<=0||netH<=0){errors.push(`${id}: voer geldige breedte en hoogte in.`);return}
    for(let i=1;i<=count;i++)items.push({baseId:id,copy:i,label:count>1?`${id}-${i}`:id,room,netW,netH,w:netW+2*margin,h:netH+2*margin,canRotate,margin});
  });
  if(!items.length)errors.push("Voer minimaal één geldige ruit in.");
  return{items,errors};
}
function intersects(a,b,g){return !(a.x+a.w+g<=b.x+EPS||b.x+b.w+g<=a.x+EPS||a.y+a.h+g<=b.y+EPS||b.y+b.h+g<=a.y+EPS)}
function candidates(placed,rollW,g){
  const xs=new Set([0]),ys=new Set([0]);placed.forEach(p=>{xs.add(p.x+p.w+g);ys.add(p.y+p.h+g)});
  const arr=[];for(const y of ys)for(const x of xs)if(x<=rollW+EPS)arr.push({x,y});
  return arr.sort((a,b)=>a.y-b.y||a.x-b.x);
}
function lexLess(a,b){for(let i=0;i<a.length;i++){if(a[i]<b[i])return true;if(a[i]>b[i])return false}return false}
function placeSequence(seq,rollW,kerf){
  const placed=[];
  for(const item of seq){
    let best=null;const oris=[{w:item.w,h:item.h,rotated:false}];
    if(item.canRotate&&Math.abs(item.w-item.h)>EPS)oris.push({w:item.h,h:item.w,rotated:true});
    const pts=candidates(placed,rollW,kerf);
    for(const o of oris){
      if(o.w>rollW+EPS)continue;
      for(const pt of pts){
        if(pt.x+o.w>rollW+EPS)continue;
        const t={x:pt.x,y:pt.y,w:o.w,h:o.h};
        if(placed.some(p=>intersects(t,p,kerf)))continue;
        const newLen=Math.max(t.y+t.h,...placed.map(p=>p.y+p.h),0);
        const score=[newLen,t.y,t.x,o.rotated?1:0];
        if(!best||lexLess(score,best.score))best={...t,rotated:o.rotated,item,score};
      }
    }
    if(!best)return null;placed.push(best);
  }
  return{placed,totalLen:Math.max(0,...placed.map(p=>p.y+p.h))};
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
async function optimize(items,rollW,kerf,attempts){
  let best=null,all=orders(items,attempts);
  for(let i=0;i<all.length;i++){
    const res=placeSequence(all[i],rollW,kerf);
    if(res&&(!best||res.totalLen<best.totalLen-EPS))best=res;
    if(i%10===0){$("#progressBar").style.width=`${Math.round((i+1)/all.length*100)}%`;$("#planStatus").textContent=`Optimaliseren… proef ${i+1} van ${all.length}`;await new Promise(r=>setTimeout(r,0))}
  }
  $("#progressBar").style.width="100%";return best;
}
function planStats(res,rollW,rollL){
  const net=res.placed.reduce((s,p)=>s+p.item.netW*p.item.netH,0),gross=res.placed.reduce((s,p)=>s+p.w*p.h,0),roll=rollW*res.totalLen,waste=Math.max(0,roll-gross);
  return{netArea:net/1e6,grossArea:gross/1e6,rollArea:roll/1e6,wasteArea:waste/1e6,eff:roll?gross/roll*100:0,loss:roll?waste/roll*100:0,remaining:(rollL-res.totalLen)/1000};
}
function drawPlan(res,rollW){
  const c=$("#planCanvas"),ctx=c.getContext("2d"),pxW=1000,scale=pxW/rollW,h=Math.max(450,Math.ceil(res.totalLen*scale)+30);
  c.width=pxW;c.height=h;ctx.fillStyle="#fff";ctx.fillRect(0,0,c.width,c.height);ctx.strokeStyle="#102535";ctx.lineWidth=2;ctx.strokeRect(1,1,rollW*scale-2,res.totalLen*scale-2);
  ctx.font="12px Segoe UI";ctx.textBaseline="top";
  res.placed.forEach(p=>{const x=p.x*scale,y=p.y*scale,w=p.w*scale,h=p.h*scale;ctx.fillStyle=colorFor(p.item.baseId);ctx.fillRect(x,y,w,h);ctx.strokeStyle="#344d60";ctx.lineWidth=1;ctx.strokeRect(x,y,w,h);ctx.fillStyle="#102535";if(w>42&&h>17)ctx.fillText(`${p.item.label}${p.rotated?" ↻":""}`,x+4,y+4);if(w>90&&h>35)ctx.fillText(`${Math.round(p.w)}×${Math.round(p.h)} mm`,x+4,y+20)});
}
function renderPlan(res,rollW,rollL,kerf){
  const stats=planStats(res,rollW,rollL),errors=[];
  res.placed.forEach(p=>{if(p.x<0||p.y<0||p.x+p.w>rollW+EPS)errors.push(`${p.item.label} buiten rolbreedte`)});
  for(let i=0;i<res.placed.length;i++)for(let j=i+1;j<res.placed.length;j++)if(intersects(res.placed[i],res.placed[j],kerf))errors.push(`${res.placed[i].item.label} overlapt ${res.placed[j].item.label}`);
  if(res.totalLen>rollL+EPS)errors.push("Benodigde lengte overschrijdt één rol.");
  lastPlan={...res,rollW,rollL,kerf,stats,project:$("#customerName").value};
  drawPlan(res,rollW);
  $("#planStatus").innerHTML=errors.length?`<span class="warn">${esc(errors.join(". "))}</span>`:`<span class="ok">Snijplan gereed en technisch gecontroleerd.</span>`;
  $("#planMetrics").innerHTML=`
    <div class="metric">Aantal stukken<strong>${res.placed.length}</strong></div>
    <div class="metric">Netto glasoppervlak<strong>${fmtN(stats.netArea)} m²</strong></div>
    <div class="metric">Bruto snijstukken<strong>${fmtN(stats.grossArea)} m²</strong></div>
    <div class="metric">Benodigde rollengte<strong>${fmtN(res.totalLen/1000)} m</strong></div>
    <div class="metric">Verbruikt roloppervlak<strong>${fmtN(stats.rollArea)} m²</strong></div>
    <div class="metric">Snijverlies<strong>${fmtN(stats.wasteArea)} m² (${fmtN(stats.loss,1)}%)</strong></div>
    <div class="metric">Snijrendement<strong>${fmtN(stats.eff,1)}%</strong></div>
    <div class="metric">Resterend op rol<strong class="${stats.remaining<0?"warn":"ok"}">${fmtN(stats.remaining)} m</strong></div>`;
  const ids=[...new Set(res.placed.map(p=>p.item.baseId))];
  $("#legend").innerHTML=ids.map(id=>`<span><i class="sw" style="background:${colorFor(id)}"></i> ${esc(id)}</span>`).join("");
  const rows=[...res.placed].sort((a,b)=>a.y-b.y||a.x-b.x).map((p,i)=>`<tr><td>${i+1}</td><td>${esc(p.item.label)}</td><td>${esc(p.item.room)}</td><td>${p.item.netW} × ${p.item.netH}</td><td>${p.w} × ${p.h}</td><td>${Math.round(p.x)}</td><td>${Math.round(p.y)}</td><td>${p.rotated?"Ja":"Nee"}</td></tr>`).join("");
  $("#pieceReport").innerHTML=`<table><thead><tr><th>#</th><th>Stuk</th><th>Ruimte</th><th>Netto mm</th><th>Bruto mm</th><th>X</th><th>Y</th><th>Gedraaid</th></tr></thead><tbody>${rows}</tbody></table>`;
  calculatePrices();
}
$("#calculatePlan").onclick=async()=>{
  const rollW=num($("#rollW").value),rollL=num($("#rollL").value),kerf=Math.max(0,num($("#kerf").value)||0),{items,errors}=readItems();
  if(errors.length)return alert(errors.join("\n"));if(!(rollW>0&&rollL>0))return alert("Voer geldige rolmaten in.");
  const tooWide=items.filter(x=>Math.min(x.w,x.canRotate?x.h:x.w)>rollW+EPS);if(tooWide.length)return alert(`Past niet op de rol: ${tooWide.map(x=>x.label).join(", ")}`);
  $("#calculatePlan").disabled=true;$("#progressBar").style.width="0%";
  try{const res=await optimize(items,rollW,kerf,parseInt($("#quality").value,10));if(!res)return alert("Geen geldige indeling gevonden.");renderPlan(res,rollW,rollL,kerf)}
  finally{$("#calculatePlan").disabled=false}
};

async function calculateAndProceed(){
  const plannerNextBtn=$('#plannerNext');
  if(!plannerNextBtn)return;
  if(lastPlan){activatePage('calc');return;}
  const rollW=num($("#rollW").value),rollL=num($("#rollL").value),kerf=Math.max(0,num($("#kerf").value)||0),{items,errors}=readItems();
  if(errors.length)return alert(errors.join("\n"));
  if(!(rollW>0&&rollL>0))return alert("Voer geldige rolmaten in.");
  const tooWide=items.filter(x=>Math.min(x.w,x.canRotate?x.h:x.w)>rollW+EPS);
  if(tooWide.length)return alert(`Past niet op de rol: ${tooWide.map(x=>x.label).join(", ")}`);
  const origText=plannerNextBtn.textContent;
  plannerNextBtn.disabled=true;plannerNextBtn.textContent='Berekenen…';
  $("#progressBar").style.width="0%";
  try{
    const res=await optimize(items,rollW,kerf,parseInt($("#quality").value,10));
    if(!res){plannerNextBtn.disabled=false;plannerNextBtn.textContent=origText;return alert("Geen geldige indeling gevonden.");}
    renderPlan(res,rollW,rollL,kerf);
    activatePage('calc');
  }finally{
    plannerNextBtn.disabled=false;plannerNextBtn.textContent=origText;
  }
}

const MOUNT_DEFAULT_RATES={easy:35,average:45,complex:60,very:75};
function selectedMountRate(){
  const direct=num($("#mountSelectedPrice").value)||0;
  if(direct>0)return direct;
  const cls=$("#mountClass")?.value||"average";
  const rates={easy:O.gn_mount_rate_easy,average:O.gn_mount_rate_average,complex:O.gn_mount_rate_complex,very:O.gn_mount_rate_very};
  return Math.max(0,num(rates[cls])||MOUNT_DEFAULT_RATES[cls]||0);
}
function mountClassText(){return {easy:"Eenvoudig",average:"Gemiddeld",complex:"Complex",very:"Zeer complex"}[$("#mountClass").value]}

function addOtherCostRow(p={}){
  const row=document.createElement("div");
  row.className="other-cost-row";
  row.innerHTML=`<div><label>Omschrijving</label><input class="other-desc" type="text" value="${esc(p.description||"")}" readonly></div>
    <div><label>Bedrag excl. btw</label><input class="other-amount" type="number" step="0.01" min="0" value="${p.amount??0}" readonly></div>`;
  $("#otherCostRows").appendChild(row);
}
function readOtherCosts(){
  return $$("#otherCostRows .other-cost-row").map(row=>({
    description:row.querySelector(".other-desc").value.trim(),
    amount:Math.max(0,num(row.querySelector(".other-amount").value)||0)
  })).filter(x=>x.description||x.amount>0);
}
function setOtherCosts(costs){
  $("#otherCostRows").innerHTML="";
  const rows=(costs&&costs.length)?costs:[{description:"",amount:0}];
  rows.forEach(addOtherCostRow);
}

function calculatePrices(){
  if(!lastPlan){$("#calcWarning").textContent="Bereken eerst een snijplan.";return null}
  const s=lastPlan.stats,pieces=lastPlan.placed.length,matRate=num($("#materialPrice").value)||0,cutRate=num($("#cutPrice").value)||0,mountRate=selectedMountRate();
  const selfInstall=installMode()==="self";
  const matGross=s.rollArea*matRate,matDisc=matGross*(num($("#materialDiscount").value)||0)/100;
  const cut=pieces*cutRate,mountArea=$("#mountAreaBasis").value==="gross"?s.grossArea:s.netArea;
  const mountGross=selfInstall?0:mountArea*mountRate,mountDisc=selfInstall?0:mountGross*(num($("#mountDiscount").value)||0)/100;
  const otherCosts=readOtherCosts(),other=otherCosts.reduce((sum,x)=>sum+x.amount,0);
  const subtotal=matGross-matDisc+cut+mountGross-mountDisc+other,vat=subtotal*(num($("#vatRate").value)||0)/100,total=subtotal+vat;
  lastCalc={matGross,matDisc,cut,mountArea,mountRate,mountGross,mountDisc,other,otherCosts,subtotal,vat,total,vatRate:num($("#vatRate").value)||0,matRate,cutRate,pieces,selfInstall};
  $("#calcWarning").innerHTML='<span class="ok">Calculatie bijgewerkt op basis van het actuele snijplan.</span>';
  $("#calcMetrics").innerHTML=`<div class="metric">Netto glas<strong>${fmtN(s.netArea)} m²</strong></div><div class="metric">Te factureren materiaal<strong>${fmtN(s.rollArea)} m²</strong></div><div class="metric">Aantal voorgesneden stukken<strong>${pieces}</strong></div><div class="metric">Montageklasse<strong>${mountClassText()}</strong></div>`;
  const otherRows=otherCosts.filter(x=>x.amount>0).map(x=>`<tr><td>${esc(x.description||"Overige kosten")}</td><td>Overige kosten</td><td></td><td>${fmtMoney(x.amount)}</td></tr>`).join("");
  const voorrijdRow=(flowMode==='measure'||!selfInstall)?`<tr><td>Voorrijdkosten</td><td>Nader te berekenen</td><td></td><td>Nader te berekenen</td></tr>`:"";
  $("#calcRows").innerHTML=`
    <tr><td>GlassShield materiaal</td><td>${fmtN(s.rollArea)} m² rolverbruik</td><td>${fmtMoney(matRate)}/m²</td><td>${fmtMoney(matGross)}</td></tr>
    ${matDisc?`<tr><td>Materiaalkorting</td><td>${fmtN(num($("#materialDiscount").value),1)}%</td><td></td><td>- ${fmtMoney(matDisc)}</td></tr>`:""}
    <tr><td>Voorsnijden</td><td>${pieces} stukken</td><td>${fmtMoney(cutRate)}/stuk</td><td>${fmtMoney(cut)}</td></tr>
    ${selfInstall?"":`<tr><td>Montage</td><td>${fmtN(mountArea)} m²</td><td></td><td>Nader te berekenen*</td></tr>`}
    ${mountDisc?`<tr><td>Montagekorting</td><td>${fmtN(num($("#mountDiscount").value),1)}%</td><td></td><td>- ${fmtMoney(mountDisc)}</td></tr>`:""}
    ${otherRows}
    ${voorrijdRow}
    <tr class="total-row"><td colspan="3">Totaal excl. btw</td><td>${fmtMoney(subtotal)}</td></tr>
    <tr><td colspan="3">${fmtN(lastCalc.vatRate,1)}% btw</td><td>${fmtMoney(vat)}</td></tr>
    <tr class="grand-row"><td colspan="3">Totaal incl. btw</td><td>${fmtMoney(total)}</td></tr>
    ${selfInstall?"":`<tr><td colspan="4" style="font-size:11px;color:var(--muted);padding-top:8px">* Nader te berekenen op basis van ingevulde gegevens en aangeleverde foto's.</td></tr>`}`;
  if($("#roiInvestmentSource")&&$("#roiInvestmentSource").value==="calculation")$("#roiInvestment").value=subtotal.toFixed(2);
  return lastCalc;
}
$("#recalculate").onclick=()=>{calculatePrices();calculateROI()};

function projectData(){
  const ids=["offerNumber","customerName","contactName","email","phone","address","city","projectDescription","projectNotes"];
  const data=Object.fromEntries(ids.map(id=>[id,$("#"+id).value]));
  data.flowMode=flowMode;
  data.installMode=installMode();
  if(flowMode==="measure"){
    data.prefDate1=$("#prefDate1")?.value||"";
    data.prefDate2=$("#prefDate2")?.value||"";
    data.prefDate3=$("#prefDate3")?.value||"";
    data.prefDay1=$("#prefDay1")?.value||"";
    data.prefDay2=$("#prefDay2")?.value||"";
    data.prefDay3=$("#prefDay3")?.value||"";
    data.prefTime1=$("#prefTime1")?.value||"";
    data.prefTime2=$("#prefTime2")?.value||"";
    data.prefTime3=$("#prefTime3")?.value||"";
  }
  return data;
}
function companyHeader(){
  return `<div class="doc-head"><div><div class="doc-brand">Glass Next B.V.</div><div>Nano-EcoLine Climate GlassShield</div></div><div style="text-align:right"><b>Isoleren zonder glas te vervangen.</b><br><span class="note">Nanothermische Low-E glas-upgrade</span></div></div>`;
}

const ROI_BTU_PER_U=0.1761101838;
const ROI_PROFILES={
  single:{label:"4 mm enkelglas",uBefore:5.80,uAfter:1.85,improvement:68.1,coolSave:15,gasMethod:"uvalue",note:"TDS-profiel: 4 mm enkelglas, Ug 5,80 → 1,85 W/m²K; verbetering 68,1%."},
  double_pre2008:{label:"Dubbelglas (voor 2008)",uBefore:2.90,uAfter:1.40,improvement:51.7,coolSave:15,gasMethod:"uvalue",note:"TDS-profiel: dubbelglas van vóór 2008, Ug 2,90 → 1,40 W/m²K; verbetering 51,7%."},
  triple_air:{label:"Driedubbelglas (lucht)",uBefore:2.20,uAfter:1.22,improvement:44.5,coolSave:15,gasMethod:"uvalue",note:"TDS-profiel: driedubbelglas met lucht, Ug 2,20 → 1,22 W/m²K; verbetering 44,5%."},
  double_lowe:{label:"Dubbelglas Low-E",uBefore:1.20,uAfter:0.83,improvement:30.8,coolSave:15,gasMethod:"uvalue",note:"TDS-profiel: dubbelglas Low-E, Ug 1,20 → 0,83 W/m²K; verbetering 30,8%."},
  planitherm_one:{label:"Dubbelglas Planitherm One",uBefore:1.10,uAfter:0.78,improvement:28.9,coolSave:15,gasMethod:"uvalue",note:"TDS-profiel: dubbelglas Planitherm One, Ug 1,10 → 0,78 W/m²K; verbetering 28,9%."},
  skn176:{label:"Dubbelglas SKN 176",uBefore:1.10,uAfter:0.78,improvement:28.9,coolSave:15,gasMethod:"uvalue",note:"TDS-profiel: dubbelglas SKN 176, Ug 1,10 → 0,78 W/m²K; verbetering 28,9%."},
  triple_planitherm:{label:"Driedubbelglas met Planitherm One",uBefore:1.00,uAfter:0.73,improvement:27.0,coolSave:15,gasMethod:"uvalue",note:"TDS-profiel: driedubbelglas met Planitherm One, Ug 1,00 → 0,73 W/m²K; verbetering 27,0%."},
  custom:{label:"Eigen technische waarden",uBefore:0,uAfter:0,improvement:0,gasSave:0,coolSave:0,gasMethod:"manual",note:"Vul project-specifieke en technisch onderbouwde waarden in."}
};
function applyROIProfile(){
  const p=ROI_PROFILES[$("#roiGlassType").value]||ROI_PROFILES.custom;
  $("#roiUBefore").value=p.uBefore;
  $("#roiUAfter").value=p.uAfter;
  $("#roiImprovement").value=Number(p.improvement||0).toFixed(1);
  $("#roiCoolSaveM2").value=p.coolSave;
  $("#roiGasMethod").value=p.gasMethod;
  if(p.gasSave!=null)$("#roiGasSaveM2").value=Number(p.gasSave).toFixed(4);
  $("#roiStatus").innerHTML=`<span class="note">${esc(p.note)}</span>`;
  updateROIDerived();calculateROI();
}
function updateROIDerived(){
  const before=num($("#roiUBefore").value)||0,after=num($("#roiUAfter").value)||0,delta=Math.max(0,before-after);
  const improvement=before>0?delta/before*100:0;
  $("#roiUDelta").value=delta.toFixed(4);
  $("#roiImprovement").value=improvement.toFixed(1);
  $("#roiBTUBefore").value=(before*ROI_BTU_PER_U).toFixed(4);
  $("#roiBTUAfter").value=(after*ROI_BTU_PER_U).toFixed(4);
  $("#roiBTUDelta").value=(delta*ROI_BTU_PER_U).toFixed(4);
  if($("#roiGasMethod").value==="uvalue"){
    const hdd=num($("#roiHDD").value)||0,eff=Math.max(.01,(num($("#roiBoilerEff").value)||0)/100),kwh=Math.max(.01,num($("#roiGasKwh").value)||0);
    const gas=(delta*hdd*24/1000)/(kwh*eff);$("#roiGasSaveM2").value=Math.max(0,gas).toFixed(4);
  }
}
function syncROIFromProject(){
  if($("#roiAreaSource").value==="planner"&&lastPlan)$("#roiArea").value=lastPlan.stats.netArea.toFixed(4);
  if($("#roiInvestmentSource").value==="calculation"&&lastCalc)$("#roiInvestment").value=lastCalc.subtotal.toFixed(2);
}
function calculateROI(){
  syncROIFromProject();updateROIDerived();
  const area=Math.max(0,num($("#roiArea").value)||0),investment=Math.max(0,num($("#roiInvestment").value)||0);
  const eiaPct=Math.max(0,num($("#roiEiaNet").value)||0)/100,factor=Math.max(0,num($("#roiBuildingFactor").value)||0)/100;
  const gasM2=Math.max(0,num($("#roiGasSaveM2").value)||0)*factor,coolM2=Math.max(0,num($("#roiCoolSaveM2").value)||0)*factor;
  const gasPrice=Math.max(0,num($("#roiGasPrice").value)||0),elecPrice=Math.max(0,num($("#roiElecPrice").value)||0);
  const co2Gas=Math.max(0,num($("#roiCO2Gas").value)||0),co2Elec=Math.max(0,num($("#roiCO2Elec").value)||0),co2Price=Math.max(0,num($("#roiCO2Price").value)||0);
  const eia=investment*eiaPct,netInvest=investment-eia,gasYear=gasM2*area,coolYear=coolM2*area,gasValue=gasYear*gasPrice,elecValue=coolYear*elecPrice;
  const energyValue=gasValue+elecValue,co2KgM2=gasM2*co2Gas+coolM2*co2Elec,co2Kg=co2KgM2*area,co2Tons=co2Kg/1000,co2Value=co2Tons*co2Price,totalValue=energyValue+co2Value;
  const pbtEnergy=energyValue>0?netInvest/energyValue:NaN,pbtIncl=totalValue>0?netInvest/totalValue:NaN,years=Math.max(1,parseInt($("#roiYears").value||"10",10));
  const cumulative=totalValue*years,roiPct=netInvest>0?(cumulative-netInvest)/netInvest*100:NaN;
  lastROI={glassType:$("#roiGlassType option:checked").textContent,area,uBefore:num($("#roiUBefore").value)||0,uAfter:num($("#roiUAfter").value)||0,uDelta:num($("#roiUDelta").value)||0,
    improvement:num($("#roiImprovement").value)||0,btuBefore:num($("#roiBTUBefore").value)||0,btuAfter:num($("#roiBTUAfter").value)||0,btuDelta:num($("#roiBTUDelta").value)||0,
    gasM2,coolM2,investment,eia,netInvest,gasYear,coolYear,gasValue,elecValue,energyValue,co2KgM2,co2Kg,co2Tons,co2Value,totalValue,pbtEnergy,pbtIncl,years,cumulative,roiPct,factor};
  $("#roiStatus").innerHTML=area>0&&investment>0?'<span class="ok">ROI-berekening bijgewerkt.</span>':'<span class="warn">Vul een glasoppervlak en investering in, of bereken eerst het project.</span>';
  $("#roiMetrics").innerHTML=`
    <div class="metric">Glastype<strong>${esc(lastROI.glassType)}</strong></div>
    <div class="metric">U-/Ug-waarde<strong>${fmtN(lastROI.uBefore,2)} → ${fmtN(lastROI.uAfter,2)} W/m²K</strong></div>
    <div class="metric">TDS-verbetering<strong>${fmtN(lastROI.improvement,1)}%</strong></div>
    <div class="metric">U-factor in BTU<strong>${fmtN(lastROI.btuBefore,4)} → ${fmtN(lastROI.btuAfter,4)}</strong></div>
    <div class="metric">BTU-verbetering<strong>${fmtN(lastROI.btuDelta,4)}</strong></div>
    <div class="metric">Netto investering<strong>${fmtMoney(netInvest)}</strong></div>
    <div class="metric">Gasbesparing<strong>${fmtN(gasYear)} m³/jaar</strong></div>
    <div class="metric">Elektrabesparing<strong>${fmtN(coolYear)} kWh/jaar</strong></div>
    <div class="metric">CO₂-reductie<strong>${fmtN(co2Tons,2)} ton/jaar</strong></div>
    <div class="metric">Totaal voordeel<strong>${fmtMoney(totalValue)}/jaar</strong></div>
    <div class="metric">TVT energie-only<strong>${isFinite(pbtEnergy)?fmtN(pbtEnergy,2)+" jaar":"—"}</strong></div>
    <div class="metric">TVT incl. CO₂<strong>${isFinite(pbtIncl)?fmtN(pbtIncl,2)+" jaar":"—"}</strong></div>
    <div class="metric">ROI na ${years} jaar<strong>${isFinite(roiPct)?fmtN(roiPct,1)+"%":"—"}</strong></div>`;
  $("#roiRows").innerHTML=`
    <tr><td>Gasbesparing</td><td>${fmtN(gasM2,4)} m³</td><td>${fmtN(gasYear)} m³</td><td>${fmtMoney(gasValue)}</td></tr>
    <tr><td>Koel-/elektrabesparing</td><td>${fmtN(coolM2,2)} kWh</td><td>${fmtN(coolYear)} kWh</td><td>${fmtMoney(elecValue)}</td></tr>
    <tr><td>CO₂-reductie</td><td>${fmtN(co2KgM2,2)} kg</td><td>${fmtN(co2Tons,2)} ton</td><td>${fmtMoney(co2Value)}</td></tr>
    <tr class="total-row"><td>Totaal jaarlijks voordeel</td><td></td><td></td><td>${fmtMoney(totalValue)}</td></tr>
    <tr><td>Bruto investering</td><td></td><td></td><td>${fmtMoney(investment)}</td></tr>
    <tr><td>EIA/subsidievoordeel</td><td></td><td></td><td>- ${fmtMoney(eia)}</td></tr>
    <tr class="grand-row"><td>Netto investering</td><td></td><td></td><td>${fmtMoney(netInvest)}</td></tr>`;
  return lastROI;
}
$("#roiGlassType").addEventListener("change",applyROIProfile);
$("#calculateROI").onclick=calculateROI;

function roiOfferHTML(){
  if(!$("#includeROIInOffer").checked||!lastROI)return"";
  const r=lastROI;
  return `<h3>Indicatieve energie- en ROI-analyse</h3>
  <table class="money-table"><tbody>
    <tr><td>Uitgangspunt glastype</td><td>${esc(r.glassType)}</td></tr>
    <tr><td>U-/Ug-waarde volgens TDS</td><td>${fmtN(r.uBefore,2)} → ${fmtN(r.uAfter,2)} W/m²K (${fmtN(r.improvement,1)}% verbetering)</td></tr>
    <tr><td>U-factor in BTU</td><td>${fmtN(r.btuBefore,4)} → ${fmtN(r.btuAfter,4)} BTU/(h·ft²·°F)</td></tr>
    <tr><td>Verwachte gasbesparing</td><td>${fmtN(r.gasYear)} m³/jaar</td></tr>
    <tr><td>Verwachte elektrabesparing</td><td>${fmtN(r.coolYear)} kWh/jaar</td></tr>
    <tr><td>Verwachte CO₂-reductie</td><td>${fmtN(r.co2Tons,2)} ton/jaar</td></tr>
    <tr><td>Indicatief jaarlijks financieel voordeel incl. CO₂</td><td>${fmtMoney(r.totalValue)}</td></tr>
    <tr><td>Indicatieve terugverdientijd incl. CO₂</td><td>${isFinite(r.pbtIncl)?fmtN(r.pbtIncl,2)+" jaar":"—"}</td></tr>
  </tbody></table>
  <p class="note">Deze scenarioanalyse is afhankelijk van de werkelijke glasopbouw, gebouwcondities, installaties, energieprijzen en het gebruik. Aan de uitkomst kunnen geen gegarandeerde besparingen worden ontleend.</p>`;
}
function roiPDF(){
  calculateROI();if(!lastROI)return alert("Maak eerst een ROI-berekening.");if(!requirePDF())return;
  const {jsPDF}=window.jspdf,doc=new jsPDF({unit:"mm",format:"a4"}),p=projectData(),r=lastROI;
  doc.setFont("helvetica","bold");doc.setFontSize(18);doc.text("Glass Next B.V.",15,16);doc.setFontSize(9);doc.setFont("helvetica","normal");doc.text("GlassShield ROI- en besparingsanalyse",15,22);doc.line(15,26,195,26);
  doc.setFontSize(16);doc.text(`Project: ${p.customerName}`,15,38);doc.setFontSize(10);let y=49;
  [`Klant: ${p.customerName}`,`Glastype: ${r.glassType}`,`Glasoppervlak: ${fmtN(r.area)} m²`,`U-/Ug-waarde volgens TDS: ${fmtN(r.uBefore,2)} → ${fmtN(r.uAfter,2)} W/m²K (${fmtN(r.improvement,1)}%)`,`U-factor BTU: ${fmtN(r.btuBefore,4)} → ${fmtN(r.btuAfter,4)} | verschil ${fmtN(r.btuDelta,4)}`].forEach(t=>{doc.text(t,15,y);y+=7});
  y+=4;doc.setFont("helvetica","bold");doc.text("Jaarlijkse besparingen",15,y);doc.setFont("helvetica","normal");y+=8;
  [[`Gas`,`${fmtN(r.gasYear)} m³ | ${fmtMoney(r.gasValue)}`],[`Elektriciteit`,`${fmtN(r.coolYear)} kWh | ${fmtMoney(r.elecValue)}`],[`CO₂`,`${fmtN(r.co2Tons,2)} ton | ${fmtMoney(r.co2Value)}`],[`Totaal voordeel`,fmtMoney(r.totalValue)]].forEach(([a,b])=>{doc.text(a,15,y);doc.text(b,195,y,{align:"right"});y+=7});
  y+=5;doc.setFont("helvetica","bold");doc.text("Investering en terugverdientijd",15,y);doc.setFont("helvetica","normal");y+=8;
  [[`Bruto investering`,fmtMoney(r.investment)],[`EIA/subsidievoordeel`,"- "+fmtMoney(r.eia)],[`Netto investering`,fmtMoney(r.netInvest)],[`TVT energie-only`,isFinite(r.pbtEnergy)?fmtN(r.pbtEnergy,2)+" jaar":"—"],[`TVT incl. CO₂`,isFinite(r.pbtIncl)?fmtN(r.pbtIncl,2)+" jaar":"—"],[`ROI na ${r.years} jaar`,isFinite(r.roiPct)?fmtN(r.roiPct,1)+"%":"—"]].forEach(([a,b])=>{doc.text(a,15,y);doc.text(b,195,y,{align:"right"});y+=7});
  y+=10;doc.setFontSize(8);const note="Deze berekening is een scenarioanalyse. Werkelijke prestaties zijn afhankelijk van onder andere glasopbouw, oriëntatie, klimaat, installaties, bezetting, ventilatie en gebouwgebruik. De uitkomst is geen garantie.";doc.text(doc.splitTextToSize(note,180),15,y);
  doc.save(`${safe(p.customerName)}_ROI.pdf`);
}
$("#roiPDF").onclick=roiPDF;

function renderOffer(){
  if(lastPlan&&!lastCalc)calculatePrices();const p=projectData(),s=lastPlan?.stats,c=lastCalc;
  if(!s||!c){$("#offerDoc").innerHTML=companyHeader()+`<h1>Offerte</h1><div class="summary-box">Bereken eerst het snijplan en de calculatie.</div>`;return}
  $("#offerDoc").innerHTML=companyHeader()+`
    <h1>Offerte ${esc(p.offerNumber||"")}</h1>
    <table><tr><td><b>Aan:</b></td><td>${esc(p.customerName)}</td><td><b>Contact:</b></td><td>${esc(p.contactName)}</td></tr>
    <tr><td><b>E-mail:</b></td><td>${esc(p.email)}</td><td><b>Adres:</b></td><td>${esc([p.address,p.city].filter(Boolean).join(", "))}</td></tr></table>
    <h3>Projectomschrijving</h3><p>${esc(p.projectDescription||`Voor het voorzien van bestaande beglazing van Nano-EcoLine Climate GlassShield.`)}</p>
    <p>Zonwerende én energiebesparende nanothermische glas-upgrade voor bestaande beglazing. Vermindert warmteverlies in de winter, beperkt zonnewarmte in de zomer, verhoogt comfort en blokkeert circa 99% van de UV-straling.</p>
    <h3>Uitgangspunten berekening</h3>
    <ul><li>Totaal aantal ruiten: ${c.pieces} stuks</li><li>Netto glasoppervlak: ${fmtN(s.netArea)} m²</li><li>Benodigd roloppervlak: ${fmtN(s.rollArea)} m²</li><li>Benodigde rollengte bij ${fmtN(lastPlan.rollW/1000,0)} m breedte: ${fmtN(lastPlan.totalLen/1000)} meter</li><li>Snijverlies: ${fmtN(s.wasteArea)} m² (${fmtN(s.loss,1)}%)</li></ul>
    <h3>Investering</h3>
    <table class="money-table"><thead><tr><th>Omschrijving</th><th>Bedrag</th></tr></thead><tbody>
    <tr><td>${fmtN(s.rollArea)} m² Nano-EcoLine Climate GlassShield à ${fmtMoney(c.matRate)}/m²</td><td>${fmtMoney(c.matGross)}</td></tr>
    ${c.matDisc?`<tr><td>Projectkorting materiaal</td><td>- ${fmtMoney(c.matDisc)}</td></tr>`:""}
    <tr><td>Voorsnijden ${c.pieces} stuks à ${fmtMoney(c.cutRate)}</td><td>${fmtMoney(c.cut)}</td></tr>
    ${c.selfInstall?"":`<tr><td>Aanbrengen GlassShield – montageklasse ${mountClassText()} (${fmtN(c.mountArea)} m²)</td><td>Nader te berekenen</td></tr>`}
    ${c.selfInstall?"":(c.mountDisc?`<tr><td>Projectkorting montage</td><td>- ${fmtMoney(c.mountDisc)}</td></tr>`:"")}
    ${(c.otherCosts||[]).filter(x=>x.amount>0).map(x=>`<tr><td>${esc(x.description||"Overige kosten")}</td><td>${fmtMoney(x.amount)}</td></tr>`).join("")}
    ${(flowMode==='measure'||!c.selfInstall)?`<tr><td>Voorrijdkosten</td><td>Nader te berekenen</td></tr>`:""}
    <tr class="total-row"><td>Totaal excl. btw</td><td>${fmtMoney(c.subtotal)}</td></tr><tr><td>${fmtN(c.vatRate,1)}% btw</td><td>${fmtMoney(c.vat)}</td></tr><tr class="grand-row"><td>Totaal incl. btw</td><td>${fmtMoney(c.total)}</td></tr></tbody></table>
    ${c.selfInstall?"":`<h3>Werkzaamheden montage</h3><ul><li>Bevochtigen en reinigen van de glasoppervlakken</li><li>Voorbereiden en positioneren van GlassShield</li><li>Verwijderen van vocht en luchtinsluitingen</li><li>Schoonsnijden en afwerken van de folie</li><li>Reinigen van glas, kozijnen en vensterbanken</li></ul>`}
    ${p.projectNotes?`<h3>Bijzonderheden</h3><p>${esc(p.projectNotes)}</p>`:""}
    ${roiOfferHTML()}
    <p class="note">Deze offerte is gebaseerd op de ingevoerde ruitmaten en het berekende snijplan. Definitieve maatvoering en geschiktheid van de beglazing worden vóór uitvoering gecontroleerd.</p>`;
}
function renderWorkorder(){
  const p=projectData(),s=lastPlan?.stats;
  if(!lastPlan){$("#workDoc").innerHTML=companyHeader()+`<h1>Werkbon</h1><div class="summary-box">Bereken eerst een snijplan.</div>`;return}
  const grouped={};lastPlan.placed.forEach(x=>{const k=x.item.room||"Niet opgegeven";(grouped[k]??=[]).push(x)});
  const roomRows=Object.entries(grouped).map(([room,arr])=>`<tr><td>${esc(room)}</td><td>${arr.length}</td><td>${[...new Set(arr.map(x=>x.item.baseId))].map(esc).join(", ")}</td></tr>`).join("");
  $("#workDoc").innerHTML=companyHeader()+`
    <h1>Werkbon – ${esc(p.customerName)}</h1>
    <table><tr><td><b>Klant</b></td><td>${esc(p.customerName)}</td><td><b>Contactpersoon</b></td><td>${esc(p.contactName)}</td></tr>
    <tr><td><b>Adres</b></td><td>${esc([p.address,p.city].filter(Boolean).join(", "))}</td><td><b>Telefoon</b></td><td>${esc(p.phone)}</td></tr>
    <tr><td><b>Offertenummer</b></td><td>${esc(p.offerNumber)}</td><td><b>Montage</b></td><td>${lastCalc?.selfInstall?"Zelf aanbrengen":mountClassText()}</td></tr></table>
    <h3>Productie- en montagegegevens</h3>
    <div class="metrics"><div class="metric">Aantal ruiten<strong>${lastPlan.placed.length}</strong></div><div class="metric">Netto glas<strong>${fmtN(s.netArea)} m²</strong></div><div class="metric">Rolverbruik<strong>${fmtN(s.rollArea)} m²</strong></div><div class="metric">Rollengte<strong>${fmtN(lastPlan.totalLen/1000)} m</strong></div></div>
    <h3>Verdeling per ruimte</h3><table><thead><tr><th>Ruimte / verdieping</th><th>Aantal</th><th>Kenmerken</th></tr></thead><tbody>${roomRows}</tbody></table>
    <h3>Uit te voeren werkzaamheden</h3><table><tbody>
      <tr><td>☐ Glas en kozijnen visueel geïnspecteerd</td><td>☐ Ruitmaten gecontroleerd</td></tr>
      <tr><td>☐ Ondergrond gereinigd en bevochtigd</td><td>☐ GlassShield aangebracht</td></tr>
      <tr><td>☐ Vocht en luchtinsluitingen verwijderd</td><td>☐ Randen gesneden en afgewerkt</td></tr>
      <tr><td>☐ Werkplek schoon achtergelaten</td><td>☐ Oplevering met opdrachtgever uitgevoerd</td></tr>
    </tbody></table>
    <h3>Bijzonderheden / bevindingen</h3><div style="height:100px;border:1px solid #aebdca;border-radius:6px;padding:8px">${esc(p.projectNotes)}</div>
    <div class="signature-grid"><div class="signature">Naam en handtekening monteur<br><br>Datum:</div><div class="signature">Naam en handtekening opdrachtgever<br><br>Datum:</div></div>`;
}
$("#refreshOffer").onclick=renderOffer;$("#refreshWorkorder").onclick=renderWorkorder;
$("#printOffer").onclick=()=>{activatePage("offer");window.print()};

function downloadCSV(){
  if(!lastPlan)return alert("Maak eerst een snijplan.");
  const rows=[["Piece_ID","Pane_ID","Copy","Room","X_mm","Y_mm","Gross_Width_mm","Gross_Height_mm","Net_Width_mm","Net_Height_mm","Rotated","Margin_per_side_mm"]];
  [...lastPlan.placed].sort((a,b)=>a.y-b.y||a.x-b.x).forEach(p=>rows.push([p.item.label,p.item.baseId,p.item.copy,p.item.room,Math.round(p.x),Math.round(p.y),Math.round(p.w),Math.round(p.h),Math.round(p.item.netW),Math.round(p.item.netH),p.rotated?1:0,Math.round(p.item.margin)]));
  const csv="\uFEFF"+rows.map(r=>r.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(";")).join("\r\n");
  downloadBlob(new Blob([csv],{type:"text/csv;charset=utf-8"}),`${safe($("#customerName").value)}_snijplan.csv`);
}
function downloadBlob(blob,name){const a=document.createElement("a");a.href=URL.createObjectURL(blob);a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1000)}
function requirePDF(){if(!window.jspdf?.jsPDF){alert("PDF-module kon niet worden geladen.");return false}return true}
function buildPlanPDF(){
  if(!lastPlan||!requirePDF())return null;
  const {jsPDF}=window.jspdf,doc=new jsPDF({unit:"mm",format:"a4"}),r=lastPlan,left=15,top=28,drawW=180,drawH=245,scale=drawW/r.rollW,seg=drawH/scale,pages=Math.max(1,Math.ceil(r.totalLen/seg));
  for(let page=0;page<pages;page++){if(page)doc.addPage();const y0=page*seg,y1=Math.min(r.totalLen,(page+1)*seg);doc.setFontSize(14);doc.text(`GlassNext snijplan – ${r.project}`,left,12);doc.setFontSize(9);doc.text(`Pagina ${page+1}/${pages} | segment ${fmtN(y0/1000)}–${fmtN(y1/1000)} m | rolbreedte ${r.rollW} mm`,left,19);doc.rect(left,top,drawW,(y1-y0)*scale);
    r.placed.forEach(p=>{const py0=Math.max(p.y,y0),py1=Math.min(p.y+p.h,y1);if(py1<=py0)return;const x=left+p.x*scale,y=top+(py0-y0)*scale,w=p.w*scale,h=(py1-py0)*scale;doc.setFillColor(225,235,245);doc.rect(x,y,w,h,"FD");if(p.y>=y0&&p.y<y1&&w>12&&h>5){doc.setFontSize(6.5);const netTxt=`Netto ${fmtN(p.item.netW/10,1)} × ${fmtN(p.item.netH/10,1)} cm`;const grossTxt=`Bruto ${fmtN(p.item.w/10,1)} × ${fmtN(p.item.h/10,1)} cm`;doc.text([`${p.item.label}${p.rotated?" R":""}`,netTxt,grossTxt],x+1.2,y+3.2,{maxWidth:Math.max(1,w-2.4),lineHeightFactor:1.05})}});
    doc.setFontSize(8);doc.text(`Rollengte ${fmtN(r.totalLen/1000)} m | roloppervlak ${fmtN(r.stats.rollArea)} m² | snijverlies ${fmtN(r.stats.wasteArea)} m² (${fmtN(r.stats.loss,1)}%)`,left,285)}
  return doc;
}
function planPDF(){
  if(!lastPlan)return alert("Maak eerst een snijplan.");
  const doc=buildPlanPDF();if(!doc)return;
  doc.save(`${safe(lastPlan.project)}_snijplan.pdf`);
}
function generatePlanPDFBase64(){
  const doc=buildPlanPDF();
  if(!doc)return null;
  return doc.output('datauristring');
}
function docToPDF(type){
  if(!requirePDF())return;type==="offer"?renderOffer():renderWorkorder();
  const {jsPDF}=window.jspdf,doc=new jsPDF({unit:"mm",format:"a4"}),p=projectData(),c=lastCalc,s=lastPlan?.stats;
  doc.setFont("helvetica","bold");doc.setFontSize(18);doc.text("Glass Next B.V.",15,16);doc.setFontSize(9);doc.setFont("helvetica","normal");doc.text("Nano-EcoLine Climate GlassShield | Isoleren zonder glas te vervangen.",15,22);doc.line(15,26,195,26);
  if(type==="offer"){
    if(!c||!s)return alert("Bereken eerst het snijplan en de calculatie.");
    doc.setFontSize(16);doc.text(`Offerte ${p.offerNumber||""}`,15,37);doc.setFontSize(10);
    const lines=[`Klant: ${p.customerName}`,`Contact: ${p.contactName}`,`Adres: ${[p.address,p.city].filter(Boolean).join(", ")}`,`Aantal ruiten: ${c.pieces}`,`Netto glasoppervlak: ${fmtN(s.netArea)} m²`,`Benodigd roloppervlak: ${fmtN(s.rollArea)} m²`,`Benodigde rollengte: ${fmtN(lastPlan.totalLen/1000)} m`,`Snijverlies: ${fmtN(s.wasteArea)} m² (${fmtN(s.loss,1)}%)`];let y=47;lines.forEach(t=>{doc.text(t,15,y);y+=6});
    y+=5;doc.setFont("helvetica","bold");doc.text("Investering",15,y);doc.setFont("helvetica","normal");y+=8;
    const offerCostRows=[
      [`GlassShield materiaal`,c.matGross],
      [`Materiaalkorting`,-c.matDisc],
      [`Voorsnijden (${c.pieces} stuks)`,c.cut],
      ...(c.selfInstall?[]:[[`Montage – ${mountClassText()}`,`Nader te berekenen`],[`Montagekorting`,-c.mountDisc]]),
      ...(c.otherCosts||[]).map(x=>[x.description||"Overige kosten",x.amount]),
      ...((flowMode==='measure'||!c.selfInstall)?[[`Voorrijdkosten`,`Nader te berekenen`]]:[])
    ];
    offerCostRows.filter(x=>typeof x[1]==="string"||Math.abs(x[1])>.001).forEach(([t,v])=>{doc.text(String(t),15,y,{maxWidth:145});doc.text(typeof v==="string"?v:fmtMoney(v),195,y,{align:"right"});y+=7});
    doc.line(15,y,195,y);y+=7;doc.setFont("helvetica","bold");doc.text("Totaal excl. btw",15,y);doc.text(fmtMoney(c.subtotal),195,y,{align:"right"});y+=7;doc.setFont("helvetica","normal");doc.text(`${fmtN(c.vatRate,1)}% btw`,15,y);doc.text(fmtMoney(c.vat),195,y,{align:"right"});y+=8;doc.setFont("helvetica","bold");doc.text("Totaal incl. btw",15,y);doc.text(fmtMoney(c.total),195,y,{align:"right"});
    doc.save(`${safe(p.customerName)}_offerte.pdf`);
  }
}
$("#exportCSVTop").onclick=downloadCSV;$("#planPDF").onclick=planPDF;$("#offerPDF").onclick=()=>docToPDF("offer");

function collectProject(){
  const inputIds=["offerNumber","customerName","contactName","email","phone","address","city","projectDescription","projectNotes","rollW","rollL","marginSide","kerf","quality","rotateDefault","materialPrice","cutPrice","materialDiscount","mountDiscount","vatRate","mountClass","mountAreaBasis","mountSelectedPrice","roiGlassType","roiAreaSource","roiArea","roiGasMethod","roiUBefore","roiUAfter","roiHDD","roiBoilerEff","roiGasKwh","roiGasSaveM2","roiCoolSaveM2","roiBuildingFactor","roiInvestmentSource","roiInvestment","roiEiaNet","roiGasPrice","roiElecPrice","roiCO2Price","roiCO2Gas","roiCO2Elec","roiYears","includeROIInOffer"];
  const values=Object.fromEntries(inputIds.map(id=>[id,$("#"+id).type==="checkbox"?$("#"+id).checked:$("#"+id).value]));
  values.installMode=installMode();
  values.flowMode=flowMode;
  if(flowMode==="measure"){
    values.prefDate1=$("#prefDate1")?.value||"";
    values.prefDate2=$("#prefDate2")?.value||"";
    values.prefDate3=$("#prefDate3")?.value||"";
    values.prefDay1=$("#prefDay1")?.value||"";
    values.prefDay2=$("#prefDay2")?.value||"";
    values.prefDay3=$("#prefDay3")?.value||"";
    values.prefTime1=$("#prefTime1")?.value||"";
    values.prefTime2=$("#prefTime2")?.value||"";
    values.prefTime3=$("#prefTime3")?.value||"";
  }
  const panes=$$("#paneTable tbody tr").map(tr=>({id:tr.querySelector(".pid").value,w:tr.querySelector(".pw").value,h:tr.querySelector(".ph").value,n:tr.querySelector(".pn").value,rot:tr.querySelector(".prot").value,room:tr.querySelector(".proom").value}));
  const workorderData=lastPlan?renderWorkorderHTML():null;
  const exportData=lastPlan?{project:$("#customerName").value,pieces:lastPlan.placed.length,rollLength:lastPlan.totalLen/1000,rollArea:lastPlan.stats.rollArea,roiStatus:lastROI?"Berekend":"Nog niet berekend"}:null;
  console.log('[GN] collectProject exportData=', exportData);
  const planData=lastPlan?{
    rollW:lastPlan.rollW,
    totalLen:lastPlan.totalLen,
    stats:lastPlan.stats,
    placed:lastPlan.placed.map(p=>({
      label:p.item.label,baseId:p.item.baseId,copy:p.item.copy,room:p.item.room,
      x:p.x,y:p.y,w:p.w,h:p.h,
      netW:p.item.netW,netH:p.item.netH,rotated:p.rotated,margin:p.item.margin
    }))
  }:null;
  console.log('[GN] collectProject planData=', planData?{placed:planData.placed.length,rollW:planData.rollW,totalLen:planData.totalLen}:null);
  return{version:"GlassNext Suite v5.0-WP",savedAt:new Date().toISOString(),values,panes,otherCosts:readOtherCosts(),workorderData,exportData,planData};
}
function renderWorkorderHTML(){
  if(!lastPlan)return"";
  const p=projectData(),s=lastPlan.stats;
  const grouped={};lastPlan.placed.forEach(x=>{const k=x.item.room||"Niet opgegeven";(grouped[k]??=[]).push(x)});
  return JSON.stringify({customerName:p.customerName,contactName:p.contactName,address:p.address,city:p.city,phone:p.phone,offerNumber:p.offerNumber,mountClass:mountClassText(),pieces:lastPlan.placed.length,netArea:s.netArea,rollArea:s.rollArea,rollLength:lastPlan.totalLen/1000,rooms:Object.entries(grouped).map(([room,arr])=>({room,count:arr.length,ids:[...new Set(arr.map(x=>x.item.baseId))]}))});
}
function renderExportSummary(){
  const p=projectData();
  $("#exportSummary").innerHTML=lastPlan?`<div class="metrics"><div class="metric">Project<strong>${esc(p.customerName)}</strong></div><div class="metric">Aantal stukken<strong>${lastPlan.placed.length}</strong></div><div class="metric">Rollengte<strong>${fmtN(lastPlan.totalLen/1000)} m</strong></div><div class="metric">Rolverbruik<strong>${fmtN(lastPlan.stats.rollArea)} m²</strong></div><div class="metric">ROI-status<strong>${lastROI?"Berekend":"Nog niet berekend"}</strong></div></div>`:`<p class="warn">Er is nog geen snijplan beschikbaar.</p>`;
}

async function submitOffer(){
  console.log('[GN] submitOffer called, flowMode=', flowMode, 'lastPlan=', !!lastPlan);
  const cb=$("#offerConsent");
  if(cb&&!cb.checked)return alert("U moet akkoord gaan met de voorwaarden voordat u een offerte kunt aanvragen.");
  const p=projectData();
  if(!p.customerName||!p.customerName.trim())return alert("Vul uw klant / organisatie naam in.");
  if(!p.email||!p.email.trim())return alert("Vul uw e-mailadres in.");
  if(flowMode!=="measure"&&!lastPlan){
    console.log('[GN] No lastPlan, generating...');
    const rollW=num($("#rollW").value),rollL=num($("#rollL").value),kerf=Math.max(0,num($("#kerf").value)||0),{items,errors}=readItems();
    console.log('[GN] rollW=', rollW, 'rollL=', rollL, 'items=', items.length, 'errors=', errors);
    if(errors.length)return alert(errors.join("\n"));
    if(!(rollW>0&&rollL>0))return alert("Voer geldige rolmaten in.");
    const tooWide=items.filter(x=>Math.min(x.w,x.canRotate?x.h:x.w)>rollW+EPS);
    if(tooWide.length)return alert(`Past niet op de rol: ${tooWide.map(x=>x.label).join(", ")}`);
    const btn0=$("#submitOffer");if(btn0){btn0.disabled=true;btn0.textContent="Berekenen…";}
    try{
      const res=await optimize(items,rollW,kerf,parseInt($("#quality").value,10));
      console.log('[GN] optimize result=', !!res);
      if(!res){if(btn0){btn0.disabled=false;btn0.textContent="Offerte aanvragen";}return alert("Geen geldige indeling gevonden.");}
      renderPlan(res,rollW,rollL,kerf);
      console.log('[GN] renderPlan done, lastPlan=', !!lastPlan);
    }finally{
      if(btn0){btn0.disabled=false;btn0.textContent="Offerte aanvragen";}
    }
  }
  console.log('[GN] Proceeding with submit, lastPlan=', !!lastPlan);
  const btn=$("#submitOffer");btn.disabled=true;const orig=btn.textContent;btn.textContent="Verzenden…";
  $("#submitStatus").innerHTML='<span class="note">Uw aanvraag wordt verzonden…</span>';
  const data=collectProject();
  const formData=new FormData();
  formData.append("action","gn_submit_offer");
  formData.append("nonce",gnNonce);
  formData.append("project_json",JSON.stringify(data));
  formData.append("customer_name",p.customerName);
  formData.append("contact_name",p.contactName);
  formData.append("email",p.email);
  formData.append("phone",p.phone);
  formData.append("address",p.address);
  formData.append("city",p.city);
  formData.append("flow_mode",flowMode||"self");
  formData.append("install_mode",installMode());
  if(flowMode==="measure"){
    formData.append("pref_date1",p.prefDate1||"");
    formData.append("pref_date2",p.prefDate2||"");
    formData.append("pref_date3",p.prefDate3||"");
    formData.append("pref_day1",p.prefDay1||"");
    formData.append("pref_day2",p.prefDay2||"");
    formData.append("pref_day3",p.prefDay3||"");
    formData.append("pref_time1",p.prefTime1||"");
    formData.append("pref_time2",p.prefTime2||"");
    formData.append("pref_time3",p.prefTime3||"");
  }
  uploadedPhotos.forEach((photo,i)=>{if(photo)formData.append("photo_"+i,photo.blob,photo.name)});
  if(flowMode!=="measure"&&lastPlan){
    try{
      const pdfDataUri=generatePlanPDFBase64();
      if(pdfDataUri){
        formData.append("plan_pdf",pdfDataUri);
        formData.append("plan_pdf_filename",`${safe(p.customerName)}_snijplan.pdf`);
      }
    }catch(e){console.warn("PDF generatie mislukt:",e)}
  }
  fetch(ajaxUrl,{method:"POST",body:formData})
    .then(r=>r.json())
    .then(resp=>{
      btn.disabled=false;btn.textContent=orig;
      if(resp.success){
        const offerNumber=resp.data.offerNumber||"";
        const thankYouUrl=resp.data.thankYouUrl||"";
        $("#offerNumber").value=offerNumber;
        if(flowMode!=="measure")renderOffer();
        $("#submitStatus").innerHTML=`<span class="ok">${esc(resp.data.message)} Uw referentienummer is: <b>${esc(offerNumber)}</b></span>`;
        if(thankYouUrl){setTimeout(()=>{window.location.href=thankYouUrl},2000)}
      }else{
        $("#submitStatus").innerHTML=`<span class="warn">${esc(resp.data?.message||"Er is een fout opgetreden.")}</span>`;
      }
    })
    .catch(()=>{
      btn.disabled=false;btn.textContent=orig;
      $("#submitStatus").innerHTML='<span class="warn">Er is een fout opgetreden bij het verzenden. Probeer het opnieuw.</span>';
    });
}
$("#submitOffer").onclick=submitOffer;

const plannerNextBtn=$('#plannerNext');
if(plannerNextBtn)plannerNextBtn.addEventListener('click',async e=>{
  if(flowMode==='self'){
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    if(!lastPlan){
      await calculateAndProceed();
    }else{
      activatePage('calc');
    }
  }
});

const projectSubmitBtn=$('#projectSubmit');
if(projectSubmitBtn)projectSubmitBtn.addEventListener('click',e=>{
  if(flowMode==='measure'){e.preventDefault();e.stopPropagation();
    const measureBtn=$('#submitMeasure')||$('#projectSubmit');
    if(measureBtn){
      measureBtn.id='submitOffer';
      const p=projectData();
      if(!p.customerName||!p.customerName.trim())return alert("Vul uw klant / organisatie naam in.");
      if(!p.email||!p.email.trim())return alert("Vul uw e-mailadres in.");
      if(!p.prefDate1&&!p.prefDate2&&!p.prefDate3)return alert("Vul minimaal één voorkeursdatum in.");
      const btn=measureBtn;btn.disabled=true;const orig=btn.textContent;btn.textContent="Verzenden…";
      const statusDiv=document.createElement('div');statusDiv.className='status';statusDiv.id='submitStatus';statusDiv.style.marginTop='12px';
      const cardBody=btn.closest('.page-nav')?.previousElementSibling?.querySelector('.card-body')||btn.closest('.page');
      const existing=$('#submitStatus');
      if(!existing){btn.closest('.page-nav').parentNode.insertBefore(statusDiv,btn.closest('.page-nav'));}
      const sd=$('#submitStatus')||statusDiv;
      sd.innerHTML='<span class="note">Uw aanvraag wordt verzonden…</span>';
      const formData=new FormData();
      formData.append("action","gn_submit_offer");
      formData.append("nonce",gnNonce);
      formData.append("project_json",JSON.stringify(collectProject()));
      formData.append("customer_name",p.customerName);
      formData.append("contact_name",p.contactName);
      formData.append("email",p.email);
      formData.append("phone",p.phone);
      formData.append("address",p.address);
      formData.append("city",p.city);
      formData.append("flow_mode","measure");
      formData.append("install_mode",installMode());
      formData.append("pref_date1",p.prefDate1||"");
      formData.append("pref_date2",p.prefDate2||"");
      formData.append("pref_date3",p.prefDate3||"");
      formData.append("pref_day1",p.prefDay1||"");
      formData.append("pref_day2",p.prefDay2||"");
      formData.append("pref_day3",p.prefDay3||"");
      formData.append("pref_time1",p.prefTime1||"");
      formData.append("pref_time2",p.prefTime2||"");
      formData.append("pref_time3",p.prefTime3||"");
      uploadedPhotos.forEach((photo,i)=>{if(photo)formData.append("photo_"+i,photo.blob,photo.name)});
      fetch(ajaxUrl,{method:"POST",body:formData})
        .then(r=>r.json())
        .then(resp=>{
          btn.disabled=false;btn.textContent=orig;
          if(resp.success){
            const offerNumber=resp.data.offerNumber||"";
            const thankYouUrl=resp.data.thankYouUrl||"";
            sd.innerHTML=`<span class="ok">${esc(resp.data.message)} Uw referentienummer is: <b>${esc(offerNumber)}</b></span>`;
            if(thankYouUrl){setTimeout(()=>{window.location.href=thankYouUrl},2000)}
          }else{
            sd.innerHTML=`<span class="warn">${esc(resp.data?.message||"Er is een fout opgetreden.")}</span>`;
          }
        })
        .catch(()=>{
          btn.disabled=false;btn.textContent=orig;
          sd.innerHTML='<span class="warn">Er is een fout opgetreden bij het verzenden. Probeer het opnieuw.</span>';
        });
    }
  }
});

function initPhotoUpload(){
  $$('.photo-pick-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const slot=btn.dataset.slot;
      const input=$('#photoFile'+slot);
      if(input)input.click();
    });
  });
  $$('.photo-input').forEach(input=>{
    input.addEventListener('change',async e=>{
      const file=e.target.files[0];
      if(!file)return;
      const slot=input.id.replace('photoFile','');
      const compressed=await compressImage(file,1280,0.85);
      uploadedPhotos[parseInt(slot)]={blob:compressed,name:file.name};
      const img=$('#photoImg'+slot);
      const preview=$('#photoPreview'+slot);
      const pickBtn=$(`.photo-pick-btn[data-slot="${slot}"]`);
      if(img){img.src=URL.createObjectURL(compressed)}
      if(preview)preview.style.display='block';
      if(pickBtn)pickBtn.style.display='none';
    });
  });
  $$('.photo-remove-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const slot=btn.dataset.slot;
      uploadedPhotos[parseInt(slot)]=null;
      const input=$('#photoFile'+slot);
      if(input)input.value='';
      const preview=$('#photoPreview'+slot);
      const pickBtn=$(`.photo-pick-btn[data-slot="${slot}"]`);
      if(preview)preview.style.display='none';
      if(pickBtn)pickBtn.style.display='flex';
    });
  });
}
function compressImage(file,maxDim,quality){
  return new Promise(resolve=>{
    const reader=new FileReader();
    reader.onload=e=>{
      const img=new Image();
      img.onload=()=>{
        const canvas=document.createElement('canvas');
        let w=img.width,h=img.height;
        if(w>maxDim||h>maxDim){if(w>h){h=Math.round(h*maxDim/w);w=maxDim}else{w=Math.round(w*maxDim/h);h=maxDim}}
        canvas.width=w;canvas.height=h;
        canvas.getContext('2d').drawImage(img,0,0,w,h);
        canvas.toBlob(blob=>resolve(blob),'image/jpeg',quality);
      };
      img.src=e.target.result;
    };
    reader.readAsDataURL(file);
  });
}

initConfig();
applyROIProfile();
initTooltips();
initPhotoUpload();
}
