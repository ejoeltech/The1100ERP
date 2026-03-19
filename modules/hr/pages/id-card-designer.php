<?php
// HR ID Card Designer - Advanced Template Edition
// require_once '../../../config.php'; // Removed to prevent double inclusion
require_once '../../../includes/session-check.php';
require_once '../classes/HR_Employee.php';

requireLogin();

if (!isAdmin()) {
    header("Location: dashboard.php");
    exit;
}

$pageTitle = 'ID Card Designer | ' . COMPANY_NAME;

// 1. Fetch current settings
$stmt = $pdo->query("SELECT * FROM hr_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// 2. Define Defaults (The "Factory" Template)
// We use {{placeholders}} for dynamic content.

// Load Settings
$saved_config = $settings['id_card_designer_config'] ?? '{}';
$saved_config_arr = json_decode($saved_config, true);


// Load Values (or defaults)
$saved_css = $settings['id_card_custom_css'] ?? $default_css;
$saved_front_html = $settings['id_card_front_html'] ?? $default_front_html;
$saved_back_html = $settings['id_card_back_html'] ?? $default_back_html;

// Text Defaults (Still used for placeholders)
$subtitle_text = $settings['id_card_subtitle_text'] ?? 'TECHNOLOGIES';
$emergency_label = $settings['id_card_emergency_label'] ?? 'IN CASE OF EMERGENCY CONTACT:';
$disclaimer_text = $settings['id_card_disclaimer_text'] ?? 'If found, please return to the address above.';

// Layout Defaults
$logo_align = $settings['id_card_logo_align'] ?? 'center';
$header_align = $settings['id_card_header_align'] ?? 'center';
$photo_shape = $settings['id_card_photo_shape'] ?? 'circle';
$show_name = $settings['id_card_show_name'] ?? '1';
$custom_css = $settings['id_card_custom_css'] ?? '';


// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = [
        'id_card_primary_color' => $_POST['primary_color'] ?? '#0072bc',
        'id_card_secondary_color' => $_POST['secondary_color'] ?? '#39b54a',
        'id_card_front_html' => $_POST['front_html'] ?? '',
        'id_card_designer_config' => $_POST['designer_config'] ?? '{}'
    ];

    $stmt = $pdo->prepare("INSERT INTO hr_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");

    foreach ($updates as $key => $val) {
        $stmt->execute([$key, $val, $val]);
        $settings[$key] = $val; // Update local for display
    }

    $success = "Design saved successfully!";
}

include_once '../../../includes/header.php';
// Close standard main container to allow full width for designer
echo '</main>';
?>

<style id="baseStyles">
    :root {
        --brand-blue:
            <?php echo $settings['id_card_primary_color'] ?? '#0072bc'; ?>
        ;
        --brand-green:
            <?php echo $settings['id_card_secondary_color'] ?? '#39b54a'; ?>
        ;
        --dark-blue:
            <?php echo $settings['id_card_tertiary_color'] ?? '#005a9c'; ?>
        ;
        --card-width: 350px;
        --card-height: 550px;
        --card-radius: 15px;
    }

    .preview-area {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
        background: #f0f2f5;
        padding: 40px;
        border-radius: 10px;
        min-height: 600px;
    }

    /* Structural Enforcements */
    .qr-section {
        width: 100%;
        display: flex;
        justify-content: center;
    }

    .id-section {
        width: 100%;
        text-align: center;
    }
</style>

<!-- DYNAMIC USER CSS -->
<style id="customStyles">
    <?php echo $saved_css; ?>
</style>

<link rel="stylesheet" href="../assets/css/id-card-designer-advanced.css">

