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

// DEFAULT CSS (Layout & Styling)
// Note: Colors are handled via CSS Variables --brand-blue, etc., injected separately.
$default_css = '
/* Card Dimensions & Base */
.id-card {
    width: var(--card-width);
    height: var(--card-height);
    background: white;
    border-radius: var(--card-radius);
    box-shadow: 0 10px 20px rgba(0,0,0,0.15);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    border: 1px solid #e0e0e0;
    font-family: \'Roboto\', sans-serif;
}

/* Header & Logo */
.header { 
    text-align: center; 
    padding-top: 30px; margin-bottom: 20px; padding-left: 20px; padding-right: 20px;
}
.logo-graphic { 
    display: flex; 
    justify-content: center; 
    align-items: center; 
    gap: 5px; 
    margin-bottom: 5px; 
}
.brand-name { font-size: 28px; font-weight: 700; color: #222; letter-spacing: -1px; line-height: 1; margin-top: 5px; }
.brand-subtitle { font-size: 10px; letter-spacing: 4px; color: #444; font-weight: 500; margin-top: 2px; text-transform: uppercase; }

/* Photo */
.photo-container { display: flex; justify-content: center; margin-bottom: 15px; position: relative; }
.photo-frame { 
    width: 160px; height: 160px; 
    border-radius: 50%;
    border: 4px solid var(--brand-blue); 
    overflow: hidden; 
    background-color: #eee; 
    z-index: 10; 
}
.photo-frame img { width: 100%; height: 100%; object-fit: cover; }

/* Person Info */
.person-info { text-align: center; margin-bottom: 20px; z-index: 10; }
.person-name { font-size: 22px; font-weight: 900; color: #222; text-transform: uppercase; margin-bottom: 5px; }
.person-role { font-size: 16px; color: var(--brand-blue); font-weight: 500; }

/* Contact List */
.contact-list { padding: 0 20px; z-index: 10; }
.contact-item { display: flex; align-items: center; margin-bottom: 12px; background: rgba(255, 255, 255, 0.9); padding: 5px 10px; border-radius: 50px; }
.icon-box { width: 30px; height: 30px; border-radius: 50%; display: flex; justify-content: center; align-items: center; color: white; margin-right: 12px; flex-shrink: 0; font-size: 14px; }
.icon-green { background-color: var(--brand-green); }
.icon-blue { background-color: var(--brand-blue); }
.contact-text { font-size: 14px; color: #333; font-weight: 600; }

/* Footer Waves */
.wave-footer { position: absolute; bottom: 0; left: 0; width: 100%; height: 180px; z-index: 1; overflow: hidden; }

/* Back Side */
.back-header { text-align: center; margin-top: 40px; padding: 0 20px; }
.back-title { color: var(--brand-blue); font-weight: 700; font-size: 16px; line-height: 1.3; margin-bottom: 20px; }
.emergency-label { font-size: 11px; color: #333; text-transform: uppercase; margin-bottom: 10px; }
.back-contact-list { padding: 0 30px; }
.back-contact-item { display: flex; align-items: flex-start; margin-bottom: 15px; }
.back-contact-text { font-size: 13px; color: #333; font-weight: 500; margin-top: 5px; line-height: 1.4; }
.id-section { text-align: center; font-weight: bold; margin-bottom: 5px; font-size: 14px; color: #333; }
.qr-section { display: flex; justify-content: center; align-items: center; margin-bottom: 10px; position: relative; z-index: 10; width: 100%; }
.qr-code { width: 70px; height: 70px; background: white; padding: 5px; border-radius: 5px; }
.disclaimer { text-align: center; font-size: 10px; color: white; padding: 0 30px; position: relative; z-index: 10; margin-bottom: 25px; line-height: 1.3; }
.footer-contacts { display: flex; flex-direction: column; gap: 2px; padding: 10px 20px; background: white; position: absolute; bottom: 0; width: 100%; z-index: 10; }
.footer-row { display: flex; justify-content: space-between; font-size: 9px; color: #333; }
.footer-item { display: flex; align-items: center; gap: 5px; }
.footer-icon { color: var(--brand-green); }
.footer-icon.blue { color: var(--brand-blue); }
';

// DEFAULT FRONT HTML
$default_front_html = '
<div class="header">
    <div class="logo-graphic">
        {{company_logo}}
    </div>
    <div class="brand-name">{{company_name}}</div>
    <div class="brand-subtitle">{{subtitle}}</div>
</div>

<div class="photo-container">
    <div class="photo-frame">
        <img src="{{photo_url}}" alt="Photo">
    </div>
</div>

<div class="person-info">
    <div class="person-name">{{full_name}}</div>
    <div class="person-role">{{designation}}</div>
</div>

<div class="contact-list">
    <div class="contact-item">
        <div class="icon-box icon-green"><i class="fa-regular fa-id-card"></i></div>
        <div class="contact-text">ID: {{employee_code}}</div>
    </div>
    <div class="contact-item">
        <div class="icon-box icon-green"><i class="fa-solid fa-phone"></i></div>
        <div class="contact-text">{{phone}}</div>
    </div>
    <div class="contact-item">
        <div class="icon-box icon-blue"><i class="fa-regular fa-envelope"></i></div>
        <div class="contact-text" style="font-size: 11px;">{{email}}</div>
    </div>
</div>

<div class="wave-footer">
    <svg class="wave-graphic" viewBox="0 0 350 180" preserveAspectRatio="none">
        <path class="fill-green" d="M0,80 C100,60 200,120 350,60 L350,180 L0,180 Z" fill="{{color_secondary}}" opacity="0.9" />
        <path class="fill-blue" d="M0,100 C120,80 250,150 350,100 L350,180 L0,180 Z" fill="{{color_primary}}" opacity="0.85" />
        <path class="fill-dark" d="M0,130 C80,110 180,160 350,120 L350,180 L0,180 Z" fill="{{color_tertiary}}" opacity="0.6" />
    </svg>
</div>
';

// DEFAULT BACK HTML
$default_back_html = '
<div class="back-header">
    <div class="back-title">RENEWABLE INVERTER<br>INSTALLATION EXPERTS</div>
    <div class="emergency-label">{{emergency_label}}</div>
</div>

<div class="back-contact-list">
    <div class="back-contact-item">
        <div class="icon-box icon-green"><i class="fa-solid fa-phone"></i></div>
        <div class="back-contact-text">{{emergency_contact}}</div>
    </div>
    <div class="back-contact-item">
        <div class="icon-box icon-green"><i class="fa-solid fa-location-dot"></i></div>
        <div class="back-contact-text">{{company_address}}</div>
    </div>
</div>

<hr style="border: 0; border-top: 1px solid #eee; margin: 10px 20px;">

<div class="id-section">ID#: {{employee_code}}</div>
<div class="qr-section" style="display: flex; justify-content: center; align-items: center; gap: 20px;">
    <div>{{qr_code}}</div>
    <div style="text-align: center;">
        {{signature}}
        <div style="font-size: 8px; color: #888; margin-top: 2px;">Authorized Signature</div>
    </div>
</div>

<div class="disclaimer">{{disclaimer}}</div>

<div class="back-card-wave-bg">
    <svg class="wave-graphic" viewBox="0 0 350 180" preserveAspectRatio="none">
        <path class="fill-green" d="M0,40 C100,20 200,120 350,60 L350,180 L0,180 Z" fill="{{color_secondary}}" />
        <path class="fill-blue" d="M0,60 C120,40 200,140 350,90 L350,160 L0,180 Z" fill="{{color_primary}}" opacity="0.9" />
    </svg>
</div>

<div class="footer-contacts">
    <div class="footer-row">
        <div class="footer-item"><i class="fa-solid fa-phone footer-icon"></i> {{company_phone}}</div>
        <div class="footer-item"><i class="fa-regular fa-envelope footer-icon blue"></i> {{company_email}}</div>
    </div>
</div>
';


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
        'id_card_tertiary_color' => $_POST['tertiary_color'] ?? '#005a9c',
        'id_card_subtitle_text' => $_POST['subtitle_text'] ?? '', // Allow HTML
        'id_card_emergency_label' => $_POST['emergency_label'] ?? 'IN CASE OF EMERGENCY CONTACT:',
        'id_card_disclaimer_text' => $_POST['disclaimer_text'] ?? '', // Allow HTML
        'id_card_logo_align' => $_POST['logo_align'] ?? 'center',
        'id_card_header_align' => $_POST['header_align'] ?? 'center',
        'id_card_photo_shape' => $_POST['photo_shape'] ?? 'circle',
        'id_card_show_name' => isset($_POST['show_name']) ? '1' : '0',
        'id_card_custom_css' => $_POST['custom_css'] ?? '',
        'id_card_front_html' => $_POST['front_html'] ?? '',
        'id_card_back_html' => $_POST['back_html'] ?? ''
    ];

    $stmt = $pdo->prepare("INSERT INTO hr_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");

    foreach ($updates as $key => $val) {
        $stmt->execute([$key, $val, $val]);
        $settings[$key] = $val; // Update local for display
    }

    $saved_css = $updates['id_card_custom_css'];
    $saved_front_html = $updates['id_card_front_html'];
    $saved_back_html = $updates['id_card_back_html'];
    $subtitle_text = $updates['id_card_subtitle_text'];
    $emergency_label = $updates['id_card_emergency_label'];
    $disclaimer_text = $updates['id_card_disclaimer_text'];

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

<div class="flex flex-col" style="height: calc(100vh - 80px);">
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar Editor -->
        <div class="w-full md:w-[500px] bg-white border-r shadow-lg flex flex-col z-20">
            <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
                <h1 class="text-xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-palette text-blue-600"></i> Designer
                </h1>
                <?php if (isset($success)): ?>
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">Saved!</span>
                <?php endif; ?>
            </div>

            <form method="POST" class="flex flex-col flex-1 overflow-hidden" id="designForm">

                <!-- TABS -->
                <div class="flex border-b text-sm font-medium bg-gray-100">
                    <button type="button" onclick="switchTab('colors')"
                        class="tab-btn px-4 py-3 bg-white border-b-2 border-blue-500 text-blue-600 focus:outline-none"
                        data-tab="colors">Settings</button>
                    <button type="button" onclick="switchTab('front')"
                        class="tab-btn px-4 py-3 text-gray-500 hover:text-gray-700 focus:outline-none"
                        data-tab="front">Front HTML</button>
                    <button type="button" onclick="switchTab('back')"
                        class="tab-btn px-4 py-3 text-gray-500 hover:text-gray-700 focus:outline-none"
                        data-tab="back">Back HTML</button>
                    <button type="button" onclick="switchTab('css')"
                        class="tab-btn px-4 py-3 text-gray-500 hover:text-gray-700 focus:outline-none"
                        data-tab="css">CSS</button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 content-section" id="tab-colors">
                    <!-- Basic Settings -->
                    <div class="mb-4">
                        <label class="font-bold text-xs uppercase text-gray-500 mb-2 block">Brand Colors</label>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="text-xs mb-1 block">Primary</label>
                                <input type="color" name="primary_color" id="primaryColorInput"
                                    value="<?php echo $settings['id_card_primary_color'] ?? '#0072bc'; ?>"
                                    class="w-full h-8 cursor-pointer rounded border">
                            </div>
                            <div>
                                <label class="text-xs mb-1 block">Secondary</label>
                                <input type="color" name="secondary_color" id="secondaryColorInput"
                                    value="<?php echo $settings['id_card_secondary_color'] ?? '#39b54a'; ?>"
                                    class="w-full h-8 cursor-pointer rounded border">
                            </div>
                            <div>
                                <label class="text-xs mb-1 block">Tertiary</label>
                                <input type="color" name="tertiary_color" id="tertiaryColorInput"
                                    value="<?php echo $settings['id_card_tertiary_color'] ?? '#005a9c'; ?>"
                                    class="w-full h-8 cursor-pointer rounded border">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="font-bold text-xs uppercase text-gray-500 mb-2 block">Text Variables</label>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500">Subtitle ({{subtitle}})</label>
                                <input type="text" name="subtitle_text" id="subtitleInput"
                                    value="<?php echo htmlspecialchars($subtitle_text); ?>"
                                    class="w-full text-sm border rounded px-2 py-1">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Emergency Label ({{emergency_label}})</label>
                                <input type="text" name="emergency_label" id="emergencyInput"
                                    value="<?php echo htmlspecialchars($emergency_label); ?>"
                                    class="w-full text-sm border rounded px-2 py-1">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Disclaimer ({{disclaimer}})</label>
                                <textarea name="disclaimer_text" id="disclaimerInput" rows="2"
                                    class="w-full text-sm border rounded px-2 py-1"><?php echo htmlspecialchars($disclaimer_text); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-50 p-4 rounded-lg text-xs leading-5 text-blue-800 border border-blue-200 mt-4">
                        <strong>Available Placeholders:</strong><br>
                        {{company_name}}, {{company_logo}}, {{photo_url}}, {{full_name}}, {{designation}},
                        {{employee_code}}, {{phone}}, {{email}}, {{qr_code}}, {{emergency_contact}},
                        {{company_address}}, {{company_phone}}, {{company_email}}, {{company_website}},
                        {{color_primary}}, {{color_secondary}}, {{color_tertiary}}, {{subtitle}}, {{emergency_label}},
                        {{disclaimer}}, {{signature}}
                    </div>
                </div>

                <!-- FRONT HTML EDITOR -->
                <div class="flex-1 overflow-y-auto p-0 hidden content-section flex flex-col" id="tab-front">
                    <textarea name="front_html" id="frontHtmlInput"
                        class="w-full h-full p-4 font-mono text-xs bg-gray-900 text-green-400 focus:outline-none resize-none"><?php echo htmlspecialchars($saved_front_html); ?></textarea>
                </div>

                <!-- BACK HTML EDITOR -->
                <div class="flex-1 overflow-y-auto p-0 hidden content-section flex flex-col" id="tab-back">
                    <textarea name="back_html" id="backHtmlInput"
                        class="w-full h-full p-4 font-mono text-xs bg-gray-900 text-green-400 focus:outline-none resize-none"><?php echo htmlspecialchars($saved_back_html); ?></textarea>
                </div>

                <!-- CSS EDITOR -->
                <div class="flex-1 overflow-y-auto p-0 hidden content-section flex flex-col" id="tab-css">
                    <textarea name="custom_css" id="customCssInput"
                        class="w-full h-full p-4 font-mono text-xs bg-gray-900 text-blue-300 focus:outline-none resize-none"><?php echo htmlspecialchars($saved_css); ?></textarea>
                </div>

                <div class="p-4 border-t bg-gray-50">
                    <button type="submit"
                        class="w-full bg-blue-600 text-white py-3 rounded-lg font-bold hover:bg-blue-700 transition shadow-lg">Save
                        All Changes</button>
                    <a href="id-card-generator.php"
                        class="block w-full text-center text-sm text-gray-500 hover:text-gray-800 mt-2">Go to
                        Generator</a>
                </div>
            </form>
        </div>

        <!-- Preview Area -->
        <div class="flex-1 overflow-auto p-8 relative bg-gray-200">
            <div class="preview-area">
                <div class="id-card" id="cardFrontPreview">
                    <!-- Content injected via JS -->
                </div>
                <div class="id-card" id="cardBackPreview">
                    <!-- Content injected via JS -->
                </div>
            </div>
            <div class="text-center mt-4 text-gray-500 text-xs">Live Preview (Mock Data)</div>
        </div>
    </div>

    <!-- MOCK DATA & LOGIC -->
    <script>
        const mockData = {
            company_name: <?php echo json_encode(defined('COMPANY_NAME') ? COMPANY_NAME : 'COMPANY NAME'); ?>,
            company_logo: <?php echo json_encode((defined('COMPANY_LOGO') && COMPANY_LOGO) ? '<img src="../../../' . COMPANY_LOGO . '" style="width: 80%; height: auto; max-width: 100%;">' : '<div style="width:20px;height:20px;background:#0072bc;border-radius:50%"></div>'); ?>,
            photo_url: "https://ui-avatars.com/api/?name=John+Doe&size=200&background=ccc&color=fff",
            full_name: "John Doe",
            designation: "Software Engineer",
            employee_code: "EMP-001",
            phone: "+234 800 123 4567",
            email: "john@example.com",
            qr_code: `<img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=EMP-001|John%20Doe|<?php echo urlencode(defined('COMPANY_NAME') ? COMPANY_NAME : 'COMPANY NAME'); ?>" style="width:70px; height:70px;">`,
            signature: `<div style="font-family: cursive; font-size: 14px; border-bottom: 1px solid #ccc; padding-bottom: 2px;">JohnDoe</div>`,
            emergency_contact: "+234 901 000 0000",
            company_address: <?php echo json_encode(defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '123 Tech Street, Lagos'); ?>,
            company_phone: <?php echo json_encode(defined('COMPANY_PHONE') ? COMPANY_PHONE : ''); ?>,
            company_email: <?php echo json_encode(defined('COMPANY_EMAIL') ? COMPANY_EMAIL : ''); ?>,
            company_website: <?php echo json_encode(defined('COMPANY_WEBSITE') ? COMPANY_WEBSITE : ''); ?>,
            color_primary: <?php echo json_encode($settings['id_card_primary_color'] ?? '#0072bc'); ?>,
            color_secondary: <?php echo json_encode($settings['id_card_secondary_color'] ?? '#39b54a'); ?>,
            color_tertiary: <?php echo json_encode($settings['id_card_tertiary_color'] ?? '#005a9c'); ?>
        };

        // Inputs
        const tabs = document.querySelectorAll('.content-section');
        const tabBtns = document.querySelectorAll('.tab-btn');
        const frontInput = document.getElementById('frontHtmlInput');
        const backInput = document.getElementById('backHtmlInput');
        const cssInput = document.getElementById('customCssInput');
        const subInput = document.getElementById('subtitleInput');
        const emInput = document.getElementById('emergencyInput');
        const disInput = document.getElementById('disclaimerInput');

        // Styles
        const customStyleTag = document.getElementById('customStyles');
        const root = document.documentElement;

        function render() {
            // Update Mock Data from Inputs
            mockData.subtitle = subInput.value;
            mockData.emergency_label = emInput.value;
            mockData.disclaimer = disInput.value;
            mockData.color_primary = document.getElementById('primaryColorInput').value;
            mockData.color_secondary = document.getElementById('secondaryColorInput').value;
            mockData.color_tertiary = document.getElementById('tertiaryColorInput').value;

            // Render Front
            let frontVal = frontInput.value;
            document.getElementById('cardFrontPreview').innerHTML = replacePlaceholders(frontVal, mockData);

            // Render Back
            let backVal = backInput.value;
            document.getElementById('cardBackPreview').innerHTML = replacePlaceholders(backVal, mockData);

            // Render CSS
            customStyleTag.textContent = cssInput.value;

            // Update Root Vars
            root.style.setProperty('--brand-blue', mockData.color_primary);
            root.style.setProperty('--brand-green', mockData.color_secondary);
            root.style.setProperty('--dark-blue', mockData.color_tertiary);
        }

        function replacePlaceholders(str, data) {
            return str.replace(/{{(\w+)}}/g, function (match, key) {
                return typeof data[key] !== 'undefined' ? data[key] : match;
            });
        }

        // Event Listeners
        [frontInput, backInput, cssInput, subInput, emInput, disInput].forEach(el => {
            if (el) el.addEventListener('input', render);
        });

        ['primaryColorInput', 'secondaryColorInput', 'tertiaryColorInput'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', render);
        });

        function switchTab(tabName) {
            tabs.forEach(t => t.classList.add('hidden'));
            document.getElementById('tab-' + tabName).classList.remove('hidden');

            tabBtns.forEach(b => {
                b.classList.remove('border-b-2', 'border-blue-500', 'text-blue-600', 'bg-white');
                b.classList.add('text-gray-500');
                if (b.dataset.tab === tabName) {
                    b.classList.add('border-b-2', 'border-blue-500', 'text-blue-600', 'bg-white');
                    b.classList.remove('text-gray-500');
                }
            });
        }

        // Initial Render
        render();

    </script>
</div>
<?php include_once '../../../includes/footer.php'; ?>