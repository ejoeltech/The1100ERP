<?php
/**
 * Clear User Information Script
 * This script removes all user data from the 1100-ERP database
 * WARNING: This action cannot be undone!
 */

// Include config
require_once __DIR__ . '/../config.php';

// Confirmation check
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$confirmed) {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Clear User Information - 1100-ERP</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                padding: 40px;
                max-width: 600px;
                width: 100%;
            }

            h1 {
                color: #e53e3e;
                margin-top: 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .warning-icon {
                font-size: 32px;
            }

            .warning-box {
                background: #fff5f5;
                border-left: 4px solid #e53e3e;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }

            .info-box {
                background: #f0f9ff;
                border-left: 4px solid #3b82f6;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }

            .actions {
                display: flex;
                gap: 10px;
                margin-top: 30px;
            }

            .btn {
                padding: 12px 24px;
                border: none;
                border-radius: 6px;
                font-size: 16px;
                cursor: pointer;
                font-weight: 500;
                text-decoration: none;
                display: inline-block;
                transition: all 0.3s ease;
            }

            .btn-danger {
                background: #e53e3e;
                color: white;
            }

            .btn-danger:hover {
                background: #c53030;
            }

            .btn-secondary {
                background: #e2e8f0;
                color: #1a202c;
            }

            .btn-secondary:hover {
                background: #cbd5e0;
            }

            ul {
                line-height: 1.8;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <h1>
                <span class="warning-icon">⚠️</span>
                Clear User Information
            </h1>

            <div class="warning-box">
                <strong>⚠️ WARNING: This action is irreversible!</strong>
                <p style="margin-top: 10px; margin-bottom: 0;">
                    This will permanently delete all user data from the database.
                    Please ensure you have a backup if needed.
                </p>
            </div>

            <div class="info-box">
                <strong>📋 What will be cleared:</strong>
                <ul>
                    <li>All user accounts (users table)</li>
                    <li>All audit logs (audit_log table)</li>
                </ul>
                <strong>✅ What will be preserved:</strong>
                <ul>
                    <li>Database structure (all tables)</li>
                    <li>Customers</li>
                    <li>Products</li>
                    <li>Quotes, Invoices, and Receipts</li>
                    <li>All other business data</li>
                </ul>
            </div>

            <div class="actions">
                <a href="?confirm=yes" class="btn btn-danger"
                    onclick="return confirm('Are you absolutely sure? This cannot be undone!');">
                    🗑️ Yes, Clear User Information
                </a>
                <a href="../index.php" class="btn btn-secondary">
                    ← Cancel
                </a>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
}

// Proceed with clearing
try {
    // Begin transaction
    $pdo->beginTransaction();

    // Disable foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // Clear tables
    $pdo->exec('TRUNCATE TABLE users');
    $pdo->exec('TRUNCATE TABLE audit_log');

    // Re-enable foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Commit transaction
    $pdo->commit();

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Success - Clear User Information</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                padding: 40px;
                max-width: 600px;
                width: 100%;
                text-align: center;
            }

            .success-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }

            h1 {
                color: #059669;
                margin: 0 0 20px 0;
            }

            .btn {
                padding: 12px 24px;
                border: none;
                border-radius: 6px;
                font-size: 16px;
                cursor: pointer;
                font-weight: 500;
                text-decoration: none;
                display: inline-block;
                margin-top: 20px;
                background: #3b82f6;
                color: white;
            }

            .btn:hover {
                background: #2563eb;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <div class="success-icon">✅</div>
            <h1>User Information Cleared Successfully</h1>
            <p>All user data has been removed from the database.</p>
            <p style="color: #6b7280; margin-top: 20px;">
                The database structure and all business data remain intact.
            </p>
            <a href="../setup/" class="btn">🔧 Run Setup Wizard Again</a>
            <div style="margin-top: 10px;">
                <a href="../index.php" style="color: #6b7280; text-decoration: none;">or go to main page</a>
            </div>
        </div>
    </body>

    </html>
    <?php

} catch (PDOException $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Clear User Information</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                padding: 40px;
                max-width: 600px;
                width: 100%;
            }

            .error-icon {
                font-size: 64px;
                text-align: center;
                margin-bottom: 20px;
            }

            h1 {
                color: #e53e3e;
                margin: 0 0 20px 0;
            }

            .error-box {
                background: #fff5f5;
                border-left: 4px solid #e53e3e;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
                font-family: 'Courier New', monospace;
                word-break: break-word;
            }

            .btn {
                padding: 12px 24px;
                border: none;
                border-radius: 6px;
                font-size: 16px;
                cursor: pointer;
                font-weight: 500;
                text-decoration: none;
                display: inline-block;
                margin-top: 20px;
                background: #e2e8f0;
                color: #1a202c;
            }

            .btn:hover {
                background: #cbd5e0;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <div class="error-icon">❌</div>
            <h1>Error Occurred</h1>
            <p>Failed to clear user information from the database.</p>
            <div class="error-box">
                <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>
            <a href="clear-users.php" class="btn">← Try Again</a>
        </div>
    </body>

    </html>
    <?php
}
?>