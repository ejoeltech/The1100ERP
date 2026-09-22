<?php
// HR ID Card Maker — Page 1: Template Maker (designs, layout, labels, back content, codes).
require_once '../../../includes/session-check.php';
requireLogin();

$pageTitle = 'ID Template Maker | ' . COMPANY_NAME;
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
</script>

<div id="idmaker">
<div class="im-head">
  <div><h1>ID Template <span>Maker</span></h1><small>Page 1 — Designs, layout &amp; labels. Preview uses sample staff data.</small></div>
  <div><small id="editLabel">New template</small></div>
</div>
<div class="im-main">
  <section class="card" id="editor">
    <h2>Template Design</h2>
    <label>Template name</label>
    <input id="preset_name" value="My Company Card" placeholder="e.g. Staff Card">
    <div class="row2">
      <div><label>Base design</label>
        <select id="template">
          <option value="1">Template 1 — Emerald Geo</option>
          <option value="2">Template 2 — Teal Oval</option>
          <option value="3">Template 3 — Hospital Blue</option>
          <option value="4">Template 4 — Purple Dots</option>
          <option value="5">Template 5 — Lime Block</option>
          <option value="6">Template 6 — Royal Frame</option>
          <option value="7">Template 7 — Corporate White</option>
        </select>
      </div>
      <div><label>&nbsp;</label><button class="ghost" onclick="newPreset()" style="width:100%">New template</button></div>
    </div>
    <div class="row2">
      <div><label>Color 1</label><input id="color1" type="color" value="#0d6b3f"></div>
      <div><label>Color 2</label><input id="color2" type="color" value="#8fd14f"></div>
    </div>
    <div class="btns">
      <button class="ghost" onclick="saveDefaultDesign()" title="Save the current colors, layout, labels and logo as this template's default">Set as default design</button>
      <button class="ghost" onclick="resetFactoryDefault()" title="Discard the saved default and restore the factory look">Restore factory</button>
    </div>
    <input type="hidden" id="layout" value="horizontal">
    <div class="row2">
      <div><label>Sample photo (preview)</label><input id="photoFile" type="file" accept="image/*"></div>
      <div><label>Company logo (saved)</label><input id="logoFile" type="file" accept="image/*"></div>
    </div>
    <?php include 'idmaker-designer.php'; ?>
    <!-- sample data for preview -->
    <input type="hidden" id="org_name" value="<?= htmlspecialchars($companyName) ?>">
    <input type="hidden" id="org_address" value="<?= htmlspecialchars($companyAddr) ?>">
    <input type="hidden" id="staff_name" value="Sample Staff">
    <input type="hidden" id="staff_id" value="EMP-2026-1001">
    <input type="hidden" id="job_title" value="Sample Role">
    <input type="hidden" id="department" value="Operations">
    <input type="hidden" id="hire_date" value="2023-03-01">
    <input type="hidden" id="employment_type" value="Full-time">
    <input type="hidden" id="phone" value="<?= htmlspecialchars($companyPhone) ?>">
    <input type="hidden" id="email" value="<?= htmlspecialchars($companyEmail) ?>">
    <input type="hidden" id="address" value="<?= htmlspecialchars($companyAddr) ?>">
    <input type="hidden" id="dob" value="">
    <input type="hidden" id="emergency_contact" value="">
    <input type="hidden" id="principal" value="">
    <h2 style="margin-top:18px">Customize Layout</h2>
    <p style="color:var(--mut);font-size:12.5px;margin:0 0 6px">Drag <b>any</b> element directly on the cards, or grab the teal resize handle. Fine-tune with sliders — layout saves with the template.</p>
    <label>Element</label>
    <select id="elSelect"></select>
    <label>Position X — <span id="valX">0</span>px</label>
    <input id="rngX" type="range" min="-200" max="200" value="0">
    <label>Position Y — <span id="valY">0</span>px</label>
    <input id="rngY" type="range" min="-200" max="200" value="0">
    <label><span id="sizeLabel">Size</span> — <span id="valSize"></span></label>
    <input id="rngSize" type="range" min="10" max="300" value="100">
    <div class="row2" style="align-items:end">
      <div><label><input id="elVisible" type="checkbox" checked style="width:auto"> Visible</label></div>
      <div><button class="ghost" onclick="resetLayout()" style="width:100%">Reset layout</button></div>
    </div>
    <h2 style="margin-top:18px">Field Labels</h2>
    <p style="color:var(--mut);font-size:12.5px;margin:0 0 6px">Rename any field — labels apply to the production forms and the card back, and save with the template.</p>
    <div id="labelGrid" class="row2"></div>
    <h2 style="margin-top:18px">Card Back Content</h2>
    <p style="color:var(--mut);font-size:12.5px;margin:0 0 6px">Edit the terms heading and body, and add your own custom fields. A field shows its label plus a live value — pick a staff detail or fixed text.</p>
    <label>Back heading</label>
    <input id="back_title" value="TERMS & CONDITIONS">
    <label>Back body (blank line = new paragraph)</label>
    <textarea id="back_body" rows="4">Identification: Carry this ID card at all times during working hours for identification purposes.

Authorized Use: This card is strictly for official use and must not be shared or used for unauthorized purposes.</textarea>
    <label>Custom fields</label>
    <div id="fieldRows"></div>
    <div class="btns"><button class="ghost" onclick="addBackField()">+ Add field</button></div>
    <h2 style="margin-top:18px">ID Code</h2>
    <div class="row2">
      <div><label>Front code</label>
        <select id="code_front"><option value="barcode">Barcode</option><option value="qr">QR code</option><option value="none">None</option></select>
      </div>
      <div><label>Back code</label>
        <select id="code_back"><option value="barcode">Barcode</option><option value="qr">QR code</option><option value="both">Barcode + QR</option><option value="none">None</option></select>
      </div>
    </div>
    <label>Code content — use {id} {name} {title} {dept} {phone} {email} {company} (empty = staff ID)</label>
    <input id="code_content" value="{id}" placeholder="{id}">
    <div class="btns">
      <button class="primary" onclick="savePreset()" style="flex:1">Save template</button>
    </div>
    <h2 style="margin-top:18px">Saved Templates</h2>
    <p><small id="tcount"></small></p>
    <table><thead><tr><th>#</th><th>Name</th><th>Base</th><th></th></tr></thead>
    <tbody id="trows"></tbody></table>
  </section>

  <section id="previewWrap">
    <?php include 'idmaker-preview.php'; ?>
  </section>
</div>
</div>
<script src="../assets/idmaker/app.js"></script>
<?php include_once '../../../includes/footer.php'; ?>
