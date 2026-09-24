const $ = id => document.getElementById(id);
const F = ['org_name','org_address','staff_name','staff_id','dob','department','job_title','hire_date','employment_type','emergency_contact','phone','email','address','principal','template','layout','color1','color2'];
let photoData = '', logoData = '', editingId = 0;

// ---- Fully-customizable layout engine ----
// Every draggable card piece has a data-el key. layout stores per-element
// {dx, dy, size, vis}. Dragging sets dx/dy, sliders fine-tune + resize.
const EL = {
  dTop:{label:'Front top decoration', kind:'h', min:20, max:140, def:0},
  dLogo:{label:'Front logo', kind:'w', min:60, max:260, def:150},
  dCoName:{label:'Front company name', kind:'f', min:12, max:30, def:19},
  dPhoto:{label:'Front photo', kind:'w', min:80, max:280, def:200},
  dName:{label:'Front employee name', kind:'f', min:14, max:36, def:24},
  dTitle:{label:'Front job title', kind:'f', min:10, max:24, def:14},
  dBot:{label:'Front bottom band', kind:'h', min:30, max:160, def:0},
  dBar:{label:'Front barcode', kind:'w', min:100, max:300, def:239},
  dQrFront:{label:'Front QR code', kind:'w', min:40, max:140, def:84, square:true},
  dId:{label:'Front ID line', kind:'f', min:10, max:22, def:14},
  dIdFoot:{label:'Front footer ID (teal)', kind:'f', min:10, max:24, def:15},
  dBTop:{label:'Back top decoration', kind:'h', min:16, max:120, def:0},
  dLogoB:{label:'Back logo', kind:'w', min:40, max:200, def:96},
  dCoNameB:{label:'Back company name', kind:'f', min:11, max:26, def:16},
  dBHead:{label:'Back heading', kind:'f', min:12, max:26, def:17},
  dTerms:{label:'Back terms block', kind:'f', min:9, max:18, def:12},
  dContact:{label:'Back contact block', kind:'f', min:9, max:20, def:13},
  dRowP:{label:'Back phone row', kind:'f', min:9, max:20, def:13},
  dRowE:{label:'Back email row', kind:'f', min:9, max:20, def:13},
  dRowA:{label:'Back address row', kind:'f', min:9, max:20, def:13},
  dBarB:{label:'Back barcode', kind:'w', min:100, max:300, def:239},
  dQrB:{label:'Back QR code', kind:'w', min:40, max:140, def:84, square:true},
  dBLines:{label:'Back ID/website lines', kind:'f', min:9, max:20, def:12},
  dBBot:{label:'Back bottom decoration', kind:'h', min:10, max:100, def:0},
  cLogo:{label:'Corporate logo', kind:'w', min:80, max:300, def:220},
  cPhoto:{label:'Corporate photo', kind:'w', min:80, max:280, def:196},
  cName:{label:'Corporate name', kind:'f', min:12, max:34, def:21},
  cRole:{label:'Corporate role band', kind:'f', min:10, max:24, def:14},
  cId:{label:'Corporate staff ID', kind:'f', min:10, max:24, def:14},
  cQr:{label:'Corporate QR code', kind:'w', min:40, max:140, def:92, square:true},
};
let layout = {}, selEl = 'dPhoto';
function layOf(k){ if(!layout[k] || typeof layout[k] !== 'object') layout[k] = {dx:0, dy:0, size:EL[k].def, vis:true}; const L = layout[k]; if(typeof L.dx!=='number')L.dx=0; if(typeof L.dy!=='number')L.dy=0; if(typeof L.size!=='number')L.size=EL[k].def; if(typeof L.vis!=='boolean')L.vis=true; return L; }
function applyLayout(){
  Object.keys(EL).forEach(k=>{
    const L = layOf(k), cfg = EL[k];
    document.querySelectorAll('[data-el="'+k+'"]').forEach(el=>{
      el.style.transform = (L.dx || L.dy) ? 'translate('+L.dx+'px,'+L.dy+'px)' : '';
      if(cfg.kind === 'w'){
        el.style.width = L.size + 'px';
        el.style.height = cfg.square ? L.size + 'px' : 'auto';
      } else if(cfg.kind === 'h'){
        el.style.height = (L.size > 0) ? L.size + 'px' : '';
      } else {
        el.style.fontSize = L.size + 'px';
      }
      el.style.display = L.vis === false ? 'none' : '';
      el.classList.toggle('el-sel', k === selEl);
    });
  });
  positionHandle();
}
function persistLayout(){ try{ localStorage.setItem('erp_idmaker_layout', JSON.stringify(layout)); }catch(e){} }
function syncPanel(){
  const cfg = EL[selEl], L = layOf(selEl);
  if($('elSelect')) $('elSelect').value = selEl;
  if($('rngX')){ $('rngX').value = L.dx; $('valX').textContent = L.dx; }
  if($('rngY')){ $('rngY').value = L.dy; $('valY').textContent = L.dy; }
  if($('rngSize')){
    $('rngSize').min = cfg.min; $('rngSize').max = cfg.max;
    let sv = L.size;
    if(cfg.kind === 'h' && !(sv > 0)){
      const el = document.querySelector('[data-el="'+selEl+'"]');
      sv = el && el.offsetHeight ? Math.round(el.offsetHeight) : cfg.min;
    }
    $('rngSize').value = sv; $('valSize').textContent = sv + 'px' + (cfg.kind==='f' ? ' font' : cfg.kind==='h' ? ' height' : ''); $('sizeLabel').textContent = cfg.kind==='w' ? 'Size (width)' : cfg.kind==='h' ? 'Size (height)' : 'Size (font)';
  }
  if($('elVisible')) $('elVisible').checked = L.vis !== false;
}
function resetLayout(){ layout = {}; persistLayout(); syncPanel(); applyLayout(); }
let rzHandle = null, rzState = null;
function positionHandle(){
  if(!rzHandle) return;
  const el = document.querySelector('[data-el="'+selEl+'"]');
  if(!el || layOf(selEl).vis === false || !el.isConnected){ rzHandle.style.display = 'none'; return; }
  const r = el.getBoundingClientRect();
  if(r.width === 0 && r.height === 0){ rzHandle.style.display = 'none'; return; }
  rzHandle.style.display = 'block';
  rzHandle.style.left = (r.right - 8 + window.scrollX) + 'px';
  rzHandle.style.top = (r.bottom - 8 + window.scrollY) + 'px';
}
function initDesigner(){
  const s = $('elSelect'); if(!s) return;
  s.innerHTML = '';
  Object.keys(EL).forEach(k=>{ const o = document.createElement('option'); o.value = k; o.textContent = EL[k].label; s.appendChild(o); });
  s.value = selEl;
  s.onchange = ()=>{ selEl = s.value; syncPanel(); applyLayout(); };
  $('rngX').oninput = e=>{ layOf(selEl).dx = +e.target.value; syncPanel(); applyLayout(); persistLayout(); };
  $('rngY').oninput = e=>{ layOf(selEl).dy = +e.target.value; syncPanel(); applyLayout(); persistLayout(); };
  $('rngSize').oninput = e=>{ layOf(selEl).size = +e.target.value; syncPanel(); applyLayout(); persistLayout(); };
  $('elVisible').onchange = e=>{ layOf(selEl).vis = e.target.checked; applyLayout(); persistLayout(); };
  try{ const l = JSON.parse(localStorage.getItem('erp_idmaker_layout') || '{}'); if(l && typeof l === 'object') layout = l; }catch(e){}
  // Direct drag-and-drop on the cards
  let drag = null;
  // Global resize handle
  rzHandle = document.createElement('div');
  rzHandle.className = 'rz-handle';
  rzHandle.title = 'Drag to resize';
  document.body.appendChild(rzHandle);
  rzHandle.addEventListener('pointerdown', e=>{
    const cfg = EL[selEl], L = layOf(selEl);
    const startSize = (cfg.kind === 'h' && !(L.size > 0))
      ? (document.querySelector('[data-el="'+selEl+'"]') || {offsetHeight: cfg.min}).offsetHeight || cfg.min
      : L.size;
    rzState = {x0: e.clientX, y0: e.clientY, size0: startSize};
    rzHandle.setPointerCapture && rzHandle.setPointerCapture(e.pointerId);
    e.preventDefault(); e.stopPropagation();
  });
  rzHandle.addEventListener('pointermove', e=>{
    if(!rzState) return;
    const cfg = EL[selEl], L = layOf(selEl);
    const delta = Math.round(((e.clientX - rzState.x0) + (e.clientY - rzState.y0)) / 2);
    L.size = Math.max(cfg.min, Math.min(cfg.max, rzState.size0 + delta));
    syncPanel(); applyLayout();
  });
  const endResize = ()=>{ if(rzState){ rzState = null; persistLayout(); } };
  rzHandle.addEventListener('pointerup', endResize);
  rzHandle.addEventListener('pointercancel', endResize);
  document.addEventListener('pointerdown', e=>{
    if(e.target.closest && e.target.closest('.rz-handle')) return;
    const t = e.target.closest ? e.target.closest('[data-el]') : null;
    if(!t) return;
    selEl = t.dataset.el; syncPanel(); applyLayout();
    const L = layOf(selEl);
    drag = {key: selEl, x0: e.clientX, y0: e.clientY, dx0: L.dx, dy0: L.dy, moved: false};
    t.classList.add('el-drag');
    e.preventDefault();
  });
  document.addEventListener('pointermove', e=>{
    if(!drag) return;
    const L = layOf(drag.key);
    L.dx = Math.max(-220, Math.min(220, drag.dx0 + Math.round(e.clientX - drag.x0)));
    L.dy = Math.max(-220, Math.min(220, drag.dy0 + Math.round(e.clientY - drag.y0)));
    drag.moved = true;
    if(drag.key === selEl) syncPanel();
    applyLayout();
  });
  const endDrag = ()=>{
    if(!drag) return;
    document.querySelectorAll('.el-drag').forEach(el=>el.classList.remove('el-drag'));
    if(drag.moved) persistLayout();
    drag = null;
  };
  document.addEventListener('pointerup', endDrag);
  document.addEventListener('pointercancel', endDrag);
  window.addEventListener('resize', positionHandle);
  window.addEventListener('scroll', positionHandle, true);
  syncPanel();
}

