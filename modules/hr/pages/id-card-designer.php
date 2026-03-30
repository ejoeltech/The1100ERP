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
        'id_card_custom_css' => $_POST['id_card_custom_css'] ?? '',
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

<link rel="stylesheet" href="../assets/css/id-card-designer-advanced.css?v=<?php echo time(); ?>">

<div style="display: flex !important; flex-direction: row !important; height: calc(100vh - 100px); overflow: hidden; background: #f0f2f5;">
    <!-- Sidebar: Fixed 450px -->
    <div style="position: sticky; top: 0; width: 450px !important; height: calc(100vh - 100px); flex-shrink: 0; background: white; display: flex; flex-direction: column; border-right: 1px solid #e5e7eb; z-index: 10;">
        <!-- Sidebar Header -->
        <div style="padding: 24px; border-bottom: 1px solid #f3f4f6; flex-shrink: 0;">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Advanced Designer</h2>
            <div class="flex bg-gray-100 p-1 rounded-xl overflow-x-auto no-scrollbar">
                <button type="button" data-tab="front" class="tab-toggle flex-1 px-3 py-1.5 rounded-lg text-[11px] font-bold bg-indigo-600 text-white transition-all whitespace-nowrap">Front</button>
                <button type="button" data-tab="back" class="tab-toggle flex-1 px-3 py-1.5 rounded-lg text-[11px] font-bold text-gray-500 hover:text-gray-700 transition-all whitespace-nowrap">Back</button>
                <button type="button" data-tab="css" class="tab-toggle flex-1 px-3 py-1.5 rounded-lg text-[11px] font-bold text-gray-500 hover:text-gray-700 transition-all whitespace-nowrap">CSS</button>
                <button type="button" data-tab="info" class="tab-toggle flex-1 px-3 py-1.5 rounded-lg text-[11px] font-bold text-gray-500 hover:text-gray-700 transition-all whitespace-nowrap">Info</button>
            </div>
        </div>

        <!-- Sidebar Content (Scrollable) -->
        <div style="flex: 1; overflow-y: auto; padding: 24px;">
            <form method="POST" id="advancedForm" class="space-y-6">
                <input type="hidden" name="designer_config" id="hiddenConfig">
                
                <!-- FRONT TAB -->
                <div class="tab-content active" data-tab="front">
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
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Secondary Color</label>
                            <input type="color" name="secondary_color" class="design-control w-full h-8 p-0.5 rounded border border-gray-200 cursor-pointer">
                        </div>
                    </div>
                </div>

                <!-- QR Template -->
                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">QR Code Data</h3>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Data Template (use {{placeholders}})</label>
                        <input type="text" name="qr_template" class="design-control w-full p-2 text-xs border rounded-lg" placeholder="{{employee_code}} | {{full_name}}">
                    </div>
                </div>

                <!-- Logo Positioning -->
                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">Company Logo</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1 flex justify-between">
                                Pos X <button type="button" data-center="logo_x" class="center-btn text-indigo-600 hover:text-indigo-800 font-bold uppercase text-[9px]">Center</button>
                            </label>
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
                            <label class="block text-[10px] font-bold text-gray-500 mb-1 flex justify-between">
                                Pos X <button type="button" data-center="photo_x" class="center-btn text-indigo-600 hover:text-indigo-800 font-bold uppercase text-[9px]">Center</button>
                            </label>
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
                    <div class="flex items-center justify-between border-b pb-2">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Employee Name</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-2">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1 flex justify-between">
                                Pos X <button type="button" data-center="name_x" class="center-btn text-indigo-600 hover:text-indigo-800 font-bold uppercase text-[9px]">Center</button>
                            </label>
                            <input type="number" name="name_x" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos Y</label>
                            <input type="number" name="name_y" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Font Size</label>
                            <input type="number" name="name_size" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Wrap Width</label>
                            <input type="number" name="name_w" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                    </div>
                </div>

                <!-- Company Name Positioning -->
                <div class="space-y-4 mb-8">
                    <div class="flex items-center justify-between border-b pb-2">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Company Name</h3>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="text-[10px] font-bold text-gray-400">SHOW</span>
                            <input type="checkbox" name="show_brand" class="design-control h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        </label>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1 flex justify-between">
                                Pos X <button type="button" data-center="brand_x" class="center-btn text-indigo-600 hover:text-indigo-800 font-bold uppercase text-[9px]">Center</button>
                            </label>
                            <input type="number" name="brand_x" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos Y</label>
                            <input type="number" name="brand_y" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Font Size</label>
                            <input type="number" name="brand_size" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                    </div>
                </div>

                <!-- Position/Role Positioning -->
                <div class="space-y-4 mb-8">
                    <div class="flex items-center justify-between border-b pb-2">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Employee Position</h3>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="text-[10px] font-bold text-gray-400">SHOW</span>
                            <input type="checkbox" name="show_role" class="design-control h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        </label>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1 flex justify-between">
                                Pos X <button type="button" data-center="role_x" class="center-btn text-indigo-600 hover:text-indigo-800 font-bold uppercase text-[9px]">Center</button>
                            </label>
                            <input type="number" name="role_x" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos Y</label>
                            <input type="number" name="role_y" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Font Size</label>
                            <input type="number" name="role_size" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                    </div>
                </div>

                <!-- Employee ID Positioning -->
                <div class="space-y-4 mb-8">
                    <div class="flex items-center justify-between border-b pb-2">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Employee ID</h3>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="text-[10px] font-bold text-gray-400">SHOW</span>
                            <input type="checkbox" name="show_code" class="design-control h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        </label>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1 flex justify-between">
                                Pos X <button type="button" data-center="code_x" class="center-btn text-indigo-600 hover:text-indigo-800 font-bold uppercase text-[9px]">Center</button>
                            </label>
                            <input type="number" name="code_x" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos Y</label>
                            <input type="number" name="code_y" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                    </div>
                </div>
            </div>

            <!-- BACK TAB -->
            <div class="tab-content" data-tab="back" style="display:none;">
                <div class="flex items-center justify-between border-b pb-2 mb-4">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Back Designer</h3>
                    <button type="button" id="resetBackBtn" class="text-[9px] font-black bg-red-50 text-red-600 px-2 py-1 rounded-md hover:bg-red-600 hover:text-white transition-all uppercase tracking-tighter">Reset to Professional Layout</button>
                </div>

                <div class="space-y-4 mb-8 bg-gray-50/50 p-3 rounded-xl border border-dashed border-gray-200">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Back Title</label>
                        <input type="text" name="back_title" class="design-control w-full p-2 text-xs border rounded-lg" placeholder="THIS IS TO CERTIFY THAT">
                        <div class="grid grid-cols-3 gap-2 mt-2">
                            <div>
                                <label class="block text-[8px] font-bold text-gray-400 uppercase">Pos X</label>
                                <input type="number" name="btitle_x" class="design-control w-full p-1 text-xs border rounded-lg">
                            </div>
                            <div>
                                <label class="block text-[8px] font-bold text-gray-400 uppercase">Pos Y</label>
                                <input type="number" name="btitle_y" class="design-control w-full p-1 text-xs border rounded-lg">
                            </div>
                            <div>
                                <label class="block text-[8px] font-bold text-gray-400 uppercase">Width</label>
                                <input type="number" name="btitle_w" class="design-control w-full p-1 text-xs border rounded-lg">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Back Content Text</label>
                        <textarea name="back_content" class="design-control w-full p-2 text-xs border rounded-lg" rows="6" placeholder="The bearer of this identification card is..."></textarea>
                        <div class="grid grid-cols-3 gap-2 mt-2">
                            <div>
                                <label class="block text-[8px] font-bold text-gray-400 uppercase">Pos X</label>
                                <input type="number" name="btext_x" class="design-control w-full p-1 text-xs border rounded-lg">
                            </div>
                            <div>
                                <label class="block text-[8px] font-bold text-gray-400 uppercase">Pos Y</label>
                                <input type="number" name="btext_y" class="design-control w-full p-1 text-xs border rounded-lg">
                            </div>
                            <div>
                                <label class="block text-[8px] font-bold text-gray-400 uppercase">Width</label>
                                <input type="number" name="btext_w" class="design-control w-full p-1 text-xs border rounded-lg">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">Theme & QR</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Background</label>
                            <input type="color" name="bg_color_back" class="design-control w-full h-8 p-0.5 rounded border border-gray-200 cursor-pointer">
                        </div>
                        <div class="flex flex-col justify-end">
                            <label class="flex items-center gap-2 cursor-pointer mb-2">
                                <span class="text-[10px] font-bold text-gray-400">SHOW QR</span>
                                <input type="checkbox" name="show_qr_back" class="design-control h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" checked>
                            </label>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3 mt-2">
                        <div>
                            <label class="block text-[9px] font-bold text-gray-400 uppercase flex justify-between">
                                QR X <button type="button" data-center="qr_x_back" class="center-btn text-indigo-600 hover:text-indigo-800 font-bold uppercase text-[8px]">Center</button>
                            </label>
                            <input type="number" name="qr_x_back" class="design-control w-full p-1.5 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-gray-400 uppercase">QR Y</label>
                            <input type="number" name="qr_y_back" class="design-control w-full p-1.5 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-gray-400 uppercase">Size</label>
                            <input type="number" name="qr_size_back" class="design-control w-full p-1.5 text-xs border rounded-lg">
                        </div>
                    </div>
                </div>

                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">Business Address</h3>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Address Text</label>
                        <textarea name="business_address" class="design-control w-full p-2 text-xs border rounded-lg" rows="3" placeholder="Contact Address..."></textarea>
                    </div>
                </div>

                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">Positions & Sizing</h3>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-3 bg-gray-50 p-2 rounded-lg">
                            <span class="text-[9px] font-bold text-gray-400 uppercase">Signature Block</span>
                            <div class="grid grid-cols-3 gap-2 mt-1">
                                <input type="number" name="sig_x" class="design-control w-full p-1 text-xs border rounded-lg" title="Pos X">
                                <input type="number" name="sig_y" class="design-control w-full p-1 text-xs border rounded-lg" title="Pos Y">
                                <input type="number" name="sig_w" class="design-control w-full p-1 text-xs border rounded-lg" title="Width">
                            </div>
                        </div>
                        <div class="col-span-3 bg-gray-50 p-2 rounded-lg">
                            <span class="text-[9px] font-bold text-gray-400 uppercase">Contact Address</span>
                            <div class="grid grid-cols-3 gap-2 mt-1">
                                <input type="number" name="addr_x" class="design-control w-full p-1 text-xs border rounded-lg" title="Pos X">
                                <input type="number" name="addr_y" class="design-control w-full p-1 text-xs border rounded-lg" title="Pos Y">
                                <input type="number" name="addr_w" class="design-control w-full p-1 text-xs border rounded-lg" title="Width">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Terms Positioning -->
                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">Terms & Text</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1 flex justify-between">
                                Pos X <button type="button" data-center="btext_x" class="center-btn text-indigo-600 hover:text-indigo-800 font-bold uppercase text-[9px]">Center</button>
                            </label>
                            <input type="number" name="btext_x" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Pos Y</label>
                            <input type="number" name="btext_y" class="design-control w-full p-2 text-xs border rounded-lg">
                        </div>
                    </div>
                </div>
            </div>

            <!-- CSS TAB -->
            <div class="tab-content" data-tab="css" style="display:none;">
                <div class="space-y-4 mb-8">
                    <div class="flex items-center justify-between border-b pb-2">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Custom CSS</h3>
                    </div>
                    <p class="text-[10px] text-gray-400 mb-2">Inject your own global CSS rules here.</p>
                    <textarea name="id_card_custom_css" class="w-full h-[400px] p-4 text-xs font-mono border rounded-xl bg-gray-50 active:bg-white transition-all"><?php echo htmlspecialchars($saved_css); ?></textarea>
                </div>
            </div>

            <!-- PLACEHOLDERS TAB -->
            <div class="tab-content" data-tab="info" style="display:none;">
                <div class="space-y-4 mb-8">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b pb-2">Available Placeholders</h3>
                    <div class="space-y-2">
                        <?php
                        $placeholders = [
                            'full_name' => 'Employee Full Name',
                            'designation' => 'Job Position / Role',
                            'employee_code' => 'Employee ID Number',
                            'phone' => 'Work Phone Number',
                            'email' => 'Work Email Address',
                            'photo_url' => 'Employee Passport Photo URL',
                            'company_name' => 'Your Company Name',
                            'qr_code' => 'Generated QR Code Image',
                            'signature' => 'Authorized Signature Image/Text',
                            'emergency_label' => 'Next of Kin Label',
                            'emergency_contact' => 'Next of Kin Phone',
                            'disclaimer' => 'General Disclaimer Text',
                            'business_address' => 'Company Physical Address',
                            'back_title' => 'Title on Card Back',
                            'back_content' => 'Main Content on Card Back'
                        ];
                        foreach ($placeholders as $code => $desc):
                        ?>
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg group hover:bg-white border border-transparent hover:border-indigo-100 transition-all">
                            <code class="text-[11px] font-bold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded cursor-pointer" onclick="navigator.clipboard.writeText('{{<?php echo $code; ?>}}')">{{<?php echo $code; ?>}}</code>
                            <span class="text-[10px] text-gray-400"><?php echo $desc; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            </form>
        </div>

        <!-- Sidebar Footer -->
        <div style="padding: 24px; border-top: 1px solid #f3f4f6; flex-shrink: 0;">
            <button type="submit" form="advancedForm" class="w-full bg-indigo-600 text-white font-bold py-4 px-6 rounded-2xl shadow-xl hover:bg-indigo-700 transition transform hover:-translate-y-1 active:scale-95 duration-200">
                Apply & Save Templates
            </button>
        </div>
    </div>

    <!-- Preview Stage (Scrollable) -->
    <div style="flex: 1; overflow-y: auto; padding: 60px; display: flex; flex-direction: column; align-items: center; gap: 60px;">
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