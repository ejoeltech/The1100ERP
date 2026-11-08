<?php
// Roll back the files from the last applied update (database schema is additive).
require_once '../../config.php';
require_once '../../includes/session-check.php';
require_once '../../includes/invites.php';
require_once '../../includes/update-manager.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    updateFail('Invalid request method.', 405);
}
updateRequireOperator();
requireCsrf();
updateVerifyReauth($pdo, 'ROLLBACK');

try {
    $root = realpath(__DIR__ . '/../../');
    $last = updateLastBackup($root);
    if (!$last) throw new Exception('No update backup is available to roll back.');

    $result = updateRollbackBackup($root, $last['id']);

    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, details, created_at) VALUES (?, 'system_update_rollback', ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], json_encode([
            'backup_id' => $last['id'],
            'app_version' => $last['app_version'] ?? '',
            'restored' => $result['restored'],
            'removed' => $result['removed'],
        ])]);
    } catch (Throwable $auditError) {
        error_log('Rollback audit failed: ' . $auditError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Rollback complete: ' . $result['restored'] . ' file(s) restored, ' . $result['removed'] . ' file(s) removed. Reload the page.',
        'backup_id' => $last['id'],
    ]);
} catch (Throwable $e) {
    error_log('Update rollback error: ' . $e->getMessage());
    updateFail($e->getMessage() !== '' ? $e->getMessage() : 'Rollback failed.', 500);
}
