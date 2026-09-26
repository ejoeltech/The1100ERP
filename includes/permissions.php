<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Permission System — groups + per-user overrides on top of legacy role matrix.
 * - super_admin role: bypasses everything.
 * - Per-user override (deny wins over grant) beats group permissions.
 * - Group permissions beat the legacy role matrix below.
 */

function getPermissionCatalog()
{
    return [
        'Users & Access' => [
            'manage_users' => 'Manage users',
            'create_user' => 'Create user',
            'edit_user' => 'Edit user',
            'delete_user' => 'Delete user',
            'toggle_user_status' => 'Enable/disable user',
            'manage_access' => 'Manage groups & permissions',
        ],
        'Documents' => [
            'view_all_documents' => 'View all documents',
            'create_quote' => 'Create quotes',
            'create_document' => 'Create documents',
            'edit_quote' => 'Edit quotes',
            'edit_invoice' => 'Edit invoices',
            'edit_document' => 'Edit documents',
            'edit_finalized' => 'Edit finalized documents',
            'delete_quote' => 'Delete quotes',
            'delete_invoice' => 'Delete invoices',
            'delete_receipt' => 'Delete receipts',
            'delete_document' => 'Delete documents',
            'archive_document' => 'Archive documents',
            'convert_to_invoice' => 'Convert quotes to invoices',
            'generate_receipt' => 'Generate receipts',
        ],
        'Communication' => [
            'send_email' => 'Send emails',
            'email_document' => 'Email documents',
        ],
        'System' => [
            'manage_settings' => 'Manage settings',
            'view_audit_log' => 'View audit log',
            'export_data' => 'Export data',
        ],
        'Modules' => [
            'manage_store' => 'Manage store inventory',
            'manage_accessories' => 'Manage accessories store',
            'manage_hr' => 'Manage HR module',
            'manage_payments' => 'Manage payments',
        ],
        'Human Resources' => [
            'hr_view' => 'View HR directory and attendance',
            'hr_manage' => 'Manage employees, documents and ID cards',
            'leave_manage' => 'Approve and manage all leave requests',
            'payroll_view' => 'View payroll and payslips',
            'payroll_run' => 'Run payroll generation',
            'recruitment_manage' => 'Manage recruitment candidates',
        ],
        'Dashboard & Profile' => [
            'view_system_dashboard' => 'View system dashboard',
            'view_team_dashboard' => 'View team dashboard',
            'view_personal_dashboard' => 'View personal dashboard',
            'edit_own_profile' => 'Edit own profile',
            'change_own_password' => 'Change own password',
        ],
    ];
}

function getAllPermissionKeys()
{
    $keys = [];
    foreach (getPermissionCatalog() as $group) {
        foreach ($group as $key => $label) {
            $keys[] = $key;
        }
    }
    return $keys;
}

// Get current user's role (fail-closed default)
function getUserRole()
{
    return $_SESSION['role'] ?? 'viewer';
}

// Role checkers
function isSuperAdmin()
{
    return getUserRole() === 'super_admin';
}

function isAdmin()
{
    return in_array(getUserRole(), ['super_admin', 'admin'], true);
}

function isManager()
{
    return getUserRole() === 'manager';
}

function isSalesRep()
{
    return getUserRole() === 'sales_rep';
}

function isAccountant()
{
    return getUserRole() === 'accountant';
}

function isViewer()
{
    return getUserRole() === 'viewer';
}

/**
 * Per-request cache of the current user's effective permissions.
 * Call clearUserPermissionCache($userId) after writing overrides/group perms
 * so same-request re-checks see fresh data.
 */
function clearUserPermissionCache($userId = null)
{
    // Reset the static cache inside getUserEffectivePermissions via a sentinel call.
    // Implemented by re-invoking with a cache-bust flag stored in $GLOBALS.
    if ($userId === null) {
        $GLOBALS['_perm_cache_bust_all'] = microtime(true);
    } else {
        $GLOBALS['_perm_cache_bust_' . (int)$userId] = microtime(true);
    }
}

function getUserEffectivePermissions($userId = null)
{
    static $cache = [];
    static $cacheStamp = [];
    global $pdo;

    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? null;
    }
    if (!$userId) {
        return ['group' => [], 'overrides' => []];
    }
    $stamp = ($GLOBALS['_perm_cache_bust_' . (int)$userId] ?? null) . '|' . ($GLOBALS['_perm_cache_bust_all'] ?? null);
    if (isset($cache[$userId]) && ($cacheStamp[$userId] ?? null) === $stamp) {
        return $cache[$userId];
    }

    $result = ['group' => [], 'overrides' => []];
    try {
        // Group permissions via users.group_id
        $stmt = $pdo->prepare("
            SELECT gp.permission_key
            FROM users u
            JOIN group_permissions gp ON gp.group_id = u.group_id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $result['group'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Per-user overrides
        $stmt = $pdo->prepare("SELECT permission_key, granted FROM user_permission_overrides WHERE user_id = ?");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            $result['overrides'][$row['permission_key']] = (int)$row['granted'];
        }
    } catch (Exception $e) {
        // Tables missing (pre-migration): fall through to legacy matrix
    }

    $cache[$userId] = $result;
    $cacheStamp[$userId] = $stamp;
    return $result;
}

