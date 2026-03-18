<?php
/**
 * Enhanced Audit Logging System
 * Track all user and document activities
 */

/**
 * Log audit trail entry
 * @param string $action The action performed
 * @param string $resourceType The type of resource (user, quote, invoice, etc.)
 * @param int|null $resourceId The ID of the resource
 * @param array|null $details Additional details as array
 */
function logAudit($action, $resourceType, $resourceId = null, $details = [])
{
    global $pdo;

    try {
        $userId = $_SESSION['user_id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // Get last hash for chain
        $lastHash = '';
        $lastStmt = $pdo->query("SELECT hash FROM audit_log ORDER BY id DESC LIMIT 1");
        $lastLog = $lastStmt->fetch();
        if ($lastLog) {
            $lastHash = $lastLog['hash'] ?? '';
        }

        $detailsJson = json_encode($details);
        $currentHash = hash('sha256', $lastHash . $action . $resourceType . $resourceId . $userId . $ipAddress . $detailsJson);

        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, action, resource_type, resource_id, ip_address, user_agent, details, hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $action,
            $resourceType,
            $resourceId,
            $ipAddress,
            $userAgent,
            $detailsJson,
            $currentHash
        ]);
    } catch (Exception $e) {
        // Log error but don't break application
        error_log("Audit log error: " . $e->getMessage());
    }
}

// ============================================
// DOCUMENT AUDIT FUNCTIONS
// ============================================

/**
 * Log document creation
 */
function logDocumentCreate($documentType, $documentId, $documentNumber)
{
    logAudit(
        'create',
        $documentType,
        $documentId,
        ['document_number' => $documentNumber]
    );
}

/**
 * Log document edit
 */
function logDocumentEdit($documentType, $documentId, $documentNumber, $changes = [])
{
    logAudit(
        'edit',
        $documentType,
        $documentId,
        array_merge(['document_number' => $documentNumber], $changes)
    );
}

/**
 * Log document finalization
 */
function logDocumentFinalize($documentType, $documentId, $documentNumber)
{
    logAudit(
        'finalize',
        $documentType,
        $documentId,
        ['document_number' => $documentNumber, 'status' => 'finalized']
    );
}

/**
 * Log document deletion
 */
function logDocumentDelete($documentType, $documentId, $documentNumber)
{
    logAudit(
        'delete',
        $documentType,
        $documentId,
        ['document_number' => $documentNumber]
    );
}

/**
 * Log document archive
 */
function logDocumentArchive($documentType, $documentId, $documentNumber)
{
    logAudit(
        'archive',
        $documentType,
        $documentId,
        ['document_number' => $documentNumber]
    );
}

/**
 * Log document restore
 */
function logDocumentRestore($documentType, $documentId, $documentNumber)
{
    logAudit(
        'restore',
        $documentType,
        $documentId,
        ['document_number' => $documentNumber]
    );
}

/**
 * Log document conversion (quote to invoice)
 */
function logDocumentConvert($fromType, $fromId, $toType, $toId, $fromNumber, $toNumber)
{
    logAudit(
        'convert',
        $fromType,
        $fromId,
        [
            'from_number' => $fromNumber,
            'to_type' => $toType,
            'to_id' => $toId,
            'to_number' => $toNumber
        ]
    );
}

/**
 * Log receipt generation
 */
function logReceiptGenerate($invoiceId, $receiptId, $invoiceNumber, $receiptNumber, $amount)
{
    logAudit(
        'generate_receipt',
        'invoice',
        $invoiceId,
        [
            'invoice_number' => $invoiceNumber,
            'receipt_id' => $receiptId,
            'receipt_number' => $receiptNumber,
            'amount' => $amount
        ]
    );
}

// ============================================
// USER AUDIT FUNCTIONS
// ============================================

/**
 * Log user login
 */
function logUserLogin($userId, $username)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, action, resource_type, resource_id, ip_address, user_agent, details)
            VALUES (?, 'login', 'user', ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            json_encode(['username' => $username])
        ]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

/**
 * Log user logout
 */
function logUserLogout($userId, $username)
{
    logAudit('logout', 'user', $userId, ['username' => $username]);
}

/**
 * Log user creation
 */
function logUserCreate($newUserId, $username, $role)
{
    logAudit(
        'create',
        'user',
        $newUserId,
        ['username' => $username, 'role' => $role]
    );
}

/**
 * Log user update
 */
function logUserUpdate($userId, $username, $changes = [])
{
    logAudit(
        'update',
        'user',
        $userId,
        array_merge(['username' => $username], $changes)
    );
}

/**
 * Log user deletion
 */
function logUserDelete($userId, $username)
{
    logAudit(
        'delete',
        'user',
        $userId,
        ['username' => $username]
    );
}

/**
 * Log user status toggle
 */
function logUserStatusToggle($userId, $username, $newStatus)
{
    logAudit(
        'status_change',
        'user',
        $userId,
        ['username' => $username, 'status' => $newStatus ? 'activated' : 'deactivated']
    );
}

/**
 * Log password change
 */
function logPasswordChange($userId, $username)
{
    logAudit(
        'password_change',
        'user',
        $userId,
        ['username' => $username]
    );
}

// ============================================
// EMAIL AUDIT FUNCTIONS
// ============================================

/**
 * Log email send
 */
function logEmailSend($documentType, $documentId, $documentNumber, $recipient, $status = 'sent')
{
    logAudit(
        'email_sent',
        $documentType,
        $documentId,
        [
            'document_number' => $documentNumber,
            'recipient' => $recipient,
            'status' => $status
        ]
    );
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get audit log for specific resource
 * @param string $resourceType
 * @param int $resourceId
 * @return array
 */
function getAuditHistory($resourceType, $resourceId)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT 
                al.*,
                u.full_name as user_name,
                u.username
            FROM audit_log al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.resource_type = ? AND al.resource_id = ?
            ORDER BY al.created_at DESC
        ");

        $stmt->execute([$resourceType, $resourceId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get audit history error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent audit log entries
 * @param int $limit
 * @return array
 */
function getRecentAuditLog($limit = 50)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT 
                al.*,
                u.full_name as user_name,
                u.username
            FROM audit_log al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get recent audit error: " . $e->getMessage());
        return [];
    }
}

/**
 * Format audit action for display
 */
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
?>