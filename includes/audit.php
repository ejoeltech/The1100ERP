<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
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

    $userId = $_SESSION['user_id'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $detailsJson = json_encode($details);

    // Serialize chain appends (deadlock-safe): the sentinel row is seeded by
    // the patcher/install-schema, so the hot path takes exactly one lock in
    // a fixed order — no INSERT+SELECT upgrade cycle to deadlock on.
    $attempts = 0;
    while (true) {
        $attempts++;
        $ownTxn = false;
        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $ownTxn = true;
            }
            try {
                $lockStmt = $pdo->query("SELECT tick FROM audit_seq WHERE id = 1 FOR UPDATE");
                $tickRow = $lockStmt->fetchColumn();
                $lockStmt->closeCursor();
                if ($tickRow === false) {
                    // Row deleted out-of-band: recreate, then lock it.
                    $pdo->exec("INSERT IGNORE INTO audit_seq (id, tick) VALUES (1, 0)");
                    $lockStmt = $pdo->query("SELECT tick FROM audit_seq WHERE id = 1 FOR UPDATE");
                    $lockStmt->fetchColumn();
                    $lockStmt->closeCursor();
                }
                $locked = true;
            } catch (Exception $e) {
                $locked = false; // pre-migration table: proceed unlocked
            }

            // Get last hash for chain
            $lastHash = '';
            $lastStmt = $pdo->query("SELECT hash FROM audit_log ORDER BY id DESC LIMIT 1");
            $lastLog = $lastStmt->fetch();
            if ($lastLog) {
                $lastHash = $lastLog['hash'] ?? '';
            }

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

            if ($locked) {
                $pdo->exec("UPDATE audit_seq SET tick = tick + 1 WHERE id = 1");
            }
            if ($ownTxn) {
                $pdo->commit();
            }
            return;
        } catch (Exception $e) {
            if ($ownTxn && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Deadlock under contention: brief backoff and retry (bounded).
            $isDeadlock = stripos($e->getMessage(), 'deadlock') !== false || ($e->getCode() == '40001');
            if ($isDeadlock && $attempts < 3) {
                usleep(random_int(10000, 50000));
                continue;
            }
            // Log error but don't break application
            error_log("Audit log error: " . $e->getMessage());
            return;
        }
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
 * Log user login (chained like all other entries so the hash chain has no gaps)
 */
function logUserLogin($userId, $username)
{
    logAudit('login', 'user', $userId, ['username' => $username]);
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
        // Native prepares reject LIMIT placeholders: int-cast and interpolate.
        $limit = max(1, min(500, (int)$limit));
        $stmt = $pdo->prepare("
            SELECT
                al.*,
                u.full_name as user_name,
                u.username
            FROM audit_log al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT $limit
        ");

        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get recent audit error: " . $e->getMessage());
        return [];
    }
}

/**
 * Verify the audit hash chain (tamper evidence, read-only).
 * Rows written before chaining (empty hash) are counted as skipped, not failures.
 * @return array ['ok'=>bool,'checked'=>int,'skipped'=>int,'failed_at'=>int|null,'breaks'=>int]
 */
function verifyAuditChain($limit = 500)
{
    global $pdo;

    $result = ['ok' => true, 'checked' => 0, 'skipped' => 0, 'failed_at' => null, 'breaks' => 0];
    try {
        $limit = max(1, min(5000, (int)$limit));
        $stmt = $pdo->query("SELECT * FROM audit_log ORDER BY id ASC LIMIT $limit");
        $lastHash = '';
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (empty($row['hash'])) {
                // Legacy row: logAudit() reads '' as the previous hash, so mirror that.
                $lastHash = '';
                $result['skipped']++;
                continue;
            }
            // Same serialization as logAudit(): nulls concatenate as ''.
            $expect = hash('sha256', $lastHash . $row['action'] . $row['resource_type'] . $row['resource_id'] . $row['user_id'] . $row['ip_address'] . $row['details']);
            $expect = hash('sha256', $lastHash . $row['action'] . $row['resource_type'] . $row['resource_id'] . $row['user_id'] . $row['ip_address'] . $row['details']);
            if (!hash_equals($expect, $row['hash'])) {
                // Record the first break but keep going from the stored hash:
                // one historical anomaly must not mask the health of the rest.
                if ($result['failed_at'] === null) {
                    $result['failed_at'] = (int)$row['id'];
                }
                $result['breaks']++;
                $result['ok'] = false;
                $lastHash = $row['hash'];
                $result['checked']++;
                continue;
            }
            $lastHash = $row['hash'];
            $result['checked']++;
        }
    } catch (Exception $e) {
        error_log("Verify audit chain error: " . $e->getMessage());
        $result['ok'] = false;
    }
    return $result;
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