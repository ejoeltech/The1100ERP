<?php
include '../includes/public-init.php';
$pageTitle = 'Solar ROI Calculator - ERP System';
include '../includes/public-header.php';
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-gray-900">Solar ROI Calculator</h2>
    <p class="text-gray-600 mt-1">Calculate potential savings and payback period for solar investments.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Calculator Form -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="font-bold text-lg text-gray-900 mb-6 border-b pb-2">Analysis Inputs</h3>

            <form id="roiForm" onsubmit="calculateROI(event)">
                <div class="space-y-6">
                    <!-- Investment -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Total System Cost (₦)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">₦</span>
                            <input type="number" name="system_cost" required min="100000" step="1000"
                                class="w-full pl-8 border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Solar Offset (% of current spend)</label>
                        <input type="number" name="offset_pct" value="85" min="0" max="100" step="5"
                            class="w-full border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                        <p class="text-xs text-gray-500 mt-1">How much of the current generator/grid spend the solar system is expected to displace.</p>
                    </div>

                    <!-- Mode Toggle -->
                    <div class="flex items-center justify-between bg-gray-100 p-2 rounded-lg mb-4">
                        <span class="text-sm font-semibold text-gray-700 px-2">Calculation Mode</span>
                        <div class="flex bg-white rounded-md shadow-sm p-1">
                            <button type="button" onclick="setMode('simple')" id="btnSimple"
                                class="px-4 py-1.5 text-xs font-bold rounded text-white bg-primary transition-colors">Estimated</button>
                            <button type="button" onclick="setMode('advanced')" id="btnAdvanced"
                                class="px-4 py-1.5 text-xs font-bold rounded text-gray-500 hover:bg-gray-50 transition-colors">Manual
                                Entry</button>
                        </div>
                        <input type="hidden" name="mode" id="calcMode" value="simple">
                    </div>

                    <!-- Current Generator Usage (Simple Mode) -->
                    <div id="simpleInputs" class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <h4 class="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wide">Generator Estimation
                        </h4>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Generator Size
                                    (kVA)</label>
                                <select name="gen_kva" class="w-full border-gray-300 rounded-md text-sm">
                                    <option value="2.5">Small (2.5 - 3.5 kVA)</option>
                                    <option value="5">Medium (5 - 7.5 kVA)</option>
                                    <option value="10">Large (10 - 15 kVA)</option>
                                    <option value="20">Industrial (20+ kVA)</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Fuel Type</label>
                                    <select name="fuel_type" class="w-full border-gray-300 rounded-md text-sm">
                                        <option value="petrol">Petrol (PMS)</option>
                                        <option value="diesel">Diesel (AGO)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Hours Run /
                                        Day</label>
                                    <input type="number" name="hours_day" value="8" min="0" max="24"
                                        class="w-full border-gray-300 rounded-md text-sm">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Inputs (Manual Entry) -->
                    <div id="advancedInputs" class="hidden bg-purple-50 p-4 rounded-lg border border-purple-100">
                        <h4 class="text-sm font-bold text-purple-900 mb-3 uppercase tracking-wide">Manual Expenses</h4>

                        <div class="space-y-4">
                            <!-- Fuel Expense -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Fuel Spending</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="relative">
                                        <span class="absolute left-2 top-2 text-gray-500 text-xs">₦</span>
                                        <input type="number" name="fuel_cost" placeholder="Amount" step="100"
                                            class="w-full pl-6 border-gray-300 rounded-md text-sm focus:ring-purple-500 focus:border-purple-500">
                                    </div>
                                    <select name="fuel_freq" class="w-full border-gray-300 rounded-md text-sm">
                                        <option value="daily">Per Day</option>
                                        <option value="weekly">Per Week</option>
                                        <option value="monthly">Per Month</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Utility Bill -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Grid/NEPA Bill</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="relative">
                                        <span class="absolute left-2 top-2 text-gray-500 text-xs">₦</span>
                                        <input type="number" name="utility_cost" placeholder="Amount" step="100"
                                            class="w-full pl-6 border-gray-300 rounded-md text-sm focus:ring-purple-500 focus:border-purple-500">
                                    </div>
                                    <select name="utility_freq" class="w-full border-gray-300 rounded-md text-sm">
                                        <option value="monthly">Per Month</option>
                                        <option value="weekly">Per Week</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="calcBtn"
                        class="w-full bg-green-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-green-700 flex justify-center items-center gap-2">
                        <span id="btnIcon">🧮</span>
                        <span id="btnText">Calculate Savings</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Display -->
    <div class="lg:col-span-1">
        <div id="resultsArea" class="hidden h-full" aria-live="polite">
            <div class="bg-white rounded-lg shadow-md p-6 h-full flex flex-col">
                <h3 class="font-bold text-lg text-gray-900 mb-6 border-b pb-2">Financial Analysis</h3>

                <!-- Key Metrics Cards -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-100 text-center">
                        <p class="text-xs text-blue-600 font-bold uppercase">Monthly Savings</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1" id="monthlySavings">-</p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg border border-green-100 text-center">
                        <p class="text-xs text-green-600 font-bold uppercase">Payback Period</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1" id="paybackPeriod">-</p>
                        <p class="text-xs text-green-700 mt-1">Estimated</p>
                    </div>
                </div>

                <div class="bg-purple-50 p-4 rounded-lg border border-purple-100 mb-6">
                    <div class="flex justify-between items-end">
                        <div class="text-left">
                            <p class="text-xs text-purple-600 font-bold uppercase">5-Year Net Savings (after system cost)</p>
                            <p class="text-3xl font-extrabold text-gray-900 mt-1" id="fiveYearSavings">-</p>
                        </div>
                        <div class="text-4xl">💰</div>
                    </div>
                </div>

                <!-- Calculation Breakdown -->
                <div class="mb-6">
                    <h4 class="font-bold text-gray-800 text-sm mb-2">📊 Calculation Breakdown</h4>
                    <div id="breakdownText"
                        class="bg-gray-50 p-3 rounded border border-gray-200 text-xs font-mono text-gray-600 whitespace-pre-line">
                        Enter details to see breakdown...
                    </div>
                </div>

                <!-- AI Analysis Text -->
                <div class="flex-grow bg-gray-50 rounded-lg p-4 border border-gray-200 overflow-y-auto">
                    <h4 class="font-bold text-gray-800 text-sm mb-2 flex items-center gap-2">
                        Financial Analysis
                    </h4>
                    <div id="aiAnalysis" class="text-sm text-gray-700 prose prose-sm max-w-none whitespace-pre-line">
                        <!-- AI Text goes here -->
                    </div>
                </div>

                <button type="button" onclick="useInProposal()"
                    class="mt-4 w-full bg-blue-600 text-white font-bold py-2.5 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                    Use in Proposal
                </button>
            </div>
        </div>

        <!-- Placeholder State -->
        <div id="placeholderState"
            class="bg-gray-100 rounded-lg p-8 h-full flex items-center justify-center text-gray-400 border-2 border-dashed border-gray-300">
            <div class="text-center">
                <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                    </path>
                </svg>
                <p>Enter details to estimate ROI</p>
            </div>
        </div>
    </div>