/**
 * Check if user has permission for an action
 */
function hasPermission($action, $resource = null, $ownerId = null)
{
    $role = getUserRole();
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        return false;
    }

    // Super admin can do everything
    if ($role === 'super_admin') {
        return true;
    }

    // 1. Per-user override: explicit deny beats everything except super_admin.
    //    An explicit grant still honors ownership scoping (a sales_rep granted
    //    edit_quote may edit OWN documents only — never everyone's).
    $effective = getUserEffectivePermissions($userId);
    if (array_key_exists($action, $effective['overrides'])) {
        if ($effective['overrides'][$action] !== 1) {
            return false;
        }
        return hasScopedGrant($action, $resource, $ownerId, $role, $userId);
    }

    // 2. Group permission (or wildcard)
    if (in_array($action, $effective['group'], true) || in_array('*', $effective['group'], true)) {
        return hasScopedGrant($action, $resource, $ownerId, $role, $userId);
    }

    // 3. Legacy role matrix fallback (pre-migration or users without group)
    return hasLegacyRolePermission($action, $resource, $ownerId, $role, $userId);
}

/**
 * A granted permission (group or override) still honors ownership rules:
 * finalized documents need edit_finalized; sales_rep edits are own-only.
 */
function hasScopedGrant($action, $resource, $ownerId, $role, $userId)
{
    if (in_array($action, ['edit_document', 'edit_quote', 'edit_invoice'], true)) {
        if ($resource && isset($resource['status']) && $resource['status'] === 'finalized') {
            return hasPermission('edit_finalized', $resource, $ownerId);
        }
        if ($role === 'sales_rep') {
            return $ownerId == $userId;
        }
    }
    return true;
}

/**
 * Original role matrix, kept as fallback.
 */
function hasLegacyRolePermission($action, $resource, $ownerId, $role, $userId)
{
    switch ($action) {
        // User Management (Admin only)
        case 'manage_users':
        case 'create_user':
        case 'edit_user':
        case 'delete_user':
        case 'toggle_user_status':
        case 'view_audit_log':
        case 'manage_access':
            return $role === 'admin';

        // View All Documents (Admin, Manager, Accountant)
        case 'view_all_documents':
            return in_array($role, ['admin', 'manager', 'accountant']);

        // Create Documents (All users)
        case 'create_quote':
        case 'create_document':
            return true;

        // Edit Documents
        case 'edit_document':
        case 'edit_quote':
        case 'edit_invoice':
            // If finalized, only admin can edit
            if ($resource && isset($resource['status']) && $resource['status'] === 'finalized') {
                return $role === 'admin';
            }
            // Sales rep can only edit own documents
            if ($role === 'sales_rep') {
                return $ownerId == $userId;
            }
            // Admin and Manager can edit any draft
            return true;

        // Edit Finalized (Admin only)
        case 'edit_finalized':
            return $role === 'admin';

        // Delete Documents (Admin only)
        case 'delete_document':
        case 'delete_quote':
        case 'delete_invoice':
        case 'delete_receipt':
        case 'archive_document':
        // Settings Management (Admin only)
        case 'manage_settings':
        case 'export_data':
            return $role === 'admin';

        // Convert & Generate (Admin, Manager, Accountant)
        case 'convert_to_invoice':
        case 'generate_receipt':
            return in_array($role, ['admin', 'manager', 'accountant']);

        // Store / Accessories / HR / Payments (Admin, Manager)
        case 'manage_store':
        case 'manage_accessories':
        case 'manage_hr':
        case 'manage_payments':
            return in_array($role, ['admin', 'manager']);

        // HR directory + attendance (Admin, Manager)
        case 'hr_view':
        case 'hr_manage':
        case 'leave_manage':
        case 'recruitment_manage':
            return in_array($role, ['admin', 'manager']);

        // Payroll viewing (Admin, Manager, Accountant); running (Admin, Accountant)
        case 'payroll_view':
            return in_array($role, ['admin', 'manager', 'accountant']);
        case 'payroll_run':
            return in_array($role, ['admin', 'accountant']);

        // Email Documents (all roles except read-only viewer)
        case 'send_email':
        case 'email_document':
            return $role !== 'viewer';

        // Profile Management (Own profile only)
        case 'edit_own_profile':
        case 'change_own_password':
            return true;

        // Dashboard Views
        case 'view_system_dashboard':
            return $role === 'admin';

        case 'view_team_dashboard':
            return in_array($role, ['admin', 'manager']);

        case 'view_personal_dashboard':
            return true;

        default:
            return false;
    }
}