const TPLS = [
  ['#0d6b3f','#8fd14f'],['#0e7c7b','#8fd8d2'],['#2f7fd0','#bfe0ff'],
  ['#6d5df6','#c9c2ff'],['#0b3d2e','#c8f169'],['#1e4fd8','#9ec1ff'],
  ['#ffffff','#e8edf5']
];
const TPL_NAMES = ['Emerald Geo','Teal Oval','Hospital Blue','Purple Dots','Lime Block','Royal Frame','Corporate White'];

/* ===== Customizable field labels ===== */
const LABEL_KEYS = [
  ['company','Company name'],['company_address','Company address'],
  ['employee_name','Employee name'],['staff_id','Staff ID'],
  ['job_title','Job title'],['department','Department'],
  ['date_joined','Date joined'],['employment_type','Employment type'],
  ['phone','Phone'],
  ['dob','DOB (optional)'],['email','Work email'],
  ['emergency','Emergency contact'],['address','Office / home address'],
  ['signatory','HR Manager / Signatory'],['id_prefix','ID prefix'],
  ['back_phone','Phone (card back)'],['back_email','Email (card back)'],['back_address','Address (card back)'],
];
const LABEL_DEFAULTS = Object.fromEntries(LABEL_KEYS.map(([k,v])=>[k, k==='id_prefix' ? 'ID ' : v]));
let LABELS = {...LABEL_DEFAULTS};
function buildLabelEditor(){
  const g = $('labelGrid'); if(!g) return;
  g.innerHTML = '';
  LABEL_KEYS.forEach(([k,def])=>{
    const d = document.createElement('div');
    d.innerHTML = `<label style="text-transform:none;color:var(--mut);font-size:12px">${esc(def)}</label><input id="lab_${k}" value="${esc(LABELS[k] ?? def)}">`;
    d.querySelector('input').addEventListener('input', ()=>{ LABELS[k] = d.querySelector('input').value; applyLabels(LABELS); render(); });
    g.appendChild(d);
  });
}
function fillLabelEditor(){
  LABEL_KEYS.forEach(([k,def])=>{ const el = $('lab_'+k); if(el) el.value = LABELS[k] ?? def; });
}
function collectLabels(){
  LABEL_KEYS.forEach(([k,def])=>{ const el = $('lab_'+k); LABELS[k] = el ? el.value : (LABELS[k] ?? def); });
  return {...LABELS};
}
function applyLabels(obj){
  LABELS = {...LABEL_DEFAULTS};
  if(obj && typeof obj === 'object') Object.keys(obj).forEach(k=>{ if(k in LABELS && typeof obj[k] === 'string') LABELS[k] = obj[k].slice(0,60); });
  document.querySelectorAll('[data-lab]').forEach(el=>{ const k = el.dataset.lab; if(k in LABELS) el.textContent = LABELS[k]; });
  fillLabelEditor();
}
function idPrefix(){ return LABELS.id_prefix || 'ID '; }

