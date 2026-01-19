<?php
include '../includes/session-check.php';

$pageTitle = 'Readymade Quotes - Bluedots Technologies';

// Fetch all readymade quotes
$stmt = $pdo->query("
    SELECT 
        qt.*,
        COUNT(qti.id) as item_count
    FROM readymade_quote_templates qt
    LEFT JOIN readymade_quote_template_items qti ON qt.id = qti.template_id
    WHERE qt.is_active = 1
    GROUP BY qt.id
    ORDER BY qt.created_at DESC
");

$templates = $stmt->fetchAll();

include '../includes/header.php';
?>

<?php if (isset($_GET['duplicated'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ Template duplicated successfully!</p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-800 font-semibold">Error: <?php echo htmlspecialchars($_GET['error']); ?></p>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md p-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Readymade Quotes</h2>
            <p class="text-sm text-gray-600 mt-1">Pre-designed quote templates for quick quote creation</p>
        </div>
        <a href="create-readymade-quote.php"
            class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Create Readymade Quote
        </a>
    </div>

    <?php if (empty($templates)): ?>
        <div class="text-center py-12">
            <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                </path>
            </svg>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No readymade quotes yet</h3>
            <p class="text-gray-500 mb-6">Create your first template to speed up quote creation</p>
            <a href="create-readymade-quote.php"
                class="inline-flex items-center px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                Create Your First Template
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($templates as $template): ?>
                <div class="border border-gray-200 rounded-lg p-6 hover:shadow-lg transition-shadow">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">
                                <?php echo htmlspecialchars($template['template_name']); ?>
                            </h3>
                            <?php if ($template['description']): ?>
                                <p class="text-sm text-gray-600 mb-3">
                                    <?php echo htmlspecialchars(substr($template['description'], 0, 100)); ?>
                                    <?php echo strlen($template['description']) > 100 ? '...' : ''; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full">
                            Template
                        </span>
                    </div>

                    <div class="border-t border-gray-200 pt-4 mb-4">
                        <div class="flex justify-between items-center text-sm mb-2">
                            <span class="text-gray-600">Items:</span>
                            <span class="font-semibold text-gray-900"><?php echo $template['item_count']; ?></span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-600">Est. Total:</span>
                            <span class="font-bold text-primary"><?php echo formatNaira($template['grand_total']); ?></span>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <a href="../api/use-readymade-quote.php?id=<?php echo $template['id']; ?>"
                            class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold text-center text-sm">
                            Use Template
                        </a>
                        <a href="edit-readymade-quote.php?id=<?php echo $template['id']; ?>"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-semibold text-sm">
                            Edit
                        </a>
                        <a href="../api/duplicate-readymade-quote.php?id=<?php echo $template['id']; ?>"
                            class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 font-semibold text-sm"
                            onclick="return confirm('Create a copy of this template?')">
                            Duplicate
                        </a>
                        <button
                            class="delete-template-btn px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 font-semibold text-sm"
                            data-id="<?php echo $template['id']; ?>"
                            data-name="<?php echo htmlspecialchars($template['template_name'], ENT_QUOTES); ?>">
                            Delete
                        </button>
                    </div>

                    <div class="mt-3 text-xs text-gray-500">
                        Created: <?php echo date('d/m/Y', strtotime($template['created_at'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Delete template using event delegation
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('delete-template-btn')) {
            const id = e.target.dataset.id;
            const name = e.target.dataset.name;

            if (confirm('Delete readymade quote "' + name + '"?\n\nThis action cannot be undone.')) {
                window.location.href = '../api/delete-readymade-quote.php?id=' + id;
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>