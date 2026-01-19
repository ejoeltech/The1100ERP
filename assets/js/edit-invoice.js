// Load existing line items into invoice edit form
document.addEventListener('DOMContentLoaded', function () {
    if (typeof existingLineItems !== 'undefined' && existingLineItems.length > 0) {
        existingLineItems.forEach(function (item) {
            lineItemCount++;

            const container = document.getElementById('lineItemsContainer');
            const row = document.createElement('tr');
            row.className = 'border-b border-gray-200 hover:bg-gray-50';
            row.id = `line-${lineItemCount}`;

            row.innerHTML = `
                <td class="px-3 py-2 text-center font-semibold text-gray-700">${lineItemCount}</td>
                <td class="px-3 py-2">
                    <input 
                        type="number" 
                        name="line_items[${lineItemCount}][quantity]"
                        min="0.01"
                        step="0.01"
                        value="${item.quantity}"
                        required
                        class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-primary"
                        onchange="calculateLine(${lineItemCount})"
                    >
                </td>
                <td class="px-3 py-2">
                    <textarea 
                        name="line_items[${lineItemCount}][description]"
                        rows="2"
                        required
                        class="w-full min-w-[200px] px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-primary resize-none"
                    >${item.description}</textarea>
                </td>
                <td class="px-3 py-2">
                    <input 
                        type="number" 
                        name="line_items[${lineItemCount}][unit_price]"
                        min="0"
                        step="0.01"
                        value="${item.unit_price}"
                        required
                        class="w-full min-w-[120px] px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-primary"
                        onchange="calculateLine(${lineItemCount})"
                    >
                </td>
                <td class="px-3 py-2 text-center">
                    <input 
                        type="checkbox" 
                        name="line_items[${lineItemCount}][vat_applicable]"
                        value="1"
                        ${item.vat_applicable ? 'checked' : ''}
                        class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary"
                        onchange="calculateLine(${lineItemCount})"
                    >
                </td>
                <td class="px-3 py-2 text-right">
                    <span id="lineTotal-${lineItemCount}" class="font-bold text-gray-900">₦0.00</span>
                    <input type="hidden" name="line_items[${lineItemCount}][line_total]" id="lineTotalInput-${lineItemCount}">
                    <input type="hidden" name="line_items[${lineItemCount}][vat_amount]" id="vatAmountInput-${lineItemCount}">
                </td>
                <td class="px-3 py-2 text-center">
                    <button 
                        type="button" 
                        onclick="removeLine(${lineItemCount})"
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

            // Calculate line totals
            calculateLine(lineItemCount);
        });

        // Calculate totals after all lines loaded
        calculateTotals();
    }
});