/* ===== Card back content: editable terms + custom fields ===== */
const FIELD_SOURCES = [
  ['staff_id','Staff ID'],['name','Employee name'],['title','Job title'],
  ['department','Department'],['phone','Phone'],['email','Email'],
  ['company','Company'],['static','Fixed text'],
];
const BACK_DEFAULT = {
  title: 'TERMS & CONDITIONS',
  body: 'Identification: Carry this ID card at all times during working hours for identification purposes.\n\nAuthorized Use: This card is strictly for official use and must not be shared or used for unauthorized purposes.',
  fields: [{label:'ID No', source:'staff_id', static:''}],
};
let BACK = JSON.parse(JSON.stringify(BACK_DEFAULT));
function backFieldValue(f, ctx){
  if(!f) return '';
  if(f.source === 'static') return f.static || '';
  return ctx[f.source] ?? '';
}
function buildBackEditor(){
  fillBackEditor();
  const t = $('back_title'); if(t){ t.addEventListener('input', ()=>{ BACK.title = t.value; render(); }); }
  const b = $('back_body'); if(b){ b.addEventListener('input', ()=>{ BACK.body = b.value; render(); }); }
}
function fillBackEditor(){
  const t = $('back_title'); if(t) t.value = BACK.title ?? BACK_DEFAULT.title;
  const b = $('back_body'); if(b) b.value = BACK.body ?? BACK_DEFAULT.body;
  const w = $('fieldRows'); if(!w) return;
  w.innerHTML = '';
  (BACK.fields || []).forEach((f,i)=>{
    const row = document.createElement('div');
    row.className = 'row3';
    row.style.cssText = 'align-items:end;margin-bottom:6px';
    row.innerHTML = `<div><input data-f="label" placeholder="Label e.g. ID No" value="${esc(f.label||'')}"></div>
      <div><select data-f="source">${FIELD_SOURCES.map(([v,l])=>`<option value="${v}"${(f.source||'staff_id')===v?' selected':''}>${l}</option>`).join('')}</select></div>
      <div><input data-f="static" placeholder="Fixed text" value="${esc(f.static||'')}" style="${(f.source||'staff_id')==='static'?'':'display:none'}">
      <button class="ghost" data-f="del" style="width:100%;margin-top:6px">Remove</button></div>`;
    row.querySelector('[data-f=label]').addEventListener('input', e=>{ BACK.fields[i].label = e.target.value; render(); });
    row.querySelector('[data-f=source]').addEventListener('change', e=>{
      BACK.fields[i].source = e.target.value;
      row.querySelector('[data-f=static]').style.display = e.target.value === 'static' ? '' : 'none';
      render();
    });
    row.querySelector('[data-f=static]').addEventListener('input', e=>{ BACK.fields[i].static = e.target.value; render(); });
    row.querySelector('[data-f=del]').onclick = ()=>{ BACK.fields.splice(i,1); fillBackEditor(); render(); };
    w.appendChild(row);
  });
}
function addBackField(){
  BACK.fields = BACK.fields || [];
  BACK.fields.push({label:'', source:'staff_id', static:''});
  fillBackEditor(); render();
}
function collectBack(){
  const t = $('back_title'); if(t) BACK.title = t.value;
  const b = $('back_body'); if(b) BACK.body = b.value;
  return JSON.parse(JSON.stringify(BACK));
}
function applyBack(obj){
  BACK = JSON.parse(JSON.stringify(BACK_DEFAULT));
  if(obj && typeof obj === 'object'){
    if(typeof obj.title === 'string') BACK.title = obj.title.slice(0,120);
    if(typeof obj.body === 'string') BACK.body = obj.body.slice(0,2000);
    if(Array.isArray(obj.fields)) BACK.fields = obj.fields.slice(0,12).map(f=>({
      label: String((f&&f.label)||'').slice(0,40),
      source: FIELD_SOURCES.some(([v])=>v===(f&&f.source)) ? f.source : 'staff_id',
      static: String((f&&f.static)||'').slice(0,120),
    }));
  }
  fillBackEditor();
}

/* ===== ID code choice: QR vs barcode + custom content ===== */
const CODE_DEFAULT = {front:'barcode', back:'barcode', content:'{id}'};
let CODE = {...CODE_DEFAULT};
function codeVars(){
  return {
    id: val('staff_id') || '', name: val('staff_name') || '',
    title: val('job_title') || '', dept: val('department') || '',
    phone: val('phone') || '', email: val('email') || '',
    company: val('org_name') || '',
  };
}
function codeContent(fallback){
  const t = (CODE.content || '').trim();
  if(!t) return fallback;
  const m = codeVars();
  return t.replace(/\{(id|name|title|dept|phone|email|company)\}/g, (_,k)=>m[k] ?? '');
}
function collectCode(){
  const f = $('code_front'); if(f) CODE.front = f.value;
  const b = $('code_back'); if(b) CODE.back = b.value;
  const c = $('code_content'); if(c) CODE.content = c.value;
  return {...CODE};
}
function applyCode(obj){
  CODE = {...CODE_DEFAULT};
  if(obj && typeof obj === 'object'){
    if(['barcode','qr','none'].includes(obj.front)) CODE.front = obj.front;
    if(['barcode','qr','both','none'].includes(obj.back)) CODE.back = obj.back;
    if(typeof obj.content === 'string') CODE.content = obj.content.slice(0,300);
  }
  fillCodeEditor();
}
function fillCodeEditor(){
  const f = $('code_front'); if(f) f.value = CODE.front;
  const b = $('code_back'); if(b) b.value = CODE.back;
  const c = $('code_content'); if(c) c.value = CODE.content;
}

function initTemplates(){
  const s = $('template');
  if(s && s.tagName === 'SELECT'){
    s.innerHTML = '';
    TPL_NAMES.forEach((n,i)=>{
      const o = document.createElement('option');
      o.value = i + 1;
      o.textContent = 'Template ' + (i + 1) + ' — ' + n;
      s.appendChild(o);
    });
    s.value = '1';
  }
}
function setTemplate(n){
  n = Math.max(1, Math.min(7, +n || 1));
  if($('template')) $('template').value = n;
  if(DEFAULTS[n] && applyBaseDefault(n)) return;
  if(n<=6){ $('color1').value = TPLS[n-1][0]; $('color2').value = TPLS[n-1][1]; }
  render();
}