<div class="designer-grid" style="height: calc(100vh - 100px);">
    <!-- Control Panel -->
    <div class="control-panel flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-gray-800">Advanced Designer</h2>
            <div class="flex bg-gray-100 p-1 rounded-xl">
                <button type="button" data-side="front" class="side-toggle px-4 py-1.5 rounded-lg text-sm font-bold bg-indigo-600 text-white transition-all">Front</button>
                <button type="button" data-side="back" class="side-toggle px-4 py-1.5 rounded-lg text-sm font-bold text-gray-500 hover:text-gray-700 transition-all">Back</button>
            </div>
        </div>

        <form method="POST" id="advancedForm" class="flex-1 space-y-6">
            <input type="hidden" name="designer_config" id="hiddenConfig">
            
            <!-- FRONT CONTROLS -->
            <div class="control-group" data-side="front">
                <!-- Theme -->
                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">Theme & Colors</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Background</label>
                            <input type="color" name="bg_color" class="design-control w-full h-8 p-0.5 rounded border border-gray-200 cursor-pointer">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Primary Color</label>
                            <input type="color" name="primary_color" class="design-control w-full h-8 p-0.5 rounded border border-gray-200 cursor-pointer">
                        </div>
                    </div>
                </div>

                <!-- Logo Positioning -->
                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">Company Logo</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos X</label>
                            <input type="number" name="logo_x" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos Y</label>
                            <input type="number" name="logo_y" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Width</label>
                            <input type="number" name="logo_width" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Height</label>
                            <input type="number" name="logo_height" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                    </div>
                </div>

                <!-- Photo Positioning -->
                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">Profile Photo</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos X</label>
                            <input type="number" name="photo_x" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos Y</label>
                            <input type="number" name="photo_y" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Radius (%)</label>
                            <input type="number" name="photo_radius" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Border (px)</label>
                            <input type="number" name="photo_border_w" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                    </div>
                </div>

                <!-- Identity Positioning -->
                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">Employee Name</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos X</label>
                            <input type="number" name="name_x" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos Y</label>
                            <input type="number" name="name_y" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Font Size</label>
                            <input type="number" name="name_size" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                    </div>
                </div>
            </div>

            <!-- BACK CONTROLS -->
            <div class="control-group" data-side="back" style="display:none;">
                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">Back Theme</h3>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Background</label>
                        <input type="color" name="bg_color_back" class="design-control w-full h-8 p-0.5 rounded border border-gray-200 cursor-pointer">
                    </div>
                </div>

                <!-- Terms Positioning -->
                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">Terms & Text</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos X</label>
                            <input type="number" name="btext_x" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos Y</label>
                            <input type="number" name="btext_y" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                    </div>
                </div>

                <!-- Signature Positioning -->
                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">Signature Box</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos X</label>
                            <input type="number" name="sig_x" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos Y</label>
                            <input type="number" name="sig_y" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-6 sticky bottom-0 bg-white pb-4 border-t mt-4">
                <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-4 px-6 rounded-2xl shadow-xl hover:bg-indigo-700 transition transform hover:-translate-y-1 active:scale-95 duration-200">
                    Apply & Save Templates
                </button>
            </div>
        </form>
    </div>

    <!-- Preview Stage -->
    <div class="preview-stage">
        <div class="flex flex-col items-center">
            <span class="text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest">FRONT CANVAS</span>
            <div id="designerPreview"></div>
        </div>
        <div class="flex flex-col items-center">
            <span class="text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest">BACK CANVAS</span>
            <div id="designerBackPreview"></div>
        </div>
    </div>
</div>

<!-- Advanced Templates -->
<template id="idFrontTemplate">
    <?php include '../templates/id-front-advanced.html'; ?>
</template>
<template id="idBackTemplate">
    <?php include '../templates/id-back-advanced.html'; ?>
</template>

<script>
    window.AppMock = {
        company_name: <?php echo json_encode(defined('COMPANY_NAME') ? COMPANY_NAME : 'COMPANY NAME'); ?>,
        company_logo: <?php echo json_encode((defined('COMPANY_LOGO') && COMPANY_LOGO) ? '<img src="../../../' . COMPANY_LOGO . '" class="comp-logo">' : ''); ?>,
        signature: '<?php echo $signature_html ?? "Authorized Signature"; ?>',
        disclaimer: <?php echo json_encode($settings['id_card_disclaimer_text'] ?? 'Terms and conditions apply.'); ?>,
        emergency_label: <?php echo json_encode($settings['id_card_emergency_label'] ?? 'EMERGENCY CONTACT'); ?>,
        emergency_contact: '+234 800 000 0000',
        company_website: <?php echo json_encode(defined('COMPANY_WEBSITE') ? COMPANY_WEBSITE : 'www.erp.com'); ?>
    };
    window.SavedConfig = <?php echo $settings['id_card_designer_config'] ?? '{}'; ?>;
</script>
<script src="../assets/js/id-card-designer-advanced.js"></script>