<?php
include '../../includes/session-check.php';
requirePermission('manage_leads');

$pageTitle = 'Leads & Follow-up - ERP System';
include '../../includes/header.php';

// Filters
$statusFilter = $_GET['status'] ?? '';
$where = '';
$params = [];
if ($statusFilter && in_array($statusFilter, ['new','contacted','qualified','converted','lost'], true)) {
    $where = 'WHERE status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("SELECT * FROM leads $where ORDER BY created_at DESC");
$stmt->execute($params);
$leads = $stmt->fetchAll();
?>
<div class="mb-8 flex items-center justify-between">
    <div>
        <h2 class="text-3xl font-bold text-gray-900">Leads & Follow-up</h2>
        <p class="text-gray-600 mt-1">Capture, nurture, and convert enquiries into customers.</p>
    </div>
    <a href="../../lead-form.php" target="_blank"
        class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold text-sm">
        Open capture form
    </a>
</div>

<?php if (isset($_GET['updated'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ Lead updated successfully!</p>
    </div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-800 text-sm"><?php echo htmlspecialchars($_GET['error']); ?></p>
    </div>
<?php endif; ?>

<!-- Status filter tabs -->
<div class="flex flex-wrap gap-2 mb-6">
    <a href="manage-leads.php"
        class="px-4 py-2 rounded-lg text-sm font-semibold <?php echo $statusFilter==''?'bg-primary text-white':'bg-white text-gray-600 border border-gray-200'; ?>">All</a>
    <?php foreach (['new'=>'New','contacted'=>'Contacted','qualified'=>'Qualified','converted'=>'Converted','lost'=>'Lost'] as $k=>$label): ?>
        <a href="manage-leads.php?status=<?php echo $k; ?>"
            class="px-4 py-2 rounded-lg text-sm font-semibold <?php echo $statusFilter==$k?'bg-primary text-white':'bg-white text-gray-600 border border-gray-200'; ?>"><?php echo $label; ?></a>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Phone</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Source</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Interest</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Next follow-up</th>
                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($leads as $lead): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($lead['name']); ?></div>
                        <?php if ($lead['email']): ?><div class="text-xs text-gray-500"><?php echo htmlspecialchars($lead['email']); ?></div><?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($lead['phone']); ?></td>
                    <td class="px-6 py-4"><span class="px-2 py-1 bg-gray-100 rounded-full text-xs"><?php echo htmlspecialchars($lead['source']); ?></span></td>
                    <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate"><?php echo htmlspecialchars($lead['interest'] ?? ''); ?></td>
                    <td class="px-6 py-4">
                        <?php
                        $colors = ['new'=>'bg-blue-100 text-blue-800','contacted'=>'bg-yellow-100 text-yellow-800','qualified'=>'bg-purple-100 text-purple-800','converted'=>'bg-green-100 text-green-800','lost'=>'bg-red-100 text-red-800'];
                        $c = $colors[$lead['status']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $c; ?>"><?php echo ucfirst($lead['status']); ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm <?php echo ($lead['next_followup_at'] && $lead['next_followup_at'] < date('Y-m-d H:i:s')) ? 'text-red-600 font-semibold' : 'text-gray-500'; ?>">
                        <?php echo $lead['next_followup_at'] ? htmlspecialchars($lead['next_followup_at']) : '—'; ?>
                    </td>
                    <td class="px-6 py-4 text-right text-sm space-x-2">
                        <form method="POST" action="../../api/leads/update-lead.php" class="inline">
                            <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                            <select name="status" onchange="this.form.submit()" class="text-xs border border-gray-300 rounded px-2 py-1">
                                <?php foreach (['new','contacted','qualified','converted','lost'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $lead['status']===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <form method="POST" action="../../api/leads/convert-lead.php" class="inline">
                            <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                            <button type="submit" class="px-3 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200 text-xs font-semibold">Convert</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($leads)): ?>
                <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400">No leads yet. Share your capture form to start collecting.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include '../../includes/footer.php'; ?>
