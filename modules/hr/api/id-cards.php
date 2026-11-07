<?php
// HR ID Card Maker API (integrated). Tables: hr_id_cards, hr_id_templates, hr_id_template_defaults.
header('Content-Type: application/json; charset=utf-8');
require_once '../../../includes/session-check.php';

function out($d, $code = 200) { http_response_code($code); echo json_encode($d); exit; }

if (!isset($_SESSION['user_id'])) {
    out(['ok' => false, 'error' => 'Unauthorized'], 401);
}
// WP3: card data, templates and the staff feed expose employee PII
// (photos, phones, emails). Every action needs hr_manage; the calling
// pages are all gated the same way, so no legitimate flow breaks.
if (function_exists('hasPermission') && !hasPermission('hr_manage')) {
    out(['ok' => false, 'error' => 'Forbidden: HR management permission required'], 403);
}
global $pdo;

function canEditCards() {
    return function_exists('hasPermission') && hasPermission('hr_manage');
}

/**
 * Validate a stored image path before it is written to the database or used
 * for file deletion. Only files directly inside the known upload folders are
 * accepted, so a crafted path cannot escape with "..", backslashes or NULs.
 * Returns the normalized path, or '' when the value is not allowed.
 */
function cleanStoredImagePath($path, $allowLogo = true) {
    $path = trim((string)$path);
    if ($path === '' || strlen($path) > 255 || strpos($path, "\0") !== false || strpos($path, '\\') !== false) {
        return '';
    }
    $prefixes = [
        'modules/hr/assets/uploads/idcards/',
        'modules/hr/assets/uploads/employees/',
        '../assets/uploads/employees/',
        'modules/hr/assets/uploads/onboarding/',
        '../assets/uploads/onboarding/',
    ];
    if ($allowLogo) {
        $prefixes[] = '../../../uploads/logo/';
        $prefixes[] = 'uploads/logo/';
    }
    foreach ($prefixes as $prefix) {
        if (strpos($path, $prefix) !== 0) continue;
        $name = substr($path, strlen($prefix));
        // Exactly one file name: no slashes, no "." / "..", no hidden dotfile.
        if ($name !== '' && basename($name) === $name
            && $name[0] !== '.'
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name)) {
            return $prefix . $name;
        }
    }
    return '';
}

/**
 * Resolve an ID-card upload path for deletion. Only the idcards folder may be
 * removed (employee/template files can be shared), and the real path must stay
 * inside that folder after symlink/.. resolution.
 */
function idCardDeletablePath($path) {
    $path = cleanStoredImagePath($path, false);
    if ($path === '' || strpos($path, 'modules/hr/assets/uploads/idcards/') !== 0) {
        return null;
    }
    $base = realpath(__DIR__ . '/../assets/uploads/idcards');
    $real = realpath(dirname(__DIR__, 3) . '/' . $path);
    if ($base === false || $real === false) {
        return null;
    }
    $basePrefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return strpos($real, $basePrefix) === 0 ? $real : null;
}

/**
 * Check whether an upload path is still used by any card, template or default
 * row before deleting it.
 */
function imagePathReferenced($pdo, $path) {
    if ($path === '') return true;
    foreach ([
        ['hr_id_cards', 'photo_path'],
        ['hr_id_cards', 'logo_path'],
        ['hr_id_cards', 'signature_path'],
        ['hr_id_templates', 'logo_path'],
        ['hr_id_templates', 'signature_path'],
        ['hr_id_template_defaults', 'logo_path'],
        ['hr_id_template_defaults', 'signature_path'],
    ] as [$table, $column]) {
        $st = $pdo->prepare("SELECT 1 FROM `$table` WHERE `$column` = ? LIMIT 1");
        $st->execute([$path]);
        if ($st->fetchColumn()) return true;
    }
    return false;
}

function deleteCardImageIfUnused($pdo, $path) {
    $file = idCardDeletablePath($path);
    if ($file === null) return;
    if (!imagePathReferenced($pdo, $path)) @unlink($file);
}

function cleanJson($s) {
    $s = substr(trim($s ?? ''), 0, 20000);
    if ($s === '' || $s === 'null') return '{}';
    $j = json_decode($s, true);
    if (!is_array($j) || !$j) return '{}';
    return json_encode($j);
}