/* ===== Per-base default designs ("Set as default design") ===== */
let DEFAULTS = {};
async function fetchDefaults(){
  try{
    const r = await fetch('../api/id-cards.php?action=default_list').then(r=>r.json());
    DEFAULTS = (r.defaults && typeof r.defaults === 'object') ? r.defaults : {};
  }catch(e){ DEFAULTS = {}; }
}
function applyBaseDefault(n){
  const t = DEFAULTS[n]; if(!t) return false;
  if($('template')) $('template').value = n;
  if($('color1')) $('color1').value = t.color1 || '#0d6b3f';
  if($('color2')) $('color2').value = t.color2 || '#8fd14f';
  try{ const l = JSON.parse(t.layout_json || '{}'); layout = (l && typeof l === 'object') ? l : {}; }catch(e){ layout = {}; }
  syncPanel();
  try{ applyLabels(JSON.parse(t.labels_json || '{}')); }catch(e){ applyLabels({}); }
  try{ applyBack(JSON.parse(t.back_json || 'null')); }catch(e){ applyBack(null); }
  try{ applyCode(JSON.parse(t.code_json || 'null')); }catch(e){ applyCode(null); }
  if(t.logo_path){ logoData = t.logo_path; }
  render();
  return true;
}
async function saveDefaultDesign(){
  const base = Math.max(1, Math.min(7, parseInt(val('template')) || 1));
  collectLabels(); collectBack(); collectCode();
  const fd = new FormData();
  fd.append('action','default_save');
  fd.append('base', base);
  fd.append('color1', val('color1'));
  fd.append('color2', val('color2'));
  fd.append('layout_json', JSON.stringify(layout));
  fd.append('labels_json', JSON.stringify(LABELS));
  fd.append('back_json', JSON.stringify(BACK));
  fd.append('code_json', JSON.stringify(CODE));
  const lf = $('logoFile') && $('logoFile').files[0];
  if(lf) fd.append('logo', lf);
  else if(logoData && !String(logoData).startsWith('data:')) fd.append('logo_path', logoData);
  const r = await fetch('../api/id-cards.php', {method:'POST', body:fd}).then(r=>r.json());
  if(r.ok){ alert('Template ' + base + ' default updated — it now opens with this design.'); fetchDefaults(); }
  else alert('Save failed: '+(r.error||'unknown'));
}
async function resetFactoryDefault(){
  const base = Math.max(1, Math.min(7, parseInt(val('template')) || 1));
  if(!confirm('Restore the factory look for Template ' + base + '? Your saved default will be discarded.')) return;
  await fetch('../api/id-cards.php?action=default_reset&base='+base, {method:'POST'}).then(r=>r.json());
  delete DEFAULTS[base];
  layout = {}; applyLabels({});
  BACK = JSON.parse(JSON.stringify(BACK_DEFAULT)); fillBackEditor();
  CODE = {...CODE_DEFAULT}; fillCodeEditor();
  if($('color1')) $('color1').value = TPLS[base-1][0];
  if($('color2')) $('color2').value = TPLS[base-1][1];
  syncPanel(); render();
}
function setLayout(v){
  $('layout').value = v;
  document.querySelectorAll('#layoutSeg button').forEach(b=>b.classList.toggle('active', b.dataset.v===v));
  render();
}
function randomId(){
  $('staff_id').value = 'EMP-' + new Date().getFullYear() + '-' + String(Math.floor(1000+Math.random()*9000));
  render();
}
function readFile(input, cb){
  const f = input.files[0]; if(!f) return;
  const r = new FileReader(); r.onload = e => cb(e.target.result); r.readAsDataURL(f);
}
function val(id){ const el = $(id); return el ? el.value.trim() : ''; }