/**
 * Require permission or deny access
 */
function requirePermission($action, $resource = null, $ownerId = null)
{
    if (!hasPermission($action, $resource, $ownerId)) {
        http_response_code(403);
        // Calculate base path
        $base_path = '';
        if (file_exists('config.php')) {
            $base_path = '.';
        } elseif (file_exists('../config.php')) {
            $base_path = '..';
        } elseif (file_exists('../../config.php')) {
            $base_path = '../..';
        }

        // JSON for API calls
        if ((defined('IS_API') && IS_API)
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
            || (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false)
        ) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Forbidden: insufficient permissions']);
            exit;
        }

        echo '<!DOCTYPE html>
<html>
<head>
    <title>Access Denied</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-md p-8 max-w-md text-center">
        <div class="text-red-600 mb-4">
            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Access Denied</h1>
        <p class="text-gray-600 mb-6">You do not have permission to perform this action.</p>
        <a href="' . $base_path . '/dashboard.php" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-block">
            Go to Dashboard
        </a>
    </div>
</body>
</html>';
        exit;
    }
}

/**
 * Check if user can view a specific document
 */
function canViewDocument($document)
{
    $role = getUserRole();
    $userId = $_SESSION['user_id'] ?? null;

    // Admin, Manager, Accountant, and Viewer can view all
    if (in_array($role, ['super_admin', 'admin', 'manager', 'accountant', 'viewer'])) {
        return true;
    }

    // Sales rep can only view own documents
    return isset($document['created_by']) && $document['created_by'] == $userId;
}

/**
 * Get role-based SQL filter
 */
function getRoleFilter($tableName = 'd')
{
    $role = getUserRole();
    $userId = $_SESSION['user_id'] ?? 0;

    // Admin, Manager, Accountant, and Viewer see all
    if (in_array($role, ['super_admin', 'admin', 'manager', 'accountant', 'viewer'])) {
        return ['sql' => '', 'params' => []];
    }

    // Sales rep sees only own
    return [
        'sql' => " AND {$tableName}.created_by = ?",
        'params' => [$userId]
    ];
}
/**
 * Get role display name
 */
function getRoleDisplayName($role)
{
    $roles = [
        'super_admin' => 'Super Administrator',
        'admin' => 'Administrator',
        'manager' => 'Manager',
        'sales_rep' => 'Sales Representative',
        'accountant' => 'Accountant',
        'viewer' => 'Viewer'
    ];
    return $roles[$role] ?? $role;
}

/**
 * Get role badge HTML
 */
function getRoleBadge($role)
{
    $badges = [
        'super_admin' => '<span class="px-3 py-1 bg-purple-100 text-purple-800 text-xs font-semibold rounded-full">Super Admin</span>',
        'admin' => '<span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">Admin</span>',
        'manager' => '<span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full">Manager</span>',
        'sales_rep' => '<span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">Sales Rep</span>',
        'accountant' => '<span class="px-3 py-1 bg-purple-100 text-purple-800 text-xs font-semibold rounded-full">Accountant</span>',
        'viewer' => '<span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded-full">Viewer</span>'
    ];
    return $badges[$role] ?? '';
}

/**
 * Resolve the caller's own HR employee id from the session (WP3).
 * Returns int or null. Self-service endpoints must use this — never a
 * request parameter — to scope data to the caller.
 */
function getMyEmployeeId($userId = null)
{
    global $pdo;
    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? null;
    }
    if (!$userId) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT id FROM hr_employees WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check if user can edit specific document
 */
function canEditDocument($document)
{
    $role = getUserRole();
    $userId = $_SESSION['user_id'] ?? null;

    // Check if finalized
    if (isset($document['status']) && $document['status'] === 'finalized') {
        return in_array($role, ['super_admin', 'admin'], true);
    }

    // Sales rep can only edit own
    if ($role === 'sales_rep') {
        return isset($document['created_by']) && $document['created_by'] == $userId;
    }

    // Admin and Manager can edit any draft
    return true;
}

/**
 * Check if user can delete specific document
 */
function canDeleteDocument($document)
{
    $role = getUserRole();

    // Only Admin can delete
    return in_array($role, ['super_admin', 'admin'], true);
}
