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
$preselectEmployeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

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
        'signature' => $e['signature_path'] ?? '',
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
<script nonce="<?php echo function_exists('cspNonce') ? cspNonce() : ''; ?>">
window.IDM_FALLBACK_PHOTO = '../assets/idmaker/staff-placeholder.svg';
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
window.IDM_PRESELECT_EMPLOYEE = <?= (int)$preselectEmployeeId ?>;
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
        <option value="<?= (int)$e['id'] ?>" <?= $preselectEmployeeId === (int)$e['id'] ? 'selected' : '' ?>><?= htmlspecialchars(($e['full_name'] ?? '') . ' (' . ($e['employee_code'] ?? '') . ')') ?></option>
      <?php endforeach; ?>
    </select>
    <p id="hrDataWarning" style="display:none;color:#b45309;font-size:12px;margin:4px 0 0">
      This employee has no department or job title on file. Update the employee record before producing the card.
    </p>
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
    <label>HR signature (optional — shown behind the card)</label>
    <input id="signatureFile" type="file" accept="image/*">
    <p style="color:var(--mut);font-size:12px;margin:4px 0 0">Leave blank to use the selected employee’s HR signature, or the template default.</p>
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
<script nonce="<?php echo function_exists('cspNonce') ? cspNonce() : ''; ?>">
function loadErpEmployee(id) {
  const e = (window.ERP_EMPLOYEES || {})[id];
  const set = (k, v) => { const el = document.getElementById(k); if (el) el.value = v || ''; };
  const warn = document.getElementById('hrDataWarning');
  if (warn) warn.style.display = 'none';
  // Drop any pending file pick from the previous card so it cannot be uploaded
  // onto the newly selected employee.
  ['photoFile', 'logoFile', 'signatureFile'].forEach(k => {
    const el = document.getElementById(k); if (el) el.value = '';
  });
  document.getElementById('employee_id').value = e ? id : '';
  if (!e) {
    // Explicit "Manual entry" must not keep the previous employee's identity.
    ['staff_name', 'staff_id', 'job_title', 'department', 'phone', 'email', 'hire_date'].forEach(k => set(k, ''));
    if (document.getElementById('employment_type')) document.getElementById('employment_type').value = 'Full-time';
    photoData = '';
    signaturePath = presetSignature || '';
    signatureData = assetUrl(signaturePath);
    render();
    return;
  }
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
  // Always assign (including empty) so the previous employee's face is cleared.
  photoData = e.photo || '';
  if (e.signature) {
    signaturePath = e.signature;
    signatureData = assetUrl(e.signature);
  } else {
    signaturePath = presetSignature || '';
    signatureData = assetUrl(signaturePath);
  }
  document.getElementById('org_name').value = window.ERP_COMPANY.name;
  document.getElementById('org_address').value = window.ERP_COMPANY.address;
  if (warn) warn.style.display = (!e.designation || !e.department) ? 'block' : 'none';
  render();
}
</script>
<script src="../assets/idmaker/app.js"></script>
<?php include_once '../../../includes/footer.php'; ?>
