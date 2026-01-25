<?php
include '../includes/public-init.php';

$pageTitle = 'System Designer - ERP System';
include '../includes/public-header.php';

// Get theme color from config and convert to RGB
$themeColor = THEME_COLOR ?? '#2563eb';
// Extract RGB from hex
$hex = ltrim($themeColor, '#');
$r = hexdec(substr($hex, 0, 2));
$g = hexdec(substr($hex, 2, 2));
$b = hexdec(substr($hex, 4, 2));
?>

<style>
    /* Modern Design System with Theme Colors */
    :root {
        --theme-primary:
            <?= $themeColor ?>
        ;
        --theme-primary-rgb:
            <?= "$r, $g, $b" ?>
        ;
        --theme-gradient: linear-gradient(135deg,
                <?= $themeColor ?>
                0%, color-mix(in srgb,
                    <?= $themeColor ?>
                    80%, black) 100%);
        --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    /* Page Background */
    .designer-container {
        min-height: 100vh;
        background: var(--theme-gradient);
        padding: 2rem 0;
    }

    /* Glass Card Effect */
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        border: 1px solid rgba(255, 255, 255, 0.3);
        overflow: hidden;
    }

    /* Mode Selection Cards */
    .mode-card {
        background: white;
        border: 3px solid #e5e7eb;
        border-radius: 20px;
        padding: 2rem;
        cursor: pointer;
        transition: all 0.3s;
        position: relative;
    }

    .mode-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        border-color: var(--theme-primary);
    }

    .mode-card.selected {
        border-color: var(--theme-primary);
        background: linear-gradient(135deg, rgba(var(--theme-primary-rgb), 0.05) 0%, #ffffff 100%);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
    }

    .mode-card .check-icon {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--success-gradient);
        display: none;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
    }

    .mode-card.selected .check-icon {
        display: flex;
    }

    /* Form Sections */
    .form-section {
        background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%);
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 2px solid #e5e7eb;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .section-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: var(--theme-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.25rem;
    }

    .section-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: #1f2937;
    }

    /* Enhanced Inputs */
    .input-group {
        margin-bottom: 1.25rem;
    }

    .input-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
    }

    .input-label .required {
        color: #ef4444;
        font-size: 1rem;
    }

    .input-label .hint {
        font-weight: 400;
        color: #6b7280;
        font-size: 0.75rem;
        display: block;
        margin-top: 0.25rem;
    }

    .input-label .example {
        color: #8b5cf6;
        font-weight: 500;
    }

    .enhanced-input,
    .enhanced-select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s;
        background: white;
    }

    .enhanced-input:focus,
    .enhanced-select:focus {
        outline: none;
        border-color: var(--theme-primary);
        box-shadow: 0 0 0 4px rgba(var(--theme-primary-rgb), 0.1);
    }

    .enhanced-input:invalid {
        border-color: #fca5a5;
    }

    .enhanced-input:disabled,
    .enhanced-select:disabled {
        background: #f3f4f6;
        cursor: not-allowed;
        opacity: 0.6;
    }

    /* Radio Button Groups */
    .radio-group {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .radio-option {
        flex: 1;
        min-width: 200px;
    }

    .radio-option input[type="radio"] {
        display: none;
    }

    .radio-label {
        display: block;
        padding: 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s;
        text-align: center;
        font-weight: 600;
    }

    .radio-option input[type="radio"]:checked+.radio-label {
        border-color: var(--theme-primary);
        background: linear-gradient(135deg, rgba(var(--theme-primary-rgb), 0.1) 0%, white 100%);
        color: var(--theme-primary);
    }

    .radio-label:hover {
        border-color: var(--theme-primary);
    }

    /* Action Buttons */
    .btn-primary {
        padding: 0.875rem 2rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1rem;
        border: none;
        background: var(--theme-gradient);
        color: white;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }

    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-secondary {
        padding: 0.875rem 2rem;
        border-radius: 12px;
        font-weight: 600;
        border: 2px solid #e5e7eb;
        background: white;
        color: #374151;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-secondary:hover {
        background: #f3f4f6;
        border-color: var(--theme-primary);
    }

    /* Results Display */
    .result-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        border-left: 4px solid var(--theme-primary);
    }

    .result-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .result-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .result-icon.success {
        background: linear-gradient(135deg, #d4fce3 0%, #a7f3d0 100%);
    }

    .result-icon.warning {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    }

    .error-message {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        border: 2px solid #ef4444;
        border-radius: 12px;
        padding: 1rem;
        margin-top: 1rem;
        color: #991b1b;
    }

    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.3);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .loading-overlay.active {
        display: flex;
    }

    .loading-spinner {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        text-align: center;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .radio-option {
            min-width: 100%;
        }
    }