function saveUpload($key) {
    if (empty($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) return null;
    // WP7: content-validated (MIME + getimagesize) via central helper.
    require_once __DIR__ . '/../../../includes/security.php';
    try {
        $ext = validateImageUpload($_FILES[$key]);
    } catch (Exception $e) {
        return null;
    }
    $dir = ensureUploadDir(__DIR__ . '/../assets/uploads/idcards');
    $name = $key . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$key]['tmp_name'], $dir . '/' . $name)) return null;
    return 'modules/hr/assets/uploads/idcards/' . $name;
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';

    // WP4: state-changing actions must be POST. Reads stay on GET.
    $mutationActions = ['save', 'delete', 'template_save', 'template_delete', 'default_save', 'default_reset'];
    if (in_array($action, $mutationActions, true)) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            out(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        // session-check already enforces this on POST; keep it explicit here
        // so the endpoint stays safe if its bootstrap ever changes.
        requireCsrf();
    }

    if ($action === 'save') {
        if (!canEditCards()) out(['ok' => false, 'error' => 'Forbidden: admin/manager only'], 403);
        $photo = saveUpload('photo');
        $logo = saveUpload('logo');
        $signature = saveUpload('signature');
        $f = $_POST;
        $id = intval($f['id'] ?? 0);
        $employeeId = intval($f['employee_id'] ?? 0) ?: null;

        // Editing an existing card must target a real row. For a new
        // employee-linked card, reuse the existing row instead of inserting a
        // duplicate when bulk generation is run twice.
        $existing = null;
        if ($id > 0) {
            $st = $pdo->prepare('SELECT * FROM hr_id_cards WHERE id=?');
            $st->execute([$id]);
            $existing = $st->fetch(PDO::FETCH_ASSOC);
            if (!$existing) out(['ok' => false, 'error' => 'Card not found'], 404);
        } elseif ($employeeId !== null) {
            $st = $pdo->prepare('SELECT * FROM hr_id_cards WHERE employee_id=? ORDER BY id DESC LIMIT 1');
            $st->execute([$employeeId]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $id = (int)$r['id'];
                $existing = $r;
            }
        }

        // keep old files / accept preset-provided paths when editing without re-upload
        $oldPhoto = cleanStoredImagePath($f['photo_path'] ?? '');
        $oldLogo = cleanStoredImagePath($f['logo_path'] ?? '');
        $oldSignature = cleanStoredImagePath($f['signature_path'] ?? '');
        if ($existing) {
            if ($oldPhoto === '') $oldPhoto = cleanStoredImagePath($existing['photo_path'] ?? '');
            if ($oldLogo === '') $oldLogo = cleanStoredImagePath($existing['logo_path'] ?? '');
            if ($oldSignature === '') $oldSignature = cleanStoredImagePath($existing['signature_path'] ?? '');
        }
        $data = [
            'employee_id' => $employeeId,
            'org_name' => trim($f['org_name'] ?? ''),
            'org_address' => trim($f['org_address'] ?? ''),
            'staff_name' => trim($f['staff_name'] ?? ''),
            'staff_id' => trim($f['staff_id'] ?? ''),
            'dob' => trim($f['dob'] ?? ''),
            'department' => trim($f['department'] ?? ''),
            'job_title' => trim($f['job_title'] ?? ''),
            'hire_date' => trim($f['hire_date'] ?? ''),
            'employment_type' => trim($f['employment_type'] ?? ''),
            'emergency_contact' => trim($f['emergency_contact'] ?? ''),
            'phone' => trim($f['phone'] ?? ''),
            'email' => trim($f['email'] ?? ''),
            'address' => trim($f['address'] ?? ''),
            'principal' => trim($f['principal'] ?? ''),
            'layout_json' => cleanJson($f['layout_json'] ?? ''),
            'labels_json' => cleanJson($f['labels_json'] ?? ''),
            'back_json' => cleanJson($f['back_json'] ?? ''),
            'code_json' => cleanJson($f['code_json'] ?? ''),
            'template' => max(1, min(7, intval($f['template'] ?? 1))),
            'template_id' => intval($f['template_id'] ?? 0) ?: null,
            'color1' => preg_match('/^#[0-9a-fA-F]{6}$/', $f['color1'] ?? '') ? $f['color1'] : '#14b8a6',
            'color2' => preg_match('/^#[0-9a-fA-F]{6}$/', $f['color2'] ?? '') ? $f['color2'] : '#0f766e',
            'photo_path' => $photo ?? $oldPhoto,
            'logo_path' => $logo ?? $oldLogo,
            'signature_path' => $signature ?? $oldSignature,
        ];
        if ($data['staff_name'] === '' || $data['staff_id'] === '') {
            out(['ok' => false, 'error' => 'Employee name and Staff ID are required'], 422);
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            out(['ok' => false, 'error' => 'Invalid email address'], 422);
        }
        if ($id > 0) {
            $sql = 'UPDATE hr_id_cards SET employee_id=:employee_id, org_name=:org_name, org_address=:org_address, staff_name=:staff_name,
                staff_id=:staff_id, dob=:dob, department=:department,
                job_title=:job_title, hire_date=:hire_date, employment_type=:employment_type,
                emergency_contact=:emergency_contact,
                phone=:phone, email=:email, address=:address, principal=:principal,
                layout_json=:layout_json, labels_json=:labels_json, back_json=:back_json, code_json=:code_json, template_id=:template_id,
                template=:template, color1=:color1, color2=:color2,
                photo_path=:photo_path, logo_path=:logo_path, signature_path=:signature_path WHERE id=:id';
            $st = $pdo->prepare($sql);
            $data['id'] = $id;
            $st->execute($data);
            out(['ok' => true, 'id' => $id]);
        } else {
            $sql = 'INSERT INTO hr_id_cards (employee_id, org_name, org_address, staff_name, staff_id, dob, department,
                job_title, hire_date, employment_type, emergency_contact,
                phone, email, address, principal, layout_json, labels_json, back_json, code_json, template_id, template,
                color1, color2, photo_path, logo_path, signature_path)
                VALUES (:employee_id, :org_name, :org_address, :staff_name, :staff_id, :dob, :department,
                :job_title, :hire_date, :employment_type, :emergency_contact,
                :phone, :email, :address, :principal, :layout_json, :labels_json, :back_json, :code_json, :template_id, :template,
                :color1, :color2, :photo_path, :logo_path, :signature_path)';
            $st = $pdo->prepare($sql);
            $st->execute($data);
            out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        }
    }

    if ($action === 'delete') {
        if (!canEditCards()) out(['ok' => false, 'error' => 'Forbidden: admin/manager only'], 403);
        $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT photo_path, logo_path, signature_path FROM hr_id_cards WHERE id=?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) out(['ok' => false, 'error' => 'Card not found'], 404);
        $st = $pdo->prepare('DELETE FROM hr_id_cards WHERE id=?');
        $st->execute([$id]);
        foreach (['photo_path', 'logo_path', 'signature_path'] as $k) {
            deleteCardImageIfUnused($pdo, $row[$k] ?? '');
        }
        out(['ok' => true]);
    }

    if ($action === 'get') {
        $id = intval($_GET['id'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM hr_id_cards WHERE id=?');
        $st->execute([$id]);
        out(['ok' => true, 'card' => $st->fetch(PDO::FETCH_ASSOC)]);
    }

    // ---- Saved templates ----
    if ($action === 'template_save') {
        if (!canEditCards()) out(['ok' => false, 'error' => 'Forbidden: admin/manager only'], 403);
        $logo = saveUpload('logo');
        $signature = saveUpload('signature');
        $f = $_POST;
        $id = intval($f['id'] ?? 0);
        $oldLogo = cleanStoredImagePath($f['logo_path'] ?? '');
        $oldSignature = cleanStoredImagePath($f['signature_path'] ?? '');
        $existingTemplate = null;
        if ($id > 0) {
            $st = $pdo->prepare('SELECT logo_path, signature_path FROM hr_id_templates WHERE id=?');
            $st->execute([$id]);
            $existingTemplate = $st->fetch(PDO::FETCH_ASSOC);
            if (!$existingTemplate) out(['ok' => false, 'error' => 'Template not found'], 404);
            if ($oldLogo === '') $oldLogo = cleanStoredImagePath($existingTemplate['logo_path'] ?? '');
            if ($oldSignature === '') $oldSignature = cleanStoredImagePath($existingTemplate['signature_path'] ?? '');
        }
        $name = trim($f['name'] ?? '');
        if ($name === '') out(['ok' => false, 'error' => 'Template name is required'], 422);
        $data = [
            'name' => $name,
            'base' => max(1, min(7, intval($f['base'] ?? 1))),
            'color1' => preg_match('/^#[0-9a-fA-F]{6}$/', $f['color1'] ?? '') ? $f['color1'] : '#0d6b3f',
            'color2' => preg_match('/^#[0-9a-fA-F]{6}$/', $f['color2'] ?? '') ? $f['color2'] : '#8fd14f',
            'layout_json' => cleanJson($f['layout_json'] ?? ''),
            'labels_json' => cleanJson($f['labels_json'] ?? ''),
            'back_json' => cleanJson($f['back_json'] ?? ''),
            'code_json' => cleanJson($f['code_json'] ?? ''),
            'logo_path' => $logo ?? $oldLogo,
            'signature_path' => $signature ?? $oldSignature,
        ];
        if ($id > 0) {
            $st = $pdo->prepare('UPDATE hr_id_templates SET name=:name, base=:base, color1=:color1, color2=:color2, layout_json=:layout_json, labels_json=:labels_json, back_json=:back_json, code_json=:code_json, logo_path=:logo_path, signature_path=:signature_path WHERE id=:id');
            $data['id'] = $id;
            $st->execute($data);
            out(['ok' => true, 'id' => $id]);
        }
        $st = $pdo->prepare('INSERT INTO hr_id_templates (name, base, color1, color2, layout_json, labels_json, back_json, code_json, logo_path, signature_path) VALUES (:name, :base, :color1, :color2, :layout_json, :labels_json, :back_json, :code_json, :logo_path, :signature_path)');
        $st->execute($data);
        out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    if ($action === 'template_delete') {
        if (!canEditCards()) out(['ok' => false, 'error' => 'Forbidden: admin/manager only'], 403);
        $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT logo_path, signature_path FROM hr_id_templates WHERE id=?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) out(['ok' => false, 'error' => 'Template not found'], 404);
        // Saved cards keep working with the factory design instead of pointing
        // at a deleted template.
        $pdo->prepare('UPDATE hr_id_cards SET template_id = NULL WHERE template_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM hr_id_templates WHERE id=?')->execute([$id]);
        foreach (['logo_path', 'signature_path'] as $k) {
            deleteCardImageIfUnused($pdo, $row[$k] ?? '');
        }
        out(['ok' => true]);
    }

    if ($action === 'template_get') {
        $id = intval($_GET['id'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM hr_id_templates WHERE id=?');
        $st->execute([$id]);
        out(['ok' => true, 'template' => $st->fetch(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'template_list') {
        $st = $pdo->query('SELECT * FROM hr_id_templates ORDER BY id DESC LIMIT 200');
        out(['ok' => true, 'templates' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // ---- Per-base default designs ----
    if ($action === 'default_save') {
        if (!canEditCards()) out(['ok' => false, 'error' => 'Forbidden: admin/manager only'], 403);
        $logo = saveUpload('logo');
        $signature = saveUpload('signature');
        $f = $_POST;
        $base = max(1, min(7, intval($f['base'] ?? 1)));
        $oldLogo = cleanStoredImagePath($f['logo_path'] ?? '');
        $oldSignature = cleanStoredImagePath($f['signature_path'] ?? '');
        if ($logo === null || $oldLogo === '') {
            $st = $pdo->prepare('SELECT logo_path, signature_path FROM hr_id_template_defaults WHERE base=?');
            $st->execute([$base]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $oldLogo = cleanStoredImagePath($r['logo_path'] ?? '');
                if ($oldSignature === '') $oldSignature = cleanStoredImagePath($r['signature_path'] ?? '');
            }
        }
        $st = $pdo->prepare('INSERT INTO hr_id_template_defaults (base, color1, color2, layout_json, labels_json, back_json, code_json, logo_path, signature_path)
            VALUES (:base, :color1, :color2, :layout_json, :labels_json, :back_json, :code_json, :logo_path, :signature_path)
            ON DUPLICATE KEY UPDATE color1=VALUES(color1), color2=VALUES(color2), layout_json=VALUES(layout_json), labels_json=VALUES(labels_json), back_json=VALUES(back_json), code_json=VALUES(code_json), logo_path=VALUES(logo_path), signature_path=VALUES(signature_path)');
        $st->execute([
            'base' => $base,
            'color1' => preg_match('/^#[0-9a-fA-F]{6}$/', $f['color1'] ?? '') ? $f['color1'] : '#0d6b3f',
            'color2' => preg_match('/^#[0-9a-fA-F]{6}$/', $f['color2'] ?? '') ? $f['color2'] : '#8fd14f',
            'layout_json' => cleanJson($f['layout_json'] ?? ''),
            'labels_json' => cleanJson($f['labels_json'] ?? ''),
            'back_json' => cleanJson($f['back_json'] ?? ''),
            'code_json' => cleanJson($f['code_json'] ?? ''),
            'logo_path' => $logo ?? $oldLogo,
            'signature_path' => $signature ?? $oldSignature,
        ]);
        out(['ok' => true, 'base' => $base]);
    }

    if ($action === 'default_reset') {
        if (!canEditCards()) out(['ok' => false, 'error' => 'Forbidden: admin/manager only'], 403);
        $base = max(1, min(7, intval($_GET['base'] ?? $_POST['base'] ?? 1)));
        $st = $pdo->prepare('SELECT logo_path, signature_path FROM hr_id_template_defaults WHERE base=?');
        $st->execute([$base]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) out(['ok' => true, 'base' => $base, 'message' => 'No default design to reset']);
        $pdo->prepare('DELETE FROM hr_id_template_defaults WHERE base=?')->execute([$base]);
        foreach (['logo_path', 'signature_path'] as $k) {
            deleteCardImageIfUnused($pdo, $row[$k] ?? '');
        }
        out(['ok' => true, 'base' => $base]);
    }

    if ($action === 'default_list') {
        $st = $pdo->query('SELECT * FROM hr_id_template_defaults ORDER BY base ASC');
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) $out[(int)$r['base']] = $r;
        out(['ok' => true, 'defaults' => $out]);
    }

    // ---- HR staff feed for bulk generation (normalized keys) ----
    if ($action === 'hr_staff') {
        $rows = $pdo->query("
            SELECT e.id, e.employee_code, e.join_date, e.employment_status,
                   u.full_name, u.email, u.phone,
                   d.name AS department, des.title AS designation,
                   e.passport_path, e.signature_path
            FROM hr_employees e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN hr_departments d ON e.department_id = d.id
            LEFT JOIN hr_designations des ON e.designation_id = des.id
            WHERE u.is_active = 1
              AND (e.termination_date IS NULL OR e.termination_date = '' OR e.termination_date > CURDATE())
            ORDER BY u.full_name ASC
            LIMIT 1000
        ")->fetchAll(PDO::FETCH_ASSOC);
        $mapStatus = ['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'intern' => 'Intern'];
        $staff = [];
        foreach ($rows as $r) {
            $staff[] = [
                'name' => $r['full_name'] ?? '',
                'staff_id' => $r['employee_code'] ?? '',
                'title' => $r['designation'] ?? '',
                'dept' => $r['department'] ?? '',
                'phone' => $r['phone'] ?? '',
                'email' => $r['email'] ?? '',
                'photo' => $r['passport_path'] ?? '',
                'signature' => $r['signature_path'] ?? '',
                'join_date' => $r['join_date'] ?? '',
                'employment_type' => $mapStatus[$r['employment_status']] ?? '',
                'employee_id' => (int)$r['id'],
            ];
        }
        out(['ok' => true, 'staff' => $staff]);
    }

    // list
    $q = trim($_GET['q'] ?? '');
    if ($q !== '') {
        $st = $pdo->prepare('SELECT * FROM hr_id_cards WHERE staff_name LIKE ? OR staff_id LIKE ? OR department LIKE ? OR job_title LIKE ? ORDER BY id DESC LIMIT 200');
        $st->execute(["%$q%", "%$q%", "%$q%", "%$q%"]);
    } else {
        $st = $pdo->query('SELECT * FROM hr_id_cards ORDER BY id DESC LIMIT 200');
    }
    out(['ok' => true, 'cards' => $st->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    out(['ok' => false, 'error' => $e->getMessage()], 500);
}
