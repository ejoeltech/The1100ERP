<?php
// HR Document Generator UI
// require_once '../../../config.php'; // Removed to prevent double inclusion
require_once '../../../includes/session-check.php';
require_once '../classes/HR_Employee.php';

requireLogin();

$pageTitle = 'Generate Documents | ' . COMPANY_NAME;
$currentPage = 'hr_documents';

$hr_employee = new HR_Employee($pdo);
$employees = $hr_employee->getAllEmployees(1000);

include_once '../../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">HR Document Generator</h1>
    <p class="text-gray-600">Create professional letters and documents using AI</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Config Panel -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Document Details</h3>

            <form id="docForm" onsubmit="generateDocument(event)">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
                        <select id="docType" name="type"
                            class="w-full rounded-lg border-gray-300 focus:ring-primary focus:border-primary">
                            <option value="employment_letter">Employment Offer Letter</option>
                            <option value="termination_letter">Termination Letter</option>
                            <option value="query">Query / Warning Letter</option>
                            <option value="promotion">Promotion Letter</option>
                            <option value="recommendation">Recommendation Letter</option>
                            <option value="other">Other (General)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                        <select id="docEmployee" name="employee_id" required
                            class="w-full rounded-lg border-gray-300 focus:ring-primary focus:border-primary">
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo htmlspecialchars($emp['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Context / Specific Details</label>
                        <textarea id="docContext" name="context" rows="4"
                            placeholder="E.g., Effective date, reason for query, new salary amount, specific performance highlights..."
                            class="w-full rounded-lg border-gray-300 focus:ring-primary focus:border-primary"></textarea>
                    </div>

                    <button type="submit" id="generateBtn"
                        class="w-full py-3 bg-purple-600 text-white rounded-lg font-bold hover:bg-purple-700 transition-colors flex justify-center items-center gap-2">
                        <span>✨</span>
                        <span id="btnText">Generate Document</span>
                    </button>

                    <div id="loadingIndicator" class="hidden text-center text-sm text-gray-500 mt-2">
                        Thinking... This may take a few seconds.
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview Panel -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 h-full flex flex-col">
            <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-100">
                <h3 class="font-bold text-gray-900">Document Preview</h3>
                <div class="flex gap-2">
                    <button onclick="copyContent()" class="text-sm text-gray-500 hover:text-primary">Copy Text</button>
                    <button onclick="printContent()"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-1 rounded-lg text-sm font-medium">Print
                        / PDF</button>
                </div>
            </div>

            <textarea id="documentEditor" class="flex-grow min-h-[500px]"></textarea>
        </div>
    </div>
</div>

<!-- TinyMCE -->
<script src="<?php echo $base_path; ?>/assets/vendors/tinymce/tinymce.min.js"></script>
<script>
    tinymce.init({
        selector: '#documentEditor',
        height: 600,
        menubar: false,
        plugins: ['advlist', 'autolink', 'lists', 'link', 'preview', 'wordcount', 'table'],
        toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter | bullist numlist | table | preview',
        content_style: 'body { font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px; line-height:1.6 }'
    });

    async function generateDocument(e) {
        e.preventDefault();

        const btn = document.getElementById('generateBtn');
        const btnText = document.getElementById('btnText');
        const loader = document.getElementById('loadingIndicator');

        const type = document.getElementById('docType').value;
        const employee_id = document.getElementById('docEmployee').value;
        const context = document.getElementById('docContext').value;

        if (!employee_id) {
            alert('Please select an employee.');
            return;
        }

        const originalText = btnText.innerText;
        btnText.innerText = 'Generating...';
        btn.disabled = true;
        loader.classList.remove('hidden');

        try {
            const response = await fetch('../api/generate-document.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, employee_id, context })
            });

            const data = await response.json();

            if (data.success) {
                tinymce.get('documentEditor').setContent(data.content);
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            alert('Connection Error: ' + error.message);
        } finally {
            btnText.innerText = originalText;
            btn.disabled = false;
            loader.classList.add('hidden');
        }
    }

    function copyContent() {
        const content = tinymce.get('documentEditor').getContent({ format: 'text' });
        navigator.clipboard.writeText(content).then(() => alert('Copied to clipboard'));
    }

    function printContent() {
        const content = tinymce.get('documentEditor').getContent();
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Print Document</title>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
</script>

<?php include_once '../../../includes/footer.php'; ?>