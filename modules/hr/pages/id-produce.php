<?php
// HR ID Card Maker — Page 2: Manual Production (type staff details by hand).
require_once '../../../includes/session-check.php';
requirePermission('hr_manage');

require_once '../classes/HR_Employee.php';
$hr = new HR_Employee($pdo);
try {
    $employees = $hr->getAllEmployees(500);
} catch (Exception $e) {
    $employees = [];
}

$pageTitle = 'Produce ID Cards | ' . COMPANY_NAME;
$currentPage = 'hr_idproduce';

$companyLogo = (defined('COMPANY_LOGO') && COMPANY_LOGO) ? '../../../' . COMPANY_LOGO : '../../../uploads/logo/placeholder_logo.png';
$companyName = COMPANY_NAME;
$companyPhone = COMPANY_PHONE;
$companyEmail = COMPANY_EMAIL;
$companyAddr = COMPANY_ADDRESS;
$empMap = [];
foreach ($employees as $e) {
    $empMap[(int)$e['id']] = [
        'full_name' => $e['full_name'] ?? '',
        'employee_code' => $e['employee_code'] ?? '',
        'designation' => $e['designation'] ?? '',
        'department' => $e['department'] ?? '',
        'phone' => $e['phone'] ?? '',
        'email' => $e['email'] ?? '',
        'photo' => $e['passport_path'] ?? '',
        'join_date' => $e['join_date'] ?? '',
        'employment_status' => $e['employment_status'] ?? '',
    ];
}

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
window.ERP_EMPLOYEES = <?= json_encode($empMap) ?>;
</script>

<div id="idmaker">
<div class="im-head">
  <div><h1>Manual <span>Production</span></h1><small>Page 2 — Type staff details or load from employee record</small></div>
  <div><small id="editLabel">New card</small></div>
</div>
<div class="im-main">
  <section class="card" id="editor">
    <h2>Staff Details</h2>
    <label>Load from employee record</label>
    <select id="erp_employee" onchange="loadErpEmployee(this.value)">
      <option value="">— Manual entry —</option>
      <?php foreach ($employees as $e): ?>
        <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars(($e['full_name'] ?? '') . ' (' . ($e['employee_code'] ?? '') . ')') ?></option>
      <?php endforeach; ?>
    </select>
    <input type="hidden" id="employee_id" value="">
    <input type="hidden" id="template" value="1">
    <input type="hidden" id="color1" value="#0d6b3f">
    <input type="hidden" id="color2" value="#8fd14f">
    <input type="hidden" id="layout" value="horizontal">
    <label>Template</label>
    <select id="presetSelect"><option value="0">— No saved template (default design) —</option></select>
    <div class="row2">
      <div><label>Employee name</label><input id="staff_name" value=""></div>
      <div><label>Staff ID</label><input id="staff_id" value=""></div>
    </div>
    <div class="row2">
      <div><label>Job title</label><input id="job_title" value=""></div>
      <div><label>Department</label><input id="department" value=""></div>
    </div>
    <div class="row2">
      <div><label>Date joined</label><input id="hire_date" type="date" value=""></div>
      <div><label>Employment type</label>
        <select id="employment_type">
          <option>Full-time</option><option>Part-time</option><option>Contract</option><option>Intern</option>
        </select>
      </div>
    </div>
    <div class="row2">
      <div><label>Phone</label><input id="phone" value=""></div>
      <div><label>Work email</label><input id="email" value=""></div>
    </div>
    <label>Emergency contact</label><input id="emergency_contact" value="">
    <label>Office / home address</label><input id="address" value="">
    <label>HR Manager / Signatory</label><input id="principal" value="">
    <input type="hidden" id="org_name" value="<?= htmlspecialchars($companyName) ?>">
    <input type="hidden" id="org_address" value="<?= htmlspecialchars($companyAddr) ?>">
    <input type="hidden" id="dob" value="">
    <div class="row2">
      <div><label>Photo upload</label><input id="photoFile" type="file" accept="image/*"></div>
      <div><label>Logo override (optional)</label><input id="logoFile" type="file" accept="image/*"></div>
    </div>
    <?php include 'idmaker-designer.php'; ?>
    <div class="btns">
      <button class="ghost" onclick="randomId()">Random ID</button>
      <button class="primary" onclick="save()">Produce &amp; save card</button>
      <button class="ghost" onclick="resetForm()">Reset</button>
    </div>
  </section>

  <section id="previewWrap">
    <?php include 'idmaker-preview.php'; ?>
    <?php include 'idmaker-records.php'; ?>
  </section>
</div>
</div>
<script>
function loadErpEmployee(id) {
  document.getElementById('employee_id').value = id || '';
  const e = (window.ERP_EMPLOYEES || {})[id];
  const set = (k, v) => { const el = document.getElementById(k); if (el) el.value = v || ''; };
  if (!e) { render(); return; }
  set('staff_name', e.full_name);
  set('staff_id', e.employee_code);
  set('job_title', e.designation);
  set('department', e.department);
  set('phone', e.phone);
  set('email', e.email);
  set('hire_date', e.join_date);
  if (e.employment_status) {
    const map = {full_time: 'Full-time', part_time: 'Part-time', contract: 'Contract', intern: 'Intern'};
    set('employment_type', map[e.employment_status] || e.employment_status);
  }
  if (e.photo) {
    photoData = e.photo;
    const dp = document.getElementById('dPhoto'); if (dp) dp.src = photoData;
    const cp = document.getElementById('cPhoto'); if (cp) cp.src = photoData;
  }
  document.getElementById('org_name').value = window.ERP_COMPANY.name;
  document.getElementById('org_address').value = window.ERP_COMPANY.address;
  render();
}
</script>
<script src="../assets/idmaker/app.js"></script>
<?php include_once '../../../includes/footer.php'; ?>
