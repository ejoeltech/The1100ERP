<?php
// HR ID Card Maker API (integrated). Tables: hr_id_cards, hr_id_templates, hr_id_template_defaults.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
require_once '../../../includes/session-check.php';
require_once '../../../includes/audit.php';

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

require_once __DIR__ . '/../includes/uploads.php';

function cleanJson($s) {
    if (is_array($s)) return '{}';
    $s = substr(trim($s ?? ''), 0, 20000);
    if ($s === '' || $s === 'null') return '{}';
    $j = json_decode($s, true);
    if (!is_array($j) || !$j) return '{}';
    return json_encode($j);
}

/** Trim a scalar POST field and enforce the database column length. */
function postText($f, $key, $max, $label) {
    $v = $f[$key] ?? '';
    if (is_array($v)) out(['ok' => false, 'error' => $label . ' must be text'], 422);
    $v = trim((string)$v);
    $len = function_exists('mb_strlen') ? mb_strlen($v) : strlen($v);
    if ($len > $max) out(['ok' => false, 'error' => $label . ' is too long (max ' . $max . ' characters)'], 422);
    return $v;
}

/** Coerce a POST field to an integer without array warnings. */
function postInt($f, $key, $default = 0) {
    $v = $f[$key] ?? $default;
    return is_scalar($v) ? (int)$v : (int)$default;
}

function auditIdCard($action, $type, $id = null, $details = []) {
    try {
        if (function_exists('logAudit')) logAudit($action, $type, $id, $details);
    } catch (Throwable $e) {
        error_log('ID card audit failed: ' . $e->getMessage());
    }
}

