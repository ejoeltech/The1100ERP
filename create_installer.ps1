$source = "C:\xampp\htdocs\1100erp"
$dest = "C:\xampp\htdocs\1100erp_dist"
$zipFile = "C:\xampp\htdocs\1100erp_installer.zip"

# Clean up previous runs
if (Test-Path $dest) { Remove-Item -Recurse -Force $dest }
if (Test-Path $zipFile) { Remove-Item -Force $zipFile }

# Create destination
New-Item -ItemType Directory -Force -Path $dest | Out-Null

# Copy files (Using Robocopy for speed and exclusion, ignore error code 1/3)
# Exclude: .git, .vscode, vendor (wait, need vendor), node_modules
# Exclude files: config.php (force fresh install), .env
Write-Host "Copying files..."
& robocopy $source $dest /MIR /XD .git .vscode .idea /XF config.php config.php.bak config_broken.php.bak *.zip create_installer.ps1

# Robocopy returns exit codes that aren't errors (0-7 are success/partial)
if ($LASTEXITCODE -gt 7) { 
    Write-Error "Robocopy failed with code $LASTEXITCODE"
    exit
}

# Remove Debug and Test Scripts from destination
$filesToRemove = @(
    "create-test-users.php",
    "reset-test-passwords.php",
    "update-password.php",
    "debug_settings.php",
    "debug_settings_2.php",
    "debug_log.txt",
    "check_db.php",
    "check_schema.php",
    "check_invoices_schema.php",
    "test_debt_clearance.php",
    "test_save_customer.php",
    "test_save_product.php",
    "reproduce_save_multiple.php",
    "add_column.php",
    "update_invoices_enum.php",
    "update_key.php",
    "run-bank-migration.php",
    "add-bulk-actions.php",
    "add-export-dropdowns.php",
    "add-html-export.php",
    "csrf-implementation-guide.php",
    "finalize-archive-system.php",
    "generate-archive-pages.php",
    "generate-readymade-files.php",
    "git-init.bat",
    "implement-security.php",
    "phase2b-progress.php",
    "rename-to-1100erp.ps1",
    "RENAME-FOLDER.bat",
    "schema.txt",
    "update-jpeg-exports-gd.php"
)

foreach ($file in $filesToRemove) {
    if (Test-Path "$dest\$file") {
        Remove-Item -Force "$dest\$file"
    }
}

# Ensure setup directory exists (it should, but verifying)
if (-not (Test-Path "$dest\setup")) {
    Write-Error "CRITICAL: Setup directory missing!"
    exit
}

# Zip it
Write-Host "Zipping..."
Compress-Archive -Path "$dest\*" -DestinationPath $zipFile

# Cleanup Temp
Remove-Item -Recurse -Force $dest

Write-Host "Done. Zip created at $zipFile"
