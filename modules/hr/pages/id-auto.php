<?php
// HR ID Card Maker — Page 3: Auto Production (bulk from HR records or CSV).
require_once '../../../includes/session-check.php';
requireLogin();

$pageTitle = 'Bulk ID Production | ' . COMPANY_NAME;
$currentPage = 'hr_idcards';

$companyLogo = (defined('COMPANY_LOGO') && COMPANY_LOGO) ? '../../../' . COMPANY_LOGO : '../../../uploads/logo/placeholder_logo.png';
$companyName = COMPANY_NAME;
$companyPhone = COMPANY_PHONE;
$companyEmail = COMPANY_EMAIL;
$companyAddr = COMPANY_ADDRESS;

include_once '../../../includes/header.php';
?>
<link rel="stylesheet" href="../assets/idmaker/style.css?v=<?= time() ?>">
<script src="../assets/idmaker/vendor/html2canvas.min.js"></script>
<script src="../assets/idmaker/vendor/qrcode.min.js"></script>
<script src="../assets/idmaker/vendor/JsBarcode.all.min.js"></script>
<script>
window.IDM_FALLBACK_PHOTO = 'https://ui-avatars.com/api/?name=Staff&size=220&background=0e4b8f&color=fff';
window.IDM_FALLBACK_LOGO = '<?= addslashes($companyLogo) ?>';
window.ERP_COMPANY = {
  name: <?= json_encode(COMPANY_NAME) ?>,
  address: <?= json_encode(COMPANY_ADDRESS) ?>,
  phone: <?= json_encode(COMPANY_PHONE) ?>,
  email: <?= json_encode(COMPANY_EMAIL) ?>,
  website: <?= json_encode(defined('COMPANY_WEBSITE') ? COMPANY_WEBSITE : '') ?>,
  logo: '<?= addslashes($companyLogo) ?>'
};
</script>

<div id="idmaker">
<div class="im-head">
  <div><h1>Auto <span>Production</span></h1><small>Page 3 — Pull staff from HR, tick who needs cards, generate in bulk</small></div>
  <div><small id="editLabel">Bulk mode</small></div>
</div>
<div class="im-main">
  <section class="card" id="editor">
    <h2>Staff Source</h2>
    <input type="hidden" id="template" value="1">
    <input type="hidden" id="color1" value="#0d6b3f">
    <input type="hidden" id="color2" value="#8fd14f">
    <input type="hidden" id="layout" value="horizontal">
    <input type="hidden" id="org_name" value="<?= htmlspecialchars($companyName) ?>">
    <input type="hidden" id="org_address" value="<?= htmlspecialchars($companyAddr) ?>">
    <input type="hidden" id="staff_name" value="Sample Staff">
    <input type="hidden" id="staff_id" value="EMP-0000">
    <input type="hidden" id="job_title" value="">
    <input type="hidden" id="department" value="">
    <input type="hidden" id="phone" value="<?= htmlspecialchars($companyPhone) ?>">
    <input type="hidden" id="email" value="<?= htmlspecialchars($companyEmail) ?>">
    <input type="hidden" id="hire_date" value="">
    <input type="hidden" id="employment_type" value="Full-time">
    <input type="hidden" id="emergency_contact" value="">
    <input type="hidden" id="dob" value="">
    <input type="hidden" id="address" value="">
    <input type="hidden" id="principal" value="">
    <label>Template</label>
    <select id="presetSelectAuto"><option value="0">— No saved template (default design) —</option></select>

    <h2 style="margin-top:18px">HR Source A — This ERP</h2>
    <p style="color:var(--mut);font-size:12.5px;margin:0 0 6px">Loads all staff directly from HR records — no URL or CSV needed.</p>
    <div class="btns"><button class="ghost" onclick="fetchErpStaff()" style="width:100%">Load ERP staff</button></div>

    <h2 style="margin-top:18px">HR Source B — External REST API</h2>
    <label>Staff endpoint (must return a JSON array)</label>
    <input id="hr_url" placeholder="https://hr.example.com/api/staff">
    <div class="row2">
      <div><label>Name field</label><input id="map_name" value="name"></div>
      <div><label>ID field</label><input id="map_id" value="staff_id"></div>
    </div>
    <div class="row2">
      <div><label>Title field</label><input id="map_title" value="job_title"></div>
      <div><label>Dept field</label><input id="map_dept" value="department"></div>
    </div>
    <div class="row2">
      <div><label>Phone field</label><input id="map_phone" value="phone"></div>
      <div><label>Email field</label><input id="map_email" value="email"></div>
    </div>
    <label>Photo URL field (optional)</label><input id="map_photo" value="photo">
    <div class="btns"><button class="ghost" onclick="fetchHR()">Fetch from HR</button></div>

    <h2 style="margin-top:18px">HR Source C — CSV</h2>
    <label>Paste CSV (headers: name,staff_id,job_title,department,phone,email,photo) or choose a file</label>
    <textarea id="csv_text" rows="4" placeholder="name,staff_id,job_title,department,phone,email"></textarea>
    <div class="row2" style="margin-top:8px">
      <div><input id="csv_file" type="file" accept=".csv,text/csv"></div>
      <div><button class="ghost" onclick="parseCSVText()" style="width:100%">Load CSV</button></div>
    </div>
    <div class="btns"><button class="ghost" onclick="loadDemoHR()">Load demo HR data</button></div>

    <h2 style="margin-top:18px">Company name (applied to all)</h2>
    <label>Company name (applied to all)</label>
    <input id="auto_company" value="<?= htmlspecialchars($companyName) ?>">

    <h2 style="margin-top:18px">Staff Pulled (<span id="hrCount">0</span>)</h2>
    <table><thead><tr><th><input type="checkbox" id="hrAll" checked style="width:auto" onclick="toggleHRAll()"></th><th>Employee</th><th>Title</th></tr></thead>
    <tbody id="hrRows"><tr><td colspan="3" style="color:var(--mut)">No staff loaded yet.</td></tr></tbody></table>
    <div class="btns">
      <button class="primary" onclick="generateSelected()" style="flex:1">Generate cards for selected</button>
    </div>
    <p><small id="genMsg"></small></p>
  </section>

  <section id="previewWrap">
    <?php include 'idmaker-preview.php'; ?>
    <?php include 'idmaker-records.php'; ?>
  </section>
</div>
</div>
<script>
async function fetchErpStaff() {
  try {
    const r = await fetch('../api/id-cards.php?action=hr_staff').then(r => r.json());
    if (!r.ok) { alert('Failed: ' + (r.error || 'unknown')); return; }
    HR = (r.staff || []).map(s => ({
      name: s.name || '', id: s.staff_id || '', title: s.title || '',
      dept: s.dept || '', phone: s.phone || '', email: s.email || '',
      photo: s.photo || '', employee_id: s.employee_id || ''
    }));
    renderHRTable();
  } catch (e) { alert('ERP staff fetch failed: ' + e.message); }
}
</script>
<script src="../assets/idmaker/app.js"></script>
<?php include_once '../../../includes/footer.php'; ?>
