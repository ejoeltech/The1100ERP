<?php
include '../includes/session-check.php';
$pageTitle = 'Create Solar Proposal - ERP System';
include '../includes/header.php';
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-gray-900">Create AI Solar Proposal</h2>
    <p class="text-gray-600 mt-1">Generate professional project proposals using AI based on system specifications.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Input Form -->
    <div class="lg:col-span-1 space-y-6">
        <!-- AI Recommendation Section -->
        <div class="bg-indigo-50 border border-indigo-100 rounded-lg shadow-sm p-6">
            <h3 class="font-bold text-lg text-indigo-900 mb-2 flex items-center gap-2">
                <span>🤖</span> Smart Recommender
            </h3>
            <p class="text-xs text-indigo-600 mb-4">Describe the client's needs to auto-fill specs.</p>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">House Type</label>
                    <select id="recHouseType"
                        class="w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="3-Bedroom Flat">3-Bedroom Flat</option>
                        <option value="2-Bedroom Flat">2-Bedroom Flat</option>
                        <option value="4-Bedroom Duplex">4-Bedroom Duplex</option>
                        <option value="5-Bedroom Duplex">5-Bedroom Duplex</option>
                        <option value="Small Office">Small Office</option>
                        <option value="Shop/plaza">Shop / Plaza</option>
                        <option value="Clinic/Hospital">Clinic / Hospital</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Appliances to Power</label>
                    <textarea id="recAppliances" rows="3"
                        placeholder="e.g. 1x Fridge, 3x Fans, 10x Lights, 1x TV, 1x 1HP AC (daytime only)"
                        class="w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Grid (Hrs/Day)</label>
                        <input type="number" id="recPowerHours" placeholder="e.g. 4"
                            class="w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Battery Type</label>
                        <select id="recBatteryType"
                            class="w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="Any">Any (AI Decide)</option>
                            <option value="Tubular">Tubular (Lead Acid)</option>
                            <option value="Lithium">Lithium (LiFePO4)</option>
                        </select>
                    </div>
                </div>
                <button type="button" onclick="getRecommendation()" id="recBtn"
                    class="w-full bg-indigo-600 text-white text-sm font-bold py-2 rounded-md hover:bg-indigo-700 transition-colors flex justify-center items-center gap-2">
                    <span>⚡</span> <span id="recBtnText">Analyze & Auto-Fill</span>
                </button>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="font-bold text-lg text-gray-900 mb-4 border-b pb-2">System Specifications</h3>

            <form id="proposalForm" onsubmit="generateProposal(event)">
                <input type="hidden" id="proposalId" name="proposalId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Project Type</label>
                        <select name="project_type"
                            class="w-full border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                            <option value="Residential Home">Residential Home</option>
                            <option value="Commercial Office">Small Office / Commercial</option>
                            <option value="Industrial Facility">Industrial (Factory/Large)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Inverter System</label>
                        <input type="text" name="inverter" placeholder="e.g. 5kVA Hybrid Inverter (48V)" required
                            class="w-full border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Battery Storage</label>
                        <input type="text" name="batteries" placeholder="e.g. 4x 200Ah Gel Batteries" required
                            class="w-full border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Solar Array</label>
                        <input type="text" name="panels" placeholder="e.g. 8x 450W Monocrystalline Panels"
                            class="w-full border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Installation Context</label>
                        <textarea name="context" rows="3"
                            placeholder="e.g. 3-bedroom bungalow, reliable grid, roof mounting."
                            class="w-full border-gray-300 rounded-md focus:ring-primary focus:border-primary"></textarea>
                    </div>

                    <div class="pt-4">
                        <button type="submit" id="generateBtn"
                            class="w-full bg-purple-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-purple-700 flex justify-center items-center gap-2">
                            <span id="btnIcon">✨</span>
                            <span id="btnText">Generate Proposal</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Output Preview (TinyMCE) -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow-md p-6 h-full flex flex-col">
            <div class="flex justify-between items-center mb-4 border-b pb-2">
                <h3 class="font-bold text-lg text-gray-900">Proposal Preview</h3>
                <div class="flex gap-2">
                    <button onclick="saveDraft()" id="saveDraftBtn"
                        class="px-3 py-1 text-sm bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-md border border-gray-300">
                        Save Draft
                    </button>
                    <button onclick="convertToQuote()" id="convertBtn"
                        class="hidden px-3 py-1 text-sm bg-blue-600 text-white hover:bg-blue-700 rounded-md">
                        Convert to Quote
                    </button>
                    <button onclick="copyToClipboard()" class="text-sm text-gray-600 hover:text-primary pl-2">
                        Copy Text
                    </button>
                </div>
            </div>

            <textarea id="proposalEditor" class="flex-grow h-screen min-h-[500px]">
                <p class="text-gray-400 italic">Generated proposal will appear here...</p>
            </textarea>
        </div>
    </div>
</div>

