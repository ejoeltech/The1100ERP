<?php
/**
 * Permission System
 * Role-based access control for Bluedots ERP
 */

// Get current user's role (with fallback)
function getUserRole()
{
    return $_SESSION['role'] ?? 'admin'; // Default to admin for backward compatibility
}

// Role checkers
function isAdmin()
{
    return getUserRole() === 'admin';
}

function isManager()
{
    return getUserRole() === 'manager';
}

function isSalesRep()
{
    return getUserRole() === 'sales_rep';
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

    // Admin can do everything
    if ($role === 'admin') {
        return true;
    }

    // Permission matrix
    switch ($action) {
        // User Management (Admin only)
        case 'manage_users':
        case 'create_user':
        case 'edit_user':
        case 'delete_user':
        case 'toggle_user_status':
        case 'view_audit_log':
            return $role === 'admin';

        // View All Documents (Admin & Manager)
        case 'view_all_documents':
            return in_array($role, ['admin', 'manager']);

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

        // Delete Documents (Admin & Manager)
        case 'delete_document':
        case 'delete_quote':
        case 'delete_invoice':
        case 'delete_receipt':
        case 'archive_document':
            return in_array($role, ['admin', 'manager']);

        // Convert & Generate (Admin & Manager)
        case 'convert_to_invoice':
        case 'generate_receipt':
            return in_array($role, ['admin', 'manager']);

        // Email Documents (All users)
        case 'send_email':
        case 'email_document':
            return true;

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

    // Admin and Manager can view all
    if (in_array($role, ['admin', 'manager'])) {
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

    // Admin and Manager see all
    if (in_array($role, ['admin', 'manager'])) {
        return '';
    }

    // Sales rep sees only own
    return " AND {$tableName}.created_by = {$userId}";
}

/**
 * Get role display name
 */
function getRoleDisplayName($role)
{
    $roles = [
        'admin' => 'Administrator',
        'manager' => 'Manager',
        'sales_rep' => 'Sales Representative'
    ];
    return $roles[$role] ?? $role;
}

/**
 * Get role badge HTML
 */
function getRoleBadge($role)
{
    $badges = [
        'admin' => '<span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">Admin</span>',
        'manager' => '<span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full">Manager</span>',
        'sales_rep' => '<span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">Sales Rep</span>'
    ];
    return $badges[$role] ?? '';
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
        return $role === 'admin';
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

    // Sales rep cannot delete
    if ($role === 'sales_rep') {
        return false;
    }

    // Admin and Manager can delete
    return in_array($role, ['admin', 'manager']);
}
?>