function saveUpload($key, &$error = null) {
    $error = null;
    if (empty($_FILES[$key])) return null;
    $err = $_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) return null;
    if ($err !== UPLOAD_ERR_OK) {
        $error = 'The ' . $key . ' upload failed (server error ' . (int)$err . ').';
        return null;
    }
    // WP7: content-validated (MIME + getimagesize) via central helper.
    require_once __DIR__ . '/../../../includes/security.php';
    try {
        $ext = validateImageUpload($_FILES[$key]);
    } catch (Exception $e) {
        $error = 'The ' . $key . ' file is not a valid JPG, PNG, GIF or WebP image.';
        return null;
    }
    $dir = ensureUploadDir(__DIR__ . '/../assets/uploads/idcards');
    if (!is_dir($dir) || !is_writable($dir)) {
        $error = 'The ID-card upload folder is not writable.';
        return null;
    }
    $name = $key . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$key]['tmp_name'], $dir . '/' . $name)) {
        $error = 'The ' . $key . ' file could not be saved.';
        return null;
    }
    return 'modules/hr/assets/uploads/idcards/' . $name;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? 'list');
    if ($method === 'POST' && $action === '') {
        out(['ok' => false, 'error' => 'Missing action. The upload may exceed the server limit.'], 400);
    }

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
        $f = $_POST;
        $id = postInt($f, 'id');
        $employeeId = postInt($f, 'employee_id') ?: null;

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
            'org_name' => postText($f, 'org_name', 120, 'Organisation name'),
            'org_address' => postText($f, 'org_address', 255, 'Organisation address'),
            'staff_name' => postText($f, 'staff_name', 120, 'Employee name'),
            'staff_id' => postText($f, 'staff_id', 60, 'Staff ID'),
            'dob' => postText($f, 'dob', 30, 'Date of birth'),
            'department' => postText($f, 'department', 120, 'Department'),
            'job_title' => postText($f, 'job_title', 120, 'Job title'),
            'hire_date' => postText($f, 'hire_date', 30, 'Date joined'),
            'employment_type' => postText($f, 'employment_type', 40, 'Employment type'),
            'emergency_contact' => postText($f, 'emergency_contact', 60, 'Emergency contact'),
            'phone' => postText($f, 'phone', 30, 'Phone'),
            'email' => postText($f, 'email', 120, 'Email'),
            'address' => postText($f, 'address', 255, 'Address'),
            'principal' => postText($f, 'principal', 120, 'Signatory'),
            'layout_json' => cleanJson($f['layout_json'] ?? ''),
            'labels_json' => cleanJson($f['labels_json'] ?? ''),
            'back_json' => cleanJson($f['back_json'] ?? ''),
            'code_json' => cleanJson($f['code_json'] ?? ''),
            'template' => max(1, min(7, postInt($f, 'template', 1))),
            'template_id' => postInt($f, 'template_id') ?: null,
            'color1' => preg_match('/^#[0-9a-fA-F]{6}$/', postText($f, 'color1', 10, 'Colour')) ? postText($f, 'color1', 10, 'Colour') : '#14b8a6',
            'color2' => preg_match('/^#[0-9a-fA-F]{6}$/', postText($f, 'color2', 10, 'Colour')) ? postText($f, 'color2', 10, 'Colour') : '#0f766e',
        ];
        if ($data['staff_name'] === '' || $data['staff_id'] === '') {
            out(['ok' => false, 'error' => 'Employee name and Staff ID are required'], 422);
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            out(['ok' => false, 'error' => 'Invalid email address'], 422);
        }

        // Validate first, then store files. New files are removed again if the
        // database write fails.
        $newFiles = [];
        $cleanupUploads = function () use (&$newFiles) {
            foreach ($newFiles as $p) {
                $file = idCardDeletablePath($p);
                if ($file !== null) @unlink($file);
            }
        };
        $uploadError = null;
        $photo = saveUpload('photo', $uploadError);
        if ($uploadError) { $cleanupUploads(); out(['ok' => false, 'error' => $uploadError], 422); }
        if ($photo) $newFiles[] = $photo;
        $logo = saveUpload('logo', $uploadError);
        if ($uploadError) { $cleanupUploads(); out(['ok' => false, 'error' => $uploadError], 422); }
        if ($logo) $newFiles[] = $logo;
        $signature = saveUpload('signature', $uploadError);
        if ($uploadError) { $cleanupUploads(); out(['ok' => false, 'error' => $uploadError], 422); }
        if ($signature) $newFiles[] = $signature;

        $data['photo_path'] = $photo ?? $oldPhoto;
        $data['logo_path'] = $logo ?? $oldLogo;
        $data['signature_path'] = $signature ?? $oldSignature;

        try {
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
                $id = (int)$pdo->lastInsertId();
            }
        } catch (Throwable $e) {
            $cleanupUploads();
            throw $e;
        }
        auditIdCard($existing ? 'id_card_update' : 'id_card_create', 'hr_id_card', $id, [
            'staff_id' => $data['staff_id'],
            'employee_id' => $employeeId,
        ]);
        out(['ok' => true, 'id' => $id]);
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
        auditIdCard('id_card_delete', 'hr_id_card', $id);
        out(['ok' => true]);
    }

    if ($action === 'get') {
        $id = intval($_GET['id'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM hr_id_cards WHERE id=?');
        $st->execute([$id]);
        $card = $st->fetch(PDO::FETCH_ASSOC);
        if (!$card) out(['ok' => false, 'error' => 'Card not found'], 404);
        auditIdCard('id_card_view', 'hr_id_card', $id);
        out(['ok' => true, 'card' => $card]);
    }

    // ---- Saved templates ----
    if ($action === 'template_save') {
        if (!canEditCards()) out(['ok' => false, 'error' => 'Forbidden: admin/manager only'], 403);
        $f = $_POST;
        $id = postInt($f, 'id');
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
        $name = postText($f, 'name', 120, 'Template name');
        if ($name === '') out(['ok' => false, 'error' => 'Template name is required'], 422);
        $data = [
            'name' => $name,
            'base' => max(1, min(7, postInt($f, 'base', 1))),
            'color1' => preg_match('/^#[0-9a-fA-F]{6}$/', postText($f, 'color1', 10, 'Colour')) ? postText($f, 'color1', 10, 'Colour') : '#0d6b3f',
            'color2' => preg_match('/^#[0-9a-fA-F]{6}$/', postText($f, 'color2', 10, 'Colour')) ? postText($f, 'color2', 10, 'Colour') : '#8fd14f',
            'layout_json' => cleanJson($f['layout_json'] ?? ''),
            'labels_json' => cleanJson($f['labels_json'] ?? ''),
            'back_json' => cleanJson($f['back_json'] ?? ''),
            'code_json' => cleanJson($f['code_json'] ?? ''),
        ];

        $newFiles = [];
        $cleanupUploads = function () use (&$newFiles) {
            foreach ($newFiles as $p) {
                $file = idCardDeletablePath($p);
                if ($file !== null) @unlink($file);
            }
        };
        $uploadError = null;
        $logo = saveUpload('logo', $uploadError);
        if ($uploadError) { $cleanupUploads(); out(['ok' => false, 'error' => $uploadError], 422); }
        if ($logo) $newFiles[] = $logo;
        $signature = saveUpload('signature', $uploadError);
        if ($uploadError) { $cleanupUploads(); out(['ok' => false, 'error' => $uploadError], 422); }
        if ($signature) $newFiles[] = $signature;
        $data['logo_path'] = $logo ?? $oldLogo;
        $data['signature_path'] = $signature ?? $oldSignature;

        try {
            if ($id > 0) {
                $st = $pdo->prepare('UPDATE hr_id_templates SET name=:name, base=:base, color1=:color1, color2=:color2, layout_json=:layout_json, labels_json=:labels_json, back_json=:back_json, code_json=:code_json, logo_path=:logo_path, signature_path=:signature_path WHERE id=:id');
                $data['id'] = $id;
                $st->execute($data);
            } else {
                $st = $pdo->prepare('INSERT INTO hr_id_templates (name, base, color1, color2, layout_json, labels_json, back_json, code_json, logo_path, signature_path) VALUES (:name, :base, :color1, :color2, :layout_json, :labels_json, :back_json, :code_json, :logo_path, :signature_path)');
                $st->execute($data);
                $id = (int)$pdo->lastInsertId();
            }
        } catch (Throwable $e) {
            $cleanupUploads();
            throw $e;
        }
        auditIdCard($existingTemplate ? 'id_template_update' : 'id_template_create', 'hr_id_template', $id, ['name' => $name]);
        out(['ok' => true, 'id' => $id]);
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
        auditIdCard('id_template_delete', 'hr_id_template', $id);
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
        $f = $_POST;
        $base = max(1, min(7, postInt($f, 'base', 1)));
        $oldLogo = cleanStoredImagePath($f['logo_path'] ?? '');
        $oldSignature = cleanStoredImagePath($f['signature_path'] ?? '');
        $st = $pdo->prepare('SELECT logo_path, signature_path FROM hr_id_template_defaults WHERE base=?');
        $st->execute([$base]);
        if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            if ($oldLogo === '') $oldLogo = cleanStoredImagePath($r['logo_path'] ?? '');
            if ($oldSignature === '') $oldSignature = cleanStoredImagePath($r['signature_path'] ?? '');
        }

        $params = [
            'base' => $base,
            'color1' => preg_match('/^#[0-9a-fA-F]{6}$/', postText($f, 'color1', 10, 'Colour')) ? postText($f, 'color1', 10, 'Colour') : '#0d6b3f',
            'color2' => preg_match('/^#[0-9a-fA-F]{6}$/', postText($f, 'color2', 10, 'Colour')) ? postText($f, 'color2', 10, 'Colour') : '#8fd14f',
            'layout_json' => cleanJson($f['layout_json'] ?? ''),
            'labels_json' => cleanJson($f['labels_json'] ?? ''),
            'back_json' => cleanJson($f['back_json'] ?? ''),
            'code_json' => cleanJson($f['code_json'] ?? ''),
        ];

        $newFiles = [];
        $cleanupUploads = function () use (&$newFiles) {
            foreach ($newFiles as $p) {
                $file = idCardDeletablePath($p);
                if ($file !== null) @unlink($file);
            }
        };
        $uploadError = null;
        $logo = saveUpload('logo', $uploadError);
        if ($uploadError) { $cleanupUploads(); out(['ok' => false, 'error' => $uploadError], 422); }
        if ($logo) $newFiles[] = $logo;
        $signature = saveUpload('signature', $uploadError);
        if ($uploadError) { $cleanupUploads(); out(['ok' => false, 'error' => $uploadError], 422); }
        if ($signature) $newFiles[] = $signature;

        $params['logo_path'] = $logo ?? $oldLogo;
        $params['signature_path'] = $signature ?? $oldSignature;
        try {
            $st = $pdo->prepare('INSERT INTO hr_id_template_defaults (base, color1, color2, layout_json, labels_json, back_json, code_json, logo_path, signature_path)
                VALUES (:base, :color1, :color2, :layout_json, :labels_json, :back_json, :code_json, :logo_path, :signature_path)
                ON DUPLICATE KEY UPDATE color1=VALUES(color1), color2=VALUES(color2), layout_json=VALUES(layout_json), labels_json=VALUES(labels_json), back_json=VALUES(back_json), code_json=VALUES(code_json), logo_path=VALUES(logo_path), signature_path=VALUES(signature_path)');
            $st->execute($params);
        } catch (Throwable $e) {
            $cleanupUploads();
            throw $e;
        }
        auditIdCard('id_default_update', 'hr_id_template_default', $base);
        out(['ok' => true, 'base' => $base]);
    }

    if ($action === 'default_reset') {
        if (!canEditCards()) out(['ok' => false, 'error' => 'Forbidden: admin/manager only'], 403);
        $base = max(1, min(7, postInt($_GET + $_POST, 'base', 1)));
        $st = $pdo->prepare('SELECT logo_path, signature_path FROM hr_id_template_defaults WHERE base=?');
        $st->execute([$base]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) out(['ok' => true, 'base' => $base, 'message' => 'No default design to reset']);
        $pdo->prepare('DELETE FROM hr_id_template_defaults WHERE base=?')->execute([$base]);
        foreach (['logo_path', 'signature_path'] as $k) {
            deleteCardImageIfUnused($pdo, $row[$k] ?? '');
        }
        auditIdCard('id_default_reset', 'hr_id_template_default', $base);
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
        auditIdCard('hr_staff_feed_read', 'hr_employee', null, ['count' => count($staff)]);
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
    error_log('HR ID-card API error: ' . $e->getMessage());
    out(['ok' => false, 'error' => 'The ID-card request failed. Please try again.'], 500);
}