</style>

<div class="designer-container">
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <svg class="w-12 h-12 animate-spin mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                </path>
            </svg>
            <p class="font-semibold text-gray-700">Generating Recommendation...</p>
            <p class="text-sm text-gray-500 mt-2">This may take a few moments</p>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-3">System Designer</h1>
            <p class="text-xl text-white text-opacity-90">Professional Solar System Design & Recommendations</p>
        </div>

        <!-- Main Card -->
        <div class="glass-card p-6 md:p-8">
            <!-- Mode Selection -->
            <div id="modeSelection" class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Select Design Mode</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Mode 1 -->
                    <div class="mode-card" onclick="selectMode(1)">
                        <div class="check-icon">✓</div>
                        <div class="text-6xl mb-4 text-center">📋</div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">Design Planner</h3>
                        <p class="text-gray-600 text-center mb-4">Planning a system before purchasing materials</p>
                        <ul class="text-sm text-gray-600 space-y-2">
                            <li>✓ Recommends maximum number of panels</li>
                            <li>✓ Suggests optimal arrangement</li>
                            <li>✓ Calculates breaker & wire sizing</li>
                        </ul>
                    </div>

                    <!-- Mode 2 -->
                    <div class="mode-card" onclick="selectMode(2)">
                        <div class="check-icon">✓</div>
                        <div class="text-6xl mb-4 text-center">🔧</div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">Design Implementation</h3>
                        <p class="text-gray-600 text-center mb-4">Installing with materials already purchased</p>
                        <ul class="text-sm text-gray-600 space-y-2">
                            <li>✓ Validates your panel configuration</li>
                            <li>✓ Provides installation guidance</li>
                            <li>✓ Ensures safety compliance</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Design Forms (Hidden Initially) -->
            <div id="designForms" class="hidden">
                <!-- Back Button -->
                <button onclick="backToModeSelection()" class="btn-secondary mb-6">
                    <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                        </path>
                    </svg>
                    Change Mode
                </button>

                <form id="systemForm" onsubmit="generateRecommendation(event)">
                    <!-- System Type Selection -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon">⚡</div>
                            <div class="section-title">System Type</div>
                        </div>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="type_hybrid" name="system_type" value="hybrid"
                                    onchange="toggleSystemType()" required>
                                <label for="type_hybrid" class="radio-label">Hybrid Inverter</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="type_controller" name="system_type" value="controller"
                                    onchange="toggleSystemType()">
                                <label for="type_controller" class="radio-label">Charge Controller</label>
                            </div>
                        </div>
                    </div>

                    <!-- Hybrid Inverter Parameters -->
                    <div id="hybridParams" class="form-section hidden">
                        <div class="section-header">
                            <div class="section-icon">🔋</div>
                            <div class="section-title">Hybrid Inverter Parameters</div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="input-group">
                                <label class="input-label">
                                    Inverter Capacity <span class="required">*</span>
                                    <span class="hint">Enter in W or VA <span class="example">(e.g., 5000)</span></span>
                                </label>
                                <input type="number" id="hybrid_inv_capacity" class="enhanced-input" placeholder="5000"
                                    min="100" step="0.1">
                            </div>
                            <div class="input-group">
                                <label class="input-label">
                                    Hybrid Controller Capacity (W) <span class="required">*</span>
                                    <span class="hint"><span class="example">(e.g., 6500)</span></span>
                                </label>
                                <input type="number" id="hybrid_controller_capacity" class="enhanced-input"
                                    placeholder="6500" min="100">
                            </div>
                            <div class="input-group">
                                <label class="input-label">
                                    Controller Nominal Solar Voltage (V) <span class="required">*</span>
                                    <span class="hint"><span class="example">(e.g., 360)</span></span>
                                </label>
                                <input type="number" id="hybrid_nominal_voltage" class="enhanced-input"
                                    placeholder="360" min="12" max="1000">
                            </div>
                            <div class="input-group">
                                <label class="input-label">
                                    Controller Maximum Solar Voltage (V) <span class="required">*</span>
                                    <span class="hint"><span class="example">(e.g., 500)</span></span>
                                </label>
                                <input type="number" id="hybrid_max_voltage" class="enhanced-input" placeholder="500"
                                    min="12" max="1000">
                            </div>
                            <div class="input-group">
                                <label class="input-label">
                                    Controller Voltage Range (V)
                                    <span class="hint">Operating range <span class="example">(e.g.,
                                            120-450)</span></span>
                                </label>
                                <input type="text" id="hybrid_voltage_range" class="enhanced-input"
                                    placeholder="120-450">
                            </div>
                            <div class="input-group">
                                <label class="input-label">
                                    Controller Maximum Current (A) <span class="required">*</span>
                                    <span class="hint">Total max current controller can handle <span
                                            class="example">(e.g., 18)</span></span>
                                </label>
                                <input type="number" id="hybrid_max_current" class="enhanced-input" placeholder="18"
                                    min="1" max="500">
                            </div>
                            <div class="input-group">
                                <label class="input-label">
                                    Battery Terminal Voltage (V) <span class="required">*</span>
                                    <span class="hint"><span class="example">(e.g., 12, 24, 48)</span></span>
                                </label>
                                <input type="number" id="hybrid_battery_voltage" class="enhanced-input" placeholder="48"
                                    min="12" max="96">
                            </div>
                        </div>
                    </div>

                    <!-- Charge Controller Parameters -->
                    <div id="controllerParams" class="form-section hidden">
                        <div class="section-header">
                            <div class="section-icon">☀️</div>
                            <div class="section-title">Charge Controller Parameters</div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="input-group">
                                <label class="input-label">
                                    Inverter Capacity <span class="required">*</span>
                                    <span class="hint">Enter in W or VA <span class="example">(e.g., 5000)</span></span>
                                </label>
                                <input type="number" id="controller_inv_capacity" class="enhanced-input"
                                    placeholder="5000" min="100" step="0.1">
                            </div>
                            <div class="input-group">
                                <label class="input-label">
                                    Solar Charge Controller Capacity (W) <span class="required">*</span>
                                    <span class="hint"><span class="example">(e.g., 3000)</span></span>
                                </label>
                                <input type="number" id="controller_capacity" class="enhanced-input" placeholder="3000"
                                    min="100">
                            </div>
                            <div class="input-group">
                                <label class="input-label">
                                    Battery Nominal Voltage (V) <span class="required">*</span>
                                    <span class="hint"><span class="example">(e.g., 12, 24, 48)</span></span>
                                </label>
                                <input type="number" id="controller_battery_voltage" class="enhanced-input"
                                    placeholder="48" min="12" max="96">
                            </div>
                            <div class="input-group">
                                <label class="input-label">
                                    Controller Maximum Solar Voltage (V) <span class="required">*</span>
                                    <span class="hint"><span class="example">(e.g., 150)</span></span>
                                </label>
                                <input type="number" id="controller_max_voltage" class="enhanced-input"
                                    placeholder="150" min="12" max="1000">
                            </div>
                        </div>
                    </div>

                    <!-- Solar Panel Parameters -->
                    <div id="panelParams" class="form-section hidden">
                        <div class="section-header">
                            <div class="section-icon">🌞</div>
                            <div class="section-title">Solar Panel Specifications</div>
                        </div>
                        <div id="panelOptionalNote" class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4"
                            style="display: none;">
                            <p class="text-sm text-blue-800"><strong>💡 Tip:</strong> Leave panel fields blank to get
                                recommendations for panel sizes ranging from 100W to 720W (system prefers 400W-650W
                                range)</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="input-group" id="panelCountGroup" style="display: none;">
                                <label class="input-label">
                                    Number of Panels <span class="required">*</span>
                                    <span class="hint">Total panels you have</span>
                                </label>
                                <input type="number" id="panel_count" class="enhanced-input" placeholder="10" min="1"
                                    max="1000">
                            </div>
                            <div class="input-group">
                                <label class="input-label">
                                    Solar Panel Power (W) <span class="required" id="panel_power_required">*</span>
                                    <span class="hint" id="panel_power_hint">Per panel <span class="example">(e.g.,
                                            550)</span></span>
                                </label>
                                <input type="number" id="panel_power" class="enhanced-input" placeholder="550" min="50">
                            </div>
                            <div class="input-group">
                                <label class="input-label">
                                    Open Circuit Voltage - VOC (V) <span class="required"
                                        id="panel_voc_required">*</span>
                                    <span class="hint"><span class="example">(e.g., 49.5)</span></span>
                                </label>
                                <input type="number" id="panel_voc" class="enhanced-input" placeholder="49.5" min="1"
                                    step="0.1">
                            </div>
                            <div class="input-group">
                                <label class="input-label">
                                    Short Circuit Current - ISC (A) <span class="required"
                                        id="panel_isc_required">*</span>
                                    <span class="hint"><span class="example">(e.g., 14)</span></span>
                                </label>
                                <input type="number" id="panel_isc" class="enhanced-input" placeholder="14" min="0.1"
                                    step="0.1">
                            </div>
                        </div>
                    </div>

                    <!-- Generate Button -->
                    <div class="text-center mt-8">
                        <button type="submit" class="btn-primary" id="generateBtn">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Generate Recommendation
                        </button>
                    </div>
                </form>

                <!-- Error Display -->
                <div id="errorDisplay" class="hidden error-message"></div>

                <!-- Results Area -->
                <div id="resultsArea" class="hidden mt-8"></div>
            </div>
        </div>
    </div>
