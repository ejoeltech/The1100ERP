// Load existing line items into form
document.addEventListener('DOMContentLoaded', function () {
    const items = window.existingLineItems || existingLineItems;

    if (typeof items !== 'undefined' && items.length > 0) {
        console.log('Found ' + items.length + ' existing items to load.');

        // Clear any default empty line added by quote-form.js
        const container = document.getElementById('lineItemsContainer');
        if (container) {
            container.innerHTML = '';
            window.lineItemCount = 0;
        }

        items.forEach(function (item, index) {
            try {
                console.log('Loading item ' + (index + 1), item);

                // Use the shared function to add a line
                addLineItem();

                // Get the newly added row ID using the global counter
                const currentCount = window.lineItemCount;
                const row = document.getElementById(`line-${currentCount}`);

                if (row) {
                    // Populate the fields
                    row.querySelector(`[name="line_items[${currentCount}][quantity]"]`).value = item.quantity;
                    row.querySelector(`[name="line_items[${currentCount}][description]"]`).value = item.description;
                    row.querySelector(`[name="line_items[${currentCount}][unit_price]"]`).value = item.unit_price;

                    // Handle checkbox
                    const vatCheckbox = row.querySelector(`[name="line_items[${currentCount}][vat_applicable]"]`);
                    if (vatCheckbox) {
                        vatCheckbox.checked = item.vat_applicable == 1;
                    }

                    // Trigger calculation
                    calculateLine(currentCount);
                } else {
                    console.error('Row not found for count: ' + currentCount);
                }
            } catch (error) {
                console.error('Error loading item ' + (index + 1) + ':', error);
            }
        });

        // Calculate final totals
        calculateTotals();
    }
});
