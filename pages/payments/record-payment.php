<?php
// pages/payments/record-payment.php
include '../../includes/session-check.php';
requirePermission('create_payment');

$pageTitle = 'Record Payment';

// Check for pre-fill data from Invoice
$prefill = [];
if (isset($_GET['invoice_id'])) {
    $stmt = $pdo->prepare("SELECT id, customer_id, balance_due FROM invoices WHERE id = ? AND status = 'finalized'");
    $stmt->execute([$_GET['invoice_id']]);
    $inv = $stmt->fetch();
    if ($inv) {
        $prefill = [
            'customer_id' => $inv['customer_id'],
            'invoice_id' => $inv['id'],
            'amount' => $inv['balance_due']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment - 1100ERP</title>
    <link href="../../assets/css/style.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-50 text-gray-900">
    <?php include '../../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="max-w-5xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Record Payment</h1>
                    <p class="text-gray-600 mt-1">Record a payment from a customer and allocate it to invoices.</p>
                </div>
                <a href="manage-payments.php" class="text-gray-600 hover:text-gray-900 flex items-center gap-2">
                    ← Back to Payments
                </a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Payment Details (Left Column) -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold mb-4 text-gray-800 border-b pb-2">Payment Details</h2>
                        <form id="paymentForm" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                                <select id="customer" name="customer_id" placeholder="Select customer..."
                                    autocomplete="off">
                                    <option value="">Select a customer...</option>
                                    <?php
                                    $stmt = $pdo->query("SELECT id, customer_name FROM customers WHERE is_active = 1 ORDER BY customer_name ASC");
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='{$row['id']}'>{$row['customer_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Credit Balance Display -->
                            <div id="creditBox"
                                class="hidden bg-blue-50 border border-blue-200 rounded p-3 text-sm text-blue-800">
                                <div class="flex justify-between items-center">
                                    <span>Available Credit:</span>
                                    <span class="font-bold text-lg" id="creditAmount">₦0.00</span>
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <input type="checkbox" id="useCredit"
                                        class="rounded text-blue-600 focus:ring-blue-500">
                                    <label for="useCredit" class="font-medium cursor-pointer">Use Credit
                                        Balance</label>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Amount
                                    (₦)</label>
                                <input type="number" id="amount" name="amount" step="0.01" min="0"
                                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-lg font-bold p-3"
                                    placeholder="0.00">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>"
                                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                                <select name="payment_method"
                                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="POS">POS</option>
                                    <option value="Online">Online</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Reference
                                    (Optional)</label>
                                <input type="text" name="reference" placeholder="Check No., Trasaction ID, etc."
                                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                <textarea name="notes" rows="2"
                                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>
                        </form>
                    </div>

                    <!-- Summary Card -->
                    <div class="bg-gray-800 text-white rounded-lg shadow-md p-6">
                        <div class="space-y-3">
                            <div class="flex justify-between text-gray-300">
                                <span>Payment Amount:</span>
                                <span class="font-mono" id="summaryTotal">₦0.00</span>
                            </div>
                            <div class="flex justify-between text-yellow-400">
                                <span>Allocated:</span>
                                <span class="font-mono" id="summaryAllocated">₦0.00</span>
                            </div>
                            <div class="border-t border-gray-600 pt-3 flex justify-between font-bold text-xl">
                                <span>Remaining:</span>
                                <span class="font-mono" id="summaryRemaining">₦0.00</span>
                            </div>
                        </div>
                        <p id="creditNote" class="text-xs text-gray-400 mt-3 text-center hidden">
                            * Remaining funds will auto-pay oldest invoices first, then add to Credit.
                        </p>
                        <button type="button" onclick="savePayment()"
                            class="w-full mt-6 bg-green-600 hover:bg-green-500 text-white font-bold py-3 px-4 rounded-lg shadow transition-colors">
                            Valid & Save Payment
                        </button>
                    </div>
                </div>

                <!-- Invoices List (Right Column) -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-md p-6 h-full">
                        <div class="flex justify-between items-center mb-4 border-b pb-2">
                            <h2 class="text-xl font-bold text-gray-800">Unpaid Invoices</h2>
                            <button type="button" onclick="autoAllocate()"
                                class="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded hover:bg-blue-200 font-semibold">
                                ⚡ Auto Allocate
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="text-left text-gray-500 text-sm border-b">
                                        <th class="pb-2">Invoice #</th>
                                        <th class="pb-2">Date</th>
                                        <th class="pb-2 text-right">Total</th>
                                        <th class="pb-2 text-right">Due</th>
                                        <th class="pb-2 w-32 text-right">Allocate</th>
                                    </tr>
                                </thead>
                                <tbody id="invoicesTableBody">
                                    <tr>
                                        <td colspan="5" class="text-center py-8 text-gray-400 italic">
                                            Select a customer to view unpaid invoices.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script>
        // Init Tom Select
        var tomSelectInstance = new TomSelect("#customer", {
            create: false,
            sortField: { field: "text", direction: "asc" },
            onChange: function (value) {
                if (value) loadCustomerData(value);
            }
        });

        // Handle Pre-fill
        const prefillData = <?php echo json_encode($prefill); ?>;

        if (prefillData.customer_id) {
            // Set customer silently to avoid double triggering if we call load manually
            tomSelectInstance.setValue(prefillData.customer_id, true);

            // Load data and then pre-fill allocation
            loadCustomerData(prefillData.customer_id).then(() => {
                if (prefillData.invoice_id) {
                    // Set main amount
                    document.getElementById('amount').value = parseFloat(prefillData.amount).toFixed(2);

                    // Find and set allocation
                    const input = document.querySelector(`.allocation-input[data-id="${prefillData.invoice_id}"]`);
                    if (input) {
                        input.value = parseFloat(prefillData.amount).toFixed(2);
                        updateSummary();

                        // Highlight the row
                        input.closest('tr').classList.add('bg-blue-50');
                    }
                }
            });
        }

        let currentInvoices = [];
        let customerCredit = 0;

        async function loadCustomerData(customerId) {
            const tbody = document.getElementById('invoicesTableBody');
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Loading...</td></tr>';

            try {
                const response = await fetch(`../../api/customers/get-unpaid-invoices.php?customer_id=${customerId}`);
                const data = await response.json();

                if (data.success) {
                    currentInvoices = data.invoices;
                    customerCredit = data.credit_balance;
                    renderInvoices();
                    updateCreditDisplay();
                } else {
                    alert('Error loading invoices');
                }
            } catch (e) {
                console.error(e);
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-red-500">Failed to load data</td></tr>';
            }
        }

        function renderInvoices() {
            const tbody = document.getElementById('invoicesTableBody');
            if (currentInvoices.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-gray-500">No unpaid invoices found for this customer.</td></tr>';
                return;
            }

            tbody.innerHTML = currentInvoices.map(inv => `
                <tr class="border-b last:border-0 hover:bg-gray-50 transition-colors">
                    <td class="py-3 font-medium text-gray-800">${inv.invoice_number}</td>
                    <td class="py-3 text-sm text-gray-600">${new Date(inv.invoice_date).toLocaleDateString('en-GB')}</td>
                    <td class="py-3 text-right text-sm">₦${parseFloat(inv.grand_total).toLocaleString()}</td>
                    <td class="py-3 text-right font-bold text-red-600">₦${parseFloat(inv.balance_due).toLocaleString()}</td>
                    <td class="py-3 text-right">
                        <input type="number" 
                               data-id="${inv.id}" 
                               data-max="${inv.balance_due}"
                               class="allocation-input w-28 text-right p-1 border rounded focus:ring-blue-500 focus:border-blue-500 bg-gray-50"
                               placeholder="0.00"
                               oninput="updateSummary()">
                    </td>
                </tr>
            `).join('');
        }

        function updateCreditDisplay() {
            const box = document.getElementById('creditBox');
            const amount = document.getElementById('creditAmount');
            const note = document.getElementById('creditNote');

            amount.innerText = '₦' + customerCredit.toLocaleString(undefined, { minimumFractionDigits: 2 });

            if (customerCredit > 0) {
                box.classList.remove('hidden');
                document.getElementById('useCredit').checked = false; // Reset checkbox
            } else {
                box.classList.add('hidden');
            }
            note.classList.remove('hidden'); // Always show note explaining remaining goes to credit
        }

        function autoAllocate() {
            const total = parseFloat(document.getElementById('amount').value) || 0;
            if (total <= 0) return;

            let remaining = total;
            const inputs = document.querySelectorAll('.allocation-input');

            inputs.forEach(input => {
                if (remaining <= 0) {
                    input.value = '';
                    return;
                }
                const max = parseFloat(input.dataset.max);
                const alloc = Math.min(remaining, max);
                input.value = alloc.toFixed(2);
                remaining -= alloc;
            });
            updateSummary();
        }

        function updateSummary() {
            const paymentInput = parseFloat(document.getElementById('amount').value) || 0;
            let allocated = 0;

            document.querySelectorAll('.allocation-input').forEach(input => {
                allocated += parseFloat(input.value) || 0;
            });

            const remaining = paymentInput - allocated;

            document.getElementById('summaryTotal').innerText = '₦' + paymentInput.toLocaleString(undefined, { minimumFractionDigits: 2 });
            document.getElementById('summaryAllocated').innerText = '₦' + allocated.toLocaleString(undefined, { minimumFractionDigits: 2 });
            document.getElementById('summaryRemaining').innerText = '₦' + remaining.toLocaleString(undefined, { minimumFractionDigits: 2 });

            // Styling for valid/invalid state
            const rEl = document.getElementById('summaryRemaining').parentElement;
            if (remaining < 0) {
                rEl.classList.add('text-red-500');
                rEl.classList.remove('text-white'); // or whatever base color
            } else {
                rEl.classList.remove('text-red-500');
                // rEl.classList.add('text-green-400');
            }
        }

        // Listen for main amount changes
        document.getElementById('amount').addEventListener('input', updateSummary);

        // Handle "Use Credit" toggle
        document.getElementById('useCredit').addEventListener('change', function (e) {
            const amountInput = document.getElementById('amount');
            if (e.target.checked) {
                amountInput.value = customerCredit.toFixed(2);
                amountInput.readOnly = true; // Lock input to credit amount mostly? Or allow partial? 
                // Currently implementing: Set amount to credit balance (or up to needed?)
                // Let's just set it to credit balance for simplicity, user can reduce it.
            } else {
                amountInput.readOnly = false;
                amountInput.value = '';
            }
            updateSummary();
        });

        async function savePayment() {
            // Helper for alerts
            const showMessage = (title, text, icon) => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire(title, text, icon);
                } else {
                    alert(`${title}: ${text}`);
                }
            };

            // Confirm function exists
            console.log('savePayment called');

            try {
                const customerId = document.getElementById('customer').value;
                const amount = parseFloat(document.getElementById('amount').value) || 0;

                console.log('Customer:', customerId, 'Amount:', amount);

                if (!customerId) {
                    showMessage('Error', 'Please select a customer', 'error');
                    return;
                }
                if (amount <= 0) {
                    showMessage('Error', 'Please enter a valid amount greater than 0', 'error');
                    return;
                }

                // Gather allocations
                const allocations = [];
                let totalAllocated = 0;
                document.querySelectorAll('.allocation-input').forEach(input => {
                    const val = parseFloat(input.value) || 0;
                    if (val > 0) {
                        allocations.push({
                            invoice_id: input.dataset.id,
                            amount: val
                        });
                        totalAllocated += val;
                    }
                });

                if (totalAllocated > amount + 0.01) { // Floating point tolerance
                    showMessage('Error', 'Allocated amount cannot exceed payment amount', 'error');
                    return;
                }

                const formData = {
                    customer_id: customerId,
                    amount: amount,
                    payment_date: document.querySelector('input[name="payment_date"]').value,
                    payment_method: document.querySelector('select[name="payment_method"]').value,
                    reference: document.querySelector('input[name="reference"]').value,
                    notes: document.querySelector('textarea[name="notes"]').value,
                    use_credit: document.getElementById('useCredit')?.checked || false,
                    allocations: allocations
                };

                // Disable button
                const btn = document.querySelector('button[onclick="savePayment()"]');
                const originalText = btn.innerText;
                btn.disabled = true;
                btn.innerText = 'Processing...';

                try {
                    const res = await fetch('../../api/payments/save-payment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(formData)
                    });

                    // Check for non-JSON response (e.g. PHP Fatal Error HTML)
                    const text = await res.text();
                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch (e) {
                        console.error('Server response:', text);
                        throw new Error('Server returned invalid JSON. See console.');
                    }

                    if (result.success) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Payment recorded successfully',
                                icon: 'success'
                            }).then(() => {
                                handleSuccessRedirect();
                            });
                        } else {
                            alert('Payment recorded successfully');
                            handleSuccessRedirect();
                        }
                    } else {
                        showMessage('Error', result.message || 'Failed to save payment', 'error');
                        btn.disabled = false;
                        btn.innerText = originalText;
                    }
                } catch (e) {
                    console.error(e);
                    showMessage('Error', 'Network error or Server Error: ' + e.message, 'error');
                    btn.disabled = false;
                    btn.innerText = originalText;
                }
            } catch (err) {
                console.error('Critical JS Error:', err);
                alert('Critical Error: ' + err.message);
            }
        }

        function handleSuccessRedirect() {
            if (prefillData.invoice_id) {
                // Check if Swal is available for confirm dialog
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Payment Recorded',
                        text: 'What would you like to do next?',
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: 'View Invoice',
                        cancelButtonText: 'New Payment'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '../../pages/view-invoice.php?id=' + prefillData.invoice_id;
                        } else {
                            window.location.href = 'record-payment.php';
                        }
                    });
                } else {
                    if (confirm('Payment Recorded. View Invoice? (Cancel for New Payment)')) {
                        window.location.href = '../../pages/view-invoice.php?id=' + prefillData.invoice_id;
                    } else {
                        window.location.href = 'record-payment.php';
                    }
                }
            } else {
                window.location.reload();
            }
        }
    </script>
</body>

</html>