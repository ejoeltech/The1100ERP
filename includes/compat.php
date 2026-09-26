<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Compatibility Helper Functions
 * These functions provide backward compatibility for Phase 3A features
 */

// Check if function exists before defining
if (!function_exists('getRoleFilter')) {
    function getRoleFilter($tableName = 'd')
    {
        // If permissions system not loaded, return empty filter
        return '';
    }
}

if (!function_exists('hasPermission')) {
    function hasPermission($action, $resource = null, $ownerId = null)
    {
        // If permissions system not loaded, allow everything (backward compatibility)
        return true;
    }
}

if (!function_exists('requirePermission')) {
    function requirePermission($action, $resource = null, $ownerId = null)
    {
        // If permissions system not loaded, allow everything (backward compatibility)
        return true;
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin()
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

if (!function_exists('getRoleBadge')) {
    function getRoleBadge($role)
    {
        $badges = [
            'admin' => '<span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">Admin</span>',
            'manager' => '<span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full">Manager</span>',
            'sales_rep' => '<span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">Sales Rep</span>'
        ];
        return $badges[$role] ?? '';
    }
}

if (!function_exists('logAudit')) {
    function logAudit($action, $resourceType, $resourceId = null, $details = [])
    {
        // If audit system not loaded, do nothing (backward compatibility)
        return;
    }
}
?>