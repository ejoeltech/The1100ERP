<?php
include '../includes/session-check.php';

// Check if user is admin (simplified)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$pageTitle = 'Audit Log - ' . COMPANY_NAME;

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filters
$filterAction = $_GET['action'] ?? '';
$filterResourceType = $_GET['resource_type'] ?? '';
$filterUserId = $_GET['user_id'] ?? '';

// Build query
$where = [];
$params = [];

if ($filterAction) {
    $where[] = "al.action = ?";
    $params[] = $filterAction;
}

if ($filterResourceType) {
    $where[] = "al.resource_type = ?";
    $params[] = $filterResourceType;
}

if ($filterUserId) {
    $where[] = "al.user_id = ?";
    $params[] = (int) $filterUserId;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Initialize variables
$totalRecords = 0;
$totalPages = 0;
$auditLogs = [];
$actions = [];
$resourceTypes = [];
$users = [];
$error_message = null;

// Try to get data from database
try {
    // Get total count
    $countQuery = "SELECT COUNT(*) FROM audit_log al $whereClause";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();
    $totalPages = ceil($totalRecords / $perPage);

    // Get audit logs
    $query = "
        SELECT 
            al.*,
            u.full_name as user_name,
            u.username
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        $whereClause
        ORDER BY al.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $auditLogs = $stmt->fetchAll();

    // Get filter options
    $actionsStmt = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action");
    $actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

    $resourceTypesStmt = $pdo->query("SELECT DISTINCT resource_type FROM audit_log WHERE resource_type IS NOT NULL ORDER BY resource_type");
    $resourceTypes = $resourceTypesStmt->fetchAll(PDO::FETCH_COLUMN);

    $usersStmt = $pdo->query("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $usersStmt->fetchAll();

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Simple formatAuditAction function
function formatAuditAction($action)
{
    $actions = [
        'create' => 'Created',
        'edit' => 'Edited',
        'update' => 'Updated',
        'delete' => 'Deleted',
        'archive' => 'Archived',
        'restore' => 'Restored',
        'finalize' => 'Finalized',
        'convert' => 'Converted',
        'generate_receipt' => 'Generated Receipt',
        'login' => 'Logged In',
        'logout' => 'Logged Out',
        'password_change' => 'Changed Password',
        'status_change' => 'Status Changed',
        'email_sent' => 'Email Sent'
    ];
    return $actions[$action] ?? ucfirst(str_replace('_', ' ', $action));
}

include '../includes/header.php';
?>

<?php if ($error_message): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <h2 class="text-lg font-bold text-red-900 mb-2">⚠️ Audit Log Table Error</h2>
            <p class="text-red-800 mb-4">There was an error accessing the audit log.</p>
            <details class="mb-4">
                <summary class="cursor-pointer text-red-700 font-semibold">Show Error Details</summary>
                <p class="text-sm text-red-700 mt-2 p-3 bg-red-100 rounded"><?php echo htmlspecialchars($error_message); ?>
                </p>
            </details>
            <div class="bg-yellow-50 border border-yellow-300 rounded p-4 mt-4">
                <p class="text-yellow-900 font-semibold mb-2">Solution:</p>
                <p class="text-yellow-800 text-sm">The audit_log table may not exist. Please create it with this SQL:</p>
                <pre class="bg-gray-900 text-green-400 p-3 rounded mt-2 text-xs overflow-x-auto">CREATE TABLE IF NOT EXISTS audit_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    action VARCHAR(50) NOT NULL,
                    resource_type VARCHAR(50) NULL,
                    resource_id INT NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent TEXT NULL,
                    details JSON NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_action (action),
                    INDEX idx_resource (resource_type, resource_id),
                    INDEX idx_created_at (created_at)
                );</pre>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php';
    exit; ?>
<?php endif; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Audit Log</h1>
            <p class="text-gray-600 mt-1">System activity and change history</p>
        </div>
        <div class="mt-4 md:mt-0">
            <span class="text-sm text-gray-600">
                Total Records: <strong><?php echo number_format($totalRecords); ?></strong>
            </span>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Action</label>
                <select name="action"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $filterAction === $action ? 'selected' : ''; ?>>
                            <?php echo formatAuditAction($action); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Resource Type</label>
                <select name="resource_type"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="">All Types</option>
                    <?php foreach ($resourceTypes as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filterResourceType === $type ? 'selected' : ''; ?>>
                            <?php echo ucfirst($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">User</label>
                <select name="user_id"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $filterUserId == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                            (<?php echo htmlspecialchars($user['username']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit"
                    class="flex-1 px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                    Filter
                </button>
                <a href="audit-log.php"
                    class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Audit Log Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Time</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            User</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Action</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Resource</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Details</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">IP
                            Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($auditLogs)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                No audit log entries found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($log['user_name']): ?>
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($log['user_name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">@<?php echo htmlspecialchars($log['username']); ?></div>
                                    <?php else: ?>
                                        <span class="text-gray-400">System</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        <?php
                                        if (in_array($log['action'], ['create', 'login']))
                                            echo 'bg-green-100 text-green-800';
                                        elseif (in_array($log['action'], ['edit', 'update']))
                                            echo 'bg-blue-100 text-blue-800';
                                        elseif (in_array($log['action'], ['delete', 'logout']))
                                            echo 'bg-red-100 text-red-800';
                                        elseif (in_array($log['action'], ['archive']))
                                            echo 'bg-yellow-100 text-yellow-800';
                                        else
                                            echo 'bg-gray-100 text-gray-800';
                                        ?>">
                                        <?php echo formatAuditAction($log['action']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php if ($log['resource_type']): ?>
                                        <span class="font-medium"><?php echo ucfirst($log['resource_type']); ?></span>
                                        <?php if ($log['resource_id']): ?>
                                            <span class="text-gray-500">#<?php echo $log['resource_id']; ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php
                                    $details = json_decode($log['details'], true);
                                    if ($details && is_array($details)) {
                                        $displayDetails = array_slice($details, 0, 5);
                                        foreach ($displayDetails as $key => $value) {
                                            if (is_string($value)) {
                                                echo '<div class="text-xs"><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</div>';
                                            }
                                        }
                                        if (count($details) > 5) {
                                            echo '<div class="text-xs text-gray-400">+' . (count($details) - 5) . ' more</div>';
                                        }
                                    } else {
                                        echo '<span class="text-gray-400">—</span>';
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($log['ip_address']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div
                class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="text-sm text-gray-600">
                    Showing <?php echo number_format($offset + 1); ?> to
                    <?php echo number_format(min($offset + $perPage, $totalRecords)); ?>
                    of <?php echo number_format($totalRecords); ?> entries
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $filterAction ? '&action=' . urlencode($filterAction) : ''; ?><?php echo $filterResourceType ? '&resource_type=' . urlencode($filterResourceType) : ''; ?><?php echo $filterUserId ? '&user_id=' . urlencode($filterUserId) : ''; ?>"
                            class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium">
                            Previous
                        </a>
                    <?php endif; ?>

                    <span class="px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm font-medium">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                    </span>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $filterAction ? '&action=' . urlencode($filterAction) : ''; ?><?php echo $filterResourceType ? '&resource_type=' . urlencode($filterResourceType) : ''; ?><?php echo $filterUserId ? '&user_id=' . urlencode($filterUserId) : ''; ?>"
                            class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>