// Line item counter
window.lineItemCount = 0;

// Add initial line item on page load (only if no existing items AND not from template)
document.addEventListener('DOMContentLoaded', function () {
    // Check if we're loading from template or have existing items
    const isFromTemplate = window.location.search.includes('from_template=1');
    const hasExistingItems = typeof existingLineItems !== 'undefined' && existingLineItems.length > 0;

    // Only add initial blank line if NOT loading template and NO existing items
    if (!isFromTemplate && !hasExistingItems) {
        addLineItem();
    }
});

// Add line item button
document.getElementById('addLineBtn').addEventListener('click', addLineItem);

function addLineItem() {
    window.lineItemCount++;
    const currentCount = window.lineItemCount;

    const container = document.getElementById('lineItemsContainer');
    const row = document.createElement('tr');
    row.className = 'border-b border-gray-200 hover:bg-gray-50';
    row.id = `line-${currentCount}`;

    row.innerHTML = `
        <td class="px-3 py-2 text-center font-semibold text-gray-700">${currentCount}</td>
        <td class="px-3 py-2">
            <input 
                type="number" 
                name="line_items[${currentCount}][quantity]"
                min="0.01"
                step="0.01"
                value="1"
                required
                class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-primary"
                onchange="calculateLine(${currentCount})"
            >
        </td>
        <td class="px-3 py-2">
            <textarea 
                name="line_items[${currentCount}][description]"
                rows="2"
                required
                placeholder="Enter item description"
                class="w-full min-w-[200px] px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-primary resize-none"
            ></textarea>
        </td>
        <td class="px-3 py-2">
            <input 
                type="number" 
                name="line_items[${currentCount}][unit_price]"
                min="0"
                step="0.01"
                required
                placeholder="0.00"
                class="w-full min-w-[120px] px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-primary"
                onchange="calculateLine(${currentCount})"
            >
        </td>
        <td class="px-3 py-2 text-center">
            <input 
                type="checkbox" 
                name="line_items[${currentCount}][vat_applicable]"
                value="1"
                class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary"
                onchange="calculateLine(${currentCount})"
            >
        </td>
        <td class="px-3 py-2 text-right">
            <span id="lineTotal-${currentCount}" class="font-bold text-gray-900">₦0.00</span>
            <input type="hidden" name="line_items[${currentCount}][line_total]" id="lineTotalInput-${currentCount}">
            <input type="hidden" name="line_items[${currentCount}][vat_amount]" id="vatAmountInput-${currentCount}">
        </td>
        <td class="px-3 py-2 text-center">
            <button 
                type="button" 
                onclick="removeLine(${currentCount})"
                class="text-red-500 hover:text-red-700"
                title="Remove line"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </td>
    `;

    container.appendChild(row);
    return row;
}

function removeLine(lineNumber) {
    const row = document.getElementById(`line-${lineNumber}`);
    if (row) {
        row.remove();
        calculateTotals();
        renumberLines();
    }
}

function renumberLines() {
    const rows = document.querySelectorAll('#lineItemsContainer tr');
    rows.forEach((row, index) => {
        const itemNumber = index + 1;
        const firstCell = row.querySelector('td:first-child');
        if (firstCell) {
            firstCell.textContent = itemNumber;
        }
    });
}

function calculateLine(lineNumber) {
    const row = document.getElementById(`line-${lineNumber}`);
    if (!row) return;

    // Get values
    const quantity = parseFloat(row.querySelector('[name*="[quantity]"]').value) || 0;
    const unitPrice = parseFloat(row.querySelector('[name*="[unit_price]"]').value) || 0;
    const vatEnabled = row.querySelector('[name*="[vat_applicable]"]').checked;

    // Calculate
    const baseAmount = quantity * unitPrice;
    const vatAmount = vatEnabled ? (baseAmount * 0.075) : 0;
    const lineTotal = baseAmount + vatAmount;

    // Update display
    document.getElementById(`lineTotal-${lineNumber}`).textContent = formatCurrency(lineTotal);

    // Update hidden inputs
    document.getElementById(`lineTotalInput-${lineNumber}`).value = lineTotal.toFixed(2);
    document.getElementById(`vatAmountInput-${lineNumber}`).value = vatAmount.toFixed(2);

    // Recalculate totals
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    let totalVAT = 0;

    // Sum all line items
    const rows = document.querySelectorAll('#lineItemsContainer tr');
    rows.forEach(row => {
        const quantity = parseFloat(row.querySelector('[name*="[quantity]"]')?.value) || 0;
        const unitPrice = parseFloat(row.querySelector('[name*="[unit_price]"]')?.value) || 0;
        const vatEnabled = row.querySelector('[name*="[vat_applicable]"]')?.checked || false;

        const baseAmount = quantity * unitPrice;
        const vatAmount = vatEnabled ? (baseAmount * 0.075) : 0;

        subtotal += baseAmount;
        totalVAT += vatAmount;
    });

    const grandTotal = subtotal + totalVAT;

    // Update display
    document.getElementById('subtotalDisplay').textContent = formatCurrency(subtotal);
    document.getElementById('vatDisplay').textContent = formatCurrency(totalVAT);
    document.getElementById('grandTotalDisplay').textContent = formatCurrency(grandTotal);

    // Update hidden inputs for form submission
    document.getElementById('subtotalInput').value = subtotal.toFixed(2);
    document.getElementById('vatInput').value = totalVAT.toFixed(2);
    document.getElementById('grandTotalInput').value = grandTotal.toFixed(2);
}

function formatCurrency(amount) {
    return '₦' + amount.toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Form submission validation
document.getElementById('quoteForm').addEventListener('submit', function (e) {
    const lineItems = document.querySelectorAll('#lineItemsContainer tr');

    if (lineItems.length === 0) {
        e.preventDefault();
        alert('Please add at least one line item.');
        return false;
    }

    // Validate each line has required data
    let isValid = true;
    lineItems.forEach(row => {
        const qty = row.querySelector('[name*="[quantity]"]').value;
        const desc = row.querySelector('[name*="[description]"]').value;
        const price = row.querySelector('[name*="[unit_price]"]').value;

        if (!qty || !desc || !price) {
            isValid = false;
        }
    });

    if (!isValid) {
        e.preventDefault();
        alert('Please fill in all line item fields.');
        return false;
    }
});
