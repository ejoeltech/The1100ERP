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
        { bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-900' },
        { bg: 'bg-green-50', border: 'border-green-200', text: 'text-green-900' },
        { bg: 'bg-purple-50', border: 'border-purple-200', text: 'text-purple-900' },
        { bg: 'bg-orange-50', border: 'border-orange-200', text: 'text-orange-900' }
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

    function renderArrangement(arrangement) {
        if (!arrangement) {
            return '';
        }

        if (typeof arrangement === 'string') {
            return `
            <div class="result-card">
                <h4 class="font-bold text-lg mb-3">&#128295; Panel Arrangement</h4>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-700">${esc(arrangement)}</p>
                </div>
            </div>`;
        }

        const facts = [
            ['Strings', arrangement.strings],
            ['Panels per string', arrangement.panels_per_string],
            ['Total VOC', arrangement.total_voc],
            ['Total current', arrangement.total_current]
        ].filter((fact) => fact[1] !== undefined && fact[1] !== null && fact[1] !== '');

        const factsHtml = facts.length
            ? '<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">' + facts.map((fact) => `
                    <div class="bg-white bg-opacity-70 rounded-lg p-3">
                        <p class="text-xs text-purple-700 font-semibold">${esc(fact[0])}</p>
                        <p class="text-lg font-bold text-purple-900">${esc(fact[1])}</p>
                    </div>`).join('') + '</div>'
            : '';

        return `
        <div class="result-card">
            <h4 class="font-bold text-lg mb-3">&#128295; Panel Arrangement</h4>
            <div class="bg-purple-50 p-4 rounded-lg">
                <p class="font-semibold text-purple-900">${esc(arrangement.configuration)}</p>
                ${arrangement.explanation ? '<p class="text-sm text-gray-600 mt-2">' + esc(arrangement.explanation) + '</p>' : ''}
                ${factsHtml}
            </div>
        </div>`;
    }

    function renderBreaker(breaker) {
        if (!breaker) {
            return '';
        }
        const isString = typeof breaker === 'string';
        return `
        <div class="result-card">
            <h4 class="font-bold text-lg mb-3">&#9889; DC Circuit Breaker</h4>
            <div class="bg-yellow-50 p-4 rounded-lg">
                <p class="font-semibold text-yellow-900">Recommended: ${esc(isString ? breaker : breaker.rating)}</p>
                ${!isString && breaker.explanation ? '<p class="text-sm text-gray-600 mt-2">' + esc(breaker.explanation) + '</p>' : ''}
            </div>
        </div>`;
    }

    function renderWiring(wiring) {
        if (!wiring) {
            return '';
        }
        if (typeof wiring === 'string') {
            return `
            <div class="result-card">
                <h4 class="font-bold text-lg mb-3">&#128268; Wire Sizing</h4>
                <p class="text-sm text-gray-700">${esc(wiring)}</p>
            </div>`;
        }
        return `
        <div class="result-card">
            <h4 class="font-bold text-lg mb-3">&#128268; Wire Sizing</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-green-50 p-4 rounded-lg">
                    <p class="text-sm text-green-600 font-semibold">PV to Controller</p>
                    <p class="text-xl font-bold text-green-900">${esc(wiring.pv_wire)}</p>
                </div>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-sm text-blue-600 font-semibold">Battery to Inverter</p>
                    <p class="text-xl font-bold text-blue-900">${esc(wiring.battery_wire)}</p>
                </div>
            </div>
            ${wiring.explanation ? '<p class="text-sm text-gray-600 mt-3">' + esc(wiring.explanation) + '</p>' : ''}
        </div>`;
    }

    function displayRecommendation(rec) {
        const container = el('resultsArea');
        if (!container) {
            return;
        }

        const parts = [];

        parts.push(`
        <div class="result-card">
            <div class="result-header">
                <div class="result-icon success">&#10003;</div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Recommendation Generated</h3>
                    <p class="text-gray-600">${esc(MODE_INFO[selectedMode] ? MODE_INFO[selectedMode].title : 'Design')} &middot; ${esc(currentSystemTypeLabel())}</p>
                </div>
            </div>
        </div>`);

        if (rec.safe === false) {
            parts.push(`
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg" role="alert">
                <h4 class="font-bold text-red-800 mb-1">&#9888;&#65039; Configuration flagged as unsafe</h4>
                <p class="text-sm text-red-700">Review the warnings below before installing this array. The suggested arrangement may exceed a controller limit.</p>
            </div>`);
        }

        const panelOptions = Array.isArray(rec.panel_recommendations)
            ? rec.panel_recommendations.filter((option) => option && typeof option === 'object')
            : [];

        if (panelOptions.length > 0) {
            const gridCols = Math.min(panelOptions.length, 4);
            const cards = panelOptions.map((option, idx) => {
                const color = OPTION_COLORS[idx % OPTION_COLORS.length];
                const maxPanels = option.max_panels_display
                    ? String(option.max_panels_display)
                    : (option.max_panels !== undefined && option.max_panels !== null
                        ? option.max_panels + ' ' + (option.panel_size || '')
                        : '');
                return `
                <div class="${color.bg} p-4 rounded-lg border-2 ${color.border}">
                    <p class="text-sm font-semibold ${color.text}">Option ${idx + 1}</p>
                    <p class="text-2xl font-bold ${color.text} mb-2">${esc(option.panel_size)}</p>
                    <div class="text-xs text-gray-600 space-y-1 mb-3">
                        <p><strong>VOC:</strong> ${esc(option.voc)}</p>
                        <p><strong>ISC:</strong> ${esc(option.isc || option.imp)}</p>
                    </div>
                    <div class="bg-white bg-opacity-60 p-2 rounded">
                        <p class="text-sm font-bold ${color.text}">${esc(maxPanels.trim())}</p>
                        <p class="text-xs text-gray-600 mt-1"><strong>Total:</strong> ${esc(option.total_capacity)}</p>
                        <p class="text-xs text-gray-600 mt-2">${esc(option.arrangement)}</p>
                    </div>
                </div>`;
            }).join('');

            parts.push(`
            <div class="result-card">
                <h4 class="font-bold text-lg mb-3">&#128161; Panel Options</h4>
                <p class="text-sm text-gray-600 mb-4">Based on your controller specifications, here are recommended panel options:</p>
                <div class="grid grid-cols-1 md:grid-cols-${gridCols} gap-4">${cards}</div>
            </div>`);
        }

        if (selectedMode === 1 && rec.max_panels && panelOptions.length === 0) {
            parts.push(`
            <div class="result-card">
                <h4 class="font-bold text-lg mb-3">&#128202; Maximum Configuration</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-blue-600 font-semibold">Maximum Panels</p>
                        <p class="text-2xl font-bold text-blue-900">${esc(rec.max_panels)}</p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-green-600 font-semibold">Total Capacity</p>
                        <p class="text-2xl font-bold text-green-900">${esc(rec.total_capacity || 'N/A')}</p>
                    </div>
                </div>
            </div>`);
        }

        parts.push(renderArrangement(rec.arrangement));
        parts.push(renderBreaker(rec.breaker));
        parts.push(renderWiring(rec.wiring));

        if (rec.summary) {
            parts.push(`
            <div class="result-card">
                <h4 class="font-bold text-lg mb-3">&#128203; Summary</h4>
                <div class="prose prose-sm max-w-none">${sanitizeAiHtml(rec.summary)}</div>
            </div>`);
        }

        const warnings = Array.isArray(rec.warnings)
            ? rec.warnings
            : (typeof rec.warnings === 'string' && rec.warnings.trim() !== '' ? [rec.warnings] : []);
        const cleanWarnings = warnings.filter((warning) => String(warning || '').trim() !== '');

        if (cleanWarnings.length > 0) {
            parts.push(`
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg" role="alert">
                <h4 class="font-bold text-red-800 mb-2">&#9888;&#65039; Important Warnings</h4>
                <ul class="list-disc list-inside text-red-700 space-y-1">
                    ${cleanWarnings.map((warning) => '<li>' + esc(warning) + '</li>').join('')}
                </ul>
            </div>`);
        }

        parts.push(`
        <div class="result-card bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-300">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h4 class="font-bold text-lg text-blue-900 mb-2">&#128196; Export Recommendation</h4>
                    <p class="text-sm text-blue-700">Download a professional PDF report to share with your client or installer</p>
                </div>
                <button type="button" onclick="downloadPDF(this)"
                    class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2 whitespace-nowrap">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download PDF
                </button>
            </div>
        </div>`);

        container.innerHTML = parts.filter(Boolean).join('');
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