// Updated Bulk Actions JavaScript for Archive System
// Changed from "Delete" to "Archive" terminology

let selectedItems = new Set();
let documentType = '';

function initBulkActions(type) {
    documentType = type;
    updateBulkActionBar();
}

function toggleCheckbox(id, event) {
    event.stopPropagation();

    if (event.target.checked) {
        selectedItems.add(id);
    } else {
        selectedItems.delete(id);
    }

    updateSelectAllCheckbox();
    updateBulkActionBar();
}

function toggleSelectAll(event) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const isChecked = event.target.checked;

    checkboxes.forEach(checkbox => {
        checkbox.checked = isChecked;
        const id = parseInt(checkbox.value);
        if (isChecked) {
            selectedItems.add(id);
        } else {
            selectedItems.delete(id);
        }
    });

    updateBulkActionBar();
}

function updateSelectAllCheckbox() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.item-checkbox');

    if (checkboxes.length === 0) return;

    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    const someChecked = Array.from(checkboxes).some(cb => cb.checked);

    selectAllCheckbox.checked = allChecked;
    selectAllCheckbox.indeterminate = someChecked && !allChecked;
}

function updateBulkActionBar() {
    const bulkBar = document.getElementById('bulkActionBar');
    const selectedCount = document.getElementById('selectedCount');

    if (selectedItems.size > 0) {
        bulkBar.classList.remove('hidden');
        selectedCount.textContent = selectedItems.size;
    } else {
        bulkBar.classList.add('hidden');
    }
}

function deselectAll() {
    selectedItems.clear();
    document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateBulkActionBar();
}

async function bulkDownloadPDFs() {
    if (selectedItems.size === 0) {
        alert('Please select items to download');
        return;
    }

    const ids = Array.from(selectedItems);
    const downloadBtn = document.getElementById('bulkDownloadBtn');
    const originalText = downloadBtn.innerHTML;
    downloadBtn.innerHTML = '<svg class="w-5 h-5 inline animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Preparing...';
    downloadBtn.disabled = true;

    try {
        postApiAction('../api/bulk-download-pdf.php', {
            document_type: documentType,
            ids: JSON.stringify(ids)
        }, '_blank');

        setTimeout(() => {
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
        }, 2000);

    } catch (error) {
        alert('Error downloading PDFs: ' + error.message);
        downloadBtn.innerHTML = originalText;
        downloadBtn.disabled = false;
    }
}

// CHANGED: Delete → Archive
function showBulkArchiveConfirmation() {
    if (selectedItems.size === 0) {
        alert('Please select items to archive');
        return;
    }

    const modal = document.getElementById('archiveConfirmModal');
    const countSpan = document.getElementById('archiveCount');
    const confirmInput = document.getElementById('archiveConfirmInput');
    const confirmBtn = document.getElementById('confirmArchiveBtn');

    countSpan.textContent = selectedItems.size;
    confirmInput.value = '';
    confirmBtn.disabled = true;

    modal.classList.remove('hidden');
}

function hideArchiveConfirmation() {
    document.getElementById('archiveConfirmModal').classList.add('hidden');
}

function validateArchiveConfirmation() {
    const input = document.getElementById('archiveConfirmInput');
    const btn = document.getElementById('confirmArchiveBtn');

    btn.disabled = input.value !== 'ARCHIVE';
}

async function executeBulkArchive() {
    const ids = Array.from(selectedItems);
    const confirmBtn = document.getElementById('confirmArchiveBtn');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<svg class="w-5 h-5 inline animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Archiving...';
    confirmBtn.disabled = true;

    try {
        const response = await fetch('../api/bulk-delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                document_type: documentType,
                ids: ids
            })
        });

        const result = await response.json();

        if (result.success) {
            hideArchiveConfirmation();
            alert(`Successfully archived ${result.deleted_count} items`);
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        }

    } catch (error) {
        alert('Error archiving items: ' + error.message);
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
    }
}

// For archive pages - restore functionality
async function bulkRestore() {
    if (selectedItems.size === 0) {
        alert('Please select items to restore');
        return;
    }

    if (!confirm(`Restore ${selectedItems.size} item(s) back to active list?`)) {
        return;
    }

    const ids = Array.from(selectedItems);

    try {
        const response = await fetch('../api/bulk-restore.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                document_type: documentType,
                ids: ids
            })
        });

        const result = await response.json();

        if (result.success) {
            alert(`Successfully restored ${result.restored_count} items`);
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
        }

    } catch (error) {
        alert('Error restoring items: ' + error.message);
    }
}

// For archive pages - permanent delete (admin only)
function showBulkPermanentDeleteConfirmation() {
    if (selectedItems.size === 0) {
        alert('Please select items to permanently delete');
        return;
    }

    const modal = document.getElementById('permanentDeleteModal');
    const countSpan = document.getElementById('permanentDeleteCount');
    const confirmInput = document.getElementById('permanentDeleteInput');
    const confirmBtn = document.getElementById('confirmPermanentDeleteBtn');

    countSpan.textContent = selectedItems.size;
    confirmInput.value = '';
    confirmBtn.disabled = true;

    modal.classList.remove('hidden');
}

function hidePermanentDeleteConfirmation() {
    document.getElementById('permanentDeleteModal').classList.add('hidden');
}

function validatePermanentDeleteConfirmation() {
    const input = document.getElementById('permanentDeleteInput');
    const btn = document.getElementById('confirmPermanentDeleteBtn');

    btn.disabled = input.value !== 'PERMANENT DELETE';
}

async function executeBulkPermanentDelete() {
    const ids = Array.from(selectedItems);
    const confirmBtn = document.getElementById('confirmPermanentDeleteBtn');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<svg class="w-5 h-5 inline animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Deleting...';
    confirmBtn.disabled = true;

    try {
        const response = await fetch('../api/permanent-delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                document_type: documentType,
                ids: ids
            })
        });

        const result = await response.json();

        if (result.success) {
            hidePermanentDeleteConfirmation();
            alert(`Successfully deleted ${result.deleted_count} items permanently`);
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        }

    } catch (error) {
        alert('Error deleting items: ' + error.message);
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
    }
}