function render(){
  const c1 = val('color1')||'#0d6b3f', c2 = val('color2')||'#8fd14f';
  const tpl = Math.max(1, Math.min(7, parseInt(val('template')||'1',10)));
  const isCorp = tpl === 7;
  const dxF = $('dxFront'), dxB = $('dxBack');
  if($('cardCorp')) $('cardCorp').style.display = isCorp ? 'flex' : 'none';
  if(dxF) dxF.style.display = isCorp ? 'none' : 'flex';
  if(dxB) dxB.style.display = isCorp ? 'none' : 'flex';
  if(!isCorp){
    if(dxF){ dxF.className = 'dx-front t'+tpl; dxF.style.setProperty('--a', c1); dxF.style.setProperty('--b', c2); }
    if(dxB){ dxB.className = 'dx-back t'+tpl; dxB.style.setProperty('--a', c1); dxB.style.setProperty('--b', c2); }
  }
  const empName = val('staff_name') || 'EMPLOYEE NAME';
  const staffId = val('staff_id') || 'EMP-0000';
  const title = val('job_title') || val('department') || val('employment_type') || 'Staff';
  // Designed front
  if($('dCoName')) $('dCoName').textContent = val('org_name') || 'Your Company';
  if($('dName')) $('dName').textContent = empName.toUpperCase();
  if($('dTitle')) $('dTitle').textContent = title;
  if($('dId')) $('dId').textContent = idPrefix() + staffId;
  if($('dIdFoot')) $('dIdFoot').textContent = idPrefix() + staffId;
  // Designed back
  if($('dCoNameB')) $('dCoNameB').textContent = val('org_name') || 'Your Company';
  if($('dBPhone')) $('dBPhone').textContent = val('phone') || '--';
  if($('dBEmail')) $('dBEmail').textContent = val('email') || '--';
  if($('dBAddr')) $('dBAddr').textContent = val('address') || val('org_address') || '--';
  if($('dBId')) $('dBId').textContent = idPrefix() + staffId;
  if($('dBWeb')){
    const em = val('email'), dom = em.includes('@') ? em.split('@')[1] : '';
    $('dBWeb').textContent = dom ? 'www.' + dom : (val('org_name') || '');
  }
  if(photoData && $('dPhoto')) $('dPhoto').src = photoData;
  if(logoData){ if($('dLogo')) $('dLogo').src = logoData; if($('dLogoB')) $('dLogoB').src = logoData; }
  // Back: editable heading/body + custom fields
  const ctx = {staff_id: staffId, name: empName, title: title, department: val('department'),
    phone: val('phone'), email: val('email'), company: val('org_name')};
  if($('dBHeadT')) $('dBHeadT').textContent = BACK.title || '';
  if($('dBTerms')) $('dBTerms').innerHTML = String(BACK.body || '').split(/\n\s*\n/).filter(p=>p.trim()!=='').map(p=>'<p>'+esc(p.trim()).replace(/\n/g,'<br>')+'</p>').join('');
  if($('dBackFields')) $('dBackFields').innerHTML = (BACK.fields || []).map(f=>'<div class="fb-row"><b>'+esc(f.label||'')+(f.label?':':'')+'</b><span>'+esc(backFieldValue(f, ctx))+'</span></div>').join('');
  // Codes: QR vs barcode + custom content
  const qrText = JSON.stringify({name:empName, id:staffId, title:title, org:val('org_name')});
  const frontPayload = codeContent(staffId), backPayload = codeContent(staffId);
  const showBarF = !isCorp && CODE.front === 'barcode';
  const showQrF = !isCorp && CODE.front === 'qr';
  if($('dBarcode')) $('dBarcode').style.display = showBarF ? '' : 'none';
  if($('dQrFront')) $('dQrFront').style.display = showQrF ? '' : 'none';
  try{
    if($('dQrB') && !isCorp){ $('dQrB').innerHTML = ''; new QRCode($('dQrB'), { text: codeContent(qrText), width:84, height:84 }); }
  }catch(e){}
  try{
    if($('dQrFront') && showQrF){ $('dQrFront').innerHTML = ''; new QRCode($('dQrFront'), { text: codeContent(qrText), width:84, height:84 }); }
  }catch(e){}
  try{ if(showBarF){ JsBarcode('#dBarcode', frontPayload || staffId, {format:'CODE128', displayValue:false, background:'transparent', lineColor:c1}); } }catch(e){}
  const backMode = CODE.back || 'barcode';
  const showBarB = !isCorp && (backMode === 'barcode' || backMode === 'both');
  const showQrB = !isCorp && (backMode === 'qr' || backMode === 'both');
  if($('dBarcodeB')) $('dBarcodeB').style.display = showBarB ? '' : 'none';
  if($('dQrB')) $('dQrB').style.display = showQrB ? '' : 'none';
  const bcode = $('dBarcodeB') && $('dBarcodeB').parentElement;
  if(bcode) bcode.classList.toggle('both', backMode === 'both');
  try{ if(showBarB){ JsBarcode('#dBarcodeB', backPayload || staffId, {format:'CODE128', displayValue:false, background:'transparent', lineColor:c1}); } }catch(e){}
  // Corporate card fields (template 7)
  if($('cName')) $('cName').textContent = empName.toUpperCase();
  if($('cRole')) $('cRole').textContent = val('employment_type') || val('job_title') || 'Staff';
  if($('cId')) $('cId').textContent = idPrefix() + staffId;
  if(photoData && $('cPhoto')) $('cPhoto').src = photoData;
  if(logoData && $('cLogo')) $('cLogo').src = logoData;
  try{
    if($('qrcodeCorp') && isCorp){ $('qrcodeCorp').innerHTML = ''; new QRCode($('qrcodeCorp'), { text: qrText, width:92, height:92 }); }
  }catch(e){}
  applyLayout();
}

async function downloadPNG(elId, name){
  const el = $(elId);
  const prev = el.style.display;
  if(prev === 'none'){ el.style.display = (elId==='cardCorp' ? 'flex' : 'block'); await new Promise(r=>setTimeout(r,60)); }
  // Hide editor visuals (selection outline + resize handle) so exports are clean
  const sel = Array.from(document.querySelectorAll('.el-sel'));
  sel.forEach(x=>x.classList.remove('el-sel'));
  const rh = document.querySelector('.rz-handle');
  const rhPrev = rh ? rh.style.display : '';
  if(rh) rh.style.display = 'none';
  let canvas;
  try{ canvas = await html2canvas(el, {scale:2, useCORS:true, backgroundColor:'#ffffff'}); }
  catch(err){ alert('PNG export blocked: an image refused cross-origin access. Use Print instead.'); throw err; }
  sel.forEach(x=>x.classList.add('el-sel'));
  if(rh) rh.style.display = rhPrev;
  if(prev === 'none'){ el.style.display = 'none'; }
  const a = document.createElement('a');
  a.download = name; a.href = canvas.toDataURL('image/png'); a.click();
}