<!-- TinyMCE -->
<!-- TinyMCE -->
<script src="../assets/vendors/tinymce/tinymce.min.js"></script>
<script>
    tinymce.init({
        selector: '#proposalEditor',
        height: 600,
        menubar: false,
        plugins: ['advlist', 'autolink', 'lists', 'link', 'preview', 'wordcount', 'table'],
        toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter | bullist numlist | table | preview',
        content_style: 'body { font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px; line-height:1.6 }'
    });

    async function getRecommendation() {
        const houseType = document.getElementById('recHouseType').value;
        const appliances = document.getElementById('recAppliances').value;
        const powerHours = document.getElementById('recPowerHours').value;
        const batteryType = document.getElementById('recBatteryType').value;

        if (!appliances) {
            alert('Please list the appliances to power.');
            return;
        }

        const btn = document.getElementById('recBtn');
        const btnText = document.getElementById('recBtnText');
        const originalText = btnText.innerText;

        btnText.innerText = 'Analyzing...';
        btn.disabled = true;
        btn.classList.add('opacity-75');

        try {
            const response = await fetch('../api/ai/recommend-specs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    house_type: houseType,
                    appliances: appliances,
                    power_hours: powerHours,
                    battery_preference: batteryType
                })
            });

            const data = await response.json();

            if (data.success) {
                const rec = data.recommendation;

                // Auto-fill form fields
                document.getElementsByName('inverter')[0].value = rec.inverter;
                document.getElementsByName('batteries')[0].value = rec.batteries;
                document.getElementsByName('panels')[0].value = rec.panels;
                document.getElementsByName('context')[0].value =
                    `House Type: ${houseType}\nGrid: ${powerHours}hrs/day\nAnalysis: ${rec.context_summary}\nAppliances: ${appliances}`;

                // Highlight success
                btn.classList.remove('bg-indigo-600');
                btn.classList.add('bg-green-600');
                btnText.innerText = 'Specs Updated!';

                setTimeout(() => {
                    btn.classList.remove('bg-green-600');
                    btn.classList.add('bg-indigo-600');
                    btnText.innerText = originalText;
                    btn.disabled = false;
                    btn.classList.remove('opacity-75');
                }, 2000);

            } else {
                alert('AI Error: ' + (data.error || 'Unknown error'));
                btnText.innerText = originalText;
                btn.disabled = false;
                btn.classList.remove('opacity-75');
            }
        } catch (error) {
            alert('Connection Error: ' + error.message);
            btnText.innerText = originalText;
            btn.disabled = false;
            btn.classList.remove('opacity-75');
        }
    }

    async function generateProposal(e) {
        e.preventDefault();

        const btn = document.getElementById('generateBtn');
        const btnText = document.getElementById('btnText');
        const form = document.getElementById('proposalForm');

        const originalText = btnText.innerText;
        btnText.innerText = 'Generating with AI...';
        btn.disabled = true;
        btn.classList.add('opacity-75', 'cursor-not-allowed');

        const formData = {
            project_type: form.project_type.value,
            inverter: form.inverter.value,
            batteries: form.batteries.value,
            panels: form.panels.value,
            context: form.context.value
        };

        try {
            const response = await fetch('../api/ai/generate-proposal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (data.success) {
                tinymce.get('proposalEditor').setContent(data.proposal_html);
            } else {
                alert('AI Error: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            alert('Connection Error: ' + error.message);
        } finally {
            btnText.innerText = originalText;
            btn.disabled = false;
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
        }
    }

    function copyToClipboard() {
        const content = tinymce.get('proposalEditor').getContent({ format: 'text' });
        navigator.clipboard.writeText(content).then(() => alert('Copied to clipboard!'));
    }

    async function saveDraft() {
        const content = tinymce.get('proposalEditor').getContent();
        if (!content || content.length < 20) {
            alert('Please generate or write some content first.');
            return;
        }

        const form = document.getElementById('proposalForm');
        const specs = {
            project_type: form.project_type.value,
            inverter: form.inverter.value,
            batteries: form.batteries.value,
            panels: form.panels.value,
            context: form.context.value
        };

        const proposalId = document.getElementById('proposalId').value;
        const title = `${specs.inverter} System - ${specs.project_type}`;

        const btn = document.getElementById('saveDraftBtn');
        const originalText = btn.innerText;
        btn.innerText = 'Saving...';
        btn.disabled = true;

        try {
            const response = await fetch('../api/proposals/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: proposalId,
                    title: title,
                    content: content,
                    specs: specs
                })
            });

            const result = await response.json();

            if (result.success) {
                document.getElementById('proposalId').value = result.id;
                document.getElementById('convertBtn').classList.remove('hidden');

                // Show temporary success feedback
                btn.innerText = 'Saved!';
                setTimeout(() => {
                    btn.innerText = originalText;
                    btn.disabled = false;
                }, 2000);
            } else {
                alert('Save Failed: ' + result.error);
                btn.innerText = originalText;
                btn.disabled = false;
            }
        } catch (error) {
            alert('Connection Error: ' + error.message);
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }

    async function convertToQuote() {
        const proposalId = document.getElementById('proposalId').value;
        if (!proposalId) {
            alert('Please save the draft first.');
            return;
        }

        if (!confirm('Create a new Quote from this proposal?')) return;

        const btn = document.getElementById('convertBtn');
        btn.innerText = 'Converting...';
        btn.disabled = true;

        try {
            const response = await fetch('../api/proposals/convert-to-quote.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: proposalId })
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = `edit-quote.php?id=${result.quote_id}`;
            } else {
                alert('Conversion Failed: ' + result.error);
                btn.innerText = 'Convert to Quote';
                btn.disabled = false;
            }
        } catch (error) {
            alert('Connection Error: ' + error.message);
            btn.innerText = 'Convert to Quote';
            btn.disabled = false;
        }
    }
</script>

<?php include '../includes/footer.php'; ?>