</div>

<script nonce="<?php echo function_exists('cspNonce') ? cspNonce() : ''; ?>">
    function setMode(mode) {
        document.getElementById('calcMode').value = mode;

        if (mode === 'simple') {
            document.getElementById('simpleInputs').classList.remove('hidden');
            document.getElementById('advancedInputs').classList.add('hidden');

            document.getElementById('btnSimple').classList.add('bg-primary', 'text-white');
            document.getElementById('btnSimple').classList.remove('text-gray-500', 'hover:bg-gray-50');

            document.getElementById('btnAdvanced').classList.remove('bg-primary', 'bg-purple-600', 'text-white');
            document.getElementById('btnAdvanced').classList.add('text-gray-500', 'hover:bg-gray-50');
        } else {
            document.getElementById('simpleInputs').classList.add('hidden');
            document.getElementById('advancedInputs').classList.remove('hidden');

            document.getElementById('btnAdvanced').classList.add('bg-purple-600', 'text-white');
            document.getElementById('btnAdvanced').classList.remove('text-gray-500', 'hover:bg-gray-50');

            document.getElementById('btnSimple').classList.remove('bg-primary', 'text-white');
            document.getElementById('btnSimple').classList.add('text-gray-500', 'hover:bg-gray-50');
        }
    }

    function useInProposal() {
        const form = document.getElementById('roiForm');
        const data = Object.fromEntries(new FormData(form).entries());
        const summary = {
            system_cost: data.system_cost || '',
            offset_pct: data.offset_pct || '',
            monthly_savings: document.getElementById('monthlySavings').textContent,
            payback: document.getElementById('paybackPeriod').textContent,
            five_year: document.getElementById('fiveYearSavings').textContent
        };
        try {
            sessionStorage.setItem('roiHandoff', JSON.stringify(summary));
        } catch (error) {
            console.error('Could not store ROI handoff:', error);
        }
        window.location.href = 'create-proposal.php?from=roi';
    }

    async function calculateROI(e) {
        e.preventDefault();

        const btn = document.getElementById('calcBtn');
        const btnText = document.getElementById('btnText');
        const form = document.getElementById('roiForm');

        const originalText = btnText.innerText;
        btnText.innerText = 'Analyzing...';
        btn.disabled = true;
        btn.classList.add('opacity-75', 'cursor-not-allowed');

        // Prepare data
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch('../api/ai/calculate-roi.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (!response.ok) throw new Error(result.error || ('HTTP ' + response.status));

            if (result.success && result.data) {
                // Show results
                document.getElementById('placeholderState').classList.add('hidden');
                document.getElementById('resultsArea').classList.remove('hidden');

                // Update metrics
                document.getElementById('monthlySavings').textContent = result.data.monthly_savings_fmt || '—';
                document.getElementById('paybackPeriod').textContent = result.data.payback_label || '—';
                document.getElementById('fiveYearSavings').textContent = result.data.five_year_savings_fmt || '—';

                // Update Breakdown
                document.getElementById('breakdownText').textContent = result.data.breakdown_text || 'No details available.';

                // Update AI Text (plain text)
                const aiAnalysisEl = document.getElementById('aiAnalysis');
                aiAnalysisEl.textContent = result.ai_analysis || result.ai_warning || '';
            } else {
                throw new Error(result.error || 'The calculator returned an unexpected response.');
            }
        } catch (error) {
            alert('Error: ' + error.message);
        } finally {
            btnText.innerText = originalText;
            btn.disabled = false;
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
        }
    }
</script>

<?php include '../includes/footer.php'; ?>