async function save(){
  const fd = new FormData();
  F.forEach(k=>fd.append(k, val(k)));
  fd.append('layout_json', JSON.stringify(layout));
  fd.append('labels_json', JSON.stringify(LABELS));
  fd.append('back_json', JSON.stringify(collectBack()));
  fd.append('code_json', JSON.stringify(collectCode()));
  fd.append('id', editingId);
  fd.append('template_id', currentPresetId);
  if(!($('photoFile') && $('photoFile').files[0]) && presetPhoto) fd.append('photo_path', presetPhoto);
  if(!($('logoFile') && $('logoFile').files[0]) && presetLogo) fd.append('logo_path', presetLogo);
  const pf = $('photoFile') && $('photoFile').files[0]; if(pf) fd.append('photo', pf);
  const lf = $('logoFile') && $('logoFile').files[0]; if(lf) fd.append('logo', lf);
  fd.append('action','save');
  const r = await fetch('../api/id-cards.php', {method:'POST', body:fd}).then(r=>r.json());
  if(r.ok){ alert('Saved (ID '+r.id+')'); editingId = 0; if($('editLabel')) $('editLabel').textContent='New card'; loadRecords(); }
  else alert('Save failed: '+(r.error||'unknown'));
}
async function loadRecords(){
  if(!$('rows')) return;
  const q = $('search') ? $('search').value.trim() : '';
  const r = await fetch('../api/id-cards.php?action=list&q='+encodeURIComponent(q)).then(r=>r.json());
  const tb = $('rows'); tb.innerHTML='';
  (r.cards||[]).forEach(c=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${c.id}</td><td><b>${esc(c.staff_name)}</b><br><small>${esc(c.staff_id)}</small></td>
      <td>${esc(c.department||'')}</td><td>${esc(c.job_title||'')}</td>
      <td class="actions">
        <button class="ghost" data-a="load">Load</button>
        <button class="ghost" data-a="del">Delete</button>
      </td>`;
    tr.querySelector('[data-a=load]').onclick = ()=>loadCard(c.id);
    tr.querySelector('[data-a=del]').onclick = async ()=>{
      if(!confirm('Delete #'+c.id+'?')) return;
      await fetch('../api/id-cards.php?action=delete&id='+c.id).then(r=>r.json());
      loadRecords();
    };
    tb.appendChild(tr);
  });
  if($('count')) $('count').textContent = (r.cards||[]).length + ' record(s)';
}
function esc(s){ return String(s??'').replace(/[&<>"]/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m])); }
async function loadCard(id){
  const r = await fetch('../api/id-cards.php?action=get&id='+id).then(r=>r.json());
  if(!r.card) return;
  const c = r.card; editingId = c.id;
  currentPresetId = c.template_id || 0;
  const ps = $('presetSelect'); if(ps) ps.value = currentPresetId;
  presetLogo = ''; presetPhoto = '';
  F.forEach(k=>{ if($(k) && c[k]!==undefined && c[k]!==null) $(k).value = c[k]; });
  try{ const l = JSON.parse(c.layout_json || '{}'); layout = (l && typeof l === 'object') ? l : {}; }catch(e){ layout = {}; }
  try{ applyLabels(JSON.parse(c.labels_json || '{}')); }catch(e){ applyLabels({}); }
  try{ applyBack(JSON.parse(c.back_json || 'null')); }catch(e){ applyBack(null); }
  try{ applyCode(JSON.parse(c.code_json || 'null')); }catch(e){ applyCode(null); }
  persistLayout(); syncPanel();
  photoData = c.photo_path||''; logoData = c.logo_path||'';
  if(photoData && $('dPhoto')) $('dPhoto').src = photoData;
  if(logoData){ if($('dLogo')) $('dLogo').src = logoData; if($('dLogoB')) $('dLogoB').src = logoData; }
  setLayout(c.layout||'horizontal');
  $('editLabel').textContent = 'Editing #' + c.id;
  render(); window.scrollTo({top:0, behavior:'smooth'});
}
function resetForm(){
  editingId = 0; if($('editLabel')) $('editLabel').textContent='New card';
  ['org_name','org_address','staff_name','staff_id','dob','department','job_title','hire_date','emergency_contact','phone','email','address','principal'].forEach(k=>{ if($(k)) $(k).value=''; });
  if($('employment_type')) $('employment_type').value='Full-time';
  photoData=''; logoData='';
  if($('photoFile')) $('photoFile').value='';
  if($('logoFile')) $('logoFile').value='';
  if($('dPhoto')) $('dPhoto').src=window.IDM_FALLBACK_PHOTO;
  if($('dLogo')) $('dLogo').src=window.IDM_FALLBACK_LOGO;
  if($('dLogoB')) $('dLogoB').src=window.IDM_FALLBACK_LOGO;
  layout = {}; persistLayout(); syncPanel();
  applyLabels({});
  BACK = JSON.parse(JSON.stringify(BACK_DEFAULT)); fillBackEditor();
  CODE = {...CODE_DEFAULT}; fillCodeEditor();
  setTemplate(1); setLayout('horizontal');
  const ps = $('presetSelect') || $('presetSelectAuto');
  if(ps && ps.value && +ps.value > 0) applyPreset(+ps.value);
}

window.addEventListener('DOMContentLoaded', ()=>{
  initTemplates();
  initDesigner();
  buildLabelEditor();
  buildBackEditor();
  const cf = $('code_front'); if(cf) cf.addEventListener('change', ()=>{ collectCode(); render(); });
  const cb = $('code_back'); if(cb) cb.addEventListener('change', ()=>{ collectCode(); render(); });
  const cc = $('code_content'); if(cc) cc.addEventListener('input', ()=>{ collectCode(); render(); });
  applyLabels({}); applyBack(null); applyCode(null);
  F.forEach(k=>{ if($(k)) $(k).addEventListener('input', render); if($(k)) $(k).addEventListener('change', render); });
  if($('photoFile')) $('photoFile').addEventListener('change', e=>readFile(e.target, d=>{photoData=d; render();}));
  if($('logoFile')) $('logoFile').addEventListener('change', e=>readFile(e.target, d=>{logoData=d; render();}));
  if($('search')) $('search').addEventListener('input', loadRecords);
  if($('presetSelect')){ loadPresetOptions('presetSelect'); $('presetSelect').addEventListener('change', e=>applyPreset(+e.target.value)); }
  if($('presetSelectAuto')){ loadPresetOptions('presetSelectAuto'); $('presetSelectAuto').addEventListener('change', e=>applyPreset(+e.target.value)); }
  if($('preset_name')) loadTemplateList();
  if($('csv_file')) $('csv_file').addEventListener('change', e=>{ const f=e.target.files[0]; if(!f) return; const r=new FileReader(); r.onload=ev=>{ if($('csv_text')) $('csv_text').value=ev.target.result; parseCSVText(); }; r.readAsText(f); });
  if($('template')) $('template').addEventListener('change', e=>setTemplate(+e.target.value));
  fetchDefaults().then(()=>{
    const b = Math.max(1, Math.min(7, parseInt(val('template')) || 1));
    const ps = $('presetSelect') || $('presetSelectAuto');
    if(!ps || !(+ps.value > 0)) applyBaseDefault(b);
  });
  render(); loadRecords();
});

/* ===== Template presets (Page 1 maker, shared by pages 2 & 3) ===== */
let currentPresetId = 0, presetLogo = '', presetPhoto = '', editingPresetId = 0;

async function loadPresetOptions(selId){
  const s = $(selId); if(!s) return;
  try{
    const r = await fetch('../api/id-cards.php?action=template_list').then(r=>r.json());
    const cur = s.value;
    s.innerHTML = '<option value="0">— No saved template (default design) —</option>';
    (r.templates||[]).forEach(t=>{
      const o = document.createElement('option');
      o.value = t.id; o.textContent = t.name + ' (base ' + t.base + ' — ' + (TPL_NAMES[t.base-1]||'') + ')';
      s.appendChild(o);
    });
    if(cur && [...s.options].some(o=>o.value==cur)) s.value = cur;
  }catch(e){}
}

async function applyPreset(id){
  currentPresetId = id || 0; presetLogo = ''; presetPhoto = '';
  if(!id){ const b = Math.max(1, Math.min(7, parseInt(val('template')) || 1)); if(!applyBaseDefault(b)) render(); return; }
  try{
    const r = await fetch('../api/id-cards.php?action=template_get&id='+id).then(r=>r.json());
    const t = r.template; if(!t) return;
    if($('template')) $('template').value = t.base;
    if($('color1')) $('color1').value = t.color1;
    if($('color2')) $('color2').value = t.color2;
    try{ const l = JSON.parse(t.layout_json || '{}'); layout = (l && typeof l === 'object') ? l : {}; }catch(e){ layout = {}; }
    syncPanel();
    try{ applyLabels(JSON.parse(t.labels_json || '{}')); }catch(e){ applyLabels({}); }
    try{ applyBack(JSON.parse(t.back_json || 'null')); }catch(e){ applyBack(null); }
    try{ applyCode(JSON.parse(t.code_json || 'null')); }catch(e){ applyCode(null); }
    if(t.logo_path){ presetLogo = t.logo_path; logoData = t.logo_path; }
    render();
  }catch(e){}
}

async function savePreset(){
  const name = val('preset_name');
  if(!name){ alert('Give the template a name first.'); return; }
  collectLabels(); collectBack(); collectCode();
  const fd = new FormData();
  fd.append('action','template_save');
  fd.append('id', editingPresetId);
  fd.append('name', name);
  fd.append('base', val('template'));
  fd.append('color1', val('color1'));
  fd.append('color2', val('color2'));
  fd.append('layout_json', JSON.stringify(layout));
  fd.append('labels_json', JSON.stringify(LABELS));
  fd.append('back_json', JSON.stringify(collectBack()));
  fd.append('code_json', JSON.stringify(collectCode()));
  const lf = $('logoFile') && $('logoFile').files[0]; if(lf) fd.append('logo', lf);
  const r = await fetch('../api/id-cards.php', {method:'POST', body:fd}).then(r=>r.json());
  if(r.ok){ alert('Template saved (ID '+r.id+')'); editingPresetId = 0; loadTemplateList(); }
  else alert('Save failed: '+(r.error||'unknown'));
}
function newPreset(){
  editingPresetId = 0;
  if($('preset_name')) $('preset_name').value = '';
  layout = {}; persistLayout(); syncPanel();
  applyLabels({});
  BACK = JSON.parse(JSON.stringify(BACK_DEFAULT)); fillBackEditor();
  CODE = {...CODE_DEFAULT}; fillCodeEditor();
  setTemplate(1); render();
}
async function loadTemplateList(){
  const tb = $('trows'); if(!tb) return;
  const r = await fetch('../api/id-cards.php?action=template_list').then(r=>r.json());
  tb.innerHTML = '';
  (r.templates||[]).forEach(t=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${t.id}</td><td><b>${esc(t.name)}</b></td><td>${t.base} — ${esc(TPL_NAMES[t.base-1]||'')}</td>
      <td class="actions"><button class="ghost" data-a="load">Load</button><button class="ghost" data-a="del">Delete</button></td>`;
    tr.querySelector('[data-a=load]').onclick = async ()=>{
      const g = await fetch('../api/id-cards.php?action=template_get&id='+t.id).then(r=>r.json());
      if(!g.template) return;
      editingPresetId = g.template.id;
      if($('preset_name')) $('preset_name').value = g.template.name;
      if($('template')) $('template').value = g.template.base;
      if($('color1')) $('color1').value = g.template.color1;
      if($('color2')) $('color2').value = g.template.color2;
      try{ const l = JSON.parse(g.template.layout_json || '{}'); layout = (l && typeof l === 'object') ? l : {}; }catch(e){ layout = {}; }
      syncPanel();
      try{ applyLabels(JSON.parse(g.template.labels_json || '{}')); }catch(e){ applyLabels({}); }
      try{ applyBack(JSON.parse(g.template.back_json || 'null')); }catch(e){ applyBack(null); }
      try{ applyCode(JSON.parse(g.template.code_json || 'null')); }catch(e){ applyCode(null); }
      if(g.template.logo_path){ logoData = g.template.logo_path; }
      render(); window.scrollTo({top:0, behavior:'smooth'});
    };
    tr.querySelector('[data-a=del]').onclick = async ()=>{
      if(!confirm('Delete template "'+t.name+'"?')) return;
      await fetch('../api/id-cards.php?action=template_delete&id='+t.id).then(r=>r.json());
      if(editingPresetId === t.id) editingPresetId = 0;
      loadTemplateList();
    };
    tb.appendChild(tr);
  });
  if($('tcount')) $('tcount').textContent = (r.templates||[]).length + ' template(s)';
}

