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
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'save') {
        if (!canEditCards()) out(['ok' => false, 'error' => 'Forbidden: admin/manager only'], 403);
        $photo = saveUpload('photo');
        $logo = saveUpload('logo');
        $f = $_POST;
        $id = intval($f['id'] ?? 0);
        // keep old files / accept preset-provided paths when editing without re-upload
        $oldPhoto = trim($f['photo_path'] ?? '');
        $oldLogo = trim($f['logo_path'] ?? '');
        if ($id > 0) {
            $st = $pdo->prepare('SELECT photo_path, logo_path FROM hr_id_cards WHERE id=?');
            $st->execute([$id]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                if ($oldPhoto === '') $oldPhoto = $r['photo_path'];
                if ($oldLogo === '') $oldLogo = $r['logo_path'];
            }
        }
        $data = [
            'employee_id' => intval($f['employee_id'] ?? 0) ?: null,
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
            'layout' => (($f['layout'] ?? '') === 'vertical') ? 'vertical' : 'horizontal',
            'color1' => preg_match('/^#[0-9a-fA-F]{6}$/', $f['color1'] ?? '') ? $f['color1'] : '#14b8a6',
            'color2' => preg_match('/^#[0-9a-fA-F]{6}$/', $f['color2'] ?? '') ? $f['color2'] : '#0f766e',
            'photo_path' => $photo ?? $oldPhoto,
            'logo_path' => $logo ?? $oldLogo,
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
                template=:template, layout=:layout, color1=:color1, color2=:color2,
                photo_path=:photo_path, logo_path=:logo_path WHERE id=:id';
            $st = $pdo->prepare($sql);
            $data['id'] = $id;
            $st->execute($data);
            out(['ok' => true, 'id' => $id]);
        } else {
            $sql = 'INSERT INTO hr_id_cards (employee_id, org_name, org_address, staff_name, staff_id, dob, department,
                job_title, hire_date, employment_type, emergency_contact,
                phone, email, address, principal, layout_json, labels_json, back_json, code_json, template_id, template, layout,
                color1, color2, photo_path, logo_path)
                VALUES (:employee_id, :org_name, :org_address, :staff_name, :staff_id, :dob, :department,
                :job_title, :hire_date, :employment_type, :emergency_contact,
                :phone, :email, :address, :principal, :layout_json, :labels_json, :back_json, :code_json, :template_id, :template, :layout,
                :color1, :color2, :photo_path, :logo_path)';
            $st = $pdo->prepare($sql);
            $st->execute($data);
            out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        }
    }

    if ($action === 'delete') {
        if (!canEditCards()) out(['ok' => false, 'error' => 'Forbidden: admin/manager only'], 403);
        $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT photo_path, logo_path FROM hr_id_cards WHERE id=?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $st = $pdo->prepare('DELETE FROM hr_id_cards WHERE id=?');
        $st->execute([$id]);
        foreach (['photo_path', 'logo_path'] as $k) {
            $p = $row[$k] ?? '';
            if ($p !== '' && strpos($p, 'modules/hr/assets/uploads/idcards/') === 0) {
                @unlink(dirname(__DIR__, 3) . '/' . $p);
            }
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
        $f = $_POST;
        $id = intval($f['id'] ?? 0);
        $oldLogo = trim($f['logo_path'] ?? '');
        if ($id > 0 && $oldLogo === '') {
            $st = $pdo->prepare('SELECT logo_path FROM hr_id_templates WHERE id=?');
            $st->execute([$id]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) $oldLogo = $r['logo_path'];
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
        ];
        if ($id > 0) {
            $st = $pdo->prepare('UPDATE hr_id_templates SET name=:name, base=:base, color1=:color1, color2=:color2, layout_json=:layout_json, labels_json=:labels_json, back_json=:back_json, code_json=:code_json, logo_path=:logo_path WHERE id=:id');
            $data['id'] = $id;
            $st->execute($data);
            out(['ok' => true, 'id' => $id]);
        }
        $st = $pdo->prepare('INSERT INTO hr_id_templates (name, base, color1, color2, layout_json, labels_json, back_json, code_json, logo_path) VALUES (:name, :base, :color1, :color2, :layout_json, :labels_json, :back_json, :code_json, :logo_path)');
        $st->execute($data);
        out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    if ($action === 'template_delete') {
        if (!canEditCards()) out(['ok' => false, 'error' => 'Forbidden: admin/manager only'], 403);
        $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
        $st = $pdo->prepare('DELETE FROM hr_id_templates WHERE id=?');
        $st->execute([$id]);
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
        $f = $_POST;
        $base = max(1, min(7, intval($f['base'] ?? 1)));
        $oldLogo = trim($f['logo_path'] ?? '');
        if ($logo === null && $oldLogo === '') {
            $st = $pdo->prepare('SELECT logo_path FROM hr_id_template_defaults WHERE base=?');
            $st->execute([$base]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) $oldLogo = $r['logo_path'];
        }
        $st = $pdo->prepare('INSERT INTO hr_id_template_defaults (base, color1, color2, layout_json, labels_json, back_json, code_json, logo_path)
            VALUES (:base, :color1, :color2, :layout_json, :labels_json, :back_json, :code_json, :logo_path)
            ON DUPLICATE KEY UPDATE color1=VALUES(color1), color2=VALUES(color2), layout_json=VALUES(layout_json), labels_json=VALUES(labels_json), back_json=VALUES(back_json), code_json=VALUES(code_json), logo_path=VALUES(logo_path)');
        $st->execute([
            'base' => $base,
            'color1' => preg_match('/^#[0-9a-fA-F]{6}$/', $f['color1'] ?? '') ? $f['color1'] : '#0d6b3f',
            'color2' => preg_match('/^#[0-9a-fA-F]{6}$/', $f['color2'] ?? '') ? $f['color2'] : '#8fd14f',
            'layout_json' => cleanJson($f['layout_json'] ?? ''),
            'labels_json' => cleanJson($f['labels_json'] ?? ''),
            'back_json' => cleanJson($f['back_json'] ?? ''),
            'code_json' => cleanJson($f['code_json'] ?? ''),
            'logo_path' => $logo ?? $oldLogo,
        ]);
        out(['ok' => true, 'base' => $base]);
    }

    if ($action === 'default_reset') {
        if (!canEditCards()) out(['ok' => false, 'error' => 'Forbidden: admin/manager only'], 403);
        $base = max(1, min(7, intval($_GET['base'] ?? $_POST['base'] ?? 1)));
        $st = $pdo->prepare('DELETE FROM hr_id_template_defaults WHERE base=?');
        $st->execute([$base]);
        out(['ok' => true]);
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
                   e.passport_path
            FROM hr_employees e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN hr_departments d ON e.department_id = d.id
            LEFT JOIN hr_designations des ON e.designation_id = des.id
            ORDER BY u.full_name ASC
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
