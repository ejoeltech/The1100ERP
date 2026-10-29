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

    /* Datasheet search + autofill */
    .datasheet-picker {
        margin-bottom: 1.25rem;
        padding: 0.75rem 0.85rem;
        border: 1px dashed #c7d2fe;
        border-radius: 12px;
        background: #f8faff;
        transition: border-color 0.2s, background 0.2s;
    }

    .datasheet-picker .input-label {
        margin-bottom: 0.35rem;
    }

    .datasheet-search {
        position: relative;
    }

    .datasheet-results {
        position: absolute;
        z-index: 40;
        left: 0;
        right: 0;
        top: calc(100% + 0.25rem);
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.15);
        max-height: 260px;
        overflow-y: auto;
    }

    .datasheet-result {
        display: block;
        width: 100%;
        text-align: left;
        padding: 0.5rem 0.7rem;
        border: 0;
        border-bottom: 1px solid #f1f5f9;
        background: #fff;
        cursor: pointer;
    }

    .datasheet-result:last-child {
        border-bottom: 0;
    }

    .datasheet-result:hover,
    .datasheet-result:focus {
        background: #eff6ff;
        outline: none;
    }

    .datasheet-result .ds-name {
        display: block;
        font-size: 0.8rem;
        font-weight: 700;
        color: #1e3a8a;
        line-height: 1.25;
    }

    .datasheet-result .ds-specs {
        display: block;
        font-size: 0.7rem;
        color: #64748b;
        margin-top: 0.1rem;
    }

    .datasheet-empty {
        padding: 0.6rem 0.7rem;
        font-size: 0.75rem;
        color: #64748b;
    }

    .datasheet-loaded {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem;
        margin-top: 0.45rem;
        font-size: 0.7rem;
        color: #047857;
    }

    .datasheet-loaded strong {
        color: #064e3b;
    }

    .datasheet-clear {
        border: 0;
        background: transparent;
        color: #6b7280;
        font-size: 0.68rem;
        text-decoration: underline;
        cursor: pointer;
        padding: 0;
    }

    .datasheet-picker.is-loaded {
        border-style: solid;
        border-color: #a7f3d0;
        background: #f0fdf4;
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

    /* Results Display - compact and responsive */
    .results-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 0.75rem;
        align-items: start;
    }

    @media (min-width: 900px) {
        .results-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    .result-card {
        background: white;
        border-radius: 12px;
        padding: 0.9rem 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        border-left: 3px solid var(--theme-primary);
        min-width: 0;
    }

    .result-card.span-all {
        grid-column: 1 / -1;
    }

    .result-card.danger {
        border-left-color: #ef4444;
        background: #fef2f2;
    }

    .result-head {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .result-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #111827;
        line-height: 1.25;
    }

    .result-subtitle {
        font-size: 0.75rem;
        color: #6b7280;
        margin-top: 0.1rem;
    }

    .result-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }

    .result-icon.success {
        background: linear-gradient(135deg, #d4fce3 0%, #a7f3d0 100%);
    }

    .result-icon.warning {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    }

    .panel-options {
        display: grid;
        gap: 0.5rem;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    }

    .panel-option {
        border: 1px solid #e5e7eb;
        border-left-width: 4px;
        border-radius: 10px;
        padding: 0.6rem 0.7rem;
        background: #fff;
        min-width: 0;
    }

    .fact-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }

    .fact-chip {
        display: inline-flex;
        align-items: baseline;
        gap: 0.3rem;
        background: #fff;
        border: 1px solid #e9d5ff;
        border-radius: 999px;
        padding: 0.1rem 0.55rem;
        font-size: 0.7rem;
        color: #6b21a8;
        white-space: nowrap;
    }

    .fact-chip strong {
        font-weight: 700;
        color: #4c1d95;
    }

    .details-toggle {
        display: inline-block;
        margin-top: 0.3rem;
        font-size: 0.7rem;
        font-weight: 600;
        color: #6b7280;
        cursor: pointer;
    }

    .details-toggle:hover {
        color: #374151;
    }

    /* Compact typography for the AI summary block */
    .result-summary {
        font-size: 0.8125rem;
        line-height: 1.5;
        color: #374151;
    }

    .result-summary :is(h2, h3, h4) {
        font-size: 0.9rem;
        font-weight: 700;
        color: #111827;
        margin: 0.6rem 0 0.25rem;
    }

    .result-summary > :first-child {
        margin-top: 0;
    }

    .result-summary p {
        margin: 0.25rem 0;
    }

    .result-summary ul,
    .result-summary ol {
        margin: 0.25rem 0 0.4rem 1.1rem;
        padding-left: 0.25rem;
    }

    .result-summary ul {
        list-style: disc;
    }

    .result-summary ol {
        list-style: decimal;
    }

    .result-summary li {
        margin: 0.1rem 0;
    }

    .result-summary table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
        margin: 0.35rem 0;
    }

    .result-summary th,
    .result-summary td {
        border: 1px solid #e5e7eb;
        padding: 0.25rem 0.4rem;
        text-align: left;
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

    /* Validation + accessibility */
    .input-error {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12) !important;
    }

    /* The page header is sticky; keep scrolled-to blocks clear of it. */
    #resultsArea,
    #errorDisplay {
        scroll-margin-top: 5rem;
    }

    .mode-card:focus-visible {
        outline: 3px solid var(--theme-primary);
        outline-offset: 4px;
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
                    <div class="mode-card" data-mode="1" role="button" tabindex="0"
                        aria-label="Select Design Planner mode" onclick="selectMode(1, this)">
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
                    <div class="mode-card" data-mode="2" role="button" tabindex="0"
                        aria-label="Select Design Implementation mode" onclick="selectMode(2, this)">
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

                <div id="modeBanner" role="status" aria-live="polite"
                    class="mb-4 text-sm font-semibold text-blue-700 bg-blue-50 border border-blue-100 rounded-lg px-4 py-2"></div>

                <form id="systemForm" onsubmit="generateRecommendation(event)" novalidate>
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
                        <div class="datasheet-picker" data-datasheet-picker data-category="inverter">
                            <label class="input-label" for="dsSearch_inverter">
                                Load from datasheet
                                <span class="hint">Search the catalog by brand or model; the fields below fill automatically</span>
                            </label>
                            <div class="datasheet-search">
                                <input type="search" id="dsSearch_inverter" class="enhanced-input"
                                    placeholder="e.g. Victron MultiPlus-II 48/5000" autocomplete="off"
                                    data-datasheet-input data-no-persist aria-autocomplete="list" aria-expanded="false">
                                <div class="datasheet-results hidden" data-datasheet-results role="listbox"
                                    aria-label="Inverter datasheet results"></div>
                            </div>
                            <p class="datasheet-loaded hidden" data-datasheet-loaded role="status" aria-live="polite"></p>
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
                        <div class="datasheet-picker" data-datasheet-picker data-category="controller">
                            <label class="input-label" for="dsSearch_controller">
                                Load from datasheet
                                <span class="hint">Search the catalog by brand or model; the fields below fill automatically</span>
                            </label>
                            <div class="datasheet-search">
                                <input type="search" id="dsSearch_controller" class="enhanced-input"
                                    placeholder="e.g. Victron SmartSolar MPPT 150/70" autocomplete="off"
                                    data-datasheet-input data-no-persist aria-autocomplete="list" aria-expanded="false">
                                <div class="datasheet-results hidden" data-datasheet-results role="listbox"
                                    aria-label="Charge controller datasheet results"></div>
                            </div>
                            <p class="datasheet-loaded hidden" data-datasheet-loaded role="status" aria-live="polite"></p>
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

                    <!-- Battery Bank Parameters -->
                    <div id="batteryParams" class="form-section hidden">
                        <div class="section-header">
                            <div class="section-icon">🔋</div>
                            <div class="section-title">Battery Bank <span class="text-sm font-normal text-gray-500">(optional)</span></div>
                        </div>
                        <div class="datasheet-picker" data-datasheet-picker data-category="battery">
                            <label class="input-label" for="dsSearch_battery">
                                Load from datasheet
                                <span class="hint">Search a battery model to fill the bank details and terminal voltage</span>
                            </label>
                            <div class="datasheet-search">
                                <input type="search" id="dsSearch_battery" class="enhanced-input"
                                    placeholder="e.g. Pylontech US5000" autocomplete="off"
                                    data-datasheet-input data-no-persist aria-autocomplete="list" aria-expanded="false">
                                <div class="datasheet-results hidden" data-datasheet-results role="listbox"
                                    aria-label="Battery datasheet results"></div>
                            </div>
                            <p class="datasheet-loaded hidden" data-datasheet-loaded role="status" aria-live="polite"></p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="input-group">
                                <label class="input-label" for="battery_capacity_kwh">
                                    Bank Capacity (kWh)
                                    <span class="hint">Total usable energy <span class="example">(e.g., 4.8)</span></span>
                                </label>
                                <input type="number" id="battery_capacity_kwh" class="enhanced-input" placeholder="4.8"
                                    min="0" step="0.1">
                            </div>
                            <div class="input-group">
                                <label class="input-label" for="battery_quantity">
                                    Number of Batteries
                                    <span class="hint">Units in the bank <span class="example">(e.g., 1)</span></span>
                                </label>
                                <input type="number" id="battery_quantity" class="enhanced-input" placeholder="1" min="1"
                                    max="200" step="1">
                            </div>
                            <div class="input-group">
                                <label class="input-label" for="battery_chemistry">
                                    Chemistry
                                    <span class="hint">Battery technology</span>
                                </label>
                                <select id="battery_chemistry" class="enhanced-select">
                                    <option value="">Not specified</option>
                                    <option value="lithium">Lithium (LiFePO4 / NMC)</option>
                                    <option value="lead_acid">Lead-acid (AGM / GEL / flooded)</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="input-group">
                                <label class="input-label" for="battery_max_charge_current">
                                    Max Charge Current (A)
                                    <span class="hint">Per battery or bank <span class="example">(e.g., 50)</span></span>
                                </label>
                                <input type="number" id="battery_max_charge_current" class="enhanced-input"
                                    placeholder="50" min="0" step="0.1">
                            </div>
                            <div class="input-group">
                                <label class="input-label" for="battery_max_discharge_current">
                                    Max Discharge Current (A)
                                    <span class="hint">Per battery or bank <span class="example">(e.g., 100)</span></span>
                                </label>
                                <input type="number" id="battery_max_discharge_current" class="enhanced-input"
                                    placeholder="100" min="0" step="0.1">
                            </div>
                        </div>
                    </div>

                    <!-- Solar Panel Parameters -->
                    <div id="panelParams" class="form-section hidden">
                        <div class="section-header">
                            <div class="section-icon">🌞</div>
                            <div class="section-title">Solar Panel Specifications</div>
                        </div>
                        <div class="datasheet-picker" data-datasheet-picker data-category="panel">
                            <label class="input-label" for="dsSearch_panel">
                                Load from datasheet
                                <span class="hint">Search a panel model to fill power, VOC and ISC</span>
                            </label>
                            <div class="datasheet-search">
                                <input type="search" id="dsSearch_panel" class="enhanced-input"
                                    placeholder="e.g. Jinko Tiger Neo 620W" autocomplete="off"
                                    data-datasheet-input data-no-persist aria-autocomplete="list" aria-expanded="false">
                                <div class="datasheet-results hidden" data-datasheet-results role="listbox"
                                    aria-label="Solar panel datasheet results"></div>
                            </div>
                            <p class="datasheet-loaded hidden" data-datasheet-loaded role="status" aria-live="polite"></p>
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
                <div id="errorDisplay" class="hidden error-message" role="alert" aria-live="polite"></div>

                <!-- Results Area -->
                <div id="resultsArea" class="hidden mt-8" aria-live="polite"></div>
            </div>
        </div>
    </div>
</div>

<script>
    'use strict';

    const STORAGE_KEY = 'systemDesignerData';

    const MODE_INFO = {
        1: {
            title: 'Design Planner',
            hint: 'Panel specifications are optional. Leave them blank to receive panel size recommendations.'
        },
        2: {
            title: 'Design Implementation',
            hint: 'Panel power, VOC, ISC and the number of panels you already own are all required.'
        }
    };

    const REQUIRED_FIELDS = {
        hybrid: [
            'hybrid_inv_capacity',
            'hybrid_controller_capacity',
            'hybrid_nominal_voltage',
            'hybrid_max_voltage',
            'hybrid_max_current',
            'hybrid_battery_voltage'
        ],
        controller: [
            'controller_inv_capacity',
            'controller_capacity',
            'controller_battery_voltage',
            'controller_max_voltage'
        ]
    };

    const PANEL_FIELDS = ['panel_power', 'panel_voc', 'panel_isc'];

    const OPTION_COLORS = [
        { accent: 'border-l-blue-400', text: 'text-blue-900' },
        { accent: 'border-l-green-400', text: 'text-green-900' },
        { accent: 'border-l-purple-400', text: 'text-purple-900' },
        { accent: 'border-l-orange-400', text: 'text-orange-900' }
    ];

    let selectedMode = null;
    let lastRecommendation = null;
    let lastFormData = null;

    /* ------------------------------------------------------------------ *
     * Helpers
     * ------------------------------------------------------------------ */

    function el(id) {
        return document.getElementById(id);
    }

    // Every AI-provided value passes through here before it reaches innerHTML.
    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // `summary` is documented as HTML. Keep a small formatting whitelist and
    // drop everything else (scripts, styles, iframes, event handlers, URLs) so
    // a prompt-injected AI response cannot execute script in the page.
    const ALLOWED_TAGS = new Set([
        'P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'UL', 'OL', 'LI',
        'H2', 'H3', 'H4', 'H5', 'H6', 'SPAN', 'DIV', 'TABLE', 'THEAD', 'TBODY',
        'TR', 'TH', 'TD', 'HR', 'SMALL', 'CODE'
    ]);
    const ALLOWED_ATTRS = new Set(['class']);
    const DROP_WITH_CONTENT = new Set([
        'SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'NOSCRIPT',
        'TEMPLATE', 'SVG', 'MATH', 'LINK', 'META', 'BASE'
    ]);

    function sanitizeAiHtml(html) {
        const source = String(html === null || html === undefined ? '' : html);
        if (!source.trim()) {
            return '';
        }

        const doc = new DOMParser().parseFromString(
            '<div id="ai-root">' + source + '</div>',
            'text/html'
        );
        const root = doc.getElementById('ai-root');
        if (!root) {
            return '';
        }

        const clean = (node) => {
            Array.from(node.childNodes).forEach((child) => {
                if (child.nodeType === 3) {
                    return; // text
                }
                if (child.nodeType !== 1) {
                    child.remove();
                    return;
                }

                if (DROP_WITH_CONTENT.has(child.tagName)) {
                    child.remove();
                    return;
                }

                if (!ALLOWED_TAGS.has(child.tagName)) {
                    child.replaceWith(doc.createTextNode(child.textContent || ''));
                    return;
                }

                Array.from(child.attributes).forEach((attr) => {
                    if (!ALLOWED_ATTRS.has(attr.name.toLowerCase())) {
                        child.removeAttribute(attr.name);
                    }
                });

                clean(child);
            });
        };

        clean(root);
        return root.innerHTML;
    }

    function setBusy(busy) {
        const overlay = el('loadingOverlay');
        if (overlay) {
            overlay.classList.toggle('active', busy);
            overlay.setAttribute('aria-hidden', busy ? 'false' : 'true');
        }
        document
            .querySelectorAll('#systemForm input, #systemForm select, #systemForm button')
            .forEach((node) => {
                node.disabled = busy;
            });
        const form = el('systemForm');
        if (form) {
            form.setAttribute('aria-busy', busy ? 'true' : 'false');
        }
    }

    async function readJsonResponse(response) {
        const text = await response.text();
        if (!text.trim()) {
            throw new Error('The server returned an empty response (HTTP ' + response.status + ').');
        }
        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error(
                'The server returned an unexpected response (HTTP ' + response.status +
                ') instead of JSON. Check the server error log.'
            );
        }
    }

    /* ------------------------------------------------------------------ *
     * Form persistence
     * ------------------------------------------------------------------ */

    function collectFormData() {
        const form = el('systemForm');
        if (!form) {
            return {};
        }

        const data = {};
        form.querySelectorAll('input, select, textarea').forEach((input) => {
            if (input.hasAttribute('data-no-persist')) {
                return;
            }
            if (input.type === 'radio') {
                if (input.checked) {
                    data[input.name] = input.value;
                }
                return;
            }
            if (input.type === 'checkbox') {
                data[input.id] = input.checked;
                return;
            }
            if (input.id) {
                data[input.id] = input.value;
            }
        });
        return data;
    }

    function saveFormData() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(collectFormData()));
        } catch (error) {
            // Private browsing / storage disabled: persistence is optional.
        }
    }

    function loadFormData() {
        let data = null;
        try {
            data = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
        } catch (error) {
            data = null;
        }
        if (!data || typeof data !== 'object') {
            return;
        }

        const form = el('systemForm');
        if (!form) {
            return;
        }

        form.querySelectorAll('input, select, textarea').forEach((input) => {
            if (input.hasAttribute('data-no-persist')) {
                return;
            }
            if (input.type === 'radio') {
                if (Object.prototype.hasOwnProperty.call(data, input.name)) {
                    input.checked = String(input.value) === String(data[input.name]);
                }
                return;
            }
            if (input.id && Object.prototype.hasOwnProperty.call(data, input.id)) {
                input.value = data[input.id];
            }
        });
    }

    /* ------------------------------------------------------------------ *
     * Datasheet autofill
     * ------------------------------------------------------------------ */

    const DATASHEET_FIELDS = {
        inverter: {
            capacity_w: 'hybrid_inv_capacity',
            controller_capacity_w: 'hybrid_controller_capacity',
            nominal_voltage: 'hybrid_nominal_voltage',
            max_voltage: 'hybrid_max_voltage',
            voltage_range: 'hybrid_voltage_range',
            max_current: 'hybrid_max_current',
            battery_voltage: 'hybrid_battery_voltage'
        },
        controller: {
            capacity_w: 'controller_inv_capacity',
            controller_capacity_w: 'controller_capacity',
            battery_voltage: 'controller_battery_voltage',
            max_voltage: 'controller_max_voltage'
        },
        panel: {
            power_w: 'panel_power',
            voc: 'panel_voc',
            isc: 'panel_isc'
        }
    };

    const DATASHEET_SUMMARY = {
        inverter: [['capacity_w', 'W'], ['controller_capacity_w', 'W PV'], ['max_voltage', 'V max'], ['battery_voltage', 'V battery']],
        controller: [['controller_capacity_w', 'W'], ['max_voltage', 'V max'], ['battery_voltage', 'V battery']],
        battery: [['battery_voltage', 'V'], ['capacity_ah', 'Ah'], ['capacity_kwh', 'kWh'], ['chemistry', '']],
        panel: [['power_w', 'W'], ['voc', 'V VOC'], ['isc', 'A ISC'], ['efficiency', '%']]
    };

    function setFieldValue(id, value) {
        const input = el(id);
        if (!input) {
            return false;
        }
        input.value = value;
        clearFieldError(input);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    function datasheetChemistry(value) {
        const text = String(value || '').toLowerCase();
        if (text === '') {
            return '';
        }
        if (text.indexOf('lith') !== -1 || text.indexOf('lifepo') !== -1 || text.indexOf('nmc') !== -1) {
            return 'lithium';
        }
        if (text.indexOf('lead') !== -1 || text.indexOf('agm') !== -1 || text.indexOf('gel') !== -1 || text.indexOf('flood') !== -1) {
            return 'lead_acid';
        }
        return 'other';
    }

    function datasheetSummary(category, specs) {
        const fields = DATASHEET_SUMMARY[category] || [];
        return fields
            .filter((field) => specs[field[0]] !== undefined && specs[field[0]] !== null && specs[field[0]] !== '')
            .map((field) => {
                const value = specs[field[0]];
                return field[1] ? value + ' ' + field[1] : String(value);
            })
            .join(' \u00b7 ');
    }

    function applyDatasheet(picker, category, result) {
        const specs = result.specs || {};
        let filled = 0;

        if (category === 'battery') {
            const checked = document.querySelector('input[name="system_type"]:checked');
            const voltageField = checked && checked.value === 'controller'
                ? 'controller_battery_voltage'
                : 'hybrid_battery_voltage';

            if (specs.battery_voltage !== undefined && specs.battery_voltage !== null) {
                if (setFieldValue(voltageField, specs.battery_voltage)) {
                    filled++;
                }
            }

            let kwh = specs.capacity_kwh;
            if ((kwh === undefined || kwh === null)
                && specs.capacity_ah !== undefined && specs.capacity_ah !== null
                && specs.battery_voltage !== undefined && specs.battery_voltage !== null) {
                kwh = Math.round((Number(specs.capacity_ah) * Number(specs.battery_voltage) / 1000) * 100) / 100;
            }
            if (kwh !== undefined && kwh !== null) {
                if (setFieldValue('battery_capacity_kwh', kwh)) {
                    filled++;
                }
            }
            if (specs.chemistry) {
                if (setFieldValue('battery_chemistry', datasheetChemistry(specs.chemistry))) {
                    filled++;
                }
            }
            if (specs.max_charge_current !== undefined && specs.max_charge_current !== null) {
                if (setFieldValue('battery_max_charge_current', specs.max_charge_current)) {
                    filled++;
                }
            }
            if (specs.max_discharge_current !== undefined && specs.max_discharge_current !== null) {
                if (setFieldValue('battery_max_discharge_current', specs.max_discharge_current)) {
                    filled++;
                }
            }
            const quantity = el('battery_quantity');
            if (quantity && String(quantity.value || '').trim() === '') {
                quantity.value = '1';
                quantity.dispatchEvent(new Event('input', { bubbles: true }));
            }
        } else {
            Object.keys(DATASHEET_FIELDS[category] || {}).forEach((key) => {
                if (specs[key] === undefined || specs[key] === null || specs[key] === '') {
                    return;
                }
                if (setFieldValue(DATASHEET_FIELDS[category][key], specs[key])) {
                    filled++;
                }
            });
        }

        const badge = picker.querySelector('[data-datasheet-loaded]');
        const input = picker.querySelector('[data-datasheet-input]');
        if (badge) {
            badge.innerHTML = 'Loaded: <strong>' + esc(result.name) + '</strong>' +
                '<span>' + filled + ' field' + (filled === 1 ? '' : 's') + ' filled</span>' +
                '<button type="button" class="datasheet-clear" data-datasheet-clear>Clear selection</button>';
            badge.classList.remove('hidden');
        }
        picker.classList.add('is-loaded');
        if (input) {
            input.value = result.name;
        }
        saveFormData();
        clearAllFieldErrors();
    }

    function initDatasheetPickers() {
        document.querySelectorAll('[data-datasheet-picker]').forEach((picker) => {
            const input = picker.querySelector('[data-datasheet-input]');
            const results = picker.querySelector('[data-datasheet-results]');
            if (!input || !results) {
                return;
            }

            const category = picker.dataset.category;
            let debounceTimer = null;
            let requestController = null;
            let currentResults = [];

            const close = () => {
                results.classList.add('hidden');
                input.setAttribute('aria-expanded', 'false');
            };

            const select = (result) => {
                if (!result) {
                    return;
                }
                applyDatasheet(picker, category, result);
                close();
            };

            const render = (list) => {
                currentResults = list;
                if (!list.length) {
                    results.innerHTML = '<div class="datasheet-empty">No matching datasheets. Enter the specs manually or add the model in the catalog.</div>';
                    results.classList.remove('hidden');
                    input.setAttribute('aria-expanded', 'true');
                    return;
                }

                results.innerHTML = list.map((result, index) => {
                    const summary = datasheetSummary(category, result.specs || {});
                    return `<button type="button" class="datasheet-result" role="option" data-datasheet-index="${index}">
                        <span class="ds-name">${esc(result.name)}</span>
                        ${summary ? `<span class="ds-specs">${esc(summary)}</span>` : ''}
                    </button>`;
                }).join('');
                results.classList.remove('hidden');
                input.setAttribute('aria-expanded', 'true');
            };

            const search = async (query) => {
                if (requestController) {
                    requestController.abort();
                }
                requestController = new AbortController();
                results.innerHTML = '<div class="datasheet-empty">Searching catalog...</div>';
                results.classList.remove('hidden');
                input.setAttribute('aria-expanded', 'true');

                try {
                    const url = '../api/designer/datasheets.php?category=' + encodeURIComponent(category)
                        + '&q=' + encodeURIComponent(query);
                    const response = await fetch(url, {
                        signal: requestController.signal,
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await readJsonResponse(response);
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'The catalog is unavailable.');
                    }
                    render(Array.isArray(data.results) ? data.results : []);
                } catch (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                    results.innerHTML = '<div class="datasheet-empty">Could not reach the equipment catalog. Enter the values manually.</div>';
                }
            };

            input.addEventListener('input', () => {
                if (debounceTimer) {
                    window.clearTimeout(debounceTimer);
                }
                const query = input.value.trim();
                debounceTimer = window.setTimeout(() => search(query), 220);
            });

            input.addEventListener('focus', () => {
                if (!input.value.trim()) {
                    search('');
                }
            });

            input.addEventListener('keydown', (event) => {
                const options = Array.from(results.querySelectorAll('.datasheet-result'));
                if (event.key === 'Escape') {
                    close();
                    return;
                }
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    if (!options.length) {
                        return;
                    }
                    event.preventDefault();
                    const currentIndex = options.indexOf(document.activeElement);
                    let nextIndex = event.key === 'ArrowDown' ? currentIndex + 1 : currentIndex - 1;
                    if (currentIndex === -1) {
                        nextIndex = event.key === 'ArrowDown' ? 0 : options.length - 1;
                    }
                    nextIndex = Math.max(0, Math.min(options.length - 1, nextIndex));
                    options[nextIndex].focus();
                    return;
                }
                if (event.key === 'Enter') {
                    if (results.classList.contains('hidden')) {
                        event.preventDefault();
                        search(input.value.trim());
                        return;
                    }
                    const target = options.includes(document.activeElement) ? document.activeElement : options[0];
                    if (target) {
                        event.preventDefault();
                        target.click();
                    }
                }
            });

            results.addEventListener('click', (event) => {
                const button = event.target.closest('[data-datasheet-index]');
                if (!button) {
                    return;
                }
                select(currentResults[Number(button.dataset.datasheetIndex)]);
            });

            picker.addEventListener('click', (event) => {
                if (event.target.matches('[data-datasheet-clear]')) {
                    const badge = picker.querySelector('[data-datasheet-loaded]');
                    if (badge) {
                        badge.classList.add('hidden');
                        badge.innerHTML = '';
                    }
                    picker.classList.remove('is-loaded');
                    input.value = '';
                    input.focus();
                }
            });

            document.addEventListener('click', (event) => {
                if (!picker.contains(event.target)) {
                    close();
                }
            });
        });
    }

    /* ------------------------------------------------------------------ *
     * Mode selection
     * ------------------------------------------------------------------ */

    function selectMode(mode, card) {
        selectedMode = Number(mode);

        document.querySelectorAll('.mode-card').forEach((node) => {
            const isSelected = node === card || Number(node.dataset.mode) === selectedMode;
            node.classList.toggle('selected', isSelected);
            node.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });

        const banner = el('modeBanner');
        if (banner) {
            const info = MODE_INFO[selectedMode];
            banner.textContent = info ? info.title + ' - ' + info.hint : '';
        }

        el('modeSelection').classList.add('hidden');
        el('designForms').classList.remove('hidden');

        applyModeRequirements();
        hideError();
        clearResult();

        loadFormData();
        toggleSystemType();

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function applyModeRequirements() {
        const isImplementation = selectedMode === 2;

        el('panelCountGroup').style.display = isImplementation ? '' : 'none';
        el('panelOptionalNote').style.display = isImplementation ? 'none' : 'block';

        PANEL_FIELDS.forEach((id) => {
            const input = el(id);
            const required = el(id + '_required');
            if (input) {
                if (isImplementation) {
                    input.setAttribute('required', 'required');
                    input.setAttribute('aria-required', 'true');
                } else {
                    input.removeAttribute('required');
                    input.removeAttribute('aria-required');
                    clearFieldError(input);
                }
            }
            if (required) {
                required.style.display = isImplementation ? 'inline' : 'none';
            }
        });

        const hint = el('panel_power_hint');
        if (hint) {
            hint.innerHTML = isImplementation
                ? 'Per panel <span class="example">(e.g., 550)</span>'
                : 'Leave blank to get panel recommendations';
        }
    }

    function backToModeSelection() {
        selectedMode = null;
        lastRecommendation = null;
        lastFormData = null;

        el('designForms').classList.add('hidden');
        el('modeSelection').classList.remove('hidden');

        document.querySelectorAll('.mode-card').forEach((card) => {
            card.classList.remove('selected');
            card.setAttribute('aria-pressed', 'false');
        });

        clearResult();
        hideError();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function toggleSystemType() {
        const checked = document.querySelector('input[name="system_type"]:checked');
        const systemType = checked ? checked.value : null;

        el('hybridParams').classList.toggle('hidden', systemType !== 'hybrid');
        el('controllerParams').classList.toggle('hidden', systemType !== 'controller');
        el('batteryParams').classList.toggle('hidden', systemType === null);
        el('panelParams').classList.toggle('hidden', systemType === null);

        Object.keys(REQUIRED_FIELDS).forEach((type) => {
            const active = type === systemType;
            REQUIRED_FIELDS[type].forEach((id) => {
                const input = el(id);
                if (!input) {
                    return;
                }
                if (active) {
                    input.setAttribute('required', 'required');
                    input.setAttribute('aria-required', 'true');
                } else {
                    input.removeAttribute('required');
                    input.removeAttribute('aria-required');
                    clearFieldError(input);
                }
            });
        });
    }

    /* ------------------------------------------------------------------ *
     * Validation + errors
     * ------------------------------------------------------------------ */

    function showError(message) {
        const errorDiv = el('errorDisplay');
        if (!errorDiv) {
            return;
        }
        errorDiv.textContent = message;
        errorDiv.classList.remove('hidden');
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideError() {
        const errorDiv = el('errorDisplay');
        if (errorDiv) {
            errorDiv.classList.add('hidden');
            errorDiv.textContent = '';
        }
    }

    function clearFieldError(input) {
        if (input) {
            input.classList.remove('input-error');
            input.removeAttribute('aria-invalid');
        }
    }

    function clearAllFieldErrors() {
        document.querySelectorAll('#systemForm .input-error').forEach((input) => {
            input.classList.remove('input-error');
            input.removeAttribute('aria-invalid');
        });
    }

    function markFieldError(input, message) {
        if (input) {
            input.classList.add('input-error');
            input.setAttribute('aria-invalid', 'true');
            input.focus({ preventScroll: true });
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        showError(message);
    }

    function labelFor(input) {
        if (!input) {
            return 'this field';
        }
        const group = input.closest('.input-group');
        const label = group ? group.querySelector('.input-label') : null;
        if (!label) {
            return 'this field';
        }
        const clone = label.cloneNode(true);
        clone.querySelectorAll('.hint, .required, .example').forEach((node) => node.remove());
        const text = clone.textContent.replace(/\s+/g, ' ').trim();
        return text || 'this field';
    }

    function positiveNumber(input) {
        const raw = String(input.value || '').trim();
        if (raw === '') {
            return { ok: false, message: 'Please fill in ' + labelFor(input) + '.' };
        }
        const value = Number(raw);
        if (!isFinite(value) || value <= 0) {
            return { ok: false, message: labelFor(input) + ' must be greater than zero.' };
        }
        return { ok: true, value: value };
    }

    function validateForm() {
        const systemTypeField = document.querySelector('input[name="system_type"]:checked');
        if (!systemTypeField) {
            return {
                ok: false,
                field: el('type_hybrid'),
                message: 'Please choose a system type: hybrid inverter or charge controller.'
            };
        }

        const systemType = systemTypeField.value;
        const required = REQUIRED_FIELDS[systemType] || [];

        for (let i = 0; i < required.length; i++) {
            const input = el(required[i]);
            if (!input) {
                continue;
            }
            const check = positiveNumber(input);
            if (!check.ok) {
                return { ok: false, field: input, message: check.message };
            }
        }

        const panelInputs = PANEL_FIELDS.map(el).filter(Boolean);
        const filled = panelInputs.filter((input) => String(input.value || '').trim() !== '');

        if (filled.length > 0 && filled.length < panelInputs.length) {
            const missing = panelInputs.find((input) => String(input.value || '').trim() === '');
            return {
                ok: false,
                field: missing,
                message: 'Enter all three panel specifications (power, VOC and ISC), or leave all three blank.'
            };
        }

        if (selectedMode === 2 && filled.length === 0) {
            return {
                ok: false,
                field: panelInputs[0],
                message: 'Implementation mode needs the solar panel specifications.'
            };
        }

        for (let i = 0; i < filled.length; i++) {
            const check = positiveNumber(filled[i]);
            if (!check.ok) {
                return { ok: false, field: filled[i], message: check.message };
            }
        }

        if (selectedMode === 2) {
            const countInput = el('panel_count');
            const raw = String(countInput.value || '').trim();
            if (raw === '') {
                return { ok: false, field: countInput, message: 'Enter how many panels you have.' };
            }
            const count = Number(raw);
            if (!Number.isInteger(count) || count < 1 || count > 1000) {
                return {
                    ok: false,
                    field: countInput,
                    message: 'Number of panels must be a whole number between 1 and 1000.'
                };
            }
        }

        return { ok: true };
    }

    /* ------------------------------------------------------------------ *
     * Request + render
     * ------------------------------------------------------------------ */

    function buildRequestData() {
        const formData = collectFormData();
        const systemType = formData.system_type;

        const data = {
            mode: selectedMode,
            system_type: systemType,
            has_panel_specs: false
        };

        if (systemType === 'hybrid') {
            data.inverter_capacity = formData.hybrid_inv_capacity;
            data.controller_capacity = formData.hybrid_controller_capacity;
            data.nominal_voltage = formData.hybrid_nominal_voltage;
            data.max_voltage = formData.hybrid_max_voltage;
            data.voltage_range = formData.hybrid_voltage_range;
            data.max_current = formData.hybrid_max_current;
            data.battery_voltage = formData.hybrid_battery_voltage;
        } else {
            data.inverter_capacity = formData.controller_inv_capacity;
            data.controller_capacity = formData.controller_capacity;
            data.battery_voltage = formData.controller_battery_voltage;
            data.max_voltage = formData.controller_max_voltage;
        }

        const hasPanelSpecs = PANEL_FIELDS.every((id) => String(formData[id] || '').trim() !== '');
        if (hasPanelSpecs) {
            data.panel_power = formData.panel_power;
            data.panel_voc = formData.panel_voc;
            data.panel_isc = formData.panel_isc;
            data.has_panel_specs = true;
        }

        const batteryKeys = [
            'battery_capacity_kwh',
            'battery_chemistry',
            'battery_quantity',
            'battery_max_charge_current',
            'battery_max_discharge_current'
        ];
        batteryKeys.forEach((key) => {
            const value = formData[key];
            if (value !== undefined && String(value).trim() !== '') {
                data[key] = value;
            }
        });

        if (selectedMode === 2) {
            data.panel_count = Number(formData.panel_count);
        }

        return data;
    }

    async function generateRecommendation(event) {
        event.preventDefault();
        hideError();
        clearAllFieldErrors();

        if (!selectedMode) {
            showError('Please choose a design mode first.');
            return;
        }

        const validation = validateForm();
        if (!validation.ok) {
            markFieldError(validation.field, validation.message);
            return;
        }

        const payload = buildRequestData();
        // Same shape the PDF endpoint expects (mode, system_type, capacities...).
        lastFormData = payload;

        const endpoint = selectedMode === 1
            ? '../api/ai/design-planner.php'
            : '../api/ai/design-implementation.php';

        clearResult();
        setBusy(true);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await readJsonResponse(response);

            if (!response.ok || !result.success) {
                throw new Error(result.message || ('The service returned HTTP ' + response.status + '.'));
            }
            if (!result.recommendation || typeof result.recommendation !== 'object') {
                throw new Error('The recommendation service returned an unexpected response. Please try again.');
            }

            lastRecommendation = result.recommendation;
            displayRecommendation(result.recommendation);
        } catch (error) {
            console.error('Recommendation error:', error);
            lastRecommendation = null;
            showError(
                error && error.message
                    ? error.message
                    : 'Unable to generate a recommendation. Please check your inputs and try again.'
            );
        } finally {
            setBusy(false);
        }
    }

    function clearResult() {
        const container = el('resultsArea');
        if (container) {
            container.innerHTML = '';
            container.classList.add('hidden');
        }
    }

    function currentSystemTypeLabel() {
        const checked = document.querySelector('input[name="system_type"]:checked');
        if (!checked) {
            return 'System';
        }
        return checked.value === 'hybrid' ? 'Hybrid Inverter' : 'Charge Controller';
    }

    function renderArrangement(arrangement, extraClass) {
        if (!arrangement) {
            return '';
        }

        const wrapper = 'result-card ' + (extraClass || 'span-all');

        if (typeof arrangement === 'string') {
            return `
            <div class="${wrapper}">
                <h4 class="font-bold text-base mb-2">&#128295; Panel Arrangement</h4>
                <p class="text-sm text-gray-700">${esc(arrangement)}</p>
            </div>`;
        }

        const facts = [
            ['Strings', arrangement.strings],
            ['Panels/string', arrangement.panels_per_string],
            ['Total VOC', arrangement.total_voc],
            ['Total current', arrangement.total_current]
        ].filter((fact) => fact[1] !== undefined && fact[1] !== null && fact[1] !== '');

        const chips = facts.map((fact) =>
            `<span class="fact-chip">${esc(fact[0])} <strong>${esc(fact[1])}</strong></span>`
        ).join('');

        return `
        <div class="${wrapper}">
            <h4 class="font-bold text-base mb-2">&#128295; Panel Arrangement</h4>
            <p class="text-sm font-semibold text-purple-900 leading-snug">${esc(arrangement.configuration)}</p>
            ${chips ? '<div class="fact-chips mt-2">' + chips + '</div>' : ''}
            ${arrangement.explanation
                ? `<details><summary class="details-toggle">Why this arrangement</summary>
                     <p class="text-xs text-gray-600 mt-1 leading-relaxed">${esc(arrangement.explanation)}</p>
                   </details>`
                : ''}
        </div>`;
    }

    function renderBreaker(breaker, extraClass) {
        if (!breaker) {
            return '';
        }
        const isString = typeof breaker === 'string';
        const rating = isString ? breaker : breaker.rating;
        const explanation = !isString && breaker.explanation ? breaker.explanation : '';

        return `
        <div class="result-card ${extraClass || ''}">
            <h4 class="font-bold text-base mb-2">&#9889; DC Breaker</h4>
            <p class="text-xl font-bold text-yellow-900 leading-tight">${esc(rating)}</p>
            ${explanation
                ? `<details><summary class="details-toggle">Calculation details</summary>
                     <p class="text-xs text-gray-600 mt-1 leading-relaxed">${esc(explanation)}</p>
                   </details>`
                : ''}
        </div>`;
    }

    function renderWiring(wiring, extraClass) {
        if (!wiring) {
            return '';
        }

        const wrapper = 'result-card ' + (extraClass || '');

        if (typeof wiring === 'string') {
            return `
            <div class="${wrapper}">
                <h4 class="font-bold text-base mb-2">&#128268; Wire Sizing</h4>
                <p class="text-sm text-gray-700">${esc(wiring)}</p>
            </div>`;
        }

        return `
        <div class="${wrapper}">
            <h4 class="font-bold text-base mb-2">&#128268; Wire Sizing</h4>
            <dl class="text-xs space-y-1">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                    <dt class="text-gray-500">PV to controller</dt>
                    <dd class="font-bold text-green-900 text-right break-words ml-auto">${esc(wiring.pv_wire)}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                    <dt class="text-gray-500">Battery to inverter</dt>
                    <dd class="font-bold text-blue-900 text-right break-words ml-auto">${esc(wiring.battery_wire)}</dd>
                </div>
            </dl>
            ${wiring.explanation
                ? `<details><summary class="details-toggle">Why these sizes</summary>
                     <p class="text-xs text-gray-600 mt-1 leading-relaxed">${esc(wiring.explanation)}</p>
                   </details>`
                : ''}
        </div>`;
    }

    function displayRecommendation(rec) {
        const container = el('resultsArea');
        if (!container) {
            return;
        }

        const parts = [];

        const panelOptions = Array.isArray(rec.panel_recommendations)
            ? rec.panel_recommendations.filter((option) => option && typeof option === 'object')
            : [];

        const capacity = rec.total_capacity
            || (panelOptions[0] && panelOptions[0].total_capacity)
            || '';

        parts.push(`
        <div class="result-card span-all result-head">
            <div class="result-icon success">&#10003;</div>
            <div class="min-w-0 flex-1">
                <h3 class="result-title">Recommendation ready</h3>
                <p class="result-subtitle">${esc(MODE_INFO[selectedMode] ? MODE_INFO[selectedMode].title : 'Design')} &middot; ${esc(currentSystemTypeLabel())}</p>
            </div>
            ${capacity ? `<span class="text-xs font-bold text-blue-700 bg-blue-50 border border-blue-100 px-2.5 py-1 rounded-full whitespace-nowrap">${esc(capacity)}</span>` : ''}
        </div>`);

        if (rec.safe === false) {
            parts.push(`
            <div class="result-card span-all danger" role="alert">
                <p class="text-sm font-bold text-red-800">&#9888;&#65039; Configuration flagged as unsafe</p>
                <p class="text-xs text-red-700 mt-1">Review the warnings below before installing this array. The suggested arrangement may exceed a controller limit.</p>
            </div>`);
        }

        if (panelOptions.length > 0) {
            const cards = panelOptions.map((option, idx) => {
                const color = OPTION_COLORS[idx % OPTION_COLORS.length];
                const maxPanels = option.max_panels_display
                    ? String(option.max_panels_display)
                    : (option.max_panels !== undefined && option.max_panels !== null
                        ? option.max_panels + (option.panel_size ? ' \u00d7 ' + option.panel_size : '')
                        : '');
                const current = option.isc || option.imp;
                const specs = [
                    option.voc ? 'VOC ' + option.voc : '',
                    current ? 'ISC ' + current : ''
                ].filter(Boolean).join(' \u00b7 ');

                return `
                <div class="panel-option ${color.accent}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold">Option ${idx + 1}</p>
                            <p class="text-lg font-bold ${color.text} leading-tight">${esc(option.panel_size)}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-xs font-bold ${color.text} leading-snug">${esc(maxPanels.trim())}</p>
                            <p class="text-[11px] text-gray-500">${esc(option.total_capacity)}</p>
                        </div>
                    </div>
                    ${specs ? `<p class="text-[11px] text-gray-600 mt-1">${esc(specs)}</p>` : ''}
                    ${option.arrangement ? `<p class="text-[11px] text-gray-500 mt-0.5 sm:truncate" title="${esc(option.arrangement)}">${esc(option.arrangement)}</p>` : ''}
                </div>`;
            }).join('');

            parts.push(`
            <div class="result-card span-all">
                <div class="flex items-baseline justify-between gap-2 mb-2">
                    <h4 class="font-bold text-base">&#128161; Panel Options</h4>
                    <span class="text-[11px] text-gray-400">${panelOptions.length} options</span>
                </div>
                <div class="panel-options">${cards}</div>
            </div>`);
        }

        const showMaxCard = selectedMode === 1 && rec.max_panels && panelOptions.length === 0;

        if (showMaxCard) {
            parts.push(`
            <div class="result-card">
                <h4 class="font-bold text-base mb-2">&#128202; Maximum Configuration</h4>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] text-gray-500">Panels</p>
                        <p class="text-base font-bold text-blue-900 leading-tight">${esc(rec.max_panels)}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[11px] text-gray-500">Capacity</p>
                        <p class="text-base font-bold text-green-700 leading-tight">${esc(rec.total_capacity || 'N/A')}</p>
                    </div>
                </div>
            </div>`);
        }

        parts.push(renderArrangement(rec.arrangement, showMaxCard ? '' : 'span-all'));

        const pairClass = (rec.breaker && rec.wiring) ? '' : 'span-all';
        parts.push(renderBreaker(rec.breaker, pairClass));
        parts.push(renderWiring(rec.wiring, pairClass));

        if (rec.summary) {
            parts.push(`
            <div class="result-card span-all">
                <h4 class="font-bold text-base mb-2">&#128203; Summary</h4>
                <div class="result-summary">${sanitizeAiHtml(rec.summary)}</div>
            </div>`);
        }

        const warnings = Array.isArray(rec.warnings)
            ? rec.warnings
            : (typeof rec.warnings === 'string' && rec.warnings.trim() !== '' ? [rec.warnings] : []);
        const cleanWarnings = warnings.filter((warning) => String(warning || '').trim() !== '');

        if (cleanWarnings.length > 0) {
            parts.push(`
            <div class="result-card span-all danger" role="alert">
                <p class="text-sm font-bold text-red-800">&#9888;&#65039; Important Warnings</p>
                <ul class="list-disc list-inside text-xs text-red-700 mt-1 space-y-0.5">
                    ${cleanWarnings.map((warning) => '<li>' + esc(warning) + '</li>').join('')}
                </ul>
            </div>`);
        }

        parts.push(`
        <div class="result-card span-all" style="background: linear-gradient(135deg, #eff6ff 0%, #eef2ff 100%); border-left-color: #3b82f6;">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-blue-900">Export this recommendation</p>
                    <p class="text-[11px] text-blue-700 hidden sm:block">Share the PDF with your client or installer.</p>
                </div>
                <button type="button" onclick="downloadPDF(this)"
                    class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2 whitespace-nowrap flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download PDF
                </button>
            </div>
        </div>`);

        container.innerHTML = '<div class="results-grid">' + parts.filter(Boolean).join('') + '</div>';
        container.classList.remove('hidden');
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function downloadPDF(btn) {
        if (!lastRecommendation) {
            showError('Generate a recommendation before exporting a PDF.');
            return;
        }

        const originalText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = 'Preparing PDF...';
        }

        try {
            const response = await fetch('../api/ai/export-recommendation-pdf.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/pdf'
                },
                body: JSON.stringify({
                    recommendation: lastRecommendation,
                    form_data: lastFormData || { mode: selectedMode, system_type: currentSystemTypeLabel() }
                })
            });

            const contentType = response.headers.get('content-type') || '';
            if (!response.ok || contentType.indexOf('application/pdf') === -1) {
                const text = await response.text();
                const detail = text && text.length <= 200 ? text.trim() : '';
                throw new Error(
                    detail || ('PDF export failed (HTTP ' + response.status + '). Check the server error log.')
                );
            }

            const buffer = await response.arrayBuffer();
            const signature = String.fromCharCode.apply(
                null,
                new Uint8Array(buffer.slice(0, 5))
            );

            // A PHP fatal can still be served with the PDF content type.
            if (signature !== '%PDF-') {
                const text = new TextDecoder('utf-8').decode(new Uint8Array(buffer.slice(0, 300)));
                throw new Error(
                    text.trim() || 'The server did not return a valid PDF. Check the server error log.'
                );
            }

            const blob = new Blob([buffer], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            link.href = url;
            link.download = 'Solar_Recommendation_' + stamp + '.pdf';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 10000);
        } catch (error) {
            console.error('PDF export error:', error);
            showError(
                error && error.message
                    ? error.message
                    : 'Could not export the recommendation as a PDF.'
            );
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    }

    /* ------------------------------------------------------------------ *
     * Wiring
     * ------------------------------------------------------------------ */

    document.addEventListener('DOMContentLoaded', () => {
        const form = el('systemForm');
        if (form) {
            form.addEventListener('input', saveFormData);
            form.addEventListener('change', saveFormData);
        }

        initDatasheetPickers();

        document.querySelectorAll('.mode-card').forEach((card) => {
            card.setAttribute('aria-pressed', 'false');
            card.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
                    event.preventDefault();
                    selectMode(card.dataset.mode, card);
                }
            });
        });

        // Mark only the fields that are actually required for the current
        // mode; the markup defaults to "hybrid" requirements.
        if (!selectedMode) {
            document.querySelectorAll('#systemForm [required]').forEach((input) => {
                if (input.name !== 'system_type') {
                    input.removeAttribute('required');
                }
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>