</div>

<script>
    let selectedMode = null;

    // Load saved form data from localStorage
    function loadFormData() {
        const saved = localStorage.getItem('systemDesignerData');
        if (saved) {
            try {
                const data = JSON.parse(saved);
                // Restore form values
                Object.keys(data).forEach(key => {
                    const el = document.getElementById(key);
                    if (el) {
                        if (el.type === 'radio') {
                            if (el.value === data[key]) el.checked = true;
                        } else {
                            el.value = data[key];
                        }
                    }
                });
            } catch (e) {
                console.error('Error loading saved data:', e);
            }
        }
    }

    // Save form data to localStorage
    function saveFormData() {
        const form = document.getElementById('systemForm');
        if (!form) return;

        const data = {};
        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.type === 'radio') {
                if (input.checked) data[input.name] = input.value;
            } else {
                data[input.id] = input.value;
            }
        });

        localStorage.setItem('systemDesignerData', JSON.stringify(data));
    }

    // Auto-save on input changes
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('systemForm');
        if (form) {
            form.addEventListener('input', saveFormData);
        }
    });

    function selectMode(mode) {
        selectedMode = mode;

        // Update UI
        document.querySelectorAll('.mode-card').forEach(card => card.classList.remove('selected'));
        event.currentTarget.classList.add('selected');

        // Show forms after short delay
        setTimeout(() => {
            document.getElementById('modeSelection').classList.add('hidden');
            document.getElementById('designForms').classList.remove('hidden');

            // Show/hide panel count based on mode
            if (mode === 2) {
                document.getElementById('panelCountGroup').style.display = 'block';
                document.getElementById('panelOptionalNote').style.display = 'none';
                // Make panel specs required in Mode 2
                document.getElementById('panel_power').setAttribute('required', 'required');
                document.getElementById('panel_voc').setAttribute('required', 'required');
                document.getElementById('panel_isc').setAttribute('required', 'required');
                document.getElementById('panel_power_required').style.display = 'inline';
                document.getElementById('panel_voc_required').style.display = 'inline';
                document.getElementById('panel_isc_required').style.display = 'inline';
                document.getElementById('panel_power_hint').innerHTML = 'Per panel <span class="example">(e.g., 550)</span>';
            } else {
                document.getElementById('panelCountGroup').style.display = 'none';
                document.getElementById('panelOptionalNote').style.display = 'block';
                // Make panel specs optional in Mode 1
                document.getElementById('panel_power').removeAttribute('required');
                document.getElementById('panel_voc').removeAttribute('required');
                document.getElementById('panel_isc').removeAttribute('required');
                document.getElementById('panel_power_required').style.display = 'none';
                document.getElementById('panel_voc_required').style.display = 'none';
                document.getElementById('panel_isc_required').style.display = 'none';
                document.getElementById('panel_power_hint').innerHTML = 'Leave blank to get panel recommendations';
            }

            // Load any saved data
            loadFormData();

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }, 200);
    }

    function backToModeSelection() {
        document.getElementById('designForms').classList.add('hidden');
        document.getElementById('modeSelection').classList.remove('hidden');
        document.querySelectorAll('.mode-card').forEach(card => card.classList.remove('selected'));
        document.getElementById('resultsArea').classList.add('hidden');
        document.getElementById('errorDisplay').classList.add('hidden');
        selectedMode = null;
    }

    function toggleSystemType() {
        const systemType = document.querySelector('input[name="system_type"]:checked')?.value;

        if (systemType === 'hybrid') {
            document.getElementById('hybridParams').classList.remove('hidden');
            document.getElementById('controllerParams').classList.add('hidden');
            document.getElementById('panelParams').classList.remove('hidden');

            // Set hybrid fields as required
            document.getElementById('hybrid_inv_capacity').setAttribute('required', 'required');
            document.getElementById('hybrid_controller_capacity').setAttribute('required', 'required');
            document.getElementById('hybrid_nominal_voltage').setAttribute('required', 'required');
            document.getElementById('hybrid_max_voltage').setAttribute('required', 'required');
            document.getElementById('hybrid_max_current').setAttribute('required', 'required');
            document.getElementById('hybrid_battery_voltage').setAttribute('required', 'required');

            // Remove controller requirements
            document.getElementById('controller_inv_capacity').removeAttribute('required');
            document.getElementById('controller_capacity').removeAttribute('required');
            document.getElementById('controller_battery_voltage').removeAttribute('required');
            document.getElementById('controller_max_voltage').removeAttribute('required');
        } else if (systemType === 'controller') {
            document.getElementById('hybridParams').classList.add('hidden');
            document.getElementById('controllerParams').classList.remove('hidden');
            document.getElementById('panelParams').classList.remove('hidden');

            // Set controller fields as required
            document.getElementById('controller_inv_capacity').setAttribute('required', 'required');
            document.getElementById('controller_capacity').setAttribute('required', 'required');
            document.getElementById('controller_battery_voltage').setAttribute('required', 'required');
            document.getElementById('controller_max_voltage').setAttribute('required', 'required');

            // Remove hybrid requirements
            document.getElementById('hybrid_inv_capacity').removeAttribute('required');
            document.getElementById('hybrid_controller_capacity').removeAttribute('required');
            document.getElementById('hybrid_nominal_voltage').removeAttribute('required');
            document.getElementById('hybrid_max_voltage').removeAttribute('required');
            document.getElementById('hybrid_max_current').removeAttribute('required');
            document.getElementById('hybrid_battery_voltage').removeAttribute('required');
        }
    }

    function showError(message) {
        const errorDiv = document.getElementById('errorDisplay');
        errorDiv.textContent = message;
        errorDiv.classList.remove('hidden');
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideError() {
        document.getElementById('errorDisplay').classList.add('hidden');
    }

    async function generateRecommendation(event) {
        event.preventDefault();
        hideError();

        // Collect data
        const systemType = document.querySelector('input[name="system_type"]:checked')?.value;

        const data = {
            mode: selectedMode,
            system_type: systemType
        };

        // Collect parameters based on system type
        if (systemType === 'hybrid') {
            data.inverter_capacity = document.getElementById('hybrid_inv_capacity').value;
            data.controller_capacity = document.getElementById('hybrid_controller_capacity').value;
            data.nominal_voltage = document.getElementById('hybrid_nominal_voltage').value;
            data.max_voltage = document.getElementById('hybrid_max_voltage').value;
            data.voltage_range = document.getElementById('hybrid_voltage_range').value;
            data.max_current = document.getElementById('hybrid_max_current').value;
            data.battery_voltage = document.getElementById('hybrid_battery_voltage').value;
        } else {
            data.inverter_capacity = document.getElementById('controller_inv_capacity').value;
            data.controller_capacity = document.getElementById('controller_capacity').value;
            data.battery_voltage = document.getElementById('controller_battery_voltage').value;
            data.max_voltage = document.getElementById('controller_max_voltage').value;
        }

        // Panel parameters (handle optional in Mode 1)
        const panelPower = document.getElementById('panel_power').value;
        const panelVoc = document.getElementById('panel_voc').value;
        const panelIsc = document.getElementById('panel_isc').value;

        if (panelPower && panelVoc && panelIsc) {
            // User provided panel specs
            data.panel_power = panelPower;
            data.panel_voc = panelVoc;
            data.panel_isc = panelIsc;
            data.has_panel_specs = true;
        } else if (selectedMode === 2) {
            // Mode 2 requires panel specs
            showError('Please provide solar panel specifications for implementation mode');
            return;
        } else {
            // Mode 1 without panel specs - request recommendations
            data.has_panel_specs = false;
        }

        if (selectedMode === 2) {
            data.panel_count = document.getElementById('panel_count').value;
            if (!data.panel_count) {
                showError('Please enter the number of panels you have');
                return;
            }
        }

        // Determine endpoint based on mode
        const endpoint = selectedMode === 1 ? '../api/ai/design-planner.php' : '../api/ai/design-implementation.php';

        // Show loading overlay
        document.getElementById('loadingOverlay').classList.add('active');

        // Disable form
        const formElements = document.querySelectorAll('#systemForm input, #systemForm button');
        formElements.forEach(el => el.disabled = true);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                displayRecommendation(result.recommendation);
                hideError();
            } else {
                showError(result.message || 'Failed to generate recommendation. Please check your inputs and try again.');
            }
        } catch (e) {
            console.error('Error:', e);
            showError('Network error: Unable to connect to the recommendation service. Please check your connection and try again.');
        } finally {
            // Hide loading overlay
            document.getElementById('loadingOverlay').classList.remove('active');

            // Re-enable form
            formElements.forEach(el => el.disabled = false);
        }
    }

    function displayRecommendation(rec) {
        const container = document.getElementById('resultsArea');

        let html = `
        <div class="result-card">
            <div class="result-header">
                <div class="result-icon success">✓</div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Recommendation Generated</h3>
                    <p class="text-gray-600">Based on your system parameters</p>
                </div>
            </div>
        </div>
    `;

        // Panel recommendations (Mode 1 without specs provided)
        if (rec.panel_recommendations && rec.panel_recommendations.length > 0) {
            const gridCols = Math.min(rec.panel_recommendations.length, 4);
            html += `
            <div class="result-card">
                <h4 class="font-bold text-lg mb-3">💡 Panel Options</h4>
                <p class="text-sm text-gray-600 mb-4">Based on your controller specifications, here are recommended panel options:</p>
                <div class="grid grid-cols-1 md:grid-cols-${gridCols} gap-4">
        `;

            const colors = [
                { bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-900' },
                { bg: 'bg-green-50', border: 'border-green-200', text: 'text-green-900' },
                { bg: 'bg-purple-50', border: 'border-purple-200', text: 'text-purple-900' },
                { bg: 'bg-orange-50', border: 'border-orange-200', text: 'text-orange-900' }
            ];

            rec.panel_recommendations.forEach((option, idx) => {
                const color = colors[idx % 4];
                html += `
                <div class="${color.bg} p-4 rounded-lg border-2 ${color.border}">
                    <p class="text-sm font-semibold ${color.text}">Option ${idx + 1}</p>
                    <p class="text-2xl font-bold ${color.text} mb-2">${option.panel_size}</p>
                    <div class="text-xs text-gray-600 space-y-1 mb-3">
                        <p><strong>VOC:</strong> ${option.voc}</p>
                        <p><strong>ISC:</strong> ${option.imp || option.isc}</p>
                    </div>
                    <div class="bg-white bg-opacity-60 p-2 rounded">
                        <p class="text-sm font-bold ${color.text}">${option.max_panels_display || (option.max_panels + '× ' + option.panel_size)}</p>
                        <p class="text-xs text-gray-600 mt-1"><strong>Total:</strong> ${option.total_capacity}</p>
                        <p class="text-xs text-gray-600 mt-2">${option.arrangement}</p>
                    </div>
                </div>
            `;
            });

            html += `</div></div>`;
        }

        // Mode 1 specific: Max panels (when user provided specs)
        if (selectedMode === 1 && rec.max_panels && !rec.panel_recommendations) {
            html += `
            <div class="result-card">
                <h4 class="font-bold text-lg mb-3">📊 Maximum Configuration</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-blue-600 font-semibold">Configuration</p>
                        <p class="text-2xl font-bold text-blue-900">${rec.max_panels}</p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-green-600 font-semibold">Total Capacity</p>
                        <p class="text-2xl font-bold text-green-900">${rec.total_capacity}</p>
                    </div>
                </div>
            </div>
        `;
        }

        // Panel arrangement
        if (rec.arrangement) {
            html += `
            <div class="result-card">
                <h4 class="font-bold text-lg mb-3">🔧 Panel Arrangement</h4>
                <div class="bg-purple-50 p-4 rounded-lg mb-3">
                    <p class="font-semibold text-purple-900">${rec.arrangement.configuration}</p>
                    <p class="text-sm text-gray-600 mt-2">${rec.arrangement.explanation}</p>
                </div>
            </div>
        `;
        }

        // Breaker capacity
        if (rec.breaker) {
            html += `
            <div class="result-card">
                <h4 class="font-bold text-lg mb-3">⚡ DC Circuit Breaker</h4>
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <p class="font-semibold text-yellow-900">Recommended: ${rec.breaker.rating}</p>
                    <p class="text-sm text-gray-600 mt-2">${rec.breaker.explanation}</p>
                </div>
            </div>
        `;
        }

        // Wire sizing
        if (rec.wiring) {
            html += `
            <div class="result-card">
                <h4 class="font-bold text-lg mb-3">🔌 Wire Sizing</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-green-600 font-semibold">PV to Controller</p>
                        <p class="text-xl font-bold text-green-900">${rec.wiring.pv_wire}</p>
                    </div>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-blue-600 font-semibold">Battery to Inverter</p>
                        <p class="text-xl font-bold text-blue-900">${rec.wiring.battery_wire}</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mt-3">${rec.wiring.explanation}</p>
            </div>
        `;
        }

        // Summary
        if (rec.summary) {
            html += `
            <div class="result-card">
                <h4 class="font-bold text-lg mb-3">📋 Summary</h4>
                <div class="prose prose-sm max-w-none">
                    ${rec.summary}
                </div>
            </div>
        `;
        }

        // PDF Download Button
        html += `
        <div class="result-card bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-300">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-bold text-lg text-blue-900 mb-2">📄 Export Recommendation</h4>
                    <p class="text-sm text-blue-700">Download a professional PDF report to share with your client or installer</p>
                </div>
                <button onclick="downloadPDF()" class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download PDF
                </button>
            </div>
        </div>
    `;
    }

    // Warnings
    if (rec.warnings && rec.warnings.length > 0) {
        html += `
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
                <h4 class="font-bold text-red-800 mb-2">⚠️ Important Warnings</h4>
                <ul class="list-disc list-inside text-red-700 space-y-1">
                    ${rec.warnings.map(w => `<li>${w}</li>`).join('')}
                </ul>
            </div>
        `;
    }

    container.innerHTML = html;
    container.classList.remove('hidden');
    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>

<?php include '../includes/footer.php'; ?>