/* ===== Auto production — HR pull (Page 3) ===== */
let HR = [];
function tryPaths(obj, paths){
  for(const p of paths){
    const v = p.split('.').reduce((o,k)=>(o && o[k]!==undefined)?o[k]:undefined, obj);
    if(v !== undefined && v !== null && v !== '') return v;
  }
  return '';
}
function hrMap(){
  return {
    name: val('map_name') || 'name',
    id: val('map_id') || 'staff_id',
    title: val('map_title') || 'job_title',
    dept: val('map_dept') || 'department',
    phone: val('map_phone') || 'phone',
    email: val('map_email') || 'email',
    photo: val('map_photo') || 'photo',
  };
}
function normStaff(o){
  const m = hrMap();
  const alt = {
    name:[m.name,'name','full_name','fullName','employee_name','displayName'],
    id:[m.id,'staff_id','staffId','employee_id','id','empId'],
    title:[m.title,'job_title','jobTitle','title','position','role'],
    dept:[m.dept,'department','dept','unit','team'],
    phone:[m.phone,'phone','phone_number','mobile','tel'],
    email:[m.email,'email','email_address','mail'],
    photo:[m.photo,'photo','photo_url','avatar','image','picture'],
  };
  return {
    name: String(tryPaths(o, alt.name)), id: String(tryPaths(o, alt.id)),
    title: String(tryPaths(o, alt.title)), dept: String(tryPaths(o, alt.dept)),
    phone: String(tryPaths(o, alt.phone)), email: String(tryPaths(o, alt.email)),
    photo: String(tryPaths(o, alt.photo)),
    employee_id: o.employee_id ?? '',
  };
}
async function fetchHR(){
  const url = val('hr_url');
  if(!url){ alert('Enter the HR staff endpoint URL first.'); return; }
  try{
    const r = await fetch(url);
    const j = await r.json();
    const arr = Array.isArray(j) ? j : (Array.isArray(j.data) ? j.data : (Array.isArray(j.staff) ? j.staff : null));
    if(!arr) throw new Error('Response is not a JSON array (looked for array, .data, .staff).');
    HR = arr.map(normStaff).filter(s=>s.name || s.id);
    renderHRTable();
  }catch(e){ alert('HR fetch failed: '+e.message+' — note the HR server must allow CORS, or use the CSV option.'); }
}
function parseCSVLine(line){
  const out = []; let cur = '', q = false;
  for(let i=0;i<line.length;i++){
    const c = line[i];
    if(q){ if(c==='"'){ if(line[i+1]==='"'){ cur+='"'; i++; } else q=false; } else cur+=c; }
    else if(c==='"') q=true;
    else if(c===','){ out.push(cur); cur=''; }
    else cur+=c;
  }
  out.push(cur); return out.map(s=>s.trim());
}
function parseCSVText(){
  const txt = ($('csv_text') ? $('csv_text').value : '').trim();
  if(!txt){ alert('Paste CSV data first.'); return; }
  const lines = txt.split(/\r?\n/).filter(l=>l.trim()!=='');
  if(lines.length < 2){ alert('CSV needs a header row plus at least one data row.'); return; }
  const heads = parseCSVLine(lines[0]).map(h=>h.toLowerCase());
  const idx = n => heads.indexOf(n);
  HR = lines.slice(1).map(l=>{
    const c = parseCSVLine(l);
    const g = n => { const i = idx(n); return i >= 0 ? (c[i]||'') : ''; };
    return {
      name: g('name')||g('full_name')||g('employee'), id: g('staff_id')||g('id')||g('employee_id'),
      title: g('job_title')||g('title')||g('position'), dept: g('department')||g('dept'),
      phone: g('phone')||g('mobile'), email: g('email'), photo: g('photo')||g('photo_url')||g('avatar'),
    };
  }).filter(s=>s.name || s.id);
  renderHRTable();
}
function loadDemoHR(){
  HR = [
    {name:'Sample One', id:'EMP-2026-1001', title:'Project Manager', dept:'Projects', phone:'08000000001', email:'sample1@example.com', photo:''},
    {name:'Sample Two', id:'EMP-2026-1002', title:'Manager', dept:'Sales', phone:'08000000002', email:'sample2@example.com', photo:''},
    {name:'Sample Three', id:'EMP-2026-1003', title:'Officer', dept:'Operations', phone:'08000000003', email:'sample3@example.com', photo:''},
  ];
  renderHRTable();
}
function renderHRTable(){
  const tb = $('hrRows'); if(!tb) return;
  tb.innerHTML = '';
  if(!HR.length){ tb.innerHTML = '<tr><td colspan="3" style="color:var(--mut)">No staff loaded yet.</td></tr>'; }
  HR.forEach((s,i)=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><input type="checkbox" data-hr="${i}" checked style="width:auto"></td>
      <td><b>${esc(s.name)}</b><br><small>${esc(s.id)}</small></td><td>${esc(s.title)}${s.dept?' · '+esc(s.dept):''}</td>`;
    tr.querySelector('input').onchange = ()=>previewHRStaff();
    tb.appendChild(tr);
  });
  if($('hrCount')) $('hrCount').textContent = HR.length;
  previewHRStaff();
}
function hrChecked(){
  return [...document.querySelectorAll('input[data-hr]:checked')].map(c=>HR[+c.dataset.hr]).filter(Boolean);
}
function toggleHRAll(){
  const all = $('hrAll');
  document.querySelectorAll('input[data-hr]').forEach(c=>{ c.checked = all.checked; });
  previewHRStaff();
}
function fillStaffForm(s, company){
  const set = (id,v)=>{ if($(id)) $(id).value = v||''; };
  set('org_name', company); set('staff_name', s.name); set('staff_id', s.id);
  set('job_title', s.title); set('department', s.dept);
  set('phone', s.phone); set('email', s.email);
  photoData = s.photo || ''; logoData = presetLogo || logoData;
  render();
}
function previewHRStaff(){
  const list = hrChecked();
  if(!list.length) return;
  fillStaffForm(list[0], val('auto_company'));
}
async function generateSelected(){
  const list = hrChecked();
  if(!list.length){ alert('Tick at least one staff member.'); return; }
  const company = val('auto_company') || 'Your Company';
  let ok = 0, fail = 0;
  if($('genMsg')) $('genMsg').textContent = 'Generating 0/' + list.length + '...';
  for(const s of list){
    try{
      const fd = new FormData();
      fd.append('action','save'); fd.append('id', 0);
      fd.append('employee_id', s.employee_id || '');
      fd.append('template_id', currentPresetId);
      fd.append('org_name', company); fd.append('org_address', '');
      fd.append('staff_name', s.name); fd.append('staff_id', s.id);
      fd.append('job_title', s.title); fd.append('department', s.dept);
      fd.append('phone', s.phone); fd.append('email', s.email);
      fd.append('template', val('template')); fd.append('layout', 'horizontal');
      fd.append('color1', val('color1')); fd.append('color2', val('color2'));
      ['dob','hire_date','employment_type','emergency_contact','address','principal'].forEach(k=>fd.append(k,''));
      fd.append('layout_json', JSON.stringify(layout));
      fd.append('labels_json', JSON.stringify(typeof LABELS !== 'undefined' ? collectLabels() : {}));
      fd.append('back_json', JSON.stringify(typeof BACK !== 'undefined' ? collectBack() : {}));
      fd.append('code_json', JSON.stringify(typeof CODE !== 'undefined' ? collectCode() : {}));
      fd.append('labels_json', JSON.stringify(LABELS));
      fd.append('back_json', JSON.stringify(BACK));
      fd.append('code_json', JSON.stringify(CODE));
      if(s.photo) fd.append('photo_path', s.photo);
      if(presetLogo) fd.append('logo_path', presetLogo);
      const r = await fetch('../api/id-cards.php', {method:'POST', body:fd}).then(r=>r.json());
      if(r.ok) ok++; else fail++;
    }catch(e){ fail++; }
    if($('genMsg')) $('genMsg').textContent = 'Generating ' + (ok+fail) + '/' + list.length + '...';
  }
  if($('genMsg')) $('genMsg').textContent = 'Done: ' + ok + ' card(s) produced' + (fail ? ', ' + fail + ' failed (name + ID required)' : '') + '. See Saved Staff Records below.';
  loadRecords();
}
