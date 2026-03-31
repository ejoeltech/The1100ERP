<?php
/**
 * Database stub — $pdo is already established by config.php
 *
 * Several AI endpoints include this file expecting to get a $pdo connection,
 * but the connection is already set up via config.php (which is loaded
 * by session-check.php before this file is ever reached).
 * This stub exists solely to prevent "Failed to open stream" Fatal Errors.
 */

// $pdo is already available as a global from config.php.
// No action needed here.

// Make setSetting and getSetting available as globals if they are needed
// (they are defined in config.php).
