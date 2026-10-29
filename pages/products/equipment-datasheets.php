<?php
include '../../includes/session-check.php';
requirePermission('manage_products');
require_once '../../includes/equipment-datasheets.php';

$categories = equipmentDatasheetCategories();

/* -------------------------------------------------------------------------
 * CSV template download
 * ---------------------------------------------------------------------- */
if (isset($_GET['template'])) {
    $columns = array_merge(
        ['category', 'brand', 'model', 'title', 'status'],
        equipmentDatasheetColumns(),
        ['notes', 'source_url']
    );
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="equipment-datasheets-template.csv"');
    echo "\xEF\xBB\xBF" . implode(',', $columns) . "\r\n";
    exit;
}

$pageTitle = 'Equipment Datasheets - ERP System';

$search = trim((string)($_GET['search'] ?? ''));
$categoryFilter = strtolower(trim((string)($_GET['category'] ?? '')));
$statusFilter = strtolower(trim((string)($_GET['status'] ?? '')));

$where = ['1=1'];
$params = [];
if ($search !== '') {
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
    $where[] = "(brand LIKE ? OR model LIKE ? OR title LIKE ? OR CONCAT_WS(' ', brand, model, title) LIKE ?)";
    array_push($params, $like, $like, $like, $like);
}
if ($categoryFilter !== '' && isset($categories[$categoryFilter])) {
    $where[] = 'category = ?';
    $params[] = $categoryFilter;
}
if ($statusFilter !== '' && isset(equipmentDatasheetStatuses()[$statusFilter])) {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare('SELECT * FROM equipment_datasheets WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC, brand ASC LIMIT 500');
$stmt->execute($params);
$rows = $stmt->fetchAll();

$stats = $pdo->query("SELECT
    COUNT(*) AS total,
    SUM(status = 'published') AS published,
    SUM(status = 'draft') AS drafts,
    SUM(status = 'archived') AS archived
    FROM equipment_datasheets")->fetch();

$specFieldsForJs = [];
foreach ($categories as $key => $category) {
    $fields = [];
    foreach ($category['fields'] as $field => $meta) {
        $fields[] = ['key' => $field, 'label' => $meta[0], 'unit' => $meta[1], 'type' => $meta[2]];
    }
    $specFieldsForJs[$key] = ['label' => $category['label'], 'fields' => $fields];
}

function datasheetKeySpecs($row, $categories)
{
    $category = $categories[$row['category']] ?? null;
    if (!$category) {
        return '';
    }
    $parts = [];
    foreach ($category['fields'] as $field => $meta) {
        if ($row[$field] === null || $row[$field] === '') {
            continue;
        }
        $value = $row[$field];
        if ($meta[2] === 'number') {
            $value = rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
        }
        $parts[] = $value . ($meta[1] !== '' ? ' ' . $meta[1] : '');
        if (count($parts) >= 4) {
            break;
        }
    }
    return implode(' &middot; ', array_map('htmlspecialchars', $parts));
}

include '../../includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <div>
        <h2 class="text-3xl font-bold text-gray-900">Equipment Datasheets</h2>
        <p class="text-sm text-gray-600 mt-1">Searchable inverter, controller, battery and panel specs used by the System Designer.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" onclick="openImport()"
            class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">
            Import CSV
        </button>
        <a href="?template=1"
            class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">
            CSV Template
        </a>
        <button type="button" onclick="openCreate()"
            class="px-5 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
            + Add Datasheet
        </button>
    </div>
</div>

<div id="pageNotice" class="hidden mb-5 rounded-lg border px-4 py-3 text-sm font-semibold" role="status" aria-live="polite"></div>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-4 rounded-lg shadow">
        <p class="text-sm text-gray-600">Total</p>
        <p class="text-2xl font-bold text-gray-900"><?php echo (int)$stats['total']; ?></p>
    </div>
    <div class="bg-white p-4 rounded-lg shadow">
        <p class="text-sm text-gray-600">Published</p>
        <p class="text-2xl font-bold text-green-600"><?php echo (int)$stats['published']; ?></p>
    </div>
    <div class="bg-white p-4 rounded-lg shadow">
        <p class="text-sm text-gray-600">Drafts</p>
        <p class="text-2xl font-bold text-amber-600"><?php echo (int)$stats['drafts']; ?></p>
    </div>
    <div class="bg-white p-4 rounded-lg shadow">
        <p class="text-sm text-gray-600">Archived</p>
        <p class="text-2xl font-bold text-gray-500"><?php echo (int)$stats['archived']; ?></p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-md p-5 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="search" name="search" placeholder="Search brand, model or title..."
            value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
            class="px-4 py-2 border border-gray-300 rounded-lg md:col-span-2">
        <select name="category" class="px-4 py-2 border border-gray-300 rounded-lg">
            <option value="">All equipment</option>
            <?php foreach ($categories as $key => $category): ?>
                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $categoryFilter === $key ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="flex gap-2">
            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg flex-1">
                <option value="">All statuses</option>
                <?php foreach (equipmentDatasheetStatuses() as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $statusFilter === $key ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(explode(' (', $label)[0]); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-5 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 font-semibold">Filter</button>
        </div>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden table-responsive">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Equipment</th>
                <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Key specs</th>
                <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Status</th>
                <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Updated</th>
                <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="5" class="px-4 py-12 text-center text-gray-500">
                        <p class="font-semibold text-gray-700 mb-1">No datasheets yet</p>
                        <p class="text-sm">Add one manually, use the AI lookup, or import a CSV template.</p>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php
                $category = $categories[$row['category']] ?? null;
                $status = $row['status'];
                $statusClass = $status === 'published'
                    ? 'bg-green-100 text-green-800'
                    : ($status === 'draft' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600');
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-start gap-2">
                            <span class="text-lg leading-none mt-0.5"><?php echo $category ? $category['icon'] : ''; ?></span>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900"><?php echo htmlspecialchars(equipmentDatasheetDisplayName($row)); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($category ? $category['label'] : ucfirst($row['category'])); ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?php echo datasheetKeySpecs($row, $categories) ?: '<span class="text-gray-400">No specs</span>'; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?php echo $statusClass; ?>">
                            <?php echo htmlspecialchars(ucfirst($status)); ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">
                        <?php echo htmlspecialchars(date('j M Y', strtotime($row['updated_at']))); ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-1">
                            <?php if (!empty($row['source_url'])): ?>
                                <a href="<?php echo htmlspecialchars($row['source_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"
                                    class="p-2 text-gray-500 hover:text-blue-700" title="Open source datasheet">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                </a>
                            <?php endif; ?>
                            <button type="button" onclick="openEdit(<?php echo (int)$row['id']; ?>)"
                                class="px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100">Edit</button>
                            <button type="button" onclick="deleteDatasheet(<?php echo (int)$row['id']; ?>)"
                                class="px-3 py-1.5 text-xs font-semibold text-red-700 bg-red-50 rounded-lg hover:bg-red-100">Delete</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Create / edit modal -->
<div id="dsModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="dsModalTitle">
    <div class="absolute inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal()"></div>
    <div class="relative mx-auto my-6 w-[min(760px,calc(100%-1.5rem))] max-h-[92vh] overflow-y-auto bg-white rounded-xl shadow-2xl">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-5 py-4 flex items-center justify-between rounded-t-xl z-10">
            <div>
                <h3 id="dsModalTitle" class="text-lg font-bold text-gray-900">Add datasheet</h3>
                <p class="text-xs text-gray-500">Values saved here power the public System Designer autocomplete.</p>
            </div>
            <button type="button" onclick="closeModal()" class="p-2 text-gray-500 hover:text-gray-900" aria-label="Close">&times;</button>
        </div>

        <form id="dsForm" class="p-5 space-y-5" onsubmit="saveDatasheet(event)">
            <input type="hidden" id="dsId" value="0">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Equipment type *</label>
                    <select id="dsCategory" class="w-full px-3 py-2 border border-gray-300 rounded-lg" onchange="renderSpecFields(true)">
                        <?php foreach ($categories as $key => $category): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($category['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                    <select id="dsStatus" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <?php foreach (equipmentDatasheetStatuses() as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars(explode(' (', $label)[0]); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Only published records appear in the System Designer.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Brand *</label>
                    <input type="text" id="dsBrand" maxlength="120" placeholder="e.g. Victron Energy"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Model *</label>
                    <input type="text" id="dsModel" maxlength="160" placeholder="e.g. MultiPlus-II 48/5000/70-50"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Title / variant <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" id="dsTitle" maxlength="255" placeholder="e.g. 5 kVA hybrid inverter with 70 A charger"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
            </div>

            <!-- AI lookup -->
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-blue-900">Populate from an online datasheet</p>
                        <p class="text-xs text-blue-700">Searches the web, reads manufacturer pages, and fills a draft you can check.</p>
                    </div>
                    <button type="button" id="dsLookupBtn" onclick="lookupDatasheet()"
                        class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 whitespace-nowrap">
                        Look up online
                    </button>
                </div>
                <div id="dsLookupNote" class="hidden mt-3 text-xs rounded-md bg-white bg-opacity-70 border border-blue-100 px-3 py-2 text-blue-900"></div>
            </div>

            <!-- Spec fields -->
            <div>
                <div class="flex items-baseline justify-between gap-2 mb-2">
                    <h4 class="text-sm font-bold text-gray-900">Technical specifications</h4>
                    <span class="text-xs text-gray-500">Blank fields are ignored</span>
                </div>
                <div id="dsSpecFields" class="grid grid-cols-1 sm:grid-cols-2 gap-4"></div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
                <textarea id="dsNotes" rows="2" maxlength="4000" placeholder="Verification notes, datasheet revision, source caveats..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg"></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Source URL <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="url" id="dsSourceUrl" maxlength="500" placeholder="https://manufacturer.com/datasheet.pdf"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>

            <div id="dsFormError" class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm font-semibold text-red-800"></div>

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeModal()"
                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50">Cancel</button>
                <button type="submit" id="dsSaveBtn"
                    class="px-5 py-2 bg-primary text-white rounded-lg font-semibold hover:bg-blue-700">Save datasheet</button>
            </div>
        </form>
    </div>
</div>

<!-- Import modal -->
<div id="importModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="importTitle">
    <div class="absolute inset-0 bg-gray-900 bg-opacity-50" onclick="closeImport()"></div>
    <div class="relative mx-auto my-10 w-[min(520px,calc(100%-1.5rem))] bg-white rounded-xl shadow-2xl">
        <div class="border-b border-gray-200 px-5 py-4 flex items-center justify-between">
            <h3 id="importTitle" class="text-lg font-bold text-gray-900">Import datasheets</h3>
            <button type="button" onclick="closeImport()" class="p-2 text-gray-500 hover:text-gray-900" aria-label="Close">&times;</button>
        </div>
        <form id="importForm" class="p-5 space-y-4" onsubmit="importDatasheets(event)">
            <p class="text-sm text-gray-600">
                Upload a CSV using the template columns. Rows with the same type, brand and model update the existing record;
                new rows are added. Invalid rows are skipped and reported.
            </p>
            <input type="file" name="file" accept=".csv,text/csv" required
                class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
            <div id="importResult" class="hidden text-sm rounded-lg border px-3 py-2"></div>
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <button type="button" onclick="closeImport()"
                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50">Cancel</button>
                <button type="submit" id="importBtn"
                    class="px-5 py-2 bg-primary text-white rounded-lg font-semibold hover:bg-blue-700">Import CSV</button>
            </div>
        </form>
    </div>
</div>

<script type="application/json" id="datasheetData"><?php
    echo json_encode($rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>
<script type="application/json" id="datasheetFields"><?php
    echo json_encode($specFieldsForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>

<script>
    const DATASHEETS = JSON.parse(document.getElementById('datasheetData').textContent);
    const DATASHEET_FIELDS = JSON.parse(document.getElementById('datasheetFields').textContent);
    const REQUIRED_SPECS = {
        inverter: ['capacity_w'],
        controller: ['controller_capacity_w', 'max_voltage'],
        battery: ['battery_voltage'],
        panel: ['power_w', 'voc', 'isc']
    };

    function byId(id) {
        return document.getElementById(id);
    }

    function notice(message, kind) {
        const box = byId('pageNotice');
        if (!box) return;
        box.textContent = message;
        box.className = 'mb-5 rounded-lg border px-4 py-3 text-sm font-semibold ' + (
            kind === 'error'
                ? 'bg-red-50 border-red-200 text-red-800'
                : 'bg-green-50 border-green-200 text-green-800'
        );
        box.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function fieldError(message) {
        const box = byId('dsFormError');
        box.textContent = message;
        box.classList.remove('hidden');
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function clearFieldError() {
        const box = byId('dsFormError');
        box.classList.add('hidden');
        box.textContent = '';
    }

    function openCreate() {
        byId('dsModalTitle').textContent = 'Add datasheet';
        byId('dsId').value = '0';
        byId('dsCategory').value = 'inverter';
        byId('dsStatus').value = 'published';
        byId('dsBrand').value = '';
        byId('dsModel').value = '';
        byId('dsTitle').value = '';
        byId('dsNotes').value = '';
        byId('dsSourceUrl').value = '';
        byId('dsLookupNote').classList.add('hidden');
        clearFieldError();
        renderSpecFields(true);
        showModal();
    }

    function openEdit(id) {
        const row = DATASHEETS.find((item) => Number(item.id) === Number(id));
        if (!row) return;

        byId('dsModalTitle').textContent = 'Edit datasheet';
        byId('dsId').value = row.id;
        byId('dsCategory').value = row.category;
        byId('dsStatus').value = row.status;
        byId('dsBrand').value = row.brand || '';
        byId('dsModel').value = row.model || '';
        byId('dsTitle').value = row.title || '';
        byId('dsNotes').value = row.notes || '';
        byId('dsSourceUrl').value = row.source_url || '';
        byId('dsLookupNote').classList.add('hidden');
        clearFieldError();
        renderSpecFields(false, row);
        showModal();
    }

    function showModal() {
        byId('dsModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => byId('dsBrand').focus(), 50);
    }

    function closeModal() {
        byId('dsModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    function openImport() {
        byId('importModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        byId('importResult').classList.add('hidden');
    }

    function closeImport() {
        byId('importModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    function renderSpecFields(resetValues, values) {
        const category = byId('dsCategory').value;
        const config = DATASHEET_FIELDS[category] || { fields: [] };
        const target = byId('dsSpecFields');
        const required = REQUIRED_SPECS[category] || [];

        target.innerHTML = config.fields.map((field) => {
            const value = !resetValues && values ? (values[field.key] ?? '') : '';
            const isRequired = required.includes(field.key);
            const step = field.type === 'int' ? '1' : '0.01';
            const inputType = field.type === 'text' ? 'text' : 'number';
            return `
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        ${escapeHtml(field.label)}${isRequired ? ' *' : ''}
                        ${field.unit ? `<span class="text-gray-400 font-normal">(${escapeHtml(field.unit)})</span>` : ''}
                    </label>
                    <input type="${inputType}" data-spec="${escapeHtml(field.key)}"
                        ${inputType === 'number' ? `min="0" step="${step}"` : 'maxlength="60"'}
                        value="${escapeHtml(String(value))}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>`;
        }).join('');
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[char]));
    }

    function collectSpecs() {
        const specs = {};
        document.querySelectorAll('#dsSpecFields [data-spec]').forEach((input) => {
            const value = input.value.trim();
            specs[input.dataset.spec] = value === '' ? null : value;
        });
        return specs;
    }

    async function readJson(response) {
        const text = await response.text();
        if (!text.trim()) {
            throw new Error('The server returned an empty response (HTTP ' + response.status + ').');
        }
        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error('The server returned an unexpected response (HTTP ' + response.status + ').');
        }
    }

    async function lookupDatasheet() {
        clearFieldError();
        const category = byId('dsCategory').value;
        const brand = byId('dsBrand').value.trim();
        const model = byId('dsModel').value.trim();
        if (!brand || !model) {
            fieldError('Enter the brand and model, then run the lookup.');
            return;
        }

        const button = byId('dsLookupBtn');
        const note = byId('dsLookupNote');
        button.disabled = true;
        button.textContent = 'Searching...';
        note.classList.remove('hidden');
        note.textContent = 'Searching the web and reading datasheet pages. This can take up to a minute.';

        try {
            const response = await fetch('../../api/products/lookup-datasheet.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ category, brand, model })
            });
            const result = await readJson(response);
            if (!response.ok || !result.success) {
                throw new Error(result.message || ('Lookup failed with HTTP ' + response.status + '.'));
            }

            const draft = result.draft || {};
            const specs = draft.specs || {};
            byId('dsBrand').value = draft.brand || brand;
            byId('dsModel').value = draft.model || model;
            byId('dsTitle').value = draft.title || '';
            byId('dsNotes').value = draft.notes || '';
            byId('dsSourceUrl').value = draft.source_url || (result.sources || [])[0] || '';
            byId('dsStatus').value = 'draft';
            renderSpecFields(false, specs);

            const bits = [];
            bits.push(result.used_web ? 'Web sources read.' : 'No page text available; model knowledge only.');
            if (typeof result.confidence === 'number') {
                bits.push('Confidence ' + Math.round(result.confidence * 100) + '%.');
            }
            if (result.missing && result.missing.length) {
                bits.push('Still missing: ' + result.missing.join(', ') + '.');
            }
            if (result.sources && result.sources.length) {
                bits.push('Sources: ' + result.sources.slice(0, 3).map((url) =>
                    '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" class="underline">' + escapeHtml(url) + '</a>'
                ).join(' | '));
            }
            bits.push('Saved as a draft. Verify each value against the real datasheet.');
            note.innerHTML = bits.join(' ');
        } catch (error) {
            note.classList.add('hidden');
            fieldError(error.message || 'The lookup failed. Try again or enter the specs manually.');
        } finally {
            button.disabled = false;
            button.textContent = 'Look up online';
        }
    }

    async function saveDatasheet(event) {
        event.preventDefault();
        clearFieldError();

        const id = Number(byId('dsId').value || 0);
        const category = byId('dsCategory').value;
        const brand = byId('dsBrand').value.trim();
        const model = byId('dsModel').value.trim();

        if (!brand || !model) {
            fieldError('Brand and model are required.');
            return;
        }

        const required = REQUIRED_SPECS[category] || [];
        const specs = collectSpecs();
        const missing = required.filter((key) => specs[key] === null || specs[key] === '');
        if (missing.length) {
            fieldError('Fill the required specifications before saving.');
            return;
        }

        const payload = Object.assign({
            id,
            category,
            brand,
            model,
            title: byId('dsTitle').value.trim(),
            status: byId('dsStatus').value,
            notes: byId('dsNotes').value.trim(),
            source_url: byId('dsSourceUrl').value.trim()
        }, specs);

        const button = byId('dsSaveBtn');
        button.disabled = true;
        button.textContent = 'Saving...';

        try {
            const response = await fetch('../../api/products/save-datasheet.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await readJson(response);
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Could not save the datasheet.');
            }
            window.location.href = 'equipment-datasheets.php?saved=1';
        } catch (error) {
            fieldError(error.message || 'Could not save the datasheet.');
            button.disabled = false;
            button.textContent = 'Save datasheet';
        }
    }

    async function deleteDatasheet(id) {
        if (!window.confirm('Delete this datasheet? The System Designer will stop offering it.')) {
            return;
        }
        try {
            const response = await fetch('../../api/products/delete-datasheet.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ id })
            });
            const result = await readJson(response);
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Could not delete the datasheet.');
            }
            window.location.href = 'equipment-datasheets.php?deleted=1';
        } catch (error) {
            notice(error.message || 'Could not delete the datasheet.', 'error');
        }
    }

    async function importDatasheets(event) {
        event.preventDefault();
        const form = byId('importForm');
        const resultBox = byId('importResult');
        const button = byId('importBtn');
        const file = form.querySelector('input[type="file"]');

        if (!file.files.length) {
            resultBox.className = 'text-sm rounded-lg border px-3 py-2 bg-red-50 border-red-200 text-red-800';
            resultBox.textContent = 'Choose a CSV file first.';
            resultBox.classList.remove('hidden');
            return;
        }

        button.disabled = true;
        button.textContent = 'Importing...';
        resultBox.classList.add('hidden');

        try {
            const response = await fetch('../../api/products/import-datasheets.php', {
                method: 'POST',
                body: new FormData(form)
            });
            const result = await readJson(response);
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'The import failed.');
            }

            resultBox.className = 'text-sm rounded-lg border px-3 py-2 bg-green-50 border-green-200 text-green-800';
            resultBox.innerHTML = '<p class="font-semibold">' + escapeHtml(result.message) + '</p>' +
                (result.errors && result.errors.length
                    ? '<ul class="list-disc list-inside mt-1">' + result.errors.map((row) => '<li>' + escapeHtml(row) + '</li>').join('') + '</ul>'
                    : '');
            resultBox.classList.remove('hidden');
            setTimeout(() => window.location.reload(), 1500);
        } catch (error) {
            resultBox.className = 'text-sm rounded-lg border px-3 py-2 bg-red-50 border-red-200 text-red-800';
            resultBox.textContent = error.message || 'The import failed.';
            resultBox.classList.remove('hidden');
        } finally {
            button.disabled = false;
            button.textContent = 'Import CSV';
        }
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
            closeImport();
        }
    });

    <?php if (isset($_GET['saved'])): ?>
    notice('Datasheet saved successfully.', 'success');
    <?php elseif (isset($_GET['deleted'])): ?>
    notice('Datasheet deleted.', 'success');
    <?php endif; ?>
</script>

<?php include '../../includes/footer.php'; ?>
